(() => {
    'use strict';

    const workspace = document.getElementById(
        'contractorDashboardWorkspace'
    );

    if (!workspace) return;

    const endpoint =
        window.BRITE_BASE +
        '/api/contractor_assignment_response.php';

    workspace.addEventListener('click', async (event) => {
        if (!(event.target instanceof Element)) {
            return;
        }

        const button = event.target.closest(
            '[data-assignment-action]'
        );

        if (!button || !workspace.contains(button)) {
            return;
        }

        const group = button.closest(
            '[data-appointment-id]'
        );

        if (!group || group.dataset.pending === '1') {
            return;
        }

        const appointmentId = Number(
            group.dataset.appointmentId
        );

        const action = button.dataset.assignmentAction;

        if (
            !Number.isSafeInteger(appointmentId) ||
            appointmentId <= 0 ||
            !['accept', 'decline'].includes(action)
        ) {
            return;
        }

        if (
            action === 'decline' &&
            !window.confirm(
                'Decline this assignment? The owner will be notified.'
            )
        ) {
            return;
        }

        group.dataset.pending = '1';

        const buttons = group.querySelectorAll('button');

        buttons.forEach(b => {
            b.disabled = true;
        });

        const originalText = button.textContent;

        button.textContent = 'Processing...';

        try {
            const response = await fetch(endpoint, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-Token': window.BRITE_CSRF || ''
                },
                body: JSON.stringify({
                    appointment_id: appointmentId,
                    action
                })
            });

            const result = await response.json();

            if (!response.ok || !result.ok) {
                throw new Error(
                    result.error ||
                    `Request failed: HTTP ${response.status}`
                );
            }

            const row = group.closest('tr');

            const status = row?.querySelector(
                '[data-assignment-status]'
            );

            if (status) {
                status.textContent = result.status;
            }

            group.replaceWith(
                document.createTextNode('—')
            );

        } catch (error) {
            window.alert(
                error.message ||
                'Unable to update assignment.'
            );

            button.textContent = originalText;

            buttons.forEach(b => {
                b.disabled = false;
            });

            group.dataset.pending = '0';
        }
    });
})();
