import os
import re
import shutil
import subprocess
from pathlib import Path
from datetime import datetime
from typing import Tuple, List, Dict, Any, Optional

BACKUP_DIR = Path("/www/backup/database")
BACKUP_DIR.mkdir(parents=True, exist_ok=True)

def is_mysql_available() -> bool:
    return bool(shutil.which("mysql") or shutil.which("mariadb"))

def run_mysql_command(query: str, db_name: Optional[str] = None) -> Tuple[bool, str]:
    if not is_mysql_available():
        return False, "MariaDB/MySQL is not installed on this system."

    cmd = ["mysql", "-u", "root"]
    if db_name:
        cmd.append(db_name)
    cmd.extend(["-e", query])

    try:
        res = subprocess.run(cmd, stdout=subprocess.PIPE, stderr=subprocess.PIPE, text=True, timeout=30)
        if res.returncode != 0:
            err = res.stderr.strip() or res.stdout.strip()
            return False, err
        return True, res.stdout.strip()
    except subprocess.TimeoutExpired:
        return False, "Query timed out after 30 seconds."
    except Exception as e:
        return False, str(e)

def get_database_sizes() -> Dict[str, str]:
    sizes = {}
    if not is_mysql_available():
        return sizes

    query = """
    SELECT table_schema AS db_name, 
           ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS size_mb 
    FROM information_schema.TABLES 
    GROUP BY table_schema;
    """
    success, output = run_mysql_command(query)
    if not success or not output:
        return sizes

    lines = output.splitlines()
    if len(lines) > 1:
        for line in lines[1:]:
            parts = line.split()
            if len(parts) >= 2:
                sizes[parts[0]] = f"{parts[1]} MB"
    return sizes

def create_db_and_user(
    db_name: str,
    db_user: str,
    db_pass: str,
    db_host: str = "127.0.0.1",
    charset: str = "utf8mb4"
) -> Tuple[bool, str]:
    # Sanitize identifier names
    if not re.match(r'^[a-zA-Z0-9_]+$', db_name):
        return False, "Database name can only contain letters, numbers, and underscores."
    if not re.match(r'^[a-zA-Z0-9_]+$', db_user):
        return False, "Username can only contain letters, numbers, and underscores."

    # Escaping password for MySQL statement
    escaped_pass = db_pass.replace("'", "\\'")
    
    collate = "utf8mb4_unicode_ci" if charset == "utf8mb4" else "utf8_general_ci"
    
    sql = f"""
    CREATE DATABASE IF NOT EXISTS `{db_name}` CHARACTER SET {charset} COLLATE {collate};
    CREATE USER IF NOT EXISTS '{db_user}'@'{db_host}' IDENTIFIED BY '{escaped_pass}';
    GRANT ALL PRIVILEGES ON `{db_name}`.* TO '{db_user}'@'{db_host}';
    FLUSH PRIVILEGES;
    """
    
    # If host is not localhost, also grant for localhost to allow local web apps (like WordPress/Laravel)
    if db_host not in ["127.0.0.1", "localhost"]:
        sql += f"""
        CREATE USER IF NOT EXISTS '{db_user}'@'localhost' IDENTIFIED BY '{escaped_pass}';
        GRANT ALL PRIVILEGES ON `{db_name}`.* TO '{db_user}'@'localhost';
        FLUSH PRIVILEGES;
        """

    return run_mysql_command(sql)

def drop_db_and_user(db_name: str, db_user: Optional[str] = None, db_host: str = "127.0.0.1") -> Tuple[bool, str]:
    if not re.match(r'^[a-zA-Z0-9_]+$', db_name):
        return False, "Invalid database name"

    sql = f"DROP DATABASE IF EXISTS `{db_name}`;"
    if db_user and re.match(r'^[a-zA-Z0-9_]+$', db_user):
        sql += f"DROP USER IF EXISTS '{db_user}'@'{db_host}';"
        if db_host != 'localhost':
            sql += f"DROP USER IF EXISTS '{db_user}'@'localhost';"
    sql += "FLUSH PRIVILEGES;"

    return run_mysql_command(sql)

def change_db_password(db_user: str, new_pass: str, db_host: str = "127.0.0.1") -> Tuple[bool, str]:
    if not re.match(r'^[a-zA-Z0-9_]+$', db_user):
        return False, "Invalid username"

    escaped_pass = new_pass.replace("'", "\\'")
    sql = f"ALTER USER '{db_user}'@'{db_host}' IDENTIFIED BY '{escaped_pass}'; FLUSH PRIVILEGES;"
    if db_host != 'localhost':
        sql += f"ALTER USER '{db_user}'@'localhost' IDENTIFIED BY '{escaped_pass}'; FLUSH PRIVILEGES;"

    return run_mysql_command(sql)

