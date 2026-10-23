<?php
declare(strict_types=1);

require_once __DIR__ . '/app/bootstrap.php';

Auth::requireRole('owner', 'admin');

$pageTitle = 'Contractor Qualification Review';

require __DIR__ . '/partials/app_header.php';
?>

<style>
.qr-grid {
    display: grid;
    grid-template-columns:
        repeat(auto-fit, minmax(min(100%, 320px), 1fr));
    gap: 14px;
}
.qr-controls {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
    align-items: center;
}
.qr-controls button {
    min-width: 110px;
}
.qr-status {
    min-height: 24px;
    margin: 12px 0;
}
.qr-card h3 {
    margin-top: 0;
}
.qr-card button:disabled {
    opacity: .5;
}
</style>

<h1>Contractor Qualification Review</h1>

<p class="muted">
    Review contractor service requests within your tenant.
    Approval enables contractor-specific pricing.
</p>

<div class="card">
    <div class="qr-controls">
        <label for="qr-filter">Show</label>
        <select id="qr-filter">
            <option value="pending">Pending requests</option>
            <option value="all">All qualification records</option>
        </select>
        <button type="button" id="qr-refresh">
            Refresh
        </button>
    </div>
    <p id="qr-status"
       class="qr-status muted"
       role="status"
       aria-live="polite">
        Loading...
    </p>
</div>

<div id="qr-results"
     class="qr-grid"
     style="margin-top:16px">
</div>

<script>
(() => {
    'use strict';

    const results = document.getElementById('qr-results');
    const status = document.getElementById('qr-status');
    const filter = document.getElementById('qr-filter');
    const refresh = document.getElementById('qr-refresh');

    const base = window.BRITE_BASE;

    function el(tag, text) {
        const node = document.createElement(tag);
        if (text !== undefined) node.textContent = text;
        return node;
    }

    async function get(url) {
        const response = await fetch(base + url, {
            credentials: 'same-origin'
        });

        const data = await response.json();

        if (!response.ok) {
            throw new Error(data.error || 'Request failed');
        }

        return data;
    }

    async function update(contractorId, serviceId, decision) {
        const response = await fetch(
            base + '/api/contractor_service_qualifications.php',
            {
                method: 'PUT',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': window.BRITE_CSRF
                },
                body: JSON.stringify({
                    contractor_id: contractorId,
                    service_id: serviceId,
                    verification_status: decision
                })
            }
        );

        const data = await response.json();

        if (!response.ok) {
            throw new Error(data.error || 'Update failed');
        }

        return data;
    }

    function card(contractor, qualification) {
        const node = el('article');
        node.className = 'card qr-card';

        const heading = el(
            'h3',
            qualification.service_name
        );

        const contractorName = [
            contractor.first_name,
            contractor.last_name
        ].filter(Boolean).join(' ').trim() ||
            'Contractor #' + contractor.id;

        const person = el(
            'p',
            'Contractor #' + contractor.id +
            ' — ' + contractorName
        );

        const state = el(
            'p',
            'Status: ' + qualification.verification_status
        );

        const controls = el('div');
        controls.className = 'qr-controls';

        for (const [decision, label] of [
            ['verified', 'Approve'],
            ['rejected', 'Reject']
        ]) {
            const button = el('button', label);
            button.type = 'button';

            button.addEventListener('click', async () => {
                const confirmed = window.confirm(
                    label + ' ' +
                    qualification.service_name +
                    ' for ' + contractorName + '?'
                );

                if (!confirmed) return;

                for (const child of controls.children) {
                    child.disabled = true;
                }

                status.textContent = 'Saving decision...';

                try {
                    await update(
                        Number(contractor.id),
                        Number(qualification.service_id),
                        decision
                    );

                    await load();
                } catch (error) {
                    status.textContent = error.message;

                    for (const child of controls.children) {
                        child.disabled = false;
                    }
                }
            });

            controls.appendChild(button);
        }

        node.append(heading, person, state, controls);
        return node;
    }

    async function load() {
        results.replaceChildren();
        status.textContent = 'Loading qualifications...';

        try {
            const contractorData = await get(
                '/api/contractors.php'
            );

            const contractors = Array.isArray(contractorData)
                ? contractorData
                : contractorData.contractors || [];

            if (!Array.isArray(contractors)) {
                throw new Error(
                    'Unexpected contractor response'
                );
            }

            const responses = await Promise.all(
                contractors.map(async contractor => ({
                    contractor,
                    data: await get(
                        '/api/contractor_service_qualifications.php' +
                        '?contractor_id=' +
                        encodeURIComponent(contractor.id)
                    )
                }))
            );

            let count = 0;

            for (const entry of responses) {
                for (const qualification of
                    entry.data.qualifications || []) {

                    // Missing qualifications are not requests.
                    if (!qualification.qualification_id) {
                        continue;
                    }

                    if (
                        filter.value === 'pending' &&
                        qualification.verification_status !==
                            'unverified'
                    ) {
                        continue;
                    }

                    results.appendChild(
                        card(entry.contractor, qualification)
                    );

                    count++;
                }
            }

            status.textContent =
                count + ' qualification record(s) displayed.';

            if (!count) {
                results.appendChild(el(
                    'p',
                    'No matching qualification requests.'
                ));
            }
        } catch (error) {
            status.textContent = 'Error: ' + error.message;
        }
    }

    refresh.addEventListener('click', load);
    filter.addEventListener('change', load);

    load();
})();
</script>

<?php require __DIR__ . '/partials/app_footer.php'; ?>
