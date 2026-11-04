(()=>{
'use strict';
const base=window.BRITE_BASE||'';
const csrf=window.BRITE_CSRF||'';
const el=(tag,text)=>{const n=document.createElement(tag);if(text!==undefined)n.textContent=String(text);return n;};
function attach(card,id,role){
 const wrap=el('section');wrap.style.cssText='margin-top:14px;border-top:1px solid #cbd5e1;padding-top:12px';
 const button=el('button',role==='contractor'?'Request more information / View conversation':'View conversation / Reply');
 button.type='button';
 const panel=el('div');panel.hidden=true;panel.style.cssText='margin-top:12px';
 const status=el('p');status.setAttribute('role','status');
 const messages=el('div');messages.style.cssText='display:grid;gap:10px;margin:12px 0';
 const form=el('form');form.enctype='multipart/form-data';form.style.cssText='display:grid;gap:10px';
 const label=el('label',role==='contractor'?'Ask the client a question':'Reply to the contractor');
 const textarea=el('textarea');textarea.required=true;textarea.maxLength=3000;textarea.rows=3;
 label.append(textarea);
 const photoLabel=el('label','Additional job photographs (optional, up to 5 JPG/PNG, 5 MB each)');
 const photos=el('input');photos.type='file';photos.accept='.jpg,.jpeg,.png,image/jpeg,image/png';photos.multiple=true;photos.setAttribute('data-brite-pills','off');
 photoLabel.append(photos);
 const send=el('button','Send message');send.type='submit';
 form.append(label,photoLabel,send);
 panel.append(status,messages,form);wrap.append(button,panel);card.append(wrap);
 async function refresh(){
  const response=await fetch(base+'/api/quote_request_messages.php?request_id='+encodeURIComponent(id),{credentials:'same-origin',headers:{Accept:'application/json'}});
  const data=await response.json();if(!response.ok)throw Error(data.error||'HTTP '+response.status);
  messages.replaceChildren();
  if(!data.messages.length)messages.append(el('p','No messages yet.'));
  data.messages.forEach(m=>{
   const item=el('article');item.style.cssText='padding:10px;border:1px solid #cbd5e1;border-radius:8px';
   item.append(el('strong',(m.sender_role==='contractor'?'Contractor':'Client')+' — '+m.created_at));
   item.append(el('p',m.body));
   const gallery=el('div');gallery.style.cssText='display:flex;flex-wrap:wrap;gap:8px';
   (m.photos||[]).forEach((p,i)=>{
    const a=el('a');a.href=p.url;a.target='_blank';a.rel='noopener noreferrer';
    const img=el('img');img.src=p.url;img.alt='Message photo '+(i+1);img.loading='lazy';
    img.style.cssText='width:110px;height:90px;object-fit:cover;border-radius:8px';a.append(img);gallery.append(a);
   });item.append(gallery);messages.append(item);
  });
  const closed=['accepted','declined','cancelled'].includes(data.request_status);
  send.disabled=closed;textarea.disabled=closed;photos.disabled=closed;
  if(closed)status.textContent='This conversation is closed.';
 }
 button.addEventListener('click',async()=>{
  panel.hidden=!panel.hidden;
  if(!panel.hidden){status.textContent='Loading conversation...';try{await refresh();if(!send.disabled)status.textContent='';}catch(e){status.textContent=e.message;}}
 });
 form.addEventListener('submit',async event=>{
  event.preventDefault();if(!textarea.value.trim()){status.textContent='Enter a message.';return;}
  const selected=Array.from(photos.files||[]);
  if(selected.length>5||selected.some(f=>f.size<1||f.size>5*1024*1024||!['image/jpeg','image/png'].includes(f.type))){status.textContent='Choose up to 5 JPG/PNG photographs, each no larger than 5 MB.';return;}
  const data=new FormData();data.append('request_id',String(id));data.append('body',textarea.value.trim());
  selected.forEach(f=>data.append('photos[]',f,f.name));
  send.disabled=true;status.textContent='Sending...';
  try{
   const response=await fetch(base+'/api/quote_request_messages.php',{method:'POST',credentials:'same-origin',headers:{'X-CSRF-Token':csrf,Accept:'application/json'},body:data});
   const result=await response.json();if(!response.ok)throw Error(result.error||'HTTP '+response.status);
   textarea.value='';photos.value='';await refresh();status.textContent='Message sent successfully.';
  }catch(e){status.textContent='Unable to send: '+e.message;}
  finally{if(!['accepted','declined','cancelled'].includes(status.dataset.requestStatus||''))send.disabled=false;}
 });
}
window.BriteQuoteConversation={attach};
})();
