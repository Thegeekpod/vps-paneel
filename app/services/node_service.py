import os
import re
import shutil
import socket
import subprocess
from pathlib import Path
from typing import Dict, Any, Tuple, Optional
from app.database import get_db

def find_binary(name: str) -> Optional[str]:
    """Finds path of a binary looking in common system directories and user PATH."""
    found = shutil.which(name)
    if found:
        return found
    common_paths = [
        f"/usr/local/bin/{name}",
        f"/usr/bin/{name}",
        f"/bin/{name}",
        f"/opt/homebrew/bin/{name}",
        f"/root/.nvm/versions/node/*/bin/{name}",
    ]
    for p in common_paths:
        if "*" in p:
            import glob
            matches = glob.glob(p)
            if matches:
                return matches[0]
        elif Path(p).exists():
            return p
    return None

def get_node_environment() -> Dict[str, Any]:
    """Detects system Node.js, NPM, and PM2 versions."""
    node_bin = find_binary("node")
    npm_bin = find_binary("npm")
    pm2_bin = find_binary("pm2")

    node_version = None
    npm_version = None
    pm2_version = None

    if node_bin:
        try:
            res = subprocess.run([node_bin, "-v"], stdout=subprocess.PIPE, stderr=subprocess.PIPE, text=True, timeout=5)
            node_version = res.stdout.strip()
        except Exception:
            pass

    if npm_bin:
        try:
            res = subprocess.run([npm_bin, "-v"], stdout=subprocess.PIPE, stderr=subprocess.PIPE, text=True, timeout=5)
            npm_version = res.stdout.strip()
        except Exception:
            pass

    if pm2_bin:
        try:
            res = subprocess.run([pm2_bin, "-v"], stdout=subprocess.PIPE, stderr=subprocess.PIPE, text=True, timeout=5)
            pm2_version = res.stdout.strip()
        except Exception:
            pass

    return {
        "installed": bool(node_bin),
        "node_path": node_bin,
        "node_version": node_version or "Not installed",
        "npm_path": npm_bin,
        "npm_version": npm_version or "Not installed",
        "pm2_path": pm2_bin,
        "pm2_version": pm2_version or "Not installed",
        "has_pm2": bool(pm2_bin)
    }

def is_port_in_use(port: int) -> bool:
    """Checks whether a local TCP port is already open/listening."""
    with socket.socket(socket.AF_INET, socket.SOCK_STREAM) as s:
        s.settimeout(0.5)
        return s.connect_ex(('127.0.0.1', port)) == 0

def get_next_available_node_port(start_port: int = 3000) -> int:
    """Finds the next unused local port for a Node/Next.js application."""
    conn = get_db()
    cursor = conn.cursor()
    try:
        cursor.execute("SELECT node_port FROM websites WHERE node_port IS NOT NULL")
        used_db_ports = {row["node_port"] for row in cursor.fetchall() if row["node_port"]}
    except Exception:
        used_db_ports = set()
    conn.close()

    port = start_port
    while port in used_db_ports or is_port_in_use(port):
        port += 1
        if port > 65000:
            return 3000
    return port

def detect_node_app_details(root_dir: str) -> Dict[str, Any]:
    """Inspects a directory for Next.js or Node.js project signatures."""
    p = Path(root_dir)
    package_json = p / "package.json"
    is_nextjs = False
    is_nodejs = False
    start_script = "npm start"
    has_build = False
    project_name = p.name

    if package_json.exists():
        is_nodejs = True
        try:
            content = package_json.read_text(errors="ignore")
            if '"next"' in content or (p / "next.config.js").exists() or (p / "next.config.mjs").exists() or (p / "next.config.ts").exists():
                is_nextjs = True
                start_script = "npm run start"
            elif '"start"' in content:
                start_script = "npm start"
            if '"build"' in content:
                has_build = True
        except Exception:
            pass

    # Check for direct entry files
    entry_candidates = ["server.js", "app.js", "index.js", "main.js", "dist/server.js", "dist/index.js"]
    for c in entry_candidates:
        if (p / c).exists():
            is_nodejs = True
            if not package_json.exists():
                start_script = f"node {c}"
            break

    return {
        "is_nodejs": is_nodejs,
        "is_nextjs": is_nextjs,
        "recommended_type": "nextjs" if is_nextjs else ("nodejs" if is_nodejs else "php"),
        "recommended_script": start_script,
        "has_package_json": package_json.exists(),
        "has_build_script": has_build
    }

