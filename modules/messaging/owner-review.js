(()=>{
'use strict';
const root=document.getElementById('msgOwnerReview');if(!root)return;
const $=id=>document.getElementById(id),base=(window.BRITE_BASE||'')+'/modules/messaging/owner_review.php';
const node=(tag,text)=>{const e=document.createElement(tag);e.textContent=String(text??'');return e;};
let threads=[];
async function get(action,params={}){
 const url=new URL(base,location.href);url.searchParams.set('action',action);
 for(const [key,value] of Object.entries(params))url.searchParams.set(key,String(value));
 const r=await fetch(url,{credentials:'same-origin',headers:{Accept:'application/json'}});
 const j=await r.json();if(!r.ok)throw Error(j.error||'Unable to load conversation');return j;
}
function render(){
 const list=$('msgReviewThreads');list.replaceChildren();
 const search=$('msgReviewSearch').value.trim().toLowerCase();
 const filtered=threads.filter(t=>(t.subject+' '+(t.participants||'')).toLowerCase().includes(search));
 if(!filtered.length)list.append(node('p','No matching conversations.'));
 for(const t of filtered){
  const button=node('button');button.type='button';button.className='msg-review-item';
  button.append(node('strong',t.participants||'Participants unavailable'),node('span',t.subject),node('small',t.message_count+' messages · '+t.updated_at));
  button.addEventListener('click',()=>open(t));list.append(button);
 }
}
async function open(t){
 try{
  const r=await get('thread',{thread_id:t.id});
  $('msgReviewHeading').textContent=(t.participants||'Conversation')+' — '+r.subject;
  const list=$('msgReviewMessages');list.replaceChildren();
  if(!r.messages.length)list.append(node('p','No messages.'));
  for(const m of r.messages){
   const bubble=node('article');bubble.className='msg-bubble';bubble.style.alignSelf='stretch';bubble.style.maxWidth='100%';
   const body=node('p',m.body);body.style.whiteSpace='pre-wrap';
   bubble.append(node('strong',[m.first_name,m.last_name].filter(Boolean).join(' ')),body,node('small',m.created_at));
   list.append(bubble);
  }
 }catch(e){$('msgReviewHeading').textContent='Unable to open conversation';$('msgReviewMessages').replaceChildren(node('p',e.message));}
}
$('msgReviewSearch').addEventListener('input',render);
get('list').then(r=>{threads=r.threads;render();}).catch(e=>$('msgReviewThreads').replaceChildren(node('p',e.message)));
})();
