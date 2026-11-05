-- Sprint 85: staff ownership and priority for CRM activities.
CREATE TABLE IF NOT EXISTS crm_task_details (
 tenant_id BIGINT UNSIGNED NOT NULL,
 activity_id BIGINT UNSIGNED NOT NULL,
 assignee_user_id BIGINT UNSIGNED NULL,
 priority ENUM('low','normal','high','urgent') NOT NULL DEFAULT 'normal',
 updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 PRIMARY KEY(tenant_id,activity_id),
 KEY idx_crm_task_assignee(tenant_id,assignee_user_id),
 CONSTRAINT fk_crm_task_tenant FOREIGN KEY(tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
 CONSTRAINT fk_crm_task_activity FOREIGN KEY(activity_id) REFERENCES crm_activities(id) ON DELETE CASCADE,
 CONSTRAINT fk_crm_task_assignee FOREIGN KEY(assignee_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
