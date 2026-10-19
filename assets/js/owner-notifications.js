(() => {
    'use strict';

    const panel = document.getElementById('ownerNotifications');
    if (!panel) return;

    const status = document.getElementById('ownerNotificationsStatus');
    const count = document.getElementById('ownerNotificationsCount');
    const list = document.getElementById('ownerNotificationsList');

    const base = (window.BRITE_BASE || '/dashboard')
        .replace(/\/$/, '');

    async function markNotificationRead(notificationId, button) {
        button.disabled = true;
        button.textContent = 'Saving…';

        try {
            const response = await fetch(
                base + '/api/owner_notification_read.php',
                {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': window.BRITE_CSRF || ''
                    },
                    body: JSON.stringify({
                        notification_id: notificationId
                    })
                }
            );

            const data = await response.json();

            if (!response.ok || data.ok !== true) {
                throw new Error(data.error || 'HTTP ' + response.status);
            }

            await loadNotifications();

        } catch (error) {
            button.disabled = false;
            button.textContent = 'Mark as Read';

            status.hidden = false;
            status.textContent = 'Unable to mark notification as read.';

            console.error('Mark notification read:', error);
        }
    }

    async function loadNotifications() {
        try {
            const response = await fetch(
                window.BRITE_BASE + '/api/owner_notifications.php',
                {
                    credentials: 'same-origin',
                    cache: 'no-store'
                }
            );

            if (!response.ok) {
                throw new Error('HTTP ' + response.status);
            }

            const data = await response.json();

            if (!data.ok || !Array.isArray(data.notifications)) {
                throw new Error('Invalid notification response');
            }

            count.textContent = String(data.unread_count);
            list.replaceChildren();

            if (data.notifications.length === 0) {
                status.hidden = false;
                status.textContent = 'No notifications yet.';
                return;
            }

            status.hidden = true;

            for (const notification of data.notifications) {
                const item = document.createElement('article');
                item.className = 'brite-dashboard-quick-action';
                item.style.display = 'block';
                item.style.marginBottom = '10px';

                const heading = document.createElement('strong');
                heading.textContent = notification.title;

                const body = document.createElement('p');
                body.textContent = notification.body;
                body.style.margin = '8px 0';

                const meta = document.createElement('small');
                meta.textContent =
                    notification.read_at === null
                        ? 'Unread'
                        : 'Read';

                item.append(heading, body, meta);

                const appointmentId = notification.appointment_id;

                if (
                    notification.type === 'appointment' &&
                    appointmentId !== null &&
                    appointmentId !== undefined &&
                    Number.isSafeInteger(Number(appointmentId)) &&
                    Number(appointmentId) > 0
                ) {
                    const reviewLink = document.createElement('a');
                    reviewLink.textContent = 'Review Booking';
                    reviewLink.href = base +
                        '/scheduling.php?appointment_id=' +
                        encodeURIComponent(String(appointmentId));
                    reviewLink.style.display = 'inline-block';
                    reviewLink.style.marginTop = '10px';
                    reviewLink.style.marginRight = '12px';

                    item.appendChild(reviewLink);
                }

                if (notification.read_at === null) {
                    const button = document.createElement('button');
                    button.type = 'button';
                    button.textContent = 'Mark as Read';
                    button.className = 'brite-btn';
                    button.style.display = 'block';
                    button.style.marginTop = '10px';
                    button.style.padding = '8px 12px';
                    button.style.cursor = 'pointer';

                    button.addEventListener('click', () => {
                        markNotificationRead(notification.id, button);
                    });

                    item.appendChild(button);
                }

                list.appendChild(item);
            }

        } catch (error) {
            status.hidden = false;
            status.textContent = 'Unable to load notifications.';
            console.error('Owner notifications:', error);
        }
    }

    loadNotifications();
})();
