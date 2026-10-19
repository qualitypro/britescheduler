(() => {
    'use strict';

    const dialog = document.getElementById('clientBookingDialog');
    const openButton = document.getElementById('openClientBooking');

    // The controller is safe to load on pages without the wizard.
    if (!dialog || !openButton) return;

    const $ = id => document.getElementById(id);

    const locationSelect = $('clientBookingLocation');
    const serviceSelect = $('clientBookingService');
    const startInput = $('clientBookingStart');
    const review = $('clientBookingReview');
    const errorBox = $('clientBookingError');
    const successBox = $('clientBookingSuccess');

    const backButton = $('clientBookingBack');
    const nextButton = $('clientBookingNext');
    const submitButton = $('clientBookingSubmit');
    const closeButton = $('closeClientBooking');

    const steps = [...dialog.querySelectorAll(
        '[data-booking-step]'
    )];

    const progress = [...dialog.querySelectorAll(
        '#clientBookingProgress li'
    )];

    let currentStep = 1;
    let locations = [];
    let services = [];
    let submitting = false;
    let completed = false;

    // Preserve the request key for retries of the same booking.
    let requestKey = null;

    const base = window.BRITE_BASE ||
        `${location.origin}/dashboard`;

    function endpoint(path) {
        return `${base.replace(/\/$/, '')}${path}`;
    }

    function showError(message) {
        errorBox.textContent = message;
        errorBox.hidden = false;
    }

    function clearError() {
        errorBox.textContent = '';
        errorBox.hidden = true;
    }

    function selectedLocation() {
        return locations.find(
            item => Number(item.id) === Number(locationSelect.value)
        );
    }

    function selectedService() {
        return services.find(
            item => Number(item.id) === Number(serviceSelect.value)
        );
    }

    function locationLabel(item) {
        const label = item.label || 'Service location';
        const address = [
            item.address1,
            item.city,
            item.state,
            item.postal_code
        ].filter(Boolean).join(', ');

        return address ? `${label} — ${address}` : label;
    }

    function updateStep() {
        steps.forEach(section => {
            section.hidden =
                Number(section.dataset.bookingStep) !== currentStep;
        });

        progress.forEach((item, index) => {
            if (index + 1 === currentStep) {
                item.setAttribute('aria-current', 'step');
            } else {
                item.removeAttribute('aria-current');
            }
        });

        backButton.hidden = currentStep === 1;
        nextButton.hidden = currentStep === 4;
        submitButton.hidden = currentStep !== 4;

        if (currentStep === 4) renderReview();

        clearError();
    }

    function validateStep() {
        if (currentStep === 1 && !selectedLocation()) {
            showError('Please select a service location.');
            return false;
        }

        if (currentStep === 2 && !selectedService()) {
            showError('Please select a service.');
            return false;
        }

        if (currentStep === 3) {
            if (!startInput.value) {
                showError('Please select a date and time.');
                return false;
            }

            const selected = new Date(startInput.value);

            if (
                Number.isNaN(selected.getTime()) ||
                selected.getTime() <= Date.now()
            ) {
                showError('Please choose a future date and time.');
                return false;
            }
        }

        return true;
    }

    function renderReview() {
        const location = selectedLocation();
        const service = selectedService();

        review.replaceChildren();

        const details = [
            ['Location', location ? locationLabel(location) : ''],
            ['Service', service?.name || ''],
            ['Duration', service
                ? `${service.duration_minutes} minutes`
                : ''
            ],
            ['Requested time', startInput.value.replace('T', ' ')]
        ];

        details.forEach(([label, value]) => {
            const row = document.createElement('div');
            const heading = document.createElement('strong');

            heading.textContent = `${label}: `;
            row.append(heading, document.createTextNode(value));
            review.appendChild(row);
        });
    }

    function fillSelect(select, items, labelFunction) {
        select.replaceChildren(new Option(
            select === locationSelect
                ? 'Choose a location'
                : 'Choose a service',
            ''
        ));

        items.forEach(item => {
            select.add(new Option(
                labelFunction(item),
                String(item.id)
            ));
        });
    }

    async function loadOptions() {
        const [locationResponse, serviceResponse] = await Promise.all([
            fetch(endpoint('/api/client_locations.php'), {
                credentials: 'same-origin'
            }),
            fetch(endpoint('/api/client_service_catalog.php'), {
                credentials: 'same-origin'
            })
        ]);

        if (!locationResponse.ok || !serviceResponse.ok) {
            throw new Error('Unable to load booking options.');
        }

        const locationData = await locationResponse.json();
        const serviceData = await serviceResponse.json();

        locations = Array.isArray(locationData.locations)
            ? locationData.locations
            : [];

        services = Array.isArray(serviceData.services)
            ? serviceData.services
            : [];

        fillSelect(locationSelect, locations, locationLabel);
        fillSelect(serviceSelect, services, item => item.name);

        if (!locations.length) {
            showError(
                'No service locations found. Add a location before booking.'
            );
        } else if (!services.length) {
            showError('No services are currently available.');
        }
    }

    function resetWizard() {
        currentStep = 1;
        completed = false;
        submitting = false;
        requestKey = null;

        locationSelect.value = '';
        serviceSelect.value = '';
        startInput.value = '';

        successBox.hidden = true;
        successBox.textContent = '';

        submitButton.disabled = false;
        nextButton.disabled = false;

        updateStep();
    }

    openButton.addEventListener('click', async () => {
        if (dialog.open) return;

        resetWizard();
        dialog.showModal();

        nextButton.disabled = true;

        try {
            await loadOptions();
        } catch (error) {
            showError(error.message);
        } finally {
            nextButton.disabled = false;
        }
    });

    closeButton.addEventListener('click', () => {
        if (!submitting) dialog.close();
    });

    nextButton.addEventListener('click', () => {
        if (!validateStep()) return;

        if (currentStep < 4) {
            currentStep++;
            updateStep();
        }
    });

    backButton.addEventListener('click', () => {
        if (currentStep > 1 && !submitting) {
            currentStep--;
            updateStep();
        }
    });

    [locationSelect, serviceSelect, startInput].forEach(field => {
        field.addEventListener('change', () => {
            // Changing booking details creates a distinct request.
            requestKey = null;
            clearError();
        });
    });

    submitButton.addEventListener('click', async () => {
        if (submitting || completed) return;

        if (!selectedLocation() ||
            !selectedService() ||
            !startInput.value) {
            showError('Complete all booking details.');
            return;
        }

        if (!window.BRITE_CSRF) {
            showError('Your session is unavailable. Please sign in again.');
            return;
        }

        if (!requestKey) {
            requestKey = crypto.randomUUID();
        }

        const payload = {
            client_location_id: Number(locationSelect.value),
            service_id: Number(serviceSelect.value),
            starts_at: startInput.value,
            request_key: requestKey
        };

        submitting = true;
        submitButton.disabled = true;
        backButton.disabled = true;
        clearError();

        try {
            const response = await fetch(
                endpoint('/api/client_bookings.php'),
                {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': window.BRITE_CSRF
                    },
                    body: JSON.stringify(payload)
                }
            );

            const result = await response.json();

            if (!response.ok || !result.ok) {
                throw new Error(
                    result.error || 'Unable to submit booking.'
                );
            }

            completed = true;

            successBox.textContent =
                `Booking request #${result.id} submitted. ` +
                'Pending account owner confirmation.';

            successBox.hidden = false;

            submitButton.hidden = true;
            nextButton.hidden = true;
            backButton.hidden = true;

            // Refresh after dismissal to show the new appointment.
        } catch (error) {
            showError(
                error.message || 'Unable to submit booking.'
            );
        } finally {
            submitting = false;
            submitButton.disabled = completed;
            backButton.disabled = false;
        }
    });

    dialog.addEventListener('cancel', event => {
        if (submitting) event.preventDefault();
    });

    dialog.addEventListener('close', () => {
        if (completed) window.location.reload();
    });
})();
