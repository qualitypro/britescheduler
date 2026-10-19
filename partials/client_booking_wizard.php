<?php
declare(strict_types=1);
?>
<style>
.client-booking-actions {
    display: flex;
    justify-content: flex-end;
    margin: 18px 0;
}

.client-booking-open,
.client-booking-primary {
    background: #2563eb;
    color: #fff;
    border: 1px solid #2563eb;
    font-weight: 600;
}

.client-booking-dialog {
    width: min(650px, calc(100vw - 32px));
    max-height: calc(100vh - 48px);
    overflow-y: auto;
    border: 1px solid #e2e8f0;
    border-radius: 14px;
    padding: 0;
    box-shadow: 0 20px 65px #0003;
}

.client-booking-dialog::backdrop {
    background: rgb(15 23 42 / 65%);
}

.client-booking-header,
.client-booking-content,
.client-booking-footer {
    padding: 20px 24px;
}

.client-booking-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-bottom: 1px solid #e2e8f0;
}

.client-booking-header h2 {
    margin: 0;
}

.client-booking-progress {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 8px;
    margin: 0 0 24px;
    padding: 0;
    list-style: none;
}

.client-booking-progress li {
    text-align: center;
    color: #64748b;
    font-size: 13px;
}

.client-booking-progress li[aria-current="step"] {
    color: #1d4ed8;
    font-weight: 700;
}

.client-booking-step[hidden] {
    display: none;
}

.client-booking-field {
    display: flex;
    flex-direction: column;
    gap: 7px;
    margin: 14px 0;
}

.client-booking-field select,
.client-booking-field input {
    width: 100%;
    box-sizing: border-box;
}

.client-booking-footer {
    display: flex;
    justify-content: space-between;
    gap: 12px;
    border-top: 1px solid #e2e8f0;
}

.client-booking-error {
    color: #b91c1c;
    margin-top: 12px;
}

.client-booking-review {
    line-height: 1.9;
    overflow-wrap: anywhere;
}

.client-booking-field select:disabled {
    opacity: 0.65;
    cursor: not-allowed;
}

#clientBookingAvailabilityStatus {
    min-height: 1.5em;
}

@media (max-width: 480px) {
    .client-booking-header,
    .client-booking-content,
    .client-booking-footer {
        padding: 16px;
    }
}
</style>

<div class="client-booking-actions">
    <button
        type="button"
        class="client-booking-open"
        id="openClientBooking">
        + Book a Service
    </button>
</div>

<dialog
    id="clientBookingDialog"
    class="client-booking-dialog"
    aria-labelledby="clientBookingTitle">

    <div class="client-booking-header">
        <h2 id="clientBookingTitle">Book a Service</h2>
        <button
            type="button"
            id="closeClientBooking"
            aria-label="Close booking wizard">
            &times;
        </button>
    </div>

    <div class="client-booking-content">

        <ol class="client-booking-progress"
            id="clientBookingProgress">
            <li aria-current="step">1. Location</li>
            <li>2. Service</li>
            <li>3. Date &amp; Time</li>
            <li>4. Review</li>
        </ol>

        <section class="client-booking-step"
                 data-booking-step="1">
            <h3>Select Service Location</h3>

            <label class="client-booking-field">
                Location
                <select id="clientBookingLocation" required>
                    <option value="">Choose a location</option>
                </select>
            </label>
        </section>

        <section class="client-booking-step"
                 data-booking-step="2"
                 hidden>
            <h3>Select Service</h3>

            <label class="client-booking-field">
                Service
                <select id="clientBookingService" required>
                    <option value="">Choose a service</option>
                </select>
            </label>
        </section>

        <section class="client-booking-step"
                 data-booking-step="3"
                 hidden>
            <h3>Select Appointment Date &amp; Time</h3>

            <label class="client-booking-field">
                Appointment date
                <input
                    id="clientBookingDate"
                    type="date"
                    required>
            </label>

            <div class="client-booking-field">
                <label for="clientBookingTime">
                    Available starting times
                </label>

                <select
                    id="clientBookingTime"
                    required
                    disabled>
                    <option value="">
                        Select a date to view available times
                    </option>
                </select>
            </div>

            <p
                id="clientBookingAvailabilityStatus"
                class="muted"
                role="status"
                aria-live="polite">
                Choose a date to check availability.
            </p>

            <input
                id="clientBookingStart"
                type="hidden">

            <p class="muted">
                Available times are provisional. Your booking
                requires confirmation by the account owner.
            </p>
        </section>

        <section class="client-booking-step"
                 data-booking-step="4"
                 hidden>
            <h3>Review Booking Request</h3>

            <div class="client-booking-review"
                 id="clientBookingReview"></div>

            <p class="muted">
                Your request will be sent to the account owner
                for confirmation.
            </p>
        </section>

        <p id="clientBookingError"
           class="client-booking-error"
           role="alert"
           hidden></p>

        <p id="clientBookingSuccess"
           role="status"
           hidden></p>
    </div>

    <div class="client-booking-footer">
        <button type="button"
                id="clientBookingBack"
                hidden>
            Back
        </button>

        <button type="button"
                id="clientBookingNext"
                class="client-booking-primary">
            Next
        </button>

        <button type="button"
                id="clientBookingSubmit"
                class="client-booking-primary"
                hidden>
            Request Booking
        </button>
    </div>
</dialog>
