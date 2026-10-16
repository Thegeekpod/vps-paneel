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

def get_nvm_dir() -> Path:
    """Finds NVM root directory on the system."""
    candidates = [
        Path(os.environ.get("NVM_DIR", "")),
        Path("/root/.nvm"),
        Path.home() / ".nvm",
        Path("/usr/local/nvm"),
        Path.home() / "Library" / "Application Support" / "Herd" / "config" / "nvm"
    ]
    for c in candidates:
        if c and str(c) != "." and (c / "nvm.sh").exists():
            return c
    return Path("/root/.nvm") if (os.name != 'nt' and hasattr(os, 'getuid') and os.getuid() == 0) else (Path.home() / ".nvm")

def ensure_nvm_installed() -> bool:
    """Installs official NVM (Node Version Manager) if missing."""
    nvm_dir = get_nvm_dir()
    if (nvm_dir / "nvm.sh").exists():
        return True
    if os.name == 'nt':
        return False
    try:
        cmd = 'curl -o- https://raw.githubusercontent.com/nvm-sh/nvm/v0.40.1/install.sh | bash'
        subprocess.run(cmd, shell=True, stdout=subprocess.PIPE, stderr=subprocess.PIPE, timeout=120)
        return (nvm_dir / "nvm.sh").exists()
    except Exception:
        return False

def run_nvm_command(cmd_args: str, timeout: int = 180) -> Tuple[int, str, str]:
    """Executes a command within an NVM environment."""
    nvm_dir = get_nvm_dir()
    sh_file = nvm_dir / "nvm.sh"
    if not sh_file.exists():
        ensure_nvm_installed()

    script = f'''
    export NVM_DIR="{nvm_dir}"
    [ -s "$NVM_DIR/nvm.sh" ] && \\. "$NVM_DIR/nvm.sh"
    {cmd_args}
    '''
    try:
        res = subprocess.run(["bash", "-c", script], stdout=subprocess.PIPE, stderr=subprocess.PIPE, text=True, timeout=timeout)
        return res.returncode, res.stdout.strip(), res.stderr.strip()
    except Exception as e:
        return -1, "", str(e)

def get_installed_node_versions() -> Dict[str, Any]:
    """Retrieves all installed Node.js versions, current default, and available LTS versions."""
    nvm_dir = get_nvm_dir()
    versions_dir = nvm_dir / "versions" / "node"

    # 1. Current active system version
    node_bin = find_binary("node")
    current_version = None
    if node_bin:
        try:
            res = subprocess.run([node_bin, "-v"], stdout=subprocess.PIPE, stderr=subprocess.PIPE, text=True, timeout=5)
            current_version = res.stdout.strip()
        except Exception:
            pass

    installed = []
    seen_majors = set()

    # 2. Check NVM versions directory
    if versions_dir.exists():
        for p in sorted(versions_dir.iterdir(), key=lambda x: x.name, reverse=True):
            if p.is_dir() and p.name.startswith("v"):
                v_str = p.name
                node_exec = p / "bin" / "node"
                if node_exec.exists():
                    major = v_str[1:].split(".")[0] if "." in v_str else v_str[1:]
                    is_curr = bool(current_version and (current_version == v_str or current_version.startswith(v_str)))
                    installed.append({
                        "version": v_str,
                        "major": major,
                        "path": str(node_exec),
                        "is_current": is_curr
                    })
                    seen_majors.add(major)

    # 3. If system node exists and was not in NVM
    if node_bin and not any(i.get("is_current") for i in installed):
        curr_major = ""
        if current_version and current_version.startswith("v"):
            curr_major = current_version[1:].split(".")[0]
        installed.insert(0, {
            "version": current_version or "System Node",
            "major": curr_major,
            "path": node_bin,
            "is_current": True
        })
        if curr_major:
            seen_majors.add(curr_major)

    # 4. Standard LTS channels available
    channels = [
        {"major": "22", "title": "Node.js 22 (LTS Jod)", "recommended": True},
        {"major": "20", "title": "Node.js 20 (LTS Iron)", "recommended": False},
        {"major": "18", "title": "Node.js 18 (LTS Hydrogen)", "recommended": False},
        {"major": "24", "title": "Node.js 24 (Current)", "recommended": False},
    ]

    available = []
    for ch in channels:
        available.append({
            **ch,
            "is_installed": ch["major"] in seen_majors
        })

    return {
        "current_version": current_version or "Not installed",
        "has_nvm": (nvm_dir / "nvm.sh").exists(),
        "installed": installed,
        "available": available
    }

