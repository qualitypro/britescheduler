-- Brite Scheduler
-- Migration 015: Enable High Contrast by Default
--
-- New preference records default to enabled.
-- Existing user preferences are preserved.
-- Users without preference records receive the
-- application-level High Contrast default.

ALTER TABLE user_preferences
    MODIFY COLUMN high_contrast TINYINT(1)
    NOT NULL DEFAULT 1;
