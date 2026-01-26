<?php
/**
 * OTP Manager - ניהול קודי אימות SMS
 * תומך ב-WebOTP API לקריאה אוטומטית
 *
 * @version 1.0.0
 */

require_once $_SERVER['DOCUMENT_ROOT'] . '/config.php';

class OTPManager {

    private $pdo;
    private $domain;

    const OTP_LENGTH = 6;
    const OTP_LIFETIME = 300;        // 5 דקות
    const MAX_ATTEMPTS = 3;
    const RATE_LIMIT_INTERVAL = 60;  // דקה בין שליחות
    const RATE_LIMIT_MAX = 5;        // מקסימום 5 שליחות בשעה

    public function __construct() {
        $this->pdo = getDBConnection();
        $this->domain = $this->getDomain();
    }

    /**
     * קבלת הדומיין
     */
    private function getDomain(): string {
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        return preg_replace('/:\d+$/', '', $host);
    }

    /**
     * יצירת ושליחת OTP
     *
     * @param string $phone - מספר טלפון
     * @param string $purpose - מטרה (login, registration, verification, etc.)
     * @param int|null $userId - מזהה משתמש (אופציונלי)
     * @param array $actionData - מידע נוסף (אופציונלי)
     * @return array
     */
    public function sendOTP(string $phone, string $purpose = 'verification', ?int $userId = null, array $actionData = []): array {
        // נרמל מספר טלפון
        $phone = $this->normalizePhone($phone);

        if (!$this->validatePhone($phone)) {
            return ['success' => false, 'error' => 'Invalid phone number'];
        }

        // בדיקת rate limit
        if (!$this->checkRateLimit($phone)) {
            return ['success' => false, 'error' => 'Too many requests. Please wait.', 'rate_limited' => true];
        }

        // בטל קודים קודמים
        $this->invalidatePreviousCodes($phone, $purpose);

        // יצירת קוד חדש
        $code = $this->generateCode();
        $expiresAt = date('Y-m-d H:i:s', time() + self::OTP_LIFETIME);

        // שמור ב-DB
        $stmt = $this->pdo->prepare("
            INSERT INTO otp_codes (user_id, phone, code, purpose, action_data, expires_at)
            VALUES (?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $userId,
            $phone,
            $code,
            $purpose,
            !empty($actionData) ? json_encode($actionData) : null,
            $expiresAt
        ]);

        $otpId = $this->pdo->lastInsertId();

        // רשום rate limit
        $this->recordSMSSent($phone);

        // שלח SMS
        $sent = $this->sendSMS($phone, $code);

        if (!$sent) {
            return ['success' => false, 'error' => 'Failed to send SMS'];
        }

        return [
            'success' => true,
            'otp_id' => $otpId,
            'expires_in' => self::OTP_LIFETIME,
            'phone_masked' => $this->maskPhone($phone)
        ];
    }

    /**
     * אימות OTP
     *
     * @param string $phone - מספר טלפון
     * @param string $code - הקוד שהוזן
     * @param string $purpose - מטרה
     * @return array
     */
    public function verifyOTP(string $phone, string $code, string $purpose = 'verification'): array {
        $phone = $this->normalizePhone($phone);

        // מצא את הקוד
        $stmt = $this->pdo->prepare("
            SELECT * FROM otp_codes
            WHERE phone = ?
              AND purpose = ?
              AND is_used = 0
              AND expires_at > NOW()
            ORDER BY created_at DESC
            LIMIT 1
        ");

        $stmt->execute([$phone, $purpose]);
        $otp = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$otp) {
            return ['success' => false, 'error' => 'Code expired or not found'];
        }

        // בדיקת מספר ניסיונות
        if ($otp['attempts'] >= $otp['max_attempts']) {
            $this->markAsUsed($otp['id']);
            return ['success' => false, 'error' => 'Too many attempts', 'locked' => true];
        }

        // עדכן ניסיון
        $this->incrementAttempts($otp['id']);

        // בדיקת קוד
        if (!hash_equals($otp['code'], $code)) {
            $remaining = $otp['max_attempts'] - $otp['attempts'] - 1;
            return [
                'success' => false,
                'error' => 'Invalid code',
                'attempts_remaining' => $remaining
            ];
        }

        // סמן כמשומש
        $this->markAsUsed($otp['id']);

        // עדכן טלפון מאומת אם יש user_id
        if ($otp['user_id']) {
            $this->markPhoneVerified($otp['user_id'], $phone);
        }

        return [
            'success' => true,
            'user_id' => $otp['user_id'],
            'action_data' => $otp['action_data'] ? json_decode($otp['action_data'], true) : null
        ];
    }

    /**
     * שליחת SMS
     * ניתן להחליף לספק SMS אמיתי (Twilio, Nexmo, וכו')
     *
     * @param string $phone
     * @param string $code
     * @return bool
     */
    private function sendSMS(string $phone, string $code): bool {
        // הודעה בפורמט WebOTP
        $message = $this->formatWebOTPMessage($code);

        // === כאן צריך להחליף לספק SMS אמיתי ===

        // דוגמה ל-Twilio:
        // return $this->sendViaTwilio($phone, $message);

        // דוגמה ל-SMS Gateway ישראלי:
        // return $this->sendViaLocalProvider($phone, $message);

        // לפיתוח - רק לוג
        error_log("[OTP] Sending to {$phone}: {$code}");
        error_log("[OTP] Message: {$message}");

        // TODO: הגדר את ספק ה-SMS שלך
        // בינתיים מחזיר true לצורכי פיתוח
        return true;
    }

