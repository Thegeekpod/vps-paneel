import os
import subprocess
from pathlib import Path
from typing import Tuple
from app.config import NGINX_AVAILABLE, NGINX_ENABLED, WEB_ROOT

def run_cmd(cmd: list) -> Tuple[int, str]:
    try:
        res = subprocess.run(cmd, stdout=subprocess.PIPE, stderr=subprocess.PIPE, text=True, timeout=30)
        return res.returncode, res.stdout + res.stderr
    except Exception as e:
        return 1, str(e)

def test_and_reload_nginx() -> Tuple[bool, str]:
    if os.name == 'nt' or not NGINX_AVAILABLE.exists():
        return True, "Simulation: Nginx reloaded"
    
    code, out = run_cmd(["nginx", "-t"])
    if code != 0:
        return False, f"Nginx configuration test failed: {out}"
    
    run_cmd(["systemctl", "reload", "nginx"])
    return True, "Nginx reloaded successfully"

def ensure_default_catchall():
    """Ensure port 80 has a default catch-all site so port 80 is never refused."""
    if os.name == 'nt' or not NGINX_AVAILABLE.exists():
        return
    
    default_conf = NGINX_AVAILABLE / "00-default-catchall.conf"
    default_enabled = NGINX_ENABLED / "00-default-catchall.conf"
    default_html_dir = Path("/var/www/html")
    default_html_dir.mkdir(parents=True, exist_ok=True)
    
    index_file = default_html_dir / "index.html"
    if not index_file.exists():
        index_file.write_text("""<!DOCTYPE html>
<html>
<head><title>VPS Panel - Server Online</title></head>
<body style="font-family:sans-serif;text-align:center;padding:50px;background:#0f172a;color:#fff;">
<h1>Server Online</h1>
<p>Domain pointed successfully. Please add this domain in your VPS Control Panel.</p>
</body></html>""")

    content = f"""server {{
    listen 80 default_server;
    listen [::]:80 default_server;
    server_name _;
    root {default_html_dir};
    index index.html index.htm;
    location / {{
        try_files $uri $uri/ /index.html;
    }}
}}
"""
    try:
        default_conf.write_text(content)
        if not default_enabled.exists():
            default_enabled.symlink_to(default_conf)
        # Remove old conflicting default if present
        old_default = NGINX_ENABLED / "default"
        if old_default.exists() or old_default.is_symlink():
            old_default.unlink(missing_ok=True)
    except Exception:
        pass

def create_vhost(domain: str, root_dir: str, php_version: str = "8.3", project_type: str = "php") -> Tuple[bool, str]:
    vhost_file = NGINX_AVAILABLE / f"{domain}.conf"
    vhost_enabled = NGINX_ENABLED / f"{domain}.conf"
    
    # PHP-FPM socket path
    fpm_socket = f"/run/php/php{php_version}-fpm.sock"
    clean_root = root_dir.rstrip("/")
    if project_type == "laravel" and not clean_root.endswith("/public"):
        clean_root = f"{clean_root}/public"

    if project_type == "static":
        config = f"""server {{
    listen 80;
    listen [::]:80;
    server_name {domain} www.{domain};
    root {clean_root};
    index index.html index.htm;

    access_log /var/log/nginx/{domain}-access.log;
    error_log /var/log/nginx/{domain}-error.log error;

    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;

    location / {{
        try_files $uri $uri/ /index.html =404;
    }}

    location ~ /\\.ht {{
        deny all;
    }}
}}
"""
    elif project_type == "laravel":
        config = f"""server {{
    listen 80;
    listen [::]:80;
    server_name {domain} www.{domain};
    root {clean_root};
    index index.php index.html index.htm;

    access_log /var/log/nginx/{domain}-access.log;
    error_log /var/log/nginx/{domain}-error.log error;

    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-XSS-Protection "1; mode=block" always;

    charset utf-8;

    location / {{
        try_files $uri $uri/ /index.php?$query_string;
    }}

    location = /favicon.ico {{ access_log off; log_not_found off; }}
    location = /robots.txt  {{ access_log off; log_not_found off; }}

    error_page 404 /index.php;

    location ~ \\.php$ {{
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:{fpm_socket};
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }}

    location ~ /\\.(?!well-known).* {{
        deny all;
    }}
}}
"""
    else:  # Standard PHP
        config = f"""server {{
    listen 80;
    listen [::]:80;
    server_name {domain} www.{domain};
    root {clean_root};
    index index.html index.htm index.php;

    access_log /var/log/nginx/{domain}-access.log;
    error_log /var/log/nginx/{domain}-error.log error;

    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;

    location / {{
        try_files $uri $uri/ /index.html /index.php?$query_string;
    }}

    location ~ \\.php$ {{
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:{fpm_socket};
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }}

    location ~ /\\.ht {{
        deny all;
    }}
}}
"""
    if os.name != 'nt' and NGINX_AVAILABLE.exists():
        try:
            vhost_file.write_text(config)
            if not vhost_enabled.exists():
                vhost_enabled.symlink_to(vhost_file)
            
            # Remove default conflicting site
            old_default = NGINX_ENABLED / "default"
            if old_default.exists() or old_default.is_symlink():
                old_default.unlink(missing_ok=True)
                
            ensure_default_catchall()
            
            success, msg = test_and_reload_nginx()
            if not success:
                vhost_enabled.unlink(missing_ok=True)
                vhost_file.unlink(missing_ok=True)
                return False, msg
            return True, "Nginx vhost created and active"
        except Exception as e:
            return False, str(e)
            
    return True, "Simulated vhost creation"

def delete_vhost(domain: str):
    vhost_file = NGINX_AVAILABLE / f"{domain}.conf"
    vhost_enabled = NGINX_ENABLED / f"{domain}.conf"
    
    try:
        if vhost_enabled.exists() or vhost_enabled.is_symlink():
            vhost_enabled.unlink(missing_ok=True)
        if vhost_file.exists():
            vhost_file.unlink(missing_ok=True)
        test_and_reload_nginx()
    except Exception:
        pass
