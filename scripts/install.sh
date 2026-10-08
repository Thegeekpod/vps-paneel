#!/usr/bin/env bash
# ==============================================================================
# VPS Control Panel - One-Line Blank Server Auto-Provisioner
# Supported OS: Ubuntu 22.04 LTS / Ubuntu 24.04 LTS / Debian 12
# Repository: https://github.com/Thegeekpod/vps-paneel
# ==============================================================================

set -e

# ANSI Color Codes
BOLD='\033[1m'
GREEN='\033[0;32m'
CYAN='\033[0;36m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
BLUE='\033[0;34m'
PURPLE='\033[0;35m'
NC='\033[0m'

echo -e "${PURPLE}${BOLD}"
cat << 'EOF'
 __     ______   ____    _____                      _ 
 \ \   / /  _ \ / ___|  |  __ \                    | |
  \ \ / /| |_) |\___ \  | |__) |_ _ _ __   ___  ___| |
   \ V / |  __/  ___) | |  ___/ _` | '_ \ / _ \/ _ \ |
    \_/  |_|    |____/  |_|  \__,_|_| |_|\___/\___|_|
EOF
echo -e "${NC}"
echo -e "${CYAN}===================================================================${NC}"
echo -e "${BOLD}🚀 VPS PANEEL AUTO-INSTALLER FOR BLANK UBUNTU SERVERS${NC}"
echo -e "   This script transforms a fresh VPS into a multi-stack hosting hub"
echo -e "   Supports: Next.js (Node 22), Laravel, WordPress, and Custom PHP"
echo -e "   Databases: MySQL & PostgreSQL with phpMyAdmin integration"
echo -e "${CYAN}===================================================================${NC}"
echo ""

# 1. Root Check
if [ "$EUID" -ne 0 ]; then
  echo -e "${RED}[ERROR] Please run this script as root: sudo bash install.sh${NC}"
  exit 1
fi

export DEBIAN_FRONTEND=noninteractive

# 2. Update System Packages
echo -e "${BLUE}[1/9] Updating package lists & installing base essentials...${NC}"
apt-get update -y
apt-get install -y curl wget git unzip zip htop software-properties-common ca-certificates lsb-release apt-transport-https ufw fail2ban sqlite3

# 3. Add Repositories (Ondřej Surý PHP & NodeSource Node.js 22 LTS)
echo -e "${BLUE}[2/9] Adding PHP 8.3 and Node.js 22 LTS repositories...${NC}"
if ! grep -q "ondrej/php" /etc/apt/sources.list /etc/apt/sources.list.d/* 2>/dev/null; then
    add-apt-repository -y ppa:ondrej/php
fi

if ! command -v node &> /dev/null; then
    curl -fsSL https://deb.nodesource.com/setup_22.x | bash -
fi

apt-get update -y

# 4. Install Nginx, PHP 8.3 + FPM, MySQL, PostgreSQL, Node.js & Composer
echo -e "${BLUE}[3/9] Installing Web Server, PHP 8.3 FPM, MySQL, PostgreSQL & Node.js...${NC}"
apt-get install -y nginx
apt-get install -y php8.3 php8.3-fpm php8.3-cli php8.3-common php8.3-mysql php8.3-pgsql php8.3-mbstring \
                   php8.3-xml php8.3-curl php8.3-zip php8.3-gd php8.3-bcmath php8.3-intl \
                   php8.3-sqlite3
apt-get install -y mysql-server
apt-get install -y postgresql postgresql-contrib
apt-get install -y nodejs

# Install Composer
if ! command -v composer &> /dev/null; then
    echo -e "${BLUE}[4/9] Installing Composer globally...${NC}"
    curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
fi

# Install Global NPM Packages (PM2 & Next)
echo -e "${BLUE}[5/9] Installing PM2 Process Manager...${NC}"
npm install -g pm2 next

# Install Certbot for Let's Encrypt SSL
apt-get install -y certbot python3-certbot-nginx

# 5. Clone and Set up VPS Control Panel
PANEL_DIR="/var/www/vps-panel"
echo -e "${BLUE}[6/9] Deploying VPS Control Panel to ${PANEL_DIR}...${NC}"

if [ -d "$PANEL_DIR" ]; then
    echo "Directory exists. Updating codebase..."
    cd "$PANEL_DIR"
    git pull origin main || true
else
    mkdir -p /var/www
    git clone https://github.com/Thegeekpod/vps-paneel.git "$PANEL_DIR"
    cd "$PANEL_DIR"
fi

# Setup Environment File
if [ ! -f "$PANEL_DIR/.env" ]; then
    cp "$PANEL_DIR/.env.example" "$PANEL_DIR/.env"
fi

# Configure SQLite DB for Panel Internal Data
mkdir -p "$PANEL_DIR/database"
touch "$PANEL_DIR/database/database.sqlite"

sed -i "s/DB_CONNECTION=.*/DB_CONNECTION=sqlite/" "$PANEL_DIR/.env"
sed -i "s/# DB_DATABASE=.*/DB_DATABASE=\/var\/www\/vps-panel\/database\/database.sqlite/" "$PANEL_DIR/.env" || true

# Install Composer Dependencies
echo -e "${BLUE}[7/9] Installing dependencies and building dashboard UI...${NC}"
composer install --no-dev --optimize-autoloader --no-interaction

# Generate App Key
php artisan key:generate --force

# Install NPM Packages and Build Production Assets
npm install --silent
npm run build

# Run Database Migrations & Seeds
php artisan migrate --force
php artisan db:seed --force

# Set Admin User Credentials
ADMIN_EMAIL="admin@vps-panel.local"
ADMIN_PASSWORD="password"
php artisan panel:admin --email="$ADMIN_EMAIL" --password="$ADMIN_PASSWORD" --name="VPS Administrator"

# 6. Install phpMyAdmin
echo -e "${BLUE}[8/9] Setting up phpMyAdmin for MySQL management...${NC}"
PMA_DIR="$PANEL_DIR/public/phpmyadmin"
mkdir -p "$PMA_DIR"
if [ ! -f "$PMA_DIR/index.php" ]; then
    curl -sSL https://www.phpmyadmin.net/downloads/phpMyAdmin-latest-all-languages.tar.gz | tar -xz -C "$PMA_DIR" --strip-components=1 || true
    if [ -f "$PMA_DIR/config.sample.inc.php" ]; then
        cp "$PMA_DIR/config.sample.inc.php" "$PMA_DIR/config.inc.php"
        BLOWFISH_SECRET=$(openssl rand -base64 32 | tr -d "=+/" | cut -c1-32)
        sed -i "s/\$cfg\['blowfish_secret'\] = '';/\$cfg\['blowfish_secret'\] = '${BLOWFISH_SECRET}';/" "$PMA_DIR/config.inc.php" || true
    fi
fi

# Fix Permissions
chown -R www-data:www-data "$PANEL_DIR"
chmod -R 775 "$PANEL_DIR/storage" "$PANEL_DIR/bootstrap/cache" "$PANEL_DIR/database"

# 7. Configure Nginx for Panel on Port 8080
cat << 'EOF' > /etc/nginx/sites-available/vps-panel.conf
server {
    listen 8080 default_server;
    listen [::]:8080 default_server;

    root /var/www/vps-panel/public;
    index index.php index.html;

    server_name _;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include fastcgi_params;
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        fastcgi_hide_header X-Powered-By;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }

    client_max_body_size 128M;
}
EOF

ln -sf /etc/nginx/sites-available/vps-panel.conf /etc/nginx/sites-enabled/vps-panel.conf

# Test and Restart Nginx, PHP, MySQL, and PostgreSQL
nginx -t
systemctl restart php8.3-fpm
systemctl restart nginx
systemctl restart mysql
systemctl restart postgresql

# 8. Configure UFW Firewall
echo -e "${BLUE}[9/9] Configuring UFW Firewall...${NC}"
ufw default deny incoming
ufw default allow outgoing
ufw allow 22/tcp comment 'SSH'
ufw allow 80/tcp comment 'HTTP'
ufw allow 443/tcp comment 'HTTPS'
ufw allow 8080/tcp comment 'VPS Control Panel'
ufw --force enable

# Get Public Server IP
SERVER_IP=$(curl -s -4 ifconfig.me || curl -s -4 icanhazip.com || hostname -I | awk '{print $1}')

# Final Success Banner
echo ""
echo -e "${GREEN}${BOLD}===================================================================${NC}"
echo -e "${GREEN}${BOLD}   🎉 VPS PANEEL HAS BEEN INSTALLED SUCCESSFULLY!${NC}"
echo -e "${GREEN}${BOLD}===================================================================${NC}"
echo ""
echo -e "   ${BOLD}Portal URL:${NC}      ${YELLOW}${BOLD}http://${SERVER_IP}:8080${NC}"
echo -e "   ${BOLD}phpMyAdmin URL:${NC}  ${CYAN}${BOLD}http://${SERVER_IP}:8080/phpmyadmin${NC}"
echo -e "   ${BOLD}Admin Email:${NC}     ${CYAN}${ADMIN_EMAIL}${NC}"
echo -e "   ${BOLD}Admin Password:${NC}  ${CYAN}${ADMIN_PASSWORD}${NC}"
echo ""
echo -e "${CYAN}===================================================================${NC}"
echo -e "  Databases Ready:"
echo -e "  • ${BOLD}MySQL / MariaDB${NC} (Port 3306 &bull; Web Manager: phpMyAdmin)"
echo -e "  • ${BOLD}PostgreSQL${NC}      (Port 5432 &bull; Web Manager: Adminer)"
echo -e ""
echo -e "  Application Runtimes Ready:"
echo -e "  • ${BOLD}Next.js${NC}   (Node.js 22 LTS + PM2 Daemon + Reverse Proxy)"
echo -e "  • ${BOLD}Laravel${NC}   (PHP 8.3 FPM + /public Root + Composer + .env)"
echo -e "  • ${BOLD}WordPress${NC} (Auto MySQL DB + wp-config + Salts + Rewrites)"
echo -e "  • ${BOLD}PHP${NC}       (FastCGI pool on Nginx)"
echo -e "${CYAN}===================================================================${NC}"
echo -e "  Open your browser and navigate to: ${YELLOW}http://${SERVER_IP}:8080${NC}"
echo ""
