<?php
/**
 * Audit Logger - מערכת לוגים לפעולות משתמשים ואירועי אבטחה
 *
 * שימוש:
 * require_once 'audit-logger.php';
 *
 * // רישום התחברות
 * AuditLogger::logLogin($userId, true);  // הצלחה
 * AuditLogger::logLogin(null, false, 'username');  // כישלון
 *
 * // רישום פעולה
 * AuditLogger::log('create', 'grave', $graveId, ['name' => 'קבר חדש']);
 *
 * // קבלת לוגים
 * $logs = AuditLogger::getLogs(['user_id' => 5, 'limit' => 50]);
 *
 * @version 1.0.0
 * @author Malkiel
 */

class AuditLogger {
    private static ?PDO $pdo = null;

    // סוגי אירועים
    const EVENT_LOGIN = 'login';
    const EVENT_LOGOUT = 'logout';
    const EVENT_LOGIN_FAILED = 'login_failed';
    const EVENT_REGISTER = 'register';
    const EVENT_PASSWORD_CHANGE = 'password_change';
    const EVENT_PASSWORD_RESET = 'password_reset';
    const EVENT_CREATE = 'create';
    const EVENT_UPDATE = 'update';
    const EVENT_DELETE = 'delete';
    const EVENT_VIEW = 'view';
    const EVENT_EXPORT = 'export';
    const EVENT_PERMISSION_DENIED = 'permission_denied';
    const EVENT_RATE_LIMITED = 'rate_limited';
    const EVENT_CSRF_FAILED = 'csrf_failed';

    /**
     * קבלת חיבור למסד הנתונים
     */
    private static function getConnection(): PDO {
        if (self::$pdo === null) {
            self::$pdo = getDBConnection();
            self::ensureTableExists();
        }
        return self::$pdo;
    }

