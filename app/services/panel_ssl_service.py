import os
import re
import shutil
import subprocess
from datetime import datetime
from pathlib import Path
from typing import Dict, Any, Tuple

from app.config import DATA_DIR, PANEL_PORT, PANEL_HOST
from app.database import get_panel_setting, set_panel_setting
from app.services.dns_service import get_server_public_ip, check_domain_dns

PANEL_SSL_DIR = DATA_DIR / "ssl"
PANEL_CERT_FILE = PANEL_SSL_DIR / "panel.crt"
PANEL_KEY_FILE = PANEL_SSL_DIR / "panel.key"

def get_panel_ssl_status() -> Dict[str, Any]:
    """Retrieves full SSL configuration and certificate status for the control panel."""
    PANEL_SSL_DIR.mkdir(parents=True, exist_ok=True)

    has_cert = PANEL_CERT_FILE.exists() and PANEL_KEY_FILE.exists()
    server_ip = get_server_public_ip()
    panel_domain = get_panel_setting("panel_domain", "")
    ssl_type = get_panel_setting("panel_ssl_type", "none")
    raw_enabled = get_panel_setting("panel_ssl_enabled", "0") == "1"
    is_active = has_cert and raw_enabled

    expiry = None
    days_left = None
    issuer = None
    subject_cn = None

    if has_cert:
        try:
            cmd = ["openssl", "x509", "-enddate", "-issuer", "-subject", "-noout", "-in", str(PANEL_CERT_FILE)]
            res = subprocess.run(cmd, stdout=subprocess.PIPE, stderr=subprocess.PIPE, text=True, timeout=5)
            if res.returncode == 0:
                out = res.stdout
                date_m = re.search(r'notAfter=(.+)', out)
                if date_m:
                    raw_date = date_m.group(1).strip()
                    try:
                        dt = datetime.strptime(raw_date, "%b %d %H:%M:%S %Y %Z")
                        expiry = dt.strftime("%Y-%m-%d %H:%M:%S UTC")
                        days_left = (dt - datetime.utcnow()).days
                    except Exception:
                        expiry = raw_date
                iss_m = re.search(r'issuer=.*(?:O|CN)\s*=\s*([^,\n]+)', out)
                if iss_m:
                    issuer = iss_m.group(1).strip()
                sub_m = re.search(r'subject=.*CN\s*=\s*([^,\n]+)', out)
                if sub_m:
                    subject_cn = sub_m.group(1).strip()
        except Exception:
            pass

    if not issuer:
        if ssl_type == "letsencrypt":
            issuer = "Let's Encrypt Authority"
        elif ssl_type == "self_signed":
            issuer = "Self-Signed (Local Server)"
        elif has_cert:
            issuer = "Custom / Active Certificate"
        else:
            issuer = "None"

    # Determine recommended protocol and active panel URL
    protocol = "https" if is_active else "http"
    active_host = panel_domain if panel_domain else server_ip
    panel_url = f"{protocol}://{active_host}:{PANEL_PORT}"

    return {
        "has_cert": has_cert,
        "is_active": is_active,
        "ssl_type": ssl_type if is_active else "none",
        "panel_domain": panel_domain,
        "server_ip": server_ip,
        "panel_port": PANEL_PORT,
        "panel_url": panel_url,
        "protocol": protocol,
        "expiry": expiry,
        "days_left": days_left,
        "issuer": issuer,
        "subject_cn": subject_cn,
        "cert_path": str(PANEL_CERT_FILE) if has_cert else None,
        "key_path": str(PANEL_KEY_FILE) if has_cert else None
    }

def clean_domain(raw_domain: str) -> str:
    """Normalizes domain input by removing scheme, port, and trailing slashes."""
    d = (raw_domain or "").strip().lower()
    d = re.sub(r'^https?://', '', d)
    d = d.split('/')[0].split(':')[0].strip()
    return d

def save_panel_domain(raw_domain: str) -> Tuple[bool, str]:
    """Updates the configured panel domain in database settings."""
    domain = clean_domain(raw_domain)
    set_panel_setting("panel_domain", domain)
    return True, f"Panel domain updated to '{domain}'" if domain else "Panel domain cleared (using Server IP)"

def restart_panel_service_background():
    """Restarts the systemd vps-panel service asynchronously."""
    if os.name != 'nt' and hasattr(os, 'getuid') and os.getuid() == 0:
        try:
            # 1-second delay allows current HTTP request to complete smoothly
            cmd = "nohup bash -c 'sleep 1 && systemctl restart vps-panel' >/dev/null 2>&1 &"
            subprocess.Popen(cmd, shell=True)
        except Exception:
            pass

