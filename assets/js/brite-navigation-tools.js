/* Cycle 72B — discoverability and navigation features; no API or data mutations. */
(()=>{
'use strict';
const app=document.querySelector('.brite-app');if(!app)return;
const make=(tag,cls,text)=>{const e=document.createElement(tag);if(cls)e.className=cls;if(text!==undefined)e.textContent=text;return e;};
const sidebarNav=document.querySelector('#briteSidebar .brite-sidebar-nav');
const main=document.getElementById('briteMainContent');
if(!sidebarNav||!main)return;
// Give visible page sections stable in-page targets without changing their content.
const existingIds=new Set([...document.querySelectorAll('[id]')].map(n=>n.id));
main.querySelectorAll('h2,h3').forEach((heading,i)=>{
 if(heading.id||!heading.textContent.trim())return;
 const baseId='brite-section-'+heading.textContent.trim().toLowerCase().replace(/[^a-z0-9]+/g,'-').replace(/^-|-$/g,'').slice(0,48);
 let id=baseId||'brite-section-'+i;let suffix=2;
 while(existingIds.has(id))id=(baseId||'brite-section-'+i)+'-'+suffix++;
 heading.id=id;existingIds.add(id);
});
const trigger=make('button','brite-command-trigger','Search pages');trigger.type='button';trigger.setAttribute('aria-keyshortcuts','Control+K Meta+K');trigger.setAttribute('aria-haspopup','dialog');
trigger.title='Find a page or jump to a section (Ctrl/⌘ + K)';
// Keep page search permanently accessible above the accordion sections.
const searchIcon=make('span','brite-nav-icon');
searchIcon.setAttribute('aria-hidden','true');
searchIcon.innerHTML='<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"/><path d="m20 20-4-4"/></svg>';
trigger.prepend(searchIcon);
trigger.setAttribute('aria-label','Search pages and sections');
// The accordion groups all siblings following its final heading. Insert only
// after accordion initialization, so Search Pages stays a standalone main-nav
// control rather than being captured inside the Personal accordion panel.
function placeSearchInMainNav() {
    sidebarNav.append(trigger);
}
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', placeSearchInMainNav);
} else {
    placeSearchInMainNav();
}
const dialog=make('dialog','brite-command-dialog');dialog.setAttribute('aria-label','Find pages and sections');
const frame=make('div','brite-command-frame');
const header=make('div','brite-command-header');
const title=make('strong','Go to…');
const close=make('button','','Close');close.type='button';close.setAttribute('aria-label','Close page search');
header.append(title,close);
const input=make('input','brite-command-input');input.type='search';input.placeholder='Search pages or sections…';input.autocomplete='off';input.setAttribute('aria-label','Search pages and sections');
const hint=make('p','brite-command-hint','Type to filter • ↑ ↓ to move • Enter to open • Esc to close');
const list=make('div','brite-command-results');list.setAttribute('role','listbox');list.setAttribute('aria-label','Navigation matches');
const status=make('p','brite-command-status');status.setAttribute('role','status');status.setAttribute('aria-live','polite');
frame.append(header,input,hint,list,status);dialog.append(frame);document.body.append(dialog);
let options=[],active=0,focusReturn=null;
function navigation(){
 const links=[...document.querySelectorAll('.brite-sidebar-nav a[href],.brite-quick-actions a[href]')];
 const seen=new Set();const pages=[];
 for(const link of links){
  let url;try{url=new URL(link.href,location.href);}catch(_){continue;}
  if(url.origin!==location.origin||seen.has(url.pathname+url.search))continue;
  seen.add(url.pathname+url.search);
  const label=link.textContent.replace(/\s+/g,' ').trim();
  if(label)pages.push({label,detail:'Page',url:url.href});
 }
 const sections=[];
 for(const h of main.querySelectorAll('h2[id],h3[id],section[id] > h2,section[id] > h3')){
  const label=h.textContent.replace(/\s+/g,' ').trim();
  if(!label||label.length>110)continue;
  const target=h.id?h: h.parentElement;
  if(!target.id)continue;
  sections.push({label,detail:'On this page',target});
 }
 return [...pages,...sections];
}
function render(){
 const query=input.value.trim().toLocaleLowerCase();
 options=navigation().filter(o=>!query||(o.label+' '+o.detail).toLocaleLowerCase().includes(query)).slice(0,35);
 active=0;list.replaceChildren();
 options.forEach((item,i)=>{
  const button=make('button','brite-command-option');button.type='button';button.setAttribute('role','option');button.id='brite-command-option-'+i;
  const label=make('span','',item.label),detail=make('small','',item.detail);button.append(label,detail);
  button.addEventListener('click',()=>choose(i));list.append(button);
 });
 updateActive();
 status.textContent=options.length?options.length+' result'+(options.length===1?'':'s'):'No matching pages or sections.';
}
function updateActive(){
 [...list.children].forEach((el,i)=>{el.setAttribute('aria-selected',String(i===active));});
 if(options.length){input.setAttribute('aria-activedescendant','brite-command-option-'+active);}
 else input.removeAttribute('aria-activedescendant');
}
function choose(i){
 const item=options[i];if(!item)return;
 dialog.close();
 if(item.target){
  item.target.scrollIntoView({block:'start',behavior:app.dataset.briteReducedMotion==='1'?'instant':'smooth'});
  if(!item.target.hasAttribute('tabindex'))item.target.setAttribute('tabindex','-1');
  item.target.focus({preventScroll:true});
 }else if(item.url)location.assign(item.url);
}
function open(){
 if(dialog.open)return;
 focusReturn=document.activeElement;input.value='';render();dialog.showModal();input.focus();
}
trigger.addEventListener('click',open);close.addEventListener('click',()=>dialog.close());
dialog.addEventListener('click',e=>{if(e.target===dialog)dialog.close();});
dialog.addEventListener('close',()=>{if(focusReturn?.isConnected)focusReturn.focus();});
input.addEventListener('input',render);
input.addEventListener('keydown',e=>{
 if(e.key==='ArrowDown'&&options.length){e.preventDefault();active=(active+1)%options.length;updateActive();}
 else if(e.key==='ArrowUp'&&options.length){e.preventDefault();active=(active+options.length-1)%options.length;updateActive();}
 else if(e.key==='Enter'&&options.length){e.preventDefault();choose(active);}
});
document.addEventListener('keydown',e=>{
 if((e.ctrlKey||e.metaKey)&&e.key.toLowerCase()==='k'&&!e.altKey){e.preventDefault();open();}
});
const tools=make('div','brite-page-tools');
const top=make('button','','Back to top ↑');top.type='button';top.addEventListener('click',()=>{window.scrollTo({top:0,behavior:app.dataset.briteReducedMotion==='1'?'instant':'smooth'});});
const copy=make('button','','Copy page link');copy.type='button';copy.addEventListener('click',async()=>{
 try{
  if(!navigator.clipboard?.writeText)throw Error('Clipboard unavailable');
  await navigator.clipboard.writeText(location.href);
  copy.textContent='Link copied';setTimeout(()=>{copy.textContent='Copy page link';},1800);
 }catch(_){copy.textContent='Copy unavailable';setTimeout(()=>{copy.textContent='Copy page link';},1800);}
});
// Scheduling calendar pages do not offer the redundant page-link action.
const isSchedulingCalendar = /(?:^|\/)scheduling\.php$/i.test(window.location.pathname);
if(isSchedulingCalendar){tools.append(top);}else{tools.append(top,copy);}
main.append(tools);
const updateTop=()=>{top.hidden=window.scrollY<300;};
window.addEventListener('scroll',updateTop,{passive:true});updateTop();
})();
