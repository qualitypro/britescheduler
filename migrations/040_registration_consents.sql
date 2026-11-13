-- Cycle 93D: immutable consent evidence for each enrollment application.
CREATE TABLE IF NOT EXISTS registration_consents (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  application_id BIGINT UNSIGNED NOT NULL,
  terms_version VARCHAR(80) NOT NULL,
  privacy_version VARCHAR(80) NOT NULL,
  accepted_at DATETIME NOT NULL,
  ip_hmac CHAR(64) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_reg_consent_app FOREIGN KEY (application_id) REFERENCES registration_applications(id) ON DELETE CASCADE,
  UNIQUE KEY uq_reg_consent_application (application_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
