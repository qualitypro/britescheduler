-- Cycle 105H: per-user notification dismissal independent of read state
ALTER TABLE notifications
 ADD COLUMN dismissed_at DATETIME NULL DEFAULT NULL,
 ADD KEY idx_notifications_user_dismissed (tenant_id,user_id,dismissed_at);
