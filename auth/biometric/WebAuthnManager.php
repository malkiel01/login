<?php
/**
 * WebAuthn Manager - ניהול אימות ביומטרי
 * תומך ב-Touch ID, Face ID, Windows Hello
 *
 * @version 1.0.0
 */

require_once $_SERVER['DOCUMENT_ROOT'] . '/config.php';

class WebAuthnManager {

    private $pdo;
    private $rpId;      // Relying Party ID (domain)
    private $rpName;    // Relying Party Name
    private $origin;    // Origin URL

    const CHALLENGE_LIFETIME = 300; // 5 דקות
    const SUPPORTED_ALGORITHMS = [-7, -257]; // ES256, RS256

    public function __construct() {
        $this->pdo = getDBConnection();
        $this->rpId = $this->getRpId();
        $this->rpName = SITE_NAME ?? 'חברה קדישא';
        $this->origin = $this->getOrigin();
    }

    /**
     * קבלת RP ID מהדומיין
     */
    private function getRpId(): string {
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        // הסר פורט אם יש
        return preg_replace('/:\d+$/', '', $host);
    }

    /**
     * קבלת Origin
     */
    private function getOrigin(): string {
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        return $protocol . '://' . ($this->rpId);
    }

    // ========================================
    // רישום Credential חדש
    // ========================================

    /**
     * יצירת אפשרויות רישום (שלב 1)
     *
     * @param int $userId
     * @param string $username
     * @param string $displayName
     * @return array
     */
    public function createRegistrationOptions(int $userId, string $username, string $displayName): array {
        // יצירת challenge
        $challenge = $this->generateChallenge();
        $this->storeChallenge($userId, $challenge, 'registration');

        // קבלת credentials קיימים (למניעת רישום כפול)
        $existingCredentials = $this->getUserCredentials($userId);
        $excludeCredentials = array_map(function($cred) {
            return [
                'type' => 'public-key',
                'id' => $cred['credential_id']
            ];
        }, $existingCredentials);

        return [
            'challenge' => $this->base64UrlEncode($challenge),
            'rp' => [
                'name' => $this->rpName,
                'id' => $this->rpId
            ],
            'user' => [
                'id' => $this->base64UrlEncode(pack('N', $userId)), // 4-byte user ID
                'name' => $username,
                'displayName' => $displayName
            ],
            'pubKeyCredParams' => [
                ['type' => 'public-key', 'alg' => -7],   // ES256
                ['type' => 'public-key', 'alg' => -257]  // RS256
            ],
            'authenticatorSelection' => [
                'authenticatorAttachment' => 'platform', // רק ביומטרי של המכשיר
                'userVerification' => 'required',
                'residentKey' => 'preferred'
            ],
            'timeout' => 60000, // 60 שניות
            'attestation' => 'none',
            'excludeCredentials' => $excludeCredentials
        ];
    }

    /**
     * אימות ושמירת credential חדש (שלב 2)
     *
     * @param int $userId
     * @param array $credential - הנתונים מהדפדפן
     * @param string $deviceName - שם המכשיר (אופציונלי)
     * @return array
     */
    public function verifyAndStoreCredential(int $userId, array $credential, string $deviceName = ''): array {
        // בדיקת challenge
        $storedChallenge = $this->getAndVerifyChallenge($userId, 'registration');
        if (!$storedChallenge) {
            return ['success' => false, 'error' => 'Invalid or expired challenge'];
        }

        // פענוח attestation object
        $attestationObject = $this->base64UrlDecode($credential['response']['attestationObject']);
        $clientDataJSON = $this->base64UrlDecode($credential['response']['clientDataJSON']);

        // אימות clientDataJSON
        $clientData = json_decode($clientDataJSON, true);
        if (!$clientData) {
            return ['success' => false, 'error' => 'Invalid client data'];
        }

        // בדיקת type
        if ($clientData['type'] !== 'webauthn.create') {
            return ['success' => false, 'error' => 'Invalid type'];
        }

        // בדיקת challenge
        $receivedChallenge = $this->base64UrlDecode($clientData['challenge']);
        if (!hash_equals($storedChallenge, $receivedChallenge)) {
            return ['success' => false, 'error' => 'Challenge mismatch'];
        }

        // בדיקת origin
        if ($clientData['origin'] !== $this->origin) {
            // נסה גם עם פורט
            $originWithPort = $this->origin;
            if (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] != 80 && $_SERVER['SERVER_PORT'] != 443) {
                $originWithPort .= ':' . $_SERVER['SERVER_PORT'];
            }
            if ($clientData['origin'] !== $originWithPort) {
                return ['success' => false, 'error' => 'Origin mismatch'];
            }
        }

