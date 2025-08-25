<?php
// test-notifications.php - דף בדיקה מקיף להתראות
session_start();
require_once 'config.php';

// בדיקת הרשאות - מאפשר גישה בכמה דרכים
$isAuthorized = false;

// דרך 1: סביבת development
if (defined('ENVIRONMENT') && ENVIRONMENT === 'development') {
    $isAuthorized = true;
}

// דרך 2: משתמש מחובר (כל משתמש)
if (!$isAuthorized && isset($_SESSION['user_id'])) {
    $isAuthorized = true;
}

// דרך 3: טוקן מיוחד ב-URL
if (!$isAuthorized && isset($_GET['token']) && $_GET['token'] === 'test123') {
    $isAuthorized = true;
}

// דרך 4: בדיקה אם זה localhost
if (!$isAuthorized && ($_SERVER['HTTP_HOST'] === 'localhost' || $_SERVER['HTTP_HOST'] === '127.0.0.1')) {
    $isAuthorized = true;
}

if (!$isAuthorized) {
    ?>
    <!DOCTYPE html>
    <html dir="rtl" lang="he">
    <head>
        <meta charset="UTF-8">
        <title>גישה נדחתה</title>
        <style>
            body { 
                font-family: Arial, sans-serif; 
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                display: flex;
                align-items: center;
                justify-content: center;
                min-height: 100vh;
                margin: 0;
            }
            .error-box {
                background: white;
                padding: 40px;
                border-radius: 15px;
                box-shadow: 0 10px 30px rgba(0,0,0,0.2);
                text-align: center;
                max-width: 500px;
            }
            h1 { color: #dc3545; }
            p { color: #666; line-height: 1.6; }
            .options { 
                background: #f8f9fa; 
                padding: 20px; 
                border-radius: 10px; 
                margin: 20px 0;
                text-align: right;
            }
            .options li { margin: 10px 0; }
            .btn {
                display: inline-block;
                background: #667eea;
                color: white;
                padding: 10px 20px;
                border-radius: 8px;
                text-decoration: none;
                margin: 10px 5px;
            }
            code {
                background: #f8f9fa;
                padding: 2px 6px;
                border-radius: 3px;
                color: #e83e8c;
            }
        </style>
    </head>
    <body>
        <div class="error-box">
            <h1>🔒 גישה מוגבלת</h1>
            <p>דף זה זמין רק למשתמשים מורשים או בסביבת פיתוח.</p>
            
            <div class="options">
                <strong>אפשרויות גישה:</strong>
                <ol>
                    <li>התחבר למערכת: <a href="auth/login.php" class="btn">התחברות</a></li>
                    <li>השתמש בטוקן: <code>?token=test123</code></li>
                    <li>הגדר ב-.env: <code>ENVIRONMENT=development</code></li>
                </ol>
            </div>
            
            <p><strong>לגישה מהירה:</strong></p>
            <a href="?token=test123" class="btn">🔑 גישה עם טוקן</a>
            <a href="auth/login.php" class="btn">👤 התחברות</a>
        </div>
    </body>
    </html>
    <?php
    exit;
}

$pdo = getDBConnection();
$message = '';
$messageType = '';

// טיפול בשליחת התראת בדיקה
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'send_test_email':
            $to = $_POST['email'];
            $subject = $_POST['subject'];
            $body = $_POST['body'];
            
            // הגדרת headers
            $headers = "From: " . ($_ENV['MAIL_FROM'] ?? 'noreply@panan-bakan.com') . "\r\n";
            $headers .= "Reply-To: " . ($_ENV['MAIL_FROM'] ?? 'noreply@panan-bakan.com') . "\r\n";
            $headers .= "MIME-Version: 1.0\r\n";
            $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
            
            // תוכן HTML
            $htmlBody = "
            <!DOCTYPE html>
            <html dir='rtl'>
            <head>
                <meta charset='UTF-8'>
                <style>
                    body { font-family: Arial, sans-serif; background: #f5f5f5; margin: 0; padding: 20px; }
                    .container { max-width: 600px; margin: 0 auto; background: white; border-radius: 10px; overflow: hidden; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
                    .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; text-align: center; }
                    .header h1 { margin: 0; font-size: 24px; }
                    .content { padding: 30px; }
                    .button { display: inline-block; background: #667eea; color: white; padding: 12px 30px; border-radius: 25px; text-decoration: none; margin: 20px 0; }
                    .footer { background: #f8f9fa; padding: 20px; text-align: center; color: #666; font-size: 14px; }
                </style>
            </head>
            <body>
                <div class='container'>
                    <div class='header'>
                        <h1>🔔 $subject</h1>
                    </div>
                    <div class='content'>
                        <p>$body</p>
                        <a href='https://yourdomain.com/dashboard.php' class='button'>כניסה למערכת</a>
                    </div>
                    <div class='footer'>
                        <p>הודעה זו נשלחה ממערכת ניהול קניות מרוכזות</p>
                        <p>אם אינך מעוניין לקבל התראות, <a href='#'>לחץ כאן</a></p>
                    </div>
                </div>
            </body>
            </html>";
            
            if (mail($to, $subject, $htmlBody, $headers)) {
                $message = "אימייל נשלח בהצלחה ל-$to";
                $messageType = 'success';
            } else {
                $message = "שגיאה בשליחת אימייל";
                $messageType = 'error';
            }
            break;
            
        case 'send_invitation_notification':
            $email = $_POST['target_email'];
            $groupName = $_POST['group_name'];
            $inviterName = $_POST['inviter_name'];
            
            // סימולציה של הזמנה
            $stmt = $pdo->prepare("
                INSERT INTO notification_queue (type, data, status, created_at)
                VALUES ('invitation', ?, 'pending', NOW())
            ");
            $data = json_encode([
                'email' => $email,
                'group_name' => $groupName,
                'inviter_name' => $inviterName,
                'timestamp' => date('Y-m-d H:i:s')
            ]);
            $stmt->execute([$data]);
            
            $message = "התראת הזמנה נוצרה בהצלחה";
            $messageType = 'success';
            break;
            
        case 'test_browser_notification':
            // זה יטופל ב-JavaScript
            break;
            
        case 'clear_queue':
            $pdo->exec("DELETE FROM notification_queue WHERE status = 'pending'");
            $message = "תור ההתראות נוקה";
            $messageType = 'success';
            break;
            
        case 'process_queue':
            // עיבוד תור התראות
            ob_start();
            include 'process_notifications.php';
            $output = ob_get_clean();
            $message = "תור ההתראות עובד. בדוק את הפלט למטה.";
            $messageType = 'success';
            $_SESSION['process_output'] = $output;
            break;
    }
}

// קבלת סטטיסטיקות
$stats = [
    'pending' => 0,
    'sent' => 0,
    'failed' => 0
];

try {
    $stmt = $pdo->query("
        SELECT status, COUNT(*) as count 
        FROM notification_queue 
        WHERE created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)
        GROUP BY status
    ");
    while ($row = $stmt->fetch()) {
        $stats[$row['status']] = $row['count'];
    }
} catch (Exception $e) {
    // טבלה אולי לא קיימת
}

// קבלת התראות אחרונות
$recentNotifications = [];
try {
    $stmt = $pdo->query("
        SELECT * FROM notification_queue 
        ORDER BY created_at DESC 
        LIMIT 10
    ");
    $recentNotifications = $stmt->fetchAll();
} catch (Exception $e) {
    // טבלה אולי לא קיימת
}
?>
<!DOCTYPE html>
<html dir="rtl" lang="he">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>בדיקת מערכת התראות</title>
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
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .header p {
            color: #666;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            text-align: center;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .stat-card.success {
            border-top: 4px solid #28a745;
        }
        
        .stat-card.warning {
            border-top: 4px solid #ffc107;
        }
        
        .stat-card.danger {
            border-top: 4px solid #dc3545;
        }
        
        .stat-value {
            font-size: 36px;
            font-weight: bold;
            margin: 10px 0;
        }
        
        .stat-label {
            color: #666;
            font-size: 14px;
        }
        
        .test-section {
            background: white;
            border-radius: 15px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }
        
        .test-section h2 {
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
        .form-group textarea,
        .form-group select {
            width: 100%;
            padding: 12px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 14px;
            transition: border-color 0.3s;
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
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }
        
        .btn-secondary {
            background: #6c757d;
        }
        
        .btn-danger {
            background: #dc3545;
        }
        
        .btn-success {
            background: #28a745;
        }
        
        .alert {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
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
        
        .alert.info {
            background: #d1ecf1;
            color: #0c5460;
            border: 1px solid #bee5eb;
        }
        
        .notification-list {
            max-height: 400px;
            overflow-y: auto;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            padding: 10px;
        }
        
        .notification-item {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 10px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .notification-item.pending {
            background: #fff3cd;
            border-right: 4px solid #ffc107;
        }
        
        .notification-item.completed {
            background: #d4edda;
            border-right: 4px solid #28a745;
        }
        
        .notification-item.failed {
            background: #f8d7da;
            border-right: 4px solid #dc3545;
        }
        
        .notification-content {
            flex: 1;
        }
        
        .notification-type {
            font-weight: bold;
            color: #333;
            margin-bottom: 5px;
        }
        
        .notification-time {
            font-size: 12px;
            color: #666;
        }
        
        .buttons-row {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-top: 20px;
        }
        
        .test-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
        }
        
        .test-card {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            border: 2px solid #e9ecef;
        }
        
        .test-card h3 {
            color: #667eea;
            margin-bottom: 15px;
        }
        
        .quick-test-btn {
            width: 100%;
            margin-top: 10px;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <h1>
                <span style="font-size: 40px;">🔔</span>
                מערכת בדיקת התראות
            </h1>
            <p>דף בדיקה מקיף לכל סוגי ההתראות במערכת</p>
        </div>
        
        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-card warning">
                <div class="stat-label">ממתינות</div>
                <div class="stat-value"><?php echo $stats['pending'] ?? 0; ?></div>
            </div>
            <div class="stat-card success">
                <div class="stat-label">נשלחו (24 שעות)</div>
                <div class="stat-value"><?php echo $stats['sent'] ?? 0; ?></div>
            </div>
            <div class="stat-card danger">
                <div class="stat-label">נכשלו</div>
                <div class="stat-value"><?php echo $stats['failed'] ?? 0; ?></div>
            </div>
        </div>
        
        <?php if ($message): ?>
        <div class="alert <?php echo $messageType; ?>">
            <?php echo $messageType === 'success' ? '✅' : '❌'; ?>
            <?php echo $message; ?>
        </div>
        <?php endif; ?>
        
        <!-- Email Test Section -->
        <div class="test-section">
            <h2>📧 בדיקת שליחת אימייל</h2>
            <form method="POST">
                <input type="hidden" name="action" value="send_test_email">
                <div class="form-group">
                    <label>כתובת אימייל</label>
                    <input type="email" name="email" value="<?php echo $_SESSION['email'] ?? ''; ?>" required>
                </div>
                <div class="form-group">
                    <label>נושא ההודעה</label>
                    <input type="text" name="subject" value="הזמנה לקבוצת רכישה - בדיקת מערכת" required>
                </div>
                <div class="form-group">
                    <label>תוכן ההודעה</label>
                    <textarea name="body" rows="4" required>שלום רב,

זוהי הודעת בדיקה ממערכת ניהול הקניות.
הוזמנת להצטרף לקבוצת "משפחת כהן - פסח 2024".

לחץ על הכפתור למטה כדי להיכנס למערכת ולאשר את ההזמנה.</textarea>
                </div>
                <button type="submit" class="btn">
                    📨 שלח אימייל בדיקה
                </button>
            </form>
        </div>
        
        <!-- Browser Notification Test -->
        <div class="test-section">
            <h2>🔔 בדיקת התראות דפדפן</h2>
            <div class="alert info">
                <span>ℹ️</span>
                <span>סטטוס הרשאות: <strong id="permission-status">בודק...</strong></span>
            </div>
            
            <div class="test-grid">
                <div class="test-card">
                    <h3>התראת הזמנה</h3>
                    <p>סימולציה של התראת הזמנה לקבוצה</p>
                    <button class="btn quick-test-btn" onclick="testInvitationNotification()">
                        🎉 בדוק התראת הזמנה
                    </button>
                </div>
                
                <div class="test-card">
                    <h3>התראת קנייה</h3>
                    <p>סימולציה של התראת קנייה חדשה</p>
                    <button class="btn quick-test-btn" onclick="testPurchaseNotification()">
                        🛒 בדוק התראת קנייה
                    </button>
                </div>
                
                <div class="test-card">
                    <h3>התראת חישוב</h3>
                    <p>סימולציה של התראת סיכום חישובים</p>
                    <button class="btn quick-test-btn" onclick="testCalculationNotification()">
                        💰 בדוק התראת חישוב
                    </button>
                </div>
            </div>
            
            <div class="buttons-row">
                <button class="btn btn-success" onclick="requestPermission()">
                    ✅ בקש הרשאת התראות
                </button>
                <button class="btn btn-secondary" onclick="checkSubscription()">
                    🔍 בדוק מנוי
                </button>
            </div>
        </div>
        
        <!-- Simulation Section -->
        <div class="test-section">
            <h2>🎭 סימולציית תרחישים</h2>
            <form method="POST">
                <input type="hidden" name="action" value="send_invitation_notification">
                <div class="form-group">
                    <label>סוג התראה</label>
                    <select name="notification_type" onchange="updateSimulationForm(this.value)">
                        <option value="invitation">הזמנה לקבוצה</option>
                        <option value="purchase">קנייה חדשה</option>
                        <option value="calculation">עדכון חישובים</option>
                        <option value="reminder">תזכורת</option>
                    </select>
                </div>
                
                <div id="invitation-fields">
                    <div class="form-group">
                        <label>אימייל יעד</label>
                        <input type="email" name="target_email" value="<?php echo $_SESSION['email'] ?? ''; ?>">
                    </div>
                    <div class="form-group">
                        <label>שם קבוצה</label>
                        <input type="text" name="group_name" value="משפחת כהן - פסח 2024">
                    </div>
                    <div class="form-group">
                        <label>שם מזמין</label>
                        <input type="text" name="inviter_name" value="ישראל ישראלי">
                    </div>
                </div>
                
                <button type="submit" class="btn">
                    🚀 הפעל סימולציה
                </button>
            </form>
        </div>
        
        <!-- Recent Notifications -->
        <div class="test-section">
            <h2>📋 התראות אחרונות</h2>
            <?php if (empty($recentNotifications)): ?>
                <div class="alert info">
                    אין התראות אחרונות
                </div>
            <?php else: ?>
                <div class="notification-list">
                    <?php foreach ($recentNotifications as $notification): ?>
                        <?php $data = json_decode($notification['data'], true); ?>
                        <div class="notification-item <?php echo $notification['status']; ?>">
                            <div class="notification-content">
                                <div class="notification-type">
                                    <?php echo $notification['type']; ?>
                                </div>
                                <div class="notification-time">
                                    <?php echo date('d/m/Y H:i', strtotime($notification['created_at'])); ?>
                                </div>
                                <small><?php echo htmlspecialchars(substr($notification['data'], 0, 100)); ?>...</small>
                            </div>
                            <div>
                                <span class="badge"><?php echo $notification['status']; ?></span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            
            <div class="buttons-row">
                <form method="POST" style="display: inline;">
                    <input type="hidden" name="action" value="process_queue">
                    <button type="submit" class="btn btn-success">
                        ⚡ עבד תור התראות
                    </button>
                </form>
                <form method="POST" style="display: inline;">
                    <input type="hidden" name="action" value="clear_queue">
                    <button type="submit" class="btn btn-danger">
                        🗑️ נקה תור התראות
                    </button>
                </form>
            </div>
            
            <?php if (isset($_SESSION['process_output'])): ?>
            <div class="test-section" style="margin-top: 20px;">
                <h3>פלט עיבוד התראות:</h3>
                <pre style="background: #f8f9fa; padding: 15px; border-radius: 8px; overflow-x: auto;">
                    <?php 
                    echo htmlspecialchars($_SESSION['process_output']); 
                    unset($_SESSION['process_output']);
                    ?>
                </pre>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- System Info -->
        <div class="test-section">
            <h2>ℹ️ מידע מערכת</h2>
            <div class="alert info">
                <div style="width: 100%;">
                    <p><strong>סביבת עבודה:</strong> <?php echo ENVIRONMENT; ?></p>
                    <p><strong>משתמש נוכחי:</strong> <?php echo $_SESSION['name'] ?? 'לא מחובר'; ?> (<?php echo $_SESSION['email'] ?? ''; ?>)</p>
                    <p><strong>Service Worker:</strong> <span id="sw-status">בודק...</span></p>
                    <p><strong>Push API:</strong> <span id="push-status">בודק...</span></p>
                    <p><strong>Notification API:</strong> <span id="notification-api-status">בודק...</span></p>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        // בדיקת סטטוס התראות
        window.addEventListener('load', () => {
            checkNotificationStatus();
            checkServiceWorker();
        });
        
        function checkNotificationStatus() {
            const permissionStatus = document.getElementById('permission-status');
            const notificationApiStatus = document.getElementById('notification-api-status');
            const pushStatus = document.getElementById('push-status');
            
            // בדוק Notification API
            if ('Notification' in window) {
                notificationApiStatus.textContent = '✅ נתמך';
                permissionStatus.textContent = Notification.permission;
                permissionStatus.style.color = Notification.permission === 'granted' ? 'green' : 
                                              Notification.permission === 'denied' ? 'red' : 'orange';
            } else {
                notificationApiStatus.textContent = '❌ לא נתמך';
                permissionStatus.textContent = 'לא זמין';
            }
            
            // בדוק Push API
            if ('PushManager' in window) {
                pushStatus.textContent = '✅ נתמך';
            } else {
                pushStatus.textContent = '❌ לא נתמך';
            }
        }
        
        function checkServiceWorker() {
            const swStatus = document.getElementById('sw-status');
            
            if ('serviceWorker' in navigator) {
                navigator.serviceWorker.ready.then(registration => {
                    swStatus.textContent = '✅ פעיל';
                    swStatus.style.color = 'green';
                }).catch(err => {
                    swStatus.textContent = '❌ לא פעיל';
                    swStatus.style.color = 'red';
                });
            } else {
                swStatus.textContent = '❌ לא נתמך';
                swStatus.style.color = 'red';
            }
        }
        
        async function requestPermission() {
            if (!('Notification' in window)) {
                alert('הדפדפן שלך לא תומך בהתראות');
                return;
            }
            
            const permission = await Notification.requestPermission();
            
            if (permission === 'granted') {
                alert('✅ הרשאה ניתנה בהצלחה!');
                checkNotificationStatus();
                
                // שלח התראת בדיקה
                new Notification('ברוך הבא! 👋', {
                    body: 'ההתראות הופעלו בהצלחה',
                    icon: '/login/images/icons/android/android-launchericon-192-192.png',
                    badge: '/login/images/icons/android/android-launchericon-96-96.png',
                    vibrate: [200, 100, 200],
                    tag: 'welcome'
                });
            } else if (permission === 'denied') {
                alert('❌ ההרשאה נדחתה. יש לאפשר התראות בהגדרות הדפדפן');
            }
        }
        
        async function checkSubscription() {
            if (!('serviceWorker' in navigator)) {
                alert('Service Worker לא נתמך');
                return;
            }
            
            try {
                const registration = await navigator.serviceWorker.ready;
                const subscription = await registration.pushManager.getSubscription();
                
                if (subscription) {
                    alert('✅ יש מנוי פעיל להתראות Push');
                    console.log('Subscription:', subscription);
                } else {
                    alert('❌ אין מנוי פעיל להתראות Push');
                }
            } catch (error) {
                alert('שגיאה בבדיקת מנוי: ' + error.message);
            }
        }
        
        function testInvitationNotification() {
            if (Notification.permission !== 'granted') {
                alert('יש לאשר הרשאת התראות תחילה');
                return;
            }
            
            const notification = new Notification('הזמנה לקבוצת רכישה 🎉', {
                body: 'ישראל ישראלי הזמין אותך לקבוצה "משפחת כהן - פסח 2024"',
                icon: '/login/images/icons/android/android-launchericon-192-192.png',
                badge: '/login/images/icons/android/android-launchericon-96-96.png',
                tag: 'invitation-test',
                requireInteraction: true,
                actions: [
                    { action: 'accept', title: 'קבל הזמנה', icon: '✅' },
                    { action: 'reject', title: 'דחה', icon: '❌' }
                ],
                data: {
                    type: 'invitation',
                    groupId: 123,
                    invitationId: 456
                }
            });
            
            notification.onclick = () => {
                window.open('/login/dashboard.php#invitations');
                notification.close();
            };
        }
        
        function testPurchaseNotification() {
            if (Notification.permission !== 'granted') {
                alert('יש לאשר הרשאת התראות תחילה');
                return;
            }
            
            const notification = new Notification('קנייה חדשה בקבוצה 🛒', {
                body: 'משה כהן הוסיף קנייה בסך ₪245.50',
                icon: '/login/images/icons/android/android-launchericon-192-192.png',
                badge: '/login/images/icons/android/android-launchericon-96-96.png',
                tag: 'purchase-test',
                vibrate: [200, 100, 200],
                data: {
                    type: 'purchase',
                    groupId: 123,
                    purchaseId: 789
                }
            });
            
            notification.onclick = () => {
                window.open('/login/group.php?id=123#purchases');
                notification.close();
            };
        }
        
        function testCalculationNotification() {
            if (Notification.permission !== 'granted') {
                alert('יש לאשר הרשאת התראות תחילה');
                return;
            }
            
            const notification = new Notification('עדכון חישובים 💰', {
                body: 'מגיע לך החזר של ₪127.30 בקבוצה "פסח 2024"',
                icon: '/login/images/icons/android/android-launchericon-192-192.png',
                badge: '/login/images/icons/android/android-launchericon-96-96.png',
                tag: 'calculation-test',
                requireInteraction: false,
                data: {
                    type: 'calculation',
                    groupId: 123,
                    amount: 127.30
                }
            });
            
            notification.onclick = () => {
                window.open('/login/group.php?id=123#calculations');
                notification.close();
            };
        }
        
        function updateSimulationForm(type) {
            // כאן אפשר להוסיף לוגיקה להחלפת שדות בהתאם לסוג ההתראה
            console.log('Selected notification type:', type);
        }
    </script>
</body>
</html>