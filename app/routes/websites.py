import os
import re
import secrets
import shutil
import subprocess
import urllib.parse
from pathlib import Path
from typing import Optional, Tuple
from fastapi import APIRouter, Request, Depends, Form, HTTPException
from fastapi.responses import HTMLResponse, RedirectResponse, JSONResponse
from app.auth import login_required
from app.config import WEB_ROOT
from app.database import get_db, log_action
from app.services.nginx_service import create_vhost, delete_vhost, test_and_reload_nginx, restore_ssl_for_existing_domains
from app.services.ssl_service import issue_ssl, remove_ssl
from app.services.git_service import clone_repository, pull_repository
from app.services.database_service import create_db_and_user, drop_db_and_user
from app.templates_util import render

router = APIRouter(prefix="/websites")

@router.get("", response_class=HTMLResponse)
async def list_websites(
    request: Request,
    error: Optional[str] = None,
    success: Optional[str] = None,
    user: dict = Depends(login_required)
):
    try:
        restore_ssl_for_existing_domains()
    except Exception:
        pass

    conn = get_db()
    cursor = conn.cursor()
    cursor.execute("SELECT * FROM websites ORDER BY id DESC")
    websites = [dict(row) for row in cursor.fetchall()]
    conn.close()

    return render("websites.html", {
        "user": user,
        "websites": websites,
        "active_nav": "websites",
        "error": error,
        "success": success
    }, request)

def setup_laravel_environment(
    site_root: Path,
    db_name: Optional[str] = None,
    db_user: Optional[str] = None,
    db_pass: Optional[str] = None,
    php_version: str = "8.3"
) -> Tuple[bool, str]:
    """Automates Laravel setup: storage/cache dirs, 775 permissions, .env configuration, and artisan commands."""
    msgs = []
    base = site_root.parent if site_root.name == "public" else site_root

    # 1. Ensure storage and bootstrap directories exist
    dirs_to_create = [
        base / "storage" / "app" / "public",
        base / "storage" / "framework" / "cache" / "data",
        base / "storage" / "framework" / "sessions",
        base / "storage" / "framework" / "views",
        base / "storage" / "logs",
        base / "bootstrap" / "cache",
        base / "public"
    ]
    for d in dirs_to_create:
        try:
            d.mkdir(parents=True, exist_ok=True)
        except Exception:
            pass

    # 2. Check .env. If .env missing and .env.example exists, copy it
    env_file = base / ".env"
    env_example = base / ".env.example"
    if not env_file.exists() and env_example.exists():
        try:
            shutil.copyfile(str(env_example), str(env_file))
            msgs.append("Created .env from .env.example")
        except Exception as e:
            msgs.append(f"Failed to copy .env.example: {e}")

    # 3. If db credentials provided and .env exists, inject them
    if env_file.exists() and db_name:
        try:
            content = env_file.read_text(encoding="utf-8", errors="ignore")
            replacements = {
                r'^DB_CONNECTION=.*': 'DB_CONNECTION=mysql',
                r'^DB_HOST=.*': 'DB_HOST=127.0.0.1',
                r'^DB_PORT=.*': 'DB_PORT=3306',
                r'^DB_DATABASE=.*': f'DB_DATABASE={db_name}',
            }
            if db_user:
                replacements[r'^DB_USERNAME=.*'] = f'DB_USERNAME={db_user}'
            if db_pass:
                replacements[r'^DB_PASSWORD=.*'] = f'DB_PASSWORD={db_pass}'

            for pat, repl in replacements.items():
                if re.search(pat, content, flags=re.M):
                    content = re.sub(pat, repl, content, flags=re.M)
                else:
                    content += f"\n{repl}"
            env_file.write_text(content, encoding="utf-8")
            msgs.append("Configured database in .env")
        except Exception as e:
            msgs.append(f"Failed to update .env DB settings: {e}")

    # 4. Permissions: www-data ownership and 775 on storage & cache
    try:
        subprocess.run(["chown", "-R", "www-data:www-data", str(base)], check=False, timeout=15)
        for sub in ["storage", "bootstrap/cache"]:
            sub_path = base / sub
            if sub_path.exists():
                subprocess.run(["chmod", "-R", "775", str(sub_path)], check=False, timeout=15)
        msgs.append("Configured 775 permissions on storage & cache")
    except Exception as e:
        msgs.append(f"Permission error: {e}")

    # 5. Artisan commands if artisan exists
    artisan = base / "artisan"
    if artisan.exists():
        php_bin = f"php{php_version}" if shutil.which(f"php{php_version}") else "php"

        # Check APP_KEY
        if env_file.exists():
            env_content = env_file.read_text(encoding="utf-8", errors="ignore")
            if "APP_KEY=" in env_content and (not re.search(r'^APP_KEY=base64:|^APP_KEY=\S+', env_content, re.M)):
                try:
                    res = subprocess.run([php_bin, "artisan", "key:generate", "--force"], cwd=str(base), capture_output=True, text=True, timeout=15)
                    if res.returncode == 0:
                        msgs.append("Generated APP_KEY")
                except Exception:
                    pass

        # Clear stale cache and link storage
        try:
            subprocess.run([php_bin, "artisan", "config:clear"], cwd=str(base), capture_output=True, timeout=10)
            subprocess.run([php_bin, "artisan", "cache:clear"], cwd=str(base), capture_output=True, timeout=10)
            subprocess.run([php_bin, "artisan", "storage:link"], cwd=str(base), capture_output=True, timeout=10)
            msgs.append("Cleared cache & created storage symlink")
        except Exception:
            pass

    return True, "; ".join(msgs) if msgs else "Laravel setup complete"

