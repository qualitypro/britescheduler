<?php
declare(strict_types=1);

require_once __DIR__ . '/app/bootstrap.php';

Auth::requireUser();
$tenant = Auth::requireRole('owner', 'admin', 'client');

$pageTitle = 'Contractor Profile';
require __DIR__ . '/partials/app_header.php';
?>

<main class="container">
    <div class="card" style="margin:24px auto;max-width:900px">
        <h1>Contractor Profile</h1>

        <p>
            <a href="<?= htmlspecialchars(
                app_url('/bidding.php'),
                ENT_QUOTES,
                'UTF-8'
            ) ?>">Back to Marketplace</a>
        </p>

        <div id="contractorProfileStatus" role="status">
            Loading contractor profile...
        </div>

        <section id="contractorProfileContent" hidden>
            <h2 id="contractorProfileName"></h2>

            <p id="contractorProfileBusiness"></p>

            <h3>Skills and Experience</h3>
            <p id="contractorProfileSkills"></p>

            <h3>Services and Qualifications</h3>
            <div id="contractorProfileServices"></div>

            <h3>Pricing</h3>
            <p id="contractorProfileRate"></p>

            <h3>Appointment Relationship</h3>
            <p id="contractorProfileAppointment"></p>
            <p id="contractorProfileRelationship"></p>
        </section>
    </div>
</main>

<script>
(async () => {
    'use strict';

    const status = document.getElementById(
        'contractorProfileStatus'
    );

    const content = document.getElementById(
        'contractorProfileContent'
    );

    const params = new URLSearchParams(location.search);

    const appointmentId = params.get('appointment_id');
    const contractorId = params.get('contractor_id');

    const validId = value =>
        /^[1-9]\d*$/.test(String(value || ''));

    if (!validId(appointmentId) || !validId(contractorId)) {
        status.textContent = 'Invalid contractor profile link.';
        return;
    }

    const endpoint = <?= json_encode(
        app_url('/api/marketplace_contractor_profile.php'),
        JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
    ) ?>;

    const url = new URL(endpoint, location.origin);

    url.searchParams.set('appointment_id', appointmentId);
    url.searchParams.set('contractor_id', contractorId);

    try {
        const response = await fetch(url, {
            credentials: 'same-origin',
            headers: { Accept: 'application/json' }
        });

        const data = await response.json();

        if (!response.ok || !data.ok) {
            throw new Error(
                data.error || 'Unable to load contractor profile'
            );
        }

        const profile = data.profile;
        const context = data.context;

        const displayName = [
            profile.first_name,
            profile.last_name
        ].filter(Boolean).join(' ');

        document.getElementById(
            'contractorProfileName'
        ).textContent = displayName || 'Contractor';

        document.getElementById(
            'contractorProfileBusiness'
        ).textContent =
            profile.business_name || 'Independent contractor';

        document.getElementById(
            'contractorProfileSkills'
        ).textContent =
            profile.skills || 'No skills listed.';

        const servicesContainer = document.getElementById(
            'contractorProfileServices'
        );

        servicesContainer.replaceChildren();

        const services = Array.isArray(profile.services)
            ? profile.services
            : [];

        if (services.length === 0) {
            const empty = document.createElement('p');
            empty.textContent =
                'No service qualifications are currently listed.';
            servicesContainer.appendChild(empty);
        } else {
            const list = document.createElement('ul');

            for (const service of services) {
                const item = document.createElement('li');

                const name = document.createElement('strong');
                name.textContent =
                    service.name || 'Unnamed service';

                item.appendChild(name);

                const verification = document.createElement('span');

                verification.textContent =
                    service.verification_status === 'verified'
                        ? ' — Verified qualification'
                        : ' — Not yet verified';

                item.appendChild(verification);

                if (service.description) {
                    const description = document.createElement('p');
                    description.textContent = service.description;
                    item.appendChild(description);
                }

                list.appendChild(item);
            }

            servicesContainer.appendChild(list);
        }

        const rate = Number(profile.hourly_rate);

        document.getElementById(
            'contractorProfileRate'
        ).textContent =
            profile.hourly_rate !== null &&
            profile.hourly_rate !== '' &&
            Number.isFinite(rate)
                ? '$' + rate.toFixed(2) + ' per hour'
                : 'Contact for pricing';

        document.getElementById(
            'contractorProfileAppointment'
        ).textContent =
            context.appointment_title ||
            'Appointment #' + context.appointment_id;

        document.getElementById(
            'contractorProfileRelationship'
        ).textContent =
            context.is_assigned
                ? 'Assigned contractor'
                : context.has_bid
                    ? 'Contractor submitted a proposal'
                    : 'Contractor relationship unavailable';

        content.hidden = false;
        status.textContent = '';

    } catch (error) {
        status.textContent = error.message;
    }
})();
</script>

<?php require __DIR__ . '/partials/app_footer.php'; ?>
