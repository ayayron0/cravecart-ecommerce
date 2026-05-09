(() => {
    const BASE_PATH = document.documentElement.dataset.basePath || '';
    const badge     = document.getElementById('cc-notif-badge');
    const menu      = document.getElementById('cc-notif-menu');
    const btn       = document.getElementById('cc-notif-btn');
    const empty     = document.getElementById('cc-notif-empty');

    if (!badge) return; // not logged in or is admin — nothing to do

    let shownIds = new Set();

    function timeAgo(dateStr) {
        const diff = Math.floor((Date.now() - new Date(dateStr).getTime()) / 1000);
        if (diff < 60)  return 'Just now';
        if (diff < 3600) return Math.floor(diff / 60) + 'm ago';
        if (diff < 86400) return Math.floor(diff / 3600) + 'h ago';
        return Math.floor(diff / 86400) + 'd ago';
    }

    function showToast(message) {
        const container = document.getElementById('cc-toast-container') || (() => {
            const el = document.createElement('div');
            el.id = 'cc-toast-container';
            el.style.cssText = 'position:fixed;bottom:24px;right:24px;z-index:9999;display:flex;flex-direction:column;gap:8px;';
            document.body.appendChild(el);
            return el;
        })();

        const toast = document.createElement('div');
        toast.className = 'toast show align-items-center text-white border-0';
        toast.style.cssText = 'background:#e63946;min-width:280px;';
        toast.innerHTML = `
            <div class="d-flex">
                <div class="toast-body"><i class="bi bi-bell-fill me-2"></i>${message}</div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" onclick="this.closest('.toast').remove()"></button>
            </div>`;
        container.appendChild(toast);
        setTimeout(() => toast.remove(), 5000);
    }

    function renderMenu(notifications) {
        // Remove old notification items (keep header + empty)
        menu.querySelectorAll('.cc-notif-item').forEach(el => el.remove());

        if (notifications.length === 0) {
            empty.classList.remove('d-none');
            return;
        }

        empty.classList.add('d-none');

        notifications.forEach(n => {
            const li = document.createElement('li');
            li.className = 'cc-notif-item px-3 py-2 border-bottom';
            li.innerHTML = `
                <p class="mb-0 small">${n.message}</p>
                <span class="text-muted" style="font-size:.72rem;">${timeAgo(n.created_at)}</span>`;
            menu.appendChild(li);
        });
    }

    function poll() {
        fetch(`${BASE_PATH}/api/notifications`)
            .then(r => r.json())
            .then(data => {
                const count = data.count || 0;
                const notifications = data.notifications || [];

                // Update badge
                if (count > 0) {
                    badge.textContent = count > 9 ? '9+' : count;
                    badge.classList.remove('d-none');
                } else {
                    badge.classList.add('d-none');
                }

                // Toast for any new ones we haven't shown yet
                notifications.forEach(n => {
                    if (!shownIds.has(n.id)) {
                        shownIds.add(n.id);
                        showToast(n.message);
                    }
                });

                renderMenu(notifications);
            })
            .catch(() => {}); // silent fail — network hiccup shouldn't break the page
    }

    // Mark all read only after the dropdown closes — so the user can read them first
    const dropdownEl = document.getElementById('cc-notif-dropdown');
    dropdownEl.addEventListener('hidden.bs.dropdown', () => {
        if (!badge.classList.contains('d-none')) {
            fetch(`${BASE_PATH}/api/notifications/read`, { method: 'POST' })
                .then(() => {
                    badge.classList.add('d-none');
                    shownIds.clear();
                    renderMenu([]);
                })
                .catch(() => {});
        }
    });

    poll();                         // run immediately on page load
    setInterval(poll, 30_000);      // then every 30 seconds
})();
