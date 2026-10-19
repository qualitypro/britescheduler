(function () {
    'use strict';

    const picker = document.getElementById('briteDateTimePicker');
    if (!picker) return;

    const dateInput = document.getElementById('briteAppointmentDate');
    const slots = document.getElementById('briteTimeSlots');
    const presets = document.getElementById('briteDurationPresets');
    const customContainer =
        document.getElementById('briteCustomDurationContainer');
    const customInput = document.getElementById('briteCustomDuration');
    const summary = document.getElementById('briteDateTimeSummary');

    const startInput = document.getElementById('startsAt');
    const endInput = document.getElementById('endsAt');

    if (!startInput || !endInput) return;

    let duration = 60;
    let selectedTime = '';
    let syncingToFields = false;
    let originalAppointmentStart = '';
    let originalAppointmentId = '';

    function localDateTimeValue(date) {
        return `${date.getUTCFullYear()}-` +
            `${pad(date.getUTCMonth() + 1)}-` +
            `${pad(date.getUTCDate())}T` +
            `${pad(date.getUTCHours())}:` +
            `${pad(date.getUTCMinutes())}`;
    }

    function wallClockDate(value) {
        const date = new Date(value + 'Z');

        if (!Number.isFinite(date.getTime()) ||
            localDateTimeValue(date) !== value.slice(0, 16)) {
            return null;
        }

        return date;
    }

    function isExistingAppointment() {
        return Boolean(document.getElementById('appointmentId')?.value);
    }

    function captureOriginalAppointmentStart() {
        const id = document.getElementById('appointmentId')?.value || '';

        if (id !== originalAppointmentId) {
            originalAppointmentId = id;
            originalAppointmentStart = '';
        }
    }

    window.briteSetOriginalAppointmentStart = function () {
        originalAppointmentId =
            document.getElementById('appointmentId')?.value || '';
        originalAppointmentStart =
            originalAppointmentId ? startInput.value : '';
    };

    function mayKeepHistoricalStart() {
        captureOriginalAppointmentStart();

        return Boolean(originalAppointmentId) &&
            Boolean(originalAppointmentStart) &&
            startInput.value === originalAppointmentStart;
    }

    function updateDateMinimum() {
        // Calendar selections may include historical dates.
        // Validate appointment creation using the start-time rule
        // instead of the browser's native date minimum.
        dateInput.removeAttribute('min');
        dateInput.setCustomValidity('');
    }

    function tenantCurrentWallTime() {
        const timezone = window.briteTenantTimezone;

        if (!timezone) {
            throw new Error('Tenant timezone is not configured.');
        }

        const parts = new Intl.DateTimeFormat('en-US', {
            timeZone: timezone,
            year: 'numeric',
            month: '2-digit',
            day: '2-digit',
            hour: '2-digit',
            minute: '2-digit',
            second: '2-digit',
            hourCycle: 'h23'
        }).formatToParts(new Date());

        const values = Object.fromEntries(
            parts.map(part => [part.type, part.value])
        );

        return (
            values.year + '-' +
            values.month + '-' +
            values.day + 'T' +
            values.hour + ':' +
            values.minute + ':' +
            values.second
        );
    }

    function isPastStart(date, time) {
        if (!date || !time) return false;

        const candidate = `${date}T${time}:00`;

        return candidate <= tenantCurrentWallTime();
    }

    function pad(value) {
        return String(value).padStart(2, '0');
    }

    function formatTime(time) {
        const [hours, minutes] = time.split(':').map(Number);
        const suffix = hours >= 12 ? 'PM' : 'AM';
        const hour = hours % 12 || 12;
        return `${hour}:${pad(minutes)} ${suffix}`;
    }

    function formatDate(date) {
        return new Date(date + 'T12:00:00').toLocaleDateString(
            'en-US',
            {
                weekday: 'long',
                month: 'long',
                day: 'numeric',
                year: 'numeric'
            }
        );
    }

    function renderSlots() {
        slots.replaceChildren();

        for (let hour = 6; hour <= 22; hour++) {
            for (let minute = 0; minute < 60; minute += 30) {
                if (hour === 22 && minute > 0) break;

                const value = `${pad(hour)}:${pad(minute)}`;
                const button = document.createElement('button');

                button.type = 'button';
                button.className = 'brite-time-slot';
                button.dataset.time = value;
                button.textContent = formatTime(value);

                const unavailable =
                    !isExistingAppointment() &&
                    isPastStart(dateInput.value, value);

                button.disabled = unavailable;
                if (unavailable) {
                    button.title = 'This time has already passed';
                }

                button.setAttribute(
                    'aria-pressed',
                    String(selectedTime === value)
                );

                if (selectedTime === value) {
                    button.classList.add('is-selected');
                }

                slots.appendChild(button);
            }
        }

        // Existing appointments may start outside standard slots.
        if (selectedTime && !slots.querySelector(
            `[data-time="${selectedTime}"]`
        )) {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'brite-time-slot is-selected';
            button.dataset.time = selectedTime;
            button.textContent = formatTime(selectedTime);
            button.setAttribute('aria-pressed', 'true');
            slots.prepend(button);
        }
    }

    function renderDuration() {
        const standard = [30, 60, 90, 120];
        const custom = !standard.includes(duration);

        presets.querySelectorAll('button').forEach(button => {
            const value = button.dataset.duration;
            const active = value === 'custom'
                ? custom
                : Number(value) === duration;

            button.classList.toggle('is-selected', active);
            button.setAttribute('aria-pressed', String(active));
        });

        customContainer.hidden = !custom;
        customInput.value = String(duration);
    }

    function updateSummary() {
        if (!dateInput.value || !selectedTime) {
            summary.textContent =
                'Choose a date and start time to see your appointment summary.';
            return;
        }

        const start = wallClockDate(
            `${dateInput.value}T${selectedTime}:00`
        );

        if (!start) {
            summary.textContent = 'Choose a valid date and time.';
            return;
        }

        const end = new Date(
            start.getTime() + duration * 60000
        );

        const endDate = localDateTimeValue(end).slice(0, 10);

        summary.textContent =
            `${formatDate(dateInput.value)} · ` +
            `${formatTime(selectedTime)} – ` +
            (endDate !== dateInput.value
                ? `${formatDate(endDate)} · `
                : '') +
            `${formatTime(localDateTimeValue(end).slice(11, 16))}` +
            ` · ${duration} minutes`;
    }

    function syncToExistingFields() {
        if (!dateInput.value || !selectedTime) {
            startInput.value = '';
            endInput.value = '';
        } else {
            const start = wallClockDate(
                `${dateInput.value}T${selectedTime}:00`
            );

            if (!start) {
                startInput.value = '';
                endInput.value = '';
                return;
            }

            const end = new Date(
                start.getTime() + duration * 60000
            );

            startInput.value =
                `${dateInput.value}T${selectedTime}`;

            endInput.value = localDateTimeValue(end);
        }

        // Notify existing scheduling listeners without recursively
        // synchronizing this picker from its own generated changes.
        syncingToFields = true;

        try {
            startInput.dispatchEvent(
                new Event('change', { bubbles: true })
            );
            endInput.dispatchEvent(
                new Event('change', { bubbles: true })
            );
        } finally {
            syncingToFields = false;
        }

        updateSummary();
    }

    function syncFromExistingFields() {
        if (syncingToFields) return;

        const start = startInput.value;
        const end = endInput.value;

        updateDateMinimum();
        captureOriginalAppointmentStart();

        if (!start) duration = 60;

        dateInput.value = start ? start.slice(0, 10) : '';
        selectedTime = start ? start.slice(11, 16) : '';

        if (start && end) {
            const minutes = Math.round(
                (wallClockDate(end)?.getTime() -
                 wallClockDate(start)?.getTime()) / 60000
            );

            if (Number.isFinite(minutes) &&
                minutes >= 1 && minutes <= 1440) {
                duration = minutes;
            }
        }

        renderSlots();
        renderDuration();
        updateSummary();
    }

    dateInput.addEventListener('change', () => {
        updateDateMinimum();
        renderSlots();
        syncToExistingFields();
    });

    slots.addEventListener('click', event => {
        const button = event.target.closest('[data-time]');
        if (!button || button.disabled) return;

        if (!mayKeepHistoricalStart() &&
            isPastStart(dateInput.value, button.dataset.time)) {
            renderSlots();
            return;
        }

        selectedTime = button.dataset.time;
        renderSlots();
        syncToExistingFields();
    });

    presets.addEventListener('click', event => {
        const button = event.target.closest('[data-duration]');
        if (!button) return;

        const value = button.dataset.duration;

        if (value === 'custom') {
            const standard = [30, 60, 90, 120];
            if (standard.includes(duration)) duration = 75;
        } else {
            duration = Number(value);
        }

        renderDuration();
        syncToExistingFields();
    });

    customInput.addEventListener('input', () => {
        const value = Number(customInput.value);

        if (!Number.isInteger(value) || value < 1 ||
            value > 1440) return;

        duration = value;
        syncToExistingFields();
    });

    startInput.addEventListener(
        'change',
        syncFromExistingFields
    );

    endInput.addEventListener(
        'change',
        syncFromExistingFields
    );

    // Called after programmatic form changes (edit/reset/calendar).
    window.briteSyncDateTimePicker = syncFromExistingFields;

    window.briteValidateAppointmentStart = function () {
        updateDateMinimum();
        renderSlots();

        const start = startInput.value;

        if (!start) {
            return 'Choose an appointment start date and time.';
        }

        if (!/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/.test(start)) {
            return 'Enter a valid appointment start date and time.';
        }

        const year = Number(start.slice(0, 4));
        const parsed = wallClockDate(start);

        if (year < 2000 || !parsed ||
            localDateTimeValue(parsed) !== start) {
            return 'Enter a valid appointment start date and time.';
        }

        if (!mayKeepHistoricalStart() &&
            isPastStart(start.slice(0, 10), start.slice(11, 16))) {
            return 'Appointment start time must be in the future.';
        }

        return '';
    };

    syncFromExistingFields();
})();
