/*
 * Brite Scheduler — Responsive Application Shell
 * Milestone 146A
 */

(function () {
    'use strict';

    function init() {
        const app = document.querySelector('.brite-app');
        const toggle = document.getElementById('briteMobileToggle');
        const backdrop = document.getElementById('briteSidebarBackdrop');
        const sidebar = document.getElementById('briteSidebar');

        if (!app || !toggle || !backdrop || !sidebar) return;

        let focusBeforeOpen = null;
        function closeSidebar(restoreFocus = false) {
            const wasOpen = app.classList.contains('brite-sidebar-open');
            app.classList.remove('brite-sidebar-open');
            toggle.setAttribute('aria-expanded', 'false');
            backdrop.setAttribute('aria-hidden', 'true');
            if (wasOpen && restoreFocus && focusBeforeOpen?.isConnected) {
                focusBeforeOpen.focus();
            }
        }

        function openSidebar() {
            focusBeforeOpen = document.activeElement;
            app.classList.add('brite-sidebar-open');
            toggle.setAttribute('aria-expanded', 'true');
            backdrop.setAttribute('aria-hidden', 'false');
            const firstLink = sidebar.querySelector('a');
            if (firstLink) firstLink.focus();
        }

        toggle.addEventListener('click', function () {
            if (app.classList.contains('brite-sidebar-open')) {
                closeSidebar(true);
            } else {
                openSidebar();
            }
        });

        backdrop.addEventListener('click', () => closeSidebar(true));

        document.addEventListener('keydown', function (event) {
            if (!app.classList.contains('brite-sidebar-open')) return;
            if (event.key === 'Escape') {
                event.preventDefault();
                closeSidebar(true);
            }
            if (event.key === 'Tab') {
                const focusable = Array.from(sidebar.querySelectorAll(
                    'a[href], button:not([disabled]), [tabindex]:not([tabindex="-1"])'
                )).filter(element => element.getClientRects().length);
                if (!focusable.length) return;
                const first = focusable[0];
                const last = focusable[focusable.length - 1];
                if (event.shiftKey && (document.activeElement === first || !sidebar.contains(document.activeElement))) {
                    event.preventDefault();
                    last.focus();
                } else if (!event.shiftKey && (document.activeElement === last || !sidebar.contains(document.activeElement))) {
                    event.preventDefault();
                    first.focus();
                }
            }
        });

        sidebar.addEventListener('click', function (event) {
            if (event.target.closest('a')) closeSidebar(false);
        });

        window.addEventListener('resize', function () {
            if (window.innerWidth > 900) closeSidebar();
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
