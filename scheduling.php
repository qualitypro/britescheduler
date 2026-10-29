<?php
require_once __DIR__.'/app/bootstrap.php';
Auth::requireRole('owner','admin','scheduler','accounting','contractor','client');

$timezoneStatement = Database::connection()->prepare(
    'SELECT timezone FROM tenants WHERE id=? LIMIT 1'
);
$timezoneStatement->execute([Auth::tenantId()]);
$tenantTimezone = $timezoneStatement->fetchColumn();

if (!$tenantTimezone) {
    throw new RuntimeException('Tenant timezone not configured');
}

try {
    new DateTimeZone((string)$tenantTimezone);
} catch (Throwable $e) {
    throw new RuntimeException('Invalid tenant timezone');
}

$canManageAppointments = in_array(
    $tenant['role'] ?? '',
    ['owner', 'admin', 'scheduler'],
    true
);

$pageTitle='Schedule';
require __DIR__.'/partials/app_header.php';
?>

<?php if (($tenant['role'] ?? '') === 'client'): ?>
<?php require __DIR__ . '/partials/client_booking_wizard.php'; ?>
<script src="<?=htmlspecialchars(app_url('/assets/js/client-booking-wizard.js'), ENT_QUOTES, 'UTF-8')?>"></script>
<?php endif; ?>

<script src="<?=htmlspecialchars(app_url('/events/dist/index.global.min.js'))?>"></script>

<style>
.scheduler-layout{
    display:grid;
    grid-template-columns:minmax(0,1fr);
    gap:18px;
}
.scheduler-form label{
    display:block;
    font-size:13px;
    font-weight:600;
    margin-top:10px;
}
.scheduler-form input,
.scheduler-form select,
.scheduler-form textarea{
    width:100%;
    box-sizing:border-box;
    margin-top:4px;
}
.scheduler-form textarea{
    min-height:70px;
}
#calendar{
    min-height:700px;
}

/* Compact, status-colored calendar appointments. */
#calendar .fc-event {
    cursor: pointer;
}

#calendar .brite-calendar-event {
    min-width: 0;
    padding: 3px 5px;
    line-height: 1.3;
    overflow: hidden;
}

#calendar .brite-event-heading {
    display: flex;
    align-items: baseline;
    gap: 5px;
    min-width: 0;
    font-weight: 700;
}

#calendar .brite-event-time {
    flex-shrink: 0;
    font-size: 11px;
}

#calendar .brite-event-title,
#calendar .brite-event-client,
#calendar .brite-event-contractors {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    min-width: 0;
}

#calendar .brite-event-title {
    flex: 1;
}

#calendar .brite-event-client,
#calendar .brite-event-contractors {
    font-size: 11px;
    opacity: .95;
}

#calendar .brite-event-status {
    display: inline-block;
    width: fit-content;
    max-width: 100%;
    padding: 1px 5px;
    margin-top: 3px;
    border-radius: 4px;
    background: rgba(255,255,255,.22);
    font-size: 10px;
    font-weight: 700;
    text-transform: capitalize;
}

#calendar .fc-daygrid-event .brite-calendar-event {
    width: 100%;
}

#calendar .fc-timegrid-event .brite-calendar-event {
    padding: 4px 6px;
}

@media (max-width: 600px) {
    #calendar .brite-event-client,
    #calendar .brite-event-contractors {
        display: none;
    }
}

.contractor-recommendations{
    margin-top:16px;
    padding:14px;
    border:1px solid #cbd5e1;
    border-radius:8px;
}

.contractor-recommendations h3{
    margin:0 0 8px;
    font-size:16px;
}

.recommendations-help{
    font-size:12px;
    line-height:1.5;
    opacity:.8;
}

/* Contractor availability feedback in wizard step 5. */
#contractorConflictStatus {
    margin: 12px 0;
    padding: 11px 13px;
    border: 1px solid #cbd5e1;
    border-radius: 8px;
    font-size: 13px;
    line-height: 1.5;
    overflow-wrap: anywhere;
}

#contractorConflictStatus[data-state="available"] {
    border-color: #86efac;
    background: #f0fdf4;
    color: #166534;
}

#contractorConflictStatus[data-state="conflict"] {
    border-color: #fca5a5;
    background: #fef2f2;
    color: #991b1b;
}

#contractorConflictStatus[data-state="checking"] {
    border-color: #93c5fd;
    background: #eff6ff;
    color: #1d4ed8;
}

#contractorConflictStatus[data-state="unassigned"] {
    border-color: #cbd5e1;
    background: #f8fafc;
    color: #475569;
}

#loadRecommendations{
    margin-top:10px;
}

#recommendationsStatus{
    font-size:13px;
    margin:12px 0;
}

#recommendationsResults{
    display:grid;
    gap:10px;
}

.recommendation-card{
    padding:12px;
    border:1px solid #cbd5e1;
    border-radius:8px;
}

.recommendation-score{
    font-weight:700;
    font-size:18px;
}

.recommendation-details{
    font-size:12px;
    line-height:1.6;
}

/* Appointment wizard field-level validation. */
.wizard-step .wizard-field-invalid {
    border-color: #b91c1c;
    outline-color: #b91c1c;
}

.wizard-step .wizard-field-invalid:focus {
    outline: 2px solid #b91c1c;
    outline-offset: 1px;
}

.wizard-field-error {
    margin: 4px 0 12px;
    color: #b91c1c;
    font-size: 12px;
    line-height: 1.5;
}

.wizard-field-error[hidden] {
    display: none;
}

.wizard-validation-summary {
    margin: 10px 0;
    color: #991b1b;
    font-size: 13px;
    line-height: 1.5;
}

.wizard-validation-summary[hidden] {
    display: none;
}

/* Searchable contractor selection and removable chips. */
.contractor-native-select {
    display: none !important;
}
.contractor-selection-count {
    margin: 10px 0;
    font-size: 13px;
    font-weight: 600;
}
.contractor-checkbox-list {
    display: grid;
    gap: 7px;
    max-height: 260px;
    overflow-y: auto;
    padding: 9px;
    border: 1px solid #cbd5e1;
    border-radius: 8px;
}
.contractor-checkbox-row {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 9px 11px;
    border: 1px solid #e2e8f0;
    border-radius: 7px;
    cursor: pointer;
}
.contractor-checkbox-row.is-selected {
    border-color: #2563eb;
    background: #eff6ff;
}
.scheduler-form .contractor-checkbox-row input {
    width: auto;
    margin: 0;
}
.contractor-checkbox-row span {
    overflow-wrap: anywhere;
}
.selected-contractor-chips {
    display: flex;
    flex-wrap: wrap;
    gap: 7px;
    margin-top: 12px;
}
.selected-contractor-chip {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    padding: 5px 9px;
    border: 1px solid #93c5fd;
    border-radius: 999px;
    background: #eff6ff;
    font-size: 12px;
}
.selected-contractor-chip button {
    cursor: pointer;
}
.contractor-empty-message {
    margin: 8px;
    font-size: 13px;
    color: #64748b;
}

/* Contractor recommendation card UX. */
.recommendation-card {
    display: grid;
    gap: 12px;
    min-width: 0;
    transition: border-color .15s ease, box-shadow .15s ease;
}

.recommendation-card.is-selected {
    border-color: #2563eb;
    box-shadow: 0 0 0 2px rgba(37,99,235,.15);
}

.recommendation-card-header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 10px;
}

.recommendation-card-name {
    font-size: 16px;
    font-weight: 700;
    line-height: 1.4;
}

.recommendation-card .recommendation-score {
    font-size: 16px;
    font-weight: 700;
    color: #1d4ed8;
}

.recommendation-card-details {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 10px;
}

.recommendation-detail {
    display: grid;
    gap: 4px;
    min-width: 0;
}

.recommendation-detail-label {
    font-size: 11px;
    font-weight: 600;
    color: #64748b;
}

.recommendation-detail-value {
    font-size: 13px;
    overflow-wrap: anywhere;
}

.recommendation-status {
    display: inline-flex;
    align-items: center;
    width: fit-content;
    padding: 3px 8px;
    border-radius: 999px;
    font-size: 12px;
    font-weight: 600;
}

.recommendation-status[data-state="positive"] {
    background: #f0fdf4;
    color: #166534;
}

.recommendation-status[data-state="negative"] {
    background: #fef2f2;
    color: #991b1b;
}

.recommendation-status[data-state="neutral"] {
    background: #f1f5f9;
    color: #475569;
}

.recommendation-card-actions {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
}

.recommendation-card.is-selected .recommendation-select {
    background: #167347;
    color: #fff;
}

@media (max-width: 600px) {
    .recommendation-card-details {
        grid-template-columns: 1fr;
    }
}
.schedule-toolbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 12px;
    margin-bottom: 18px;
}

.schedule-toolbar h1 {
    margin: 0;
}

/* Appointment wizard modal foundation. */
.appointment-dialog {
    width: min(760px, calc(100vw - 32px));
    max-height: calc(100vh - 48px);
    box-sizing: border-box;
    padding: 24px;
    border: 0;
    border-radius: 12px;
    overflow-y: auto;
    background: var(--card-bg, #fff);
    color: inherit;
    box-shadow: 0 20px 70px rgba(0,0,0,.25);
}

.appointment-dialog::backdrop {
    background: rgba(15,23,42,.65);
}

.appointment-dialog-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    margin-bottom: 20px;
}

.appointment-dialog-header h2 {
    margin: 0;
}

.appointment-dialog-close {
    flex-shrink: 0;
}

@media(max-width:900px){
    .scheduler-layout{
        grid-template-columns:1fr;
    }
}

/* Fixed-height appointment wizard with a persistent action footer. */
#appointmentDialog {
    height: min(760px, calc(100dvh - 32px));
    max-height: calc(100dvh - 32px);
    overflow: hidden;
    flex-direction: column;
}

#appointmentDialog[open] {
    display: flex;
}

#appointmentDialog > .appointment-dialog-header {
    flex: 0 0 auto;
}

#appointmentWizardContent {
    display: flex;
    flex: 1 1 auto;
    flex-direction: column;
    min-height: 0;
    overflow: hidden;
}

#appointmentWizardContent > #formTitle,
#appointmentWizardContent > .appointment-wizard-progress {
    flex: 0 0 auto;
}

#appointmentForm {
    display: flex;
    flex: 1 1 auto;
    flex-direction: column;
    min-height: 0;
    overflow: hidden;
}

#appointmentForm > .wizard-step:not([hidden]) {
    flex: 1 1 auto;
    min-height: 0;
    overflow-y: auto;
    overscroll-behavior: contain;
    padding: 2px 6px 12px 2px;
}

#appointmentForm > .appointment-wizard-footer {
    flex: 0 0 auto;
    border-top: 1px solid #dbe2ea;
    padding-top: 12px;
    background: var(--card-bg, #fff);
}

#appointmentForm .appointment-wizard-footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    min-width: 0;
    overflow-x: auto;
}

#appointmentForm .appointment-wizard-actions {
    display: flex;
    flex: 0 0 auto;
    align-items: center;
    flex-wrap: nowrap;
    gap: 8px;
    margin: 0;
}

