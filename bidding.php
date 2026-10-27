<?php
declare(strict_types=1);

require_once __DIR__ . '/app/bootstrap.php';

Auth::requireRole(
    'owner',
    'admin',
    'scheduler',
    'accounting',
    'contractor',
    'client'
);

$pageTitle = 'Marketplace';
require __DIR__ . '/partials/app_header.php';

$marketplaceRole = (string)($tenant['role'] ?? '');
?>
<link rel="stylesheet" href="<?=htmlspecialchars(app_url('/assets/css/brite-marketplace-ux.css'), ENT_QUOTES, 'UTF-8')?>">
<div class="brite-marketplace">

<style>
.bidding-heading {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 16px;
    flex-wrap: wrap;
    margin-bottom: 24px;
}
.bidding-controls {
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
    margin-bottom: 20px;
}
.bidding-controls input,
.bidding-controls select {
    min-width: 190px;
    flex: 1;
}
.bidding-projects {
    display: grid;
    grid-template-columns: repeat(auto-fill,minmax(280px,1fr));
    gap: 16px;
}
.bidding-project {
    min-width: 0;
}
.bidding-project h3 {
    margin-top: 0;
    overflow-wrap: anywhere;
}
.bidding-project p {
    overflow-wrap: anywhere;
}
.bidding-status {
    display: inline-block;
    padding: 4px 9px;
    border-radius: 999px;
    background: #e0f2fe;
    color: #075985;
    font-size: 12px;
    font-weight: 700;
}
.bidding-actions {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
    margin-top: 16px;
}
.bidding-actions a {
    display: inline-block;
    padding: 9px 13px;
    border-radius: 7px;
    background: #172033;
    color: #fff;
    text-decoration: none;
}
.bidding-hidden {
    display: none !important;
}
</style>

<section class="bidding-heading">
    <div>
        <h1>Marketplace</h1>
        <p class="muted">
            Discover projects, manage proposals, and track awards.
        </p>
    </div>
</section>

<?php if ($marketplaceRole === 'contractor'): ?>

<section aria-labelledby="available-projects-heading">
    <h2 id="available-projects-heading">Available Client Projects</h2>

    <div class="bidding-controls">
        <input
            id="biddingSearch"
            type="search"
            placeholder="Search projects or locations"
            aria-label="Search projects"
        >
        <select id="biddingService" aria-label="Filter by service">
            <option value="">All services</option>
        </select>
        <button type="button" id="biddingRefresh">
            Refresh Projects
        </button>
    </div>

    <p id="biddingProjectStatus" role="status">
        Loading available projects...
    </p>

    <div id="biddingProjects" class="bidding-projects"></div>
</section>

<section style="margin-top:32px" aria-labelledby="my-bids-heading">
    <h2 id="my-bids-heading">My Bids</h2>
    <p id="biddingBidStatus" role="status">
        Loading your bids...
    </p>
    <div id="biddingMyBids" class="bidding-projects"></div>
</section>

