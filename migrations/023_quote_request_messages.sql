-- Cycle 71E.2: tenant-scoped quote conversation and optional message photos.
CREATE TABLE contractor_quote_messages (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
 tenant_id BIGINT UNSIGNED NOT NULL,
 request_id BIGINT UNSIGNED NOT NULL,
 sender_role ENUM('client','contractor') NOT NULL,
 sender_user_id BIGINT UNSIGNED NOT NULL,
 body TEXT NOT NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 PRIMARY KEY(id),
 KEY idx_cqm_thread(tenant_id,request_id,id),
 CONSTRAINT fk_cqm_request FOREIGN KEY(request_id) REFERENCES contractor_quote_requests(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE contractor_quote_message_photos (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
 tenant_id BIGINT UNSIGNED NOT NULL,
 message_id BIGINT UNSIGNED NOT NULL,
 storage_key CHAR(64) NOT NULL,
 mime_type VARCHAR(20) NOT NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 PRIMARY KEY(id),
 UNIQUE KEY uq_cqmp_storage(storage_key),
 KEY idx_cqmp_message(tenant_id,message_id),
 CONSTRAINT fk_cqmp_message FOREIGN KEY(message_id) REFERENCES contractor_quote_messages(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
