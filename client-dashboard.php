<?php

require_once __DIR__.'/app/bootstrap.php';

$user = Auth::requireUser();
$tenant = Auth::requireRole('client');
$tid = (int)$tenant['tenant_id'];

$pdo = Database::connection();

$q=$pdo->prepare(
    "SELECT *
       FROM clients
      WHERE tenant_id=?
        AND user_id=?
      LIMIT 1"
);

$q->execute([
    $tid,
    (int)$user['id']
]);

$client=$q->fetch();

if(!$client){
    http_response_code(403);
    exit('Client profile not found.');
}

$pageTitle='My Dashboard';

require __DIR__.'/partials/app_header.php';

$q=$pdo->prepare(
    "SELECT
        a.id,
        a.title,
        a.starts_at,
        a.ends_at,
        a.status,
        s.name AS service_name
     FROM appointments a
     LEFT JOIN services s
       ON s.id=a.service_id
      AND s.tenant_id=a.tenant_id
     WHERE a.tenant_id=?
       AND a.client_id=?
     ORDER BY a.starts_at DESC
     LIMIT 20"
);

$q->execute([$tid,(int)$client['id']]);

$appointments=$q->fetchAll();

$q=$pdo->prepare(
    "SELECT
        id,
        invoice_number,
        status,
        total,
        balance_due,
        due_at
     FROM invoices
     WHERE tenant_id=?
       AND client_id=?
     ORDER BY created_at DESC
     LIMIT 20"
);

$q->execute([$tid,(int)$client['id']]);

$invoices=$q->fetchAll();

?>

<h1>Client Dashboard</h1>

<p class="muted">
Welcome,
<?=htmlspecialchars($user['first_name'])?>.
</p>

<div class="grid">

<div class="card">
<div class="muted">Appointments</div>
<div class="metric"><?=count($appointments)?></div>
</div>

<div class="card">
<div class="muted">Invoices</div>
<div class="metric"><?=count($invoices)?></div>
</div>

<div class="card">
<div class="muted">Outstanding Balance</div>
<div class="metric">
$<?=number_format(array_sum(array_map(
    fn($x)=>(float)$x['balance_due'],
    $invoices
)),2)?>
</div>
</div>

</div>

<div class="card" style="margin-top:18px">

<h2>My Appointments</h2>

<?php if(!$appointments): ?>

<p class="muted">No appointments yet.</p>

<?php else: ?>

<div style="overflow-x:auto">

<table>
<thead>
<tr>
<th>Service</th>
<th>Appointment</th>
<th>Start</th>
<th>Status</th>
</tr>
</thead>

<tbody>

<?php foreach($appointments as $a): ?>

<tr>
<td><?=htmlspecialchars($a['service_name'] ?? '')?></td>
<td><?=htmlspecialchars($a['title'] ?? '')?></td>
<td><?=htmlspecialchars($a['starts_at'])?></td>
<td><?=htmlspecialchars($a['status'])?></td>
</tr>

<?php endforeach; ?>

</tbody>
</table>

</div>

<?php endif; ?>

</div>


<div class="card" style="margin-top:18px">

<h2>My Invoices</h2>

<?php if(!$invoices): ?>

<p class="muted">No invoices yet.</p>

<?php else: ?>

<div style="overflow-x:auto">

<table>
<thead>
<tr>
<th>Invoice</th>
<th>Status</th>
<th>Total</th>
<th>Balance</th>
<th>Due</th>
</tr>
</thead>

<tbody>

<?php foreach($invoices as $i): ?>

<tr>
<td><?=htmlspecialchars($i['invoice_number'])?></td>
<td><?=htmlspecialchars($i['status'])?></td>
<td>$<?=number_format((float)$i['total'],2)?></td>
<td>$<?=number_format((float)$i['balance_due'],2)?></td>
<td><?=htmlspecialchars($i['due_at'] ?? '')?></td>
</tr>

<?php endforeach; ?>

</tbody>
</table>

</div>

<?php endif; ?>

</div>

<?php require __DIR__.'/partials/app_footer.php'; ?>
