#!/usr/bin/env bash

# ==============================================================================
# VPS Control Panel - Automated Ubuntu 24.04 LTS Blank Server Installer
# ==============================================================================
# Target OS: Ubuntu 24.04 LTS (Noble Numbat)
# Minimum RAM: 2 GB (4 GB recommended)
# ==============================================================================

set -euo pipefail
IFS=$'\n\t'

INSTALL_LOG="/var/log/vps-panel-install.log"
INSTALL_DIR="/var/www/vps-panel"
PANEL_USER="vpspanel"
PANEL_PORT="8443"
PHP_VERSION="8.4"
UNATTENDED=false
ADMIN_EMAIL="admin@example.com"
ADMIN_PASSWORD=""

mkdir -p "$(dirname "$INSTALL_LOG")"
exec > >(tee -a "$INSTALL_LOG") 2>&1

echo "======================================================================"
echo "    VPS Hosting Control Panel - Automated Installer                   "
echo "    Target: Ubuntu 24.04 LTS                                          "
echo "======================================================================"
echo "Installation started at: $(date)"

# ------------------------------------------------------------------------------
# 1. Parse Arguments
# ------------------------------------------------------------------------------
while [[ $# -gt 0 ]]; do
    case "$1" in
        -y|--unattended)
            UNATTENDED=true
            shift
            ;;
        --email=*)
            ADMIN_EMAIL="${1#*=}"
            shift
            ;;
        --password=*)
            ADMIN_PASSWORD="${1#*=}"
            shift
            ;;
        --port=*)
            PANEL_PORT="${1#*=}"
            shift
            ;;
        *)
            echo "Unknown argument: $1"
            exit 1
            ;;
    esac
done

# ------------------------------------------------------------------------------
# 2. System Pre-flight Validation
# ------------------------------------------------------------------------------
echo "[-] Checking system pre-requisites..."

# Root privileges check
if [[ $EUID -ne 0 ]]; then
   echo "[ERROR] This installer must be run as root (EUID 0). Use 'sudo bash install.sh'."
   exit 1
fi

# Operating System Validation (Ubuntu 24.04)
if [[ -f /etc/os-release ]]; then
    . /etc/os-release
    if [[ "$ID" != "ubuntu" ]] || [[ "$VERSION_ID" != "24.04" ]]; then
        echo "[WARNING] This installer is specifically tailored and tested on Ubuntu 24.04 LTS."
        echo "Detected: $PRETTY_NAME"
        if [[ "$UNATTENDED" != "true" ]]; then
            read -rp "Do you wish to continue anyway? (y/N): " confirm_os
            if [[ "${confirm_os,,}" != "y" ]]; then
                echo "Installation aborted."
                exit 1
            fi
        fi
    fi
else
    echo "[ERROR] Cannot detect operating system. /etc/os-release is missing."
    exit 1
fi

# Memory Check (Requires at least 2GB, recommends 4GB)
TOTAL_RAM_MB=$(free -m | awk '/^Mem:/{print $2}')
if [[ $TOTAL_RAM_MB -lt 1800 ]]; then
    echo "[ERROR] Insufficient RAM. At least 2048 MB RAM required. Detected: ${TOTAL_RAM_MB} MB."
    exit 1
fi
echo "[OK] System RAM: ${TOTAL_RAM_MB} MB"

# Disk Space Check (Requires at least 8GB free)
FREE_DISK_MB=$(df -m / | awk 'NR==2 {print $4}')
if [[ $FREE_DISK_MB -lt 8192 ]]; then
    echo "[ERROR] Insufficient disk space on /. At least 8 GB required. Free: ${FREE_DISK_MB} MB."
    exit 1
fi
echo "[OK] Free Disk Space: ${FREE_DISK_MB} MB"

# Hostname & Network Connectivity
if ! curl -s --connect-timeout 5 https://1.1.1.1 > /dev/null; then
    echo "[ERROR] No external internet connection detected. Network access is required."
    exit 1
fi
echo "[OK] Network connectivity verified."

# Detect Conflicting Control Panels
CONFLICTING=("cpanel" "plesk" "cyberpanel" "aapanel" "webmin" "directadmin")
for panel in "${CONFLICTING[@]}"; do
    if which "$panel" &>/dev/null || [[ -d "/usr/local/$panel" ]]; then
        echo "[ERROR] Conflicting hosting panel detected ($panel). Please install on a clean Ubuntu VPS."
        exit 1
    fi
done

