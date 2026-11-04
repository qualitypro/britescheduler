(()=>{
'use strict';
const target=document.getElementById('contractorQuoteInbox'),status=document.getElementById('contractorQuoteStatus');
if(!target||!status)return;
const base=window.BRITE_BASE||'';
const node=(tag,text)=>{const e=document.createElement(tag);if(text!==undefined)e.textContent=String(text);return e;};
const statuses={submitted:'New',more_info_requested:'More information requested',quoted:'Quoted',accepted:'Accepted',declined:'Declined',cancelled:'Cancelled'};
async function load(){
 try{
  const response=await fetch(base+'/api/contractor_quote_inbox.php',{credentials:'same-origin',headers:{Accept:'application/json'}});
  const data=await response.json();
  if(!response.ok)throw Error(data.error||'HTTP '+response.status);
  target.replaceChildren();
  const requests=data.requests||[];
  status.textContent=requests.length+' quote request'+(requests.length===1?'':'s')+' received.';
  if(!requests.length){target.append(node('p','No quote requests received yet.'));return;}
  requests.forEach(r=>{
   const card=node('article');card.className='card';card.style.cssText='border:1px solid #cbd5e1';
   const title=node('h2','Request #'+r.id+' · '+(r.service_name||'Service request'));title.style.cssText='margin:0 0 8px';card.append(title);
   const badge=node('p',statuses[r.status]||r.status);badge.style.cssText='display:inline-block;padding:5px 10px;border-radius:16px;background:#e2e8f0;font-weight:600;margin:0 0 12px';card.append(badge);
   for(const [label,value] of [
    ['Client',r.client_name],
    ['Location',[r.location_label,r.address1,r.address2,r.city,r.state,r.postal_code].filter(Boolean).join(', ')],
    ['Preferred date',r.preferred_date||'Flexible'],['Received',r.created_at],['Description',r.description]
   ]){const line=node('p');const strong=node('strong',label+': ');line.append(strong,document.createTextNode(String(value||'')));card.append(line);}
   const gallery=node('div');gallery.style.cssText='display:grid;grid-template-columns:repeat(auto-fill,minmax(140px,1fr));gap:12px';
   (r.photos||[]).forEach((p,i)=>{
    const link=node('a');link.href=p.url;link.target='_blank';link.rel='noopener noreferrer';
    const img=node('img');img.src=p.url;img.alt='Job photograph '+(i+1)+' for request '+r.id;img.loading='lazy';
    img.style.cssText='width:100%;height:125px;object-fit:cover;border-radius:8px';
    link.append(img);gallery.append(link);
   });
   if((r.photos||[]).length){const photoTitle=node('h3','Job Photos ('+r.photos.length+')');card.append(photoTitle);}card.append(gallery);
   const next=node('p',r.status==='more_info_requested'?'Waiting for the client to provide more information. You can still send a follow-up.':r.status==='submitted'?'Next step: review the job and reply, or request additional information.':'Review the conversation for updates.');next.className='muted';card.append(next);
   window.BriteQuoteConversation.attach(card,r.id,'contractor');target.append(card);
  });
 }catch(error){status.textContent='Unable to load quote requests: '+error.message;}
}
load();
})();
