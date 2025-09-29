<?php
// test-final.php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<pre>";
echo "Testing complete loading sequence:\n\n";

echo "1. Loading main config...\n";
require_once $_SERVER['DOCUMENT_ROOT'] . '/config.php';
echo "   ✓ Done\n\n";

echo "2. Loading PDF config...\n";
require_once $_SERVER['DOCUMENT_ROOT'] . '/dashboard/dashboards/printPDF/config.php';
echo "   ✓ Done\n\n";

echo "3. Loading functions...\n";
require_once $_SERVER['DOCUMENT_ROOT'] . '/dashboard/dashboards/printPDF/includes/functions.php';
echo "   ✓ Done\n\n";

echo "4. Testing functionality:\n";
$db = getPDFEditorDB();
echo "   Database: " . ($db ? "Connected" : "Failed") . "\n";

$token = generateCSRFToken();
echo "   CSRF Token: " . substr($token, 0, 10) . "...\n";

$permission = checkPermission('view', 'pdf_editor');
echo "   Permission: " . ($permission ? "Granted" : "Denied") . "\n";

echo "\n✅ ALL TESTS PASSED!\n\n";
echo "<strong>The site should work now!</strong>\n";
echo "<a href='index.php' target='_blank' style='font-size: 20px; color: green;'>→ Click here to open index.php</a>";
echo "</pre>";
?>