def get_client_ip(request: Request) -> str:
    if request.client and request.client.host:
        return request.client.host
    return "127.0.0.1"

@router.post("/create")
async def create_website_action(
    request: Request,
    domain: str = Form(...),
    root_dir: Optional[str] = Form(None),
    php_version: str = Form("8.3"),
    project_type: str = Form("php"),
    git_repo: Optional[str] = Form(None),
    git_branch: Optional[str] = Form(None),
    create_db: Optional[str] = Form(None),
    db_name: Optional[str] = Form(None),
    db_user: Optional[str] = Form(None),
    db_pass: Optional[str] = Form(None),
    user: dict = Depends(login_required)
):
    client_ip = get_client_ip(request)
    try:
        domain = domain.strip().lower()
        project_type = project_type.strip().lower() if project_type else "php"
        if project_type not in ["laravel", "php", "static"]:
            project_type = "php"

        if not domain or " " in domain:
            return RedirectResponse(url=f"/websites?error={urllib.parse.quote('Invalid domain name provided')}", status_code=303)

        if root_dir and root_dir.strip():
            site_root = Path(root_dir.strip())
        else:
            site_root = WEB_ROOT / domain
        site_root.mkdir(parents=True, exist_ok=True)
        
        clean_git_repo = git_repo.strip() if git_repo else ""
        clean_git_branch = git_branch.strip() if git_branch else ""

        if clean_git_repo:
            # If directory has leftover files from a previously deleted site, wipe it clean for fresh clone
            if site_root.exists() and any(site_root.iterdir()):
                try:
                    shutil.rmtree(str(site_root), ignore_errors=True)
                    subprocess.run(["rm", "-rf", str(site_root)], check=False, timeout=15)
                    site_root.mkdir(parents=True, exist_ok=True)
                except Exception:
                    pass

            # Clone repository into document root
            cloned, clone_msg = clone_repository(clean_git_repo, site_root, clean_git_branch, clean_target=True)
            if not cloned:
                # If directory is empty, remove it to keep filesystem clean
                try:
                    if not any(site_root.iterdir()):
                        site_root.rmdir()
                except Exception:
                    pass
                log_action(user["username"], "CreateWebsiteFailed", f"Domain: {domain}, Git error: {clone_msg}", client_ip)
                return RedirectResponse(url=f"/websites?error={urllib.parse.quote(f'Git clone failed: {clone_msg}')}", status_code=303)
        else:
            if project_type == "laravel":
                # For Laravel, create public directory and welcoming index.php
                public_dir = site_root / "public"
                public_dir.mkdir(parents=True, exist_ok=True)
                index_file = public_dir / "index.php"
                if not index_file.exists():
                    index_file.write_text(f"""<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Laravel - {domain}</title>
    <style>
        body {{ font-family: -apple-system, sans-serif; text-align: center; padding: 60px 20px; background: #0f172a; color: #f8fafc; }}
        .card {{ background: #1e293b; padding: 40px; border-radius: 16px; display: inline-block; max-width: 600px; }}
        h1 {{ color: #f43f5e; }}
    </style>
</head>
<body>
    <div class="card">
        <h1>Laravel Application Ready</h1>
        <p>Domain: <strong>{domain}</strong></p>
        <p>Upload your Laravel project or clone from Git. Web root is pointed to <code>/public</code>.</p>
    </div>
</body>
</html>
""")
            else:
                # Create default index.html if empty
                index_file = site_root / "index.html"
                if not index_file.exists():
                    index_file.write_text(f"""<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Welcome to {domain}</title>
    <style>
        body {{ font-family: -apple-system, sans-serif; text-align: center; padding: 60px 20px; background: #0f172a; color: #f8fafc; }}
        .card {{ background: #1e293b; padding: 40px; border-radius: 16px; display: inline-block; max-width: 600px; }}
        h1 {{ color: #38bdf8; }}
    </style>
</head>
<body>
    <div class="card">
        <h1>Welcome to {domain}</h1>
        <p>Your website is hosted and running smoothly via VPS Control Panel.</p>
    </div>
</body>
</html>
""")

        # Permissions: ensure web server (www-data or www) can read
        try:
            shutil.chown(str(site_root), user="www-data", group="www-data")
            for p in site_root.glob("**/*"):
                try:
                    shutil.chown(str(p), user="www-data", group="www-data")
                except Exception:
                    pass
        except Exception:
            pass

        # Optional Database Creation
        linked_db_name = None
        clean_user = None
        clean_pass = None
        db_info_msg = ""
        should_create_db = create_db in ["1", "true", "on", True]

        if should_create_db:
            # Generate safe database name
            if db_name and db_name.strip():
                raw_db = db_name.strip()
            else:
                raw_db = domain.replace(".", "_").replace("-", "_")
            clean_db = re.sub(r'[^a-zA-Z0-9_]', '_', raw_db)[:16].strip('_')
            if not clean_db:
                clean_db = "site_db"
                
            # User
            if db_user and db_user.strip():
                clean_user = re.sub(r'[^a-zA-Z0-9_]', '_', db_user.strip())[:16].strip('_')
            else:
                clean_user = clean_db[:16]
                
            # Password
            if db_pass and db_pass.strip():
                clean_pass = db_pass.strip()
            else:
                clean_pass = secrets.token_urlsafe(12)

            db_success, db_err = create_db_and_user(clean_db, clean_user, clean_pass, db_host="127.0.0.1", charset="utf8mb4")
            if db_success:
                linked_db_name = clean_db
                conn = get_db()
                cur = conn.cursor()
                try:
                    cur.execute(
                        "INSERT INTO databases (db_name, db_user, db_pass, db_host, charset, note) VALUES (?, ?, ?, ?, ?, ?)",
                        (clean_db, clean_user, clean_pass, "127.0.0.1", "utf8mb4", f"Created with website: {domain}")
                    )
                    conn.commit()
                except Exception:
                    pass
                conn.close()
                db_info_msg = f" (Database: {clean_db} created)"
                log_action(user["username"], "CreateDatabaseForSite", f"Domain: {domain}, DB: {clean_db}, User: {clean_user}", client_ip)
            else:
                log_action(user["username"], "CreateDatabaseFailed", f"Domain: {domain}, Error: {db_err}", client_ip)

        # If Laravel, execute Laravel bootstrapping (permissions, .env, artisan)
        if project_type == "laravel":
            setup_laravel_environment(site_root, db_name=linked_db_name, db_user=clean_user, db_pass=clean_pass, php_version=php_version)

        # Create Nginx VirtualHost
        vhost_success, vhost_msg = create_vhost(domain, str(site_root), php_version, project_type)

        conn = get_db()
        cursor = conn.cursor()
        try:
            cursor.execute(
                "INSERT INTO websites (domain, root_dir, php_version, status, git_repo, git_branch, db_name, project_type) VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
                (domain, str(site_root), php_version, "active" if vhost_success else "failed", clean_git_repo or None, clean_git_branch or None, linked_db_name, project_type)
            )
            conn.commit()
        except Exception as e:
            conn.close()
            return RedirectResponse(url=f"/websites?error={urllib.parse.quote(f'Website already exists or DB error: {e}')}", status_code=303)
        conn.close()

        action_details = f"Domain: {domain}, Type: {project_type}, Status: {'OK' if vhost_success else vhost_msg}"
        if clean_git_repo:
            action_details += f", Git: {clean_git_repo}"
        if linked_db_name:
            action_details += f", DB: {linked_db_name}"
        log_action(user["username"], "CreateWebsite", action_details, client_ip)
        
        success_msg = f"Website {domain} ({project_type.capitalize()}) created successfully!{db_info_msg}"
        if clean_git_repo:
            success_msg += " (Repository cloned)"
        return RedirectResponse(url=f"/websites?success={urllib.parse.quote(success_msg)}", status_code=303)
    except Exception as e:
        import traceback
        traceback.print_exc()
        log_action(user["username"], "CreateWebsiteError", f"Domain: {domain if 'domain' in locals() else 'unknown'}, Error: {str(e)}", client_ip)
        return RedirectResponse(url=f"/websites?error={urllib.parse.quote(f'Failed to create website: {e}')}", status_code=303)

