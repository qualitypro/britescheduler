-- WBS 1.6B: Geocoding cache and shared request throttle.
-- Existing client locations and appointments remain unchanged.

CREATE TABLE geocoding_cache (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    address_hash CHAR(64) NOT NULL,
    normalized_address TEXT NOT NULL,
    latitude DECIMAL(10,7) DEFAULT NULL,
    longitude DECIMAL(10,7) DEFAULT NULL,
    precision_type VARCHAR(60) DEFAULT NULL,
    display_name TEXT DEFAULT NULL,
    status ENUM('resolved','not_found','rejected')
        NOT NULL,
    provider VARCHAR(60) NOT NULL DEFAULT 'nominatim',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_geocoding_provider_address
        (provider,address_hash)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;

CREATE TABLE geocoding_rate_limit (
    provider VARCHAR(60) NOT NULL,
    next_allowed_at DATETIME(6) NOT NULL,
    PRIMARY KEY (provider)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;

INSERT INTO geocoding_rate_limit
    (provider,next_allowed_at)
VALUES
    ('nominatim','1970-01-01 00:00:00.000000');
