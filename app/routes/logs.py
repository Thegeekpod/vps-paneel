import csv
import io
from fastapi import APIRouter, Request, Depends, Form, Query
from fastapi.responses import HTMLResponse, RedirectResponse, Response
from app.auth import login_required
from app.database import get_db, log_action
from app.templates_util import render

router = APIRouter(prefix="/logs")

@router.get("", response_class=HTMLResponse)
async def logs_view(
    request: Request,
    action: str = Query(None),
    search: str = Query(None),
    limit: int = Query(100),
    user: dict = Depends(login_required)
):
    conn = get_db()
    cursor = conn.cursor()

    query = "SELECT * FROM audit_logs WHERE 1=1"
    params = []

    if action and action.strip():
        query += " AND action = ?"
        params.append(action.strip())

    if search and search.strip():
        query += " AND (details LIKE ? OR username LIKE ? OR ip_address LIKE ?)"
        term = f"%{search.strip()}%"
        params.extend([term, term, term])

    query += " ORDER BY id DESC LIMIT ?"
    params.append(min(max(limit, 10), 500))

    cursor.execute(query, params)
    logs = [dict(row) for row in cursor.fetchall()]

    # Fetch unique actions for filter dropdown
    cursor.execute("SELECT DISTINCT action FROM audit_logs ORDER BY action ASC")
    available_actions = [row["action"] for row in cursor.fetchall()]

    # Total count
    cursor.execute("SELECT COUNT(*) FROM audit_logs")
    total_logs_count = cursor.fetchone()[0]

    conn.close()

    return render("logs.html", {
        "user": user,
        "logs": logs,
        "selected_action": action or "",
        "search_term": search or "",
        "available_actions": available_actions,
        "total_logs_count": total_logs_count,
        "active_nav": "logs"
    }, request)

@router.post("/clear")
async def clear_logs_action(request: Request, user: dict = Depends(login_required)):
    conn = get_db()
    cursor = conn.cursor()
    cursor.execute("DELETE FROM audit_logs")
    conn.commit()
    conn.close()

    log_action(user["username"], "ClearLogs", "Admin purged all audit and operation logs", request.client.host)
    return RedirectResponse(url="/logs?cleared=1", status_code=303)

@router.get("/export")
async def export_logs_csv(user: dict = Depends(login_required)):
    conn = get_db()
    cursor = conn.cursor()
    cursor.execute("SELECT id, created_at, username, action, details, ip_address FROM audit_logs ORDER BY id DESC")
    rows = cursor.fetchall()
    conn.close()

    output = io.StringIO()
    writer = csv.writer(output)
    writer.writerow(["ID", "Timestamp", "User", "Action", "Details", "IP Address"])
    for r in rows:
        writer.writerow([r["id"], r["created_at"], r["username"], r["action"], r["details"], r["ip_address"]])

    csv_data = output.getvalue()
    return Response(
        content=csv_data,
        media_type="text/csv",
        headers={"Content-Disposition": "attachment; filename=vps_panel_audit_logs.csv"}
    )
