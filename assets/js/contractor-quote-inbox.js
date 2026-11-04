(()=>{
'use strict';
const target=document.getElementById('contractorQuoteInbox');
const status=document.getElementById('contractorQuoteStatus');
const filters=document.getElementById('contractorQuoteFilters');
const search=document.getElementById('contractorQuoteSearch');
if(!target||!status)return;
const base=window.BRITE_BASE||'';
const node=(tag,text)=>{const e=document.createElement(tag);if(text!==undefined)e.textContent=String(text);return e;};
const statuses={submitted:'New request',more_info_requested:'Waiting on client',quoted:'Quote sent',accepted:'Accepted',declined:'Declined',cancelled:'Cancelled'};
const closed=new Set(['quoted','accepted','declined','cancelled']);
let requests=[],activeFilter='all';
const expanded=new Set();
let lastFocusedRequest=null;
const matches=r=>{
 const q=(search?.value||'').trim().toLowerCase();
 const found=!q||[r.id,r.client_name,r.service_name,r.description,r.city,r.postal_code].some(v=>String(v||'').toLowerCase().includes(q));
 return found&&(activeFilter==='all'||(activeFilter==='new'&&r.status==='submitted')||(activeFilter==='waiting'&&r.status==='more_info_requested')||(activeFilter==='closed'&&closed.has(r.status)));
};
function line(parent,label,value){
 const p=node('p');p.style.margin='8px 0';
 p.append(node('strong',label+': '),document.createTextNode(String(value||'Not provided')));
 parent.append(p);
}
function detailsFor(r){
 const body=node('div');body.style.cssText='border-top:1px solid #cbd5e1;margin-top:12px;padding-top:14px';
 const grid=node('div');grid.style.cssText='display:grid;grid-template-columns:repeat(auto-fit,minmax(230px,1fr));gap:8px 18px';
 line(grid,'Client',r.client_name);
 line(grid,'Location',[r.location_label,r.address1,r.address2,r.city,r.state,r.postal_code].filter(Boolean).join(', '));
 line(grid,'Preferred date',r.preferred_date||'Flexible');
 line(grid,'Received',r.created_at);
 body.append(grid);
 body.append(node('h3','Job description'),node('p',r.description||'No description provided.'));
 const photos=r.photos||[];
 body.append(node('h3','Job photographs ('+photos.length+')'));
 if(!photos.length)body.append(node('p','No job photographs attached.'));
 else{
  const gallery=node('div');gallery.style.cssText='display:grid;grid-template-columns:repeat(auto-fill,minmax(135px,1fr));gap:12px';
  photos.forEach((p,i)=>{
   const link=node('a');link.href=p.url;link.target='_blank';link.rel='noopener noreferrer';
   const img=node('img');img.src=p.url;img.alt='Job photograph '+(i+1)+' for request '+r.id;img.loading='lazy';
   img.style.cssText='width:100%;height:125px;object-fit:cover;border-radius:8px';
   link.append(img);gallery.append(link);
  });
  body.append(gallery);
 }
 const guidance=r.status==='more_info_requested'?'Waiting for the client to respond. You can send a follow-up in the conversation below.':r.status==='submitted'?'Review the job and message the client, or request more details or photographs.':'Review the conversation for updates.';
 const hint=node('p',guidance);hint.className='muted';hint.style.marginTop='14px';body.append(hint);
 const action=node('h3','Conversation and next steps');body.append(action);
 if(window.BriteQuoteConversation&&typeof window.BriteQuoteConversation.attach==='function'){
  window.BriteQuoteConversation.attach(body,r.id,'contractor');
 }else body.append(node('p','Conversation is currently unavailable. Refresh the page to retry.'));
 const future=node('p','Formal pricing and sending a quote will be available in a future update.');
 future.className='muted';body.append(future);
 return body;
}
function render(){
 const focused=document.activeElement?.closest('article')?.dataset.requestId;
 if(focused)lastFocusedRequest=focused;
 target.replaceChildren();
 const visible=requests.filter(matches).sort((a,b)=>{
 const priority=r=>r.status==='submitted'?0:r.status==='more_info_requested'?1:2;
 return priority(a)-priority(b)||Number(b.id)-Number(a.id);
 });
 status.textContent=visible.length+' of '+requests.length+' quote requests shown.';
 if(!visible.length){const empty=node('div');empty.className='card';empty.append(node('h3',requests.length?'No matching requests':'No quote requests yet'),node('p',requests.length?'Try another search or status filter.':'New client requests will appear here when received.'));target.append(empty);return;}
 visible.forEach(r=>{
  const card=node('article');card.dataset.requestId=String(r.id);card.className='card';card.style.cssText='border:1px solid #cbd5e1;padding:16px;min-width:0';
  const toggle=node('button');toggle.type='button';toggle.setAttribute('aria-expanded','false');
  toggle.style.cssText='display:flex;align-items:center;justify-content:space-between;gap:12px;width:100%;text-align:left;cursor:pointer;background:transparent;color:inherit;border:0;padding:0';
  const heading=node('span');heading.style.cssText='display:grid;gap:5px;min-width:0';
  const title=node('strong','Request #'+r.id+' · '+(r.service_name||'Service request'));title.style.fontSize='1.05rem';
  const meta=node('span',[r.client_name,r.preferred_date||'Flexible date'].filter(Boolean).join(' · '));meta.className='muted';
  const state=node('span',statuses[r.status]||r.status);state.style.cssText='font-size:.85rem;font-weight:600';
  heading.append(title,meta,state);
  const indicator=node('span',r.status==='submitted'?'Review new request +':'View details +');indicator.style.cssText='white-space:nowrap;font-weight:600;font-size:.85rem';
  toggle.append(heading,indicator);card.append(toggle);
  let content=null;
  toggle.addEventListener('click',()=>{
   const opening=toggle.getAttribute('aria-expanded')!=='true';
   if(opening)expanded.add(String(r.id));else expanded.delete(String(r.id));
   toggle.setAttribute('aria-expanded',String(opening));
   indicator.textContent=opening?'Hide details −':'Review request +';
   if(opening){
    if(!content){content=detailsFor(r);content.id='quote-request-details-'+r.id;toggle.setAttribute('aria-controls',content.id);card.append(content);}
    content.hidden=false;
   }else if(content)content.hidden=true;
  });
  if(expanded.has(String(r.id)))toggle.click();
  target.append(card);
 });
}
if(search)search.addEventListener('input',render);
if(filters){
 filters.addEventListener('click',event=>{
  const button=event.target.closest('button[data-quote-filter]');
  if(!button||!filters.contains(button))return;
  activeFilter=button.dataset.quoteFilter;
  filters.querySelectorAll('button[data-quote-filter]').forEach(b=>b.setAttribute('aria-pressed',String(b===button)));
  render();
 });
}
async function load(){
 try{
  const response=await fetch(base+'/api/contractor_quote_inbox.php',{credentials:'same-origin',headers:{Accept:'application/json'}});
  const data=await response.json();
  if(!response.ok)throw Error(data.error||'HTTP '+response.status);
  requests=Array.isArray(data.requests)?data.requests:[];
  render();
 }catch(error){status.textContent='Unable to load quote requests: '+error.message;}
}
load();
})();
