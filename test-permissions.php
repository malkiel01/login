<?php
/**
 * בדיקת מערכת הרשאות - גרסה מפורטת עם תפיסת שגיאות
 * permissions-check-detailed.php
 */

// מניעת הפניות
if (!defined('SKIP_AUTH_CHECK')) {
    define('SKIP_AUTH_CHECK', true);
}

// הצג כל השגיאות
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Custom error handler
function customErrorHandler($errno, $errstr, $errfile, $errline) {
    echo "<div style='background:#fee; padding:10px; margin:5px; border-left:3px solid red;'>";
    echo "<strong>שגיאת PHP:</strong><br>";
    echo "קובץ: " . basename($errfile) . " שורה: $errline<br>";
    echo "הודעה: $errstr";
    echo "</div>";
    return false; // המשך לטפל בשגיאה כרגיל
}
set_error_handler("customErrorHandler");

// Exception handler
function customExceptionHandler($exception) {
    echo "<div style='background:#fee; padding:10px; margin:5px; border-left:3px solid red;'>";
    echo "<strong>Exception:</strong><br>";
    echo "קובץ: " . basename($exception->getFile()) . " שורה: " . $exception->getLine() . "<br>";
    echo "הודעה: " . $exception->getMessage();
    echo "</div>";
}
set_exception_handler("customExceptionHandler");

