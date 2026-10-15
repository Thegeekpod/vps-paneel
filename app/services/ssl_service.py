import os
import subprocess
from pathlib import Path
from typing import Tuple
from app.config import NGINX_AVAILABLE, LETSENCRYPT_DIR
from app.services.nginx_service import test_and_reload_nginx

def issue_ssl(domain: str, webroot: str, email: str = "") -> Tuple[bool, str]:
    if not email:
        email = f"admin@{domain}"
        
    if os.name == 'nt' or not Path("/usr/bin/certbot").exists():
        # Simulation mode
        return True, "Simulated SSL Certificate issued successfully."

    # Direct certbot execution with root privileges
    cmd = [
        "certbot", "certonly",
        "--webroot", "-w", webroot,
        "-d", domain,
        "--non-interactive", "--agree-tos",
        "--email", email,
        "--keep-until-expiring"
    ]
    
    try:
        res = subprocess.run(cmd, stdout=subprocess.PIPE, stderr=subprocess.PIPE, text=True, timeout=120)
        if res.returncode != 0:
            return False, f"Certbot error: {res.stdout} {res.stderr}"
            
        fullchain = f"/etc/letsencrypt/live/{domain}/fullchain.pem"
        privkey = f"/etc/letsencrypt/live/{domain}/privkey.pem"
        
        # Inject SSL into Nginx config
        vhost_file = NGINX_AVAILABLE / f"{domain}.conf"
        if vhost_file.exists():
            content = vhost_file.read_text()
            if "ssl_certificate" not in content:
                ssl_block = f"""
    listen 443 ssl http2;
    listen [::]:443 ssl http2;
    ssl_certificate {fullchain};
    ssl_certificate_key {privkey};
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers HIGH:!aNULL:!MD5;
"""
                # Insert inside the server block
                content = content.replace("server {", "server {" + ssl_block, 1)
                vhost_file.write_text(content)
                test_and_reload_nginx()
                
        return True, "SSL Certificate issued and Nginx reloaded"
    except Exception as e:
        return False, str(e)

def remove_ssl(domain: str) -> Tuple[bool, str]:
    if os.name == 'nt' or not Path("/usr/bin/certbot").exists():
        # Simulation or directory cleanup
        live_dir = LETSENCRYPT_DIR / "live" / domain
        if live_dir.exists():
            import shutil
            shutil.rmtree(str(live_dir), ignore_errors=True)
        return True, "SSL Certificate removed (Simulation mode)"

    cmd = ["certbot", "delete", "--cert-name", domain, "--non-interactive"]
    try:
        res = subprocess.run(cmd, stdout=subprocess.PIPE, stderr=subprocess.PIPE, text=True, timeout=30)
        return True, "SSL Certificate removed successfully"
    except Exception as e:
        return False, str(e)
