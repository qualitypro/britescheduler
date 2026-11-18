(()=>{
'use strict';
const root=document.getElementById('msgOwnerReview');if(!root)return;
const $=id=>document.getElementById(id),base=(window.BRITE_BASE||'')+'/modules/messaging/owner_review.php';
const node=(tag,text)=>{const e=document.createElement(tag);if(text!==undefined)e.textContent=String(text??'');return e;};
let threads=[],page=1,total=0,pages=1,selected=null,requestToken=0,loadToken=0,searchTimer=null;
async function get(action,params={}){
 const url=new URL(base,location.href);url.searchParams.set('action',action);
 for(const [key,value] of Object.entries(params))url.searchParams.set(key,String(value));
 const r=await fetch(url,{credentials:'same-origin',headers:{Accept:'application/json'}});
 let j;try{j=await r.json();}catch{throw Error('Invalid server response');}
 if(!r.ok)throw Error(j.error||'Unable to load conversation');return j;
}
function render(){
 const sort=$('msgReviewSort').value;
 $('msgReviewSortArrow').textContent=sort==='oldest'?'↑':'↓';
 $('msgReviewSortDate').setAttribute('aria-label',sort==='oldest'?'Sort by date and time, newest first':'Sort by date and time, oldest first');
 const list=$('msgReviewThreads');list.replaceChildren();
 for(const t of threads){
  const tr=node('tr');tr.setAttribute('aria-selected',String(Number(selected)===Number(t.id)));
  for(const value of [t.participants||'Participants unavailable',t.subject||'(No subject)']){
   const td=node('td'),button=node('button',value);button.type='button';button.className='msg-cell-select';
   button.addEventListener('click',()=>open(t));td.append(button);tr.append(td);
  }
  const date=node('td'),time=node('time',t.updated_at_display||t.updated_at||'');
  time.className='msg-table-date';date.append(time);tr.append(date);
  tr.append(node('td',t.message_count||0));list.append(tr);
 }
 if(!threads.length){const tr=node('tr'),td=node('td','No matching conversations.');td.colSpan=4;td.style.padding='12px';tr.append(td);list.append(tr);}
 const from=total?(page-1)*Number($('msgReviewPageSize').value)+1:0;
 const to=total?from+threads.length-1:0;
 $('msgReviewCount').textContent=from+'–'+to+' of '+total+' tenant conversations';
 $('msgReviewPageInfo').textContent='Page '+page+' of '+pages;
 $('msgReviewPrev').disabled=page<=1;$('msgReviewNext').disabled=page>=pages;
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
 const token=++loadToken;
 $('msgReviewCount').textContent='Loading conversations…';
 const r=await get('list',{page,page_size:$('msgReviewPageSize').value,sort:$('msgReviewSort').value,search:$('msgReviewSearch').value.trim()});
 if(token!==loadToken)return;
 threads=Array.isArray(r.threads)?r.threads:[];total=Number(r.total)||0;
 page=Number(r.page)||1;pages=Number(r.pages)||1;render();
}
function reload(){load().catch(e=>{$('msgReviewCount').textContent='Unable to load conversations: '+e.message;});}
$('msgReviewSearch').addEventListener('input',()=>{page=1;clearTimeout(searchTimer);searchTimer=setTimeout(reload,250);});
for(const id of ['msgReviewSort','msgReviewPageSize'])$(id).addEventListener('change',()=>{page=1;reload();});
$('msgReviewSortDate').addEventListener('click',()=>{$('msgReviewSort').value=$('msgReviewSort').value==='recent'?'oldest':'recent';page=1;reload();});
$('msgReviewPrev').addEventListener('click',()=>{if(page>1){page--;reload();}});
$('msgReviewNext').addEventListener('click',()=>{if(page<pages){page++;reload();}});
$('msgReviewRefresh').addEventListener('click',reload);
reload();
})();
