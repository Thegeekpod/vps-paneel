import os
import psutil
import time
import subprocess
from datetime import timedelta
from typing import Dict, Any

def get_system_stats() -> Dict[str, Any]:
    # CPU
    cpu_percent = psutil.cpu_percent(interval=None)
    cpu_count = psutil.cpu_count(logical=True)
    
    # Memory
    mem = psutil.virtual_memory()
    mem_total_gb = round(mem.total / (1024**3), 2)
    mem_used_gb = round(mem.used / (1024**3), 2)
    mem_percent = mem.percent
    
    # Disk
    disk = psutil.disk_usage('/')
    disk_total_gb = round(disk.total / (1024**3), 2)
    disk_used_gb = round(disk.used / (1024**3), 2)
    disk_percent = disk.percent
    
    # Uptime
    boot_time = psutil.boot_time()
    uptime_sec = time.time() - boot_time
    uptime_str = str(timedelta(seconds=int(uptime_sec)))
    
    # Load average
    try:
        load1, load5, load15 = os.getloadavg()
    except Exception:
        load1, load5, load15 = 0.0, 0.0, 0.0

    return {
        "cpu_percent": cpu_percent,
        "cpu_count": cpu_count,
        "mem_total_gb": mem_total_gb,
        "mem_used_gb": mem_used_gb,
        "mem_percent": mem_percent,
        "disk_total_gb": disk_total_gb,
        "disk_used_gb": disk_used_gb,
        "disk_percent": disk_percent,
        "uptime": uptime_str,
        "load_avg": f"{load1:.2f}, {load5:.2f}, {load15:.2f}"
    }

def get_service_status(service_name: str) -> bool:
    if os.name == 'nt':
        return True
    try:
        res = subprocess.run(["systemctl", "is-active", service_name], stdout=subprocess.PIPE, stderr=subprocess.PIPE, text=True)
        return res.stdout.strip() == "active"
    except Exception:
        return False

def control_service(service_name: str, action: str) -> bool:
    if os.name == 'nt':
        return True
    if action not in ["restart", "reload", "start", "stop"]:
        return False
    try:
        res = subprocess.run(["systemctl", action, service_name], stdout=subprocess.PIPE, stderr=subprocess.PIPE, text=True)
        return res.returncode == 0
    except Exception:
        return False

def check_panel_update() -> Dict[str, Any]:
    from app.config import get_git_repo_dir
    repo_dir = get_git_repo_dir()
    if not repo_dir.exists() or not (repo_dir / ".git").exists():
        return {
            "has_update": False,
            "message": "Git repo not found",
            "local_commit": "v2.0",
            "remote_commit": "",
            "commit_msg": ""
        }
    
    try:
        local_res = subprocess.run(
            ["git", "rev-parse", "--short", "HEAD"],
            cwd=str(repo_dir), stdout=subprocess.PIPE, stderr=subprocess.PIPE, text=True, timeout=5
        )
        local_commit = local_res.stdout.strip()

        # Fetch remote updates silently
        subprocess.run(
            ["git", "fetch", "origin", "main"],
            cwd=str(repo_dir), stdout=subprocess.PIPE, stderr=subprocess.PIPE, text=True, timeout=10
        )

        remote_res = subprocess.run(
            ["git", "rev-parse", "--short", "origin/main"],
            cwd=str(repo_dir), stdout=subprocess.PIPE, stderr=subprocess.PIPE, text=True, timeout=5
        )
        remote_commit = remote_res.stdout.strip()

        msg_res = subprocess.run(
            ["git", "log", "-1", "--pretty=%s", "origin/main"],
            cwd=str(repo_dir), stdout=subprocess.PIPE, stderr=subprocess.PIPE, text=True, timeout=5
        )
        commit_msg = msg_res.stdout.strip()

        has_update = bool(remote_commit) and (local_commit != remote_commit)
        return {
            "has_update": has_update,
            "local_commit": local_commit,
            "remote_commit": remote_commit,
            "commit_msg": commit_msg or "New panel improvements from GitHub",
            "message": "Update available!" if has_update else "Panel is up to date."
        }
    except Exception as e:
        return {
            "has_update": False,
            "message": str(e),
            "local_commit": "unknown",
            "remote_commit": "",
            "commit_msg": ""
        }

def perform_panel_update() -> Dict[str, Any]:
    from app.config import get_git_repo_dir
    repo_dir = get_git_repo_dir()
    update_script = repo_dir / "update.sh"
    if not update_script.exists():
        return {"success": False, "error": f"Update script not found at {update_script}"}

    try:
        cmd = f"nohup bash {update_script} > /tmp/vps_panel_update.log 2>&1 &"
        subprocess.Popen(cmd, shell=True, cwd=str(repo_dir))
        return {
            "success": True,
            "message": "Panel update process initiated in background. The service will reload in a few seconds."
        }
    except Exception as e:
        return {"success": False, "error": str(e)}

