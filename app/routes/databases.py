import os
import shutil
from pathlib import Path
from typing import Optional
from fastapi import APIRouter, Request, Depends, Form, UploadFile, File, HTTPException
import asyncio
import subprocess
from fastapi.responses import HTMLResponse, RedirectResponse, JSONResponse, FileResponse, StreamingResponse

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
    is_phpmyadmin_installed,
    install_phpmyadmin,
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
    pma_installed = is_phpmyadmin_installed()
    pma_url = f"http://{server_ip}:888"

    return render("databases.html", {
        "user": user,
        "databases": databases,
        "backups": backups,
        "mysql_status": mysql_status,
        "server_ip": server_ip,
        "pma_installed": pma_installed,
        "pma_url": pma_url,
        "active_nav": "databases"
    }, request)

@router.get("/stream-install-pma")
async def stream_install_pma_action(request: Request, user: dict = Depends(login_required)):
    server_ip = request.headers.get("host", "").split(":")[0] or "97.74.84.87"

    async def run_installer_stream():
        script = f"""#!/usr/bin/env bash
set -e
exec 2>&1

echo "===================================================================="
echo " [TERMINAL] Starting Official phpMyAdmin 5.2.3 Setup"
echo " Time: $(date)"
echo " Server Host: {server_ip}"
echo " Source: https://www.phpmyadmin.net/downloads/"
echo "===================================================================="

PMA_DIR="/var/www/phpmyadmin"

echo "[-] 1/5 Checking PHP dependencies and FastCGI..."
DEBIAN_FRONTEND=noninteractive apt-get update -y >/dev/null 2>&1 || true
DEBIAN_FRONTEND=noninteractive apt-get install -y php-fpm php-mysql php-mbstring php-zip php-gd php-curl php-xml curl tar gzip >/dev/null 2>&1 || true

PHP_SOCK=$(find /run/php/ -name "php*-fpm.sock" 2>/dev/null | sort -V | tail -n 1)
if [[ -z "$PHP_SOCK" ]]; then PHP_SOCK="/run/php/php8.3-fpm.sock"; fi
echo "[+] PHP FastCGI socket: ${{PHP_SOCK}}"

echo "[-] 2/5 Downloading official phpMyAdmin from files.phpmyadmin.net..."
PMA_TAR="/tmp/phpmyadmin-download.tar.gz"
rm -f "$PMA_TAR"

if curl -# -L "https://files.phpmyadmin.net/phpMyAdmin/5.2.3/phpMyAdmin-5.2.3-all-languages.tar.gz" -o "$PMA_TAR"; then
    echo "[+] Downloaded phpMyAdmin 5.2.3 archive ($(du -h "$PMA_TAR" | cut -f1))"
elif curl -# -L "https://files.phpmyadmin.net/phpMyAdmin/5.2.2/phpMyAdmin-5.2.2-all-languages.tar.gz" -o "$PMA_TAR"; then
    echo "[+] Downloaded phpMyAdmin 5.2.2 archive ($(du -h "$PMA_TAR" | cut -f1))"
else
    echo "[!] Downloading phpMyAdmin 5.2.1..."
    curl -# -L "https://files.phpmyadmin.net/phpMyAdmin/5.2.1/phpMyAdmin-5.2.1-all-languages.tar.gz" -o "$PMA_TAR"
fi

if [[ ! -f "$PMA_TAR" || ! -s "$PMA_TAR" ]]; then
    echo "[FATAL ERROR] Download failed from phpmyadmin.net!"
    exit 1
fi

echo "[-] 3/5 Extracting files into ${{PMA_DIR}}..."
rm -rf "$PMA_DIR"
mkdir -p "$PMA_DIR"
tar -xzf "$PMA_TAR" -C "$PMA_DIR" --strip-components=1
rm -f "$PMA_TAR"
echo "[+] Extraction completed."

echo "[-] 4/5 Generating config.inc.php with Blowfish encryption secret..."
BLOWFISH=$(openssl rand -hex 16 2>/dev/null || echo "vpspanelblowfishsecretkey123456")
cat > "$PMA_DIR/config.inc.php" << 'EOF_PMA'
<?php
declare(strict_types=1);

$cfg['blowfish_secret'] = 'BLOWFISH_KEY_PLACEHOLDER';

$i = 0;
$i++;
$cfg['Servers'][$i]['auth_type'] = 'cookie';
$cfg['Servers'][$i]['host'] = 'localhost';
$cfg['Servers'][$i]['compress'] = false;
$cfg['Servers'][$i]['AllowNoPassword'] = true;

$cfg['UploadDir'] = '';
$cfg['SaveDir'] = '';
$cfg['TempDir'] = '/var/www/phpmyadmin/tmp';
EOF_PMA
sed -i "s|BLOWFISH_KEY_PLACEHOLDER|${{BLOWFISH}}|g" "$PMA_DIR/config.inc.php"

mkdir -p "$PMA_DIR/tmp"
chmod 777 "$PMA_DIR/tmp"
mkdir -p /var/www/html
ln -sfn "$PMA_DIR" /var/www/html/phpmyadmin 2>/dev/null || true
chown -R www-data:www-data "$PMA_DIR" 2>/dev/null || true
chmod -R 755 "$PMA_DIR" 2>/dev/null || true
echo "[+] Permissions set & Blowfish secret generated."

echo "[-] 5/5 Configuring Nginx FastCGI on Port 888..."
cat > /etc/nginx/sites-available/phpmyadmin.conf << 'EOF_NGINX'
server {{
    listen 888 default_server;
    listen [::]:888 default_server;
    server_name _;
    root /var/www/phpmyadmin;
    index index.php index.html index.htm;

    client_max_body_size 256M;

    location / {{
        try_files $uri $uri/ /index.php?$query_string;
    }}

    location ~ \\.php$ {{
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:PHP_SOCK_PLACEHOLDER;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    }}

    location ~ /\\.ht {{
        deny all;
    }}
}}
EOF_NGINX
sed -i "s|PHP_SOCK_PLACEHOLDER|${{PHP_SOCK}}|g" /etc/nginx/sites-available/phpmyadmin.conf
ln -sf /etc/nginx/sites-available/phpmyadmin.conf /etc/nginx/sites-enabled/phpmyadmin.conf
ufw allow 888/tcp 2>/dev/null || true

nginx -t
systemctl reload nginx || systemctl restart nginx
echo "[+] Nginx configuration verified & reloaded on Port 888."

echo "===================================================================="
echo " [SUCCESS] Official phpMyAdmin is INSTALLED and READY!"
echo " Access URL: http://{server_ip}:888"
echo "===================================================================="
"""
        proc = subprocess.Popen(["bash", "-c", script], stdout=subprocess.PIPE, stderr=subprocess.STDOUT, text=True, bufsize=1)
        for line in iter(proc.stdout.readline, ''):
            yield f"data: {line}\n\n"
            await asyncio.sleep(0.01)
        proc.stdout.close()
        proc.wait()
        if proc.returncode == 0:
            yield "data: [DONE_SUCCESS]\n\n"
        else:
            yield f"data: [DONE_ERROR_CODE_{proc.returncode}]\n\n"

    return StreamingResponse(run_installer_stream(), media_type="text/event-stream")

@router.post("/install-pma")
async def install_pma_action(request: Request, user: dict = Depends(login_required)):
    success, msg = install_phpmyadmin()
    log_action(user["username"], "InstallPMA", f"Status: {success}, msg: {msg}", request.client.host)
    return JSONResponse({"success": success, "message": msg})


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

