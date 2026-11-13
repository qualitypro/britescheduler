-- Cycle 93: isolated registration and moderation foundation.
-- No automatic client/contractor activation; existing user and tenant records unchanged.
CREATE TABLE IF NOT EXISTS registration_applications (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 tenant_id BIGINT UNSIGNED NULL,
 applicant_type ENUM('client','contractor','business') NOT NULL,
 first_name VARCHAR(100) NOT NULL,
 last_name VARCHAR(100) NOT NULL,
 email VARCHAR(190) NOT NULL,
 phone VARCHAR(40) NULL,
 organization_name VARCHAR(190) NULL,
 password_hash VARCHAR(255) NULL,
 status ENUM('pending_email','pending_review','action_required','approved','rejected','withdrawn') NOT NULL DEFAULT 'pending_email',
 email_verified_at DATETIME NULL,
 submitted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 reviewed_at DATETIME NULL,
 reviewed_by BIGINT UNSIGNED NULL,
 provisioned_user_id BIGINT UNSIGNED NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 CONSTRAINT fk_reg_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE SET NULL,
 CONSTRAINT fk_reg_reviewer FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL,
 CONSTRAINT fk_reg_user FOREIGN KEY (provisioned_user_id) REFERENCES users(id) ON DELETE SET NULL,
 INDEX idx_reg_queue (tenant_id,status,submitted_at),
 INDEX idx_reg_email (email,applicant_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS registration_verifications (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 application_id BIGINT UNSIGNED NOT NULL,
 verification_type ENUM('email','phone','government_id','authorized_representative_id','business_details','business','license','insurance') NOT NULL,
 status ENUM('pending','verified','failed','expired') NOT NULL DEFAULT 'pending',
 provider_reference VARCHAR(190) NULL,
 verified_at DATETIME NULL,
 expires_at DATETIME NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 CONSTRAINT fk_reg_verification_app FOREIGN KEY (application_id) REFERENCES registration_applications(id) ON DELETE CASCADE,
 INDEX idx_reg_verification_app (application_id,verification_type,status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS registration_tokens (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 application_id BIGINT UNSIGNED NOT NULL,
 token_hash CHAR(64) NOT NULL,
 purpose ENUM('email_verify','invitation') NOT NULL,
 expires_at DATETIME NOT NULL,
 consumed_at DATETIME NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 CONSTRAINT fk_reg_token_app FOREIGN KEY (application_id) REFERENCES registration_applications(id) ON DELETE CASCADE,
 UNIQUE KEY uq_reg_token_hash (token_hash),
 INDEX idx_reg_token_expiry (application_id,purpose,expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS registration_reviews (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 application_id BIGINT UNSIGNED NOT NULL,
 reviewer_id BIGINT UNSIGNED NULL,
 action ENUM('submitted','email_verified','requested_info','approved','rejected','suspended','note') NOT NULL,
 reason VARCHAR(500) NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 CONSTRAINT fk_reg_review_app FOREIGN KEY (application_id) REFERENCES registration_applications(id) ON DELETE CASCADE,
 CONSTRAINT fk_reg_review_actor FOREIGN KEY (reviewer_id) REFERENCES users(id) ON DELETE SET NULL,
 INDEX idx_reg_review_history (application_id,created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS registration_requirements (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 tenant_id BIGINT UNSIGNED NULL,
 applicant_type ENUM('client','contractor','business') NOT NULL,
 requirement_key VARCHAR(60) NOT NULL,
 required TINYINT(1) NOT NULL DEFAULT 1,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 CONSTRAINT fk_reg_requirement_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
 INDEX idx_reg_requirement_lookup (tenant_id,applicant_type,requirement_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Private document records hold only opaque storage references, never public URLs or ID numbers.
CREATE TABLE IF NOT EXISTS registration_documents (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 application_id BIGINT UNSIGNED NOT NULL,
 document_type ENUM('government_id','license','insurance','certification') NOT NULL,
 storage_key VARCHAR(255) NOT NULL,
 status ENUM('pending','verified','rejected','deleted') NOT NULL DEFAULT 'pending',
 uploaded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 delete_after DATETIME NULL,
 deleted_at DATETIME NULL,
 CONSTRAINT fk_reg_doc_app FOREIGN KEY (application_id) REFERENCES registration_applications(id) ON DELETE CASCADE,
 INDEX idx_reg_doc_retention (status,delete_after)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
