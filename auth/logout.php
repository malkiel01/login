<?php
session_start();
require_once '../config.php';
require_once 'audit-logger.php';
require_once 'token-manager.php';

// Audit Log - רישום התנתקות (לפני מחיקת הסשן)
if (isset($_SESSION['user_id'])) {
    AuditLogger::logLogout();

    // ביטול token עמיד (חדש)
    try {
        $tokenManager = getTokenManager();
        $authToken = $_COOKIE['auth_token'] ?? null;

        if ($authToken) {
            $userData = $tokenManager->validateToken($authToken);
            if ($userData) {
                $tokenManager->revokeToken($userData['token_id']);
            }
        }
    } catch (Exception $e) {
        // לא קריטי - ממשיכים בהתנתקות
        error_log("Token revoke error: " . $e->getMessage());
    }

    // ניקוי remember_token מהמסד (תאימות אחורה)
    try {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("UPDATE users SET remember_token = NULL, remember_expiry = NULL WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
    } catch (Exception $e) {
        // לא קריטי - ממשיכים בהתנתקות
    }
}

// מחיקת עוגיית auth_token (חדש)
if (isset($_COOKIE['auth_token'])) {
    setcookie('auth_token', '', time() - 3600, '/', $_SERVER['HTTP_HOST'], true, true);
}

// מחיקת עוגיית remember_token (תאימות אחורה)
if (isset($_COOKIE['remember_token'])) {
    setcookie('remember_token', '', time() - 3600, '/', $_SERVER['HTTP_HOST'], true, true);
}

// ניקוי כל משתני הסשן
$_SESSION = array();

// מחיקת עוגיית הסשן
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// השמדת הסשן
session_destroy();

// הפניה לדף ההתחברות
header("Location: login.php");
exit;
?>