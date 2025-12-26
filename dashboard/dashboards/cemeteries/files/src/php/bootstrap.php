<?php
/**
 * Bootstrap File
 *
 * טוען את המערכת - autoloader, config, error handling
 * נקודת כניסה ראשית לכל קובץ PHP שרוצה להשתמש במערכת החדשה
 *
 * @package PDFEditor
 * @version 1.0.0
 * @since Phase 2 Refactoring
 *
 * @example
 * require_once __DIR__ . '/src/php/bootstrap.php';
 *
 * use PDFEditor\Services\PDFService;
 * use PDFEditor\Core\Response;
 *
 * $pdfService = new PDFService();
 * // ...
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    define('ABSPATH', dirname(__DIR__, 2) . '/');
}

// ===================================
// Autoloader
// ===================================

/**
 * PSR-4 Autoloader
 *
 * טוען classes אוטומטית לפי namespace
 *
 * PDFEditor\Core\Response        -> src/php/core/Response.php
 * PDFEditor\Services\PDFService  -> src/php/services/PDFService.php
 */
spl_autoload_register(function ($class) {
    // Base namespace
    $baseNamespace = 'PDFEditor\\';

    // Check if class uses our namespace
    if (strpos($class, $baseNamespace) !== 0) {
        return;
    }

    // Remove base namespace
    $relativeClass = substr($class, strlen($baseNamespace));

    // Convert namespace to file path
    // PDFEditor\Core\Response -> Core/Response
    $file = str_replace('\\', '/', $relativeClass);

    // Build full path
    // src/php/Core/Response.php
    $filePath = __DIR__ . '/' . $file . '.php';

    // Load file if exists
    if (file_exists($filePath)) {
        require_once $filePath;
    }
});

// ===================================
// Load Config
// ===================================

// Load config class
require_once __DIR__ . '/../../config/config.php';

// Use config
use PDFEditor\Config;

// ===================================
// Error Handling
// ===================================

/**
 * Custom error handler
 *
 * ממיר errors ל-exceptions כך שניתן לתפוס אותם
 */
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    // Don't throw exception for suppressed errors (@)
    if (!(error_reporting() & $errno)) {
        return false;
    }

    throw new \ErrorException($errstr, 0, $errno, $errfile, $errline);
});

/**
 * Custom exception handler
 *
 * מטפל ב-exceptions לא נתפסים
 */
set_exception_handler(function($exception) {
    // Log error
    if (Config::LOG_ERRORS) {
        $logMessage = sprintf(
            "[%s] %s in %s:%d\n",
            date('Y-m-d H:i:s'),
            $exception->getMessage(),
            $exception->getFile(),
            $exception->getLine()
        );

        error_log($logMessage, 3, Config::ERROR_LOG_FILE);
    }

    // Return error response
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');

    $response = [
        'success' => false,
        'error' => 'שגיאת שרת פנימית'
    ];

    // Include details in debug mode
    if (Config::DEBUG_MODE) {
        $response['debug'] = [
            'message' => $exception->getMessage(),
            'file' => basename($exception->getFile()),
            'line' => $exception->getLine()
        ];
    }

    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit;
});

// ===================================
// Helper Functions
// ===================================

/**
 * Debug helper
 *
 * הדפסת debug רק במצב debug
 *
 * @param mixed $data נתונים להדפסה
 * @param string $label תווית אופציונלית
 */
function debug($data, $label = '') {
    if (!Config::DEBUG_MODE) {
        return;
    }

    if ($label) {
        echo "DEBUG [{$label}]: ";
    } else {
        echo "DEBUG: ";
    }

    print_r($data);
    echo "\n";
}

/**
 * Dump and die
 *
 * הדפס ועצור (לשימוש בדבאגינג בלבד)
 *
 * @param mixed $data נתונים להדפסה
 */
function dd($data) {
    echo "<pre>";
    print_r($data);
    echo "</pre>";
    die();
}

// ===================================
// Timezone
// ===================================

// Set timezone to Israel
date_default_timezone_set('Asia/Jerusalem');

// ===================================
// Session (if needed in future)
// ===================================

// Uncomment if you need sessions
// if (session_status() === PHP_SESSION_NONE) {
//     session_start();
// }

// ===================================
// Bootstrap Complete
// ===================================

// Optional: Log bootstrap completion in debug mode
if (Config::DEBUG_MODE) {
    error_log("[" . date('Y-m-d H:i:s') . "] PDF Editor system bootstrapped\n", 3, Config::ERROR_LOG_FILE);
}
