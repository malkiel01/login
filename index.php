<?php
/**
 * דף אינדקס - הפניה אוטומטית לדשבורד
 * index.php
 *
 * משתמש ב-location.replace() כדי לא להוסיף להיסטוריה
 * (חשוב במיוחד ל-PWA - זה ה-start_url)
*/

session_start();

// מניעת קאש - חשוב ל-PWA!
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: Thu, 01 Jan 1970 00:00:00 GMT');

// בדוק אם יש redirect_to בפרמטרים
if (isset($_GET['redirect_to'])) {
    $_SESSION['redirect_after_login'] = $_GET['redirect_to'];
}

// פונקציית redirect עם location.replace + דיבוג
function jsRedirect($url) {
    $safeUrl = htmlspecialchars($url, ENT_QUOTES, 'UTF-8');
    echo '<!DOCTYPE html><html><head><meta charset="UTF-8">';
    echo '<script>
        // DEBUG: Log before redirect
        fetch("/dashboard/dashboards/cemeteries/api/debug-log.php", {
            method: "POST",
            headers: {"Content-Type": "application/json"},
            body: JSON.stringify({
                event: "ROOT_INDEX_REDIRECT",
                from: "/index.php",
                to: "' . $safeUrl . '",
                historyLength: history.length,
                referrer: document.referrer,
                timestamp: Date.now()
            })
        }).finally(() => {
            location.replace("' . $safeUrl . '");
        });
    </script>';
    echo '</head><body></body></html>';
    exit;
}

// v16: תמיד להשתמש ב-location.replace() כדי לא להוסיף להיסטוריה
// כך ה-index.php לעולם לא יישאר בהיסטוריה
if (isset($_SESSION['user_id'])) {
    // מחובר - הפנה לדשבורד
    if (isset($_SESSION['redirect_after_login'])) {
        $redirect = $_SESSION['redirect_after_login'];
        unset($_SESSION['redirect_after_login']);
        jsRedirect($redirect);
    } else {
        jsRedirect('/dashboard/dashboards/cemeteries/');
    }
} else {
    // לא מחובר - הפנה ללוגין
    if (isset($_SESSION['redirect_after_login'])) {
        $redirect = $_SESSION['redirect_after_login'];
        jsRedirect('/auth/login.php?redirect_to=' . urlencode($redirect));
    } else {
        jsRedirect('/auth/login.php');
    }
}
?>
