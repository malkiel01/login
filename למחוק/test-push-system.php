<?php
// test-push-system.php - מערכת בדיקת התראות Push
session_start();
require_once 'config.php';

// בדיקת הרשאות - רק למנהלים או לבדיקה
if (!isset($_SESSION['user_id'])) {
    header('Location: auth/login.php');
    exit;
}

$pdo = getDBConnection();
$currentUserId = $_SESSION['user_id'];

// טיפול בשליחת התראה
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    if ($_POST['action'] === 'send_notification') {
        $email = $_POST['email'] ?? '';
        $notificationType = $_POST['notification_type'] ?? 'test';
        $customTitle = $_POST['custom_title'] ?? '';
        $customBody = $_POST['custom_body'] ?? '';
        
        // מצא את המשתמש לפי אימייל
        $stmt = $pdo->prepare("SELECT id, name FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $targetUser = $stmt->fetch();
        
        if (!$targetUser) {
            echo json_encode([
                'success' => false,
                'message' => 'משתמש לא נמצא',
                'log' => "❌ User not found: $email"
            ]);
            exit;
        }
        
        // בנה את ההתראה לפי הסוג
        $notificationData = [
            'user_id' => $targetUser['id'],
            'target_email' => $email,
            'target_name' => $targetUser['name'],
            'sent_by' => $_SESSION['name'],
            'sent_at' => date('Y-m-d H:i:s')
        ];
        
        switch ($notificationType) {
            case 'invitation':
                $notificationData['title'] = 'הזמנה לקבוצה חדשה 👥';
                $notificationData['body'] = 'הוזמנת להצטרף לקבוצת רכישה "קבוצת בדיקה"';
                $notificationData['url'] = '/login/dashboard.php#invitations';
                break;
                
            case 'purchase':
                $notificationData['title'] = 'קנייה חדשה בקבוצה 🛒';
                $notificationData['body'] = $_SESSION['name'] . ' הוסיף קנייה בסך ₪150.00';
                $notificationData['url'] = '/login/dashboard.php#purchases';
                break;
                
            case 'payment':
                $notificationData['title'] = 'תזכורת תשלום 💰';
                $notificationData['body'] = 'יש לך חוב של ₪75.50 בקבוצה';
                $notificationData['url'] = '/login/dashboard.php#payments';
                break;
                
            case 'custom':
                $notificationData['title'] = $customTitle ?: 'התראה מותאמת אישית';
                $notificationData['body'] = $customBody ?: 'זו התראת בדיקה מותאמת אישית';
                $notificationData['url'] = '/login/dashboard.php';
                break;
                
            default: // test
                $notificationData['title'] = 'התראת בדיקה 🧪';
                $notificationData['body'] = 'זו התראת בדיקה שנשלחה ב-' . date('H:i:s');
                $notificationData['url'] = '/login/dashboard.php';
        }
        
        $notificationData['type'] = $notificationType;
        $notificationData['icon'] = '/login/images/icons/android/android-launchericon-192-192.png';
        $notificationData['badge'] = '/login/images/icons/android/android-launchericon-96-96.png';
        
        try {
            // הכנס לתור ההתראות
            $stmt = $pdo->prepare("
                INSERT INTO notification_queue (type, data, status, created_at) 
                VALUES (?, ?, 'pending', NOW())
            ");
            $result = $stmt->execute([$notificationType, json_encode($notificationData)]);
            
            if ($result) {
                $notificationId = $pdo->lastInsertId();
                
                // נסה לשלוח מיידית דרך push_subscriptions
                $sendResult = sendPushNotification($pdo, $targetUser['id'], $notificationData);
                
                echo json_encode([
                    'success' => true,
                    'message' => 'התראה נוצרה בהצלחה',
                    'notification_id' => $notificationId,
                    'push_sent' => $sendResult['sent'],
                    'log' => [
                        "✅ Notification created (ID: $notificationId)",
                        "📧 Target: {$targetUser['name']} ($email)",
                        "📱 Push status: " . ($sendResult['sent'] ? "Sent successfully" : "Failed - " . $sendResult['error']),
                        "⏰ Time: " . date('H:i:s')
                    ]
                ]);
            } else {
                echo json_encode([
                    'success' => false,
                    'message' => 'שגיאה ביצירת התראה',
                    'log' => "❌ Failed to create notification"
                ]);
            }
        } catch (Exception $e) {
            echo json_encode([
                'success' => false,
                'message' => 'שגיאה: ' . $e->getMessage(),
                'log' => "❌ Error: " . $e->getMessage()
            ]);
        }
        exit;
    }
    
    if ($_POST['action'] === 'check_status') {
        // בדוק סטטוס מערכת
        $status = [];
        
        // בדוק טבלאות
        $tables = ['notification_queue', 'push_subscriptions', 'users', 'group_invitations'];
        foreach ($tables as $table) {
            $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
            $status['tables'][$table] = $stmt->fetch() ? '✅' : '❌';
        }
        
        // בדוק התראות
        $stmt = $pdo->query("SELECT COUNT(*) FROM notification_queue WHERE status = 'pending'");
        $status['pending_notifications'] = $stmt->fetchColumn();
        
        // בדוק subscriptions
        $stmt = $pdo->query("SELECT COUNT(*) FROM push_subscriptions WHERE is_active = 1");
        $status['active_subscriptions'] = $stmt->fetchColumn();
        
        echo json_encode(['success' => true, 'status' => $status]);
        exit;
    }
}

// פונקציה לשליחת Push Notification
function sendPushNotification($pdo, $userId, $data) {
    try {
        // בדוק אם יש subscription פעיל
        $stmt = $pdo->prepare("
            SELECT * FROM push_subscriptions 
            WHERE user_id = ? AND is_active = 1 
            LIMIT 1
        ");
        $stmt->execute([$userId]);
        $subscription = $stmt->fetch();
        
        if (!$subscription) {
            return ['sent' => false, 'error' => 'No active subscription'];
        }
        
        // כאן בעתיד תהיה שליחה אמיתית דרך Web Push API
        // לעכשיו רק מסמן שנשלח
        return ['sent' => true, 'subscription_id' => $subscription['id']];
        
    } catch (Exception $e) {
        return ['sent' => false, 'error' => $e->getMessage()];
    }
}

// קבל רשימת משתמשים לטסטים
$stmt = $pdo->query("SELECT id, name, email FROM users ORDER BY name");
$users = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html dir="rtl" lang="he">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🧪 מערכת בדיקת התראות Push</title>
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
            border-radius: 20px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            text-align: center;
        }
        
        .header h1 {
            color: #333;
            margin-bottom: 10px;
            font-size: 32px;
        }
        
        .main-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            margin-bottom: 30px;
        }
        
        @media (max-width: 768px) {
            .main-grid {
                grid-template-columns: 1fr;
            }
        }
        
        .card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }
        
        .card h2 {
            color: #667eea;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
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
        
        .form-control {
            width: 100%;
            padding: 12px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 14px;
            transition: border-color 0.3s;
        }
        
        .form-control:focus {
            outline: none;
            border-color: #667eea;
        }
        
        select.form-control {
            cursor: pointer;
        }
        
        textarea.form-control {
            resize: vertical;
            min-height: 80px;
        }
        
        .custom-fields {
            display: none;
            margin-top: 15px;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
        }
        
        .btn {
            padding: 12px 30px;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(102, 126, 234, 0.3);
        }
        
        .btn-secondary {
            background: #6c757d;
            color: white;
            margin-right: 10px;
        }
        
        .btn-secondary:hover {
            background: #5a6268;
        }
        
        .status-indicator {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 600;
        }
        
        .status-active {
            background: #d4edda;
            color: #155724;
        }
        
        .status-inactive {
            background: #f8d7da;
            color: #721c24;
        }
        
        .log-container {
            background: #263238;
            color: #aed581;
            padding: 20px;
            border-radius: 10px;
            height: 400px;
            overflow-y: auto;
            font-family: 'Courier New', monospace;
            font-size: 13px;
            line-height: 1.6;
        }
        
        .log-entry {
            padding: 8px;
            margin-bottom: 5px;
            border-left: 3px solid transparent;
            animation: slideIn 0.3s;
        }
        
        @keyframes slideIn {
            from {
                transform: translateX(-20px);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }
        
        .log-success {
            border-left-color: #4caf50;
            background: rgba(76, 175, 80, 0.1);
        }
        
        .log-error {
            border-left-color: #f44336;
            background: rgba(244, 67, 54, 0.1);
        }
        
        .log-info {
            border-left-color: #2196f3;
            background: rgba(33, 150, 243, 0.1);
        }
        
        .log-warning {
            border-left-color: #ff9800;
            background: rgba(255, 152, 0, 0.1);
        }
        
        .status-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-top: 20px;
        }
        
        .status-item {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            text-align: center;
        }
        
        .status-item .label {
            font-size: 12px;
            color: #666;
            margin-bottom: 5px;
        }
        
        .status-item .value {
            font-size: 24px;
            font-weight: bold;
            color: #667eea;
        }
        
        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: none;
        }
        
        .alert.show {
            display: block;
            animation: fadeIn 0.3s;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .quick-users {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-top: 10px;
        }
        
        .quick-user {
            padding: 5px 12px;
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 20px;
            font-size: 13px;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .quick-user:hover {
            background: #667eea;
            color: white;
            border-color: #667eea;
        }
        
        .loading {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid rgba(255,255,255,.3);
            border-radius: 50%;
            border-top-color: white;
            animation: spin 1s ease-in-out infinite;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🧪 מערכת בדיקת התראות Push</h1>
            <p>שלח התראות בדיקה למשתמשים וצפה בלוג בזמן אמת</p>
        </div>
        
        <div class="main-grid">
            <!-- טופס שליחת התראה -->
            <div class="card">
                <h2>📤 שליחת התראה</h2>
                
                <div id="alertBox" class="alert"></div>
                
                <form id="notificationForm">
                    <div class="form-group">
                        <label for="email">כתובת אימייל המקבל:</label>
                        <input type="email" id="email" class="form-control" required 
                               placeholder="user@example.com">
                        
                        <div class="quick-users">
                            <?php foreach ($users as $user): ?>
                                <span class="quick-user" onclick="setEmail('<?php echo $user['email']; ?>')">
                                    <?php echo htmlspecialchars($user['name']); ?>
                                </span>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="notificationType">סוג התראה:</label>
                        <select id="notificationType" class="form-control" onchange="toggleCustomFields()">
                            <option value="test">🧪 בדיקה</option>
                            <option value="invitation">👥 הזמנה לקבוצה</option>
                            <option value="purchase">🛒 קנייה חדשה</option>
                            <option value="payment">💰 תזכורת תשלום</option>
                            <option value="custom">✏️ מותאם אישית</option>
                        </select>
                    </div>
                    
                    <div id="customFields" class="custom-fields">
                        <div class="form-group">
                            <label for="customTitle">כותרת:</label>
                            <input type="text" id="customTitle" class="form-control" 
                                   placeholder="הכנס כותרת להתראה">
                        </div>
                        <div class="form-group">
                            <label for="customBody">תוכן:</label>
                            <textarea id="customBody" class="form-control" 
                                      placeholder="הכנס תוכן להתראה"></textarea>
                        </div>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">
                        <span id="btnText">🚀 שלח התראה</span>
                        <span id="btnLoader" class="loading" style="display: none;"></span>
                    </button>
                    <button type="button" class="btn btn-secondary" onclick="checkSystemStatus()">
                        🔍 בדוק מערכת
                    </button>
                </form>
            </div>
            
            <!-- סטטוס מערכת -->
            <div class="card">
                <h2>📊 סטטוס מערכת</h2>
                
                <div class="status-grid" id="statusGrid">
                    <div class="status-item">
                        <div class="label">התראות ממתינות</div>
                        <div class="value" id="pendingCount">-</div>
                    </div>
                    <div class="status-item">
                        <div class="label">Subscriptions פעילים</div>
                        <div class="value" id="subscriptionCount">-</div>
                    </div>
                    <div class="status-item">
                        <div class="label">Service Worker</div>
                        <div class="value" id="swStatus">-</div>
                    </div>
                    <div class="status-item">
                        <div class="label">הרשאות</div>
                        <div class="value" id="permissionStatus">-</div>
                    </div>
                </div>
                
                <div style="margin-top: 20px;">
                    <h3 style="margin-bottom: 10px;">🔧 כלים מהירים</h3>
                    <button class="btn btn-secondary" onclick="testServiceWorker()">
                        🔔 בדוק Service Worker
                    </button>
                    <button class="btn btn-secondary" onclick="requestPermission()">
                        ✅ בקש הרשאות
                    </button>
                    <button class="btn btn-secondary" onclick="clearLog()">
                        🗑️ נקה לוג
                    </button>
                </div>
            </div>
        </div>
        
        <!-- לוג מערכת -->
        <div class="card">
            <h2>📝 לוג מערכת</h2>
            <div class="log-container" id="logContainer">
                <div class="log-entry log-info">🚀 System initialized at <?php echo date('H:i:s'); ?></div>
                <div class="log-entry log-info">👤 Current user: <?php echo htmlspecialchars($_SESSION['name']); ?></div>
            </div>
        </div>
    </div>
    
    <script>
        // משתנים גלובליים
        let logCounter = 0;
        
        // פונקציות עזר
        function setEmail(email) {
            document.getElementById('email').value = email;
            addLog(`Selected user: ${email}`, 'info');
        }
        
        function toggleCustomFields() {
            const type = document.getElementById('notificationType').value;
            const customFields = document.getElementById('customFields');
            customFields.style.display = type === 'custom' ? 'block' : 'none';
        }
        
        function addLog(message, type = 'info') {
            const logContainer = document.getElementById('logContainer');
            const entry = document.createElement('div');
            entry.className = `log-entry log-${type}`;
            
            const timestamp = new Date().toLocaleTimeString('he-IL');
            entry.textContent = `[${timestamp}] ${message}`;
            
            logContainer.appendChild(entry);
            logContainer.scrollTop = logContainer.scrollHeight;
            
            logCounter++;
            if (logCounter > 100) {
                logContainer.removeChild(logContainer.firstChild);
                logCounter--;
            }
        }
        
        function clearLog() {
            const logContainer = document.getElementById('logContainer');
            logContainer.innerHTML = '<div class="log-entry log-info">🗑️ Log cleared</div>';
            logCounter = 1;
        }
        
        function showAlert(message, type) {
            const alertBox = document.getElementById('alertBox');
            alertBox.className = `alert alert-${type} show`;
            alertBox.textContent = message;
            
            setTimeout(() => {
                alertBox.classList.remove('show');
            }, 5000);
        }
        
        // שליחת התראה
        document.getElementById('notificationForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            
            const btnText = document.getElementById('btnText');
            const btnLoader = document.getElementById('btnLoader');
            
            btnText.style.display = 'none';
            btnLoader.style.display = 'inline-block';
            
            const formData = new FormData();
            formData.append('action', 'send_notification');
            formData.append('email', document.getElementById('email').value);
            formData.append('notification_type', document.getElementById('notificationType').value);
            formData.append('custom_title', document.getElementById('customTitle').value);
            formData.append('custom_body', document.getElementById('customBody').value);
            
            addLog(`Sending notification to ${formData.get('email')}...`, 'info');
            
            try {
                const response = await fetch('', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                
                if (data.success) {
                    showAlert('התראה נשלחה בהצלחה!', 'success');
                    
                    if (data.log && Array.isArray(data.log)) {
                        data.log.forEach(logEntry => {
                            const type = logEntry.includes('✅') ? 'success' : 
                                        logEntry.includes('❌') ? 'error' : 'info';
                            addLog(logEntry, type);
                        });
                    }
                    
                    // בקש מה-Service Worker לבדוק התראות
                    if ('serviceWorker' in navigator) {
                        navigator.serviceWorker.ready.then(reg => {
                            reg.active.postMessage({ type: 'CHECK_NOW' });
                            addLog('Triggered Service Worker check', 'info');
                        });
                    }
                } else {
                    showAlert(data.message || 'שגיאה בשליחת התראה', 'error');
                    addLog(data.log || data.message, 'error');
                }
            } catch (error) {
                showAlert('שגיאת תקשורת', 'error');
                addLog(`Communication error: ${error.message}`, 'error');
            } finally {
                btnText.style.display = 'inline';
                btnLoader.style.display = 'none';
            }
        });
        
        // בדיקת סטטוס מערכת
        async function checkSystemStatus() {
            addLog('Checking system status...', 'info');
            
            const formData = new FormData();
            formData.append('action', 'check_status');
            
            try {
                const response = await fetch('', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                
                if (data.success && data.status) {
                    // עדכן תצוגה
                    document.getElementById('pendingCount').textContent = data.status.pending_notifications || '0';
                    document.getElementById('subscriptionCount').textContent = data.status.active_subscriptions || '0';
                    
                    // הוסף ללוג
                    Object.entries(data.status.tables || {}).forEach(([table, status]) => {
                        addLog(`Table ${table}: ${status}`, status === '✅' ? 'success' : 'error');
                    });
                }
            } catch (error) {
                addLog(`Status check failed: ${error.message}`, 'error');
            }
        }
        
        // בדיקת Service Worker
        async function testServiceWorker() {
            addLog('Testing Service Worker...', 'info');
            
            if ('serviceWorker' in navigator) {
                const registration = await navigator.serviceWorker.ready;
                
                if (registration.active) {
                    document.getElementById('swStatus').textContent = '✅';
                    addLog('Service Worker is active', 'success');
                    
                    // שלח התראת בדיקה
                    registration.showNotification('בדיקת Service Worker', {
                        body: 'אם אתה רואה את זה, ה-Service Worker עובד!',
                        icon: '/login/images/icons/android/android-launchericon-192-192.png',
                        vibrate: [200, 100, 200]
                    });
                } else {
                    document.getElementById('swStatus').textContent = '❌';
                    addLog('Service Worker not active', 'error');
                }
            } else {
                document.getElementById('swStatus').textContent = '❌';
                addLog('Service Worker not supported', 'error');
            }
        }
        
        // בקשת הרשאות
        async function requestPermission() {
            addLog('Requesting notification permission...', 'info');
            
            if ('Notification' in window) {
                const permission = await Notification.requestPermission();
                document.getElementById('permissionStatus').textContent = 
                    permission === 'granted' ? '✅' : '❌';
                
                addLog(`Permission: ${permission}`, 
                    permission === 'granted' ? 'success' : 'error');
            } else {
                addLog('Notifications not supported', 'error');
            }
        }
        
        // אתחול
        window.addEventListener('load', () => {
            // בדוק סטטוס ראשוני
            checkSystemStatus();
            
            // בדוק Service Worker
            if ('serviceWorker' in navigator) {
                navigator.serviceWorker.ready.then(() => {
                    document.getElementById('swStatus').textContent = '✅';
                });
            }
            
            // בדוק הרשאות
            if ('Notification' in window) {
                document.getElementById('permissionStatus').textContent = 
                    Notification.permission === 'granted' ? '✅' : '❌';
            }
            
            // רענן סטטוס כל 30 שניות
            setInterval(checkSystemStatus, 30000);
            
            addLog('System ready', 'success');
        });
    </script>
</body>
</html>