#appointmentForm .appointment-wizard-navigation {
    display: flex;
    flex: 0 0 auto;
    align-items: center;
    gap: 8px;
    margin: 0 0 0 auto;
}

#appointmentForm .appointment-wizard-footer button {
    white-space: nowrap;
    flex-shrink: 0;
    min-width: max-content;
}

#appointmentForm .appointment-wizard-navigation {
    min-width: max-content;
}

#appointmentForm .appointment-wizard-footer {
    scrollbar-width: thin;
    scroll-padding-inline: 16px;
    padding-left: 2px;
    padding-right: 8px;
}

#appointmentForm .appointment-wizard-footer
.appointment-wizard-navigation {
    padding-left: 4px;
    padding-right: 4px;
}

@media (max-width: 650px) {
    #appointmentForm .appointment-wizard-footer {
        gap: 6px;
    }

    #appointmentForm .appointment-wizard-actions,
    #appointmentForm .appointment-wizard-navigation {
        gap: 4px;
    }

    #appointmentForm .appointment-wizard-footer button {
        padding-left: 8px;
        padding-right: 8px;
    }
}

#appointmentForm .appointment-wizard-footer
.appointment-wizard-actions,
#appointmentForm .appointment-wizard-footer
.appointment-wizard-navigation {
    align-self: center;
}





#appointmentForm #appointmentError {
    margin: 4px 0 8px;
    max-height: 64px;
    overflow-y: auto;
}

@media (max-height: 650px) {
    #appointmentDialog {
        padding: 12px;
    }

    #appointmentDialog .appointment-dialog-header {
        margin-bottom: 8px;
    }

    #appointmentDialog .appointment-wizard-progress {
        margin: 6px 0 10px;
        padding-bottom: 8px;
    }
}

/* Wizard navigation and visibility. */
.wizard-step[hidden],
.appointment-wizard-navigation [hidden],
#saveButton[hidden],
#newButton[hidden],
#deleteButton[hidden],
#invoiceButton[hidden] {
    display: none !important;
}

.appointment-wizard-progress {
    margin: 12px 0 20px;
    padding-bottom: 16px;
    border-bottom: 1px solid #ddd;
}

.appointment-wizard-progress-heading {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 12px;
    margin-bottom: 16px;
}

.appointment-wizard-steps {
    display: grid;
    grid-template-columns: repeat(6, minmax(0, 1fr));
    gap: 8px;
    list-style: none;
    padding: 0;
    margin: 0;
}

.appointment-wizard-steps li {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 6px;
    text-align: center;
    min-width: 0;
    color: #64748b;
}

.wizard-progress-number {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 34px;
    height: 34px;
    border-radius: 50%;
    background: #e2e8f0;
    color: #334155;
    font-weight: 700;
}

.appointment-wizard-steps li.is-current .wizard-progress-number {
    background: #2563eb;
    color: #fff;
}

.appointment-wizard-steps li.is-complete .wizard-progress-number {
    background: #167347;
    color: #fff;
}

.appointment-wizard-steps li.is-current {
    color: #2563eb;
    font-weight: 700;
}

.wizard-progress-label {
    font-size: 12px;
    overflow-wrap: anywhere;
}

@media (max-width: 600px) {
    .appointment-wizard-steps {
        gap: 3px;
    }

    .wizard-progress-number {
        width: 28px;
        height: 28px;
        font-size: 12px;
    }

    .wizard-progress-label {
        font-size: 10px;
    }
}

.appointment-wizard-navigation {
    display: flex;
    justify-content: space-between;
    gap: 12px;
    margin-top: 20px;
}

#wizardReviewSummary dl {
    display: grid;
    grid-template-columns: minmax(100px, 1fr) 2fr;
    gap: 8px 16px;
}

#wizardReviewSummary dt {
    font-weight: 600;
}

#wizardReviewSummary dd {
    margin: 0;
    overflow-wrap: anywhere;
}

.appointment-details-dialog {
    width: min(620px, calc(100vw - 32px));
}

.appointment-details-grid {
    display: grid;
    grid-template-columns: minmax(110px, 1fr) 2fr;
    gap: 12px 18px;
    margin: 0;
}

.appointment-details-grid dt {
    font-weight: 600;
    color: #64748b;
}

.appointment-details-grid dd {
    margin: 0;
    overflow-wrap: anywhere;
}

.appointment-details-actions {
    display: flex;
    justify-content: flex-end;
    flex-wrap: wrap;
    gap: 12px;
    margin-top: 24px;
}

@media (max-width: 480px) {
    .appointment-details-grid {
        grid-template-columns: 1fr;
        gap: 4px;
    }

    .appointment-details-grid dd {
        margin-bottom: 12px;
    }
}


/* Respect server-side visibility restrictions for role-protected controls. */
#openAppointmentWizardButton[hidden],
#appointmentDialog[hidden] {
    display: none !important;
}

</style>

<link rel="stylesheet"
      href="<?=htmlspecialchars(app_url('/assets/css/brite-scheduling.css'))?>">
<link rel="stylesheet"
      href="<?=htmlspecialchars(app_url('/assets/css/brite-workspace-tabs.css'), ENT_QUOTES, 'UTF-8')?>">

<div class="brite-tab-workspace"
     id="schedulingWorkspace"
     data-viewport="true"
     style="--brite-workspace-offset: 155px">

    <div class="brite-tab-toolbar">
        <div class="brite-tab-list"
             role="tablist"
             aria-label="Scheduling workspace">

            <button type="button"
                    class="brite-tab-button"
                    id="scheduleCalendarTab"
                    role="tab"
                    aria-controls="scheduleCalendarPanel"
                    aria-selected="true">
                Calendar
            </button>

            <button type="button"
                    class="brite-tab-button"
                    id="scheduleActionsTab"
                    role="tab"
                    aria-controls="scheduleActionsPanel"
                    aria-selected="false">
                Quick Actions
            </button>
        </div>

        <button
            type="button"
            id="openAppointmentWizardButton"
            <?= $canManageAppointments ? '' : 'hidden disabled' ?>>
            + New Appointment
        </button>
    </div>

<div id="cycle68bConfirmation" class="card" role="status" tabindex="-1" hidden style="margin-bottom:12px"></div>
<!-- Modal foundation; existing appointment form remains unchanged. -->
<dialog id="appointmentDialog"
        class="appointment-dialog"
        aria-labelledby="appointmentDialogTitle"
        <?= $canManageAppointments ? '' : 'hidden' ?>>
    <div class="appointment-dialog-header">
        <h2 id="appointmentDialogTitle">Appointment Wizard</h2>
        <button type="button"
                id="closeAppointmentDialog"
                class="appointment-dialog-close"
                aria-label="Close appointment wizard">
            Close
        </button>
    </div>
    <div id="appointmentWizardContent">
        <h3 id="formTitle">New Appointment</h3>

        <nav class="appointment-wizard-progress"
             aria-label="Appointment wizard progress">
            <div class="appointment-wizard-progress-heading">
                <span id="wizardStepIndicator">Step 1 of 6</span>
                <strong id="wizardStepTitle">Client</strong>
            </div>
            <ol class="appointment-wizard-steps"
                id="wizardProgressSteps">
                <li class="is-current" aria-current="step">
                    <span class="wizard-progress-number">1</span>
                    <span class="wizard-progress-label">Client</span>
                </li>
                <li>
                    <span class="wizard-progress-number">2</span>
                    <span class="wizard-progress-label">Location</span>
                </li>
                <li>
                    <span class="wizard-progress-number">3</span>
                    <span class="wizard-progress-label">Service</span>
                </li>
                <li>
                    <span class="wizard-progress-number">4</span>
                    <span class="wizard-progress-label">Date &amp; Time</span>
                </li>
                <li>
                    <span class="wizard-progress-number">5</span>
                    <span class="wizard-progress-label">Contractor</span>
                </li>
                <li>
                    <span class="wizard-progress-number">6</span>
                    <span class="wizard-progress-label">Review</span>
                </li>
            </ol>
        </nav>

        <form id="appointmentForm" class="scheduler-form">

        <input type="hidden" name="id" id="appointmentId">

        <section class="wizard-step" data-wizard-step="1" aria-label="Client">
        <label>Client</label>
        <select name="client_id" id="clientId" required>
            <option value="">No client</option>
        </select>

        </section>

        <section class="wizard-step" data-wizard-step="2" aria-label="Location">
        <label>Saved Client Location</label>
        <select name="client_location_id" id="clientLocationId">
            <option value="">Manual / Other Location</option>
        </select>

        <label>Location</label>
        <input name="location"
               id="appointmentLocation"
               placeholder="Location">

        </section>

        <section class="wizard-step" data-wizard-step="3" aria-label="Service">
        <label>Service</label>
        <select name="service_id" id="serviceId" required>
            <option value="">No service</option>
        </select>

        <label>Title</label>
        <input name="title"
               id="appointmentTitle"
               required
               placeholder="Appointment">

        <label>Description</label>
        <textarea name="description"
                  id="appointmentDescription"
                  placeholder="Notes"></textarea>

        </section>

        <section class="wizard-step" data-wizard-step="4" aria-label="Date & Time">

        <!-- Milestone 146E: simplified appointment date/time picker -->
        <div class="brite-datetime-picker" id="briteDateTimePicker">

            <div class="brite-datetime-heading">
                <h3>When should this appointment take place?</h3>
                <p>Select a date, start time, and duration.</p>
            </div>

            <div class="brite-datetime-section">
                <label for="briteAppointmentDate">Appointment Date</label>
                <input type="date"
                       id="briteAppointmentDate"
                       autocomplete="off">
            </div>

            <div class="brite-datetime-section">
                <span class="brite-datetime-label">Start Time</span>
                <div id="briteTimeSlots"
                     class="brite-time-slots"
                     role="group"
                     aria-label="Choose appointment start time">
                </div>
            </div>

            <div class="brite-datetime-section">
                <span class="brite-datetime-label">Duration</span>
                <div id="briteDurationPresets"
                     class="brite-duration-presets"
                     role="group"
                     aria-label="Choose appointment duration">

                    <button type="button" data-duration="30">30 min</button>
                    <button type="button" data-duration="60">1 hour</button>
                    <button type="button" data-duration="90">1.5 hours</button>
                    <button type="button" data-duration="120">2 hours</button>
                    <button type="button" data-duration="custom">Custom</button>
                </div>

                <div id="briteCustomDurationContainer" hidden>
                    <label for="briteCustomDuration">Duration in minutes</label>
                    <input type="number"
                           id="briteCustomDuration"
                           min="1"
                           max="1440"
                           step="1">
                </div>
            </div>

            <div class="brite-datetime-summary"
                 id="briteDateTimeSummary"
                 role="status"
                 aria-live="polite">
                Choose a date and start time to see your appointment summary.
            </div>
        </div>

        <label>Start</label>
        <input name="starts_at"
               id="startsAt"
               type="datetime-local"
               required>

        <label>End</label>
        <input name="ends_at"
               id="endsAt"
               type="datetime-local"
               required>

        <label>Status</label>
        <select name="status" id="appointmentStatus">
            <option value="tentative">Tentative</option>
            <option value="scheduled" selected>Scheduled</option>
            <option value="confirmed">Confirmed</option>
            <option value="in_progress">In Progress</option>
            <option value="completed">Completed</option>
            <option value="cancelled">Cancelled</option>
            <option value="no_show">No Show</option>
        </select>

        </section>

        <section class="wizard-step" data-wizard-step="5" aria-label="Contractor">
