import os
import shutil
from pathlib import Path
from typing import Optional
from fastapi import APIRouter, Request, Depends, Form, UploadFile, File, HTTPException
from fastapi.responses import HTMLResponse, RedirectResponse, JSONResponse, FileResponse

from app.auth import login_required
from app.database import get_db, log_action
from app.templates_util import render
from app.services.database_service import (
    is_mysql_available,
    get_database_sizes,
    create_db_and_user,
    drop_db_and_user,
    change_db_password,
    backup_db,
    restore_db,
    execute_query_console,
    get_database_tables,
    get_table_schema,
    get_table_data,
    execute_raw_sql,
    truncate_table,
    drop_table,
    optimize_table,
    BACKUP_DIR
)
from app.services.system_service import get_service_status

router = APIRouter(prefix="/databases")

@router.get("", response_class=HTMLResponse)
async def databases_view(request: Request, user: dict = Depends(login_required)):
    conn = get_db()
    cursor = conn.cursor()
    cursor.execute("SELECT * FROM databases ORDER BY id DESC")
    databases = [dict(row) for row in cursor.fetchall()]

    cursor.execute("SELECT * FROM db_backups ORDER BY id DESC LIMIT 50")
    backups = [dict(row) for row in cursor.fetchall()]
    conn.close()

    # Get live sizes from MySQL information_schema
    db_sizes = get_database_sizes()
    for db in databases:
        db["size"] = db_sizes.get(db["db_name"], "0.00 MB")

    mysql_status = get_service_status("mariadb") or get_service_status("mysql")
    server_ip = request.headers.get("host", "").split(":")[0] or "97.74.84.87"

    return render("databases.html", {
        "user": user,
        "databases": databases,
        "backups": backups,
        "mysql_status": mysql_status,
        "server_ip": server_ip,
        "active_nav": "databases"
    }, request)


@router.post("/create")
async def create_database_action(
    request: Request,
    db_name: str = Form(...),
    db_user: str = Form(...),
    db_pass: str = Form(...),
    db_host: str = Form("127.0.0.1"),
    charset: str = Form("utf8mb4"),
    note: str = Form(""),
    user: dict = Depends(login_required)
):
    db_name = db_name.strip().lower()
    db_user = db_user.strip()
    db_pass = db_pass.strip()

    if not db_name or not db_user or not db_pass:
        return JSONResponse({"success": False, "error": "Database name, user, and password are required"}, status_code=400)

    # Provision on MySQL / MariaDB
    success, msg = create_db_and_user(db_name, db_user, db_pass, db_host, charset)
    if not success:
        return JSONResponse({"success": False, "error": f"MySQL error: {msg}"}, status_code=500)

    # Record in SQLite
    conn = get_db()
    cursor = conn.cursor()
    try:
        cursor.execute(
            "INSERT INTO databases (db_name, db_user, db_pass, db_host, charset, note) VALUES (?, ?, ?, ?, ?, ?)",
            (db_name, db_user, db_pass, db_host, charset, note)
        )
        conn.commit()
    except Exception as e:
        conn.close()
        return JSONResponse({"success": False, "error": f"Database already registered: {e}"}, status_code=400)
    conn.close()

    log_action(user["username"], "CreateDatabase", f"Database: {db_name}, User: {db_user}", request.client.host)
    return JSONResponse({"success": True, "message": f"Database '{db_name}' created successfully!"})

@router.post("/{db_id}/delete")
async def delete_database_action(
    db_id: int,
    request: Request,
    user: dict = Depends(login_required)
):
    conn = get_db()
    cursor = conn.cursor()
    cursor.execute("SELECT * FROM databases WHERE id = ?", (db_id,))
    row = cursor.fetchone()
    if not row:
        conn.close()
        return JSONResponse({"success": False, "error": "Database record not found"}, status_code=404)

    db_name = row["db_name"]
    db_user = row["db_user"]
    db_host = row["db_host"]

    # Drop MySQL db and user
    success, msg = drop_db_and_user(db_name, db_user, db_host)
    if not success:
        conn.close()
        return JSONResponse({"success": False, "error": f"MySQL error: {msg}"}, status_code=500)

    cursor.execute("DELETE FROM databases WHERE id = ?", (db_id,))
    conn.commit()
    conn.close()

    log_action(user["username"], "DeleteDatabase", f"Deleted {db_name}", request.client.host)
    return JSONResponse({"success": True, "message": f"Database '{db_name}' dropped successfully."})