def backup_db(db_name: str) -> Tuple[bool, str, str, int]:
    """Create mysqldump backup. Returns (success, filename, full_path, size_bytes)."""
    if not re.match(r'^[a-zA-Z0-9_]+$', db_name):
        return False, "Invalid database name", "", 0

    BACKUP_DIR.mkdir(parents=True, exist_ok=True)
    timestamp = datetime.now().strftime("%Y%m%d_%H%M%S")
    filename = f"{db_name}_{timestamp}.sql"
    out_file = BACKUP_DIR / filename

    cmd = ["mysqldump", "-u", "root", "--routines", "--triggers", db_name]
    try:
        with open(out_file, "w", encoding="utf-8") as f:
            res = subprocess.run(cmd, stdout=f, stderr=subprocess.PIPE, text=True, timeout=120)
            if res.returncode != 0:
                err = res.stderr.strip()
                out_file.unlink(missing_ok=True)
                return False, f"mysqldump failed: {err}", "", 0

        size_bytes = out_file.stat().st_size
        return True, filename, str(out_file), size_bytes
    except Exception as e:
        out_file.unlink(missing_ok=True)
        return False, str(e), "", 0

def restore_db(db_name: str, sql_file_path: str) -> Tuple[bool, str]:
    """Restore database from .sql file."""
    if not re.match(r'^[a-zA-Z0-9_]+$', db_name):
        return False, "Invalid database name"

    sql_path = Path(sql_file_path)
    if not sql_path.exists():
        return False, "Backup file not found."

    cmd = ["mysql", "-u", "root", db_name]
    try:
        with open(sql_path, "r", encoding="utf-8", errors="replace") as f:
            res = subprocess.run(cmd, stdin=f, stdout=subprocess.PIPE, stderr=subprocess.PIPE, text=True, timeout=180)
            if res.returncode != 0:
                return False, res.stderr.strip() or "Import failed"
        return True, "Database restored successfully."
    except Exception as e:
        return False, str(e)

def execute_query_console(query: str, db_name: Optional[str] = None) -> Dict[str, Any]:
    """Execute arbitrary SQL query for the embedded Web SQL console."""
    if not is_mysql_available():
        return {"success": False, "error": "MariaDB/MySQL server is not available"}

    clean_query = query.strip()
    if not clean_query:
        return {"success": False, "error": "Query cannot be empty"}

    cmd = ["mysql", "-u", "root", "--table"]
    if db_name:
        cmd.append(db_name)
    cmd.extend(["-e", clean_query])

    try:
        res = subprocess.run(cmd, stdout=subprocess.PIPE, stderr=subprocess.PIPE, text=True, timeout=20)
        if res.returncode != 0:
            return {"success": False, "error": res.stderr.strip() or res.stdout.strip()}
        return {"success": True, "output": res.stdout.strip() or "Query OK (0 rows affected)"}
    except Exception as e:
        return {"success": False, "error": str(e)}

def is_phpmyadmin_ready() -> bool:
    return Path("/var/www/phpmyadmin/index.php").exists()

def setup_phpmyadmin_service() -> Tuple[bool, str]:
    if os.name == 'nt':
        return True, "Simulation: phpMyAdmin configured on port 888"

    from app.config import BASE_DIR
    bundled_file = BASE_DIR / "app" / "static" / "adminer.php"
    
    script = f"""#!/usr/bin/env bash
set -e
DEBIAN_FRONTEND=noninteractive apt-get update -y >/dev/null 2>&1 || true
DEBIAN_FRONTEND=noninteractive apt-get install -y php-fpm php-mysql php-mbstring php-zip php-gd php-curl php-xml >/dev/null 2>&1 || true

PHP_SOCK=$(find /run/php/ -name "php*-fpm.sock" 2>/dev/null | sort -V | tail -n 1)
if [[ -z "$PHP_SOCK" ]]; then
    PHP_SOCK="/run/php/php8.3-fpm.sock"
fi

mkdir -p /var/www/phpmyadmin
if [[ -f "{bundled_file}" ]]; then
    cp -f "{bundled_file}" /var/www/phpmyadmin/index.php
fi

chown -R www-data:www-data /var/www/phpmyadmin 2>/dev/null || true
chmod -R 755 /var/www/phpmyadmin 2>/dev/null || true

cat > /etc/nginx/sites-available/phpmyadmin.conf << 'EOF'
server {{
    listen 888 default_server;
    listen [::]:888 default_server;
    server_name _;
    root /var/www/phpmyadmin;
    index index.php index.html index.htm;

    location / {{
        try_files $uri $uri/ /index.php?$query_string;
    }}

    location ~ \\.php$ {{
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:PHP_SOCK_PLACEHOLDER;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    }}
}}
EOF
sed -i "s|PHP_SOCK_PLACEHOLDER|${{PHP_SOCK}}|g" /etc/nginx/sites-available/phpmyadmin.conf
ln -sf /etc/nginx/sites-available/phpmyadmin.conf /etc/nginx/sites-enabled/phpmyadmin.conf
ufw allow 888/tcp 2>/dev/null || true
nginx -t && (systemctl reload nginx || systemctl restart nginx)
"""
    try:
        res = subprocess.run(["bash", "-c", script], stdout=subprocess.PIPE, stderr=subprocess.PIPE, text=True, timeout=120)
        if res.returncode != 0:
            return False, res.stderr or res.stdout
        return True, "Database manager configured on port 888"
    except Exception as e:
        return False, str(e)

