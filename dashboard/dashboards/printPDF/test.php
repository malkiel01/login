<?php
// test-create-working-functions.php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<pre>";

$working_functions = '<?php
/**
 * PDF Editor Functions - MINIMAL WORKING VERSION
 * Location: /dashboard/dashboards/printPDF/includes/functions.php
 */

/**
 * Get PDF Editor Database Connection
 */
function getPDFEditorDB() {
    // פשוט החזר את החיבור הקיים
    return getDBConnection();
}

/**
 * Create PDF Editor tables if needed
 * Call this manually when needed, not automatically
 */
function createPDFEditorTables() {
    $db = getDBConnection();
    if (!$db) return false;
    
    try {
        // Create only the essential table for now
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
    // For now, just log to error_log
    error_log("[$module] $action: $details");
}
?>';

// Create backup of current functions.php
$current_file = $_SERVER['DOCUMENT_ROOT'] . '/dashboard/dashboards/printPDF/includes/functions.php';
$backup_file = $_SERVER['DOCUMENT_ROOT'] . '/dashboard/dashboards/printPDF/includes/functions.backup.php';

if (file_exists($current_file)) {
    copy($current_file, $backup_file);
    echo "1. Created backup: functions.backup.php\n";
}

// Write the new working version
file_put_contents($current_file, $working_functions);
echo "2. Created new functions.php (minimal version)\n";

// Test it
echo "3. Testing the new file:\n";
require_once $current_file;
echo "   ✓ Loaded successfully\n";

$db = getPDFEditorDB();
if ($db) {
    echo "   ✓ getPDFEditorDB() works\n";
}

echo "\n4. Now test if index.php works:\n";
echo "   <a href='index.php' target='_blank'>Click here to test index.php</a>\n";

echo "</pre>";
?>