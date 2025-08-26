<?php
/**
 * קובץ בדיקה למערכת ההרשאות
 * test-permissions.php
 * 
 * שים את הקובץ בשורש האתר ופתח אותו בדפדפן
 */

// טיפול בשגיאות
error_reporting(E_ALL);
ini_set('display_errors', 1);

// בדיקת מיקום הקובץ
echo "<h1>🔍 בדיקת מערכת הרשאות</h1>";
echo "<pre style='background: #f5f5f5; padding: 10px;'>";

// בדיקה 1: האם קובץ האתחול קיים?
$initFile = __DIR__ . '/permissions/permissions-init.php';
echo "1. בדיקת קובץ אתחול:\n";
if (file_exists($initFile)) {
    echo "   ✅ הקובץ נמצא: $initFile\n";
} else {
    echo "   ❌ הקובץ לא נמצא: $initFile\n";
    echo "   נתיב מלא: " . realpath(__DIR__) . "/permissions/permissions-init.php\n";
    die("</pre><h2>❌ הקובץ permissions-init.php לא נמצא!</h2>");
}

// בדיקה 2: טעינת הקובץ
echo "\n2. טעינת קובץ אתחול:\n";
try {
    $result = @include_once $initFile;
    if ($result === false) {
        echo "   ❌ שגיאה בטעינת הקובץ\n";
    } else {
        echo "   ✅ הקובץ נטען בהצלחה\n";
    }
} catch (Exception $e) {
    echo "   ❌ שגיאה: " . $e->getMessage() . "\n";
}

// בדיקה 3: בדיקת פונקציות
echo "\n3. בדיקת פונקציות:\n";
$functions = [
    'getPermissionsHeaders',
    'getPermissionsScripts', 
    'checkPermission',
    'checkAllPermissions'
];

foreach ($functions as $func) {
    if (function_exists($func)) {
        echo "   ✅ הפונקציה $func קיימת\n";
    } else {
        echo "   ❌ הפונקציה $func לא קיימת\n";
    }
}

// בדיקה 4: בדיקת מנהל ההרשאות
echo "\n4. בדיקת מנהל הרשאות:\n";
if (isset($GLOBALS['permissionsManager'])) {
    echo "   ✅ מנהל ההרשאות נוצר\n";
    echo "   Class: " . get_class($GLOBALS['permissionsManager']) . "\n";
} else {
    echo "   ❌ מנהל ההרשאות לא נוצר\n";
}

// בדיקה 5: בדיקת קבצי Core
echo "\n5. בדיקת קבצי Core:\n";
$coreFiles = [
    'PermissionsManager.php',
    'PermissionStorage.php', 
    'PermissionTypes.php'
];

foreach ($coreFiles as $file) {
    $path = __DIR__ . '/permissions/core/' . $file;
    if (file_exists($path)) {
        echo "   ✅ $file נמצא\n";
    } else {
        echo "   ❌ $file חסר\n";
    }
}

// בדיקה 6: בדיקת Handlers
echo "\n6. בדיקת Handlers:\n";
$handlers = [
    'NotificationHandler.php',
    'PushHandler.php',
    'MediaHandler.php',
    'StorageHandler.php',
    'LocationHandler.php',
    'BackgroundHandler.php'
];

foreach ($handlers as $handler) {
    $path = __DIR__ . '/permissions/handlers/' . $handler;
    if (file_exists($path)) {
        echo "   ✅ $handler נמצא\n";
    } else {
        echo "   ⚠️ $handler חסר (אופציונלי)\n";
    }
}

// בדיקה 7: בדיקת חיבור DB
echo "\n7. בדיקת חיבור DB:\n";
if (file_exists(__DIR__ . '/config.php')) {
    echo "   ✅ קובץ config.php נמצא\n";
    
    // נסה לטעון את הקובץ
    @include_once __DIR__ . '/config.php';
    
    if (defined('DB_HOST')) {
        echo "   ✅ הגדרות DB נטענו\n";
        echo "   DB_HOST: " . DB_HOST . "\n";
        echo "   DB_NAME: " . DB_NAME . "\n";
    } else {
        echo "   ⚠️ הגדרות DB לא נטענו\n";
    }
} else {
    echo "   ❌ קובץ config.php לא נמצא\n";
}

// בדיקה 8: בדיקת HTTPS
echo "\n8. בדיקת HTTPS:\n";
if (function_exists('isHTTPS')) {
    $isHttps = isHTTPS();
    if ($isHttps) {
        echo "   ✅ האתר רץ ב-HTTPS\n";
    } else {
        echo "   ⚠️ האתר לא רץ ב-HTTPS (חלק מההרשאות לא יעבדו)\n";
    }
} else {
    echo "   ❌ הפונקציה isHTTPS לא קיימת\n";
}

// בדיקה 9: בדיקת Session
echo "\n9. בדיקת Session:\n";
if (session_status() === PHP_SESSION_ACTIVE) {
    echo "   ✅ Session פעיל\n";
    if (isset($_SESSION['user_id'])) {
        echo "   ✅ user_id: " . $_SESSION['user_id'] . "\n";
    } else {
        echo "   ⚠️ user_id לא מוגדר בסשן\n";
    }
} else {
    echo "   ⚠️ Session לא פעיל\n";
}

// בדיקה 10: נסה לבדוק הרשאות
echo "\n10. בדיקת הרשאות:\n";
if (function_exists('checkAllPermissions')) {
    try {
        $permissions = checkAllPermissions();
        if (is_array($permissions)) {
            echo "   ✅ בדיקת הרשאות עובדת\n";
            echo "   מספר הרשאות: " . count($permissions) . "\n";
        } else {
            echo "   ⚠️ לא הוחזרו הרשאות\n";
        }
    } catch (Exception $e) {
        echo "   ❌ שגיאה בבדיקת הרשאות: " . $e->getMessage() . "\n";
    }
}

echo "</pre>";

// סיכום
echo "<h2>📊 סיכום</h2>";
echo "<div style='background: #f0f8ff; padding: 15px; border-radius: 5px;'>";

if (isset($GLOBALS['permissionsManager'])) {
    echo "<p style='color: green; font-size: 18px;'>✅ <strong>המערכת פועלת!</strong></p>";
    echo "<p>אפשר להשתמש במערכת ההרשאות.</p>";
    echo "<p><a href='/permissions/debug/permissions-debug.php'>פתח את דף הדיבוג המלא</a></p>";
} else {
    echo "<p style='color: red; font-size: 18px;'>❌ <strong>המערכת לא פועלת</strong></p>";
    echo "<p>בדוק את השגיאות למעלה ותקן אותן.</p>";
}

echo "</div>";

// הצג שגיאות PHP אם יש
$error = error_get_last();
if ($error && $error['type'] === E_ERROR) {
    echo "<h3>❌ שגיאת PHP:</h3>";
    echo "<pre style='background: #ffeeee; padding: 10px; color: red;'>";
    echo "Message: " . $error['message'] . "\n";
    echo "File: " . $error['file'] . "\n";
    echo "Line: " . $error['line'] . "\n";
    echo "</pre>";
}
?>

<style>
body {
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
    max-width: 1200px;
    margin: 20px auto;
    padding: 20px;
    direction: rtl;
}
h1 { color: #333; }
h2 { color: #555; }
h3 { color: #777; }
pre { 
    overflow-x: auto;
    direction: ltr;
    text-align: left;
}
</style>