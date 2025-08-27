<!DOCTYPE html>
<html dir="rtl" lang="he">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Debug Panel - התראות</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, 'Segoe UI', sans-serif;
            background: #1a1a1a;
            color: #0f0;
            min-height: 100vh;
            padding: 20px;
        }
        
        .debug-container {
            max-width: 800px;
            margin: 0 auto;
            background: #000;
            border: 2px solid #0f0;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 0 20px rgba(0, 255, 0, 0.3);
        }
        
        h1 {
            color: #0f0;
            text-align: center;
            margin-bottom: 30px;
            text-shadow: 0 0 10px #0f0;
            font-size: 24px;
        }
        
        .status-panel {
            background: #111;
            border: 1px solid #0f0;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 5px;
        }
        
        .status-row {
            display: flex;
            justify-content: space-between;
            padding: 5px 0;
            border-bottom: 1px solid #333;
        }
        
        .status-row:last-child {
            border-bottom: none;
        }
        
        .status-label {
            color: #888;
        }
        
        .status-value {
            color: #0f0;
            font-weight: bold;
        }
        
        .status-value.denied {
            color: #f00;
        }
        
        .status-value.default {
            color: #ff0;
        }
        
        .notification-form {
            background: #111;
            border: 1px solid #0f0;
            padding: 20px;
            margin-bottom: 20px;
            border-radius: 5px;
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        label {
            display: block;
            color: #0f0;
            margin-bottom: 5px;
            font-size: 14px;
        }
        
        input, textarea, select {
            width: 100%;
            padding: 10px;
            background: #000;
            border: 1px solid #0f0;
            color: #0f0;
            border-radius: 3px;
            font-family: monospace;
        }
        
        input:focus, textarea:focus, select:focus {
            outline: none;
            box-shadow: 0 0 5px #0f0;
        }
        
        .button-group {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            margin-bottom: 20px;
        }
        
        button {
            padding: 12px 20px;
            background: #000;
            border: 2px solid #0f0;
            color: #0f0;
            cursor: pointer;
            border-radius: 5px;
            font-weight: bold;
            transition: all 0.3s;
        }
        
        button:hover {
            background: #0f0;
            color: #000;
            box-shadow: 0 0 10px #0f0;
        }
        
        button.danger {
            border-color: #f00;
            color: #f00;
        }
        
        button.danger:hover {
            background: #f00;
            color: #fff;
            box-shadow: 0 0 10px #f00;
        }
        
        .console-output {
            background: #000;
            border: 1px solid #0f0;
            padding: 15px;
            height: 200px;
            overflow-y: auto;
            font-family: monospace;
            font-size: 12px;
            border-radius: 5px;
        }
        
        .console-line {
            margin: 2px 0;
            padding: 2px;
        }
        
        .console-time {
            color: #888;
        }
        
        .console-success {
            color: #0f0;
        }
        
        .console-error {
            color: #f00;
        }
        
        .console-info {
            color: #0ff;
        }
        
        .notification-counter {
            background: #111;
            border: 1px solid #0f0;
            padding: 15px;
            margin-bottom: 20px;
            text-align: center;
            border-radius: 5px;
        }
        
        .counter-display {
            font-size: 48px;
            color: #0f0;
            text-shadow: 0 0 10px #0f0;
            font-family: monospace;
        }
        
        .preset-buttons {
            background: #111;
            border: 1px solid #0f0;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 5px;
        }
        
        .preset-title {
            color: #0f0;
            margin-bottom: 10px;
            font-size: 14px;
            text-transform: uppercase;
        }
    </style>
</head>
<body>
    <?php
    require_once '/permissions/init.php';
    ?>
    
    <div class="debug-container">
        <h1>🔧 DEBUG PANEL - NOTIFICATIONS 🔧</h1>
        
        <!-- סטטוס המערכת -->
        <div class="status-panel">
            <div class="status-row">
                <span class="status-label">Notification API:</span>
                <span class="status-value" id="api-status">בודק...</span>
            </div>
            <div class="status-row">
                <span class="status-label">Permission Status:</span>
                <span class="status-value" id="permission-status">בודק...</span>
            </div>
            <div class="status-row">
                <span class="status-label">Service Worker:</span>
                <span class="status-value" id="sw-status">בודק...</span>
            </div>
            <div class="status-row">
                <span class="status-label">Current Time:</span>
                <span class="status-value" id="current-time">--:--:--</span>
            </div>
        </div>
        
        <!-- מונה התראות -->
        <div class="notification-counter">
            <div class="preset-title">התראות שנשלחו</div>
            <div class="counter-display" id="notification-count">0</div>
        </div>
        
        <!-- כפתורים מהירים -->
        <div class="preset-buttons">
            <div class="preset-title">התראות מוכנות</div>
            <div class="button-group">
                <button onclick="sendTimeNotification()">📅 שלח עם השעה</button>
                <button onclick="sendTestNotification()">🧪 התראת בדיקה</button>
                <button onclick="sendWarningNotification()">⚠️ התראת אזהרה</button>
                <button onclick="sendSuccessNotification()">✅ התראת הצלחה</button>
                <button onclick="sendRandomNotification()">🎲 התראה אקראית</button>
                <button onclick="sendMultipleNotifications()">📦 5 התראות ברצף</button>
            </div>
        </div>
        
        <!-- טופס מותאם אישית -->
        <div class="notification-form">
            <div class="preset-title">התראה מותאמת אישית</div>
            
            <div class="form-group">
                <label for="custom-title">כותרת:</label>
                <input type="text" id="custom-title" placeholder="כותרת ההתראה" value="התראה מותאמת">
            </div>
            
            <div class="form-group">
                <label for="custom-body">תוכן:</label>
                <textarea id="custom-body" rows="3" placeholder="תוכן ההתראה">זו התראה מותאמת אישית מהדיבאגר</textarea>
            </div>
            
            <div class="form-group">
                <label for="custom-icon">אייקון:</label>
                <select id="custom-icon">
                    <option value="default">ברירת מחדל</option>
                    <option value="success">✅ הצלחה</option>
                    <option value="warning">⚠️ אזהרה</option>
                    <option value="error">❌ שגיאה</option>
                    <option value="info">ℹ️ מידע</option>
                </select>
            </div>
            
            <button onclick="sendCustomNotification()" style="width: 100%; background: #0f0; color: #000;">
                🚀 שלח התראה מותאמת
            </button>
        </div>
        
        <!-- כפתורי ניהול -->
        <div class="button-group">
            <button onclick="requestPermissions()">🔓 בקש הרשאות</button>
            <button onclick="clearConsole()" class="danger">🗑️ נקה קונסול</button>
        </div>
        
        <!-- קונסול -->
        <div class="console-output" id="console"></div>
    </div>
    
    <?php echo getPermissionsScript(); ?>
    
    <script>
        let notificationCount = 0;
        const consoleDiv = document.getElementById('console');
        
        // עדכון השעה
        setInterval(() => {
            document.getElementById('current-time').textContent = 
                new Date().toLocaleTimeString('he-IL');
        }, 1000);
        
        // בדיקת סטטוס
        function checkStatus() {
            // API Status
            if ('Notification' in window) {
                document.getElementById('api-status').textContent = 'זמין';
                document.getElementById('api-status').className = 'status-value';
            } else {
                document.getElementById('api-status').textContent = 'לא זמין';
                document.getElementById('api-status').className = 'status-value denied';
            }
            
            // Permission Status
            if ('Notification' in window) {
                const permission = Notification.permission;
                document.getElementById('permission-status').textContent = permission;
                document.getElementById('permission-status').className = 
                    'status-value ' + permission;
            }
            
            // Service Worker
            if ('serviceWorker' in navigator) {
                navigator.serviceWorker.getRegistration().then(reg => {
                    if (reg) {
                        document.getElementById('sw-status').textContent = 'פעיל';
                        document.getElementById('sw-status').className = 'status-value';
                    } else {
                        document.getElementById('sw-status').textContent = 'לא רשום';
                        document.getElementById('sw-status').className = 'status-value default';
                    }
                });
            }
        }
        
        // לוג לקונסול
        function log(message, type = 'info') {
            const time = new Date().toLocaleTimeString('he-IL');
            const line = document.createElement('div');
            line.className = 'console-line console-' + type;
            line.innerHTML = `<span class="console-time">[${time}]</span> ${message}`;
            consoleDiv.appendChild(line);
            consoleDiv.scrollTop = consoleDiv.scrollHeight;
        }
        
        // עדכון מונה
        function updateCounter() {
            notificationCount++;
            document.getElementById('notification-count').textContent = notificationCount;
        }
        
        // התראות מוכנות
        function sendTimeNotification() {
            const now = new Date();
            const timeStr = now.toLocaleTimeString('he-IL');
            const dateStr = now.toLocaleDateString('he-IL');
            
            // שלח דרך Permissions כדי שיישמר
            Permissions.showNotification(`השעה: ${timeStr}`, {
                body: `התאריך: ${dateStr}\nהתראה #${notificationCount + 1}`
            });
            
            log(`נשלחה התראה עם השעה: ${timeStr}`, 'success');
            updateCounter();
        }
        
        function sendTestNotification() {
            // שלח דרך Permissions כדי שיישמר
            Permissions.showNotification('התראת בדיקה 🧪', {
                body: 'זו התראת בדיקה מהדיבאגר\nהתראה מספר ' + (notificationCount + 1)
            });
            log('נשלחה התראת בדיקה', 'success');
            updateCounter();
        }
        
        function sendWarningNotification() {
            // שלח דרך Permissions כדי שיישמר
            Permissions.showNotification('⚠️ אזהרה!', {
                body: 'זו התראת אזהרה לדוגמה'
            });
            log('נשלחה התראת אזהרה', 'success');
            updateCounter();
        }
        
        function sendSuccessNotification() {
            // שלח דרך Permissions כדי שיישמר
            Permissions.showNotification('✅ הפעולה הושלמה!', {
                body: 'הכל עבד בהצלחה'
            });
            log('נשלחה התראת הצלחה', 'success');
            updateCounter();
        }
        
        function sendRandomNotification() {
            const messages = [
                { title: '🎯 יעד הושג!', body: 'כל הכבוד!' },
                { title: '📧 הודעה חדשה', body: 'יש לך הודעה חדשה' },
                { title: '🎁 מתנה!', body: 'קיבלת פרס' },
                { title: '🚀 שדרוג!', body: 'המערכת שודרגה' },
                { title: '💡 טיפ יומי', body: 'לחץ לקבלת טיפ' }
            ];
            
            const msg = messages[Math.floor(Math.random() * messages.length)];
            // שלח דרך Permissions כדי שיישמר
            Permissions.showNotification(msg.title, { body: msg.body });
            log(`נשלחה התראה אקראית: ${msg.title}`, 'success');
            updateCounter();
        }
        
        function sendMultipleNotifications() {
            log('שולח 5 התראות...', 'info');
            let count = 0;
            
            const interval = setInterval(() => {
                count++;
                // שלח דרך Permissions כדי שיישמר
                Permissions.showNotification(`התראה ${count}/5`, {
                    body: `זו התראה מספר ${count} מתוך 5`
                });
                updateCounter();
                
                if (count >= 5) {
                    clearInterval(interval);
                    log('נשלחו 5 התראות', 'success');
                }
            }, 1000);
        }
        
        // התראה מותאמת
        function sendCustomNotification() {
            const title = document.getElementById('custom-title').value || 'התראה';
            const body = document.getElementById('custom-body').value || 'ללא תוכן';
            const icon = document.getElementById('custom-icon').value;
            
            let finalTitle = title;
            if (icon !== 'default') {
                const icons = {
                    'success': '✅',
                    'warning': '⚠️',
                    'error': '❌',
                    'info': 'ℹ️'
                };
                finalTitle = icons[icon] + ' ' + title;
            }
            
            // שלח דרך Permissions כדי שיישמר
            Permissions.showNotification(finalTitle, { body: body });
            log(`נשלחה התראה מותאמת: ${finalTitle}`, 'success');
            updateCounter();
        }
        
        // בקשת הרשאות
        async function requestPermissions() {
            log('מבקש הרשאות...', 'info');
            const result = await Permissions.requestNotificationPermission();
            if (result) {
                log('הרשאות ניתנו!', 'success');
                checkStatus();
            } else {
                log('הרשאות נדחו', 'error');
            }
        }
        
        // ניקוי קונסול
        function clearConsole() {
            consoleDiv.innerHTML = '';
            log('הקונסול נוקה', 'info');
        }
        
        // אתחול
        checkStatus();
        log('Debug Panel Ready', 'success');
        
        // רענון סטטוס כל 5 שניות
        setInterval(checkStatus, 5000);
    </script>
</body>
</html>