import os
import subprocess
import shlex
from pathlib import Path
from typing import Optional
from fastapi import APIRouter, Request, Depends, Form
from fastapi.responses import HTMLResponse, JSONResponse, StreamingResponse

from app.auth import login_required
from app.templates_util import render
from app.database import log_action

router = APIRouter(prefix="/terminal")

@router.get("", response_class=HTMLResponse)
async def terminal_view(request: Request, user: dict = Depends(login_required)):
    server_ip = request.headers.get("host", "").split(":")[0] or "97.74.84.87"
    return render("terminal.html", {
        "user": user,
        "server_ip": server_ip,
        "active_nav": "terminal"
    }, request)

@router.post("/execute")
async def execute_terminal_command(
    request: Request,
    command: str = Form(...),
    cwd: Optional[str] = Form("/root"),
    user: dict = Depends(login_required)
):
    cmd = command.strip()
    if not cmd:
        return JSONResponse({"output": "", "cwd": cwd, "returncode": 0})

    # Validate cwd
    work_dir = Path(cwd) if cwd and Path(cwd).is_dir() else Path("/root")
    if not work_dir.exists():
        work_dir = Path("/")

    # Handle cd command manually
    if cmd.startswith("cd ") or cmd == "cd":
        target = cmd[3:].strip() if len(cmd) > 2 else "/root"
        target = os.path.expanduser(target)
        if target.startswith("/"):
            new_path = Path(target)
        else:
            new_path = (work_dir / target).resolve()

        if new_path.exists() and new_path.is_dir():
            return JSONResponse({"output": "", "cwd": str(new_path), "returncode": 0})
        else:
            return JSONResponse({"output": f"bash: cd: {target}: No such file or directory\n", "cwd": str(work_dir), "returncode": 1})

    # Execute system command
    try:
        res = subprocess.run(
            cmd,
            shell=True,
            cwd=str(work_dir),
            stdout=subprocess.PIPE,
            stderr=subprocess.STDOUT,
            text=True,
            timeout=30
        )
        output = res.stdout
        log_action(user["username"], "TerminalCmd", f"Ran: {cmd[:50]}", request.client.host)
        return JSONResponse({
            "output": output,
            "cwd": str(work_dir),
            "returncode": res.returncode
        })
    except subprocess.TimeoutExpired:
        return JSONResponse({
            "output": "Command timed out after 30 seconds.\n",
            "cwd": str(work_dir),
            "returncode": 124
        })
    except Exception as e:
        return JSONResponse({
            "output": f"Execution error: {str(e)}\n",
            "cwd": str(work_dir),
            "returncode": 1
        })
