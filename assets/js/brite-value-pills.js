/* Brite Scheduler: progressive value pills for editable form controls.
 * Captures values on blur/change, without replacing form controls or changing submissions.
 */
(function () {
 'use strict';
 const selector='input, select, textarea';
 function eligible(el) {
  if (!el.matches(selector) || !el.form || el.disabled || el.readOnly ||
      el.closest('[data-brite-pills="off"]') || el.dataset.britePills==='off') return false;
  const type=(el.type||'').toLowerCase();
  if (['hidden','password','file','checkbox','radio','submit','button','reset','image','search','color','range'].includes(type)) return false;
  if (el.matches('[autocomplete="one-time-code"],[autocomplete="cc-number"],[autocomplete="cc-csc"]')) return false;
  return !!(el.name || el.id);
 }
 function label(el) {
  const text=el.labels && el.labels.length ? el.labels[0].textContent.trim() : '';
  return (text || el.getAttribute('aria-label') || el.name || el.id || 'Value').replace(/[:*]+$/,'').trim();
 }
 function value(el) {
  if (el.tagName==='SELECT') return el.selectedOptions.length ? [...el.selectedOptions].map(o=>o.textContent.trim()).join(', ') : '';
  return el.value.trim();
 }
 function clear(el) {
  if (!el._britePill) return;
  el.classList.remove('brite-pill-input-hidden');
  el._britePill.remove();
  el._britePill=null;
 }
 function capture(el) {
  if (!eligible(el) || !el.isConnected || !value(el)) { clear(el); return; }
  if (el._britePill) el._britePill.remove();
  const pill=document.createElement('span');
  pill.className='brite-value-pill';
  const kind=el.tagName==='SELECT'?'choice':el.type==='number'?'number':el.type==='date'?'date':'text';
  pill.dataset.kind=kind;
  const text=document.createElement('span');
  text.className='brite-value-pill-label';
  text.textContent=label(el)+': '+value(el);
  const close=document.createElement('button');
  close.type='button';
  close.className='brite-value-pill-close';
  close.textContent='×';
  close.setAttribute('aria-label','Edit '+label(el));
  close.addEventListener('click',()=>{
   clear(el);
   el.focus();
  });
  pill.append(text,close);
  el.insertAdjacentElement('afterend',pill);
  el._britePill=pill;
  el.classList.add('brite-pill-input-hidden');
 }
 document.addEventListener('focusout',event=>{
  const el=event.target;
  if (el instanceof HTMLElement && eligible(el)) capture(el);
 },true);
 document.addEventListener('change',event=>{
  const el=event.target;
  if (el instanceof HTMLElement && eligible(el) && (el.tagName==='SELECT' || ['date','datetime-local','time'].includes(el.type))) capture(el);
 },true);
 document.addEventListener('reset',event=>{
  if (!(event.target instanceof HTMLFormElement)) return;
  event.target.querySelectorAll(selector).forEach(clear);
 },true);
})();
