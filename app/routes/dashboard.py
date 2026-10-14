from fastapi import APIRouter, Request, Depends
from fastapi.responses import HTMLResponse
from fastapi.templating import Jinja2Templates
from pathlib import Path
from app.auth import login_required
from app.services.system_service import get_system_stats, get_service_status
from app.database import get_db

router = APIRouter()
templates = Jinja2Templates(directory=str(Path(__file__).parent.parent / "templates"))

@router.get("/", response_class=HTMLResponse)
async def dashboard_view(request: Request, user: dict = Depends(login_required)):
    stats = get_system_stats()
    
    # Check common services
    services = {
        "Nginx": get_service_status("nginx"),
        "PHP-FPM": get_service_status("php8.3-fpm") or get_service_status("php8.2-fpm"),
        "MariaDB / MySQL": get_service_status("mariadb") or get_service_status("mysql"),
        "UFW Firewall": get_service_status("ufw"),
    }
    
    conn = get_db()
    cursor = conn.cursor()
    cursor.execute("SELECT COUNT(*) FROM websites")
    total_sites = cursor.fetchone()[0]
    cursor.execute("SELECT * FROM audit_logs ORDER BY id DESC LIMIT 5")
    recent_logs = [dict(row) for row in cursor.fetchall()]
    conn.close()

    return templates.TemplateResponse("dashboard.html", {
        "request": request,
        "user": user,
        "stats": stats,
        "services": services,
        "total_sites": total_sites,
        "recent_logs": recent_logs,
        "active_nav": "dashboard"
    })

@router.get("/api/stats")
async def api_stats(user: dict = Depends(login_required)):
    return get_system_stats()
