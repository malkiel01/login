<?php
/**
 * WebPush - Web Push Protocol Implementation for PHP
 *
 * This class implements the Web Push protocol (RFC 8030) with VAPID authentication
 * (RFC 8292) and aes128gcm content encryption (RFC 8291).
 *
 * PROTOCOL OVERVIEW:
 * Web Push allows servers to send messages to web applications via push services
 * (like FCM for Chrome, Mozilla Push Service for Firefox). Messages are encrypted
 * end-to-end so push services cannot read the content.
 *
 * ENCRYPTION DETAILS:
 * - Uses ECDH (Elliptic Curve Diffie-Hellman) for key agreement
 * - AES-128-GCM for payload encryption
 * - HKDF (HMAC-based Key Derivation Function) for key derivation
 * - P-256 curve (prime256v1) for all EC operations
 *
 * VAPID AUTHENTICATION:
 * - VAPID (Voluntary Application Server Identification) proves server identity
 * - Uses ES256 (ECDSA with P-256 and SHA-256) for JWT signing
 * - Allows push services to identify and potentially rate-limit senders
 *
 * REQUIREMENTS:
 * - PHP 7.3+ (for openssl_pkey_derive)
 * - OpenSSL extension with EC support
 * - cURL extension
 *
 * USAGE:
 * ```php
 * $webPush = new WebPush($publicKey, $privateKeyPem, 'mailto:admin@example.com');
 * $result = $webPush->send($subscription, json_encode(['title' => 'Hello']));
 * ```
 *
 * @package     Notifications
 * @subpackage  Core
 * @version     2.0.0
 * @since       1.0.0
 * @link        https://tools.ietf.org/html/rfc8030 Web Push Protocol
 * @link        https://tools.ietf.org/html/rfc8291 Message Encryption
 * @link        https://tools.ietf.org/html/rfc8292 VAPID
 */

/**
 * WebPush - Sends encrypted push notifications via Web Push protocol
 *
 * This class handles all aspects of Web Push message delivery:
 * - Payload encryption using aes128gcm
 * - VAPID JWT generation and signing
 * - HTTP request to push service endpoint
 * - Response handling and error reporting
 *
 * @package Notifications
 * @since   1.0.0
 */
class WebPush {
    /** @var string Base64url-encoded VAPID public key */
    private string $publicKey;

    /** @var string PEM-formatted ECDSA private key for VAPID signing */
    private string $privateKeyPem;

    /** @var string VAPID subject (mailto: or https: URL) */
    private string $subject;

    /**
     * Constructor - Initialize WebPush with VAPID credentials
     *
     * @param string $publicKey     Base64url-encoded VAPID public key (65 bytes uncompressed)
     * @param string $privateKeyPem PEM-formatted EC private key for signing
     * @param string $subject       VAPID subject URL (mailto:email or https://domain)
     *
     * @example
     * ```php
     * $webPush = new WebPush(
     *     VAPID_PUBLIC_KEY,      // From push/config.php
     *     VAPID_PRIVATE_KEY_PEM, // PEM format private key
     *     'mailto:admin@example.com'
     * );
     * ```
     */
    public function __construct(string $publicKey, string $privateKeyPem, string $subject) {
        $this->publicKey = $publicKey;
        $this->privateKeyPem = $privateKeyPem;
        $this->subject = $subject;
    }

