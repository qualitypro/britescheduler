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
            management: '<rect x="5" y="4" width="14" height="17" rx="2"/><path d="M9 4V2h6v2M9 10h6M9 14h6M9 18h4"/>',
            administration: '<path d="M12 2 4 5v6c0 5 3 8 8 11 5-3 8-6 8-11V5z"/><path d="M9 12l2 2 4-4"/>',
            subscriptions: '<rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><path d="M14 17.5h7M17.5 14v7"/>',
            crm: '<circle cx="8" cy="8" r="3"/><path d="M2.5 20v-2a5.5 5.5 0 0 1 11 0v2"/><rect x="15" y="4" width="7" height="5" rx="1"/><path d="M16 13h5M16 17h5M16 21h5"/>',
            intelligence: '<path d="M4 19V12M9 19V8M14 19V14M19 19V5"/><path d="M3 21h18"/><circle cx="19" cy="4" r="1"/>',
            personal: '<rect x="3" y="4" width="18" height="16" rx="2"/><circle cx="9" cy="11" r="2.5"/><path d="M5 17c0-2 1.5-3 4-3s4 1 4 3M15 9h4M15 12h4"/>'
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