def install_pm2_if_missing() -> bool:
    """Attempts to globally install PM2 via NPM if missing."""
    npm_bin = find_binary("npm")
    if not npm_bin:
        return False
    try:
        res = subprocess.run([npm_bin, "install", "-g", "pm2"], stdout=subprocess.PIPE, stderr=subprocess.PIPE, text=True, timeout=120)
        return res.returncode == 0
    except Exception:
        return False

def start_node_app(domain: str, root_dir: str, script: str = "npm start", port: int = 3000) -> Tuple[bool, str]:
    """Starts a Node.js or Next.js application using PM2 or background runner."""
    env_info = get_node_environment()
    pm2_bin = env_info.get("pm2_path")

    if not pm2_bin:
        install_pm2_if_missing()
        pm2_bin = find_binary("pm2")

    clean_script = (script or "npm start").strip()
    root_path = Path(root_dir)
    root_path.mkdir(parents=True, exist_ok=True)

    # Prepare environment variables
    env = os.environ.copy()
    env["PORT"] = str(port)
    env["NODE_ENV"] = "production"

    if pm2_bin:
        # Stop existing instance if any
        subprocess.run([pm2_bin, "delete", domain], stdout=subprocess.PIPE, stderr=subprocess.PIPE, timeout=10)

        # Parse command
        if clean_script.startswith("npm run ") or clean_script == "npm start":
            script_sub = clean_script.replace("npm run ", "").replace("npm ", "").strip()
            cmd = [
                pm2_bin, "start", "npm",
                "--name", domain,
                "--cwd", str(root_path),
                "--", "run", script_sub,
                "--", "-p", str(port)
            ]
        elif clean_script.startswith("node "):
            entry_file = clean_script[5:].strip()
            cmd = [
                pm2_bin, "start", entry_file,
                "--name", domain,
                "--cwd", str(root_path)
            ]
        elif clean_script.startswith("next start"):
            cmd = [
                pm2_bin, "start", "npx",
                "--name", domain,
                "--cwd", str(root_path),
                "--", "next", "start", "-p", str(port)
            ]
        else:
            parts = clean_script.split()
            cmd = [pm2_bin, "start", parts[0], "--name", domain, "--cwd", str(root_path)]
            if len(parts) > 1:
                cmd.extend(["--"] + parts[1:])

        try:
            res = subprocess.run(cmd, cwd=str(root_path), env=env, stdout=subprocess.PIPE, stderr=subprocess.PIPE, text=True, timeout=30)
            if res.returncode != 0:
                return False, f"PM2 error: {res.stderr.strip() or res.stdout.strip()}"

            # Save PM2 state for automatic reboot persistence
            subprocess.run([pm2_bin, "save"], stdout=subprocess.PIPE, stderr=subprocess.PIPE, timeout=10)
            update_node_status_in_db(domain, "running", port, clean_script)
            return True, f"Application '{domain}' started successfully on port {port} via PM2."
        except Exception as e:
            return False, f"Failed to start PM2 process: {e}"

    # Fallback to nohup if PM2 is unavailable
    pid_file = root_path / ".app.pid"
    log_file = root_path / ".app.log"
    run_cmd = f"PORT={port} NODE_ENV=production nohup {clean_script} > {log_file} 2>&1 & echo $! > {pid_file}"
    try:
        proc = subprocess.Popen(run_cmd, shell=True, cwd=str(root_path), env=env)
        update_node_status_in_db(domain, "running", port, clean_script)
        return True, f"Application started in background on port {port} (PID tracked)."
    except Exception as e:
        return False, f"Fallback execution error: {e}"

