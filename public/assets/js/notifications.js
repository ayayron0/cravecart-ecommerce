(() => {
    const BASE_PATH = document.documentElement.dataset.basePath || '';
    const badge     = document.getElementById('cc-notif-badge');
    const menu      = document.getElementById('cc-notif-menu');
    const btn       = document.getElementById('cc-notif-btn');
    const empty     = document.getElementById('cc-notif-empty');

    if (!badge) return; // not logged in or is admin — nothing to do

    function timeAgo(dateStr) {
        const diff = Math.floor((Date.now() - new Date(dateStr).getTime()) / 1000);
        if (diff < 60)  return 'Just now';
        if (diff < 3600) return Math.floor(diff / 60) + 'm ago';
        if (diff < 86400) return Math.floor(diff / 3600) + 'h ago';
        return Math.floor(diff / 86400) + 'd ago';
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

                renderMenu(notifications);
            })
            .catch(() => {}); // silent fail — network hiccup shouldn't break the page
    }

    // Clear All button — only clears when the user explicitly clicks it.
    const clearBtn = document.getElementById('cc-notif-clear');
    if (clearBtn) {
        clearBtn.addEventListener('click', (e) => {
            e.stopPropagation(); // prevent dropdown from closing on click
            fetch(`${BASE_PATH}/api/notifications/read`, { method: 'POST' })
                .then(() => {
                    badge.classList.add('d-none');
                    renderMenu([]);
                })
                .catch(() => {});
        });
    }

    poll();                         // run immediately on page load
    setInterval(poll, 30_000);      // then every 30 seconds
})();
