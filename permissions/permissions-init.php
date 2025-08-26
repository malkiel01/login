<?php
/**
 * Permissions System Initialization
 * permissions/permissions-init.php
 * 
 * קובץ אתחול מרכזי למערכת ההרשאות
 * יש לכלול אותו בכל דף שצריך לעבוד עם הרשאות
 */

// אתחול session אם לא פעיל
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// טעינת קבצי הליבה
require_once __DIR__ . '/core/PermissionsManager.php';
require_once __DIR__ . '/core/PermissionStorage.php';
require_once __DIR__ . '/core/PermissionTypes.php';

// טעינת handlers - רק אם הקבצים קיימים
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
if (!isset($GLOBALS['permissionsManager'])) {
    $userId = $_SESSION['user_id'] ?? null;
    $GLOBALS['permissionsManager'] = new PermissionsManager($userId);
}

/**
 * פונקציה להחזרת headers ל-HTML
 * @param array $options אפשרויות להתאמה אישית
 * @return string HTML headers
 */
function getPermissionsHeaders($options = []) {
    $defaults = [
        'include_css' => true,
        'include_js' => true,
        'auto_init' => true,
        'check_on_load' => true,
        'show_banners' => true,
        'api_endpoint' => '/permissions/api/',
        'vapid_public_key' => $_ENV['VAPID_PUBLIC_KEY'] ?? null
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

/**
 * פונקציה להחזרת scripts ל-HTML
 * @param array $options אפשרויות להתאמה אישית
 * @return string HTML scripts
 */
function getPermissionsScripts($options = []) {
    $defaults = [
        'auto_init' => true,
        'check_on_load' => true,
        'show_banners' => true,
        'debug' => false,
        'api_endpoint' => '/permissions/api/',
        'check_interval' => 60000,
        'vapid_public_key' => $_ENV['VAPID_PUBLIC_KEY'] ?? null
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

/**
 * פונקציה לבדיקת הרשאה בצד השרת
 * @param string $type סוג ההרשאה
 * @return array מידע על ההרשאה
 */
function checkPermission($type) {
    global $permissionsManager;
    return $permissionsManager->checkPermission($type);
}

/**
 * פונקציה לבדיקת כל ההרשאות
 * @return array מערך של כל ההרשאות
 */
function checkAllPermissions() {
    global $permissionsManager;
    return $permissionsManager->checkAllPermissions();
}

/**
 * פונקציה לבדיקה אם הרשאה נחסמה
 * @param string $type סוג ההרשאה
 * @return bool האם נחסמה
 */
function isPermissionBlocked($type) {
    global $permissionsManager;
    return $permissionsManager->isPermissionBlocked($type);
}

/**
 * פונקציה לבדיקה אם אפשר לבקש הרשאה
 * @param string $type סוג ההרשאה
 * @return bool האם אפשר לבקש
 */
function canRequestPermission($type) {
    global $permissionsManager;
    return $permissionsManager->canRequestPermission($type);
}

/**
 * פונקציה לקבלת הרשאות חסרות קריטיות
 * @return array הרשאות חסרות
 */
function getMissingCriticalPermissions() {
    global $permissionsManager;
    return $permissionsManager->getMissingCriticalPermissions();
}

/**
 * פונקציה לעדכון סטטוס הרשאה
 * @param string $type סוג ההרשאה
 * @param string $status הסטטוס החדש
 * @param array $metadata מידע נוסף
 * @return bool הצלחה
 */
function updatePermissionStatus($type, $status, $metadata = []) {
    global $permissionsManager;
    return $permissionsManager->updatePermissionStatus($type, $status, $metadata);
}

/**
 * פונקציה ליצירת דוח הרשאות
 * @return array דוח מלא
 */
function generatePermissionsReport() {
    global $permissionsManager;
    return $permissionsManager->generatePermissionsReport();
}

/**
 * פונקציה להצגת באנר הרשאות
 * @param array $permissions הרשאות להצגה
 * @return string HTML של הבאנר
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

/**
 * פונקציה לבדיקת HTTPS
 * @return bool האם האתר רץ ב-HTTPS
 */
function isHTTPS() {
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') 
        || $_SERVER['SERVER_PORT'] == 443
        || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
}

/**
 * פונקציה להצגת התראה על חוסר HTTPS
 * @return string HTML התראה
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

/**
 * פונקציה ליצירת נתוני הרשאות ל-JavaScript
 * @return string JavaScript object
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

/**
 * Middleware לבדיקת הרשאות
 * @param array $required הרשאות נדרשות
 * @return bool האם יש את כל ההרשאות
 */
function requirePermissions($required = []) {
    foreach ($required as $type) {
        $permission = checkPermission($type);
        if ($permission['status'] !== PermissionsManager::STATUS_GRANTED) {
            return false;
        }
    }
    return true;
}

/**
 * פונקציה לניקוי cache של הרשאות
 */
function clearPermissionsCache() {
    if (isset($_SESSION['permissions_cache'])) {
        unset($_SESSION['permissions_cache']);
    }
}

/**
 * פונקציה לקבלת URL לדף הגדרות הרשאות
 * @return string URL
 */
function getPermissionsSettingsURL() {
    return '/permissions/settings.php';
}

/**
 * פונקציה לקבלת URL לדף דיבוג
 * @return string URL
 */
function getPermissionsDebugURL() {
    return '/permissions/debug/permissions-debug.php';
}