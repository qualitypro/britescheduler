<?php
require_once __DIR__ . '/app/bootstrap.php';
Auth::requireUser();
$pageTitle = 'Activity Hub';
require __DIR__ . '/partials/app_header.php';
?>
<style>
.brite-activity-page{max-width:1250px;margin:auto;padding:18px}.brite-activity-toolbar{display:flex;gap:10px;flex-wrap:wrap;align-items:center;margin:14px 0}.brite-activity-toolbar input,.brite-activity-toolbar select{min-height:40px;padding:8px;border:1px solid #94a3b8;border-radius:8px;background:var(--brite-surface,#fff);color:inherit}.brite-activity-toolbar input{flex:1;min-width:190px}.brite-activity-toolbar button,.brite-activity-page button{min-height:36px;border:1px solid #94a3b8;border-radius:8px;padding:7px 12px;cursor:pointer;background:var(--brite-surface,#fff);color:inherit}.brite-activity-stats{display:flex;gap:10px;flex-wrap:wrap}.brite-activity-stats span{padding:9px 13px;background:var(--brite-surface-muted,#f1f5f9);border-radius:9px;font-weight:650}.brite-activity-record{--accent:#0f766e;border:1px solid #cbd5e1;border-left:5px solid var(--accent);border-radius:10px;padding:11px 14px;margin:8px 0;background:var(--brite-surface,#fff)}.brite-activity-record.marketplace{--accent:#7c3aed}.brite-activity-record.schedule{--accent:#2563eb}.brite-activity-record.warning{--accent:#b45309}.brite-activity-record.unread{background:var(--brite-surface-muted,#f8fafc)}.brite-activity-record header{display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap}.brite-activity-record p{margin:5px 0 8px;font-size:.9rem}.brite-activity-record small{color:var(--accent);font-weight:650}.brite-activity-record .actions{display:flex;gap:8px;flex-wrap:wrap}.brite-activity-record a{display:inline-block;padding:8px 10px;border-radius:7px;background:var(--accent);color:white;text-decoration:none;font-size:.85rem}.brite-activity-record button{font-size:.85rem}.brite-activity-record .actions{margin-top:10px}.brite-activity-record .actions .activity-primary{background:var(--accent);color:#fff;border-color:var(--accent)}.brite-activity-record .activity-detail{margin-top:10px;padding:10px 12px;border:1px solid var(--brite-border,#cbd5e1);border-radius:8px;background:var(--brite-surface-muted,#f8fafc)}.brite-activity-record .activity-detail dl{display:grid;grid-template-columns:max-content 1fr;gap:6px 14px;margin:0}.brite-activity-record .activity-detail dt{font-weight:650}.brite-activity-record .activity-detail dd{margin:0;overflow-wrap:anywhere}.brite-activity-record .activity-detail p{white-space:pre-wrap;overflow-wrap:anywhere}.brite-activity-record .actions button[disabled]{opacity:.6;cursor:wait}.brite-activity-pagination{display:flex;align-items:center;justify-content:space-between;gap:12px;margin:14px 0}.brite-activity-page :is(button,input,select,a):focus-visible{outline:3px solid #2563eb;outline-offset:2px}
.brite-activity-record{cursor:pointer}.brite-activity-record:focus-visible{outline:3px solid #2563eb;outline-offset:2px}.activity-modal{border:0;border-radius:12px;padding:0;width:min(640px,calc(100vw - 28px));max-height:min(85vh,820px);background:var(--brite-surface,#fff);color:inherit;box-shadow:0 20px 60px #0005}.activity-modal::backdrop{background:#0f172a99}.activity-modal-inner{padding:20px;display:grid;gap:12px}.activity-modal-head{display:flex;justify-content:space-between;align-items:start;gap:12px}.activity-modal-head h2{margin:0;font-size:1.15rem}.activity-modal-body{white-space:pre-wrap;overflow-wrap:anywhere}.activity-modal-meta{display:grid;grid-template-columns:max-content minmax(0,1fr);gap:6px 12px;font-size:.85rem}.activity-modal-meta dt{font-weight:650}.activity-modal-meta dd{margin:0;overflow-wrap:anywhere}.activity-modal-actions{display:flex;gap:8px;flex-wrap:wrap;border-top:1px solid var(--brite-border,#cbd5e1);padding-top:14px}.activity-modal-actions a,.activity-modal-actions button{display:inline-flex;align-items:center;min-height:36px;padding:8px 12px;border-radius:7px;border:1px solid #94a3b8;text-decoration:none;color:inherit;background:var(--brite-surface,#fff)}.activity-modal-actions .activity-primary{background:#2563eb;color:#fff;border-color:#2563eb}.activity-modal-status{font-size:.83rem;min-height:1em}.activity-modal button{cursor:pointer}
</style>
<main class="brite-activity-page">
<header><h1>Activity Hub</h1><p>Review activity, open related work, and manage notifications. Dismissing an alert does not complete the underlying task.</p></header>
<div class="brite-activity-stats" aria-live="polite"><span id="activityOutstanding">Outstanding: —</span><span id="activityLoaded">Showing: —</span></div>
<div class="brite-activity-toolbar" role="search" aria-label="Filter activity">
<input id="activitySearch" type="search" placeholder="Search titles, messages, or IDs" aria-label="Search notifications">
<select id="activityStatus" aria-label="Notification status"><option value="all">All statuses</option><option value="outstanding">Outstanding</option><option value="dismissed">Dismissed</option></select>
<select id="activityType" aria-label="Notification category"><option value="all">All categories</option><option value="booking_requests">Booking requests</option><option value="marketplace">Marketplace</option><option value="schedule">Scheduling</option><option value="warning">Attention</option><option value="general">General</option></select>
<button id="activityRefresh" type="button">Refresh</button>
</div>
<p id="activityMessage" role="status" aria-live="polite">Loading activity…</p>
<div class="brite-list-card-headings" id="activitySort" role="group" aria-label="Sort activity">
<button type="button" class="brite-list-sort" data-sort="type">Type</button>
<button type="button" class="brite-list-sort" data-sort="title">Record</button>
<button type="button" class="brite-list-sort" data-sort="status">Status</button>
<button type="button" class="brite-list-sort" data-sort="created_at">Created On</button>
<button type="button" class="brite-list-sort" data-sort="updated_at">Last Edited</button>
</div>
<section id="activityRecords" aria-label="Notifications"></section>
<div class="brite-activity-pagination"><button id="activityPrev" type="button">Previous</button><span id="activityPageLabel"></span><button id="activityNext" type="button">Next</button></div>
</main>
<dialog id="activityModal" class="activity-modal" aria-labelledby="activityModalTitle"><div class="activity-modal-inner"><div class="activity-modal-head"><div><h2 id="activityModalTitle"></h2><small id="activityModalState"></small></div><button id="activityModalClose" type="button" aria-label="Close notification">✕</button></div><p id="activityModalBody" class="activity-modal-body"></p><dl id="activityModalMeta" class="activity-modal-meta"></dl><div id="activityModalActions" class="activity-modal-actions"></div><p id="activityModalStatus" class="activity-modal-status" role="status"></p></div></dialog>
<script>
(() => {
'use strict';
const base=(window.BRITE_BASE||'/dashboard').replace(/\/$/,'');
const el=id=>document.getElementById(id);
const records=el('activityRecords'),message=el('activityMessage'),modal=el('activityModal');
let modalNotification=null;
const validId=value=>{const id=Number(value);return Number.isSafeInteger(id)&&id>0?id:null;};
function closeModal(){modal.close();modalNotification=null;}
el('activityModalClose').addEventListener('click',closeModal);
modal.addEventListener('click',event=>{if(event.target===modal)closeModal();});
modal.addEventListener('close',()=>{modalNotification=null;});
async function dismissNotification(n){
 const res=await fetch(base+'/api/user_notification_read.php',{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json','X-CSRF-Token':window.BRITE_CSRF||''},body:JSON.stringify({notification_id:n.id})});
 const data=await res.json();if(!res.ok||data.ok!==true)throw Error(data.error||'Unable to dismiss notification');
 n.read_at=n.read_at||new Date().toISOString();unread=Math.max(0,unread-1);
 render();
}
function showModal(n){
 modalNotification=n;
 el('activityModalTitle').textContent=n.title||'Notification';
 el('activityModalBody').textContent=n.body||'No additional description provided.';
 el('activityModalState').textContent=(n.read_at===null?'Outstanding':'Dismissed')+' · '+(isBookingRequest(n)?'Booking request':category(n));
 const meta=el('activityModalMeta');meta.replaceChildren();
 const add=(label,value)=>{if(value===null||value===undefined||value==='')return;meta.append(node('dt',label),node('dd',value));};
 add('Notification',n.id);add('Appointment',n.appointment_id);add('Client',n.client_name);add('Contractor',n.contractor_name);
 add('Created',n.created_at);add('Updated',n.updated_at);
 const actions=el('activityModalActions');actions.replaceChildren();
 const appointment=validId(n.appointment_id);
 if(appointment){
  const a=node('a',isBookingRequest(n)?'Review booking':'Open appointment','activity-primary');
  a.href=base+'/scheduling.php?appointment_id='+encodeURIComponent(String(appointment));actions.append(a);
  if(category(n)==='marketplace'){
   const m=node('a','Review marketplace');m.href=base+'/bidding.php?appointment_id='+encodeURIComponent(String(appointment));actions.append(m);
  }
 }
 if(n.read_at===null){
  const dismiss=node('button','Dismiss notification');dismiss.type='button';
  dismiss.addEventListener('click',async()=>{
   dismiss.disabled=true;el('activityModalStatus').textContent='Saving…';
   try{await dismissNotification(n);el('activityModalState').textContent='Dismissed';dismiss.remove();el('activityModalStatus').textContent='Notification dismissed. The underlying task was not changed.';}
   catch(error){dismiss.disabled=false;el('activityModalStatus').textContent=error.message;}
  });actions.append(dismiss);
 }
 if(!appointment){const info=node('p','No linked appointment is available for this notification.');actions.append(info);}
 el('activityModalStatus').textContent='';
 if(!modal.open)modal.showModal();
}
let offset=0,items=[],hasMore=false,unread=0;
const limit=500;
let activitySortField='created_at',activitySortDirection=-1;
const activityCollator=new Intl.Collator(undefined,{numeric:true,sensitivity:'base'});
function category(n){const t=String(n.type||'').toLowerCase();return /marketplace|bid|award/.test(t)?'marketplace':/cancel|fail|declin|reject|overdue|urgent/.test(t)?'warning':/appointment|booking|assign|schedul/.test(t)?'schedule':'general';}
function isBookingRequest(n){const t=String(n.type||'').toLowerCase();const title=String(n.title||'').trim().toLowerCase();return /booking_request|client_booking_request|appointment_request|booking_requested/.test(t)||(t==='appointment'&&title==='new client booking request');}
function node(tag,text,cls){const e=document.createElement(tag);if(text!==undefined)e.textContent=String(text);if(cls)e.className=cls;return e;}
function render(){
records.replaceChildren();
const q=el('activitySearch').value.trim().toLowerCase(),st=el('activityStatus').value,ty=el('activityType').value;
const filtered=items.filter(n=>(st==='all'||(st==='outstanding')===(n.read_at===null))&&(ty==='all'||(ty==='booking_requests'?isBookingRequest(n):category(n)===ty))&&(!q||[n.title,n.body,n.id,n.appointment_id].some(x=>String(x??'').toLowerCase().includes(q))));
filtered.sort((a,b)=>{
 const field=activitySortField;
 const val=n=>field==='type'?category(n):field==='status'?(n.read_at===null?'Outstanding':'Dismissed'):n[field];
 const result=activityCollator.compare(String(val(a)??''),String(val(b)??''));
 return activitySortDirection*result;
});
el('activityOutstanding').textContent='Outstanding: '+unread;
el('activityLoaded').textContent='Showing: '+filtered.length+' of '+items.length+' loaded';
el('activityPageLabel').textContent='History batch '+(Math.floor(offset/limit)+1);
el('activityPrev').disabled=offset===0;
el('activityNext').disabled=!hasMore;
message.textContent=filtered.length?'':(items.length?'No matches in this batch. Try another filter or the next batch.':'No notifications in this batch.');
for(const n of filtered){
const unreadItem=n.read_at===null,cat=category(n),article=node('article',undefined,'brite-activity-record '+cat+(unreadItem?' unread':''));
const header=node('header'),title=node('strong',n.title||'Notification'),meta=node('small',cat[0].toUpperCase()+cat.slice(1)+' · '+(unreadItem?'Outstanding':'Dismissed'));
const icon=node('span',undefined,'brite-list-flat-icon');icon.setAttribute('role','img');icon.setAttribute('aria-label',cat+' activity');
const paths={marketplace:'<path d="M4 7h16v14H4zM8 7V4h8v3"/>',schedule:'<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M7 3v4M17 3v4M3 10h18"/>',warning:'<path d="M12 3 2 21h20zM12 9v5M12 18h.01"/>',general:'<rect x="4" y="3" width="16" height="18" rx="2"/><path d="M8 8h8M8 12h8"/>'};
icon.innerHTML='<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round">'+(paths[cat]||paths.general)+'</svg>';
header.append(icon,title,meta);
article.tabIndex=0;article.setAttribute('role','button');article.setAttribute('aria-label','Review '+(n.title||'notification'));
article.addEventListener('click',event=>{if(event.target.closest('button,a'))return;showModal(n);});
article.addEventListener('keydown',event=>{if((event.key==='Enter'||event.key===' ')&&event.target===article){event.preventDefault();showModal(n);}});
const body=node('p',n.body||'');
const readable=value=>{if(!value)return '';const raw=String(value),d=new Date(raw.includes('T')?raw:raw.replace(' ','T')+'Z');return Number.isNaN(d.getTime())?'':new Intl.DateTimeFormat(undefined,{dateStyle:'medium',timeStyle:'short'}).format(d);};
const date=node('small',[n.created_at?'Created on: '+readable(n.created_at):'',n.updated_at?'Last edited: '+readable(n.updated_at):''].filter(Boolean).join(' · '));
const people=[n.client_name?'Client: '+n.client_name:null,n.contractor_name?'Contractor: '+n.contractor_name:null].filter(Boolean);
const peopleLine=people.length?node('p',people.join(' · ')):null;
const actions=node('div',undefined,'actions');
const detail=node('div',undefined,'activity-detail');detail.hidden=true;
const details=node('dl');
const addDetail=(label,value)=>{if(value===null||value===undefined||value==='')return;details.append(node('dt',label),node('dd',value));};
addDetail('Category',isBookingRequest(n)?'Booking request':cat[0].toUpperCase()+cat.slice(1));
addDetail('Notification ID',n.id);
addDetail('Appointment ID',n.appointment_id);
addDetail('Client',n.client_name);
addDetail('Contractor',n.contractor_name);
addDetail('Created',readable(n.created_at));
addDetail('Updated',readable(n.updated_at));
detail.append(details);
if(n.body){detail.append(node('p',n.body));}
const inspect=node('button','Review');inspect.type='button';inspect.setAttribute('aria-expanded','false');
inspect.addEventListener('click',()=>showModal(n));
actions.append(inspect);
const appointmentId=Number(n.appointment_id);
if(Number.isSafeInteger(appointmentId)&&appointmentId>0){
const link=node('a',isBookingRequest(n)?'Open booking':'Open appointment');link.className='activity-primary';link.href=base+'/scheduling.php?appointment_id='+encodeURIComponent(String(appointmentId));actions.append(link);
if(cat==='marketplace'){const market=node('a','Marketplace');market.href=base+'/bidding.php?appointment_id='+encodeURIComponent(String(appointmentId));actions.append(market);}
}
if(unreadItem){
const dismiss=node('button','Dismiss');dismiss.type='button';
dismiss.addEventListener('click',async()=>{dismiss.disabled=true;try{await dismissNotification(n);}catch(err){dismiss.disabled=false;message.textContent=err.message;}});actions.append(dismiss);
}
article.append(header,body);
if(peopleLine)article.append(peopleLine);
article.append(date,actions,detail);records.append(article);
}
}
document.querySelectorAll('#activitySort [data-sort]').forEach(button=>button.addEventListener('click',()=>{
 const field=button.dataset.sort;
 activitySortDirection=field===activitySortField?-activitySortDirection:1;activitySortField=field;
 document.querySelectorAll('#activitySort [data-sort]').forEach(b=>{
  const active=b.dataset.sort===field;
  b.setAttribute('aria-pressed',String(active));
  b.textContent=b.textContent.replace(/ [▲▼]$/,'')+(active?(activitySortDirection===1?' ▲':' ▼'):'');
 });
 render();
}));
async function load(){
message.textContent='Loading notifications…';
try{
const res=await fetch(base+'/api/user_notifications.php?all=1&offset='+offset,{credentials:'same-origin',cache:'no-store'});
if(!res.ok)throw Error('HTTP '+res.status);
const data=await res.json();if(!data.ok||!Array.isArray(data.notifications))throw Error('Invalid response');
items=data.notifications;unread=Number(data.unread_count)||0;hasMore=items.length===limit;render();
}catch(err){message.textContent='Unable to load notifications: '+err.message;}
}
for(const id of ['activitySearch','activityStatus','activityType'])el(id).addEventListener(id==='activitySearch'?'input':'change',render);
el('activityRefresh').addEventListener('click',load);
el('activityPrev').addEventListener('click',()=>{offset=Math.max(0,offset-limit);load();});
el('activityNext').addEventListener('click',()=>{offset+=limit;load();});
load();
})();
</script>
<?php require __DIR__ . '/partials/app_footer.php'; ?>