# Confirm installation on existing server
if [[ "$UNATTENDED" != "true" ]]; then
    echo ""
    echo "This script will install and configure Nginx, MariaDB, PowerDNS, Postfix, Dovecot, PHP, and UFW."
    read -rp "Proceed with installation? [Y/n]: " confirm_install
    confirm_install="${confirm_install:-y}"
    if [[ "${confirm_install,,}" != "y" ]]; then
        echo "Installation aborted by user."
        exit 0
    fi
fi

# ------------------------------------------------------------------------------
# 3. Package Installation
# ------------------------------------------------------------------------------
echo "[-] Updating package lists and installing core dependencies..."
export DEBIAN_FRONTEND=noninteractive

# Free port 53 from systemd-resolved so PowerDNS can bind to it
if [[ -f /etc/systemd/resolved.conf ]]; then
    mkdir -p /etc/systemd/resolved.conf.d
    cat > /etc/systemd/resolved.conf.d/pdns.conf << 'EOF'
[Resolve]
DNSStubListener=no
DNS=1.1.1.1 8.8.8.8
EOF
    systemctl restart systemd-resolved 2>/dev/null || true
fi

# Prevent services from failing apt triggers before they are configured
cat > /usr/sbin/policy-rc.d << 'EOF'
#!/bin/sh
exit 101
EOF
chmod +x /usr/sbin/policy-rc.d

apt-get update -y
apt-get install -y --no-install-recommends \
    curl \
    git \
    unzip \
    tar \
    software-properties-common \
    ca-certificates \
    gnupg \
    lsb-release \
    ufw \
    nginx \
    mariadb-server \
    mariadb-client \
    pdns-server \
    pdns-backend-mysql \
    postfix \
    dovecot-core \
    dovecot-imapd \
    dovecot-pop3d \
    dovecot-lmtpd \
    restic \
    certbot \
    python3-certbot-nginx

# Add ondrej/php repository for multi-version PHP support
if ! grep -q "ondrej/php" /etc/apt/sources.list /etc/apt/sources.list.d/* 2>/dev/null; then
    add-apt-repository -y ppa:ondrej/php
    apt-get update -y
fi

# Install PHP 8.3 & PHP 8.4 packages
apt-get install -y \
    php8.3-fpm php8.3-cli php8.3-common php8.3-mysql php8.3-mbstring php8.3-xml \
    php8.3-curl php8.3-zip php8.3-intl php8.3-bcmath php8.3-sqlite3 \
    php8.4-fpm php8.4-cli php8.4-common php8.4-mysql php8.4-mbstring php8.4-xml \
    php8.4-curl php8.4-zip php8.4-intl php8.4-bcmath php8.4-sqlite3

# Re-enable service management now that packages are installed
rm -f /usr/sbin/policy-rc.d

# Install Composer if missing
if ! command -v composer &> /dev/null; then
    echo "[-] Installing Composer..."
    curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
fi

# ------------------------------------------------------------------------------
# 4. System Users & Directories
# ------------------------------------------------------------------------------
echo "[-] Setting up dedicated panel user and system directories..."

if ! id "$PANEL_USER" &>/dev/null; then
    useradd -r -m -s /bin/bash -d "/home/$PANEL_USER" "$PANEL_USER"
    usermod -a -G www-data "$PANEL_USER"
fi

mkdir -p "$INSTALL_DIR"
mkdir -p /var/log/vps-panel
mkdir -p /var/backups/restic
mkdir -p /etc/vps-panel
mkdir -p /var/vmail

chown -R "$PANEL_USER:$PANEL_USER" "$INSTALL_DIR"
chown -R "$PANEL_USER:$PANEL_USER" /var/log/vps-panel
chmod 750 /var/log/vps-panel

# Generate Restic password file if missing
if [[ ! -f /etc/vps-panel/restic-password ]]; then
    openssl rand -base64 32 > /etc/vps-panel/restic-password
    chmod 600 /etc/vps-panel/restic-password
    chown root:root /etc/vps-panel/restic-password
fi

# ------------------------------------------------------------------------------
# 5. Privileged Server Agent Configuration
# ------------------------------------------------------------------------------
echo "[-] Configuring privileged server agent with strict sudoers allowlist..."

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
if [[ -f "$SCRIPT_DIR/bin/vps-agent" ]]; then
    cp "$SCRIPT_DIR/bin/vps-agent" /usr/local/bin/vps-agent
else
    echo "[ERROR] bin/vps-agent not found in current installer directory."
    exit 1
fi

chmod 750 /usr/local/bin/vps-agent
chown "root:$PANEL_USER" /usr/local/bin/vps-agent

# Sudoers allowlist: ONLY allow execution of /usr/local/bin/vps-agent
cat > /etc/sudoers.d/vps-panel-agent << EOF
# Strict allowlist: VPS Panel Agent
$PANEL_USER ALL=(ALL) NOPASSWD: /usr/local/bin/vps-agent
EOF
chmod 440 /etc/sudoers.d/vps-panel-agent
visudo -c -f /etc/sudoers.d/vps-panel-agent || {
    echo "[ERROR] Sudoers file syntax validation failed!"
    rm -f /etc/sudoers.d/vps-panel-agent
    exit 1
}

# ------------------------------------------------------------------------------
# 6. Panel Codebase Setup
# ------------------------------------------------------------------------------
cp -rn "$SCRIPT_DIR"/* "$INSTALL_DIR/" || true
cp -rn "$SCRIPT_DIR"/.[!.]* "$INSTALL_DIR/" 2>/dev/null || true
cp "$SCRIPT_DIR/.env.example" "$INSTALL_DIR/.env" 2>/dev/null || true

# Mark directory safe for Git
git config --system --add safe.directory "$INSTALL_DIR" 2>/dev/null || true

cd "$INSTALL_DIR"

# Ensure .env exists
if [[ ! -f "$INSTALL_DIR/.env" ]]; then
    cp "$INSTALL_DIR/.env.example" "$INSTALL_DIR/.env"
fi

# Configure Database in .env
# Default to SQLite for lightweight reliability or configure MySQL
sed -i 's/DB_CONNECTION=.*/DB_CONNECTION=sqlite/' "$INSTALL_DIR/.env"
touch "$INSTALL_DIR/database/database.sqlite"