<script>
(() => {
    'use strict';

    const projectsContainer =
        document.getElementById('biddingProjects');
    const bidsContainer =
        document.getElementById('biddingMyBids');
    const projectStatus =
        document.getElementById('biddingProjectStatus');
    const bidStatus =
        document.getElementById('biddingBidStatus');
    const searchInput =
        document.getElementById('biddingSearch');
    const serviceFilter =
        document.getElementById('biddingService');

    let projects = [];

    const base = window.BRITE_BASE || '/dashboard';

    function element(tag, className, content) {
        const node = document.createElement(tag);
        if (className) node.className = className;
        if (content !== undefined && content !== null) {
            node.textContent = String(content);
        }
        return node;
    }

    async function getJSON(path) {
        const response = await fetch(base + path, {
            credentials: 'same-origin',
            headers: { Accept: 'application/json' }
        });

        const data = await response.json();

        if (!response.ok) {
            throw new Error(data.error || 'Request failed');
        }

        return data;
    }


    let myBids = [];

    function editable(bid) {
        return bid &&
            ['submitted', 'shortlisted'].includes(bid.bid_status) &&
            bid.listing_status === 'open';
    }

    function makeBidForm(listingId, existingBid = null) {
        const form = element('form', 'bidding-bid-form');
        form.style.marginTop = '16px';

        const heading = element(
            'h4', '',
            existingBid ? 'Revise your bid' : 'Submit a bid'
        );
        form.appendChild(heading);

        const priceType = document.createElement('select');
        priceType.required = true;
        priceType.setAttribute('aria-label', 'Pricing type');

        for (const [value, label] of [
            ['flat', 'Flat rate'],
            ['hourly', 'Hourly rate']
        ]) {
            priceType.add(new Option(label, value));
        }

        priceType.value = existingBid?.pricing_type || 'flat';

        const amount = document.createElement('input');
        amount.type = 'number';
        amount.min = '0';
        amount.max = '9999999999.99';
        amount.step = '0.01';
        amount.required = true;
        amount.placeholder = 'Amount in USD';
        amount.setAttribute('aria-label', 'Bid amount');

        if (existingBid) {
            amount.value = existingBid.amount;
        }

        const message = document.createElement('textarea');
        message.maxLength = 2000;
        message.rows = 3;
        message.placeholder = 'Describe your proposal (optional)';
        message.setAttribute('aria-label', 'Bid message');
        message.value = existingBid?.message || '';

        const submit = element(
            'button', '',
            existingBid ? 'Save Revised Bid' : 'Submit Bid'
        );
        submit.type = 'submit';

        const feedback = element('p', 'muted', '');
        feedback.setAttribute('role', 'status');

        for (const field of [priceType, amount, message]) {
            field.style.display = 'block';
            field.style.width = '100%';
            field.style.boxSizing = 'border-box';
            field.style.marginBottom = '8px';
            form.appendChild(field);
        }

        form.appendChild(submit);
        form.appendChild(feedback);

        form.addEventListener('submit', async event => {
            event.preventDefault();

            const action = existingBid ? 'revise' : 'submit';

            if (!window.confirm(
                action === 'revise'
                    ? 'Save the changes to your bid?'
                    : 'Submit this bid to the project owner?'
            )) return;

            submit.disabled = true;
            feedback.textContent = 'Processing bid...';

            try {
                await sendBid({
                    action,
                    listing_id: Number(listingId),
                    pricing_type: priceType.value,
                    amount: amount.value,
                    message: message.value
                });

                feedback.textContent = 'Bid saved successfully.';
                await Promise.all([loadProjects(), loadBids()]);
            } catch (error) {
                feedback.textContent = error.message;
            } finally {
                submit.disabled = false;
            }
        });

        return form;
    }

    async function sendBid(payload) {
        const response = await fetch(
            base + '/api/contractor_bids.php',
            {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-Token': window.BRITE_CSRF
                },
                body: JSON.stringify(payload)
            }
        );

        const data = await response.json();

        if (!response.ok) {
            throw new Error(data.error || 'Bid operation failed');
        }

        return data;
    }

    function addWithdrawButton(card, bid) {
        const button = element('button', '', 'Withdraw Bid');
        button.type = 'button';

        const feedback = element('p', 'muted', '');
        feedback.setAttribute('role', 'status');

        button.addEventListener('click', async () => {
            if (!window.confirm(
                'Withdraw this bid? Withdrawn bids cannot currently ' +
                'be resubmitted.'
            )) return;

            button.disabled = true;
            feedback.textContent = 'Withdrawing bid...';

            try {
                await sendBid({
                    action: 'withdraw',
                    listing_id: Number(bid.listing_id)
                });

                await Promise.all([loadProjects(), loadBids()]);
            } catch (error) {
                feedback.textContent = error.message;
                button.disabled = false;
            }
        });

        card.appendChild(button);
        card.appendChild(feedback);
    }

    function projectCard(project) {
        const card = element('article', 'card bidding-project');

        card.appendChild(element(
            'h3', '', project.title || 'Untitled project'
        ));

        card.appendChild(element(
            'span', 'bidding-status', 'Open for bids'
        ));

        card.appendChild(element(
            'p', '',
            'Service: ' + (project.service_name || 'Not specified')
        ));

        card.appendChild(element(
            'p', '',
            'Scheduled: ' + (project.starts_at || 'Not specified')
        ));

        if (project.location) {
            card.appendChild(element(
                'p', '', 'Location: ' + project.location
            ));
        }

        if (project.description) {
            card.appendChild(element(
                'p', '', project.description
            ));
        }

        const actions = element('div', 'bidding-actions');
        const link = element('a', '', 'View on Schedule');

        link.href = base + '/scheduling.php?appointment_id=' +
            encodeURIComponent(project.appointment_id);

        actions.appendChild(link);
        card.appendChild(actions);

        const existingBid = myBids.find(
            bid => Number(bid.listing_id) ===
                Number(project.listing_id)
        );

        if (!existingBid) {
            card.appendChild(makeBidForm(project.listing_id));
        } else {
            card.appendChild(element(
                'p', 'muted',
                'Your bid status: ' + existingBid.bid_status
            ));
        }

        return card;
    }

    function renderProjects() {
        const term = searchInput.value.trim().toLowerCase();
        const service = serviceFilter.value;

        const visible = projects.filter(project => {
            const haystack = [
                project.title,
                project.description,
                project.service_name,
                project.location
            ].filter(Boolean).join(' ').toLowerCase();

            return (!term || haystack.includes(term)) &&
                (!service || String(project.service_id) === service);
        });

        projectsContainer.replaceChildren();

        visible.forEach(project => {
            projectsContainer.appendChild(projectCard(project));
        });

        if (!visible.length) {
            projectsContainer.appendChild(element(
                'p', 'brite-marketplace-empty',
                projects.length
                    ? 'No projects match these filters. Try another search or service.'
                    : 'No client projects are available right now. Check back soon.'
            ));
        }

        projectStatus.textContent = visible.length
            ? visible.length + ' available project(s)'
            : 'No projects match your current filters.';
    }

    async function loadProjects() {
        projectStatus.textContent = 'Loading available projects...';

        try {
            const data = await getJSON(
                '/api/contractor_marketplace.php'
            );

            projects = Array.isArray(data.appointments)
                ? data.appointments
                : [];

            const services = new Map();

            projects.forEach(project => {
                if (project.service_id !== null &&
                    project.service_id !== undefined) {
                    services.set(
                        String(project.service_id),
                        project.service_name ||
                            'Service ' + project.service_id
                    );
                }
            });

            const previous = serviceFilter.value;
            serviceFilter.replaceChildren(
                new Option('All services', '')
            );

            services.forEach((name, id) => {
                serviceFilter.add(new Option(name, id));
            });

            serviceFilter.value = previous;
            renderProjects();

        } catch (error) {
            projectStatus.textContent =
                'Unable to load projects: ' + error.message;
        }
    }

    async function loadBids() {
        bidStatus.textContent = 'Loading your bids...';

        try {
            const data = await getJSON('/api/contractor_bids.php');
            const bids = Array.isArray(data.bids) ? data.bids : [];

            myBids = bids;
            bidsContainer.replaceChildren();

            bids.forEach(bid => {
                const card = element(
                    'article', 'card bidding-project'
                );

                card.appendChild(element(
                    'h3', '', bid.title || 'Project'
                ));

                card.appendChild(element(
                    'p', '',
                    'Bid status: ' + (bid.bid_status || 'Unknown')
                ));

                card.appendChild(element(
                    'p', '',
                    'Price: $' + bid.amount +
                    ' (' + bid.pricing_type + ')'
                ));

                card.appendChild(element(
                    'p', '',
                    'Listing status: ' +
                    (bid.listing_status || 'Unknown')
                ));

                if (editable(bid)) {
                    card.appendChild(makeBidForm(
                        bid.listing_id, bid
                    ));
                    addWithdrawButton(card, bid);
                }

                bidsContainer.appendChild(card);
            });

            renderProjects();

            bidStatus.textContent = bids.length
                ? bids.length + ' bid(s) found'
                : 'You have not submitted any bids yet.';

        } catch (error) {
            bidStatus.textContent =
                'Unable to load bids: ' + error.message;
        }
    }

    searchInput.addEventListener('input', renderProjects);
    serviceFilter.addEventListener('change', renderProjects);

    document.getElementById('biddingRefresh')
        .addEventListener('click', () => {
            loadProjects();
            loadBids();
        });

    loadProjects();
    loadBids();
})();
</script>

