/* Cycle 72A: shared production-polish progressive enhancements. */
(()=>{
'use strict';
function init(){
 const root=document.querySelector('.brite-app');if(!root)return;
 const main=document.getElementById('briteMainContent');
 const top=document.querySelector('.brite-topbar');
 if(main&&top){
  const heading=main.querySelector('h1');
  if(heading&&!heading.id)heading.id='britePageHeading';
  if(heading)main.setAttribute('aria-labelledby',heading.id);
 }
 // Avoid accidental form submissions from action buttons without an explicit type.
 // Existing submit buttons and buttons inside forms are intentionally untouched.
 root.querySelectorAll('button:not([type])').forEach(button=>{
  if(!button.closest('form')&&!button.hasAttribute('form'))button.type='button';
 });
 // Mark external destinations for screen-reader users without changing navigation behavior.
 root.querySelectorAll('a[target="_blank"]').forEach(link=>{
  const rel=new Set((link.getAttribute('rel')||'').split(/\s+/).filter(Boolean));
  rel.add('noopener');rel.add('noreferrer');link.rel=[...rel].join(' ');
  if(!link.querySelector('.brite-sr-only')){
   const hint=document.createElement('span');hint.className='brite-sr-only';hint.textContent=' (opens in a new tab)';link.append(hint);
  }
 });
 // Surface browser validation near the relevant control.
 root.addEventListener('invalid',event=>{
  const field=event.target;
  if(!(field instanceof HTMLElement))return;
  field.setAttribute('aria-invalid','true');
 },true);
 root.addEventListener('input',event=>{
  const field=event.target;
  if(field instanceof HTMLElement&&field.hasAttribute('aria-invalid')&&field.validity?.valid)field.removeAttribute('aria-invalid');
 });
 // Mobile tables remain scrollable without clipping page-wide content.
 root.querySelectorAll('.brite-content table').forEach(table=>{
  if(table.closest('.brite-table-scroll,[data-brite-table-scroll]'))return;
  const wrapper=document.createElement('div');wrapper.className='brite-table-scroll';wrapper.tabIndex=0;
  wrapper.setAttribute('role','region');wrapper.setAttribute('aria-label',table.getAttribute('aria-label')||'Scrollable data table');
  table.parentNode.insertBefore(wrapper,table);wrapper.append(table);
 });
}
if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',init,{once:true});else init();
})();
