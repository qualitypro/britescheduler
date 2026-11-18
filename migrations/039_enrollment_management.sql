-- Cycle 93: tenant-scoped contractor/client enrollment intake and decisions.
CREATE TABLE IF NOT EXISTS enrollment_applications (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 tenant_id BIGINT UNSIGNED NOT NULL,
 applicant_type ENUM('contractor','client') NOT NULL,
 full_name VARCHAR(190) NOT NULL,
 email VARCHAR(190) NOT NULL,
 phone VARCHAR(40) NULL,
 notes TEXT NULL,
 status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
 submitted_by BIGINT UNSIGNED NOT NULL,
 reviewed_by BIGINT UNSIGNED NULL,
 reviewed_at DATETIME NULL,
 decision_note VARCHAR(1000) NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 KEY idx_enrollment_tenant_status (tenant_id,status,created_at),
 KEY idx_enrollment_tenant_type (tenant_id,applicant_type,created_at),
 CONSTRAINT fk_enrollment_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
 CONSTRAINT fk_enrollment_submitter FOREIGN KEY (submitted_by) REFERENCES users(id),
 CONSTRAINT fk_enrollment_reviewer FOREIGN KEY (reviewed_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
