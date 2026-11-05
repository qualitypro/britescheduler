-- Cycle 75C: auditable owner-only review of tenant message threads.
CREATE TABLE IF NOT EXISTS module_message_owner_audit (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 tenant_id BIGINT UNSIGNED NOT NULL,
 owner_user_id BIGINT UNSIGNED NOT NULL,
 thread_id BIGINT UNSIGNED NOT NULL,
 viewed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 KEY idx_mmoa_tenant_time (tenant_id,viewed_at),
 KEY idx_mmoa_thread (tenant_id,thread_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
