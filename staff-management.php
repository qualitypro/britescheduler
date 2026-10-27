<?php
declare(strict_types=1);
require_once __DIR__.'/app/bootstrap.php';
Auth::requireRole('owner');
$pageTitle='Staff Management';
require __DIR__.'/partials/app_header.php';
?>
<link rel="stylesheet" href="<?=htmlspecialchars(app_url('/assets/css/brite-directory-accordion.css'),ENT_QUOTES,'UTF-8')?>">
<main class="brite-directory">
 <div class="brite-directory-header"><div><h1>Staff Management</h1><p class="muted">Review tenant staff and expand a record to manage its membership. Owner accounts are protected and read-only.</p></div><button type="button" id="staffRefresh">Refresh</button></div>
 <button type="button" id="staffAddToggle">Add existing account to staff</button>
 <section id="staffAddPanel" class="card" hidden>
  <h2>Add staff membership</h2>
  <p class="muted">The person must already have an active Brite Scheduler account. Adding a membership grants access to this tenant.</p>
  <form id="staffAddForm">
   <div class="row">
    <label>Email <input name="email" type="email" maxlength="190" autocomplete="off" required></label>
    <label>Staff role <select name="role" required><option value="scheduler">Scheduler</option><option value="accounting">Accounting</option><option value="admin">Administrator</option></select></label>
   </div>
   <button type="submit">Add staff membership</button>
   <button type="button" id="staffAddCancel">Cancel</button>
   <p id="staffAddStatus" role="status" aria-live="polite"></p>
  </form>
 </section>
 <button type="button" id="inviteToggle">Invite new staff</button>
 <section id="invitePanel" class="card" hidden>
  <h2>Invite staff member</h2>
  <p class="muted">Create a seven-day invitation. Share the generated link privately with the intended recipient; the link grants access to the selected role when accepted.</p>
  <form id="inviteForm">
   <div class="row"><label>First name <input name="first_name" maxlength="100" required></label>
   <label>Last name <input name="last_name" maxlength="100" required></label></div>
   <div class="row"><label>Email <input name="email" type="email" maxlength="190" required></label>
   <label>Role <select name="role"><option value="scheduler">Scheduler</option><option value="accounting">Accounting</option><option value="admin">Administrator</option></select></label></div>
   <button type="submit">Create invitation</button><button type="button" id="inviteCancel">Cancel</button>
  </form>
  <p id="inviteFeedback" role="status"></p>
  <div id="inviteLinkContainer" hidden><label>Private invitation link <input id="inviteLink" readonly></label><button type="button" id="inviteCopy">Copy link</button></div>
 </section>
 <h2>Invitations</h2><section id="inviteRecords" aria-label="Staff invitations"></section>
 <p id="staffStatus" role="status" aria-live="polite">Loading staff…</p>
 <section id="staffRecords" class="brite-directory-list" aria-label="Tenant staff"></section>
