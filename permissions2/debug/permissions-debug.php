<!DOCTYPE html>
<html dir="rtl" lang="he">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🔧 דיבוג הרשאות - Permissions Debug</title>
    
    <!-- CSS -->
    <link rel="stylesheet" href="../ui/styles/permissions.css">
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            margin: 0;
            padding: 20px;
        }
        
        .debug-container {
            max-width: 1400px;
            margin: 0 auto;
        }
        
        .debug-header-main {
            background: white;
            border-radius: 16px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }
        
        .debug-header-main h1 {
            margin: 0 0 10px 0;
            font-size: 32px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            -webkit-background-clip: text;
            background-clip: text;
            -webkit-text-fill-color: transparent;
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .debug-info {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        
        .info-box {
            background: #f3f4f6;
            padding: 15px;
            border-radius: 12px;
            text-align: center;
        }
        
        .info-box-title {
            font-size: 12px;
            color: #6b7280;
            margin-bottom: 5px;
        }
        
        .info-box-value {
            font-size: 20px;
            font-weight: 700;
            color: #1f2937;
        }
        
        .info-box-value.good { color: #10b981; }
        .info-box-value.warning { color: #f59e0b; }
        .info-box-value.danger { color: #ef4444; }
        
        .controls-section {
            background: white;
            border-radius: 16px;
            padding: 25px;
            margin-bottom: 30px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }
        
        .controls-section h2 {
            margin: 0 0 20px 0;
            font-size: 20px;
            color: #1f2937;
            border-bottom: 2px solid #e5e7eb;
            padding-bottom: 10px;
        }
        
        .controls-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
        }
        
        .control-btn {
            padding: 12px 20px;
            border: none;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        
        .control-btn.primary {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
        }
        
        .control-btn.success {
            background: #10b981;
            color: white;
        }
        
        .control-btn.warning {
            background: #f59e0b;
            color: white;
        }
        
        .control-btn.danger {
            background: #ef4444;
            color: white;
        }
        
        .control-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0,0,0,0.2);
        }
        
        .console-output {
            background: #1f2937;
            color: #10b981;
            font-family: 'Courier New', monospace;
            padding: 20px;
            border-radius: 12px;
            margin-top: 20px;
            min-height: 200px;
            max-height: 400px;
            overflow-y: auto;
            font-size: 12px;
            line-height: 1.6;
        }
        
        .console-line {
            margin: 2px 0;
            padding: 2px 5px;
        }
        
        .console-line.error { color: #ef4444; }
        .console-line.warning { color: #f59e0b; }
        .console-line.success { color: #10b981; }
        .console-line.info { color: #3b82f6; }
        
        .loading {
            display: none;
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: white;
            padding: 30px;
            border-radius: 16px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
            z-index: 10000;
            text-align: center;
        }
        
        .loading.show { display: block; }
        
        .spinner {
            border: 4px solid #f3f4f6;
            border-top: 4px solid #667eea;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            animation: spin 1s linear infinite;
            margin: 0 auto 20px;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        .tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            border-bottom: 2px solid #e5e7eb;
        }
        
        .tab-btn {
            padding: 10px 20px;
            background: none;
            border: none;
            font-size: 14px;
            font-weight: 600;
            color: #6b7280;
            cursor: pointer;
            transition: all 0.3s;
            border-bottom: 3px solid transparent;
            margin-bottom: -2px;
        }
        
        .tab-btn.active {
            color: #667eea;
            border-bottom-color: #667eea;
        }
        
        .tab-content {
            display: none;
        }
        
        .tab-content.active {
            display: block;
        }
    </style>
</head>
<body>
    <div class="debug-container">
        <!-- Header -->
        <div class="debug-header-main">
            <h1>
                <span>🔧</span>
                מערכת דיבוג הרשאות
            </h1>
            <p style="color: #6b7280; margin: 0;">בדיקה וניהול של כל ההרשאות באפליקציה</p>
            
            <div class="debug-info">
                <div class="info-box">
                    <div class="info-box-title">סטטוס כללי</div>
                    <div class="info-box-value" id="generalStatus">בודק...</div>
                </div>
                <div class="info-box">
                    <div class="info-box-title">הרשאות פעילות</div>
                    <div class="info-box-value good" id="activePermissions">0</div>
                </div>
                <div class="info-box">
                    <div class="info-box-title">הרשאות חסומות</div>
                    <div class="info-box-value danger" id="blockedPermissions">0</div>
                </div>
                <div class="info-box">
                    <div class="info-box-title">דפדפן</div>
                    <div class="info-box-value" id="browserInfo">---</div>
                </div>
                <div class="info-box">
                    <div class="info-box-title">HTTPS</div>
                    <div class="info-box-value" id="httpsStatus">---</div>
                </div>
                <div class="info-box">
                    <div class="info-box-title">Service Worker</div>
                    <div class="info-box-value" id="swStatus">---</div>
                </div>
            </div>
        </div>

        <!-- Controls -->
        <div class="controls-section">
            <h2>🎮 כלי בדיקה</h2>
            
            <div class="tabs">
                <button class="tab-btn active" onclick="switchTab('tests')">בדיקות</button>
                <button class="tab-btn" onclick="switchTab('requests')">בקשות</button>
                <button class="tab-btn" onclick="switchTab('simulations')">סימולציות</button>
                <button class="tab-btn" onclick="switchTab('cleanup')">ניקוי</button>
            </div>
            
            <!-- בדיקות -->
            <div class="tab-content active" id="tests-tab">
                <div class="controls-grid">
                    <button class="control-btn primary" onclick="checkAllPermissions()">
                        <span>🔍</span> בדוק את כל ההרשאות
                    </button>
                    <button class="control-btn primary" onclick="checkNotifications()">
                        <span>🔔</span> בדוק התראות
                    </button>
                    <button class="control-btn primary" onclick="checkPush()">
                        <span>📨</span> בדוק Push
                    </button>
                    <button class="control-btn primary" onclick="checkMedia()">
                        <span>📷</span> בדוק מדיה
                    </button>
                    <button class="control-btn primary" onclick="checkLocation()">
                        <span>📍</span> בדוק מיקום
                    </button>
                    <button class="control-btn primary" onclick="checkStorage()">
                        <span>💾</span> בדוק אחסון
                    </button>
                    <button class="control-btn primary" onclick="checkClipboard()">
                        <span>📋</span> בדוק לוח העתקה
                    </button>
                    <button class="control-btn primary" onclick="checkBackground()">
                        <span>🔄</span> בדוק רקע
                    </button>
                </div>
            </div>
            
            <!-- בקשות -->
            <div class="tab-content" id="requests-tab">
                <div class="controls-grid">
                    <button class="control-btn success" onclick="requestNotification()">
                        <span>🔔</span> בקש התראות
                    </button>
                    <button class="control-btn success" onclick="requestPush()">
                        <span>📨</span> בקש Push
                    </button>
                    <button class="control-btn success" onclick="requestCamera()">
                        <span>📷</span> בקש מצלמה
                    </button>
                    <button class="control-btn success" onclick="requestMicrophone()">
                        <span>🎤</span> בקש מיקרופון
                    </button>
                    <button class="control-btn success" onclick="requestLocation()">
                        <span>📍</span> בקש מיקום
                    </button>
                    <button class="control-btn success" onclick="requestStorage()">
                        <span>💾</span> בקש אחסון
                    </button>
                    <button class="control-btn success" onclick="requestAllCritical()">
                        <span>⚡</span> בקש הרשאות קריטיות
                    </button>
                    <button class="control-btn warning" onclick="requestAllPermissions()">
                        <span>🚀</span> בקש הכל
                    </button>
                </div>
            </div>
            
            <!-- סימולציות -->
            <div class="tab-content" id="simulations-tab">
                <div class="controls-grid">
                    <button class="control-btn warning" onclick="testNotification()">
                        <span>🔔</span> שלח התראת בדיקה
                    </button>
                    <button class="control-btn warning" onclick="testPushNotification()">
                        <span>📨</span> שלח Push בדיקה
                    </button>
                    <button class="control-btn warning" onclick="testCamera()">
                        <span>📷</span> הפעל מצלמה
                    </button>
                    <button class="control-btn warning" onclick="testMicrophone()">
                        <span>🎤</span> הפעל מיקרופון
                    </button>
                    <button class="control-btn warning" onclick="testLocation()">
                        <span>📍</span> קבל מיקום
                    </button>
                    <button class="control-btn warning" onclick="testVibration()">
                        <span>📳</span> הפעל רטט
                    </button>
                    <button class="control-btn warning" onclick="testClipboard()">
                        <span>📋</span> בדוק העתקה
                    </button>
                    <button class="control-btn warning" onclick="testFullscreen()">
                        <span>🖥️</span> מסך מלא
                    </button>
                </div>
            </div>
            
            <!-- ניקוי -->
            <div class="tab-content" id="cleanup-tab">
                <div class="controls-grid">
                    <button class="control-btn danger" onclick="clearPermissionCache()">
                        <span>🗑️</span> נקה מטמון הרשאות
                    </button>
                    <button class="control-btn danger" onclick="unregisterServiceWorker()">
                        <span>⚠️</span> הסר Service Worker
                    </button>
                    <button class="control-btn danger" onclick="clearLocalStorage()">
                        <span>💾</span> נקה Local Storage
                    </button>
                    <button class="control-btn danger" onclick="resetAllSettings()">
                        <span>🔄</span> איפוס כללי
                    </button>
                    <button class="control-btn primary" onclick="exportDebugData()">
                        <span>📤</span> ייצא נתוני דיבוג
                    </button>
                    <button class="control-btn primary" onclick="generateReport()">
                        <span>📊</span> צור דוח מלא
                    </button>
                </div>
            </div>
            
            <!-- Console Output -->
            <div class="console-output" id="console">
                <div class="console-line info">🚀 מערכת דיבוג הרשאות מוכנה</div>
                <div class="console-line info">⏰ ${new Date().toLocaleString('he-IL')}</div>
            </div>
        </div>

        <!-- Permissions Grid -->
        <div class="permissions-grid" id="permissionsGrid">
            <!-- יתמלא דינמית -->
        </div>
    </div>

    <!-- Loading -->
    <div class="loading" id="loading">
        <div class="spinner"></div>
        <div>טוען...</div>
    </div>

    <!-- Scripts -->
    <script src="../js/permissions-manager.js"></script>
    <script>
        // Initialize
        let pm;
        const consoleEl = document.getElementById('console');
        
        document.addEventListener('DOMContentLoaded', () => {
            pm = new PermissionsManager({
                debug: true,
                autoCheck: true
            });
            
            // Listen to events
            pm.on('update', (permissions) => {
                updateUI(permissions);
            });
            
            pm.on('change', (type, status) => {
                log(`הרשאת ${type} השתנתה ל-${status}`, 'info');
            });
            
            // Initial check
            setTimeout(() => checkAllPermissions(), 1000);
        });

        // UI Functions
        function switchTab(tab) {
            document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
            document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));
            
            event.target.classList.add('active');
            document.getElementById(`${tab}-tab`).classList.add('active');
        }

        function log(message, type = 'info') {
            const line = document.createElement('div');
            line.className = `console-line ${type}`;
            line.textContent = `[${new Date().toLocaleTimeString('he-IL')}] ${message}`;
            consoleEl.appendChild(line);
            consoleEl.scrollTop = consoleEl.scrollHeight;
        }

        function showLoading(show = true) {
            document.getElementById('loading').classList.toggle('show', show);
        }

        function updateUI(permissions) {
            let granted = 0, denied = 0, blocked = 0;
            
            Object.values(permissions).forEach(p => {
                if (p.status === 'granted') granted++;
                else if (p.status === 'denied') denied++;
                else if (p.status === 'blocked') blocked++;
            });
            
            document.getElementById('activePermissions').textContent = granted;
            document.getElementById('blockedPermissions').textContent = blocked;
            
            // Update grid
            updatePermissionsGrid(permissions);
            
            // Update general status
            const statusEl = document.getElementById('generalStatus');
            if (granted > 5) {
                statusEl.textContent = 'מצוין';
                statusEl.className = 'info-box-value good';
            } else if (granted > 2) {
                statusEl.textContent = 'טוב';
                statusEl.className = 'info-box-value warning';
            } else {
                statusEl.textContent = 'חלש';
                statusEl.className = 'info-box-value danger';
            }
        }

        function updatePermissionsGrid(permissions) {
            const grid = document.getElementById('permissionsGrid');
            grid.innerHTML = '';
            
            Object.entries(permissions).forEach(([type, data]) => {
                const card = createPermissionCard(type, data);
                grid.appendChild(card);
            });
        }

        function createPermissionCard(type, data) {
            const card = document.createElement('div');
            card.className = `permission-card ${data.status}`;
            
            const info = getPermissionInfo(type);
            
            card.innerHTML = `
                <div class="permission-header">
                    <div class="permission-title">
                        <span class="permission-title-icon">${info.icon}</span>
                        <span class="permission-title-text">${info.name}</span>
                    </div>
                    <span class="permission-status ${data.status}">${translateStatus(data.status)}</span>
                </div>
                <div class="permission-description">${info.description}</div>
                <div class="permission-meta">
                    <div class="permission-meta-item">
                        <span class="permission-meta-icon">🌐</span>
                        <span>${data.browser_support ? 'נתמך' : 'לא נתמך'}</span>
                    </div>
                    <button class="permission-action" onclick="requestPermission('${type}')" 
                            ${data.status === 'granted' || !data.browser_support ? 'disabled' : ''}>
                        ${data.status === 'granted' ? 'פעיל' : 'בקש הרשאה'}
                    </button>
                </div>
            `;
            
            return card;
        }

        function getPermissionInfo(type) {
            const info = {
                'notification': { name: 'התראות', icon: '🔔', description: 'הצגת התראות במערכת' },
                'push': { name: 'Push', icon: '📨', description: 'התראות גם כשהאתר סגור' },
                'camera': { name: 'מצלמה', icon: '📷', description: 'גישה למצלמת המכשיר' },
                'microphone': { name: 'מיקרופון', icon: '🎤', description: 'הקלטת שמע' },
                'geolocation': { name: 'מיקום', icon: '📍', description: 'מיקום המכשיר' },
                'persistent-storage': { name: 'אחסון', icon: '💾', description: 'שמירת נתונים' },
                'clipboard-read': { name: 'לוח העתקה', icon: '📋', description: 'גישה ללוח' },
                'background-sync': { name: 'סנכרון רקע', icon: '🔄', description: 'סנכרון ברקע' }
            };
            
            return info[type] || { name: type, icon: '❓', description: 'הרשאה לא מוכרת' };
        }

        function translateStatus(status) {
            const translations = {
                'granted': 'מאושר',
                'denied': 'נדחה',
                'prompt': 'ממתין',
                'blocked': 'חסום',
                'not_supported': 'לא נתמך'
            };
            return translations[status] || status;
        }

        // Test Functions
        async function checkAllPermissions() {
            showLoading();
            log('בודק את כל ההרשאות...', 'info');
            
            try {
                const results = await pm.checkAllPermissions();
                log(`✅ נבדקו ${Object.keys(results).length} הרשאות`, 'success');
                
                // Check browser info
                const ua = navigator.userAgent;
                let browser = 'Unknown';
                if (ua.includes('Chrome')) browser = 'Chrome';
                else if (ua.includes('Firefox')) browser = 'Firefox';
                else if (ua.includes('Safari')) browser = 'Safari';
                else if (ua.includes('Edge')) browser = 'Edge';
                
                document.getElementById('browserInfo').textContent = browser;
                document.getElementById('httpsStatus').textContent = location.protocol === 'https:' ? '✅' : '❌';
                document.getElementById('swStatus').textContent = 'serviceWorker' in navigator ? '✅' : '❌';
                
            } catch (error) {
                log(`❌ שגיאה: ${error.message}`, 'error');
            } finally {
                showLoading(false);
            }
        }

        async function requestPermission(type) {
            showLoading();
            log(`מבקש הרשאת ${type}...`, 'info');
            
            try {
                const result = await pm.requestPermission(type);
                if (result.success) {
                    log(`✅ הרשאת ${type} ניתנה`, 'success');
                } else {
                    log(`❌ הרשאת ${type} נדחתה: ${result.reason}`, 'error');
                }
            } catch (error) {
                log(`❌ שגיאה: ${error.message}`, 'error');
            } finally {
                showLoading(false);
                checkAllPermissions();
            }
        }

        // Specific permission tests
        async function checkNotifications() {
            const status = await pm.checkPermission('notification');
            log(`התראות: ${translateStatus(status.status)}`, status.status === 'granted' ? 'success' : 'warning');
        }

        async function requestNotification() {
            await requestPermission('notification');
        }

        async function testNotification() {
            if (Notification.permission === 'granted') {
                new Notification('בדיקת התראה 🔔', {
                    body: 'זו התראת בדיקה מדף הדיבוג',
                    icon: '/pwa/icons/android/android-launchericon-192-192.png',
                    badge: '/pwa/icons/android/android-launchericon-96-96.png',
                    vibrate: [200, 100, 200]
                });
                log('התראה נשלחה', 'success');
            } else {
                log('אין הרשאת התראות', 'error');
            }
        }

        async function testCamera() {
            try {
                const stream = await navigator.mediaDevices.getUserMedia({ video: true });
                log('מצלמה פועלת', 'success');
                setTimeout(() => {
                    stream.getTracks().forEach(track => track.stop());
                    log('מצלמה נעצרה', 'info');
                }, 3000);
            } catch (error) {
                log(`שגיאה במצלמה: ${error.message}`, 'error');
            }
        }

        async function testVibration() {
            if ('vibrate' in navigator) {
                navigator.vibrate([200, 100, 200, 100, 400]);
                log('רטט הופעל', 'success');
            } else {
                log('רטט לא נתמך', 'warning');
            }
        }

        function clearPermissionCache() {
            localStorage.removeItem('permissions-cache');
            sessionStorage.clear();
            log('מטמון הרשאות נוקה', 'success');
            location.reload();
        }

        function exportDebugData() {
            const data = {
                timestamp: new Date().toISOString(),
                permissions: pm.getAllStatuses(),
                browser: navigator.userAgent,
                protocol: location.protocol,
                serviceWorker: 'serviceWorker' in navigator,
                localStorage: { ...localStorage }
            };
            
            const blob = new Blob([JSON.stringify(data, null, 2)], { type: 'application/json' });
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `permissions-debug-${Date.now()}.json`;
            a.click();
            
            log('נתוני דיבוג יוצאו', 'success');
        }

        function generateReport() {
            window.open('/permissions/api/generate-report.php', '_blank');
            log('דוח נוצר', 'success');
        }
    </script>
</body>
</html>