<div class="brite-contractor-layout">
<div class="brite-contractor-selection">
        <label for="contractorSearch">Find Contractors</label>
        <input id="contractorSearch"
               type="search"
               placeholder="Search contractors by name"
               autocomplete="off"
               aria-controls="contractorCheckboxList">

        <p id="contractorSelectionHelp" class="recommendations-help">
            Select any number of contractors using the checkboxes.
            Assignments are not saved until you save the appointment.
        </p>

        <div id="contractorSelectionCount"
             class="contractor-selection-count"
             role="status"
             aria-live="polite">0 contractors selected</div>

        <div id="contractorCheckboxList"
             class="contractor-checkbox-list"
             role="group"
             aria-label="Available contractor choices"></div>

        <div id="selectedContractorChips"
             class="selected-contractor-chips"
             aria-label="Selected contractors"></div>

        <select id="contractorId"
                multiple
                aria-hidden="true"
                tabindex="-1"
                class="contractor-native-select"></select>

        <p id="contractorConflictStatus"
           data-state="unassigned"
           role="status"
           aria-live="polite">
            Leave unassigned or select a contractor to check availability.
        </p>


</div>
<div class="brite-contractor-recommendation-column"><!-- Advisory contractor recommendations; no automatic assignment. -->
        <section id="contractorRecommendations"
                 class="contractor-recommendations"
                 aria-labelledby="recommendationsTitle">

            <h3 id="recommendationsTitle">
                Contractor Recommendations
            </h3>

            <p class="recommendations-help">
                Review geographic coverage, availability,
                qualifications, and workload before selecting
                a contractor.
            </p>

            <button type="button"
                    id="loadRecommendations"
                    disabled>
                Find Recommended Contractors
            </button>

            <p id="recommendationsStatus"
               role="status"
               aria-live="polite">
                Select a saved client location and appointment
                times to view recommendations.
            </p>

            <div id="recommendationsResults"></div>

            <p class="recommendations-help">
                Recommendations are advisory.
                Selecting a contractor does not assign them
                until the appointment is saved.
            </p>
        </section>


</div>
</div>
</section>

        <section class="wizard-step"
                 data-wizard-step="6"
                 aria-label="Review">
            <div id="wizardReviewSummary"
                 aria-live="polite"></div>
            <section id="cycle68PricingPreview" aria-label="Contractor pricing preview" aria-live="polite"></section>
        </section>

        <div class="appointment-wizard-footer">
        <div class="appointment-wizard-actions">
            <button type="submit" id="saveButton">Create Appointment</button>
            <button type="button" id="newButton">Clear</button>
            <button type="button" id="deleteButton"
                    style="display:none">Delete</button>
<button type="button"
        id="invoiceButton"
        style="display:none">
    Generate Invoice
</button>
        </div>

        <p id="appointmentError" class="danger"></p>

        <div class="appointment-wizard-navigation">
            <button type="button"
                    id="wizardBackButton"
                    disabled>
                Back
            </button>
            <button type="button"
                    id="wizardNextButton">
                Next
            </button>
        </div>
        </div>
    </form>
    </div>
</dialog>

<!-- Read-only appointment details modal. -->
<dialog id="appointmentDetailsDialog"
        class="appointment-dialog appointment-details-dialog"
        aria-labelledby="appointmentDetailsTitle">
    <div class="appointment-dialog-header">
        <h2 id="appointmentDetailsTitle">Appointment Details</h2>
        <button type="button"
                id="closeAppointmentDetails"
                aria-label="Close appointment details">Close</button>
    </div>
    <div id="appointmentDetailsContent"></div>
    <p id="appointmentDetailsEditStatus"
       role="status"
       aria-live="polite"></p>
    <div class="appointment-details-actions">
        <button type="button" id="editAppointmentDetails">
            Edit Appointment
        </button>
        <button type="button" id="duplicateAppointmentDetails">
            Duplicate Appointment
        </button>
        <button type="button" id="dismissAppointmentDetails">
            Close
        </button>
    </div>
</dialog>

    <div class="brite-tab-panels">
        <section class="brite-tab-panel"
                 id="scheduleCalendarPanel"
                 role="tabpanel"
                 aria-labelledby="scheduleCalendarTab"
                 tabindex="0">
            <div class="scheduler-layout">
                <div class="card">
                    <div id="calendar"></div>
                </div>
            </div>
        </section>

        <section class="brite-tab-panel"
                 id="scheduleActionsPanel"
                 role="tabpanel"
                 aria-labelledby="scheduleActionsTab"
                 tabindex="0"
                 hidden>
            <div class="card">
                <h2>Scheduling quick actions</h2>
                <?php if ($canManageAppointments): ?>
                <p>Manage appointments using the calendar or start a new appointment with the toolbar.</p>
                <?php else: ?>
                <p>View scheduled appointments and select an appointment on the calendar to see its details.</p>
                <?php endif; ?>
                <?php if ($canManageAppointments): ?>
                <button type="button"
                        id="openAppointmentWizardFromActions">
                    + New Appointment
                </button>
                <?php endif; ?>
                <button type="button"
                        id="returnToScheduleCalendar">
                    Return to Calendar
                </button>
            </div>
        </section>
    </div>
</div>

<script src="<?=htmlspecialchars(app_url('/assets/js/owner-assignment-details.js'), ENT_QUOTES, 'UTF-8')?>"></script>
<script src="<?=htmlspecialchars(app_url('/assets/js/brite-workspace-tabs.js'), ENT_QUOTES, 'UTF-8')?>"></script>

<script>
window.briteTenantTimezone = <?=json_encode(
    (string)$tenantTimezone,
    JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS |
    JSON_HEX_QUOT | JSON_THROW_ON_ERROR
)?>;

let calendar;
let services=[];
let detailsEvent = null;

document.getElementById('openAppointmentWizardFromActions')
    ?.addEventListener('click', () => {
        document.getElementById('openAppointmentWizardButton')?.click();
    });

document.getElementById('returnToScheduleCalendar')
    ?.addEventListener('click', () => {
        document.getElementById('scheduleCalendarTab')?.click();
    });

const detailsDialog =
    document.getElementById('appointmentDetailsDialog');
const detailsContent =
    document.getElementById('appointmentDetailsContent');

function appointmentDetailsDate(value) {
    if (!value) return 'Not specified';

    const date = value instanceof Date
        ? new Date(value.getTime())
        : new Date(calendarEventDateTime(value) + 'Z');

    if (!Number.isFinite(date.getTime())) {
        return String(value);
    }

    const use24HourTime =
        window.BRITE_CALENDAR_PREFERENCES?.time_format === '24h';

    return new Intl.DateTimeFormat('en-US', {
        timeZone: 'UTC',
        dateStyle: 'medium',
        timeStyle: 'short',
        hour12: !use24HourTime
    }).format(date);
}

function showAppointmentDetails(event) {
    if (!event || detailsDialog.open) return;

    detailsEvent = event;
    window.briteOwnerDetailsEvent = event;
    const appointment = event.extendedProps || {};
    const contractorIds = String(appointment.contractor_ids || '')
        .split(',')
        .map(value => value.trim())
        .filter(Boolean);

    const contractorSelect =
        document.getElementById('contractorId');

    const contractorNames = contractorIds.map(id => {
        const option = Array.from(
            contractorSelect?.options || []
        ).find(item => item.value === id);
        return option
            ? option.textContent.trim()
            : 'Contractor #' + id;
    });

    const role = BRITE_CALENDAR_ROLE;
    const isManagementRole = ['owner', 'admin', 'scheduler'].includes(role);

    const fields = [
        ['Title', appointment.title || event.title || 'Appointment'],
        ...(role !== 'client' ? [[
            'Client',
            [
                appointment.client_first,
                appointment.client_last
            ].filter(Boolean).join(' ') || 'Not specified'
        ]] : []),
        ['Service', appointment.service_name || 'Not specified'],
        ['Start', appointmentDetailsDate(event.start)],
        ['End', appointmentDetailsDate(event.end)],
        ['Status', String(appointment.status || 'scheduled')
            .replaceAll('_', ' ')],
        ['Location', appointment.location || 'Not specified'],
        ...(isManagementRole
            ? [['Contractors', contractorNames.join(', ') || 'Unassigned']]
            : [])
    ];

    const list = document.createElement('dl');
    list.className = 'appointment-details-grid';

    for (const [label, value] of fields) {
        const dt = document.createElement('dt');
        const dd = document.createElement('dd');
        dt.textContent = label;
        dd.textContent = value;
        list.append(dt, dd);
    }

    detailsContent.replaceChildren(list);
    document.getElementById('appointmentDetailsEditStatus').textContent = '';
    detailsDialog.showModal();
}

function closeAppointmentDetails() {
    if (detailsDialog.open) detailsDialog.close();
}

document.getElementById('closeAppointmentDetails')
    .addEventListener('click', closeAppointmentDetails);
document.getElementById('dismissAppointmentDetails')
    .addEventListener('click', closeAppointmentDetails);

document.getElementById('editAppointmentDetails')
    .addEventListener('click', async () => {
        if (BRITE_CALENDAR_READ_ONLY) return;
        const event = detailsEvent;
        if (!event) return;

        const button = document.getElementById('editAppointmentDetails');
        const status = document.getElementById('appointmentDetailsEditStatus');

        if (button.disabled) return;

        button.disabled = true;
        status.textContent = 'Loading appointment for editing...';

        try {
            const loaded = await editAppointment(event);

            if (loaded) {
                closeAppointmentDetails();
                openAppointmentWizard();
            } else {
                status.textContent =
                    appointmentError.textContent ||
                    'Unable to prepare this appointment for editing.';
            }
        } catch (error) {
            status.textContent =
                'Unable to prepare appointment: ' + error.message;
        } finally {
            button.disabled = false;
        }
    });

document.getElementById('duplicateAppointmentDetails')
    .addEventListener('click', async () => {
        if (BRITE_CALENDAR_READ_ONLY) return;
        const event = detailsEvent;
        if (!event) return;

        const button = document.getElementById('duplicateAppointmentDetails');
        const editButton = document.getElementById('editAppointmentDetails');
        const status = document.getElementById('appointmentDetailsEditStatus');

        if (button.disabled || editButton.disabled) return;

        button.disabled = true;
        editButton.disabled = true;
        status.textContent = 'Preparing duplicate appointment...';

        try {
            const loaded = await editAppointment(event);

            if (loaded) {
                appointmentId.value = '';
                formTitle.textContent = 'Duplicate Appointment';
                saveButton.textContent = 'Create Appointment';
                invalidateRecommendations();
                invalidateContractorConflict();

                closeAppointmentDetails();
                openAppointmentWizard();
            } else {
                status.textContent =
                    appointmentError.textContent ||
                    'Unable to prepare duplicate appointment.';
            }
        } catch (error) {
            status.textContent =
                'Unable to duplicate appointment: ' + error.message;
        } finally {
            button.disabled = false;
            editButton.disabled = false;
        }
    });

