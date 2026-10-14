#!/usr/bin/env bash
# ==============================================================================
# VPS Control Panel (Python Edition) Auto-Updater
# ==============================================================================
set -e

if [[ $EUID -ne 0 ]]; then
   echo "[ERROR] Please run with sudo: sudo bash update.sh"
   exit 1
fi

INSTALL_DIR="/opt/vps-panel"
PANEL_PORT=8443
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

echo "[-] 1/6 Pulling latest updates from Git..."
cd "$SCRIPT_DIR"
git pull origin main

echo "[-] 2/6 Syncing files to ${INSTALL_DIR}..."
mkdir -p "$INSTALL_DIR"
mkdir -p "$INSTALL_DIR/data"
mkdir -p "/www/wwwroot"
cp -rf "$SCRIPT_DIR/app" "$INSTALL_DIR/"
cp -f "$SCRIPT_DIR/requirements.txt" "$INSTALL_DIR/"
cp -f "$SCRIPT_DIR/run.py" "$INSTALL_DIR/"

echo "[-] 3/6 Verifying Python virtual environment..."
if [[ ! -f "$INSTALL_DIR/venv/bin/python3" ]]; then
    echo "Creating Python venv at ${INSTALL_DIR}/venv..."
    apt-get update -y >/dev/null 2>&1 || true
    DEBIAN_FRONTEND=noninteractive apt-get install -y python3-venv python3-pip >/dev/null 2>&1 || true
    python3 -m venv "$INSTALL_DIR/venv"
fi
if ! command -v mysql &> /dev/null && ! command -v mariadb &> /dev/null; then
    echo "[-] Installing MariaDB Server for Database Management..."
    apt-get update -y >/dev/null 2>&1 || true
    DEBIAN_FRONTEND=noninteractive apt-get install -y mariadb-server >/dev/null 2>&1 || true
    systemctl enable mariadb 2>/dev/null || true
    systemctl start mariadb 2>/dev/null || true
fi
mkdir -p /www/backup/database

# Setup Database Web Manager (phpMyAdmin / Adminer)
echo "[-] Setting up Database Web Manager on Port 888..."
DEBIAN_FRONTEND=noninteractive apt-get install -y php-fpm php-mysql php-mbstring php-zip php-gd php-curl php-xml >/dev/null 2>&1 || true
PHP_SOCK=$(find /run/php/ -name "php*-fpm.sock" 2>/dev/null | sort -V | tail -n 1)
if [[ -z "$PHP_SOCK" ]]; then PHP_SOCK="/run/php/php8.3-fpm.sock"; fi

mkdir -p /var/www/phpmyadmin
if [[ -f "$SCRIPT_DIR/app/static/adminer.php" ]]; then
    cp -f "$SCRIPT_DIR/app/static/adminer.php" /var/www/phpmyadmin/index.php
fi

chown -R www-data:www-data /var/www/phpmyadmin 2>/dev/null || true
chmod -R 755 /var/www/phpmyadmin 2>/dev/null || true

cat > /etc/nginx/sites-available/phpmyadmin.conf << 'EOF'
server {
    listen 888 default_server;
    listen [::]:888 default_server;
    server_name _;
    root /var/www/phpmyadmin;
    index index.php index.html index.htm;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:PHP_SOCK_PLACEHOLDER;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }
}
EOF
sed -i "s|PHP_SOCK_PLACEHOLDER|${PHP_SOCK}|g" /etc/nginx/sites-available/phpmyadmin.conf
ln -sf /etc/nginx/sites-available/phpmyadmin.conf /etc/nginx/sites-enabled/phpmyadmin.conf
nginx -t && (systemctl reload nginx || systemctl restart nginx)

"$INSTALL_DIR/venv/bin/pip" install --upgrade pip >/dev/null 2>&1 || true
"$INSTALL_DIR/venv/bin/pip" install -r "$INSTALL_DIR/requirements.txt"

echo "[-] 4/6 Cleaning conflicting port 8443 and old configurations..."
rm -f /etc/nginx/sites-enabled/vps-panel.conf
rm -f /etc/nginx/sites-available/vps-panel.conf
fuser -k 8443/tcp 2>/dev/null || true

echo "[-] 5/6 Ensuring systemd service is properly configured..."
cat > /etc/systemd/system/vps-panel.service << EOF
[Unit]
Description=VPS Control Panel (Python Edition)
After=network.target

[Service]
Type=simple
User=root
WorkingDirectory=${INSTALL_DIR}
Environment="WEB_ROOT=/www/wwwroot"
Environment="PANEL_PORT=${PANEL_PORT}"
Environment="PANEL_DATA_DIR=${INSTALL_DIR}/data"
Environment="GIT_REPO_DIR=${SCRIPT_DIR}"
ExecStart=${INSTALL_DIR}/venv/bin/python3 run.py
Restart=always
RestartSec=3

[Install]
WantedBy=multi-user.target
EOF

systemctl daemon-reload
systemctl enable vps-panel 2>/dev/null || true
systemctl restart vps-panel

echo "[-] 6/6 Ensuring firewall allows port ${PANEL_PORT}..."
ufw allow ${PANEL_PORT}/tcp 2>/dev/null || true
ufw allow 80/tcp 2>/dev/null || true
ufw allow 443/tcp 2>/dev/null || true
ufw allow 888/tcp 2>/dev/null || true

sleep 2

if systemctl is-active --quiet vps-panel; then
    SERVER_IP=$(curl -s -4 ifconfig.me || curl -s icanhazip.com || echo "97.74.84.87")
    echo ""
    echo "=============================================================================="
    echo " [OK] VPS Control Panel is ACTIVE and running successfully!"
    echo " Access URL: http://${SERVER_IP}:${PANEL_PORT}/login"
    echo " Default Login: admin / admin123456"
    echo "=============================================================================="
else
    echo ""
    echo "[WARNING] Service did not start cleanly. Showing recent logs:"
    journalctl -u vps-panel -n 25 --no-pager
fi

