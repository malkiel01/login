<?php
/**
 * קובץ הגדרות עם טעינת ENV
 * config.php
 */

// פונקציה פשוטה לטעינת קובץ .env
function loadEnv($path = __DIR__ . '/.env') {
    if (!file_exists($path)) {
        die("Error: .env file not found at: $path");
    }
    
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        // דלג על הערות
        if (strpos(trim($line), '#') === 0) {
            continue;
        }
        
        // פרק את השורה ל-key=value
        $parts = explode('=', $line, 2);
        if (count($parts) == 2) {
            $key = trim($parts[0]);
            $value = trim($parts[1]);
            
            // הסר מרכאות אם יש
            $value = trim($value, '"\'');
            
            // הגדר כמשתנה סביבה
            putenv("$key=$value");
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        }
    }
}

// טען את קובץ ה-ENV
loadEnv();

// הגדרות חיבור למסד נתונים מה-ENV
define('DB_HOST', $_ENV['DB_HOST'] ?? 'localhost');
define('DB_NAME', $_ENV['DB_NAME'] ?? 'database');
define('DB_USER', $_ENV['DB_USER'] ?? 'root');
define('DB_PASSWORD', $_ENV['DB_PASSWORD'] ?? '');
define('DB_CHARSET', $_ENV['DB_CHARSET'] ?? 'utf8mb4');
// אל תשתמש בפורט 8080! זה לא פורט של MySQL
// define('DB_PORT', $_ENV['PORT'] ?? '3306'); // <- מחק את זה!

// הגדרות Google Auth
define('GOOGLE_CLIENT_ID', $_ENV['CLIENT_ID'] ?? '');

// הגדרות כלליות
define('SITE_NAME', 'חברה קדישא לעדת המערבים');
define('TIMEZONE', 'Asia/Jerusalem');
// define('CURRENCY_SYMBOL', '₪');
if (!defined('CURRENCY_SYMBOL')) {
    define('CURRENCY_SYMBOL', '₪');
}

// הגדרות העלאת קבצים
define('UPLOAD_DIR', 'uploads/');
define('MAX_FILE_SIZE', 5242880);

// הגדרת סביבת עבודה
define('ENVIRONMENT', $_ENV['ENVIRONMENT'] ?? 'production');

// הצג שגיאות רק בסביבת פיתוח
if (ENVIRONMENT === 'development') {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}

// פונקציית חיבור משופרת - בלי פורט!
function getDBConnection() {
    static $pdo = null;
    
    if ($pdo === null) {
        try {
            // חיבור בלי פורט - בדיוק כמו שעבד קודם!
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES " . DB_CHARSET
            ];
            
            $pdo = new PDO($dsn, DB_USER, DB_PASSWORD, $options);
            
        } catch (PDOException $e) {
            // בסביבת production - אל תחשוף פרטי שגיאה
            if (ENVIRONMENT === 'development') {
                die("Connection failed: " . $e->getMessage());
            } else {
                // רשום ל-log
                error_log("Database connection error: " . $e->getMessage());
                die("שגיאת מערכת. אנא נסה שוב מאוחר יותר.");
            }
        }
    }
    
    return $pdo;
}

// פונקציה לבדיקת קיום קובץ ENV (לדיבאג)
function checkEnvFile() {
    $envPath = __DIR__ . '/.env';
    $envExamplePath = __DIR__ . '/.env.example';
    
    if (!file_exists($envPath)) {
        if (file_exists($envExamplePath)) {
            echo "<div style='background: #ffcccc; padding: 20px; margin: 20px; border: 2px solid #ff0000;'>";
            echo "<h2>קובץ .env לא נמצא!</h2>";
            echo "<p>יש ליצור קובץ .env בתיקייה הראשית.</p>";
            echo "<p>ניתן להעתיק את .env.example ולערוך אותו.</p>";
            echo "</div>";
            die();
        }
    }
}

// בדוק אם קובץ ENV קיים (רק בסביבת פיתוח)
if (ENVIRONMENT === 'development') {
    checkEnvFile();
}
?>