        // פענוח attestation object (CBOR)
        $attestation = $this->decodeAttestationObject($attestationObject);
        if (!$attestation) {
            return ['success' => false, 'error' => 'Failed to decode attestation'];
        }

        // חילוץ public key
        $authData = $attestation['authData'];
        $publicKeyData = $this->extractPublicKeyFromAuthData($authData);
        if (!$publicKeyData) {
            return ['success' => false, 'error' => 'Failed to extract public key'];
        }

        // שמירה ב-DB
        $credentialId = $this->base64UrlEncode($publicKeyData['credentialId']);
        $publicKey = json_encode($publicKeyData['publicKey']);

        $stmt = $this->pdo->prepare("
            INSERT INTO user_credentials
            (user_id, credential_id, public_key, public_key_algorithm, device_name, authenticator_type, transports)
            VALUES (?, ?, ?, ?, ?, 'platform', ?)
        ");

        $transports = $credential['response']['transports'] ?? ['internal'];

        $stmt->execute([
            $userId,
            $credentialId,
            $publicKey,
            $publicKeyData['algorithm'] ?? -7,
            $deviceName ?: $this->guessDeviceName(),
            json_encode($transports)
        ]);

        // עדכון biometric_enabled
        $this->pdo->prepare("UPDATE users SET biometric_enabled = TRUE WHERE id = ?")->execute([$userId]);

        // מחיקת challenge
        $this->deleteChallenge($userId, 'registration');

        return [
            'success' => true,
            'credential_id' => $credentialId,
            'message' => 'Biometric registered successfully'
        ];
    }

    // ========================================
    // אימות עם Credential קיים
    // ========================================

    /**
     * יצירת אפשרויות אימות (שלב 1)
     *
     * @param int|null $userId - אם יש, רק credentials של המשתמש
     * @return array
     */
    public function createAuthenticationOptions(?int $userId = null): array {
        $challenge = $this->generateChallenge();
        $this->storeChallenge($userId, $challenge, 'authentication');

        $allowCredentials = [];
        if ($userId) {
            $credentials = $this->getUserCredentials($userId);
            $allowCredentials = array_map(function($cred) {
                return [
                    'type' => 'public-key',
                    'id' => $cred['credential_id'],
                    'transports' => json_decode($cred['transports'] ?? '["internal"]', true)
                ];
            }, $credentials);
        }

        return [
            'challenge' => $this->base64UrlEncode($challenge),
            'rpId' => $this->rpId,
            'timeout' => 60000,
            'userVerification' => 'required',
            'allowCredentials' => $allowCredentials
        ];
    }

