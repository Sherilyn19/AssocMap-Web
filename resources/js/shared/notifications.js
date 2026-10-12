// Membership notification bell: loads newest notifications and plays a sound for new items.
(() => {
    const root = document.querySelector('[data-notification-root]');

    if (!root) {
        return;
    }

    const toggle = root.querySelector('[data-notification-toggle]');
    const panel = root.querySelector('[data-notification-panel]');
    const list = root.querySelector('[data-notification-list]');
    const count = root.querySelector('[data-notification-count]');
    const readAll = root.querySelector('[data-notification-read-all]');

    // Use the sound file that exists in public/sounds.
    const sound = new Audio('/sounds/cute-pop-sound-effects.mp3');

    let knownIds = new Set();

    const escapeHtml = (value) => String(value)
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');

    const render = (notifications) => {
        if (!notifications.length) {
            list.innerHTML = '<p class="am-notification-empty">No notifications yet.</p>';
            return;
        }

        list.innerHTML = notifications.map((item) => `
            <a
                class="am-notification-item ${item.read ? '' : 'is-unread'}"
                href="${escapeHtml(item.url)}"
                data-notification-id="${escapeHtml(item.id)}"
            >
                <strong>${escapeHtml(item.title)}</strong>
                <span>${escapeHtml(item.message)}</span>
                <time>${new Date(item.created_at).toLocaleString()}</time>
            </a>
        `).join('');
    };

    const load = async (playSound = false) => {
        const response = await fetch('/notifications', {
            headers: { Accept: 'application/json' },
        });

        if (!response.ok) {
            return;
        }

        const payload = await response.json();
        const notifications = payload.notifications ?? [];

        const hasNewNotification = notifications.some((item) => !knownIds.has(item.id));

        if (playSound && hasNewNotification) {
            sound.currentTime = 0;
            sound.play().catch(() => {
                // Browsers may block audio until the user interacts with the page.
            });
        }

        knownIds = new Set(notifications.map((item) => item.id));

        if (count) {
            count.textContent = payload.unread_count;
            count.classList.toggle('hidden', payload.unread_count === 0);
        }

        render(notifications);
    };

    toggle.addEventListener('click', async () => {
        const opening = panel.hidden;

        panel.hidden = !opening;
        toggle.setAttribute('aria-expanded', String(opening));

        if (opening) {
            await load();
        }
    });

    readAll.addEventListener('click', async () => {
        await fetch('/notifications/read-all', {
            method: 'PATCH',
            headers: {
                Accept: 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
            },
        });

        await load();
    });

    list.addEventListener('click', async (event) => {
        const item = event.target.closest('[data-notification-id]');

        if (!item) {
            return;
        }

        await fetch(`/notifications/${item.dataset.notificationId}/read`, {
            method: 'PATCH',
            headers: {
                Accept: 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
            },
        });
    });

    // Polling provides new notifications without requiring an external API.
    load();
    window.setInterval(() => load(true), 30000);
})();