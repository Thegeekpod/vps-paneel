import os
import re
import shutil
import socket
import subprocess
import urllib.request
from datetime import datetime
from pathlib import Path
from typing import Tuple, Dict, Any, Optional
from app.config import NGINX_AVAILABLE, LETSENCRYPT_DIR
from app.services.nginx_service import test_and_reload_nginx

def get_ssl_info(domain: str) -> Dict[str, Any]:
    """Retrieves full SSL certificate and Nginx binding status for a domain."""
    fullchain = Path(f"/etc/letsencrypt/live/{domain}/fullchain.pem")
    privkey = Path(f"/etc/letsencrypt/live/{domain}/privkey.pem")
    vhost_file = NGINX_AVAILABLE / f"{domain}.conf"

    has_cert_files = fullchain.exists() and privkey.exists()
    in_nginx = False
    force_https = False

    if vhost_file.exists():
        try:
            content = vhost_file.read_text()
            if "ssl_certificate" in content and ("443 ssl" in content or "listen 443" in content):
                in_nginx = True
            if "return 301 https://" in content:
                force_https = True
        except Exception:
            pass

    expiry = None
    days_left = None
    issuer = None

    if has_cert_files:
        try:
            cmd = ["openssl", "x509", "-enddate", "-issuer", "-noout", "-in", str(fullchain)]
            res = subprocess.run(cmd, stdout=subprocess.PIPE, stderr=subprocess.PIPE, text=True, timeout=5)
            if res.returncode == 0:
                out = res.stdout
                match = re.search(r'notAfter=(.+)', out)
                if match:
                    raw_date = match.group(1).strip()
                    try:
                        dt = datetime.strptime(raw_date, "%b %d %H:%M:%S %Y %Z")
                        expiry = dt.strftime("%Y-%m-%d %H:%M:%S UTC")
                        days_left = (dt - datetime.utcnow()).days
                    except Exception:
                        expiry = raw_date
                match_iss = re.search(r'issuer=.*O\s*=\s*([^,\n]+)', out)
                if match_iss:
                    issuer = match_iss.group(1).strip()
                else:
                    issuer = "Let's Encrypt"
        except Exception:
            pass

    return {
        "domain": domain,
        "has_cert": has_cert_files,
        "in_nginx": in_nginx,
        "is_active": has_cert_files and in_nginx,
        "force_https": force_https,
        "expiry": expiry,
        "days_left": days_left,
        "issuer": issuer or ("Let's Encrypt" if has_cert_files else "None"),
        "fullchain": str(fullchain) if has_cert_files else None,
        "privkey": str(privkey) if has_cert_files else None
    }

def inject_ssl_into_vhost(domain: str) -> Tuple[bool, str]:
    """Injects SSL directives for domain into its Nginx configuration and reloads."""
    fullchain = Path(f"/etc/letsencrypt/live/{domain}/fullchain.pem")
    privkey = Path(f"/etc/letsencrypt/live/{domain}/privkey.pem")
    if not (fullchain.exists() and privkey.exists()):
        return False, "Certificate files not found in /etc/letsencrypt/live"

    vhost_file = NGINX_AVAILABLE / f"{domain}.conf"
    if not vhost_file.exists():
        return False, f"Vhost file {vhost_file} does not exist"

    try:
        content = vhost_file.read_text()
        if "ssl_certificate" not in content and "server {" in content:
            ssl_block = f"""
    listen 443 ssl http2;
    listen [::]:443 ssl http2;
    ssl_certificate {fullchain};
    ssl_certificate_key {privkey};
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers HIGH:!aNULL:!MD5;
"""
            content = content.replace("server {", "server {" + ssl_block, 1)
            vhost_file.write_text(content)
            ok, msg = test_and_reload_nginx()
            if not ok:
                return False, msg
            return True, "SSL applied to Nginx successfully"
        elif "ssl_certificate" in content:
            test_and_reload_nginx()
            return True, "SSL already active in Nginx configuration"
        return False, "Could not find server block in Nginx configuration"
    except Exception as e:
        return False, str(e)

def strip_ssl_from_vhost(domain: str) -> Tuple[bool, str]:
    """Removes SSL directives from Nginx configuration and reloads."""
    vhost_file = NGINX_AVAILABLE / f"{domain}.conf"
    if not vhost_file.exists():
        return True, "No vhost file to clean"

    try:
        content = vhost_file.read_text()
        lines = content.splitlines()
        new_lines = []
        for line in lines:
            if any(k in line for k in ["ssl_certificate", "ssl_protocols", "ssl_ciphers", "443 ssl", "listen 443", "return 301 https://"]):
                continue
            new_lines.append(line)
        new_content = "\n".join(new_lines)
        vhost_file.write_text(new_content)
        test_and_reload_nginx()
        return True, "SSL removed from Nginx configuration"
    except Exception as e:
        return False, str(e)

