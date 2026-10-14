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