detailsDialog.addEventListener('close', () => {
    detailsEvent = null;
});

// Six-step appointment wizard navigation.
const wizardStepNames = [
    'Client',
    'Location',
    'Service',
    'Date & Time',
    'Contractor',
    'Review'
];

let wizardCurrentStep = 1;

function wizardFieldValue(id) {
    const field = document.getElementById(id);
    if (!field) return '';

    if (field.tagName === 'SELECT') {
        if (field.multiple) {
            return Array.from(field.selectedOptions)
                .map(option => option.textContent.trim())
                .filter(Boolean)
                .join(', ');
        }

        return field.selectedOptions[0]?.textContent?.trim() || '';
    }

    return field.value?.trim() || '';
}

function wizardReviewDate(value) {
    if (!value) return null;

    // Represent tenant-local wall time in UTC to avoid browser
    // timezone and daylight-saving adjustments.
    const date = new Date(value + ':00Z');

    if (!Number.isFinite(date.getTime())) return null;

    const pad = number => String(number).padStart(2, '0');

    const normalized =
        String(date.getUTCFullYear()).padStart(4, '0') + '-' +
        pad(date.getUTCMonth() + 1) + '-' +
        pad(date.getUTCDate()) + 'T' +
        pad(date.getUTCHours()) + ':' +
        pad(date.getUTCMinutes());

    if (normalized !== value.slice(0, 16)) return null;

    return date;
}

function wizardReview() {
    const summary = document.getElementById('wizardReviewSummary');
    if (!summary) return;

    summary.replaceChildren();

    const grid = document.createElement('div');
    grid.className = 'brite-review-six-grid';

    function reviewCard(title, icon, entries) {
        const card = document.createElement('section');
        card.className = 'brite-review-type-card';
        card.tabIndex = 0;
        card.setAttribute('aria-label', title + ' review details');

        const heading = document.createElement('h3');
        heading.className = 'brite-review-type-heading';

        const symbol = document.createElement('span');
        symbol.className = 'brite-review-type-icon';
        symbol.setAttribute('aria-hidden', 'true');
        symbol.textContent = icon;

        heading.append(symbol, document.createTextNode(title));
        card.appendChild(heading);

        const body = document.createElement('div');
        body.className = 'brite-review-type-body';

        entries.forEach(([label, value]) => {
            const row = document.createElement('div');
            row.className = 'brite-review-type-row';

            const name = document.createElement('span');
            name.className = 'brite-review-type-label';
            name.textContent = label;

            const content = document.createElement('span');
            content.className = 'brite-review-type-value';
            content.textContent = value || 'Not specified';
            content.title = content.textContent;

            row.append(name, content);
            body.appendChild(row);
        });

        card.appendChild(body);
        return card;
    }

    const start = wizardReviewDate(
        document.getElementById('startsAt').value
    );
    const end = wizardReviewDate(
        document.getElementById('endsAt').value
    );

    function formatHeadingTime(date) {
        return date
            ? date.toLocaleTimeString('en-US', {
                timeZone: 'UTC',
                hour: 'numeric',
                minute: '2-digit',
                hour12:
                    window.BRITE_CALENDAR_PREFERENCES?.time_format !== '24h'
            })
            : '';
    }

    const date = start
        ? start.toLocaleDateString('en-US', {
            timeZone: 'UTC',
            weekday: 'short',
            year: 'numeric',
            month: 'short',
            day: 'numeric'
        })
        : 'Not specified';

    const time = start && end
        ? formatHeadingTime(start) + ' – ' + formatHeadingTime(end)
        : 'Not specified';

    let duration = 'Not specified';
    if (start && end) {
        const minutes = Math.round(
            (end.getTime() - start.getTime()) / 60000
        );
        if (minutes > 0) {
            const hours = Math.floor(minutes / 60);
            const remainder = minutes % 60;
            duration = [
                hours ? hours + 'h' : '',
                remainder ? remainder + 'm' : ''
            ].filter(Boolean).join(' ');
        }
    }

    const dateEntries = [
        ['Date', date],
        ['Time', time],
        ['Duration', duration]
    ];

    if (start && end &&
        start.toISOString().slice(0, 10) !==
        end.toISOString().slice(0, 10)) {
        dateEntries.push([
            'Ends',
            end.toLocaleDateString('en-US', {
                timeZone: 'UTC',
                weekday: 'short',
                year: 'numeric',
                month: 'short',
                day: 'numeric'
            })
        ]);
    }

    [
        reviewCard('Client', '◉', [
            ['Client', wizardFieldValue('clientId')]
        ]),
        reviewCard('Location', '⌖', [
            ['Saved Location', wizardFieldValue('clientLocationId')],
            ['Address', wizardFieldValue('appointmentLocation')]
        ]),
        reviewCard('Service', '⚙', [
            ['Service', wizardFieldValue('serviceId')]
        ]),
        reviewCard('Date & Time', '◷', dateEntries),
        reviewCard('Contractors', '♧', [
            ['Assigned', (() => {
                const checked = [
                    ...document.querySelectorAll(
                        '#contractorCheckboxList input[type="checkbox"]:checked'
                    )
                ];

                const names = checked.map(input => {
                    const label = input.closest('label');
                    return label?.textContent?.trim() ||
                        input.value;
                });

                return names.length
                    ? names.join(', ')
                    : wizardFieldValue('contractorId');
            })()]
        ]),
        reviewCard('Details', '☷', [
            ['Title', wizardFieldValue('appointmentTitle')],
            ['Description', wizardFieldValue('appointmentDescription')],
            ['Status', wizardFieldValue('appointmentStatus')]
        ])
    ].forEach(card => grid.appendChild(card));

    summary.appendChild(grid);
}

function wizardClearFieldError(field) {
    field.classList.remove('wizard-field-invalid');
    field.removeAttribute('aria-invalid');

    const errorId = field.id + '-wizard-error';
    const error = document.getElementById(errorId);

    if (error) error.remove();

    const describedBy = (field.getAttribute('aria-describedby') || '')
        .split(/\\s+/)
        .filter(value => value && value !== errorId);

    if (describedBy.length) {
        field.setAttribute('aria-describedby', describedBy.join(' '));
    } else {
        field.removeAttribute('aria-describedby');
    }
}

function wizardShowFieldError(field) {
    wizardClearFieldError(field);

    const error = document.createElement('p');
    error.id = field.id + '-wizard-error';
    error.className = 'wizard-field-error';
    error.textContent = field.validationMessage ||
        'Please check this field.';

    field.insertAdjacentElement('afterend', error);
    field.classList.add('wizard-field-invalid');
    field.setAttribute('aria-invalid', 'true');

    const describedBy = (field.getAttribute('aria-describedby') || '')
        .split(/\\s+/)
        .filter(Boolean);

    describedBy.push(error.id);
    field.setAttribute('aria-describedby', describedBy.join(' '));
}

function wizardValidateCurrentStep() {
    const step = document.querySelector(
        '.wizard-step[data-wizard-step="' +
        wizardCurrentStep + '"]'
    );

    if (!step) return false;

    if (wizardCurrentStep === 4) {
        const start = document.getElementById('startsAt');
        const end = document.getElementById('endsAt');

        start.setCustomValidity('');
        end.setCustomValidity('');

        if (window.briteValidateAppointmentStart) {
            const startError = window.briteValidateAppointmentStart();
            if (startError) start.setCustomValidity(startError);
        }

        // datetime-local values use normalized local wall-clock
        // timestamps. Compare them without browser timezone parsing.
        if (start.value && end.value &&
            end.value <= start.value) {
            end.setCustomValidity(
                'End time must be after start time.'
            );
        }
    }

    let firstInvalid = null;

    for (const field of step.querySelectorAll(
        'input, select, textarea'
    )) {
        if (!field.willValidate) continue;

        if (!field.checkValidity()) {
            wizardShowFieldError(field);
            if (!firstInvalid) firstInvalid = field;
        } else {
            wizardClearFieldError(field);
        }
    }

    if (firstInvalid) {
        firstInvalid.focus();
        return false;
    }

    return true;
}

document.getElementById('appointmentForm')
    .addEventListener('input', event => {
        const field = event.target;

        if (!field.matches('input, select, textarea')) return;

        if (field.id === 'startsAt' || field.id === 'endsAt') {
            document.getElementById('endsAt').setCustomValidity('');
        }

        if (field.checkValidity()) {
            wizardClearFieldError(field);
        }
    });

document.getElementById('appointmentForm')
    .addEventListener('change', event => {
        const field = event.target;

        if (!field.matches('input, select, textarea')) return;

        if (field.checkValidity()) {
            wizardClearFieldError(field);
        }
    });

function wizardShowStep(number) {
    wizardCurrentStep = Math.max(
        1,
        Math.min(wizardStepNames.length, number)
    );

    document.querySelectorAll('.wizard-step').forEach(step => {
        step.hidden = Number(step.dataset.wizardStep) !==
            wizardCurrentStep;
    });

    document.getElementById('wizardStepIndicator').textContent =
        'Step ' + wizardCurrentStep + ' of 6';

    document.getElementById('wizardStepTitle').textContent =
        wizardStepNames[wizardCurrentStep - 1];

    document.querySelectorAll('#wizardProgressSteps li')
        .forEach((item, index) => {
            const stepNumber = index + 1;
            const current = stepNumber === wizardCurrentStep;
            const complete = stepNumber < wizardCurrentStep;

            item.classList.toggle('is-current', current);
            item.classList.toggle('is-complete', complete);

            if (current) {
                item.setAttribute('aria-current', 'step');
            } else {
                item.removeAttribute('aria-current');
            }

            item.querySelector('.wizard-progress-number')
                .textContent = complete ? '✓' : stepNumber;
        });

    document.getElementById('wizardBackButton').disabled =
        wizardCurrentStep === 1;

    document.getElementById('wizardNextButton').hidden =
        wizardCurrentStep === 6;

    const review = wizardCurrentStep === 6;

    const save = document.getElementById('saveButton');
    const clear = document.getElementById('newButton');
    const remove = document.getElementById('deleteButton');
    const invoice = document.getElementById('invoiceButton');

    save.hidden = !review;
    clear.hidden = !review;

    const editing = Boolean(
        document.getElementById('appointmentId').value
    );

    const completed =
        document.getElementById('appointmentStatus').value ===
        'completed';

    // Remove legacy inline display rules before applying wizard visibility.
    remove.style.removeProperty('display');
    invoice.style.removeProperty('display');

    remove.hidden = !review || !editing;
    invoice.hidden = !review || !editing || !completed;

    if (review) wizardReview();

    if (wizardCurrentStep === 4 &&
        window.briteSyncDateTimePicker) {
        window.briteSyncDateTimePicker();
    }

    if (wizardCurrentStep === 5) {
        void checkContractorConflict();
    }
}

