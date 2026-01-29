-- Notification Test Runs Table
-- Tracks automation test executions
-- Created: 2026-01-29

CREATE TABLE IF NOT EXISTS notification_test_runs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    run_id VARCHAR(36) NOT NULL UNIQUE,    -- UUID

    -- Test Status
    status ENUM('running', 'completed', 'failed', 'cancelled') DEFAULT 'running',

    -- Results
    total_tests INT DEFAULT 0,
    passed_tests INT DEFAULT 0,
    failed_tests INT DEFAULT 0,

    -- Timestamps
    started_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    completed_at DATETIME NULL,

    -- Details
    test_config JSON NULL,                 -- Test configuration
    results JSON NULL,                     -- Detailed results per test
    error_log TEXT NULL,

    -- Who ran it
    created_by INT NOT NULL,

    -- Indexes
    INDEX idx_run_id (run_id),
    INDEX idx_status (status),
    INDEX idx_created_by (created_by),
    INDEX idx_started_at (started_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
