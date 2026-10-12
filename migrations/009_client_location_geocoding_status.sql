-- WBS 1.6D: Persist client-location geocoding status.
-- Apply once after migration 008.
-- Existing client locations and appointment references are preserved.

ALTER TABLE client_locations
    ADD COLUMN geocoding_status
        ENUM(
            'pending',
            'resolved',
            'not_found',
            'rejected',
            'unavailable'
        ) NOT NULL DEFAULT 'pending'
        AFTER longitude,
    ADD COLUMN geocoding_attempted_at
        DATETIME(6) NULL
        AFTER geocoding_status,
    ADD COLUMN geocoding_resolved_at
        DATETIME(6) NULL
        AFTER geocoding_attempted_at;

-- Backfill existing coordinates without requesting external geocoding.
UPDATE client_locations
SET
    geocoding_status = 'resolved',
    geocoding_resolved_at = UTC_TIMESTAMP(6)
WHERE latitude IS NOT NULL
  AND longitude IS NOT NULL;

-- Rows without coordinates retain the default 'pending' status.
