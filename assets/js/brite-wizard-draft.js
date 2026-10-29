/* Cycle 68C: per-tab unsaved appointment draft; never submits automatically. */
(() => {
'use strict';
const KEY='brite:appointment-draft:v1:'+location.pathname;
const ids=['clientId','clientLocationId','appointmentLocation','serviceId','appointmentTitle','appointmentDescription','startsAt','endsAt','appointmentStatus','briteAppointmentDate'];
const read=()=>{try{return JSON.parse(sessionStorage.getItem(KEY)||'null');}catch(_){return null;}};
const clear=()=>{try{sessionStorage.removeItem(KEY);}catch(_){}};
let restoring=false;
function save(){
 if(restoring)return;
 const form=document.getElementById('appointmentForm');
 if(!form||!document.getElementById('appointmentDialog')?.open||document.getElementById('appointmentId')?.value)return;
 const fields={};
 for(const id of ids){const el=document.getElementById(id);if(el)fields[id]=el.value;}
 const select=document.getElementById('contractorId');
 fields.contractorIds=select?Array.from(select.selectedOptions).map(x=>x.value):[];
 if(!fields.clientId&&!fields.serviceId&&!fields.appointmentTitle&&!fields.appointmentLocation&&!fields.startsAt&&!fields.contractorIds.length)return;
 try{sessionStorage.setItem(KEY,JSON.stringify({fields,createdAt:Date.now()}));}catch(_){}
}
function applyField(id,value){
 const el=document.getElementById(id);
 if(!el||value===undefined||value===null)return;
 if(el.tagName==='SELECT'&&value&&!Array.from(el.options).some(o=>o.value===String(value)))return;
 el.value=String(value);
 el.dispatchEvent(new Event('change',{bubbles:true}));
}
async function restore(draft){
 const fields=draft.fields||{};
 restoring=true;
 try{
  applyField('clientId',fields.clientId);
  await new Promise(resolve=>setTimeout(resolve,300));
  for(const id of ids.filter(id=>id!=='clientId'&&id!=='clientLocationId'))applyField(id,fields[id]);
  if(fields.clientLocationId){
   const location=document.getElementById('clientLocationId');
   if(location){
    await new Promise(resolve=>{
     if(Array.from(location.options).some(o=>o.value===String(fields.clientLocationId))){resolve();return;}
     const observer=new MutationObserver(()=>{
      if(Array.from(location.options).some(o=>o.value===String(fields.clientLocationId))){observer.disconnect();resolve();}
     });
     observer.observe(location,{childList:true});
     setTimeout(()=>{observer.disconnect();resolve();},1800);
    });
    applyField('clientLocationId',fields.clientLocationId);
  }
  }
  const contractors=document.getElementById('contractorId');
  if(contractors){
   const chosen=new Set(fields.contractorIds||[]);
   for(const option of contractors.options)option.selected=chosen.has(option.value);
   contractors.dispatchEvent(new Event('change',{bubbles:true}));
  }
  if(typeof window.briteSyncDateTimePicker==='function')window.briteSyncDateTimePicker();
 }finally{restoring=false;}
}
window.briteCycle68c={
 clear,
 save,
 async promptRestore(){
  const draft=read();
  if(!draft||!draft.fields)return;
  if(!window.confirm('Resume your unsaved appointment draft? Choose Cancel to discard it.')){clear();return;}
  await restore(draft);
 }
};
document.addEventListener('DOMContentLoaded',()=>{
 const form=document.getElementById('appointmentForm');
 if(!form)return;
 form.addEventListener('input',save);
 form.addEventListener('change',save);
});
})();
