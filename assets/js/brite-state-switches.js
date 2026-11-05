/* Cycle 77: progressive enhancement of persistent boolean settings.
 * Explicit data-brite-switch wins; selection checkboxes stay unchanged.
 * No click interception, replacement DOM, or change-event synthesis.
 */
(function(){
 'use strict';
 const statePattern=/(?:^|[_\\s-])(enabled|enable|active|activate|activation|visible|visibility|notify|notifications|notification|reduced.motion|high.contrast|remember|auto.save|public|availability|available|allow|allowed|permission|preferences|setting)(?:$|[_\\s-])/i;
 const excludePattern=/(?:^|[_\\s-])(select.all|bulk|selected|select.row|choose|picked|confirm|agree|consent|terms|delete|remove)(?:$|[_\\s-])/i;
 function decorate(input){
  if(!(input instanceof HTMLInputElement)||input.type!=='checkbox')return;
  if(input.dataset.briteSwitch==='off'||input.closest('[data-brite-switches="off"]'))return;
  const label=input.labels&&input.labels.length?input.labels[0]:null;
  const name=[input.name,input.id,input.getAttribute('aria-label'),label?.textContent?.trim().slice(0,90)].filter(Boolean).join(' ');
  if(input.dataset.briteSwitch==='on'||(!excludePattern.test(name)&&statePattern.test(name)))input.classList.add('brite-state-switch');
 }
 function scan(root){
  if(root instanceof HTMLInputElement)decorate(root);
  if(root.querySelectorAll)root.querySelectorAll('input[type="checkbox"]').forEach(decorate);
 }
 function start(){
  scan(document);
  const observer=new MutationObserver(records=>{
   for(const record of records)for(const node of record.addedNodes){
    if(node instanceof Element)scan(node);
   }
  });
  observer.observe(document.body,{childList:true,subtree:true});
 }
 if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',start,{once:true});
 else start();
 window.briteRefreshStateSwitches=scan;
})();