# Set permissions
chown -R "$PANEL_USER:$PANEL_USER" "$INSTALL_DIR"
chmod -R 775 "$INSTALL_DIR/storage" "$INSTALL_DIR/bootstrap/cache" "$INSTALL_DIR/database"

# Install composer dependencies as panel user
sudo -u "$PANEL_USER" composer install --no-dev --optimize-autoloader --no-interaction

# Generate application key and run migrations
sudo -u "$PANEL_USER" php artisan key:generate --force
sudo -u "$PANEL_USER" php artisan migrate --force
sudo -u "$PANEL_USER" php artisan db:seed --force

# Generate initial admin credentials
if [[ -z "$ADMIN_PASSWORD" ]]; then
    ADMIN_PASSWORD=$(openssl rand -base64 12)
fi
sudo -u "$PANEL_USER" php artisan vps:create-admin --email="$ADMIN_EMAIL" --password="$ADMIN_PASSWORD" --name="Administrator"

# Publish Filament assets
sudo -u "$PANEL_USER" php artisan filament:assets || true
sudo -u "$PANEL_USER" php artisan config:cache
sudo -u "$PANEL_USER" php artisan route:cache
sudo -u "$PANEL_USER" php artisan view:cache

# ------------------------------------------------------------------------------
# 7. Nginx VirtualHost for Control Panel
# ------------------------------------------------------------------------------
echo "[-] Configuring Nginx reverse proxy for VPS Control Panel on port ${PANEL_PORT}..."

# Generate self-signed TLS cert for panel port initial access
mkdir -p /etc/vps-panel/ssl
if [[ ! -f /etc/vps-panel/ssl/panel.crt ]]; then
    openssl req -x509 -nodes -days 3650 -newkey rsa:2048 \
        -keyout /etc/vps-panel/ssl/panel.key \
        -out /etc/vps-panel/ssl/panel.crt \
        -subj "/C=US/ST=State/L=City/O=VPS Panel/CN=$(hostname)" \
        &>/dev/null
    chmod 600 /etc/vps-panel/ssl/panel.key
fi

cat > /etc/nginx/sites-available/vps-panel.conf << EOF
server {
    listen ${PANEL_PORT} ssl http2 default_server;
    listen [::]:${PANEL_PORT} ssl http2 default_server;
    server_name _;

    ssl_certificate /etc/vps-panel/ssl/panel.crt;
    ssl_certificate_key /etc/vps-panel/ssl/panel.key;
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers HIGH:!aNULL:!MD5;

    root ${INSTALL_DIR}/public;
    index index.php index.html;

    access_log /var/log/nginx/vps-panel-access.log;
    error_log /var/log/nginx/vps-panel-error.log;

    # Security Headers
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-XSS-Protection "1; mode=block" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header Referrer-Policy "strict-origin-when-cross-origin" always;

    # Redirect plain HTTP requests on this port directly to HTTPS
    error_page 497 301 =307 https://\$http_host\$request_uri;

    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php${PHP_VERSION}-fpm.sock;
        fastcgi_param SCRIPT_FILENAME \$document_root\$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\. {
        deny all;
    }
}
EOF