document.getElementById('wizardBackButton')
    .addEventListener('click', () => {
        wizardShowStep(wizardCurrentStep - 1);
    });

document.getElementById('wizardNextButton')
    .addEventListener('click', async () => {
        if (!wizardValidateCurrentStep()) return;

        if (wizardCurrentStep === 5) {
            const allowed = await checkContractorConflict();

            if (!allowed || wizardCurrentStep !== 5) return;
        }

        wizardShowStep(wizardCurrentStep + 1);
    });

document.getElementById('startsAt')
    .addEventListener('input', () => {
        document.getElementById('endsAt').setCustomValidity('');
    });

document.getElementById('endsAt')
    .addEventListener('input', event => {
        event.target.setCustomValidity('');
    });

// Appointment wizard modal foundation.
const appointmentDialog =
    document.getElementById('appointmentDialog');
const closeAppointmentDialog =
    document.getElementById('closeAppointmentDialog');
const appointmentWizardContent =
    document.getElementById('appointmentWizardContent');

function openAppointmentWizard(){
    if (BRITE_CALENDAR_READ_ONLY) return;
    if(!appointmentDialog || appointmentDialog.open) return;

    // Management roles must be able to display the native dialog.
    // Read-only roles are blocked by the guard above.
    appointmentDialog.hidden = false;

    wizardShowStep(1);

    if (window.briteSyncDateTimePicker) {
        window.briteSyncDateTimePicker();
    }

    appointmentDialog.showModal();
}

function closeAppointmentWizard(){
    if(appointmentDialog?.open){
        appointmentDialog.close();
    }
}

// Reset abandoned wizard state, including native Escape dismissal.
appointmentDialog.addEventListener('close', () => {
    resetForm();
    wizardShowStep(1);
});

closeAppointmentDialog.addEventListener(
    'click',
    closeAppointmentWizard
);

document.getElementById('openAppointmentWizardButton')
    .addEventListener('click', () => {
        if (BRITE_CALENDAR_READ_ONLY) return;
        resetForm();
        openAppointmentWizard();
        window.briteCycle68c?.promptRestore();
    });


const appointmentForm = document.getElementById('appointmentForm');
const appointmentId = document.getElementById('appointmentId');
const invoiceButton = document.getElementById('invoiceButton');
const clientId = document.getElementById('clientId');
const serviceId = document.getElementById('serviceId');
const contractorId = document.getElementById('contractorId');
const contractorConflictStatus =
    document.getElementById('contractorConflictStatus');

let contractorConflictSequence = 0;

function contractorConflictInputs() {
    const contractor_ids = Array.from(contractorId.selectedOptions)
        .map(option => option.value)
        .filter(Boolean);

    if (!contractor_ids.length) return null;

    const start = sqlDateTime(startsAt.value);
    const end = sqlDateTime(endsAt.value);

    if (!start || !end || end <= start) return false;

    return {
        contractor_ids,
        starts_at: start,
        ends_at: end,
        exclude_appointment_id: appointmentId.value
    };
}

function invalidateContractorConflict() {
    ++contractorConflictSequence;
    const inputs = contractorConflictInputs();

    if (inputs === null) {
        contractorConflictStatus.dataset.state = 'unassigned';
        contractorConflictStatus.textContent =
            'No contractors assigned. You may continue.';
    } else {
        contractorConflictStatus.dataset.state = 'checking';
        contractorConflictStatus.textContent =
            'Contractor availability needs to be checked.';
    }
}

async function checkContractorConflict() {
    const sequence = ++contractorConflictSequence;
    const inputs = contractorConflictInputs();

    if (inputs === null) {
        contractorConflictStatus.dataset.state = 'unassigned';
        contractorConflictStatus.textContent =
            'No contractors assigned. You may continue.';
        return true;
    }

    if (inputs === false) {
        contractorConflictStatus.dataset.state = 'conflict';
        contractorConflictStatus.textContent =
            'Enter a valid appointment start and end time.';
        return false;
    }

    const signature = JSON.stringify(inputs);
    contractorConflictStatus.dataset.state = 'checking';
    contractorConflictStatus.textContent =
        'Checking availability for ' +
        inputs.contractor_ids.length + ' contractor(s)...';

    try {
        const results = await Promise.all(
            inputs.contractor_ids.map(async id => {
                const params = new URLSearchParams({
                    contractor_id: id,
                    starts_at: inputs.starts_at,
                    ends_at: inputs.ends_at
                });

                if (inputs.exclude_appointment_id) {
                    params.set(
                        'exclude_appointment_id',
                        inputs.exclude_appointment_id
                    );
                }

                const data = await api(
                    '/api/contractor_conflict_check.php?' +
                    params.toString()
                );

                if (data.ok !== true ||
                    typeof data.available !== 'boolean') {
                    throw new Error('Unexpected availability response');
                }

                const option = Array.from(contractorId.options)
                    .find(item => item.value === id);

                return {
                    id,
                    name: option?.textContent.trim() ||
                        ('Contractor ' + id),
                    available: data.available,
                    reason: data.reason
                };
            })
        );

        if (sequence !== contractorConflictSequence ||
            JSON.stringify(contractorConflictInputs()) !== signature) {
            return false;
        }

        const conflicts = results.filter(item => !item.available);

        if (conflicts.length) {
            contractorConflictStatus.dataset.state = 'conflict';
            contractorConflictStatus.textContent =
                'Scheduling conflict: ' +
                conflicts.map(item =>
                    item.name + ': ' +
                    (item.reason || 'Unavailable')
                ).join(' | ');
            return false;
        }

        contractorConflictStatus.dataset.state = 'available';
        contractorConflictStatus.textContent =
            'All ' + results.length +
            ' selected contractor(s) are available.';
        return true;

    } catch (error) {
        if (sequence === contractorConflictSequence) {
            contractorConflictStatus.dataset.state = 'conflict';
            contractorConflictStatus.textContent =
                'Unable to verify contractor availability: ' +
                error.message;
        }
        return false;
    }
}

const appointmentTitle = document.getElementById('appointmentTitle');
const startsAt = document.getElementById('startsAt');
const endsAt = document.getElementById('endsAt');
const appointmentStatus = document.getElementById('appointmentStatus');
const appointmentLocation = document.getElementById('appointmentLocation');
const clientLocationId = document.getElementById('clientLocationId');

for (const element of [
    contractorId,
    startsAt,
    endsAt,
    appointmentId
]) {
    element.addEventListener('change', () => {
        invalidateContractorConflict();

        if (wizardCurrentStep === 5) {
            void checkContractorConflict();
        }
    });

    element.addEventListener('input', () => {
        invalidateContractorConflict();

        if (wizardCurrentStep === 5) {
            void checkContractorConflict();
        }
    });
}

let locationLoadSequence = 0;

// Advisory recommendations never save or assign contractors.
const recommendationsButton =
    document.getElementById('loadRecommendations');
const recommendationsStatus =
    document.getElementById('recommendationsStatus');
const recommendationsResults =
    document.getElementById('recommendationsResults');

let recommendationSequence = 0;
let recommendationSignature = '';

function recommendationInputs(){
    const location = clientLocationId.value;
    const start = sqlDateTime(startsAt.value);
    const end = sqlDateTime(endsAt.value);

    if(!location || !start || !end || end <= start){
        return null;
    }

    return {
        location_id: location,
        starts_at: start,
        ends_at: end,
        service_id: serviceId.value,
        exclude_appointment_id: appointmentId.value
    };
}

function recommendationKey(inputs){
    return JSON.stringify(inputs);
}

function invalidateRecommendations(){
    ++recommendationSequence;
    recommendationSignature = '';
    recommendationsResults.replaceChildren();

    const inputs = recommendationInputs();
    recommendationsButton.disabled = !inputs;

    recommendationsStatus.textContent = inputs
        ? 'Ready to find recommended contractors.'
        : 'Select a saved client location and valid appointment times.';
}

function recommendationText(parent, className, content){
    const element = document.createElement('div');
    element.className = className;
    element.textContent = content;
    parent.appendChild(element);
    return element;
}

function syncRecommendedContractorSelection(){
    const selectedIds = new Set(
        Array.from(contractorId.selectedOptions)
            .map(option => option.value)
    );

    recommendationsResults.querySelectorAll(
        '.recommendation-card'
    ).forEach(card => {
        const selected = selectedIds.has(card.dataset.contractorId);
        card.classList.toggle('is-selected', selected);

        const button = card.querySelector('.recommendation-select');

        if(button){
            const eligible = card.dataset.eligible === 'true';
            button.disabled = !eligible && !selected;
            button.textContent = selected
                ? 'Remove Contractor'
                : 'Select Contractor';
            button.setAttribute('aria-pressed', String(selected));
            button.title = !eligible && !selected
                ? 'Contractor is not eligible for review'
                : '';
        }
    });
}

const contractorSearch =
    document.getElementById('contractorSearch');
const contractorCheckboxList =
    document.getElementById('contractorCheckboxList');
const selectedContractorChips =
    document.getElementById('selectedContractorChips');
const contractorSelectionCount =
    document.getElementById('contractorSelectionCount');

function renderContractorSelection() {
    const query = contractorSearch.value.trim().toLowerCase();
    const options = Array.from(contractorId.options);
    const selected = options.filter(option => option.selected);

    contractorSelectionCount.textContent =
        selected.length + ' contractor(s) selected';

    contractorCheckboxList.replaceChildren();
    selectedContractorChips.replaceChildren();

    let visible = 0;

    for (const option of options) {
        const name = option.textContent.trim();

        if (!name.toLowerCase().includes(query)) continue;
        visible++;

        const row = document.createElement('label');
        row.className = 'contractor-checkbox-row';
        row.classList.toggle('is-selected', option.selected);

        const checkbox = document.createElement('input');
        checkbox.type = 'checkbox';
        checkbox.checked = option.selected;
        checkbox.value = option.value;

        const label = document.createElement('span');
        label.textContent = name;

        checkbox.addEventListener('change', () => {
            option.selected = checkbox.checked;
            contractorId.dispatchEvent(
                new Event('change', { bubbles: true })
            );
        });

        row.append(checkbox, label);
        contractorCheckboxList.appendChild(row);
    }

    if (!visible) {
        const empty = document.createElement('p');
        empty.className = 'contractor-empty-message';
        empty.textContent = options.length
            ? 'No contractors match your search.'
            : 'No contractors are available in the selection list.';
        contractorCheckboxList.appendChild(empty);
    }

    for (const option of selected) {
        const chip = document.createElement('span');
        chip.className = 'selected-contractor-chip';

        const name = document.createElement('span');
        name.textContent = option.textContent.trim();

        const remove = document.createElement('button');
        remove.type = 'button';
        remove.textContent = 'Remove';
        remove.setAttribute(
            'aria-label',
            'Remove ' + option.textContent.trim()
        );

        remove.addEventListener('click', () => {
            option.selected = false;
            contractorId.dispatchEvent(
                new Event('change', { bubbles: true })
            );
        });

        chip.append(name, remove);
        selectedContractorChips.appendChild(chip);
    }

    syncRecommendedContractorSelection();
}

