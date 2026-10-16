import os
from pathlib import Path
import uvicorn
from app.config import PANEL_HOST, PANEL_PORT, DATA_DIR

if __name__ == "__main__":
    ssl_cert = DATA_DIR / "ssl" / "panel.crt"
    ssl_key = DATA_DIR / "ssl" / "panel.key"

    ssl_kwargs = {}
    protocol = "http"
    if ssl_cert.exists() and ssl_key.exists():
        ssl_kwargs["ssl_certfile"] = str(ssl_cert)
        ssl_kwargs["ssl_keyfile"] = str(ssl_key)
        protocol = "https"

    print(f"Starting VPS Control Panel on {protocol}://{PANEL_HOST}:{PANEL_PORT} ...")
    uvicorn.run("app.main:app", host=PANEL_HOST, port=PANEL_PORT, reload=False, **ssl_kwargs)