<?php elseif (in_array(
    $marketplaceRole,
    ['owner', 'admin'],
    true
)): ?>

<section aria-labelledby="owner-bidding-heading">
    <div class="bidding-heading">
        <div>
            <h2 id="owner-bidding-heading">Marketplace Management</h2>
            <p class="muted">
                Review service appointments and published bidding listings.
            </p>
        </div>

        <button type="button" id="ownerBiddingRefresh">
            Refresh Listings
        </button>
    </div>

    <div class="bidding-controls">
        <input
            id="ownerBiddingSearch"
            type="search"
            placeholder="Search appointments"
            aria-label="Search marketplace appointments"
        >

        <select
            id="ownerBiddingFilter"
            aria-label="Filter listing status"
        >
            <option value="">All appointments</option>
            <option value="open">Open listings</option>
            <option value="awarded">Awarded listings</option>
            <option value="unlisted">Not listed</option>
        </select>
    </div>

    <p id="ownerBiddingStatus" role="status">
        Loading marketplace appointments...
    </p>

    <div
        id="ownerBiddingListings"
        class="bidding-projects"
    ></div>
</section>

<script>
(() => {
    'use strict';

    const base = window.BRITE_BASE || '/dashboard';

    const container =
        document.getElementById('ownerBiddingListings');

    const status =
        document.getElementById('ownerBiddingStatus');

    const search =
        document.getElementById('ownerBiddingSearch');

    const filter =
        document.getElementById('ownerBiddingFilter');

    const refresh =
        document.getElementById('ownerBiddingRefresh');

    let appointments = [];

    function element(tag, className, value) {
        const node = document.createElement(tag);

        if (className) {
            node.className = className;
        }

        if (value !== undefined && value !== null) {
            node.textContent = String(value);
        }

        return node;
    }

    function listingState(appointment) {
        return appointment.listing_status || 'unlisted';
    }

    function render() {
        const term = search.value.trim().toLowerCase();
        const selected = filter.value;

        const visible = appointments.filter(appointment => {
            const state = listingState(appointment);

            const text = [
                appointment.title,
                appointment.appointment_id,
                appointment.listing_id,
                appointment.appointment_status
            ].filter(value => value !== null &&
                value !== undefined
            ).join(' ').toLowerCase();

            return (!term || text.includes(term)) &&
                (!selected || selected === state);
        });

        container.replaceChildren();

        for (const appointment of visible) {
            const card = element(
                'article',
                'card bidding-project'
            );

            card.appendChild(element(
                'h3',
                '',
                appointment.title || 'Untitled appointment'
            ));

            const stateBadge = element(
                'span', 'bidding-status', listingState(appointment)
            );
            stateBadge.dataset.state = String(listingState(appointment)).toLowerCase();
            card.appendChild(stateBadge);

            card.appendChild(element(
                'p',
                '',
                'Appointment ID: ' + appointment.appointment_id
            ));

            card.appendChild(element(
                'p',
                '',
                'Listing ID: ' +
                (appointment.listing_id ?? 'Not published')
            ));

            card.appendChild(element(
                'p',
                '',
                'Scheduled: ' +
                (appointment.starts_at || 'Not specified')
            ));

            card.appendChild(element(
                'p',
                '',
                'Appointment status: ' +
                (appointment.appointment_status || 'Unknown')
            ));

            card.appendChild(element(
                'p',
                '',
                'Contractor assignments: ' +
                (appointment.assignment_count ?? 0)
            ));

            const actions = element(
                'div',
                'bidding-actions'
            );

            const schedule = element(
                'a',
                '',
                'View on Schedule'
            );

            schedule.href =
                base + '/scheduling.php?appointment_id=' +
                encodeURIComponent(appointment.appointment_id);

            actions.appendChild(schedule);

            if (appointment.listing_id !== null &&
                appointment.listing_id !== undefined) {

                const activityButton = element(
                    'button', '', 'View Bid Activity'
                );

                activityButton.type = 'button';

                const activityPanel = element(
                    'div', 'ownerBidActivity'
                );

                activityPanel.hidden = true;
                activityPanel.style.marginTop = '16px';
                activityButton.setAttribute('aria-expanded', 'false');
                activityButton.setAttribute('aria-controls', 'owner-bid-activity-' + appointment.listing_id);
                activityPanel.id = 'owner-bid-activity-' + appointment.listing_id;

                let activityLoaded = false;

                activityButton.addEventListener(
                    'click',
                    async () => {
                        if (!activityPanel.hidden) {
                            activityPanel.hidden = true;
                            activityButton.setAttribute('aria-expanded', 'false');
                            activityButton.textContent =
                                'View Bid Activity';
                            return;
                        }

                        activityPanel.hidden = false;
                        activityButton.setAttribute('aria-expanded', 'true');
                        activityButton.textContent =
                            'Hide Bid Activity';

                        if (activityLoaded) return;

                        activityPanel.replaceChildren(
                            element(
                                'p', 'muted',
                                'Loading bid activity...'
                            )
                        );

                        activityButton.disabled = true;

                        try {
                            const response = await fetch(
                                base +
                                '/api/appointment_bid_activity.php' +
                                '?listing_id=' +
                                encodeURIComponent(
                                    appointment.listing_id
                                ),
                                {
                                    credentials: 'same-origin',
                                    headers: {
                                        Accept: 'application/json'
                                    }
                                }
                            );

                            const data = await response.json();

                            if (!response.ok ||
                                data.ok !== true) {
                                throw new Error(
                                    data.error ||
                                    'Unable to load bid activity'
                                );
                            }

                            if (!Array.isArray(data.bids) ||
                                !data.summary) {
                                throw new Error(
                                    'Unexpected activity response'
                                );
                            }

                            activityPanel.replaceChildren();

                            activityPanel.appendChild(element(
                                'h4', '', 'Bid Activity'
                            ));

                            const summary = data.summary;

                            activityPanel.appendChild(element(
                                'p', '',
                                'Total bids: ' +
                                summary.total_bids +
                                ' | Active: ' +
                                summary.active_bids +
                                ' | Withdrawn: ' +
                                summary.withdrawn_bids
                            ));

                            const activeStatuses = new Set([
                                'submitted',
                                'shortlisted',
                                'selected',
                                'accepted'
                            ]);

                            const controls = element(
                                'div',
                                'bidding-controls ownerBidComparisonControls'
                            );

                            controls.style.marginTop = '16px';

                            const statusFilter =
                                document.createElement('select');

                            statusFilter.setAttribute(
                                'aria-label',
                                'Filter contractor bids by status'
                            );

                            for (const [value, label] of [
                                ['all', 'All bid statuses'],
                                ['active', 'Active bids'],
                                ['withdrawn', 'Withdrawn bids'],
                                ['other', 'Other statuses']
                            ]) {
                                statusFilter.add(new Option(label, value));
                            }

                            const pricingFilter =
                                document.createElement('select');

                            pricingFilter.setAttribute(
                                'aria-label',
                                'Filter contractor bids by pricing type'
                            );

                            for (const [value, label] of [
                                ['all', 'All pricing types'],
                                ['flat', 'Flat rate'],
                                ['hourly', 'Hourly rate']
                            ]) {
                                pricingFilter.add(new Option(label, value));
                            }

                            const sortSelect =
                                document.createElement('select');

                            sortSelect.setAttribute(
                                'aria-label',
                                'Sort contractor bids'
                            );

                            for (const [value, label] of [
                                ['submitted', 'Oldest submitted first'],
                                ['recent', 'Newest submitted first'],
                                ['price_asc', 'Price: low to high'],
                                ['price_desc', 'Price: high to low'],
                                ['contractor', 'Contractor ID']
                            ]) {
                                sortSelect.add(new Option(label, value));
                            }

                            controls.append(
                                statusFilter,
                                pricingFilter,
                                sortSelect
                            );

                            activityPanel.appendChild(controls);

                            const comparisonNote = element(
                                'p',
                                'muted',
                                'Hourly rates and flat-rate totals are ' +
                                'different pricing units. Price sorting ' +
                                'groups them separately.'
                            );

                            activityPanel.appendChild(comparisonNote);

                            const resultsStatus = element(
                                'p',
                                'muted ownerBidComparisonStatus'
                            );

                            resultsStatus.setAttribute('role', 'status');

                            activityPanel.appendChild(resultsStatus);

                            const resultsContainer = element(
                                'div',
                                'ownerBidComparisonResults'
                            );

                            activityPanel.appendChild(resultsContainer);

                            function renderComparedBids() {
                                const selectedStatus = statusFilter.value;
                                const selectedPricing = pricingFilter.value;
                                const sort = sortSelect.value;

                                const visible = data.bids.filter(bid => {
                                    const state = String(bid.status || '');
                                    const isActive =
                                        activeStatuses.has(state);

                                    const matchesStatus =
                                        selectedStatus === 'all' ||
                                        (selectedStatus === 'active' &&
                                            isActive) ||
                                        (selectedStatus === 'withdrawn' &&
                                            state === 'withdrawn') ||
                                        (selectedStatus === 'other' &&
                                            !isActive &&
                                            state !== 'withdrawn');

                                    const matchesPricing =
                                        selectedPricing === 'all' ||
                                        bid.pricing_type === selectedPricing;

                                    return matchesStatus && matchesPricing;
                                });

                                const priceValue = bid => {
                                    if (bid.amount === null ||
                                        bid.amount === undefined ||
                                        String(bid.amount).trim() === '') {
                                        return null;
                                    }

                                    const value = Number(bid.amount);
                                    return Number.isFinite(value)
                                        ? value
                                        : null;
                                };

                                visible.sort((a, b) => {
                                    if (sort === 'contractor') {
                                        return Number(a.contractor_id) -
                                            Number(b.contractor_id) ||
                                            Number(a.bid_id) -
                                            Number(b.bid_id);
                                    }

                                    if (sort === 'price_asc' ||
                                        sort === 'price_desc') {
                                        const typeComparison =
                                            String(a.pricing_type).localeCompare(
                                                String(b.pricing_type)
                                            );

                                        if (typeComparison !== 0) {
                                            return typeComparison;
                                        }

                                        const aPrice = priceValue(a);
                                        const bPrice = priceValue(b);

                                        if (aPrice === null) return 1;
                                        if (bPrice === null) return -1;

                                        const direction =
                                            sort === 'price_asc' ? 1 : -1;

                                        return direction *
                                            (aPrice - bPrice) ||
                                            Number(a.bid_id) -
                                            Number(b.bid_id);
                                    }

                                    const comparison =
                                        String(a.submitted_at || '')
                                            .localeCompare(
                                                String(b.submitted_at || '')
                                            );

                                    return (sort === 'recent'
                                        ? -comparison
                                        : comparison) ||
                                        Number(a.bid_id) -
                                        Number(b.bid_id);
                                });

                                resultsContainer.replaceChildren();

                                resultsStatus.textContent =
                                    'Showing ' + visible.length +
                                    ' of ' + data.bids.length + ' bids.';

                                if (visible.length === 0) {
                                    resultsContainer.appendChild(element(
                                        'p',
                                        'muted',
                                        'No bids match these filters.'
                                    ));
                                    return;
                                }

                                for (const bid of visible) {
                                    const state = String(
                                        bid.status || 'unknown'
                                    );

                                    const isActive =
                                        activeStatuses.has(state);

                                    const bidCard = element(
                                        'div',
                                        'card ownerBidEntry'
                                    );

                                    bidCard.style.marginTop = '12px';

                                    bidCard.appendChild(element(
                                        'h4',
                                        '',
                                        'Contractor ' + bid.contractor_id
                                    ));

                                    bidCard.appendChild(element(
                                        'p',
                                        '',
                                        'Bid ID: ' + bid.bid_id
                                    ));

                                    bidCard.appendChild(element(
                                        'p',
                                        '',
                                        'Status: ' + state
                                    ));

                                    if (!isActive) {
                                        bidCard.appendChild(element(
                                            'p',
                                            'muted',
                                            'Historical / inactive proposal — ' +
                                            'not an active offer.'
                                        ));
                                    }

                                    const amount = priceValue(bid);

                                    const price = amount === null
                                        ? 'Unavailable'
                                        : amount.toLocaleString(
                                            'en-US',
                                            {
                                                style: 'currency',
                                                currency: 'USD'
                                            }
                                        );

                                    bidCard.appendChild(element(
                                        'p',
                                        '',
                                        'Offer: ' + price +
                                        ' (' + bid.pricing_type + ')'
                                    ));

                                    if (bid.message) {
                                        bidCard.appendChild(element(
                                            'p',
                                            '',
                                            bid.message
                                        ));
                                    }

                                    bidCard.appendChild(element(
                                        'p',
                                        'muted',
                                        'Submitted: ' +
                                        (bid.submitted_at || 'Unknown')
                                    ));

                                    bidCard.appendChild(element(
                                        'p',
                                        'muted',
                                        'Updated: ' +
                                        (bid.updated_at || 'Unknown')
                                    ));

                                    const awardEligible =
                                        appointment.listing_status === 'open' &&
                                        ['submitted', 'shortlisted'].includes(state);

                                    const awardButton = element(
                                        'button',
                                        'ownerBidAwardButton',
                                        'Award Bid'
                                    );

                                    awardButton.type = 'button';
                                    awardButton.disabled = !awardEligible;

                                    const awardStatus = element(
                                        'p',
                                        'muted ownerBidAwardStatus',
                                        awardEligible
                                            ? 'Eligible for award.'
                                            : 'Award unavailable for this proposal.'
                                    );

                                    awardStatus.setAttribute('role', 'status');

                                    awardButton.addEventListener(
                                        'click',
                                        async () => {
                                            if (!awardEligible ||
                                                awardButton.disabled) {
                                                return;
                                            }

                                            const confirmed = window.confirm(
                                                'Award bid #' + bid.bid_id +
                                                ' to contractor #' +
                                                bid.contractor_id +
                                                ' for appointment #' +
                                                appointment.appointment_id +
                                                '?\n\n' +
                                                'This will assign the contractor, ' +
                                                'close the listing, reject competing ' +
                                                'eligible bids, and send notifications.'
                                            );

                                            if (!confirmed) return;

                                            awardButton.disabled = true;
                                            awardStatus.textContent =
                                                'Submitting award...';

                                            let serverResponseReceived = false;
                                            let awardConfirmed = false;

                                            try {
                                                const response = await fetch(
                                                    base +
                                                    '/api/appointment_bid_award.php',
                                                    {
                                                        method: 'POST',
                                                        credentials: 'same-origin',
                                                        headers: {
                                                            'Content-Type':
                                                                'application/json',
                                                            Accept:
                                                                'application/json',
                                                            'X-CSRF-Token':
                                                                window.BRITE_CSRF || ''
                                                        },
                                                        body: JSON.stringify({
                                                            listing_id:
                                                                Number(
                                                                    appointment.listing_id
                                                                ),
                                                            bid_id:
                                                                Number(bid.bid_id)
                                                        })
                                                    }
                                                );

                                                serverResponseReceived = true;

                                                const data =
                                                    await response.json();

                                                if (!response.ok ||
                                                    data.ok !== true) {
                                                    const error = new Error(
                                                        data.error ||
                                                        'Award request rejected'
                                                    );

                                                    error.confirmedRejection =
                                                        !response.ok;

                                                    throw error;
                                                }

                                                awardConfirmed = true;

                                                awardStatus.textContent =
                                                    'Bid awarded successfully. ' +
                                                    'Refreshing marketplace...';

                                                await load(true);

                                            } catch (error) {
                                                if (awardConfirmed) {
                                                    awardStatus.textContent =
                                                        'Award succeeded, but marketplace ' +
                                                        'refresh failed. Reload the page ' +
                                                        'to verify the latest status.';
                                                } else if (
                                                    serverResponseReceived &&
                                                    error.confirmedRejection === true
                                                ) {
                                                    awardStatus.textContent =
                                                        'Award rejected: ' +
                                                        error.message;

                                                    awardButton.disabled = false;
                                                } else {
                                                    awardStatus.textContent =
                                                        'Award outcome uncertain. ' +
                                                        'Do not retry. Reload the page ' +
                                                        'and verify the listing status.';
                                                }
                                            }
                                        }
                                    );

                                    bidCard.appendChild(awardButton);
                                    bidCard.appendChild(awardStatus);

                                    if (state !== 'withdrawn') {
                                        const profileLink = document.createElement('a');

                                        const profileUrl = new URL(
                                            base + '/marketplace-contractor-profile.php',
                                            window.location.origin
                                        );

                                        profileUrl.searchParams.set(
                                            'appointment_id',
                                            String(appointment.appointment_id)
                                        );

                                        profileUrl.searchParams.set(
                                            'contractor_id',
                                            String(bid.contractor_id)
                                        );

                                        profileLink.href = profileUrl.toString();
                                        profileLink.textContent = 'View Contractor Profile';
                                        profileLink.className = 'btn btn-secondary';

                                        bidCard.appendChild(profileLink);
                                    }

                                    resultsContainer.appendChild(bidCard);
                                }
                            }

                            statusFilter.addEventListener(
                                'change',
                                renderComparedBids
                            );

                            pricingFilter.addEventListener(
                                'change',
                                renderComparedBids
                            );

                            sortSelect.addEventListener(
                                'change',
                                renderComparedBids
                            );

                            renderComparedBids();

                            activityLoaded = true;

                        } catch (error) {
                            activityPanel.replaceChildren(
                                element(
                                    'p', '',
                                    'Unable to load bid activity: ' +
                                    error.message
                                )
                            );
                        } finally {
                            activityButton.disabled = false;
                        }
                    }
                );

                actions.appendChild(activityButton);
                card.appendChild(actions);
                card.appendChild(activityPanel);

            } else {
                card.appendChild(actions);
            }

            container.appendChild(card);
        }

        if (!visible.length) {
            container.appendChild(element(
                'p', 'brite-marketplace-empty',
                appointments.length
                    ? 'No appointments match your filters. Try another search or status.'
                    : 'No marketplace appointments are available yet.'
            ));
        }

        status.textContent =
            visible.length + ' of ' +
            appointments.length +
            ' marketplace appointment(s) displayed.';
    }

    async function load(propagateErrors = false) {
        status.textContent = 'Loading marketplace appointments...';
        refresh.disabled = true;

        try {
            const response = await fetch(
                base + '/api/appointment_bid_listings.php',
                {
                    credentials: 'same-origin',
                    headers: {
                        Accept: 'application/json'
                    }
                }
            );

            const data = await response.json();

            if (!response.ok) {
                throw new Error(
                    data.error || 'Unable to load listings'
                );
            }

            if (!Array.isArray(data.appointments)) {
                throw new Error(
                    'Unexpected listing response structure'
                );
            }

            appointments = data.appointments;
            render();

        } catch (error) {
            status.textContent =
                'Unable to load marketplace: ' + error.message;

            if (propagateErrors) {
                throw error;
            }
        } finally {
            refresh.disabled = false;
        }
    }

    search.addEventListener('input', render);
    filter.addEventListener('change', render);
    refresh.addEventListener('click', load);

    load();
})();
</script>

