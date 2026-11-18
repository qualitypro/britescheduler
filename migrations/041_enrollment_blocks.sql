-- Cycle 96B: tenant-scoped enrollment email blocking; no global bans.
CREATE TABLE IF NOT EXISTS enrollment_blocks (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 tenant_id BIGINT UNSIGNED NOT NULL,
 email VARCHAR(190) NOT NULL,
 reason VARCHAR(1000) NOT NULL,
 blocked_by BIGINT UNSIGNED NOT NULL,
 blocked_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 unblocked_by BIGINT UNSIGNED NULL,
 unblocked_at DATETIME NULL,
 UNIQUE KEY uq_enrollment_block_tenant_email (tenant_id,email),
 KEY idx_enrollment_blocks_active (tenant_id,unblocked_at),
 CONSTRAINT fk_enrollment_block_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
 CONSTRAINT fk_enrollment_block_creator FOREIGN KEY (blocked_by) REFERENCES users(id),
 CONSTRAINT fk_enrollment_block_remover FOREIGN KEY (unblocked_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
