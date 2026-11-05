-- Cycle 75: tenant-isolated messaging module. Apply after migration 027.
CREATE TABLE IF NOT EXISTS module_message_threads (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
 tenant_id BIGINT UNSIGNED NOT NULL,
 subject VARCHAR(180) NOT NULL,
 created_by BIGINT UNSIGNED NOT NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 PRIMARY KEY(id),
 KEY idx_mmt_tenant_updated(tenant_id,updated_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS module_message_participants (
 tenant_id BIGINT UNSIGNED NOT NULL,
 thread_id BIGINT UNSIGNED NOT NULL,
 user_id BIGINT UNSIGNED NOT NULL,
 last_read_message_id BIGINT UNSIGNED NULL,
 PRIMARY KEY(thread_id,user_id),
 KEY idx_mmp_user(tenant_id,user_id),
 CONSTRAINT fk_mmp_thread FOREIGN KEY(thread_id) REFERENCES module_message_threads(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS module_messages (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
 tenant_id BIGINT UNSIGNED NOT NULL,
 thread_id BIGINT UNSIGNED NOT NULL,
 sender_id BIGINT UNSIGNED NOT NULL,
 body TEXT NOT NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 PRIMARY KEY(id),
 KEY idx_mm_thread(id,thread_id),
 KEY idx_mm_tenant_thread(tenant_id,thread_id,id),
 CONSTRAINT fk_mm_thread FOREIGN KEY(thread_id) REFERENCES module_message_threads(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS module_message_preferences (
 tenant_id BIGINT UNSIGNED NOT NULL,
 user_id BIGINT UNSIGNED NOT NULL,
 in_app_enabled TINYINT(1) NOT NULL DEFAULT 1,
 PRIMARY KEY(tenant_id,user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