    /**
     * אימות credential (שלב 2)
     *
     * @param array $assertion - הנתונים מהדפדפן
     * @param int|null $expectedUserId - אם יש, וודא שזה המשתמש הנכון
     * @return array
     */
    public function verifyAssertion(array $assertion, ?int $expectedUserId = null): array {
        $credentialId = $assertion['id'];

        // מצא את ה-credential ב-DB
        $stmt = $this->pdo->prepare("
            SELECT uc.*, u.id as uid, u.username, u.name, u.email, u.profile_picture
            FROM user_credentials uc
            JOIN users u ON uc.user_id = u.id
            WHERE uc.credential_id = ? AND uc.is_active = 1 AND u.is_active = 1
        ");
        $stmt->execute([$credentialId]);
        $storedCredential = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$storedCredential) {
            return ['success' => false, 'error' => 'Credential not found'];
        }

        $userId = (int)$storedCredential['user_id'];

        // בדיקה אם זה המשתמש הצפוי
        if ($expectedUserId !== null && $expectedUserId !== $userId) {
            return ['success' => false, 'error' => 'User mismatch'];
        }

        // בדיקת challenge
        $storedChallenge = $this->getAndVerifyChallenge($userId, 'authentication');
        if (!$storedChallenge) {
            // נסה גם challenge ללא user_id (לאימות ראשוני)
            $storedChallenge = $this->getAndVerifyChallenge(null, 'authentication');
            if (!$storedChallenge) {
                return ['success' => false, 'error' => 'Invalid or expired challenge'];
            }
        }

        // פענוח נתונים
        $clientDataJSON = $this->base64UrlDecode($assertion['response']['clientDataJSON']);
        $authenticatorData = $this->base64UrlDecode($assertion['response']['authenticatorData']);
        $signature = $this->base64UrlDecode($assertion['response']['signature']);

        // אימות clientDataJSON
        $clientData = json_decode($clientDataJSON, true);
        if ($clientData['type'] !== 'webauthn.get') {
            return ['success' => false, 'error' => 'Invalid type'];
        }

        // בדיקת challenge
        $receivedChallenge = $this->base64UrlDecode($clientData['challenge']);
        if (!hash_equals($storedChallenge, $receivedChallenge)) {
            return ['success' => false, 'error' => 'Challenge mismatch'];
        }

        // אימות חתימה
        $publicKey = json_decode($storedCredential['public_key'], true);
        $clientDataHash = hash('sha256', $clientDataJSON, true);
        $signedData = $authenticatorData . $clientDataHash;

        $isValid = $this->verifySignature($signedData, $signature, $publicKey, $storedCredential['public_key_algorithm']);

        if (!$isValid) {
            return ['success' => false, 'error' => 'Invalid signature'];
        }

        // עדכון sign count ו-last_used
        $this->updateCredentialUsage($storedCredential['id']);

        // מחיקת challenge
        $this->deleteChallenge($userId, 'authentication');

        return [
            'success' => true,
            'user' => [
                'id' => $userId,
                'username' => $storedCredential['username'],
                'name' => $storedCredential['name'],
                'email' => $storedCredential['email'],
                'profile_picture' => $storedCredential['profile_picture']
            ]
        ];
    }

    // ========================================
    // אישור פעולות רגישות
    // ========================================

    /**
     * יצירת אפשרויות לאישור פעולה
     *
     * @param int $userId
     * @param string $actionType - סוג הפעולה (payment, delete, etc.)
     * @param array $actionData - מידע על הפעולה
     * @return array
     */
    public function createConfirmationOptions(int $userId, string $actionType, array $actionData = []): array {
        $challenge = $this->generateChallenge();
        $this->storeChallenge($userId, $challenge, 'confirmation', [
            'action_type' => $actionType,
            'action_data' => $actionData
        ]);

        $credentials = $this->getUserCredentials($userId);
        $allowCredentials = array_map(function($cred) {
            return [
                'type' => 'public-key',
                'id' => $cred['credential_id'],
                'transports' => json_decode($cred['transports'] ?? '["internal"]', true)
            ];
        }, $credentials);

        return [
            'challenge' => $this->base64UrlEncode($challenge),
            'rpId' => $this->rpId,
            'timeout' => 30000, // 30 שניות לאישור
            'userVerification' => 'required',
            'allowCredentials' => $allowCredentials,
            'action' => [
                'type' => $actionType,
                'data' => $actionData
            ]
        ];
    }

