#!/usr/bin/env bash
# ==============================================================================
# VPS Control Panel (Python Edition) Auto-Updater
# ==============================================================================
set -e

if [[ $EUID -ne 0 ]]; then
   echo "[ERROR] Please run with sudo: sudo bash update.sh"
   exit 1
fi

INSTALL_DIR="/opt/vps-panel"
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

echo "[-] 1/4 Pulling latest updates from Git..."
cd "$SCRIPT_DIR"
git pull origin main

echo "[-] 2/4 Syncing files to ${INSTALL_DIR}..."
mkdir -p "$INSTALL_DIR"
cp -rf "$SCRIPT_DIR/app" "$INSTALL_DIR/"
cp -f "$SCRIPT_DIR/requirements.txt" "$INSTALL_DIR/"
cp -f "$SCRIPT_DIR/run.py" "$INSTALL_DIR/"

echo "[-] 3/4 Updating Python packages..."
if [[ -f "$INSTALL_DIR/venv/bin/pip" ]]; then
    "$INSTALL_DIR/venv/bin/pip" install -r "$INSTALL_DIR/requirements.txt"
fi

echo "[-] 4/4 Restarting VPS Control Panel service..."
systemctl daemon-reload
systemctl restart vps-panel || systemctl start vps-panel
nginx -t && (systemctl reload nginx || systemctl restart nginx)

echo ""
echo "=============================================================================="
echo " [OK] VPS Control Panel updated and running successfully!"
echo "=============================================================================="