def stop_node_app(domain: str, root_dir: str = "") -> Tuple[bool, str]:
    """Stops the Node.js/Next.js application."""
    pm2_bin = find_binary("pm2")
    stopped = False

    if pm2_bin:
        try:
            res = subprocess.run([pm2_bin, "stop", domain], stdout=subprocess.PIPE, stderr=subprocess.PIPE, text=True, timeout=15)
            if res.returncode == 0:
                subprocess.run([pm2_bin, "save"], stdout=subprocess.PIPE, stderr=subprocess.PIPE, timeout=5)
                stopped = True
        except Exception:
            pass

    if root_dir:
        pid_file = Path(root_dir) / ".app.pid"
        if pid_file.exists():
            try:
                pid = pid_file.read_text().strip()
                if pid.isdigit():
                    subprocess.run(["kill", "-9", pid], stdout=subprocess.PIPE, stderr=subprocess.PIPE, timeout=5)
                pid_file.unlink(missing_ok=True)
                stopped = True
            except Exception:
                pass

    update_node_status_in_db(domain, "stopped")
    return True, f"Application '{domain}' stopped successfully."

def restart_node_app(domain: str, root_dir: str = "", script: str = "npm start", port: int = 3000) -> Tuple[bool, str]:
    """Restarts the Node.js/Next.js application."""
    pm2_bin = find_binary("pm2")
    if pm2_bin:
        try:
            res = subprocess.run([pm2_bin, "restart", domain], stdout=subprocess.PIPE, stderr=subprocess.PIPE, text=True, timeout=15)
            if res.returncode == 0:
                update_node_status_in_db(domain, "running", port, script)
                return True, f"Application '{domain}' restarted successfully via PM2."
        except Exception:
            pass

    stop_node_app(domain, root_dir)
    return start_node_app(domain, root_dir, script, port)

def get_node_app_status(domain: str, port: int = 3000) -> Dict[str, Any]:
    """Gets real-time runtime status for a Node.js/Next.js application."""
    pm2_bin = find_binary("pm2")
    is_running = False
    status_str = "stopped"
    cpu = "0%"
    memory = "0 MB"
    uptime = "0s"
    pid = None

    if pm2_bin:
        try:
            res = subprocess.run([pm2_bin, "jlist"], stdout=subprocess.PIPE, stderr=subprocess.PIPE, text=True, timeout=5)
            if res.returncode == 0:
                import json
                apps = json.loads(res.stdout)
                for app in apps:
                    if app.get("name") == domain:
                        pm2_env = app.get("pm2_env", {})
                        status_str = pm2_env.get("status", "stopped")
                        is_running = (status_str == "online")
                        monit = app.get("monit", {})
                        cpu = f"{monit.get('cpu', 0)}%"
                        mem_bytes = monit.get('memory', 0)
                        memory = f"{round(mem_bytes / (1024*1024), 1)} MB"
                        pid = app.get("pid")
                        break
        except Exception:
            pass

    if not is_running:
        # Fallback port probe
        if is_port_in_use(port):
            is_running = True
            status_str = "running (port active)"

    return {
        "domain": domain,
        "is_running": is_running,
        "status": status_str,
        "port": port,
        "cpu": cpu,
        "memory": memory,
        "pid": pid
    }

