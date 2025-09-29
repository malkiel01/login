<?php
/**
 * PDF Editor Configuration File
 * Location: /dashboard/dashboards/printPDF/config.php
 */

// Include main system config - בדיוק כמו בבתי עלמין
require_once $_SERVER['DOCUMENT_ROOT'] . '/config.php';

// Include functions if exists
if (file_exists($_SERVER['DOCUMENT_ROOT'] . '/dashboard/dashboards/printPDF/includes/functions.php')) {
    require_once $_SERVER['DOCUMENT_ROOT'] . '/dashboard/dashboards/printPDF/includes/functions.php';
}

// PDF Editor specific configuration
define('PDF_EDITOR_VERSION', '1.0.0');
define('PDF_EDITOR_PATH', dirname(__FILE__));
define('PDF_EDITOR_URL', '/dashboard/dashboards/printPDF/');
define('DASHBOARD_NAME', 'עורך PDF ותמונות');

// Allowed user permissions for PDF Editor
$ALLOWED_PERMISSIONS = [
    'admin',
    'pdf_editor', 
    'cemetery_manager',
    'super_user'
];

// Storage configuration
define('STORAGE_MODE', 'server'); // 'server' or 'local'
define('AUTO_SAVE_ENABLED', true);
define('AUTO_SAVE_INTERVAL', 120); // seconds

// File upload configuration
define('MAX_UPLOAD_SIZE', 10485760); // 10MB in bytes
define('ALLOWED_FILE_TYPES', ['pdf', 'jpg', 'jpeg', 'png']);
define('TEMP_FILE_LIFETIME', 3600); // 1 hour in seconds

// Paths
define('TEMP_PATH', __DIR__ . '/temp/');
define('FONTS_PATH', __DIR__ . '/fonts/');
define('TEMPLATES_PATH', __DIR__ . '/templates/');
define('TCPDF_PATH', __DIR__ . '/lib/tcpdf/');

// Default language
define('DEFAULT_LANGUAGE', 'he'); // Hebrew as default
define('AVAILABLE_LANGUAGES', ['he', 'en', 'ar']);

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
                unlink($file);
            }
        }
    }
}

/**
 * Get database connection specifically for PDF Editor
 */
function getPDFEditorDB() {
    return getDBConnection(); // Using main config connection
}

/**
 * Initialize PDF Editor tables if not exist
 */
function initializePDFEditorTables() {
    $db = getPDFEditorDB();
    
    // Projects table
    $sql1 = "CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "projects` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `project_id` VARCHAR(36) UNIQUE NOT NULL,
        `user_id` INT NOT NULL,
        `name` VARCHAR(255) NOT NULL,
        `data` LONGTEXT,
        `thumbnail` TEXT,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        `is_template` BOOLEAN DEFAULT FALSE,
        `template_category` VARCHAR(50),
        INDEX idx_user_id (`user_id`),
        INDEX idx_project_id (`project_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    // Shared projects table
    $sql2 = "CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "shares` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `project_id` VARCHAR(36) NOT NULL,
        `share_token` VARCHAR(32) UNIQUE NOT NULL,
        `expires_at` TIMESTAMP NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_share_token (`share_token`),
        FOREIGN KEY (`project_id`) REFERENCES `" . DB_PREFIX . "projects`(`project_id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    // Auto-save states table
    $sql3 = "CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "autosave` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `project_id` VARCHAR(36) NOT NULL,
        `state_data` LONGTEXT,
        `saved_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_project_saved (`project_id`, `saved_at`),
        FOREIGN KEY (`project_id`) REFERENCES `" . DB_PREFIX . "projects`(`project_id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    try {
        $db->exec($sql1);
        $db->exec($sql2);
        $db->exec($sql3);
        return true;
    } catch (PDOException $e) {
        error_log("PDF Editor table creation error: " . $e->getMessage());
        return false;
    }
}

// Run cleanup on each request (you might want to run this less frequently)
if (rand(1, 100) <= 10) { // 10% chance to clean on each request
    cleanTempFiles();
}

// Initialize session if needed
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Initialize tables on first run
if (!isset($_SESSION['pdf_editor_tables_initialized'])) {
    if (initializePDFEditorTables()) {
        $_SESSION['pdf_editor_tables_initialized'] = true;
    }
}

// Log activity - בדיוק כמו בבתי עלמין
function logActivity($action, $module, $itemId, $details = []) {
    // TODO: להוסיף מערכת לוגים
    error_log("[$module] $action on item #$itemId");
}
?>