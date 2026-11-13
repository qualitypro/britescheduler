-- Cycle 92: audit trail for sensitive enrollment document handling.
CREATE TABLE IF NOT EXISTS registration_document_audit (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 document_id BIGINT UNSIGNED NOT NULL,
 application_id BIGINT UNSIGNED NOT NULL,
 tenant_id BIGINT UNSIGNED NULL,
 actor_user_id BIGINT UNSIGNED NULL,
 action ENUM('uploaded','viewed','verified','rejected','deleted') NOT NULL,
 reason VARCHAR(500) NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 CONSTRAINT fk_reg_doc_audit_document FOREIGN KEY (document_id) REFERENCES registration_documents(id) ON DELETE RESTRICT,
 CONSTRAINT fk_reg_doc_audit_application FOREIGN KEY (application_id) REFERENCES registration_applications(id) ON DELETE RESTRICT,
 CONSTRAINT fk_reg_doc_audit_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE SET NULL,
 CONSTRAINT fk_reg_doc_audit_actor FOREIGN KEY (actor_user_id) REFERENCES users(id) ON DELETE SET NULL,
 INDEX idx_reg_doc_audit_history (document_id,created_at),
 INDEX idx_reg_doc_audit_app (application_id,created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
