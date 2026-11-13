-- Cycle 93D: shared database-backed throttling across PHP workers.
-- Store keyed HMACs only; never persist raw email addresses or IPs in throttle records.
CREATE TABLE IF NOT EXISTS enrollment_rate_limits (
  bucket_key CHAR(64) NOT NULL PRIMARY KEY,
  window_started_at DATETIME NOT NULL,
  attempts INT UNSIGNED NOT NULL DEFAULT 0,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_enrollment_limit_expiry (window_started_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
