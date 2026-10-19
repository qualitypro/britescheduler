/*
 * Brite Scheduler — Owner Activity Hub
 *
 * Reuses the existing owner notification APIs.
 * Does not change appointment state.
 */
(() => {
    'use strict';

    const hub = document.getElementById('briteActivityHub');
    const toggle = document.getElementById('briteActivityHubToggle');

    if (!hub || !toggle) return;

    const close = document.getElementById('briteActivityHubClose');
    const count = document.getElementById('briteActivityHubCount');
    const toggleCount = document.getElementById('briteActivityHubToggleCount');
    const status = document.getElementById('briteActivityHubStatus');
    const list = document.getElementById('briteActivityHubList');

    if (!close || !count || !toggleCount || !status || !list) return;

    const base = (window.BRITE_BASE || '/dashboard').replace(/\/$/, '');

    let open = false;
    let loading = false;
    let initialized = false;

    const backdrop = document.createElement('button');
    backdrop.type = 'button';
    backdrop.className = 'brite-activity-hub-backdrop';
    backdrop.setAttribute('aria-label', 'Close activity hub');
    backdrop.hidden = true;
    hub.insertAdjacentElement('beforebegin', backdrop);

    function setOpen(nextOpen, restoreFocus = false) {
        open = Boolean(nextOpen);

        document.body.classList.toggle('brite-activity-hub-open', open);

        hub.hidden = !open;
        hub.setAttribute('aria-hidden', String(!open));

        toggle.hidden = open;
        backdrop.hidden = !open;
        toggle.setAttribute('aria-expanded', String(open));
        toggle.setAttribute(
            'aria-label',
            open ? 'Close activity hub' : 'Open activity hub'
        );

        if (open) {
            close.focus();
            loadNotifications();
        } else if (restoreFocus) {
            toggle.focus();
        }
    }

    function showStatus(message) {
        status.hidden = false;
        status.textContent = message;
    }

    function updateCount(value) {
        const unread = Number(value);
        const safeCount = Number.isSafeInteger(unread) && unread >= 0
            ? unread
            : 0;

        count.textContent = String(safeCount);
        toggleCount.textContent = String(safeCount);

        toggle.setAttribute(
            'aria-label',
            'Open activity hub, ' + safeCount + ' unread notifications'
        );
    }

    function makeElement(tag, className, text) {
        const element = document.createElement(tag);

        if (className) element.className = className;
        if (text !== undefined) element.textContent = String(text);

        return element;
    }

    async function markRead(notificationId, button) {
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
                throw new Error(data.error || 'Unable to update notification');
            }

            await loadNotifications();

        } catch (error) {
            button.disabled = false;
            button.textContent = 'Mark as Read';

            showStatus('Unable to mark notification as read.');
            console.error('Activity Hub mark read:', error);
        }
    }

    function renderNotification(notification) {
        const unread = notification.read_at === null;

        const item = makeElement(
            'article',
            'brite-activity-hub-item' + (unread ? ' is-unread' : '')
        );

        const title = makeElement(
            'strong',
            'brite-activity-hub-item-title',
            notification.title || 'Notification'
        );

        const body = makeElement(
            'p',
            'brite-activity-hub-item-body',
            notification.body || ''
        );

        const meta = makeElement(
            'small',
            'brite-activity-hub-item-meta',
            unread ? 'Unread' : 'Read'
        );

        const actions = makeElement(
            'div',
            'brite-activity-hub-item-actions'
        );

        const appointmentId = Number(notification.appointment_id);

        if (
            notification.type === 'appointment' &&
            notification.appointment_id !== null &&
            Number.isSafeInteger(appointmentId) &&
            appointmentId > 0
        ) {
            const link = makeElement(
                'a',
                '',
                'Review Booking'
            );

            link.href = base +
                '/scheduling.php?appointment_id=' +
                encodeURIComponent(String(appointmentId));

            actions.appendChild(link);
        }

        if (unread) {
            const button = makeElement(
                'button',
                '',
                'Mark as Read'
            );

            button.type = 'button';

            button.addEventListener('click', () => {
                markRead(notification.id, button);
            });

            actions.appendChild(button);
        }

        item.append(title, body, meta);

        if (actions.childElementCount > 0) {
            item.appendChild(actions);
        }

        return item;
    }

    async function loadNotifications() {
        if (loading) return;

        loading = true;

        try {
            const response = await fetch(
                base + '/api/owner_notifications.php',
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

            updateCount(data.unread_count);

            const fragment = document.createDocumentFragment();

            for (const notification of data.notifications) {
                fragment.appendChild(renderNotification(notification));
            }

            list.replaceChildren(fragment);

            if (data.notifications.length === 0) {
                showStatus('No notifications yet.');
            } else {
                status.hidden = true;
            }

        } catch (error) {
            showStatus('Unable to load notifications.');
            console.error('Activity Hub loading:', error);
        } finally {
            loading = false;
        }
    }

    function initialize() {
        if (initialized) return;
        initialized = true;

        // Both desktop and mobile start with the Activity Hub closed.
        hub.hidden = false;
        toggle.hidden = false;

        setOpen(false);

        toggle.addEventListener('click', () => setOpen(true));

        close.addEventListener('click', () => setOpen(false, true));
        backdrop.addEventListener('click', () => setOpen(false, true));

        document.addEventListener('keydown', event => {
            if (event.key === 'Escape' && open) {
                setOpen(false, true);
            }
        });

        window.addEventListener('resize', () => {
            // Maintain explicit owner choice across viewport changes.
            hub.setAttribute('aria-hidden', String(!open));
        });

        // Lightweight polling for new notifications.
        window.setInterval(() => {
            if (document.visibilityState === 'visible') {
                loadNotifications();
            }
        }, 60000);

        // Load the unread badge without opening the panel.
        loadNotifications();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initialize, {
            once: true
        });
    } else {
        initialize();
    }
})();
