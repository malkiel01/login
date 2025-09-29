<?php
// show-real-error.php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "<pre>";

// נסה לטעון את index.php ולתפוס את השגיאה
echo "Attempting to load index.php components:\n\n";

echo "1. Config: ";
require_once $_SERVER['DOCUMENT_ROOT'] . '/dashboard/dashboards/printPDF/config.php';
echo "OK\n";

echo "2. Functions: ";
require_once $_SERVER['DOCUMENT_ROOT'] . '/dashboard/dashboards/printPDF/includes/functions.php';
echo "OK\n";

echo "3. Permission check: ";
$result = checkPermission('view', 'pdf_editor');
echo ($result ? "OK" : "FAILED") . "\n";

echo "4. CSRF token: ";
$token = generateCSRFToken();
echo "OK\n";

echo "\nEverything works in isolation.\n";
echo "The error 500 might be from:\n";
echo "- Missing CSS/JS files\n";
echo "- PHP timeout\n";
echo "- Memory limit\n";

// בדוק את error log
$error_log = ini_get('error_log');
echo "\nError log location: $error_log\n";

if (file_exists($error_log)) {
    echo "Last 5 errors:\n";
    $errors = array_slice(file($error_log), -5);
    foreach ($errors as $error) {
        echo $error;
    }
}
?>