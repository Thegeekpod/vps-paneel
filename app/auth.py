import jwt
from datetime import datetime, timedelta
from typing import Optional
from fastapi import Request, HTTPException, status, Depends
from fastapi.responses import RedirectResponse
from app.config import SECRET_KEY
from app.database import get_db, hash_password

ALGORITHM = "HS256"
COOKIE_NAME = "vps_session"

def create_access_token(username: str, role: str = "admin") -> str:
    expires = datetime.utcnow() + timedelta(days=7)
    payload = {
        "sub": username,
        "role": role,
        "exp": expires
    }
    return jwt.encode(payload, SECRET_KEY, algorithm=ALGORITHM)

def get_current_user_from_token(token: str) -> Optional[dict]:
    try:
        payload = jwt.decode(token, SECRET_KEY, algorithms=[ALGORITHM])
        username: str = payload.get("sub")
        if not username:
            return None
        conn = get_db()
        cursor = conn.cursor()
        cursor.execute("SELECT id, username, role FROM users WHERE username = ?", (username,))
        user = cursor.fetchone()
        conn.close()
        if user:
            return dict(user)
        return None
    except Exception:
        return None

def get_current_user(request: Request) -> Optional[dict]:
    token = request.cookies.get(COOKIE_NAME)
    if not token:
        # Also check Authorization header
        auth_header = request.headers.get("Authorization")
        if auth_header and auth_header.startswith("Bearer "):
            token = auth_header.split(" ")[1]
    
    if not token:
        return None
    return get_current_user_from_token(token)

def login_required(request: Request):
    user = get_current_user(request)
    if not user:
        raise HTTPException(
            status_code=status.HTTP_307_TEMPORARY_REDIRECT,
            headers={"Location": "/login"}
        )
    return user
