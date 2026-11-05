-- Sprint 82: tenant-scoped customer segments and reusable follow-up templates.
CREATE TABLE IF NOT EXISTS crm_segments (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 tenant_id BIGINT UNSIGNED NOT NULL,
 name VARCHAR(100) NOT NULL,
 description VARCHAR(500) NULL,
 created_by BIGINT UNSIGNED NOT NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY uq_crm_segment_name(tenant_id,name),
 UNIQUE KEY uq_crm_segment_tenant_id(tenant_id,id),
 CONSTRAINT fk_crm_segment_tenant FOREIGN KEY(tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS crm_customer_segments (
 tenant_id BIGINT UNSIGNED NOT NULL,
 client_id BIGINT UNSIGNED NOT NULL,
 segment_id BIGINT UNSIGNED NOT NULL,
 assigned_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 PRIMARY KEY(tenant_id,client_id,segment_id),
 KEY idx_crm_cs_segment(tenant_id,segment_id),
 CONSTRAINT fk_crm_cs_tenant FOREIGN KEY(tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
 CONSTRAINT fk_crm_cs_client FOREIGN KEY(client_id) REFERENCES clients(id) ON DELETE CASCADE,
 CONSTRAINT fk_crm_cs_segment FOREIGN KEY(tenant_id,segment_id) REFERENCES crm_segments(tenant_id,id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS crm_followup_templates (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 tenant_id BIGINT UNSIGNED NOT NULL,
 name VARCHAR(100) NOT NULL,
 body TEXT NOT NULL,
 delay_days SMALLINT UNSIGNED NOT NULL DEFAULT 1,
 created_by BIGINT UNSIGNED NOT NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY uq_crm_template_name(tenant_id,name),
 CONSTRAINT fk_crm_template_tenant FOREIGN KEY(tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
