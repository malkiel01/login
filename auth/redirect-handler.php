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
// v11: שימוש ב-location.replace() כדי למחוק את דף הלוגין מההיסטוריה לחלוטין!
// זה פותר את הבעיה של back מהדשבורד שהולך ללוגין במקום לצאת מהאפליקציה
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
        'method' => 'location_replace', // v11: שינוי מ-header_303 ל-location.replace
        'session_id' => session_id(),
        'user_id' => $_SESSION['user_id'] ?? null,
        'timestamp' => time(),
        'server_time' => date('Y-m-d H:i:s')
    ];
    $debugFile = $_SERVER['DOCUMENT_ROOT'] . '/dashboard/dashboards/cemeteries/logs/debug.log';
    file_put_contents($debugFile, date('Y-m-d H:i:s') . ' | ' . json_encode($debugData, JSON_UNESCAPED_UNICODE) . "\n", FILE_APPEND | LOCK_EX);

    // v12: location.replace() עם דף שקוף לחלוטין
    // הדף הזמני לא יראה כלום - רק רקע לבן וredirect מיידי
    echo '<!DOCTYPE html><html><head><meta charset="UTF-8">';
    echo '<style>*{margin:0;padding:0}body{background:#fff}</style>'; // רקע לבן נקי
    echo '<script>location.replace(' . json_encode($url) . ');</script>';
    echo '</head><body></body></html>';
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