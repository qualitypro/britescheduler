/* Progressive enhancement: without JavaScript, all original CRM forms remain visible. */
(()=>{'use strict';
const root=document.querySelector('.crm-shell');
if(!root)return;
const tab=new URLSearchParams(location.search).get('tab')||'overview';
// Card labels keep table data understandable when columns collapse.
root.querySelectorAll('.crm-table').forEach(table=>{
 const headings=Array.from(table.querySelectorAll('thead th')).map(th=>th.textContent.trim());
 table.querySelectorAll('tbody tr').forEach(row=>{
  Array.from(row.children).forEach((cell,index)=>{
   if(cell.tagName==='TD' && headings[index])cell.setAttribute('data-crm-label',headings[index]);
  });
 });
});

const layouts={
 leads:[['Browse leads','Add lead']],
 pipeline:[['Opportunities','Add opportunity']],
 activities:[['Recent activity','Log activity']],
 segments:[['Customer segments','Segment membership','Follow-up templates','Schedule follow-up']],
 tasks:[['Staff workload','Task management','Campaign performance']],
 smart:[['Smart audiences','Segment rules','Campaign builder']]
};
if(!layouts[tab])return;
const iconPaths={
 'Browse leads':'<circle cx="10" cy="8" r="3"/><path d="M4 20v-2a6 6 0 0 1 12 0v2M18 8h4M18 12h4"/>',
 'Add lead':'<circle cx="9" cy="8" r="3"/><path d="M3 20v-2a6 6 0 0 1 12 0v2M19 8v8M15 12h8"/>',
 'Opportunities':'<circle cx="12" cy="12" r="9"/><circle cx="12" cy="12" r="5"/><circle cx="12" cy="12" r="1"/>',
 'Add opportunity':'<path d="M4 19V5M4 19h16M7 15l5-5 3 2 5-7M19 2v6M16 5h6"/>',
 'Recent activity':'<path d="M3 12h4l3-7 4 14 3-7h4"/>',
 'Log activity':'<path d="M5 4h11l3 3v13H5zM8 11h8M8 15h5M17 17h5M19.5 14.5v5"/>',
 'Customer segments':'<circle cx="8" cy="8" r="3"/><circle cx="17" cy="9" r="2"/><path d="M2 20v-2a6 6 0 0 1 12 0v2M15 15a4 4 0 0 1 7 3v2"/>',
 'Segment membership':'<path d="M3 5h18M3 12h12M3 19h18M17 11l2 2 4-5"/>',
 'Follow-up templates':'<path d="M6 3h9l4 4v14H6zM15 3v5h4M9 12h7M9 16h7"/>',
 'Schedule follow-up':'<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M7 3v4M17 3v4M3 10h18M12 13v5M9.5 15.5h5"/>',
 'Staff workload':'<rect x="3" y="7" width="18" height="14" rx="2"/><path d="M8 7V5a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2M3 12h18"/>',
 'Task management':'<path d="M4 7l2 2 4-4M13 7h8M4 16l2 2 4-4M13 16h8"/>',
 'Campaign performance':'<path d="M3 20V4M3 20h18M7 16v-4M12 16V8M17 16V5"/>',
 'Smart audiences':'<circle cx="8" cy="8" r="3"/><path d="M2 20v-2a6 6 0 0 1 12 0v2M17 3l1.5 3.5L22 8l-3.5 1.5L17 13l-1.5-3.5L12 8l3.5-1.5z"/>',
 'Segment rules':'<path d="M4 6h16M4 12h16M4 18h16"/><circle cx="9" cy="6" r="2"/><circle cx="15" cy="12" r="2"/><circle cx="11" cy="18" r="2"/>',
 'Campaign builder':'<path d="M3 10v4l12 4V6L3 10zM15 9l5-3v12l-5-3M6 17l2 4"/>'
};
function navIcon(label){
 const path=iconPaths[label]||'<circle cx="12" cy="12" r="8"/>';
 const span=document.createElement('span');
 span.className='crm-nav-symbol';
 span.setAttribute('aria-hidden','true');
 span.innerHTML='<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">'+path+'</svg>';
 return span;
}
const sections=[];
const pick=(parent,selector)=>Array.from(parent.querySelectorAll(selector));
if(['leads','pipeline','activities'].includes(tab)){
 const grid=root.querySelector('.crm-grid');
 if(!grid)return;
 const panels=Array.from(grid.children).filter(el=>el.classList.contains('crm-panel'));
 if(panels.length!==2)return;
 // Original markup puts the creation form first.
 sections.push(panels[1],panels[0]);
 grid.replaceWith(...sections);
}else if(tab==='segments'){
 const grids=Array.from(root.children).filter(el=>el.classList.contains('crm-grid'));
 if(grids.length!==2)return;
 const a=Array.from(grids[0].children),b=Array.from(grids[1].children);
 if(a.length!==2||b.length!==2)return;
 sections.push(a[0],a[1],b[0],b[1]);
 grids[0].replaceWith(a[0],a[1]);
 grids[1].replaceWith(b[0],b[1]);
}else if(tab==='tasks'){
 sections.push(...Array.from(root.children).filter(el=>el.classList.contains('crm-panel')));
 if(sections.length!==3)return;
}else if(tab==='smart'){
 const outer=Array.from(root.children).find(el=>el.classList.contains('crm-panel'));
 if(!outer)return;
 const grid=outer.querySelector('.crm-grid');
 if(!grid||grid.children.length!==2)return;
 const audience=document.createElement('section');
 audience.className='crm-panel';
 // Keep audience heading, selector, results and explanatory text together.
 for(const node of Array.from(outer.childNodes)){
   if(node===grid)break;
   audience.appendChild(node);
 }
 const [rules,campaign]=Array.from(grid.children);
 grid.replaceWith(audience,rules,campaign);
 sections.push(audience,rules,campaign);
 // Move workspace outside the former enclosing panel.
 outer.replaceWith(...sections);
}
if(sections.length!==layouts[tab][0].length)return;
const workspace=document.createElement('div');
workspace.className='crm-workspace';
const nav=document.createElement('div');
nav.className='crm-workspace-nav';
nav.setAttribute('role','tablist');
nav.setAttribute('aria-label','CRM '+tab+' functions');
const content=document.createElement('div');
content.className='crm-workspace-content';
const first=sections[0];
first.parentNode.insertBefore(workspace,first);
workspace.append(nav,content);
const names=layouts[tab][0];
const buttons=[];
sections.forEach((section,i)=>{
 const button=document.createElement('button');
 button.type='button';
 button.textContent=names[i];button.prepend(navIcon(names[i]));
 button.id='crm-workspace-tab-'+tab+'-'+i;
 button.setAttribute('role','tab');
 button.setAttribute('aria-controls','crm-workspace-panel-'+tab+'-'+i);
 button.addEventListener('click',()=>activate(i,true));
 button.addEventListener('keydown',event=>{
  if(!['ArrowDown','ArrowUp','ArrowRight','ArrowLeft','Home','End'].includes(event.key))return;
  event.preventDefault();
  let next=event.key==='Home'?0:event.key==='End'?buttons.length-1:
   (i+(['ArrowDown','ArrowRight'].includes(event.key)?1:-1)+buttons.length)%buttons.length;
  activate(next,true);buttons[next].focus();
 });
 buttons.push(button);
 nav.appendChild(button);
 section.id='crm-workspace-panel-'+tab+'-'+i;
 section.setAttribute('role','tabpanel');
 section.setAttribute('aria-labelledby',button.id);
 section.setAttribute('tabindex','0');
 content.appendChild(section);
});
function activate(index,updateUrl){
 sections.forEach((panel,i)=>{
  const active=i===index;
  panel.hidden=!active;
  buttons[i].setAttribute('aria-selected',String(active));
  buttons[i].tabIndex=active?0:-1;
 });
 if(updateUrl){
  const url=new URL(location.href);
  url.searchParams.set('view',String(index));
  history.replaceState(history.state,'',url);
 }
}
const requested=Number(new URLSearchParams(location.search).get('view'));
const initial=Number.isInteger(requested)&&requested>=0&&requested<sections.length?requested:0;
activate(initial,false);
})();
