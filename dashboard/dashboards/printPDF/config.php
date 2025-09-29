<?php
/**
 * PDF Editor Configuration File
 * Location: /dashboard/dashboards/printPDF/config.php
 * Based on working cemetery config structure
 */

// טוען את הקונפיג הראשי של הפרויקט
require_once $_SERVER['DOCUMENT_ROOT'] . '/config.php';

// הגדרות ספציפיות לדשבורד PDF Editor
define('DASHBOARD_NAME', 'עורך PDF ותמונות');
define('PDF_EDITOR_VERSION', '1.0.0');
define('PDF_EDITOR_PATH', dirname(__FILE__));
define('PDF_EDITOR_URL', '/dashboard/dashboards/printPDF/');

// Storage configuration
define('STORAGE_MODE', 'server');
define('AUTO_SAVE_ENABLED', true);
define('AUTO_SAVE_INTERVAL', 120);

// File upload configuration
define('MAX_UPLOAD_SIZE', 10485760); // 10MB
define('ALLOWED_FILE_TYPES', ['pdf', 'jpg', 'jpeg', 'png']);
define('TEMP_FILE_LIFETIME', 3600);

// Paths
define('TEMP_PATH', __DIR__ . '/temp/');
define('FONTS_PATH', __DIR__ . '/fonts/');
define('TEMPLATES_PATH', __DIR__ . '/templates/');
define('TCPDF_PATH', __DIR__ . '/lib/tcpdf/');

// Security settings
define('CSRF_TOKEN_NAME', 'pdf_editor_csrf');
define('SESSION_NAME', 'pdf_editor_session');

// Database tables prefix
define('DB_PREFIX', 'pdf_editor_');

/**
 * Check user permission - בדיוק כמו בבתי עלמין
 */
function checkPermission($action, $module = 'pdf_editor') {
    // TODO: להוסיף בדיקת הרשאות אמיתית
    return true;
}

/**
 * Generate CSRF token
 */
function generateCSRFToken() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    if (!isset($_SESSION[CSRF_TOKEN_NAME])) {
        $_SESSION[CSRF_TOKEN_NAME] = bin2hex(random_bytes(32));
    }
    return $_SESSION[CSRF_TOKEN_NAME];
}

/**
 * Verify CSRF token
 */
function verifyCSRFToken($token) {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    if (!isset($_SESSION[CSRF_TOKEN_NAME]) || $token !== $_SESSION[CSRF_TOKEN_NAME]) {
        return false;
    }
    return true;
}

/**
 * Clean old temporary files
 */
function cleanTempFiles() {
    $tempPath = TEMP_PATH;
    if (!is_dir($tempPath)) {
        mkdir($tempPath, 0755, true);
        return;
    }
    
    $files = glob($tempPath . '*');
    $now = time();
    
    foreach ($files as $file) {
        if (is_file($file)) {
            if ($now - filemtime($file) >= TEMP_FILE_LIFETIME) {
                @unlink($file);
            }
        }
    }
}

/**
 * Log activity - בדיוק כמו בבתי עלמין
 */
function logActivity($action, $module, $itemId = null, $details = []) {
    // TODO: להוסיף מערכת לוגים
    error_log("[$module] $action" . ($itemId ? " on item #$itemId" : ""));
}

// אל תטען את functions.php כאן! 
// זה ייטען ב-index.php אחרי config.php
?>