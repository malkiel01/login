<!DOCTYPE html>
<html dir="rtl" lang="he">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>בדיקת PWA</title>
    <link rel="manifest" href="/form/family/manifest.json">
    <meta name="theme-color" content="#667eea">
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            max-width: 800px;
            margin: 50px auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .check-item {
            background: white;
            padding: 15px;
            margin: 10px 0;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .success { border-right: 4px solid #28a745; }
        .error { border-right: 4px solid #dc3545; }
        .warning { border-right: 4px solid #ffc107; }
        .info { border-right: 4px solid #17a2b8; }
        button {
            background: #667eea;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            margin: 5px;
        }
        button:hover { background: #5569d0; }
        pre {
            background: #f8f9fa;
            padding: 10px;
            border-radius: 5px;
            overflow-x: auto;
            font-size: 12px;
        }
    </style>
</head>
<body>
    <h1>🔍 בדיקת תקינות PWA</h1>
    
    <div id="checks"></div>
    
    <div style="margin-top: 30px;">
        <button onclick="installPWA()">📱 התקן אפליקציה</button>
        <button onclick="testNotification()">🔔 בדוק התראות</button>
        <button onclick="clearCache()">🗑️ נקה Cache</button>
        <button onclick="location.reload()">🔄 רענן</button>
    </div>
    
    <div id="install-prompt" style="display:none; margin-top:20px;" class="check-item info">
        <h3>האפליקציה מוכנה להתקנה!</h3>
        <button onclick="promptInstall()">התקן עכשיו</button>
    </div>

    <script>
        const checks = document.getElementById('checks');
        let deferredPrompt = null;
        
        // בדיקות אוטומטיות
        async function runChecks() {
            checks.innerHTML = '';
            
            // 1. בדיקת HTTPS
            addCheck(
                location.protocol === 'https:' || location.hostname === 'localhost',
                'HTTPS',
                `Protocol: ${location.protocol}, Host: ${location.hostname}`
            );
            
            // 2. בדיקת Service Worker
            if ('serviceWorker' in navigator) {
                try {
                    const reg = await navigator.serviceWorker.getRegistration('/form/family/');
                    addCheck(!!reg, 'Service Worker', reg ? 'Registered' : 'Not registered');
                    
                    if (!reg) {
                        // נסה לרשום
                        const newReg = await navigator.serviceWorker.register('/form/family/service-worker.js');
                        addCheck(true, 'Service Worker Registration', 'Successfully registered');
                    }
                } catch (e) {
                    addCheck(false, 'Service Worker', e.message);
                }
            } else {
                addCheck(false, 'Service Worker', 'Not supported');
            }
            
            // 3. בדיקת Manifest
            try {
                const response = await fetch('/form/family/manifest.json');
                const manifest = await response.json();
                addCheck(
                    response.ok,
                    'Manifest',
                    response.ok ? `Found: ${manifest.name}` : 'Not found'
                );
                
                // בדוק אייקונים
                const icon192 = manifest.icons?.find(i => i.sizes === '192x192');
                const icon512 = manifest.icons?.find(i => i.sizes === '512x512');
                addCheck(
                    icon192 && icon512,
                    'Required Icons',
                    `192x192: ${icon192 ? '✓' : '✗'}, 512x512: ${icon512 ? '✓' : '✗'}`
                );
                
                // בדוק start_url
                addCheck(
                    manifest.start_url === '/form/family/dashboard.php',
                    'Start URL',
                    manifest.start_url || 'Not defined'
                );
                
            } catch (e) {
                addCheck(false, 'Manifest', e.message);
            }
            
            // 4. בדיקת אייקון 192x192
            try {
                const iconResponse = await fetch('/form/family/images/icons/icon-192x192.png');
                addCheck(
                    iconResponse.ok,
                    'Icon 192x192',
                    iconResponse.ok ? 'Found' : 'Missing'
                );
            } catch (e) {
                addCheck(false, 'Icon 192x192', 'Missing');
            }
            
            // 5. בדיקת Display Mode
            const displayMode = window.matchMedia('(display-mode: standalone)').matches;
            addCheck(
                true, // תמיד הצג כ-info
                'Display Mode',
                displayMode ? 'Standalone (Installed)' : 'Browser',
                'info'
            );
            
            // 6. בדיקת התראות
            if ('Notification' in window) {
                const permission = Notification.permission;
                addCheck(
                    permission === 'granted',
                    'Notifications',
                    `Permission: ${permission}`,
                    permission === 'denied' ? 'error' : permission === 'default' ? 'warning' : 'success'
                );
            } else {
                addCheck(false, 'Notifications', 'Not supported');
            }
            
            // 7. בדיקת Cache
            if ('caches' in window) {
                try {
                    const cacheNames = await caches.keys();
                    addCheck(
                        cacheNames.length > 0,
                        'Cache Storage',
                        `Active caches: ${cacheNames.join(', ') || 'None'}`,
                        cacheNames.length > 0 ? 'success' : 'warning'
                    );
                } catch (e) {
                    addCheck(false, 'Cache Storage', e.message);
                }
            }
        }
        
        function addCheck(success, title, details, type = null) {
            const div = document.createElement('div');
            div.className = `check-item ${type || (success ? 'success' : 'error')}`;
            div.innerHTML = `
                <strong>${success ? '✅' : '❌'} ${title}</strong>
                <div style="color: #666; font-size: 14px; margin-top: 5px;">${details}</div>
            `;
            checks.appendChild(div);
        }
        
        // התקנת PWA
        window.addEventListener('beforeinstallprompt', (e) => {
            e.preventDefault();
            deferredPrompt = e;
            document.getElementById('install-prompt').style.display = 'block';
            addCheck(true, 'Install Prompt', 'Ready to install!', 'success');
        });
        
        async function installPWA() {
            if (!deferredPrompt) {
                alert('האפליקציה כבר מותקנת או שהדפדפן לא תומך בהתקנה');
                return;
            }
            promptInstall();
        }
        
        async function promptInstall() {
            if (!deferredPrompt) return;
            
            deferredPrompt.prompt();
            const result = await deferredPrompt.userChoice;
            
            if (result.outcome === 'accepted') {
                alert('האפליקציה הותקנה בהצלחה!');
            }
            
            deferredPrompt = null;
            document.getElementById('install-prompt').style.display = 'none';
        }
        
        // בדיקת התראות
        async function testNotification() {
            if (!('Notification' in window)) {
                alert('הדפדפן לא תומך בהתראות');
                return;
            }
            
            if (Notification.permission === 'default') {
                const permission = await Notification.requestPermission();
                if (permission !== 'granted') {
                    alert('ההרשאה נדחתה');
                    return;
                }
            }
            
            if (Notification.permission === 'granted') {
                new Notification('בדיקת התראות', {
                    body: 'ההתראות עובדות מצוין! 🎉',
                    icon: '/form/family/images/icons/icon-192x192.png',
                    badge: '/form/family/images/icons/badge-72x72.png',
                    dir: 'rtl',
                    lang: 'he'
                });
            }
        }
        
        // ניקוי Cache
        async function clearCache() {
            if ('caches' in window) {
                const cacheNames = await caches.keys();
                await Promise.all(cacheNames.map(name => caches.delete(name)));
                alert('Cache נוקה בהצלחה');
                location.reload();
            }
        }
        
        // הרץ בדיקות בטעינה
        runChecks();
        
        // רענן כל 5 שניות
        setInterval(runChecks, 5000);
    </script>
</body>
</html>