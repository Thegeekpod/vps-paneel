from fastapi import APIRouter, Request, Depends, Form
from fastapi.responses import HTMLResponse, RedirectResponse, JSONResponse
from app.auth import login_required
from app.database import get_db, hash_password, log_action
from app.templates_util import render
from app.services.panel_ssl_service import (
    get_panel_ssl_status,
    save_panel_domain,
    issue_panel_letsencrypt,
    generate_panel_self_signed,
    disable_panel_ssl,
    restart_panel_service_background,
    clean_domain
)
from app.services.dns_service import check_domain_dns

router = APIRouter(prefix="/settings")

@router.get("", response_class=HTMLResponse)
async def settings_view(request: Request, user: dict = Depends(login_required)):
    ssl_status = get_panel_ssl_status()
    error = request.query_params.get("error")
    success = request.query_params.get("success")
    msg = request.query_params.get("msg")

    return render("settings.html", {
        "user": user,
        "ssl_status": ssl_status,
        "error": error,
        "success": success,
        "msg": msg,
        "active_nav": "settings"
    }, request)

@router.post("/domain")
async def save_domain_action(
    request: Request,
    panel_domain: str = Form(""),
    user: dict = Depends(login_required)
):
    ok, message = save_panel_domain(panel_domain)
    log_action(user["username"], "UpdatePanelDomain", f"Set panel domain to '{panel_domain}'", request.client.host)
    if ok:
        return RedirectResponse(url=f"/settings?success=1&msg={message}", status_code=303)
    return RedirectResponse(url=f"/settings?error={message}", status_code=303)

@router.post("/check-domain")
async def check_domain_action(request: Request, user: dict = Depends(login_required)):
    data = await request.json()
    domain = data.get("domain", "")
    result = check_domain_dns(domain)
    return JSONResponse(result)

@router.post("/ssl/issue-letsencrypt")
async def issue_letsencrypt_action(
    request: Request,
    domain: str = Form(""),
    email: str = Form(""),
    user: dict = Depends(login_required)
):
    target_domain = clean_domain(domain)
    if not target_domain:
        ssl_status = get_panel_ssl_status()
        target_domain = ssl_status.get("panel_domain")

    if not target_domain:
        return JSONResponse({"success": False, "error": "Please provide a domain name (e.g. panel.yourdomain.com) first."}, status_code=400)

    ok, message = issue_panel_letsencrypt(target_domain, email)
    log_action(user["username"], "PanelSSL-LetsEncrypt", f"Issued SSL for {target_domain}: {message}", request.client.host)
    return JSONResponse({"success": ok, "message": message if ok else None, "error": None if ok else message})

@router.post("/ssl/generate-self-signed")
async def generate_self_signed_action(request: Request, user: dict = Depends(login_required)):
    ssl_status = get_panel_ssl_status()
    target = ssl_status.get("panel_domain") or ssl_status.get("server_ip")
    ok, message = generate_panel_self_signed(target)
    log_action(user["username"], "PanelSSL-SelfSigned", f"Generated self-signed certificate: {message}", request.client.host)
    return JSONResponse({"success": ok, "message": message if ok else None, "error": None if ok else message})

@router.post("/ssl/disable")
async def disable_ssl_action(request: Request, user: dict = Depends(login_required)):
    ok, message = disable_panel_ssl()
    log_action(user["username"], "PanelSSL-Disable", "Disabled panel SSL certificate", request.client.host)
    return JSONResponse({"success": ok, "message": message if ok else None, "error": None if ok else message})

@router.post("/restart-service")
async def restart_service_action(request: Request, user: dict = Depends(login_required)):
    restart_panel_service_background()
    log_action(user["username"], "RestartPanelService", "Admin restarted vps-panel service", request.client.host)
    return JSONResponse({"success": True, "message": "VPS Control Panel service is restarting..."})

@router.post("/change-password")
async def change_password(
    request: Request,
    old_password: str = Form(...),
    new_password: str = Form(...),
    confirm_password: str = Form(...),
    user: dict = Depends(login_required)
):
    ssl_status = get_panel_ssl_status()
    if new_password != confirm_password:
        return render("settings.html", {
            "user": user,
            "ssl_status": ssl_status,
            "error": "New passwords do not match",
            "active_nav": "settings"
        }, request)

    conn = get_db()
    cursor = conn.cursor()
    cursor.execute("SELECT password_hash FROM users WHERE username = ?", (user["username"],))
    row = cursor.fetchone()
    
    if not row or row["password_hash"] != hash_password(old_password):
        conn.close()
        return render("settings.html", {
            "user": user,
            "ssl_status": ssl_status,
            "error": "Current password incorrect",
            "active_nav": "settings"
        }, request)

    cursor.execute("UPDATE users SET password_hash = ? WHERE username = ?",
                   (hash_password(new_password), user["username"]))
    conn.commit()
    conn.close()

    log_action(user["username"], "ChangePassword", "Admin changed password", request.client.host)
    return RedirectResponse(url="/settings?success=1&msg=Password+updated+successfully", status_code=303)

@router.get("/check-update")
async def api_check_update(user: dict = Depends(login_required)):
    from app.services.system_service import check_panel_update
    return check_panel_update()

@router.post("/apply-update")
async def api_apply_update(request: Request, user: dict = Depends(login_required)):
    from app.services.system_service import perform_panel_update
    result = perform_panel_update()
    log_action(user["username"], "PanelUpdate", "Triggered self-update from GitHub", request.client.host)
    return JSONResponse(result)
