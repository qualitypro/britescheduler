-- Brite Scheduler
-- Migration 013: Structured appointment references for notifications.
--
-- Existing notifications and read states are preserved.
-- Non-appointment notifications retain NULL appointment_id.
-- Composite foreign key prevents cross-tenant references.
--
-- Requires migration 012.

ALTER TABLE appointments
    ADD UNIQUE KEY uq_appointments_tenant_id (tenant_id, id);

ALTER TABLE notifications
    ADD COLUMN appointment_id BIGINT UNSIGNED NULL
        AFTER user_id,
    ADD INDEX idx_notif_tenant_appointment
        (tenant_id, appointment_id),
    ADD CONSTRAINT fk_notif_tenant_appointment
        FOREIGN KEY (tenant_id, appointment_id)
        REFERENCES appointments (tenant_id, id)
        ON DELETE RESTRICT;