@router.post("/{site_id}/delete")
async def delete_website_action(
    site_id: int,
    request: Request,
    delete_files: Optional[str] = Form(None),
    delete_ssl: Optional[str] = Form(None),
    delete_db: Optional[str] = Form(None),
    user: dict = Depends(login_required)
):
    conn = get_db()
    cursor = conn.cursor()
    cursor.execute("SELECT * FROM websites WHERE id = ?", (site_id,))
    site = cursor.fetchone()
    if not site:
        conn.close()
        return RedirectResponse(url=f"/websites?error={urllib.parse.quote('Website not found')}", status_code=303)

    domain = site["domain"]
    root_dir_str = site["root_dir"]
    site_keys = site.keys()
    linked_db = site["db_name"] if "db_name" in site_keys else None

    should_delete_files = delete_files in ["1", "true", "on", True]
    should_delete_ssl = delete_ssl in ["1", "true", "on", True]
    should_delete_db = delete_db in ["1", "true", "on", True]

    # 1. Delete Nginx vhost
    delete_vhost(domain)

    # 2. Remove SSL certificate if requested
    if should_delete_ssl:
        remove_ssl(domain)

    # 3. Delete files if requested
    if should_delete_files:
        forbidden = ["/", "/var", "/var/www", "/www", "/www/wwwroot", "/root", "/home", "/etc", "/usr", "/tmp"]
        targets_to_delete = []
        if root_dir_str:
            rp = Path(root_dir_str).resolve()
            if rp.name == "public" and rp.parent != WEB_ROOT:
                targets_to_delete.append(rp.parent)
            targets_to_delete.append(rp)

        # Also ensure /www/wwwroot/<domain> is cleaned
        domain_folder = (WEB_ROOT / domain).resolve()
        targets_to_delete.append(domain_folder)

        for target in targets_to_delete:
            try:
                target_str = str(target)
                if target_str not in forbidden and len(target_str) > 7:
                    if target.exists():
                        shutil.rmtree(target_str, ignore_errors=True)
                        subprocess.run(["rm", "-rf", target_str], check=False, timeout=15)
            except Exception:
                pass

    # 4. Delete Database if requested
    if should_delete_db:
        # If no explicit linked_db, check for matching db by domain name
        if not linked_db:
            clean_d = re.sub(r'[^a-zA-Z0-9_]', '_', domain)[:16].strip('_')
            cursor.execute("SELECT db_name FROM databases WHERE db_name = ? OR note LIKE ?", (clean_d, f"%{domain}%"))
            db_match = cursor.fetchone()
            if db_match:
                linked_db = db_match["db_name"]

        if linked_db:
            cursor.execute("SELECT db_user, db_host FROM databases WHERE db_name = ?", (linked_db,))
            db_row = cursor.fetchone()
            db_user = db_row["db_user"] if db_row else None
            db_host = db_row["db_host"] if db_row else "127.0.0.1"
            drop_db_and_user(linked_db, db_user, db_host)
            cursor.execute("DELETE FROM databases WHERE db_name = ?", (linked_db,))

    # 5. Delete website from websites table
    cursor.execute("DELETE FROM websites WHERE id = ?", (site_id,))
    conn.commit()
    conn.close()

    deleted_info = []
    if should_delete_files:
        deleted_info.append("files removed")
    if should_delete_ssl:
        deleted_info.append("SSL removed")
    if should_delete_db and linked_db:
        deleted_info.append(f"database {linked_db} removed")

    info_str = f" ({', '.join(deleted_info)})" if deleted_info else ""
    log_action(user["username"], "DeleteWebsite", f"Deleted {domain}{info_str}", get_client_ip(request))
    return RedirectResponse(url=f"/websites?success={urllib.parse.quote(f'Website {domain} deleted successfully{info_str}')}", status_code=303)