<?php elseif ($marketplaceRole === 'client'): ?>

<section aria-labelledby="client-marketplace-heading">
    <div class="bidding-heading">
        <div>
            <h2 id="client-marketplace-heading">
                My Marketplace Requests
            </h2>
            <p class="muted">
                Review contractor proposals for your service requests.
            </p>
        </div>
        <a class="btn btn-secondary" href="<?=htmlspecialchars(
            app_url('/client-dashboard.php?book=1'),
            ENT_QUOTES,
            'UTF-8'
        )?>">Book a Service</a>
    </div>

    <div class="bidding-controls">
        <input
            id="clientMarketplaceSearch"
            type="search"
            placeholder="Search your service requests"
            aria-label="Search Marketplace requests"
        >
        <button
            type="button"
            id="clientMarketplaceRefresh"
        >Refresh Requests</button>
    </div>

    <p id="clientMarketplaceStatus" role="status">
        Loading your Marketplace requests...
    </p>

    <div
        id="clientMarketplaceListings"
        class="bidding-projects"
    ></div>
</section>

<script>
(() => {
    'use strict';

    const base = window.BRITE_BASE || '/dashboard';
    const container = document.getElementById(
        'clientMarketplaceListings'
    );
    const status = document.getElementById(
        'clientMarketplaceStatus'
    );
    const search = document.getElementById(
        'clientMarketplaceSearch'
    );
    const refresh = document.getElementById(
        'clientMarketplaceRefresh'
    );

    let listings = [];

    function element(tag, className, value) {
        const node = document.createElement(tag);
        if (className) node.className = className;
        if (value !== undefined && value !== null) {
            node.textContent = String(value);
        }
        return node;
    }

    async function getJSON(path) {
        const response = await fetch(base + path, {
            credentials: 'same-origin',
            cache: 'no-store',
            headers: { Accept: 'application/json' }
        });

        const data = await response.json();

        if (!response.ok) {
            throw new Error(
                data.error || 'Marketplace request failed'
            );
        }

        return data;
    }

    function money(value) {
        const amount = Number(value);

        if (!Number.isFinite(amount)) {
            return 'Price unavailable';
        }

        return new Intl.NumberFormat('en-US', {
            style: 'currency',
            currency: 'USD'
        }).format(amount);
    }

    function profileURL(appointmentId, contractorId) {
        const url = new URL(
            base + '/marketplace-contractor-profile.php',
            window.location.origin
        );

        url.searchParams.set(
            'appointment_id',
            String(appointmentId)
        );

        url.searchParams.set(
            'contractor_id',
            String(contractorId)
        );

        return url.toString();
    }

    function renderBid(bid, appointmentId) {
        const card = element('article', 'card bidding-project');
        card.style.marginTop = '12px';

        const name = [
            bid.first_name,
            bid.last_name
        ].filter(Boolean).join(' ').trim();

        card.appendChild(element(
            'h4', '', name || 'Contractor'
        ));

        if (bid.business_name) {
            card.appendChild(element(
                'p', '', bid.business_name
            ));
        }

        const pricingLabel =
            bid.pricing_type === 'hourly'
                ? ' per hour'
                : ' flat rate';

        card.appendChild(element(
            'p', '',
            'Proposal: ' + money(bid.amount) + pricingLabel
        ));

        card.appendChild(element(
            'p',
            'bidding-status',
            'Status: ' + (bid.status || 'Unknown')
        ));

        if (bid.message) {
            card.appendChild(element(
                'p', '', bid.message
            ));
        }

        const contractorId = Number(bid.contractor_id);

        if (Number.isSafeInteger(contractorId) &&
            contractorId > 0) {
            const actions = element('div', 'bidding-actions');
            const link = element(
                'a', '', 'View Contractor Profile'
            );

            link.href = profileURL(
                appointmentId,
                contractorId
            );

            actions.appendChild(link);
            card.appendChild(actions);
        }

        return card;
    }

    function renderListing(listing) {
        const card = element('article', 'card bidding-project');

        card.appendChild(element(
            'h3', '', listing.title || 'Service Request'
        ));

        card.appendChild(element(
            'p', '',
            'Appointment #' + listing.appointment_id
        ));

        if (listing.starts_at) {
            card.appendChild(element(
                'p', '',
                'Scheduled: ' + listing.starts_at
            ));
        }

        card.appendChild(element(
            'p',
            'bidding-status',
            'Listing: ' + listing.listing_status
        ));

        card.appendChild(element(
            'p', '',
            'Eligible proposals: ' +
                listing.eligible_bid_count
        ));

        const button = element(
            'button', '', 'View Contractor Bids'
        );
        button.type = 'button';
        button.setAttribute('aria-expanded', 'false');

        const panel = element('div', '');
        panel.hidden = true;

        let loaded = false;

        button.addEventListener('click', async () => {
            if (!panel.hidden) {
                panel.hidden = true;
                button.setAttribute('aria-expanded', 'false');
                button.textContent = 'View Contractor Bids';
                return;
            }

            panel.hidden = false;
            button.setAttribute('aria-expanded', 'true');
            button.textContent = 'Hide Contractor Bids';

            if (loaded) return;

            button.disabled = true;
            panel.replaceChildren(element(
                'p', '', 'Loading contractor proposals...'
            ));

            try {
                const data = await getJSON(
                    '/api/appointment_bid_activity.php' +
                    '?listing_id=' +
                    encodeURIComponent(listing.listing_id)
                );

                const bids = Array.isArray(data.bids)
                    ? data.bids.filter(bid =>
                        [
                            'submitted',
                            'shortlisted',
                            'selected',
                            'accepted'
                        ].includes(bid.status)
                    )
                    : [];

                panel.replaceChildren();

                if (!bids.length) {
                    panel.appendChild(element(
                        'p', '',
                        'No eligible contractor proposals yet.'
                    ));
                } else {
                    for (const bid of bids) {
                        panel.appendChild(
                            renderBid(
                                bid,
                                listing.appointment_id
                            )
                        );
                    }
                }

                loaded = true;
            } catch (error) {
                panel.replaceChildren(element(
                    'p', '',
                    'Unable to load proposals: ' +
                        error.message
                ));
            } finally {
                button.disabled = false;
            }
        });

        const actions = element('div', 'bidding-actions');
        actions.appendChild(button);

        card.appendChild(actions);
        card.appendChild(panel);

        return card;
    }

    function render() {
        const term = search.value.trim().toLowerCase();

        const visible = listings.filter(listing => {
            const haystack = [
                listing.title,
                listing.appointment_id,
                listing.listing_status
            ].join(' ').toLowerCase();

            return haystack.includes(term);
        });

        container.replaceChildren();

        for (const listing of visible) {
            container.appendChild(renderListing(listing));
        }

        if (!visible.length) {
            container.appendChild(element(
                'p', 'brite-marketplace-empty',
                listings.length
                    ? 'No requests match your search. Try a different keyword.'
                    : 'You have no marketplace requests yet. Book a service to get started.'
            ));
        }

        status.textContent =
            visible.length + ' of ' +
            listings.length +
            ' Marketplace request(s) displayed.';
    }

    async function load() {
        refresh.disabled = true;
        status.textContent =
            'Loading your Marketplace requests...';

        try {
            const data = await getJSON(
                '/api/marketplace_client_listings.php'
            );

            if (!Array.isArray(data.listings)) {
                throw new Error(
                    'Unexpected listing response structure'
                );
            }

            listings = data.listings;
            render();
        } catch (error) {
            container.replaceChildren();
            status.textContent =
                'Unable to load Marketplace requests: ' +
                error.message;
        } finally {
            refresh.disabled = false;
        }
    }

    search.addEventListener('input', render);
    refresh.addEventListener('click', load);

    load();
})();
</script>

<?php else: ?>

<div class="card">
    <h2>Marketplace Activity</h2>
    <p>
        Marketplace visibility for this role
        will be added in a later development cycle.
    </p>
</div>

<?php endif; ?>

</div>
<?php require __DIR__ . '/partials/app_footer.php'; ?>
