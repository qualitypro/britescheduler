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
 <p id="staffStatus" role="status" aria-live="polite">Loading staff…</p>
 <section id="staffRecords" class="brite-directory-list" aria-label="Tenant staff"></section>
</main>
<script>
(() => {
 'use strict';
 const list=document.getElementById('staffRecords');
 const status=document.getElementById('staffStatus');
 const refresh=document.getElementById('staffRefresh');
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
       const summary=document.createElement('summary');summary.className='brite-directory-summary';
       const main=document.createElement('span');main.className='brite-directory-summary-main';
       const title=document.createElement('strong');title.textContent=[member.first_name,member.last_name].filter(Boolean).join(' ')||'Staff member';
       const subtitle=document.createElement('small');subtitle.textContent=member.email||'';
       main.append(title,subtitle);
       const badge=document.createElement('span');badge.className='brite-directory-state';badge.textContent=member.role+' · '+member.status;
       summary.append(main,badge);details.append(summary);
       const sheet=document.createElement('div');sheet.className='brite-directory-form';
       if(member.role==='owner'){
         const note=document.createElement('p');note.textContent='Owner membership is protected. Edit your own name and email under Business Settings.';sheet.append(note);
       }else{
         const form=document.createElement('form');
         const grid=document.createElement('div');grid.className='brite-directory-fields';
         const roleField=document.createElement('div');roleField.className='brite-directory-field';
         const roleLabel=document.createElement('label');roleLabel.textContent='Tenant role';
         const roleSelect=select('role',roles,member.role);roleLabel.append(roleSelect);roleField.append(roleLabel);
         const statusField=document.createElement('div');statusField.className='brite-directory-field';
         const statusLabel=document.createElement('label');statusLabel.textContent='Membership status';
         const statusSelect=select('status',['active','disabled'],member.status);statusLabel.append(statusSelect);statusField.append(statusLabel);
         grid.append(roleField,statusField);form.append(grid);
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
 refresh.addEventListener('click',load);load();
})();
</script>
<link rel="stylesheet" href="<?=htmlspecialchars(app_url('/assets/css/brite-single-active-records.css'),ENT_QUOTES,'UTF-8')?>">
<script src="<?=htmlspecialchars(app_url('/assets/js/brite-single-active-records.js'),ENT_QUOTES,'UTF-8')?>" defer></script>
<?php require __DIR__.'/partials/app_footer.php'; ?>
