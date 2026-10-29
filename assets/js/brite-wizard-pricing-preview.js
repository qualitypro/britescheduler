/* Cycle 68: advisory contractor pricing preview in existing six-step wizard.
   Never changes appointment payload, bid, invoice or assignment. */
(() => {
'use strict';
document.addEventListener('DOMContentLoaded', () => {
 const form=document.getElementById('appointmentForm');
 const preview=document.getElementById('cycle68PricingPreview');
 const service=document.getElementById('serviceId');
 const contractors=document.getElementById('contractorId');
 if(!form||!preview||!service||!contractors||typeof api!=='function')return;
 let generation=0;
 const money=value=>{
  const n=Number(value);
  return Number.isFinite(n)?new Intl.NumberFormat(undefined,{style:'currency',currency:'USD'}).format(n):'Not set';
 };
 const selected=()=>Array.from(contractors.selectedOptions).filter(o=>o.value).map(o=>({id:o.value,name:o.textContent.trim()}));
 async function update(){
  const token=++generation;
  const serviceId=Number(service.value);
  const selectedContractors=selected();
  preview.replaceChildren();
  const heading=document.createElement('h4');heading.textContent='Contractor pricing preview (advisory)';
  preview.append(heading);
  if(serviceId){
   try {
    const catalog=await api('/api/services.php');
    if(token!==generation)return;
    const item=(catalog.services||[]).find(x=>Number(x.id)===serviceId);
    if(item){const p=document.createElement('p');p.textContent='Tenant base service price: '+money(item.price);preview.append(p);}
   }catch(error){const p=document.createElement('p');p.textContent='Base service price unavailable';preview.append(p);}
  }
  if(!serviceId||!selectedContractors.length){
   const p=document.createElement('p');p.textContent='Select a service and contractor to compare configured rates.';preview.append(p);return;
  }
  const note=document.createElement('p');
  note.textContent='Rates are estimates from contractor profiles, not quotes or appointment charges. Hourly rates are not multiplied by duration.';
  preview.append(note);
  const list=document.createElement('ul');preview.append(list);
  const results=await Promise.all(selectedContractors.map(async c=>{
   try{
    const data=await api('/api/contractor_service_offerings.php?contractor_id='+encodeURIComponent(c.id));
    const offering=(data.offerings||[]).find(x=>Number(x.service_id)===serviceId);
    if(!offering||Number(offering.offered)!==1)return {name:c.name,message:'No active offering configured'};
    return {name:c.name,message:offering.rate===null?'Rate not set':money(offering.rate)+' / '+(offering.pricing_model==='hourly'?'hour':'service')};
   }catch(error){return {name:c.name,message:'Pricing unavailable: '+error.message};}
  }));
  if(token!==generation)return;
  for(const item of results){
   const li=document.createElement('li');
   li.textContent=item.name+': '+item.message;
   list.append(li);
  }
 }
 let scheduled=false;
 const schedule=()=>{
  if(scheduled)return;
  scheduled=true;
  queueMicrotask(()=>{scheduled=false;update();});
 };
 service.addEventListener('change',schedule);
 contractors.addEventListener('change',schedule);
 form.addEventListener('change',schedule);
 const observer=new MutationObserver(()=>{
  const review=form.querySelector('[data-wizard-step="6"]');
  if(review&&!review.hidden)schedule();
 });
 observer.observe(form,{attributes:true,attributeFilter:['hidden'],subtree:true});
 schedule();
});
})();
