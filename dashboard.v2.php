<?php
require_once __DIR__.'/app/bootstrap.php';
Auth::requireRole('owner','admin','scheduler','accounting');

$pageTitle = 'Dashboard';
$role = Auth::tenant()['role'];

require __DIR__.'/partials/app_header.php';
?>

<link rel="stylesheet"
      href="<?=htmlspecialchars(app_url('/assets/css/brite-dashboard.css'))?>">

<section class="brite-dashboard" aria-label="Operations dashboard">

    <header class="brite-dashboard-header">
        <div class="brite-dashboard-heading">
            <h1>Operations Dashboard</h1>
            <p>Monitor your business activity and manage daily operations.</p>
        </div>

        <div class="brite-dashboard-actions">
            <?php if(in_array($role,['owner','admin','scheduler'],true)): ?>
                <a class="brite-ui-button brite-ui-button-primary"
                   href="<?=htmlspecialchars(app_url('/scheduling.php?action=new'))?>">
                    + New Appointment
                </a>
            <?php endif; ?>

            <a class="brite-ui-button brite-ui-button-secondary"
               href="<?=htmlspecialchars(app_url('/clients.v2.php'))?>">
                View Clients
            </a>
        </div>
    </header>

    <div id="dashboardStatus"
         class="brite-dashboard-status"
         role="status"
         aria-live="polite">
        Loading dashboard metrics…
    </div>

    <section id="metrics"
             class="brite-dashboard-metrics"
             aria-label="Business metrics"
             hidden></section>

    <?php if ($role === 'owner'): ?>
    <section id="ownerNotifications"
             class="brite-dashboard-panel"
             aria-label="Owner notifications"
             style="margin-top:20px">
        <h2>
            Notifications
            <span id="ownerNotificationsCount"
                  aria-label="Unread notifications">0</span>
        </h2>
        <p id="ownerNotificationsStatus"
           role="status">Loading notifications…</p>
        <div id="ownerNotificationsList"></div>
    </section>

    <?php endif; ?>

    <div class="brite-dashboard-lower">

        <section class="brite-dashboard-panel">
            <h2>Quick Actions</h2>
            <p>Jump directly to the areas you use most frequently.</p>

            <div class="brite-dashboard-quick-actions">
                <?php if(in_array($role,['owner','admin','scheduler'],true)): ?>
                    <a class="brite-dashboard-quick-action"
                       href="<?=htmlspecialchars(app_url('/scheduling.php'))?>">
                        Schedule Appointments
                    </a>

                    <a class="brite-dashboard-quick-action"
                       href="<?=htmlspecialchars(app_url('/contractors.v2.php'))?>">
                        Manage Contractors
                    </a>

                    <a class="brite-dashboard-quick-action"
                       href="<?=htmlspecialchars(app_url('/services.v2.php'))?>">
                        Manage Services
                    </a>
                <?php endif; ?>

                <a class="brite-dashboard-quick-action"
                   href="<?=htmlspecialchars(app_url('/clients.v2.php'))?>">
                    Manage Clients
                </a>

                <?php if(in_array($role,['owner','admin','accounting'],true)): ?>
                    <a class="brite-dashboard-quick-action"
                       href="<?=htmlspecialchars(app_url('/billing.php'))?>">
                        View Billing
                    </a>
                <?php endif; ?>
            </div>
        </section>

        <section class="brite-dashboard-panel">
            <h2>System Overview</h2>
            <p>
                Brite Scheduler provides tenant-isolated client,
                contractor, scheduling, invoice, and payment management.
            </p>

            <p style="margin-top:14px">
                Payment records are ledger entries. A PCI-compliant
                payment provider must be connected before processing
                payment cards.
            </p>
        </section>

    </div>
</section>

<script>
(function () {
    'use strict';

    const metrics = [
        {
            label: 'Active Clients',
            key: 'clients',
            description: 'Clients in your business directory',
            icon: 'users'
        },
        {
            label: 'Active Contractors',
            key: 'contractors',
            description: 'Contractors available to your business',
            icon: 'contractors'
        },
        {
            label: 'Appointments Today',
            key: 'appointments_today',
            description: 'Appointments scheduled for today',
            icon: 'calendar'
        },
        {
            label: 'Next 7 Days',
            key: 'appointments_week',
            description: 'Appointments scheduled this week',
            icon: 'calendar'
        },
        {
            label: 'Receivables',
            key: 'receivables',
            description: 'Outstanding customer balances',
            icon: 'billing',
            currency: true
        },
        {
            label: 'Payments This Month',
            key: 'payments_month',
            description: 'Payments recorded this month',
            icon: 'billing',
            currency: true
        }
    ];

    const icons = {
        users: '<circle cx="9" cy="8" r="3"/><path d="M3 20v-2a6 6 0 0 1 12 0v2M17 8a3 3 0 0 1 0 6M18 16a4 4 0 0 1 3 4"/>',
        contractors: '<path d="M3 19h18M5 19v-4a7 7 0 0 1 14 0v4M12 8v6M8 10v3M16 10v3"/>',
        calendar: '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M7 3v4M17 3v4M3 10h18"/>',
        billing: '<rect x="2" y="5" width="20" height="14" rx="2"/><path d="M2 10h20M6 15h4"/>'
    };

    const container = document.getElementById('metrics');
    const status = document.getElementById('dashboardStatus');

    const currencyFormatter = new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: 'USD'
    });

    const numberFormatter = new Intl.NumberFormat('en-US');

    function formatValue(value, currency) {
        if (value === null || value === undefined || value === '') {
            return '—';
        }

        const numeric = Number(value);

        if (!Number.isFinite(numeric)) {
            return '—';
        }

        return currency
            ? currencyFormatter.format(numeric)
            : numberFormatter.format(numeric);
    }

    function createMetric(metric, data) {
        const card = document.createElement('article');
        card.className = 'brite-dashboard-metric';

        const top = document.createElement('div');
        top.className = 'brite-dashboard-metric-top';

        const label = document.createElement('div');
        label.className = 'brite-dashboard-metric-label';
        label.textContent = metric.label;

        const icon = document.createElement('span');
        icon.className = 'brite-dashboard-metric-icon';
        icon.setAttribute('aria-hidden', 'true');
        icon.innerHTML =
            '<svg width="22" height="22" viewBox="0 0 24 24" ' +
            'fill="none" stroke="currentColor" stroke-width="1.8" ' +
            'stroke-linecap="round" stroke-linejoin="round">' +
            icons[metric.icon] + '</svg>';

        top.append(label, icon);

        const value = document.createElement('div');
        value.className = 'brite-dashboard-metric-value';
        value.textContent = formatValue(data[metric.key], metric.currency);

        const description = document.createElement('div');
        description.className = 'brite-dashboard-metric-description';
        description.textContent = metric.description;

        card.append(top, value, description);

        return card;
    }

    api('/api/dashboard.php')
        .then(function (data) {
            const cards = metrics.map(function (metric) {
                return createMetric(metric, data);
            });

            container.replaceChildren(...cards);
            container.hidden = false;
            status.hidden = true;
        })
        .catch(function (error) {
            status.hidden = false;
            status.setAttribute('role', 'alert');
            status.textContent =
                'Unable to load dashboard metrics. ' +
                (error.message || 'Please try again.');
        });
})();
</script>

<?php if ($role === 'owner'): ?>
<script src="<?=htmlspecialchars(
    app_url('/assets/js/owner-notifications.js'),
    ENT_QUOTES,
    'UTF-8'
)?>"></script>
<?php endif; ?>

<?php require __DIR__.'/partials/app_footer.php'; ?>
