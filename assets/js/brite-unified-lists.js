/* Shared progressive enhancement for plain data tables.
 * Existing interactive grids, tables with their own pager, and nested tables opt out.
 * Dates are displayed only when authoritative values exist in the DOM.
 */
(() => {
 'use strict';
 const collator=new Intl.Collator(undefined,{numeric:true,sensitivity:'base'});
 const stamp=value=>{
   const raw=String(value||'').trim();
   if(!raw)return '';
   const normalized=/^\d{4}-\d\d-\d\d \d\d:\d\d/.test(raw)?raw.replace(' ','T')+'Z':raw;
   const date=new Date(normalized);
   return Number.isNaN(date.getTime())?'':new Intl.DateTimeFormat(undefined,{dateStyle:'medium',timeStyle:'short'}).format(date);
 };
 const normalize=value=>String(value||'').replace(/\s+/g,' ').trim();
 const iconPaths={
   client:'<circle cx="12" cy="8" r="4"/><path d="M4 21v-2a8 8 0 0 1 16 0v2"/>',
   contractor:'<path d="M4 7h16v14H4zM8 7V4h8v3M4 12h16"/>',
   appointment:'<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M7 3v4M17 3v4M3 10h18"/>',
   notification:'<path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9M10 21h4"/>',
   location:'<path d="M20 10c0 6-8 12-8 12S4 16 4 10a8 8 0 1 1 16 0z"/><circle cx="12" cy="10" r="2"/>',
   service:'<path d="m4 16 7-7 4 4-7 7H4zM13 7l4-4 4 4-4 4"/>',
   default:'<rect x="4" y="3" width="16" height="18" rx="2"/><path d="M8 8h8M8 12h8M8 16h5"/>'
 };
 function kind(table){
   const text=(table.getAttribute('aria-label')||table.closest('[data-directory]')?.dataset.directory||table.closest('section')?.querySelector('h2')?.textContent||'').toLowerCase();
   return Object.keys(iconPaths).find(key=>text.includes(key))||'default';
 }
 function init(table){
   if(table.dataset.briteListEnhanced||table.closest('[data-brite-list="off"],.brite-marketplace,.dataTables_wrapper,[data-directory-list]'))return;
   if(table.querySelector('table')||!table.tHead||!table.tBodies.length)return;
   const body=table.tBodies[0];
   const headers=[...table.tHead.querySelectorAll('th')];
   if(!headers.length||headers.some(h=>h.colSpan>1||h.rowSpan>1))return;
   if(table.querySelector('[role="grid"],[data-sort],.sortable')||table.closest('.brite-pagination')||table.parentElement?.querySelector('.brite-pagination,.dataTables_paginate,[data-pagination]')||table.closest('[data-brite-pagination-managed]'))return;
   const group=kind(table);
   const original=[...body.rows];
   if(original.some(row=>[...row.cells].some(cell=>cell.colSpan>1||cell.rowSpan>1)))return;
   table.dataset.briteListEnhanced='true';
   const dateColumns=headers.map((h,i)=>/^(created on|created at|last edited|updated at|modified at)$/i.test(normalize(h.textContent))?i:-1).filter(i=>i>=0);
   original.forEach(row=>dateColumns.forEach(i=>{
     const cell=row.cells[i],formatted=stamp(cell.textContent);
     if(formatted){cell.title=normalize(cell.textContent)+' UTC';cell.textContent=formatted;}
   }));
   original.forEach(row=>{
     const cell=document.createElement('td');cell.className='brite-list-icon-cell';
     const icon=document.createElement('span');icon.className='brite-list-flat-icon';icon.dataset.kind=group;
     icon.setAttribute('role','img');icon.setAttribute('aria-label',group==='default'?'Record':group);
     icon.innerHTML='<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'+iconPaths[group]+'</svg>';
     cell.append(icon);
     const first=row.cells[0];
     if(first)first.prepend(icon);
     else row.append(cell);
   });
   const controls=document.createElement('div');controls.className='brite-list-toolbar';
   const top=document.createElement('div');top.className='brite-list-page-top';
   const bottom=document.createElement('div');bottom.className='brite-list-page-bottom';
   const wrapper=document.createElement('div');wrapper.className='brite-list-table-wrap';
   table.before(controls,top,wrapper);wrapper.append(table);wrapper.after(bottom);
   const pager=new BritePagination({container:bottom,render(visible){
     const set=new Set(visible);
     original.forEach(row=>{row.hidden=!set.has(row);});
   }});
   const topPager=new BritePagination({container:top,render(){}});
   const baseSet=pager.setRecords.bind(pager),baseGo=pager.goTo.bind(pager),baseUpdate=pager.update.bind(pager);
   function mirror(){
     topPager.pageSize=pager.pageSize;topPager.pageSizeSelect.value=String(pager.pageSize);
     topPager.records=pager.records.slice();topPager.page=pager.page;topPager.update();
   }
   pager.setRecords=(records,reset)=>{baseSet(records,reset);mirror();};
   pager.goTo=page=>{baseGo(page);mirror();};
   pager.update=()=>{baseUpdate();mirror();};
   topPager.goTo=page=>pager.goTo(page);
   topPager.pageSizeSelect.addEventListener('change',()=>{
     pager.pageSize=topPager.pageSize;pager.pageSizeSelect.value=String(pager.pageSize);
     pager.page=1;pager.update();
   });
   let field=0,direction=1;
   const sortable=headers;
   function redraw(){
     const sorted=original.map((row,index)=>({row,index})).sort((a,b)=>{
       const aText=normalize(a.row.cells[field]?.textContent);
       const bText=normalize(b.row.cells[field]?.textContent);
       return direction*collator.compare(aText,bText)||a.index-b.index;
     });
     sorted.forEach(({row})=>body.append(row));
     pager.setRecords(sorted.map(entry=>entry.row));
   }
   sortable.forEach((header,index)=>{
     if(header.querySelector('button,a,input')||header.dataset.briteSortOff==='true')return;
     const label=normalize(header.textContent)||'Column';
     const button=document.createElement('button');button.type='button';button.className='brite-list-sort';
     button.textContent=label;button.setAttribute('aria-label','Sort by '+label);
     header.replaceChildren(button);
     button.addEventListener('click',()=>{
       direction=field===index?-direction:1;field=index;
       sortable.forEach(h=>h.removeAttribute('aria-sort'));
       header.setAttribute('aria-sort',direction===1?'ascending':'descending');
       redraw();
     });
   });
   redraw();
 }

 // Accordion directories are not HTML tables. Enhance their record summaries too.
 // Do not take over lists already managed by a server/client paginator.
 const cardLists=new WeakMap();
 function enhanceCards(root){
   if(cardLists.has(root)||root.closest('.brite-marketplace,[data-brite-list="off"]'))return;
   if(root.id==='rows'&&document.getElementById('contractorPagination'))return;
   if(root.id==='staffRecords'||root.id==='inviteRecords'||root.hasAttribute('data-directory-list')){
     const cards=[...root.children].filter(el=>el.matches('details.brite-directory-record'));
     if(!cards.length)return;
     const category=root.hasAttribute('data-directory-list')?root.closest('[data-directory]')?.dataset.directory:
       root.id==='inviteRecords'?'notification':'contractor';
     const group=category?.startsWith('client')?'client':category?.startsWith('contractor')?'contractor':category||'default';
     const heading=document.createElement('div');heading.className='brite-list-card-headings';
     const fields=group==='client'
       ? [['status','Status'],['created','Created On'],['edited','Last Edited']]
       : [['record','Record'],['status','Status'],['created','Created On'],['edited','Last Edited']];
     if(group==='client')heading.classList.add('brite-list-client-headings');
     const state={field:'record',direction:1,cards:[],pager:null};
     cardLists.set(root,state);
     fields.forEach(([field,label])=>{
       const button=document.createElement('button');button.type='button';button.className='brite-list-sort';
       button.textContent=label;button.dataset.field=field;
       button.addEventListener('click',()=>{
         state.direction=state.field===field?-state.direction:1;state.field=field;
         state.ready=false;state.pager.goTo(1);refresh();
       });
       heading.append(button);
     });
     const top=document.createElement('div');top.className='brite-list-page-top';
     const bottom=document.createElement('div');bottom.className='brite-list-page-bottom';
     root.before(top,heading);root.after(bottom);
     const pager=new BritePagination({container:bottom,render(rows){
       const visible=new Set(rows);
       state.cards.forEach(row=>{row.hidden=!visible.has(row);});
     }});
     state.pager=pager;
     const topPager=new BritePagination({container:top,render(){}});
     const originalUpdate=pager.update.bind(pager);
     function mirror(){
       topPager.pageSize=pager.pageSize;topPager.pageSizeSelect.value=String(pager.pageSize);
       topPager.records=pager.records.slice();topPager.page=pager.page;topPager.update();
     }
     pager.update=()=>{originalUpdate();mirror();};
     topPager.goTo=page=>pager.goTo(page);
     topPager.pageSizeSelect.addEventListener('change',()=>{
       pager.pageSize=topPager.pageSize;pager.pageSizeSelect.value=String(pager.pageSize);
       pager.page=1;pager.update();
     });
     function decorate(card){
       const summary=card.querySelector(':scope > summary');
       if(!summary||summary.querySelector('.brite-list-flat-icon'))return;
       const icon=document.createElement('span');icon.className='brite-list-flat-icon';
       icon.dataset.kind=group;icon.setAttribute('role','img');icon.setAttribute('aria-label',group+' record');
       icon.innerHTML='<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'+(iconPaths[group]||iconPaths.default)+'</svg>';
       summary.prepend(icon);
     }
     function value(card,field){
       if(field==='type')return group;
       if(field==='status')return normalize(card.querySelector('.brite-directory-state')?.textContent);
       if(field==='created'||field==='edited'){
         const t=card.querySelector('.brite-record-timestamps')?.textContent||'';
         const match=t.match(new RegExp((field==='created'?'Created on':'Last edited')+':\\s*(.*?)(?=\\s*·\\s*|$)','i'));
         const parsed=match?Date.parse(match[1]):NaN;
         return Number.isFinite(parsed)?parsed:-1;
       }
       return normalize(card.querySelector('summary strong')?.textContent||card.querySelector('summary')?.textContent);
     }
     function refresh(){
       const incoming=[...root.children].filter(el=>el.matches('details.brite-directory-record'));
       if(state.cards.length===incoming.length&&incoming.every(card=>state.cards.includes(card))&&state.ready)return;
       state.cards=incoming;
       state.cards.forEach(decorate);
       const sorted=state.cards.map((card,index)=>({card,index})).sort((a,b)=>{
         const av=value(a.card,state.field),bv=value(b.card,state.field);
         return state.direction*(typeof av==='number'&&typeof bv==='number'?av-bv:collator.compare(av,bv))||a.index-b.index;
       });
       sorted.forEach(({card})=>root.append(card));
       pager.setRecords(sorted.map(x=>x.card));
       state.ready=true;
       heading.querySelectorAll('button').forEach(button=>{
         const active=button.dataset.field===state.field;
         button.setAttribute('aria-pressed',String(active));
         button.textContent=fields.find(f=>f[0]===button.dataset.field)[1]+(active?(state.direction===1?' ▲':' ▼'):'');
       });
     }
     state.refresh=refresh;
     refresh();
   }
 }

 function cardListsForRefresh(){
   document.querySelectorAll('main [data-directory-list], main #staffRecords, main #inviteRecords').forEach(root=>cardLists.get(root)?.refresh?.());
 }
 function scan(){
   if(typeof BritePagination!=='function')return;
   document.querySelectorAll('main [data-directory-list], main #staffRecords, main #inviteRecords').forEach(enhanceCards);
   document.querySelectorAll('main table:not([data-brite-list-enhanced])').forEach(table=>{
     if(table.tBodies.length&&table.tBodies[0].rows.length)init(table);
   });
 }
 if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',scan);
 else scan();
 // Dynamic tables are initialized when first populated; do not alter existing row events.
 let scheduled=false;
 const observer=new MutationObserver(records=>{
   if(!records.some(r=>[...r.addedNodes].some(n=>n.nodeType===1&&(n.matches?.('table,tbody,tr,.brite-directory-record')||n.querySelector?.('table,.brite-directory-record')))))return;
   if(scheduled)return;scheduled=true;
   queueMicrotask(()=>{scheduled=false;scan();cardListsForRefresh();});
 });
 observer.observe(document.documentElement,{childList:true,subtree:true});
})();
