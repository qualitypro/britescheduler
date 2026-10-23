-- Cycle 02.2: Contractor-specific service pricing.
-- Additive migration; existing prices and qualifications remain unchanged.

CREATE TABLE contractor_service_pricing (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    tenant_id BIGINT UNSIGNED NOT NULL,
    contractor_id BIGINT UNSIGNED NOT NULL,
    service_id BIGINT UNSIGNED NOT NULL,

    pricing_type ENUM('hourly','flat') NOT NULL,
    amount DECIMAL(12,2) NOT NULL,
    active TINYINT(1) NOT NULL DEFAULT 1,

    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (id),

    UNIQUE KEY uq_csp_tenant_contractor_service
        (tenant_id, contractor_id, service_id),

    KEY idx_csp_tenant_service
        (tenant_id, service_id, active),

    CONSTRAINT fk_csp_tenant_contractor
        FOREIGN KEY (tenant_id, contractor_id)
        REFERENCES contractors (tenant_id, id)
        ON DELETE CASCADE,

    CONSTRAINT fk_csp_tenant_service
        FOREIGN KEY (tenant_id, service_id)
        REFERENCES services (tenant_id, id)
        ON DELETE CASCADE,

    CONSTRAINT chk_csp_amount
        CHECK (amount >= 0 AND amount <= 9999999999.99),

    CONSTRAINT chk_csp_active
        CHECK (active IN (0,1))

) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;
