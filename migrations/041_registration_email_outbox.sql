-- Transactional enrollment email outbox. No plaintext verification tokens in DB.
CREATE TABLE IF NOT EXISTS registration_email_outbox (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 application_id BIGINT UNSIGNED NOT NULL,
 recipient_email VARCHAR(190) NOT NULL,
 subject VARCHAR(190) NOT NULL,
 body_ciphertext MEDIUMTEXT NOT NULL,
 status ENUM('pending','sending','sent','failed') NOT NULL DEFAULT 'pending',
 attempts INT UNSIGNED NOT NULL DEFAULT 0,
 available_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 claimed_at DATETIME NULL,
 sent_at DATETIME NULL,
 last_error VARCHAR(100) NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 CONSTRAINT fk_reg_outbox_app FOREIGN KEY (application_id) REFERENCES registration_applications(id) ON DELETE CASCADE,
 INDEX idx_reg_outbox_ready (status,available_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
