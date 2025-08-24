<?php
// setup-push-notifications.php - הגדרת Push Notifications
session_start();
require_once 'config.php';

// בדיקת הרשאות
if (!isset($_GET['token']) || $_GET['token'] !== 'push123') {
    die('Access denied. Use ?token=push123');
}

$pdo = getDBConnection();

// יצירת מפתחות VAPID אם לא קיימים
$vapidKeysFile = __DIR__ . '/.vapid-keys.json';
$vapidKeys = null;

if (file_exists($vapidKeysFile)) {
    $vapidKeys = json_decode(file_get_contents($vapidKeysFile), true);
} else {
    // צור מפתחות חדשים
    $vapidKeys = [
        'publicKey' => 'BIebVHmU3XyXJKx9J3eLrIk5H9O7TnWkL2XYF1LwNkY3UKZQxW2fEQKSpDQuzSL5b8SLdar4xajLMuvFhNzFJUI',
        'privateKey' => 'EPiPYSDhsxj8J9WBAK7WAwxlXaF0_b8a5OoO-FO0XW8'
    ];
    // שמור לקובץ
    file_put_contents($vapidKeysFile, json_encode($vapidKeys, JSON_PRETTY_PRINT));
}

// יצירת טבלאות Push אם לא קיימות
if (isset($_POST['create_tables'])) {
    try {
        $sql = "
            CREATE TABLE IF NOT EXISTS push_subscriptions (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                endpoint TEXT NOT NULL,
                p256dh TEXT,
                auth TEXT,
                user_agent VARCHAR(255),
                device_type VARCHAR(50),
                is_active BOOLEAN DEFAULT TRUE,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_user (user_id),
                INDEX idx_active (is_active),
                UNIQUE KEY unique_endpoint (user_id, endpoint(255)),
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $pdo->exec($sql);
        $message = "✅ טבלת push_subscriptions נוצרה בהצלחה!";
    } catch (Exception $e) {
        $error = "❌ שגיאה: " . $e->getMessage();
    }
}

// בדיקת סטטוס
$tableExists = false;
try {
    $stmt = $pdo->query("SHOW TABLES LIKE 'push_subscriptions'");
    $tableExists = $stmt->fetch() !== false;
} catch (Exception $e) {}

$subscriptionCount = 0;
if ($tableExists) {
    try {
        $stmt = $pdo->query("SELECT COUNT(*) FROM push_subscriptions WHERE is_active = 1");
        $subscriptionCount = $stmt->fetchColumn();
    } catch (Exception $e) {}
}
?>
<!DOCTYPE html>
<html dir="rtl" lang="he">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🔔 הגדרת Push Notifications</title>
    <link rel="manifest" href="manifest.json">
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
            max-width: 900px;
            margin: 0 auto;
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            overflow: hidden;
        }
        
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 30px;
            color: white;
            text-align: center;
        }
        
        .header h1 {
            font-size: 32px;
            margin-bottom: 10px;
        }
        
        .content {
            padding: 30px;
        }
        
        .status-card {
            background: #f8f9fa;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 25px;
            border: 2px solid #e9ecef;
        }
        
        .status-card.success {
            background: #d4edda;
            border-color: #c3e6cb;
        }
        
        .status-card.warning {
            background: #fff3cd;
            border-color: #ffeeba;
        }
        
        .status-card.error {
            background: #f8d7da;
            border-color: #f5c6cb;
        }
        
        .status-card h3 {
            color: #333;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .icon {
            font-size: 24px;
        }
        
        .btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 12px 30px;
            border-radius: 10px;
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
            box-shadow: 0 10px 25px rgba(102, 126, 234, 0.3);
        }
        
        .btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        
        .btn-success {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
        }
        
        .btn-warning {
            background: linear-gradient(135deg, #ffc107 0%, #ff9800 100%);
        }
        
        .btn-danger {
            background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
        }
        
        code {
            background: #f4f4f4;
            padding: 3px 8px;
            border-radius: 4px;
            font-family: 'Courier New', monospace;
            color: #e83e8c;
        }
        
        pre {
            background: #263238;
            color: #aed581;
            padding: 20px;
            border-radius: 10px;
            overflow-x: auto;
            margin: 15px 0;
        }
        
        .steps {
            counter-reset: step;
            margin: 20px 0;
        }
        
        .step {
            position: relative;
            padding: 20px 20px 20px 60px;
            margin-bottom: 20px;
            background: #f8f9fa;
            border-radius: 10px;
            counter-increment: step;
        }
        
        .step::before {
            content: counter(step);
            position: absolute;
            left: 20px;
            top: 50%;
            transform: translateY(-50%);
            width: 30px;
            height: 30px;
            background: #667eea;
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
        }
        
        .step.completed::before {
            background: #28a745;
            content: "✓";
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin: 20px 0;
        }
        
        .info-box {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            text-align: center;
        }
        
        .info-box .value {
            font-size: 28px;
            font-weight: bold;
            color: #667eea;
            margin: 10px 0;
        }
        
        .info-box .label {
            color: #666;
            font-size: 14px;
        }
        
        .test-notification {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border-radius: 15px;
            padding: 25px;
            margin: 20px 0;
            text-align: center;
        }
        
        #notification-status {
            margin: 20px 0;
            padding: 15px;
            border-radius: 10px;
            display: none;
        }
        
        #notification-status.show {
            display: block;
        }
        
        .buttons-row {
            display: flex;
            gap: 15px;
            margin: 20px 0;
            flex-wrap: wrap;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🔔 הגדרת Push Notifications</h1>
            <p>קבל התראות בזמן אמת ישירות לדפדפן</p>
        </div>
        
        <div class="content">
            <?php if (isset($message)): ?>
                <div class="status-card success">
                    <?php echo $message; ?>
                </div>
            <?php endif; ?>
            
            <?php if (isset($error)): ?>
                <div class="status-card error">
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>
            
            <!-- סטטוס מערכת -->
            <div class="status-card">
                <h3><span class="icon">📊</span> סטטוס מערכת</h3>
                
                <div class="info-grid">
                    <div class="info-box">
                        <div class="label">Service Worker</div>
                        <div class="value" id="sw-status">בודק...</div>
                    </div>
                    
                    <div class="info-box">
                        <div class="label">הרשאות</div>
                        <div class="value" id="permission-status">בודק...</div>
                    </div>
                    
                    <div class="info-box">
                        <div class="label">מנויים פעילים</div>
                        <div class="value"><?php echo $subscriptionCount; ?></div>
                    </div>
                    
                    <div class="info-box">
                        <div class="label">טבלת DB</div>
                        <div class="value"><?php echo $tableExists ? '✅' : '❌'; ?></div>
                    </div>
                </div>
            </div>
            
            <!-- שלבי התקנה -->
            <div class="status-card">
                <h3><span class="icon">🚀</span> שלבי ההתקנה</h3>
                
                <div class="steps">
                    <div class="step <?php echo $tableExists ? 'completed' : ''; ?>">
                        <h4>יצירת טבלת מסד נתונים</h4>
                        <?php if (!$tableExists): ?>
                            <p>צור את הטבלה לשמירת מנויי Push</p>
                            <form method="POST" style="margin-top: 10px;">
                                <button type="submit" name="create_tables" class="btn">
                                    🔨 צור טבלה
                                </button>
                            </form>
                        <?php else: ?>
                            <p>✅ הטבלה קיימת ומוכנה</p>
                        <?php endif; ?>
                    </div>
                    
                    <div class="step" id="sw-step">
                        <h4>רישום Service Worker</h4>
                        <p>ה-Service Worker אחראי על קבלת והצגת ההתראות</p>
                        <button class="btn" onclick="registerServiceWorker()">
                            📱 רשום Service Worker
                        </button>
                    </div>
                    
                    <div class="step" id="permission-step">
                        <h4>בקשת הרשאה להתראות</h4>
                        <p>אשר לאתר לשלוח לך התראות</p>
                        <button class="btn btn-success" onclick="requestPermission()">
                            ✅ אשר התראות
                        </button>
                    </div>
                    
                    <div class="step" id="subscribe-step">
                        <h4>הרשמה להתראות</h4>
                        <p>שמור את המנוי שלך במערכת</p>
                        <button class="btn btn-warning" onclick="subscribeToPush()">
                            🔔 הרשם להתראות
                        </button>
                    </div>
                </div>
            </div>
            
            <!-- VAPID Keys -->
            <div class="status-card">
                <h3><span class="icon">🔑</span> מפתחות VAPID</h3>
                <p>המפתחות הציבוריים לשליחת Push:</p>
                <pre>publicKey: <?php echo $vapidKeys['publicKey']; ?></pre>
                <p>הוסף לקובץ <code>.env</code>:</p>
                <pre>VAPID_PUBLIC_KEY=<?php echo $vapidKeys['publicKey']; ?>
VAPID_PRIVATE_KEY=<?php echo $vapidKeys['privateKey']; ?></pre>
            </div>
            
            <!-- בדיקת התראות -->
            <div class="test-notification">
                <h3>🧪 בדיקת התראות</h3>
                <p>לאחר השלמת ההתקנה, בדוק שההתראות עובדות:</p>
                
                <div class="buttons-row" style="justify-content: center;">
                    <button class="btn btn-success" onclick="testNotification()">
                        📢 שלח התראת בדיקה
                    </button>
                    <button class="btn" onclick="checkSubscription()">
                        🔍 בדוק מנוי
                    </button>
                </div>
                
                <div id="notification-status"></div>
            </div>
            
            <!-- קישורים -->
            <div class="buttons-row">
                <a href="test-notifications.php?token=test123" class="btn">
                    ↩️ חזרה לבדיקת התראות
                </a>
                <a href="dashboard.php" class="btn">
                    🏠 לדשבורד
                </a>
            </div>
        </div>
    </div>
    
    <script>
        // מפתח VAPID ציבורי
        const VAPID_PUBLIC_KEY = '<?php echo $vapidKeys['publicKey']; ?>';
        
        // בדיקת סטטוס בטעינה
        window.addEventListener('load', () => {
            checkStatus();
        });
        
        function checkStatus() {
            // בדוק Service Worker
            const swStatus = document.getElementById('sw-status');
            if ('serviceWorker' in navigator) {
                navigator.serviceWorker.getRegistration().then(registration => {
                    if (registration) {
                        swStatus.textContent = '✅';
                        swStatus.style.color = '#28a745';
                        document.getElementById('sw-step').classList.add('completed');
                    } else {
                        swStatus.textContent = '❌';
                        swStatus.style.color = '#dc3545';
                    }
                });
            } else {
                swStatus.textContent = 'לא נתמך';
                swStatus.style.color = '#dc3545';
            }
            
            // בדוק הרשאות
            const permissionStatus = document.getElementById('permission-status');
            if ('Notification' in window) {
                const permission = Notification.permission;
                if (permission === 'granted') {
                    permissionStatus.textContent = '✅';
                    permissionStatus.style.color = '#28a745';
                    document.getElementById('permission-step').classList.add('completed');
                } else if (permission === 'denied') {
                    permissionStatus.textContent = '❌';
                    permissionStatus.style.color = '#dc3545';
                } else {
                    permissionStatus.textContent = '⏳';
                    permissionStatus.style.color = '#ffc107';
                }
            } else {
                permissionStatus.textContent = 'לא נתמך';
            }
        }
        
        async function registerServiceWorker() {
            try {
                const registration = await navigator.serviceWorker.register('service-worker.js', {
                    scope: '/'
                });
                console.log('Service Worker registered:', registration);
                showMessage('✅ Service Worker נרשם בהצלחה!', 'success');
                checkStatus();
            } catch (error) {
                console.error('Registration failed:', error);
                showMessage('❌ שגיאה ברישום Service Worker: ' + error.message, 'error');
            }
        }
        
        async function requestPermission() {
            if (!('Notification' in window)) {
                showMessage('❌ הדפדפן לא תומך בהתראות', 'error');
                return;
            }
            
            const permission = await Notification.requestPermission();
            
            if (permission === 'granted') {
                showMessage('✅ הרשאה ניתנה!', 'success');
                checkStatus();
                
                // שלח התראת ברוכים הבאים
                new Notification('ברוך הבא! 👋', {
                    body: 'התראות Push הופעלו בהצלחה',
                    icon: '/family/images/icons/android/android-launchericon-192-192.png',
                    badge: '/family/images/icons/android/android-launchericon-96-96.png'
                });
            } else if (permission === 'denied') {
                showMessage('❌ ההרשאה נדחתה. יש לאפשר בהגדרות הדפדפן', 'error');
            }
        }
        
        async function subscribeToPush() {
            try {
                const registration = await navigator.serviceWorker.ready;
                
                // המר את המפתח
                const vapidKey = urlBase64ToUint8Array(VAPID_PUBLIC_KEY);
                
                // צור מנוי
                const subscription = await registration.pushManager.subscribe({
                    userVisibleOnly: true,
                    applicationServerKey: vapidKey
                });
                
                // שלח לשרת
                const response = await fetch('api/save-push-subscription.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        subscription: subscription.toJSON()
                    })
                });
                
                if (response.ok) {
                    showMessage('✅ נרשמת להתראות Push!', 'success');
                    document.getElementById('subscribe-step').classList.add('completed');
                } else {
                    throw new Error('Failed to save subscription');
                }
                
            } catch (error) {
                console.error('Subscription error:', error);
                showMessage('❌ שגיאה בהרשמה: ' + error.message, 'error');
            }
        }
        
        async function testNotification() {
            if (Notification.permission !== 'granted') {
                showMessage('❌ יש לאשר הרשאות תחילה', 'error');
                return;
            }
            
            // התראה מקומית
            const notification = new Notification('התראת בדיקה 🎉', {
                body: 'אם אתה רואה את זה, Push Notifications עובד!',
                icon: '/family/images/icons/android/android-launchericon-192-192.png',
                badge: '/family/images/icons/android/android-launchericon-96-96.png',
                vibrate: [200, 100, 200],
                tag: 'test-notification',
                requireInteraction: true,
                actions: [
                    { action: 'like', title: '👍 אהבתי' },
                    { action: 'close', title: '❌ סגור' }
                ]
            });
            
            notification.onclick = () => {
                window.open('/family/dashboard.php');
                notification.close();
            };
            
            showMessage('📢 התראה נשלחה! בדוק את שורת ההתראות', 'success');
        }
        
        async function checkSubscription() {
            try {
                const registration = await navigator.serviceWorker.ready;
                const subscription = await registration.pushManager.getSubscription();
                
                if (subscription) {
                    showMessage('✅ יש מנוי פעיל!', 'success');
                    console.log('Subscription:', subscription);
                } else {
                    showMessage('❌ אין מנוי פעיל', 'warning');
                }
            } catch (error) {
                showMessage('❌ שגיאה בבדיקה: ' + error.message, 'error');
            }
        }
        
        function urlBase64ToUint8Array(base64String) {
            const padding = '='.repeat((4 - base64String.length % 4) % 4);
            const base64 = (base64String + padding)
                .replace(/\-/g, '+')
                .replace(/_/g, '/');
            
            const rawData = window.atob(base64);
            const outputArray = new Uint8Array(rawData.length);
            
            for (let i = 0; i < rawData.length; ++i) {
                outputArray[i] = rawData.charCodeAt(i);
            }
            return outputArray;
        }
        
        function showMessage(message, type) {
            const status = document.getElementById('notification-status');
            status.className = 'status-card show ' + type;
            status.textContent = message;
            
            setTimeout(() => {
                status.classList.remove('show');
            }, 5000);
        }
    </script>
</body>
</html>