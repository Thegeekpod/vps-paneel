import os
from pathlib import Path
from fastapi import FastAPI, Request, Form, Depends
from fastapi.responses import HTMLResponse, RedirectResponse
from fastapi.staticfiles import StaticFiles
from fastapi.templating import Jinja2Templates

from app.config import BASE_DIR, PANEL_PORT
from app.database import init_db, get_db, hash_password, log_action
from app.auth import create_access_token, get_current_user, COOKIE_NAME
from app.services.nginx_service import ensure_default_catchall
from app.templates_util import render
from app.routes import dashboard, websites, filemanager, services, settings

app = FastAPI(title="VPS Control Panel", docs_url=None, redoc_url=None)

# Static files
STATIC_DIR = Path(__file__).parent / "static"
STATIC_DIR.mkdir(parents=True, exist_ok=True)
app.mount("/static", StaticFiles(directory=str(STATIC_DIR)), name="static")

# Include Routers
app.include_router(dashboard.router)
app.include_router(websites.router)
app.include_router(filemanager.router)
app.include_router(services.router)
app.include_router(settings.router)

@app.on_event("startup")
async def on_startup():
    init_db()
    ensure_default_catchall()

@app.get("/login", response_class=HTMLResponse)
async def login_view(request: Request):
    user = get_current_user(request)
    if user:
        return RedirectResponse(url="/", status_code=303)
    return render("login.html", {"error": None}, request)

@app.post("/login")
async def login_action(
    request: Request,
    username: str = Form(...),
    password: str = Form(...)
):
    conn = get_db()
    cursor = conn.cursor()
    cursor.execute("SELECT id, username, password_hash, role FROM users WHERE username = ?", (username.strip(),))
    user = cursor.fetchone()
    conn.close()

    if not user or user["password_hash"] != hash_password(password):
        return render("login.html", {"error": "Invalid username or password"}, request, status_code=400)

    token = create_access_token(user["username"], user["role"])
    log_action(user["username"], "Login", "Successful web login", request.client.host)

    response = RedirectResponse(url="/", status_code=303)
    response.set_cookie(
        key=COOKIE_NAME,
        value=token,
        httponly=True,
        max_age=7 * 86400,
        samesite="lax"
    )
    return response

@app.get("/logout")
async def logout_action():
    response = RedirectResponse(url="/login", status_code=303)
    response.delete_cookie(COOKIE_NAME)
    return response
