<?php

namespace App\Services;

class InstallerScriptService
{
    /**
     * Generate the complete bash installer script for a blank Ubuntu 22.04 / 24.04 VPS.
     */
    public function generateBashScript(string $panelPort = '8080'): string
    {
        return <<<'BASH'
#!/usr/bin/env bash
# ==============================================================================
# VPS Control Panel - Automated Blank Server Provisioner
# Supported OS: Ubuntu 22.04 LTS / Ubuntu 24.04 LTS
# Installs: Nginx, PHP 8.3 + FPM, MySQL, Node.js 22, PM2, Composer, Certbot, UFW
# ==============================================================================

set -e

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
BLUE='\033[0;34m'
YELLOW='\033[1;33m'
CYAN='\033[0;36m'
NC='\033[0m'

echo -e "${CYAN}"
echo "=========================================================="
echo "    🚀 STARTING VPS PANEEL AUTO-INSTALLER"
echo "    Turning fresh Ubuntu VPS into a complete hosting server"
echo "=========================================================="
echo -e "${NC}"

# 1. Root Check
if [ "$EUID" -ne 0 ]; then
  echo -e "${RED}[ERROR] Please run this script as root (sudo bash install.sh)${NC}"
  exit 1
fi

export DEBIAN_FRONTEND=noninteractive

# 2. Update System Packages
echo -e "${BLUE}[1/8] Updating system repositories...${NC}"
apt-get update -y && apt-get upgrade -y
apt-get install -y curl wget git unzip zip htop software-properties-common ca-certificates lsb-release apt-transport-https ufw fail2ban

# 3. Add Repositories (PHP & Node.js 22 LTS)
echo -e "${BLUE}[2/8] Adding PHP and Node.js repositories...${NC}"
add-apt-repository -y ppa:ondrej/php
curl -fsSL https://deb.nodesource.com/setup_22.x | bash -

apt-get update -y

# 4. Install Nginx, PHP 8.3, MySQL, Node & PM2
echo -e "${BLUE}[3/8] Installing Web Server, PHP 8.3, MySQL, Node.js 22 & PM2...${NC}"
apt-get install -y nginx
apt-get install -y php8.3 php8.3-fpm php8.3-cli php8.3-common php8.3-mysql php8.3-mbstring \
                   php8.3-xml php8.3-curl php8.3-zip php8.3-gd php8.3-bcmath php8.3-intl \
                   php8.3-sqlite3 php8.3-redis
apt-get install -y mysql-server
apt-get install -y nodejs
npm install -g pm2 next

# Install Composer
echo -e "${BLUE}[4/8] Installing Composer...${NC}"
if ! command -v composer &> /dev/null; then
    curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
fi

# Install Certbot for Let's Encrypt SSL
echo -e "${BLUE}[5/8] Installing Certbot (Let's Encrypt)...${NC}"
apt-get install -y certbot python3-certbot-nginx

# 5. Configure Firewall (UFW)
echo -e "${BLUE}[6/8] Configuring UFW Firewall...${NC}"
ufw --force reset
ufw default deny incoming
ufw default allow outgoing
ufw allow 22/tcp comment 'SSH'
ufw allow 80/tcp comment 'HTTP'
ufw allow 443/tcp comment 'HTTPS'
ufw allow 8080/tcp comment 'VPS Control Panel'
ufw --force enable

# 6. Deploy VPS Control Panel Application
echo -e "${BLUE}[7/8] Setting up VPS Control Panel in /var/www/vps-panel...${NC}"
mkdir -p /var/www/vps-panel
mkdir -p /var/www/vps-panel/database

# Set correct ownership
chown -R www-data:www-data /var/www

# Configure Nginx for Panel on port 8080
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
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
EOF

ln -sf /etc/nginx/sites-available/vps-panel.conf /etc/nginx/sites-enabled/vps-panel.conf
systemctl restart nginx
systemctl restart php8.3-fpm
systemctl restart mysql

# 7. Get Server IP
SERVER_IP=$(curl -s -4 ifconfig.me || hostname -I | awk '{print $1}')

echo -e "${GREEN}"
echo "=========================================================="
echo "    🎉 VPS SERVER PROVISIONING COMPLETE!"
echo "=========================================================="
echo -e "Nginx:          ${CYAN}Active${NC}"
echo -e "PHP 8.3 FPM:    ${CYAN}Active${NC}"
echo -e "MySQL:          ${CYAN}Active${NC}"
echo -e "Node.js & PM2:  ${CYAN}Active (v22 LTS)${NC}"
echo -e "UFW Firewall:   ${CYAN}Configured (Ports 22, 80, 443, 8080)${NC}"
echo ""
echo -e "Access your Panel Dashboard at:"
echo -e "${YELLOW}http://${SERVER_IP}:8080${NC}"
echo "=========================================================="
BASH;
    }
}
