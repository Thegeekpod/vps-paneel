# Security Architecture & Hardening Guide

The VPS Control Panel is designed with defense-in-depth principles across the web layer, privileged agent layer, and host Linux operating system.

---

## 1. Privileged Server Agent Architecture

### Direct Shell Execution is Strictly Forbidden
Laravel web processes running under the `vpspanel` system user **never** execute arbitrary shell commands directly from HTTP requests. 

Instead, all system modifications are delegated to a dedicated privileged executable:
`/usr/local/bin/vps-agent`

```
Web Request (HTTP)
  └── Laravel Controller / Job (app/Services/ServerAgentService.php)
        └── Invokes: sudo /usr/local/bin/vps-agent --action=ActionName --payload='{...}'
              ├── 1. Validates Action in strict ALLOWLIST
              ├── 2. Validates JSON payload schema and regexes
              ├── 3. Executes atomic change with pre-checks (e.g. nginx -t)
              ├── 4. Performs automatic rollback on error
              └── 5. Returns structured JSON with redacted secrets
```

### Sudoers Principle of Least Privilege
The `vpspanel` user is given sudo access **exclusively** to the agent executable via `/etc/sudoers.d/vps-panel-agent`:

```sudoers
vpspanel ALL=(ALL) NOPASSWD: /usr/local/bin/vps-agent
```

No other root commands or shell escapes are granted to the web user.

---

## 2. Input Validation & Injection Prevention

- **Domain Names**: Validated with strict RFC 1035 regular expressions (`/^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/i`). Semicolons, pipes, and backticks are rejected.
- **Linux Usernames**: Validated with `/^[a-z_][a-z0-9_-]{2,31}$/`. System reserved usernames (`root`, `bin`, `daemon`, `vpspanel`, etc.) cannot be allocated to hosted sites.
- **Database & Mail Names**: Validated with alphanumeric and underscore constraints.
- **Process Arguments**: All external commands in the agent use argument escaping (`escapeshellcmd` and `escapeshellarg`).

---

## 3. Directory Traversal & Filesystem Jail

- **Website Isolation**: Hosted sites are owned by dedicated per-site Linux users (`u_{name}`).
- **Permissions**: Home directories `/home/{user}` are set to `0750` mode.
- **File Manager Confinement**: The built-in File Manager verifies canonical realpaths:
  ```php
  if (! str_starts_with($realTarget, $websiteRoot)) {
      throw new AccessDeniedException("Path traversal attempt detected");
  }
  ```
  Relative traversal segments (`../`, `..\`) and null bytes (`\0`) are stripped before resolution.
- **PHP-FPM open_basedir**: PHP pools enforce:
  ```ini
  php_admin_value[open_basedir] = /home/{user}:/tmp:/usr/share/php
  php_admin_value[disable_functions] = exec,passthru,shell_exec,system,proc_open,popen
  ```

---

## 4. Secret Redaction & Audit Logging

All administrative actions and agent invocations are recorded in the `server_operations` table. Sensitive attributes are automatically redacted:

- `password`
- `db_password`
- `root_password`
- `private_key`
- `dkim_private_key`
- `token`
- `restic_password`

Redacted output is displayed in logs as: `******** (redacted)`.

---

## 5. Network & Firewall Hardening

- **Private Databases**: MariaDB listens on `127.0.0.1` by default and is never exposed to the public internet.
- **Firewall (UFW)**: Unsolicited incoming ports are dropped. Only explicitly allowlisted hosting ports (SSH, HTTP/S, DNS, Mail) are permitted.
- **Rate Limiting**: Panel authentication routes are protected by Livewire/Laravel rate limiters to prevent brute-force attacks.
- **Passwords**: Hashed with modern algorithms (Argon2id or Bcrypt with 12 rounds).
