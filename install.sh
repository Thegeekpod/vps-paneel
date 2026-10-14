#!/usr/bin/env bash
# ==============================================================================
# VPS Control Panel (Python Edition - aaPanel Style)
# Complete Automated Installer for Ubuntu
# ==============================================================================
set -e

if [[ $EUID -ne 0 ]]; then
   echo "[ERROR] This installer must be run as root (EUID 0). Use 'sudo bash install.sh'."
   exit 1
fi

INSTALL_DIR="/opt/vps-panel"
PANEL_PORT=8443

echo "=============================================================================="
echo " Starting VPS Control Panel (Python Edition) Installation..."
echo "=============================================================================="

echo "[-] 1/6 Installing system dependencies (Python3, Nginx, Certbot)..."
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
    ufw

echo "[-] 2/6 Creating directory structure..."
mkdir -p "$INSTALL_DIR"
mkdir -p "/www/wwwroot"
mkdir -p "$INSTALL_DIR/data"

# Copy panel files to /opt/vps-panel
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cp -rf "$SCRIPT_DIR/app" "$INSTALL_DIR/"
cp -f "$SCRIPT_DIR/requirements.txt" "$INSTALL_DIR/"
cp -f "$SCRIPT_DIR/run.py" "$INSTALL_DIR/"

echo "[-] 3/6 Setting up Python virtual environment & dependencies..."
if [[ ! -d "$INSTALL_DIR/venv" ]]; then
    python3 -m venv "$INSTALL_DIR/venv"
fi

"$INSTALL_DIR/venv/bin/pip" install --upgrade pip
"$INSTALL_DIR/venv/bin/pip" install -r "$INSTALL_DIR/requirements.txt"

echo "[-] 4/6 Configuring Nginx default catch-all & clean setup..."
mkdir -p /var/www/html
cat > /var/www/html/index.html << 'EOF'
<!DOCTYPE html>
<html>
<head><title>VPS Panel - Server Online</title></head>
<body style="font-family:sans-serif;text-align:center;padding:50px;background:#0f172a;color:#fff;">
<h1>Server Online</h1>
<p>Domain pointed successfully. Please add this domain in your VPS Control Panel.</p>
</body></html>
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

echo "[-] 5/6 Creating systemd service (vps-panel.service)..."
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
ExecStart=${INSTALL_DIR}/venv/bin/python3 run.py
Restart=always
RestartSec=3

[Install]
WantedBy=multi-user.target
EOF

systemctl daemon-reload
systemctl enable vps-panel
systemctl restart vps-panel

echo "[-] 6/6 Configuring firewall rules..."
ufw allow 22/tcp 2>/dev/null || true
ufw allow 80/tcp 2>/dev/null || true
ufw allow 443/tcp 2>/dev/null || true
ufw allow ${PANEL_PORT}/tcp 2>/dev/null || true

# Detect public IP
SERVER_IP=$(curl -s -4 ifconfig.me || curl -s icanhazip.com || echo "YOUR_SERVER_IP")

echo ""
echo "=============================================================================="
echo " [SUCCESS] VPS Control Panel (Python Edition) Installed Successfully!"
echo "=============================================================================="
echo ""
echo " Panel URL:      http://${SERVER_IP}:${PANEL_PORT}"
echo " Username:       admin"
echo " Password:       admin123456"
echo ""
echo " Web Root:       /www/wwwroot"
echo " Service Status: systemctl status vps-panel"
echo "=============================================================================="
