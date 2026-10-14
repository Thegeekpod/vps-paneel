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
def is_phpmyadmin_installed() -> bool:
    pma = Path("/var/www/phpmyadmin")
    return (pma / "index.php").exists() and (pma / "libraries").exists()

def install_phpmyadmin() -> Tuple[bool, str]:
    """Download official phpMyAdmin from phpmyadmin.net/downloads and configure on port 888."""
    if os.name == 'nt':
        return True, "Simulation: phpMyAdmin installed"

    script = """#!/usr/bin/env bash
set -e
PMA_DIR="/var/www/phpmyadmin"

echo "[-] Installing PHP-FPM and required extensions..."
DEBIAN_FRONTEND=noninteractive apt-get update -y >/dev/null 2>&1 || true
DEBIAN_FRONTEND=noninteractive apt-get install -y php-fpm php-mysql php-mbstring php-zip php-gd php-curl php-xml curl tar gzip >/dev/null 2>&1 || true

PHP_SOCK=$(find /run/php/ -name "php*-fpm.sock" 2>/dev/null | sort -V | tail -n 1)
if [[ -z "$PHP_SOCK" ]]; then
    PHP_SOCK="/run/php/php8.3-fpm.sock"
fi

echo "[-] Downloading official phpMyAdmin from files.phpmyadmin.net..."
PMA_TAR="/tmp/phpmyadmin-download.tar.gz"
rm -f "$PMA_TAR"

curl -sSL "https://files.phpmyadmin.net/phpMyAdmin/5.2.3/phpMyAdmin-5.2.3-all-languages.tar.gz" -o "$PMA_TAR" 2>/dev/null || \
curl -sSL "https://files.phpmyadmin.net/phpMyAdmin/5.2.2/phpMyAdmin-5.2.2-all-languages.tar.gz" -o "$PMA_TAR" 2>/dev/null || \
curl -sSL "https://files.phpmyadmin.net/phpMyAdmin/5.2.1/phpMyAdmin-5.2.1-all-languages.tar.gz" -o "$PMA_TAR" 2>/dev/null

if [[ ! -f "$PMA_TAR" || ! -s "$PMA_TAR" ]]; then
    echo "ERROR: Failed to download phpMyAdmin archive" >&2
    exit 1
fi

echo "[-] Extracting phpMyAdmin..."
rm -rf "$PMA_DIR"
mkdir -p "$PMA_DIR"
tar -xzf "$PMA_TAR" -C "$PMA_DIR" --strip-components=1
rm -f "$PMA_TAR"

echo "[-] Generating config.inc.php with blowfish secret..."
BLOWFISH=$(openssl rand -hex 16 2>/dev/null || echo "vpspanelblowfishsecretkey123456")
cat > "$PMA_DIR/config.inc.php" << EOF
<?php
declare(strict_types=1);

\$cfg['blowfish_secret'] = '${BLOWFISH}';

\$i = 0;
\$i++;
\$cfg['Servers'][\$i]['auth_type'] = 'cookie';
\$cfg['Servers'][\$i]['host'] = 'localhost';
\$cfg['Servers'][\$i]['compress'] = false;
\$cfg['Servers'][\$i]['AllowNoPassword'] = true;

\$cfg['UploadDir'] = '';
\$cfg['SaveDir'] = '';
\$cfg['TempDir'] = '/var/www/phpmyadmin/tmp';
EOF

mkdir -p "$PMA_DIR/tmp"
chmod 777 "$PMA_DIR/tmp"

mkdir -p /var/www/html
ln -sfn "$PMA_DIR" /var/www/html/phpmyadmin 2>/dev/null || true

chown -R www-data:www-data "$PMA_DIR" 2>/dev/null || true
chmod -R 755 "$PMA_DIR" 2>/dev/null || true

echo "[-] Configuring Nginx on Port 888..."
cat > /etc/nginx/sites-available/phpmyadmin.conf << 'EOF'
server {
    listen 888 default_server;
    listen [::]:888 default_server;
    server_name _;
    root /var/www/phpmyadmin;
    index index.php index.html index.htm;

    client_max_body_size 256M;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:PHP_SOCK_PLACEHOLDER;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    }

    location ~ /\.ht {
        deny all;
    }
}
EOF

sed -i "s|PHP_SOCK_PLACEHOLDER|${PHP_SOCK}|g" /etc/nginx/sites-available/phpmyadmin.conf
ln -sf /etc/nginx/sites-available/phpmyadmin.conf /etc/nginx/sites-enabled/phpmyadmin.conf
ufw allow 888/tcp 2>/dev/null || true

nginx -t
systemctl reload nginx || systemctl restart nginx
"""
    try:
        res = subprocess.run(["bash", "-c", script], stdout=subprocess.PIPE, stderr=subprocess.PIPE, text=True, timeout=180)
        if res.returncode != 0:
            return False, res.stderr or res.stdout
        return True, "phpMyAdmin installed successfully on Port 888"
    except Exception as e:
        return False, str(e)

