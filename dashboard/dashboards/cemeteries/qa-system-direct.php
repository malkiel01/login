<?php
// dashboard/dashboards/cemeteries/qa-test-standalone.php
// מערכת בדיקה עצמאית לחלוטין - ללא תלות בקבצים חיצוניים

// התחל session בבטיחות
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// עקיפת כל הבדיקות
$_SESSION['user_id'] = 999999;
$_SESSION['dashboard_type'] = 'cemetery_manager';

// נסה לטעון קובץ config אם קיים, אחרת צור חיבור ידני
$db_config = null;
$pdo = null;

// נסיון לקריאת קובץ .env
$env_path = $_SERVER['DOCUMENT_ROOT'] . '/.env';
if (file_exists($env_path)) {
    $env_content = file_get_contents($env_path);
    $lines = explode("\n", $env_content);
    $env = [];
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0 || empty(trim($line))) continue;
        list($key, $value) = explode('=', $line, 2);
        $env[trim($key)] = trim($value, '"\'');
    }
    
    if (isset($env['DB_HOST']) && isset($env['DB_NAME'])) {
        $db_config = [
            'host' => $env['DB_HOST'],
            'dbname' => $env['DB_NAME'],
            'user' => $env['DB_USER'] ?? 'root',
            'pass' => $env['DB_PASSWORD'] ?? ''
        ];
    }
}

