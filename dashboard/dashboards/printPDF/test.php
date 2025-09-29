<?php
// check-tables.php
require_once $_SERVER['DOCUMENT_ROOT'] . '/config.php';

$db = getDBConnection();

echo "<pre>";
echo "בדיקת טבלאות PDF Editor:\n\n";

// בדוק אילו טבלאות קיימות
$tables = $db->query("SHOW TABLES LIKE 'pdf_editor_%'")->fetchAll(PDO::FETCH_COLUMN);

echo "טבלאות קיימות:\n";
if (empty($tables)) {
    echo "  ❌ אין טבלאות של PDF Editor\n";
} else {
    foreach ($tables as $table) {
        echo "  ✓ $table\n";
    }
}

echo "\nניסיון ליצור את הטבלאות החסרות:\n";

// נסה ליצור רק את הטבלה הבסיסית
try {
    $db->exec("
        CREATE TABLE IF NOT EXISTS `pdf_editor_activity_log` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `user_id` INT NOT NULL,
            `action` VARCHAR(100) NOT NULL,
            `module` VARCHAR(50) NOT NULL,
            `details` TEXT,
            `metadata` TEXT,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    echo "  ✓ טבלת activity_log נוצרה/קיימת\n";
} catch (PDOException $e) {
    echo "  ❌ שגיאה: " . $e->getMessage() . "\n";
}

// בדוק שוב
$tables = $db->query("SHOW TABLES LIKE 'pdf_editor_%'")->fetchAll(PDO::FETCH_COLUMN);
echo "\nטבלאות אחרי היצירה:\n";
foreach ($tables as $table) {
    echo "  ✓ $table\n";
}

echo "\nעכשיו אפשר להפעיל את הפונקציה logActivity בלי בעיה.\n";
echo "</pre>";
?>