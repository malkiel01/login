<?php
// test-final-check.php
error_reporting(E_ALL);
ini_set('display_errors', 1);
set_time_limit(3); // הגבל ל-3 שניות

echo "<pre>";

// Load config
require_once $_SERVER['DOCUMENT_ROOT'] . '/dashboard/dashboards/printPDF/config.php';
echo "1. Config loaded\n";

// Create a simplified version of getPDFEditorDB to test
echo "2. Testing simplified getPDFEditorDB:\n";

function getPDFEditorDB_test() {
    echo "   - Entering getPDFEditorDB_test\n";
    static $initialized = false;
    
    echo "   - Getting DB connection...\n";
    $db = getDBConnection();
    echo "   - Got DB connection\n";
    
    if (!$initialized && $db) {
        echo "   - First run, would create tables here\n";
        $initialized = true;
    }
    
    echo "   - Returning DB\n";
    return $db;
}

// Test it
try {
    $db = getPDFEditorDB_test();
    echo "   ✓ Function completed successfully\n";
} catch (Exception $e) {
    echo "   ✗ Error: " . $e->getMessage() . "\n";
}

echo "\n3. Now checking the actual functions.php file:\n";

// Let's check if there's an issue with the static variable
$content = file_get_contents($_SERVER['DOCUMENT_ROOT'] . '/dashboard/dashboards/printPDF/includes/functions.php');

// Look for the exact getPDFEditorDB function
if (preg_match('/function getPDFEditorDB\(\)\s*\{(.*?)\n\}/s', $content, $matches)) {
    echo "   Found getPDFEditorDB function\n";
    $function_body = $matches[1];
    
    // Check what it does
    if (strpos($function_body, 'static $initialized') !== false) {
        echo "   - Uses static \$initialized\n";
    }
    if (strpos($function_body, 'getDBConnection()') !== false) {
        echo "   - Calls getDBConnection()\n";
    }
    if (strpos($function_body, 'createPDFEditorTables') !== false) {
        echo "   - Calls createPDFEditorTables\n";
    }
}

echo "\n4. Final test - load functions.php with error handler:\n";
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    echo "   ERROR: $errstr in " . basename($errfile) . " on line $errline\n";
    return true;
});

register_shutdown_function(function() {
    $error = error_get_last();
    if ($error && $error['type'] === E_ERROR) {
        echo "   FATAL: " . $error['message'] . " in " . basename($error['file']) . " on line " . $error['line'] . "\n";
    }
});

echo "   Attempting to load...\n";
require_once $_SERVER['DOCUMENT_ROOT'] . '/dashboard/dashboards/printPDF/includes/functions.php';
echo "   ✓ Loaded!\n";

echo "</pre>";
?>