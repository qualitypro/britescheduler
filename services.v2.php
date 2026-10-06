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
            <th>Status</th>
        </tr>
        </thead>
        <tbody id="serviceRows"></tbody>
    </table>
</div>

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

async function loadServices(){
    try {
        const d=await api('/api/services.php');

        serviceRows.innerHTML=d.services.map(s=>`
            <tr>
                <td>
                    <strong>${esc(s.name)}</strong>
                    <div class="muted">${esc(s.description)}</div>
                </td>
                <td>${Number(s.duration_minutes)} min</td>
                <td>$${Number(s.price).toFixed(2)}</td>
                <td>${Number(s.active) ? 'Active' : 'Inactive'}</td>
            </tr>
        `).join('');
    } catch(e){
        serviceError.textContent=e.message;
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
