<?php
/**
 * Token API - נקודות קצה לניהול tokens
 *
 * POST /auth/api/token-api.php
 * Actions:
 *   - validate: אימות token
 *   - refresh: רענון token
 *   - revoke: ביטול token
 *   - sessions: רשימת sessions פעילים
 */

header('Content-Type: application/json; charset=utf-8');

// CORS for PWA
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Auth-Token');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../token-manager.php';

// קבלת הנתונים
$input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
$action = $input['action'] ?? '';

$tokenManager = getTokenManager();

try {
    switch ($action) {

        // אימות token
        case 'validate':
            $token = $input['token'] ?? getTokenFromHeader();

            if (empty($token)) {
                throw new Exception('Token is required');
            }

            $userData = $tokenManager->validateToken($token);

            if (!$userData) {
                http_response_code(401);
                echo json_encode([
                    'success' => false,
                    'error' => 'invalid_token',
                    'message' => 'Token is invalid or expired'
                ]);
                exit;
            }

            // אם צריך לרענן, שלח flag
            echo json_encode([
                'success' => true,
                'user' => [
                    'id' => $userData['user_id'],
                    'username' => $userData['username'],
                    'name' => $userData['name'],
                    'email' => $userData['email'],
                    'profile_picture' => $userData['profile_picture']
                ],
                'should_refresh' => $userData['should_refresh'],
                'expires_at' => $userData['expires_at']
            ]);
            break;

        // רענון token
        case 'refresh':
            $refreshToken = $input['refresh_token'] ?? '';

            if (empty($refreshToken)) {
                throw new Exception('Refresh token is required');
            }

            $newTokens = $tokenManager->refreshToken($refreshToken);

            if (!$newTokens) {
                http_response_code(401);
                echo json_encode([
                    'success' => false,
                    'error' => 'invalid_refresh_token',
                    'message' => 'Refresh token is invalid or expired'
                ]);
                exit;
            }

            // הגדר cookie חדש
            setTokenCookie($newTokens['token'], $newTokens['expires']);

            echo json_encode([
                'success' => true,
                'token' => $newTokens['token'],
                'refresh_token' => $newTokens['refresh_token'],
                'expires' => $newTokens['expires'],
                'expires_at' => $newTokens['expires_at']
            ]);
            break;

        // ביטול token (התנתקות)
        case 'revoke':
            $token = $input['token'] ?? getTokenFromHeader();

            if (!empty($token)) {
                $userData = $tokenManager->validateToken($token);
                if ($userData) {
                    $tokenManager->revokeToken($userData['token_id']);
                }
            }

            // מחק cookie
            setcookie('auth_token', '', time() - 3600, '/', '', true, true);

            echo json_encode([
                'success' => true,
                'message' => 'Token revoked'
            ]);
            break;

        // ביטול כל ה-sessions
        case 'revoke_all':
            $token = $input['token'] ?? getTokenFromHeader();

            if (empty($token)) {
                throw new Exception('Token is required');
            }

            $userData = $tokenManager->validateToken($token);

            if (!$userData) {
                http_response_code(401);
                throw new Exception('Invalid token');
            }

            $tokenManager->revokeAllUserTokens($userData['user_id']);

            echo json_encode([
                'success' => true,
                'message' => 'All sessions revoked'
            ]);
            break;

        // רשימת sessions פעילים
        case 'sessions':
            $token = $input['token'] ?? getTokenFromHeader();

            if (empty($token)) {
                throw new Exception('Token is required');
            }

            $userData = $tokenManager->validateToken($token);

            if (!$userData) {
                http_response_code(401);
                throw new Exception('Invalid token');
            }

            $sessions = $tokenManager->getUserSessions($userData['user_id']);

            echo json_encode([
                'success' => true,
                'sessions' => $sessions,
                'current_token_id' => $userData['token_id']
            ]);
            break;

        default:
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => 'invalid_action',
                'message' => 'Invalid action'
            ]);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'server_error',
        'message' => $e->getMessage()
    ]);
}

/**
 * קבלת token מ-header
 */
function getTokenFromHeader(): ?string {
    $headers = getallheaders();

    // Authorization: Bearer <token>
    if (isset($headers['Authorization'])) {
        if (preg_match('/Bearer\s+(.+)/', $headers['Authorization'], $matches)) {
            return $matches[1];
        }
    }

    // X-Auth-Token: <token>
    if (isset($headers['X-Auth-Token'])) {
        return $headers['X-Auth-Token'];
    }

    // Cookie
    if (isset($_COOKIE['auth_token'])) {
        return $_COOKIE['auth_token'];
    }

    return null;
}

/**
 * הגדרת cookie לtoken
 */
function setTokenCookie(string $token, int $expiresMs): void {
    $expires = (int)($expiresMs / 1000); // המרה משניות
    setcookie(
        'auth_token',
        $token,
        [
            'expires' => $expires,
            'path' => '/',
            'domain' => $_SERVER['HTTP_HOST'] ?? '',
            'secure' => true,
            'httponly' => true,
            'samesite' => 'Lax'
        ]
    );
}
