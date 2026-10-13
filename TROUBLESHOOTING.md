# Troubleshooting & Operations Guide

This guide covers resolution steps for common issues encountered when managing hosting environments on Ubuntu 24.04.

---

## 1. Email Delivery & Port 25 Blocked

### Symptom
Outbound emails do not leave the server or time out when connecting to remote mail exchangers (Gmail, Outlook, etc.).

### Root Cause
Most major cloud hosting providers (including **Hetzner, DigitalOcean, AWS EC2, Linode/Akamai, and Vultr**) block outbound TCP port 25 by default to prevent spam.

### Solution
1. **Check Port 25 Connectivity**:
   ```bash
   telnet smtp.gmail.com 25
   ```
   If it hangs or reports `Connection timed out`, outbound port 25 is blocked by your hosting provider.
2. **Request Unblocking**: Open a support ticket with your VPS hosting provider and request removal of the port 25 restriction for legitimate transactional mail.
3. **Alternative — Use SMTP Relay**: Configure Postfix to relay outbound mail through Amazon SES, SendGrid, Mailgun, or Postmark via port `587`.

---

## 2. Reverse DNS (rDNS / PTR) & SPF/DKIM/DMARC

### Symptom
Emails sent from the server land in the recipient's Spam / Junk folder.

### Checklist
1. **Reverse DNS (PTR Record)**:
   - The PTR record of your server's public IP **must** match your mail hostname (e.g. `mail.yourdomain.com`).
   - PTR records can **only** be configured in your VPS provider's control panel (Hetzner Cloud, DigitalOcean Networking, AWS Elastic IP, etc.) — not in PowerDNS.
2. **SPF Record**:
   - Ensure your DNS zone contains: `v=spf1 mx a ip4:YOUR_SERVER_IP ~all`.
3. **DKIM Record**:
   - In the panel, open **Email** -> click **DNS Setup Guide** -> copy the 2048-bit DKIM public key into your DNS zone under `mail._domainkey`.
4. **DMARC Record**:
   - Ensure you publish: `v=DMARC1; p=quarantine; pct=100; rua=mailto:postmaster@yourdomain.com`.

---

## 3. Nginx Configuration Test Fails (`nginx -t`)

### Symptom
When creating a new website or issuing SSL, the panel reports `Nginx configuration test failed` and rolls back.

### Resolution
1. **Inspect Detailed Syntax Errors**:
   ```bash
   sudo nginx -t
   ```
2. **Check for Duplicate Domains**:
   Look for overlapping `server_name` entries across `/etc/nginx/sites-enabled/`:
   ```bash
   grep -rn "server_name" /etc/nginx/sites-enabled/
   ```
3. **Remove Broken Symlinks**:
   ```bash
   ls -la /etc/nginx/sites-enabled/
   sudo rm /etc/nginx/sites-enabled/broken-vhost.conf
   sudo systemctl reload nginx
   ```

---

## 4. Let's Encrypt SSL Issuance Fails

### Symptom
Certbot returns `Challenge failed for domain... (HTTP-01)`.

### Resolution
1. **Verify Public DNS**:
   Confirm that the domain's public DNS `A` record points to your server's exact public IP:
   ```bash
   dig +short example.com
   ```
2. **Verify Port 80 is Accessible**:
   Let's Encrypt requires port 80 to verify domain ownership via `.well-known/acme-challenge`.
   ```bash
   sudo ufw status | grep 80
   curl -I http://example.com/.well-known/acme-challenge/test
   ```
3. **Disable Cloudflare Proxy Temporarily**:
   If using Cloudflare, change the DNS proxy status from "Proxied" (orange cloud) to "DNS Only" (gray cloud) during initial SSL issuance.

---

## 5. Restic Backup Lock or Repository Issues

### Symptom
Scheduled backup fails with: `repository is already locked by another process`.

### Resolution
1. **Unlock the Repository**:
   ```bash
   sudo restic -r /var/backups/restic --password-file /etc/vps-panel/restic-password unlock
   ```
2. **Check Repository Integrity**:
   ```bash
   sudo restic -r /var/backups/restic --password-file /etc/vps-panel/restic-password check
   ```

---

## 6. Service Management Commands

Useful CLI commands for diagnosing system health:

```bash
# Check Queue Worker status
sudo systemctl status vps-panel-worker.service

# Restart Queue Worker
sudo systemctl restart vps-panel-worker.service

# Check Scheduler timer
sudo systemctl status vps-panel-scheduler.timer

# View Panel Agent logs
sudo tail -f /var/log/vps-panel/agent.log

# View Nginx access & error logs
sudo tail -f /var/log/nginx/vps-panel-error.log
```
