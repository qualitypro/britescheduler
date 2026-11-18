(()=>{
'use strict';
const root=document.getElementById('msgOwnerReview');if(!root)return;
const $=id=>document.getElementById(id),base=(window.BRITE_BASE||'')+'/modules/messaging/owner_review.php';
const node=(tag,text)=>{const e=document.createElement(tag);if(text!==undefined)e.textContent=String(text??'');return e;};
let threads=[],page=1,selected=null,requestToken=0;
async function get(action,params={}){
 const url=new URL(base,location.href);url.searchParams.set('action',action);
 for(const [key,value] of Object.entries(params))url.searchParams.set(key,String(value));
 const r=await fetch(url,{credentials:'same-origin',headers:{Accept:'application/json'}});
 let j;try{j=await r.json();}catch{throw Error('Invalid server response');}
 if(!r.ok)throw Error(j.error||'Unable to load conversation');return j;
}
function render(){
 const term=$('msgReviewSearch').value.trim().toLocaleLowerCase(),sort=$('msgReviewSort').value;
 const filtered=threads.filter(t=>[t.subject,t.participants].filter(Boolean).join(' ').toLocaleLowerCase().includes(term));
 const txt=(t,key)=>String(t[key]||'').toLocaleLowerCase();
 filtered.sort((a,b)=>{
  if(sort==='participants')return txt(a,'participants').localeCompare(txt(b,'participants'));
  if(sort==='subject')return txt(a,'subject').localeCompare(txt(b,'subject'));
  if(sort==='count')return Number(b.message_count)-Number(a.message_count)||txt(b,'updated_at').localeCompare(txt(a,'updated_at'));
  const cmp=txt(a,'updated_at').localeCompare(txt(b,'updated_at'))||Number(a.id)-Number(b.id);
  return sort==='oldest'?cmp:-cmp;
 });
 $('msgReviewSortArrow').textContent=sort==='oldest'?'↑':'↓';
 $('msgReviewSortDate').setAttribute('aria-label',sort==='oldest'?'Sort by date and time, newest first':'Sort by date and time, oldest first');
 const size=Number($('msgReviewPageSize').value)||25,total=Math.max(1,Math.ceil(filtered.length/size));
 page=Math.max(1,Math.min(page,total));
 const list=$('msgReviewThreads');list.replaceChildren();
 for(const t of filtered.slice((page-1)*size,page*size)){
  const tr=node('tr');tr.setAttribute('aria-selected',String(Number(selected)===Number(t.id)));
  for(const value of [t.participants||'Participants unavailable',t.subject||'(No subject)']){
   const td=node('td'),button=node('button',value);button.type='button';button.className='msg-cell-select';
   button.addEventListener('click',()=>open(t));td.append(button);tr.append(td);
  }
  const date=node('td'),time=node('time',t.updated_at_display||t.updated_at||'');
  time.className='msg-table-date';date.append(time);tr.append(date);
  tr.append(node('td',t.message_count||0));list.append(tr);
 }
 if(!filtered.length){const tr=node('tr'),td=node('td','No matching conversations.');td.colSpan=4;td.style.padding='12px';tr.append(td);list.append(tr);}
 $('msgReviewCount').textContent=filtered.length+' of '+threads.length+' conversations';
 $('msgReviewPageInfo').textContent='Page '+page+' of '+total;
 $('msgReviewPrev').disabled=page<=1;$('msgReviewNext').disabled=page>=total;
}
async function open(t){
 const token=++requestToken;selected=t.id;render();
 $('msgReviewHeading').textContent='Loading conversation…';
 try{
  const r=await get('thread',{thread_id:t.id});if(token!==requestToken)return;
  $('msgReviewHeading').textContent=(t.participants||'Conversation')+' — '+r.subject;
  const list=$('msgReviewMessages');list.replaceChildren();
  if(!r.messages.length)list.append(node('p','No messages.'));
  for(const m of r.messages){
   const bubble=node('article');bubble.className='msg-bubble';bubble.style.alignSelf='stretch';bubble.style.maxWidth='100%';
   const body=node('p',m.body);body.style.whiteSpace='pre-wrap';
   bubble.append(node('strong',[m.first_name,m.last_name].filter(Boolean).join(' ')),body,node('small',m.created_at_display||m.created_at));
   list.append(bubble);
  }
 }catch(e){if(token!==requestToken)return;$('msgReviewHeading').textContent='Unable to open conversation';$('msgReviewMessages').replaceChildren(node('p',e.message));}
}
async function load(){
 const r=await get('list');threads=Array.isArray(r.threads)?r.threads:[];render();
}
$('msgReviewSearch').addEventListener('input',()=>{page=1;render();});
for(const id of ['msgReviewSort','msgReviewPageSize'])$(id).addEventListener('change',()=>{page=1;render();});
$('msgReviewSortDate').addEventListener('click',()=>{$('msgReviewSort').value=$('msgReviewSort').value==='recent'?'oldest':'recent';page=1;render();});
$('msgReviewPrev').addEventListener('click',()=>{if(page>1){page--;render();}});
$('msgReviewNext').addEventListener('click',()=>{page++;render();});
$('msgReviewRefresh').addEventListener('click',()=>load().catch(e=>$('msgReviewHeading').textContent='Refresh failed: '+e.message));
load().catch(e=>{$('msgReviewHeading').textContent='Unable to load tenant conversations';$('msgReviewMessages').replaceChildren(node('p',e.message));});
})();
