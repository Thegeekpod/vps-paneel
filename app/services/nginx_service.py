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

def get_fpm_socket(php_version: str = "8.3") -> str:
    candidates = [
        f"/run/php/php{php_version}-fpm.sock",
        f"/var/run/php/php{php_version}-fpm.sock",
    ]
    for c in candidates:
        if Path(c).exists():
            return c
    for d in ["/run/php", "/var/run/php"]:
        dp = Path(d)
        if dp.exists():
            socks = list(dp.glob("php*-fpm.sock"))
            if socks:
                return str(socks[0])
    return f"/run/php/php{php_version}-fpm.sock"

def get_existing_ssl_block(domain: str, vhost_file: Path) -> str:
    """Returns Nginx SSL configuration block if Let's Encrypt certificate or existing SSL directives exist."""
    fullchain = Path(f"/etc/letsencrypt/live/{domain}/fullchain.pem")
    privkey = Path(f"/etc/letsencrypt/live/{domain}/privkey.pem")
    
    if fullchain.exists() and privkey.exists():
        return f"""
    listen 443 ssl http2;
    listen [::]:443 ssl http2;
    ssl_certificate {fullchain};
    ssl_certificate_key {privkey};
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers HIGH:!aNULL:!MD5;
"""
    if vhost_file.exists():
        try:
            content = vhost_file.read_text()
            if "ssl_certificate" in content:
                ssl_lines = [
                    line.strip() for line in content.splitlines()
                    if any(k in line for k in ["ssl_certificate", "ssl_protocols", "ssl_ciphers", "443 ssl"])
                ]
                if ssl_lines:
                    return "\n    " + "\n    ".join(ssl_lines) + "\n"
        except Exception:
            pass
    return ""

def restore_ssl_for_existing_domains() -> bool:
    """Scans all existing Let's Encrypt certificates and restores SSL in Nginx vhost configs if missing."""
    if os.name == 'nt' or not NGINX_AVAILABLE.exists():
        return False
    letsencrypt_live = Path("/etc/letsencrypt/live")
    if not letsencrypt_live.exists():
        return False

    modified = False
    try:
        for domain_dir in letsencrypt_live.iterdir():
            if domain_dir.is_dir() and domain_dir.name != "README":
                domain = domain_dir.name
                fullchain = domain_dir / "fullchain.pem"
                privkey = domain_dir / "privkey.pem"
                if fullchain.exists() and privkey.exists():
                    vhost_file = NGINX_AVAILABLE / f"{domain}.conf"
                    if vhost_file.exists():
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
                                new_content = content.replace("server {", "server {" + ssl_block, 1)
                                vhost_file.write_text(new_content)
                                modified = True
                        except Exception:
                            pass
        if modified:
            test_and_reload_nginx()
    except Exception:
        pass
    return modified

def create_vhost(domain: str, root_dir: str, php_version: str = "8.3", project_type: str = "php") -> Tuple[bool, str]:
    vhost_file = NGINX_AVAILABLE / f"{domain}.conf"
    vhost_enabled = NGINX_ENABLED / f"{domain}.conf"
    
    # PHP-FPM socket path
    fpm_socket = get_fpm_socket(php_version)
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
    # Automatically preserve SSL if certificates exist on disk or previously in vhost
    ssl_block = get_existing_ssl_block(domain, vhost_file)
    if ssl_block and "server {" in config:
        config = config.replace("server {", "server {" + ssl_block, 1)

    if os.name != 'nt' and NGINX_AVAILABLE.exists():
        old_content = None
        if vhost_file.exists():
            try:
                old_content = vhost_file.read_text()
            except Exception:
                pass

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
                if old_content:
                    vhost_file.write_text(old_content)
                    test_and_reload_nginx()
                else:
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
