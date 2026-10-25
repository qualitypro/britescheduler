-- Cycle 03.3: Appointment bidding marketplace.
-- Additive schema. No existing appointment assignments are changed.

CREATE TABLE appointment_bid_listings (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    tenant_id BIGINT UNSIGNED NOT NULL,
    appointment_id BIGINT UNSIGNED NOT NULL,

    status ENUM(
        'open',
        'paused',
        'awarded',
        'closed'
    ) NOT NULL DEFAULT 'open',

    opened_by BIGINT UNSIGNED NOT NULL,
    awarded_bid_id BIGINT UNSIGNED DEFAULT NULL,

    opened_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    closed_at TIMESTAMP NULL DEFAULT NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (id),

    UNIQUE KEY uq_abl_tenant_appointment
        (tenant_id, appointment_id),

    UNIQUE KEY uq_abl_tenant_id
        (tenant_id, id),

    KEY idx_abl_tenant_status
        (tenant_id, status),

    CONSTRAINT fk_abl_appointment
        FOREIGN KEY (tenant_id, appointment_id)
        REFERENCES appointments (tenant_id, id)
        ON DELETE CASCADE,

    CONSTRAINT fk_abl_opened_by
        FOREIGN KEY (opened_by)
        REFERENCES users (id)

) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;


CREATE TABLE appointment_bids (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    tenant_id BIGINT UNSIGNED NOT NULL,
    listing_id BIGINT UNSIGNED NOT NULL,
    contractor_id BIGINT UNSIGNED NOT NULL,

    pricing_type ENUM('hourly','flat') NOT NULL,
    amount DECIMAL(12,2) NOT NULL,

    message TEXT DEFAULT NULL,

    status ENUM(
        'submitted',
        'withdrawn',
        'shortlisted',
        'selected',
        'accepted',
        'rejected'
    ) NOT NULL DEFAULT 'submitted',

    submitted_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (id),

    UNIQUE KEY uq_ab_tenant_listing_contractor
        (tenant_id, listing_id, contractor_id),

    UNIQUE KEY uq_ab_tenant_listing_id
        (tenant_id, listing_id, id),

    KEY idx_ab_tenant_contractor
        (tenant_id, contractor_id, status),

    CONSTRAINT fk_ab_listing
        FOREIGN KEY (tenant_id, listing_id)
        REFERENCES appointment_bid_listings (tenant_id, id)
        ON DELETE CASCADE,

    CONSTRAINT fk_ab_contractor
        FOREIGN KEY (tenant_id, contractor_id)
        REFERENCES contractors (tenant_id, id)
        ON DELETE CASCADE,

    CONSTRAINT chk_ab_amount
        CHECK (
            amount >= 0
            AND amount <= 9999999999.99
        )

) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;


CREATE TABLE appointment_bid_events (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    tenant_id BIGINT UNSIGNED NOT NULL,
    listing_id BIGINT UNSIGNED NOT NULL,
    bid_id BIGINT UNSIGNED NOT NULL,

    actor_user_id BIGINT UNSIGNED NOT NULL,

    event_type ENUM(
        'submitted',
        'revised',
        'withdrawn',
        'shortlisted',
        'selected',
        'accepted',
        'rejected',
        'reopened'
    ) NOT NULL,

    details_json LONGTEXT DEFAULT NULL,

    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (id),

    KEY idx_abe_listing_created
        (tenant_id, listing_id, created_at),

    KEY idx_abe_bid_created
        (tenant_id, bid_id, created_at),

    CONSTRAINT fk_abe_bid
        FOREIGN KEY (tenant_id, listing_id, bid_id)
        REFERENCES appointment_bids (tenant_id, listing_id, id)
        ON DELETE CASCADE,

    CONSTRAINT fk_abe_actor
        FOREIGN KEY (actor_user_id)
        REFERENCES users (id),

    CONSTRAINT chk_abe_details_json
        CHECK (
            details_json IS NULL
            OR JSON_VALID(details_json)
        )

) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;
