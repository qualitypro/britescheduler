-- Cycle 93B: invitation lifecycle, no plaintext tokens stored.
CREATE TABLE IF NOT EXISTS enrollment_invitations (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 tenant_id BIGINT UNSIGNED NOT NULL,
 application_id BIGINT UNSIGNED NOT NULL,
 token_hash CHAR(64) NOT NULL,
 expires_at DATETIME NOT NULL,
 accepted_at DATETIME NULL,
 revoked_at DATETIME NULL,
 created_by BIGINT UNSIGNED NOT NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY uq_enrollment_invite_token (token_hash),
 KEY idx_enrollment_invite_application (tenant_id,application_id,created_at),
 CONSTRAINT fk_enrollment_invite_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
 CONSTRAINT fk_enrollment_invite_application FOREIGN KEY (application_id) REFERENCES enrollment_applications(id) ON DELETE CASCADE,
 CONSTRAINT fk_enrollment_invite_creator FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
