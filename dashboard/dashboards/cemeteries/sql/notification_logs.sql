-- Notification Logs Table
-- Tracks all notification events for debugging and monitoring
-- Created: 2026-01-29

CREATE TABLE IF NOT EXISTS notification_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,

    -- Identifiers
    notification_id INT NULL,              -- Reference to scheduled_notifications
    user_id INT NULL,                      -- Target user (if relevant)
    subscription_id INT NULL,              -- Push subscription (if relevant)

    -- Event Type
    event_type ENUM(
        'created',           -- Notification created
        'scheduled',         -- Notification scheduled
        'send_attempt',      -- Send attempt
        'delivered',         -- Successfully delivered
        'failed',            -- Send failed
        'retry',             -- Retry attempt
        'viewed',            -- Viewed by user
        'clicked',           -- User clicked notification
        'read',              -- Marked as read
        'approval_sent',     -- Approval notification sent
        'approved',          -- User approved
        'rejected',          -- User rejected
        'expired',           -- Approval expired
        'subscription_created',  -- New subscription created
        'subscription_removed',  -- Subscription removed
        'test_started',      -- Automation test started
        'test_completed'     -- Automation test completed
    ) NOT NULL,

    -- Notification Details
    notification_title VARCHAR(255) NULL,
    notification_body TEXT NULL,
    notification_type VARCHAR(50) NULL,    -- info/warning/urgent

    -- Device Info
    device_type VARCHAR(50) NULL,          -- mobile/desktop/tablet
    os VARCHAR(50) NULL,                   -- iOS/Android/Windows/macOS/Linux
    browser VARCHAR(50) NULL,              -- Chrome/Safari/Firefox/Edge
    user_agent TEXT NULL,
    ip_address VARCHAR(45) NULL,

    -- Send Details
    push_endpoint VARCHAR(500) NULL,
    http_status INT NULL,                  -- Response code from push service
    error_code VARCHAR(50) NULL,
    error_message TEXT NULL,

    -- Metadata
    extra_data JSON NULL,                  -- Additional flexible data
    test_run_id VARCHAR(36) NULL,          -- UUID for automation tests

    -- Timestamps
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    -- Indexes
    INDEX idx_notification_id (notification_id),
    INDEX idx_user_id (user_id),
    INDEX idx_event_type (event_type),
    INDEX idx_created_at (created_at),
    INDEX idx_test_run_id (test_run_id),
    INDEX idx_notification_event (notification_id, event_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