contractorSearch.addEventListener(
    'input',
    renderContractorSelection
);

contractorId.addEventListener(
    'change',
    renderContractorSelection
);

function renderRecommendations(contractors){
    recommendationsResults.replaceChildren();

    if(!contractors.length){
        recommendationsStatus.textContent =
            'No contractors match this location and time. ' +
            'Review geographic coverage and availability.';
        return;
    }

    recommendationsStatus.textContent =
        contractors.length + ' contractor recommendation(s) found.';

    function addDetail(parent, label, value, state = ''){
        const detail = document.createElement('div');
        detail.className = 'recommendation-detail';

        recommendationText(
            detail,
            'recommendation-detail-label',
            label
        );

        const valueElement = recommendationText(
            detail,
            'recommendation-detail-value',
            value
        );

        if(state){
            valueElement.classList.add('recommendation-status');
            valueElement.dataset.state = state;
        }

        parent.appendChild(detail);
    }

    for(const contractor of contractors){
        const ranking = contractor.ranking || {};
        const workload = ranking.workload || {};
        const id = String(contractor.contractor_id);

        const card = document.createElement('article');
        card.className = 'recommendation-card';
        card.dataset.contractorId = id;
        card.dataset.eligible =
            String(Boolean(contractor.eligible_for_review));

        const header = document.createElement('div');
        header.className = 'recommendation-card-header';

        recommendationText(
            header,
            'recommendation-card-name',
            [
                contractor.first_name,
                contractor.last_name
            ].filter(Boolean).join(' ') || 'Unnamed Contractor'
        );

        recommendationText(
            header,
            'recommendation-score',
            Number(ranking.score || 0).toFixed(2) + ' / 100'
        );

        card.appendChild(header);

        const details = document.createElement('div');
        details.className = 'recommendation-card-details';

        addDetail(
            details,
            'Qualifications',
            contractor.qualification_status || 'Unverified',
            'neutral'
        );

        const available = Boolean(
            contractor.availability_ok && contractor.conflict_free
        );

        addDetail(
            details,
            'Availability',
            available ? 'Available' : 'Restricted',
            available ? 'positive' : 'negative'
        );

        const geographicMatch = Boolean(contractor.geographic_match);

        addDetail(
            details,
            'Geographic coverage',
            geographicMatch ? 'Matched' : 'Not matched',
            geographicMatch ? 'positive' : 'negative'
        );

        addDetail(
            details,
            'Workload',
            Number(workload.hours || 0) + ' hours / ' +
            Number(workload.jobs || 0) + ' jobs'
        );

        const offering = contractor.service_offering || {};
        const offeringLabel = {
            offered: 'Offered',
            not_offered: 'Not offered',
            not_configured: 'Not configured',
            not_evaluated: 'Select a service'
        }[offering.status] || 'Unknown';
        addDetail(
            details,
            'Service offering',
            offeringLabel,
            offering.status === 'offered' ? 'positive' : 'neutral'
        );
        const rate = Number(offering.rate);
        const rateLabel = offering.status === 'offered' &&
            offering.rate !== null && offering.rate !== undefined &&
            Number.isFinite(rate)
            ? new Intl.NumberFormat('en-US', {
                style: 'currency', currency: 'USD'
            }).format(rate) + (offering.pricing_model === 'hourly' ? ' / hour' : ' flat')
            : 'Not configured';
        addDetail(details, 'Contractor rate', rateLabel);

        const dispatch = contractor.dispatch_explanation || {};
        const dispatchLabels = {
            restricted: 'Restricted',
            manual_review: 'Manual review required',
            ready_for_review: 'Ready for review'
        };
        addDetail(
            details,
            'Dispatch review',
            dispatchLabels[dispatch.status] || 'Review required',
            dispatch.status === 'ready_for_review' ? 'positive'
                : dispatch.status === 'restricted' ? 'negative' : 'neutral'
        );
        const distance = ranking.distance_miles;
        if(distance !== null && distance !== undefined &&
           Number.isFinite(Number(distance))){
            addDetail(details, 'Service-area distance',
                Number(distance).toFixed(1) + ' miles (straight line)');
        }

        card.appendChild(details);

        for(const area of contractor.service_area_explanations || []){
            recommendationText(card, 'recommendation-details', area);
        }
        for(const issue of dispatch.issues || []){
            recommendationText(card, 'recommendation-details', 'Review: ' + issue);
        }

        for(const explanation of ranking.explanations || []){
            recommendationText(
                card,
                'recommendation-details',
                explanation
            );
        }

        const actions = document.createElement('div');
        actions.className = 'recommendation-card-actions';

        const select = document.createElement('button');
        select.type = 'button';
        select.className = 'recommendation-select';

        const eligible = Boolean(contractor.eligible_for_review);
        select.disabled = !eligible &&
            !Array.from(contractorId.selectedOptions)
                .some(option => option.value === id);

        if(!eligible){
            select.title = 'Contractor is not eligible for review';
        }

        select.setAttribute('aria-pressed', 'false');

        select.addEventListener('click', () => {
            const inputs = recommendationInputs();

            if(!inputs ||
               recommendationKey(inputs) !== recommendationSignature){
                invalidateRecommendations();
                return;
            }

            const option = Array.from(contractorId.options)
                .find(item => item.value === id);

            if(!option){
                recommendationsStatus.textContent =
                    'Contractor is no longer in the selection list.';
                return;
            }

            if(!eligible && !option.selected){
                recommendationsStatus.textContent =
                    'This contractor is not eligible for selection.';
                return;
            }

            option.selected = !option.selected;

            contractorId.dispatchEvent(
                new Event('change', { bubbles: true })
            );

            syncRecommendedContractorSelection();
            invalidateContractorConflict();

            recommendationsStatus.textContent = option.selected
                ? 'Contractor added. Save the appointment to submit assignments.'
                : 'Contractor removed from the selection.';
        });

        actions.appendChild(select);
        card.appendChild(actions);
        recommendationsResults.appendChild(card);
    }

    syncRecommendedContractorSelection();
}

recommendationsButton.addEventListener('click', async () => {
    const inputs = recommendationInputs();

    if(!inputs){
        invalidateRecommendations();
        return;
    }

    const sequence = ++recommendationSequence;
    const signature = recommendationKey(inputs);

    recommendationSignature = '';
    recommendationsResults.replaceChildren();
    recommendationsButton.disabled = true;
    recommendationsStatus.textContent = 'Loading recommendations...';

    const query = new URLSearchParams({
        location_id: inputs.location_id,
        starts_at: inputs.starts_at,
        ends_at: inputs.ends_at
    });

    if(inputs.service_id){
        query.set('service_id', inputs.service_id);
    }

    if(inputs.exclude_appointment_id){
        query.set(
            'exclude_appointment_id',
            inputs.exclude_appointment_id
        );
    }

    try{
        const data = await api(
            '/api/contractor_recommendations.php?' + query.toString()
        );

        if(sequence !== recommendationSequence ||
           recommendationKey(recommendationInputs()) !== signature){
            return;
        }

        recommendationSignature = signature;
        renderRecommendations(data.contractors || []);

    }catch(error){
        if(sequence === recommendationSequence){
            recommendationSignature = '';
            recommendationsResults.replaceChildren();
            recommendationsStatus.textContent =
                'Unable to load recommendations: ' + error.message;
        }
    }finally{
        if(sequence === recommendationSequence){
            recommendationsButton.disabled = !recommendationInputs();
        }
    }
});

for(const element of [
    clientLocationId,
    startsAt,
    endsAt,
    serviceId,
    appointmentId
]){
    element.addEventListener('change', invalidateRecommendations);
    element.addEventListener('input', invalidateRecommendations);
}

async function loadClientLocations(selectedId='', usePrimary=true){
    const sequence = ++locationLoadSequence;
    const selectedClient = clientId.value;

    clientLocationId.innerHTML =
        '<option value="">Manual / Other Location</option>';
    invalidateRecommendations();

    if(!selectedClient) return;

    const data = await api(
        '/api/client_locations.php?client_id=' +
        encodeURIComponent(selectedClient)
    );

    if(sequence !== locationLoadSequence ||
       clientId.value !== selectedClient) return;

    clientLocationId.innerHTML =
        '<option value="">Manual / Other Location</option>' +
        data.locations.map(l =>
            `<option value="${Number(l.id)}">` +
            `${esc(l.label)} — ${esc(l.address1)}, ` +
            `${esc(l.city)}, ${esc(l.state)} ` +
            `${esc(l.postal_code)}</option>`
        ).join('');

    if(selectedId){
        clientLocationId.value = String(selectedId);
    } else if(usePrimary) {
        const primary = data.locations.find(l => Number(l.is_primary) === 1);
        if(primary) clientLocationId.value = String(primary.id);
    }

    invalidateRecommendations();
}

clientId.onchange = async () => {
    invalidateRecommendations();
    clientLocationId.innerHTML =
        '<option value="">Manual / Other Location</option>';
    appointmentLocation.value = '';
    try {
        await loadClientLocations();
    } catch(e) {
        appointmentError.textContent = e.message;
    }
};

const appointmentDescription = document.getElementById('appointmentDescription');
const appointmentError = document.getElementById('appointmentError');
const formTitle = document.getElementById('formTitle');
const saveButton = document.getElementById('saveButton');
const newButton = document.getElementById('newButton');
const deleteButton = document.getElementById('deleteButton');

function esc(s){
    return String(s ?? '').replace(/[&<>"']/g,x=>({
        '&':'&amp;',
        '<':'&lt;',
        '>':'&gt;',
        '"':'&quot;',
        "'":'&#039;'
    }[x]));
}

function localDateTime(value){
    if(!value) return '';

    const d = value instanceof Date
        ? new Date(value.getTime())
        : new Date(value);

    if (!Number.isFinite(d.getTime())) return '';

    const year = d.getFullYear();

    // Reject malformed historical years rather than allowing
    // them to populate a datetime-local appointment field.
    if (year < 2000 || year > 9999) return '';

    const pad = number => String(number).padStart(2, '0');

    return String(year).padStart(4, '0') + '-' +
        pad(d.getMonth() + 1) + '-' +
        pad(d.getDate()) + 'T' +
        pad(d.getHours()) + ':' +
        pad(d.getMinutes());
}

function calendarWallTime(value){
    if (!value) return '';

    const date = value instanceof Date
        ? value
        : new Date(value);

    if (!Number.isFinite(date.getTime())) return '';

    const pad = number => String(number).padStart(2, '0');

    return String(date.getUTCFullYear()).padStart(4, '0') +
        '-' + pad(date.getUTCMonth() + 1) +
        '-' + pad(date.getUTCDate()) +
        'T' + pad(date.getUTCHours()) +
        ':' + pad(date.getUTCMinutes());
}

function calendarEventDateTime(value){
    if (!value) return null;

    const match = String(value).match(
        /^(\d{4}-\d{2}-\d{2})[ T](\d{2}:\d{2})(?::(\d{2}))?$/
    );

    if (!match) return null;

    const normalized =
        match[1] + 'T' + match[2] + ':' + (match[3] || '00');

    const parsed = new Date(normalized + 'Z');

    if (!Number.isFinite(parsed.getTime()) ||
        parsed.toISOString().slice(0, 19) !== normalized ||
        Number(match[1].slice(0, 4)) < 2000) {
        return null;
    }

    return normalized;
}

