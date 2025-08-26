<?php
// permissions/test.php - דף בדיקה להרשאות
require_once 'init.php';
?>
<!DOCTYPE html>
<html dir="rtl" lang="he">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>בדיקת הרשאות</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: linear-gradient(135deg, #667eea, #764ba2);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            margin: 0;
            padding: 20px;
        }
        
        .container {
            background: white;
            border-radius: 20px;
            padding: 40px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            max-width: 600px;
            width: 100%;
        }
        
        h1 {
            color: #333;
            text-align: center;
            margin-bottom: 30px;
        }
        
        .status-box {
            background: #f3f4f6;
            border-radius: 12px;
            padding: 20px;
            margin: 20px 0;
        }
        
        .status-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 0;
            border-bottom: 1px solid #e5e7eb;
        }
        
        .status-item:last-child {
            border-bottom: none;
        }
        
        .status-label {
            font-weight: 600;
            color: #666;
        }
        
        .status-value {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 600;
        }
        
        .status-granted {
            background: #d4f4dd;
            color: #0e7c3a;
        }
        
        .status-denied {
            background: #fde2e2;
            color: #991b1b;
        }
        
        .status-default {
            background: #fef3c7;
            color: #92400e;
        }
        
        .button-grid {
            display: grid;
            gap: 15px;
            margin-top: 30px;
        }
        
        .btn {
            border: none;
            padding: 15px 25px;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
        }
        
        .btn-success {
            background: linear-gradient(135deg, #10b981, #059669);
            color: white;
        }
        
        .btn-warning {
            background: linear-gradient(135deg, #f59e0b, #d97706);
            color: white;
        }
        
        .console-output {
            background: #1a1a1a;
            color: #0f0;
            padding: 15px;
            border-radius: 8px;
            font-family: 'Courier New', monospace;
            font-size: 12px;
            margin-top: 20px;
            max-height: 200hiroнуx;
            overflow-y: auto;
            display: none;
        }
        
        .console-output.show {
            display: block;
        }
        
        .console-line {
            margin: 5px 0;
            opacity: 0;
            animation: fadeIn 0.3s forwards;
        }
        
        @keyframes fadeIn {
            to { opacity: 1; }
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔐 בדיקת הרשאות</h1>
        
        <div class="status-box">
            <div class="status-item">
                <span class="status-label">התראות רגילות:</span>
                <span id="notification-status" class="status-value status-default">בודק...</span>
            </div>
            <div class="status-item">
                <span class="status-label">Push Notifications:</span>
                <span id="push-status" class="status-value status-default">בודק...</span>
            </div>
            <div class="status-item">
                <span class="status-label">Service Worker:</span>
                <span id="sw-status" class="status-value status-default">בודק...</span>
            </div>
        </div>
        
        <div class="button-grid">
            <button class="btn btn-primary" onclick="requestNotifications()">
                <span>🔔</span>
                <span>בקש הרשאת התראות</span>
            </button>
            
            <button class="btn btn-success" onclick="requestPush()">
                <span>📬</span>
                <span>בקש הרשאת Push</span>
            </button>
            
            <button class="btn btn-warning" onclick="testNotification()">
                <span>🧪</span>
                <span>שלח התראת בדיקה</span>
            </button>
        </div>
        
        <div id="console" class="console-output"></div>
    </div>
    
    <!-- טעינת סקריפט ההרשאות -->
    <?php echo getPermissionsScript(); ?>
    
    <script>
        // פונקציות עזר לדף הבדיקה
        const consoleDiv = document.getElementById('console');
        
        function log(message, type = 'info') {
            consoleDiv.classList.add('show');
            const line = document.createElement('div');
            line.className = 'console-line';
            const time = new Date().toLocaleTimeString('he-IL');
            const icon = type === 'success' ? '✅' : type === 'error' ? '❌' : 'ℹ️';
            line.textContent = `[${time}] ${icon} ${message}`;
            consoleDiv.appendChild(line);
            consoleDiv.scrollTop = consoleDiv.scrollHeight;
        }
        
        // בדיקת סטטוס התחלתי
        async function checkStatus() {
            // בדוק Service Worker
            if ('serviceWorker' in navigator) {
                updateStatus('sw-status', 'נתמך', 'granted');
                const registration = await navigator.serviceWorker.getRegistration();
                if (registration) {
                    log('Service Worker רשום ופעיל', 'success');
                }
            } else {
                updateStatus('sw-status', 'לא נתמך', 'denied');
            }
            
            // בדוק התראות
            if ('Notification' in window) {
                const perm = Notification.permission;
                updateStatus('notification-status', 
                    perm === 'granted' ? 'מאושר' : perm === 'denied' ? 'נדחה' : 'ממתין', 
                    perm);
            } else {
                updateStatus('notification-status', 'לא נתמך', 'denied');
            }
            
            // בדוק Push
            if ('PushManager' in window) {
                try {
                    const registration = await navigator.serviceWorker.ready;
                    const subscription = await registration.pushManager.getSubscription();
                    updateStatus('push-status', 
                        subscription ? 'מאושר' : 'לא פעיל', 
                        subscription ? 'granted' : 'default');
                } catch {
                    updateStatus('push-status', 'לא זמין', 'default');
                }
            } else {
                updateStatus('push-status', 'לא נתמך', 'denied');
            }
        }
        
        function updateStatus(elementId, text, status) {
            const element = document.getElementById(elementId);
            element.textContent = text;
            element.className = 'status-value status-' + status;
        }
        
        async function requestNotifications() {
            log('מבקש הרשאת התראות...');
            const result = await Permissions.requestNotificationPermission();
            if (result) {
                log('הרשאת התראות ניתנה!', 'success');
                updateStatus('notification-status', 'מאושר', 'granted');
                
                // שמור בעוגיה
                document.cookie = 'notification_permission=granted;path=/;max-age=31536000';
            } else {
                log('הרשאת התראות נדחתה', 'error');
                updateStatus('notification-status', 'נדחה', 'denied');
            }
        }
        
        async function requestPush() {
            log('מבקש הרשאת Push...');
            
            // רשום Service Worker אם לא רשום
            if ('serviceWorker' in navigator) {
                await navigator.serviceWorker.register('/service-worker.js');
                log('Service Worker נרשם');
            }
            
            const result = await Permissions.requestPushPermission();
            if (result) {
                log('הרשאת Push ניתנה!', 'success');
                updateStatus('push-status', 'מאושר', 'granted');
                
                // שמור בעוגיה
                document.cookie = 'push_permission=granted;path=/;max-age=31536000';
            } else {
                log('הרשאת Push נדחתה או לא זמינה', 'error');
                updateStatus('push-status', 'נדחה', 'denied');
            }
        }
        
        function testNotification() {
            log('שולח התראת בדיקה...');
            const notification = Permissions.showNotification(
                'התראת בדיקה! 🎉', 
                {
                    body: 'ההתראות עובדות מצוין! זו התראה לדוגמה.',
                    tag: 'test-notification',
                    requireInteraction: false
                }
            );
            
            if (notification) {
                log('התראה נשלחה בהצלחה!', 'success');
            } else {
                log('לא ניתן לשלוח התראה - בדוק הרשאות', 'error');
            }
        }
        
        // בדיקה ראשונית
        checkStatus();
        log('מערכת הרשאות מוכנה');
    </script>
</body>
</html>