def get_server_public_ip() -> str:
    """Detects public IP of this server."""
    for url in ["https://api.ipify.org", "https://ifconfig.me/ip", "https://icanhazip.com"]:
        try:
            req = urllib.request.Request(url, headers={"User-Agent": "curl/7.68.0"})
            with urllib.request.urlopen(req, timeout=3) as resp:
                ip = resp.read().decode().strip()
                if ip:
                    return ip
        except Exception:
            continue
    return ""

def diagnose_domain_ssl(domain: str, webroot: str) -> Dict[str, Any]:
    """Runs DNS, Port 80, and challenge pre-flight checks for SSL issuance."""
    report = {
        "domain": domain,
        "resolved_ip": None,
        "www_resolved_ip": None,
        "server_ip": None,
        "dns_match": False,
        "is_cloudflare": False,
        "port_80_open": False,
        "webroot_ok": False,
        "acme_test_ok": False,
        "warnings": [],
        "errors": []
    }

    server_ip = get_server_public_ip()
    report["server_ip"] = server_ip

    # Check main domain DNS
    try:
        domain_ip = socket.gethostbyname(domain)
        report["resolved_ip"] = domain_ip

        # Check Cloudflare IP prefixes
        cf_prefixes = ("104.16.", "104.17.", "104.18.", "104.19.", "104.20.", "104.21.", "172.64.", "172.67.", "108.162.", "162.158.", "188.114.")
        if any(domain_ip.startswith(p) for p in cf_prefixes):
            report["is_cloudflare"] = True
            report["warnings"].append("Cloudflare Proxy (Orange Cloud) detected on DNS. Certbot HTTP challenges often fail behind Cloudflare proxy. Temporarily switch to 'DNS Only (Grey Cloud)' in Cloudflare.")

        if server_ip and domain_ip == server_ip:
            report["dns_match"] = True
        elif server_ip:
            report["warnings"].append(f"Domain resolves to {domain_ip}, but server public IP is {server_ip}. If not using a reverse proxy or CDN, Let's Encrypt cannot reach this server.")
    except Exception as e:
        report["errors"].append(f"DNS Resolution failed for '{domain}': {e}. Verify domain A-record.")

    # Check www subdomain
    try:
        www_ip = socket.gethostbyname(f"www.{domain}")
        report["www_resolved_ip"] = www_ip
    except Exception:
        report["www_resolved_ip"] = None

    # Check port 80 local listening
    try:
        with socket.socket(socket.AF_INET, socket.SOCK_STREAM) as s:
            s.settimeout(2)
            res = s.connect_ex(('127.0.0.1', 80))
            if res == 0:
                report["port_80_open"] = True
            else:
                report["warnings"].append("Port 80 is not currently listening locally on 127.0.0.1.")
    except Exception as e:
        report["warnings"].append(f"Port 80 check: {e}")

    # Check webroot & challenge dir write test
    try:
        wr_path = Path(webroot)
        if wr_path.exists() and wr_path.is_dir():
            report["webroot_ok"] = True
            acme_dir = wr_path / ".well-known" / "acme-challenge"
            acme_dir.mkdir(parents=True, exist_ok=True)
            test_file = acme_dir / "precheck.tmp"
            test_file.write_text("ok")
            test_file.unlink(missing_ok=True)
            report["acme_test_ok"] = True
        else:
            report["errors"].append(f"Webroot path '{webroot}' does not exist.")
    except Exception as e:
        report["errors"].append(f"Webroot write test error: {e}")

    return report

def get_certbot_log(lines_count: int = 60) -> str:
    """Reads recent log entries from /var/log/letsencrypt/letsencrypt.log."""
    log_file = Path("/var/log/letsencrypt/letsencrypt.log")
    if not log_file.exists():
        return "No Certbot log file found at /var/log/letsencrypt/letsencrypt.log"
    try:
        content = log_file.read_text(errors="replace")
        lines = content.splitlines()
        last = lines[-lines_count:] if len(lines) > lines_count else lines
        return "\n".join(last)
    except Exception as e:
        return f"Error reading certbot log: {e}"

