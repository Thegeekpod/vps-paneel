import os
import shutil
from pathlib import Path
from fastapi import APIRouter, Request, Depends, Form, HTTPException
from fastapi.responses import HTMLResponse, RedirectResponse, JSONResponse
from fastapi.templating import Jinja2Templates
from app.auth import login_required
from app.config import WEB_ROOT
from app.database import get_db, log_action
from app.services.nginx_service import create_vhost, delete_vhost, test_and_reload_nginx
from app.services.ssl_service import issue_ssl

router = APIRouter(prefix="/websites")
templates = Jinja2Templates(directory=str(Path(__file__).parent.parent / "templates"))

@router.get("", response_class=HTMLResponse)
async def list_websites(request: Request, user: dict = Depends(login_required)):
    conn = get_db()
    cursor = conn.cursor()
    cursor.execute("SELECT * FROM websites ORDER BY id DESC")
    websites = [dict(row) for row in cursor.fetchall()]
    conn.close()

    return templates.TemplateResponse("websites.html", {
        "request": request,
        "user": user,
        "websites": websites,
        "active_nav": "websites"
    })

@router.post("/create")
async def create_website_action(
    request: Request,
    domain: str = Form(...),
    php_version: str = Form("8.3"),
    user: dict = Depends(login_required)
):
    domain = domain.strip().lower()
    if not domain or " " in domain:
        raise HTTPException(status_code=400, detail="Invalid domain name")

    site_root = WEB_ROOT / domain
    site_root.mkdir(parents=True, exist_ok=True)
    
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
    success, msg = create_vhost(domain, str(site_root), php_version)

    conn = get_db()
    cursor = conn.cursor()
    try:
        cursor.execute(
            "INSERT INTO websites (domain, root_dir, php_version, status) VALUES (?, ?, ?, ?)",
            (domain, str(site_root), php_version, "active" if success else "failed")
        )
        conn.commit()
    except Exception as e:
        conn.close()
        raise HTTPException(status_code=400, detail=f"Website already exists: {e}")
    conn.close()

    log_action(user["username"], "CreateWebsite", f"Domain: {domain}, Status: {'OK' if success else msg}", request.client.host)
    return RedirectResponse(url="/websites", status_code=303)

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
