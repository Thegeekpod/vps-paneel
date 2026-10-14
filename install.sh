#!/usr/bin/env bash
# ==============================================================================
# VPS Control Panel (Python Edition - aaPanel Style)
# Complete Automated Clean Installer for Ubuntu
# ==============================================================================
set -e

if [[ $EUID -ne 0 ]]; then
   echo "[ERROR] This installer must be run as root (EUID 0). Use 'sudo bash install.sh'."
   exit 1
fi

INSTALL_DIR="/opt/vps-panel"
PANEL_PORT=8443

echo "=============================================================================="
echo " Starting Full VPS Cleanup & Fresh Control Panel Installation..."
echo "=============================================================================="

# ------------------------------------------------------------------------------
# STEP 0: Clean Up Old Services, Configurations & Lingering Processes
# ------------------------------------------------------------------------------
echo "[-] 0/7 Cleaning old services and stale configurations from server..."

# Stop and remove old systemd services from previous versions
for svc in vps-panel-worker.service vps-panel-scheduler.service vps-panel.service; do
    if systemctl is-active --quiet "$svc" 2>/dev/null; then
        systemctl stop "$svc" 2>/dev/null || true
    fi
    if systemctl is-enabled --quiet "$svc" 2>/dev/null; then
        systemctl disable "$svc" 2>/dev/null || true
    fi
    rm -f "/etc/systemd/system/$svc"
done
systemctl daemon-reload

# Kill any lingering background processes from previous setups
pkill -9 -f "php artisan" 2>/dev/null || true
pkill -9 -f "vps-agent" 2>/dev/null || true
fuser -k 8443/tcp 2>/dev/null || true

# Remove old sudoers and binaries
rm -f /etc/sudoers.d/vps-panel-agent
rm -f /usr/local/bin/vps-agent

# Clean up broken Nginx symlinks and conflicting default vhosts
rm -f /etc/nginx/sites-enabled/default
rm -f /etc/nginx/sites-available/default
rm -f /etc/nginx/sites-enabled/vps-panel.conf
rm -f /etc/nginx/sites-available/vps-panel.conf
find /etc/nginx/sites-enabled/ -xtype l -delete 2>/dev/null || true

# Clean old installation files
if [[ -d "$INSTALL_DIR" ]]; then
    rm -rf "$INSTALL_DIR/app" "$INSTALL_DIR/venv" "$INSTALL_DIR/run.py" "$INSTALL_DIR/requirements.txt"
fi

echo "[OK] System cleaned up successfully."

# ------------------------------------------------------------------------------
# STEP 1: Dependencies Installation
# ------------------------------------------------------------------------------
echo "[-] 1/7 Installing system packages (Python3, Nginx, Certbot, UFW)..."
apt-get update -y
DEBIAN_FRONTEND=noninteractive apt-get install -y \
    python3 \
    python3-pip \
    python3-venv \
    nginx \
    certbot \
    python3-certbot-nginx \
    curl \
    git \
    ufw \
    mariadb-server

# ------------------------------------------------------------------------------
# STEP 2: Directory Structure
# ------------------------------------------------------------------------------
echo "[-] 2/7 Creating directory structure (/opt/vps-panel & /www/wwwroot)..."
mkdir -p "$INSTALL_DIR"
mkdir -p "/www/wwwroot"
mkdir -p "$INSTALL_DIR/data"

# Copy latest panel files
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cp -rf "$SCRIPT_DIR/app" "$INSTALL_DIR/"
cp -f "$SCRIPT_DIR/requirements.txt" "$INSTALL_DIR/"
cp -f "$SCRIPT_DIR/run.py" "$INSTALL_DIR/"

# ------------------------------------------------------------------------------
# STEP 3: Python Virtual Environment
# ------------------------------------------------------------------------------
echo "[-] 3/7 Setting up fresh Python virtual environment & dependencies..."
rm -rf "$INSTALL_DIR/venv"
python3 -m venv "$INSTALL_DIR/venv"
"$INSTALL_DIR/venv/bin/pip" install --upgrade pip
"$INSTALL_DIR/venv/bin/pip" install -r "$INSTALL_DIR/requirements.txt"

# ------------------------------------------------------------------------------
# STEP 4: Default Port 80 Catch-All VirtualHost
# ------------------------------------------------------------------------------
echo "[-] 4/7 Configuring Nginx default catch-all site on port 80..."
mkdir -p /var/www/html
cat > /var/www/html/index.html << 'EOF'
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>VPS Panel - Server Online</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background: #0f172a; color: #f8fafc; display: flex; align-items: center; justify-content: center; min-height: 100vh; margin: 0; padding: 20px; text-align: center; }
        .box { background: #1e293b; border: 1px solid #334155; border-radius: 16px; padding: 36px; max-width: 500px; width: 100%; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.5); }
        h1 { color: #38bdf8; font-size: 24px; margin-bottom: 12px; }
        p { color: #94a3b8; font-size: 14px; line-height: 1.6; }
    </style>
</head>
<body>
    <div class="box">
        <h1>🚀 Server Online</h1>
        <p>This domain is successfully pointing to your VPS server. Please log in to your <strong>VPS Control Panel (:8443)</strong> and add this domain in <strong>Websites</strong> to publish your content.</p>
    </div>
</body>
</html>
EOF

rm -f /etc/nginx/sites-enabled/default
cat > /etc/nginx/sites-available/00-default-catchall.conf << 'EOF'
server {
    listen 80 default_server;
    listen [::]:80 default_server;
    server_name _;
    root /var/www/html;
    index index.html index.htm;
    location / {
        try_files $uri $uri/ /index.html;
    }
}
EOF
ln -sf /etc/nginx/sites-available/00-default-catchall.conf /etc/nginx/sites-enabled/00-default-catchall.conf

nginx -t
systemctl restart nginx || systemctl start nginx
systemctl enable nginx 2>/dev/null || true

# ------------------------------------------------------------------------------
# STEP 5: Systemd Service (vps-panel.service)
# ------------------------------------------------------------------------------
echo "[-] 5/7 Setting up root systemd service for VPS Control Panel..."
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
systemctl enable vps-panel
fuser -k 8443/tcp 2>/dev/null || true
systemctl restart vps-panel

# ------------------------------------------------------------------------------
# STEP 6: Firewall Rules
# ------------------------------------------------------------------------------
echo "[-] 6/7 Enabling firewall ports (22, 80, 443, ${PANEL_PORT})..."
ufw allow 22/tcp 2>/dev/null || true
ufw allow 80/tcp 2>/dev/null || true
ufw allow 443/tcp 2>/dev/null || true
ufw allow ${PANEL_PORT}/tcp 2>/dev/null || true

# ------------------------------------------------------------------------------
# STEP 7: Completed
# ------------------------------------------------------------------------------
sleep 2
SERVER_IP=$(curl -s -4 ifconfig.me || curl -s icanhazip.com || echo "97.74.84.87")

echo ""
echo "=============================================================================="
if systemctl is-active --quiet vps-panel; then
    echo " [SUCCESS] VPS Control Panel Installed Freshly & Running!"
else
    echo " [WARNING] Service starting up. Checking logs:"
    journalctl -u vps-panel -n 20 --no-pager
fi
echo "=============================================================================="
echo ""
echo " Panel URL:      http://${SERVER_IP}:${PANEL_PORT}/login"
echo " Username:       admin"
echo " Password:       admin123456"
echo ""
echo " Web Root:       /www/wwwroot"
echo " Service Status: systemctl status vps-panel"
echo "=============================================================================="

