<?php
/**
 * קובץ בדיקה עצמאי למערכת ההרשאות
 * check-permissions-standalone.php
 * 
 * קובץ זה לא דורש התחברות ועוקף את ה-htaccess
 */

// מניעת הפניה אוטומטית
define('SKIP_AUTH_CHECK', true);

// הפעלת דיווח שגיאות
error_reporting(E_ALL);
ini_set('display_errors', 1);

// התחלת פלט
header('Content-Type: text/html; charset=UTF-8');
?>
<!DOCTYPE html>
<html dir="rtl" lang="he">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>בדיקת מערכת הרשאות</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            max-width: 1200px;
            margin: 20px auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 {
            color: #333;
            border-bottom: 3px solid #667eea;
            padding-bottom: 10px;
        }
        .check-section {
            margin: 20px 0;
            padding: 15px;
            background: #f9f9f9;
            border-radius: 5px;
            border-right: 4px solid #ddd;
        }
        .check-section.success {
            border-right-color: #10b981;
            background: #f0fdf4;
        }
        .check-section.warning {
            border-right-color: #f59e0b;
            background: #fffbeb;
        }
        .check-section.error {
            border-right-color: #ef4444;
            background: #fef2f2;
        }
        .check-title {
            font-weight: bold;
            margin-bottom: 10px;
            font-size: 16px;
        }
        .check-item {
            margin: 5px 0;
            padding: 5px;
            font-size: 14px;
        }
        .success-mark { color: #10b981; }
        .warning-mark { color: #f59e0b; }
        .error-mark { color: #ef4444; }
        pre {
            background: #1f2937;
            color: #10b981;
            padding: 10px;
            border-radius: 5px;
            overflow-x: auto;
            font-size: 12px;
            direction: ltr;
            text-align: left;
        }
        .btn {
            display: inline-block;
            padding: 10px 20px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            text-decoration: none;
            border-radius: 5px;
            margin: 10px 5px;
        }
        .btn:hover {
            opacity: 0.9;
        }
        .summary {
            margin-top: 30px;
            padding: 20px;
            border-radius: 10px;
            text-align: center;
        }
        .summary.success {
            background: linear-gradient(135deg, #d4f4dd, #bbf7d0);
            color: #0e7c3a;
        }
        .summary.error {
            background: linear-gradient(135deg, #fee2e2, #fecaca);
            color: #991b1b;
        }
        .file-tree {
            font-family: monospace;
            font-size: 13px;
            line-height: 1.5;
            background: #f5f5f5;
            padding: 10px;
            border-radius: 5px;
            margin: 10px 0;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔍 בדיקת מערכת הרשאות - Permissions System Check</h1>
        
        <?php
        $allGood = true;
        $warnings = 0;
        $errors = 0;
        
        // בדיקה 1: מבנה תיקיות
        echo '<div class="check-section">';
        echo '<div class="check-title">📁 בדיקת מבנה תיקיות</div>';
        
        $baseDir = dirname(__FILE__);
        $permissionsDir = $baseDir . '/permissions';
        
        echo '<div class="file-tree">';
        echo "Base Directory: $baseDir<br>";
        
        if (is_dir($permissionsDir)) {
            echo '<span class="success-mark">✅</span> /permissions/ - קיים<br>';
            
            // בדוק תתי-תיקיות
            $subdirs = ['core', 'handlers', 'ui', 'js', 'api', 'debug'];
            foreach ($subdirs as $subdir) {
                if (is_dir($permissionsDir . '/' . $subdir)) {
                    echo '<span class="success-mark">✅</span> /permissions/' . $subdir . '/ - קיים<br>';
                } else {
                    echo '<span class="warning-mark">⚠️</span> /permissions/' . $subdir . '/ - חסר<br>';
                    $warnings++;
                }
            }
        } else {
            echo '<span class="error-mark">❌</span> תיקיית permissions לא נמצאה!<br>';
            echo 'נתיב מצופה: ' . $permissionsDir . '<br>';
            $errors++;
            $allGood = false;
        }
        echo '</div></div>';
        
        // בדיקה 2: קבצי Core
        echo '<div class="check-section">';
        echo '<div class="check-title">🎯 בדיקת קבצי Core</div>';
        
        $coreFiles = [
            'permissions-init.php' => $permissionsDir . '/permissions-init.php',
            'PermissionsManager.php' => $permissionsDir . '/core/PermissionsManager.php',
            'PermissionStorage.php' => $permissionsDir . '/core/PermissionStorage.php',
            'PermissionTypes.php' => $permissionsDir . '/core/PermissionTypes.php'
        ];
        
        foreach ($coreFiles as $name => $path) {
            if (file_exists($path)) {
                $size = filesize($path);
                echo '<div class="check-item">';
                echo '<span class="success-mark">✅</span> ' . $name . ' (' . number_format($size) . ' bytes)';
                echo '</div>';
            } else {
                echo '<div class="check-item">';
                echo '<span class="error-mark">❌</span> ' . $name . ' - חסר!';
                echo '</div>';
                $errors++;
                $allGood = false;
            }
        }
        echo '</div>';
        
        // בדיקה 3: Handlers
        echo '<div class="check-section warning">';
        echo '<div class="check-title">📦 בדיקת Handlers (אופציונלי)</div>';
        
        $handlers = [
            'NotificationHandler.php',
            'PushHandler.php',
            'MediaHandler.php',
            'StorageHandler.php',
            'LocationHandler.php',
            'BackgroundHandler.php'
        ];
        
        $handlersFound = 0;
        foreach ($handlers as $handler) {
            $path = $permissionsDir . '/handlers/' . $handler;
            if (file_exists($path)) {
                echo '<div class="check-item"><span class="success-mark">✅</span> ' . $handler . '</div>';
                $handlersFound++;
            } else {
                echo '<div class="check-item"><span class="warning-mark">⚠️</span> ' . $handler . ' - לא נמצא (אופציונלי)</div>';
            }
        }
        echo '<div class="check-item">נמצאו ' . $handlersFound . ' מתוך ' . count($handlers) . ' handlers</div>';
        echo '</div>';
        
        // בדיקה 4: קבצי UI
        echo '<div class="check-section">';
        echo '<div class="check-title">🎨 בדיקת קבצי UI</div>';
        
        $uiFiles = [
            'permissions.css' => $permissionsDir . '/ui/styles/permissions.css',
            'permissions-manager.js' => $permissionsDir . '/js/permissions-manager.js',
            'permissions-debug.php' => $permissionsDir . '/debug/permissions-debug.php'
        ];
        
        foreach ($uiFiles as $name => $path) {
            if (file_exists($path)) {
                echo '<div class="check-item"><span class="success-mark">✅</span> ' . $name . '</div>';
            } else {
                echo '<div class="check-item"><span class="warning-mark">⚠️</span> ' . $name . ' - חסר</div>';
                $warnings++;
            }
        }
        echo '</div>';
        
        // בדיקה 5: טעינת המערכת
        echo '<div class="check-section">';
        echo '<div class="check-title">⚙️ טעינת המערכת</div>';
        
        $initFile = $permissionsDir . '/permissions-init.php';
        if (file_exists($initFile)) {
            echo '<div class="check-item">מנסה לטעון את permissions-init.php...</div>';
            
            // נסה לטעון
            $originalErrorReporting = error_reporting(E_ALL & ~E_WARNING & ~E_NOTICE);
            $loadResult = @include_once $initFile;
            error_reporting($originalErrorReporting);
            
            if ($loadResult !== false) {
                echo '<div class="check-item"><span class="success-mark">✅</span> הקובץ נטען בהצלחה!</div>';
                
                // בדוק פונקציות
                $functions = [
                    'getPermissionsHeaders',
                    'getPermissionsScripts',
                    'checkPermission',
                    'checkAllPermissions'
                ];
                
                echo '<div class="check-item">בדיקת פונקציות:</div>';
                foreach ($functions as $func) {
                    if (function_exists($func)) {
                        echo '<div class="check-item" style="padding-right: 20px;">';
                        echo '<span class="success-mark">✅</span> ' . $func . '()';
                        echo '</div>';
                    } else {
                        echo '<div class="check-item" style="padding-right: 20px;">';
                        echo '<span class="error-mark">❌</span> ' . $func . '() - לא נמצאה';
                        echo '</div>';
                        $errors++;
                    }
                }
            } else {
                echo '<div class="check-item"><span class="error-mark">❌</span> שגיאה בטעינת הקובץ!</div>';
                $errors++;
                $allGood = false;
                
                // הצג שגיאות PHP
                $error = error_get_last();
                if ($error) {
                    echo '<pre>';
                    echo 'PHP Error: ' . $error['message'] . "\n";
                    echo 'File: ' . $error['file'] . "\n";
                    echo 'Line: ' . $error['line'];
                    echo '</pre>';
                }
            }
        } else {
            echo '<div class="check-item"><span class="error-mark">❌</span> הקובץ permissions-init.php לא נמצא!</div>';
            $errors++;
            $allGood = false;
        }
        echo '</div>';
        
        // בדיקה 6: חיבור DB
        echo '<div class="check-section">';
        echo '<div class="check-title">🗄️ בדיקת חיבור למסד נתונים</div>';
        
        $configFile = $baseDir . '/config.php';
        if (file_exists($configFile)) {
            echo '<div class="check-item"><span class="success-mark">✅</span> config.php נמצא</div>';
            
            // נסה לטעון
            @include_once $configFile;
            
            if (defined('DB_HOST') && defined('DB_NAME')) {
                echo '<div class="check-item"><span class="success-mark">✅</span> הגדרות DB נטענו</div>';
                echo '<div class="check-item">Host: ' . DB_HOST . '</div>';
                echo '<div class="check-item">Database: ' . DB_NAME . '</div>';
                
                // נסה להתחבר
                if (function_exists('getDBConnection')) {
                    try {
                        $pdo = getDBConnection();
                        if ($pdo) {
                            echo '<div class="check-item"><span class="success-mark">✅</span> חיבור ל-DB הצליח!</div>';
                        }
                    } catch (Exception $e) {
                        echo '<div class="check-item"><span class="warning-mark">⚠️</span> שגיאת חיבור: ' . $e->getMessage() . '</div>';
                        $warnings++;
                    }
                }
            } else {
                echo '<div class="check-item"><span class="warning-mark">⚠️</span> הגדרות DB לא נטענו</div>';
                $warnings++;
            }
        } else {
            echo '<div class="check-item"><span class="error-mark">❌</span> config.php לא נמצא!</div>';
            $errors++;
        }
        echo '</div>';
        
        // בדיקה 7: בדיקת HTTPS
        echo '<div class="check-section ' . (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'success' : 'warning') . '">';
        echo '<div class="check-title">🔒 בדיקת HTTPS</div>';
        
        $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || $_SERVER['SERVER_PORT'] == 443;
        
        if ($isHttps) {
            echo '<div class="check-item"><span class="success-mark">✅</span> האתר רץ ב-HTTPS</div>';
        } else {
            echo '<div class="check-item"><span class="warning-mark">⚠️</span> האתר לא רץ ב-HTTPS</div>';
            echo '<div class="check-item">חלק מההרשאות (מצלמה, מיקום) דורשות HTTPS לפעולה תקינה.</div>';
            $warnings++;
        }
        
        echo '<div class="check-item">Protocol: ' . (!empty($_SERVER['HTTPS']) ? 'HTTPS' : 'HTTP') . '</div>';
        echo '<div class="check-item">Port: ' . $_SERVER['SERVER_PORT'] . '</div>';
        echo '</div>';
        
        // סיכום
        $summaryClass = $allGood ? 'success' : 'error';
        echo '<div class="summary ' . $summaryClass . '">';
        
        if ($allGood && $errors == 0) {
            echo '<h2>✅ המערכת מוכנה לשימוש!</h2>';
            echo '<p>כל הקבצים הקריטיים נמצאו והמערכת נטענה בהצלחה.</p>';
        } else {
            echo '<h2>❌ נמצאו בעיות במערכת</h2>';
            echo '<p>';
            echo 'שגיאות: ' . $errors . ' | ';
            echo 'אזהרות: ' . $warnings;
            echo '</p>';
            echo '<p>יש לתקן את הבעיות הקריטיות (באדום) לפני שימוש במערכת.</p>';
        }
        
        echo '<div style="margin-top: 20px;">';
        if ($allGood) {
            echo '<a href="/permissions/debug/permissions-debug.php" class="btn">🔧 פתח דף דיבוג מלא</a>';
        }
        echo '<a href="/auth/login.php" class="btn">🔐 חזור לדף התחברות</a>';
        echo '</div>';
        
        echo '</div>';
        
        // מידע נוסף לדיבוג
        echo '<div class="check-section" style="margin-top: 30px;">';
        echo '<div class="check-title">📊 מידע נוסף לדיבוג</div>';
        echo '<pre>';
        echo 'PHP Version: ' . phpversion() . "\n";
        echo 'Server: ' . $_SERVER['SERVER_SOFTWARE'] . "\n";
        echo 'Document Root: ' . $_SERVER['DOCUMENT_ROOT'] . "\n";
        echo 'Script Path: ' . __FILE__ . "\n";
        echo 'Permissions Dir: ' . $permissionsDir . "\n";
        echo '</pre>';
        echo '</div>';
        ?>
    </div>
</body>
</html>