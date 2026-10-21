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

$upcomingQuery = $pdo->prepare(
    "SELECT
        a.id,
        a.title,
        a.starts_at,
        a.ends_at,
        a.status,
        a.location,
        s.name AS service_name,
        cl.label AS location_label
     FROM appointments a
     LEFT JOIN services s
       ON s.id = a.service_id
      AND s.tenant_id = a.tenant_id
     LEFT JOIN client_locations cl
       ON cl.id = a.client_location_id
      AND cl.tenant_id = a.tenant_id
      AND cl.client_id = a.client_id
     WHERE a.tenant_id = ?
       AND a.client_id = ?
       AND a.starts_at >= NOW()
       AND a.status IN (
           'tentative',
           'scheduled',
           'confirmed',
           'in_progress'
       )
     ORDER BY a.starts_at ASC, a.id ASC
     LIMIT 3"
);

$upcomingQuery->execute([$tid, (int)$client['id']]);
$upcomingAppointments = $upcomingQuery->fetchAll();

$pageTitle='My Dashboard';

require __DIR__.'/partials/app_header.php';

?>
<link rel="stylesheet"
      href="<?=htmlspecialchars(app_url('/assets/css/client-dashboard.css'),ENT_QUOTES,'UTF-8')?>">
<link rel="stylesheet"
      href="<?=htmlspecialchars(app_url('/assets/css/brite-workspace-tabs.css'),ENT_QUOTES,'UTF-8')?>">
<link rel="stylesheet"
      href="<?=htmlspecialchars(app_url('/assets/css/brite-dashboard-workspace.css'),ENT_QUOTES,'UTF-8')?>">



<div class="brite-client-dashboard brite-dashboard brite-tab-workspace"
     id="clientDashboardWorkspace"
     data-viewport="true">

    <div class="brite-tab-toolbar">
        <div class="brite-tab-list"
             role="tablist"
             aria-label="Client dashboard sections">

<button type="button"
        class="brite-tab"
        id="clientHomeTab"
        role="tab"
        aria-controls="clientHomePanel"
        aria-selected="true">
    Home
</button>

<button type="button"
        class="brite-tab"
        id="clientOverviewTab"
        role="tab"
        aria-controls="clientOverviewPanel"
        aria-selected="false"
        tabindex="-1"
        >
    Overview
</button>

<button type="button"
        class="brite-tab"
        id="clientAppointmentsTab"
        role="tab"
        aria-controls="clientAppointmentsPanel"
        aria-selected="false"
        tabindex="-1">
    My Appointments
</button>

<button type="button"
        class="brite-tab"
        id="clientInvoicesTab"
        role="tab"
        aria-controls="clientInvoicesPanel"
        aria-selected="false"
        tabindex="-1">
    My Invoices
</button>

<button type="button"
        class="brite-tab"
        id="clientLocationsTab"
        role="tab"
        aria-controls="clientLocationsPanel"
        aria-selected="false"
        tabindex="-1">
    My Locations
</button>

<button type="button"
        class="brite-tab"
        id="clientActionsTab"
        role="tab"
        aria-controls="clientActionsPanel"
        aria-selected="false"
        tabindex="-1">
    Quick Actions
</button>
        </div>
    </div>

    <div class="brite-tab-panels">

<section class="brite-tab-panel"
         id="clientHomePanel"
         role="tabpanel"
         aria-labelledby="clientHomeTab">
<div class="brite-client-persistent-hero">
<section class="brite-client-hero" aria-labelledby="briteClientWelcome">
    <div class="brite-client-eyebrow">Simple service scheduling</div>
    <h1 id="briteClientWelcome">
        Ready to schedule your next service?
    </h1>
    <p>
        Welcome, <?=htmlspecialchars($user['first_name'])?>.
        Choose your service, select a location, and request
        a convenient appointment time.
    </p>
    <button type="button"
            class="brite-client-book-button"
            id="briteClientHeroBooking">
        <span aria-hidden="true">+</span>
        Book a Service
        <span aria-hidden="true">→</span>
    </button>
    <span class="brite-client-hero-note">
        Quick scheduling. No phone call required.
    </span>
</section>
    </div>
</section>