@router.post("/{site_id}/ssl")
async def issue_ssl_action(site_id: int, request: Request, user: dict = Depends(login_required)):
    conn = get_db()
    cursor = conn.cursor()
    cursor.execute("SELECT * FROM websites WHERE id = ?", (site_id,))
    site = cursor.fetchone()
    if not site:
        conn.close()
        return JSONResponse({"success": False, "error": "Website not found"}, status_code=404)

    domain = site["domain"]
    root_dir = site["root_dir"]

    success, msg = issue_ssl(domain, root_dir)
    if success:
        cursor.execute("UPDATE websites SET ssl_enabled = 1 WHERE id = ?", (site_id,))
        conn.commit()
    conn.close()

    log_action(user["username"], "IssueSSL", f"Domain: {domain}, Result: {msg}", get_client_ip(request))
    return JSONResponse({"success": success, "message": msg})

@router.post("/{site_id}/sync")
async def sync_vhost_action(site_id: int, request: Request, user: dict = Depends(login_required)):
    conn = get_db()
    cursor = conn.cursor()
    cursor.execute("SELECT * FROM websites WHERE id = ?", (site_id,))
    site = cursor.fetchone()
    if not site:
        conn.close()
        return JSONResponse({"success": False, "error": "Website not found"}, status_code=404)

    site_keys = site.keys()
    project_type = site["project_type"] if "project_type" in site_keys and site["project_type"] else "php"
    success, msg = create_vhost(site["domain"], site["root_dir"], site["php_version"], project_type)
    conn.close()

    log_action(user["username"], "SyncVhost", f"Domain: {site['domain']}, Preset: {project_type}, Result: {msg}", get_client_ip(request))
    return JSONResponse({"success": success, "message": msg})

