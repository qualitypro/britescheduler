-- Cycle 71D: client quote requests and private job photographs.
-- Apply once after migrations 020 and 021. No existing rows modified.
CREATE TABLE contractor_quote_requests (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
 tenant_id BIGINT UNSIGNED NOT NULL,
 client_id BIGINT UNSIGNED NOT NULL,
 contractor_id BIGINT UNSIGNED NOT NULL,
 client_location_id BIGINT UNSIGNED NOT NULL,
 service_id BIGINT UNSIGNED NULL,
 description TEXT NOT NULL,
 preferred_date DATE NULL,
 status ENUM('submitted','more_info_requested','quoted','accepted','declined','cancelled') NOT NULL DEFAULT 'submitted',
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 PRIMARY KEY(id),
 KEY idx_cqr_client(tenant_id,client_id,created_at),
 KEY idx_cqr_contractor(tenant_id,contractor_id,status,created_at),
 CONSTRAINT fk_cqr_client FOREIGN KEY(tenant_id,client_id) REFERENCES clients(tenant_id,id) ON DELETE CASCADE,
 CONSTRAINT fk_cqr_contractor FOREIGN KEY(tenant_id,contractor_id) REFERENCES contractors(tenant_id,id) ON DELETE CASCADE,
 CONSTRAINT fk_cqr_location FOREIGN KEY(client_location_id) REFERENCES client_locations(id) ON DELETE RESTRICT,
 CONSTRAINT fk_cqr_service FOREIGN KEY(service_id) REFERENCES services(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE contractor_quote_request_photos (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
 tenant_id BIGINT UNSIGNED NOT NULL,
 request_id BIGINT UNSIGNED NOT NULL,
 storage_key CHAR(64) NOT NULL,
 mime_type VARCHAR(20) NOT NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 PRIMARY KEY(id),
 UNIQUE KEY uq_cqrp_storage(storage_key),
 KEY idx_cqrp_request(tenant_id,request_id),
 CONSTRAINT fk_cqrp_request FOREIGN KEY(request_id) REFERENCES contractor_quote_requests(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
