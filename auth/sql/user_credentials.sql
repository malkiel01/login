-- טבלת credentials ביומטריים (WebAuthn)
-- יש להריץ את הקובץ הזה פעם אחת ליצירת הטבלה

CREATE TABLE IF NOT EXISTS user_credentials (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    credential_id VARCHAR(512) NOT NULL,
    public_key TEXT NOT NULL,
    public_key_algorithm INT NOT NULL DEFAULT -7,
    sign_count INT UNSIGNED DEFAULT 0,
    device_name VARCHAR(255) DEFAULT NULL,
    authenticator_type ENUM('platform', 'cross-platform') DEFAULT 'platform',
    transports JSON DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_used_at TIMESTAMP DEFAULT NULL,
    is_active BOOLEAN DEFAULT TRUE,

    -- Foreign key
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,

    -- Indexes
    UNIQUE KEY idx_credential_id (credential_id(255)),
    INDEX idx_user_active (user_id, is_active),
    INDEX idx_last_used (last_used_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- טבלת challenges זמניים (לאימות WebAuthn)
CREATE TABLE IF NOT EXISTS webauthn_challenges (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT DEFAULT NULL,
    challenge VARCHAR(128) NOT NULL,
    type ENUM('registration', 'authentication', 'confirmation') NOT NULL,
    action_data JSON DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NOT NULL,
    used BOOLEAN DEFAULT FALSE,

    -- Foreign key (nullable for authentication before user is known)
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,

    -- Indexes
    INDEX idx_challenge (challenge),
    INDEX idx_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- הוספת עמודה לטבלת users אם לא קיימת
ALTER TABLE users
ADD COLUMN IF NOT EXISTS biometric_enabled BOOLEAN DEFAULT FALSE;
