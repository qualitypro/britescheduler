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
    const dateInput = $('clientBookingDate');
    const timeSelect = $('clientBookingTime');
    const availabilityStatus = $('clientBookingAvailabilityStatus');
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
    let availableSlots = [];
    let availabilityLoading = false;
    let availabilityVersion = 0;

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

    function clearAvailableSlots(message) {
        availableSlots = [];
        startInput.value = '';
        timeSelect.disabled = true;
        timeSelect.replaceChildren(
            new Option('Choose an available time', '')
        );
        availabilityStatus.textContent = message;
    }

    async function refreshAvailability() {
        const version = ++availabilityVersion;

        requestKey = null;
        availabilityLoading = false;

        clearAvailableSlots(
            'Choose a date to check availability.'
        );

        if (
            !selectedLocation() ||
            !selectedService() ||
            !dateInput.value
        ) {
            return;
        }

        availabilityLoading = true;

        availabilityStatus.textContent =
            'Checking available appointment times...';

        const params = new URLSearchParams({
            client_location_id: locationSelect.value,
            service_id: serviceSelect.value,
            date: dateInput.value
        });

        try {
            const response = await fetch(
                endpoint('/api/client_available_slots.php') +
                    '?' + params.toString(),
                {
                    credentials: 'same-origin',
                    cache: 'no-store'
                }
            );

            const result = await response.json();

            if (version !== availabilityVersion) return;

            if (!response.ok || result.ok !== true) {
                throw new Error(
                    result.error ||
                    'Unable to retrieve available times.'
                );
            }

            if (!Array.isArray(result.slots)) {
                throw new Error('Invalid availability response.');
            }

            availableSlots = result.slots.filter(slot =>
                typeof slot.starts_at === 'string' &&
                typeof slot.time === 'string'
            );

            timeSelect.replaceChildren(
                new Option('Choose an available time', '')
            );

            for (const slot of availableSlots) {
                const [hour, minute] = slot.time
                    .split(':')
                    .map(Number);

                const displayHour = hour % 12 || 12;
                const period = hour >= 12 ? 'PM' : 'AM';

                timeSelect.add(new Option(
                    `${displayHour}:${String(minute).padStart(2, '0')} ${period}`,
                    slot.starts_at
                ));
            }

            timeSelect.disabled = availableSlots.length === 0;

            availabilityStatus.textContent =
                availableSlots.length
                    ? `${availableSlots.length} available starting times.`
                    : 'No available times for this date. ' +
                      'Please choose another date.';

        } catch (error) {
            if (version !== availabilityVersion) return;

            clearAvailableSlots(
                error.message ||
                'Unable to load available times.'
            );
        } finally {
            if (version === availabilityVersion) {
                availabilityLoading = false;
            }
        }
    }

    function syncSelectedTime() {
        const selected = availableSlots.find(
            slot => slot.starts_at === timeSelect.value
        );

        startInput.value = selected
            ? selected.starts_at.slice(0, 16)
            : '';

        requestKey = null;
        clearError();
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
            if (availabilityLoading) {
                showError('Please wait for availability to load.');
                return false;
            }

            const selected = availableSlots.find(
                slot => slot.starts_at === timeSelect.value
            );

            if (
                !selected ||
                !startInput.value ||
                startInput.value !== selected.starts_at.slice(0, 16)
            ) {
                showError('Please select an available appointment time.');
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

    function resetWizard(preferredDate = '') {
        currentStep = 1;
        completed = false;
        submitting = false;
        requestKey = null;

        locationSelect.value = '';
        serviceSelect.value = '';
        startInput.value = '';

        // Calendar dates are tenant-local wall-clock dates.
        dateInput.value =
            /^\d{4}-\d{2}-\d{2}$/.test(preferredDate)
                ? preferredDate
                : '';

        ++availabilityVersion;
        availabilityLoading = false;
        clearAvailableSlots(
            'Choose a date to check availability.'
        );

        successBox.hidden = true;
        successBox.textContent = '';

        submitButton.disabled = false;
        nextButton.disabled = false;

        updateStep();
    }

    async function openBookingWizard(preferredDate = '') {
        if (dialog.open) return;

        resetWizard(preferredDate);
        dialog.showModal();

        nextButton.disabled = true;

        try {
            await loadOptions();
        } catch (error) {
            showError(error.message);
        } finally {
            nextButton.disabled = false;
        }
    }

    openButton.addEventListener('click', () => openBookingWizard());

    // Shared calendar integration: allow an optional preselected date.
    window.briteOpenClientBookingForDate = dateValue => {
        openBookingWizard(
            typeof dateValue === 'string' ? dateValue : ''
        );
    };

    // Allow client navigation to open the wizard directly.
    const bookingUrl = new URL(window.location.href);

    if (bookingUrl.searchParams.get('book') === '1') {
        bookingUrl.searchParams.delete('book');

        window.history.replaceState(
            window.history.state,
            '',
            bookingUrl.pathname +
                bookingUrl.search +
                bookingUrl.hash
        );

        openBookingWizard();
    }

    closeButton.addEventListener('click', () => {
        if (!submitting) dialog.close();
    });

    nextButton.addEventListener('click', () => {
        if (!validateStep()) return;

        if (currentStep < 4) {
            currentStep++;
            updateStep();

            if (currentStep === 3) {
                refreshAvailability();
            }
        }
    });

    backButton.addEventListener('click', () => {
        if (currentStep > 1 && !submitting) {
            currentStep--;
            updateStep();
        }
    });

    [locationSelect, serviceSelect, dateInput].forEach(field => {
        field.addEventListener('change', () => {
            requestKey = null;
            clearError();
            refreshAvailability();
        });
    });

    timeSelect.addEventListener('change', syncSelectedTime);

    submitButton.addEventListener('click', async () => {
        if (submitting || completed) return;

        if (
            !selectedLocation() ||
            !selectedService() ||
            !startInput.value ||
            !availableSlots.some(
                slot =>
                    slot.starts_at === timeSelect.value &&
                    slot.starts_at.slice(0, 16) === startInput.value
            )
        ) {
            showError('Please select a valid available time.');
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
