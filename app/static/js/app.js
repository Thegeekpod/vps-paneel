function showToast(message, type = 'success') {
    const container = document.getElementById('toast-container');
    if (!container) return;
    const toast = document.createElement('div');
    toast.className = `toast ${type}`;
    toast.textContent = message;
    container.appendChild(toast);
    setTimeout(() => {
        toast.remove();
    }, 4000);
}

function openModal(id) {
    const el = document.getElementById(id);
    if (el) el.classList.add('active');
}

function closeModal(id) {
    const el = document.getElementById(id);
    if (el) el.classList.remove('active');
}

// Global Panel Auto-Update Check & Modal System
let panelUpdateData = null;

async function checkPanelUpdates(manual = false) {
    const btn = document.getElementById("btnUpdatePanel");
    const icon = document.getElementById("updateIcon");
    const text = document.getElementById("updateText");
    if (!btn || !text) return;

    if (manual) {
        text.innerText = "Checking...";
    }

    try {
        const res = await fetch("/settings/check-update");
        const data = await res.json();
        panelUpdateData = data;

        if (data.has_update) {
            btn.className = "btn btn-update-blinking";
            if (icon) icon.innerText = "🔴";
            text.innerText = "Update Available!";
            btn.setAttribute("title", `GitHub update: ${data.commit_msg}`);
            if (manual) showToast("New update found from GitHub!", "success");
        } else {
            btn.className = "btn btn-update-idle";
            if (icon) icon.innerText = "✓";
            text.innerText = "v2.0 Up to date";
            if (manual) showToast("Panel is up to date!", "success");
        }
    } catch (e) {
        console.error("Update check error:", e);
    }
}

function openUpdateModal() {
    const modal = document.getElementById("panelUpdateModal");
    if (!modal) return;
    modal.classList.add("active");

    document.getElementById("updateStatusContent").style.display = "block";
    document.getElementById("updateProgressContent").style.display = "none";

    if (!panelUpdateData) {
        checkPanelUpdates(true).then(renderUpdateModalContent);
    } else {
        renderUpdateModalContent();
    }
}

function renderUpdateModalContent() {
    if (!panelUpdateData) return;
    const curEl = document.getElementById("updateCurrentCommit");
    const remEl = document.getElementById("updateRemoteCommit");
    const msgEl = document.getElementById("updateCommitMsg");
    const notice = document.getElementById("updateNoticeArea");
    const startBtn = document.getElementById("btnStartUpdate");

    if (curEl) curEl.innerText = panelUpdateData.local_commit || "v2.0";
    if (remEl) remEl.innerText = panelUpdateData.remote_commit || (panelUpdateData.has_update ? "Newer" : "Up to date");
    if (msgEl) msgEl.innerText = panelUpdateData.commit_msg ? `"${panelUpdateData.commit_msg}"` : "";

    if (panelUpdateData.has_update) {
        if (notice) notice.innerHTML = `<span style="color: #34d399; font-weight: 600;">🚀 A new update is ready on GitHub!</span><div style="margin-top: 4px; color: var(--text-secondary);">Clicking <strong>Update Panel Now</strong> will automatically pull the changes and reload the panel in seconds.</div>`;
        if (startBtn) startBtn.style.display = "inline-flex";
    } else {
        if (notice) notice.innerHTML = `<span style="color: var(--text-muted);">✓ Your panel is currently running the latest version from GitHub.</span>`;
        if (startBtn) startBtn.style.display = "none";
    }
}

async function applyUpdateNow() {
    document.getElementById("updateStatusContent").style.display = "none";
    document.getElementById("updateProgressContent").style.display = "block";

    try {
        await fetch("/settings/apply-update", { method: "POST" });
    } catch (e) {}

    let secondsLeft = 6;
    const countdownEl = document.getElementById("updateCountdown");
    const barEl = document.getElementById("updateProgressBar");

    const timer = setInterval(() => {
        secondsLeft--;
        const pct = Math.min(100, Math.round(((6 - secondsLeft) / 6) * 100));
        if (barEl) barEl.style.width = pct + "%";

        if (secondsLeft > 0) {
            if (countdownEl) countdownEl.innerText = `Reloading in ${secondsLeft}s...`;
        } else {
            clearInterval(timer);
            if (countdownEl) countdownEl.innerText = "Reconnecting...";
            setTimeout(() => {
                location.reload();
            }, 1000);
        }
    }, 1000);
}

