<?php
// test-index-steps.php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<pre>";
echo "=== Testing index.php loading sequence ===\n\n";

echo "1. Loading config.php...\n";
require_once $_SERVER['DOCUMENT_ROOT'] . '/dashboard/dashboards/printPDF/config.php';
echo "   ✓ Config loaded\n\n";

echo "2. Checking what's defined after config:\n";
echo "   DASHBOARD_NAME: " . (defined('DASHBOARD_NAME') ? DASHBOARD_NAME : 'NOT DEFINED') . "\n";
echo "   checkPermission: " . (function_exists('checkPermission') ? 'EXISTS' : 'NOT EXISTS') . "\n";
echo "   generateCSRFToken: " . (function_exists('generateCSRFToken') ? 'EXISTS' : 'NOT EXISTS') . "\n";
echo "   getPDFEditorDB: " . (function_exists('getPDFEditorDB') ? 'EXISTS' : 'NOT EXISTS') . "\n\n";

echo "3. Checking functions.php file...\n";
$functions_file = $_SERVER['DOCUMENT_ROOT'] . '/dashboard/dashboards/printPDF/includes/functions.php';
if (file_exists($functions_file)) {
    echo "   File exists\n";
    
    // Check syntax
    $output = shell_exec('php -l ' . escapeshellarg($functions_file) . ' 2>&1');
    echo "   Syntax: " . trim($output) . "\n\n";
    
    echo "4. Loading functions.php...\n";
    ob_start();
    $error_handler = set_error_handler(function($severity, $message, $file, $line) {
        echo "   ERROR: $message in $file on line $line\n";
    });
    
    try {
        require_once $functions_file;
        echo "   ✓ Functions loaded\n";
    } catch (Exception $e) {
        echo "   ✗ Exception: " . $e->getMessage() . "\n";
    } catch (ParseError $e) {
        echo "   ✗ Parse Error: " . $e->getMessage() . "\n";
    } catch (Error $e) {
        echo "   ✗ Fatal Error: " . $e->getMessage() . "\n";
    } finally {
        restore_error_handler();
        $output = ob_get_clean();
        if ($output) {
            echo "   Output: $output\n";
        }
    }
} else {
    echo "   File NOT found\n";
}

echo "\n5. Testing permission check...\n";
if (function_exists('checkPermission')) {
    $result = checkPermission('view', 'pdf_editor');
    echo "   checkPermission returned: " . ($result ? 'TRUE' : 'FALSE') . "\n";
}

echo "\n6. Testing CSRF token generation...\n";
if (function_exists('generateCSRFToken')) {
    $token = generateCSRFToken();
    echo "   Token generated: " . substr($token, 0, 10) . "...\n";
}

echo "\n7. Testing database function...\n";
if (function_exists('getPDFEditorDB')) {
    try {
        $db = getPDFEditorDB();
        echo "   Database connection: " . ($db ? 'SUCCESS' : 'NULL') . "\n";
    } catch (Exception $e) {
        echo "   Database error: " . $e->getMessage() . "\n";
    }
}

echo "\n=== All tests completed ===\n";
echo "</pre>";
?>