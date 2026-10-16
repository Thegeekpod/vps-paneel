from fastapi import APIRouter, Request, Depends, Form
from fastapi.responses import HTMLResponse, JSONResponse
from app.auth import login_required
from app.services.system_service import get_service_status, control_service
from app.services.node_service import (
    get_node_environment,
    get_all_configured_node_apps,
    install_node_environment
)
from app.database import log_action
from app.templates_util import render

router = APIRouter(prefix="/services")

SERVICE_LIST = [
    {"name": "nginx", "title": "Nginx Web Server", "desc": "High performance HTTP & reverse proxy server"},
    {"name": "php8.3-fpm", "title": "PHP 8.3 FPM", "desc": "FastCGI Process Manager for PHP 8.3"},
    {"name": "mariadb", "title": "MariaDB Database", "desc": "Relational database server"},
    {"name": "pm2", "title": "PM2 Process Manager", "desc": "Production process manager & cluster runner for Node.js and Next.js"},
    {"name": "ufw", "title": "UFW Firewall", "desc": "Uncomplicated Firewall for network security"},
]

@router.get("", response_class=HTMLResponse)
async def services_view(request: Request, user: dict = Depends(login_required)):
    services = []
    for s in SERVICE_LIST:
        services.append({
            **s,
            "is_active": get_service_status(s["name"])
        })

    # Optional: If redis-server is active or installed, add it
    if get_service_status("redis-server") or get_service_status("redis"):
        services.append({
            "name": "redis-server",
            "title": "Redis In-Memory Cache",
            "desc": "In-memory data structure store used as a database and cache",
            "is_active": True
        })

    node_env = get_node_environment()
    node_apps = get_all_configured_node_apps()

    return render("services.html", {
        "user": user,
        "services": services,
        "node_env": node_env,
        "node_apps": node_apps,
        "active_nav": "services"
    }, request)

@router.post("/control")
async def service_control(
    request: Request,
    service_name: str = Form(...),
    action: str = Form(...),
    user: dict = Depends(login_required)
):
    success = control_service(service_name, action)
    log_action(user["username"], "ManageService", f"{action} {service_name} (Success: {success})", request.client.host)
    return JSONResponse({"success": success, "message": f"{action.capitalize()} {service_name} {'succeeded' if success else 'failed'}"})

@router.post("/install-node")
async def install_node_endpoint(request: Request, user: dict = Depends(login_required)):
    success, msg = install_node_environment()
    log_action(user["username"], "InstallNodeEnv", msg, request.client.host)
    return JSONResponse({"success": success, "message": msg})

