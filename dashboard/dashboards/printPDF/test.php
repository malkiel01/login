<?php
// check-table-structure.php
require_once $_SERVER['DOCUMENT_ROOT'] . '/config.php';

$db = getDBConnection();

echo "<pre>";
echo "מבנה הטבלה pdf_editor_activity_log:\n\n";

$columns = $db->query("SHOW COLUMNS FROM pdf_editor_activity_log")->fetchAll();

foreach ($columns as $col) {
    echo sprintf("%-15s %-20s %s\n", 
        $col['Field'], 
        $col['Type'],
        $col['Null'] === 'YES' ? 'NULL' : 'NOT NULL'
    );
}

echo "\nבעיה אפשרית: אם metadata מוגדר כ-JSON וה-MySQL לא תומך בזה.\n";

// נסה להכניס רשומת בדיקה
echo "\nניסיון להכניס רשומת בדיקה:\n";
try {
    $stmt = $db->prepare("
        INSERT INTO pdf_editor_activity_log 
        (user_id, action, module, details, metadata, created_at) 
        VALUES (?, ?, ?, ?, ?, NOW())
    ");
    
    $stmt->execute([
        0,
        'test',
        'pdf_editor',
        'test details',
        '{"test": "data"}'  // JSON as string
    ]);
    
    echo "✓ ההכנסה הצליחה!\n";
    
} catch (PDOException $e) {
    echo "✗ שגיאה: " . $e->getMessage() . "\n";
}

echo "\nהמסקנה: ";
echo "אם ההכנסה הצליחה, אפשר להפעיל את כל הפונקציות ב-functions.php\n";
echo "</pre>";
?>