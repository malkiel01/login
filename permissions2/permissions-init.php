<?php
/**
 * Permissions System Initialization
 * permissions/permissions-init.php
 * 
 * קובץ אתחול מרכזי למערכת ההרשאות
 */

// אתחול session אם לא פעיל
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// בדיקה אם הקבצים הנדרשים קיימים
$requiredFiles = [
    __DIR__ . '/core/PermissionsManager.php',
    __DIR__ . '/core/PermissionStorage.php',
    __DIR__ . '/core/PermissionTypes.php'
];

foreach ($requiredFiles as $file) {
    if (!file_exists($file)) {
        error_log("Permissions Error: Missing file - $file");
        // החזר בשקט בלי לקרוס את האתר
        return false;
    }
}

// טעינת קבצי הליבה
require_once __DIR__ . '/core/PermissionsManager.php';
require_once __DIR__ . '/core/PermissionStorage.php';
require_once __DIR__ . '/core/PermissionTypes.php';

// טעינת handlers אופציונלית
$handlers = [
    'NotificationHandler.php',
    'PushHandler.php', 
    'MediaHandler.php',
    'StorageHandler.php',
    'LocationHandler.php',
    'BackgroundHandler.php'
];

foreach ($handlers as $handler) {
    $handlerPath = __DIR__ . '/handlers/' . $handler;
    if (file_exists($handlerPath)) {
        require_once $handlerPath;
    }
}

// Use statements
use Permissions\Core\PermissionsManager;
use Permissions\Core\PermissionStorage;
use Permissions\Core\PermissionTypes;

/**
 * אתחול מנהל ההרשאות הגלובלי
 */
try {
    if (!isset($GLOBALS['permissionsManager'])) {
        $userId = $_SESSION['user_id'] ?? null;
        $GLOBALS['permissionsManager'] = new PermissionsManager($userId);
    }
} catch (Exception $e) {
    error_log('Permissions Error: ' . $e->getMessage());
    // אל תקרוס את האתר
    $GLOBALS['permissionsManager'] = null;
}

/**
 * פונקציות גלובליות - נבדוק שלא קיימות כבר
 */

if (!function_exists('getPermissionsHeaders')) {
    /**
     * פונקציה להחזרת headers ל-HTML
     */
    function getPermissionsHeaders($options = []) {
        $defaults = [
            'include_css' => true,
            'include_js' => true,
            'auto_init' => true,
            'check_on_load' => true,
            'show_banners' => true,
            'api_endpoint' => '/permissions/api/',
            'vapid_public_key' => null
        ];
        
        $config = array_merge($defaults, $options);
        
        $html = '';
        
        // CSS
        if ($config['include_css']) {
            $html .= '
        <!-- Permissions System CSS -->
        <link rel="stylesheet" href="/permissions/ui/styles/permissions.css">
            ';
        }
        
        // Meta tags for permissions
        $html .= '
        <!-- Permissions Meta Tags -->
        <meta name="permissions-policy" content="camera=*, microphone=*, geolocation=*">
        ';
        
        return $html;
    }
}

if (!function_exists('getPermissionsScripts')) {
    /**
     * פונקציה להחזרת scripts ל-HTML
     */
    function getPermissionsScripts($options = []) {
        $defaults = [
            'auto_init' => true,
            'check_on_load' => true,
            'show_banners' => true,
            'debug' => false,
            'api_endpoint' => '/permissions/api/',
            'check_interval' => 60000,
            'vapid_public_key' => null
        ];
        
        $config = array_merge($defaults, $options);
        
        $html = '
        <!-- Permissions System JavaScript -->
        <script src="/permissions/js/permissions-manager.js"></script>
        ';
        
        // אתחול אוטומטי
        if ($config['auto_init']) {
            $html .= '
        <script>
            document.addEventListener("DOMContentLoaded", function() {
                // Initialize Permissions Manager
                window.permissionsManager = new PermissionsManager({
                    autoCheck: ' . ($config['check_on_load'] ? 'true' : 'false') . ',
                    showBanners: ' . ($config['show_banners'] ? 'true' : 'false') . ',
                    debug: ' . ($config['debug'] ? 'true' : 'false') . ',
                    apiEndpoint: "' . $config['api_endpoint'] . '",
                    checkInterval: ' . $config['check_interval'] . ',
                    vapidPublicKey: ' . ($config['vapid_public_key'] ? '"' . $config['vapid_public_key'] . '"' : 'null') . '
                });
                
                // Log status
                if (' . ($config['debug'] ? 'true' : 'false') . ') {
                    console.log("Permissions Manager initialized");
                }
            });
        </script>
            ';
        }
        
        return $html;
    }
}

if (!function_exists('checkPermission')) {
    /**
     * בדיקת הרשאה בצד השרת
     */
    function checkPermission($type) {
        if (!isset($GLOBALS['permissionsManager']) || !$GLOBALS['permissionsManager']) {
            return null;
        }
        return $GLOBALS['permissionsManager']->checkPermission($type);
    }
}

if (!function_exists('checkAllPermissions')) {
    /**
     * בדיקת כל ההרשאות
     */
    function checkAllPermissions() {
        if (!isset($GLOBALS['permissionsManager']) || !$GLOBALS['permissionsManager']) {
            return [];
        }
        return $GLOBALS['permissionsManager']->checkAllPermissions();
    }
}

if (!function_exists('isPermissionBlocked')) {
    /**
     * בדיקה אם הרשאה נחסמה
     */
    function isPermissionBlocked($type) {
        if (!isset($GLOBALS['permissionsManager']) || !$GLOBALS['permissionsManager']) {
            return false;
        }
        return $GLOBALS['permissionsManager']->isPermissionBlocked($type);
    }
}