def get_database_tables(db_name: str) -> List[Dict[str, Any]]:
    """Fetch tables list with engine, row count, collation and size for a database."""
    if not re.match(r'^[a-zA-Z0-9_]+$', db_name):
        return []

    sql = f"""
    SELECT 
        TABLE_NAME, 
        IFNULL(ENGINE, 'InnoDB') as ENGINE, 
        IFNULL(TABLE_ROWS, 0) as TABLE_ROWS, 
        IFNULL(DATA_LENGTH, 0) as DATA_LENGTH, 
        IFNULL(INDEX_LENGTH, 0) as INDEX_LENGTH, 
        IFNULL(TABLE_COLLATION, 'utf8mb4_unicode_ci') as TABLE_COLLATION, 
        IFNULL(TABLE_COMMENT, '') as TABLE_COMMENT 
    FROM information_schema.TABLES 
    WHERE TABLE_SCHEMA = '{db_name}' 
    ORDER BY TABLE_NAME ASC;
    """
    cmd = ["mysql", "-u", "root", "--batch", "--raw", "-e", sql]
    try:
        res = subprocess.run(cmd, stdout=subprocess.PIPE, stderr=subprocess.PIPE, text=True, timeout=10)
        if res.returncode != 0:
            return []
        lines = [l for l in res.stdout.strip().split("\n") if l.strip()]
        if len(lines) < 2:
            return []
        
        tables = []
        for line in lines[1:]:
            parts = line.split("\t")
            if len(parts) >= 7:
                data_len = int(parts[3]) if parts[3].isdigit() else 0
                idx_len = int(parts[4]) if parts[4].isdigit() else 0
                total_mb = (data_len + idx_len) / (1024 * 1024)
                tables.append({
                    "name": parts[0],
                    "engine": parts[1],
                    "rows": int(parts[2]) if parts[2].isdigit() else 0,
                    "data_size": f"{data_len / 1024:.1f} KB" if data_len < 1048576 else f"{data_len / 1048576:.2f} MB",
                    "index_size": f"{idx_len / 1024:.1f} KB" if idx_len < 1048576 else f"{idx_len / 1048576:.2f} MB",
                    "total_size": f"{total_mb:.2f} MB" if total_mb >= 0.1 else f"{(data_len + idx_len) / 1024:.1f} KB",
                    "collation": parts[5],
                    "comment": parts[6]
                })
        return tables
    except Exception:
        return []

def get_table_schema(db_name: str, table_name: str) -> List[Dict[str, Any]]:
    """Fetch column structure for a given table."""
    if not re.match(r'^[a-zA-Z0-9_]+$', db_name) or not re.match(r'^[a-zA-Z0-9_]+$', table_name):
        return []

    sql = f"""
    SELECT 
        COLUMN_NAME, 
        COLUMN_TYPE, 
        IS_NULLABLE, 
        COLUMN_KEY, 
        IFNULL(COLUMN_DEFAULT, 'NULL') as COLUMN_DEFAULT, 
        EXTRA, 
        IFNULL(COLLATION_NAME, '') as COLLATION_NAME, 
        IFNULL(COLUMN_COMMENT, '') as COLUMN_COMMENT 
    FROM information_schema.COLUMNS 
    WHERE TABLE_SCHEMA = '{db_name}' AND TABLE_NAME = '{table_name}' 
    ORDER BY ORDINAL_POSITION ASC;
    """
    cmd = ["mysql", "-u", "root", "--batch", "--raw", "-e", sql]
    try:
        res = subprocess.run(cmd, stdout=subprocess.PIPE, stderr=subprocess.PIPE, text=True, timeout=10)
        if res.returncode != 0:
            return []
        lines = [l for l in res.stdout.strip().split("\n") if l.strip()]
        if len(lines) < 2:
            return []
        
        cols = []
        for line in lines[1:]:
            parts = line.split("\t")
            if len(parts) >= 8:
                cols.append({
                    "name": parts[0],
                    "type": parts[1],
                    "nullable": parts[2],
                    "key": parts[3],
                    "default": parts[4],
                    "extra": parts[5],
                    "collation": parts[6],
                    "comment": parts[7]
                })
        return cols
    except Exception:
        return []

