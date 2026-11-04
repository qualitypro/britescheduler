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
   const title=node('h2','Request #'+r.id+' — '+(statuses[r.status]||r.status));card.append(title);
   for(const [label,value] of [
    ['Client',r.client_name],['Service',r.service_name||'Not specified'],
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
   card.append(gallery);window.BriteQuoteConversation.attach(card,r.id,'contractor');target.append(card);
  });
 }catch(error){status.textContent='Unable to load quote requests: '+error.message;}
}
load();
})();
