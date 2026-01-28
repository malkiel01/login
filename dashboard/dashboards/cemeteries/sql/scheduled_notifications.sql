-- scheduled_notifications table
-- Version: 1.0.0
-- Created: 2026-01-28

CREATE TABLE IF NOT EXISTS `scheduled_notifications` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `title` VARCHAR(255) NOT NULL COMMENT 'כותרת ההתראה',
    `body` TEXT NOT NULL COMMENT 'תוכן ההתראה',
    `notification_type` ENUM('info', 'warning', 'urgent') DEFAULT 'info' COMMENT 'סוג ההתראה',
    `target_users` JSON NOT NULL COMMENT 'מערך של user IDs או ["all"] לכולם',
    `scheduled_at` DATETIME NULL COMMENT 'מתי לשלוח - NULL לשליחה מיידית',
    `url` VARCHAR(255) DEFAULT NULL COMMENT 'לינק אופציונלי',
    `status` ENUM('pending', 'sent', 'cancelled', 'failed') DEFAULT 'pending' COMMENT 'סטטוס ההתראה',
    `created_by` INT NOT NULL COMMENT 'מי יצר את ההתראה',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `sent_at` DATETIME DEFAULT NULL COMMENT 'מתי נשלחה בפועל',
    `error_message` TEXT DEFAULT NULL COMMENT 'הודעת שגיאה אם נכשל',
    INDEX `idx_status_scheduled` (`status`, `scheduled_at`),
    INDEX `idx_created_by` (`created_by`),
    INDEX `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