@router.post("/{site_id}/git-pull")
async def pull_website_git(site_id: int, request: Request, user: dict = Depends(login_required)):
    conn = get_db()
    cursor = conn.cursor()
    cursor.execute("SELECT * FROM websites WHERE id = ?", (site_id,))
    site = cursor.fetchone()
    conn.close()
    if not site:
        return JSONResponse({"success": False, "error": "Website not found"}, status_code=404)

    root_dir = site["root_dir"]
    site_keys = site.keys()
    project_type = site["project_type"] if "project_type" in site_keys and site["project_type"] else "php"
    success, msg = pull_repository(root_dir)
    
    # Re-apply permissions if git pull modified/created files
    if success:
        try:
            site_root = Path(root_dir)
            if project_type == "laravel":
                setup_laravel_environment(site_root, php_version=site["php_version"])
            else:
                shutil.chown(str(site_root), user="www-data", group="www-data")
                for p in site_root.glob("**/*"):
                    try:
                        shutil.chown(str(p), user="www-data", group="www-data")
                    except Exception:
                        pass
        except Exception:
            pass

    log_action(user["username"], "GitPull", f"Domain: {site['domain']}, Result: {msg}", get_client_ip(request))
    return JSONResponse({"success": success, "message": msg})

@router.post("/{site_id}/settings")
async def update_website_settings(
    site_id: int,
    request: Request,
    root_dir: str = Form(...),
    php_version: str = Form("8.3"),
    project_type: str = Form("php"),
    git_repo: Optional[str] = Form(None),
    git_branch: Optional[str] = Form(None),
    db_name: Optional[str] = Form(None),
    user: dict = Depends(login_required)
):
    conn = get_db()
    cursor = conn.cursor()
    cursor.execute("SELECT * FROM websites WHERE id = ?", (site_id,))
    site = cursor.fetchone()
    if not site:
        conn.close()
        return RedirectResponse(url=f"/websites?error={urllib.parse.quote('Website not found')}", status_code=303)

    domain = site["domain"]
    project_type = project_type.strip().lower() if project_type else "php"
    if project_type not in ["laravel", "php", "static"]:
        project_type = "php"

    clean_root_dir = root_dir.strip()
    if not clean_root_dir:
        conn.close()
        return RedirectResponse(url=f"/websites?error={urllib.parse.quote('Root directory cannot be empty')}", status_code=303)

    new_root = Path(clean_root_dir)
    try:
        new_root.mkdir(parents=True, exist_ok=True)
    except Exception as e:
        conn.close()
        return RedirectResponse(url=f"/websites?error={urllib.parse.quote(f'Failed to create root directory: {e}')}", status_code=303)

    clean_git_repo = git_repo.strip() if git_repo else ""
    clean_git_branch = git_branch.strip() if git_branch else ""
    clean_db_name = db_name.strip() if db_name and db_name.strip() else None

    # Reconfigure Nginx vhost with new root_dir, php_version, and project_type
    vhost_success, vhost_msg = create_vhost(domain, str(new_root), php_version, project_type)
    if not vhost_success:
        conn.close()
        return RedirectResponse(url=f"/websites?error={urllib.parse.quote(f'Nginx configuration failed: {vhost_msg}')}", status_code=303)

    # Set file ownership for web server
    try:
        shutil.chown(str(new_root), user="www-data", group="www-data")
        for p in new_root.glob("**/*"):
            try:
                shutil.chown(str(p), user="www-data", group="www-data")
            except Exception:
                pass
    except Exception:
        pass

    if project_type == "laravel":
        setup_laravel_environment(new_root, clean_db_name, php_version=php_version)

    cursor.execute(
        "UPDATE websites SET root_dir = ?, php_version = ?, git_repo = ?, git_branch = ?, db_name = ?, project_type = ? WHERE id = ?",
        (str(new_root), php_version, clean_git_repo or None, clean_git_branch or None, clean_db_name, project_type, site_id)
    )
    conn.commit()
    conn.close()

    log_action(user["username"], "UpdateWebsiteSettings", f"Domain: {domain}, Type: {project_type}, Root: {new_root}, PHP: {php_version}", get_client_ip(request))
    return RedirectResponse(url=f"/websites?success={urllib.parse.quote(f'Settings updated for {domain} ({project_type.capitalize()})')}", status_code=303)

