CREATE TABLE IF NOT EXISTS staff_invitations (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 tenant_id BIGINT UNSIGNED NOT NULL,
 email VARCHAR(190) NOT NULL,
 first_name VARCHAR(100) NOT NULL,
 last_name VARCHAR(100) NOT NULL,
 role ENUM('admin','scheduler','accounting') NOT NULL,
 token_hash CHAR(64) NOT NULL UNIQUE,
 status ENUM('pending','accepted','revoked') NOT NULL DEFAULT 'pending',
 expires_at DATETIME NOT NULL,
 accepted_at DATETIME NULL,
 created_by BIGINT UNSIGNED NOT NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 CONSTRAINT fk_staff_inv_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
 CONSTRAINT fk_staff_inv_creator FOREIGN KEY (created_by) REFERENCES users(id),
 INDEX idx_staff_inv_tenant (tenant_id,status,email)
) ENGINE=InnoDB;