// Auto check for updates on load and periodically every 60 seconds
document.addEventListener("DOMContentLoaded", () => {
    checkPanelUpdates();
    setInterval(checkPanelUpdates, 60000);
});

// Domain Connection Guide & DNS Live Checker
function openDomainGuideModal(prefillDomain = '') {
    openModal('domainGuideModal');
    if (prefillDomain) {
        switchGuideTab('guideTabChecker');
        const input = document.getElementById('domainCheckerInput');
        if (input) {
            input.value = prefillDomain;
            runDomainDnsCheck();
        }
    }
}

function switchGuideTab(tabId) {
    document.querySelectorAll('#domainGuideModal .modal-tab-btn').forEach(btn => btn.classList.remove('active'));
    document.querySelectorAll('#domainGuideModal .tab-pane').forEach(pane => pane.classList.remove('active'));
    const btn = document.getElementById('btnTab_' + tabId);
    const pane = document.getElementById(tabId);
    if (btn) btn.classList.add('active');
    if (pane) pane.classList.add('active');
}

function copyServerIp(ip) {
    const textToCopy = ip || document.getElementById('sidebarServerIp')?.innerText || '';
    if (!textToCopy || textToCopy === 'Loading...' || textToCopy === 'Detecting...') {
        showToast('Server IP not loaded yet', 'danger');
        return;
    }
    navigator.clipboard.writeText(textToCopy).then(() => {
        showToast(`Server IP copied: ${textToCopy}`, 'success');
    }).catch(() => {
        const ta = document.createElement('textarea');
        ta.value = textToCopy;
        document.body.appendChild(ta);
        ta.select();
        document.execCommand('copy');
        document.body.removeChild(ta);
        showToast(`Server IP copied: ${textToCopy}`, 'success');
    });
}

