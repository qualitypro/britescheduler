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
        const words = value.split(/[^a-z]+/).filter(Boolean);
        const key = Object.keys(symbols).find(name => name !== 'default' && words.includes(name)) || 'default';
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

    // Per-list sorting, type filtering and pagination. API-rendered rows remain intact.
    const listState = new WeakMap();
    const collator = new Intl.Collator(undefined,{numeric:true,sensitivity:'base'});
    const textOf = (row,selector) => normalize(row.querySelector(selector)?.textContent || '');
    function recordType(row) {
        const title=textOf(row,'.brite-marketplace-record-title').toLowerCase();
        const content=textOf(row,'.brite-marketplace-record-sheet').toLowerCase();
        if (/notification|alert|message/.test(title)) return 'Notification';
        if (/booking request/.test(title)) return 'Booking Request';
        if (/bid|proposal/.test(title)) return 'Bid';
        if (/assignment/.test(title)) return 'Assignment';
        if (/listing/.test(title)) return 'Listing';
        if (/appointment/.test(title)) return 'Appointment';
        if (/notification|alert/.test(content) && !/appointment|listing|bid/.test(title)) return 'Notification';
        return 'Other';
    }
    function fieldValue(row,field) {
        if(field==='type') return recordType(row);
        return textOf(row,({
            record:'.brite-marketplace-record-title',
            details:'.brite-marketplace-record-meta',
            status:'.brite-marketplace-record-state'
        })[field] || '.brite-marketplace-record-title');
    }
    function draw(root) {
        const st=listState.get(root);
        if(!st) return;
        const rows=[...root.children].filter(el=>el.classList?.contains('brite-marketplace-record-host'));
        const types=[...new Set(rows.map(recordType))].sort(collator.compare);
        const prior=st.type.value;
        st.type.replaceChildren(new Option('All record types','all'),...types.map(type=>new Option(type,type)));
        st.type.value=types.includes(prior)?prior:'all';
        const matching=rows.filter(row=>st.type.value==='all'||recordType(row)===st.type.value);
        const ordered=matching.map((row,index)=>({row,index})).sort((a,b)=>
            st.direction*collator.compare(fieldValue(a.row,st.field),fieldValue(b.row,st.field))||a.index-b.index
        );
        // Hide rather than detach records, preserving active forms and event handlers.
        const total=ordered.length;
        const pageSize=Number(st.size.value);
        const pages=Math.max(1,Math.ceil(total/pageSize));
        st.page=Math.min(st.page,pages);
        const visible=new Set(ordered.slice((st.page-1)*pageSize,st.page*pageSize).map(x=>x.row));
        const ranks=new Map(ordered.map((entry,index)=>[entry.row,index]));
        rows.forEach(row=>{row.hidden=!visible.has(row);row.style.order=String(ranks.get(row)??rows.length);});
        // CSS display overrides hidden in some layouts; mark for explicit hiding.
        st.summary.textContent=total ? 'Showing '+((st.page-1)*pageSize+1)+'–'+Math.min(st.page*pageSize,total)+' of '+total+' records' : 'No matching records';
        st.pageLabel.textContent='Page '+st.page+' of '+pages;
        st.previous.disabled=st.page<=1;
        st.next.disabled=st.page>=pages;
        st.headers.querySelectorAll('button').forEach(button=>{
            const active=button.dataset.sortField===st.field;
            button.setAttribute('aria-pressed',String(active));
            button.textContent=button.dataset.label+(active?(st.direction===1?' ▲':' ▼'):'');
        });
    }
    function setup(root) {
        if(listState.has(root)) return;
        const headers=document.createElement('div');
        headers.className='brite-marketplace-sort-headers';
        headers.setAttribute('role','group');
        headers.setAttribute('aria-label','Sort Marketplace records');
        const toolbar=document.createElement('div');
        toolbar.className='brite-marketplace-list-tools';
        const type=document.createElement('select');
        type.setAttribute('aria-label','Filter by record or notification type');
        const size=document.createElement('select');
        size.setAttribute('aria-label','Records per page');
        [10,25,50,100].forEach(n=>size.add(new Option(n+' per page',String(n))));
        size.value='10';
        const summary=document.createElement('span');
        summary.setAttribute('role','status');
        const previous=document.createElement('button');
        previous.type='button';previous.textContent='Previous';
        const pageLabel=document.createElement('span');
        const next=document.createElement('button');
        next.type='button';next.textContent='Next';
        toolbar.append(type,size,summary,previous,pageLabel,next);
        const st={field:'record',direction:1,page:1,headers,type,size,summary,previous,next,pageLabel};
        listState.set(root,st);
        for(const [field,title] of [['record','Record'],['details','Details'],['type','Type / Notification'],['status','Status']]){
            const button=document.createElement('button');
            button.type='button';
            button.className='brite-marketplace-sort-heading';
            button.dataset.sortField=field;
            button.dataset.label=title;
            button.setAttribute('aria-label','Sort by '+title);
            button.addEventListener('click',()=>{
                st.direction=st.field===field?-st.direction:1;
                st.field=field;st.page=1;draw(root);
            });
            headers.append(button);
        }
        type.addEventListener('change',()=>{st.page=1;draw(root);});
        size.addEventListener('change',()=>{st.page=1;draw(root);});
        previous.addEventListener('click',()=>{st.page=Math.max(1,st.page-1);draw(root);});
        next.addEventListener('click',()=>{st.page++;draw(root);});
        root.before(headers,toolbar);
        draw(root);
    }
    roots.forEach(root=>{
        setup(root);
        let queued=false;
        const observer=new MutationObserver(records=>{
            // Ignore changes to attributes and inner details; API refreshes replace rows.
            if(!records.some(record=>[...record.addedNodes,...record.removedNodes].some(node=>
                node instanceof HTMLElement && node.classList.contains('bidding-project')))) return;
            if(queued) return;
            queued=true;
            queueMicrotask(()=>{queued=false;upgrade(root);draw(root);});
        });
        observer.observe(root,{childList:true});
        upgrade(root);
        draw(root);
    });
})();
