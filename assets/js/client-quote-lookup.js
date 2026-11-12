(()=>{
'use strict';
const root=document.getElementById('clientQuoteLookup');
const status=document.getElementById('clientQuoteLookupStatus');
const search=document.getElementById('clientQuoteSearch');
const filter=document.getElementById('clientQuoteFilter');
if(!root||!status||!search||!filter)return;
const base=window.BRITE_BASE||'';
const node=(tag,text)=>{const e=document.createElement(tag);if(text!==undefined)e.textContent=String(text);return e;};
const labels={submitted:'Awaiting contractor response',more_info_requested:'Action needed',quoted:'Quote received',accepted:'Accepted',declined:'Declined',cancelled:'Cancelled'};
let requests=[];
let pager=null;
const expanded=new Set();
function matches(r){
 const q=search.value.trim().toLowerCase();
 const match=!q||[r.id,r.contractor_name,r.description,r.preferred_date].some(v=>String(v||'').toLowerCase().includes(q));
 const state=filter.value;
 return match&&(state==='all'||(state==='closed'?['accepted','declined','cancelled'].includes(r.status):r.status===state));
}
function detail(r){
 const body=node('div');body.style.cssText='border-top:1px solid #cbd5e1;margin-top:12px;padding-top:12px';
 const date=node('p','Preferred date: '+(r.preferred_date||'Flexible'));
 const received=node('p','Requested: '+(r.created_at||''));
 const desc=node('p',r.description||'No description');
 body.append(date,received,node('h3','Your job description'),desc);
 const photos=r.photos||[];
 body.append(node('h3','Job photographs ('+photos.length+')'));
 if(photos.length){
  const gallery=node('div');gallery.setAttribute('data-brite-photo-gallery','');gallery.style.cssText='display:flex;flex-wrap:wrap;gap:10px';
  photos.forEach((p,i)=>{
   const link=node('a');link.href=p.url;link.target='_blank';link.rel='noopener noreferrer';
   const img=node('img');img.src=p.url;img.alt='Job photograph '+(i+1)+' for request '+r.id;img.loading='lazy';
   img.style.cssText='width:115px;height:95px;object-fit:cover;border-radius:8px';
   link.append(img);gallery.append(link);
  });
  body.append(gallery);
 }
 if(window.BriteFormalQuote)window.BriteFormalQuote.attach(body,r.id,'client');
 body.append(node('h3','Conversation'));
 if(window.BriteQuoteConversation&&typeof window.BriteQuoteConversation.attach==='function'){
  window.BriteQuoteConversation.attach(body,r.id,'client');
 }else body.append(node('p','Conversation unavailable. Refresh to retry.'));
 return body;
}
function render(){
 const focused=document.activeElement?.closest('article')?.dataset.requestId;
 root.replaceChildren();
 const visible=(pager?requests:requests.filter(matches)).sort((a,b)=>{
 const priority=r=>r.status==='more_info_requested'?0:r.status==='quoted'?1:r.status==='submitted'?2:3;
 return priority(a)-priority(b)||Number(b.id)-Number(a.id);
 });
 const attention=requests.filter(r=>r.status==='more_info_requested').length;
 status.textContent=visible.length+' of '+requests.length+' quote requests shown.'+(attention?' '+attention+' need your attention.':'');
 if(!visible.length){
  const p=node('p',requests.length?'No quote requests match your search.':'You have not requested any quotes yet.');
  root.append(p);
  if(requests.length){const reset=node('button','Clear search and filters');reset.type='button';reset.addEventListener('click',()=>{search.value='';filter.value='all';render();});root.append(reset);}
  if(!requests.length){const a=node('a','Find a contractor to request a quote');a.href=base+'/client-contractor-directory.php';root.append(a);}
  return;
 }
 visible.forEach(r=>{
  const card=node('article');card.dataset.requestId=String(r.id);card.className='card';card.style.cssText='border:1px solid #cbd5e1;padding:16px';
  const button=node('button');button.type='button';button.setAttribute('aria-expanded','false');
  button.style.cssText='display:flex;justify-content:space-between;align-items:center;gap:12px;text-align:left;width:100%;background:transparent;border:0;color:inherit;padding:0;cursor:pointer';
  const summary=node('span');summary.style.cssText='display:grid;gap:5px;min-width:0';
  const title=node('strong','Request #'+r.id+' · '+(r.contractor_name||'Contractor'));
  const state=node('span',labels[r.status]||r.status);state.style.cssText='font-weight:700;font-size:.9rem;'+(r.status==='more_info_requested'?'color:#9a3412':'');
  const excerpt=node('span',(r.description||'').slice(0,120));excerpt.className='muted';
  summary.append(title,state,excerpt);
  if(r.status==='more_info_requested')summary.append(node('span','Next step: Open the conversation and answer the contractor’s question.'));
  else if(r.status==='submitted')summary.append(node('span','Next step: Waiting for the contractor to review your request.'));
  else if(r.status==='quoted')summary.append(node('span','A quote has been marked as received. Review the conversation for details.'));
  const indicator=node('span',r.status==='more_info_requested'?'Respond +':'View details +');indicator.style.cssText='white-space:nowrap;font-size:.85rem;font-weight:600';
  button.append(summary,indicator);card.append(button);
  let body=null;
  button.addEventListener('click',()=>{
   const open=button.getAttribute('aria-expanded')!=='true';
   if(open)expanded.add(String(r.id));else expanded.delete(String(r.id));
   button.setAttribute('aria-expanded',String(open));
   indicator.textContent=open?'Hide details −':'View details +';
   if(open){
    if(!body){body=detail(r);body.id='client-quote-detail-'+r.id;button.setAttribute('aria-controls',body.id);card.append(body);}
    body.hidden=false;
   }else if(body)body.hidden=true;
  });
  if(expanded.has(String(r.id)))button.click();
  root.append(card);
 });
}
let debounce;
search.addEventListener('input',()=>{if(!pager){render();return;}clearTimeout(debounce);debounce=setTimeout(()=>pager.goTo(1),300);});
filter.addEventListener('change',()=>pager?pager.goTo(1):render());
if(window.BriteServerPagination && document.getElementById('clientQuotePagination')){
 pager=new BriteServerPagination({
  container:document.getElementById('clientQuotePagination'),
  load:async ({page,per_page})=>{
   const qs=new URLSearchParams({page:String(page),per_page:String(per_page),search:search.value.trim(),status:filter.value});
   const response=await fetch(base+'/api/client_quote_requests.php?'+qs,{credentials:'same-origin',headers:{Accept:'application/json'}});
   const data=await response.json();
   if(!response.ok)throw Error(data.error||'HTTP '+response.status);
   return {records:data.requests,pagination:data.pagination};
  },
  render:records=>{requests=records;expanded.clear();render();},
  onError:error=>{status.textContent='Unable to load quote requests: '+error.message;}
 });
}else{
(async()=>{
 try{
  const response=await fetch(base+'/api/client_quote_requests.php',{credentials:'same-origin',headers:{Accept:'application/json'}});
  const data=await response.json();
  if(!response.ok)throw Error(data.error||'HTTP '+response.status);
  requests=Array.isArray(data.requests)?data.requests:[];
  render();
 }catch(e){status.textContent='Unable to load quote requests: '+e.message;}
})();
}
})();
