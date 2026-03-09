-- Add dismissed_at column to push_notifications
-- Used by NotificationCenter to track when user dismissed (pressed back) on a notification
-- Dismissed notifications won't be shown again for 5 hours

ALTER TABLE push_notifications
ADD COLUMN IF NOT EXISTS `dismissed_at` DATETIME DEFAULT NULL
COMMENT 'מתי המשתמש דחה/התעלם מההתראה - לא תוצג שוב למשך 5 שעות'
AFTER `is_read`;

CREATE INDEX IF NOT EXISTS idx_dismissed_at ON push_notifications (dismissed_at);
