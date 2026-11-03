-- Cycle 71A: contractor-managed public profile details.
-- Run once after 001_core.sql. No existing contractor rows are changed.
CREATE TABLE contractor_profiles (
  tenant_id BIGINT UNSIGNED NOT NULL,
  contractor_id BIGINT UNSIGNED NOT NULL,
  headline VARCHAR(140) NOT NULL DEFAULT '',
  biography TEXT NULL,
  specialties VARCHAR(500) NOT NULL DEFAULT '',
  years_experience SMALLINT UNSIGNED NULL,
  portfolio_url VARCHAR(500) NULL,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (tenant_id, contractor_id),
  CONSTRAINT fk_cp_contractor FOREIGN KEY (tenant_id, contractor_id)
    REFERENCES contractors (tenant_id, id) ON DELETE CASCADE,
  CONSTRAINT chk_cp_experience CHECK (years_experience IS NULL OR years_experience <= 80)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
