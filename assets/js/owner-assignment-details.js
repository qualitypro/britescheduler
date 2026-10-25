(() => {
    'use strict';

    function initializeOwnerAssignmentDetails() {

    const role = typeof BRITE_CALENDAR_ROLE !== 'undefined'
        ? BRITE_CALENDAR_ROLE
        : null;

    if (role !== 'owner') return;

    const dialog = document.getElementById('appointmentDetailsDialog');
    const content = document.getElementById('appointmentDetailsContent');

    if (!dialog || !content) return;

    const base = (window.BRITE_BASE || '/dashboard')
        .replace(/\/$/, '');

    let requestSequence = 0;

    function findAppointmentId() {
        const event = window.briteOwnerDetailsEvent;

        if (event?.id) {
            return Number(event.id);
        }

        return null;
    }

    async function loadAssignmentStatus(appointmentId) {
        const response = await fetch(
            `${base}/api/owner_assignment_status.php?appointment_id=${encodeURIComponent(appointmentId)}`,
            {
                credentials: 'same-origin',
                headers: { Accept: 'application/json' }
            }
        );

        const data = await response.json();

        if (!response.ok) {
            throw new Error(data.error || 'Unable to load assignments');
        }

        return data;
    }

    function createStatusRow(assignment) {
        const row = document.createElement('div');
        row.className = 'brite-assignment-status-row';

        const name = document.createElement('span');

        name.textContent = [
            assignment.first_name,
            assignment.last_name
        ].filter(Boolean).join(' ') ||
            assignment.business_name ||
            `Contractor #${assignment.contractor_id}`;

        const status = document.createElement('strong');
        status.textContent = String(assignment.status || 'assigned')
            .replaceAll('_', ' ');

        status.className =
            'brite-assignment-status-' + assignment.status;

        row.append(name, status);

        if (assignment.reassignment_handled) {
            const handled = document.createElement('small');
            handled.textContent = 'Reassigned';
            row.append(handled);
        }

        return row;
    }

    function createReassignmentControls(
        appointmentId,
        assignment,
        assignments
    ) {
        const wrapper = document.createElement('div');
        wrapper.className = 'brite-reassignment-controls';

        const label = document.createElement('label');
        label.textContent = 'Choose replacement contractor';

        const select = document.createElement('select');
        select.className = 'brite-reassignment-select';

        const placeholder = document.createElement('option');
        placeholder.value = '';
        placeholder.textContent = 'Select a contractor';
        select.append(placeholder);

        const assignedIds = new Set(
            assignments.map(item => Number(item.contractor_id))
        );

        const sourceSelect = document.getElementById('contractorId');

        if (sourceSelect) {
            for (const option of sourceSelect.options) {
                const contractorId = Number(option.value);

                if (
                    !Number.isInteger(contractorId) ||
                    contractorId <= 0 ||
                    assignedIds.has(contractorId) ||
                    option.disabled
                ) {
                    continue;
                }

                const candidate = document.createElement('option');
                candidate.value = String(contractorId);
                candidate.textContent = option.textContent.trim();
                select.append(candidate);
            }
        }

        const button = document.createElement('button');
        button.type = 'button';
        button.textContent = 'Reassign Contractor';
        button.disabled = true;

        const message = document.createElement('p');
        message.setAttribute('role', 'status');
        message.setAttribute('aria-live', 'polite');

        select.addEventListener('change', () => {
            button.disabled = !select.value;
        });

        button.addEventListener('click', async () => {
            const replacementId = Number(select.value);

            if (
                !Number.isInteger(replacementId) ||
                replacementId <= 0
            ) {
                return;
            }

            const selectedName =
                select.selectedOptions[0]?.textContent.trim() ||
                `Contractor #${replacementId}`;

            if (!window.confirm(
                `Reassign this declined assignment to ${selectedName}?`
            )) {
                return;
            }

            button.disabled = true;
            select.disabled = true;
            message.textContent = 'Submitting reassignment...';

            try {
                const response = await fetch(
                    `${base}/api/owner_assignment_reassign.php`,
                    {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-Token': window.BRITE_CSRF || ''
                        },
                        body: JSON.stringify({
                            appointment_id: appointmentId,
                            declined_contractor_id:
                                Number(assignment.contractor_id),
                            replacement_contractor_id: replacementId
                        })
                    }
                );

                const result = await response.json();

                if (!response.ok || !result.ok) {
                    throw new Error(
                        result.error || 'Reassignment failed'
                    );
                }

                message.textContent = 'Reassignment successful.';

                await refreshDetails(appointmentId);

            } catch (error) {
                message.textContent =
                    error.message || 'Unable to reassign contractor';

                select.disabled = false;
                button.disabled = !select.value;
            }
        });

        if (select.options.length === 1) {
            placeholder.textContent =
                'No unassigned contractors available';
            select.disabled = true;
        }

        label.append(select);
        wrapper.append(label, button, message);

        return wrapper;
    }

    async function refreshDetails(appointmentId) {
        const sequence = ++requestSequence;

        content.querySelectorAll(
            '.brite-owner-assignment-details'
        ).forEach(element => element.remove());

        const container = document.createElement('section');
        container.className = 'brite-owner-assignment-details';

        const heading = document.createElement('h3');
        heading.textContent = 'Contractor Assignment Status';

        const body = document.createElement('div');
        body.textContent = 'Loading assignments...';

        container.append(heading, body);
        content.append(container);

        try {
            const data = await loadAssignmentStatus(appointmentId);

            if (sequence !== requestSequence || !dialog.open) return;

            body.replaceChildren();

            if (!data.assignments?.length) {
                body.textContent = 'No contractors assigned.';
                return;
            }

            for (const assignment of data.assignments) {
                body.append(createStatusRow(assignment));

                if (assignment.can_reassign === true) {
                    body.append(
                        createReassignmentControls(
                            appointmentId,
                            assignment,
                            data.assignments
                        )
                    );
                }
            }

        } catch (error) {
            if (sequence === requestSequence) {
                body.textContent = error.message;
            }
        }
    }

    const observer = new MutationObserver(() => {
        if (!dialog.open) {
            requestSequence++;
            return;
        }

        const appointmentId = findAppointmentId();

        if (appointmentId) {
            refreshDetails(appointmentId);
        }
    });

    observer.observe(dialog, {
        attributes: true,
        attributeFilter: ['open']
    });

    window.briteRefreshOwnerAssignmentDetails = refreshDetails;
    }

    if (document.readyState === 'loading') {
        document.addEventListener(
            'DOMContentLoaded',
            initializeOwnerAssignmentDetails,
            { once: true }
        );
    } else {
        initializeOwnerAssignmentDetails();
    }
})();