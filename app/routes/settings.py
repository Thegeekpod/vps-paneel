from fastapi import APIRouter, Request, Depends, Form
from fastapi.responses import HTMLResponse, RedirectResponse, JSONResponse
from fastapi.templating import Jinja2Templates
from pathlib import Path
from app.auth import login_required
from app.database import get_db, hash_password, log_action

router = APIRouter(prefix="/settings")
templates = Jinja2Templates(directory=str(Path(__file__).parent.parent / "templates"))

@router.get("", response_class=HTMLResponse)
async def settings_view(request: Request, user: dict = Depends(login_required)):
    conn = get_db()
    cursor = conn.cursor()
    cursor.execute("SELECT * FROM audit_logs ORDER BY id DESC LIMIT 50")
    logs = [dict(row) for row in cursor.fetchall()]
    conn.close()

    return templates.TemplateResponse("settings.html", {
        "request": request,
        "user": user,
        "logs": logs,
        "active_nav": "settings"
    })

@router.post("/change-password")
async def change_password(
    request: Request,
    old_password: str = Form(...),
    new_password: str = Form(...),
    confirm_password: str = Form(...),
    user: dict = Depends(login_required)
):
    if new_password != confirm_password:
        return templates.TemplateResponse("settings.html", {
            "request": request, "user": user, "error": "New passwords do not match", "active_nav": "settings"
        })

    conn = get_db()
    cursor = conn.cursor()
    cursor.execute("SELECT password_hash FROM users WHERE username = ?", (user["username"],))
    row = cursor.fetchone()
    
    if not row or row["password_hash"] != hash_password(old_password):
        conn.close()
        return templates.TemplateResponse("settings.html", {
            "request": request, "user": user, "error": "Current password incorrect", "active_nav": "settings"
        })

    cursor.execute("UPDATE users SET password_hash = ? WHERE username = ?",
                   (hash_password(new_password), user["username"]))
    conn.commit()
    conn.close()

    log_action(user["username"], "ChangePassword", "Admin changed password", request.client.host)
    return RedirectResponse(url="/settings?success=1", status_code=303)
