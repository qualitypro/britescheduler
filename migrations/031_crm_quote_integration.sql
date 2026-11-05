-- Sprint 80: idempotent quote-to-CRM linkage.
CREATE TABLE IF NOT EXISTS crm_quote_links (
 tenant_id BIGINT UNSIGNED NOT NULL,
 request_id BIGINT UNSIGNED NOT NULL,
 opportunity_id BIGINT UNSIGNED NOT NULL,
 last_status VARCHAR(30) NOT NULL,
 last_offer_id BIGINT UNSIGNED NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 PRIMARY KEY (tenant_id,request_id),
 UNIQUE KEY uq_crm_quote_opportunity(tenant_id,opportunity_id),
 CONSTRAINT fk_crm_ql_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
 CONSTRAINT fk_crm_ql_request FOREIGN KEY (request_id) REFERENCES contractor_quote_requests(id) ON DELETE CASCADE,
 CONSTRAINT fk_crm_ql_opportunity FOREIGN KEY (opportunity_id) REFERENCES crm_opportunities(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
