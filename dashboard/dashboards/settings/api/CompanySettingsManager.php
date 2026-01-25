<?php
/**
 * Company Settings Manager
 * v1.0.0 - 2026-01-25
 *
 * Manages company-wide settings stored in the database.
 */

class CompanySettingsManager {
    private $conn;
    private $cache = [];
    private static $instance = null;
    private static $tableChecked = false;

    private function __construct($conn) {
        $this->conn = $conn;

        // Ensure table exists (one-time check per request)
        if (!self::$tableChecked) {
            $this->ensureTable();
            self::$tableChecked = true;
        }
    }

    /**
     * Get singleton instance
     */
    public static function getInstance($conn) {
        if (self::$instance === null) {
            self::$instance = new self($conn);
        }
        return self::$instance;
    }

    /**
     * Ensure the company_settings table exists
     */
    private function ensureTable() {
        try {
            $this->conn->exec("
                CREATE TABLE IF NOT EXISTS company_settings (
                    id INT PRIMARY KEY AUTO_INCREMENT,
                    setting_key VARCHAR(50) NOT NULL UNIQUE,
                    setting_value TEXT,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    INDEX idx_setting_key (setting_key)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");

            // Insert defaults if table is empty
            $stmt = $this->conn->query("SELECT COUNT(*) FROM company_settings");
            if ($stmt->fetchColumn() == 0) {
                $this->conn->exec("
                    INSERT INTO company_settings (setting_key, setting_value) VALUES
                    ('company_name', 'חברה קדישא'),
                    ('company_logo', ''),
                    ('phone_primary', ''),
                    ('phone_secondary', ''),
                    ('address', ''),
                    ('social_security_code', ''),
                    ('email', '')
                ");
            }
        } catch (Exception $e) {
            error_log("CompanySettingsManager: Table creation error - " . $e->getMessage());
        }
    }

    /**
     * Get a single setting
     */
    public function get($key, $default = '') {
        // Check cache first
        if (isset($this->cache[$key])) {
            return $this->cache[$key];
        }

        try {
            $stmt = $this->conn->prepare("
                SELECT setting_value
                FROM company_settings
                WHERE setting_key = :key
            ");
            $stmt->execute(['key' => $key]);
            $result = $stmt->fetchColumn();

            $value = $result !== false ? $result : $default;
            $this->cache[$key] = $value;
            return $value;

        } catch (Exception $e) {
            error_log("CompanySettingsManager::get error: " . $e->getMessage());
            return $default;
        }
    }

    /**
     * Get all settings
     */
    public function getAll() {
        try {
            $stmt = $this->conn->query("
                SELECT setting_key, setting_value
                FROM company_settings
            ");
            $results = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

            // Update cache
            $this->cache = array_merge($this->cache, $results);

            return $results;

        } catch (Exception $e) {
            error_log("CompanySettingsManager::getAll error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Set a single setting
     */
    public function set($key, $value) {
        try {
            $stmt = $this->conn->prepare("
                INSERT INTO company_settings (setting_key, setting_value)
                VALUES (:key, :value)
                ON DUPLICATE KEY UPDATE
                    setting_value = :value2,
                    updated_at = CURRENT_TIMESTAMP
            ");
            $stmt->execute([
                'key' => $key,
                'value' => $value,
                'value2' => $value
            ]);

            $this->cache[$key] = $value;
            return true;

        } catch (Exception $e) {
            error_log("CompanySettingsManager::set error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Set multiple settings at once
     */
    public function setMultiple($settings) {
        try {
            $this->conn->beginTransaction();

            foreach ($settings as $key => $value) {
                $this->set($key, $value);
            }

            $this->conn->commit();
            return true;

        } catch (Exception $e) {
            $this->conn->rollBack();
            error_log("CompanySettingsManager::setMultiple error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get company branding for header display
     * Returns array with name and logo path
     */
    public function getBranding() {
        $all = $this->getAll();
        return [
            'name' => $all['company_name'] ?? 'חברה קדישא',
            'logo' => $all['company_logo'] ?? '',
            'hasLogo' => !empty($all['company_logo'])
        ];
    }

    /**
     * Clear cache
     */
    public function clearCache() {
        $this->cache = [];
    }
}
