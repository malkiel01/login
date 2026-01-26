<?php
/**
 * WebAuthn API - נקודות קצה לאימות ביומטרי
 *
 * POST /auth/biometric/api.php
 * Actions:
 *   - check_support: בדיקת תמיכה
 *   - register_options: אפשרויות רישום
 *   - register_verify: אימות רישום
 *   - auth_options: אפשרויות אימות
 *   - auth_verify: אימות
 *   - confirm_options: אפשרויות אישור פעולה
 *   - confirm_verify: אימות אישור
 *   - list_credentials: רשימת credentials
 *   - delete_credential: מחיקת credential
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Auth-Token');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../middleware.php';
require_once __DIR__ . '/WebAuthnManager.php';

$input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
$action = $input['action'] ?? '';

$webAuthn = getWebAuthnManager();

try {
    switch ($action) {

        // ========================================
        // בדיקת תמיכה
        // ========================================
        case 'check_support':
            $userId = getCurrentUserId();
            $hasBiometric = $userId ? $webAuthn->userHasBiometric($userId) : false;

            echo json_encode([
                'success' => true,
                'has_biometric' => $hasBiometric,
                'user_id' => $userId
            ]);
            break;

        // ========================================
        // רישום - שלב 1: אפשרויות
        // ========================================
        case 'register_options':
            requireApiAuth();

            $userId = getCurrentUserId();
            $username = $_SESSION['username'] ?? '';
            $displayName = $_SESSION['name'] ?? $username;

            $options = $webAuthn->createRegistrationOptions($userId, $username, $displayName);

            echo json_encode([
                'success' => true,
                'options' => $options
            ]);
            break;

        // ========================================
        // רישום - שלב 2: אימות
        // ========================================
        case 'register_verify':
            requireApiAuth();

            $userId = getCurrentUserId();
            $credential = $input['credential'] ?? null;
            $deviceName = $input['device_name'] ?? '';

            if (!$credential) {
                throw new Exception('Credential data is required');
            }

            $result = $webAuthn->verifyAndStoreCredential($userId, $credential, $deviceName);

            echo json_encode($result);
            break;

        // ========================================
        // אימות - שלב 1: אפשרויות
        // ========================================
        case 'auth_options':
            $userId = $input['user_id'] ?? getCurrentUserId();

            $options = $webAuthn->createAuthenticationOptions($userId);

            echo json_encode([
                'success' => true,
                'options' => $options
            ]);
            break;

        // ========================================
        // אימות - שלב 2: אימות
        // ========================================
        case 'auth_verify':
            $assertion = $input['assertion'] ?? null;
            $expectedUserId = $input['user_id'] ?? null;

            if (!$assertion) {
                throw new Exception('Assertion data is required');
            }

            $result = $webAuthn->verifyAssertion($assertion, $expectedUserId);

            if ($result['success']) {
                // התחבר את המשתמש
                session_regenerate_id(true);
                $_SESSION['user_id'] = $result['user']['id'];
                $_SESSION['username'] = $result['user']['username'];
                $_SESSION['name'] = $result['user']['name'];
                $_SESSION['email'] = $result['user']['email'];
                $_SESSION['profile_picture'] = $result['user']['profile_picture'];
                $_SESSION['auth_method'] = 'biometric';

                // יצירת token עמיד
                require_once __DIR__ . '/../token-manager.php';
                $tokenManager = getTokenManager();
                $tokenData = $tokenManager->generateToken($result['user']['id'], [
                    'auth_method' => 'biometric',
                    'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
                ]);

                // הגדרת cookie
                setcookie(
                    'auth_token',
                    $tokenData['token'],
                    (int)($tokenData['expires'] / 1000),
                    '/',
                    $_SERVER['HTTP_HOST'],
                    true,
                    true
                );

                $result['token'] = $tokenData;
            }

            echo json_encode($result);
            break;

        // ========================================
        // אישור פעולה - שלב 1: אפשרויות
        // ========================================
        case 'confirm_options':
            requireApiAuth();

            $userId = getCurrentUserId();
            $actionType = $input['action_type'] ?? 'generic';
            $actionData = $input['action_data'] ?? [];

            $options = $webAuthn->createConfirmationOptions($userId, $actionType, $actionData);

            echo json_encode([
                'success' => true,
                'options' => $options
            ]);
            break;

        // ========================================
        // אישור פעולה - שלב 2: אימות
        // ========================================
        case 'confirm_verify':
            requireApiAuth();

            $userId = getCurrentUserId();
            $assertion = $input['assertion'] ?? null;

            if (!$assertion) {
                throw new Exception('Assertion data is required');
            }

            $result = $webAuthn->verifyConfirmation($userId, $assertion);

            echo json_encode($result);
            break;

        // ========================================
        // רשימת credentials
        // ========================================
        case 'list_credentials':
            requireApiAuth();

            $userId = getCurrentUserId();
            $credentials = $webAuthn->getUserCredentials($userId);

            // הסר מידע רגיש
            $safeCredentials = array_map(function($c) {
                return [
                    'id' => $c['credential_id'],
                    'device_name' => $c['device_name'],
                    'created_at' => $c['created_at'],
                    'last_used_at' => $c['last_used_at']
                ];
            }, $credentials);

            echo json_encode([
                'success' => true,
                'credentials' => $safeCredentials
            ]);
            break;

        // ========================================
        // מחיקת credential
        // ========================================
        case 'delete_credential':
            requireApiAuth();

            $userId = getCurrentUserId();
            $credentialId = $input['credential_id'] ?? '';

            if (empty($credentialId)) {
                throw new Exception('Credential ID is required');
            }

            $deleted = $webAuthn->deleteCredential($userId, $credentialId);

            echo json_encode([
                'success' => $deleted,
                'message' => $deleted ? 'Credential deleted' : 'Credential not found'
            ]);
            break;

        // ========================================
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
