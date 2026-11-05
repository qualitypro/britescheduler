(()=>{
'use strict';
const $=id=>document.getElementById(id),base=(window.BRITE_BASE||'')+'/modules/messaging/api.php';
let current=null,busy=false;
const status=message=>{$('msgStatus').textContent=message;};
const el=(tag,text)=>{const n=document.createElement(tag);if(text!==undefined)n.textContent=String(text);return n;};
async function call(action,data){
 const post=!!data,url=base+(post?'':'?action='+encodeURIComponent(action));
 if(post)data.set('action',action);
 const r=await fetch(url,{method:post?'POST':'GET',credentials:'same-origin',headers:{Accept:'application/json',...(post?{'X-CSRF-Token':window.BRITE_CSRF}:{})},...(post?{body:data}:{})});
 const result=await r.json();if(!r.ok)throw Error(result.error||'Request failed');return result;
}
async function load(){
 const result=await call('list'),list=$('msgThreads');list.replaceChildren();
 if(!result.threads.length)list.append(el('p','No conversations yet.'));
 for(const t of result.threads){
  const b=el('button',t.subject+(Number(t.unread)?' · '+t.unread+' unread':''));b.type='button';b.style.textAlign='left';
  b.addEventListener('click',()=>openThread(t.id,t.subject));list.append(b);
 }
}
async function openThread(id,subject){
 current=id;$('msgCompose').hidden=true;$('msgDetail').hidden=false;
 $('msgHeading').textContent=subject;
 const result=await call('thread&thread_id='+encodeURIComponent(id));
 const container=$('msgMessages');container.replaceChildren();
 for(const m of result.messages){
  const card=el('div');card.className='card';card.style.padding='10px';
  const name=el('strong',[m.first_name,m.last_name].filter(Boolean).join(' '));
  const body=el('p',m.body);body.style.whiteSpace='pre-wrap';
  card.append(name,body,el('small',m.created_at));container.append(card);
 }
 $('msgReply').hidden=false;await load();
}
async function guarded(fn){
 if(busy)return;busy=true;
 try{await fn();status('Updated successfully.');}catch(e){status('Error: '+e.message);}finally{busy=false;}
}
$('msgNew').addEventListener('click',()=>guarded(async()=>{
 const r=await call('recipients'),sel=$('msgRecipients');sel.replaceChildren();
 for(const p of r.recipients){const o=el('option',[p.first_name,p.last_name].filter(Boolean).join(' ')+' ('+p.role+')');o.value=p.id;sel.append(o);}
 $('msgCompose').hidden=false;$('msgDetail').hidden=true;
 if(!r.recipients.length)status('No eligible recipients for this tenant.');
}));
$('msgCreate').addEventListener('submit',e=>{e.preventDefault();guarded(async()=>{
 const subject=e.target.elements.subject.value;const r=await call('create',new FormData(e.target));e.target.reset();await load();await openThread(r.thread_id,subject);
});});
$('msgReply').addEventListener('submit',e=>{e.preventDefault();guarded(async()=>{
 const subject=$('msgHeading').textContent;await call('reply',new FormDataWithThread(e.target,current));e.target.reset();await openThread(current,subject);
});});
function FormDataWithThread(form,id){const data=new FormData(form);data.set('thread_id',id);return data;}
$('msgInApp').addEventListener('change',e=>guarded(async()=>{
 const desired=e.target.checked,data=new FormData();data.set('in_app_enabled',desired?'1':'0');
 try{await call('preferences',data);}catch(err){e.target.checked=!desired;throw err;}
}));
Promise.all([load(),call('preferences').then(r=>{$('msgInApp').checked=r.in_app_enabled;})]).catch(e=>status('Unable to load messages: '+e.message));
})();