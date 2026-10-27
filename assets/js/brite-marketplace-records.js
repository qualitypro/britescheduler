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