    /**
     * פורמט הודעה לתמיכה ב-WebOTP
     * חייב להיות בפורמט ספציפי!
     *
     * @param string $code
     * @return string
     */
    private function formatWebOTPMessage(string $code): string {
        // פורמט WebOTP:
        // שורה אחרונה חייבת להיות: @domain #code
        return "קוד האימות שלך: {$code}\n\n@{$this->domain} #{$code}";
    }

    /**
     * יצירת קוד אקראי
     */
    private function generateCode(): string {
        return str_pad((string)random_int(0, 999999), self::OTP_LENGTH, '0', STR_PAD_LEFT);
    }

    /**
     * נרמול מספר טלפון
     */
    private function normalizePhone(string $phone): string {
        // הסר כל מה שאינו ספרה
        $phone = preg_replace('/[^0-9]/', '', $phone);

        // המר מ-05 ל-+972
        if (substr($phone, 0, 1) === '0') {
            $phone = '972' . substr($phone, 1);
        }

        // הוסף + אם אין
        if (substr($phone, 0, 1) !== '+') {
            $phone = '+' . $phone;
        }

        return $phone;
    }

    /**
     * אימות מספר טלפון
     */
    private function validatePhone(string $phone): bool {
        // בדיקה בסיסית - מספר ישראלי
        return preg_match('/^\+9725[0-9]{8}$/', $phone) === 1;
    }

    /**
     * הסתרת מספר טלפון
     */
    private function maskPhone(string $phone): string {
        $len = strlen($phone);
        if ($len < 8) return $phone;

        return substr($phone, 0, 4) . str_repeat('*', $len - 6) . substr($phone, -2);
    }

    /**
     * בדיקת rate limit
     */
    private function checkRateLimit(string $phone): bool {
        $ip = $this->getClientIP();

        // בדוק שליחות אחרונה
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) as count FROM sms_rate_limits
            WHERE (phone = ? OR ip_address = ?)
              AND sent_at > DATE_SUB(NOW(), INTERVAL ? SECOND)
        ");
        $stmt->execute([$phone, $ip, self::RATE_LIMIT_INTERVAL]);
        $recent = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($recent['count'] > 0) {
            return false; // שליחה אחרונה היתה לפני פחות מדקה
        }

        // בדוק סה"כ בשעה האחרונה
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) as count FROM sms_rate_limits
            WHERE (phone = ? OR ip_address = ?)
              AND sent_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)
        ");
        $stmt->execute([$phone, $ip]);
        $hourly = $stmt->fetch(PDO::FETCH_ASSOC);

        return $hourly['count'] < self::RATE_LIMIT_MAX;
    }

    /**
     * רישום שליחת SMS
     */
    private function recordSMSSent(string $phone): void {
        $ip = $this->getClientIP();

        $stmt = $this->pdo->prepare("
            INSERT INTO sms_rate_limits (phone, ip_address) VALUES (?, ?)
        ");
        $stmt->execute([$phone, $ip]);

        // ניקוי רשומות ישנות
        $this->pdo->exec("DELETE FROM sms_rate_limits WHERE sent_at < DATE_SUB(NOW(), INTERVAL 24 HOUR)");
    }

    /**
     * ביטול קודים קודמים
     */
    private function invalidatePreviousCodes(string $phone, string $purpose): void {
        $stmt = $this->pdo->prepare("
            UPDATE otp_codes
            SET is_used = 1
            WHERE phone = ? AND purpose = ? AND is_used = 0
        ");
        $stmt->execute([$phone, $purpose]);
    }

    /**
     * עדכון מספר ניסיונות
     */
    private function incrementAttempts(int $otpId): void {
        $stmt = $this->pdo->prepare("UPDATE otp_codes SET attempts = attempts + 1 WHERE id = ?");
        $stmt->execute([$otpId]);
    }

    /**
     * סימון כמשומש
     */
    private function markAsUsed(int $otpId): void {
        $stmt = $this->pdo->prepare("UPDATE otp_codes SET is_used = 1, verified_at = NOW() WHERE id = ?");
        $stmt->execute([$otpId]);
    }

    /**
     * סימון טלפון כמאומת
     */
    private function markPhoneVerified(int $userId, string $phone): void {
        $stmt = $this->pdo->prepare("
            UPDATE users
            SET phone = ?, phone_verified = TRUE, phone_verified_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$phone, $userId]);
    }

    /**
     * קבלת IP
     */
    private function getClientIP(): string {
        $headers = ['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR'];

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

        return '0.0.0.0';
    }

    /**
     * בדיקה אם יש OTP פעיל
     */
    public function hasActiveOTP(string $phone, string $purpose): bool {
        $phone = $this->normalizePhone($phone);

        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) as count FROM otp_codes
            WHERE phone = ?
              AND purpose = ?
              AND is_used = 0
              AND expires_at > NOW()
        ");
        $stmt->execute([$phone, $purpose]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return $result['count'] > 0;
    }

    /**
     * קבלת זמן עד לשליחה הבאה
     */
    public function getTimeUntilNextSend(string $phone): int {
        $phone = $this->normalizePhone($phone);
        $ip = $this->getClientIP();

        $stmt = $this->pdo->prepare("
            SELECT MAX(sent_at) as last_sent FROM sms_rate_limits
            WHERE phone = ? OR ip_address = ?
        ");
        $stmt->execute([$phone, $ip]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$result['last_sent']) {
            return 0;
        }

        $lastSent = strtotime($result['last_sent']);
        $nextAllowed = $lastSent + self::RATE_LIMIT_INTERVAL;
        $remaining = $nextAllowed - time();

        return max(0, $remaining);
    }
}

/**
 * פונקציה גלובלית לקבלת instance
 */
function getOTPManager(): OTPManager {
    static $instance = null;
    if ($instance === null) {
        $instance = new OTPManager();
    }
    return $instance;
}
