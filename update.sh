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

# Remove old Adminer if present (preserve official phpMyAdmin if installed)
if [[ -f "/var/www/phpmyadmin/index.php" && ! -d "/var/www/phpmyadmin/libraries" ]]; then
    echo "[-] Removing old Adminer..."
    rm -rf /var/www/phpmyadmin
    rm -rf /var/www/html/phpmyadmin
    rm -f /etc/nginx/sites-enabled/phpmyadmin.conf
    rm -f /etc/nginx/sites-available/phpmyadmin.conf
    fuser -k 888/tcp 2>/dev/null || true
    nginx -t 2>/dev/null && (systemctl reload nginx 2>/dev/null || systemctl restart nginx 2>/dev/null) || true
fi

if [[ -d "/var/www/phpmyadmin/libraries" ]]; then
    ufw allow 888/tcp 2>/dev/null || true
fi

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

