# Installation Guide — Blank Ubuntu 24.04 LTS

This guide provides step-by-step instructions for deploying the VPS Control Panel on a fresh, completely blank **Ubuntu 24.04 LTS (Noble Numbat)** server.

---

## 1. System Requirements

| Resource | Minimum | Recommended |
| :--- | :--- | :--- |
| **Operating System** | Ubuntu 24.04 LTS (64-bit) | Ubuntu 24.04 LTS (64-bit) |
| **RAM** | 2 GB | 4 GB or higher |
| **CPU** | 1 Core | 2 Cores or higher |
| **Disk Space** | 10 GB Free | 40 GB+ NVMe SSD |
| **Network** | Static IPv4 with outbound internet access | Static IPv4 + IPv6 |

> [!IMPORTANT]
> The server must be a clean installation without existing control panels (such as cPanel, Plesk, CyberPanel, aaPanel, or Webmin).

---

## 2. Pre-Installation Setup

1. **Update Root Password & Set Hostname**:
   ```bash
   sudo hostnamectl set-hostname vps.yourdomain.com
   ```

2. **Ensure SSH Access is Functional**:
   Make sure you have active SSH key or password access. Port 22 will remain open throughout the installation.

---

## 3. Automated Installation

Run the following commands as `root` (or with `sudo`):

```bash
# 1. Download installer repository
cd /tmp
git clone https://github.com/thegeekpod/vps-paneel.git
cd vps-paneel

# 2. Execute the installer
sudo bash install.sh
```

### Unattended (Headless) Installation

For automated orchestration (e.g. cloud-init, Ansible, Terraform), specify the non-interactive flags:

```bash
sudo bash install.sh \
  --unattended \
  --email="admin@yourdomain.com" \
  --password="ReplaceWithStrongPassword123!" \
  --port=8443
```

---

## 4. What the Installer Does

1. **Pre-flight Checks**: Validates Ubuntu 24.04 version, root privileges, RAM, disk space, and internet connectivity.
2. **System Dependencies**: Installs Nginx, MariaDB, PowerDNS, Postfix, Dovecot, PHP 8.3 & 8.4, Composer, Certbot, Restic, and UFW.
3. **Dedicated Panel User**: Creates the isolated system user `vpspanel`.
4. **Privileged Server Agent**: Installs `/usr/local/bin/vps-agent` and configures a strictly locked `/etc/sudoers.d/vps-panel-agent`.
5. **Database & Migrations**: Configures the panel database and runs all migrations and seeders.
6. **Administrator Account**: Creates the initial administrator login.
7. **Nginx Panel Vhost**: Deploys an HTTPS vhost on port `8443` with TLS security headers.
8. **Systemd Services**: Configures `vps-panel-worker.service` (queue) and `vps-panel-scheduler.timer` (cron).
9. **Firewall Rules**: Automatically permits necessary ports (22, 80, 443, 8443, 53, 25, 587, 993).

---

## 5. Post-Installation Steps

1. **Log in to the Control Panel**:
   Open your browser and navigate to:
   ```
   https://YOUR_SERVER_IP:8443/admin
   ```
   *(Accept the temporary self-signed certificate on initial access).*

2. **Point your Domain**:
   Create an `A` record in your DNS provider pointing your domain to the server's public IP address.

3. **Issue a Valid SSL Certificate**:
   Inside the panel, navigate to **Websites** -> select your website -> click **Issue SSL** to obtain a trusted Let's Encrypt TLS certificate.
