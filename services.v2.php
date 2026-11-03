<?php
require_once __DIR__.'/app/bootstrap.php';
Auth::requireRole('owner','admin','scheduler');

$pageTitle='Services';
require __DIR__.'/partials/app_header.php';
?>

<h1>Services</h1>

<div class="card">
    <h2>Add Service</h2>

    <form id="serviceForm">
        <div class="row">
            <input name="name"
                   placeholder="Service name"
                   required>

            <input name="duration_minutes"
                   type="number"
                   min="1"
                   value="60"
                   placeholder="Duration">

            <input name="price"
                   type="number"
                   min="0"
                   step=".01"
                   value="0.00"
                   placeholder="Price">

            <select name="active">
                <option value="1">Active</option>
                <option value="">Inactive</option>
            </select>

            <button>Add Service</button>
        </div>

        <div class="row">
            <textarea name="description"
                      placeholder="Service description"></textarea>
        </div>

        <p id="serviceError" class="danger"></p>
    </form>
</div>

<div class="card" style="margin-top:18px">
    <div class="brite-list-card-headings" id="serviceSort" role="group" aria-label="Sort services">
<button type="button" class="brite-list-sort" data-sort="name">Service</button>
<button type="button" class="brite-list-sort" data-sort="duration_minutes">Duration</button>
<button type="button" class="brite-list-sort" data-sort="price">Price</button>
<button type="button" class="brite-list-sort" data-sort="active">Status</button>
</div>
    <table>
        <thead>
        <tr>
            <th>Service</th>
            <th>Duration</th>
            <th>Price</th>
            <th>Status</th>
        </tr>
        </thead>
        <tbody id="serviceRows"></tbody>
    </table>
    <div id="servicePagination"></div>
</div>

<script src="<?=htmlspecialchars(app_url('/assets/js/brite-pagination.js'))?>"></script>
<script>
function esc(s){
    return String(s ?? '').replace(/[&<>"']/g,x=>({
        '&':'&amp;',
        '<':'&lt;',
        '>':'&gt;',
        '"':'&quot;',
        "'":'&#039;'
    }[x]));
}

let openId=null, allServices=[];
function serviceDetail(s){
 const id=Number(s.id);
 return '<tr><td colspan="4"><form class="edit-service" data-id="'+id+'">'+
 '<label>Name <input name="name" required maxlength="255" value="'+esc(s.name)+'"></label> '+
 '<label>Duration <input name="duration_minutes" type="number" min="1" max="10080" required value="'+Number(s.duration_minutes)+'"></label> '+
 '<label>Price <input name="price" type="number" min="0" step=".01" required value="'+Number(s.price).toFixed(2)+'"></label> '+
 '<label>Status <select name="active"><option value="1" '+(Number(s.active)?'selected':'')+'>Active</option><option value="0" '+(!Number(s.active)?'selected':'')+'>Inactive</option></select></label>'+
 '<label>Description <textarea name="description">'+esc(s.description||'')+'</textarea></label>'+
 '<p class="danger" role="alert"></p><button type="submit">Save</button> <button type="button" data-cancel>Cancel</button></form></td></tr>';
}
let serviceSortField='name',serviceSortDirection=1;
function refreshServiceRows(){
 const compare=new Intl.Collator(undefined,{numeric:true,sensitivity:'base'});
 const sorted=allServices.slice().sort((a,b)=>{
  const field=serviceSortField,av=a[field],bv=b[field];
  const result=['duration_minutes','price','active'].includes(field)?Number(av||0)-Number(bv||0):compare.compare(String(av??''),String(bv??''));
  return serviceSortDirection*result;
 });
 servicePager.setRecords(sorted);
 document.querySelectorAll('#serviceSort [data-sort]').forEach(button=>{
  const active=button.dataset.sort===serviceSortField;
  button.setAttribute('aria-pressed',String(active));
  button.textContent=button.textContent.replace(/ [▲▼]$/,'')+(active?(serviceSortDirection===1?' ▲':' ▼'):'');
 });
}
document.querySelectorAll('#serviceSort [data-sort]').forEach(button=>button.addEventListener('click',()=>{
 const field=button.dataset.sort;
 serviceSortDirection=field===serviceSortField?-serviceSortDirection:1;
 serviceSortField=field;servicePager.goTo(1);refreshServiceRows();
}));
serviceRows.addEventListener('click',e=>{
 const open=e.target.closest('[data-open]'), cancel=e.target.closest('[data-cancel]');
 if(open && !e.target.closest('button,a,input,textarea,select,label')){const id=Number(open.dataset.open);openId=openId===id?null:id;}
 else if(cancel){openId=null;}
 else return;
 refreshServiceRows();
});
serviceRows.addEventListener('keydown',e=>{
 const row=e.target.closest('tr[data-open]');
 if(row && e.target===row && (e.key==='Enter'||e.key===' ')){
  e.preventDefault();row.click();
 }
});
serviceRows.addEventListener('submit',async e=>{
 if(!e.target.matches('.edit-service'))return;
 e.preventDefault();
 const form=e.target,button=form.querySelector('[type="submit"]');
 button.disabled=true;
 try{
  const data=Object.fromEntries(new FormData(form));data.id=Number(form.dataset.id);
  await api('/api/services.php',{method:'PUT',headers:{'Content-Type':'application/json'},body:JSON.stringify(data)});
  await loadServices();
 }catch(err){form.querySelector('[role="alert"]').textContent=err.message;}
 finally{button.disabled=false;}
});
const servicePager = new BritePagination({
    container: document.getElementById('servicePagination'),
    render(services) {
        serviceRows.innerHTML = services.length
            ? services.map(s => `
                <tr data-open="${Number(s.id)}" tabindex="0" role="button" aria-expanded="${openId===Number(s.id)}" aria-label="Edit ${esc(s.name)}" style="cursor:pointer">
                    <td>
                        <span class="brite-list-flat-icon" role="img" aria-label="Service"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="m4 16 7-7 4 4-7 7H4zM13 7l4-4 4 4-4 4"/></svg></span>
                        <strong>${esc(s.name)}</strong>
                        <div class="muted">${esc(s.description)}</div>
                    </td>
                    <td>${Number(s.duration_minutes)} min</td>
                    <td>$${Number(s.price).toFixed(2)}</td>
                    <td>${Number(s.active) ? 'Active' : 'Inactive'}</td>
                </tr>
                ${openId===Number(s.id)?serviceDetail(s):''}` ).join('')
            : '<tr><td colspan="4">No services found.</td></tr>';
    }
});

async function loadServices(){
    try {
        const d = await api('/api/services.php');
        allServices=d.services;
        refreshServiceRows();
    } catch(e){
        serviceError.textContent = e.message;
    }
}

serviceForm.onsubmit=async e=>{
    e.preventDefault();
    serviceError.textContent='';

    try {
        const d=Object.fromEntries(new FormData(serviceForm));

        await api('/api/services.php',{
            method:'POST',
            headers:{'Content-Type':'application/json'},
            body:JSON.stringify(d)
        });

        serviceForm.reset();
        serviceForm.duration_minutes.value=60;
        serviceForm.price.value='0.00';
        serviceForm.active.value='1';

        await loadServices();

    } catch(e){
        serviceError.textContent=e.message;
    }
};

loadServices();
</script>

<?php require __DIR__.'/partials/app_footer.php'; ?>
