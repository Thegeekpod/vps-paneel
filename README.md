# 🚀 VPS Paneel - Self-Hosted Blank VPS Management Portal

A self-hosted, modern VPS Control Panel built with **Laravel 11+**, designed to run directly on any blank Ubuntu VPS (or locally in Laravel Herd) to host and manage **Next.js**, **Laravel**, **WordPress**, and **Custom PHP** applications with automated Nginx reverse proxying, Let's Encrypt SSL, MySQL databases, and UFW firewall management.

---

## 🌟 Key Features

1. **Multi-Stack Hosting Out-of-the-Box**:
   - **Next.js (Node.js)**: Automated internal port assignment (e.g. 3000, 3001), PM2 cluster ecosystem generator (`ecosystem.config.cjs`), Node 20/22 runtime support, and Nginx WebSocket reverse proxy with static cache headers.
   - **Laravel**: Public document root routing, PHP version selection (8.2, 8.3, 8.4), automated `.env` generation with database credentials, Artisan & Composer integration.
   - **WordPress**: Automated download & setup, automatic isolated MySQL database and user creation, security salts generation, and hardened Nginx rewrite rules.
   - **Custom PHP**: Standard PHP scripts with FastCGI pool and custom web roots.

2. **Automated Nginx Virtual Hosts & SSL**:
   - Automatic generation of production-ready Nginx configurations in `/etc/nginx/sites-available/`.
   - One-click **Let's Encrypt SSL** issuance via Certbot with automated 90-day renewals.
   - Automatic HTTP to HTTPS redirection.

3. **MySQL / MariaDB Manager**:
   - Create, list, and delete databases and isolated database users.
   - Automatic connection string generation.

4. **UFW Firewall Manager**:
   - Add and remove ports and protocols (TCP/UDP) with preset shortcuts for SSH (22), HTTP (80), HTTPS (443), Next.js (3000), and MySQL (3306).

5. **Real-time Server Metrics**:
   - Live CPU percentage, memory usage (RAM), disk storage, load averages, uptime, and core services health status (Nginx, PHP-FPM, MySQL, PM2, Redis, UFW).

6. **Blank VPS Auto-Provisioner Script (`install.sh`)**:
   - Single-command installation that transforms a brand-new blank Ubuntu 22.04 / 24.04 VPS into a fully functional hosting server.

---

## 💻 Local Herd URL

Your portal is running live at:
```
http://vps-paneel.test
```

---

## 🛠️ Deploying on a Blank VPS (Ubuntu 22.04 / 24.04)

On a freshly created VPS, connect via SSH as `root` and run:

```bash
curl -sSL http://<YOUR_PANEL_DOMAIN_OR_IP>/install.sh | sudo bash
```

This single command automatically:
1. Installs Nginx, PHP 8.3 & PHP-FPM, MySQL Server, Node.js 22 LTS, PM2, Composer, and Certbot.
2. Configures UFW firewall for ports 22, 80, 443, and 8080.
3. Sets up this panel at `http://<YOUR_VPS_IP>:8080`.
