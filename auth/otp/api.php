<?php
/**
 * OTP API - נקודות קצה לאימות SMS
 *
 * POST /auth/otp/api.php
 * Actions:
 *   - send: שליחת קוד
 *   - verify: אימות קוד
 *   - status: בדיקת סטטוס
 *   - resend: שליחה מחדש
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
require_once __DIR__ . '/OTPManager.php';
require_once __DIR__ . '/../../push/push-log.php';

$input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
$action = $input['action'] ?? '';

$otpManager = getOTPManager();

try {
    switch ($action) {

        // ========================================
        // שליחת קוד
        // ========================================
        case 'send':
            $phone = $input['phone'] ?? '';
            $purpose = $input['purpose'] ?? 'verification';
            $userId = getCurrentUserId(); // null אם לא מחובר
            $actionData = $input['action_data'] ?? [];

            if (empty($phone)) {
                throw new Exception('Phone number is required');
            }

            // בדוק אם צריך להיות מחובר
            if (in_array($purpose, ['verification', 'action_confirm']) && !$userId) {
                http_response_code(401);
                throw new Exception('Authentication required');
            }

            $result = $otpManager->sendOTP($phone, $purpose, $userId, $actionData);

            echo json_encode($result);
            break;

        // ========================================
        // אימות קוד
        // ========================================
        case 'verify':
            $phone = $input['phone'] ?? '';
            $code = $input['code'] ?? '';
            $purpose = $input['purpose'] ?? 'verification';

            if (empty($phone) || empty($code)) {
                throw new Exception('Phone and code are required');
            }

            $result = $otpManager->verifyOTP($phone, $code, $purpose);

            if ($result['success']) {
                // אם זה login, התחבר את המשתמש
                if ($purpose === 'login' && $result['user_id']) {
                    $pdo = getDBConnection();
                    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND is_active = 1");
                    $stmt->execute([$result['user_id']]);
                    $user = $stmt->fetch(PDO::FETCH_ASSOC);

                    if ($user) {
                        session_regenerate_id(true);
                        $_SESSION['user_id'] = $user['id'];
                        $_SESSION['username'] = $user['username'];
                        $_SESSION['name'] = $user['name'];
                        $_SESSION['email'] = $user['email'];
                        $_SESSION['profile_picture'] = $user['profile_picture'];
                        $_SESSION['auth_method'] = 'sms';

                        // יצירת token עמיד
                        require_once __DIR__ . '/../token-manager.php';
                        $tokenManager = getTokenManager();
                        $tokenData = $tokenManager->generateToken($user['id'], [
                            'auth_method' => 'sms',
                            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
                        ]);

                        setcookie('auth_token', $tokenData['token'],
                            (int)($tokenData['expires'] / 1000), '/',
                            $_SERVER['HTTP_HOST'], true, true);

                        $result['token'] = $tokenData;
                        $result['user'] = [
                            'id' => $user['id'],
                            'name' => $user['name'],
                            'username' => $user['username']
                        ];

                        // Push Log - רישום התחברות OTP עם מידע על המכשיר
                        logUserLogin($user['id'], $user['name'] ?? $user['username']);
                    }
                }
            }

            echo json_encode($result);
            break;

        // ========================================
        // בדיקת סטטוס
        // ========================================
        case 'status':
            $phone = $input['phone'] ?? '';
            $purpose = $input['purpose'] ?? 'verification';

            if (empty($phone)) {
                throw new Exception('Phone number is required');
            }

            $hasActive = $otpManager->hasActiveOTP($phone, $purpose);
            $timeUntilNext = $otpManager->getTimeUntilNextSend($phone);

            echo json_encode([
                'success' => true,
                'has_active_code' => $hasActive,
                'can_resend' => $timeUntilNext === 0,
                'resend_in' => $timeUntilNext
            ]);
            break;

        // ========================================
        // שליחה מחדש
        // ========================================
        case 'resend':
            $phone = $input['phone'] ?? '';
            $purpose = $input['purpose'] ?? 'verification';
            $userId = getCurrentUserId();

            if (empty($phone)) {
                throw new Exception('Phone number is required');
            }

            // בדוק rate limit
            $timeUntilNext = $otpManager->getTimeUntilNextSend($phone);
            if ($timeUntilNext > 0) {
                echo json_encode([
                    'success' => false,
                    'error' => 'Please wait before resending',
                    'resend_in' => $timeUntilNext
                ]);
                break;
            }

            $result = $otpManager->sendOTP($phone, $purpose, $userId);
            echo json_encode($result);
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
