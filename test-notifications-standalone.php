<?php
// test-notifications-standalone.php - דף בדיקה עצמאי ללא תלויות
// מונע redirect loops
if (isset($_SERVER['HTTP_X_REDIRECT_COUNT']) && $_SERVER['HTTP_X_REDIRECT_COUNT'] > 3) {
    die('Too many redirects detected');
}

// התחלת session נקייה
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// מנע include של קבצים שעלולים לגרום ל-redirect
define('SKIP_AUTH_CHECK', true);
define('DIRECT_ACCESS', true);

// כלול רק את הקונפיג הבסיסי
$configFile = __DIR__ . '/config.php';
if (!file_exists($configFile)) {
    die('<h1>Error: config.php not found</h1>');
}

// נסה לטעון config בצורה בטוחה
try {
    // טען את הפונקציה loadEnv אם לא קיימת
    if (!function_exists('loadEnv')) {
        function loadEnv($path = __DIR__ . '/.env') {
            if (!file_exists($path)) {
                return false;
            }
            
            $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                if (strpos(trim($line), '#') === 0) continue;
                
                $parts = explode('=', $line, 2);
                if (count($parts) == 2) {
                    $key = trim($parts[0]);
                    $value = trim($parts[1], '"\'');
                    putenv("$key=$value");
                    $_ENV[$key] = $value;
                    $_SERVER[$key] = $value;
                }
            }
            return true;
        }
    }
    
    // טען ENV
    loadEnv();
    
    // הגדרות בסיסיות
    define('ENVIRONMENT', $_ENV['ENVIRONMENT'] ?? 'development');
    define('DB_HOST', $_ENV['DB_HOST'] ?? 'localhost');
    define('DB_NAME', $_ENV['DB_NAME'] ?? 'database');
    define('DB_USER', $_ENV['DB_USER'] ?? 'root');
    define('DB_PASSWORD', $_ENV['DB_PASSWORD'] ?? '');
    define('DB_CHARSET', $_ENV['DB_CHARSET'] ?? 'utf8mb4');
    
    // פונקציית חיבור למסד נתונים
    function getTestDBConnection() {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            $pdo = new PDO($dsn, DB_USER, DB_PASSWORD, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false
            ]);
            return $pdo;
        } catch (PDOException $e) {
            return null;
        }
    }
    
    $pdo = getTestDBConnection();
    
} catch (Exception $e) {
    $pdo = null;
}

// בדיקה בסיסית של הרשאות
$isAuthorized = false;

// בדיקה 1: סביבת development
if (ENVIRONMENT === 'development') {
    $isAuthorized = true;
}

// בדיקה 2: משתמש מחובר
if (!$isAuthorized && isset($_SESSION['user_id'])) {
    $isAuthorized = true;
}

// בדיקה 3: token מיוחד
if (!$isAuthorized && isset($_GET['token']) && $_GET['token'] === 'test123') {
    $isAuthorized = true;
}

if (!$isAuthorized) {
    die('<h1>Access Denied</h1><p>This page is only available in development mode or for logged-in users.</p><p>Add ?token=test123 to access.</p>');
}

$message = '';
$messageType = '';

// טיפול בפעולות
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'test_connection':
            if ($pdo) {
                $message = "✅ חיבור למסד נתונים תקין";
                $messageType = 'success';
            } else {
                $message = "❌ אין חיבור למסד נתונים";
                $messageType = 'error';
            }
            break;
            
        case 'send_test_email':
            $to = $_POST['email'];
            $subject = $_POST['subject'];
            $body = $_POST['body'];
            
            $headers = "From: noreply@" . $_SERVER['HTTP_HOST'] . "\r\n";
            $headers .= "MIME-Version: 1.0\r\n";
            $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
            
            $htmlBody = "<html dir='rtl'><body style='font-family: Arial; direction: rtl;'>{$body}</body></html>";
            
            if (@mail($to, $subject, $htmlBody, $headers)) {
                $message = "אימייל נשלח בהצלחה ל-$to";
                $messageType = 'success';
            } else {
                $message = "שגיאה בשליחת אימייל";
                $messageType = 'error';
            }
            break;
    }
}

