/* Progressive enhancement: without JavaScript, all original CRM forms remain visible. */
(()=>{'use strict';
const root=document.querySelector('.crm-shell');
if(!root)return;
const tab=new URLSearchParams(location.search).get('tab')||'overview';
const layouts={
 leads:[['Browse leads','Add lead']],
 pipeline:[['Opportunities','Add opportunity']],
 activities:[['Recent activity','Log activity']],
 segments:[['Customer segments','Segment membership','Follow-up templates','Schedule follow-up']],
 tasks:[['Staff workload','Task management','Campaign performance']],
 smart:[['Smart audiences','Segment rules','Campaign builder']]
};
if(!layouts[tab])return;
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
 button.textContent=names[i];
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
