(()=>{
'use strict';
const base=window.BRITE_BASE||'';
const csrf=window.BRITE_CSRF||'';
const el=(tag,text)=>{const n=document.createElement(tag);if(text!==undefined)n.textContent=String(text);return n;};
function attach(card,id,role){
 const wrap=el('section');wrap.style.cssText='margin-top:14px;border-top:1px solid #cbd5e1;padding-top:12px';
 const button=el('button',role==='contractor'?'Open Conversation & Reply':'Open Conversation & Reply');
 button.type='button';
 const panel=el('div');panel.hidden=true;panel.style.cssText='margin-top:12px';
 button.setAttribute('aria-expanded','false');
 button.setAttribute('aria-controls','quote-conversation-'+role+'-'+id);
 panel.id='quote-conversation-'+role+'-'+id;
 const heading=el('h3','Conversation with '+(role==='contractor'?'Client':'Contractor'));
 const actionHint=el('p');actionHint.className='muted';actionHint.style.cssText='padding:10px;border-radius:8px;background:#eef6ff';
 const help=el('p',role==='contractor'?'Choose the type of response, write your message, and send it to the client.':'Review the conversation and reply below. You can attach additional job photographs.');
 help.className='muted';
 const status=el('p');status.setAttribute('role','status');status.setAttribute('aria-live','polite');
 const messages=el('div');messages.style.cssText='display:grid;gap:10px;margin:12px 0;max-height:420px;overflow-y:auto';messages.setAttribute('aria-label','Conversation history');
 const form=el('form');form.enctype='multipart/form-data';form.style.cssText='display:grid;gap:10px';
 const label=el('label',role==='contractor'?'Your reply to the client':'Your reply to the contractor');
 const textarea=el('textarea');textarea.required=true;textarea.maxLength=3000;textarea.rows=3;
 textarea.placeholder=role==='contractor'?'Type your reply or question for the client...':'Type your reply to the contractor...';textarea.style.cssText='display:block;width:100%;box-sizing:border-box;margin-top:6px';
 label.append(textarea);
 const intentWrap=el('fieldset');intentWrap.style.cssText='border:1px solid #cbd5e1;border-radius:8px;padding:12px;display:grid;gap:9px';
 let send=null;
 const intentInputs=[];
 const guidance=el('p');guidance.className='muted';guidance.style.cssText='margin:0;font-size:13px';
 if(role==='contractor'){
  intentWrap.append(el('legend','What would you like to send?'));
  [['reply','Reply to Client'],['request_photos','Request More Photos'],['request_data','Request More Data']].forEach(([value,title])=>{
   const option=el('label');option.style.cssText='display:flex;align-items:center;gap:9px;cursor:pointer';
   const input=el('input');input.type='radio';input.name='quote_intent_'+id;input.value=value;input.checked=value==='reply';
   option.append(input,document.createTextNode(title));intentWrap.append(option);intentInputs.push(input);
   input.addEventListener('change',()=>{if(input.checked)updateIntent();});
  });
  intentWrap.append(guidance);
 }
 function updateIntent(){
  const type=intentInputs.find(input=>input.checked)?.value||'reply';
  const copy={reply:['Send Reply to Client','Type your message to the client...','Send a normal message. The request stays in its current stage.'],request_photos:['Request More Photos','Which views or areas should the client photograph?','The client will see this as a request for additional photographs.'],request_data:['Request More Data','What details, measurements, or specifications do you need?','The client will see this as a request for additional job information.']};
  const [buttonText,placeholder,explanation]=copy[type];
  textarea.placeholder=placeholder;guidance.textContent=explanation;
  if(send)send.textContent=buttonText;
 }
 const photoLabel=el('label','Additional job photographs (optional, up to 5 JPG/PNG, 5 MB each)');
 const photos=el('input');photos.type='file';photos.accept='.jpg,.jpeg,.png,image/jpeg,image/png';photos.multiple=true;photos.setAttribute('data-brite-pills','off');
 photoLabel.append(photos);
 const previewStatus=el('p','No photographs selected.');previewStatus.setAttribute('role','status');previewStatus.setAttribute('aria-live','polite');
 const preview=el('div');preview.style.cssText='display:grid;grid-template-columns:repeat(auto-fill,minmax(130px,1fr));gap:10px';
 preview.setAttribute('aria-label','Photographs selected for this reply');
 let selectedPhotos=[],sending=false;
 function renderPreview(){
  preview.replaceChildren();
  previewStatus.textContent=selectedPhotos.length+' of 5 photographs selected for this reply.';
  selectedPhotos.forEach((file,index)=>{
   const tile=el('div');tile.style.cssText='border:1px solid #cbd5e1;border-radius:8px;padding:8px;min-width:0';
   const img=el('img');const url=URL.createObjectURL(file);
   img.src=url;img.alt='Reply photograph '+(index+1);img.style.cssText='width:100%;height:110px;object-fit:cover;border-radius:6px';
   img.addEventListener('load',()=>URL.revokeObjectURL(url),{once:true});
   img.addEventListener('error',()=>URL.revokeObjectURL(url),{once:true});
   const name=el('p',file.name);name.style.cssText='font-size:12px;overflow-wrap:anywhere';
   const remove=el('button','Remove');remove.type='button';remove.disabled=sending;
   remove.addEventListener('click',()=>{if(sending)return;selectedPhotos.splice(index,1);renderPreview();});
   tile.append(img,name,remove);preview.append(tile);
  });
 }
 photos.addEventListener('change',()=>{
  if(sending)return;
  for(const file of Array.from(photos.files||[])){
   if(selectedPhotos.length>=5){status.textContent='Maximum 5 photographs per reply.';break;}
   if(file.size<1||file.size>5*1024*1024||!['image/jpeg','image/png'].includes(file.type)||!/\.(jpe?g|png)$/i.test(file.name)){
    status.textContent='Only JPG/PNG files up to 5 MB are accepted: '+file.name;continue;
   }
   selectedPhotos.push(file);
  }
  photos.value='';renderPreview();
 });
 renderPreview();
 send=el('button',role==='contractor'?'Send Reply to Client':'Send Reply to Contractor');send.type='submit';send.style.cssText='font-weight:700;justify-self:start';
 if(role==='contractor'){form.append(intentWrap);updateIntent();}
 form.append(label,photoLabel,previewStatus,preview,send);
 panel.append(heading,help,actionHint,messages,form,status);wrap.append(button,panel);card.append(wrap);
 async function refresh(){
  const response=await fetch(base+'/api/quote_request_messages.php?request_id='+encodeURIComponent(id),{credentials:'same-origin',headers:{Accept:'application/json'}});
  const data=await response.json();if(!response.ok)throw Error(data.error||'HTTP '+response.status);
  messages.replaceChildren();
  if(!data.messages.length)messages.append(el('p',role==='client'?'Your contractor has not sent any messages yet. You can still send a question below.':'No conversation yet. Start by writing a message below.'));
  if(role==='client'){
   const pending=[...data.messages].reverse().find(m=>m.sender_role==='contractor'&&['request_photos','request_data'].includes(m.message_type));
   const latestClient=[...data.messages].reverse().find(m=>m.sender_role==='client');
   if(pending&&(!latestClient||Number(latestClient.id)<Number(pending.id))){
    const needsPhotos=pending.message_type==='request_photos';
    actionHint.textContent=needsPhotos?'Action needed: Your contractor requested more photos. Add the requested pictures below, write a short reply, then select Send Reply to Contractor.':'Action needed: Your contractor requested more details. Answer the question below, then select Send Reply to Contractor.';
    actionHint.hidden=false;
   }else{actionHint.textContent='You can send a message or additional photos to your contractor at any time while this request is open.';actionHint.hidden=false;}
  }else{actionHint.hidden=true;}
  data.messages.forEach(m=>{
   const item=el('article');const mine=m.sender_role===role;item.style.cssText='padding:12px;border:1px solid #cbd5e1;border-radius:12px;max-width:88%;min-width:0;'+(mine?'justify-self:end;background:#e8f2ff':'justify-self:start;background:#f8fafc');
   item.append(el('strong',(mine?'You':(m.sender_role==='contractor'?'Contractor':'Client'))+' · '+m.created_at));
   if(m.message_type==='request_photos')item.append(el('p','📷 Request More Photos'));
   if(m.message_type==='request_data')item.append(el('p','📋 Request More Data'));
   item.append(el('p',m.body));
   const gallery=el('div');gallery.setAttribute('data-brite-photo-gallery','');gallery.style.cssText='display:flex;flex-wrap:wrap;gap:8px';
   (m.photos||[]).forEach((p,i)=>{
    const a=el('a');a.href=p.url;a.target='_blank';a.rel='noopener noreferrer';
    const img=el('img');img.src=p.url;img.alt='Message photo '+(i+1);img.loading='lazy';
    img.style.cssText='width:110px;height:90px;object-fit:cover;border-radius:8px';a.append(img);gallery.append(a);
   });item.append(gallery);messages.append(item);
  });
  const closed=['accepted','declined','cancelled'].includes(data.request_status);
  send.disabled=closed;textarea.disabled=closed;photos.disabled=closed;intentInputs.forEach(input=>input.disabled=closed);
  status.dataset.requestStatus=data.request_status;
  if(closed)status.textContent='This conversation is closed. Previous messages and photographs remain available.';
  messages.scrollTop=messages.scrollHeight;
 }
 button.addEventListener('click',async()=>{
  panel.hidden=!panel.hidden;
  button.setAttribute('aria-expanded',String(!panel.hidden));
  button.textContent=panel.hidden?'Open Conversation & Reply':'Hide Conversation';
  if(!panel.hidden){status.textContent='Loading conversation...';try{await refresh();if(!send.disabled)status.textContent='';textarea.focus();}catch(e){status.textContent=e.message;}}
 });
 form.addEventListener('submit',async event=>{
  event.preventDefault();if(!textarea.value.trim()){status.textContent='Enter a message.';return;}
  if(sending)return;
  const selected=selectedPhotos.slice();
  if(selected.length>5||selected.some(f=>f.size<1||f.size>5*1024*1024||!['image/jpeg','image/png'].includes(f.type))){status.textContent='Choose up to 5 JPG/PNG photographs, each no larger than 5 MB.';return;}
  const data=new FormData();data.append('request_id',String(id));data.append('body',textarea.value.trim());
  data.append('message_type',role==='contractor'?(intentInputs.find(input=>input.checked)?.value||'reply'):'reply');
  selected.forEach(f=>data.append('photos[]',f,f.name));
  sending=true;send.disabled=true;photos.disabled=true;preview.querySelectorAll('button').forEach(b=>b.disabled=true);status.textContent='Sending...';
  try{
   const response=await fetch(base+'/api/quote_request_messages.php',{method:'POST',credentials:'same-origin',headers:{'X-CSRF-Token':csrf,Accept:'application/json'},body:data});
   const result=await response.json();if(!response.ok)throw Error(result.error||'HTTP '+response.status);
   textarea.value='';photos.value='';selectedPhotos=[];renderPreview();intentInputs.forEach(input=>input.checked=input.value==='reply');if(role==='contractor')updateIntent();await refresh();status.textContent='Message sent. It is now visible in the conversation above.';
  }catch(e){status.textContent='Unable to send: '+e.message;}
  finally{sending=false;const closed=['accepted','declined','cancelled'].includes(status.dataset.requestStatus||'');send.disabled=closed;photos.disabled=closed;preview.querySelectorAll('button').forEach(b=>b.disabled=closed);}
 });
}
window.BriteQuoteConversation={attach};
})();
