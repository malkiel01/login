<?php
// test-specific.php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<pre>";
echo "1. Loading main config...\n";
require_once $_SERVER['DOCUMENT_ROOT'] . '/config.php';
echo "   OK\n\n";

echo "2. Checking functions before PDF config:\n";
echo "   checkPermission exists: " . (function_exists('checkPermission') ? 'YES' : 'NO') . "\n";
echo "   generateCSRFToken exists: " . (function_exists('generateCSRFToken') ? 'YES' : 'NO') . "\n\n";

echo "3. Reading PDF config.php line by line:\n";
$config_file = __DIR__ . '/config.php';
$lines = file($config_file);
foreach ($lines as $num => $line) {
    $line_num = $num + 1;
    
    // Check for function definitions
    if (preg_match('/function\s+(\w+)\s*\(/', $line, $matches)) {
        echo "   Line $line_num: Defines function " . $matches[1] . "\n";
        if (function_exists($matches[1])) {
            echo "   ⚠️ WARNING: Function " . $matches[1] . " already exists!\n";
        }
    }
    
    // Check for require/include statements
    if (preg_match('/(require|include)(_once)?\s*[^\s]*functions\.php/', $line)) {
        echo "   Line $line_num: Tries to load functions.php\n";
    }
}
echo "\n";

echo "4. Now trying to load PDF config.php...\n";
ob_start();
$error = null;
try {
    require_once __DIR__ . '/config.php';
    echo "   SUCCESS!\n";
} catch (ParseError $e) {
    $error = "Parse Error: " . $e->getMessage() . " on line " . $e->getLine();
} catch (Error $e) {
    $error = "Fatal Error: " . $e->getMessage() . " on line " . $e->getLine();
} catch (Exception $e) {
    $error = "Exception: " . $e->getMessage();
}
$output = ob_get_clean();

if ($error) {
    echo "   FAILED: $error\n";
    echo "   Output before error:\n$output\n";
} else {
    echo "   Output:\n$output\n";
}

echo "</pre>";