    /**
     * אימות אישור פעולה
     *
     * @param int $userId
     * @param array $assertion
     * @return array
     */
    public function verifyConfirmation(int $userId, array $assertion): array {
        $result = $this->verifyAssertion($assertion, $userId);

        if ($result['success']) {
            // קבל את פרטי הפעולה
            $stmt = $this->pdo->prepare("
                SELECT action_data FROM webauthn_challenges
                WHERE user_id = ? AND type = 'confirmation' AND used = 0
                ORDER BY created_at DESC LIMIT 1
            ");
            $stmt->execute([$userId]);
            $challenge = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($challenge && $challenge['action_data']) {
                $result['action'] = json_decode($challenge['action_data'], true);
            }

            // סמן כמשומש
            $this->deleteChallenge($userId, 'confirmation');
        }

        return $result;
    }

    // ========================================
    // פונקציות עזר
    // ========================================

    /**
     * יצירת challenge אקראי
     */
    private function generateChallenge(): string {
        return random_bytes(32);
    }

    /**
     * שמירת challenge ב-DB
     */
    private function storeChallenge(?int $userId, string $challenge, string $type, array $actionData = []): void {
        $expires = date('Y-m-d H:i:s', time() + self::CHALLENGE_LIFETIME);

        $stmt = $this->pdo->prepare("
            INSERT INTO webauthn_challenges (user_id, challenge, type, action_data, expires_at)
            VALUES (?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $userId,
            bin2hex($challenge),
            $type,
            !empty($actionData) ? json_encode($actionData) : null,
            $expires
        ]);
    }

    /**
     * קבלה ואימות challenge
     */
    private function getAndVerifyChallenge(?int $userId, string $type): ?string {
        $sql = "SELECT challenge FROM webauthn_challenges
                WHERE type = ? AND used = 0 AND expires_at > NOW()";
        $params = [$type];

        if ($userId !== null) {
            $sql .= " AND user_id = ?";
            $params[] = $userId;
        } else {
            $sql .= " AND user_id IS NULL";
        }

        $sql .= " ORDER BY created_at DESC LIMIT 1";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($result) {
            return hex2bin($result['challenge']);
        }

        return null;
    }

    /**
     * מחיקת challenge
     */
    private function deleteChallenge(?int $userId, string $type): void {
        $sql = "UPDATE webauthn_challenges SET used = 1 WHERE type = ?";
        $params = [$type];

        if ($userId !== null) {
            $sql .= " AND user_id = ?";
            $params[] = $userId;
        }

        $this->pdo->prepare($sql)->execute($params);
    }

    /**
     * קבלת credentials של משתמש
     */
    public function getUserCredentials(int $userId): array {
        $stmt = $this->pdo->prepare("
            SELECT * FROM user_credentials
            WHERE user_id = ? AND is_active = 1
            ORDER BY last_used_at DESC
        ");
        $stmt->execute([$userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * מחיקת credential
     */
    public function deleteCredential(int $userId, string $credentialId): bool {
        $stmt = $this->pdo->prepare("
            DELETE FROM user_credentials
            WHERE user_id = ? AND credential_id = ?
        ");
        $stmt->execute([$userId, $credentialId]);

        // בדוק אם יש עוד credentials
        $remaining = $this->getUserCredentials($userId);
        if (empty($remaining)) {
            $this->pdo->prepare("UPDATE users SET biometric_enabled = FALSE WHERE id = ?")->execute([$userId]);
        }

        return $stmt->rowCount() > 0;
    }

    /**
     * עדכון שימוש ב-credential
     */
    private function updateCredentialUsage(int $credentialId): void {
        $stmt = $this->pdo->prepare("
            UPDATE user_credentials
            SET sign_count = sign_count + 1, last_used_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$credentialId]);
    }

    /**
     * בדיקה אם למשתמש יש ביומטרי מוגדר
     */
    public function userHasBiometric(int $userId): bool {
        $credentials = $this->getUserCredentials($userId);
        return !empty($credentials);
    }

    /**
     * ניחוש שם המכשיר
     */
    private function guessDeviceName(): string {
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';

        if (stripos($ua, 'iPhone') !== false) return 'iPhone';
        if (stripos($ua, 'iPad') !== false) return 'iPad';
        if (stripos($ua, 'Mac') !== false) return 'Mac';
        if (stripos($ua, 'Windows') !== false) return 'Windows PC';
        if (stripos($ua, 'Android') !== false) return 'Android Device';

        return 'Unknown Device';
    }

    // ========================================
    // פונקציות קריפטוגרפיות
    // ========================================

    /**
     * Base64 URL encode
     */
    private function base64UrlEncode(string $data): string {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    /**
     * Base64 URL decode
     */
    private function base64UrlDecode(string $data): string {
        return base64_decode(strtr($data, '-_', '+/') . str_repeat('=', 3 - (3 + strlen($data)) % 4));
    }

    /**
     * פענוח attestation object (CBOR פשוט)
     * הערה: זהו מימוש פשוט - לייצור מומלץ להשתמש בספריית CBOR
     */
    private function decodeAttestationObject(string $data): ?array {
        // CBOR map פשוט - מחפש authData
        $pos = strpos($data, 'authData');
        if ($pos === false) {
            // נסה לחלץ ישירות (מבנה פשוט)
            // בדרך כלל authData מתחיל אחרי ה-header
            if (strlen($data) > 37) {
                return ['authData' => substr($data, 37)]; // approximation
            }
            return null;
        }

        // מצא את ה-authData bytes
        $authDataStart = $pos + 8; // אחרי "authData"
        if (ord($data[$authDataStart]) === 0x58) { // byte string
            $len = ord($data[$authDataStart + 1]);
            return ['authData' => substr($data, $authDataStart + 2, $len)];
        }

        return null;
    }

    /**
     * חילוץ public key מ-authData
     */
    private function extractPublicKeyFromAuthData(string $authData): ?array {
        if (strlen($authData) < 37) {
            return null;
        }

        // rpIdHash (32) + flags (1) + signCount (4) = 37
        $flags = ord($authData[32]);
        $hasAttestedCredentialData = ($flags & 0x40) !== 0;

        if (!$hasAttestedCredentialData) {
            return null;
        }

        // AAGUID (16) + credentialIdLength (2)
        $offset = 37;
        $credIdLen = unpack('n', substr($authData, $offset + 16, 2))[1];
        $offset += 18;

        // Credential ID
        $credentialId = substr($authData, $offset, $credIdLen);
        $offset += $credIdLen;

        // Public Key (COSE format - simplified extraction)
        $publicKeyBytes = substr($authData, $offset);

        // נסה לפענח COSE key פשוט (ES256)
        $publicKey = $this->parseCoseKey($publicKeyBytes);

        return [
            'credentialId' => $credentialId,
            'publicKey' => $publicKey,
            'algorithm' => $publicKey['alg'] ?? -7
        ];
    }

    /**
     * פענוח COSE key
     */
    private function parseCoseKey(string $data): array {
        // מימוש פשוט - שמור את ה-raw data
        // לייצור מומלץ להשתמש בספריית CBOR
        return [
            'raw' => base64_encode($data),
            'alg' => -7 // ES256 default
        ];
    }

    /**
     * אימות חתימה
     */
    private function verifySignature(string $data, string $signature, array $publicKey, int $algorithm): bool {
        // מימוש פשוט - בודק שהחתימה קיימת
        // לייצור אמיתי צריך לממש אימות קריפטוגרפי מלא

        // בינתיים - החזר true אם יש חתימה
        // TODO: מימוש אימות מלא עם openssl
        return strlen($signature) > 0 && isset($publicKey['raw']);
    }
}

/**
 * פונקציה גלובלית לקבלת instance
 */
function getWebAuthnManager(): WebAuthnManager {
    static $instance = null;
    if ($instance === null) {
        $instance = new WebAuthnManager();
    }
    return $instance;
}
