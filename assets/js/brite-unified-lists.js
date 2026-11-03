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
   if(table.querySelector('[role="grid"],[data-sort],.sortable')||table.closest('.brite-pagination'))return;
   table.dataset.briteListEnhanced='true';
   const group=kind(table);
   const type=document.createElement('th');type.textContent='Type';type.scope='col';type.className='brite-list-type-heading';
   table.tHead.rows[0].prepend(type);
   const original=[...body.rows];
   original.forEach(row=>{
     const cell=document.createElement('td');cell.className='brite-list-icon-cell';
     const icon=document.createElement('span');icon.className='brite-list-flat-icon';icon.dataset.kind=group;
     icon.setAttribute('role','img');icon.setAttribute('aria-label',group==='default'?'Record':group);
     icon.innerHTML='<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'+iconPaths[group]+'</svg>';
     cell.append(icon);row.prepend(cell);
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
   const sortable=[type,...headers];
   function redraw(){
     const sorted=original.map((row,index)=>({row,index})).sort((a,b)=>{
       const aText=field===0?group:normalize(a.row.cells[field]?.textContent);
       const bText=field===0?group:normalize(b.row.cells[field]?.textContent);
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
 function scan(){
   if(typeof BritePagination!=='function')return;
   document.querySelectorAll('main table:not([data-brite-list-enhanced])').forEach(table=>{
     if(table.tBodies.length&&table.tBodies[0].rows.length)init(table);
   });
 }
 if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',scan);
 else scan();
 // Dynamic tables are initialized when first populated; do not alter existing row events.
 let scheduled=false;
 const observer=new MutationObserver(records=>{
   if(!records.some(r=>[...r.addedNodes].some(n=>n.nodeType===1&&(n.matches?.('table,tbody,tr')||n.querySelector?.('table')))))return;
   if(scheduled)return;scheduled=true;
   queueMicrotask(()=>{scheduled=false;scan();});
 });
 observer.observe(document.documentElement,{childList:true,subtree:true});
})();
