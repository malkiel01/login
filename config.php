<?php
/**
 * קובץ הגדרות בלבד
 * config.php
 */

// הגדרות חיבור למסד נתונים
define('DB_HOST', 'mbe-plus.com');
define('DB_NAME', 'mbeplusc_test');
define('DB_USER', 'mbeplusc_test');
define('DB_PASSWORD', 'Gxfv16be');
define('DB_CHARSET', 'utf8mb4');

// הגדרות כלליות
define('SITE_NAME', 'מערכת ניהול קניות פנאן בפקאן 2025');
define('TIMEZONE', 'Asia/Jerusalem');
define('CURRENCY_SYMBOL', '₪');

// הגדרות העלאת קבצים
define('UPLOAD_DIR', 'uploads/');
define('MAX_FILE_SIZE', 5242880);

// פונקציית חיבור
function getDBConnection() {
    try {
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
        $pdo = new PDO($dsn, DB_USER, DB_PASSWORD);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return $pdo;
    } catch (PDOException $e) {
        die("Connection failed: " . $e->getMessage());
    }
}
?>