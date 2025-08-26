<?php
/**
 * בדיקת מערכת הרשאות - גרסה פשוטה
 * permissions-check.php
 */

// מניעת הפניות - חשוב!
if (!defined('SKIP_AUTH_CHECK')) {
    define('SKIP_AUTH_CHECK', true);
}

// מניעת session אוטומטי שעלול לגרום להפניה
session_write_close();

// הצג שגיאות
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Headers
header('Content-Type: text/html; charset=UTF-8');
header('X-Robots-Tag: noindex');

// פונקציה לבדיקת קובץ
function checkFile($path, $required = false) {
    if (file_exists($path)) {
        $size = filesize($path);
        return "✅ קיים (" . number_format($size) . " bytes)";
    } else {
        return $required ? "❌ חסר!" : "⚠️ לא נמצא (אופציונלי)";
    }
}

// התחלת HTML
?>
<!DOCTYPE html>
<html dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>בדיקת מערכת הרשאות</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 1000px;
            margin: 20px auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .box {
            background: white;
            padding: 20px;
            margin: 10px 0;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        h1 { color: #333; }
        h2 { 
            color: #555; 
            border-bottom: 2px solid #667eea;
            padding-bottom: 5px;
        }
        .success { color: #10b981; }
        .warning { color: #f59e0b; }
        .error { color: #ef4444; }
        pre {
            background: #f0f0f0;
            padding: 10px;
            border-radius: 5px;
            overflow-x: auto;
        }
        .file-list {
            font-family: monospace;
            font-size: 14px;
        }
        .file-item {
            padding: 3px 0;
        }
        .summary {
            padding: 15px;
            border-radius: 8px;
            margin: 20px 0;
            font-size: 18px;
            text-align: center;
        }
        .summary.ok {
            background: #d4f4dd;
            color: #0e7c3a;
        }
        .summary.error {
            background: #fee2e2;
            color: #991b1b;
        }
        .btn {
            display: inline-block;
            padding: 10px 20px;
            background: #667eea;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            margin: 5px;
        }
        .btn:hover {
            background: #5a6ed8;
        }
    </style>
</head>
<body>
    <h1>🔍 בדיקת מערכת הרשאות</h1>
    
    <div class="box">
        <h2>📁 מבנה תיקיות</h2>
        <div class="file-list">
            <?php
            $base = dirname(__FILE__);
            $permDir = $base . '/permissions';
            
            echo "<div class='file-item'>תיקייה ראשית: $base</div>";
            
            if (is_dir($permDir)) {
                echo "<div class='file-item success'>✅ /permissions/ - נמצאה</div>";
                
                // בדוק תתי תיקיות
                $subdirs = [
                    'core' => true,
                    'handlers' => false,
                    'ui' => false,
                    'ui/styles' => false,
                    'js' => false,
                    'api' => false,
                    'debug' => false
                ];
                
                foreach ($subdirs as $dir => $required) {
                    $path = $permDir . '/' . $dir;
                    if (is_dir($path)) {
                        echo "<div class='file-item success'>  ✅ /$dir/</div>";
                    } else {
                        $class = $required ? 'error' : 'warning';
                        $icon = $required ? '❌' : '⚠️';
                        echo "<div class='file-item $class'>  $icon /$dir/ - לא נמצאה</div>";
                    }
                }
            } else {
                echo "<div class='file-item error'>❌ /permissions/ - לא נמצאה!</div>";
                echo "<div class='file-item'>נתיב מצופה: $permDir</div>";
            }
            ?>
        </div>
    </div>
    
    <div class="box">
        <h2>📄 קבצי Core</h2>
        <div class="file-list">
            <?php
            $files = [
                'permissions-init.php' => $permDir . '/permissions-init.php',
                'PermissionsManager.php' => $permDir . '/core/PermissionsManager.php',
                'PermissionStorage.php' => $permDir . '/core/PermissionStorage.php',
                'PermissionTypes.php' => $permDir . '/core/PermissionTypes.php'
            ];
            
            $allCoreExists = true;
            foreach ($files as $name => $path) {
                $status = checkFile($path, true);
                echo "<div class='file-item'>$name: $status</div>";
                if (!file_exists($path)) {
                    $allCoreExists = false;
                }
            }
            ?>
        </div>
    </div>
    
    <div class="box">
        <h2>🔧 Handlers (אופציונלי)</h2>
        <div class="file-list">
            <?php
            $handlers = [
                'NotificationHandler.php',
                'PushHandler.php',
                'MediaHandler.php',
                'StorageHandler.php',
                'LocationHandler.php',
                'BackgroundHandler.php'
            ];
            
            foreach ($handlers as $handler) {
                $path = $permDir . '/handlers/' . $handler;
                $status = checkFile($path, false);
                echo "<div class='file-item'>$handler: $status</div>";
            }
            ?>
        </div>
    </div>
    
    <div class="box">
        <h2>🎨 קבצי UI</h2>
        <div class="file-list">
            <?php
            $uiFiles = [
                'permissions.css' => $permDir . '/ui/styles/permissions.css',
                'permissions-manager.js' => $permDir . '/js/permissions-manager.js',
                'permissions-debug.php' => $permDir . '/debug/permissions-debug.php'
            ];
            
            foreach ($uiFiles as $name => $path) {
                $status = checkFile($path, false);
                echo "<div class='file-item'>$name: $status</div>";
            }
            ?>
        </div>
    </div>
    
    <div class="box">
        <h2>⚙️ בדיקת טעינה</h2>
        <?php
        $canLoad = false;
        if ($allCoreExists) {
            echo "<div>מנסה לטעון את המערכת...</div>";
            
            // כבה דיווח שגיאות זמנית
            $oldError = error_reporting(0);
            $result = @include_once $permDir . '/permissions-init.php';
            error_reporting($oldError);
            
            if ($result !== false) {
                echo "<div class='success'>✅ המערכת נטענה בהצלחה!</div>";
                $canLoad = true;
                
                // בדוק פונקציות
                $funcs = ['getPermissionsHeaders', 'getPermissionsScripts', 'checkPermission'];
                echo "<div>פונקציות זמינות:</div>";
                foreach ($funcs as $func) {
                    if (function_exists($func)) {
                        echo "<div class='success'>  ✅ $func()</div>";
                    } else {
                        echo "<div class='error'>  ❌ $func()</div>";
                    }
                }
            } else {
                echo "<div class='error'>❌ שגיאה בטעינת המערכת</div>";
                $error = error_get_last();
                if ($error) {
                    echo "<pre>" . print_r($error, true) . "</pre>";
                }
            }
        } else {
            echo "<div class='error'>❌ חסרים קבצי Core - לא ניתן לטעון</div>";
        }
        ?>
    </div>
    
    <div class="box">
        <h2>🗄️ בדיקת Database</h2>
        <?php
        $configFile = $base . '/config.php';
        if (file_exists($configFile)) {
            echo "<div class='success'>✅ config.php נמצא</div>";
            
            @include_once $configFile;
            
            if (defined('DB_HOST')) {
                echo "<div>Host: " . DB_HOST . "</div>";
                echo "<div>Database: " . DB_NAME . "</div>";
                
                // נסה חיבור
                try {
                    if (function_exists('getDBConnection')) {
                        $pdo = @getDBConnection();
                        if ($pdo) {
                            echo "<div class='success'>✅ חיבור למסד נתונים הצליח</div>";
                        }
                    }
                } catch (Exception $e) {
                    echo "<div class='warning'>⚠️ בעיית חיבור: " . $e->getMessage() . "</div>";
                }
            } else {
                echo "<div class='warning'>⚠️ הגדרות DB לא נטענו</div>";
            }
        } else {
            echo "<div class='error'>❌ config.php לא נמצא</div>";
        }
        ?>
    </div>
    
    <div class="box">
        <h2>🔒 בדיקת HTTPS</h2>
        <?php
        $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || $_SERVER['SERVER_PORT'] == 443;
        if ($isHttps) {
            echo "<div class='success'>✅ האתר רץ ב-HTTPS</div>";
        } else {
            echo "<div class='warning'>⚠️ האתר לא רץ ב-HTTPS - חלק מההרשאות לא יעבדו</div>";
        }
        echo "<div>Protocol: " . (isset($_SERVER['HTTPS']) ? 'HTTPS' : 'HTTP') . "</div>";
        echo "<div>Port: " . $_SERVER['SERVER_PORT'] . "</div>";
        ?>
    </div>
    
    <?php
    // סיכום
    if ($allCoreExists && $canLoad) {
        echo '<div class="summary ok">';
        echo '✅ המערכת מוכנה לשימוש!';
        echo '</div>';
        echo '<div style="text-align: center;">';
        echo '<a href="/permissions/debug/permissions-debug.php" class="btn">פתח דף דיבוג מלא</a>';
        echo '<a href="/auth/login.php" class="btn">חזור להתחברות</a>';
        echo '</div>';
    } else {
        echo '<div class="summary error">';
        echo '❌ המערכת לא מוכנה - יש לתקן את הבעיות';
        echo '</div>';
    }
    ?>
    
    <div class="box" style="background: #f0f0f0; font-size: 12px;">
        <strong>מידע נוסף:</strong><br>
        PHP: <?php echo phpversion(); ?><br>
        Server: <?php echo $_SERVER['SERVER_SOFTWARE']; ?><br>
        Path: <?php echo __FILE__; ?>
    </div>
</body>
</html>