(() => {
 'use strict';
 const form=document.getElementById('quoteWizard');if(!form)return;
 const base=window.BRITE_BASE||'';
 window.addEventListener('error',event=>{if(event.filename&&event.filename.includes('client-quote-wizard.js')){const message=document.getElementById('quoteStatus');if(message)message.textContent='Quote wizard error: '+event.message;}});
 const status=document.getElementById('quoteStatus');
 const submit=document.getElementById('quoteSubmit');
 const contractor=document.getElementById('quoteContractor');
 const service=document.getElementById('quoteService');
 const locationSelect=document.getElementById('quoteLocation');
 const input=document.getElementById('quotePhotos');
 const gallery=document.getElementById('quotePhotoPreview');
 const count=document.getElementById('quotePhotoCount');
 const review=document.getElementById('quoteReview');
 const reviewDetails=document.getElementById('quoteReviewDetails');
 const confirm=document.getElementById('quoteConfirm');
 const back=document.getElementById('quoteBack');
 let contractors=[],photos=[],busy=false;
 const labels={submitted:'Awaiting contractor response',more_info_requested:'Contractor needs more information',quoted:'Quote received',accepted:'Accepted',declined:'Declined',cancelled:'Cancelled'};
 const el=(tag,text)=>{const n=document.createElement(tag);if(text!==undefined)n.textContent=String(text);return n;};
 async function json(path){
  const response=await fetch(base+path,{credentials:'same-origin',headers:{Accept:'application/json'}});
  let data;try{data=await response.json();}catch(_){throw Error('Server returned HTTP '+response.status+' instead of JSON');}
  if(!response.ok)throw Error(data.error||'HTTP '+response.status);
  return data;
 }
 function updateServices(){
  const selected=contractors.find(c=>String(c.contractor_id)===contractor.value);
  service.replaceChildren(new Option('Choose a service (optional)',''));
  (selected?.services||[]).forEach(s=>service.add(new Option(s.name,s.id)));
 }
 function renderPhotos(){
  gallery.replaceChildren();
  count.textContent=photos.length+' of 10 photographs selected (minimum 2).';
  photos.forEach((file,index)=>{
   const card=el('div');card.style.cssText='border:1px solid #cbd5e1;border-radius:10px;padding:8px;min-width:0';
   const img=document.createElement('img');const url=URL.createObjectURL(file);
   img.src=url;img.alt='Selected job photo '+(index+1);
   img.style.cssText='width:100%;height:115px;object-fit:cover;border-radius:6px';
   img.addEventListener('load',()=>URL.revokeObjectURL(url),{once:true});
   img.addEventListener('error',()=>URL.revokeObjectURL(url),{once:true});
   const label=el('p',file.name);label.style.cssText='font-size:.8rem;overflow-wrap:anywhere';
   const remove=el('button','Remove');remove.type='button';
   remove.addEventListener('click',()=>{if(busy)return;photos.splice(index,1);renderPhotos();review.hidden=true;review.style.display='none';});
   card.append(img,label,remove);gallery.append(card);
  });
 }
 input.addEventListener('change',()=>{
  const chosen=Array.from(input.files||[]);
  for(const file of chosen){
   if(photos.length>=10){status.textContent='Maximum 10 photographs. Remove a photo before adding more.';break;}
   if(file.size<1||file.size>5*1024*1024||!(/\.(jpe?g|png)$/i.test(file.name))||!['image/jpeg','image/png'].includes(file.type)){
    status.textContent='Only JPG/PNG photos up to 5 MB each are supported: '+file.name;
    continue;
   }
   photos.push(file);
  }
  input.value='';
  review.hidden=true;renderPhotos();
 });
 async function history(){
  const target=document.getElementById('quoteHistory');
  try{
   const data=await json('/api/client_quote_requests.php');
   target.replaceChildren();
   if(!data.requests.length){target.append(el('p','No requests yet. Complete the form above to send your first quote request.'));return;}
   data.requests.forEach(r=>{
    const card=el('article');card.style.cssText='border-top:1px solid #cbd5e1;padding:12px 0';
    const heading=el('h3','Request #'+r.id+' · '+r.contractor_name);card.append(heading);
    const state=el('p',labels[r.status]||r.status);state.style.cssText='display:inline-block;background:#e2e8f0;border-radius:12px;padding:6px 10px;font-weight:600';card.append(state);
    const hint=el('p',r.status==='more_info_requested'?'Your contractor has asked for additional information. Open the conversation below to see what they need.':r.status==='submitted'?'Your request has been sent. You can open the conversation to check messages or ask a question.':'Open the conversation to review updates.');hint.className='muted';card.append(hint);
    card.append(el('p',r.description));
    const images=el('div');images.style.cssText='display:flex;flex-wrap:wrap;gap:10px';
    (r.photos||[]).forEach(p=>{
      const a=document.createElement('a');a.href=p.url;a.target='_blank';a.rel='noopener noreferrer';
      const img=document.createElement('img');img.src=p.url;img.loading='lazy';img.alt='Job photo for request '+r.id;
      img.style.cssText='width:110px;height:90px;object-fit:cover;border-radius:8px';
      a.append(img);images.append(a);
    });
    if((r.photos||[]).length)card.append(el('h4','Your job photographs'));
    card.append(images);window.BriteQuoteConversation.attach(card,r.id,'client');target.append(card);
   });
  }catch(error){target.textContent=error.message;}
 }
 async function init(){
  try{
   const [directory,locations]=await Promise.all([json('/api/client_contractor_directory.php'),json('/api/client_locations.php')]);
   contractors=directory.contractors||[];
   contractors.forEach(c=>contractor.add(new Option(c.business_name||[c.first_name,c.last_name].filter(Boolean).join(' '),c.contractor_id)));
   (locations.locations||[]).forEach(l=>locationSelect.add(new Option(l.label+' — '+l.address1+', '+l.city,l.id)));
   const pre=new URLSearchParams(window.location.search).get('contractor_id');
   if(pre){
    contractor.value=pre;
    if(!contractor.value){status.textContent='This contractor is not currently available. Please select a contractor from the directory.';submit.disabled=true;return;}
    contractor.disabled=true;
    const hidden=document.createElement('input');hidden.type='hidden';hidden.name='contractor_id';hidden.value=contractor.value;form.append(hidden);
   }
   updateServices();
   const today=new Date();const localToday=new Date(today.getTime()-today.getTimezoneOffset()*60000).toISOString().slice(0,10);
   document.getElementById('quoteDate').min=localToday;
   if(!contractors.length)status.textContent='No active contractors are available in your network.';
   else if(locationSelect.options.length<2)status.textContent='Add a service location before requesting a quote.';
   else status.textContent='Choose 2–10 photographs and review your request.';
  }catch(error){status.textContent='Unable to load form options: '+error.message;submit.disabled=true;}
  renderPhotos();history();
 }
 contractor.addEventListener('change',()=>{updateServices();review.hidden=true;});
 form.addEventListener('input',()=>{review.hidden=true;});
 function validateRequest(){
  const fields=[
   [contractor,'Select a contractor.'],
   [locationSelect,'Select a service location.'],
   [document.getElementById('quoteDescription'),'Enter a job description of at least 20 characters.'],
   [document.getElementById('quoteDate'),'Select a valid preferred date, or leave it blank.']
  ];
  for(const [field,message] of fields){
   if(!field.checkValidity()){
    status.textContent=message;
    field.focus();
    return false;
   }
  }
  if(photos.length<2||photos.length>10){
   status.textContent='Add at least 2 photographs (maximum 10) before reviewing.';
   input.focus();return false;
  }
  return true;
 }
 function showReview(){
  if(busy)return;
  if(!validateRequest())return;
  reviewDetails.replaceChildren();
  for(const [label,value] of [
   ['Contractor',contractor.selectedOptions[0]?.textContent],
   ['Service',service.selectedOptions[0]?.textContent],
   ['Location',locationSelect.selectedOptions[0]?.textContent],
   ['Preferred date',document.getElementById('quoteDate').value||'Flexible'],
   ['Description',document.getElementById('quoteDescription').value],
   ['Photographs',photos.length+' attached']
  ])reviewDetails.append(el('p',label+': '+(value||'Not selected')));
  review.hidden=false;
  review.style.display='block';
  review.scrollIntoView({behavior:'smooth',block:'nearest'});
  status.textContent='Review your request, then click Confirm and submit.';
 }
 submit.addEventListener('click',showReview);
 form.addEventListener('submit',event=>{event.preventDefault();showReview();});
 back.addEventListener('click',()=>{review.hidden=true;submit.focus();});
 confirm.addEventListener('click',async()=>{
  if(busy)return;
  if(!form.reportValidity()||photos.length<2||photos.length>10){review.hidden=true;status.textContent='Please correct the request before submitting.';return;}
  busy=true;confirm.disabled=true;submit.disabled=true;status.textContent='Uploading photographs and submitting request...';
  try{
   const data=new FormData(form);
   photos.forEach(file=>data.append('photos[]',file,file.name));
   const response=await fetch(base+'/api/client_quote_requests.php',{
    method:'POST',credentials:'same-origin',
    headers:{'X-CSRF-Token':window.BRITE_CSRF,Accept:'application/json'},
    body:data
   });
   let result;try{result=await response.json();}catch(_){throw Error('Server returned HTTP '+response.status+' instead of JSON');}
   if(!response.ok)throw Error((result.error||'Submission failed')+' (HTTP '+response.status+')');
   status.textContent='Quote request #'+result.request_id+' sent successfully. You can track it in My Quote Requests below.';
   photos=[];renderPhotos();form.reset();updateServices();review.hidden=true;
   await history();
  }catch(error){status.textContent='Unable to submit: '+error.message;}
  finally{busy=false;confirm.disabled=false;submit.disabled=false;}
 });
 init();
})();
