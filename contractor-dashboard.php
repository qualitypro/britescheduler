<?php

require_once __DIR__.'/app/bootstrap.php';

$user = Auth::requireUser();
$tenant = Auth::requireRole('contractor');
$tid = (int)$tenant['tenant_id'];

$pdo = Database::connection();

$q=$pdo->prepare(
    "SELECT *
       FROM contractors
      WHERE tenant_id=?
        AND user_id=?
      LIMIT 1"
);

$q->execute([
    $tid,
    (int)$user['id']
]);

$contractor=$q->fetch();

if(!$contractor){
    http_response_code(403);
    exit('Contractor profile not found.');
}

$pageTitle='Contractor Dashboard';

require __DIR__.'/partials/app_header.php';

$q=$pdo->prepare(
    "SELECT
        a.id,
        a.title,
        a.starts_at,
        a.ends_at,
        a.status,
        ac.status AS assignment_status,
        c.first_name AS client_first_name,
        c.last_name AS client_last_name,
        s.name AS service_name
     FROM appointment_contractors ac
     JOIN appointments a
       ON a.id=ac.appointment_id
      AND a.tenant_id=?
     LEFT JOIN clients c
       ON c.id=a.client_id
      AND c.tenant_id=a.tenant_id
     LEFT JOIN services s
       ON s.id=a.service_id
      AND s.tenant_id=a.tenant_id
     WHERE ac.contractor_id=?
     ORDER BY a.starts_at DESC
     LIMIT 30"
);

$q->execute([
    $tid,
    (int)$contractor['id']
]);

$appointments=$q->fetchAll();

?>

<h1>Contractor Dashboard</h1>

<p class="muted">
Welcome,
<?=htmlspecialchars($user['first_name'])?>.
</p>

<div class="grid">

<div class="card">
<div class="muted">Assigned Appointments</div>
<div class="metric"><?=count($appointments)?></div>
</div>

<div class="card">
<div class="muted">Business</div>
<div style="font-size:20px;font-weight:700">
<?=htmlspecialchars(
    $contractor['business_name']
    ?: $contractor['first_name'].' '.$contractor['last_name']
)?>
</div>
</div>

</div>

<div class="card" style="margin-top:18px">

<h2>My Schedule</h2>

<?php if(!$appointments): ?>

<p class="muted">No appointments assigned yet.</p>

<?php else: ?>

<div style="overflow-x:auto">

<table>

<thead>
<tr>
<th>Service</th>
<th>Appointment</th>
<th>Client</th>
<th>Start</th>
<th>End</th>
<th>Assignment</th>
</tr>
</thead>

<tbody>

<?php foreach($appointments as $a): ?>

<tr>

<td>
<?=htmlspecialchars($a['service_name'] ?? '')?>
</td>

<td>
<?=htmlspecialchars($a['title'] ?? '')?>
</td>

<td>
<?=htmlspecialchars(trim(
    ($a['client_first_name'] ?? '') .
    ' ' .
    ($a['client_last_name'] ?? '')
))?>
</td>

<td>
<?=htmlspecialchars($a['starts_at'])?>
</td>

<td>
<?=htmlspecialchars($a['ends_at'])?>
</td>

<td>
<?=htmlspecialchars($a['assignment_status'])?>
</td>

</tr>

<?php endforeach; ?>

</tbody>

</table>

</div>

<?php endif; ?>

</div>

<?php require __DIR__.'/partials/app_footer.php'; ?>
