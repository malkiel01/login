<?php
// dashboard/dashboards/cemeteries/qa-test-fixed.php
// מערכת בדיקה תקינה - PHP בלבד

// הפעל דיווח שגיאות
error_reporting(E_ALL);
ini_set('display_errors', 1);

// התחל session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// הגדר משתני session לבדיקה
$_SESSION['user_id'] = 999999;
$_SESSION['dashboard_type'] = 'cemetery_manager';
$_SESSION['username'] = 'QA_TESTER';

// משתנים בסיסיים
$php_version = phpversion();
$session_active = (session_status() === PHP_SESSION_ACTIVE);
$db_connected = false;
$db_error = '';
$tables_data = array();

// נסה לקרוא קובץ ENV
$env_path = $_SERVER['DOCUMENT_ROOT'] . '/.env';
$env_exists = file_exists($env_path);
$db_config = array();

if ($env_exists) {
    $lines = file($env_path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        $parts = explode('=', $line, 2);
        if (count($parts) == 2) {
            $key = trim($parts[0]);
            $value = trim($parts[1], '"\'');
            if (in_array($key, array('DB_HOST', 'DB_NAME', 'DB_USER', 'DB_PASSWORD'))) {
                $db_config[$key] = $value;
            }
        }
    }
}

// נסה להתחבר למסד נתונים
if (!empty($db_config['DB_HOST']) && !empty($db_config['DB_NAME'])) {
    try {
        $dsn = "mysql:host=" . $db_config['DB_HOST'] . ";dbname=" . $db_config['DB_NAME'] . ";charset=utf8mb4";
        $user = isset($db_config['DB_USER']) ? $db_config['DB_USER'] : 'root';
        $pass = isset($db_config['DB_PASSWORD']) ? $db_config['DB_PASSWORD'] : '';
        
        $pdo = new PDO($dsn, $user, $pass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $db_connected = true;
        
        // קבל רשימת טבלאות
        $stmt = $pdo->query("SHOW TABLES");
        $all_tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        // בדוק טבלאות חשובות
        $important_tables = array('users', 'cemeteries', 'blocks', 'plots', 'graves', 'customers');
        foreach ($important_tables as $table) {
            if (in_array($table, $all_tables)) {
                $count_stmt = $pdo->query("SELECT COUNT(*) FROM `$table`");
                $count = $count_stmt->fetchColumn();
                $tables_data[$table] = $count;
            } else {
                $tables_data[$table] = -1;
            }
        }
        
    } catch (Exception $e) {
        $db_connected = false;
        $db_error = $e->getMessage();
    }
} else {
    $db_error = 'חסרים פרטי חיבור למסד נתונים';
}

// בדיקת קבצים
$files_to_check = array(
    'config.php' => $_SERVER['DOCUMENT_ROOT'] . '/config.php',
    '.env' => $env_path,
    'index.php' => __DIR__ . '/index.php',
    'forms/forms-config.php' => __DIR__ . '/forms/forms-config.php'
);

$files_status = array();
foreach ($files_to_check as $name => $path) {
    $files_status[$name] = file_exists($path);
}

?>
<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>בדיקת מערכת בתי עלמין</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: Arial, sans-serif; 
            background: #f5f5f5; 
            padding: 20px;
            direction: rtl;
        }
        .container {
            max-width: 1000px;
            margin: 0 auto;
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 {
            color: #333;
            border-bottom: 2px solid #667eea;
            padding-bottom: 10px;
            margin-bottom: 20px;
        }
        .status-box {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .status-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid #dee2e6;
        }
        .status-row:last-child { border-bottom: none; }
        .ok { color: green; font-weight: bold; }
        .error { color: red; font-weight: bold; }
        .warning { color: orange; font-weight: bold; }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        th, td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: right;
        }
        th { background: #f2f2f2; }
        .btn {
            display: inline-block;
            padding: 10px 20px;
            background: #667eea;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            margin: 5px;
        }
        .btn:hover { background: #5a67d8; }
        pre {
            background: #f4f4f4;
            padding: 10px;
            border-radius: 5px;
            overflow-x: auto;
            font-size: 12px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>בדיקת מערכת בתי עלמין</h1>
        
        <!-- סטטוס כללי -->
        <div class="status-box">
            <h2>סטטוס כללי</h2>
            <div class="status-row">
                <span>גרסת PHP</span>
                <span class="ok"><?php echo $php_version; ?></span>
            </div>
            <div class="status-row">
                <span>Session</span>
                <span class="<?php echo $session_active ? 'ok' : 'error'; ?>">
                    <?php echo $session_active ? 'פעיל' : 'לא פעיל'; ?>
                </span>
            </div>
            <div class="status-row">
                <span>מסד נתונים</span>
                <span class="<?php echo $db_connected ? 'ok' : 'error'; ?>">
                    <?php echo $db_connected ? 'מחובר' : 'לא מחובר - ' . htmlspecialchars($db_error); ?>
                </span>
            </div>
        </div>
        
        <!-- בדיקת קבצים -->
        <div class="status-box">
            <h2>קבצים במערכת</h2>
            <?php foreach ($files_status as $name => $exists): ?>
            <div class="status-row">
                <span><?php echo $name; ?></span>
                <span class="<?php echo $exists ? 'ok' : 'warning'; ?>">
                    <?php echo $exists ? 'קיים' : 'חסר'; ?>
                </span>
            </div>
            <?php endforeach; ?>
        </div>
        
        <?php if ($db_connected): ?>
        <!-- טבלאות במסד נתונים -->
        <div class="status-box">
            <h2>טבלאות במסד נתונים</h2>
            <table>
                <tr>
                    <th>שם טבלה</th>
                    <th>מספר רשומות</th>
                    <th>סטטוס</th>
                </tr>
                <?php foreach ($tables_data as $table => $count): ?>
                <tr>
                    <td><?php echo $table; ?></td>
                    <td><?php echo $count >= 0 ? number_format($count) : 'לא קיימת'; ?></td>
                    <td class="<?php echo $count >= 0 ? 'ok' : 'error'; ?>">
                        <?php echo $count >= 0 ? 'תקין' : 'חסר'; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </table>
        </div>
        <?php endif; ?>
        
        <!-- Session Data -->
        <div class="status-box">
            <h2>נתוני Session</h2>
            <pre><?php print_r($_SESSION); ?></pre>
        </div>
        
        <!-- קישורים לבדיקה -->
        <div class="status-box">
            <h2>קישורים לבדיקה</h2>
            <a href="index.php?bypass=true" class="btn">דשבורד ראשי</a>
            <a href="test-permissions.php" class="btn">בדיקת הרשאות</a>
            <a href="forms/test-form.php" class="btn">בדיקת טפסים</a>
            <a href="?phpinfo=1" class="btn">PHP Info</a>
        </div>
    </div>
</body>
</html>
<?php
// הצג phpinfo אם התבקש
if (isset($_GET['phpinfo'])) {
    phpinfo();
    exit;
}
?>