def get_node_binary_for_version(version: Optional[str]) -> Optional[str]:
    """Finds the node binary path for a requested major version or version string."""
    if not version or str(version).lower() in ["default", "system"]:
        return find_binary("node")

    clean_v = str(version).strip().lstrip("v")
    nvm_dir = get_nvm_dir()
    versions_dir = nvm_dir / "versions" / "node"

    if versions_dir.exists():
        for p in sorted(versions_dir.iterdir(), key=lambda x: x.name, reverse=True):
            if p.is_dir():
                name_clean = p.name.lstrip("v")
                if name_clean == clean_v or name_clean.startswith(f"{clean_v}."):
                    node_exec = p / "bin" / "node"
                    if node_exec.exists():
                        return str(node_exec)

    return find_binary("node")

def switch_node_version(target_version: str) -> Tuple[bool, str]:
    """Switches the default active Node.js version on the server."""
    clean_v = target_version.strip()
    if clean_v.startswith("v"):
        clean_v = clean_v[1:]

    target_bin = get_node_binary_for_version(clean_v)
    if not target_bin or not Path(target_bin).exists():
        return False, f"Node.js version {target_version} is not installed. Install it first."

    # 1. If NVM available, set alias default
    run_nvm_command(f"nvm alias default {clean_v} && nvm use {clean_v}")

    # 2. Symlink to /usr/local/bin and /usr/bin for global system persistence
    target_node = Path(target_bin)
    target_npm = target_node.parent / "npm"
    for dest_dir in [Path("/usr/local/bin"), Path("/usr/bin")]:
        try:
            if dest_dir.exists() and os.name != 'nt' and hasattr(os, 'getuid') and os.getuid() == 0:
                link_node = dest_dir / "node"
                if link_node.is_symlink() or not link_node.exists():
                    link_node.unlink(missing_ok=True)
                    link_node.symlink_to(target_node)
                if target_npm.exists():
                    link_npm = dest_dir / "npm"
                    if link_npm.is_symlink() or not link_npm.exists():
                        link_npm.unlink(missing_ok=True)
                        link_npm.symlink_to(target_npm)
        except Exception:
            pass

    # 3. Ensure PM2 is installed in this Node version
    target_pm2 = target_node.parent / "pm2"
    if not target_pm2.exists() and target_npm.exists():
        try:
            subprocess.run([str(target_npm), "install", "-g", "pm2"], stdout=subprocess.PIPE, stderr=subprocess.PIPE, timeout=60)
        except Exception:
            pass

    return True, f"Switched default Node.js version to Node {clean_v} successfully."

def install_node_version(version: str) -> Tuple[bool, str]:
    """Installs a specific Node.js version via NVM."""
    clean_v = version.strip()
    ensure_nvm_installed()

    code, out, err = run_nvm_command(f"nvm install {clean_v}", timeout=300)
    if code != 0:
        return False, f"NVM install failed: {err or out}"

    # Also install pm2 for the newly installed version
    run_nvm_command(f"nvm use {clean_v} && npm install -g pm2", timeout=120)
    return True, f"Node.js {clean_v} installed successfully via NVM!"

def uninstall_node_version(version: str) -> Tuple[bool, str]:
    """Uninstalls an unused Node.js version via NVM."""
    clean_v = version.strip()
    code, out, err = run_nvm_command(f"nvm uninstall {clean_v}", timeout=60)
    if code != 0:
        return False, f"NVM uninstall failed: {err or out}"
    return True, f"Node.js {clean_v} uninstalled successfully."

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

