<?php $pageTitle='Dashboard';require __DIR__.'/partials/app_header.php';?>
<h1>Operations Dashboard</h1><div id="metrics" class="grid"></div>
<div class="card" style="margin-top:18px"><h2>System scope</h2><p class="muted">Tenant-isolated clients, contractors, scheduling, invoices and payments are active. Payment records are ledger entries; connect a PCI-compliant payment provider for card processing.</p></div>
<script>
api('/api/dashboard.php').then(d=>{let m=[['Active clients',d.clients],['Active contractors',d.contractors],['Appointments today',d.appointments_today],['Next 7 days',d.appointments_week],['Receivables','$'+Number(d.receivables).toFixed(2)],['Payments this month','$'+Number(d.payments_month).toFixed(2)]];document.querySelector('#metrics').innerHTML=m.map(x=>`<div class="card"><div class="muted">${x[0]}</div><div class="metric">${x[1]}</div></div>`).join('')});
</script><?php require __DIR__.'/partials/app_footer.php';?>
