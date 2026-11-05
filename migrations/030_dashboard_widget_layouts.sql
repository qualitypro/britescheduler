-- Widget layouts: per-tenant, per-user, per-dashboard preferences.
-- Apply once after existing core migrations. No user data is modified.
CREATE TABLE IF NOT EXISTS dashboard_widget_layouts (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  tenant_id BIGINT UNSIGNED NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  dashboard_key VARCHAR(64) NOT NULL,
  layout_json TEXT NOT NULL,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_dashboard_widget_scope (tenant_id,user_id,dashboard_key),
  KEY idx_dashboard_widget_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