def issue_ssl(
    domain: str,
    webroot: str,
    email: str = "",
    force_renewal: bool = False,
    method: str = "webroot", # "webroot", "nginx", "standalone"
    include_www: bool = False
) -> Tuple[bool, str]:
    """Issues or force-renews Let's Encrypt SSL certificate using Certbot with chosen method."""
    if not email:
        email = f"admin@{domain}"

    certbot_bin = shutil.which("certbot") or "/usr/bin/certbot"
    if os.name == 'nt' or not Path(certbot_bin).exists():
        # Simulation mode for testing
        sim_dir = LETSENCRYPT_DIR / "live" / domain
        sim_dir.mkdir(parents=True, exist_ok=True)
        (sim_dir / "fullchain.pem").write_text("SIMULATED_CERT")
        (sim_dir / "privkey.pem").write_text("SIMULATED_KEY")
        inject_ssl_into_vhost(domain)
        return True, f"Simulated SSL Certificate issued successfully ({method.title()} mode)."

    cmd = [
        certbot_bin, "certonly",
        "--non-interactive", "--agree-tos",
        "--email", email,
        "-d", domain
    ]
    if include_www:
        cmd.extend(["-d", f"www.{domain}"])

    if force_renewal:
        cmd.append("--force-renewal")
    else:
        cmd.append("--keep-until-expiring")

    stopped_nginx = False
    try:
        if method == "nginx":
            cmd.append("--nginx")
        elif method == "standalone":
            # Standalone needs port 80 free, temporarily stop Nginx
            subprocess.run(["systemctl", "stop", "nginx"], check=False, timeout=15)
            stopped_nginx = True
            cmd.append("--standalone")
        else: # "webroot"
            acme_dir = Path(webroot) / ".well-known" / "acme-challenge"
            try:
                acme_dir.mkdir(parents=True, exist_ok=True)
                os.chmod(Path(webroot) / ".well-known", 0o755)
                os.chmod(acme_dir, 0o755)
            except Exception:
                pass
            cmd.extend(["--webroot", "-w", webroot])

        res = subprocess.run(cmd, stdout=subprocess.PIPE, stderr=subprocess.PIPE, text=True, timeout=120)

        # Always restore Nginx if we stopped it
        if stopped_nginx:
            subprocess.run(["systemctl", "start", "nginx"], check=False, timeout=15)
            stopped_nginx = False

        if res.returncode != 0:
            err = res.stderr.strip() or res.stdout.strip() or "Unknown certbot error"
            return False, f"Certbot error ({method} mode): {err}"

        # Inject SSL into Nginx config
        ok, msg = inject_ssl_into_vhost(domain)
        if not ok:
            return False, f"SSL issued, but Nginx configuration failed: {msg}"

        return True, f"SSL Certificate issued and activated in Nginx using {method.title()} mode!"
    except Exception as e:
        if stopped_nginx:
            try:
                subprocess.run(["systemctl", "start", "nginx"], check=False, timeout=15)
            except Exception:
                pass
        return False, str(e)

def remove_ssl(domain: str) -> Tuple[bool, str]:
    """Completely revokes and deletes Let's Encrypt SSL certificate and cleans Nginx configuration."""
    certbot_bin = shutil.which("certbot") or "/usr/bin/certbot"

    # 1. Strip SSL from Nginx first so reload succeeds
    strip_ssl_from_vhost(domain)

    if os.name == 'nt' or not Path(certbot_bin).exists():
        live_dir = LETSENCRYPT_DIR / "live" / domain
        if live_dir.exists():
            shutil.rmtree(str(live_dir), ignore_errors=True)
        return True, "SSL Certificate removed (Simulation mode)"

    # 2. Delete certbot certificate
    cmd = [certbot_bin, "delete", "--cert-name", domain, "--non-interactive"]
    try:
        subprocess.run(cmd, stdout=subprocess.PIPE, stderr=subprocess.PIPE, text=True, timeout=30)
    except Exception:
        pass

    # 3. Clean any leftover directory remnants
    for sub in ["live", "archive", "renewal"]:
        p = LETSENCRYPT_DIR / sub / (f"{domain}.conf" if sub == "renewal" else domain)
        if p.exists():
            try:
                if p.is_dir():
                    shutil.rmtree(str(p), ignore_errors=True)
                else:
                    p.unlink(missing_ok=True)
            except Exception:
                pass

    return True, "SSL Certificate completely removed"

def toggle_force_https(domain: str, enable: bool) -> Tuple[bool, str]:
    """Enables or disables 301 HTTP -> HTTPS redirection in Nginx configuration."""
    vhost_file = NGINX_AVAILABLE / f"{domain}.conf"
    if not vhost_file.exists():
        return False, "Vhost file not found"

    try:
        content = vhost_file.read_text()
        redirect_line = "    if ($scheme != \"https\") { return 301 https://$host$request_uri; }"
        
        # Remove existing redirect lines if present
        lines = [l for l in content.splitlines() if "return 301 https://" not in l]
        content = "\n".join(lines)

        if enable:
            if "server {" in content:
                content = content.replace("server {", "server {\n" + redirect_line, 1)
        
        vhost_file.write_text(content)
        ok, msg = test_and_reload_nginx()
        if not ok:
            return False, msg
        return True, "Force HTTPS " + ("enabled" if enable else "disabled")
    except Exception as e:
        return False, str(e)
