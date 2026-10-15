from __future__ import annotations
import os
import shutil
import subprocess
from pathlib import Path
from typing import Tuple, Union

def sanitize_repo_url(url: str) -> str:
    url = url.strip()
    if not url:
        return ""
    # Auto-prepend https:// if user pasted domain without protocol e.g. github.com/user/repo
    if url.startswith("github.com/") or url.startswith("gitlab.com/"):
        url = "https://" + url
    return url

def clone_repository(repo_url: str, target_dir: Union[Path, str], branch: str = "", clean_target: bool = True) -> Tuple[bool, str]:
    target_path = Path(target_dir)
    clean_url = sanitize_repo_url(repo_url)
    
    if not clean_url:
        return False, "Invalid repository URL provided"

    # Ensure target directory exists
    target_path.mkdir(parents=True, exist_ok=True)

    # Check if target directory is empty (git clone fails if target is non-empty)
    if any(target_path.iterdir()):
        if clean_target:
            # Clean leftover files or default placeholders so clone succeeds
            for item in target_path.iterdir():
                try:
                    if item.is_dir():
                        shutil.rmtree(item, ignore_errors=True)
                    else:
                        item.unlink(missing_ok=True)
                except Exception:
                    pass
        else:
            # Remove empty or default placeholder index.html if present
            placeholder = target_path / "index.html"
            if placeholder.exists() and len(list(target_path.iterdir())) == 1:
                placeholder.unlink()
            else:
                return False, f"Target directory {target_path} is not empty"

    cmd = ["git", "clone", "--depth", "1"]
    if branch and branch.strip():
        cmd.extend(["-b", branch.strip()])
    cmd.extend([clean_url, str(target_path)])

    env = {**os.environ, "GIT_TERMINAL_PROMPT": "0"}

    try:
        res = subprocess.run(
            cmd,
            stdout=subprocess.PIPE,
            stderr=subprocess.PIPE,
            text=True,
            timeout=120,
            env=env
        )
        if res.returncode != 0:
            err = res.stderr.strip() or res.stdout.strip() or "Git clone failed with unknown error"
            # Cleanup any partial files if clone failed
            for item in target_path.iterdir():
                if item.is_dir():
                    shutil.rmtree(item, ignore_errors=True)
                else:
                    item.unlink(missing_ok=True)
            return False, err
        return True, "Repository cloned successfully"
    except subprocess.TimeoutExpired:
        return False, "Git clone timed out after 120 seconds"
    except FileNotFoundError:
        return False, "Git is not installed on this system"
    except Exception as e:
        return False, str(e)

def pull_repository(target_dir: Union[Path, str]) -> Tuple[bool, str]:
    target_path = Path(target_dir)
    if not (target_path / ".git").exists():
        return False, "Document root is not a Git repository"

    env = {**os.environ, "GIT_TERMINAL_PROMPT": "0"}

    try:
        res = subprocess.run(
            ["git", "pull"],
            cwd=str(target_path),
            stdout=subprocess.PIPE,
            stderr=subprocess.PIPE,
            text=True,
            timeout=60,
            env=env
        )
        if res.returncode == 0:
            msg = res.stdout.strip() or "Repository is already up to date"
            return True, msg
        else:
            err = res.stderr.strip() or res.stdout.strip() or "Git pull failed"
            return False, err
    except subprocess.TimeoutExpired:
        return False, "Git pull timed out after 60 seconds"
    except FileNotFoundError:
        return False, "Git is not installed on this system"
    except Exception as e:
        return False, str(e)
