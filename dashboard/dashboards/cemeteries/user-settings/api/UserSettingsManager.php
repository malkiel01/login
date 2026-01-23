<?php
/*
 * File: user-settings/api/UserSettingsManager.php
 * Version: 1.0.0
 * Created: 2026-01-23
 * Author: Malkiel
 * Description: מחלקה לניהול הגדרות משתמש
 */

class UserSettingsManager {
    private $conn;
    private $userId;
    private $cache = [];

    public function __construct($conn, $userId) {
        $this->conn = $conn;
        $this->userId = $userId;
    }

    /**
     * קבלת הגדרה בודדת
     */
    public function get($key, $default = null) {
        // בדיקה בcache
        if (isset($this->cache[$key])) {
            return $this->cache[$key];
        }

        try {
            $stmt = $this->conn->prepare("
                SELECT settingValue, settingType
                FROM user_settings
                WHERE userId = :userId AND settingKey = :key
            ");
            $stmt->execute(['userId' => $this->userId, 'key' => $key]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($result) {
                $value = $this->castValue($result['settingValue'], $result['settingType']);
                $this->cache[$key] = $value;
                return $value;
            }

            // אם אין ערך, בדוק ברירת מחדל
            $defaultValue = $this->getDefault($key);
            return $defaultValue !== null ? $defaultValue : $default;

        } catch (Exception $e) {
            error_log("UserSettingsManager::get error: " . $e->getMessage());
            return $default;
        }
    }

    /**
     * קבלת כל ההגדרות של המשתמש
     */
    public function getAll($category = null) {
        try {
            $sql = "
                SELECT us.settingKey, us.settingValue, us.settingType, us.category,
                       usd.label, usd.description
                FROM user_settings us
                LEFT JOIN user_settings_defaults usd ON us.settingKey = usd.settingKey
                WHERE us.userId = :userId
            ";
            $params = ['userId' => $this->userId];

            if ($category) {
                $sql .= " AND us.category = :category";
                $params['category'] = $category;
            }

            $sql .= " ORDER BY us.category, usd.sortOrder";

            $stmt = $this->conn->prepare($sql);
            $stmt->execute($params);
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $settings = [];
            foreach ($results as $row) {
                $settings[$row['settingKey']] = [
                    'value' => $this->castValue($row['settingValue'], $row['settingType']),
                    'type' => $row['settingType'],
                    'category' => $row['category'],
                    'label' => $row['label'],
                    'description' => $row['description']
                ];
            }

            return $settings;

        } catch (Exception $e) {
            error_log("UserSettingsManager::getAll error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * קבלת כל ההגדרות עם ברירות מחדל
     */
    public function getAllWithDefaults($category = null) {
        try {
            // קבלת כל ברירות המחדל
            $sql = "
                SELECT settingKey, defaultValue, settingType, category,
                       label, description, options, sortOrder
                FROM user_settings_defaults
                WHERE isActive = 1
            ";
            $params = [];

            if ($category) {
                $sql .= " AND category = :category";
                $params['category'] = $category;
            }

            $sql .= " ORDER BY category, sortOrder";

            $stmt = $this->conn->prepare($sql);
            $stmt->execute($params);
            $defaults = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // קבלת ערכי המשתמש
            $userSettings = $this->getAll($category);

            // מיזוג
            $result = [];
            foreach ($defaults as $default) {
                $key = $default['settingKey'];
                $value = isset($userSettings[$key])
                    ? $userSettings[$key]['value']
                    : $this->castValue($default['defaultValue'], $default['settingType']);

                $result[$key] = [
                    'value' => $value,
                    'defaultValue' => $this->castValue($default['defaultValue'], $default['settingType']),
                    'type' => $default['settingType'],
                    'category' => $default['category'],
                    'label' => $default['label'],
                    'description' => $default['description'],
                    'options' => $default['options'] ? json_decode($default['options'], true) : null,
                    'isDefault' => !isset($userSettings[$key])
                ];
            }

            return $result;

        } catch (Exception $e) {
            error_log("UserSettingsManager::getAllWithDefaults error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * שמירת הגדרה
     */
    public function set($key, $value, $type = null, $category = null) {
        try {
            // קבלת סוג הערך מברירת מחדל אם לא סופק
            if (!$type) {
                $stmt = $this->conn->prepare("
                    SELECT settingType, category FROM user_settings_defaults WHERE settingKey = :key
                ");
                $stmt->execute(['key' => $key]);
                $default = $stmt->fetch(PDO::FETCH_ASSOC);
                $type = $default['settingType'] ?? 'string';
                $category = $category ?? $default['category'] ?? 'general';
            }

            // המרה לstring לשמירה
            $stringValue = $this->valueToString($value, $type);

            $stmt = $this->conn->prepare("
                INSERT INTO user_settings (userId, settingKey, settingValue, settingType, category)
                VALUES (:userId, :key, :value, :type, :category)
                ON DUPLICATE KEY UPDATE
                    settingValue = :value2,
                    settingType = :type2,
                    updateDate = CURRENT_TIMESTAMP
            ");

            $stmt->execute([
                'userId' => $this->userId,
                'key' => $key,
                'value' => $stringValue,
                'type' => $type,
                'category' => $category ?? 'general',
                'value2' => $stringValue,
                'type2' => $type
            ]);

            // עדכון cache
            $this->cache[$key] = $value;

            return true;

        } catch (Exception $e) {
            error_log("UserSettingsManager::set error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * שמירת מספר הגדרות בבת אחת
     */
    public function setMultiple($settings) {
        $success = true;

        try {
            $this->conn->beginTransaction();

            foreach ($settings as $key => $data) {
                $value = is_array($data) ? ($data['value'] ?? $data) : $data;
                $type = is_array($data) ? ($data['type'] ?? null) : null;
                $category = is_array($data) ? ($data['category'] ?? null) : null;

                if (!$this->set($key, $value, $type, $category)) {
                    $success = false;
                }
            }

            $this->conn->commit();
            return $success;

        } catch (Exception $e) {
            $this->conn->rollBack();
            error_log("UserSettingsManager::setMultiple error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * מחיקת הגדרה (חזרה לברירת מחדל)
     */
    public function reset($key) {
        try {
            $stmt = $this->conn->prepare("
                DELETE FROM user_settings
                WHERE userId = :userId AND settingKey = :key
            ");
            $stmt->execute(['userId' => $this->userId, 'key' => $key]);

            unset($this->cache[$key]);

            return true;

        } catch (Exception $e) {
            error_log("UserSettingsManager::reset error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * איפוס כל ההגדרות
     */
    public function resetAll($category = null) {
        try {
            $sql = "DELETE FROM user_settings WHERE userId = :userId";
            $params = ['userId' => $this->userId];

            if ($category) {
                $sql .= " AND category = :category";
                $params['category'] = $category;
            }

            $stmt = $this->conn->prepare($sql);
            $stmt->execute($params);

            $this->cache = [];

            return true;

        } catch (Exception $e) {
            error_log("UserSettingsManager::resetAll error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * קבלת ערך ברירת מחדל
     */
    private function getDefault($key) {
        try {
            $stmt = $this->conn->prepare("
                SELECT defaultValue, settingType
                FROM user_settings_defaults
                WHERE settingKey = :key AND isActive = 1
            ");
            $stmt->execute(['key' => $key]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($result) {
                return $this->castValue($result['defaultValue'], $result['settingType']);
            }

            return null;

        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * המרת ערך לסוג הנכון
     */
    private function castValue($value, $type) {
        switch ($type) {
            case 'number':
                return is_numeric($value) ? (float)$value : 0;
            case 'boolean':
                return filter_var($value, FILTER_VALIDATE_BOOLEAN);
            case 'json':
                return json_decode($value, true);
            default:
                return $value;
        }
    }

    /**
     * המרת ערך למחרוזת לשמירה
     */
    private function valueToString($value, $type) {
        switch ($type) {
            case 'boolean':
                return $value ? 'true' : 'false';
            case 'json':
                return is_array($value) ? json_encode($value) : $value;
            default:
                return (string)$value;
        }
    }

    /**
     * קבלת רשימת קטגוריות
     */
    public function getCategories() {
        try {
            $stmt = $this->conn->prepare("
                SELECT DISTINCT category
                FROM user_settings_defaults
                WHERE isActive = 1
                ORDER BY category
            ");
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_COLUMN);

        } catch (Exception $e) {
            return [];
        }
    }
}
