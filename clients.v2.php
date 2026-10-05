<?php $pageTitle='Clients';require __DIR__.'/partials/app_header.php';?>
<h1>Clients</h1><div class="card"><form id="f"><div class="row"><input name="first_name" placeholder="First name" required><input name="last_name" placeholder="Last name" required><input name="company_name" placeholder="Company"><input name="email" type="email" placeholder="Email"><input name="phone" placeholder="Phone"><button>Add client</button></div></form></div>
<div class="card" style="margin-top:18px"><table><thead><tr><th>Name</th><th>Company</th><th>Email</th><th>Phone</th><th>Status</th></tr></thead><tbody id="rows"></tbody></table></div>
<script>
async function load(){let d=await api('/api/clients.php');rows.innerHTML=d.clients.map(c=>`<tr><td>${esc(c.first_name)} ${esc(c.last_name)}</td><td>${esc(c.company_name||'')}</td><td>${esc(c.email||'')}</td><td>${esc(c.phone||'')}</td><td>${esc(c.status)}</td></tr>`).join('')}
function esc(s){return String(s).replace(/[&<>"']/g,x=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[x]))}
f.onsubmit=async e=>{e.preventDefault();let d=Object.fromEntries(new FormData(f));await api('/api/clients.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(d)});f.reset();load()};load();
</script><?php require __DIR__.'/partials/app_footer.php';?>
