<?php
require_once __DIR__.'/app/bootstrap.php';
$user=Auth::requireUser();
$tenant=Auth::requireRole('client','owner','admin','scheduler');
$tid=(int)$tenant['tenant_id'];
$pdo=Database::connection();
if ($tenant['role']==='client') {
    $q=$pdo->prepare('SELECT id,first_name,last_name FROM clients WHERE tenant_id=? AND user_id=? LIMIT 1');
    $q->execute([$tid,(int)$user['id']]);
    $clients=$q->fetchAll();
} else {
    $q=$pdo->prepare('SELECT id,first_name,last_name FROM clients WHERE tenant_id=? ORDER BY last_name,first_name');
    $q->execute([$tid]);
    $clients=$q->fetchAll();
}
$pageTitle='Client Locations';
require __DIR__.'/partials/app_header.php';
?>
<h1>Client Locations</h1>
<p class="muted">Manage service addresses for each client. Coordinates and map display will follow in WBS 1.7.</p>
<div class="card">
<label for="client">Client</label>
<select id="client">
<?php foreach($clients as $c): ?>
<option value="<?= (int)$c['id'] ?>"><?=htmlspecialchars($c['first_name'].' '.$c['last_name'],ENT_QUOTES,'UTF-8')?></option>
<?php endforeach; ?>
</select>
<p id="message" role="status"></p>
<div id="locations"></div>
</div>
<div class="card" style="margin-top:18px">
<h2 id="formTitle">Add Location</h2>
<form id="locationForm">
<input type="hidden" name="id" id="locationId">
<div class="row"><input name="label" placeholder="Location name (Home, Office...)" maxlength="120" required><input name="address1" placeholder="Street address" maxlength="190" required></div>
<div class="row"><input name="address2" placeholder="Suite / unit" maxlength="190"><input name="city" placeholder="City" maxlength="100" required></div>
<div class="row"><input name="state" placeholder="State" maxlength="100" required><input name="postal_code" placeholder="ZIP / postal code" maxlength="30" required></div>
<div class="row"><textarea name="service_instructions" placeholder="Service instructions (optional)" maxlength="5000"></textarea></div>
<label><input type="checkbox" name="is_primary" value="1"> Primary location</label>
<div style="margin-top:14px"><button type="submit">Save Location</button> <button type="button" id="resetForm">Clear</button></div>
</form>
</div>
<script>
const clientSelect=document.getElementById('client'), list=document.getElementById('locations'), form=document.getElementById('locationForm'), msg=document.getElementById('message');
let locations=[];
function say(s){msg.textContent=s;}
function escapeHtml(s){return String(s??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));}
async function refresh(){
 if(!clientSelect.value){list.textContent='No client profile available.';return;}
 try {const j=await api('/api/client_locations.php?client_id='+encodeURIComponent(clientSelect.value));locations=j.locations;
 list.innerHTML=locations.length?locations.map(x=>`<div style="padding:12px;border-bottom:1px solid #ddd"><b>${escapeHtml(x.label)}</b> ${Number(x.is_primary)?'(Primary)':''}<p>${escapeHtml([x.address1,x.address2,x.city,x.state,x.postal_code].filter(Boolean).join(', '))}</p><button type="button" data-edit="${x.id}">Edit</button> <button type="button" data-delete="${x.id}">Delete</button></div>`).join(''):'No locations saved.';
 }catch(e){say(e.message);}
}
function clearForm(){form.reset();document.getElementById('locationId').value='';document.getElementById('formTitle').textContent='Add Location';}
document.getElementById('resetForm').onclick=clearForm;
clientSelect.onchange=()=>{say('');clearForm();refresh();};
list.addEventListener('click',async e=>{
 const edit=e.target.closest('[data-edit]'), del=e.target.closest('[data-delete]');
 if(edit){const x=locations.find(x=>String(x.id)===edit.dataset.edit);if(!x)return;clearForm();for(const [k,v] of Object.entries(x)){const el=form.elements.namedItem(k);if(el&&k!=='is_primary')el.value=v??'';}form.elements.namedItem('is_primary').checked=Number(x.is_primary)===1;document.getElementById('formTitle').textContent='Edit Location';form.scrollIntoView({behavior:'smooth'});}
 if(del&&confirm('Delete this service location?')){try{await api('/api/client_locations.php',{method:'DELETE',headers:{'Content-Type':'application/json'},body:JSON.stringify({id:Number(del.dataset.delete),client_id:Number(clientSelect.value)})});say('Location deleted');clearForm();await refresh();}catch(err){say(err.message);}}
});
form.onsubmit=async e=>{e.preventDefault();const d=Object.fromEntries(new FormData(form).entries());d.client_id=Number(clientSelect.value);d.is_primary=form.elements.namedItem('is_primary').checked?1:0;const method=d.id?'PUT':'POST';if(!d.id)delete d.id;try{await api('/api/client_locations.php',{method,headers:{'Content-Type':'application/json'},body:JSON.stringify(d)});say('Location saved');clearForm();await refresh();}catch(err){say(err.message);}};
refresh();
</script>
<?php require __DIR__.'/partials/app_footer.php'; ?>
