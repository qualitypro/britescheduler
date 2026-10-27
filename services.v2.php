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
    <table>
        <thead>
        <tr>
            <th>Service</th>
            <th>Duration</th>
            <th>Price</th>
            <th>Status</th><th>Manage</th>
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

let openId=null, editId=null, allServices=[];
function serviceDetail(s){
 const id=Number(s.id);
 if(editId!==id) return '<tr><td colspan="5"><p>'+esc(s.description||'No description')+'</p><button type="button" data-edit="'+id+'">Edit</button></td></tr>';
 return '<tr><td colspan="5"><form class="edit-service" data-id="'+id+'">'+
 '<label>Name <input name="name" required maxlength="255" value="'+esc(s.name)+'"></label> '+
 '<label>Duration <input name="duration_minutes" type="number" min="1" max="10080" required value="'+Number(s.duration_minutes)+'"></label> '+
 '<label>Price <input name="price" type="number" min="0" step=".01" required value="'+Number(s.price).toFixed(2)+'"></label> '+
 '<label>Status <select name="active"><option value="1" '+(Number(s.active)?'selected':'')+'>Active</option><option value="0" '+(!Number(s.active)?'selected':'')+'>Inactive</option></select></label>'+
 '<label>Description <textarea name="description">'+esc(s.description||'')+'</textarea></label>'+
 '<p class="danger" role="alert"></p><button type="submit">Save</button> <button type="button" data-cancel>Cancel</button></form></td></tr>';
}
function refreshServiceRows(){servicePager.setRecords(allServices);}
serviceRows.addEventListener('click',e=>{
 const open=e.target.closest('[data-open]'), edit=e.target.closest('[data-edit]'), cancel=e.target.closest('[data-cancel]');
 if(open){const id=Number(open.dataset.open);openId=openId===id?null:id;editId=null;}
 else if(edit){openId=editId=Number(edit.dataset.edit);}
 else if(cancel){editId=null;}
 else return;
 refreshServiceRows();
});
serviceRows.addEventListener('submit',async e=>{
 if(!e.target.matches('.edit-service'))return;
 e.preventDefault();
 const form=e.target,button=form.querySelector('[type="submit"]');
 button.disabled=true;
 try{
  const data=Object.fromEntries(new FormData(form));data.id=Number(form.dataset.id);
  await api('/api/services.php',{method:'PUT',headers:{'Content-Type':'application/json'},body:JSON.stringify(data)});
  editId=null;await loadServices();
 }catch(err){form.querySelector('[role="alert"]').textContent=err.message;}
 finally{button.disabled=false;}
});
const servicePager = new BritePagination({
    container: document.getElementById('servicePagination'),
    render(services) {
        serviceRows.innerHTML = services.length
            ? services.map(s => `
                <tr>
                    <td>
                        <strong>${esc(s.name)}</strong>
                        <div class="muted">${esc(s.description)}</div>
                    </td>
                    <td>${Number(s.duration_minutes)} min</td>
                    <td>$${Number(s.price).toFixed(2)}</td>
                    <td>${Number(s.active) ? 'Active' : 'Inactive'}</td><td><button type="button" data-open="${Number(s.id)}">${openId===Number(s.id)?'Close':'Details'}</button></td>
                </tr>
                ${openId===Number(s.id)?serviceDetail(s):''}` ).join('')
            : '<tr><td colspan="5">No services found.</td></tr>';
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
