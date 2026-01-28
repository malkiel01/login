<?php
/**
 * Web Push Implementation for PHP
 * Fixed implementation with proper ECDH encryption
 *
 * @version 2.0.0
 */

class WebPush {
    private string $publicKey;
    private string $privateKey;
    private string $subject;

    public function __construct(string $publicKey, string $privateKey, string $subject) {
        $this->publicKey = $publicKey;
        $this->privateKey = $privateKey;
        $this->subject = $subject;
    }

    /**
     * Send push notification to a subscription
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
     * Encrypt payload using aes128gcm (RFC 8291)
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
     * Compute ECDH shared secret
     * Uses openssl_pkey_derive for proper elliptic curve Diffie-Hellman
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
     * Create DER encoded EC public key
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
     * Create DER encoded EC private key
     */
    private function createECPrivateKeyDER(string $d, string $x, string $y): string {
        // ECPrivateKey structure
        $ecPoint = "\x04" . $x . $y;

        // Version
        $version = "\x02\x01\x01";

        // Private key
        $privateKey = "\x04\x20" . $d;

        // Parameters (curve OID)
        $params = "\xa0\x0a\x06\x08\x2a\x86\x48\xce\x3d\x03\x01\x07";

        // Public key
        $publicKey = "\xa1" . chr(strlen($ecPoint) + 4) .
                     "\x03" . chr(strlen($ecPoint) + 1) . "\x00" . $ecPoint;

        $inner = $version . $privateKey . $params . $publicKey;

        return "\x30" . chr(strlen($inner)) . $inner;
    }

    /**
     * HKDF Expand function
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
     * Create VAPID authorization headers
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
     * Sign data with ECDSA private key
     */
    private function signWithPrivateKey(string $data): string {
        $privateKeyBin = $this->base64UrlDecode($this->privateKey);
        $publicKeyBin = $this->base64UrlDecode($this->publicKey);

        $x = substr($publicKeyBin, 1, 32);
        $y = substr($publicKeyBin, 33, 32);

        $der = $this->createECPrivateKeyDER($privateKeyBin, $x, $y);
        $pem = "-----BEGIN EC PRIVATE KEY-----\n" .
               chunk_split(base64_encode($der), 64, "\n") .
               "-----END EC PRIVATE KEY-----\n";

        $key = openssl_pkey_get_private($pem);
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
     * Convert DER signature to raw (R || S) format
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
     * Send HTTP request to push service
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

    private function base64UrlEncode(string $data): string {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private function base64UrlDecode(string $data): string {
        $padding = strlen($data) % 4;
        if ($padding) {
            $data .= str_repeat('=', 4 - $padding);
        }
        return base64_decode(strtr($data, '-_', '+/'));
    }
}
