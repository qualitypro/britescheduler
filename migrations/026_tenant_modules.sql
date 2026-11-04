-- Cycle 73A: per-tenant module activation; installed code is filesystem-managed.
CREATE TABLE IF NOT EXISTS tenant_modules (
 tenant_id BIGINT UNSIGNED NOT NULL,
 module_key VARCHAR(64) NOT NULL,
 enabled TINYINT(1) NOT NULL DEFAULT 0,
 updated_by BIGINT UNSIGNED NULL,
 updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 PRIMARY KEY (tenant_id,module_key),
 KEY idx_tenant_modules_enabled (tenant_id,enabled)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
