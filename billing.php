<?php $pageTitle='Billing';require __DIR__.'/partials/app_header.php';?>
<h1>Billing & Payments</h1><div class="grid"><div class="card"><h2>Invoices</h2><table><thead><tr><th>#</th><th>Client</th><th>Status</th><th>Total</th><th>Due</th></tr></thead><tbody id="inv"></tbody></table></div><div class="card"><h2>Payments</h2><table><thead><tr><th>Client</th><th>Amount</th><th>Status</th><th>Date</th></tr></thead><tbody id="pay"></tbody></table></div></div>
<script>
Promise.all([api('/api/invoices.php'),api('/api/payments.php')]).then(([a,b])=>{inv.innerHTML=a.invoices.map(x=>`<tr><td>${x.invoice_number}</td><td>${x.client_name}</td><td>${x.status}</td><td>$${Number(x.total).toFixed(2)}</td><td>$${Number(x.balance_due).toFixed(2)}</td></tr>`).join('');pay.innerHTML=b.payments.map(x=>`<tr><td>${x.client_name}</td><td>$${Number(x.amount).toFixed(2)}</td><td>${x.status}</td><td>${x.paid_at||''}</td></tr>`).join('')});
</script><?php require __DIR__.'/partials/app_footer.php';?>
