<?php
/**
 * Permission Storage Class
 * permissions/core/PermissionStorage.php
 * 
 * מחלקה לניהול שמירת וטעינת נתוני הרשאות מה-DB
 */

namespace Permissions\Core;

class PermissionStorage {
    
    private $pdo;
    private $tableName = 'user_permissions';
    private $logTableName = 'permission_logs';
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->pdo = $this->getDBConnection();
        $this->ensureTablesExist();
    }
    
    /**
     * קבלת חיבור למסד נתונים
     */
    private function getDBConnection() {
        // נסה לטעון את הפונקציה הגלובלית
        if (function_exists('getDBConnection')) {
            return getDBConnection();
        }
        
        // אחרת, צור חיבור ישיר
        require_once dirname(dirname(dirname(__DIR__))) . '/config.php';
        
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            $options = [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                \PDO::ATTR_EMULATE_PREPARES => false,
            ];
            
            return new \PDO($dsn, DB_USER, DB_PASSWORD, $options);
        } catch (\PDOException $e) {
            error_log("Permission Storage DB Error: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * וידוא שהטבלאות קיימות
     */
    private function ensureTablesExist() {
        // טבלת הרשאות משתמשים
        $sql1 = "CREATE TABLE IF NOT EXISTS {$this->tableName} (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            permission_type VARCHAR(50) NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'default',
            browser_support BOOLEAN DEFAULT TRUE,
            api_support BOOLEAN DEFAULT TRUE,
            denied_count INT DEFAULT 0,
            last_checked TIMESTAMP NULL DEFAULT NULL,
            last_prompted TIMESTAMP NULL DEFAULT NULL,
            granted_at TIMESTAMP NULL DEFAULT NULL,
            metadata JSON,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY unique_user_permission (user_id, permission_type),
            INDEX idx_user_id (user_id),
            INDEX idx_status (status),
            INDEX idx_permission_type (permission_type)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        // טבלת לוג פעולות
        $sql2 = "CREATE TABLE IF NOT EXISTS {$this->logTableName} (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            permission_type VARCHAR(50) NOT NULL,
            action VARCHAR(50) NOT NULL,
            old_status VARCHAR(20),
            new_status VARCHAR(20),
            browser VARCHAR(255),
            ip_address VARCHAR(45),
            user_agent TEXT,
            metadata JSON,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_user_id (user_id),
            INDEX idx_permission_type (permission_type),
            INDEX idx_action (action),
            INDEX idx_created_at (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        try {
            $this->pdo->exec($sql1);
            $this->pdo->exec($sql2);
        } catch (\PDOException $e) {
            error_log("Error creating permissions tables: " . $e->getMessage());
        }
    }
    
    /**
     * קבלת סטטוס הרשאה
     * @param int $userId
     * @param string $type
     * @return array|null
     */
    public function getPermissionStatus($userId, $type) {
        if (!$userId) return null;
        
        $stmt = $this->pdo->prepare("
            SELECT * FROM {$this->tableName} 
            WHERE user_id = ? AND permission_type = ?
        ");
        
        $stmt->execute([$userId, $type]);
        $result = $stmt->fetch();
        
        // אם יש תוצאה, פענח את ה-JSON
        if ($result && isset($result['metadata'])) {
            $result['metadata'] = json_decode($result['metadata'], true);
        }
        
        return $result;
    }
    
    /**
     * קבלת כל ההרשאות של משתמש
     * @param int $userId
     * @return array
     */
    public function getUserPermissions($userId) {
        if (!$userId) return [];
        
        $stmt = $this->pdo->prepare("
            SELECT * FROM {$this->tableName} 
            WHERE user_id = ? 
            ORDER BY permission_type
        ");
        
        $stmt->execute([$userId]);
        $results = $stmt->fetchAll();
        
        // פענח JSON לכל תוצאה
        foreach ($results as &$result) {
            if (isset($result['metadata'])) {
                $result['metadata'] = json_decode($result['metadata'], true);
            }
        }
        
        return $results;
    }
    
    /**
     * עדכון סטטוס הרשאה
     * @param array $data
     * @return bool
     */
    public function updatePermissionStatus($data) {
        // לוג את הפעולה קודם
        $this->logPermissionAction($data);
        
        // בדוק אם קיימת רשומה
        $existing = $this->getPermissionStatus($data['user_id'], $data['permission_type']);
        
        if ($existing) {
            // עדכון רשומה קיימת
            $sql = "UPDATE {$this->tableName} SET 
                    status = :status,
                    denied_count = :denied_count,
                    last_checked = NOW(),
                    last_prompted = :last_prompted,
                    granted_at = :granted_at,
                    metadata = :metadata,
                    browser_support = :browser_support,
                    api_support = :api_support
                    WHERE user_id = :user_id AND permission_type = :permission_type";
            
            $params = [
                ':status' => $data['status'],
                ':denied_count' => $data['denied_count'] ?? $existing['denied_count'],
                ':last_prompted' => $data['last_prompted'] ?? $existing['last_prompted'],
                ':granted_at' => $data['granted_at'] ?? $existing['granted_at'],
                ':metadata' => isset($data['metadata']) ? json_encode($data['metadata']) : $existing['metadata'],
                ':browser_support' => $data['browser_support'] ?? $existing['browser_support'],
                ':api_support' => $data['api_support'] ?? $existing['api_support'],
                ':user_id' => $data['user_id'],
                ':permission_type' => $data['permission_type']
            ];
        } else {
            // הוספת רשומה חדשה
            $sql = "INSERT INTO {$this->tableName} 
                    (user_id, permission_type, status, denied_count, last_checked, 
                     last_prompted, granted_at, metadata, browser_support, api_support)
                    VALUES 
                    (:user_id, :permission_type, :status, :denied_count, NOW(), 
                     :last_prompted, :granted_at, :metadata, :browser_support, :api_support)";
            
            $params = [
                ':user_id' => $data['user_id'],
                ':permission_type' => $data['permission_type'],
                ':status' => $data['status'],
                ':denied_count' => $data['denied_count'] ?? 0,
                ':last_prompted' => $data['last_prompted'] ?? null,
                ':granted_at' => $data['granted_at'] ?? null,
                ':metadata' => isset($data['metadata']) ? json_encode($data['metadata']) : null,
                ':browser_support' => $data['browser_support'] ?? true,
                ':api_support' => $data['api_support'] ?? true
            ];
        }
        
        try {
            $stmt = $this->pdo->prepare($sql);
            return $stmt->execute($params);
        } catch (\PDOException $e) {
            error_log("Error updating permission status: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * הגדלת מונה דחיות
     * @param int $userId
     * @param string $type
     * @return int המונה החדש
     */
    public function incrementDeniedCount($userId, $type) {
        $current = $this->getPermissionStatus($userId, $type);
        $newCount = ($current['denied_count'] ?? 0) + 1;
        
        $this->updatePermissionStatus([
            'user_id' => $userId,
            'permission_type' => $type,
            'status' => 'denied',
            'denied_count' => $newCount,
            'last_prompted' => date('Y-m-d H:i:s')
        ]);
        
        return $newCount;
    }
    
    /**
     * רישום פעולת הרשאה ללוג
     * @param array $data
     */
    private function logPermissionAction($data) {
        // קבל סטטוס קודם
        $existing = $this->getPermissionStatus($data['user_id'], $data['permission_type']);
        $oldStatus = $existing['status'] ?? null;
        
        $sql = "INSERT INTO {$this->logTableName} 
                (user_id, permission_type, action, old_status, new_status, 
                 browser, ip_address, user_agent, metadata)
                VALUES 
                (:user_id, :permission_type, :action, :old_status, :new_status,
                 :browser, :ip_address, :user_agent, :metadata)";
        
        $params = [
            ':user_id' => $data['user_id'],
            ':permission_type' => $data['permission_type'],
            ':action' => $this->determineAction($oldStatus, $data['status']),
            ':old_status' => $oldStatus,
            ':new_status' => $data['status'],
            ':browser' => $data['browser'] ?? $this->detectBrowser(),
            ':ip_address' => $data['ip_address'] ?? $_SERVER['REMOTE_ADDR'] ?? null,
            ':user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
            ':metadata' => isset($data['metadata']) ? json_encode($data['metadata']) : null
        ];
        
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
        } catch (\PDOException $e) {
            error_log("Error logging permission action: " . $e->getMessage());
        }
    }
    
    /**
     * קבלת היסטוריית הרשאות
     * @param int $userId
     * @param string|null $type
     * @param int $limit
     * @return array
     */
    public function getPermissionHistory($userId, $type = null, $limit = 50) {
        $sql = "SELECT * FROM {$this->logTableName} 
                WHERE user_id = ?";
        
        $params = [$userId];
        
        if ($type) {
            $sql .= " AND permission_type = ?";
            $params[] = $type;
        }
        
        $sql .= " ORDER BY created_at DESC LIMIT ?";
        $params[] = $limit;
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll();
    }
    
    /**
     * קבלת סטטיסטיקות הרשאות
     * @param int|null $userId
     * @return array
     */
    public function getPermissionStats($userId = null) {
        $stats = [];
        
        // סטטיסטיקות כלליות
        $sql = "SELECT 
                COUNT(DISTINCT user_id) as total_users,
                COUNT(*) as total_permissions,
                SUM(CASE WHEN status = 'granted' THEN 1 ELSE 0 END) as granted_count,
                SUM(CASE WHEN status = 'denied' THEN 1 ELSE 0 END) as denied_count,
                SUM(CASE WHEN status = 'blocked' THEN 1 ELSE 0 END) as blocked_count
                FROM {$this->tableName}";
        
        if ($userId) {
            $sql .= " WHERE user_id = ?";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$userId]);
        } else {
            $stmt = $this->pdo->query($sql);
        }
        
        $stats['general'] = $stmt->fetch();
        
        // סטטיסטיקות לפי סוג הרשאה
        $sql = "SELECT 
                permission_type,
                COUNT(*) as count,
                SUM(CASE WHEN status = 'granted' THEN 1 ELSE 0 END) as granted,
                AVG(denied_count) as avg_denied_count
                FROM {$this->tableName}";
        
        if ($userId) {
            $sql .= " WHERE user_id = ?";
        }
        
        $sql .= " GROUP BY permission_type";
        
        if ($userId) {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$userId]);
        } else {
            $stmt = $this->pdo->query($sql);
        }
        
        $stats['by_type'] = $stmt->fetchAll();
        
        return $stats;
    }
    
    /**
     * ניקוי נתונים ישנים
     * @param int $daysToKeep
     * @return int מספר רשומות שנמחקו
     */
    public function cleanOldData($daysToKeep = 90) {
        $sql = "DELETE FROM {$this->logTableName} 
                WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$daysToKeep]);
        
        return $stmt->rowCount();
    }
    
    /**
     * איפוס הרשאות משתמש
     * @param int $userId
     * @param string|null $type אם null, מאפס הכל
     * @return bool
     */
    public function resetUserPermissions($userId, $type = null) {
        if ($type) {
            $sql = "DELETE FROM {$this->tableName} 
                    WHERE user_id = ? AND permission_type = ?";
            $params = [$userId, $type];
        } else {
            $sql = "DELETE FROM {$this->tableName} WHERE user_id = ?";
            $params = [$userId];
        }
        
        try {
            $stmt = $this->pdo->prepare($sql);
            return $stmt->execute($params);
        } catch (\PDOException $e) {
            error_log("Error resetting permissions: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * פונקציות עזר פרטיות
     */
    
    private function determineAction($oldStatus, $newStatus) {
        if (!$oldStatus && $newStatus) {
            return 'requested';
        }
        if ($oldStatus === 'prompt' && $newStatus === 'granted') {
            return 'granted';
        }
        if ($oldStatus === 'prompt' && $newStatus === 'denied') {
            return 'denied';
        }
        if ($newStatus === 'blocked') {
            return 'blocked';
        }
        return 'updated';
    }
    
    private function detectBrowser() {
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        if (strpos($userAgent, 'Chrome') !== false) return 'Chrome';
        if (strpos($userAgent, 'Firefox') !== false) return 'Firefox';
        if (strpos($userAgent, 'Safari') !== false) return 'Safari';
        if (strpos($userAgent, 'Edge') !== false) return 'Edge';
        
        return 'Unknown';
    }
}