if (!function_exists('canRequestPermission')) {
    /**
     * בדיקה אם אפשר לבקש הרשאה
     */
    function canRequestPermission($type) {
        if (!isset($GLOBALS['permissionsManager']) || !$GLOBALS['permissionsManager']) {
            return false;
        }
        return $GLOBALS['permissionsManager']->canRequestPermission($type);
    }
}

if (!function_exists('getMissingCriticalPermissions')) {
    /**
     * קבלת הרשאות חסרות קריטיות
     */
    function getMissingCriticalPermissions() {
        if (!isset($GLOBALS['permissionsManager']) || !$GLOBALS['permissionsManager']) {
            return [];
        }
        return $GLOBALS['permissionsManager']->getMissingCriticalPermissions();
    }
}

if (!function_exists('updatePermissionStatus')) {
    /**
     * עדכון סטטוס הרשאה
     */
    function updatePermissionStatus($type, $status, $metadata = []) {
        if (!isset($GLOBALS['permissionsManager']) || !$GLOBALS['permissionsManager']) {
            return false;
        }
        return $GLOBALS['permissionsManager']->updatePermissionStatus($type, $status, $metadata);
    }
}

if (!function_exists('generatePermissionsReport')) {
    /**
     * יצירת דוח הרשאות
     */
    function generatePermissionsReport() {
        if (!isset($GLOBALS['permissionsManager']) || !$GLOBALS['permissionsManager']) {
            return [];
        }
        return $GLOBALS['permissionsManager']->generatePermissionsReport();
    }
}

if (!function_exists('renderPermissionsBanner')) {
    /**
     * הצגת באנר הרשאות
     */
    function renderPermissionsBanner($permissions = []) {
        if (empty($permissions)) {
            $permissions = getMissingCriticalPermissions();
        }
        
        if (empty($permissions)) {
            return '';
        }
        
        $html = '<div class="permissions-request-banner" id="permissionsBanner">';
        $html .= '<div class="permissions-banner-content">';
        $html .= '<h3>🔐 הרשאות נדרשות</h3>';
        $html .= '<p>האפליקציה זקוקה להרשאות הבאות לפעולה מיטבית:</p>';
        $html .= '<ul>';
        
        foreach ($permissions as $permission) {
            $html .= sprintf(
                '<li>%s %s - %s</li>',
                htmlspecialchars($permission['icon'] ?? '❓'),
                htmlspecialchars($permission['name'] ?? $permission['type']),
                htmlspecialchars($permission['description'] ?? '')
            );
        }
        
        $html .= '</ul>';
        $html .= '<div class="permissions-banner-actions">';
        $html .= '<button onclick="permissionsManager.requestMultiple(' . 
                 htmlspecialchars(json_encode(array_column($permissions, 'type'))) . 
                 ')" class="btn-allow-all">אשר הכל</button>';
        $html .= '<button onclick="document.getElementById(\'permissionsBanner\').remove()" class="btn-dismiss">אולי מאוחר יותר</button>';
        $html .= '</div>';
        $html .= '</div>';
        $html .= '</div>';
        
        return $html;
    }
}

if (!function_exists('isHTTPS')) {
    /**
     * בדיקת HTTPS
     */
    function isHTTPS() {
        return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') 
            || $_SERVER['SERVER_PORT'] == 443
            || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
    }
}

if (!function_exists('renderHTTPSWarning')) {
    /**
     * הצגת התראה על חוסר HTTPS
     */
    function renderHTTPSWarning() {
        if (isHTTPS()) {
            return '';
        }
        
        return '
        <div class="permissions-https-warning">
            <strong>⚠️ אזהרה:</strong> 
            חלק מההרשאות (כמו מצלמה ומיקום) דורשות חיבור מאובטח (HTTPS).
            חלק מהתכונות עלולות לא לעבוד כראוי.
        </div>
        ';
    }
}

if (!function_exists('getPermissionsDataScript')) {
    /**
     * יצירת נתוני הרשאות ל-JavaScript
     */
    function getPermissionsDataScript() {
        $data = [
            'current' => checkAllPermissions(),
            'critical' => getMissingCriticalPermissions(),
            'isHTTPS' => isHTTPS(),
            'userId' => $_SESSION['user_id'] ?? null,
            'apiEndpoint' => '/permissions/api/'
        ];
        
        return '
        <script>
            window.permissionsData = ' . json_encode($data, JSON_PRETTY_PRINT) . ';
        </script>
        ';
    }
}

if (!function_exists('requirePermissions')) {
    /**
     * Middleware לבדיקת הרשאות
     */
    function requirePermissions($required = []) {
        if (!isset($GLOBALS['permissionsManager']) || !$GLOBALS['permissionsManager']) {
            return false;
        }
        
        foreach ($required as $type) {
            $permission = checkPermission($type);
            if (!$permission || $permission['status'] !== PermissionsManager::STATUS_GRANTED) {
                return false;
            }
        }
        return true;
    }
}

if (!function_exists('clearPermissionsCache')) {
    /**
     * ניקוי cache של הרשאות
     */
    function clearPermissionsCache() {
        if (isset($_SESSION['permissions_cache'])) {
            unset($_SESSION['permissions_cache']);
        }
    }
}

if (!function_exists('getPermissionsSettingsURL')) {
    /**
     * קבלת URL לדף הגדרות הרשאות
     */
    function getPermissionsSettingsURL() {
        return '/permissions/settings.php';
    }
}

if (!function_exists('getPermissionsDebugURL')) {
    /**
     * קבלת URL לדף דיבוג
     */
    function getPermissionsDebugURL() {
        return '/permissions/debug/permissions-debug.php';
    }
}

// הצלחה
return true;