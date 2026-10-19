-- Brite Scheduler
-- Client booking idempotency
--
-- One request key per tenant and authenticated client user.
-- Repeated submissions can return the existing appointment.
--
-- This migration is additive and does not alter existing records.

CREATE TABLE client_booking_requests (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    tenant_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    request_key CHAR(36) CHARACTER SET ascii
        COLLATE ascii_bin NOT NULL,
    appointment_id BIGINT UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL
        DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (id),

    UNIQUE KEY uq_client_booking_request (
        tenant_id,
        user_id,
        request_key
    ),

    KEY idx_client_booking_appointment (
        appointment_id
    ),

    CONSTRAINT fk_cbr_tenant
        FOREIGN KEY (tenant_id)
        REFERENCES tenants(id)
        ON DELETE CASCADE,

    CONSTRAINT fk_cbr_user
        FOREIGN KEY (user_id)
        REFERENCES users(id)
        ON DELETE CASCADE,

    CONSTRAINT fk_cbr_appointment
        FOREIGN KEY (appointment_id)
        REFERENCES appointments(id)
        ON DELETE CASCADE
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;
