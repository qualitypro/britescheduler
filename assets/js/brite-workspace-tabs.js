/*
 * Brite Scheduler — Accessible Workspace Tabs
 * Milestone 148D
 */
(() => {
    'use strict';

    function initializeWorkspace(root) {
        function updateViewport() {
            if (root.dataset.viewport !== 'true') return;

            const top = root.getBoundingClientRect().top +
                window.scrollY;

            root.style.setProperty(
                '--brite-workspace-top',
                Math.max(0, Math.round(top)) + 'px'
            );
        }

        const dashboardMain = root.matches(
            '.brite-dashboard[data-viewport="true"]'
        ) ? root.closest('main.brite-content') : null;

        function normalizeDashboardPosition() {
            if (!dashboardMain) return;

            dashboardMain.scrollTop = 0;
            dashboardMain.scrollLeft = 0;

            updateViewport();
        }

        normalizeDashboardPosition();

        window.addEventListener(
            'resize',
            normalizeDashboardPosition,
            {passive: true}
        );

        if (dashboardMain) {
            window.addEventListener('load', () => {
                normalizeDashboardPosition();

                requestAnimationFrame(() => {
                    normalizeDashboardPosition();
                });
            }, {once: true});
        }

        const tabList = root.querySelector('[role="tablist"]');
        if (!tabList) return;

        const tabs = Array.from(
            tabList.querySelectorAll('[role="tab"]')
        );

        if (!tabs.length) return;

        const entries = tabs.map(tab => {
            const id = tab.getAttribute('aria-controls');
            const panel = id ? document.getElementById(id) : null;

            if (!panel || !root.contains(panel)) return null;

            return {tab, panel};
        }).filter(Boolean);

        if (!entries.length) return;

        function activate(entry, focus = false, updateHash = false) {
            entries.forEach(item => {
                const selected = item === entry;

                item.tab.setAttribute(
                    'aria-selected',
                    selected ? 'true' : 'false'
                );

                item.tab.tabIndex = selected ? 0 : -1;
                item.panel.hidden = !selected;
            });

            if (focus) entry.tab.focus();

            if (updateHash && entry.panel.id) {
                history.replaceState(
                    null,
                    '',
                    '#' + encodeURIComponent(entry.panel.id)
                );
            }

            root.dispatchEvent(new CustomEvent(
                'brite:tabchange',
                {
                    bubbles: true,
                    detail: {
                        tab: entry.tab,
                        panel: entry.panel
                    }
                }
            ));
        }

        function entryFromHash() {
            let id;

            try {
                id = decodeURIComponent(
                    location.hash.slice(1)
                );
            } catch (_) {
                return null;
            }

            return entries.find(item =>
                item.panel.id === id ||
                item.tab.id === id
            ) || null;
        }

        tabs.forEach(tab => {
            const entry = entries.find(item => item.tab === tab);
            if (!entry) return;

            tab.addEventListener('click', () => {
                activate(entry, false, true);
            });

            tab.addEventListener('keydown', event => {
                const index = entries.indexOf(entry);
                let next = index;

                switch (event.key) {
                    case 'ArrowRight':
                    case 'ArrowDown':
                        next = (index + 1) % entries.length;
                        break;
                    case 'ArrowLeft':
                    case 'ArrowUp':
                        next = (index - 1 + entries.length)
                            % entries.length;
                        break;
                    case 'Home':
                        next = 0;
                        break;
                    case 'End':
                        next = entries.length - 1;
                        break;
                    default:
                        return;
                }

                event.preventDefault();
                activate(entries[next], true, true);
            });
        });

        const initiallySelected = entries.find(item =>
            item.tab.getAttribute('aria-selected') === 'true'
        );

        activate(
            entryFromHash() ||
            initiallySelected ||
            entries[0]
        );

        root.addEventListener('click', event => {
            const link = event.target.closest('a[href^="#"]');
            if (!link || !root.contains(link)) return;

            let targetId;

            try {
                targetId = decodeURIComponent(
                    link.getAttribute('href').slice(1)
                );
            } catch (_) {
                return;
            }

            const entry = entries.find(item =>
                item.panel.id === targetId ||
                item.tab.id === targetId
            );

            if (!entry) return;

            event.preventDefault();
            activate(entry, false, true);
        });

        window.addEventListener('hashchange', () => {
            const entry = entryFromHash();
            if (entry) activate(entry);
        });
    }

    function initialize() {
        document.querySelectorAll(
            '.brite-tab-workspace'
        ).forEach(initializeWorkspace);
    }

    if (document.readyState === 'loading') {
        document.addEventListener(
            'DOMContentLoaded',
            initialize
        );
    } else {
        initialize();
    }
})();
