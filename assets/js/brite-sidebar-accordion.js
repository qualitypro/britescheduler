/* Cycle 90AK — Restore the accessible, exclusive sidebar accordion.
 * Enhance existing role-filtered navigation in place; never change link URLs.
 */
(() => {
    'use strict';
    function init() {
        const nav = document.querySelector('#briteSidebar .brite-sidebar-nav');
        if (!nav || nav.dataset.accordionReady === '1') return;
        const labels = Array.from(nav.children).filter(el =>
            el.classList?.contains('brite-nav-group-label'));
        if (!labels.length) return;

        const icons = {
            workspace: '<rect x="3" y="4" width="18" height="16" rx="2"/><path d="M3 10h18M9 10v10"/>',
            management: '<path d="M3 21V7l9-4 9 4v14M3 10h18M9 21v-7h6v7"/>',
            administration: '<circle cx="12" cy="12" r="3"/><path d="M12 2v3M12 19v3M2 12h3M19 12h3M4.9 4.9 7 7M17 17l2.1 2.1M19.1 4.9 17 7M7 17l-2.1 2.1"/>',
            subscriptions: '<rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><path d="M14 17.5h7M17.5 14v7"/>',
            personal: '<circle cx="12" cy="8" r="4"/><path d="M4 21v-2a8 8 0 0 1 16 0v2"/>'
        };
        const groups = labels.map((label, index) => {
            const title = label.textContent.trim();
            const key = title.toLowerCase().replace(/[^a-z0-9]+/g, '-');
            const region = document.createElement('div');
            region.className = 'brite-nav-accordion-panel';
            region.id = 'brite-nav-panel-' + index;
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'brite-nav-accordion-trigger';
            button.setAttribute('aria-controls', region.id);
            const icon = document.createElement('span');
            icon.className = 'brite-nav-accordion-icon';
            icon.setAttribute('aria-hidden', 'true');
            icon.innerHTML = '<svg viewBox="0 0 24 24" width="17" height="17" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">' +
                (icons[key] || '<rect x="4" y="4" width="16" height="16" rx="3"/>') + '</svg>';
            const text = document.createElement('span');
            text.textContent = title;
            button.append(icon, text);
            const items = [];
            let sibling = label.nextElementSibling;
            while (sibling && !sibling.classList.contains('brite-nav-group-label')) {
                const next = sibling.nextElementSibling;
                items.push(sibling);
                sibling = next;
            }
            label.replaceWith(button);
            button.after(region);
            region.append(...items);
            return {key, button, region, active: !!region.querySelector('a[aria-current="page"]')};
        });
        if (!groups.length) return;
        // Persist the user's open section across full-page navigation.
        // Scope it to this tenant/role so switching workspaces cannot
        // restore an unrelated section.
        const tenantLabel = document.querySelector('#briteSidebar .brite-sidebar-footer')?.textContent.trim() || '';
        const role = document.querySelector('.brite-account-role')?.textContent.trim() || '';
        const storageKey = 'brite:sidebar:open:' + tenantLabel + ':' + role;
        function readStored() {
            try { return sessionStorage.getItem(storageKey); } catch (_) { return null; }
        }
        function saveStored(key) {
            try {
                if (key === null) sessionStorage.removeItem(storageKey);
                else sessionStorage.setItem(storageKey, key);
            } catch (_) { /* Storage may be unavailable. */ }
        }
        function activate(selected, persist = true) {
            for (const group of groups) {
                const expanded = group === selected;
                group.button.setAttribute('aria-expanded', String(expanded));
                group.region.hidden = !expanded;
            }
            if (persist) saveStored(selected ? selected.key : null);
        }
        nav.addEventListener('click', event => {
            const button = event.target.closest('.brite-nav-accordion-trigger');
            if (button && nav.contains(button)) {
                event.preventDefault();
                const group = groups.find(item => item.button === button);
                if (!group) return;
                activate(button.getAttribute('aria-expanded') === 'true' ? null : group);
                return;
            }
            const link = event.target.closest('.brite-nav-accordion-panel a[href]');
            if (link && nav.contains(link)) {
                const group = groups.find(item => item.region.contains(link));
                if (group) saveStored(group.key);
            }
        });
        const storedKey = readStored();
        // A stored open section takes precedence on reload; on first visit
        // show the section containing the active page.
        const initial = storedKey === null
            ? (groups.find(group => group.active) || groups[0])
            : groups.find(group => group.key === storedKey);
        activate(initial || groups.find(group => group.active) || groups[0], false);
        nav.dataset.accordionReady = '1';
    }
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
