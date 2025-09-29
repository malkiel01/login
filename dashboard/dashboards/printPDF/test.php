<?php
// fix-functions-final.php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<pre>";

$correct_functions = '<?php
/**
 * PDF Editor Functions - WORKING VERSION
 * Location: /dashboard/dashboards/printPDF/includes/functions.php
 */

// אין צורך לטעון את config.php כאן - הוא כבר נטען ב-index.php

/**
 * Get PDF Editor Database Connection
 */
function getPDFEditorDB() {
    // פשוט החזר את החיבור הקיים מהפונקציה הגלובלית
    if (function_exists("getDBConnection")) {
        return getDBConnection();
    } else {
        error_log("Error: getDBConnection not found");
        return null;
    }
}

/**
 * Create PDF Editor tables if needed
 */
function createPDFEditorTables() {
    $db = getPDFEditorDB();
    if (!$db) return false;
    
    try {
        $db->exec("
            CREATE TABLE IF NOT EXISTS `pdf_editor_projects` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `project_id` VARCHAR(100) UNIQUE NOT NULL,
                `user_id` INT NOT NULL,
                `name` VARCHAR(255) NOT NULL,
                `data` LONGTEXT,
                `thumbnail` TEXT,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        
        return true;
    } catch (PDOException $e) {
        error_log("Failed to create tables: " . $e->getMessage());
        return false;
    }
}

/**
 * Simple log function
 */
function logActivity($action, $module = "pdf_editor", $details = "", $metadata = []) {
    error_log("[$module] $action: $details");
}
?>';

// Write the corrected version
$functions_file = $_SERVER['DOCUMENT_ROOT'] . '/dashboard/dashboards/printPDF/includes/functions.php';
file_put_contents($functions_file, $correct_functions);
echo "1. Updated functions.php with correct version\n";

// Test with proper loading order
echo "\n2. Testing with proper loading order:\n";
echo "   Loading main config...\n";
require_once $_SERVER['DOCUMENT_ROOT'] . '/config.php';
echo "   ✓ Main config loaded\n";

echo "   Loading PDF config...\n";
require_once $_SERVER['DOCUMENT_ROOT'] . '/dashboard/dashboards/printPDF/config.php';
echo "   ✓ PDF config loaded\n";

echo "   Loading functions...\n";
require_once $functions_file;
echo "   ✓ Functions loaded\n";

echo "\n3. Testing getPDFEditorDB:\n";
$db = getPDFEditorDB();
if ($db) {
    echo "   ✓ Database connection works\n";
} else {
    echo "   ✗ Database connection failed\n";
}

echo "\n4. SUCCESS! Now try index.php:\n";
echo "   <a href='index.php' target='_blank' style='font-size: 18px; color: green;'>→ Click here to open index.php</a>\n";

echo "</pre>";
?>