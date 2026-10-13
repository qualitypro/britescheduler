-- WBS 1.6F: Contractor geographic service areas.
-- Apply once after migration 009.
-- No existing contractor, client, or appointment rows are modified.

-- Required for composite tenant/contractor referential integrity.
ALTER TABLE contractors
    ADD UNIQUE KEY uq_contractors_tenant_id (tenant_id, id);

CREATE TABLE contractor_service_areas (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    tenant_id BIGINT UNSIGNED NOT NULL,
    contractor_id BIGINT UNSIGNED NOT NULL,

    area_type ENUM('zip','radius') NOT NULL,
    label VARCHAR(120) NOT NULL,

    country_code CHAR(2) NOT NULL DEFAULT 'US',
    state_code CHAR(2) DEFAULT NULL,
    postal_code VARCHAR(10) DEFAULT NULL,

    center_latitude DECIMAL(10,7) DEFAULT NULL,
    center_longitude DECIMAL(10,7) DEFAULT NULL,
    radius_miles DECIMAL(7,2) DEFAULT NULL,

    active TINYINT(1) NOT NULL DEFAULT 1,

    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (id),

    KEY idx_csa_contractor
        (tenant_id, contractor_id, active),

    KEY idx_csa_zip
        (tenant_id, country_code, state_code, postal_code, active),

    CONSTRAINT fk_csa_tenant_contractor
        FOREIGN KEY (tenant_id, contractor_id)
        REFERENCES contractors (tenant_id, id)
        ON DELETE CASCADE,

    CONSTRAINT chk_csa_active
        CHECK (active IN (0,1)),

    CONSTRAINT chk_csa_identity
        CHECK (
            CHAR_LENGTH(TRIM(label)) > 0
            AND country_code = 'US'
        ),

    CONSTRAINT chk_csa_area_type
        CHECK (
            (
                area_type = 'zip'
                AND state_code REGEXP '^[A-Z]{2}$'
                AND postal_code REGEXP '^[0-9]{5}$'
                AND postal_code <> '00000'
                AND center_latitude IS NULL
                AND center_longitude IS NULL
                AND radius_miles IS NULL
            )
            OR
            (
                area_type = 'radius'
                AND state_code IS NULL
                AND postal_code IS NULL
                AND center_latitude BETWEEN -90 AND 90
                AND center_longitude BETWEEN -180 AND 180
                AND radius_miles > 0
                AND radius_miles <= 500
            )
        )

) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;
