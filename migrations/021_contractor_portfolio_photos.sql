-- Cycle 71B: contractor portfolio photos. Apply once after 020.
CREATE TABLE contractor_portfolio_photos (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
 tenant_id BIGINT UNSIGNED NOT NULL,
 contractor_id BIGINT UNSIGNED NOT NULL,
 storage_key CHAR(64) NOT NULL,
 mime_type VARCHAR(20) NOT NULL,
 sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 PRIMARY KEY(id),
 UNIQUE KEY uq_cpp_storage(storage_key),
 KEY idx_cpp_gallery(tenant_id,contractor_id,sort_order,id),
 CONSTRAINT fk_cpp_contractor FOREIGN KEY(tenant_id,contractor_id)
 REFERENCES contractors(tenant_id,id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