def generate_panel_self_signed(target_cn: str = "") -> Tuple[bool, str]:
    """Generates an immediate 2048-bit RSA Self-Signed Certificate for the IP or domain."""
    PANEL_SSL_DIR.mkdir(parents=True, exist_ok=True)
    server_ip = get_server_public_ip()
    saved_domain = get_panel_setting("panel_domain", "")
    cn = clean_domain(target_cn) or saved_domain or server_ip or "localhost"

    cmd = [
        "openssl", "req", "-x509", "-nodes", "-days", "365",
        "-newkey", "rsa:2048",
        "-keyout", str(PANEL_KEY_FILE),
        "-out", str(PANEL_CERT_FILE),
        "-subj", f"/C=US/ST=State/L=City/O=VPS Control Panel/CN={cn}"
    ]

    try:
        res = subprocess.run(cmd, stdout=subprocess.PIPE, stderr=subprocess.PIPE, text=True, timeout=15)
        if res.returncode != 0:
            return False, f"OpenSSL error: {res.stderr.strip()}"

        set_panel_setting("panel_ssl_enabled", "1")
        set_panel_setting("panel_ssl_type", "self_signed")
        restart_panel_service_background()
        return True, f"Self-Signed SSL Certificate generated successfully for {cn}. Panel is reloading with HTTPS!"
    except Exception as e:
        return False, f"Failed to generate self-signed certificate: {e}"

def issue_panel_letsencrypt(raw_domain: str, email: str = "") -> Tuple[bool, str]:
    """
    Issues a genuine trusted Let's Encrypt SSL certificate for the panel domain.
    Uses Webroot mode against /var/www/html (default catch-all).
    """
    domain = clean_domain(raw_domain)
    if not domain:
        return False, "Domain name is required to issue Let's Encrypt SSL."

    if not email:
        email = f"admin@{domain}"

    # Pre-flight DNS verification
    dns_check = check_domain_dns(domain)
    server_ip = get_server_public_ip()
    if not dns_check.get("matches_server") and not dns_check.get("is_cloudflare"):
        resolved = ", ".join(dns_check.get("resolved_ips", [])) or "None"
        return False, (
            f"DNS Check Failed: '{domain}' does not point to this server IP ({server_ip}). "
            f"It currently resolves to: [{resolved}]. Please update your DNS A-Record first."
        )

    PANEL_SSL_DIR.mkdir(parents=True, exist_ok=True)
    certbot_bin = shutil.which("certbot") or "/usr/bin/certbot"

    # Simulated mode for development/local environments
    if os.name == 'nt' or not Path(certbot_bin).exists():
        PANEL_CERT_FILE.write_text("SIMULATED_PANEL_CERT")
        PANEL_KEY_FILE.write_text("SIMULATED_PANEL_KEY")
        set_panel_setting("panel_domain", domain)
        set_panel_setting("panel_ssl_enabled", "1")
        set_panel_setting("panel_ssl_type", "letsencrypt")
        return True, f"Simulated Let's Encrypt SSL issued for {domain} (Development environment)."

    # Production Certbot Webroot issuance
    # /var/www/html is served on port 80 by 00-default-catchall.conf
    webroot_path = Path("/var/www/html")
    webroot_path.mkdir(parents=True, exist_ok=True)
    acme_dir = webroot_path / ".well-known" / "acme-challenge"
    acme_dir.mkdir(parents=True, exist_ok=True)

    cmd = [
        certbot_bin, "certonly",
        "--webroot", "-w", str(webroot_path),
        "-d", domain,
        "--non-interactive", "--agree-tos",
        "--email", email,
        "--keep-until-expiring"
    ]

    try:
        res = subprocess.run(cmd, stdout=subprocess.PIPE, stderr=subprocess.PIPE, text=True, timeout=120)
        if res.returncode != 0:
            err = res.stderr.strip() or res.stdout.strip() or "Unknown certbot error"
            return False, f"Certbot verification error: {err}"

        live_dir = Path(f"/etc/letsencrypt/live/{domain}")
        fullchain = live_dir / "fullchain.pem"
        privkey = live_dir / "privkey.pem"

        if not (fullchain.exists() and privkey.exists()):
            return False, f"Certbot finished but certificate files not found at {live_dir}"

        # Copy to panel ssl directory
        shutil.copyfile(str(fullchain), str(PANEL_CERT_FILE))
        shutil.copyfile(str(privkey), str(PANEL_KEY_FILE))
        os.chmod(str(PANEL_KEY_FILE), 0o600)

        # Save settings
        set_panel_setting("panel_domain", domain)
        set_panel_setting("panel_ssl_enabled", "1")
        set_panel_setting("panel_ssl_type", "letsencrypt")

        restart_panel_service_background()
        return True, f"Trusted Let's Encrypt SSL certificate issued successfully for {domain}! Panel is reloading on HTTPS (:8443)."
    except Exception as e:
        return False, f"SSL issuance error: {e}"

def disable_panel_ssl() -> Tuple[bool, str]:
    """Disables SSL and reverts panel to standard HTTP."""
    try:
        if PANEL_CERT_FILE.exists():
            PANEL_CERT_FILE.rename(PANEL_SSL_DIR / "panel.crt.disabled")
        if PANEL_KEY_FILE.exists():
            PANEL_KEY_FILE.rename(PANEL_SSL_DIR / "panel.key.disabled")

        set_panel_setting("panel_ssl_enabled", "0")
        set_panel_setting("panel_ssl_type", "none")
        restart_panel_service_background()
        return True, "Panel SSL has been disabled. Panel is reloading on standard HTTP."
    except Exception as e:
        return False, f"Failed to disable SSL: {e}"
