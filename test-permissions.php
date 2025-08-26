<?php
/**
 * בדיקה חיה של מערכת ההרשאות
 * test-permissions-live.php
 */

// התחל output buffering כדי למנוע בעיות session
ob_start();

// אל תעשה redirect
define('SKIP_AUTH_CHECK', true);

// טען את המערכת
require_once 'permissions/permissions-init.php';

?>
<!DOCTYPE html>
<html dir="rtl" lang="he">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>בדיקה חיה - מערכת הרשאות</title>
    <?php echo getPermissionsHeaders(); ?>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            max-width: 1200px;
            margin: 20px auto;
            padding: 20px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
        }
        .container {
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.1);
        }
        h1 {
            color: #333;
            border-bottom: 3px solid #667eea;
            padding-bottom: 10px;
            margin-bottom: 30px;
        }
        .test-section {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin: 20px 0;
            border-right: 4px solid #667eea;
        }
        .test-title {
            font-size: 18px;
            font-weight: bold;
            color: #667eea;
            margin-bottom: 15px;
        }
        .test-item {
            padding: 8px;
            margin: 5px 0;
            background: white;
            border-radius: 5px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .success { color: #10b981; }
        .warning { color: #f59e0b; }
        .error { color: #ef4444; }
        .info { color: #3b82f6; }
        
        .permission-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 15px;
            margin: 20px 0;
        }
        .permission-card {
            background: white;
            border-radius: 8px;
            padding: 15px;
            border: 2px solid #e5e7eb;
            transition: all 0.3s ease;
        }
        .permission-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        .permission-icon {
            font-size: 24px;
            margin-bottom: 10px;
        }
        .permission-name {
            font-weight: bold;
            margin-bottom: 5px;
        }
        .permission-status {
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 12px;
            display: inline-block;
        }
        .status-granted {
            background: #d4f4dd;
            color: #0e7c3a;
        }
        .status-denied {
            background: #fee2e2;
            color: #991b1b;
        }
        .status-prompt {
            background: #fef3c7;
            color: #92400e;
        }
        .btn {
            padding: 10px 20px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 16px;
            margin: 5px;
            transition: all 0.3s;
        }
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
        }
        pre {
            background: #1f2937;
            color: #10b981;
            padding: 15px;
            border-radius: 8px;
            overflow-x: auto;
            font-size: 12px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🚀 בדיקה חיה - מערכת הרשאות</h1>
        
        <!-- בדיקה 1: מנהל הרשאות -->
        <div class="test-section">
            <div class="test-title">🎯 מנהל הרשאות</div>
            <?php
            if (isset($GLOBALS['permissionsManager'])) {
                echo '<div class="test-item">';
                echo '<span>מנהל הרשאות</span>';
                echo '<span class="success">✅ פעיל</span>';
                echo '</div>';
                
                $manager = $GLOBALS['permissionsManager'];
                echo '<div class="test-item">';
                echo '<span>Class</span>';
                echo '<span class="info">' . get_class($manager) . '</span>';
                echo '</div>';
            } else {
                echo '<div class="test-item">';
                echo '<span>מנהל הרשאות</span>';
                echo '<span class="error">❌ לא נמצא</span>';
                echo '</div>';
            }
            ?>
        </div>
        
        <!-- בדיקה 2: סטטיסטיקות -->
        <div class="test-section">
            <div class="test-title">📊 סטטיסטיקות הרשאות</div>
            <?php
            try {
                $stats = $manager->getPermissionsStats();
                
                echo '<div class="test-item">';
                echo '<span>סה"כ הרשאות</span>';
                echo '<span class="info">' . $stats['total'] . '</span>';
                echo '</div>';
                
                echo '<div class="test-item">';
                echo '<span>הרשאות שאושרו</span>';
                echo '<span class="success">' . $stats['granted'] . '</span>';
                echo '</div>';
                
                echo '<div class="test-item">';
                echo '<span>הרשאות שנדחו</span>';
                echo '<span class="warning">' . $stats['denied'] . '</span>';
                echo '</div>';
                
                echo '<div class="test-item">';
                echo '<span>הרשאות חסומות</span>';
                echo '<span class="error">' . $stats['blocked'] . '</span>';
                echo '</div>';
                
                echo '<div class="test-item">';
                echo '<span>אחוז השלמה</span>';
                echo '<span class="info">' . $stats['completion_percentage'] . '%</span>';
                echo '</div>';
                
            } catch (Exception $e) {
                echo '<div class="error">שגיאה: ' . $e->getMessage() . '</div>';
            }
            ?>
        </div>
        
        <!-- בדיקה 3: כל ההרשאות -->
        <div class="test-section">
            <div class="test-title">🔐 כל ההרשאות</div>
            <div class="permission-grid">
                <?php
                try {
                    $permissions = $manager->checkAllPermissions();
                    
                    foreach ($permissions as $type => $permission) {
                        $statusClass = 'status-' . str_replace('_', '-', $permission['status']);
                        echo '<div class="permission-card">';
                        echo '<div class="permission-icon">' . $permission['icon'] . '</div>';
                        echo '<div class="permission-name">' . $permission['name'] . '</div>';
                        echo '<div class="permission-description" style="font-size: 12px; color: #6b7280; margin: 5px 0;">';
                        echo $permission['description'];
                        echo '</div>';
                        echo '<div class="permission-status ' . $statusClass . '">' . $permission['status'] . '</div>';
                        
                        if ($permission['required_https'] && !isHTTPS()) {
                            echo '<div class="warning" style="font-size: 11px; margin-top: 5px;">⚠️ דורש HTTPS</div>';
                        }
                        
                        echo '</div>';
                    }
                } catch (Exception $e) {
                    echo '<div class="error">שגיאה: ' . $e->getMessage() . '</div>';
                }
                ?>
            </div>
        </div>
        
        <!-- בדיקה 4: הרשאות קריטיות חסרות -->
        <div class="test-section">
            <div class="test-title">⚠️ הרשאות קריטיות חסרות</div>
            <?php
            try {
                $missing = $manager->getMissingCriticalPermissions();
                
                if (empty($missing)) {
                    echo '<div class="success">✅ כל ההרשאות הקריטיות ניתנו!</div>';
                } else {
                    echo '<div class="warning">נמצאו ' . count($missing) . ' הרשאות קריטיות חסרות:</div>';
                    echo '<ul>';
                    foreach ($missing as $permission) {
                        echo '<li>' . $permission['icon'] . ' ' . $permission['name'] . ' - ' . $permission['description'] . '</li>';
                    }
                    echo '</ul>';
                }
            } catch (Exception $e) {
                echo '<div class="error">שגיאה: ' . $e->getMessage() . '</div>';
            }
            ?>
        </div>
        
        <!-- בדיקה 5: דוח מלא -->
        <div class="test-section">
            <div class="test-title">📋 דוח מערכת</div>
            <?php
            try {
                $report = $manager->generatePermissionsReport();
                
                echo '<div class="test-item">';
                echo '<span>User ID</span>';
                echo '<span>' . ($report['user_id'] ?? 'לא מחובר') . '</span>';
                echo '</div>';
                
                echo '<div class="test-item">';
                echo '<span>דפדפן</span>';
                echo '<span>' . $report['browser'] . '</span>';
                echo '</div>';
                
                echo '<div class="test-item">';
                echo '<span>מכשיר</span>';
                echo '<span>' . $report['device'] . '</span>';
                echo '</div>';
                
                if (!empty($report['recommendations'])) {
                    echo '<h4>המלצות:</h4>';
                    echo '<ul>';
                    foreach ($report['recommendations'] as $rec) {
                        echo '<li>' . $rec['message'] . '</li>';
                    }
                    echo '</ul>';
                }
                
            } catch (Exception $e) {
                echo '<div class="error">שגיאה: ' . $e->getMessage() . '</div>';
            }
            ?>
        </div>
        
        <!-- בדיקה 6: באנר הרשאות -->
        <div class="test-section">
            <div class="test-title">🎨 באנר הרשאות</div>
            <?php
            echo renderPermissionsBanner();
            
            if (empty($missing)) {
                echo '<div class="info">אין הרשאות חסרות - הבאנר לא יוצג</div>';
            }
            ?>
        </div>
        
        <!-- בדיקה 7: HTTPS -->
        <div class="test-section">
            <div class="test-title">🔒 בדיקת HTTPS</div>
            <?php
            if (isHTTPS()) {
                echo '<div class="success">✅ האתר רץ ב-HTTPS - כל ההרשאות יעבדו</div>';
            } else {
                echo renderHTTPSWarning();
                echo '<div class="warning">⚠️ חלק מההרשאות (מצלמה, מיקום, התראות) דורשות HTTPS</div>';
            }
            ?>
        </div>
        
        <!-- כפתורי פעולה -->
        <div style="text-align: center; margin-top: 30px;">
            <button class="btn" onclick="testJavaScript()">🧪 בדוק JavaScript</button>
            <button class="btn" onclick="window.location.reload()">🔄 רענן</button>
            <button class="btn" onclick="window.location.href='/permissions/debug/permissions-debug.php'">🔧 דף דיבוג מלא</button>
            <button class="btn" onclick="window.location.href='/auth/login.php'">🔐 חזור להתחברות</button>
        </div>
        
        <!-- Debug Info -->
        <div class="test-section" style="margin-top: 30px;">
            <div class="test-title">🔍 Debug Info</div>
            <pre><?php
            echo "PHP Version: " . phpversion() . "\n";
            echo "Session ID: " . session_id() . "\n";
            echo "User ID: " . ($_SESSION['user_id'] ?? 'Not logged in') . "\n";
            echo "Script: " . $_SERVER['SCRIPT_NAME'] . "\n";
            echo "Method: " . $_SERVER['REQUEST_METHOD'] . "\n";
            echo "Protocol: " . (isHTTPS() ? 'HTTPS' : 'HTTP') . "\n";
            ?></pre>
        </div>
    </div>
    
    <?php echo getPermissionsScripts(['debug' => true]); ?>
    
    <script>
        function testJavaScript() {
            console.log('Testing Permissions Manager...');
            
            if (window.permissionsManager) {
                alert('✅ JavaScript Permissions Manager זמין!');
                console.log(window.permissionsManager);
                
                // נסה לבדוק הרשאה
                permissionsManager.checkPermission('notification').then(result => {
                    console.log('Notification permission:', result);
                });
            } else {
                alert('❌ JavaScript Permissions Manager לא נטען');
            }
        }
    </script>
</body>
</html>