    /**
     * וודא שטבלת הלוגים קיימת
     */
    private static function ensureTableExists(): void {
        self::$pdo->exec("
            CREATE TABLE IF NOT EXISTS audit_logs (
                id INT AUTO_INCREMENT PRIMARY KEY,
                event_type VARCHAR(50) NOT NULL,
                event_category VARCHAR(50) DEFAULT 'general',
                user_id INT DEFAULT NULL,
                username VARCHAR(255) DEFAULT NULL,
                ip_address VARCHAR(45) NOT NULL,
                user_agent TEXT,
                module VARCHAR(100) DEFAULT NULL,
                entity_type VARCHAR(100) DEFAULT NULL,
                entity_id INT DEFAULT NULL,
                action_details JSON DEFAULT NULL,
                success BOOLEAN DEFAULT TRUE,
                error_message TEXT DEFAULT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_event_type (event_type),
                INDEX idx_user_id (user_id),
                INDEX idx_ip (ip_address),
                INDEX idx_created (created_at),
                INDEX idx_entity (entity_type, entity_id),
                INDEX idx_category (event_category)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    /**
     * קבלת כתובת IP
     */
    private static function getClientIP(): string {
        $headers = [
            'HTTP_CF_CONNECTING_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'REMOTE_ADDR'
        ];

        foreach ($headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ip = $_SERVER[$header];
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }

    /**
     * רישום לוג כללי
     */
    public static function log(
        string $eventType,
        ?string $entityType = null,
        ?int $entityId = null,
        array $details = [],
        bool $success = true,
        ?string $errorMessage = null,
        ?string $category = null
    ): bool {
        try {
            $pdo = self::getConnection();

            $userId = $_SESSION['user_id'] ?? null;
            $username = $_SESSION['username'] ?? null;

            // קביעת קטגוריה אוטומטית
            if ($category === null) {
                $category = self::determineCategory($eventType);
            }

            $stmt = $pdo->prepare("
                INSERT INTO audit_logs (
                    event_type, event_category, user_id, username,
                    ip_address, user_agent, entity_type, entity_id,
                    action_details, success, error_message
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

            return $stmt->execute([
                $eventType,
                $category,
                $userId,
                $username,
                self::getClientIP(),
                $_SERVER['HTTP_USER_AGENT'] ?? null,
                $entityType,
                $entityId,
                !empty($details) ? json_encode($details, JSON_UNESCAPED_UNICODE) : null,
                $success ? 1 : 0,
                $errorMessage
            ]);

        } catch (Exception $e) {
            error_log("Audit Logger Error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * קביעת קטגוריה לפי סוג האירוע
     */
    private static function determineCategory(string $eventType): string {
        $authEvents = [self::EVENT_LOGIN, self::EVENT_LOGOUT, self::EVENT_LOGIN_FAILED,
                       self::EVENT_REGISTER, self::EVENT_PASSWORD_CHANGE, self::EVENT_PASSWORD_RESET];
        $securityEvents = [self::EVENT_PERMISSION_DENIED, self::EVENT_RATE_LIMITED, self::EVENT_CSRF_FAILED];
        $dataEvents = [self::EVENT_CREATE, self::EVENT_UPDATE, self::EVENT_DELETE, self::EVENT_VIEW, self::EVENT_EXPORT];

        if (in_array($eventType, $authEvents)) return 'auth';
        if (in_array($eventType, $securityEvents)) return 'security';
        if (in_array($eventType, $dataEvents)) return 'data';
        return 'general';
    }

    /**
     * רישום התחברות מוצלחת
     */
    public static function logLogin(int $userId, string $username, string $authType = 'local'): bool {
        return self::log(
            self::EVENT_LOGIN,
            'user',
            $userId,
            ['auth_type' => $authType],
            true,
            null,
            'auth'
        );
    }

    /**
     * רישום התחברות כושלת
     */
    public static function logLoginFailed(string $username, string $reason = 'Invalid credentials'): bool {
        // שמירה זמנית של השם משתמש לפני הלוג
        $originalUsername = $_SESSION['username'] ?? null;
        $_SESSION['username'] = $username;

        $result = self::log(
            self::EVENT_LOGIN_FAILED,
            'user',
            null,
            ['attempted_username' => $username],
            false,
            $reason,
            'auth'
        );

        // החזרת המצב הקודם
        if ($originalUsername === null) {
            unset($_SESSION['username']);
        } else {
            $_SESSION['username'] = $originalUsername;
        }

        return $result;
    }

    /**
     * רישום התנתקות
     */
    public static function logLogout(): bool {
        $userId = $_SESSION['user_id'] ?? null;
        return self::log(
            self::EVENT_LOGOUT,
            'user',
            $userId,
            [],
            true,
            null,
            'auth'
        );
    }

    /**
     * רישום הרשמה
     */
    public static function logRegister(int $userId, string $username, string $email): bool {
        return self::log(
            self::EVENT_REGISTER,
            'user',
            $userId,
            ['username' => $username, 'email' => $email],
            true,
            null,
            'auth'
        );
    }

    /**
     * רישום שינוי סיסמה
     */
    public static function logPasswordChange(int $userId): bool {
        return self::log(
            self::EVENT_PASSWORD_CHANGE,
            'user',
            $userId,
            [],
            true,
            null,
            'auth'
        );
    }

    /**
     * רישום יצירת רשומה
     */
    public static function logCreate(string $entityType, int $entityId, array $details = []): bool {
        return self::log(self::EVENT_CREATE, $entityType, $entityId, $details);
    }

    /**
     * רישום עדכון רשומה
     */
    public static function logUpdate(string $entityType, int $entityId, array $changes = []): bool {
        return self::log(self::EVENT_UPDATE, $entityType, $entityId, ['changes' => $changes]);
    }

    /**
     * רישום מחיקת רשומה
     */
    public static function logDelete(string $entityType, int $entityId, array $details = []): bool {
        return self::log(self::EVENT_DELETE, $entityType, $entityId, $details);
    }

    /**
     * רישום צפייה (אופציונלי - לפעולות רגישות)
     */
    public static function logView(string $entityType, int $entityId): bool {
        return self::log(self::EVENT_VIEW, $entityType, $entityId);
    }

    /**
     * רישום ייצוא נתונים
     */
    public static function logExport(string $entityType, array $details = []): bool {
        return self::log(self::EVENT_EXPORT, $entityType, null, $details);
    }

    /**
     * רישום אירוע אבטחה - הרשאה נדחתה
     */
    public static function logPermissionDenied(string $resource, string $action): bool {
        return self::log(
            self::EVENT_PERMISSION_DENIED,
            $resource,
            null,
            ['attempted_action' => $action],
            false,
            'Permission denied',
            'security'
        );
    }

    /**
     * רישום אירוע אבטחה - rate limit
     */
    public static function logRateLimited(string $action): bool {
        return self::log(
            self::EVENT_RATE_LIMITED,
            null,
            null,
            ['action' => $action],
            false,
            'Rate limit exceeded',
            'security'
        );
    }

    /**
     * רישום אירוע אבטחה - CSRF נכשל
     */
    public static function logCsrfFailed(): bool {
        return self::log(
            self::EVENT_CSRF_FAILED,
            null,
            null,
            [],
            false,
            'CSRF validation failed',
            'security'
        );
    }

    /**
     * קבלת לוגים עם פילטרים
     */
    public static function getLogs(array $filters = []): array {
        try {
            $pdo = self::getConnection();

            $where = [];
            $params = [];

            if (!empty($filters['user_id'])) {
                $where[] = 'user_id = ?';
                $params[] = $filters['user_id'];
            }

            if (!empty($filters['event_type'])) {
                $where[] = 'event_type = ?';
                $params[] = $filters['event_type'];
            }

            if (!empty($filters['category'])) {
                $where[] = 'event_category = ?';
                $params[] = $filters['category'];
            }

            if (!empty($filters['entity_type'])) {
                $where[] = 'entity_type = ?';
                $params[] = $filters['entity_type'];
            }

            if (!empty($filters['entity_id'])) {
                $where[] = 'entity_id = ?';
                $params[] = $filters['entity_id'];
            }

            if (!empty($filters['ip_address'])) {
                $where[] = 'ip_address = ?';
                $params[] = $filters['ip_address'];
            }

            if (!empty($filters['date_from'])) {
                $where[] = 'created_at >= ?';
                $params[] = $filters['date_from'];
            }

            if (!empty($filters['date_to'])) {
                $where[] = 'created_at <= ?';
                $params[] = $filters['date_to'];
            }

            if (isset($filters['success'])) {
                $where[] = 'success = ?';
                $params[] = $filters['success'] ? 1 : 0;
            }

            $sql = "SELECT * FROM audit_logs";
            if (!empty($where)) {
                $sql .= " WHERE " . implode(' AND ', $where);
            }
            $sql .= " ORDER BY created_at DESC";

            $limit = $filters['limit'] ?? 100;
            $offset = $filters['offset'] ?? 0;
            $sql .= " LIMIT $limit OFFSET $offset";

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);

            return $stmt->fetchAll(PDO::FETCH_ASSOC);

        } catch (Exception $e) {
            error_log("Audit Logger Error (getLogs): " . $e->getMessage());
            return [];
        }
    }

    /**
     * קבלת סטטיסטיקות לוגים
     */
    public static function getStats(string $period = '24h'): array {
        try {
            $pdo = self::getConnection();

            // חישוב תאריך התחלה
            $periods = [
                '1h' => '1 HOUR',
                '24h' => '24 HOUR',
                '7d' => '7 DAY',
                '30d' => '30 DAY'
            ];
            $interval = $periods[$period] ?? '24 HOUR';

            $stmt = $pdo->prepare("
                SELECT
                    event_category,
                    event_type,
                    COUNT(*) as count,
                    SUM(CASE WHEN success = 1 THEN 1 ELSE 0 END) as success_count,
                    SUM(CASE WHEN success = 0 THEN 1 ELSE 0 END) as failure_count
                FROM audit_logs
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL $interval)
                GROUP BY event_category, event_type
                ORDER BY count DESC
            ");
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_ASSOC);

        } catch (Exception $e) {
            error_log("Audit Logger Error (getStats): " . $e->getMessage());
            return [];
        }
    }

    /**
     * ניקוי לוגים ישנים
     */
    public static function cleanOldLogs(int $daysToKeep = 90): int {
        try {
            $pdo = self::getConnection();

            $stmt = $pdo->prepare("
                DELETE FROM audit_logs
                WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)
            ");
            $stmt->execute([$daysToKeep]);

            return $stmt->rowCount();

        } catch (Exception $e) {
            error_log("Audit Logger Error (cleanOldLogs): " . $e->getMessage());
            return 0;
        }
    }

    /**
     * קבלת פעילות אחרונה של משתמש
     */
    public static function getUserActivity(int $userId, int $limit = 20): array {
        return self::getLogs([
            'user_id' => $userId,
            'limit' => $limit
        ]);
    }

    /**
     * קבלת אירועי אבטחה אחרונים
     */
    public static function getSecurityEvents(int $limit = 50): array {
        return self::getLogs([
            'category' => 'security',
            'limit' => $limit
        ]);
    }

    /**
     * קבלת התחברויות כושלות אחרונות
     */
    public static function getFailedLogins(int $limit = 50): array {
        return self::getLogs([
            'event_type' => self::EVENT_LOGIN_FAILED,
            'limit' => $limit
        ]);
    }
}

// פונקציות עזר גלובליות
function auditLog(string $eventType, ?string $entityType = null, ?int $entityId = null, array $details = []): bool {
    return AuditLogger::log($eventType, $entityType, $entityId, $details);
}

function auditLogCreate(string $entityType, int $entityId, array $details = []): bool {
    return AuditLogger::logCreate($entityType, $entityId, $details);
}

function auditLogUpdate(string $entityType, int $entityId, array $changes = []): bool {
    return AuditLogger::logUpdate($entityType, $entityId, $changes);
}

function auditLogDelete(string $entityType, int $entityId, array $details = []): bool {
    return AuditLogger::logDelete($entityType, $entityId, $details);
}
?>
