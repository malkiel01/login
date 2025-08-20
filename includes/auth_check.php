<?php
/**
 * בדיקת הרשאות והגנה על דפים
 * includes/auth_check.php
 * 
 * יש לכלול את הקובץ הזה בראש כל דף מוגן
 */

// מנע גישה ישירה לקובץ
if (basename($_SERVER['PHP_SELF']) == basename(__FILE__)) {
    header('Location: ../index.php');
    exit;
}

// בדיקה שהבקשה הגיעה מהשרת ולא ישירות
function isDirectAccess() {
    // בדוק אם יש referrer מהאתר שלנו
    $allowed_referrers = [
        $_SERVER['HTTP_HOST'],
        'localhost'
    ];
    
    if (isset($_SERVER['HTTP_REFERER'])) {
        $referrer_host = parse_url($_SERVER['HTTP_REFERER'], PHP_URL_HOST);
        if (!in_array($referrer_host, $allowed_referrers)) {
            return true;
        }
    }
    
    return false;
}

// בדיקת סשן
if (!isset($_SESSION)) {
    session_start();
}

// בדיקה אם המשתמש מחובר
if (!isset($_SESSION['user_id'])) {
    header('Location: auth/login.php');
    exit;
}

// בדיקת timeout של סשן (30 דקות)
$timeout = 1800; // 30 minutes
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $timeout)) {
    session_unset();
    session_destroy();
    header('Location: auth/login.php?timeout=1');
    exit;
}
$_SESSION['last_activity'] = time();

// הגנה מפני Session Hijacking
if (!isset($_SESSION['user_agent'])) {
    $_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT'];
} else {
    if ($_SESSION['user_agent'] !== $_SERVER['HTTP_USER_AGENT']) {
        session_unset();
        session_destroy();
        header('Location: auth/login.php?error=security');
        exit;
    }
}

// הגנה מפני CSRF בפעולות POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_SESSION['csrf_token']) || !isset($_POST['csrf_token']) || $_SESSION['csrf_token'] !== $_POST['csrf_token']) {
        // בקשות AJAX
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Invalid security token']);
            exit;
        }
        // בקשות רגילות
        die('Invalid security token');
    }
}

// יצירת CSRF token אם לא קיים
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// פונקציה להדפסת ה-token בטפסים
function csrf_field() {
    return '<input type="hidden" name="csrf_token" value="' . $_SESSION['csrf_token'] . '">';
}

// פונקציה לבדיקת הרשאת מנהל קבוצה
function checkGroupOwnership($pdo, $group_id, $user_id) {
    $stmt = $pdo->prepare("SELECT owner_id FROM purchase_groups WHERE id = ? AND is_active = 1");
    $stmt->execute([$group_id]);
    $group = $stmt->fetch();
    
    return $group && $group['owner_id'] == $user_id;
}

// פונקציה לבדיקת חברות בקבוצה
function checkGroupMembership($pdo, $group_id, $user_id) {
    $stmt = $pdo->prepare("
        SELECT id FROM group_members 
        WHERE group_id = ? AND user_id = ? AND is_active = 1
    ");
    $stmt->execute([$group_id, $user_id]);
    return $stmt->fetch() !== false;
}
?>