from fastapi import APIRouter, Request, Depends
from fastapi.responses import HTMLResponse
from app.auth import login_required
from app.services.system_service import get_system_stats, get_service_status
from app.database import get_db
from app.templates_util import render

router = APIRouter()

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

    return render("dashboard.html", {
        "user": user,
        "stats": stats,
        "services": services,
        "total_sites": total_sites,
        "recent_logs": recent_logs,
        "active_nav": "dashboard"
    }, request)

@router.get("/api/stats")
async def api_stats(user: dict = Depends(login_required)):
    return get_system_stats()