def get_table_data(db_name: str, table_name: str, page: int = 1, page_size: int = 25, search: str = "") -> Dict[str, Any]:
    """Fetch paginated rows from a table."""
    if not re.match(r'^[a-zA-Z0-9_]+$', db_name) or not re.match(r'^[a-zA-Z0-9_]+$', table_name):
        return {"columns": [], "rows": [], "total_rows": 0, "page": 1, "total_pages": 1}

    offset = max(0, (page - 1) * page_size)
    
    # Get total count
    count_sql = f"SELECT COUNT(*) FROM `{db_name}`.`{table_name}`;"
    total_rows = 0
    try:
        res_count = subprocess.run(["mysql", "-u", "root", "--batch", "--raw", "-e", count_sql], stdout=subprocess.PIPE, stderr=subprocess.PIPE, text=True, timeout=5)
        if res_count.returncode == 0:
            lines = res_count.stdout.strip().split("\n")
            if len(lines) >= 2 and lines[1].strip().isdigit():
                total_rows = int(lines[1].strip())
    except Exception:
        pass

    # Query rows
    data_sql = f"SELECT * FROM `{db_name}`.`{table_name}` LIMIT {page_size} OFFSET {offset};"
    cmd = ["mysql", "-u", "root", "--batch", "--raw", "-e", data_sql]
    try:
        start_time = datetime.now()
        res = subprocess.run(cmd, stdout=subprocess.PIPE, stderr=subprocess.PIPE, text=True, timeout=15)
        exec_duration = (datetime.now() - start_time).total_seconds()
        if res.returncode != 0:
            return {"columns": [], "rows": [], "total_rows": total_rows, "page": page, "total_pages": 1, "error": res.stderr.strip()}

        raw_lines = res.stdout.split("\n")
        lines = [l for l in raw_lines if l != ""]
        if not lines:
            # Query succeeded but 0 rows; get column names
            cols_schema = get_table_schema(db_name, table_name)
            return {
                "columns": [c["name"] for c in cols_schema],
                "rows": [],
                "total_rows": total_rows,
                "page": page,
                "total_pages": max(1, (total_rows + page_size - 1) // page_size),
                "duration": f"{exec_duration:.4f}"
            }

        columns = lines[0].split("\t")
        rows = [line.split("\t") for line in lines[1:]]

        total_pages = max(1, (total_rows + page_size - 1) // page_size)
        return {
            "columns": columns,
            "rows": rows,
            "total_rows": total_rows,
            "page": page,
            "total_pages": total_pages,
            "duration": f"{exec_duration:.4f}"
        }
    except Exception as e:
        return {"columns": [], "rows": [], "total_rows": total_rows, "page": page, "total_pages": 1, "error": str(e)}

def execute_raw_sql(db_name: str, sql: str) -> Dict[str, Any]:
    """Execute raw SQL in db_name and return structured rows or affected count."""
    if not re.match(r'^[a-zA-Z0-9_]+$', db_name):
        return {"success": False, "error": "Invalid database name"}

    clean_sql = sql.strip()
    if not clean_sql:
        return {"success": False, "error": "Query cannot be empty"}

    cmd = ["mysql", "-u", "root", "--batch", "--raw", db_name, "-e", clean_sql]
    try:
        start_time = datetime.now()
        res = subprocess.run(cmd, stdout=subprocess.PIPE, stderr=subprocess.PIPE, text=True, timeout=30)
        exec_duration = (datetime.now() - start_time).total_seconds()
        if res.returncode != 0:
            return {"success": False, "error": res.stderr.strip() or res.stdout.strip()}

        raw_out = res.stdout.strip()
        if not raw_out:
            return {"success": True, "type": "action", "message": f"Query executed successfully in {exec_duration:.4f}s"}

        lines = raw_out.split("\n")
        columns = lines[0].split("\t")
        rows = [l.split("\t") for l in lines[1:] if l.strip()]

        return {
            "success": True,
            "type": "result",
            "columns": columns,
            "rows": rows,
            "count": len(rows),
            "duration": f"{exec_duration:.4f}"
        }
    except Exception as e:
        return {"success": False, "error": str(e)}

def truncate_table(db_name: str, table_name: str) -> Tuple[bool, str]:
    if not re.match(r'^[a-zA-Z0-9_]+$', db_name) or not re.match(r'^[a-zA-Z0-9_]+$', table_name):
        return False, "Invalid database or table name"
    return run_mysql_command(f"TRUNCATE TABLE `{db_name}`.`{table_name}`;")

def drop_table(db_name: str, table_name: str) -> Tuple[bool, str]:
    if not re.match(r'^[a-zA-Z0-9_]+$', db_name) or not re.match(r'^[a-zA-Z0-9_]+$', table_name):
        return False, "Invalid database or table name"
    return run_mysql_command(f"DROP TABLE IF EXISTS `{db_name}`.`{table_name}`;")

def optimize_table(db_name: str, table_name: str) -> Tuple[bool, str]:
    if not re.match(r'^[a-zA-Z0-9_]+$', db_name) or not re.match(r'^[a-zA-Z0-9_]+$', table_name):
        return False, "Invalid database or table name"
    return run_mysql_command(f"OPTIMIZE TABLE `{db_name}`.`{table_name}`;")


