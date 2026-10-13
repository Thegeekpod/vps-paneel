#!/usr/bin/env bash
# ==============================================================================
# VPS Control Panel Auto-Updater & Permission Synchronizer
# ==============================================================================
set -e

if [[ $EUID -ne 0 ]]; then
   echo "[ERROR] Please run with sudo: sudo bash update.sh"
   exit 1
fi

PANEL_DIR="/var/www/vps-panel"
if [[ ! -d "$PANEL_DIR" ]]; then
    PANEL_DIR="$(pwd)"
fi

cd "$PANEL_DIR"

echo "[-] 1/7 Pulling latest panel updates from Git..."
git pull origin main

echo "[-] 2/7 Installing and securing privileged vps-agent..."
cp "$PANEL_DIR/bin/vps-agent" /usr/local/bin/vps-agent
chmod 750 /usr/local/bin/vps-agent
chown root:www-data /usr/local/bin/vps-agent

echo "[-] 3/7 Ensuring sudoers privileges for www-data..."
cat > /etc/sudoers.d/vps-panel-agent << 'EOF'
www-data ALL=(ALL) NOPASSWD: /usr/local/bin/vps-agent, /var/www/vps-panel/bin/vps-agent
EOF
chmod 440 /etc/sudoers.d/vps-panel-agent
visudo -c -f /etc/sudoers.d/vps-panel-agent

echo "[-] 4/8 Setting up default port 80 catch-all site so port 80 is never refused..."
rm -f /etc/nginx/sites-enabled/default

cat > /etc/nginx/sites-available/00-default-catchall.conf << 'EOFDEFAULT'
server {
    listen 80 default_server;
    listen [::]:80 default_server;
    server_name _;
    root /var/www/vps-panel/public/default-site;
    index index.html;

    location / {
        try_files $uri $uri/ /index.html;
    }
}
EOFDEFAULT
ln -sf /etc/nginx/sites-available/00-default-catchall.conf /etc/nginx/sites-enabled/00-default-catchall.conf

echo "[-] 5/8 Configuring PHP-FPM systemd overrides (allowing /etc, /home and Let's Encrypt write access)..."
for ver in 8.2 8.3 8.4; do
    if systemctl list-unit-files "php${ver}-fpm.service" 2>/dev/null | grep -q "php${ver}-fpm"; then
        mkdir -p "/etc/systemd/system/php${ver}-fpm.service.d"
        cat > "/etc/systemd/system/php${ver}-fpm.service.d/override.conf" << 'EOFOVERRIDE'
[Service]
ProtectSystem=false
ProtectHome=false
PrivateTmp=false
ReadWritePaths=/etc /home /var /tmp /run
EOFOVERRIDE
    fi
done
systemctl daemon-reload

echo "[-] 6/8 Ensuring Let's Encrypt and Certbot directories exist..."
mkdir -p /etc/letsencrypt /var/log/letsencrypt /var/lib/letsencrypt
chmod 755 /etc/letsencrypt /var/log/letsencrypt /var/lib/letsencrypt

echo "[-] 7/8 Synchronizing all website virtual hosts and permissions (aaPanel-style)..."
sudo -u www-data php artisan vps:sync-websites || true

# Ensure all available website configs are symlinked to enabled
for conf in /etc/nginx/sites-available/*.conf; do
    if [[ -f "$conf" && "$(basename "$conf")" != "default" ]]; then
        ln -sf "$conf" "/etc/nginx/sites-enabled/$(basename "$conf")"
    fi
done

# Also ensure panel writable dirs
chown -R www-data:www-data "$PANEL_DIR/database" "$PANEL_DIR/storage" "$PANEL_DIR/bootstrap/cache"
chmod -R 775 "$PANEL_DIR/database" "$PANEL_DIR/storage" "$PANEL_DIR/bootstrap/cache"
chmod 666 "$PANEL_DIR/database/database.sqlite" 2>/dev/null || true

echo "[-] 8/8 Clearing caches and restarting services..."
sudo -u www-data php artisan optimize:clear
sudo -u www-data php artisan config:cache
sudo -u www-data php artisan route:cache
sudo -u www-data php artisan view:cache

nginx -t && (systemctl restart nginx || systemctl start nginx)
systemctl restart php8.3-fpm 2>/dev/null || systemctl reload php8.3-fpm 2>/dev/null || true

echo ""
echo "=============================================================================="
echo " [OK] VPS Control Panel is up to date & synchronized successfully!"
echo "=============================================================================="
