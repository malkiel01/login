-- טבלת קודי OTP לאימות SMS
-- יש להריץ את הקובץ הזה פעם אחת ליצירת הטבלה

CREATE TABLE IF NOT EXISTS otp_codes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT DEFAULT NULL,
    phone VARCHAR(20) NOT NULL,
    code VARCHAR(6) NOT NULL,
    purpose ENUM('login', 'registration', 'verification', 'password_reset', 'action_confirm') NOT NULL DEFAULT 'verification',
    action_data TEXT DEFAULT NULL,
    attempts INT DEFAULT 0,
    max_attempts INT DEFAULT 3,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at DATETIME NOT NULL,
    verified_at DATETIME DEFAULT NULL,
    is_used TINYINT(1) DEFAULT 0,

    -- Foreign key (nullable for registration)
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,

    -- Indexes
    INDEX idx_phone_code (phone, code),
    INDEX idx_expires (expires_at),
    INDEX idx_user_purpose (user_id, purpose)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- טבלת rate limiting לשליחת SMS
CREATE TABLE IF NOT EXISTS sms_rate_limits (
    id INT AUTO_INCREMENT PRIMARY KEY,
    phone VARCHAR(20) NOT NULL,
    ip_address VARCHAR(45) NOT NULL,
    sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_phone (phone),
    INDEX idx_ip (ip_address),
    INDEX idx_sent (sent_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
