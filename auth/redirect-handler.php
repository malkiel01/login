<?php
/**
 * Redirect Handler for Notifications
 * טיפול בהפניות אחרי לחיצה על התראות
 */

// בדיקה אם יש redirect_to בפרמטרים
if (isset($_GET['redirect_to'])) {
    $_SESSION['redirect_after_login'] = $_GET['redirect_to'];
}

// פונקציה לטיפול בהפניה אחרי התחברות מוצלחת
// שימוש ב-header() - הדרך הנכונה! לא יוצר entry בהיסטוריה
function handleLoginRedirect() {
    if (isset($_SESSION['redirect_after_login'])) {
        $redirect = $_SESSION['redirect_after_login'];
        unset($_SESSION['redirect_after_login']);
        $url = $redirect;
    } else {
        // ישירות לבתי קברות - דילוג על dashboard/index.php
        $url = '/dashboard/dashboards/cemeteries/';
    }

    // DEBUG: Log the redirect
    $debugData = [
        'event' => 'LOGIN_REDIRECT',
        'from' => '/auth/login.php',
        'to' => $url,
        'method' => 'header_303',
        'session_id' => session_id(),
        'user_id' => $_SESSION['user_id'] ?? null,
        'timestamp' => time(),
        'server_time' => date('Y-m-d H:i:s')
    ];
    $debugFile = $_SERVER['DOCUMENT_ROOT'] . '/dashboard/dashboards/cemeteries/logs/debug.log';
    file_put_contents($debugFile, date('Y-m-d H:i:s') . ' | ' . json_encode($debugData, JSON_UNESCAPED_UNICODE) . "\n", FILE_APPEND | LOCK_EX);

    // HTTP 303 See Other - הדרך הנכונה לredirect אחרי POST
    // זה אומר לדפדפן: "הבקשה הצליחה, עכשיו לך לכתובת הזו עם GET"
    // הדפדפן לא ישמור את ה-POST בהיסטוריה!
    header('Location: ' . $url, true, 303);
    exit;
}

// JavaScript לטיפול בהודעות מ-Service Worker
function getRedirectScript() {
    return <<<'SCRIPT'
<script>
// טיפול בהפניות מהתראות
(function() {
    if ('serviceWorker' in navigator) {
        // האזנה להודעות מ-Service Worker
        navigator.serviceWorker.addEventListener('message', event => {
            if (event.data && event.data.type === 'REDIRECT_AFTER_LOGIN') {
                sessionStorage.setItem('redirect_after_login', event.data.url);
                
                // הצג הודעה
                const msg = document.createElement('div');
                msg.style.cssText = `
                    position: fixed;
                    top: 20px;
                    right: 20px;
                    background: #667eea;
                    color: white;
                    padding: 15px 20px;
                    border-radius: 8px;
                    box-shadow: 0 2px 10px rgba(0,0,0,0.2);
                    z-index: 10000;
                `;
                msg.textContent = 'התחבר כדי לצפות בהתראות';
                document.body.appendChild(msg);
                setTimeout(() => msg.remove(), 5000);
            }
        });
        
        // בדוק redirect בURL
        const urlParams = new URLSearchParams(window.location.search);
        const redirectParam = urlParams.get('redirect_to');
        if (redirectParam) {
            sessionStorage.setItem('redirect_after_login', redirectParam);
        }
        
        // בדוק redirect שמור
        const redirectTo = sessionStorage.getItem('redirect_after_login');
        if (redirectTo) {
            document.querySelectorAll('form').forEach(form => {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'redirect_to';
                input.value = redirectTo;
                form.appendChild(input);
            });
        }
    }
})();
</script>
SCRIPT;
}
?>