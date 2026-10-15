import os
import shutil
import zipfile
import json
from datetime import datetime
from pathlib import Path
from typing import Optional, List
from fastapi import APIRouter, Request, Depends, Form, UploadFile, File, HTTPException
from fastapi.responses import HTMLResponse, JSONResponse, FileResponse
from app.auth import login_required
from app.config import WEB_ROOT
from app.database import get_db, log_action
from app.templates_util import render

router = APIRouter(prefix="/file-manager")

IMAGE_EXTS = {".jpg", ".jpeg", ".png", ".gif", ".webp", ".svg", ".ico", ".bmp"}
BINARY_EXTS = {
    ".zip", ".tar", ".gz", ".tgz", ".bz2", ".7z", ".rar", ".iso",
    ".png", ".jpg", ".jpeg", ".gif", ".webp", ".bmp", ".ico", ".tiff",
    ".mp3", ".wav", ".ogg", ".flac", ".mp4", ".mkv", ".avi", ".mov", ".webm",
    ".pdf", ".exe", ".bin", ".dll", ".so", ".dylib", ".o", ".a",
    ".woff", ".woff2", ".ttf", ".eot", ".otf"
}
ARCHIVE_EXTS = {".zip"}

def format_bytes(size: int) -> str:
    if size >= 1073741824:
        return f"{size / 1073741824:.2f} GB"
    elif size >= 1048576:
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

def get_file_metadata(entry: os.DirEntry, base_rel: str) -> dict:
    try:
        stat = entry.stat()
        is_dir = entry.is_dir()
        name = entry.name
        ext = Path(name).suffix.lower() if not is_dir else ""
        rel_path = str(Path(base_rel) / name).replace("\\", "/").lstrip("/")
        
        # Human date
        dt = datetime.fromtimestamp(stat.st_mtime)
        mtime_str = dt.strftime("%Y-%m-%d %H:%M")
        
        # Permissions octal
        perms = oct(stat.st_mode)[-4:]

        # Any regular file that is not a binary image, archive, or compiled binary is fully editable
        is_editable = (not is_dir) and (ext not in BINARY_EXTS)

        return {
            "name": name,
            "rel_path": rel_path,
            "is_dir": is_dir,
            "size": "-" if is_dir else format_bytes(stat.st_size),
            "size_bytes": 0 if is_dir else stat.st_size,
            "permissions": perms,
            "modified": mtime_str,
            "ext": ext.lstrip("."),
            "is_editable": is_editable,
            "is_archive": ext in ARCHIVE_EXTS,
            "is_image": ext in IMAGE_EXTS
        }
    except Exception:
        return {
            "name": entry.name,
            "rel_path": str(Path(base_rel) / entry.name).replace("\\", "/").lstrip("/"),
            "is_dir": entry.is_dir(),
            "size": "-",
            "size_bytes": 0,
            "permissions": "0644",
            "modified": "-",
            "ext": "",
            "is_editable": False,
            "is_archive": False,
            "is_image": False
        }

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
            items.append(get_file_metadata(entry, path))

    # Breadcrumbs generator
    breadcrumbs = []
    parts = [p for p in path.strip("/").split("/") if p]
    accumulated = ""
    for p in parts:
        accumulated = f"{accumulated}/{p}".lstrip("/")
        breadcrumbs.append({"name": p, "path": accumulated})

    return render("filemanager.html", {
        "user": user,
        "websites": websites,
        "selected_site": selected_site,
        "current_path": path.strip("/"),
        "breadcrumbs": breadcrumbs,
        "items": items,
        "active_nav": "filemanager"
    }, request)

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
        ext = target.suffix.lower().lstrip(".")
        return {
            "success": True,
            "content": content,
            "filename": target.name,
            "extension": ext
        }
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
        target.write_text(content, encoding="utf-8")
        try:
            os.chmod(target, 0o664)
            shutil.chown(target, user="www-data", group="www-data")
        except Exception:
            pass
        log_action(user["username"], "SaveFile", f"File: {path}", request.client.host)
        return {"success": True, "message": f"Saved {target.name} successfully"}
    except Exception as e:
        return JSONResponse({"success": False, "error": str(e)}, status_code=500)