def start_node_app(domain: str, root_dir: str, script: str = "npm start", port: int = 3000, node_version: Optional[str] = None) -> Tuple[bool, str]:
    """Starts a Node.js or Next.js application using PM2 or background runner with specified Node version."""
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

    # Prepend specific Node.js binary directory to PATH if requested
    node_bin = get_node_binary_for_version(node_version)
    if node_bin and Path(node_bin).exists():
        bin_dir = str(Path(node_bin).parent)
        env["PATH"] = f"{bin_dir}:{env.get('PATH', '')}"

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
            if node_bin:
                cmd.extend(["--interpreter", node_bin])
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
            update_node_status_in_db(domain, "running", port, clean_script, node_version)
            return True, f"Application '{domain}' started successfully on port {port} via PM2."
        except Exception as e:
            return False, f"Failed to start PM2 process: {e}"

    # Fallback to nohup if PM2 is unavailable
    pid_file = root_path / ".app.pid"
    log_file = root_path / ".app.log"
    run_cmd = f"PORT={port} NODE_ENV=production nohup {clean_script} > {log_file} 2>&1 & echo $! > {pid_file}"
    try:
        proc = subprocess.Popen(run_cmd, shell=True, cwd=str(root_path), env=env)
        update_node_status_in_db(domain, "running", port, clean_script, node_version)
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

def restart_node_app(domain: str, root_dir: str = "", script: str = "npm start", port: int = 3000, node_version: Optional[str] = None) -> Tuple[bool, str]:
    """Restarts the Node.js/Next.js application."""
    pm2_bin = find_binary("pm2")
    if pm2_bin:
        try:
            res = subprocess.run([pm2_bin, "restart", domain], stdout=subprocess.PIPE, stderr=subprocess.PIPE, text=True, timeout=15)
            if res.returncode == 0:
                update_node_status_in_db(domain, "running", port, script, node_version)
                return True, f"Application '{domain}' restarted successfully via PM2."
        except Exception:
            pass

    stop_node_app(domain, root_dir)
    return start_node_app(domain, root_dir, script, port, node_version)

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

def update_node_status_in_db(domain: str, status: str, port: int = None, script: str = None, version: str = None):
    """Updates node_status, node_port, node_script, and node_version in database."""
    try:
        conn = get_db()
        cursor = conn.cursor()
        updates = ["node_status = ?"]
        params = [status]
        if port is not None:
            updates.append("node_port = ?")
            params.append(port)
        if script is not None:
            updates.append("node_script = ?")
            params.append(script)
        if version is not None:
            updates.append("node_version = ?")
            params.append(version)
        params.append(domain)
        cursor.execute(f"UPDATE websites SET {', '.join(updates)} WHERE domain = ?", tuple(params))
        conn.commit()
        conn.close()
    except Exception:
        pass

def get_all_configured_node_apps() -> list:
    """Retrieves all websites configured as Node.js or Next.js along with their runtime stats."""
    try:
        from app.database import init_db
        init_db()
    except Exception:
        pass

    try:
        conn = get_db()
        cursor = conn.cursor()
        cursor.execute("SELECT * FROM websites WHERE project_type IN ('nextjs', 'nodejs') ORDER BY id DESC")
        rows = [dict(r) for r in cursor.fetchall()]
        conn.close()
    except Exception:
        return []

    for r in rows:
        port = r.get("node_port") or 3000
        try:
            status_info = get_node_app_status(r.get("domain", ""), port)
            r["is_running"] = status_info.get("is_running", False)
            r["status"] = status_info.get("status", "stopped")
            r["cpu"] = status_info.get("cpu", "0%")
            r["memory"] = status_info.get("memory", "0 MB")
            r["pid"] = status_info.get("pid")
        except Exception:
            r["is_running"] = False
            r["status"] = "unknown"
            r["cpu"] = "0%"
            r["memory"] = "0 MB"
            r["pid"] = None
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

