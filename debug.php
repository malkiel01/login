<?php
// הצגת כל השגיאות
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "<h1>בדיקת הגדרות המערכת</h1>";
echo "<div style='direction: rtl; font-family: Arial;'>";

// 1. בדיקת PHP
echo "<h2>1. גרסת PHP:</h2>";
echo "PHP Version: " . phpversion() . "<br>";

// 2. בדיקת Session
echo "<h2>2. בדיקת Session:</h2>";
session_start();
$_SESSION['test'] = 'working';
echo "Session Test: " . (isset($_SESSION['test']) ? "✅ עובד" : "❌ לא עובד") . "<br>";

// 3. בדיקת קובץ config.php
echo "<h2>3. בדיקת קובץ config.php:</h2>";
$config_path = __DIR__ . '/config.php';
if (file_exists($config_path)) {
    echo "✅ קובץ config.php קיים<br>";
    require_once 'config.php';
    echo "✅ הקובץ נטען בהצלחה<br>";
} else {
    echo "❌ קובץ config.php לא נמצא בנתיב: " . $config_path . "<br>";
}

// 4. בדיקת חיבור למסד נתונים
echo "<h2>4. בדיקת חיבור למסד נתונים:</h2>";
try {
    $pdo = getDBConnection();
    echo "✅ חיבור למסד נתונים הצליח<br>";
    
    // בדיקת טבלת users
    $stmt = $pdo->query("SHOW TABLES LIKE 'users'");
    if ($stmt->rowCount() > 0) {
        echo "✅ טבלת users קיימת<br>";
        
        // בדיקת מבנה הטבלה
        $columns = $pdo->query("SHOW COLUMNS FROM users")->fetchAll(PDO::FETCH_COLUMN);
        echo "עמודות בטבלה: " . implode(", ", $columns) . "<br>";
    } else {
        echo "❌ טבלת users לא קיימת - צריך להריץ את ה-SQL<br>";
        echo "<pre style='background: #f0f0f0; padding: 10px; border-radius: 5px;'>
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255),
    google_id VARCHAR(255) UNIQUE,
    name VARCHAR(100),
    profile_picture VARCHAR(255),
    auth_type ENUM('local', 'google') DEFAULT 'local',
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_login TIMESTAMP NULL,
    INDEX idx_email (email),
    INDEX idx_google_id (google_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
</pre>";
    }
    
    // בדיקת טבלאות families ו-purchases
    $stmt = $pdo->query("SHOW TABLES LIKE 'families'");
    echo "טבלת families: " . ($stmt->rowCount() > 0 ? "✅ קיימת" : "❌ לא קיימת") . "<br>";
    
    $stmt = $pdo->query("SHOW TABLES LIKE 'purchases'");
    echo "טבלת purchases: " . ($stmt->rowCount() > 0 ? "✅ קיימת" : "❌ לא קיימת") . "<br>";
    
} catch (PDOException $e) {
    echo "❌ שגיאה בחיבור למסד נתונים: " . $e->getMessage() . "<br>";
}

// 5. בדיקת מבנה התיקיות
echo "<h2>5. בדיקת מבנה התיקיות:</h2>";
$directories = [
    'auth' => __DIR__ . '/auth',
    'css' => __DIR__ . '/css',
    'uploads' => __DIR__ . '/uploads'
];

foreach ($directories as $name => $path) {
    if (is_dir($path)) {
        echo "✅ תיקיית $name קיימת<br>";
    } else {
        echo "❌ תיקיית $name לא קיימת בנתיב: $path<br>";
        // ניסיון ליצור את התיקייה
        if (mkdir($path, 0777, true)) {
            echo "✅ תיקיית $name נוצרה בהצלחה<br>";
        }
    }
}

// 6. בדיקת קבצים קריטיים
echo "<h2>6. בדיקת קבצים:</h2>";
$files = [
    'index2.php' => __DIR__ . '/index2.php',
    'auth/login.php' => __DIR__ . '/auth/login.php',
    'auth/logout.php' => __DIR__ . '/auth/logout.php',
    'css/styles.css' => __DIR__ . '/css/styles.css'
];

foreach ($files as $name => $path) {
    if (file_exists($path)) {
        echo "✅ קובץ $name קיים<br>";
    } else {
        echo "❌ קובץ $name לא נמצא בנתיב: $path<br>";
    }
}

// 7. יצירת משתמש בדיקה
echo "<h2>7. יצירת משתמש בדיקה:</h2>";
if (isset($pdo)) {
    try {
        // בדיקה אם המשתמש כבר קיים
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = 'test'");
        $stmt->execute();
        
        if ($stmt->fetch()) {
            echo "משתמש test כבר קיים במערכת<br>";
        } else {
            // יצירת משתמש חדש
            $hashedPassword = password_hash('test123', PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO users (username, email, password, name) VALUES (?, ?, ?, ?)");
            $stmt->execute(['test', 'test@example.com', $hashedPassword, 'משתמש בדיקה']);
            echo "✅ משתמש בדיקה נוצר בהצלחה!<br>";
            echo "שם משתמש: <strong>test</strong><br>";
            echo "סיסמה: <strong>test123</strong><br>";
        }
    } catch (Exception $e) {
        echo "❌ שגיאה ביצירת משתמש: " . $e->getMessage() . "<br>";
    }
}

echo "</div>";

// 8. לינקים מהירים
echo "<h2>לינקים לבדיקה:</h2>";
echo "<a href='auth/login.php'>דף התחברות</a><br>";
echo "<a href='index2.php'>דף ראשי (index2.php)</a><br>";

// 9. הצגת השגיאה האחרונה
echo "<h2>שגיאות PHP אחרונות:</h2>";
$error = error_get_last();
if ($error) {
    echo "<pre style='background: #ffeeee; padding: 10px; border: 1px solid #ff0000;'>";
    print_r($error);
    echo "</pre>";
} else {
    echo "אין שגיאות<br>";
}
?>