-- Compatibility migration.
-- The legacy `events` database/table is intentionally NOT auto-imported because its
-- user_id values cannot safely be mapped to tenant membership without operator review.
-- Export legacy events first, map them to a tenant/client/contractor, then import them
-- into `appointments`. This migration exists as an explicit audit marker.
SELECT 1;
