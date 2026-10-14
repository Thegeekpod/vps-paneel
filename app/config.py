import os
import secrets
from pathlib import Path

BASE_DIR = Path(__file__).resolve().parent.parent
DATA_DIR = Path(os.getenv("PANEL_DATA_DIR", BASE_DIR / "data"))
DATA_DIR.mkdir(parents=True, exist_ok=True)

DB_PATH = DATA_DIR / "panel.db"
SECRET_KEY = os.getenv("PANEL_SECRET_KEY", "vps-panel-secret-key-change-in-production-" + secrets.token_hex(16))
PANEL_PORT = int(os.getenv("PANEL_PORT", "8443"))
PANEL_HOST = os.getenv("PANEL_HOST", "0.0.0.0")

# Website hosting paths (aaPanel standard: /www/wwwroot)
if os.getenv("WEB_ROOT"):
    WEB_ROOT = Path(os.getenv("WEB_ROOT"))
elif os.name != 'nt' and os.path.exists("/www"):
    WEB_ROOT = Path("/www/wwwroot")
elif os.name != 'nt' and os.path.exists("/var/www"):
    WEB_ROOT = Path("/www/wwwroot" if os.getuid() == 0 else "/var/www/vhosts")
else:
    WEB_ROOT = BASE_DIR / "sandbox_www"

NGINX_AVAILABLE = Path(os.getenv("NGINX_AVAILABLE", "/etc/nginx/sites-available"))
NGINX_ENABLED = Path(os.getenv("NGINX_ENABLED", "/etc/nginx/sites-enabled"))
LETSENCRYPT_DIR = Path(os.getenv("LETSENCRYPT_DIR", "/etc/letsencrypt"))

# Ensure paths exist safely
try:
    WEB_ROOT.mkdir(parents=True, exist_ok=True)
except Exception:
    WEB_ROOT = BASE_DIR / "sandbox_www"
    WEB_ROOT.mkdir(parents=True, exist_ok=True)

if os.name != 'nt' and hasattr(os, 'getuid') and os.getuid() == 0:
    try:
        NGINX_AVAILABLE.mkdir(parents=True, exist_ok=True)
        NGINX_ENABLED.mkdir(parents=True, exist_ok=True)
    except Exception:
        pass

def get_git_repo_dir() -> Path:
    env_dir = os.getenv("GIT_REPO_DIR")
    if env_dir and Path(env_dir).exists():
        return Path(env_dir)
    if Path("/var/www/vps-panel/.git").exists():
        return Path("/var/www/vps-panel")
    if (BASE_DIR / ".git").exists():
        return BASE_DIR
    return Path("/var/www/vps-panel")

