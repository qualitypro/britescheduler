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

$pageSize = 15;

$countQuery = $pdo->prepare(
    "SELECT COUNT(*)
       FROM appointment_contractors ac
       JOIN appointments a
         ON a.id=ac.appointment_id
        AND a.tenant_id=?
      WHERE ac.contractor_id=?"
);

$countQuery->execute([
    $tid,
    (int)$contractor['id']
]);

$appointmentCount = (int)$countQuery->fetchColumn();
$totalPages = max(1, (int)ceil($appointmentCount / $pageSize));

$requestedPage = $_GET['schedule_page'] ?? '1';

$schedulePage = is_string($requestedPage)
    && ctype_digit($requestedPage)
    && strlen($requestedPage) <= 9
    ? (int)$requestedPage
    : 1;

$schedulePage = min(max(1, $schedulePage), $totalPages);
$offset = ($schedulePage - 1) * $pageSize;

$q = $pdo->prepare(
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
     ORDER BY a.starts_at DESC, a.id DESC
     LIMIT ? OFFSET ?"
);

$q->bindValue(1, $tid, PDO::PARAM_INT);
$q->bindValue(2, (int)$contractor['id'], PDO::PARAM_INT);
$q->bindValue(3, $pageSize, PDO::PARAM_INT);
$q->bindValue(4, $offset, PDO::PARAM_INT);
$q->execute();

$appointments = $q->fetchAll();

$pageTitle='Contractor Dashboard';

require __DIR__.'/partials/app_header.php';

?>

<h1>Contractor Dashboard</h1>

<p class="muted">
Welcome,
<?=htmlspecialchars($user['first_name'])?>.
</p>

<div class="grid">

<div class="card">
<div class="muted">Assigned Appointments</div>
<div class="metric"><?=$appointmentCount?></div>
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

<nav class="brite-pagination"
     aria-label="Schedule pagination"
     style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-top:14px">
    <span>
        Showing <?=$appointmentCount > 0 ? ($schedulePage-1)*$pageSize+1 : 0?>–<?=min($schedulePage*$pageSize,$appointmentCount)?>
        of <?=$appointmentCount?>
    </span>
    <div style="display:flex;align-items:center;gap:6px">
        <?php foreach([
            ['«',1,'First page'],
            ['‹',$schedulePage-1,'Previous page'],
            ['›',$schedulePage+1,'Next page'],
            ['»',$totalPages,'Last page']
        ] as [$symbol,$target,$label]): ?>
            <?php
                $disabled = $appointmentCount === 0 || $target < 1
                    || $target > $totalPages
                    || $target === $schedulePage;
            ?>
            <?php if($disabled): ?>
                <button type="button"
                        disabled
                        aria-label="<?=htmlspecialchars($label)?>">
                    <?=$symbol?>
                </button>
            <?php else: ?>
                <a href="?schedule_page=<?=$target?>"
                   aria-label="<?=htmlspecialchars($label)?>"
                   style="display:inline-block;padding:8px 12px;border:1px solid currentColor;border-radius:6px;text-decoration:none">
                    <?=$symbol?>
                </a>
            <?php endif; ?>
        <?php endforeach; ?>
        <span>Page <?=$schedulePage?> of <?=$totalPages?></span>
    </div>
</nav>



</div>

<?php require __DIR__.'/partials/app_footer.php'; ?>