def get_node_logs(domain: str, root_dir: str = "", lines: int = 60) -> str:
    """Retrieves recent stdout and stderr logs for a Node.js app."""
    pm2_bin = find_binary("pm2")
    if pm2_bin:
        try:
            res = subprocess.run([pm2_bin, "logs", domain, "--lines", str(lines), "--nostream"],
                                   stdout=subprocess.PIPE, stderr=subprocess.PIPE, text=True, timeout=8)
            output = res.stdout.strip() or res.stderr.strip()
            if output:
                return output
        except Exception:
            pass

    if root_dir:
        log_file = Path(root_dir) / ".app.log"
        if log_file.exists():
            try:
                content = log_file.read_text(errors="replace")
                all_lines = content.splitlines()
                return "\n".join(all_lines[-lines:])
            except Exception as e:
                return f"Error reading log file: {e}"

    return "No active logs found for this application."

def build_node_app(root_dir: str) -> Tuple[bool, str]:
    """Runs npm install & npm run build in the app directory."""
    npm_bin = find_binary("npm")
    if not npm_bin:
        return False, "NPM binary not found on server."

    p = Path(root_dir)
    if not (p / "package.json").exists():
        return False, f"package.json not found in {root_dir}"

    try:
        # 1. npm install
        i_res = subprocess.run([npm_bin, "install", "--production=false"], cwd=str(p),
                               stdout=subprocess.PIPE, stderr=subprocess.PIPE, text=True, timeout=300)
        if i_res.returncode != 0:
            return False, f"npm install failed: {i_res.stderr.strip() or i_res.stdout.strip()}"

        # 2. npm run build
        b_res = subprocess.run([npm_bin, "run", "build"], cwd=str(p),
                               stdout=subprocess.PIPE, stderr=subprocess.PIPE, text=True, timeout=300)
        if b_res.returncode != 0:
            return False, f"npm run build failed: {b_res.stderr.strip() or b_res.stdout.strip()}"

        return True, "Dependencies installed and build succeeded!"
    except Exception as e:
        return False, f"Build error: {e}"

def update_node_status_in_db(domain: str, status: str, port: int = None, script: str = None):
    """Updates node_status, node_port, and node_script in database."""
    try:
        conn = get_db()
        cursor = conn.cursor()
        if port and script:
            cursor.execute(
                "UPDATE websites SET node_status = ?, node_port = ?, node_script = ? WHERE domain = ?",
                (status, port, script, domain)
            )
        else:
            cursor.execute("UPDATE websites SET node_status = ? WHERE domain = ?", (status, domain))
        conn.commit()
        conn.close()
    except Exception:
        pass

def get_all_configured_node_apps() -> list:
    """Retrieves all websites configured as Node.js or Next.js along with their runtime stats."""
    try:
        conn = get_db()
        cursor = conn.cursor()
        cursor.execute("SELECT id, domain, root_dir, project_type, node_port, node_script, node_status FROM websites WHERE project_type IN ('nextjs', 'nodejs') ORDER BY id DESC")
        rows = [dict(r) for r in cursor.fetchall()]
        conn.close()
    except Exception:
        return []

    for r in rows:
        port = r.get("node_port") or 3000
        status_info = get_node_app_status(r["domain"], port)
        r["is_running"] = status_info.get("is_running", False)
        r["status"] = status_info.get("status", "stopped")
        r["cpu"] = status_info.get("cpu", "0%")
        r["memory"] = status_info.get("memory", "0 MB")
        r["pid"] = status_info.get("pid")
    return rows

def install_node_environment() -> Tuple[bool, str]:
    """Installs Node.js LTS, NPM, and PM2 globally if missing."""
    if os.name == 'nt':
        return True, "Simulated Node.js installation on Windows."
    try:
        cmd = "curl -fsSL https://deb.nodesource.com/setup_20.x | bash - && apt-get install -y nodejs && npm install -g pm2"
        res = subprocess.run(cmd, shell=True, stdout=subprocess.PIPE, stderr=subprocess.PIPE, text=True, timeout=180)
        if res.returncode == 0:
            return True, "Node.js (v20 LTS), NPM, and PM2 installed successfully!"
        else:
            return False, f"Installation failed: {res.stderr.strip() or res.stdout.strip()}"
    except Exception as e:
        return False, f"Installation error: {e}"

