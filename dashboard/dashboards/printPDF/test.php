<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>Config Step-by-Step Test</h2><pre>";

// Step 1: Load main config first
echo "1. Loading main config.php...\n";
require_once $_SERVER['DOCUMENT_ROOT'] . '/config.php';
echo "   ✓ Main config loaded\n";
echo "   getDBConnection exists: " . (function_exists('getDBConnection') ? 'YES' : 'NO') . "\n\n";

// Step 2: Define basic constants that config.php needs
echo "2. Defining PDF Editor constants...\n";
define('DASHBOARD_NAME', 'עורך PDF ותמונות');
define('PDF_EDITOR_VERSION', '1.0.0');
define('PDF_EDITOR_PATH', dirname(__FILE__));
define('PDF_EDITOR_URL', '/dashboard/dashboards/printPDF/');
echo "   ✓ Constants defined\n\n";

// Step 3: Try to define the checkPermission function
echo "3. Creating checkPermission function...\n";
if (!function_exists('checkPermission')) {
    function checkPermission($action, $module = 'pdf_editor') {
        return true;
    }
    echo "   ✓ Function created\n";
} else {
    echo "   ℹ Function already exists\n";
}
echo "\n";

// Step 4: Try to define generateCSRFToken
echo "4. Creating generateCSRFToken function...\n";
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
    echo "   ✓ Function created\n";
} else {
    echo "   ℹ Function already exists\n";
}
echo "\n";

// Step 5: Test if we can load functions.php
echo "5. Testing functions.php load...\n";
$functions_file = __DIR__ . '/includes/functions.php';
if (file_exists($functions_file)) {
    try {
        require_once $functions_file;
        echo "   ✓ Functions loaded successfully!\n";
        
        // Test getPDFEditorDB
        if (function_exists('getPDFEditorDB')) {
            echo "   ✓ getPDFEditorDB exists\n";
            
            // Try to use it
            $db = getPDFEditorDB();
            if ($db) {
                echo "   ✓ Database connection successful via getPDFEditorDB!\n";
            } else {
                echo "   ✗ getPDFEditorDB returned null\n";
            }
        } else {
            echo "   ✗ getPDFEditorDB not found\n";
        }
    } catch (Exception $e) {
        echo "   ✗ Error loading functions: " . $e->getMessage() . "\n";
    }
} else {
    echo "   ✗ functions.php not found\n";
}
echo "\n";

// Step 6: Now try to load the actual config.php
echo "6. Loading PDF Editor config.php...\n";
$pdf_config = __DIR__ . '/config.php';
if (file_exists($pdf_config)) {
    
    // Read the file to check for problems
    $content = file_get_contents($pdf_config);
    
    // Check for specific issues
    if (strpos($content, 'require_once') !== false && strpos($content, '/includes/functions.php') !== false) {
        echo "   ⚠ Config tries to load functions.php\n";
    }
    
    // Check for TCPDF
    if (strpos($content, 'TCPDF_PATH') !== false) {
        echo "   ℹ Config defines TCPDF_PATH\n";
        
        // Check if TCPDF exists
        $tcpdf_path = __DIR__ . '/lib/tcpdf/';
        if (!is_dir($tcpdf_path)) {
            echo "   ⚠ TCPDF directory doesn't exist at: $tcpdf_path\n";
        }
    }
    
    try {
        require_once $pdf_config;
        echo "   ✓ Config loaded successfully!\n";
    } catch (Exception $e) {
        echo "   ✗ Error loading config: " . $e->getMessage() . "\n";
    }
} else {
    echo "   ✗ config.php not found\n";
}
echo "\n";

// Step 7: Test complete index.php load
echo "7. Testing if index.php would work now...\n";
$token = generateCSRFToken();
echo "   CSRF Token: " . substr($token, 0, 10) . "...\n";
echo "   Permission check: " . (checkPermission('view', 'pdf_editor') ? 'PASS' : 'FAIL') . "\n";
echo "   Database: " . (function_exists('getDBConnection') ? 'Available' : 'Not available') . "\n";

echo "\n✅ All tests completed!</pre>";

echo "<hr><h3>Try loading index.php now:</h3>";
echo "<a href='index.php' target='_blank'>Open index.php</a>";
?>