@router.post("/create-file")
async def create_new_file(
    request: Request,
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
    if not clean_name:
        return JSONResponse({"success": False, "error": "Invalid filename"}, status_code=400)

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
        log_action(user["username"], "CreateFile", f"File: {clean_name} in {path}", request.client.host)
        return {"success": True, "message": f"Created {clean_name}"}
    except Exception as e:
        return JSONResponse({"success": False, "error": str(e)}, status_code=500)

@router.post("/create-folder")
async def create_new_folder(
    request: Request,
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
    if not clean_name:
        return JSONResponse({"success": False, "error": "Invalid folder name"}, status_code=400)

    target = resolve_safe_path(Path(site["root_dir"]), f"{path}/{clean_name}")
    try:
        target.mkdir(parents=True, exist_ok=True)
        try:
            os.chmod(target, 0o775)
            shutil.chown(target, user="www-data", group="www-data")
        except Exception:
            pass
        log_action(user["username"], "CreateFolder", f"Folder: {clean_name} in {path}", request.client.host)
        return {"success": True, "message": f"Created folder {clean_name}"}
    except Exception as e:
        return JSONResponse({"success": False, "error": str(e)}, status_code=500)

@router.post("/rename")
async def rename_item(
    request: Request,
    site_id: int = Form(...),
    old_path: str = Form(...),
    new_name: str = Form(...),
    user: dict = Depends(login_required)
):
    conn = get_db()
    cursor = conn.cursor()
    cursor.execute("SELECT root_dir FROM websites WHERE id = ?", (site_id,))
    site = cursor.fetchone()
    conn.close()
    if not site:
        return JSONResponse({"success": False, "error": "Website not found"}, status_code=404)

    clean_name = new_name.strip().replace("/", "").replace("\\", "")
    if not clean_name:
        return JSONResponse({"success": False, "error": "New name cannot be empty"}, status_code=400)

    target = resolve_safe_path(Path(site["root_dir"]), old_path)
    if not target.exists():
        return JSONResponse({"success": False, "error": "Source item not found"}, status_code=404)

    new_target = target.parent / clean_name
    if new_target.exists():
        return JSONResponse({"success": False, "error": f"{clean_name} already exists"}, status_code=400)

    try:
        target.rename(new_target)
        log_action(user["username"], "RenameItem", f"Renamed {old_path} to {clean_name}", request.client.host)
        return {"success": True, "message": f"Renamed to {clean_name}"}
    except Exception as e:
        return JSONResponse({"success": False, "error": str(e)}, status_code=500)

@router.post("/delete")
async def delete_item(
    request: Request,
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
        log_action(user["username"], "DeleteItem", f"Deleted {path}", request.client.host)
        return {"success": True, "message": "Deleted successfully"}
    except Exception as e:
        return JSONResponse({"success": False, "error": str(e)}, status_code=500)

@router.post("/batch-delete")
async def batch_delete_items(
    request: Request,
    site_id: int = Form(...),
    paths: str = Form(...), # JSON array of paths
    user: dict = Depends(login_required)
):
    conn = get_db()
    cursor = conn.cursor()
    cursor.execute("SELECT root_dir FROM websites WHERE id = ?", (site_id,))
    site = cursor.fetchone()
    conn.close()
    if not site:
        return JSONResponse({"success": False, "error": "Website not found"}, status_code=404)

    try:
        path_list = json.loads(paths)
    except Exception:
        path_list = [p.strip() for p in paths.split(",") if p.strip()]

    deleted_count = 0
    errors = []
    base_dir = Path(site["root_dir"])

    for p in path_list:
        try:
            target = resolve_safe_path(base_dir, p)
            if target.exists():
                if target.is_dir():
                    shutil.rmtree(target)
                else:
                    target.unlink()
                deleted_count += 1
        except Exception as e:
            errors.append(f"{p}: {str(e)}")

    log_action(user["username"], "BatchDelete", f"Deleted {deleted_count} items", request.client.host)
    return {"success": True, "deleted_count": deleted_count, "errors": errors}

@router.post("/upload")
async def upload_files(
    request: Request,
    site_id: int = Form(...),
    path: str = Form(""),
    files: List[UploadFile] = File(...),
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
    uploaded = []

    for file in files:
        if not file.filename:
            continue
        dest = target_dir / file.filename
        try:
            with open(dest, "wb") as buffer:
                shutil.copyfileobj(file.file, buffer)
            try:
                os.chmod(dest, 0o664)
                shutil.chown(dest, user="www-data", group="www-data")
            except Exception:
                pass
            uploaded.append(file.filename)
        except Exception as e:
            return JSONResponse({"success": False, "error": f"Failed on {file.filename}: {e}"}, status_code=500)

    log_action(user["username"], "UploadFiles", f"Uploaded: {', '.join(uploaded)}", request.client.host)
    return {"success": True, "message": f"Successfully uploaded {len(uploaded)} file(s)", "files": uploaded}

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

@router.post("/chmod")
async def change_permissions(
    request: Request,
    site_id: int = Form(...),
    path: str = Form(...),
    perms: str = Form(...), # e.g. "0755" or "0644"
    recursive: bool = Form(False),
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
        mode = int(perms.strip(), 8)
        os.chmod(target, mode)
        if recursive and target.is_dir():
            for p in target.rglob("*"):
                try:
                    os.chmod(p, mode)
                except Exception:
                    pass
        log_action(user["username"], "Chmod", f"Chmod {path} to {perms} (Recursive: {recursive})", request.client.host)
        return {"success": True, "message": f"Permissions set to {perms}"}
    except Exception as e:
        return JSONResponse({"success": False, "error": str(e)}, status_code=500)

@router.post("/unzip")
async def extract_archive(
    request: Request,
    site_id: int = Form(...),
    zip_path: str = Form(...),
    dest_path: str = Form(""),
    user: dict = Depends(login_required)
):
    conn = get_db()
    cursor = conn.cursor()
    cursor.execute("SELECT root_dir FROM websites WHERE id = ?", (site_id,))
    site = cursor.fetchone()
    conn.close()
    if not site:
        return JSONResponse({"success": False, "error": "Website not found"}, status_code=404)

    base_dir = Path(site["root_dir"])
    target_zip = resolve_safe_path(base_dir, zip_path)
    if not target_zip.exists() or not target_zip.is_file():
        return JSONResponse({"success": False, "error": "Zip file not found"}, status_code=404)

    target_dest = resolve_safe_path(base_dir, dest_path if dest_path else str(Path(zip_path).parent))
    target_dest.mkdir(parents=True, exist_ok=True)

    try:
        with zipfile.ZipFile(target_zip, 'r') as zf:
            for member in zf.infolist():
                # Prevent Zip Slip directory traversal
                extracted_path = (target_dest / member.filename).resolve()
                if not str(extracted_path).startswith(str(target_dest.resolve())):
                    continue
                zf.extract(member, target_dest)
                try:
                    os.chmod(extracted_path, 0o775 if member.is_dir() else 0o664)
                    shutil.chown(extracted_path, user="www-data", group="www-data")
                except Exception:
                    pass

        log_action(user["username"], "UnzipArchive", f"Extracted {zip_path} to {target_dest.name}", request.client.host)
        return {"success": True, "message": f"Successfully extracted {target_zip.name}"}
    except Exception as e:
        return JSONResponse({"success": False, "error": f"Extraction failed: {str(e)}"}, status_code=500)

@router.post("/zip")
async def compress_archive(
    request: Request,
    site_id: int = Form(...),
    paths: str = Form(...), # JSON list of relative paths
    zip_name: str = Form(...),
    current_dir: str = Form(""),
    user: dict = Depends(login_required)
):
    conn = get_db()
    cursor = conn.cursor()
    cursor.execute("SELECT root_dir FROM websites WHERE id = ?", (site_id,))
    site = cursor.fetchone()
    conn.close()
    if not site:
        return JSONResponse({"success": False, "error": "Website not found"}, status_code=404)

    clean_zip = zip_name.strip()
    if not clean_zip.endswith(".zip"):
        clean_zip += ".zip"

    base_dir = Path(site["root_dir"])
    dest_dir = resolve_safe_path(base_dir, current_dir)
    out_zip = dest_dir / clean_zip

    try:
        path_list = json.loads(paths)
    except Exception:
        path_list = [p.strip() for p in paths.split(",") if p.strip()]

    try:
        with zipfile.ZipFile(out_zip, 'w', zipfile.ZIP_DEFLATED) as zf:
            for p in path_list:
                item_path = resolve_safe_path(base_dir, p)
                if not item_path.exists():
                    continue
                if item_path.is_dir():
                    for root, _, files in os.walk(item_path):
                        for file in files:
                            full = Path(root) / file
                            arcname = full.relative_to(item_path.parent)
                            zf.write(full, arcname)
                else:
                    zf.write(item_path, item_path.name)

        try:
            os.chmod(out_zip, 0o664)
            shutil.chown(out_zip, user="www-data", group="www-data")
        except Exception:
            pass

        log_action(user["username"], "ZipArchive", f"Created {clean_zip} containing {len(path_list)} items", request.client.host)
        return {"success": True, "message": f"Created archive {clean_zip}"}
    except Exception as e:
        return JSONResponse({"success": False, "error": f"Compression failed: {str(e)}"}, status_code=500)

@router.post("/fix-permissions")
async def fix_permissions(
    request: Request,
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
        log_action(user["username"], "FixPermissions", f"Repaired permissions on {root}", request.client.host)
        return {"success": True, "message": "Permissions repaired (775 dirs / 664 files for www-data)"}
    except Exception as e:
        return JSONResponse({"success": False, "error": str(e)}, status_code=500)