// נתונים לדיבאג
$debugInfo = [
    'PHP Version' => PHP_VERSION,
    'Server Software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
    'Document Root' => $_SERVER['DOCUMENT_ROOT'] ?? 'Unknown',
    'Script Path' => __FILE__,
    'Session ID' => session_id() ?: 'No session',
    'Environment' => ENVIRONMENT,
    'Database' => $pdo ? 'Connected' : 'Not connected',
    'Mail Function' => function_exists('mail') ? 'Available' : 'Not available',
    'User Agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown'
];
?>
<!DOCTYPE html>
<html dir="rtl" lang="he">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>בדיקת מערכת התראות - Standalone</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .header {
            background: white;
            border-radius: 15px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }
        
        .header h1 {
            color: #333;
            margin-bottom: 10px;
        }
        
        .section {
            background: white;
            border-radius: 15px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }
        
        .section h2 {
            color: #333;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #f0f0f0;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #333;
        }
        
        .form-group input,
        .form-group textarea {
            width: 100%;
            padding: 12px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 14px;
        }
        
        .form-group input:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #667eea;
        }
        
        .btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 12px 30px;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }
        
        .alert {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        .alert.success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert.error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .alert.warning {
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffeeba;
        }
        
        .debug-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .debug-table td {
            padding: 10px;
            border-bottom: 1px solid #e0e0e0;
        }
        
        .debug-table td:first-child {
            font-weight: 600;
            width: 200px;
            color: #667eea;
        }
        
        .status-indicator {
            display: inline-block;
            width: 10px;
            height: 10px;
            border-radius: 50%;
            margin-right: 5px;
        }
        
        .status-indicator.green {
            background: #28a745;
        }
        
        .status-indicator.red {
            background: #dc3545;
        }
        
        .status-indicator.yellow {
            background: #ffc107;
        }
        
        .test-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        
        .test-card {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            text-align: center;
            border: 2px solid #e9ecef;
            transition: all 0.3s;
        }
        
        .test-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .test-card h3 {
            color: #667eea;
            margin-bottom: 15px;
        }
        
        .test-card button {
            width: 100%;
            margin-top: 10px;
        }
        
        code {
            background: #f8f9fa;
            padding: 2px 6px;
            border-radius: 3px;
            font-family: 'Courier New', monospace;
            color: #e83e8c;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <h1>🔔 מערכת בדיקת התראות - Standalone Version</h1>
            <p>דף בדיקה עצמאי ללא תלויות חיצוניות</p>
        </div>
        
        <?php if ($message): ?>
        <div class="alert <?php echo $messageType; ?>">
            <?php echo $message; ?>
        </div>
        <?php endif; ?>
        
        <!-- System Status -->
        <div class="section">
            <h2>🔍 סטטוס מערכת</h2>
            <table class="debug-table">
                <?php foreach ($debugInfo as $key => $value): ?>
                <tr>
                    <td><?php echo $key; ?></td>
                    <td>
                        <?php if ($key === 'Database'): ?>
                            <span class="status-indicator <?php echo $value === 'Connected' ? 'green' : 'red'; ?>"></span>
                        <?php elseif ($key === 'Mail Function'): ?>
                            <span class="status-indicator <?php echo $value === 'Available' ? 'green' : 'red'; ?>"></span>
                        <?php endif; ?>
                        <?php echo htmlspecialchars($value); ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </table>
            
            <form method="POST" style="margin-top: 20px;">
                <input type="hidden" name="action" value="test_connection">
                <button type="submit" class="btn">🔌 בדוק חיבור למסד נתונים</button>
            </form>
        </div>
        
        <!-- Quick Tests -->
        <div class="section">
            <h2>⚡ בדיקות מהירות</h2>
            <div class="test-grid">
                <div class="test-card">
                    <h3>🔔 התראת דפדפן</h3>
                    <p>בדיקת התראות דפדפן</p>
                    <button class="btn" onclick="testBrowserNotification()">בדוק</button>
                </div>
                
                <div class="test-card">
                    <h3>📧 בדיקת Mail</h3>
                    <p>בדיקת פונקציית mail()</p>
                    <button class="btn" onclick="document.getElementById('emailSection').scrollIntoView()">עבור לבדיקה</button>
                </div>
                
                <div class="test-card">
                    <h3>💾 LocalStorage</h3>
                    <p>בדיקת אחסון מקומי</p>
                    <button class="btn" onclick="testLocalStorage()">בדוק</button>
                </div>
                
                <div class="test-card">
                    <h3>🍪 Cookies</h3>
                    <p>בדיקת עוגיות</p>
                    <button class="btn" onclick="testCookies()">בדוק</button>
                </div>
            </div>
        </div>
        
        <!-- Email Test -->
        <div class="section" id="emailSection">
            <h2>📧 בדיקת שליחת אימייל</h2>
            
            <div class="alert warning">
                <strong>שים לב:</strong> פונקציית mail() עלולה לא לעבוד בסביבת localhost. 
                בשרת אמיתי, ודא שה-mail server מוגדר כראוי.
            </div>
            
            <form method="POST">
                <input type="hidden" name="action" value="send_test_email">
                <div class="form-group">
                    <label>כתובת אימייל</label>
                    <input type="email" name="email" value="<?php echo $_SESSION['email'] ?? 'test@example.com'; ?>" required>
                </div>
                <div class="form-group">
                    <label>נושא</label>
                    <input type="text" name="subject" value="בדיקת מערכת התראות - <?php echo date('H:i:s'); ?>" required>
                </div>
                <div class="form-group">
                    <label>תוכן ההודעה</label>
                    <textarea name="body" rows="4" required>זוהי הודעת בדיקה מהמערכת.

נשלחה בתאריך: <?php echo date('d/m/Y H:i:s'); ?>
משרת: <?php echo $_SERVER['HTTP_HOST'] ?? 'localhost'; ?>

אם קיבלת הודעה זו, המערכת עובדת כראוי!</textarea>
                </div>
                <button type="submit" class="btn">📨 שלח אימייל בדיקה</button>
            </form>
        </div>
        
        <!-- JavaScript Tests -->
        <div class="section">
            <h2>🔧 בדיקות JavaScript</h2>
            <div id="jsTestResults"></div>
        </div>
        
        <!-- Configuration Check -->
        <div class="section">
            <h2>⚙️ בדיקת הגדרות</h2>
            <div class="alert warning">
                <p><strong>נתיב הקובץ:</strong> <code><?php echo __FILE__; ?></code></p>
                <p><strong>נתיב URL:</strong> <code><?php echo $_SERVER['REQUEST_URI'] ?? 'Unknown'; ?></code></p>
                <p><strong>htaccess קיים:</strong> <code><?php echo file_exists(__DIR__ . '/.htaccess') ? 'כן' : 'לא'; ?></code></p>
            </div>
            
            <h3>המלצות לתיקון בעיות Redirect:</h3>
            <ol style="line-height: 1.8;">
                <li>בדוק את קובץ <code>.htaccess</code> וחפש כללי redirect בעייתיים</li>
                <li>וודא שאין לולאות redirect בקוד PHP</li>
                <li>בטל זמנית את כל ה-includes של קבצי auth</li>
                <li>נקה cookies ו-cache של הדפדפן</li>
                <li>בדוק ב-Network tab של הדפדפן את שרשרת ה-redirects</li>
            </ol>
        </div>
    </div>
    
    <script>
        // בדיקת התראות דפדפן
        function testBrowserNotification() {
            if (!('Notification' in window)) {
                alert('הדפדפן לא תומך בהתראות');
                return;
            }
            
            if (Notification.permission === 'granted') {
                new Notification('בדיקת התראה 🎉', {
                    body: 'ההתראות עובדות מצוין!',
                    icon: 'data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><text y=".9em" font-size="90">🔔</text></svg>'
                });
            } else if (Notification.permission !== 'denied') {
                Notification.requestPermission().then(permission => {
                    if (permission === 'granted') {
                        new Notification('התראות הופעלו!', {
                            body: 'כעת תוכל לקבל התראות',
                            icon: 'data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><text y=".9em" font-size="90">✅</text></svg>'
                        });
                    }
                });
            } else {
                alert('התראות חסומות. יש לאפשר בהגדרות הדפדפן');
            }
        }
        
        // בדיקת LocalStorage
        function testLocalStorage() {
            try {
                const testKey = 'test_' + Date.now();
                localStorage.setItem(testKey, 'test value');
                const value = localStorage.getItem(testKey);
                localStorage.removeItem(testKey);
                
                if (value === 'test value') {
                    alert('✅ LocalStorage עובד מצוין!');
                } else {
                    alert('❌ בעיה ב-LocalStorage');
                }
            } catch (e) {
                alert('❌ LocalStorage לא זמין: ' + e.message);
            }
        }
        
        // בדיקת Cookies
        function testCookies() {
            document.cookie = "test_cookie=test_value; path=/";
            const cookies = document.cookie;
            
            if (cookies.includes('test_cookie')) {
                alert('✅ Cookies עובדות מצוין!');
                // מחק את הcookie
                document.cookie = "test_cookie=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/;";
            } else {
                alert('❌ Cookies לא עובדות או חסומות');
            }
        }
        
        // בדיקות אוטומטיות בטעינה
        window.addEventListener('load', () => {
            const results = document.getElementById('jsTestResults');
            const tests = [];
            
            // בדוק תמיכה ב-Service Worker
            tests.push({
                name: 'Service Worker',
                status: 'serviceWorker' in navigator,
                icon: 'serviceWorker' in navigator ? '✅' : '❌'
            });
            
            // בדוק תמיכה ב-Push API
            tests.push({
                name: 'Push API',
                status: 'PushManager' in window,
                icon: 'PushManager' in window ? '✅' : '❌'
            });
            
            // בדוק תמיכה ב-Notification API
            tests.push({
                name: 'Notification API',
                status: 'Notification' in window,
                icon: 'Notification' in window ? '✅' : '❌'
            });
            
            // בדוק HTTPS
            tests.push({
                name: 'HTTPS',
                status: location.protocol === 'https:',
                icon: location.protocol === 'https:' ? '✅' : '⚠️'
            });
            
            // בדוק WebSocket
            tests.push({
                name: 'WebSocket',
                status: 'WebSocket' in window,
                icon: 'WebSocket' in window ? '✅' : '❌'
            });
            
            // הצג תוצאות
            let html = '<table class="debug-table">';
            tests.forEach(test => {
                html += `
                    <tr>
                        <td>${test.name}</td>
                        <td>${test.icon} ${test.status ? 'נתמך' : 'לא נתמך'}</td>
                    </tr>
                `;
            });
            html += '</table>';
            
            results.innerHTML = html;
        });
    </script>
</body>
</html>