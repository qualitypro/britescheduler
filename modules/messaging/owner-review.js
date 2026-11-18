(()=>{
'use strict';
const root=document.getElementById('msgOwnerReview');if(!root)return;
const $=id=>document.getElementById(id),base=(window.BRITE_BASE||'')+'/modules/messaging/owner_review.php';
const node=(tag,text)=>{const e=document.createElement(tag);if(text!==undefined)e.textContent=String(text??'');return e;};
let threads=[],page=1,total=0,pages=1,selected=null,requestToken=0,loadToken=0,searchTimer=null,beforeId=null,activeThread=null;
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
function showMessages(messages,prepend=false){
 const list=$('msgReviewMessages'),fragment=document.createDocumentFragment();
 for(const m of messages){
  const bubble=node('article');bubble.className='msg-bubble';bubble.style.alignSelf='stretch';bubble.style.maxWidth='100%';
  const body=node('p',m.body);body.style.whiteSpace='pre-wrap';
  bubble.append(node('strong',[m.first_name,m.last_name].filter(Boolean).join(' ')),body,node('small',m.created_at_display||m.created_at));
  fragment.append(bubble);
 }
 if(prepend)list.prepend(fragment);else list.replaceChildren(fragment);
 if(!list.children.length)list.append(node('p','No messages.'));
}
async function older(){
 if(!activeThread||!beforeId)return;
 const token=requestToken,threadId=activeThread.id;
 const button=$('msgReviewOlder');button.disabled=true;button.textContent='Loading…';
 try{
  const r=await get('thread',{thread_id:threadId,before_id:beforeId});
  if(token!==requestToken)return;
  showMessages(r.messages,true);beforeId=r.next_before_id;
  button.hidden=!r.has_more;
 }catch(e){if(token===requestToken)$('msgReviewContext').textContent='Unable to load older messages: '+e.message;}
 finally{button.disabled=false;button.textContent='Load older messages';}
}
async function open(t){
 const token=++requestToken;selected=t.id;activeThread=t;beforeId=null;render();
 $('msgReviewDetail').hidden=false;
 $('msgReviewHeading').textContent='Loading conversation…';
 $('msgReviewContext').replaceChildren(node('span',t.participants||''));
 $('msgReviewOlder').hidden=true;
 $('msgReviewMessages').replaceChildren();
 try{
  const r=await get('thread',{thread_id:t.id});if(token!==requestToken)return;
  $('msgReviewHeading').textContent=r.subject||'(No subject)';
  const context=$('msgReviewContext');context.replaceChildren();
  for(const participant of r.participants||[]){
   const card=node('span');card.className='msg-review-participant';
   card.append(node('strong',[participant.first_name,participant.last_name].filter(Boolean).join(' ')),node('small',participant.role));
   context.append(card);
  }
  showMessages(r.messages);
  beforeId=r.next_before_id;$('msgReviewOlder').hidden=!r.has_more;
 }catch(e){if(token!==requestToken)return;$('msgReviewHeading').textContent='Unable to open conversation';$('msgReviewMessages').replaceChildren(node('p',e.message));}
}
async function loadSummary(){
 try{
  const r=await get('summary'),m=r.summary||{};
  $('msgReviewTotalMetric').textContent=Number(m.conversations||0).toLocaleString();
  $('msgReviewActiveMetric').textContent=Number(m.active_week||0).toLocaleString();
  $('msgReviewMessagesMetric').textContent=Number(m.messages||0).toLocaleString();
 }catch(e){$('msgReviewTotalMetric').textContent='—';$('msgReviewActiveMetric').textContent='—';$('msgReviewMessagesMetric').textContent='—';}
}
async function load(){
 const token=++loadToken;
 $('msgReviewCount').textContent='Loading conversations…';
 const r=await get('list',{page,page_size:$('msgReviewPageSize').value,sort:$('msgReviewSort').value,search:$('msgReviewSearch').value.trim(),role:$('msgReviewRole').value,period:$('msgReviewPeriod').value});
 if(token!==loadToken)return;
 threads=Array.isArray(r.threads)?r.threads:[];total=Number(r.total)||0;
 page=Number(r.page)||1;pages=Number(r.pages)||1;render();
}
function reload(){load().catch(e=>{$('msgReviewCount').textContent='Unable to load conversations: '+e.message;});}
$('msgReviewSearch').addEventListener('input',()=>{page=1;clearTimeout(searchTimer);searchTimer=setTimeout(reload,250);});
for(const id of ['msgReviewSort','msgReviewPageSize','msgReviewRole','msgReviewPeriod'])$(id).addEventListener('change',()=>{page=1;reload();});
$('msgReviewSortDate').addEventListener('click',()=>{$('msgReviewSort').value=$('msgReviewSort').value==='recent'?'oldest':'recent';page=1;reload();});
$('msgReviewPrev').addEventListener('click',()=>{if(page>1){page--;reload();}});
$('msgReviewNext').addEventListener('click',()=>{if(page<pages){page++;reload();}});
$('msgReviewRefresh').addEventListener('click',()=>{reload();loadSummary();});
$('msgReviewReset').addEventListener('click',()=>{$('msgReviewSearch').value='';$('msgReviewRole').value='all';$('msgReviewPeriod').value='all';$('msgReviewSort').value='recent';page=1;reload();});
$('msgReviewClose').addEventListener('click',()=>{++requestToken;selected=null;activeThread=null;beforeId=null;$('msgReviewDetail').hidden=true;render();});
$('msgReviewOlder').addEventListener('click',older);
reload();loadSummary();
})();