ln -sf /etc/nginx/sites-available/vps-panel.conf /etc/nginx/sites-enabled/vps-panel.conf
nginx -t

# Ensure PHP-FPM and Nginx are started and enabled
systemctl restart php${PHP_VERSION}-fpm || systemctl start php${PHP_VERSION}-fpm
systemctl enable php${PHP_VERSION}-fpm 2>/dev/null || true
systemctl restart nginx || systemctl start nginx
systemctl enable nginx 2>/dev/null || true

# ------------------------------------------------------------------------------
# 8. Systemd Services (Queue Worker & Scheduler)
# ------------------------------------------------------------------------------
echo "[-] Creating systemd services for Laravel Queue and Scheduler..."

# Queue Worker Service
cat > /etc/systemd/system/vps-panel-worker.service << EOF
[Unit]
Description=VPS Control Panel Queue Worker
After=network.target

[Service]
User=${PANEL_USER}
Group=${PANEL_USER}
Restart=always
ExecStart=/usr/bin/php ${INSTALL_DIR}/artisan queue:work --sleep=3 --tries=3 --max-time=3600
RestartSec=5s

[Install]
WantedBy=multi-user.target
EOF

# Scheduler Timer & Service
cat > /etc/systemd/system/vps-panel-scheduler.service << EOF
[Unit]
Description=VPS Control Panel Scheduler Run
After=network.target

[Service]
Type=oneshot
User=${PANEL_USER}
Group=${PANEL_USER}
ExecStart=/usr/bin/php ${INSTALL_DIR}/artisan schedule:run
EOF

cat > /etc/systemd/system/vps-panel-scheduler.timer << EOF
[Unit]
Description=Run VPS Control Panel Scheduler every minute

[Timer]
OnCalendar=*:0/1
Persistent=true

[Install]
WantedBy=timers.target
EOF

systemctl daemon-reload
systemctl enable --now vps-panel-worker.service
systemctl enable --now vps-panel-scheduler.timer

# ------------------------------------------------------------------------------
# 9. Firewall Configuration (UFW)
# ------------------------------------------------------------------------------
echo "[-] Configuring Uncomplicated Firewall (UFW)..."

ufw allow 22/tcp comment 'SSH' || true
ufw allow 80/tcp comment 'HTTP Web' || true
ufw allow 443/tcp comment 'HTTPS Web' || true
ufw allow ${PANEL_PORT}/tcp comment 'VPS Control Panel' || true
ufw allow 53 comment 'PowerDNS' || true
ufw allow 25/tcp comment 'Postfix SMTP' || true
ufw allow 587/tcp comment 'Postfix Submission' || true
ufw allow 993/tcp comment 'Dovecot IMAPS' || true

ufw --force enable

# ------------------------------------------------------------------------------
# 10. Summary & Completion Report
# ------------------------------------------------------------------------------
SERVER_IP=$(curl -s https://api.ipify.org || hostname -I | awk '{print $1}')

# Save credentials to /root/vps-panel-credentials.txt
CRED_FILE="/root/vps-panel-credentials.txt"
cat > "$CRED_FILE" << EOF
======================================================
  VPS CONTROL PANEL ACCESS CREDENTIALS
======================================================
  URL:       https://${SERVER_IP}:${PANEL_PORT}/admin
  Email:     ${ADMIN_EMAIL}
  Password:  ${ADMIN_PASSWORD}
======================================================
EOF
chmod 600 "$CRED_FILE" 2>/dev/null || true

echo ""
echo "======================================================================"
echo "    >>> INSTALLATION SUCCESSFUL! <<<                                  "
echo "======================================================================"
echo ""
echo "  [+] PANEL URL : https://${SERVER_IP}:${PANEL_PORT}/admin"
echo "  [+] USERNAME  : ${ADMIN_EMAIL}"
echo "  [+] PASSWORD  : ${ADMIN_PASSWORD}"
echo ""
echo "  Credentials saved to: ${CRED_FILE}"
echo "  Installation log:     ${INSTALL_LOG}"
echo "======================================================================"
echo ""
