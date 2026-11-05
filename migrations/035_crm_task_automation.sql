-- Sprint 86: optional recurrence policy for follow-up tasks.
CREATE TABLE IF NOT EXISTS crm_task_recurring (
 tenant_id BIGINT UNSIGNED NOT NULL,
 activity_id BIGINT UNSIGNED NOT NULL,
 repeat_days SMALLINT UNSIGNED NOT NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 PRIMARY KEY(tenant_id,activity_id),
 CONSTRAINT fk_crm_recurring_tenant FOREIGN KEY(tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
 CONSTRAINT fk_crm_recurring_activity FOREIGN KEY(activity_id) REFERENCES crm_activities(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
