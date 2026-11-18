(()=>{
'use strict';
const $=id=>document.getElementById(id);
const base=(window.BRITE_BASE||'')+'/modules/messaging/api.php';
let current=null,currentTitle='',busy=false,refreshing=false,selectionToken=0,composing=false;
const status=message=>{$('msgStatus').textContent=message;};
let threadCache=[],tablePage=1;
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

const el=(tag,text)=>{const n=document.createElement(tag);if(text!==undefined)n.textContent=String(text);return n;};
const tableControls=['msgThreadSearch','msgUnreadFilter','msgThreadSort','msgPageSize'];
function renderThreads(){
 const term=$('msgThreadSearch').value.trim().toLocaleLowerCase();
 const filter=$('msgUnreadFilter').value,sort=$('msgThreadSort').value;
 const filtered=threadCache.filter(t=>{
  const unread=Number(t.unread||0)>0;
  return (filter==='all'||(filter==='unread'?unread:!unread))&&
   [t.other_names,t.subject,t.last_body].filter(Boolean).join(' ').toLocaleLowerCase().includes(term);
 });
 filtered.sort((a,b)=>{
  const text=(x,k)=>String(x[k]||'').toLocaleLowerCase();
  if(sort==='participant')return text(a,'other_names').localeCompare(text(b,'other_names'));
  if(sort==='subject')return text(a,'subject').localeCompare(text(b,'subject'));
  if(sort==='unread')return Number(b.unread||0)-Number(a.unread||0)||text(b,'updated_at').localeCompare(text(a,'updated_at'));
  return (sort==='oldest'?1:-1)*text(a,'updated_at').localeCompare(text(b,'updated_at'));
 });
 const size=Number($('msgPageSize').value)||25,pages=Math.max(1,Math.ceil(filtered.length/size));
 tablePage=Math.min(Math.max(1,tablePage),pages);
 const list=$('msgThreads');list.replaceChildren();
 const pageRows=filtered.slice((tablePage-1)*size,tablePage*size);
 for(const t of pageRows){
  const row=el('tr');row.setAttribute('aria-selected',Number(t.id)===Number(current)?'true':'false');
  const participant=el('td'),subject=el('td'),date=el('td'),unread=el('td');
  const open=el('button',t.other_names||'Unknown participant');
  open.type='button';open.className='msg-cell-select';open.title='Open conversation with '+(t.other_names||'participant');
  open.addEventListener('click',()=>openThread(t.id,t.subject).catch(e=>status('Unable to open: '+e.message)));
  participant.append(open);
  const subjectButton=el('button');subjectButton.type='button';subjectButton.className='msg-cell-select';
  const title=el('span',t.subject||'(No subject)');title.className='msg-table-subject';
  const preview=el('span',t.last_body||'No messages yet');preview.className='msg-table-preview';
  subjectButton.title=(t.subject||'')+' — '+(t.last_body||'');
  subjectButton.append(title,preview);
  subjectButton.addEventListener('click',()=>openThread(t.id,t.subject).catch(e=>status('Unable to open: '+e.message)));
  subject.append(subjectButton);
  const timestamp=el('time',t.updated_at||'');timestamp.className='msg-table-date';date.append(timestamp);
  if(Number(t.unread||0)){const badge=el('span',String(t.unread));badge.className='msg-table-unread';badge.setAttribute('aria-label',t.unread+' unread messages');unread.append(badge);}
  else unread.append(el('span','—'));
  row.append(participant,subject,date,unread);list.append(row);
 }
 if(!pageRows.length){const row=el('tr'),cell=el('td',threadCache.length?'No matching conversations.':'No conversations yet. Select New conversation to start.');cell.colSpan=4;cell.style.padding='12px';row.append(cell);list.append(row);}
 $('msgThreadCount').textContent=filtered.length+' of '+threadCache.length+' conversations';
 $('msgPageInfo').textContent='Page '+tablePage+' of '+pages;
 $('msgPagePrev').disabled=tablePage<=1;$('msgPageNext').disabled=tablePage>=pages;
}
for(const id of tableControls)$(id).addEventListener(id==='msgThreadSearch'?'input':'change',()=>{tablePage=1;renderThreads();});
$('msgPagePrev').addEventListener('click',()=>{tablePage--;renderThreads();});
$('msgPageNext').addEventListener('click',()=>{tablePage++;renderThreads();});
async function load(){
 const result=await call('list');
 threadCache=Array.isArray(result.threads)?result.threads:[];
 renderThreads();
 return threadCache;
}
async function openThread(id,subject,{silent=false}={}){
 const requested=Number(id);if(!requested)return;
 const token=++selectionToken;
 const result=await call('thread',undefined,{thread_id:requested});
 // Ignore a stale response if another conversation was selected.
 if(token!==selectionToken)return;
 current=requested;currentTitle=subject;composing=false;
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
 if(!silent||!container.dataset.loadedThread||container.dataset.loadedThread!==String(requested))container.scrollTop=container.scrollHeight;
 container.dataset.loadedThread=String(requested);
 if(!silent)$('msgReplyBody').focus();
 await load();
 window.dispatchEvent(new Event('brite:messages-read'));
 if(!silent)status('Conversation opened.');
}
async function guarded(fn){
 if(busy)return;busy=true;
 try{await fn();}catch(e){status('Error: '+e.message);}finally{busy=false;}
}
$('msgNew').addEventListener('click',()=>guarded(async()=>{
 if(composing){$('msgCreate').elements.recipient_id.focus();return;}
 const r=await call('recipients'),sel=$('msgRecipients');sel.replaceChildren();
 for(const p of r.recipients){
  const option=el('option',[p.first_name,p.last_name].filter(Boolean).join(' ')+' ('+p.role+')');
  option.value=p.id;sel.append(option);
 }
 composing=true;++selectionToken;
 $('msgCompose').hidden=false;$('msgDetail').hidden=true;
 $('msgCreate').querySelector('button[type="submit"]').disabled=!r.recipients.length;
 if(r.recipients.length)sel.focus();
 status(r.recipients.length?'Choose a recipient and write your message.':'No eligible recipients. Ask your administrator to enable Messaging for the other role.');
}));
$('msgCancelCompose').addEventListener('click',()=>{composing=false;++selectionToken;$('msgCompose').hidden=true;$('msgDetail').hidden=false;status('Draft closed.');});
$('msgCreate').addEventListener('submit',event=>{event.preventDefault();guarded(async()=>{
 const form=event.target,subject=form.elements.subject.value;
 const result=await call('create',new FormData(form));
 form.reset();composing=false;current=null;await openThread(result.thread_id,subject);
 status('Conversation started and message sent.');
});});
$('msgReplyBody').addEventListener('keydown',event=>{if(event.key==='Enter'&&(event.ctrlKey||event.metaKey)){event.preventDefault();$('msgReply').requestSubmit();}});
$('msgReply').addEventListener('submit',event=>{event.preventDefault();guarded(async()=>{
 const form=event.target,data=new FormData(form),thread=current,subject=currentTitle;
 if(!thread)throw Error('Select a conversation first');
 data.set('thread_id',String(thread));await call('reply',data);
 form.reset();await openThread(thread,subject,{silent:true});$('msgReplyBody').focus();status('Reply sent.');
});});
$('msgRefresh').addEventListener('click',()=>guarded(async()=>{
 if(composing)await load();else if(current)await openThread(current,currentTitle,{silent:true});else await load();
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
 if(selected&&!composing)await openThread(selected.id,selected.subject,{silent:true});
})
.catch(e=>status('Unable to load messages: '+e.message));
// Poll only while visible; never overwrite an unsent reply or switch threads.
setInterval(async()=>{
 if(document.hidden||busy||refreshing||composing)return;
 refreshing=true;
 try{if(current)await openThread(current,currentTitle,{silent:true});else await load();}
 catch(e){status('Refresh failed: '+e.message);}
 finally{refreshing=false;}
},15000);
})();
