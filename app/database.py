import sqlite3
import hashlib
import os
from datetime import datetime
from app.config import DB_PATH

def get_db():
    conn = sqlite3.connect(DB_PATH)
    conn.row_factory = sqlite3.Row
    return conn

def hash_password(password: str) -> str:
    # PBKDF2 with SHA-256
    salt = "vps_panel_salt_"
    return hashlib.sha256((salt + password).encode()).hexdigest()

def init_db():
    conn = get_db()
    cursor = conn.cursor()

    # Users Table
    cursor.execute('''
    CREATE TABLE IF NOT EXISTS users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        username TEXT UNIQUE NOT NULL,
        password_hash TEXT NOT NULL,
        role TEXT DEFAULT 'admin',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )
    ''')

    # Websites Table
    cursor.execute('''
    CREATE TABLE IF NOT EXISTS websites (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        domain TEXT UNIQUE NOT NULL,
        root_dir TEXT NOT NULL,
        php_version TEXT DEFAULT '8.3',
        status TEXT DEFAULT 'active',
        ssl_enabled INTEGER DEFAULT 0,
        ssl_expires_at TEXT,
        git_repo TEXT,
        git_branch TEXT,
        db_name TEXT,
        project_type TEXT DEFAULT 'php',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )
    ''')

    # Migration for existing databases
    try:
        cursor.execute("ALTER TABLE websites ADD COLUMN git_repo TEXT")
    except Exception:
        pass
    try:
        cursor.execute("ALTER TABLE websites ADD COLUMN git_branch TEXT")
    except Exception:
        pass
    try:
        cursor.execute("ALTER TABLE websites ADD COLUMN db_name TEXT")
    except Exception:
        pass
    try:
        cursor.execute("ALTER TABLE websites ADD COLUMN project_type TEXT DEFAULT 'php'")
    except Exception:
        pass
    try:
        cursor.execute("ALTER TABLE websites ADD COLUMN redirect_url TEXT")
    except Exception:
        pass
    try:
        cursor.execute("ALTER TABLE websites ADD COLUMN redirect_type INTEGER DEFAULT 301")
    except Exception:
        pass
    try:
        cursor.execute("ALTER TABLE websites ADD COLUMN is_maintenance INTEGER DEFAULT 0")
    except Exception:
        pass

    # Audit Logs Table
    cursor.execute('''
    CREATE TABLE IF NOT EXISTS audit_logs (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        username TEXT,
        action TEXT NOT NULL,
        details TEXT,
        ip_address TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )
    ''')

    # Databases Table (MariaDB/MySQL metadata)
    cursor.execute('''
    CREATE TABLE IF NOT EXISTS databases (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        db_name TEXT UNIQUE NOT NULL,
        db_user TEXT NOT NULL,
        db_pass TEXT NOT NULL,
        db_host TEXT DEFAULT '127.0.0.1',
        charset TEXT DEFAULT 'utf8mb4',
        note TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )
    ''')

    # Database Backups Table
    cursor.execute('''
    CREATE TABLE IF NOT EXISTS db_backups (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        db_name TEXT NOT NULL,
        filename TEXT NOT NULL,
        file_path TEXT NOT NULL,
        size_bytes INTEGER DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )
    ''')

    # Panel Settings Table (key-value)
    cursor.execute('''
    CREATE TABLE IF NOT EXISTS panel_settings (
        key TEXT PRIMARY KEY,
        value TEXT,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )
    ''')

    # Default admin user if none exists
    cursor.execute("SELECT COUNT(*) FROM users")
    if cursor.fetchone()[0] == 0:
        default_pass = hash_password("admin123456")
        cursor.execute("INSERT INTO users (username, password_hash, role) VALUES (?, ?, ?)",
                       ("admin", default_pass, "admin"))

    conn.commit()
    conn.close()

def get_panel_setting(key: str, default: str = "") -> str:
    try:
        conn = get_db()
        cursor = conn.cursor()
        cursor.execute("SELECT value FROM panel_settings WHERE key = ?", (key,))
        row = cursor.fetchone()
        conn.close()
        if row and row["value"] is not None:
            return row["value"]
        return default
    except Exception:
        return default

def set_panel_setting(key: str, value: str):
    try:
        conn = get_db()
        cursor = conn.cursor()
        cursor.execute(
            "INSERT INTO panel_settings (key, value, updated_at) VALUES (?, ?, CURRENT_TIMESTAMP) "
            "ON CONFLICT(key) DO UPDATE SET value = excluded.value, updated_at = CURRENT_TIMESTAMP",
            (key, str(value))
        )
        conn.commit()
        conn.close()
    except Exception:
        pass

def get_all_panel_settings() -> dict:
    try:
        conn = get_db()
        cursor = conn.cursor()
        cursor.execute("SELECT key, value FROM panel_settings")
        rows = cursor.fetchall()
        conn.close()
        return {r["key"]: r["value"] for r in rows}
    except Exception:
        return {}

def log_action(username: str, action: str, details: str = "", ip_address: str = "127.0.0.1"):
    try:
        conn = get_db()
        cursor = conn.cursor()
        cursor.execute("INSERT INTO audit_logs (username, action, details, ip_address) VALUES (?, ?, ?, ?)",
                       (username, action, details, ip_address))
        conn.commit()
        conn.close()
    except Exception:
        pass
