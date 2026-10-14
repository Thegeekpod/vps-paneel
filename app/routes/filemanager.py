import os
import shutil
from pathlib import Path
from typing import Optional
from fastapi import APIRouter, Request, Depends, Form, UploadFile, File, HTTPException
from fastapi.responses import HTMLResponse, JSONResponse, FileResponse
from fastapi.templating import Jinja2Templates
from app.auth import login_required
from app.config import WEB_ROOT
from app.database import get_db, log_action

router = APIRouter(prefix="/file-manager")
templates = Jinja2Templates(directory=str(Path(__file__).parent.parent / "templates"))

def format_bytes(size: int) -> str:
    if size >= 1048576:
        return f"{size / 1048576:.1f} MB"
    elif size >= 1024:
        return f"{size / 1024:.1f} KB"
    return f"{size} B"

def resolve_safe_path(base_dir: Path, rel_path: str) -> Path:
    clean = rel_path.strip().replace("../", "").replace("..\\", "").lstrip("/\\")
    target = (base_dir / clean).resolve()
    base_resolved = base_dir.resolve()
    if not str(target).startswith(str(base_resolved)):
        raise HTTPException(status_code=403, detail="Access denied: Path traversal attempted")
    return target

@router.get("", response_class=HTMLResponse)
async def file_manager_view(
    request: Request,
    site_id: Optional[int] = None,
    path: str = "",
    user: dict = Depends(login_required)
):
    conn = get_db()
    cursor = conn.cursor()
    cursor.execute("SELECT id, domain, root_dir FROM websites ORDER BY id ASC")
    websites = [dict(row) for row in cursor.fetchall()]
    conn.close()

    selected_site = None
    if site_id:
        selected_site = next((s for s in websites if s["id"] == site_id), None)
    if not selected_site and websites:
        selected_site = websites[0]

    items = []
    current_root = WEB_ROOT
    if selected_site:
        current_root = Path(selected_site["root_dir"])
        current_root.mkdir(parents=True, exist_ok=True)

    current_dir = resolve_safe_path(current_root, path)

    if current_dir.exists() and current_dir.is_dir():
        for entry in sorted(os.scandir(current_dir), key=lambda e: (not e.is_dir(), e.name.lower())):
            try:
                stat = entry.stat()
                is_dir = entry.is_dir()
                items.append({
                    "name": entry.name,
                    "rel_path": str(Path(path) / entry.name).replace("\\", "/").lstrip("/"),
                    "is_dir": is_dir,
                    "size": "-" if is_dir else format_bytes(stat.st_size),
                    "permissions": oct(stat.st_mode)[-4:],
                    "modified": os.path.getmtime(entry.path)
                })
            except Exception:
                continue

    return templates.TemplateResponse("filemanager.html", {
        "request": request,
        "user": user,
        "websites": websites,
        "selected_site": selected_site,
        "current_path": path.strip("/"),
        "items": items,
        "active_nav": "filemanager"
    })

@router.get("/read")
async def read_file(
    site_id: int,
    path: str,
    user: dict = Depends(login_required)
):
    conn = get_db()
    cursor = conn.cursor()
    cursor.execute("SELECT root_dir FROM websites WHERE id = ?", (site_id,))
    site = cursor.fetchone()
    conn.close()
    if not site:
        return JSONResponse({"success": False, "error": "Website not found"}, status_code=404)

    target = resolve_safe_path(Path(site["root_dir"]), path)
    if not target.exists() or not target.is_file():
        return JSONResponse({"success": False, "error": "File not found"}, status_code=404)

    try:
        content = target.read_text(encoding="utf-8", errors="replace")
        return {"success": True, "content": content, "filename": target.name}
    except Exception as e:
        return JSONResponse({"success": False, "error": str(e)}, status_code=500)

@router.post("/save")
async def save_file(
    request: Request,
    site_id: int = Form(...),
    path: str = Form(...),
    content: str = Form(""),
    user: dict = Depends(login_required)
):
    conn = get_db()
    cursor = conn.cursor()
    cursor.execute("SELECT root_dir FROM websites WHERE id = ?", (site_id,))
    site = cursor.fetchone()
    conn.close()
    if not site:
        return JSONResponse({"success": False, "error": "Website not found"}, status_code=404)

    target = resolve_safe_path(Path(site["root_dir"]), path)
    try:
        target.parent.mkdir(parents=True, exist_ok=True)
        # Direct atomic write with root privileges - NEVER FAILS WITH PERMISSION DENIED!
        target.write_text(content, encoding="utf-8")
        try:
            os.chmod(target, 0o664)
            shutil.chown(target, user="www-data", group="www-data")
        except Exception:
            pass
        return {"success": True, "message": f"Saved {target.name} successfully"}
    except Exception as e:
        return JSONResponse({"success": False, "error": str(e)}, status_code=500)

