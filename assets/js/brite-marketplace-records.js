/* Marketplace record-sheet enhancement. Progressive enhancement: existing API actions remain intact. */
(() => {
    'use strict';
    const roots = [...document.querySelectorAll('.brite-marketplace .bidding-projects')];
    if (!roots.length) return;

    const normalize = value => (value || '').replace(/\s+/g, ' ').trim();
    const state = new Map();
    const idFor = (root, card, index) => {
        const heading = card.querySelector('h3,h4');
        const lines = [...card.querySelectorAll('p')].slice(0, 4).map(p => normalize(p.textContent));
        return [root.id, normalize(heading?.textContent), ...lines, index].join('|');
    };

    // Flat, accessible SVG symbols distinguish marketplace record states.
    const symbols = {
        open: '<path d="M4 7h16v13H4z"/><path d="M8 7V4h8v3"/><path d="M4 12h16"/>',
        awarded: '<path d="m5 12 4 4L19 6"/><circle cx="12" cy="12" r="9"/>',
        accepted: '<path d="m5 12 4 4L19 6"/><circle cx="12" cy="12" r="9"/>',
        selected: '<path d="m5 12 4 4L19 6"/><circle cx="12" cy="12" r="9"/>',
        shortlisted: '<path d="m12 3 2.8 5.7 6.2.9-4.5 4.4 1.1 6.2L12 17.3l-5.6 2.9 1.1-6.2L3 9.6l6.2-.9z"/>',
        pending: '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
        withdrawn: '<circle cx="12" cy="12" r="9"/><path d="m8 8 8 8m0-8-8 8"/>',
        rejected: '<circle cx="12" cy="12" r="9"/><path d="m8 8 8 8m0-8-8 8"/>',
        declined: '<circle cx="12" cy="12" r="9"/><path d="m8 8 8 8m0-8-8 8"/>',
        unlisted: '<path d="M4 7h16v13H4z"/><path d="M8 7V4h8v3"/><path d="m4 20 16-13"/>',
        cancelled: '<circle cx="12" cy="12" r="9"/><path d="m8 8 8 8m0-8-8 8"/>',
        default: '<rect x="4" y="3" width="16" height="18" rx="2"/><path d="M8 8h8M8 12h8M8 16h5"/>'
    };
    function recordIcon(status, title) {
        const value = (status + ' ' + title).toLowerCase();
        const key = Object.keys(symbols).find(name => name !== 'default' && new RegExp('\\\\b' + name + '\\\\b').test(value)) || 'default';
        const span = document.createElement('span');
        span.className = 'brite-marketplace-record-icon';
        span.dataset.kind = key;
        span.setAttribute('role', 'img');
        span.setAttribute('aria-label', key === 'default' ? 'Marketplace record' : key.charAt(0).toUpperCase() + key.slice(1) + ' record');
        span.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' + symbols[key] + '</svg>';
        return span;
    }

    function upgrade(root) {
        const cards = [...root.children].filter(node =>
            node instanceof HTMLElement && node.classList.contains('bidding-project') &&
            node.dataset.recordSheet !== 'true'
        );
        cards.forEach((card) => {
            const key = idFor(root, card, [...root.children].indexOf(card));
            const previous = state.get(key) === true;
            const heading = card.querySelector('h3,h4');
            const badge = card.querySelector('.bidding-status');
            const paragraphs = [...card.children].filter(el => el.tagName === 'P');
            const title = normalize(heading?.textContent) || 'Marketplace record';
            const status = normalize(badge?.textContent);
            const meta = paragraphs.slice(0, 2).map(p => normalize(p.textContent)).filter(Boolean);

            // Native details/summary provides keyboard and screen-reader semantics.
            const details = document.createElement('details');
            details.className = 'brite-marketplace-record';
            details.open = previous;
            details.dataset.recordKey = key;

            const summary = document.createElement('summary');
            summary.className = 'brite-marketplace-record-summary';
            const main = document.createElement('span');
            main.className = 'brite-marketplace-record-main';
            const titleNode = document.createElement('strong');
            titleNode.className = 'brite-marketplace-record-title';
            titleNode.textContent = title;
            main.appendChild(titleNode);
            if (meta.length) {
                const description = document.createElement('span');
                description.className = 'brite-marketplace-record-meta';
                description.textContent = meta.join('  ·  ');
                main.appendChild(description);
            }
            summary.appendChild(recordIcon(status, title));
            summary.appendChild(main);
            if (status) {
                const statusNode = document.createElement('span');
                statusNode.className = 'brite-marketplace-record-state';
                statusNode.textContent = status;
                if (badge?.dataset.state) statusNode.dataset.state = badge.dataset.state;
                summary.appendChild(statusNode);
            }
            const indicator = document.createElement('span');
            indicator.className = 'brite-marketplace-record-chevron';
            indicator.setAttribute('aria-hidden', 'true');
            indicator.textContent = '⌄';
            summary.appendChild(indicator);
            details.appendChild(summary);

            const sheet = document.createElement('div');
            sheet.className = 'brite-marketplace-record-sheet';
            while (card.firstChild) sheet.appendChild(card.firstChild);
            // Heading remains inside expanded record for context.
            details.appendChild(sheet);
            card.dataset.recordSheet = 'true';
            card.classList.add('brite-marketplace-record-host');
            card.appendChild(details);
            details.addEventListener('toggle', () => state.set(key, details.open));
        });
    }

    roots.forEach(root => {
        let queued = false;
        const observer = new MutationObserver(() => {
            if (queued) return;
            queued = true;
            queueMicrotask(() => { queued = false; upgrade(root); });
        });
        observer.observe(root, {childList: true});
        upgrade(root);
    });
})();
