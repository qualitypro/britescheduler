-- WBS 1.6G: Tenant-safe contractor service qualifications.
--
-- Apply once after migration 010.
-- Requires MariaDB 10.4+.
--
-- This migration adds schema only.
-- Existing contractors, services, appointments, and demo records
-- are not modified.
--
-- A verified qualification means a tenant-authorized user has
-- reviewed the contractor for the service. It does not establish
-- professional licensing or regulatory compliance.

-- Required for tenant-scoped service foreign keys.
ALTER TABLE services
    ADD UNIQUE KEY uq_services_tenant_id (tenant_id, id);

CREATE TABLE contractor_service_qualifications (

    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,

    tenant_id BIGINT UNSIGNED NOT NULL,
    contractor_id BIGINT UNSIGNED NOT NULL,
    service_id BIGINT UNSIGNED NOT NULL,

    verification_status
        ENUM('unverified','verified','rejected')
        NOT NULL DEFAULT 'unverified',

    verified_by BIGINT UNSIGNED DEFAULT NULL,
    verified_at DATETIME DEFAULT NULL,

    created_at TIMESTAMP NOT NULL
        DEFAULT CURRENT_TIMESTAMP,

    updated_at TIMESTAMP NOT NULL
        DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (id),

    UNIQUE KEY uq_csq_tenant_contractor_service
        (tenant_id, contractor_id, service_id),

    KEY idx_csq_tenant_service_status
        (tenant_id, service_id, verification_status),

    KEY idx_csq_verifier
        (tenant_id, verified_by),

    CONSTRAINT fk_csq_tenant_contractor
        FOREIGN KEY (tenant_id, contractor_id)
        REFERENCES contractors (tenant_id, id)
        ON DELETE CASCADE,

    CONSTRAINT fk_csq_tenant_service
        FOREIGN KEY (tenant_id, service_id)
        REFERENCES services (tenant_id, id)
        ON DELETE CASCADE,

    CONSTRAINT fk_csq_tenant_verifier
        FOREIGN KEY (tenant_id, verified_by)
        REFERENCES tenant_memberships (tenant_id, user_id)
        ON DELETE RESTRICT,

    CONSTRAINT chk_csq_verification_consistency
        CHECK (
            (
                verification_status = 'unverified'
                AND verified_by IS NULL
                AND verified_at IS NULL
            )
            OR
            (
                verification_status IN ('verified','rejected')
                AND verified_by IS NOT NULL
                AND verified_at IS NOT NULL
            )
        )

) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;
