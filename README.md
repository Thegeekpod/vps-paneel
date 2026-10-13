# VPS Control Panel (Laravel & Filament)

A modern, minimalist VPS hosting control panel engineered for Ubuntu 24.04 LTS servers. Built with **Laravel**, **Filament Admin**, **Nginx**, **PHP-FPM**, **MariaDB**, **PowerDNS**, **Postfix**, **Dovecot**, and **Restic**.

---

## Architecture Overview

```
                          ┌──────────────────────────┐
                          │   Client Web Browser     │
                          └────────────┬─────────────┘
                                       │ HTTPS (:8443)
                                       ▼
                          ┌──────────────────────────┐
                          │    Nginx Reverse Proxy   │
                          └────────────┬─────────────┘
                                       │ Unix Socket
                                       ▼
                          ┌──────────────────────────┐
                          │ Laravel + Filament Panel │
                          │   (user: vpspanel)       │
                          └────────────┬─────────────┘
                                       │
            Strict JSON IPC via sudo   │ (No direct arbitrary shell commands!)
                                       ▼
                          ┌──────────────────────────┐
                          │ Privileged Server Agent  │
                          │   /usr/local/bin/        │
                          │      vps-agent           │
                          │ (Allowlisted operations) │
                          └────────────┬─────────────┘
                                       │
     ┌─────────────┬─────────────┬─────┴───────┬─────────────┬─────────────┐
     ▼             ▼             ▼             ▼             ▼             ▼
┌─────────┐   ┌─────────┐   ┌─────────┐   ┌─────────┐   ┌─────────┐   ┌─────────┐
│  Nginx  │   │ PHP-FPM │   │ MariaDB │   │PowerDNS │   │ Postfix │   │ Restic  │
│ Vhosts  │   │  Pools  │   │Databases│   │  Zones  │   │ Dovecot │   │ Backups │
└─────────┘   └─────────┘   └─────────┘   └─────────┘   └─────────┘   └─────────┘
```

---

## Key Features

1. **Dashboard & Resource Monitoring**:
   - Live CPU, RAM, Disk, Load Average, and Uptime telemetry.
   - Status indicators for all core daemons (Nginx, MariaDB, PHP-FPM, PowerDNS, Postfix, Dovecot, UFW).
2. **Websites Management**:
   - Create, edit, suspend, unsuspend, and delete hosted websites.
   - Per-site isolated Linux users (`u_username`), document roots (`/home/{user}/web/{domain}/public_html`), custom PHP-FPM pools, and Nginx vhosts.
3. **Domains & Routing**:
   - Add domains, subdomains, domain aliases, and HTTP 301/302 redirects.
4. **DNS Management (PowerDNS)**:
   - Full zone management for `A`, `AAAA`, `CNAME`, `MX`, `TXT`, `NS`, `SRV`, and `CAA` records.
   - Syntax validation and automatic standard record templates.
5. **SSL Certificates (Let's Encrypt & Custom)**:
   - Automated ACME HTTP-01 issuance and renewal via Certbot.
   - Custom SSL certificate and private key upload with cryptographic validation.
6. **Databases (MariaDB)**:
   - Provision MariaDB databases, restricted localhost database users, and privilege grants.
7. **Email System (Postfix & Dovecot)**:
   - Virtual mailboxes, quotas, and alias forwarding rules.
   - Password hashing with Argon2id / SHA512-CRYPT.
   - In-app DNS setup guidance (SPF, 2048-bit DKIM, DMARC).
8. **Encrypted Backups (Restic)**:
   - Scheduled automated backups of website files and database dumps.
   - Encrypted snapshot creation, retention prune policies (keep-daily, keep-weekly), and one-click restore.
9. **Sandboxed File Manager**:
   - Safe visual file browser, in-browser code editor, file upload, and permission viewer.
   - Strict jail confinement to `/home/{user}/web/{domain}/public_html` (prevents path traversal and directory escapes).
10. **Server & Service Settings**:
    - Hostname, public IP, and port configuration.
    - Web-based daemon restart and reload (Nginx, MariaDB, PHP-FPM, Postfix, Dovecot).
    - Uncomplicated Firewall (UFW) port rule manager.
11. **Jobs & Audit Logs**:
    - Complete operation history, execution latency, and error tracing.
    - Automatic redaction of passwords, private keys, and secrets from logs.
12. **Administrators & Access Control**:
    - Role-based authorization (`SuperAdmin`, `Admin`, `Operator`, `Viewer`).
13. **One-Click Self-Update**:
    - Directly update the control panel from the Dashboard or Server Settings with one click.
    - Automatically pulls new Git commits (`git pull`), executes pending migrations (`php artisan migrate`), recompiles Filament assets, clears caches, and restarts background queue workers.

---

## Quickstart

### Automated Blank Ubuntu 24.04 VPS Installation

On a completely blank Ubuntu 24.04 LTS server (4 GB RAM recommended):

```bash
# Clone the repository
git clone https://github.com/thegeekpod/vps-paneel.git /tmp/vps-paneel
cd /tmp/vps-paneel

# Run the installer
sudo bash install.sh
```

For unattended / scriptable installation:

```bash
sudo bash install.sh -y --email="admin@yourdomain.com" --password="YourSecurePassword123!" --port=8443
```

After installation completes, navigate to:
```
https://YOUR_SERVER_IP:8443/admin
```

---

## Local Development & Testing

The panel supports full zero-configuration local development on macOS/Linux using SQLite and simulation mode:

```bash
# Install PHP dependencies
composer install

# Run database migrations and seeder
php artisan migrate --seed

# Start the development server
php artisan serve
```

Visit [http://localhost:8000/admin](http://localhost:8000/admin) and log in with:
- **Email**: `admin@example.com`
- **Password**: `Admin123!Secure`

Run automated tests:
```bash
php artisan test
```

---

## Documentation

- [INSTALL.md](file:///Users/user/Herd/vps-paneel/INSTALL.md) — Production installation guide for Ubuntu 24.04 LTS.
- [SECURITY.md](file:///Users/user/Herd/vps-paneel/SECURITY.md) — Security model, privileged agent design, and isolation.
- [TROUBLESHOOTING.md](file:///Users/user/Herd/vps-paneel/TROUBLESHOOTING.md) — Diagnosis and fixes for common hosting issues.