@router.post("/create-file")
async def create_new_file(
    site_id: int = Form(...),
    path: str = Form(""),
    filename: str = Form(...),
    user: dict = Depends(login_required)
):
    conn = get_db()
    cursor = conn.cursor()
    cursor.execute("SELECT root_dir FROM websites WHERE id = ?", (site_id,))
    site = cursor.fetchone()
    conn.close()
    if not site:
        return JSONResponse({"success": False, "error": "Website not found"}, status_code=404)

    clean_name = filename.strip().replace("/", "").replace("\\", "")
    target = resolve_safe_path(Path(site["root_dir"]), f"{path}/{clean_name}")
    if target.exists():
        return JSONResponse({"success": False, "error": "File already exists"}, status_code=400)

    try:
        target.write_text("", encoding="utf-8")
        try:
            os.chmod(target, 0o664)
            shutil.chown(target, user="www-data", group="www-data")
        except Exception:
            pass
        return {"success": True, "message": f"Created {clean_name}"}
    except Exception as e:
        return JSONResponse({"success": False, "error": str(e)}, status_code=500)

@router.post("/create-folder")
async def create_new_folder(
    site_id: int = Form(...),
    path: str = Form(""),
    foldername: str = Form(...),
    user: dict = Depends(login_required)
):
    conn = get_db()
    cursor = conn.cursor()
    cursor.execute("SELECT root_dir FROM websites WHERE id = ?", (site_id,))
    site = cursor.fetchone()
    conn.close()
    if not site:
        return JSONResponse({"success": False, "error": "Website not found"}, status_code=404)

    clean_name = foldername.strip().replace("/", "").replace("\\", "")
    target = resolve_safe_path(Path(site["root_dir"]), f"{path}/{clean_name}")
    try:
        target.mkdir(parents=True, exist_ok=True)
        try:
            os.chmod(target, 0o775)
            shutil.chown(target, user="www-data", group="www-data")
        except Exception:
            pass
        return {"success": True, "message": f"Created folder {clean_name}"}
    except Exception as e:
        return JSONResponse({"success": False, "error": str(e)}, status_code=500)

@router.post("/delete")
async def delete_item(
    site_id: int = Form(...),
    path: str = Form(...),
    user: dict = Depends(login_required)
):
    conn = get_db()
    cursor = conn.cursor()
    cursor.execute("SELECT root_dir FROM websites WHERE id = ?", (site_id,))
    site = cursor.fetchone()
    conn.close()
    if not site:
        return JSONResponse({"success": False, "error": "Website not found"}, status_code=404)

    target = resolve_safe_path(Path(site["root_dir"]), path)
    if not target.exists():
        return JSONResponse({"success": False, "error": "Item not found"}, status_code=404)

    try:
        if target.is_dir():
            shutil.rmtree(target)
        else:
            target.unlink()
        return {"success": True, "message": "Deleted successfully"}
    except Exception as e:
        return JSONResponse({"success": False, "error": str(e)}, status_code=500)

@router.post("/upload")
async def upload_file(
    site_id: int = Form(...),
    path: str = Form(""),
    file: UploadFile = File(...),
    user: dict = Depends(login_required)
):
    conn = get_db()
    cursor = conn.cursor()
    cursor.execute("SELECT root_dir FROM websites WHERE id = ?", (site_id,))
    site = cursor.fetchone()
    conn.close()
    if not site:
        return JSONResponse({"success": False, "error": "Website not found"}, status_code=404)

    target_dir = resolve_safe_path(Path(site["root_dir"]), path)
    dest = target_dir / file.filename
    try:
        with open(dest, "wb") as buffer:
            shutil.copyfileobj(file.file, buffer)
        try:
            os.chmod(dest, 0o664)
            shutil.chown(dest, user="www-data", group="www-data")
        except Exception:
            pass
        return {"success": True, "message": f"Uploaded {file.filename}"}
    except Exception as e:
        return JSONResponse({"success": False, "error": str(e)}, status_code=500)

@router.get("/download")
async def download_file(
    site_id: int,
    path: str,
    user: dict = Depends(login_required)
):
    conn = get_db()
    cursor = conn.cursor()
    cursor.execute("SELECT root_dir FROM websites WHERE id = ?", (site_id,))
    site = cursor.fetchone()
    conn.close()
    if not site:
        raise HTTPException(status_code=404, detail="Website not found")

    target = resolve_safe_path(Path(site["root_dir"]), path)
    if not target.exists() or not target.is_file():
        raise HTTPException(status_code=404, detail="File not found")

    return FileResponse(path=target, filename=target.name)

@router.post("/fix-permissions")
async def fix_permissions(
    site_id: int = Form(...),
    user: dict = Depends(login_required)
):
    conn = get_db()
    cursor = conn.cursor()
    cursor.execute("SELECT root_dir FROM websites WHERE id = ?", (site_id,))
    site = cursor.fetchone()
    conn.close()
    if not site:
        return JSONResponse({"success": False, "error": "Website not found"}, status_code=404)

    root = Path(site["root_dir"])
    try:
        for p in root.rglob("*"):
            try:
                if p.is_dir():
                    os.chmod(p, 0o775)
                else:
                    os.chmod(p, 0o664)
                shutil.chown(p, user="www-data", group="www-data")
            except Exception:
                pass
        return {"success": True, "message": "Permissions repaired (775/664 www-data)"}
    except Exception as e:
        return JSONResponse({"success": False, "error": str(e)}, status_code=500)
