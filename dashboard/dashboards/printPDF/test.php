<?php
// test-db-function.php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<pre>";

// Load config first
require_once $_SERVER['DOCUMENT_ROOT'] . '/dashboard/dashboards/printPDF/config.php';
echo "Config loaded\n\n";

// Now test the database function step by step
echo "Testing getPDFEditorDB function:\n\n";

// Manually create the function to test
echo "1. Calling getDBConnection()...\n";
try {
    $db = getDBConnection();
    echo "   ✓ Got connection\n";
    
    echo "\n2. Testing if we can run a simple query...\n";
    $result = $db->query("SELECT 1");
    echo "   ✓ Simple query works\n";
    
    echo "\n3. Testing CREATE TABLE IF NOT EXISTS...\n";
    $test_sql = "
        CREATE TABLE IF NOT EXISTS `test_pdf_editor` (
            `id` INT AUTO_INCREMENT PRIMARY KEY
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ";
    $db->exec($test_sql);
    echo "   ✓ CREATE TABLE works\n";
    
    echo "\n4. Now testing the actual PDF Editor tables creation...\n";
    
    // Test the projects table
    echo "   Creating pdf_editor_projects table...\n";
    $sql1 = "
        CREATE TABLE IF NOT EXISTS `pdf_editor_projects` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `project_id` VARCHAR(100) UNIQUE NOT NULL,
            `user_id` INT NOT NULL,
            `name` VARCHAR(255) NOT NULL,
            `data` LONGTEXT,
            `thumbnail` TEXT,
            `is_template` BOOLEAN DEFAULT 0,
            `template_category` VARCHAR(50),
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_user_id (user_id),
            INDEX idx_project_id (project_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ";
    
    $db->exec($sql1);
    echo "   ✓ pdf_editor_projects created\n";
    
    echo "\n5. Creating autosave table with foreign key...\n";
    $sql2 = "
        CREATE TABLE IF NOT EXISTS `pdf_editor_autosave` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `project_id` VARCHAR(100) NOT NULL,
            `state_data` LONGTEXT,
            `saved_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_project (project_id),
            FOREIGN KEY (project_id) REFERENCES pdf_editor_projects(project_id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ";
    
    try {
        $db->exec($sql2);
        echo "   ✓ pdf_editor_autosave created\n";
    } catch (PDOException $e) {
        echo "   ✗ Error: " . $e->getMessage() . "\n";
    }
    
} catch (Exception $e) {
    echo "   ✗ Error: " . $e->getMessage() . "\n";
}

echo "</pre>";
?>