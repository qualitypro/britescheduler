/* Brite Scheduler: progressive value pills for editable form controls.
 * Captures values on blur/change, without replacing form controls or changing submissions.
 */
(function () {
 'use strict';
 const selector='input, select, textarea';
 function eligible(el) {
  if (!el.matches(selector) || el.disabled || el.readOnly ||
      el.closest('[data-brite-pills="off"]') || el.dataset.britePills==='off') return false;
  const type=(el.type||'').toLowerCase();
  if (['hidden','password','file','checkbox','radio','submit','button','reset','image','search','color','range'].includes(type)) return false;
  if (el.matches('[autocomplete="one-time-code"],[autocomplete="cc-number"],[autocomplete="cc-csc"]')) return false;
  if (el.closest('[contenteditable="true"]')) return false;
  return !!(el.name || el.id || el.getAttribute('aria-label'));
 }
 function humanizeLabel(raw) {
  const cleaned=String(raw || 'Value')
   .replace(/([a-z0-9])([A-Z])/g,'$1 $2')
   .replace(/[_-]+/g,' ')
   .replace(/\s+/g,' ')
   .replace(/[:*\s]+$/g,'')
   .trim();
  return (cleaned || 'Value')
   .replace(/\b[a-z]/g,letter=>letter.toUpperCase())
   .replace(/\b(Id|Url|Zip|Api|Utc)\b/g,word=>({Id:'ID',Url:'URL',Zip:'ZIP',Api:'API',Utc:'UTC'})[word]);
 }
 function label(el) {
  // A label wrapping a <select> includes every option in textContent.
  // Read only the label's own text, excluding nested controls/options.
  const labelElement=el.labels && el.labels.length ? el.labels[0] : null;
  const text=labelElement ? [...labelElement.childNodes]
   .filter(node=>node.nodeType===Node.TEXT_NODE)
   .map(node=>node.textContent).join(' ').trim() : '';
  const named=labelElement?.querySelector('strong,.brite-field-label');
  const explicit=labelElement?.getAttribute('aria-label') || '';
  return humanizeLabel(el.dataset.britePillLabel || text ||
   (named ? named.textContent.trim() : '') || explicit ||
   el.getAttribute('aria-label') || el.name || el.id || 'Value');
 }
 function value(el) {
  if (el.tagName==='SELECT') return el.value && el.selectedOptions.length ? [...el.selectedOptions].filter(o=>o.value).map(o=>o.textContent.trim()).join(', ') : '';
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
  if (el._britePill && el._britePill.dataset.value===el.value && el._britePill.dataset.label===value(el) && el._britePill.dataset.fieldLabel===label(el)) return;
  if (el._britePill) el._britePill.remove();
  const pill=document.createElement('span');
  pill.className='brite-value-pill';
  const kind=el.tagName==='SELECT'?'choice':el.type==='number'?'number':el.type==='date'?'date':'text';
  pill.dataset.kind=kind;
  pill.dataset.value=el.value;
  pill.dataset.label=value(el);
  pill.dataset.fieldLabel=label(el);
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
 // Initialize existing values and fields added by client-side rendering.
 // Never collapse the active input while the user is typing.
 function initialize(root) {
  if (root instanceof Element && root.matches(selector) && root !== document.activeElement) capture(root);
  if (root.querySelectorAll) root.querySelectorAll(selector).forEach(el=>{
   if (el !== document.activeElement) capture(el);
  });
 }
 function start() {
  initialize(document);
  const observer=new MutationObserver(records=>{
   for (const record of records) for (const node of record.addedNodes) {
    if (node instanceof Element && !node.closest('.brite-value-pill')) initialize(node);
   }
  });
  observer.observe(document.body,{childList:true,subtree:true});
 }
 if (document.readyState==='loading') document.addEventListener('DOMContentLoaded',start,{once:true});
 else start();
 window.briteRefreshValuePills=initialize;
 document.addEventListener('input',event=>{
  const el=event.target;
  if (el instanceof HTMLElement && el._britePill && el.classList.contains('brite-pill-input-hidden')) capture(el);
 },true);
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
