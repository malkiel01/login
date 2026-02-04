<?php
/**
 * דף אינדקס - הפניה אוטומטית לדשבורד
 * index.php
 *
 * משתמש ב-location.replace() כדי לא להוסיף להיסטוריה
 * (חשוב במיוחד ל-PWA - זה ה-start_url)
*/

session_start();

// בדוק אם יש redirect_to בפרמטרים
if (isset($_GET['redirect_to'])) {
    $_SESSION['redirect_after_login'] = $_GET['redirect_to'];
}

// פונקציית redirect עם location.replace
function jsRedirect($url) {
    echo '<!DOCTYPE html><html><head><meta charset="UTF-8">';
    echo '<script>location.replace("' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '");</script>';
    echo '</head><body></body></html>';
    exit;
}

// בדוק אם המשתמש מחובר
if (isset($_SESSION['user_id'])) {
    // המשתמש מחובר
    if (isset($_SESSION['redirect_after_login'])) {
        // יש redirect - נווט אליו
        $redirect = $_SESSION['redirect_after_login'];
        unset($_SESSION['redirect_after_login']);
        jsRedirect($redirect);
    } else {
        // אין redirect - לדשבורד
        jsRedirect('/dashboard/index.php');
    }
} else {
    // המשתמש לא מחובר
    if (isset($_SESSION['redirect_after_login'])) {
        // העבר את ה-redirect ללוגין
        $redirect = $_SESSION['redirect_after_login'];
        jsRedirect('/auth/login.php?redirect_to=' . urlencode($redirect));
    } else {
        // ללוגין רגיל
        jsRedirect('/auth/login.php');
    }
}
?>
