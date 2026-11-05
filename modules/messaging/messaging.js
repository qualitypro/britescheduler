(()=>{
'use strict';
const $=id=>document.getElementById(id);
const base=(window.BRITE_BASE||'')+'/modules/messaging/api.php';
let current=null,currentTitle='',busy=false,refreshing=false;
const status=message=>{$('msgStatus').textContent=message;};
const el=(tag,text)=>{const n=document.createElement(tag);if(text!==undefined)n.textContent=String(text);return n;};
async function call(action,data,params={}){
 const post=data instanceof FormData;
 const url=new URL(base,location.href);
 if(!post){url.searchParams.set('action',action);for(const [key,value] of Object.entries(params))url.searchParams.set(key,String(value));}
 else data.set('action',action);
 const r=await fetch(url.toString(),{method:post?'POST':'GET',credentials:'same-origin',headers:{Accept:'application/json',...(post?{'X-CSRF-Token':window.BRITE_CSRF}:{})},...(post?{body:data}:{})});
 let result;try{result=await r.json();}catch{throw Error('Server returned an invalid response');}
 if(!r.ok)throw Error(result.error||'Request failed');
 return result;
}
async function load(){
 const result=await call('list'),list=$('msgThreads');list.replaceChildren();
 if(!result.threads.length){list.append(el('p','No conversations yet. Select New conversation to message another member of your organization.'));return result.threads;}
 for(const t of result.threads){
  const button=el('button');button.type='button';button.className='msg-thread';
  if(Number(t.id)===Number(current))button.setAttribute('aria-current','true');
  const title=el('strong',t.other_names||t.subject);title.className='msg-thread-name';
  const subject=el('span',t.subject);subject.className='msg-thread-subject';
  const preview=el('span',t.last_body||'No messages yet');preview.className='msg-thread-preview';
  const meta=el('span');meta.className='msg-thread-meta';
  meta.append(el('small',t.updated_at||''));
  if(Number(t.unread)){const count=el('span',t.unread+' unread');count.className='msg-unread';meta.append(count);}
  button.append(title,subject,preview,meta);
  button.addEventListener('click',()=>openThread(t.id,t.subject));
  list.append(button);
 }
 return result.threads;
}
async function openThread(id,subject,{silent=false}={}){
 const requested=Number(id);if(!requested)return;
 const result=await call('thread',undefined,{thread_id:requested});
 // Ignore a stale response if another conversation was selected.
 if(current!==null&&Number(current)!==requested&&silent)return;
 current=requested;currentTitle=subject;
 $('msgCompose').hidden=true;$('msgDetail').hidden=false;
 $('msgHeading').textContent=subject;
 const container=$('msgMessages');container.replaceChildren();
 if(!result.messages.length)container.append(el('p','No messages in this conversation.'));
 for(const m of result.messages){
  const card=el('article');card.className='msg-bubble'+(m.mine?' msg-mine':'');
  const who=el('strong',m.mine?'You':[m.first_name,m.last_name].filter(Boolean).join(' '));
  const body=el('p',m.body);body.style.whiteSpace='pre-wrap';
  const when=el('small',m.created_at||'');
  card.append(who,body,when);container.append(card);
 }
 $('msgReply').hidden=false;
 container.scrollTop=container.scrollHeight;
 await load();
 window.dispatchEvent(new Event('brite:messages-read'));
 if(!silent)status('Conversation opened.');
}
async function guarded(fn){
 if(busy)return;busy=true;
 try{await fn();}catch(e){status('Error: '+e.message);}finally{busy=false;}
}
$('msgNew').addEventListener('click',()=>guarded(async()=>{
 const r=await call('recipients'),sel=$('msgRecipients');sel.replaceChildren();
 for(const p of r.recipients){
  const option=el('option',[p.first_name,p.last_name].filter(Boolean).join(' ')+' ('+p.role+')');
  option.value=p.id;sel.append(option);
 }
 $('msgCompose').hidden=false;$('msgDetail').hidden=true;
 status(r.recipients.length?'Choose a recipient and write your message.':'No eligible recipients. Ask your administrator to enable Messaging for the other role.');
}));
$('msgCreate').addEventListener('submit',event=>{event.preventDefault();guarded(async()=>{
 const form=event.target,subject=form.elements.subject.value;
 const result=await call('create',new FormData(form));
 form.reset();current=null;await openThread(result.thread_id,subject);
 status('Conversation started and message sent.');
});});
$('msgReply').addEventListener('submit',event=>{event.preventDefault();guarded(async()=>{
 const form=event.target,data=new FormData(form),thread=current,subject=currentTitle;
 if(!thread)throw Error('Select a conversation first');
 data.set('thread_id',String(thread));await call('reply',data);
 form.reset();await openThread(thread,subject,{silent:true});status('Reply sent.');
});});
$('msgRefresh').addEventListener('click',()=>guarded(async()=>{
 if(current)await openThread(current,currentTitle,{silent:true});else await load();
 status('Messages refreshed.');
}));
$('msgInApp').addEventListener('change',event=>guarded(async()=>{
 const desired=event.target.checked,data=new FormData();data.set('in_app_enabled',desired?'1':'0');
 try{await call('preferences',data);status('Preference saved.');}
 catch(err){event.target.checked=!desired;throw err;}
}));
Promise.all([load(),call('preferences').then(r=>{$('msgInApp').checked=r.in_app_enabled;})])
.then(async([threads])=>{
 const requested=Number(new URLSearchParams(location.search).get('thread_id'));
 const selected=threads.find(t=>Number(t.id)===requested)||threads[0];
 if(selected)await openThread(selected.id,selected.subject,{silent:true});
})
.catch(e=>status('Unable to load messages: '+e.message));
// Poll only while visible; never overwrite an unsent reply or switch threads.
setInterval(async()=>{
 if(document.hidden||busy||refreshing)return;
 refreshing=true;
 try{if(current)await openThread(current,currentTitle,{silent:true});else await load();}
 catch(e){status('Refresh failed: '+e.message);}
 finally{refreshing=false;}
},15000);
})();
