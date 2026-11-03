(() => {
 'use strict';
 const form=document.getElementById('quoteWizard');if(!form)return;
 const base=window.BRITE_BASE||'';
 const status=document.getElementById('quoteStatus'),submit=document.getElementById('quoteSubmit');
 const contractor=form.elements.namedItem('contractor_id'),service=form.elements.namedItem('service_id'),location=form.elements.namedItem('client_location_id');
 const el=(tag,text)=>{const n=document.createElement(tag);n.textContent=String(text);return n;};
 let contractors=[];
 async function json(path,options={}){
  const r=await fetch(base+path,{credentials:'same-origin',headers:{Accept:'application/json'},...options});
  const d=await r.json();if(!r.ok)throw Error(d.error||'Request failed');return d;
 }
 function updateServices(){
  const selected=contractors.find(c=>String(c.contractor_id)===contractor.value);
  service.replaceChildren(new Option('Choose a service (optional)',''));
  (selected?.services||[]).forEach(s=>service.add(new Option(s.name,s.id)));
 }
 async function history(){
  const target=document.getElementById('quoteHistory');
  try {
   const data=await json('/api/client_quote_requests.php');target.replaceChildren();
   if(!data.requests.length){target.append(el('p','No quote requests yet.'));return;}
   data.requests.forEach(r=>{const p=el('p','#'+r.id+' — '+r.contractor_name+' — '+r.status+' — '+r.created_at);target.append(p);});
  }catch(e){target.textContent=e.message;}
 }
 async function init(){
  try{
   const [directory,locations]=await Promise.all([json('/api/client_contractor_directory.php'),json('/api/client_locations.php')]);
   contractors=directory.contractors||[];
   contractors.forEach(c=>contractor.add(new Option(c.business_name||[c.first_name,c.last_name].join(' '),c.contractor_id)));
   (locations.locations||[]).forEach(l=>location.add(new Option(l.label+' — '+l.address1+', '+l.city,l.id)));
   const params=new URLSearchParams(locationSearch());
   const pre=params.get('contractor_id');
   if(pre)contractor.value=pre;
   updateServices();
   const date=form.elements.namedItem('preferred_date');date.min=new Date().toLocaleDateString('en-CA');
   status.textContent='Ready to submit.';
  }catch(e){status.textContent=e.message;submit.disabled=true;}
  history();
 }
 function locationSearch(){return window.location.search;}
 contractor.addEventListener('change',updateServices);
 form.addEventListener('submit',async e=>{
  e.preventDefault();
  const photos=document.getElementById('quotePhotos').files;
  if(photos.length<2||photos.length>10){status.textContent='Please select 2–10 photographs.';return;}
  for(const p of photos){if(p.size>5*1024*1024||!/^image\/(jpeg|png)$/.test(p.type)){status.textContent='Photos must be JPG or PNG, up to 5 MB each.';return;}}
  submit.disabled=true;status.textContent='Submitting request...';
  try{
   const data=new FormData(form);
   const r=await fetch(base+'/api/client_quote_requests.php',{method:'POST',credentials:'same-origin',headers:{'X-CSRF-Token':window.BRITE_CSRF,Accept:'application/json'},body:data});
   const result=await r.json();if(!r.ok)throw Error(result.error||'Submission failed');
   status.textContent='Quote request #'+result.request_id+' submitted successfully.';
   form.reset();updateServices();history();
  }catch(err){status.textContent=err.message;}
  finally{submit.disabled=false;}
 });
 init();
})();
