from pathlib import Path
from fastapi import Request
from fastapi.templating import Jinja2Templates

TEMPLATES_DIR = Path(__file__).parent / "templates"
templates = Jinja2Templates(directory=str(TEMPLATES_DIR))

from app.services.dns_service import get_server_public_ip

def render(template_name: str, context: dict, request: Request, status_code: int = 200):
    context["request"] = request
    if "server_ip" not in context:
        try:
            context["server_ip"] = get_server_public_ip()
        except Exception:
            context["server_ip"] = "127.0.0.1"
    try:
        # Modern Starlette 0.38+ with keyword args
        return templates.TemplateResponse(
            request=request,
            name=template_name,
            context=context,
            status_code=status_code
        )
    except TypeError:
        try:
            # Modern Starlette with positional args (request, name, context)
            return templates.TemplateResponse(
                request,
                template_name,
                context,
                status_code=status_code
            )
        except TypeError:
            # Older Starlette (name, context)
            return templates.TemplateResponse(
                template_name,
                context,
                status_code=status_code
            )
