-- Cycle 104: tenant-scoped workflow progress event ledger
CREATE TABLE IF NOT EXISTS activity_workflow_steps (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 tenant_id BIGINT UNSIGNED NOT NULL,
 workflow_type VARCHAR(64) NOT NULL,
 record_id BIGINT UNSIGNED NOT NULL,
 step_key VARCHAR(64) NOT NULL,
 step_label VARCHAR(160) NOT NULL,
 step_order SMALLINT UNSIGNED NOT NULL,
 status ENUM('completed','failed','blocked') NOT NULL DEFAULT 'completed',
 notification_id BIGINT UNSIGNED NULL,
 details VARCHAR(500) NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY uq_workflow_step_notification (tenant_id, notification_id),
 KEY idx_activity_workflow (tenant_id,workflow_type,record_id,created_at)
);
