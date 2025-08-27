<?php
/**
 * דף אינדקס - הפניה אוטומטית לדשבורד
 * index.php
*/

session_start();

// בדוק אם יש redirect_to בפרמטרים
if (isset($_GET['redirect_to'])) {
    $_SESSION['redirect_after_login'] = $_GET['redirect_to'];
}

// בדוק אם המשתמש מחובר
if (isset($_SESSION['user_id'])) {
    // המשתמש מחובר
    if (isset($_SESSION['redirect_after_login'])) {
        // יש redirect - נווט אליו
        $redirect = $_SESSION['redirect_after_login'];
        unset($_SESSION['redirect_after_login']);
        header('Location: ' . $redirect);
    } else {
        // אין redirect - לדשבורד
        header('Location: /dashboard/index.php');
    }
} else {
    // המשתמש לא מחובר
    if (isset($_SESSION['redirect_after_login'])) {
        // העבר את ה-redirect ללוגין
        $redirect = $_SESSION['redirect_after_login'];
        header('Location: /auth/login.php?redirect_to=' . urlencode($redirect));
    } else {
        // ללוגין רגיל
        header('Location: /auth/login.php');
    }
}
exit;
?>