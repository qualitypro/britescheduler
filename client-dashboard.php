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

$pageSize = 15;

$appointmentCountQuery = $pdo->prepare(
    "SELECT COUNT(*)
       FROM appointments
      WHERE tenant_id=?
        AND client_id=?"
);
$appointmentCountQuery->execute([$tid, (int)$client['id']]);
$appointmentCount = (int)$appointmentCountQuery->fetchColumn();

$invoiceSummaryQuery = $pdo->prepare(
    "SELECT COUNT(*) AS invoice_count,
            COALESCE(SUM(balance_due), 0) AS outstanding_balance
       FROM invoices
      WHERE tenant_id=?
        AND client_id=?"
);
$invoiceSummaryQuery->execute([$tid, (int)$client['id']]);
$invoiceSummary = $invoiceSummaryQuery->fetch();

$invoiceCount = (int)$invoiceSummary['invoice_count'];
$outstandingBalance = (float)$invoiceSummary['outstanding_balance'];

$appointmentPages = max(1, (int)ceil($appointmentCount / $pageSize));
$invoicePages = max(1, (int)ceil($invoiceCount / $pageSize));

function clientDashboardRequestedPage(string $key, int $totalPages): int {
    $value = $_GET[$key] ?? '1';
    $page = is_string($value)
        && ctype_digit($value)
        && strlen($value) <= 9
        ? (int)$value
        : 1;

    return min(max(1, $page), $totalPages);
}

$appointmentPage = clientDashboardRequestedPage(
    'appointments_page', $appointmentPages
);
$invoicePage = clientDashboardRequestedPage(
    'invoices_page', $invoicePages
);

$appointmentOffset = ($appointmentPage - 1) * $pageSize;
$invoiceOffset = ($invoicePage - 1) * $pageSize;

$q = $pdo->prepare(
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
     ORDER BY a.starts_at DESC, a.id DESC
     LIMIT ? OFFSET ?"
);
$q->bindValue(1, $tid, PDO::PARAM_INT);
$q->bindValue(2, (int)$client['id'], PDO::PARAM_INT);
$q->bindValue(3, $pageSize, PDO::PARAM_INT);
$q->bindValue(4, $appointmentOffset, PDO::PARAM_INT);
$q->execute();
$appointments = $q->fetchAll();

$q = $pdo->prepare(
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
     ORDER BY created_at DESC, id DESC
     LIMIT ? OFFSET ?"
);
$q->bindValue(1, $tid, PDO::PARAM_INT);
$q->bindValue(2, (int)$client['id'], PDO::PARAM_INT);
$q->bindValue(3, $pageSize, PDO::PARAM_INT);
$q->bindValue(4, $invoiceOffset, PDO::PARAM_INT);
$q->execute();
$invoices = $q->fetchAll();

function clientDashboardPageUrl(
    int $appointmentPage,
    int $invoicePage
): string {
    return '?' . http_build_query([
        'appointments_page' => $appointmentPage,
        'invoices_page' => $invoicePage
    ]);
}

$pageTitle='My Dashboard';

require __DIR__.'/partials/app_header.php';

?>

<h1>Client Dashboard</h1>

<p class="muted">
Welcome,
<?=htmlspecialchars($user['first_name'])?>.
</p>

<div class="grid">

<div class="card">
<div class="muted">Appointments</div>
<div class="metric"><?=$appointmentCount?></div>
</div>

<div class="card">
<div class="muted">Invoices</div>
<div class="metric"><?=$invoiceCount?></div>
</div>

<div class="card">
<div class="muted">Outstanding Balance</div>
<div class="metric">
$<?=number_format($outstandingBalance,2)?>
</div>
</div>

</div>

<div class="card" style="margin-top:18px">

<?php require __DIR__.'/partials/client_booking_wizard.php'; ?>

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

<nav class="brite-pagination"
     aria-label="Appointment pagination"
     style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-top:14px">
    <span>
        Showing <?=$appointmentCount > 0 ? ($appointmentPage-1)*$pageSize+1 : 0?>–<?=min($appointmentPage*$pageSize,$appointmentCount)?>
        of <?=$appointmentCount?>
    </span>
    <div style="display:flex;align-items:center;gap:6px">
        <?php foreach([
            ['«',1,'First page'],
            ['‹',$appointmentPage-1,'Previous page'],
            ['›',$appointmentPage+1,'Next page'],
            ['»',$appointmentPages,'Last page']
        ] as [$symbol,$target,$buttonLabel]): ?>
            <?php $disabled = $appointmentCount === 0 || $target < 1 || $target > $appointmentPages || $target === $appointmentPage; ?>
            <?php if($disabled): ?>
                <button type="button" disabled aria-label="<?=htmlspecialchars($buttonLabel)?>"><?=$symbol?></button>
            <?php else: ?>
                <a href="<?=htmlspecialchars(clientDashboardPageUrl($target,$invoicePage))?>"
                   aria-label="<?=htmlspecialchars($buttonLabel)?>"
                   style="display:inline-block;padding:8px 12px;border:1px solid currentColor;border-radius:6px;text-decoration:none"><?=$symbol?></a>
            <?php endif; ?>
        <?php endforeach; ?>
        <span>Page <?=$appointmentPage?> of <?=$appointmentPages?></span>
    </div>
</nav>



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

<nav class="brite-pagination"
     aria-label="Invoice pagination"
     style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-top:14px">
    <span>
        Showing <?=$invoiceCount > 0 ? ($invoicePage-1)*$pageSize+1 : 0?>–<?=min($invoicePage*$pageSize,$invoiceCount)?>
        of <?=$invoiceCount?>
    </span>
    <div style="display:flex;align-items:center;gap:6px">
        <?php foreach([
            ['«',1,'First page'],
            ['‹',$invoicePage-1,'Previous page'],
            ['›',$invoicePage+1,'Next page'],
            ['»',$invoicePages,'Last page']
        ] as [$symbol,$target,$buttonLabel]): ?>
            <?php $disabled = $invoiceCount === 0 || $target < 1 || $target > $invoicePages || $target === $invoicePage; ?>
            <?php if($disabled): ?>
                <button type="button" disabled aria-label="<?=htmlspecialchars($buttonLabel)?>"><?=$symbol?></button>
            <?php else: ?>
                <a href="<?=htmlspecialchars(clientDashboardPageUrl($appointmentPage,$target))?>"
                   aria-label="<?=htmlspecialchars($buttonLabel)?>"
                   style="display:inline-block;padding:8px 12px;border:1px solid currentColor;border-radius:6px;text-decoration:none"><?=$symbol?></a>
            <?php endif; ?>
        <?php endforeach; ?>
        <span>Page <?=$invoicePage?> of <?=$invoicePages?></span>
    </div>
</nav>



</div>

<script src="<?=htmlspecialchars(app_url('/assets/js/client-booking-wizard.js'), ENT_QUOTES, 'UTF-8')?>"></script>

<?php require __DIR__.'/partials/app_footer.php'; ?>