// נסה להתחבר למסד נתונים
if ($db_config) {
    try {
        $dsn = "mysql:host={$db_config['host']};dbname={$db_config['dbname']};charset=utf8mb4";
        $pdo = new PDO($dsn, $db_config['user'], $db_config['pass']);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $db_connected = true;
    } catch (Exception $e) {
        $db_connected = false;
        $db_error = $e->getMessage();
    }
} else {
    $db_connected = false;
    $db_error = "לא נמצא קובץ קונפיגורציה";
}
?>
<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🧪 בדיקת מערכת בתי עלמין - Standalone</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Arial, sans-serif;
            background: #f5f5f5;
            padding: 20px;
            direction: rtl;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .header {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            padding: 20px;
            text-align: center;
        }
        
        .content { padding: 30px; }
        
        .status-box {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .status-item {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid #dee2e6;
        }
        
        .status-item:last-child { border-bottom: none; }
        
        .status-ok { color: #28a745; font-weight: bold; }
        .status-error { color: #dc3545; font-weight: bold; }
        .status-warning { color: #ffc107; font-weight: bold; }
        
        .test-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        
        .test-card {
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 15px;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .test-card:hover {
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            transform: translateY(-2px);
        }
        
        .btn {
            background: #667eea;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            margin: 5px;
        }
        
        .btn:hover { background: #5a67d8; }
        
        .alert {
            padding: 15px;
            border-radius: 5px;
            margin: 20px 0;
        }
        
        .alert-warning {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            color: #856404;
        }
        
        .alert-info {
            background: #d1ecf1;
            border: 1px solid #bee5eb;
            color: #0c5460;
        }
        
        pre {
            background: #f4f4f4;
            padding: 10px;
            border-radius: 5px;
            overflow-x: auto;
            font-size: 12px;
        }
        
        .table-test {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        
        .table-test th,
        .table-test td {
            border: 1px solid #dee2e6;
            padding: 8px;
            text-align: right;
        }
        
        .table-test th {
            background: #f8f9fa;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🧪 מערכת בדיקה עצמאית - בתי עלמין</h1>
            <p>גרסת Standalone - ללא תלות בקבצי המערכת</p>
        </div>
        
        <div class="content">
            <!-- סטטוס מערכת -->
            <div class="status-box">
                <h2>📊 סטטוס מערכת</h2>
                <div class="status-item">
                    <span>PHP Version</span>
                    <span class="status-ok"><?php echo phpversion(); ?></span>
                </div>
                <div class="status-item">
                    <span>Session</span>
                    <span class="<?php echo session_status() === PHP_SESSION_ACTIVE ? 'status-ok' : 'status-error'; ?>">
                        <?php echo session_status() === PHP_SESSION_ACTIVE ? 'פעיל' : 'לא פעיל'; ?>
                    </span>
                </div>
                <div class="status-item">
                    <span>מסד נתונים</span>
                    <span class="<?php echo $db_connected ? 'status-ok' : 'status-error'; ?>">
                        <?php echo $db_connected ? 'מחובר' : 'לא מחובר'; ?>
                    </span>
                </div>
                <?php if (!$db_connected): ?>
                <div class="status-item">
                    <span>שגיאת DB</span>
                    <span style="color: #dc3545; font-size: 12px;"><?php echo htmlspecialchars($db_error); ?></span>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- בדיקת קבצים -->
            <div class="status-box">
                <h2>📁 בדיקת קבצים במערכת</h2>
                <?php
                $files_to_check = [
                    'config.php' => $_SERVER['DOCUMENT_ROOT'] . '/config.php',
                    '.env' => $_SERVER['DOCUMENT_ROOT'] . '/.env',
                    'index.php' => __DIR__ . '/index.php',
                    'forms/forms-config.php' => __DIR__ . '/forms/forms-config.php',
                    'includes/functions.php' => __DIR__ . '/includes/functions.php'
                ];
                
                foreach ($files_to_check as $name => $path):
                    $exists = file_exists($path);
                ?>
                <div class="status-item">
                    <span><?php echo $name; ?></span>
                    <span class="<?php echo $exists ? 'status-ok' : 'status-warning'; ?>">
                        <?php echo $exists ? '✅ קיים' : '⚠️ חסר'; ?>
                    </span>
                </div>
                <?php endforeach; ?>
            </div>
            
            <?php if ($db_connected && $pdo): ?>
            <!-- בדיקת טבלאות -->
            <div class="status-box">
                <h2>🗄️ טבלאות במסד נתונים</h2>
                <?php
                try {
                    $stmt = $pdo->query("SHOW TABLES");
                    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
                    
                    $important_tables = ['users', 'cemeteries', 'blocks', 'plots', 'graves', 'customers'];
                    
                    foreach ($important_tables as $table):
                        $exists = in_array($table, $tables);
                        $count = 0;
                        if ($exists) {
                            try {
                                $count_stmt = $pdo->query("SELECT COUNT(*) FROM $table");
                                $count = $count_stmt->fetchColumn();
                            } catch (Exception $e) {
                                $count = -1;
                            }
                        }
                    ?>
                    <div class="status-item">
                        <span><?php echo $table; ?></span>
                        <span class="<?php echo $exists ? 'status-ok' : 'status-error'; ?>">
                            <?php 
                            if ($exists) {
                                echo "✅ " . number_format($count) . " רשומות";
                            } else {
                                echo "❌ לא קיימת";
                            }
                            ?>
                        </span>
                    </div>
                    <?php endforeach; ?>
                </div>
                
                <!-- בדיקת נתונים -->
                <div class="status-box">
                    <h2>📋 דוגמת נתונים</h2>
                    <?php
                    // נסה לשלוף בית עלמין ראשון
                    try {
                        $stmt = $pdo->query("SELECT * FROM cemeteries LIMIT 1");
                        $cemetery = $stmt->fetch(PDO::FETCH_ASSOC);
                        
                        if ($cemetery) {
                            echo "<h3>בית עלמין ראשון:</h3>";
                            echo "<pre>" . print_r($cemetery, true) . "</pre>";
                        } else {
                            echo "<p class='status-warning'>אין נתונים בטבלת בתי עלמין</p>";
                        }
                    } catch (Exception $e) {
                        echo "<p class='status-error'>שגיאה בשליפת נתונים: " . $e->getMessage() . "</p>";
                    }
                    ?>
                </div>
            <?php endif; ?>
            
            <!-- כלי בדיקה -->
            <div class="status-box">
                <h2>🛠️ כלי בדיקה</h2>
                <div class="test-grid">
                    <div class="test-card" onclick="testPHP()">
                        <h3>🔧 PHP Info</h3>
                        <p>הצג מידע PHP מלא</p>
                    </div>
                    
                    <div class="test-card" onclick="testSession()">
                        <h3>🔐 Session Test</h3>
                        <p>בדוק משתני Session</p>
                    </div>
                    
                    <div class="test-card" onclick="testDB()">
                        <h3>🗄️ DB Query</h3>
                        <p>הרץ שאילתה</p>
                    </div>
                    
                    <div class="test-card" onclick="loadDashboard()">
                        <h3>🏛️ טען דשבורד</h3>
                        <p>נסה לטעון את index.php</p>
                    </div>
                </div>
            </div>
            
            <!-- הוראות -->
            <div class="alert alert-info">
                <h3>📝 הוראות שימוש:</h3>
                <ol>
                    <li>בדוק את הסטטוס בחלק העליון</li>
                    <li>אם המסד מחובר, תראה רשימת טבלאות</li>
                    <li>השתמש בכלי הבדיקה למידע נוסף</li>
                    <li>אם יש שגיאות, בדוק את קובץ .env</li>
                </ol>
            </div>
            
            <!-- פרטי Session -->
            <div class="status-box">
                <h2>🔑 פרטי Session נוכחיים</h2>
                <pre><?php print_r($_SESSION); ?></pre>
            </div>
            
            <!-- בדיקת כתובת -->
            <div class="alert alert-warning">
                <strong>כתובות לבדיקה:</strong><br>
                • דף זה: <code><?php echo $_SERVER['REQUEST_URI']; ?></code><br>
                • דשבורד מלא: <code>/dashboard/dashboards/cemeteries/index.php?bypass=true</code><br>
                • בדיקת הרשאות: <code>/dashboard/dashboards/cemeteries/test-permissions.php</code><br>
                • בדיקת טפסים: <code>/dashboard/dashboards/cemeteries/forms/test-form.php</code>
            </div>
        </div>
    </div>
    
    <script>
        function testPHP() {
            window.open('<?php echo $_SERVER['PHP_SELF']; ?>?action=phpinfo', '_blank');
        }
        
        function testSession() {
            alert('Session Data:\n' + <?php echo json_encode(json_encode($_SESSION, JSON_PRETTY_PRINT)); ?>);
        }
        
        function testDB() {
            const query = prompt('הכנס שאילתת SQL:' , 'SELECT * FROM cemeteries LIMIT 5');
            if (query) {
                window.location.href = '?query=' + encodeURIComponent(query);
            }
        }
        
        function loadDashboard() {
            window.location.href = 'index.php?bypass=true';
        }
    </script>
</body>
</html>

<?php
// טיפול בפעולות מיוחדות
if (isset($_GET['action']) && $_GET['action'] === 'phpinfo') {
    phpinfo();
    exit;
}

if (isset($_GET['query']) && $pdo) {
    echo "<h2>תוצאות שאילתה:</h2>";
    try {
        $stmt = $pdo->query($_GET['query']);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo "<pre>" . print_r($results, true) . "</pre>";
    } catch (Exception $e) {
        echo "<p style='color: red;'>שגיאה: " . $e->getMessage() . "</p>";
    }
    exit;
}
?>