<?php
/**
 * CSRF Protection - הגנה מפני Cross-Site Request Forgery
 *
 * שימוש בטפסים:
 * require_once 'csrf.php';
 *
 * // בטופס HTML:
 * <form method="POST">
 *     <?php echo csrfField(); ?>
 *     ...
 * </form>
 *
 * // בקבלת הטופס:
 * if (!validateCsrf()) {
 *     die('Invalid CSRF token');
 * }
 *
 * // ב-AJAX:
 * const token = document.querySelector('meta[name="csrf-token"]').content;
 * fetch(url, {
 *     headers: { 'X-CSRF-TOKEN': token }
 * });
 *
 * @version 1.0.0
 * @author Malkiel
 */

// וודא שה-session פעיל
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * יצירת טוקן CSRF חדש
 * @return string
 */
function generateCsrfToken(): string {
    $token = bin2hex(random_bytes(32));
    $_SESSION['csrf_token'] = $token;
    $_SESSION['csrf_token_time'] = time();
    return $token;
}

/**
 * קבלת הטוקן הנוכחי או יצירת חדש
 * @param bool $regenerate - האם ליצור טוקן חדש
 * @return string
 */
function getCsrfToken(bool $regenerate = false): string {
    // אם אין טוקן או שצריך לחדש
    if ($regenerate || !isset($_SESSION['csrf_token'])) {
        return generateCsrfToken();
    }

    // בדוק אם הטוקן פג תוקף (שעה)
    $tokenAge = time() - ($_SESSION['csrf_token_time'] ?? 0);
    if ($tokenAge > 3600) {
        return generateCsrfToken();
    }

    return $_SESSION['csrf_token'];
}

/**
 * יצירת שדה hidden לטופס
 * @return string
 */
function csrfField(): string {
    $token = getCsrfToken();
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token) . '">';
}

/**
 * יצירת meta tag לשימוש ב-AJAX
 * @return string
 */
function csrfMeta(): string {
    $token = getCsrfToken();
    return '<meta name="csrf-token" content="' . htmlspecialchars($token) . '">';
}

/**
 * אימות טוקן CSRF
 * @param string|null $token - הטוקן לבדיקה (אופציונלי, ייקח מ-POST או header)
 * @return bool
 */
function validateCsrf(?string $token = null): bool {
    // קבל את הטוקן שנשלח
    if ($token === null) {
        // נסה לקחת מ-POST
        $token = $_POST['csrf_token'] ?? null;

        // אם אין, נסה מ-header (ל-AJAX)
        if ($token === null) {
            $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
        }
    }

    // אם אין טוקן בכלל
    if ($token === null || empty($token)) {
        return false;
    }

    // אם אין טוקן ב-session
    if (!isset($_SESSION['csrf_token'])) {
        return false;
    }

    // השווה את הטוקנים (timing-safe comparison)
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * אימות CSRF עם שגיאה אוטומטית
 * @param bool $isApi - האם זו קריאת API
 */
function requireCsrf(bool $isApi = false): void {
    if (!validateCsrf()) {
        if ($isApi) {
            header('Content-Type: application/json; charset=utf-8');
            http_response_code(403);
            echo json_encode([
                'success' => false,
                'error' => 'CSRF validation failed',
                'message' => 'הבקשה נדחתה מסיבות אבטחה. אנא רענן את הדף ונסה שוב.'
            ]);
            exit;
        }

        http_response_code(403);
        die('
        <!DOCTYPE html>
        <html dir="rtl" lang="he">
        <head>
            <meta charset="UTF-8">
            <title>שגיאת אבטחה</title>
            <style>
                body { font-family: sans-serif; display: flex; justify-content: center; align-items: center; min-height: 100vh; background: #f3f4f6; margin: 0; }
                .box { background: white; padding: 40px; border-radius: 12px; text-align: center; box-shadow: 0 4px 20px rgba(0,0,0,0.1); }
                h1 { color: #dc2626; margin-bottom: 10px; }
                p { color: #6b7280; }
                a { display: inline-block; margin-top: 20px; padding: 12px 24px; background: #667eea; color: white; text-decoration: none; border-radius: 8px; }
                a:hover { background: #5a67d8; }
            </style>
        </head>
        <body>
            <div class="box">
                <h1>שגיאת אבטחה</h1>
                <p>הבקשה נדחתה מסיבות אבטחה.</p>
                <p>יתכן שהסשן פג תוקף או שהדף לא רוענן.</p>
                <a href="javascript:location.reload()">רענן את הדף</a>
            </div>
        </body>
        </html>
        ');
    }
}

/**
 * רענון טוקן לאחר פעולה רגישה
 */
function refreshCsrfToken(): void {
    generateCsrfToken();
}

/**
 * קבלת JavaScript helper לשימוש בצד לקוח
 * @return string
 */
function csrfScript(): string {
    return '
<script>
// CSRF Helper for AJAX requests
window.CSRF = {
    token: function() {
        const meta = document.querySelector(\'meta[name="csrf-token"]\');
        return meta ? meta.content : null;
    },

    header: function() {
        return { "X-CSRF-TOKEN": this.token() };
    },

    // הוספה אוטומטית לכל בקשות fetch
    init: function() {
        const originalFetch = window.fetch;
        window.fetch = function(url, options = {}) {
            // רק לבקשות POST/PUT/DELETE
            if (options.method && ["POST", "PUT", "DELETE", "PATCH"].includes(options.method.toUpperCase())) {
                options.headers = options.headers || {};
                if (!options.headers["X-CSRF-TOKEN"]) {
                    options.headers["X-CSRF-TOKEN"] = CSRF.token();
                }
            }
            return originalFetch(url, options);
        };
        console.log("CSRF Protection initialized");
    }
};

// אתחול אוטומטי
document.addEventListener("DOMContentLoaded", function() {
    CSRF.init();
});
</script>';
}

/**
 * בדיקה האם הבקשה היא POST/PUT/DELETE (דורשת CSRF)
 * @return bool
 */
function isWriteRequest(): bool {
    return in_array($_SERVER['REQUEST_METHOD'], ['POST', 'PUT', 'DELETE', 'PATCH']);
}

/**
 * הגנה אוטומטית - לשימוש בתחילת קבצים
 * מאמת CSRF רק לבקשות כתיבה
 * @param bool $isApi
 */
function csrfProtect(bool $isApi = false): void {
    if (isWriteRequest()) {
        requireCsrf($isApi);
    }
}
?>
