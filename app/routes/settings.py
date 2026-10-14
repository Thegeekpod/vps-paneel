from fastapi import APIRouter, Request, Depends, Form
from fastapi.responses import HTMLResponse, RedirectResponse, JSONResponse
from app.auth import login_required
from app.database import get_db, hash_password, log_action
from app.templates_util import render

router = APIRouter(prefix="/settings")

@router.get("", response_class=HTMLResponse)
async def settings_view(request: Request, user: dict = Depends(login_required)):
    conn = get_db()
    cursor = conn.cursor()
    cursor.execute("SELECT * FROM audit_logs ORDER BY id DESC LIMIT 50")
    logs = [dict(row) for row in cursor.fetchall()]
    conn.close()

    return render("settings.html", {
        "user": user,
        "logs": logs,
        "active_nav": "settings"
    }, request)

@router.post("/change-password")
async def change_password(
    request: Request,
    old_password: str = Form(...),
    new_password: str = Form(...),
    confirm_password: str = Form(...),
    user: dict = Depends(login_required)
):
    if new_password != confirm_password:
        return render("settings.html", {
            "user": user, "error": "New passwords do not match", "active_nav": "settings"
        }, request)

    conn = get_db()
    cursor = conn.cursor()
    cursor.execute("SELECT password_hash FROM users WHERE username = ?", (user["username"],))
    row = cursor.fetchone()
    
    if not row or row["password_hash"] != hash_password(old_password):
        conn.close()
        return render("settings.html", {
            "user": user, "error": "Current password incorrect", "active_nav": "settings"
        }, request)

    cursor.execute("UPDATE users SET password_hash = ? WHERE username = ?",
                   (hash_password(new_password), user["username"]))
    conn.commit()
    conn.close()

    log_action(user["username"], "ChangePassword", "Admin changed password", request.client.host)
    return RedirectResponse(url="/settings?success=1", status_code=303)

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

