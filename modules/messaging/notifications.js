(()=>{
'use strict';
const toggle=document.getElementById('briteMsgToggle');
if(!toggle)return;
const panel=document.getElementById('briteMsgDropdown'),count=document.getElementById('briteMsgCount'),items=document.getElementById('briteMsgItems');
const base=(window.BRITE_BASE||'')+'/modules/messaging/api.php';
const inbox=(window.BRITE_BASE||'')+'/module.php?module=messaging';
let pending=false;
function link(t){
 const a=document.createElement('a');a.className='brite-msg-item';
 a.href=inbox+'&thread_id='+encodeURIComponent(t.id);
 const name=document.createElement('strong');name.textContent=t.sender||'New message';
 const subject=document.createElement('small');subject.textContent=t.subject;
 a.append(name,subject);return a;
}
async function refresh(){
 if(pending||document.hidden)return;
 pending=true;
 try{
  const r=await fetch(base+'?action=notifications',{credentials:'same-origin',headers:{Accept:'application/json'},cache:'no-store'});
  if(!r.ok)throw Error('Unable to check messages');
  const data=await r.json(),n=Math.max(0,Number(data.unread_count)||0);
  count.hidden=n===0;count.textContent=n>99?'99+':String(n);
  toggle.setAttribute('aria-label',n?n+' unread messages':'Messages, no unread messages');
  items.replaceChildren();
  if(!data.threads.length){items.textContent='You are all caught up.';}
  else for(const t of data.threads)items.append(link(t));
 }catch(e){if(!panel.hidden)items.textContent='Message notifications unavailable. Open inbox to retry.';}
 finally{pending=false;}
}
toggle.addEventListener('click',()=>{panel.hidden=!panel.hidden;toggle.setAttribute('aria-expanded',String(!panel.hidden));if(!panel.hidden)refresh();});
document.addEventListener('click',e=>{if(!panel.hidden&&!document.getElementById('briteMsgAlert').contains(e.target)){panel.hidden=true;toggle.setAttribute('aria-expanded','false');}});
document.addEventListener('keydown',e=>{if(e.key==='Escape'&&!panel.hidden){panel.hidden=true;toggle.setAttribute('aria-expanded','false');toggle.focus();}});
document.addEventListener('visibilitychange',()=>{if(!document.hidden)refresh();});
window.addEventListener('brite:messages-read',refresh);
refresh();setInterval(refresh,15000);
})();