@router.post("/{site_id}/fix-laravel")
async def fix_laravel_action(site_id: int, request: Request, user: dict = Depends(login_required)):
    conn = get_db()
    cursor = conn.cursor()
    cursor.execute("SELECT * FROM websites WHERE id = ?", (site_id,))
    site = cursor.fetchone()
    if not site:
        conn.close()
        return JSONResponse({"success": False, "error": "Website not found"}, status_code=404)

    domain = site["domain"]
    root_dir = site["root_dir"]
    php_version = site["php_version"] or "8.3"
    site_keys = site.keys()
    db_name = site["db_name"] if "db_name" in site_keys else None

    # Fetch DB credentials if present
    db_user = None
    db_pass = None
    if db_name:
        cursor.execute("SELECT db_user, db_pass FROM databases WHERE db_name = ?", (db_name,))
        db_row = cursor.fetchone()
        if db_row:
            db_user = db_row["db_user"]
            db_pass = db_row["db_pass"]

    # 1. Update Nginx vhost with Laravel preset
    vhost_ok, vhost_msg = create_vhost(domain, root_dir, php_version, "laravel")

    # 2. Run Laravel environment setup (storage, bootstrap/cache, 775, .env, artisan)
    setup_ok, setup_msg = setup_laravel_environment(
        Path(root_dir),
        db_name=db_name,
        db_user=db_user,
        db_pass=db_pass,
        php_version=php_version
    )

    # 3. Save project_type = 'laravel' in DB
    cursor.execute("UPDATE websites SET project_type = 'laravel' WHERE id = ?", (site_id,))
    conn.commit()
    conn.close()

    try:
        restore_ssl_for_existing_domains()
    except Exception:
        pass

    log_action(user["username"], "FixLaravel", f"Domain: {domain}, Nginx: {vhost_msg}, Setup: {setup_msg}", get_client_ip(request))
    return JSONResponse({
        "success": True,
        "message": f"Laravel configured! {setup_msg}. Nginx: {vhost_msg}"
    })
