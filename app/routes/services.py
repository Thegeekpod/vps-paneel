from fastapi import APIRouter, Request, Depends, Form
from fastapi.responses import HTMLResponse, JSONResponse
from app.auth import login_required
from app.services.system_service import get_service_status, control_service
from app.database import log_action
from app.templates_util import render

router = APIRouter(prefix="/services")

SERVICE_LIST = [
    {"name": "nginx", "title": "Nginx Web Server", "desc": "High performance HTTP & reverse proxy server"},
    {"name": "php8.3-fpm", "title": "PHP 8.3 FPM", "desc": "FastCGI Process Manager for PHP 8.3"},
    {"name": "mariadb", "title": "MariaDB Database", "desc": "Relational database server"},
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
    return render("services.html", {
        "user": user,
        "services": services,
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