// Headers
header('Content-Type: text/html; charset=UTF-8');
?>
<!DOCTYPE html>
<html dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>בדיקת הרשאות - מפורט</title>
    <style>
        body { 
            font-family: Arial, sans-serif; 
            margin: 20px;
            background: #f5f5f5;
        }
        .section {
            background: white;
            padding: 15px;
            margin: 10px 0;
            border-radius: 5px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        .success { color: #10b981; }
        .warning { color: #f59e0b; }
        .error { color: #ef4444; }
        h2 { 
            color: #333;
            border-bottom: 2px solid #667eea;
            padding-bottom: 5px;
        }
        pre {
            background: #f0f0f0;
            padding: 10px;
            overflow-x: auto;
            font-size: 12px;
        }
        .code {
            background: #1f2937;
            color: #10b981;
            padding: 10px;
            border-radius: 5px;
            font-family: monospace;
            overflow-x: auto;
        }
    </style>
</head>
<body>
    <h1>🔍 בדיקת מערכת הרשאות - מפורט</h1>
    
    <?php
    $baseDir = dirname(__FILE__);
    $permDir = $baseDir . '/permissions';
    ?>
    
    <div class="section">
        <h2>📁 בדיקת מבנה</h2>
        <?php
        echo "תיקיית בסיס: <code>$baseDir</code><br>";
        echo "תיקיית הרשאות: <code>$permDir</code><br><br>";
        
        if (is_dir($permDir)) {
            echo "<span class='success'>✅ תיקיית permissions קיימת</span><br>";
            
            // רשימת קבצים
            echo "<h3>קבצים בתיקייה:</h3>";
            echo "<div class='code'>";
            $files = scandir($permDir);
            foreach ($files as $file) {
                if ($file != '.' && $file != '..') {
                    $path = $permDir . '/' . $file;
                    if (is_dir($path)) {
                        echo "📁 $file/<br>";
                        $subfiles = scandir($path);
                        foreach ($subfiles as $subfile) {
                            if ($subfile != '.' && $subfile != '..') {
                                echo "&nbsp;&nbsp;&nbsp;📄 $subfile<br>";
                            }
                        }
                    } else {
                        echo "📄 $file<br>";
                    }
                }
            }
            echo "</div>";
        } else {
            echo "<span class='error'>❌ תיקיית permissions לא קיימת!</span>";
        }
        ?>
    </div>
    
    <div class="section">
        <h2>🔧 בדיקת קובץ config.php</h2>
        <?php
        $configFile = $baseDir . '/config.php';
        if (file_exists($configFile)) {
            echo "<span class='success'>✅ config.php נמצא</span><br>";
            
            // נסה לכלול
            echo "מנסה לטעון config.php...<br>";
            ob_start();
            $configLoaded = false;
            
            try {
                @include_once $configFile;
                $configLoaded = true;
                echo "<span class='success'>✅ config.php נטען</span><br>";
            } catch (Exception $e) {
                echo "<span class='error'>❌ שגיאה בטעינת config.php: " . $e->getMessage() . "</span><br>";
            }
            
            $output = ob_get_clean();
            if ($output) {
                echo "<div class='warning'>פלט מ-config.php:</div>";
                echo "<pre>$output</pre>";
            }
            
            // בדוק constants
            if ($configLoaded) {
                $constants = ['DB_HOST', 'DB_NAME', 'DB_USER', 'DB_CHARSET'];
                foreach ($constants as $const) {
                    if (defined($const)) {
                        echo "<span class='success'>✅ $const מוגדר</span><br>";
                    } else {
                        echo "<span class='warning'>⚠️ $const לא מוגדר</span><br>";
                    }
                }
                
                // בדוק פונקציה
                if (function_exists('getDBConnection')) {
                    echo "<span class='success'>✅ פונקציית getDBConnection קיימת</span><br>";
                } else {
                    echo "<span class='warning'>⚠️ פונקציית getDBConnection לא קיימת</span><br>";
                }
            }
        } else {
            echo "<span class='error'>❌ config.php לא נמצא!</span><br>";
            echo "נתיב מצופה: <code>$configFile</code>";
        }
        ?>
    </div>
    
    <div class="section">
        <h2>⚙️ טעינת permissions-init.php</h2>
        <?php
        $initFile = $permDir . '/permissions-init.php';
        
        if (file_exists($initFile)) {
            echo "<span class='success'>✅ permissions-init.php נמצא</span><br>";
            echo "גודל: " . filesize($initFile) . " bytes<br><br>";
            
            echo "מנסה לטעון...<br>";
            
            // Capture any output
            ob_start();
            $loadSuccess = false;
            
            try {
                // כבה דיווח שגיאות זמנית כדי לתפוס אותן
                $oldErrorReporting = error_reporting(E_ALL);
                
                // נסה לטעון
                $result = include_once $initFile;
                
                if ($result === false) {
                    echo "<span class='error'>❌ include_once החזיר false</span><br>";
                } else {
                    echo "<span class='success'>✅ הקובץ נטען</span><br>";
                    $loadSuccess = true;
                }
                
                error_reporting($oldErrorReporting);
                
            } catch (ParseError $e) {
                echo "<span class='error'>❌ Parse Error: " . $e->getMessage() . "</span><br>";
                echo "שורה: " . $e->getLine() . "<br>";
            } catch (Error $e) {
                echo "<span class='error'>❌ Fatal Error: " . $e->getMessage() . "</span><br>";
                echo "קובץ: " . basename($e->getFile()) . "<br>";
                echo "שורה: " . $e->getLine() . "<br>";
            } catch (Exception $e) {
                echo "<span class='error'>❌ Exception: " . $e->getMessage() . "</span><br>";
                echo "קובץ: " . basename($e->getFile()) . "<br>";
                echo "שורה: " . $e->getLine() . "<br>";
            }
            
            $output = ob_get_clean();
            if ($output) {
                echo "<div class='warning'>פלט מהטעינה:</div>";
                echo "<pre>$output</pre>";
            }
            
            // בדוק מה נטען
            if ($loadSuccess) {
                echo "<h3>בדיקת פונקציות:</h3>";
                $functions = [
                    'getPermissionsHeaders',
                    'getPermissionsScripts',
                    'checkPermission',
                    'checkAllPermissions',
                    'getMissingCriticalPermissions',
                    'renderPermissionsBanner'
                ];
                
                foreach ($functions as $func) {
                    if (function_exists($func)) {
                        echo "<span class='success'>✅ $func()</span><br>";
                    } else {
                        echo "<span class='error'>❌ $func() לא נמצאה</span><br>";
                    }
                }
                
                // בדוק classes
                echo "<h3>בדיקת Classes:</h3>";
                $classes = [
                    'Permissions\Core\PermissionsManager',
                    'Permissions\Core\PermissionStorage',
                    'Permissions\Core\PermissionTypes'
                ];
                
                foreach ($classes as $class) {
                    if (class_exists($class)) {
                        echo "<span class='success'>✅ $class</span><br>";
                    } else {
                        echo "<span class='error'>❌ $class לא נמצא</span><br>";
                    }
                }
                
                // בדוק משתנה גלובלי
                echo "<h3>בדיקת מנהל הרשאות:</h3>";
                if (isset($GLOBALS['permissionsManager'])) {
                    echo "<span class='success'>✅ \$GLOBALS['permissionsManager'] קיים</span><br>";
                    echo "Type: " . get_class($GLOBALS['permissionsManager']) . "<br>";
                } else {
                    echo "<span class='error'>❌ \$GLOBALS['permissionsManager'] לא נוצר</span><br>";
                }
            }
            
        } else {
            echo "<span class='error'>❌ permissions-init.php לא נמצא!</span><br>";
        }
        ?>
    </div>
    
    <div class="section">
        <h2>📝 שגיאות PHP אחרונות</h2>
        <?php
        $error = error_get_last();
        if ($error && ($error['type'] === E_ERROR || $error['type'] === E_PARSE)) {
            echo "<div class='error'>";
            echo "<strong>סוג:</strong> " . $error['type'] . "<br>";
            echo "<strong>הודעה:</strong> " . $error['message'] . "<br>";
            echo "<strong>קובץ:</strong> " . basename($error['file']) . "<br>";
            echo "<strong>שורה:</strong> " . $error['line'] . "<br>";
            echo "</div>";
        } else {
            echo "<span class='success'>אין שגיאות קריטיות</span>";
        }
        ?>
    </div>
    
    <div class="section">
        <h2>🔍 סביבת PHP</h2>
        <?php
        echo "PHP Version: " . phpversion() . "<br>";
        echo "Memory Limit: " . ini_get('memory_limit') . "<br>";
        echo "Max Execution Time: " . ini_get('max_execution_time') . "<br>";
        echo "Display Errors: " . (ini_get('display_errors') ? 'On' : 'Off') . "<br>";
        echo "Error Reporting: " . error_reporting() . "<br>";
        
        // Extensions
        echo "<h3>Extensions רלוונטיות:</h3>";
        $extensions = ['pdo', 'pdo_mysql', 'json', 'session', 'mbstring'];
        foreach ($extensions as $ext) {
            if (extension_loaded($ext)) {
                echo "<span class='success'>✅ $ext</span><br>";
            } else {
                echo "<span class='error'>❌ $ext חסר</span><br>";
            }
        }
        ?>
    </div>
    
    <div class="section">
        <h2>💡 פעולות מומלצות</h2>
        <?php
        if (!$loadSuccess) {
            echo "<p>נראה שיש בעיה בטעינת המערכת. נסה:</p>";
            echo "<ol>";
            echo "<li>בדוק את השגיאות למעלה</li>";
            echo "<li>וודא שכל הקבצים במקומם</li>";
            echo "<li>בדוק הרשאות קבצים (chmod 644)</li>";
            echo "<li>בדוק שה-namespace נכון בקבצי PHP</li>";
            echo "</ol>";
        } else {
            echo "<p class='success'>✅ המערכת נטענה בהצלחה!</p>";
            echo "<p>עכשיו אפשר:</p>";
            echo "<ul>";
            echo "<li><a href='/permissions/debug/permissions-debug.php'>לפתוח את דף הדיבוג המלא</a></li>";
            echo "<li><a href='/auth/login.php'>לחזור לדף ההתחברות</a></li>";
            echo "</ul>";
        }
        ?>
    </div>
</body>
</html>