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
