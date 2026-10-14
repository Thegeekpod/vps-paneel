import os
import pty
import json
import struct
import fcntl
import termios
import asyncio
import subprocess
from pathlib import Path
from typing import Optional
from fastapi import APIRouter, Request, Depends, WebSocket, WebSocketDisconnect, status
from fastapi.responses import HTMLResponse, JSONResponse

from app.auth import login_required, get_current_user_from_token, COOKIE_NAME
from app.templates_util import render

router = APIRouter(prefix="/terminal")

@router.get("", response_class=HTMLResponse)
async def terminal_view(request: Request, user: dict = Depends(login_required)):
    server_ip = request.headers.get("host", "").split(":")[0] or "97.74.84.87"
    token = request.cookies.get(COOKIE_NAME) or ""
    return render("terminal.html", {
        "user": user,
        "server_ip": server_ip,
        "token": token,
        "active_nav": "terminal"
    }, request)

@router.websocket("/ws")
async def terminal_ws_endpoint(websocket: WebSocket):
    # Verify authentication cookie or query token
    token = websocket.cookies.get(COOKIE_NAME)
    user = get_current_user_from_token(token) if token else None
    if not user:
        q_token = websocket.query_params.get("token")
        if q_token:
            user = get_current_user_from_token(q_token)

    if not user:
        await websocket.close(code=status.WS_1008_POLICY_VIOLATION)
        return

    await websocket.accept()

    # Create real pseudoterminal (PTY)
    try:
        master_fd, slave_fd = pty.openpty()
    except Exception as e:
        await websocket.send_text(f"\r\n[Fatal: Failed to allocate PTY: {e}]\r\n")
        await websocket.close()
        return

    home_dir = "/root" if os.path.exists("/root") else str(Path.home())
    shell = "/bin/bash" if os.path.exists("/bin/bash") else (os.environ.get("SHELL") or "/bin/sh")

    env = dict(os.environ)
    env["TERM"] = "xterm-256color"
    env["COLORTERM"] = "truecolor"
    env["HOME"] = home_dir
    env["USER"] = "root" if (hasattr(os, "getuid") and os.getuid() == 0) else os.environ.get("USER", "root")
    env["LC_ALL"] = "C.UTF-8"
    env["LANG"] = "C.UTF-8"

    try:
        proc = subprocess.Popen(
            [shell, "-l"],
            preexec_fn=os.setsid if hasattr(os, "setsid") else None,
            stdin=slave_fd,
            stdout=slave_fd,
            stderr=slave_fd,
            cwd=home_dir,
            env=env
        )
    except Exception as e:
        os.close(slave_fd)
        os.close(master_fd)
        await websocket.send_text(f"\r\n[Error spawning shell: {e}]\r\n")
        await websocket.close()
        return

    os.close(slave_fd)

    try:
        initial_winsize = struct.pack("HHHH", 28, 110, 0, 0)
        fcntl.ioctl(master_fd, termios.TIOCSWINSZ, initial_winsize)
    except Exception:
        pass

    async def pty_to_ws():
        try:
            while True:
                data = await asyncio.to_thread(os.read, master_fd, 4096)
                if not data:
                    break
                await websocket.send_bytes(data)
        except (OSError, Exception):
            pass

    async def ws_to_pty():
        try:
            while True:
                msg = await websocket.receive()
                if "bytes" in msg and msg["bytes"]:
                    os.write(master_fd, msg["bytes"])
                elif "text" in msg and msg["text"]:
                    text = msg["text"]
                    if text.startswith('{"type":"resize"') or text.startswith('{"type": "resize"'):
                        try:
                            payload = json.loads(text)
                            cols = int(payload.get("cols", 80))
                            rows = int(payload.get("rows", 24))
                            winsize = struct.pack("HHHH", rows, cols, 0, 0)
                            fcntl.ioctl(master_fd, termios.TIOCSWINSZ, winsize)
                            continue
                        except Exception:
                            pass
                    os.write(master_fd, text.encode("utf-8"))
        except WebSocketDisconnect:
            pass
        except Exception:
            pass

    try:
        tasks = [
            asyncio.create_task(pty_to_ws()),
            asyncio.create_task(ws_to_pty())
        ]
        done, pending = await asyncio.wait(tasks, return_when=asyncio.FIRST_COMPLETED)
        for t in pending:
            t.cancel()
    finally:
        try:
            os.close(master_fd)
        except Exception:
            pass
        try:
            proc.terminate()
            proc.wait(timeout=0.5)
        except Exception:
            try:
                proc.kill()
            except Exception:
                pass
        try:
            await websocket.close()
        except Exception:
            pass
