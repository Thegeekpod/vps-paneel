import re
import secrets
import shutil
import urllib.parse
from pathlib import Path
from typing import Optional
from fastapi import APIRouter, Request, Depends, Form, HTTPException
from fastapi.responses import HTMLResponse, RedirectResponse, JSONResponse
from app.auth import login_required
from app.config import WEB_ROOT
from app.database import get_db, log_action
from app.services.nginx_service import create_vhost, delete_vhost, test_and_reload_nginx
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

@router.post("/create")
async def create_website_action(
    request: Request,
    domain: str = Form(...),
    root_dir: Optional[str] = Form(None),
    php_version: str = Form("8.3"),
    git_repo: Optional[str] = Form(None),
    git_branch: Optional[str] = Form(None),
    create_db: Optional[str] = Form(None),
    db_name: Optional[str] = Form(None),
    db_user: Optional[str] = Form(None),
    db_pass: Optional[str] = Form(None),
    user: dict = Depends(login_required)
):
    domain = domain.strip().lower()
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
        # Clone repository into document root
        cloned, clone_msg = clone_repository(clean_git_repo, site_root, clean_git_branch)
        if not cloned:
            # If directory is empty, remove it to keep filesystem clean
            try:
                if not any(site_root.iterdir()):
                    site_root.rmdir()
            except Exception:
                pass
            log_action(user["username"], "CreateWebsiteFailed", f"Domain: {domain}, Git error: {clone_msg}", request.client.host)
            return RedirectResponse(url=f"/websites?error={urllib.parse.quote(f'Git clone failed: {clone_msg}')}", status_code=303)
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
            log_action(user["username"], "CreateDatabaseForSite", f"Domain: {domain}, DB: {clean_db}, User: {clean_user}", request.client.host)
        else:
            log_action(user["username"], "CreateDatabaseFailed", f"Domain: {domain}, Error: {db_err}", request.client.host)

    # Create Nginx VirtualHost
    vhost_success, vhost_msg = create_vhost(domain, str(site_root), php_version)

    conn = get_db()
    cursor = conn.cursor()
    try:
        cursor.execute(
            "INSERT INTO websites (domain, root_dir, php_version, status, git_repo, git_branch, db_name) VALUES (?, ?, ?, ?, ?, ?, ?)",
            (domain, str(site_root), php_version, "active" if vhost_success else "failed", clean_git_repo or None, clean_git_branch or None, linked_db_name)
        )
        conn.commit()
    except Exception as e:
        conn.close()
        return RedirectResponse(url=f"/websites?error={urllib.parse.quote(f'Website already exists or DB error: {e}')}", status_code=303)
    conn.close()

    action_details = f"Domain: {domain}, Status: {'OK' if vhost_success else vhost_msg}"
    if clean_git_repo:
        action_details += f", Git: {clean_git_repo}"
    if linked_db_name:
        action_details += f", DB: {linked_db_name}"
    log_action(user["username"], "CreateWebsite", action_details, request.client.host)
    
    success_msg = f"Website {domain} created successfully!{db_info_msg}"
    if clean_git_repo:
        success_msg += " (Repository cloned)"
    return RedirectResponse(url=f"/websites?success={urllib.parse.quote(success_msg)}", status_code=303)

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
    if should_delete_files and root_dir_str:
        try:
            root_path = Path(root_dir_str).resolve()
            # Safety guards: never delete root, system, or panel dirs
            forbidden = ["/", "/var", "/var/www", "/www", "/www/wwwroot", "/root", "/home", "/etc", "/usr", "/tmp"]
            if str(root_path) not in forbidden and len(str(root_path)) > 7:
                if root_path.exists():
                    shutil.rmtree(str(root_path), ignore_errors=True)
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
    log_action(user["username"], "DeleteWebsite", f"Deleted {domain}{info_str}", request.client.host)
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

    log_action(user["username"], "IssueSSL", f"Domain: {domain}, Result: {msg}", request.client.host)
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

    success, msg = create_vhost(site["domain"], site["root_dir"], site["php_version"])
    conn.close()

    log_action(user["username"], "SyncVhost", f"Domain: {site['domain']}, Result: {msg}", request.client.host)
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
    success, msg = pull_repository(root_dir)
    
    # Re-apply permissions if git pull modified/created files
    if success:
        try:
            site_root = Path(root_dir)
            shutil.chown(str(site_root), user="www-data", group="www-data")
            for p in site_root.glob("**/*"):
                try:
                    shutil.chown(str(p), user="www-data", group="www-data")
                except Exception:
                    pass
        except Exception:
            pass

    log_action(user["username"], "GitPull", f"Domain: {site['domain']}, Result: {msg}", request.client.host)
    return JSONResponse({"success": success, "message": msg})

@router.post("/{site_id}/settings")
async def update_website_settings(
    site_id: int,
    request: Request,
    root_dir: str = Form(...),
    php_version: str = Form("8.3"),
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

    # Reconfigure Nginx vhost with new root_dir and php_version
    vhost_success, vhost_msg = create_vhost(domain, str(new_root), php_version)
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

    cursor.execute(
        "UPDATE websites SET root_dir = ?, php_version = ?, git_repo = ?, git_branch = ?, db_name = ? WHERE id = ?",
        (str(new_root), php_version, clean_git_repo or None, clean_git_branch or None, clean_db_name, site_id)
    )
    conn.commit()
    conn.close()

    log_action(user["username"], "UpdateWebsiteSettings", f"Domain: {domain}, Root: {new_root}, PHP: {php_version}", request.client.host)
    return RedirectResponse(url=f"/websites?success={urllib.parse.quote(f'Settings updated for {domain}')}", status_code=303)