<section class="brite-tab-panel"
         id="clientOverviewPanel"
         role="tabpanel"
         aria-labelledby="clientOverviewTab"

         hidden>
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
<section class="brite-client-upcoming"
         aria-labelledby="briteUpcomingHeading">

    <div class="brite-client-section-heading">
        <div>
            <div class="brite-client-eyebrow">Your schedule</div>
            <h2 id="briteUpcomingHeading">Upcoming Appointments</h2>
        </div>
        <a href="#clientAppointmentsPanel">View all appointments →</a>
    </div>

    <?php if (!$upcomingAppointments): ?>

        <div class="brite-client-upcoming-empty">
            <div class="brite-client-upcoming-empty-icon"
                 aria-hidden="true">▦</div>
            <h3>No upcoming appointments</h3>
            <p>
                When you request a service, your upcoming
                appointments will appear here.
            </p>
            <button type="button"
                    class="brite-client-book-button"
                    onclick="document.getElementById('openClientBooking')?.click()">
                + Book a Service
            </button>
        </div>

    <?php else: ?>

        <div class="brite-client-upcoming-list">

            <?php foreach ($upcomingAppointments as $appointment): ?>

                <?php
                $status = (string)$appointment['status'];

                $statusLabels = [
                    'tentative' => 'Pending Confirmation',
                    'scheduled' => 'Scheduled',
                    'confirmed' => 'Confirmed',
                    'in_progress' => 'In Progress'
                ];

                $statusLabel = $statusLabels[$status] ?? ucfirst($status);

                $start = new DateTimeImmutable(
                    (string)$appointment['starts_at']
                );

                $end = new DateTimeImmutable(
                    (string)$appointment['ends_at']
                );

                $serviceName = $appointment['service_name']
                    ?: $appointment['title'];

                $locationName = $appointment['location_label']
                    ?: $appointment['location'];
                ?>

                <article class="brite-client-upcoming-item">

                    <div class="brite-client-upcoming-date">
                        <span><?=htmlspecialchars($start->format('M'))?></span>
                        <strong><?=htmlspecialchars($start->format('j'))?></strong>
                    </div>

                    <div class="brite-client-upcoming-details">
                        <div class="brite-client-upcoming-title">
                            <?=htmlspecialchars((string)$serviceName)?>
                        </div>

                        <div class="brite-client-upcoming-meta">
                            <?=htmlspecialchars($start->format('l, F j, Y'))?>
                            ·
                            <?=htmlspecialchars($start->format('g:i A'))?>
                            –
                            <?=htmlspecialchars($end->format('g:i A'))?>
                        </div>

                        <?php if ($locationName): ?>
                            <div class="brite-client-upcoming-meta">
                                <?=htmlspecialchars((string)$locationName)?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <span class="brite-client-appointment-status
                                 brite-client-appointment-status-<?=htmlspecialchars($status, ENT_QUOTES, 'UTF-8')?>">
                        <?=htmlspecialchars($statusLabel)?>
                    </span>

                </article>

            <?php endforeach; ?>

        </div>

    <?php endif; ?>

</section>
</section>

<section class="brite-tab-panel"
         id="clientAppointmentsPanel"
         role="tabpanel"
         aria-labelledby="clientAppointmentsTab"
         hidden>
<div class="card" style="margin-top:18px">



<h2 id="myAppointments">My Appointments</h2>

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
</section>

<section class="brite-tab-panel"
         id="clientInvoicesPanel"
         role="tabpanel"
         aria-labelledby="clientInvoicesTab"
         hidden>
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
</section>

<section class="brite-tab-panel"
         id="clientLocationsPanel"
         role="tabpanel"
         aria-labelledby="clientLocationsTab"
         hidden>

<div class="card">
    <h2>My Service Locations</h2>
    <p class="muted">
        Manage the addresses where you receive services.
    </p>
    <a href="<?=htmlspecialchars(app_url('/client-locations.php'),ENT_QUOTES,'UTF-8')?>">
        Manage My Locations →
    </a>
</div>
</section>

<section class="brite-tab-panel"
         id="clientActionsPanel"
         role="tabpanel"
         aria-labelledby="clientActionsTab"
         hidden>

<div class="brite-dashboard-quick-actions">
    <a class="card brite-dashboard-action"
       href="#clientAppointmentsPanel">
        <strong>My Appointments</strong>
        <span class="muted">Review scheduled services.</span>
    </a>
    <a class="card brite-dashboard-action"
       href="#clientInvoicesPanel">
        <strong>My Invoices</strong>
        <span class="muted">Review billing and balances.</span>
    </a>
    <a class="card brite-dashboard-action"
       href="<?=htmlspecialchars(app_url('/client-locations.php'),ENT_QUOTES,'UTF-8')?>">
        <strong>My Locations</strong>
        <span class="muted">Manage service addresses.</span>
    </a>
    <a class="card brite-dashboard-action"
       href="<?=htmlspecialchars(app_url('/my-settings.php'),ENT_QUOTES,'UTF-8')?>">
        <strong>My Settings</strong>
        <span class="muted">Customize account preferences.</span>
    </a>
</div>
<div class="brite-client-quick-actions">
    <a class="brite-client-quick-action" href="#clientAppointmentsPanel">
        <span class="brite-client-quick-icon" aria-hidden="true">▦</span>
        <span>
            <strong>My Appointments</strong>
            <small>View your scheduled services</small>
        </span>
    </a>
    <a class="brite-client-quick-action"
       href="<?=htmlspecialchars(app_url('/client-locations.php'),ENT_QUOTES,'UTF-8')?>">
        <span class="brite-client-quick-icon" aria-hidden="true">⌖</span>
        <span>
            <strong>My Locations</strong>
            <small>Manage your service addresses</small>
        </span>
    </a>
</div>
</section>
    </div>
</div>

<script src="<?=htmlspecialchars(app_url('/assets/js/brite-workspace-tabs.js'),ENT_QUOTES,'UTF-8')?>"></script>

<?php require __DIR__.'/partials/client_booking_wizard.php'; ?>

<script src="<?=htmlspecialchars(app_url('/assets/js/client-booking-wizard.js'), ENT_QUOTES, 'UTF-8')?>"></script>

<script>
document.getElementById('briteClientHeroBooking')
    ?.addEventListener('click', function () {
        document.getElementById('openClientBooking')?.click();
    });
</script>

<?php require __DIR__.'/partials/app_footer.php'; ?>