function appointmentFieldDateTime(value){
    const normalized = calendarEventDateTime(value);
    return normalized ? normalized.slice(0, 16) : '';
}

function sqlDateTime(value){
    if(!value) return null;
    return value.replace('T',' ') + ':00';
}

async function loadLookups(){

    const [clientsData,contractorsData,servicesData]=await Promise.all([
        api('/api/clients.php'),
        api('/api/contractors.php'),
        api('/api/services.php')
    ]);

    clientId.innerHTML=
        '<option value="">No client</option>'+
        clientsData.clients.map(c=>
            `<option value="${c.id}">${esc(c.first_name)} ${esc(c.last_name)}</option>`
        ).join('');

    contractorId.innerHTML =
        contractorsData.contractors.map(c =>
            `<option value="${c.id}">${esc(c.first_name)} ${esc(c.last_name)}</option>`
        ).join('');
    renderContractorSelection();

    services=servicesData.services.filter(s=>Number(s.active));

    serviceId.innerHTML=
        '<option value="">No service</option>'+
        services.map(s=>
            `<option value="${s.id}">${esc(s.name)} — ${Number(s.duration_minutes)} min</option>`
        ).join('');
}

function resetForm(){
    invoiceButton.hidden = true;


    // Clear browser custom validity and wizard error decorations.
    appointmentForm.querySelectorAll(
        'input, select, textarea'
    ).forEach(field => {
        field.setCustomValidity('');
        wizardClearFieldError(field);
    });

    appointmentForm.reset();
    appointmentId.value = '';

    if (window.briteSetOriginalAppointmentStart) {
        window.briteSetOriginalAppointmentStart();
    }

    if (window.briteSyncDateTimePicker) {
        window.briteSyncDateTimePicker();
    }

    contractorSearch.value = '';
    renderContractorSelection();
    ++locationLoadSequence;
    clientLocationId.innerHTML =
        '<option value="">Manual / Other Location</option>';

    appointmentId.value='';
    appointmentStatus.value='scheduled';

    formTitle.textContent='New Appointment';
    saveButton.textContent='Create Appointment';
    deleteButton.hidden = true;
    appointmentError.textContent='';
    invalidateRecommendations();
    invalidateContractorConflict();
}

serviceId.onchange=()=>{

    const service=services.find(
        s=>String(s.id)===String(serviceId.value)
    );

    if(!service || !startsAt.value) return;

    const startValue = startsAt.value;
    const start = new Date(startValue + ':00Z');
    const minutes = Number(service.duration_minutes);

    if (!Number.isFinite(start.getTime()) ||
        !Number.isInteger(minutes) ||
        minutes < 1 || minutes > 1440 ||
        calendarWallTime(start) !== startValue) {
        appointmentError.textContent =
            'Invalid appointment start time or service duration.';
        return;
    }

    const end = new Date(start.getTime() + minutes * 60000);

    endsAt.value = calendarWallTime(end);
    endsAt.dispatchEvent(
        new Event('change', { bubbles: true })
    );
};

const appointmentStatusColors = Object.freeze({
    tentative: '#64748b',
    scheduled: '#2563eb',
    confirmed: '#15803d',
    in_progress: '#0891b2',
    completed: '#475569',
    cancelled: '#dc2626',
    no_show: '#b45309'
});

function appointmentStatusLabel(status) {
    return String(status || 'scheduled')
        .replaceAll('_', ' ')
        .replace(/\\b\\w/g, letter => letter.toUpperCase());
}

function appointmentContractorNames(appointment) {
    const ids = String(appointment.contractor_ids || '')
        .split(',')
        .map(id => id.trim())
        .filter(Boolean);

    const options = Array.from(
        document.getElementById('contractorId')?.options || []
    );

    return ids.map(id => {
        const option = options.find(item => item.value === id);
        return option
            ? option.textContent.trim()
            : 'Contractor #' + id;
    });
}

function calendarEventContent(info) {
    const event = info.event;
    const appointment = event.extendedProps || {};

    const root = document.createElement('div');
    root.className = 'brite-calendar-event';

    const heading = document.createElement('div');
    heading.className = 'brite-event-heading';

    if (info.timeText) {
        const time = document.createElement('span');
        time.className = 'brite-event-time';
        time.textContent = info.timeText;
        heading.appendChild(time);
    }

    const title = document.createElement('span');
    title.className = 'brite-event-title';
    title.textContent =
        appointment.title ||
        appointment.service_name ||
        'Appointment';
    heading.appendChild(title);
    root.appendChild(heading);

    const clientName = [
        appointment.client_first,
        appointment.client_last
    ].filter(Boolean).join(' ');

    if (clientName) {
        const client = document.createElement('div');
        client.className = 'brite-event-client';
        client.textContent = clientName;
        root.appendChild(client);
    }

    const contractors = appointmentContractorNames(appointment);

    if (contractors.length) {
        const contractorLine = document.createElement('div');
        contractorLine.className = 'brite-event-contractors';
        contractorLine.textContent = contractors.join(', ');
        contractorLine.title = contractors.join(', ');
        root.appendChild(contractorLine);
    }

    const status = document.createElement('span');
    status.className = 'brite-event-status';
    status.textContent = appointmentStatusLabel(
        appointment.status
    );
    root.appendChild(status);

    return { domNodes: [root] };
}

async function loadEvents(info,success,failure){

    try {

        const d=await api(
            '/api/appointments.php?start='+
            encodeURIComponent(info.startStr)+
            '&end='+
            encodeURIComponent(info.endStr)
        );

        success(d.appointments.map(a=>({

            id:String(a.id),

            title:
                (a.client_first
                    ? `${a.client_first} ${a.client_last} — `
                    : '') +
                (a.title || a.service_name || 'Appointment'),

            start:calendarEventDateTime(a.starts_at),
            end:calendarEventDateTime(a.ends_at),

            backgroundColor:
                appointmentStatusColors[a.status] ||
                appointmentStatusColors.scheduled,

            borderColor:
                appointmentStatusColors[a.status] ||
                appointmentStatusColors.scheduled,

            textColor: '#ffffff',

            extendedProps:a

        })));

    } catch(e){
        failure(e);
    }
}

async function persistCalendarMove(info){

    const event=info.event;
    const a=event.extendedProps;

    try{

        const contractorIds=String(a.contractor_ids || '')
            .split(',')
            .filter(Boolean)
            .map(Number);

        await api('/api/appointments.php',{
            method:'PUT',
            headers:{
                'Content-Type':'application/json'
            },
            body:JSON.stringify({
                id:Number(event.id),
                client_id:a.client_id || null,
                client_location_id:a.client_location_id || null,
                service_id:a.service_id || null,
                title:a.title || 'Appointment',
                description:a.description || null,
                starts_at:sqlDateTime(
                    calendarWallTime(event.start)
                ),
                ends_at:sqlDateTime(
                    calendarWallTime(event.end)
                ),
                status:a.status || 'scheduled',
                location:a.location || null,
                is_public:Number(a.is_public || 0) === 1,
                contractor_ids:contractorIds
            })
        });

        calendar.refetchEvents();

    }catch(e){

        info.revert();

        alert(e.message);
    }
}

async function editAppointment(event){

    const a=event.extendedProps;

    appointmentId.value=event.id;

    clientId.value=a.client_id || '';
    serviceId.value=a.service_id || '';

    try {
        await loadClientLocations(a.client_location_id || '', false);
    } catch(e) {
        appointmentError.textContent =
            'Unable to load appointment locations: ' + e.message;
        return false;
    }

    const contractors=String(a.contractor_ids || '')
        .split(',')
        .filter(Boolean);

    const assignedContractors = new Set(contractors);
    Array.from(contractorId.options).forEach(option => {
        option.selected = assignedContractors.has(option.value);
    });
    contractorSearch.value = '';
    renderContractorSelection();

    appointmentTitle.value=a.title || 'Appointment';
    startsAt.value=appointmentFieldDateTime(a.starts_at);
    endsAt.value=appointmentFieldDateTime(a.ends_at);

    if (window.briteSetOriginalAppointmentStart) {
        window.briteSetOriginalAppointmentStart();
    }

    if (window.briteSyncDateTimePicker) {
        window.briteSyncDateTimePicker();
    }

    appointmentStatus.value=a.status || 'scheduled';
    appointmentLocation.value=a.location || '';
    appointmentDescription.value=a.description || '';

    formTitle.textContent='Edit Appointment';
    saveButton.textContent='Save Changes';
    // Action visibility is managed by wizardShowStep().


    invalidateRecommendations();
    invalidateContractorConflict();
    return true;
}

let appointmentSavePending = false;

appointmentForm.onsubmit=async e=>{

    e.preventDefault();

    if (appointmentSavePending) return;

    appointmentSavePending = true;
    const saveButton = document.getElementById('saveButton');
    saveButton.disabled = true;

    try {
        await saveAppointmentFromWizard();
    } finally {
        appointmentSavePending = false;
        saveButton.disabled = false;
    }
};

async function saveAppointmentFromWizard() {
    appointmentError.textContent='';

    if (!appointmentDialog.open || wizardCurrentStep !== 6) {
        appointmentError.textContent =
            'Complete the appointment wizard before saving.';
        return;
    }

    for (let step = 1; step <= 5; step++) {
        wizardShowStep(step);

        if (!wizardValidateCurrentStep()) {
            appointmentError.textContent =
                'Please correct the information in step ' +
                step + '.';
            return;
        }
    }

    wizardShowStep(5);

    if (!(await checkContractorConflict())) {
        appointmentError.textContent =
            'Resolve the contractor scheduling conflict before saving.';
        return;
    }

    wizardShowStep(6);

    try {

        const d=Object.fromEntries(
            new FormData(appointmentForm)
        );

        // Explicitly capture relationship selections.
        d.client_id = clientId.value || null;
        d.client_location_id = clientLocationId.value || null;
        d.service_id = serviceId.value || null;

        d.contractor_ids = Array.from(contractorId.selectedOptions)
            .map(option => Number(option.value))
            .filter(id => Number.isInteger(id) && id > 0);

        d.starts_at=sqlDateTime(d.starts_at);
        d.ends_at=sqlDateTime(d.ends_at);

        const editing=Boolean(d.id);

        if(editing){
            d.id=Number(d.id);
        } else {
            delete d.id;
        }

        const savedAppointment = await api('/api/appointments.php',{
            method:editing ? 'PUT' : 'POST',
            headers:{'Content-Type':'application/json'},
            body:JSON.stringify(d)
        });

        window.briteCycle68c?.clear();
        resetForm();
        closeAppointmentWizard();
        calendar.refetchEvents();
        const savedId = Number(savedAppointment?.id || (editing ? d.id : 0));
        const confirmation = document.getElementById('cycle68bConfirmation');
        if (confirmation) {
            confirmation.replaceChildren();
            const message = document.createElement('strong');
            message.textContent = editing ? 'Appointment updated successfully.' : 'Appointment created successfully.';
            confirmation.append(message);
            if (savedId > 0) {
                const detail = document.createElement('span');
                detail.textContent = ' Appointment #' + savedId + '.';
                confirmation.append(detail);
                const link = document.createElement('a');
                const destination = new URL(window.location.href);
                destination.searchParams.set('appointment_id', String(savedId));
                destination.searchParams.delete('action');
                link.href = destination.toString();
                link.textContent = ' Open appointment details';
                link.style.marginLeft = '12px';
                confirmation.append(link);
            }
            confirmation.hidden = false;
            confirmation.focus();
        }

    } catch(e){
        appointmentError.textContent=e.message;
    }
}

