<?php
// test-config-line-by-line.php
error_reporting(E_ALL);
ini_set('display_errors', 1);
set_time_limit(3);

echo "<pre>";

// First load main config
require_once $_SERVER['DOCUMENT_ROOT'] . '/config.php';
echo "Main config loaded\n\n";

// Now test each part of PDF config
echo "Testing PDF config parts:\n\n";

// Test basic defines
echo "1. Testing basic defines...\n";
define('DASHBOARD_NAME', 'עורך PDF ותמונות');
define('PDF_EDITOR_VERSION', '1.0.0');
echo "   ✓ Basic defines work\n\n";

// Test path defines
echo "2. Testing path defines...\n";
define('PDF_EDITOR_PATH', '/dashboard/dashboards/printPDF');
define('TEMP_PATH', '/dashboard/dashboards/printPDF/temp/');
echo "   ✓ Path defines work\n\n";

// Test TCPDF define
echo "3. Testing TCPDF define...\n";
define('TCPDF_PATH', '/dashboard/dashboards/printPDF/lib/tcpdf/');
echo "   ✓ TCPDF define works\n\n";

// Test checkPermission function
echo "4. Testing checkPermission function...\n";
if (!function_exists('checkPermission')) {
    function checkPermission($action, $module = 'pdf_editor') {
        return true;
    }
    echo "   ✓ checkPermission defined\n";
} else {
    echo "   ℹ checkPermission already exists\n";
}
echo "\n";

// Test generateCSRFToken function
echo "5. Testing generateCSRFToken function...\n";
if (!function_exists('generateCSRFToken')) {
    function generateCSRFToken() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if (!isset($_SESSION['pdf_editor_csrf'])) {
            $_SESSION['pdf_editor_csrf'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['pdf_editor_csrf'];
    }
    echo "   ✓ generateCSRFToken defined\n";
} else {
    echo "   ℹ generateCSRFToken already exists\n";
}
echo "\n";

// Test other functions
echo "6. Testing other config functions...\n";
if (!function_exists('verifyCSRFToken')) {
    function verifyCSRFToken($token) {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        return isset($_SESSION['pdf_editor_csrf']) && $token === $_SESSION['pdf_editor_csrf'];
    }
    echo "   ✓ verifyCSRFToken defined\n";
}

if (!function_exists('cleanTempFiles')) {
    function cleanTempFiles() {
        // Simple version
        return true;
    }
    echo "   ✓ cleanTempFiles defined\n";
}

if (!function_exists('logActivity')) {
    function logActivity($action, $module, $itemId = null, $details = []) {
        error_log("[$module] $action");
    }
    echo "   ✓ logActivity defined\n";
}

echo "\n7. All config parts work individually!\n";

echo "\n8. Now loading the actual config.php file:\n";
$config_content = file_get_contents($_SERVER['DOCUMENT_ROOT'] . '/dashboard/dashboards/printPDF/config.php');
echo "   File size: " . strlen($config_content) . " bytes\n";

// Check for problematic code
if (strpos($config_content, 'require_once') !== false && strpos($config_content, 'functions.php') !== false) {
    echo "   ⚠ Config tries to load functions.php - THIS IS THE PROBLEM!\n";
} else {
    echo "   ✓ Config doesn't load functions.php\n";
}

echo "</pre>";
?>