@router.post("/{db_id}/change-password")
async def change_password_action(
    db_id: int,
    request: Request,
    new_password: str = Form(...),
    user: dict = Depends(login_required)
):
    conn = get_db()
    cursor = conn.cursor()
    cursor.execute("SELECT * FROM databases WHERE id = ?", (db_id,))
    row = cursor.fetchone()
    if not row:
        conn.close()
        return JSONResponse({"success": False, "error": "Database record not found"}, status_code=404)

    db_user = row["db_user"]
    db_host = row["db_host"]

    success, msg = change_db_password(db_user, new_password.strip(), db_host)
    if not success:
        conn.close()
        return JSONResponse({"success": False, "error": f"MySQL error: {msg}"}, status_code=500)

    cursor.execute("UPDATE databases SET db_pass = ? WHERE id = ?", (new_password.strip(), db_id))
    conn.commit()
    conn.close()

    log_action(user["username"], "ChangeDbPassword", f"Changed password for {db_user}", request.client.host)
    return JSONResponse({"success": True, "message": f"Password for '{db_user}' updated successfully."})

@router.post("/{db_id}/backup")
async def backup_database_action(
    db_id: int,
    request: Request,
    user: dict = Depends(login_required)
):
    conn = get_db()
    cursor = conn.cursor()
    cursor.execute("SELECT * FROM databases WHERE id = ?", (db_id,))
    row = cursor.fetchone()
    if not row:
        conn.close()
        return JSONResponse({"success": False, "error": "Database record not found"}, status_code=404)

    db_name = row["db_name"]
    success, filename, file_path, size_bytes = backup_db(db_name)
    if not success:
        conn.close()
        return JSONResponse({"success": False, "error": filename}, status_code=500)

    cursor.execute(
        "INSERT INTO db_backups (db_name, filename, file_path, size_bytes) VALUES (?, ?, ?, ?)",
        (db_name, filename, file_path, size_bytes)
    )
    conn.commit()
    conn.close()

    log_action(user["username"], "BackupDatabase", f"Created backup {filename}", request.client.host)
    return JSONResponse({"success": True, "message": f"Backup '{filename}' created successfully!"})

@router.get("/backups/{backup_id}/download")
async def download_backup(backup_id: int, user: dict = Depends(login_required)):
    conn = get_db()
    cursor = conn.cursor()
    cursor.execute("SELECT * FROM db_backups WHERE id = ?", (backup_id,))
    row = cursor.fetchone()
    conn.close()

    if not row:
        raise HTTPException(status_code=404, detail="Backup record not found")

    file_path = Path(row["file_path"])
    if not file_path.exists():
        raise HTTPException(status_code=404, detail="Backup file not found on disk")

    return FileResponse(path=file_path, filename=row["filename"], media_type="application/sql")

@router.post("/backups/{backup_id}/restore")
async def restore_backup_action(
    backup_id: int,
    request: Request,
    user: dict = Depends(login_required)
):
    conn = get_db()
    cursor = conn.cursor()
    cursor.execute("SELECT * FROM db_backups WHERE id = ?", (backup_id,))
    row = cursor.fetchone()
    conn.close()

    if not row:
        return JSONResponse({"success": False, "error": "Backup record not found"}, status_code=404)

    success, msg = restore_db(row["db_name"], row["file_path"])
    if not success:
        return JSONResponse({"success": False, "error": msg}, status_code=500)

    log_action(user["username"], "RestoreDatabase", f"Restored {row['db_name']} from {row['filename']}", request.client.host)
    return JSONResponse({"success": True, "message": f"Database '{row['db_name']}' restored successfully."})

@router.post("/backups/{backup_id}/delete")
async def delete_backup_action(
    backup_id: int,
    request: Request,
    user: dict = Depends(login_required)
):
    conn = get_db()
    cursor = conn.cursor()
    cursor.execute("SELECT * FROM db_backups WHERE id = ?", (backup_id,))
    row = cursor.fetchone()
    if not row:
        conn.close()
        return JSONResponse({"success": False, "error": "Backup record not found"}, status_code=404)

    file_path = Path(row["file_path"])
    file_path.unlink(missing_ok=True)

    cursor.execute("DELETE FROM db_backups WHERE id = ?", (backup_id,))
    conn.commit()
    conn.close()

    return JSONResponse({"success": True, "message": "Backup file deleted."})

@router.post("/{db_id}/import-sql")
async def import_sql_file(
    db_id: int,
    request: Request,
    sql_file: UploadFile = File(...),
    user: dict = Depends(login_required)
):
    conn = get_db()
    cursor = conn.cursor()
    cursor.execute("SELECT * FROM databases WHERE id = ?", (db_id,))
    row = cursor.fetchone()
    conn.close()

    if not row:
        return JSONResponse({"success": False, "error": "Database not found"}, status_code=404)

    db_name = row["db_name"]
    temp_path = BACKUP_DIR / f"import_temp_{db_name}.sql"
    try:
        with open(temp_path, "wb") as buffer:
            shutil.copyfileobj(sql_file.file, buffer)

        success, msg = restore_db(db_name, str(temp_path))
        temp_path.unlink(missing_ok=True)

        if not success:
            return JSONResponse({"success": False, "error": f"Import failed: {msg}"}, status_code=500)

        log_action(user["username"], "ImportSQL", f"Imported into {db_name}", request.client.host)
        return JSONResponse({"success": True, "message": f"SQL file successfully imported into '{db_name}'!"})
    except Exception as e:
        temp_path.unlink(missing_ok=True)
        return JSONResponse({"success": False, "error": str(e)}, status_code=500)

