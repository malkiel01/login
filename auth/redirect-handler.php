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
// משתמש ב-location.replace() כדי שדף הלוגין לא יישאר בהיסטוריה
function handleLoginRedirect() {
    if (isset($_SESSION['redirect_after_login'])) {
        $redirect = $_SESSION['redirect_after_login'];
        unset($_SESSION['redirect_after_login']);
        $url = $redirect;
    } else {
        $url = '/dashboard/index.php';
    }

    $safeUrl = htmlspecialchars($url, ENT_QUOTES, 'UTF-8');

    // שימוש ב-JavaScript location.replace() במקום header()
    // ככה דף הלוגין לא נשאר בהיסטוריה ואי אפשר לחזור אליו
    echo '<!DOCTYPE html><html><head><meta charset="UTF-8">';
    echo '<script>
        // DEBUG: After successful login
        fetch("/dashboard/dashboards/cemeteries/api/debug-log.php", {
            method: "POST",
            headers: {"Content-Type": "application/json"},
            body: JSON.stringify({
                event: "HANDLE_LOGIN_REDIRECT",
                from: "/auth/login.php (after POST)",
                to: "' . $safeUrl . '",
                historyLength: history.length,
                referrer: document.referrer,
                timestamp: Date.now()
            })
        }).finally(() => {
            location.replace("' . $safeUrl . '");
        });
    </script>';
    echo '</head><body>מעביר...</body></html>';
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