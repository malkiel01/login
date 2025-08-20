<?php
/**
 * דף בדיקת חיבור למסד נתונים
 * test_db.php
 */

// הפעל הצגת שגיאות
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "<html dir='rtl'><head><meta charset='UTF-8'><title>בדיקת חיבור</title></head><body>";
echo "<h1>בדיקת חיבור למסד נתונים</h1>";
echo "<div style='font-family: Arial; direction: rtl;'>";

// כלול את קובץ ההגדרות
require_once 'config.php';

echo "<h2>1. בדיקת טעינת ENV:</h2>";
echo "<div style='background: #f0f0f0; padding: 10px; margin: 10px 0;'>";
echo "DB_HOST: " . DB_HOST . "<br>";
echo "DB_PORT: " . DB_PORT . "<br>";
echo "DB_NAME: " . DB_NAME . "<br>";
echo "DB_USER: " . DB_USER . "<br>";
echo "DB_PASSWORD: " . (empty(DB_PASSWORD) ? "(ריק)" : "***מוגדר***") . "<br>";
echo "GOOGLE_CLIENT_ID: " . (empty(GOOGLE_CLIENT_ID) ? "(לא מוגדר)" : "***מוגדר***") . "<br>";
echo "</div>";

echo "<h2>2. ניסיון חיבור למסד נתונים:</h2>";
echo "<div style='background: #f0f0f0; padding: 10px; margin: 10px 0;'>";

try {
    // נסה להתחבר
    $pdo = getDBConnection();
    echo "<span style='color: green; font-weight: bold;'>✅ החיבור הצליח!</span><br><br>";
    
    // בדוק גרסת MySQL
    $version = $pdo->query('SELECT VERSION()')->fetchColumn();
    echo "גרסת MySQL: " . $version . "<br>";
    
    // בדוק איזה טבלאות קיימות
    echo "<br><strong>טבלאות במסד הנתונים:</strong><br>";
    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    
    if (count($tables) > 0) {
        foreach ($tables as $table) {
            echo "- " . $table . "<br>";
        }
    } else {
        echo "<span style='color: orange;'>אין טבלאות במסד הנתונים</span><br>";
    }
    
    // בדוק טבלת users אם קיימת
    if (in_array('users', $tables)) {
        echo "<br><strong>בדיקת טבלת users:</strong><br>";
        $count = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
        echo "מספר משתמשים: " . $count . "<br>";
        
        // הצג מבנה הטבלה
        echo "<br><strong>מבנה טבלת users:</strong><br>";
        $columns = $pdo->query("SHOW COLUMNS FROM users")->fetchAll();
        echo "<table border='1' style='border-collapse: collapse;'>";
        echo "<tr><th>שם עמודה</th><th>סוג</th><th>Null</th><th>Key</th></tr>";
        foreach ($columns as $col) {
            echo "<tr>";
            echo "<td>" . $col['Field'] . "</td>";
            echo "<td>" . $col['Type'] . "</td>";
            echo "<td>" . $col['Null'] . "</td>";
            echo "<td>" . $col['Key'] . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    
    // בדוק טבלאות של הקבוצות
    echo "<br><strong>בדיקת טבלאות קבוצות:</strong><br>";
    $groupTables = ['purchase_groups', 'group_members', 'group_purchases', 'group_invitations'];
    foreach ($groupTables as $table) {
        if (in_array($table, $tables)) {
            $count = $pdo->query("SELECT COUNT(*) FROM $table")->fetchColumn();
            echo "✅ $table - $count רשומות<br>";
        } else {
            echo "❌ $table - לא קיימת<br>";
        }
    }
    
} catch (PDOException $e) {
    echo "<span style='color: red; font-weight: bold;'>❌ החיבור נכשל!</span><br><br>";
    echo "<strong>שגיאה:</strong><br>";
    echo "<div style='background: #ffeeee; padding: 10px; border: 1px solid red;'>";
    echo $e->getMessage();
    echo "</div>";
    
    echo "<br><strong>פרטי החיבור שניסיתי:</strong><br>";
    echo "Host: " . DB_HOST . "<br>";
    echo "Port: " . DB_PORT . "<br>";
    echo "Database: " . DB_NAME . "<br>";
    echo "User: " . DB_USER . "<br>";
}

echo "</div>";

echo "<h2>3. בדיקת PHP:</h2>";
echo "<div style='background: #f0f0f0; padding: 10px; margin: 10px 0;'>";
echo "PHP Version: " . phpversion() . "<br>";
echo "PDO MySQL: " . (extension_loaded('pdo_mysql') ? '✅ מותקן' : '❌ לא מותקן') . "<br>";
echo "</div>";

echo "<h2>4. בדיקת קבצים:</h2>";
echo "<div style='background: #f0f0f0; padding: 10px; margin: 10px 0;'>";
$files = [
    '.env' => __DIR__ . '/.env',
    'config.php' => __DIR__ . '/config.php',
    'dashboard.php' => __DIR__ . '/dashboard.php',
    'auth/login.php' => __DIR__ . '/auth/login.php'
];

foreach ($files as $name => $path) {
    if (file_exists($path)) {
        echo "✅ $name - קיים<br>";
    } else {
        echo "❌ $name - לא נמצא<br>";
    }
}
echo "</div>";

echo "<br><hr><br>";
echo "<a href='dashboard.php'>לדף הראשי</a> | ";
echo "<a href='auth/login.php'>לדף התחברות</a> | ";
echo "<a href='debug.php'>לדף דיבאג מלא</a>";

echo "</div></body></html>";
?>