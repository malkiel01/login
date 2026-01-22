<?php
session_start();
require_once '../config.php';
require_once 'audit-logger.php';

// Audit Log - רישום התנתקות (לפני מחיקת הסשן)
if (isset($_SESSION['user_id'])) {
    AuditLogger::logLogout();

    // ניקוי remember_token מהמסד
    try {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("UPDATE users SET remember_token = NULL, remember_expiry = NULL WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
    } catch (Exception $e) {
        // לא קריטי - ממשיכים בהתנתקות
    }
}

// מחיקת עוגיית remember_token
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