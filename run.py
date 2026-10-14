import uvicorn
from app.config import PANEL_HOST, PANEL_PORT

if __name__ == "__main__":
    print(f"Starting VPS Control Panel on http://{PANEL_HOST}:{PANEL_PORT} ...")
    uvicorn.run("app.main:app", host=PANEL_HOST, port=PANEL_PORT, reload=False)
