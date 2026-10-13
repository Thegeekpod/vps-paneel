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

echo "[-] 4/7 Removing default conflicting Nginx site..."
rm -f /etc/nginx/sites-enabled/default

echo "[-] 5/7 Synchronizing file permissions across all websites (aaPanel-style)..."
/usr/local/bin/vps-agent --action=ManageWebsiteFile --payload='{"action":"fix_permissions_all"}' || true

# Also ensure panel writable dirs
chown -R www-data:www-data "$PANEL_DIR/database" "$PANEL_DIR/storage" "$PANEL_DIR/bootstrap/cache"
chmod -R 775 "$PANEL_DIR/database" "$PANEL_DIR/storage" "$PANEL_DIR/bootstrap/cache"
chmod 666 "$PANEL_DIR/database/database.sqlite" 2>/dev/null || true

echo "[-] 6/7 Clearing caches and optimizing..."
sudo -u www-data php artisan optimize:clear
sudo -u www-data php artisan config:cache
sudo -u www-data php artisan route:cache
sudo -u www-data php artisan view:cache

echo "[-] 7/7 Testing and reloading web server..."
nginx -t && systemctl reload nginx
systemctl reload php8.3-fpm 2>/dev/null || systemctl restart php8.3-fpm 2>/dev/null || true

echo ""
echo "=============================================================================="
echo " [OK] VPS Control Panel is up to date & synchronized successfully!"
echo "=============================================================================="