    /**
     * Send push notification to a subscription endpoint
     *
     * Main public method for sending a push notification. Encrypts the payload,
     * generates VAPID headers, and sends the HTTP request to the push service.
     *
     * @param array  $subscription Push subscription data with keys:
     *                             - endpoint: string - Push service URL
     *                             - p256dh_key or keys.p256dh: string - User public key (base64url)
     *                             - auth_key or keys.auth: string - Auth secret (base64url)
     * @param string $payload      JSON-encoded notification data
     *
     * @return array Result with keys:
     *               - success: bool - true if HTTP 2xx response
     *               - httpCode: int - HTTP response code
     *               - response: string - Response body
     *               - error: string|null - Error message if failed
     *
     * @example
     * ```php
     * $result = $webPush->send([
     *     'endpoint' => 'https://fcm.googleapis.com/...',
     *     'p256dh_key' => 'BNc...',
     *     'auth_key' => 'xyz...'
     * ], json_encode(['title' => 'Hello', 'body' => 'World']));
     * ```
     */
    public function send(array $subscription, string $payload): array {
        $endpoint = $subscription['endpoint'];
        $p256dh = $subscription['p256dh_key'] ?? $subscription['keys']['p256dh'] ?? '';
        $auth = $subscription['auth_key'] ?? $subscription['keys']['auth'] ?? '';

        if (empty($endpoint) || empty($p256dh) || empty($auth)) {
            return ['success' => false, 'error' => 'Invalid subscription data'];
        }

        try {
            // Encrypt the payload
            $encrypted = $this->encrypt($payload, $p256dh, $auth);

            if (!$encrypted) {
                return ['success' => false, 'error' => 'Encryption failed'];
            }

            // Create VAPID headers
            $vapidHeaders = $this->createVapidHeaders($endpoint);

            // Send the request
            $result = $this->sendRequest($endpoint, $encrypted, $vapidHeaders);

            return $result;
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Encrypt payload using aes128gcm content encoding (RFC 8291)
     *
     * Implements the Web Push message encryption scheme:
     * 1. Generate ephemeral ECDH key pair
     * 2. Compute shared secret via ECDH with user's public key
     * 3. Derive content encryption key (CEK) and nonce using HKDF
     * 4. Encrypt payload with AES-128-GCM
     * 5. Format as aes128gcm content encoding
     *
     * @param string $payload       Plaintext payload to encrypt
     * @param string $userPublicKey Base64url-encoded user public key (p256dh)
     * @param string $userAuth      Base64url-encoded auth secret
     *
     * @return array|null Encrypted data with keys:
     *                    - body: string - Complete encrypted body with headers
     *                    - contentLength: int - Total body length
     *                    Returns null on encryption failure
     */
    private function encrypt(string $payload, string $userPublicKey, string $userAuth): ?array {
        // Decode user keys from base64url
        $uaPublic = $this->base64UrlDecode($userPublicKey);
        $uaAuth = $this->base64UrlDecode($userAuth);

        if (strlen($uaPublic) !== 65 || $uaPublic[0] !== "\x04") {
            error_log("[WebPush] Invalid user public key format");
            return null;
        }

        // Generate local ephemeral key pair
        $localKey = openssl_pkey_new([
            'curve_name' => 'prime256v1',
            'private_key_type' => OPENSSL_KEYTYPE_EC
        ]);

        if (!$localKey) {
            error_log("[WebPush] Failed to generate local key");
            return null;
        }

        $localDetails = openssl_pkey_get_details($localKey);
        $localPublic = "\x04" . $localDetails['ec']['x'] . $localDetails['ec']['y'];

        // Generate salt (16 random bytes)
        $salt = random_bytes(16);

        // Compute shared secret using ECDH
        // Pass the key resource directly (not raw bytes)
        $sharedSecret = $this->computeECDH($localKey, $uaPublic);

        if (!$sharedSecret) {
            error_log("[WebPush] ECDH computation failed");
            return null;
        }

        // Key derivation (RFC 8291)
        // IKM = ECDH(as_private, ua_public)
        // salt = auth_secret
        // info = "WebPush: info" || 0x00 || ua_public || as_public
        $ikm = $sharedSecret;
        $keyInfo = "WebPush: info\x00" . $uaPublic . $localPublic;

        // PRK = HKDF-Extract(auth_secret, ikm)
        $prk = hash_hmac('sha256', $ikm, $uaAuth, true);

        // IKM for content encryption = HKDF-Expand(PRK, keyInfo, 32)
        $ikm2 = $this->hkdfExpand($prk, $keyInfo, 32);

        // Content encryption key and nonce derivation
        // CEK = HKDF(salt, IKM, "Content-Encoding: aes128gcm" || 0x00, 16)
        // Nonce = HKDF(salt, IKM, "Content-Encoding: nonce" || 0x00, 12)
        $prk2 = hash_hmac('sha256', $ikm2, $salt, true);

        $cekInfo = "Content-Encoding: aes128gcm\x00";
        $nonceInfo = "Content-Encoding: nonce\x00";

        $cek = $this->hkdfExpand($prk2, $cekInfo, 16);
        $nonce = $this->hkdfExpand($prk2, $nonceInfo, 12);

        // Add padding and delimiter (RFC 8291)
        $paddedPayload = $payload . "\x02";

        // Encrypt with AES-128-GCM
        $tag = '';
        $ciphertext = openssl_encrypt(
            $paddedPayload,
            'aes-128-gcm',
            $cek,
            OPENSSL_RAW_DATA,
            $nonce,
            $tag,
            '',
            16
        );

        if ($ciphertext === false) {
            error_log("[WebPush] AES-GCM encryption failed");
            return null;
        }

        // Build the encrypted content (aes128gcm format)
        // Header: salt (16) + rs (4) + idlen (1) + keyid (65)
        $recordSize = 4096;
        $header = $salt .
                  pack('N', $recordSize) .
                  chr(65) .
                  $localPublic;

        $body = $header . $ciphertext . $tag;

        return [
            'body' => $body,
            'contentLength' => strlen($body)
        ];
    }

    /**
     * Compute ECDH shared secret between local and peer keys
     *
     * Performs Elliptic Curve Diffie-Hellman key agreement using P-256 curve.
     * Uses openssl_pkey_derive (PHP 7.3+) for the actual ECDH operation.
     *
     * @param resource $localKeyResource OpenSSL key resource (local ephemeral key)
     * @param string   $peerPublicKey    Raw peer public key (65 bytes, uncompressed)
     *
     * @return string|null 32-byte shared secret, or null on failure
     */
    private function computeECDH($localKeyResource, string $peerPublicKey): ?string {
        // Extract x,y from peer public key (uncompressed format: 0x04 || x || y)
        $peerX = substr($peerPublicKey, 1, 32);
        $peerY = substr($peerPublicKey, 33, 32);

        // Create PEM for peer public key using proper DER encoding
        $peerDer = $this->createECPublicKeyDER($peerX, $peerY);
        $peerPem = "-----BEGIN PUBLIC KEY-----\n" .
                   chunk_split(base64_encode($peerDer), 64, "\n") .
                   "-----END PUBLIC KEY-----\n";

        $peerKey = openssl_pkey_get_public($peerPem);

        if (!$peerKey) {
            error_log("[WebPush] Failed to load peer public key: " . openssl_error_string());
            return null;
        }

        // Use openssl_pkey_derive for ECDH (PHP 7.3+)
        if (function_exists('openssl_pkey_derive')) {
            $sharedSecret = openssl_pkey_derive($peerKey, $localKeyResource, 32);
            if ($sharedSecret !== false) {
                return $sharedSecret;
            }
            error_log("[WebPush] openssl_pkey_derive failed: " . openssl_error_string());
        } else {
            error_log("[WebPush] openssl_pkey_derive not available (requires PHP 7.3+)");
        }

        return null;
    }

    /**
     * Create DER-encoded SubjectPublicKeyInfo for EC public key
     *
     * Builds ASN.1 DER structure for an EC public key on the P-256 curve.
     * This is needed to convert raw key bytes to PEM format for OpenSSL.
     *
     * @param string $x 32-byte X coordinate of EC point
     * @param string $y 32-byte Y coordinate of EC point
     *
     * @return string DER-encoded public key
     */
    private function createECPublicKeyDER(string $x, string $y): string {
        // SubjectPublicKeyInfo for EC key with P-256
        $ecPoint = "\x04" . $x . $y; // Uncompressed point

        // Build ASN.1 structure
        $algorithmOid = "\x06\x07\x2a\x86\x48\xce\x3d\x02\x01"; // ecPublicKey
        $curveOid = "\x06\x08\x2a\x86\x48\xce\x3d\x03\x01\x07"; // prime256v1

        $algorithmId = "\x30" . chr(strlen($algorithmOid) + strlen($curveOid)) .
                       $algorithmOid . $curveOid;

        $bitString = "\x03" . chr(strlen($ecPoint) + 1) . "\x00" . $ecPoint;

        $total = $algorithmId . $bitString;

        return "\x30" . chr(strlen($total)) . $total;
    }

    /**
     * HKDF-Expand function (RFC 5869)
     *
     * Expands a pseudorandom key (PRK) into output keying material (OKM)
     * of the specified length using HMAC-SHA256.
     *
     * @param string $prk    Pseudorandom key (from HKDF-Extract)
     * @param string $info   Context and application specific info
     * @param int    $length Desired output length in bytes
     *
     * @return string Output keying material of specified length
     */
    private function hkdfExpand(string $prk, string $info, int $length): string {
        $output = '';
        $previous = '';
        $counter = 1;

        while (strlen($output) < $length) {
            $previous = hash_hmac('sha256', $previous . $info . chr($counter), $prk, true);
            $output .= $previous;
            $counter++;
        }

        return substr($output, 0, $length);
    }

    /**
     * Create VAPID authorization headers for push request
     *
     * Generates a signed JWT token for VAPID authentication.
     * The JWT contains audience (push service origin), expiration, and subject.
     *
     * VAPID Header Format:
     * Authorization: vapid t=<JWT>, k=<public-key>
     *
     * @param string $endpoint Push service endpoint URL
     *
     * @return array Headers array with 'Authorization' key
     *
     * @link https://tools.ietf.org/html/rfc8292 VAPID Specification
     */
    private function createVapidHeaders(string $endpoint): array {
        $parsedUrl = parse_url($endpoint);
        $audience = $parsedUrl['scheme'] . '://' . $parsedUrl['host'];

        // Create JWT
        $header = ['typ' => 'JWT', 'alg' => 'ES256'];
        $payload = [
            'aud' => $audience,
            'exp' => time() + 86400,
            'sub' => $this->subject
        ];

        $headerEncoded = $this->base64UrlEncode(json_encode($header));
        $payloadEncoded = $this->base64UrlEncode(json_encode($payload));

        $dataToSign = $headerEncoded . '.' . $payloadEncoded;
        $signature = $this->signWithPrivateKey($dataToSign);

        $jwt = $dataToSign . '.' . $this->base64UrlEncode($signature);

        return [
            'Authorization' => 'vapid t=' . $jwt . ', k=' . $this->publicKey,
        ];
    }

    /**
     * Sign data using ECDSA with P-256 curve (ES256)
     *
     * Signs the input data using the VAPID private key.
     * The signature is converted from DER to raw R||S format for JWT.
     *
     * @param string $data Data to sign (typically JWT header.payload)
     *
     * @return string 64-byte raw signature (R || S)
     *
     * @throws Exception If private key loading or signing fails
     */
    private function signWithPrivateKey(string $data): string {
        $key = openssl_pkey_get_private($this->privateKeyPem);
        if (!$key) {
            throw new Exception('Failed to load private key: ' . openssl_error_string());
        }

        $signature = '';
        if (!openssl_sign($data, $signature, $key, OPENSSL_ALGO_SHA256)) {
            throw new Exception('Failed to sign: ' . openssl_error_string());
        }

        return $this->derToRaw($signature);
    }

    /**
     * Convert DER-encoded ECDSA signature to raw R||S format
     *
     * OpenSSL produces DER-encoded signatures, but JWT requires
     * the raw concatenation of R and S values (32 bytes each).
     *
     * @param string $der DER-encoded signature
     *
     * @return string 64-byte raw signature (R || S)
     *
     * @throws Exception If DER structure is invalid
     */
    private function derToRaw(string $der): string {
        // Parse DER SEQUENCE
        if ($der[0] !== "\x30") {
            throw new Exception('Invalid DER sequence');
        }

        $pos = 2; // Skip SEQUENCE tag and length

        // Parse R INTEGER
        if ($der[$pos] !== "\x02") {
            throw new Exception('Invalid R integer tag');
        }
        $pos++;
        $rLen = ord($der[$pos++]);
        $r = substr($der, $pos, $rLen);
        $pos += $rLen;

        // Parse S INTEGER
        if ($der[$pos] !== "\x02") {
            throw new Exception('Invalid S integer tag');
        }
        $pos++;
        $sLen = ord($der[$pos++]);
        $s = substr($der, $pos, $sLen);

        // Remove leading zeros and pad to 32 bytes
        $r = str_pad(ltrim($r, "\x00"), 32, "\x00", STR_PAD_LEFT);
        $s = str_pad(ltrim($s, "\x00"), 32, "\x00", STR_PAD_LEFT);

        return substr($r, -32) . substr($s, -32);
    }

    /**
     * Send HTTP POST request to push service endpoint
     *
     * Sends the encrypted payload to the push service with proper headers.
     * Uses cURL for the HTTP request with SSL verification enabled.
     *
     * HTTP Headers Sent:
     * - Content-Type: application/octet-stream
     * - Content-Encoding: aes128gcm
     * - TTL: 86400 (24 hours)
     * - Urgency: high
     * - Authorization: VAPID token
     *
     * @param string $endpoint     Push service URL
     * @param array  $encrypted    Encrypted data from encrypt()
     * @param array  $vapidHeaders VAPID Authorization headers
     *
     * @return array Result with keys:
     *               - success: bool - true if HTTP 2xx
     *               - httpCode: int - HTTP response code
     *               - response: string - Response body
     *               - error: string|null - Error message
     */
    private function sendRequest(string $endpoint, array $encrypted, array $vapidHeaders): array {
        $headers = [
            'Content-Type: application/octet-stream',
            'Content-Encoding: aes128gcm',
            'Content-Length: ' . $encrypted['contentLength'],
            'TTL: 86400',
            'Urgency: high'
        ];

        foreach ($vapidHeaders as $name => $value) {
            $headers[] = "$name: $value";
        }

        $ch = curl_init($endpoint);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $encrypted['body'],
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_VERBOSE => false
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            return ['success' => false, 'error' => 'CURL: ' . $curlError, 'httpCode' => 0];
        }

        // Success codes: 201 (Created), 200 (OK), 202 (Accepted)
        $success = $httpCode >= 200 && $httpCode < 300;

        return [
            'success' => $success,
            'httpCode' => $httpCode,
            'response' => $response,
            'error' => $success ? null : "HTTP $httpCode: $response"
        ];
    }

    /**
     * Encode data using base64url encoding
     *
     * Base64url is a URL-safe variant of base64 used in JWT and Web Push.
     * Replaces + with -, / with _, and removes trailing = padding.
     *
     * @param string $data Raw binary data to encode
     *
     * @return string Base64url-encoded string
     */
    private function base64UrlEncode(string $data): string {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    /**
     * Decode base64url-encoded data
     *
     * Reverses base64url encoding by restoring standard base64 characters
     * and adding padding before decoding.
     *
     * @param string $data Base64url-encoded string
     *
     * @return string Decoded binary data
     */
    private function base64UrlDecode(string $data): string {
        $padding = strlen($data) % 4;
        if ($padding) {
            $data .= str_repeat('=', 4 - $padding);
        }
        return base64_decode(strtr($data, '-_', '+/'));
    }
}
