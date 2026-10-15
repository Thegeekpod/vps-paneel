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
from app.services.ssl_service import issue_ssl
from app.services.git_service import clone_repository, pull_repository
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

    # Create Nginx VirtualHost
    vhost_success, vhost_msg = create_vhost(domain, str(site_root), php_version)

    conn = get_db()
    cursor = conn.cursor()
    try:
        cursor.execute(
            "INSERT INTO websites (domain, root_dir, php_version, status, git_repo, git_branch) VALUES (?, ?, ?, ?, ?, ?)",
            (domain, str(site_root), php_version, "active" if vhost_success else "failed", clean_git_repo or None, clean_git_branch or None)
        )
        conn.commit()
    except Exception as e:
        conn.close()
        return RedirectResponse(url=f"/websites?error={urllib.parse.quote(f'Website already exists or DB error: {e}')}", status_code=303)
    conn.close()

    action_details = f"Domain: {domain}, Status: {'OK' if vhost_success else vhost_msg}"
    if clean_git_repo:
        action_details += f", Git: {clean_git_repo}"
    log_action(user["username"], "CreateWebsite", action_details, request.client.host)
    
    success_msg = f"Website {domain} created successfully!"
    if clean_git_repo:
        success_msg += " (Repository cloned)"
    return RedirectResponse(url=f"/websites?success={urllib.parse.quote(success_msg)}", status_code=303)

@router.post("/{site_id}/delete")
async def delete_website_action(site_id: int, request: Request, user: dict = Depends(login_required)):
    conn = get_db()
    cursor = conn.cursor()
    cursor.execute("SELECT * FROM websites WHERE id = ?", (site_id,))
    site = cursor.fetchone()
    if not site:
        conn.close()
        raise HTTPException(status_code=404, detail="Website not found")

    domain = site["domain"]
    delete_vhost(domain)

    cursor.execute("DELETE FROM websites WHERE id = ?", (site_id,))
    conn.commit()
    conn.close()

    log_action(user["username"], "DeleteWebsite", f"Deleted: {domain}", request.client.host)
    return RedirectResponse(url="/websites", status_code=303)

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
        "UPDATE websites SET root_dir = ?, php_version = ?, git_repo = ?, git_branch = ? WHERE id = ?",
        (str(new_root), php_version, clean_git_repo or None, clean_git_branch or None, site_id)
    )
    conn.commit()
    conn.close()

    log_action(user["username"], "UpdateWebsiteSettings", f"Domain: {domain}, Root: {new_root}, PHP: {php_version}", request.client.host)
    return RedirectResponse(url=f"/websites?success={urllib.parse.quote(f'Settings updated for {domain}')}", status_code=303)
