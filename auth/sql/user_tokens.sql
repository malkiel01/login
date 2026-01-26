-- טבלת tokens לאימות עמיד (PWA iOS)
-- יש להריץ את הקובץ הזה פעם אחת ליצירת הטבלה

CREATE TABLE IF NOT EXISTS user_tokens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    token VARCHAR(64) NOT NULL UNIQUE,
    refresh_token VARCHAR(64) NOT NULL UNIQUE,
    device_info TEXT DEFAULT NULL,
    ip_address VARCHAR(45) DEFAULT NULL,
    user_agent TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at DATETIME NOT NULL,
    refresh_expires_at DATETIME NOT NULL,
    last_used_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    is_active TINYINT(1) DEFAULT 1,

    -- Foreign key
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,

    -- Indexes
    INDEX idx_token (token),
    INDEX idx_refresh_token (refresh_token),
    INDEX idx_user_active (user_id, is_active),
    INDEX idx_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- הוספת עמודות לטבלת users (תתעלם מהשגיאה אם כבר קיימות)
-- ALTER TABLE users ADD COLUMN remember_token VARCHAR(64) DEFAULT NULL;
-- ALTER TABLE users ADD COLUMN remember_expiry DATETIME DEFAULT NULL;
