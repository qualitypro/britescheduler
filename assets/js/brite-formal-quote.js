(()=>{
'use strict';
const base=window.BRITE_BASE||'';
const el=(tag,text)=>{const n=document.createElement(tag);if(text!==undefined)n.textContent=String(text);return n;};
const money=n=>new Intl.NumberFormat('en-US',{style:'currency',currency:'USD'}).format(Number(n));
function attach(parent,id,role){
 const section=el('section');section.className='brite-formal-quote';
 const heading=el('h3','Formal quote');const status=el('p');status.setAttribute('role','status');status.setAttribute('aria-live','polite');
 const offers=el('div');const controls=el('div');section.append(heading,status,offers,controls);parent.append(section);
 const fields={};
 function field(label,tag,name,options){
  const wrap=el('label',label);const input=el(tag);input.name=name;input.required=['amount','pricing_type','scope_of_work'].includes(name);
  if(tag==='select')(options||[]).forEach(([v,t])=>input.add(new Option(t,v)));
  if(tag==='textarea'){input.rows=4;input.maxLength=name==='scope_of_work'?5000:3000;}
  input.style.cssText='display:block;width:100%;box-sizing:border-box;margin-top:5px';
  wrap.append(input);fields[name]=input;return wrap;
 }
 if(role==='contractor'){
  const form=el('form');form.className='brite-quote-offer-form';
  form.append(field('Price in USD','input','amount'),field('Pricing type','select','pricing_type',[['flat','Flat rate'],['hourly','Hourly rate']]),field('Scope of work (at least 20 characters)','textarea','scope_of_work'),field('Terms and exclusions (optional)','textarea','terms'),field('Estimated hours (hourly quotes only, optional)','input','estimated_hours'),field('Valid through (optional)','input','valid_until'));
  fields.amount.type='number';fields.amount.min='0.01';fields.amount.max='9999999999.99';fields.amount.step='0.01';
  fields.scope_of_work.minLength=20;fields.estimated_hours.type='number';fields.estimated_hours.min='0.01';fields.estimated_hours.step='0.01';fields.valid_until.type='date';
  const today=new Date();fields.valid_until.min=new Date(today.getTime()-today.getTimezoneOffset()*60000).toISOString().slice(0,10);
  const send=el('button','Send formal quote');send.type='submit';
  const hint=el('p','Submitting a revision replaces the previous outstanding offer. Clients can review the full history.');hint.className='muted';
  form.append(hint,send);controls.append(form);
  form.addEventListener('submit',async e=>{
   e.preventDefault();if(!form.reportValidity())return;
   if(fields.pricing_type.value==='flat'&&fields.estimated_hours.value){status.textContent='Estimated hours are only available for hourly quotes.';return;}
   if(!window.confirm('Send this formal quote to the client?'))return;
   send.disabled=true;status.textContent='Sending formal quote...';
   try{await post('submit',new FormData(form));status.textContent='Formal quote sent.';await refresh();}
   catch(error){status.textContent=error.message;}finally{send.disabled=false;}
  });
  fields.pricing_type.addEventListener('change',()=>{const hourly=fields.pricing_type.value==='hourly';fields.estimated_hours.disabled=!hourly;if(!hourly)fields.estimated_hours.value='';});
  fields.pricing_type.dispatchEvent(new Event('change'));
 }
 async function post(action,data=new FormData()){
  data.set('request_id',String(id));data.set('action',action);
  const response=await fetch(base+'/api/quote_offers.php',{method:'POST',credentials:'same-origin',headers:{'X-CSRF-Token':window.BRITE_CSRF,Accept:'application/json'},body:data});
  const result=await response.json();if(!response.ok)throw Error(result.error||'Request failed');return result;
 }
 async function refresh(){
  const response=await fetch(base+'/api/quote_offers.php?request_id='+encodeURIComponent(id),{credentials:'same-origin',headers:{Accept:'application/json'}});
  const data=await response.json();if(!response.ok)throw Error(data.error||'Unable to load formal quote');
  offers.replaceChildren();
  if(!data.offers.length){offers.append(el('p',role==='client'?'No formal quote has been sent yet.':'No formal quote submitted yet.'));return;}
  data.offers.forEach((offer,index)=>{
   const card=el('article');card.className='brite-offer-card';
   card.append(el('strong','Revision '+offer.revision+' · '+money(offer.amount)+(offer.pricing_type==='hourly'?'/hour':' flat rate')));
   card.append(el('p','Scope: '+offer.scope_of_work));
   if(offer.estimated_hours)card.append(el('p','Estimated hours: '+offer.estimated_hours));
   if(offer.terms)card.append(el('p','Terms: '+offer.terms));
   if(offer.valid_until)card.append(el('p','Valid through: '+offer.valid_until));
   card.append(el('small','Sent '+offer.created_at));
   if(index===0&&role==='client'&&data.request_status==='quoted'){
    const expired=offer.valid_until&&offer.valid_until<new Date(Date.now()-new Date().getTimezoneOffset()*60000).toISOString().slice(0,10);
    const actions=el('div');actions.className='brite-offer-actions';
    [['accept','Accept quote'],['decline','Decline quote']].forEach(([action,label])=>{
     const b=el('button',label);b.type='button';if(action==='accept'&&expired)b.disabled=true;
     b.addEventListener('click',async()=>{
      if(!window.confirm(action==='accept'?'Accept this quote? This records your acceptance but does not automatically book a service.':'Decline this quote?'))return;
      actions.querySelectorAll('button').forEach(x=>x.disabled=true);
      status.textContent='Updating quote...';
      try{await post(action);status.textContent=action==='accept'?'Quote accepted. Scheduling is a separate step.':'Quote declined.';await refresh();}
      catch(error){status.textContent=error.message;actions.querySelectorAll('button').forEach(x=>x.disabled=false);}
     });actions.append(b);
    });
    if(expired)actions.prepend(el('p','This quote has expired. Ask the contractor for a revision.'));
    card.append(actions);
   }
   offers.append(card);
  });
  if(role==='contractor'){
   const form=controls.querySelector('form');
   if(form){form.hidden=!['submitted','more_info_requested','quoted'].includes(data.request_status);if(data.request_status==='quoted')form.querySelector('button[type=submit]').textContent='Send revised quote';}
  }
 }
 status.textContent='Loading formal quotes...';
 refresh().then(()=>{status.textContent='';}).catch(e=>{status.textContent='Formal quotes unavailable: '+e.message;});
}
window.BriteFormalQuote={attach};
})();
