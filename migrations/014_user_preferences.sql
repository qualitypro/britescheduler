-- Brite Scheduler
-- Migration 014: Persistent User Preferences
--
-- Preferences belong to individual users, not tenants.
-- Existing users receive defaults until they customize settings.

CREATE TABLE user_preferences (
    user_id BIGINT UNSIGNED NOT NULL,

    color_palette ENUM(
        'ocean',
        'forest',
        'midnight',
        'slate'
    ) NOT NULL DEFAULT 'ocean',

    calendar_view ENUM(
        'month',
        'week',
        'day'
    ) NOT NULL DEFAULT 'month',

    week_starts_on ENUM(
        'sunday',
        'monday'
    ) NOT NULL DEFAULT 'sunday',

    time_format ENUM(
        '12h',
        '24h'
    ) NOT NULL DEFAULT '12h',

    reduced_motion TINYINT(1)
        NOT NULL DEFAULT 0,

    high_contrast TINYINT(1)
        NOT NULL DEFAULT 0,

    in_app_notifications TINYINT(1)
        NOT NULL DEFAULT 1,

    created_at TIMESTAMP
        NOT NULL DEFAULT CURRENT_TIMESTAMP,

    updated_at TIMESTAMP
        NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (user_id),

    CONSTRAINT fk_user_preferences_user
        FOREIGN KEY (user_id)
        REFERENCES users(id)
        ON DELETE CASCADE
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;
