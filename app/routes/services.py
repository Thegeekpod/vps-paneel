from fastapi import APIRouter, Request, Depends, Form
from fastapi.responses import HTMLResponse, JSONResponse
from app.auth import login_required
from app.services.system_service import get_service_status, control_service
from app.services.node_service import (
    get_node_environment,
    get_all_configured_node_apps,
    install_node_environment,
    get_installed_node_versions,
    switch_node_version,
    install_node_version,
    uninstall_node_version
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
    try:
        services = []
        for s in SERVICE_LIST:
            try:
                active = get_service_status(s["name"])
            except Exception:
                active = False
            services.append({
                **s,
                "is_active": active
            })

        # Optional: If redis-server is active or installed, add it
        try:
            if get_service_status("redis-server") or get_service_status("redis"):
                services.append({
                    "name": "redis-server",
                    "title": "Redis In-Memory Cache",
                    "desc": "In-memory data structure store used as a database and cache",
                    "is_active": True
                })
        except Exception:
            pass

        try:
            node_env = get_node_environment()
        except Exception:
            node_env = {
                "installed": False,
                "node_version": "Not installed",
                "npm_version": "Not installed",
                "pm2_version": "Not installed",
                "has_pm2": False,
                "node_path": None,
                "npm_path": None,
                "pm2_path": None
            }

        try:
            node_versions = get_installed_node_versions()
        except Exception:
            node_versions = {
                "current_version": "Not installed",
                "has_nvm": False,
                "installed": [],
                "available": []
            }

        try:
            node_apps = get_all_configured_node_apps()
        except Exception:
            node_apps = []

        online_count = sum(1 for a in node_apps if a.get("is_running"))
        stopped_count = max(0, len(node_apps) - online_count)

        return render("services.html", {
            "user": user,
            "services": services,
            "node_env": node_env,
            "node_versions": node_versions,
            "node_apps": node_apps,
            "online_count": online_count,
            "stopped_count": stopped_count,
            "active_nav": "services"
        }, request)
    except Exception as e:
        import traceback
        traceback.print_exc()
        return render("services.html", {
            "user": user,
            "services": [
                {"name": "nginx", "title": "Nginx Web Server", "desc": "High performance HTTP & reverse proxy server", "is_active": True},
                {"name": "php8.3-fpm", "title": "PHP 8.3 FPM", "desc": "FastCGI Process Manager for PHP 8.3", "is_active": True},
                {"name": "mariadb", "title": "MariaDB Database", "desc": "Relational database server", "is_active": True},
                {"name": "pm2", "title": "PM2 Process Manager", "desc": "Production process manager for Node.js", "is_active": False},
                {"name": "ufw", "title": "UFW Firewall", "desc": "Uncomplicated Firewall", "is_active": True},
            ],
            "node_env": {"installed": False, "node_version": "Not detected", "npm_version": "Not detected", "pm2_version": "Not detected", "has_pm2": False},
            "node_versions": {"current_version": "Not installed", "has_nvm": False, "installed": [], "available": []},
            "node_apps": [],
            "online_count": 0,
            "stopped_count": 0,
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

@router.post("/node-version/switch")
async def switch_node_version_endpoint(
    request: Request,
    version: str = Form(...),
    user: dict = Depends(login_required)
):
    success, msg = switch_node_version(version)
    log_action(user["username"], "SwitchNodeVersion", f"Target: {version}, Result: {msg}", request.client.host)
    return JSONResponse({"success": success, "message": msg})

@router.post("/node-version/install")
async def install_node_version_endpoint(
    request: Request,
    version: str = Form(...),
    user: dict = Depends(login_required)
):
    success, msg = install_node_version(version)
    log_action(user["username"], "InstallNodeVersion", f"Target: {version}, Result: {msg}", request.client.host)
    return JSONResponse({"success": success, "message": msg})

@router.post("/node-version/uninstall")
async def uninstall_node_version_endpoint(
    request: Request,
    version: str = Form(...),
    user: dict = Depends(login_required)
):
    success, msg = uninstall_node_version(version)
    log_action(user["username"], "UninstallNodeVersion", f"Target: {version}, Result: {msg}", request.client.host)
    return JSONResponse({"success": success, "message": msg})


