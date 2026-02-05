<?php
/**
 * Token Init - סקריפט אתחול token בצד הלקוח
 * יש לכלול בדפים שדורשים auth (dashboard וכו')
 *
 * שימוש:
 * <?php include $_SERVER['DOCUMENT_ROOT'] . '/auth/token-init.php'; ?>
 */

/**
 * מחזיר סקריפט JavaScript לאתחול ה-token בצד הלקוח
 * @return string
 */
function getTokenInitScript(): string {
    $tokenData = $_SESSION['token_data'] ?? null;
    $needsRefresh = $_SESSION['token_needs_refresh'] ?? false;

    // נקה מה-session אחרי קריאה
    unset($_SESSION['token_data']);
    unset($_SESSION['token_needs_refresh']);

    $script = '
    <!-- Persistent Auth Scripts -->
    <script src="/js/indexed-db-auth.js"></script>
    <script src="/js/persistent-auth.js"></script>
    ';

    // אם יש token data חדש (אחרי login), שמור אותו
    if ($tokenData) {
        $tokenJson = json_encode($tokenData);
        $script .= "
    <script>
        // שמירת token חדש אחרי התחברות
        document.addEventListener('DOMContentLoaded', async function() {
            const tokenData = {$tokenJson};
            if (window.persistentAuth) {
                await window.persistentAuth.saveLogin(tokenData);
                console.log('[TokenInit] Token saved to persistent storage');
            }
        });
    </script>
        ";
    }

    // אם צריך רענון
    if ($needsRefresh) {
        $script .= "
    <script>
        document.addEventListener('DOMContentLoaded', async function() {
            if (window.persistentAuth) {
                console.log('[TokenInit] Token needs refresh, refreshing...');
                await window.persistentAuth.refreshToken();
            }
        });
    </script>
        ";
    }

    return $script;
}

/**
 * מחזיר סקריפט לבדיקת auth והפניה ל-login אם צריך
 * @param bool $redirectToLogin - האם להפנות ל-login אם לא מחובר
 * @return string
 */
function getAuthCheckScript(bool $redirectToLogin = true): string {
    // v16: שימוש ב-location.replace() כדי לא להוסיף login להיסטוריה!
    $redirectCode = $redirectToLogin ? "location.replace('/auth/login.php');" : '';

    return "
    <script>
        // בדיקת auth בטעינת הדף
        window.addEventListener('auth:guest', function() {
            console.log('[Auth] Not logged in');
            {$redirectCode}
        });

        window.addEventListener('auth:expired', function() {
            console.log('[Auth] Token expired');
            {$redirectCode}
        });

        window.addEventListener('auth:ready', function(e) {
            console.log('[Auth] User ready:', e.detail.name);
        });
    </script>
    ";
}

/**
 * מחזיר סקריפט להתנתקות
 * @return string
 */
function getLogoutScript(): string {
    return "
    <script>
        async function performLogout() {
            if (window.persistentAuth) {
                await window.persistentAuth.logout();
            }
            // v16: location.replace() כדי לא להוסיף login להיסטוריה
            location.replace('/auth/login.php');
        }
    </script>
    ";
}
