<?php
// dashboard/debug.php - בדיקת מערכת הדשבורד
session_start();
require_once '../config.php';

echo "<div style='direction: rtl; font-family: Arial; padding: 20px;'>";
echo "<h1>🔍 בדיקת מערכת הדשבורד</h1>";

// 1. בדיקת סשן
echo "<h2>1. בדיקת Session:</h2>";
echo "<pre style='background: #f0f0f0; padding: 10px;'>";
if (isset($_SESSION['user_id'])) {
    echo "✅ user_id: " . $_SESSION['user_id'] . "\n";
    echo "✅ username: " . ($_SESSION['username'] ?? 'לא מוגדר') . "\n";
} else {
    echo "❌ אין user_id בסשן - צריך להתחבר!";
}
echo "</pre>";

// 2. בדיקת חיבור למסד
echo "<h2>2. בדיקת חיבור למסד:</h2>";
echo "<pre style='background: #f0f0f0; padding: 10px;'>";
try {
    $pdo = getDBConnection();
    echo "✅ חיבור למסד נתונים תקין\n";
} catch (Exception $e) {
    echo "❌ בעיה בחיבור: " . $e->getMessage();
}
echo "</pre>";

// 3. בדיקת טבלאות
echo "<h2>3. בדיקת טבלאות:</h2>";
echo "<pre style='background: #f0f0f0; padding: 10px;'>";
try {
    // בדיקת טבלת users
    $stmt = $pdo->query("SELECT COUNT(*) FROM users");
    $count = $stmt->fetchColumn();
    echo "✅ טבלת users קיימת - $count משתמשים\n";
    
    // בדיקת טבלת permissions
    $stmt = $pdo->query("SELECT COUNT(*) FROM user_permissions");
    $count = $stmt->fetchColumn();
    echo "✅ טבלת user_permissions קיימת - $count רשומות\n";
} catch (Exception $e) {
    echo "❌ בעיה: " . $e->getMessage();
}
echo "</pre>";

// 4. בדיקת הרשאות המשתמש הנוכחי
if (isset($_SESSION['user_id'])) {
    echo "<h2>4. הרשאות המשתמש הנוכחי:</h2>";
    echo "<pre style='background: #f0f0f0; padding: 10px;'>";
    try {
        $stmt = $pdo->prepare("
            SELECT u.*, up.dashboard_type 
            FROM users u
            LEFT JOIN user_permissions up ON u.id = up.user_id
            WHERE u.id = ?
        ");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user) {
            echo "ID: " . $user['id'] . "\n";
            echo "Username: " . $user['username'] . "\n";
            echo "Email: " . $user['email'] . "\n";
            echo "Dashboard Type: " . ($user['dashboard_type'] ?? 'לא מוגדר') . "\n";
            echo "Is Active: " . ($user['is_active'] ? 'כן' : 'לא') . "\n";
            
            if ($user['dashboard_type'] === 'admin') {
                echo "\n🎉 אתה מנהל מערכת!";
            } else {
                echo "\n⚠️ אין לך הרשאת מנהל";
            }
        } else {
            echo "❌ משתמש לא נמצא";
        }
    } catch (Exception $e) {
        echo "❌ שגיאה: " . $e->getMessage();
    }
    echo "</pre>";
}

// 5. רשימת כל המנהלים
echo "<h2>5. רשימת מנהלים במערכת:</h2>";
echo "<pre style='background: #f0f0f0; padding: 10px;'>";
try {
    $stmt = $pdo->query("
        SELECT u.id, u.username, u.email 
        FROM users u
        INNER JOIN user_permissions up ON u.id = up.user_id
        WHERE up.dashboard_type = 'admin'
    ");
    $admins = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($admins) > 0) {
        foreach ($admins as $admin) {
            echo "👨‍💼 ID: {$admin['id']}, Username: {$admin['username']}, Email: {$admin['email']}\n";
        }
    } else {
        echo "⚠️ אין מנהלים במערכת!";
    }
} catch (Exception $e) {
    echo "❌ שגיאה: " . $e->getMessage();
}
echo "</pre>";

// 6. בדיקת קבצים
echo "<h2>6. בדיקת קבצים:</h2>";
echo "<pre style='background: #f0f0f0; padding: 10px;'>";
$files = [
    'index.php' => __DIR__ . '/index.php',
    'dashboards/admin.php' => __DIR__ . '/dashboards/admin.php',
    'dashboards/default.php' => __DIR__ . '/dashboards/default.php'
];

foreach ($files as $name => $path) {
    if (file_exists($path)) {
        echo "✅ $name קיים\n";
    } else {
        echo "❌ $name חסר\n";
    }
}
echo "</pre>";

// 7. הפוך אותי למנהל
if (isset($_GET['make_admin']) && isset($_SESSION['user_id'])) {
    echo "<h2>7. הופך אותך למנהל...</h2>";
    echo "<pre style='background: #ffffcc; padding: 10px;'>";
    try {
        $stmt = $pdo->prepare("
            INSERT INTO user_permissions (user_id, dashboard_type) 
            VALUES (?, 'admin')
            ON DUPLICATE KEY UPDATE dashboard_type = 'admin'
        ");
        $stmt->execute([$_SESSION['user_id']]);
        echo "✅ הפכת למנהל מערכת!";
        echo "\n<a href='/dashboard/'>לך לדשבורד</a>";
    } catch (Exception $e) {
        echo "❌ שגיאה: " . $e->getMessage();
    }
    echo "</pre>";
}

// כפתורים
echo "<hr>";
echo "<h2>פעולות:</h2>";
echo "<p>";
echo "<a href='?make_admin=1' style='background: #667eea; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>🎯 הפוך אותי למנהל</a> ";
echo "<a href='/dashboard/' style='background: #10b981; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; margin-right: 10px;'>📊 לדשבורד</a> ";
echo "<a href='/auth/logout.php' style='background: #ef4444; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; margin-right: 10px;'>🚪 יציאה</a>";
echo "</p>";

echo "</div>";