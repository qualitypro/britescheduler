<?php
require_once __DIR__ . '/app/bootstrap.php';
Auth::requireUser();
$pageTitle = 'Activity Hub';
require __DIR__ . '/partials/app_header.php';
?>
<style>
.brite-activity-page{max-width:1250px;margin:auto;padding:18px}.brite-activity-toolbar{display:flex;gap:10px;flex-wrap:wrap;align-items:center;margin:14px 0}.brite-activity-toolbar input,.brite-activity-toolbar select{min-height:40px;padding:8px;border:1px solid #94a3b8;border-radius:8px;background:var(--brite-surface,#fff);color:inherit}.brite-activity-toolbar input{flex:1;min-width:190px}.brite-activity-toolbar button,.brite-activity-page button{min-height:36px;border:1px solid #94a3b8;border-radius:8px;padding:7px 12px;cursor:pointer;background:var(--brite-surface,#fff);color:inherit}.brite-activity-stats{display:flex;gap:10px;flex-wrap:wrap}.brite-activity-stats span{padding:9px 13px;background:var(--brite-surface-muted,#f1f5f9);border-radius:9px;font-weight:650}.brite-activity-record{--accent:#0f766e;border:1px solid #cbd5e1;border-left:5px solid var(--accent);border-radius:10px;padding:11px 14px;margin:8px 0;background:var(--brite-surface,#fff)}.brite-activity-record.marketplace{--accent:#7c3aed}.brite-activity-record.schedule{--accent:#2563eb}.brite-activity-record.warning{--accent:#b45309}.brite-activity-record.unread{background:var(--brite-surface-muted,#f8fafc)}.brite-activity-record header{display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap}.brite-activity-record p{margin:5px 0 8px;font-size:.9rem}.brite-activity-record small{color:var(--accent);font-weight:650}.brite-activity-record .actions{display:flex;gap:8px;flex-wrap:wrap}.brite-activity-record a{display:inline-block;padding:8px 10px;border-radius:7px;background:var(--accent);color:white;text-decoration:none;font-size:.85rem}.brite-activity-record button{font-size:.85rem}.brite-activity-record .actions{margin-top:10px}.brite-activity-record .actions .activity-primary{background:var(--accent);color:#fff;border-color:var(--accent)}.brite-activity-record .activity-detail{margin-top:10px;padding:10px 12px;border:1px solid var(--brite-border,#cbd5e1);border-radius:8px;background:var(--brite-surface-muted,#f8fafc)}.brite-activity-record .activity-detail dl{display:grid;grid-template-columns:max-content 1fr;gap:6px 14px;margin:0}.brite-activity-record .activity-detail dt{font-weight:650}.brite-activity-record .activity-detail dd{margin:0;overflow-wrap:anywhere}.brite-activity-record .activity-detail p{white-space:pre-wrap;overflow-wrap:anywhere}.brite-activity-record .actions button[disabled]{opacity:.6;cursor:wait}.brite-activity-pagination{display:flex;align-items:center;justify-content:space-between;gap:12px;margin:14px 0}.brite-activity-page :is(button,input,select,a):focus-visible{outline:3px solid #2563eb;outline-offset:2px}
.brite-activity-record{cursor:pointer}.brite-activity-record:focus-visible{outline:3px solid #2563eb;outline-offset:2px}.activity-modal{border:0;border-radius:12px;padding:0;width:min(640px,calc(100vw - 28px));max-height:min(85vh,820px);background:var(--brite-surface,#fff);color:inherit;box-shadow:0 20px 60px #0005}.activity-modal::backdrop{background:#0f172a99}.activity-modal-inner{padding:20px;display:grid;gap:12px}.activity-modal-head{display:flex;justify-content:space-between;align-items:start;gap:12px}.activity-modal-head h2{margin:0;font-size:1.15rem}.activity-modal-body{white-space:pre-wrap;overflow-wrap:anywhere}.activity-modal-meta{display:grid;grid-template-columns:max-content minmax(0,1fr);gap:6px 12px;font-size:.85rem}.activity-modal-meta dt{font-weight:650}.activity-modal-meta dd{margin:0;overflow-wrap:anywhere}.activity-modal-actions{display:flex;gap:8px;flex-wrap:wrap;border-top:1px solid var(--brite-border,#cbd5e1);padding-top:14px}.activity-modal-actions a,.activity-modal-actions button{display:inline-flex;align-items:center;min-height:36px;padding:8px 12px;border-radius:7px;border:1px solid #94a3b8;text-decoration:none;color:inherit;background:var(--brite-surface,#fff)}.activity-modal-actions .activity-primary{background:#2563eb;color:#fff;border-color:#2563eb}.activity-modal-status{font-size:.83rem;min-height:1em}.activity-modal button{cursor:pointer}
.activity-modal .activity-enrollment{display:grid;gap:12px;padding:16px;border:1px solid var(--brite-border,#cbd5e1);border-radius:10px;background:var(--brite-surface-muted,#f8fafc);width:100%;box-sizing:border-box}.activity-enrollment .activity-enrollment-heading{display:flex;justify-content:space-between;gap:8px;align-items:center;flex-wrap:wrap}.activity-enrollment .activity-enrollment-heading strong{font-size:1.05rem}.activity-enrollment .activity-enrollment-badge{border-radius:20px;padding:4px 10px;background:#dbeafe;color:#1e40af;font-weight:700;font-size:.8rem}.activity-enrollment dl{display:grid;grid-template-columns:110px minmax(0,1fr);gap:8px 12px;margin:0}.activity-enrollment dt{font-weight:700}.activity-enrollment dd{margin:0;overflow-wrap:anywhere}.activity-enrollment .activity-enrollment-note{padding:10px;border-radius:7px;background:var(--brite-surface,#fff);white-space:pre-wrap}.activity-enrollment .activity-enrollment-buttons{display:flex;gap:8px;flex-wrap:wrap}.activity-enrollment .activity-enrollment-buttons button{padding:9px 14px}.activity-enrollment textarea{width:100%;box-sizing:border-box;padding:10px;border-radius:7px;border:1px solid #94a3b8;background:var(--brite-surface,#fff);color:inherit}.activity-modal-actions{align-items:flex-start}.activity-modal-actions>.activity-enrollment{flex-basis:100%}
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
function enrollmentApplicationId(n){
 if(String(n.type||'')!=='enrollment.submitted')return null;
 const match=String(n.body||'').match(/(?:contractor|client) application #([1-9][0-9]*) submitted for review\./i);
 return match?validId(match[1]):null;
}
function showModal(n){
 modalNotification=n;
 el('activityModalTitle').textContent=n.title||'Notification';
 el('activityModalBody').textContent=n.body||'No additional description provided.';
 el('activityModalState').textContent=(n.read_at===null?'Unread':'Read')+' · '+(isBookingRequest(n)?'Booking request':category(n));
 const meta=el('activityModalMeta');meta.replaceChildren();
 const add=(label,value)=>{if(value===null||value===undefined||value==='')return;meta.append(node('dt',label),node('dd',value));};
 add('Notification',n.id);add('Appointment',n.appointment_id);add('Client',n.client_name);add('Contractor',n.contractor_name);
 add('Created',n.created_at);add('Updated',n.updated_at);
 const actions=el('activityModalActions');actions.replaceChildren();
 const appointment=validId(n.appointment_id);
 const enrollmentId=enrollmentApplicationId(n);
 if(enrollmentId){
  const workflow=node('section',undefined,'activity-enrollment');workflow.setAttribute('aria-label','Enrollment application workflow');
  workflow.append(node('p','Loading enrollment workflow…'));
  actions.append(workflow);
  const url=base+'/api/notification_workflows.php?application_id='+encodeURIComponent(String(enrollmentId));
  fetch(url,{credentials:'same-origin',cache:'no-store'}).then(async res=>{
   const data=await res.json();if(!res.ok||!data.ok)throw Error(data.error||'Workflow unavailable');
   if(modalNotification!==n)return;
   workflow.replaceChildren();
   const a=data.application;
   const heading=node('div',undefined,'activity-enrollment-heading');
   heading.append(node('strong',a.full_name||'Applicant'),node('span',String(a.status||'Unknown').toUpperCase(),'activity-enrollment-badge'));
   workflow.append(heading);
   const details=node('dl');
   const field=(label,value)=>{if(value!==null&&value!==undefined&&String(value).trim()!=='')details.append(node('dt',label),node('dd',value));};
   field('Application','#'+a.id);field('Type',a.applicant_type);field('Email',a.email);field('Phone',a.phone);
   field('Submitted',a.created_at);field('Reviewed',a.reviewed_at);
   workflow.append(details);
   if(a.notes)workflow.append(node('p','Applicant notes: '+a.notes,'activity-enrollment-note'));
   if(a.decision_note)workflow.append(node('p','Decision note: '+a.decision_note,'activity-enrollment-note'));
   workflow.append(node('p',data.next_step));
   if(a.blocked)workflow.append(node('p','This applicant is blocked. Approval is unavailable.'));
   if(data.actions.length){
    const note=node('textarea');note.placeholder='Optional review note';note.maxLength=1000;note.rows=2;note.setAttribute('aria-label','Review note');
    workflow.append(note);
    const buttons=node('div');buttons.className='activity-enrollment-buttons';
    for(const decision of data.actions){
     const button=node('button',decision==='approved'?'Approve application':'Reject application',decision==='approved'?'activity-primary':'');
     button.type='button';if(decision==='approved'&&a.blocked)button.disabled=true;
     button.addEventListener('click',async()=>{
      if(!window.confirm(decision==='approved'?'Approve this application? An activation invitation is still required.':'Reject this application? This decision cannot be reversed here.'))return;
      buttons.querySelectorAll('button').forEach(b=>b.disabled=true);
      el('activityModalStatus').textContent='Saving decision…';
      try{
       const response=await fetch(base+'/api/notification_workflows.php',{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json','X-CSRF-Token':window.BRITE_CSRF||''},body:JSON.stringify({application_id:enrollmentId,decision,decision_note:note.value})});
       const result=await response.json();if(!response.ok||!result.ok)throw Error(result.error||'Review failed');
       el('activityModalStatus').textContent='Decision saved. '+result.next_step;
       showModal(n);
      }catch(error){el('activityModalStatus').textContent=error.message;buttons.querySelectorAll('button').forEach(b=>b.disabled=decision==='approved'&&a.blocked);}
     });buttons.append(button);
    }
    workflow.append(buttons);
   }
  }).catch(error=>{if(modalNotification===n)workflow.replaceChildren(node('p','Unable to load enrollment workflow: '+error.message));});

  const review=node('a','Open enrollment management');
  review.href=base+'/enrollment.php?application_id='+encodeURIComponent(String(enrollmentId));
  actions.append(review);
 }
 if(appointment){
  const panel=node('section',undefined,'activity-enrollment');
  panel.setAttribute('aria-label','Linked appointment details');
  panel.append(node('p','Loading appointment status and details…'));
  actions.append(panel);
  fetch(base+'/api/notification_appointment_detail.php?appointment_id='+encodeURIComponent(String(appointment)),{credentials:'same-origin',cache:'no-store'})
   .then(async response=>{
    const result=await response.json();
    if(!response.ok||!result.ok)throw Error(result.error||'Appointment unavailable');
    if(modalNotification!==n)return;
    panel.replaceChildren();
    const heading=node('div',undefined,'activity-enrollment-heading');
    heading.append(node('strong','Appointment #'+result.record_id),node('span',result.status,'activity-enrollment-badge'));
    panel.append(heading);
    const details=node('dl');
    for(const field of result.fields||[])details.append(node('dt',field.label),node('dd',field.value));
    panel.append(details);
    panel.append(node('p','Use Scheduling to complete available appointment actions according to your role and the current appointment state.'));
   }).catch(error=>{if(modalNotification===n)panel.replaceChildren(node('p','Appointment details unavailable: '+error.message));});
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
   try{await dismissNotification(n);el('activityModalState').textContent='Read';dismiss.remove();el('activityModalStatus').textContent='Notification dismissed. The underlying task was not changed.';}
   catch(error){dismiss.disabled=false;el('activityModalStatus').textContent=error.message;}
  });actions.append(dismiss);
 }
 const billingEvent=/\b(bill(?:ing)?|invoice|payment|receivable)\b/i.test([n.type,n.title,n.body].join(' '));
 if(billingEvent){
  const invoiceMatch=([n.title,n.body].join(' ')).match(/\binvoice\s*(?:#|number\s*[:#]?\s*)([1-9][0-9]*)\b/i);
  const syntheticBilling=/\b(synthetic|demo|test notification)\b/i.test([n.title,n.body].join(' '));
  const invoiceId=!syntheticBilling&&invoiceMatch?Number(invoiceMatch[1]):null;
  const billingLink=node('a','Open billing workspace','activity-primary');
  billingLink.href=base+'/billing.php';
  actions.append(billingLink);
  if(invoiceId){
   const invoiceLink=node('a','Open invoice #'+invoiceId);
   invoiceLink.href=base+'/invoice.php?id='+encodeURIComponent(String(invoiceId));
   invoiceLink.hidden=true;
   actions.append(invoiceLink);
   const panel=node('section',undefined,'activity-enrollment');
   panel.append(node('p','Loading invoice status…'));actions.append(panel);
   fetch(base+'/api/invoices.php?id='+encodeURIComponent(String(invoiceId)),{credentials:'same-origin',cache:'no-store'})
    .then(async response=>{
     const result=await response.json();
     if(!response.ok||!result.invoice)throw Error(result.error||'Invoice unavailable');
     if(modalNotification!==n)return;
     const invoice=result.invoice;
     invoiceLink.hidden=false;
     panel.replaceChildren();
     const heading=node('div',undefined,'activity-enrollment-heading');
     heading.append(node('strong','Invoice '+(invoice.invoice_number||'#'+invoiceId)),node('span',String(invoice.status||'Unknown'),'activity-enrollment-badge'));
     panel.append(heading);
     const details=node('dl');
     for(const [label,value] of [['Client',invoice.client_name],['Total',invoice.total],['Paid',invoice.amount_paid],['Balance due',invoice.balance_due],['Due',invoice.due_at]]){
      if(value!==null&&value!==undefined&&String(value)!=='')details.append(node('dt',label),node('dd',String(value)));
     }
     panel.append(details);
     panel.append(node('p','Open the invoice to use its existing authorized payment, send, and void controls.'));
    }).catch(error=>{if(modalNotification===n)panel.replaceChildren(node('p','Invoice details unavailable: '+error.message));});
  }
 }
 if(!appointment&&!enrollmentId){
  const info=node('section',undefined,'activity-enrollment');
  const type=String(n.type||'general');
  const synthetic=/\b(synthetic|demo|test notification)\b/i.test(String(n.title||'')+' '+String(n.body||''));
  const heading=node('div',undefined,'activity-enrollment-heading');
  heading.append(node('strong',synthetic?'Demo / test event':'Notification details'),node('span',synthetic?'TEST EVENT':'INFORMATIONAL','activity-enrollment-badge'));
  info.append(heading);
  const fields=node('dl');
  const field=(label,value)=>{if(value!==null&&value!==undefined&&String(value).trim()!=='')fields.append(node('dt',label),node('dd',value));};
  field('Event type',type);field('Notification','#'+n.id);field('Notification status',n.read_at===null?'Unread':'Read');
  field('Created',n.created_at);field('Description',n.body);if(billingEvent&&!invoiceId)field('Related invoice','None verified');
  info.append(fields);
  info.append(node('p',synthetic?'This is a demo / test notification. No invoice or payment is associated with it. Use Open billing workspace to review real billing records.':'This event has no verified related record. Its details are available here; business actions require a linked record.'));
  actions.append(info);
 }
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
const header=node('header'),title=node('strong',n.title||'Notification'),meta=node('small',cat[0].toUpperCase()+cat.slice(1)+' · '+(unreadItem?'Unread':'Read'));
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
const target=new URLSearchParams(window.location.search).get('notification_id');
if(target&&/^[1-9][0-9]*$/.test(target)){
 const found=items.find(n=>String(n.id)===target);
 if(found){showModal(found);history.replaceState(null,'',window.location.pathname);}
 else if(hasMore){offset+=limit;await load();}
 else{message.textContent='Notification #'+target+' was not found in your activity history.';}
}
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
