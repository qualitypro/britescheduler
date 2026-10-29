<?php
require_once __DIR__ . '/app/bootstrap.php';
Auth::requireUser();
$pageTitle = 'Activity Hub';
require __DIR__ . '/partials/app_header.php';
?>
<style>
.brite-activity-page{max-width:1250px;margin:auto;padding:18px}.brite-activity-toolbar{display:flex;gap:10px;flex-wrap:wrap;align-items:center;margin:14px 0}.brite-activity-toolbar input,.brite-activity-toolbar select{min-height:40px;padding:8px;border:1px solid #94a3b8;border-radius:8px;background:var(--brite-surface,#fff);color:inherit}.brite-activity-toolbar input{flex:1;min-width:190px}.brite-activity-toolbar button,.brite-activity-page button{min-height:36px;border:1px solid #94a3b8;border-radius:8px;padding:7px 12px;cursor:pointer;background:var(--brite-surface,#fff);color:inherit}.brite-activity-stats{display:flex;gap:10px;flex-wrap:wrap}.brite-activity-stats span{padding:9px 13px;background:var(--brite-surface-muted,#f1f5f9);border-radius:9px;font-weight:650}.brite-activity-record{--accent:#0f766e;border:1px solid #cbd5e1;border-left:5px solid var(--accent);border-radius:10px;padding:11px 14px;margin:8px 0;background:var(--brite-surface,#fff)}.brite-activity-record.marketplace{--accent:#7c3aed}.brite-activity-record.schedule{--accent:#2563eb}.brite-activity-record.warning{--accent:#b45309}.brite-activity-record.unread{background:var(--brite-surface-muted,#f8fafc)}.brite-activity-record header{display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap}.brite-activity-record p{margin:5px 0 8px;font-size:.9rem}.brite-activity-record small{color:var(--accent);font-weight:650}.brite-activity-record .actions{display:flex;gap:8px;flex-wrap:wrap}.brite-activity-record a{display:inline-block;padding:8px 10px;border-radius:7px;background:var(--accent);color:white;text-decoration:none;font-size:.85rem}.brite-activity-record button{font-size:.85rem}.brite-activity-pagination{display:flex;align-items:center;justify-content:space-between;gap:12px;margin:14px 0}.brite-activity-page :is(button,input,select,a):focus-visible{outline:3px solid #2563eb;outline-offset:2px}
</style>
<main class="brite-activity-page">
<header><h1>Activity Hub</h1><p>Find, review and dismiss notifications. Dismissal does not resolve the underlying booking or task.</p></header>
<div class="brite-activity-stats" aria-live="polite"><span id="activityOutstanding">Outstanding: —</span><span id="activityLoaded">Showing: —</span></div>
<div class="brite-activity-toolbar" role="search" aria-label="Filter activity">
<input id="activitySearch" type="search" placeholder="Search titles, messages, or IDs" aria-label="Search notifications">
<select id="activityStatus" aria-label="Notification status"><option value="all">All statuses</option><option value="outstanding">Outstanding</option><option value="dismissed">Dismissed</option></select>
<select id="activityType" aria-label="Notification category"><option value="all">All categories</option><option value="marketplace">Marketplace</option><option value="schedule">Scheduling</option><option value="warning">Attention</option><option value="general">General</option></select>
<button id="activityRefresh" type="button">Refresh</button>
</div>
<p id="activityMessage" role="status" aria-live="polite">Loading activity…</p>
<section id="activityRecords" aria-label="Notifications"></section>
<div class="brite-activity-pagination"><button id="activityPrev" type="button">Previous</button><span id="activityPageLabel"></span><button id="activityNext" type="button">Next</button></div>
</main>
<script>
(() => {
'use strict';
const base=(window.BRITE_BASE||'/dashboard').replace(/\/$/,'');
const el=id=>document.getElementById(id);
const records=el('activityRecords'),message=el('activityMessage');
let offset=0,items=[],hasMore=false,unread=0;
const limit=500;
function category(n){const t=String(n.type||'').toLowerCase();return /marketplace|bid|award/.test(t)?'marketplace':/cancel|fail|declin|reject|overdue|urgent/.test(t)?'warning':/appointment|booking|assign|schedul/.test(t)?'schedule':'general';}
function node(tag,text,cls){const e=document.createElement(tag);if(text!==undefined)e.textContent=String(text);if(cls)e.className=cls;return e;}
function render(){
records.replaceChildren();
const q=el('activitySearch').value.trim().toLowerCase(),st=el('activityStatus').value,ty=el('activityType').value;
const filtered=items.filter(n=>(st==='all'||(st==='outstanding')===(n.read_at===null))&&(ty==='all'||category(n)===ty)&&(!q||[n.title,n.body,n.id,n.appointment_id].some(x=>String(x??'').toLowerCase().includes(q))));
filtered.sort((a,b)=>(a.read_at===null?0:1)-(b.read_at===null?0:1));
el('activityOutstanding').textContent='Outstanding: '+unread;
el('activityLoaded').textContent='Showing: '+filtered.length+' of '+items.length+' loaded';
el('activityPageLabel').textContent='History batch '+(Math.floor(offset/limit)+1);
el('activityPrev').disabled=offset===0;
el('activityNext').disabled=!hasMore;
message.textContent=filtered.length?'':(items.length?'No matches in this batch. Try another filter or the next batch.':'No notifications in this batch.');
for(const n of filtered){
const unreadItem=n.read_at===null,cat=category(n),article=node('article',undefined,'brite-activity-record '+cat+(unreadItem?' unread':''));
const header=node('header'),title=node('strong',n.title||'Notification'),meta=node('small',cat[0].toUpperCase()+cat.slice(1)+' · '+(unreadItem?'Outstanding':'Dismissed'));
header.append(title,meta);
const body=node('p',n.body||''),date=node('small',n.created_at?'Created: '+n.created_at+' UTC':'');
const actions=node('div',undefined,'actions');
const appointmentId=Number(n.appointment_id);
if(Number.isSafeInteger(appointmentId)&&appointmentId>0){
const link=node('a','Review booking');link.href=base+'/scheduling.php?appointment_id='+encodeURIComponent(String(appointmentId));actions.append(link);
if(cat==='marketplace'){const market=node('a','Marketplace');market.href=base+'/bidding.php?appointment_id='+encodeURIComponent(String(appointmentId));actions.append(market);}
}
if(unreadItem){
const dismiss=node('button','Dismiss');dismiss.type='button';
dismiss.addEventListener('click',async()=>{
dismiss.disabled=true;
try{
const res=await fetch(base+'/api/user_notification_read.php',{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json','X-CSRF-Token':window.BRITE_CSRF||''},body:JSON.stringify({notification_id:n.id})});
const data=await res.json();if(!res.ok||data.ok!==true)throw Error(data.error||'Dismiss failed');
n.read_at=new Date().toISOString();unread=Math.max(0,unread-1);render();
}catch(err){dismiss.disabled=false;message.textContent=err.message;}
});actions.append(dismiss);
}
article.append(header,body,date,actions);records.append(article);
}
}
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
