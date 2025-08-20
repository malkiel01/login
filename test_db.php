<?php
/**
 * בדיקת חיבור אמיתית - מראה את כל השגיאות
 * test_real.php
 */

// הפעל הצגת כל השגיאות
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "<html dir='rtl'><head><meta charset='UTF-8'><title>בדיקה אמיתית</title></head><body>";
echo "<h1>בדיקת חיבור אמיתית למסד נתונים</h1>";
echo "<div style='font-family: Arial; direction: rtl;'>";

// טען את קובץ ENV באופן ידני
$envPath = __DIR__ . '/.env';
if (file_exists($envPath)) {
    echo "<h2>קובץ ENV נמצא ✅</h2>";
    $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $env = [];
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        $parts = explode('=', $line, 2);
        if (count($parts) == 2) {
            $key = trim($parts[0]);
            $value = trim($parts[1], '"\'');
            $env[$key] = $value;
        }
    }
    
    echo "<div style='background: #f0f0f0; padding: 10px;'>";
    echo "DB_HOST: " . $env['DB_HOST'] . "<br>";
    echo "PORT: " . $env['PORT'] . "<br>";
    echo "DB_NAME: " . $env['DB_NAME'] . "<br>";
    echo "DB_USER: " . $env['DB_USER'] . "<br>";
    echo "</div>";
} else {
    echo "<h2 style='color: red;'>קובץ ENV לא נמצא! ❌</h2>";
    die();
}

echo "<h2>ניסיונות חיבור:</h2>";

// ניסיון 1: חיבור עם הפורט בנפרד
echo "<h3>ניסיון 1 - פורט בנפרד:</h3>";
echo "<div style='background: #f0f0f0; padding: 10px; margin: 10px 0;'>";
try {
    $dsn = "mysql:host=" . $env['DB_HOST'] . ";port=" . $env['PORT'] . ";dbname=" . $env['DB_NAME'] . ";charset=utf8mb4";
    echo "DSN: " . $dsn . "<br>";
    
    $pdo = new PDO($dsn, $env['DB_USER'], $env['DB_PASSWORD']);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "<span style='color: green; font-weight: bold;'>✅ הצלחה!</span><br>";
    $version = $pdo->query('SELECT VERSION()')->fetchColumn();
    echo "MySQL Version: " . $version . "<br>";
    
} catch (PDOException $e) {
    echo "<span style='color: red; font-weight: bold;'>❌ נכשל!</span><br>";
    echo "שגיאה: " . $e->getMessage() . "<br>";
}
echo "</div>";

// ניסיון 2: חיבור עם הפורט בתוך ה-host
echo "<h3>ניסיון 2 - פורט ב-HOST:</h3>";
echo "<div style='background: #f0f0f0; padding: 10px; margin: 10px 0;'>";
try {
    $host_with_port = $env['DB_HOST'] . ":" . $env['PORT'];
    $dsn = "mysql:host=" . $host_with_port . ";dbname=" . $env['DB_NAME'] . ";charset=utf8mb4";
    echo "DSN: " . $dsn . "<br>";
    
    $pdo = new PDO($dsn, $env['DB_USER'], $env['DB_PASSWORD']);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "<span style='color: green; font-weight: bold;'>✅ הצלחה!</span><br>";
    $version = $pdo->query('SELECT VERSION()')->fetchColumn();
    echo "MySQL Version: " . $version . "<br>";
    
} catch (PDOException $e) {
    echo "<span style='color: red; font-weight: bold;'>❌ נכשל!</span><br>";
    echo "שגיאה: " . $e->getMessage() . "<br>";
}
echo "</div>";

// ניסיון 3: חיבור עם mysqli
echo "<h3>ניסיון 3 - MySQLi:</h3>";
echo "<div style='background: #f0f0f0; padding: 10px; margin: 10px 0;'>";
$mysqli = @new mysqli($env['DB_HOST'], $env['DB_USER'], $env['DB_PASSWORD'], $env['DB_NAME'], $env['PORT']);

if ($mysqli->connect_error) {
    echo "<span style='color: red; font-weight: bold;'>❌ נכשל!</span><br>";
    echo "שגיאה: " . $mysqli->connect_error . "<br>";
} else {
    echo "<span style='color: green; font-weight: bold;'>✅ הצלחה!</span><br>";
    echo "MySQL Version: " . $mysqli->server_info . "<br>";
    $mysqli->close();
}
echo "</div>";

// ניסיון 4: בדיקת DNS
echo "<h3>בדיקת DNS:</h3>";
echo "<div style='background: #f0f0f0; padding: 10px; margin: 10px 0;'>";
$ip = gethostbyname($env['DB_HOST']);
echo "IP של " . $env['DB_HOST'] . ": " . $ip . "<br>";

if ($ip === $env['DB_HOST']) {
    echo "<span style='color: orange;'>⚠️ לא הצלחתי לפתור את כתובת ה-DNS</span><br>";
} else {
    echo "<span style='color: green;'>✅ DNS נפתר בהצלחה</span><br>";
}
echo "</div>";

// ניסיון 5: בדיקת חיבור TCP
echo "<h3>בדיקת חיבור TCP:</h3>";
echo "<div style='background: #f0f0f0; padding: 10px; margin: 10px 0;'>";
$connection = @fsockopen($env['DB_HOST'], $env['PORT'], $errno, $errstr, 5);
if ($connection) {
    echo "<span style='color: green;'>✅ פורט " . $env['PORT'] . " פתוח</span><br>";
    fclose($connection);
} else {
    echo "<span style='color: red;'>❌ לא ניתן להתחבר לפורט " . $env['PORT'] . "</span><br>";
    echo "שגיאה #$errno: $errstr<br>";
}
echo "</div>";

// הצגת הגדרות PHP
echo "<h3>הגדרות PHP:</h3>";
echo "<div style='background: #f0f0f0; padding: 10px; margin: 10px 0;'>";
echo "PHP Version: " . phpversion() . "<br>";
echo "PDO: " . (extension_loaded('pdo') ? '✅' : '❌') . "<br>";
echo "PDO MySQL: " . (extension_loaded('pdo_mysql') ? '✅' : '❌') . "<br>";
echo "MySQLi: " . (extension_loaded('mysqli') ? '✅' : '❌') . "<br>";
echo "</div>";

echo "</div></body></html>";
?>