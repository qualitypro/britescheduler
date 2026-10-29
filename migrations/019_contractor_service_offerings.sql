-- Cycle 67: optional per-contractor service offerings and prices.
-- Apply once after migration 011; existing records are untouched.
CREATE TABLE contractor_service_offerings (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 tenant_id BIGINT UNSIGNED NOT NULL,
 contractor_id BIGINT UNSIGNED NOT NULL,
 service_id BIGINT UNSIGNED NOT NULL,
 offered TINYINT(1) NOT NULL DEFAULT 0,
 pricing_model ENUM('hourly','flat') NOT NULL DEFAULT 'flat',
 rate DECIMAL(12,2) NULL,
 notes VARCHAR(500) NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 UNIQUE KEY uq_cso_tenant_contractor_service (tenant_id,contractor_id,service_id),
 KEY idx_cso_tenant_service (tenant_id,service_id,offered),
 CONSTRAINT fk_cso_contractor FOREIGN KEY (tenant_id,contractor_id)
   REFERENCES contractors (tenant_id,id) ON DELETE CASCADE,
 CONSTRAINT fk_cso_service FOREIGN KEY (tenant_id,service_id)
   REFERENCES services (tenant_id,id) ON DELETE CASCADE,
 CONSTRAINT chk_cso_rate CHECK (rate IS NULL OR rate >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
