<?php
/**
 * Token Manager - ניהול tokens לאימות עמיד
 * תומך ב-PWA וב-iOS
 *
 * @version 1.0.0
 */

require_once __DIR__ . '/../config.php';

class TokenManager {

    private $pdo;

    // משך תוקף
    const TOKEN_LIFETIME = 30 * 24 * 60 * 60;        // 30 ימים
    const REFRESH_TOKEN_LIFETIME = 90 * 24 * 60 * 60; // 90 ימים
    const REFRESH_THRESHOLD = 24 * 60 * 60;           // רענון אם פחות מ-24 שעות

    public function __construct() {
        $this->pdo = getDBConnection();
    }

    /**
     * יצירת token חדש למשתמש
     *
     * @param int $userId - מזהה המשתמש
     * @param array $deviceInfo - מידע על המכשיר (אופציונלי)
     * @return array - ['token' => ..., 'refresh_token' => ..., 'expires' => ...]
     */
    public function generateToken(int $userId, array $deviceInfo = []): array {
        // יצירת tokens אקראיים
        $token = $this->generateSecureToken();
        $refreshToken = $this->generateSecureToken();

        // תאריכי תפוגה
        $expires = date('Y-m-d H:i:s', time() + self::TOKEN_LIFETIME);
        $refreshExpires = date('Y-m-d H:i:s', time() + self::REFRESH_TOKEN_LIFETIME);

        // מידע נוסף
        $ipAddress = $this->getClientIP();
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;

        // שמור ב-DB
        $stmt = $this->pdo->prepare("
            INSERT INTO user_tokens
            (user_id, token, refresh_token, device_info, ip_address, user_agent, expires_at, refresh_expires_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $userId,
            hash('sha256', $token), // שמור hash של ה-token
            hash('sha256', $refreshToken),
            json_encode($deviceInfo),
            $ipAddress,
            $userAgent,
            $expires,
            $refreshExpires
        ]);

        // מחק tokens ישנים של אותו משתמש (שמור 5 אחרונים)
        $this->cleanupOldTokens($userId);

        return [
            'token' => $token,
            'refresh_token' => $refreshToken,
            'expires' => strtotime($expires) * 1000, // milliseconds for JS
            'expires_at' => $expires,
            'user_id' => $userId
        ];
    }

    /**
     * אימות token
     *
     * @param string $token
     * @return array|null - פרטי המשתמש או null אם לא תקף
     */
    public function validateToken(string $token): ?array {
        $hashedToken = hash('sha256', $token);

        $stmt = $this->pdo->prepare("
            SELECT ut.*, u.username, u.name, u.email, u.profile_picture
            FROM user_tokens ut
            JOIN users u ON ut.user_id = u.id
            WHERE ut.token = ?
              AND ut.is_active = 1
              AND ut.expires_at > NOW()
              AND u.is_active = 1
            LIMIT 1
        ");

        $stmt->execute([$hashedToken]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$result) {
            return null;
        }

        // עדכן זמן שימוש אחרון
        $this->updateLastUsed($result['id']);

        return [
            'user_id' => (int)$result['user_id'],
            'username' => $result['username'],
            'name' => $result['name'],
            'email' => $result['email'],
            'profile_picture' => $result['profile_picture'],
            'token_id' => (int)$result['id'],
            'expires_at' => $result['expires_at'],
            'should_refresh' => $this->shouldRefresh($result['expires_at'])
        ];
    }

    /**
     * רענון token באמצעות refresh token
     *
     * @param string $refreshToken
     * @return array|null - tokens חדשים או null אם לא תקף
     */
    public function refreshToken(string $refreshToken): ?array {
        $hashedRefresh = hash('sha256', $refreshToken);

        $stmt = $this->pdo->prepare("
            SELECT ut.*, u.id as uid
            FROM user_tokens ut
            JOIN users u ON ut.user_id = u.id
            WHERE ut.refresh_token = ?
              AND ut.is_active = 1
              AND ut.refresh_expires_at > NOW()
              AND u.is_active = 1
            LIMIT 1
        ");

        $stmt->execute([$hashedRefresh]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$result) {
            return null;
        }

        // בטל את ה-token הישן
        $this->revokeToken($result['id']);

        // צור token חדש
        $deviceInfo = json_decode($result['device_info'], true) ?? [];
        return $this->generateToken((int)$result['user_id'], $deviceInfo);
    }

    /**
     * ביטול token ספציפי
     *
     * @param int $tokenId
     */
    public function revokeToken(int $tokenId): void {
        $stmt = $this->pdo->prepare("
            UPDATE user_tokens
            SET is_active = 0
            WHERE id = ?
        ");
        $stmt->execute([$tokenId]);
    }

    /**
     * ביטול כל ה-tokens של משתמש
     *
     * @param int $userId
     */
    public function revokeAllUserTokens(int $userId): void {
        $stmt = $this->pdo->prepare("
            UPDATE user_tokens
            SET is_active = 0
            WHERE user_id = ?
        ");
        $stmt->execute([$userId]);
    }

    /**
     * בדיקה אם צריך לרענן token
     *
     * @param string $expiresAt
     * @return bool
     */
    private function shouldRefresh(string $expiresAt): bool {
        $expiresTimestamp = strtotime($expiresAt);
        return ($expiresTimestamp - time()) < self::REFRESH_THRESHOLD;
    }

    /**
     * עדכון זמן שימוש אחרון
     *
     * @param int $tokenId
     */
    private function updateLastUsed(int $tokenId): void {
        $stmt = $this->pdo->prepare("
            UPDATE user_tokens
            SET last_used_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$tokenId]);
    }

    /**
     * מחיקת tokens ישנים (שמור 5 אחרונים)
     *
     * @param int $userId
     */
    private function cleanupOldTokens(int $userId): void {
        $stmt = $this->pdo->prepare("
            DELETE FROM user_tokens
            WHERE user_id = ?
              AND id NOT IN (
                  SELECT id FROM (
                      SELECT id FROM user_tokens
                      WHERE user_id = ?
                      ORDER BY created_at DESC
                      LIMIT 5
                  ) as recent
              )
        ");
        $stmt->execute([$userId, $userId]);
    }

    /**
     * יצירת token אקראי מאובטח
     *
     * @return string
     */
    private function generateSecureToken(): string {
        return bin2hex(random_bytes(32));
    }

    /**
     * קבלת IP של הלקוח
     *
     * @return string
     */
    private function getClientIP(): string {
        $headers = [
            'HTTP_CF_CONNECTING_IP',     // Cloudflare
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_REAL_IP',
            'REMOTE_ADDR'
        ];

        foreach ($headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ip = $_SERVER[$header];
                // אם יש כמה IPs (proxy chain), קח את הראשון
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }

        return '0.0.0.0';
    }

    /**
     * קבלת רשימת sessions פעילים של משתמש
     *
     * @param int $userId
     * @return array
     */
    public function getUserSessions(int $userId): array {
        $stmt = $this->pdo->prepare("
            SELECT id, device_info, ip_address, user_agent, created_at, last_used_at
            FROM user_tokens
            WHERE user_id = ?
              AND is_active = 1
              AND expires_at > NOW()
            ORDER BY last_used_at DESC
        ");

        $stmt->execute([$userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

// פונקציה גלובלית לקבלת instance
function getTokenManager(): TokenManager {
    static $instance = null;
    if ($instance === null) {
        $instance = new TokenManager();
    }
    return $instance;
}
