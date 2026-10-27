/* Cycle 59: single-active, read-first record interaction.
   Progressive enhancement for Marketplace and management directories. */
(() => {
'use strict';
const selectors='.brite-marketplace-record, .brite-directory-record';
function initialize(record) {
 if(record.dataset.singleActiveReady==='1')return;
 record.dataset.singleActiveReady='1';
 const form=record.querySelector(':scope > .brite-directory-form, :scope > .brite-directory-form form');
 if(!form)return;
 const actualForm=form.tagName==='FORM'?form:form.querySelector('form');
 if(!actualForm)return;
 const sheet=actualForm.closest('.brite-directory-form')||actualForm;
 const actions=actualForm.querySelector('.brite-directory-actions');
 if(!actions)return;
 const fields=[...actualForm.querySelectorAll('input,textarea,select')];
 const button=document.createElement('button');
 button.type='button';button.textContent='Edit record';
 button.className='brite-record-edit-toggle';
 const cancel=document.createElement('button');
 cancel.type='button';cancel.textContent='Cancel editing';
 cancel.className='brite-record-cancel';
 const summary=document.createElement('dl');
 summary.className='brite-record-readonly';
 const render=()=>{
  summary.replaceChildren();
  fields.forEach(field=>{
   const row=document.createElement('div');row.className='brite-record-readonly-field';
   const term=document.createElement('dt');
   term.textContent=field.closest('.brite-directory-field')?.querySelector('label')?.textContent?.trim()||field.name;
   const value=document.createElement('dd');
   value.textContent=field.tagName==='SELECT'?field.selectedOptions[0]?.textContent||'—':field.value||'—';
   row.append(term,value);summary.append(row);
  });
 };
 let editing=false;
 const setEditing=on=>{
  editing=on;
  actualForm.hidden=!on;
  summary.hidden=on;
  button.hidden=on;
  cancel.hidden=!on;
  if(on)fields[0]?.focus();
 };
 sheet.insertBefore(summary,actualForm);
 sheet.insertBefore(button,actualForm);
 sheet.insertBefore(cancel,actualForm);
 button.addEventListener('click',()=>setEditing(true));
 cancel.addEventListener('click',()=>{
  actualForm.reset();render();setEditing(false);
 });
 actualForm.addEventListener('reset',()=>queueMicrotask(render));
 actualForm.addEventListener('submit',()=>{
  // Only exit editing after the existing handler reports a successful save.
  const feedback=actualForm.querySelector('[role="status"],.brite-directory-feedback');
  if(!feedback)return;
  const observer=new MutationObserver(()=>{
   if(/saved|updated successfully/i.test(feedback.textContent||'')){
    observer.disconnect();render();setEditing(false);
   }else if(/failed|error/i.test(feedback.textContent||''))observer.disconnect();
  });
  observer.observe(feedback,{childList:true,subtree:true,characterData:true});
 });
 render();setEditing(false);
}
function wire(){
 document.querySelectorAll(selectors).forEach(initialize);
}
document.addEventListener('toggle',event=>{
 const record=event.target;
 if(!(record instanceof HTMLDetailsElement)||!record.matches(selectors)||!record.open)return;
 const parent=record.parentElement;
 if(!parent)return;
 for(const sibling of parent.children){
  if(sibling!==record&&sibling instanceof HTMLDetailsElement&&sibling.matches(selectors)&&sibling.open)sibling.open=false;
  // Contractor and client directories wrap details differently; only siblings in the same list.
 }
},true);
const observer=new MutationObserver(wire);
document.addEventListener('DOMContentLoaded',()=>{
 wire();
 observer.observe(document.body,{childList:true,subtree:true});
});
})();
