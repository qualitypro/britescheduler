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

        function closeSidebar() {
            app.classList.remove('brite-sidebar-open');
            toggle.setAttribute('aria-expanded', 'false');
        }

        function openSidebar() {
            app.classList.add('brite-sidebar-open');
            toggle.setAttribute('aria-expanded', 'true');
            const firstLink = sidebar.querySelector('a');
            if (firstLink) firstLink.focus();
        }

        toggle.addEventListener('click', function () {
            if (app.classList.contains('brite-sidebar-open')) {
                closeSidebar();
            } else {
                openSidebar();
            }
        });

        backdrop.addEventListener('click', closeSidebar);

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') closeSidebar();
        });

        sidebar.addEventListener('click', function (event) {
            if (event.target.closest('a')) closeSidebar();
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
