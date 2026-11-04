-- Cycle 74: tenant-specific module role permissions.
-- Apply after tenant_modules exists, before deploying the new PHP code.
CREATE TABLE IF NOT EXISTS tenant_module_roles (
 tenant_id BIGINT UNSIGNED NOT NULL,
 module_key VARCHAR(64) NOT NULL,
 role VARCHAR(32) NOT NULL,
 allowed TINYINT(1) NOT NULL DEFAULT 0,
 PRIMARY KEY (tenant_id,module_key,role),
 CONSTRAINT fk_tmr_tenant_module FOREIGN KEY (tenant_id,module_key)
   REFERENCES tenant_modules(tenant_id,module_key) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- Preserve access for existing activated modules. Manifest restrictions still apply.
INSERT IGNORE INTO tenant_module_roles (tenant_id,module_key,role,allowed)
SELECT tm.tenant_id,tm.module_key,roles.role,1
FROM tenant_modules tm
CROSS JOIN (
 SELECT 'owner' role UNION ALL SELECT 'admin' UNION ALL SELECT 'scheduler'
 UNION ALL SELECT 'accounting' UNION ALL SELECT 'contractor' UNION ALL SELECT 'client'
) roles
WHERE tm.enabled=1;