</main>
<script>
(() => {
 'use strict';
 const list=document.getElementById('staffRecords');
 const status=document.getElementById('staffStatus');
 const refresh=document.getElementById('staffRefresh');
 const addToggle=document.getElementById('staffAddToggle');
 const addPanel=document.getElementById('staffAddPanel');
 const addForm=document.getElementById('staffAddForm');
 const addStatus=document.getElementById('staffAddStatus');
 function closeAdd(){addPanel.hidden=true;addForm.reset();addStatus.textContent='';}
 addToggle.addEventListener('click',()=>{
   list.querySelectorAll('details[open]').forEach(record=>record.open=false);
   closeInvite();addPanel.hidden=false;addForm.elements.namedItem('email').focus();
 });
 document.getElementById('staffAddCancel').addEventListener('click',closeAdd);
 addForm.addEventListener('submit',async event=>{
   event.preventDefault();
   const submit=addForm.querySelector('[type="submit"]');
   submit.disabled=true;addStatus.textContent='Adding staff…';
   try{
     const data=Object.fromEntries(new FormData(addForm));
     await api('/api/staff_memberships.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(data)});
     closeAdd();await load();
     status.textContent='Staff membership added';
   }catch(error){addStatus.textContent='Unable to add staff: '+error.message;}
   finally{submit.disabled=false;}
 });
 const inviteToggle=document.getElementById('inviteToggle');
 const invitePanel=document.getElementById('invitePanel');
 const inviteForm=document.getElementById('inviteForm');
 const inviteFeedback=document.getElementById('inviteFeedback');
 const inviteLinkContainer=document.getElementById('inviteLinkContainer');
 function closeInvite(){invitePanel.hidden=true;inviteForm.reset();inviteFeedback.textContent='';inviteLinkContainer.hidden=true;}
 inviteToggle.addEventListener('click',()=>{
   closeAdd();list.querySelectorAll('details[open]').forEach(d=>d.open=false);
   invitePanel.hidden=false;inviteForm.elements.namedItem('first_name').focus();
 });
 document.getElementById('inviteCancel').addEventListener('click',closeInvite);
 document.getElementById('inviteCopy').addEventListener('click',async()=>{
   const link=document.getElementById('inviteLink');
   try{await navigator.clipboard.writeText(link.value);inviteFeedback.textContent='Link copied';}
   catch(e){link.select();inviteFeedback.textContent='Select and copy the link manually';}
 });
 inviteForm.addEventListener('submit',async event=>{
   event.preventDefault();const button=inviteForm.querySelector('[type="submit"]');
   button.disabled=true;inviteFeedback.textContent='Creating invitation…';
   try{
     const data=await api('/api/staff_invitations.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(Object.fromEntries(new FormData(inviteForm)))});
     document.getElementById('inviteLink').value=data.invitation_url;
     inviteLinkContainer.hidden=false;inviteFeedback.textContent='Invitation created. Share the link privately.';
     await loadInvitations();
   }catch(error){inviteFeedback.textContent=error.message;}
   finally{button.disabled=false;}
 });
 async function loadInvitations(){
   const host=document.getElementById('inviteRecords');
   try{
     const data=await api('/api/staff_invitations.php');
     host.replaceChildren();
     for(const item of data.invitations||[]){
       const row=document.createElement('div');row.className='brite-directory-record';
       const label=document.createElement('span');
       label.textContent=item.first_name+' '+item.last_name+' · '+item.email+' · '+item.role+' · '+item.status+' · expires '+item.expires_at;
       row.append(label);
       if(item.status==='pending'){
         const revoke=document.createElement('button');revoke.type='button';revoke.textContent='Revoke';
         revoke.addEventListener('click',async()=>{
           if(!confirm('Revoke this invitation?'))return;
           revoke.disabled=true;
           try{await api('/api/staff_invitations.php',{method:'DELETE',headers:{'Content-Type':'application/json'},body:JSON.stringify({id:Number(item.id)})});await loadInvitations();}
           catch(error){status.textContent='Unable to revoke invitation: '+error.message;revoke.disabled=false;}
         });
         row.append(revoke);
       }
       host.append(row);
     }
     if(!host.children.length)host.textContent='No invitations yet.';
   }catch(error){host.textContent='Unable to load invitations: '+error.message;}
 }
 const roles=['admin','scheduler','accounting'];
 function select(name,values,selected){
   const el=document.createElement('select');el.name=name;
   values.forEach(value=>{const option=document.createElement('option');option.value=value;option.textContent=value;option.selected=value===selected;el.append(option);});
   return el;
 }
 async function load(){
   refresh.disabled=true;status.textContent='Loading staff…';
   try{
     const result=await api('/api/staff_memberships.php');
     list.replaceChildren();
     for(const member of result.members||[]){
       const details=document.createElement('details');details.className='brite-directory-record';
       details.addEventListener('toggle',()=>{
         if(!details.open)return;
         closeAdd();closeInvite();
         list.querySelectorAll('details[open]').forEach(other=>{if(other!==details)other.open=false;});
       });
       const summary=document.createElement('summary');summary.className='brite-directory-summary';
       const main=document.createElement('span');main.className='brite-directory-summary-main';
       const title=document.createElement('strong');title.textContent=[member.first_name,member.last_name].filter(Boolean).join(' ')||'Staff member';
       const subtitle=document.createElement('small');subtitle.textContent=member.email||'';
       main.append(title,subtitle);
       const badge=document.createElement('span');badge.className='brite-directory-state';badge.textContent=member.role+' · '+member.status;
       summary.append(main,badge);details.append(summary);
       const sheet=document.createElement('div');sheet.className='brite-directory-form';
       if(member.role==='owner'){
         const note=document.createElement('p');note.textContent='Owner membership is protected. Edit your own profile in Business Settings.';sheet.append(note);
         const ownerLink=document.createElement('a');ownerLink.href=window.BRITE_BASE+'/settings.php';ownerLink.textContent='Open Business Settings';sheet.append(ownerLink);
       }else{
         const form=document.createElement('form');
         const grid=document.createElement('div');grid.className='brite-directory-fields';
         const roleField=document.createElement('div');roleField.className='brite-directory-field';
         const roleLabel=document.createElement('label');roleLabel.textContent='Tenant role';
         const roleSelect=select('role',roles,member.role);roleLabel.append(roleSelect);roleField.append(roleLabel);
         const statusField=document.createElement('div');statusField.className='brite-directory-field';
         const statusLabel=document.createElement('label');statusLabel.textContent='Membership status';
         const statusSelect=select('status',['active','disabled'],member.status);statusLabel.append(statusSelect);statusField.append(statusLabel);
         const emailInfo=document.createElement('p');emailInfo.textContent='Account email: '+member.email+' (account profile is managed separately)';
         form.append(emailInfo);grid.append(roleField,statusField);form.append(grid);
         const actions=document.createElement('div');actions.className='brite-directory-actions';
         const save=document.createElement('button');save.type='submit';save.textContent='Save membership';
         const reset=document.createElement('button');reset.type='reset';reset.textContent='Reset';
         const feedback=document.createElement('span');feedback.setAttribute('role','status');
         actions.append(save,reset,feedback);form.append(actions);
         form.addEventListener('submit',async event=>{
           event.preventDefault();save.disabled=true;feedback.textContent='Saving…';
           try{
             await api('/api/staff_memberships.php',{method:'PUT',headers:{'Content-Type':'application/json'},body:JSON.stringify({id:Number(member.id),role:roleSelect.value,status:statusSelect.value})});
             member.role=roleSelect.value;member.status=statusSelect.value;
             badge.textContent=member.role+' · '+member.status;
             feedback.textContent='Membership saved';
           }catch(error){feedback.textContent='Save failed: '+error.message;}
           finally{save.disabled=false;}
         });
         sheet.append(form);
       }
       details.append(sheet);list.append(details);
     }
     if(!list.children.length){const p=document.createElement('p');p.textContent='No staff members found.';list.append(p);}
     status.textContent=(result.members||[]).length+' staff account(s)';
   }catch(error){status.textContent='Unable to load staff: '+error.message;}
   finally{refresh.disabled=false;}
 }
 refresh.addEventListener('click',()=>{load();loadInvitations();});load();loadInvitations();
})();
</script>
<link rel="stylesheet" href="<?=htmlspecialchars(app_url('/assets/css/brite-single-active-records.css'),ENT_QUOTES,'UTF-8')?>">
<script src="<?=htmlspecialchars(app_url('/assets/js/brite-single-active-records.js'),ENT_QUOTES,'UTF-8')?>" defer></script>
<?php require __DIR__.'/partials/app_footer.php'; ?>
