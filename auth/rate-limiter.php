<?php
/**
 * Rate Limiter - הגנה מפני ניסיונות התחברות חוזרים (Brute Force)
 *
 * שימוש:
 * require_once 'rate-limiter.php';
 * $rateLimiter = new RateLimiter($pdo);
 *
 * // בדיקה לפני ניסיון התחברות
 * if (!$rateLimiter->canAttempt($ip, $username)) {
 *     $waitTime = $rateLimiter->getWaitTime($ip, $username);
 *     die("נחסמת. נסה שוב בעוד $waitTime דקות");
 * }
 *
 * // רישום ניסיון כושל
 * $rateLimiter->recordFailedAttempt($ip, $username);
 *
 * // ניקוי לאחר הצלחה
 * $rateLimiter->clearAttempts($ip, $username);
 *
 * @version 1.0.0
 * @author Malkiel
 */

class RateLimiter {
    private PDO $pdo;

    // הגדרות ברירת מחדל
    private int $maxAttempts = 5;           // מספר ניסיונות מקסימלי
    private int $lockoutDuration = 900;     // זמן נעילה בשניות (15 דקות)
    private int $attemptWindow = 900;       // חלון זמן לספירת ניסיונות (15 דקות)

    // הגדרות נעילה פרוגרסיבית
    private array $lockoutLevels = [
        5 => 900,      // אחרי 5 ניסיונות - 15 דקות
        10 => 3600,    // אחרי 10 ניסיונות - שעה
        15 => 14400,   // אחרי 15 ניסיונות - 4 שעות
        20 => 86400,   // אחרי 20 ניסיונות - יום שלם
    ];

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
        $this->ensureTableExists();
    }

    /**
     * וודא שטבלת הניסיונות קיימת
     */
    private function ensureTableExists(): void {
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS login_attempts (
                id INT AUTO_INCREMENT PRIMARY KEY,
                ip_address VARCHAR(45) NOT NULL,
                username VARCHAR(255) DEFAULT NULL,
                attempt_time DATETIME DEFAULT CURRENT_TIMESTAMP,
                success BOOLEAN DEFAULT FALSE,
                user_agent TEXT,
                INDEX idx_ip (ip_address),
                INDEX idx_username (username),
                INDEX idx_time (attempt_time),
                INDEX idx_ip_time (ip_address, attempt_time)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    /**
     * בדיקה האם ניתן לבצע ניסיון התחברות
     */
    public function canAttempt(string $ip, ?string $username = null): bool {
        // ספירת ניסיונות כושלים לפי IP
        $ipAttempts = $this->getFailedAttemptCount($ip, null);

        // ספירת ניסיונות כושלים לפי שם משתמש (אם ניתן)
        $userAttempts = $username ? $this->getFailedAttemptCount(null, $username) : 0;

        // קח את המקסימום משניהם
        $totalAttempts = max($ipAttempts, $userAttempts);

        // בדוק אם עבר את מגבלת הניסיונות
        if ($totalAttempts >= $this->maxAttempts) {
            // בדוק אם זמן הנעילה הסתיים
            $lastAttemptTime = $this->getLastAttemptTime($ip, $username);
            $lockoutDuration = $this->getLockoutDuration($totalAttempts);

            if ($lastAttemptTime && (time() - strtotime($lastAttemptTime)) < $lockoutDuration) {
                return false;
            }
        }

        return true;
    }

    /**
     * קבלת מספר ניסיונות כושלים
     */
    public function getFailedAttemptCount(?string $ip = null, ?string $username = null): int {
        $windowStart = date('Y-m-d H:i:s', time() - $this->attemptWindow);

        if ($ip && $username) {
            $stmt = $this->pdo->prepare("
                SELECT COUNT(*)
                FROM login_attempts
                WHERE (ip_address = ? OR username = ?)
                AND attempt_time >= ?
                AND success = FALSE
            ");
            $stmt->execute([$ip, $username, $windowStart]);
        } elseif ($ip) {
            $stmt = $this->pdo->prepare("
                SELECT COUNT(*)
                FROM login_attempts
                WHERE ip_address = ?
                AND attempt_time >= ?
                AND success = FALSE
            ");
            $stmt->execute([$ip, $windowStart]);
        } elseif ($username) {
            $stmt = $this->pdo->prepare("
                SELECT COUNT(*)
                FROM login_attempts
                WHERE username = ?
                AND attempt_time >= ?
                AND success = FALSE
            ");
            $stmt->execute([$username, $windowStart]);
        } else {
            return 0;
        }

        return (int) $stmt->fetchColumn();
    }

    /**
     * קבלת זמן הניסיון האחרון
     */
    public function getLastAttemptTime(?string $ip = null, ?string $username = null): ?string {
        if ($ip && $username) {
            $stmt = $this->pdo->prepare("
                SELECT MAX(attempt_time)
                FROM login_attempts
                WHERE (ip_address = ? OR username = ?)
                AND success = FALSE
            ");
            $stmt->execute([$ip, $username]);
        } elseif ($ip) {
            $stmt = $this->pdo->prepare("
                SELECT MAX(attempt_time)
                FROM login_attempts
                WHERE ip_address = ?
                AND success = FALSE
            ");
            $stmt->execute([$ip]);
        } elseif ($username) {
            $stmt = $this->pdo->prepare("
                SELECT MAX(attempt_time)
                FROM login_attempts
                WHERE username = ?
                AND success = FALSE
            ");
            $stmt->execute([$username]);
        } else {
            return null;
        }

        return $stmt->fetchColumn() ?: null;
    }

    /**
     * חישוב זמן נעילה פרוגרסיבי
     */
    private function getLockoutDuration(int $attempts): int {
        $duration = $this->lockoutDuration;

        foreach ($this->lockoutLevels as $threshold => $lockTime) {
            if ($attempts >= $threshold) {
                $duration = $lockTime;
            }
        }

        return $duration;
    }

    /**
     * קבלת זמן המתנה בדקות
     */
    public function getWaitTime(string $ip, ?string $username = null): int {
        $ipAttempts = $this->getFailedAttemptCount($ip, null);
        $userAttempts = $username ? $this->getFailedAttemptCount(null, $username) : 0;
        $totalAttempts = max($ipAttempts, $userAttempts);

        $lastAttemptTime = $this->getLastAttemptTime($ip, $username);
        $lockoutDuration = $this->getLockoutDuration($totalAttempts);

        if ($lastAttemptTime) {
            $timePassed = time() - strtotime($lastAttemptTime);
            $remainingSeconds = $lockoutDuration - $timePassed;
            return max(1, ceil($remainingSeconds / 60));
        }

        return 0;
    }

    /**
     * קבלת מספר ניסיונות שנותרו
     */
    public function getRemainingAttempts(string $ip, ?string $username = null): int {
        $attempts = $this->getFailedAttemptCount($ip, $username);
        return max(0, $this->maxAttempts - $attempts);
    }

    /**
     * רישום ניסיון התחברות
     */
    public function recordAttempt(string $ip, ?string $username = null, bool $success = false): void {
        $stmt = $this->pdo->prepare("
            INSERT INTO login_attempts (ip_address, username, success, user_agent)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([
            $ip,
            $username,
            $success ? 1 : 0,
            $_SERVER['HTTP_USER_AGENT'] ?? null
        ]);
    }

    /**
     * רישום ניסיון כושל (קיצור)
     */
    public function recordFailedAttempt(string $ip, ?string $username = null): void {
        $this->recordAttempt($ip, $username, false);
    }

    /**
     * רישום התחברות מוצלחת (קיצור)
     */
    public function recordSuccessfulLogin(string $ip, ?string $username = null): void {
        $this->recordAttempt($ip, $username, true);
    }

    /**
     * ניקוי ניסיונות (לאחר התחברות מוצלחת)
     */
    public function clearAttempts(string $ip, ?string $username = null): void {
        // לא מוחקים את הרשומות - רק סומנים כהצלחה
        // זה מאפשר ניתוח לאחר מעשה

        // אבל כן מנקים את ה"חלון" הנוכחי על ידי כך שההתחברות המוצלחת רשומה
        // מה שמאפשר ניסיונות חדשים

        // אפשרות נוספת - למחוק ניסיונות ישנים
        $this->cleanOldAttempts();
    }

    /**
     * ניקוי ניסיונות ישנים (יותר מ-24 שעות)
     */
    public function cleanOldAttempts(): void {
        $stmt = $this->pdo->prepare("
            DELETE FROM login_attempts
            WHERE attempt_time < DATE_SUB(NOW(), INTERVAL 24 HOUR)
        ");
        $stmt->execute();
    }

    /**
     * קבלת כתובת ה-IP האמיתית של המשתמש
     */
    public static function getClientIP(): string {
        // בדיקה של proxy headers
        $headers = [
            'HTTP_CF_CONNECTING_IP',     // Cloudflare
            'HTTP_X_FORWARDED_FOR',      // Proxy
            'HTTP_X_FORWARDED',
            'HTTP_X_CLUSTER_CLIENT_IP',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'REMOTE_ADDR'
        ];

        foreach ($headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ip = $_SERVER[$header];
                // אם יש כמה IPs, קח את הראשון
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                // וודא שזה IP תקני
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }

        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }

    /**
     * קבלת סטטיסטיקות ניסיונות
     */
    public function getAttemptStats(string $ip, ?string $username = null): array {
        return [
            'failed_attempts' => $this->getFailedAttemptCount($ip, $username),
            'remaining_attempts' => $this->getRemainingAttempts($ip, $username),
            'can_attempt' => $this->canAttempt($ip, $username),
            'wait_time_minutes' => $this->canAttempt($ip, $username) ? 0 : $this->getWaitTime($ip, $username),
            'is_locked' => !$this->canAttempt($ip, $username)
        ];
    }

    /**
     * בדיקה האם IP חסום לחלוטין (blacklist)
     */
    public function isBlacklisted(string $ip): bool {
        // ספור ניסיונות כושלים ב-24 שעות האחרונות
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*)
            FROM login_attempts
            WHERE ip_address = ?
            AND attempt_time >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
            AND success = FALSE
        ");
        $stmt->execute([$ip]);

        // אם יותר מ-50 ניסיונות כושלים ב-24 שעות - חסום לחלוטין
        return (int) $stmt->fetchColumn() >= 50;
    }

    /**
     * הגדרות
     */
    public function setMaxAttempts(int $max): void {
        $this->maxAttempts = $max;
    }

    public function setLockoutDuration(int $seconds): void {
        $this->lockoutDuration = $seconds;
    }

    public function setAttemptWindow(int $seconds): void {
        $this->attemptWindow = $seconds;
    }
}

// פונקציות עזר גלובליות לשימוש קל
function getRateLimiter(): RateLimiter {
    static $instance = null;
    if ($instance === null) {
        $pdo = getDBConnection();
        $instance = new RateLimiter($pdo);
    }
    return $instance;
}

function checkRateLimit(?string $username = null): array {
    $rateLimiter = getRateLimiter();
    $ip = RateLimiter::getClientIP();

    return [
        'allowed' => $rateLimiter->canAttempt($ip, $username),
        'remaining' => $rateLimiter->getRemainingAttempts($ip, $username),
        'wait_minutes' => $rateLimiter->getWaitTime($ip, $username),
        'stats' => $rateLimiter->getAttemptStats($ip, $username)
    ];
}

function recordLoginAttempt(?string $username = null, bool $success = false): void {
    $rateLimiter = getRateLimiter();
    $ip = RateLimiter::getClientIP();
    $rateLimiter->recordAttempt($ip, $username, $success);
}
?>
