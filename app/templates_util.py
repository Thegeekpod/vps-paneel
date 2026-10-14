from pathlib import Path
from fastapi import Request
from fastapi.templating import Jinja2Templates

TEMPLATES_DIR = Path(__file__).parent / "templates"
templates = Jinja2Templates(directory=str(TEMPLATES_DIR))

def render(template_name: str, context: dict, request: Request, status_code: int = 200):
    context["request"] = request
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
