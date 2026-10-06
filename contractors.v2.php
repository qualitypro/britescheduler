<?php
require_once __DIR__.'/app/bootstrap.php';
Auth::requireRole('owner','admin','scheduler');
 $pageTitle='Contractors';require __DIR__.'/partials/app_header.php';?>
<h1>Contractors</h1><div class="card"><form id="f"><div class="row"><input name="first_name" placeholder="First name" required><input name="last_name" placeholder="Last name" required><input name="business_name" placeholder="Business"><input name="email" type="email" placeholder="Email"><input name="phone" placeholder="Phone"><input name="hourly_rate" type="number" step=".01" placeholder="Hourly rate"><button>Add contractor</button></div></form></div>
<div class="card" style="margin-top:18px"><table><thead><tr><th>Name</th><th>Business</th><th>Email</th><th>Phone</th><th>Rate</th></tr></thead><tbody id="rows"></tbody></table></div>
<script>
function esc(s){return String(s??'').replace(/[&<>"']/g,x=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[x]))}
async function load(){let d=await api('/api/contractors.php');rows.innerHTML=d.contractors.map(c=>`<tr><td>${esc(c.first_name)} ${esc(c.last_name)}</td><td>${esc(c.business_name)}</td><td>${esc(c.email)}</td><td>${esc(c.phone)}</td><td>${c.hourly_rate?'$'+Number(c.hourly_rate).toFixed(2):''}</td></tr>`).join('')}
f.onsubmit=async e=>{e.preventDefault();let d=Object.fromEntries(new FormData(f));await api('/api/contractors.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(d)});f.reset();load()};load();
</script><?php require __DIR__.'/partials/app_footer.php';?>
