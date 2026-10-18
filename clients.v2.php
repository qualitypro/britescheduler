<?php
require_once __DIR__.'/app/bootstrap.php';
Auth::requireRole('owner','admin','scheduler','accounting');
 $pageTitle='Clients';require __DIR__.'/partials/app_header.php';?>
<h1>Clients</h1><div class="card"><form id="f"><div class="row"><input name="first_name" placeholder="First name" required><input name="last_name" placeholder="Last name" required><input name="company_name" placeholder="Company"><input name="email" type="email" placeholder="Email"><input name="phone" placeholder="Phone"><button>Add client</button></div></form></div>
<div class="card" style="margin-top:18px"><table><thead><tr><th>Name</th><th>Company</th><th>Email</th><th>Phone</th><th>Status</th></tr></thead><tbody id="rows"></tbody></table><div id="clientPagination"></div></div>
<script src="<?=htmlspecialchars(app_url('/assets/js/brite-pagination.js'))?>"></script>
<script>
const clientPager = new BritePagination({
    container: document.getElementById('clientPagination'),
    render(clients) {
        rows.innerHTML = clients.length
            ? clients.map(c => `<tr>
                <td>${esc(c.first_name)} ${esc(c.last_name)}</td>
                <td>${esc(c.company_name || '')}</td>
                <td>${esc(c.email || '')}</td>
                <td>${esc(c.phone || '')}</td>
                <td>${esc(c.status)}</td>
            </tr>`).join('')
            : '<tr><td colspan="5">No clients found.</td></tr>';
    }
});

async function load(){
    const d = await api('/api/clients.php');
    clientPager.setRecords(d.clients);
}
function esc(s){return String(s).replace(/[&<>"']/g,x=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[x]))}
f.onsubmit=async e=>{e.preventDefault();let d=Object.fromEntries(new FormData(f));await api('/api/clients.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(d)});f.reset();load()};load();
</script><?php require __DIR__.'/partials/app_footer.php';?>
