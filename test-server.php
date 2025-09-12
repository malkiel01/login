<?php
/**
 * API לבדיקת שרת ותקשורת
 * test-server-api.php
 */

// הגדרת headers
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
error_reporting(E_ALL);
ini_set('display_errors', 0);

// פונקציה להחזרת תגובה
function respond($success, $data = [], $error = null) {
    echo json_encode([
        'success' => $success,
        'timestamp' => date('Y-m-d H:i:s'),
        ...$data,
        'error' => $error
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

// קבלת סוג הבדיקה
$test = $_GET['test'] ?? '';

switch ($test) {
    case 'env':
        testEnvConfiguration();
        break;
        
    case 'database':
        testDatabaseConnection();
        break;
        
    case 'tables':
        testDatabaseTables();
        break;
        
    case 'files':
        testFilesAndPermissions();
        break;
        
    case 'google':
        testGoogleAuth();
        break;
        
    default:
        respond(false, [], 'Invalid test type');
}

/**
 * בדיקת קובץ ENV וקונפיגורציה
 */
function testEnvConfiguration() {
    $result = [
        'env_exists' => false,
        'config' => [],
        'warnings' => 0
    ];
    
    // בדיקת קיום קובץ .env
    $envPath = __DIR__ . '/.env';
    $result['env_exists'] = file_exists($envPath);
    
    if ($result['env_exists']) {
        // טעינת קובץ ENV
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
        
        // בדיקת ערכים קריטיים
        $result['config'] = [
            'DB_HOST' => $env['DB_HOST'] ?? null,
            'DB_NAME' => $env['DB_NAME'] ?? null,
            'DB_USER' => $env['DB_USER'] ?? null,
            'DB_PASSWORD' => isset($env['DB_PASSWORD']) ? '***' : null,
            'CLIENT_ID' => isset($env['CLIENT_ID']) && !empty($env['CLIENT_ID']) ? 'SET' : null,
            'PORT' => $env['PORT'] ?? null
        ];
        
        // בדיקת אזהרות
        if (isset($env['PORT']) && $env['PORT'] == '8080') {
            $result['warnings']++;
            $result['port_warning'] = 'Port 8080 is not MySQL port! Remove this setting.';
        }
        
        if (empty($env['CLIENT_ID'])) {
            $result['warnings']++;
            $result['google_warning'] = 'Google CLIENT_ID not set';
        }
    }
    
    // בדיקת קובץ config.php
    $configPath = __DIR__ . '/config.php';
    $result['config_exists'] = file_exists($configPath);
    
    respond(true, $result);
}

/**
 * בדיקת חיבור למסד נתונים
 */
function testDatabaseConnection() {
    try {
        // טעינת config
        if (!file_exists('config.php')) {
            respond(false, [], 'config.php not found');
        }
        
        require_once 'config.php';
        
        // ניסיון חיבור
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
        $pdo = new PDO($dsn, DB_USER, DB_PASSWORD, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]);
        
        // קבלת מידע על המסד
        $version = $pdo->query("SELECT VERSION() as version")->fetch();
        $database = $pdo->query("SELECT DATABASE() as db")->fetch();
        $charset = $pdo->query("SHOW VARIABLES LIKE 'character_set_database'")->fetch();
        $collation = $pdo->query("SHOW VARIABLES LIKE 'collation_database'")->fetch();
        
        respond(true, [
            'connected' => true,
            'database_name' => $database['db'],
            'mysql_version' => $version['version'],
            'charset' => $charset['Value'],
            'collation' => $collation['Value']
        ]);
        
    } catch (PDOException $e) {
        respond(false, [
            'connected' => false,
            'details' => $e->getMessage()
        ], 'Database connection failed');
    }
}

/**
 * בדיקת טבלאות במסד נתונים
 */
function testDatabaseTables() {
    try {
        require_once 'config.php';
        
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
        $pdo = new PDO($dsn, DB_USER, DB_PASSWORD);
        
        $tables = [
            'users' => [],
            'cemeteries' => [],
            'graves' => [],
            'customers' => [],
            'purchases' => [],
            'push_subscriptions' => []
        ];
        
        foreach ($tables as $table => &$info) {
            // בדיקה אם הטבלה קיימת
            $stmt = $pdo->prepare("SHOW TABLES LIKE ?");
            $stmt->execute([$table]);
            $info['exists'] = $stmt->rowCount() > 0;
            
            if ($info['exists']) {
                // ספירת רשומות
                $count = $pdo->query("SELECT COUNT(*) FROM $table")->fetchColumn();
                $info['count'] = $count;
                
                // קבלת עמודות (רק ל-users)
                if ($table === 'users') {
                    $columns = $pdo->query("SHOW COLUMNS FROM $table")->fetchAll();
                    $info['columns'] = array_column($columns, 'Field');
                    
                    // בדיקת עמודות קריטיות
                    $required = ['id', 'username', 'email', 'password', 'google_id'];
                    $missing = array_diff($required, $info['columns']);
                    if (!empty($missing)) {
                        $info['missing_columns'] = $missing;
                    }
                }
            }
        }
        
        respond(true, ['tables' => $tables]);
        
    } catch (Exception $e) {
        respond(false, [], 'Error checking tables: ' . $e->getMessage());
    }
}

/**
 * בדיקת קבצים והרשאות
 */
function testFilesAndPermissions() {
    $files = [
        'config.php' => __DIR__ . '/config.php',
        '.env' => __DIR__ . '/.env',
        'auth/login.php' => __DIR__ . '/auth/login.php',
        'auth/google-auth.php' => __DIR__ . '/auth/google-auth.php',
        'dashboard/index.php' => __DIR__ . '/dashboard/index.php'
    ];
    
    $result = ['files' => []];
    
    foreach ($files as $name => $path) {
        $result['files'][$name] = [
            'exists' => file_exists($path),
            'readable' => is_readable($path),
            'writable' => is_writable($path),
            'size' => file_exists($path) ? filesize($path) : 0
        ];
    }
    
    // בדיקת תיקיות
    $uploadDir = __DIR__ . '/uploads';
    $result['uploads'] = [
        'exists' => file_exists($uploadDir),
        'writable' => is_writable($uploadDir)
    ];
    
    // בדיקת PHP extensions
    $result['extensions'] = [
        'pdo' => extension_loaded('pdo'),
        'pdo_mysql' => extension_loaded('pdo_mysql'),
        'curl' => extension_loaded('curl'),
        'json' => extension_loaded('json'),
        'session' => extension_loaded('session')
    ];
    
    respond(true, $result);
}

/**
 * בדיקת Google Authentication
 */
function testGoogleAuth() {
    require_once 'config.php';
    
    $result = [
        'client_id_exists' => !empty(GOOGLE_CLIENT_ID),
        'client_id_length' => strlen(GOOGLE_CLIENT_ID ?? ''),
        'is_https' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
        'google_script_loaded' => true // נבדק בצד הלקוח
    ];
    
    // בדיקת פורמט CLIENT_ID
    if ($result['client_id_exists']) {
        $result['client_id_valid_format'] = strpos(GOOGLE_CLIENT_ID, '.apps.googleusercontent.com') !== false;
    }
    
    respond(true, $result);
}
?>