newButton.onclick=()=>{
    window.briteCycle68c?.clear();
    resetForm();
    wizardShowStep(1);
};

deleteButton.onclick=async ()=>{

    if(!appointmentId.value) return;

    if(!confirm('Delete this appointment?')) return;

    try {

        await api('/api/appointments.php',{
            method:'DELETE',
            headers:{'Content-Type':'application/json'},
            body:JSON.stringify({
                id:Number(appointmentId.value)
            })
        });

        resetForm();
        closeAppointmentWizard();
        calendar.refetchEvents();

    } catch(e){
        appointmentError.textContent=e.message;
    }
};


invoiceButton.onclick = async () => {

    if (!appointmentId.value) {
        alert('Select an appointment first.');
        return;
    }

    if (!confirm(
        'Generate an invoice for this completed appointment?'
    )) {
        return;
    }

    if (typeof appointmentError !== 'undefined') {
        appointmentError.textContent = '';
    }

    try {

        const result = await api(
            '/api/invoices.php',
            {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    appointment_id:
                        Number(appointmentId.value)
                })
            }
        );

        alert(
            `Invoice ${result.invoice_number} created for $${Number(result.total).toFixed(2)}`
        );

        window.location.href =
            window.BRITE_BASE + '/billing.php';

    } catch (e) {

        if (typeof appointmentError !== 'undefined') {
            appointmentError.textContent = e.message;
        } else {
            alert(e.message);
        }
    }
};

const BRITE_CALENDAR_ROLE = <?=json_encode(
    (string)($tenant['role'] ?? ''),
    JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
)?>;
const BRITE_CALENDAR_READ_ONLY = <?=in_array(
    $tenant['role'],
    ['accounting', 'contractor', 'client'],
    true
) ? 'true' : 'false'?>;
const BRITE_CALENDAR_CLIENT = <?=($tenant['role'] ?? '') === 'client'
    ? 'true'
    : 'false'?>;

document.addEventListener('DOMContentLoaded',async ()=>{

    try {

        if (!BRITE_CALENDAR_READ_ONLY) {
            await loadLookups();
        }

        // Load saved preferences for the authenticated user.
        // Keep safe defaults if preferences cannot be retrieved.
        let calendarPreferences = {
            calendar_view: 'month',
            week_starts_on: 'sunday',
            time_format: '12h'
        };

        try {
            const response = await api('/api/user_preferences.php');
            const saved = response.preferences || {};

            if (['month', 'week', 'day'].includes(saved.calendar_view)) {
                calendarPreferences.calendar_view = saved.calendar_view;
            }

            if (['sunday', 'monday'].includes(saved.week_starts_on)) {
                calendarPreferences.week_starts_on = saved.week_starts_on;
            }

            if (['12h', '24h'].includes(saved.time_format)) {
                calendarPreferences.time_format = saved.time_format;
            }
        } catch (preferencesError) {
            console.warn(
                'Calendar preferences unavailable; using defaults.',
                preferencesError
            );
        }

        window.BRITE_CALENDAR_PREFERENCES = calendarPreferences;

        const preferredViews = {
            month: 'dayGridMonth',
            week: 'timeGridWeek',
            day: 'timeGridDay'
        };

        const use24HourTime = calendarPreferences.time_format === '24h';

        calendar=new FullCalendar.Calendar(
            document.getElementById('calendar'),
            {
                initialView: preferredViews[calendarPreferences.calendar_view],
                firstDay: calendarPreferences.week_starts_on === 'monday' ? 1 : 0,
                eventTimeFormat: {
                    hour: '2-digit',
                    minute: '2-digit',
                    hour12: !use24HourTime
                },
                slotLabelFormat: {
                    hour: '2-digit',
                    minute: '2-digit',
                    hour12: !use24HourTime
                },
                // UTC represents tenant-local wall-clock values here.
                timeZone:'UTC',

                headerToolbar:{
                    left:'prev,next today',
                    center:'title',
                    right:'dayGridMonth,timeGridWeek,timeGridDay'
                },

                selectable:!BRITE_CALENDAR_READ_ONLY,
                editable:!BRITE_CALENDAR_READ_ONLY,
                eventDurationEditable:!BRITE_CALENDAR_READ_ONLY,
                eventStartEditable:!BRITE_CALENDAR_READ_ONLY,

                events:loadEvents,

                // Client date clicks start self-service booking.
                // Other read-only roles retain a non-editable calendar.
                dateClick:info=>{
                    if (
                        BRITE_CALENDAR_CLIENT &&
                        typeof window.briteOpenClientBookingForDate === 'function'
                    ) {
                        window.briteOpenClientBookingForDate(info.dateStr);
                    }
                },

                eventContent:calendarEventContent,
                eventDisplay:'block',
                displayEventEnd:false,

                eventDrop:async info=>{
                    if (BRITE_CALENDAR_READ_ONLY) {
                        info.revert();
                        return;
                    }
                    await persistCalendarMove(info);
                },

                eventResize:async info=>{
                    if (BRITE_CALENDAR_READ_ONLY) {
                        info.revert();
                        return;
                    }
                    await persistCalendarMove(info);
                },

                select:info=>{
                    if (BRITE_CALENDAR_READ_ONLY) return;
                    resetForm();

                    startsAt.value=calendarWallTime(info.start);

                    let end=new Date(info.start);
                    end.setUTCHours(end.getUTCHours()+1);

                    endsAt.value=calendarWallTime(end);

                    if (window.briteSyncDateTimePicker) {
                        window.briteSyncDateTimePicker();
                    }

                    invalidateRecommendations();
                    openAppointmentWizard();
                },

                eventClick:info=>{
                    showAppointmentDetails(info.event);
                }
            }
        );

        calendar.render();

        // Position the month view at the earliest upcoming appointment
        // visible to this authenticated user. The endpoint derives
        // client/contractor identity from the session.
        try {
            const firstDateResult = await api(
                '/api/next_calendar_appointment.php'
            );

            const firstDate = firstDateResult.first_appointment_date;

            if (
                typeof firstDate === 'string' &&
                /^\\d{4}-\\d{2}-\\d{2}$/.test(firstDate)
            ) {
                calendar.gotoDate(firstDate);
            }
        } catch (dateError) {
            console.warn(
                'Unable to locate first upcoming appointment; keeping current month.',
                dateError
            );
        }

        if (BRITE_CALENDAR_READ_ONLY) {
            const newAppointmentButton = document.getElementById(
                'openAppointmentWizardButton'
            );
            if (newAppointmentButton) newAppointmentButton.hidden = true;

            const appointmentDialog = document.getElementById(
                'appointmentDialog'
            );
            if (appointmentDialog) appointmentDialog.hidden = true;

            document.getElementById('editAppointmentDetails').hidden = true;
            document.getElementById('duplicateAppointmentDetails').hidden = true;
            document.getElementById('appointmentDetailsEditStatus').hidden = true;
        }

        // Owner booking deep link: use the existing tenant-scoped API.
        const bookingParams = new URLSearchParams(
            window.location.search
        );
        const bookingIdValue = bookingParams.get('appointment_id');

        if (bookingIdValue !== null) {
            const bookingId = Number(bookingIdValue);

            if (
                !/^[1-9]\d*$/.test(bookingIdValue) ||
                !Number.isSafeInteger(bookingId)
            ) {
                console.warn('Invalid appointment deep-link ID');
            } else {
                try {
                    const result = await api(
                        '/api/appointments.php?appointment_id=' +
                        encodeURIComponent(String(bookingId))
                    );

                    const appointment = (result.appointments || [])
                        .find(a => Number(a.id) === bookingId);

                    if (!appointment) {
                        console.warn(
                            'Appointment not found or unavailable:',
                            bookingId
                        );
                    } else {
                        const appointmentDate =
                            calendarEventDateTime(
                                appointment.starts_at
                            );

                        // Listen before navigation so fast calendar
                        // responses cannot be missed.
                        const eventLoaded = new Promise(resolve => {
                            let finished = false;

                            const finish = event => {
                                if (finished) return;
                                finished = true;
                                clearTimeout(timeoutId);
                                calendar.off('eventsSet', onEventsSet);
                                resolve(event);
                            };

                            const onEventsSet = events => {
                                const matching = events.find(
                                    event => String(event.id) ===
                                        String(bookingId)
                                );

                                if (matching) {
                                    finish(matching);
                                }
                            };

                            const timeoutId = setTimeout(
                                () => finish(null),
                                5000
                            );

                            calendar.on('eventsSet', onEventsSet);

                            calendar.gotoDate(
                                appointmentDate.substring(0, 10)
                            );

                            // The target may already be in the event store.
                            const existingEvent =
                                calendar.getEventById(String(bookingId));

                            if (existingEvent) {
                                finish(existingEvent);
                            }
                        });

                        const targetEvent = await eventLoaded;

                        if (targetEvent) {
                            showAppointmentDetails(targetEvent);
                        } else {
                            console.warn(
                                'Appointment event did not load:',
                                bookingId
                            );
                        }
                    }
                } catch (error) {
                    console.error(
                        'Unable to open appointment deep link:',
                        error
                    );
                }
            }
        }

        // Dashboard deep link: launch the existing creation wizard.
        const launchParams = new URLSearchParams(
            window.location.search
        );

        if (launchParams.get('action') === 'new') {
            if (!BRITE_CALENDAR_READ_ONLY) {
                resetForm();
                openAppointmentWizard();
            }

            launchParams.delete('action');

            const remainingQuery = launchParams.toString();
            const cleanUrl =
                window.location.pathname +
                (remainingQuery ? '?' + remainingQuery : '') +
                window.location.hash;

            window.history.replaceState(
                window.history.state,
                '',
                cleanUrl
            );
        }

    } catch(e){
        appointmentError.textContent=e.message;
    }
});
</script>

<script src="<?=htmlspecialchars(app_url('/assets/js/brite-datetime-picker.js'))?>"></script>

<?php if ($canManageAppointments): ?>
<script src="<?=htmlspecialchars(app_url('/assets/js/brite-wizard-draft.js'),ENT_QUOTES,'UTF-8')?>" defer></script>
<script src="<?=htmlspecialchars(app_url('/assets/js/brite-wizard-pricing-preview.js'),ENT_QUOTES,'UTF-8')?>" defer></script>
<?php endif; ?>
<?php require __DIR__.'/partials/app_footer.php'; ?>