@router.post("/query")
async def run_sql_query(
    request: Request,
    query: str = Form(...),
    db_name: Optional[str] = Form(None),
    user: dict = Depends(login_required)
):
    result = execute_query_console(query, db_name)
    log_action(user["username"], "SQLQuery", f"Executed query on {db_name or 'global'}", request.client.host)
    return JSONResponse(result)

@router.get("/{db_name}/manage", response_class=HTMLResponse)
async def database_manage_view(
    db_name: str,
    request: Request,
    table: Optional[str] = None,
    tab: Optional[str] = "structure",
    user: dict = Depends(login_required)
):
    conn = get_db()
    cursor = conn.cursor()
    cursor.execute("SELECT * FROM databases ORDER BY db_name ASC")
    all_dbs = [dict(r) for r in cursor.fetchall()]
    conn.close()

    # Get tables in db_name
    tables = get_database_tables(db_name)
    
    active_table = table
    if not active_table and tables:
        # If no table passed, stay on database overview or select first
        pass

    schema = []
    data_res = {"columns": [], "rows": [], "total_rows": 0, "page": 1, "total_pages": 1}
    if active_table:
        schema = get_table_schema(db_name, active_table)
        data_res = get_table_data(db_name, active_table, page=1, page_size=25)

    return render("database_manager.html", {
        "user": user,
        "db_name": db_name,
        "all_dbs": all_dbs,
        "tables": tables,
        "active_table": active_table,
        "active_tab": tab,
        "schema": schema,
        "data": data_res,
        "active_nav": "databases"
    }, request)

@router.get("/api/{db_name}/tables")
async def api_get_tables(db_name: str, user: dict = Depends(login_required)):
    tables = get_database_tables(db_name)
    return JSONResponse({"success": True, "tables": tables})

@router.get("/api/{db_name}/table/{table_name}/schema")
async def api_get_table_schema(db_name: str, table_name: str, user: dict = Depends(login_required)):
    schema = get_table_schema(db_name, table_name)
    return JSONResponse({"success": True, "schema": schema})

@router.get("/api/{db_name}/table/{table_name}/data")
async def api_get_table_data(
    db_name: str, 
    table_name: str, 
    page: int = 1, 
    page_size: int = 25, 
    search: str = "",
    user: dict = Depends(login_required)
):
    data = get_table_data(db_name, table_name, page=page, page_size=page_size, search=search)
    return JSONResponse({"success": True, **data})

@router.post("/api/{db_name}/execute")
async def api_execute_query(
    db_name: str,
    request: Request,
    query: str = Form(...),
    user: dict = Depends(login_required)
):
    result = execute_raw_sql(db_name, query)
    log_action(user["username"], "SQLExecute", f"Ran custom query in {db_name}", request.client.host)
    return JSONResponse(result)

@router.post("/api/{db_name}/table/{table_name}/truncate")
async def api_truncate_table(
    db_name: str, 
    table_name: str, 
    request: Request,
    user: dict = Depends(login_required)
):
    success, msg = truncate_table(db_name, table_name)
    log_action(user["username"], "TruncateTable", f"Truncated {db_name}.{table_name}", request.client.host)
    return JSONResponse({"success": success, "message": msg})

@router.post("/api/{db_name}/table/{table_name}/drop")
async def api_drop_table(
    db_name: str, 
    table_name: str, 
    request: Request,
    user: dict = Depends(login_required)
):
    success, msg = drop_table(db_name, table_name)
    log_action(user["username"], "DropTable", f"Dropped {db_name}.{table_name}", request.client.host)
    return JSONResponse({"success": success, "message": msg})

@router.post("/api/{db_name}/table/{table_name}/optimize")
async def api_optimize_table(
    db_name: str, 
    table_name: str, 
    request: Request,
    user: dict = Depends(login_required)
):
    success, msg = optimize_table(db_name, table_name)
    return JSONResponse({"success": success, "message": msg})

@router.get("/{db_name}/export")
async def export_database_dump(db_name: str, user: dict = Depends(login_required)):
    success, filename, full_path, size_bytes = backup_db(db_name)
    if not success or not Path(full_path).exists():
        raise HTTPException(status_code=500, detail="Failed to generate database export dump")
    return FileResponse(
        path=full_path, 
        filename=filename, 
        media_type="application/sql"
    )

