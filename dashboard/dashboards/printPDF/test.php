<?php
// test-json-support.php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<pre>";

require_once $_SERVER['DOCUMENT_ROOT'] . '/config.php';

$db = getDBConnection();

echo "1. Testing MySQL version and JSON support:\n";
$result = $db->query("SELECT VERSION() as version");
$row = $result->fetch();
echo "   MySQL Version: " . $row['version'] . "\n";

// Check if JSON type is supported
$version = floatval($row['version']);
if ($version < 5.7) {
    echo "   ⚠️ JSON column type requires MySQL 5.7+\n";
}

echo "\n2. Testing table creation with JSON column:\n";
try {
    $db->exec("
        CREATE TABLE IF NOT EXISTS `test_json_support` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `metadata` JSON
        )
    ");
    echo "   ✓ JSON column works\n";
    $db->exec("DROP TABLE IF EXISTS `test_json_support`");
} catch (PDOException $e) {
    echo "   ✗ JSON column failed: " . $e->getMessage() . "\n";
}

echo "\n3. Testing without JSON column:\n";
try {
    $db->exec("
        CREATE TABLE IF NOT EXISTS `test_no_json` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `metadata` TEXT
        )
    ");
    echo "   ✓ TEXT column works\n";
    $db->exec("DROP TABLE IF EXISTS `test_no_json`");
} catch (PDOException $e) {
    echo "   ✗ TEXT column failed: " . $e->getMessage() . "\n";
}

echo "\n4. Creating a minimal functions.php to test:\n";

$minimal_functions = '<?php
function getPDFEditorDB() {
    return getDBConnection();
}

function logActivity($action, $module = "pdf_editor", $details = "", $metadata = []) {
    error_log("Activity: $action");
}
?>';

file_put_contents(__DIR__ . '/test-functions.php', $minimal_functions);

echo "   Created test-functions.php\n";

echo "\n5. Loading the minimal version:\n";
require_once __DIR__ . '/test-functions.php';
echo "   ✓ Loaded successfully!\n";

$db_test = getPDFEditorDB();
echo "   ✓ getPDFEditorDB() works\n";

echo "</pre>";
?>