async function runDomainDnsCheck() {
    const input = document.getElementById('domainCheckerInput');
    const btn = document.getElementById('btnRunDomainCheck');
    const resultArea = document.getElementById('domainCheckerResultArea');
    if (!input || !btn || !resultArea) return;

    const domain = input.value.trim();
    if (!domain) {
        showToast('Please enter a domain or URL to test', 'danger');
        input.focus();
        return;
    }

    const origBtnHtml = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = `<span style="display:inline-block; animation: spin 1s linear infinite;">⏳</span> Testing...`;

    resultArea.style.display = 'block';
    resultArea.innerHTML = `
        <div style="background: #0f172a; border: 1px solid var(--border); border-radius: 10px; padding: 24px; text-align: center;">
            <div style="font-size: 28px; margin-bottom: 10px; animation: spin 1s linear infinite; display: inline-block;">⏳</div>
            <div style="font-size: 14px; font-weight: 600; color: #fff; margin-bottom: 4px;">Querying DNS records & testing HTTP reachability...</div>
            <div style="font-size: 12px; color: var(--text-muted);">Testing ${domain}...</div>
        </div>
    `;

    try {
        const res = await fetch('/api/check-domain', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({ domain: domain })
        });
        const data = await res.json();
        
        let statusBadgeHtml = '';
        let statusBoxBorder = 'rgba(56, 189, 248, 0.25)';
        let statusBoxBg = 'rgba(56, 189, 248, 0.06)';
        let icon = 'ℹ️';

        if (data.status === 'connected') {
            statusBadgeHtml = `<span class="badge" style="background: rgba(16, 185, 129, 0.2); color: #34d399; font-size: 12px; padding: 4px 10px;">🟢 Connected to this VPS</span>`;
            statusBoxBorder = 'rgba(16, 185, 129, 0.35)';
            statusBoxBg = 'rgba(16, 185, 129, 0.08)';
            icon = '✅';
        } else if (data.status === 'cloudflare') {
            statusBadgeHtml = `<span class="badge" style="background: rgba(245, 158, 11, 0.2); color: #fbbf24; font-size: 12px; padding: 4px 10px;">🟠 Cloudflare Proxy Active</span>`;
            statusBoxBorder = 'rgba(245, 158, 11, 0.35)';
            statusBoxBg = 'rgba(245, 158, 11, 0.08)';
            icon = '☁️';
        } else if (data.status === 'not_pointing') {
            statusBadgeHtml = `<span class="badge" style="background: rgba(239, 68, 68, 0.2); color: #f87171; font-size: 12px; padding: 4px 10px;">🔴 No DNS Record Found</span>`;
            statusBoxBorder = 'rgba(239, 68, 68, 0.35)';
            statusBoxBg = 'rgba(239, 68, 68, 0.08)';
            icon = '❌';
        } else {
            statusBadgeHtml = `<span class="badge" style="background: rgba(239, 68, 68, 0.2); color: #f87171; font-size: 12px; padding: 4px 10px;">⚠️ IP Mismatch</span>`;
            statusBoxBorder = 'rgba(239, 68, 68, 0.35)';
            statusBoxBg = 'rgba(239, 68, 68, 0.08)';
            icon = '⚠️';
        }

        const resolvedIpText = (data.resolved_ips && data.resolved_ips.length > 0) ? data.resolved_ips.join(', ') : 'None (Unresolved)';
        const wwwIpText = (data.alt_ips && data.alt_ips.length > 0) ? data.alt_ips.join(', ') : 'None (Unresolved)';
        const httpStatusText = data.http_reachable ? `<span style="color: #34d399; font-weight: 600;">✓ Responding (HTTP ${data.http_code})</span>` : `<span style="color: #94a3b8;">Not responding yet</span>`;

        resultArea.innerHTML = `
            <div style="background: ${statusBoxBg}; border: 1px solid ${statusBoxBorder}; border-radius: 10px; padding: 18px; margin-bottom: 14px;">
                <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 10px; flex-wrap: wrap; gap: 8px;">
                    <div style="font-size: 15px; font-weight: 700; color: #fff; display: flex; align-items: center; gap: 8px;">
                        <span>${icon}</span> ${data.title || 'Diagnostic Result'}
                    </div>
                    ${statusBadgeHtml}
                </div>
                <p style="font-size: 13px; color: var(--text-secondary); line-height: 1.5; margin: 0 0 14px 0;">
                    ${data.message || ''}
                </p>
                <div style="background: #0f172a; border: 1px solid var(--border); border-radius: 8px; padding: 12px; font-size: 12px;">
                    <div style="display: grid; grid-template-columns: 140px 1fr; gap: 6px; padding: 5px 0; border-bottom: 1px solid rgba(255,255,255,0.05);">
                        <span style="color: var(--text-muted);">Tested Domain:</span>
                        <code style="color: #38bdf8;">${data.domain}</code>
                    </div>
                    <div style="display: grid; grid-template-columns: 140px 1fr; gap: 6px; padding: 5px 0; border-bottom: 1px solid rgba(255,255,255,0.05);">
                        <span style="color: var(--text-muted);">Your VPS Server IP:</span>
                        <code style="color: #fff; font-weight: 600;">${data.server_ip}</code>
                    </div>
                    <div style="display: grid; grid-template-columns: 140px 1fr; gap: 6px; padding: 5px 0; border-bottom: 1px solid rgba(255,255,255,0.05);">
                        <span style="color: var(--text-muted);">Resolved DNS IP:</span>
                        <code style="${data.matches_server ? 'color: #34d399;' : 'color: #f87171;'} font-weight: 600;">${resolvedIpText}</code>
                    </div>
                    <div style="display: grid; grid-template-columns: 140px 1fr; gap: 6px; padding: 5px 0; border-bottom: 1px solid rgba(255,255,255,0.05);">
                        <span style="color: var(--text-muted);">${data.alt_domain}:</span>
                        <code style="color: #cbd5e1;">${wwwIpText}</code>
                    </div>
                    <div style="display: grid; grid-template-columns: 140px 1fr; gap: 6px; padding: 5px 0;">
                        <span style="color: var(--text-muted);">Port 80 Reachability:</span>
                        <div>${httpStatusText}</div>
                    </div>
                </div>
            </div>
        `;
    } catch (e) {
        resultArea.innerHTML = `
            <div style="background: rgba(239, 68, 68, 0.1); border: 1px solid rgba(239, 68, 68, 0.3); border-radius: 8px; padding: 14px; font-size: 13px; color: #f87171;">
                Failed to perform check: ${e}
            </div>
        `;
    } finally {
        btn.disabled = false;
        btn.innerHTML = origBtnHtml;
    }
}

