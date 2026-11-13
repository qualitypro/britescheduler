-- Cycle 92: preserve sensitive documents subject to an explicit retention hold.
ALTER TABLE registration_documents
  ADD COLUMN retention_hold TINYINT(1) NOT NULL DEFAULT 0,
  ADD COLUMN retention_hold_reason VARCHAR(500) NULL,
  ADD COLUMN retention_hold_at DATETIME NULL,
  ADD COLUMN retention_hold_by BIGINT UNSIGNED NULL,
  ADD CONSTRAINT fk_registration_documents_hold_actor
    FOREIGN KEY (retention_hold_by) REFERENCES users(id) ON DELETE SET NULL,
  ADD INDEX idx_registration_documents_retention_due
    (retention_hold, delete_after, deleted_at);

ALTER TABLE registration_document_audit
  MODIFY COLUMN action ENUM('uploaded','viewed','verified','rejected','deleted','hold_placed','hold_released') NOT NULL;
