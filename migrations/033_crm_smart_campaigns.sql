-- Sprint 84: configurable smart segment rules and idempotent campaign tasks.
CREATE TABLE IF NOT EXISTS crm_smart_settings (
 tenant_id BIGINT UNSIGNED NOT NULL,
 setting_key VARCHAR(40) NOT NULL,
 setting_value INT UNSIGNED NOT NULL,
 updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 PRIMARY KEY(tenant_id,setting_key),
 CONSTRAINT fk_crm_smart_tenant FOREIGN KEY(tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS crm_followup_campaigns (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 tenant_id BIGINT UNSIGNED NOT NULL,
 segment_key VARCHAR(30) NOT NULL,
 template_id BIGINT UNSIGNED NOT NULL,
 recipients INT UNSIGNED NOT NULL DEFAULT 0,
 created_by BIGINT UNSIGNED NOT NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY uq_crm_campaign_tenant_id(tenant_id,id),
 CONSTRAINT fk_crm_campaign_tenant FOREIGN KEY(tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
 CONSTRAINT fk_crm_campaign_template FOREIGN KEY(template_id) REFERENCES crm_followup_templates(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS crm_campaign_tasks (
 tenant_id BIGINT UNSIGNED NOT NULL,
 campaign_id BIGINT UNSIGNED NOT NULL,
 client_id BIGINT UNSIGNED NOT NULL,
 activity_id BIGINT UNSIGNED NOT NULL,
 PRIMARY KEY(tenant_id,campaign_id,client_id),
 CONSTRAINT fk_crm_ct_campaign FOREIGN KEY(tenant_id,campaign_id) REFERENCES crm_followup_campaigns(tenant_id,id) ON DELETE CASCADE,
 CONSTRAINT fk_crm_ct_client FOREIGN KEY(client_id) REFERENCES clients(id) ON DELETE CASCADE,
 CONSTRAINT fk_crm_ct_activity FOREIGN KEY(activity_id) REFERENCES crm_activities(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
