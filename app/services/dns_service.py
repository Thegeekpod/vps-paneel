import socket
import re
import time
import urllib.request
import urllib.error
from typing import Dict, Any, List

_cached_server_ip: str = None
_cached_ip_time: float = 0.0

def get_server_public_ip() -> str:
    """Detects server's public IPv4 address with in-memory caching for 1 hour."""
    global _cached_server_ip, _cached_ip_time
    now = time.time()
    if _cached_server_ip and (now - _cached_ip_time < 3600):
        return _cached_server_ip

    endpoints = [
        "https://api.ipify.org",
        "https://ifconfig.me/ip",
        "https://icanhazip.com"
    ]
    for url in endpoints:
        try:
            req = urllib.request.Request(url, headers={'User-Agent': 'curl/7.68.0'})
            with urllib.request.urlopen(req, timeout=3) as resp:
                ip = resp.read().decode('utf-8').strip()
                if ip and len(ip.split('.')) == 4 and all(p.isdigit() for p in ip.split('.')):
                    _cached_server_ip = ip
                    _cached_ip_time = now
                    return ip
        except Exception:
            continue

    # Fallback to local default route interface IP
    try:
        s = socket.socket(socket.AF_INET, socket.SOCK_DGRAM)
        s.connect(("8.8.8.8", 80))
        ip = s.getsockname()[0]
        s.close()
        _cached_server_ip = ip
        _cached_ip_time = now
        return ip
    except Exception:
        return "127.0.0.1"

def check_domain_dns(raw_domain: str) -> Dict[str, Any]:
    """
    Checks DNS resolution and HTTP connectivity for a domain against this server.
    Returns status: 'connected', 'cloudflare', 'mismatched', or 'not_pointing'.
    """
    domain = (raw_domain or "").strip().lower()
    # Strip protocol and paths if user entered full URL like https://example.com/test
    domain = re.sub(r'^https?://', '', domain)
    domain = domain.split('/')[0].split(':')[0].strip()

    if not domain:
        return {"success": False, "error": "Please enter a valid domain name"}

    server_ip = get_server_public_ip()
    resolved_ips: List[str] = []
    
    # 1. Resolve domain A records
    try:
        addr_info = socket.getaddrinfo(domain, 80, socket.AF_INET, socket.SOCK_STREAM)
        resolved_ips = sorted(list(set([item[4][0] for item in addr_info])))
    except (socket.gaierror, Exception):
        resolved_ips = []

    # 2. Check www.domain or apex domain
    is_www = domain.startswith("www.")
    alt_domain = domain[4:] if is_www else f"www.{domain}"
    alt_ips: List[str] = []
    try:
        alt_addr_info = socket.getaddrinfo(alt_domain, 80, socket.AF_INET, socket.SOCK_STREAM)
        alt_ips = sorted(list(set([item[4][0] for item in alt_addr_info])))
    except Exception:
        alt_ips = []

    # 3. Detect Cloudflare proxy IP blocks
    cloudflare_prefixes = (
        "104.16.", "104.17.", "104.18.", "104.19.", "104.20.", "104.21.", "104.22.",
        "104.23.", "104.24.", "104.25.", "104.26.", "104.27.", "104.28.", "104.29.",
        "104.30.", "104.31.", "172.64.", "172.65.", "172.66.", "172.67.", "162.158.", "108.162."
    )
    is_cloudflare = any(ip.startswith(cloudflare_prefixes) for ip in resolved_ips)

    matches_server = server_ip in resolved_ips

    # 4. HTTP Reachability check
    http_reachable = False
    http_code = None
    http_error = None
    try:
        req = urllib.request.Request(
            f"http://{domain}",
            headers={'User-Agent': 'Mozilla/5.0 (VPS Domain Checker)'}
        )
        with urllib.request.urlopen(req, timeout=5) as resp:
            http_code = resp.getcode()
            http_reachable = True
    except urllib.error.HTTPError as e:
        http_code = e.code
        http_reachable = True  # Server responded with an HTTP code (e.g. 200, 301, 403, 503)
    except Exception as e:
        http_error = str(e)

    # 5. Diagnostic classification
    if not resolved_ips:
        status = "not_pointing"
        badge_color = "danger"
        title = "No DNS A-Record Found"
        message = f"Domain {domain} does not resolve to any IP address yet. Please add an A-Record at your domain registrar pointing to {server_ip}."
    elif matches_server:
        status = "connected"
        badge_color = "success"
        title = "Connected Successfully!"
        message = f"Great news! {domain} is pointing directly to your VPS IP ({server_ip})."
    elif is_cloudflare:
        status = "cloudflare"
        badge_color = "warning"
        title = "Cloudflare Proxy Detected"
        message = f"{domain} resolves to Cloudflare proxy ({', '.join(resolved_ips)}). If you configured your Cloudflare DNS A-Record to point to {server_ip}, traffic will be routed to this server."
    else:
        status = "mismatched"
        badge_color = "danger"
        title = "IP Address Mismatch"
        message = f"{domain} points to {', '.join(resolved_ips)}, which does NOT match your VPS IP ({server_ip}). Please update your domain DNS A-Record."

    return {
        "success": True,
        "domain": domain,
        "alt_domain": alt_domain,
        "server_ip": server_ip,
        "resolved_ips": resolved_ips,
        "alt_ips": alt_ips,
        "matches_server": matches_server,
        "is_cloudflare": is_cloudflare,
        "http_reachable": http_reachable,
        "http_code": http_code,
        "http_error": http_error,
        "status": status,
        "badge_color": badge_color,
        "title": title,
        "message": message
    }
