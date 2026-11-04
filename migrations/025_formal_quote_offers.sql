-- Cycle 71F: formal quote offers; run after migrations 022-024.
-- One current offer per request, with an immutable revision history.
CREATE TABLE contractor_quote_offers (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
 tenant_id BIGINT UNSIGNED NOT NULL,
 request_id BIGINT UNSIGNED NOT NULL,
 revision INT UNSIGNED NOT NULL,
 amount DECIMAL(12,2) NOT NULL,
 pricing_type ENUM('flat','hourly') NOT NULL,
 scope_of_work TEXT NOT NULL,
 terms TEXT NULL,
 estimated_hours DECIMAL(8,2) NULL,
 valid_until DATE NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 PRIMARY KEY(id),
 UNIQUE KEY uq_cqo_revision(tenant_id,request_id,revision),
 KEY idx_cqo_request(tenant_id,request_id,id),
 CONSTRAINT fk_cqo_request FOREIGN KEY(request_id) REFERENCES contractor_quote_requests(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
