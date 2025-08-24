<!DOCTYPE html>
<html dir="rtl" lang="he">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>בדיקת PWA</title>
    <link rel="manifest" href="/family/manifest.json">
    <meta name="theme-color" content="#667eea">
    <meta name="apple-mobile-web-app-capable" content="yes">
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
        button:disabled { 
            background: #ccc; 
            cursor: not-allowed;
        }
        .install-banner {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            padding: 20px;
            border-radius: 10px;
            margin: 20px 0;
            text-align: center;
            display: none;
        }
        .install-banner.show { display: block; }
        .browser-info {
            background: #fff3cd;
            color: #856404;
            padding: 15px;
            border-radius: 8px;
            margin: 20px 0;
            border: 1px solid #ffeeba;
        }
    </style>
</head>
<body>
    <h1>🔍 בדיקת תקינות PWA</h1>
    
    <!-- מידע על הדפדפן -->
    <div class="browser-info">
        <strong>📱 מידע על הדפדפן:</strong>
        <div id="browser-info"></div>
    </div>
    
    <!-- באנר התקנה -->
    <div id="install-banner" class="install-banner">
        <h2>🎉 האפליקציה מוכנה להתקנה!</h2>
        <p>לחץ על הכפתור להתקנת האפליקציה במכשיר שלך</p>
        <button onclick="promptInstall()" style="font-size: 20px; padding: 15px 30px;">
            📱 התקן עכשיו
        </button>
    </div>
    
    <div id="checks"></div>
    
    <div style="margin-top: 30px;">
        <button id="install-btn" onclick="installPWA()">📱 התקן אפליקציה</button>
        <button onclick="testNotification()">🔔 בדוק התראות</button>
        <button onclick="clearCache()">🗑️ נקה Cache</button>
        <button onclick="checkManualInstall()">📋 הוראות התקנה ידנית</button>
        <button onclick="location.reload()">🔄 רענן</button>
    </div>

    <script>
        const checks = document.getElementById('checks');
        let deferredPrompt = null;
        let installReady = false;
        
        // מידע על הדפדפן
        function detectBrowser() {
            const ua = navigator.userAgent;
            const browserInfo = document.getElementById('browser-info');
            let info = '';
            
            if (/chrome|chromium|crios/i.test(ua) && !/edge/i.test(ua)) {
                info = 'Chrome - תומך בהתקנת PWA ✅';
            } else if (/safari/i.test(ua) && !/chrome/i.test(ua)) {
                info = 'Safari - התקנה דרך "Add to Home Screen" במסך השיתוף';
            } else if (/firefox|fxios/i.test(ua)) {
                info = 'Firefox - תמיכה חלקית ב-PWA';
            } else if (/edge/i.test(ua)) {
                info = 'Edge - תומך בהתקנת PWA ✅';
            } else {
                info = 'דפדפן לא מזוהה';
            }
            
            info += '<br>User Agent: ' + ua.substring(0, 100) + '...';
            browserInfo.innerHTML = info;
        }
        
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
                    // תחילה נרשום SW חדש
                    await navigator.serviceWorker.register('/family/service-worker.js', {scope: '/family/'});
                    
                    // אז נבדוק אם רשום
                    const reg = await navigator.serviceWorker.getRegistration('/family/');
                    addCheck(!!reg, 'Service Worker', reg ? 'Registered' : 'Not registered');
                    
                    // בדוק אם ה-SW פעיל
                    if (reg && reg.active) {
                        addCheck(true, 'Service Worker Status', 'Active', 'success');
                    } else if (reg && reg.installing) {
                        addCheck(true, 'Service Worker Status', 'Installing...', 'warning');
                    } else if (reg && reg.waiting) {
                        addCheck(true, 'Service Worker Status', 'Waiting...', 'warning');
                    }
                } catch (e) {
                    addCheck(false, 'Service Worker', e.message);
                }
            } else {
                addCheck(false, 'Service Worker', 'Not supported');
            }
            
            // 3. בדיקת Manifest
            try {
                const response = await fetch('/family/manifest.json');
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
                    manifest.start_url === '/family/dashboard.php',
                    'Start URL',
                    manifest.start_url || 'Not defined'
                );
                
            } catch (e) {
                addCheck(false, 'Manifest', e.message);
            }
            
            // 4. בדיקת אייקון 192x192
            try {
                const iconResponse = await fetch('/family/images/icons/icon-192x192.png');
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
                true,
                'Display Mode',
                displayMode ? 'Standalone (Installed)' : 'Browser',
                displayMode ? 'success' : 'info'
            );
            
            // 6. בדיקת התקנה
            if (installReady) {
                addCheck(true, 'Install Ready', 'אפשר להתקין! 🎉', 'success');
            } else {
                addCheck(false, 'Install Ready', 'ממתין לאפשרות התקנה...', 'warning');
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
        
        // התקנת PWA - האזנה לאירוע
        window.addEventListener('beforeinstallprompt', (e) => {
            console.log('beforeinstallprompt fired!');
            e.preventDefault();
            deferredPrompt = e;
            installReady = true;
            
            // הצג באנר התקנה
            document.getElementById('install-banner').classList.add('show');
            document.getElementById('install-btn').disabled = false;
            
            // עדכן את הבדיקות
            runChecks();
        });
        
        // בדוק אם האפליקציה כבר מותקנת
        window.addEventListener('appinstalled', (evt) => {
            console.log('App installed!');
            deferredPrompt = null;
            document.getElementById('install-banner').classList.remove('show');
            alert('האפליקציה הותקנה בהצלחה! 🎉');
            runChecks();
        });
        
        async function installPWA() {
            if (deferredPrompt) {
                promptInstall();
            } else {
                checkManualInstall();
            }
        }
        
        async function promptInstall() {
            if (!deferredPrompt) {
                alert('האפליקציה כבר מותקנת או שאין אפשרות התקנה כרגע');
                return;
            }
            
            deferredPrompt.prompt();
            const result = await deferredPrompt.userChoice;
            
            console.log('User choice:', result.outcome);
            
            if (result.outcome === 'accepted') {
                console.log('User accepted the install prompt');
            } else {
                console.log('User dismissed the install prompt');
            }
            
            deferredPrompt = null;
            document.getElementById('install-banner').classList.remove('show');
        }
        
        // הוראות התקנה ידנית
        function checkManualInstall() {
            const ua = navigator.userAgent;
            let instructions = '';
            
            if (/iphone|ipad|ipod/i.test(ua)) {
                instructions = `
                    <h3>התקנה ב-iOS (Safari):</h3>
                    <ol>
                        <li>פתח את האתר ב-Safari</li>
                        <li>לחץ על כפתור השיתוף (ריבוע עם חץ)</li>
                        <li>גלול למטה ובחר "Add to Home Screen"</li>
                        <li>תן שם לאפליקציה ולחץ "Add"</li>
                    </ol>
                `;
            } else if (/android/i.test(ua)) {
                instructions = `
                    <h3>התקנה ב-Android (Chrome):</h3>
                    <ol>
                        <li>לחץ על שלוש הנקודות בפינה העליונה</li>
                        <li>בחר "התקן אפליקציה" או "Add to Home Screen"</li>
                        <li>אשר את ההתקנה</li>
                    </ol>
                    <p>אם האפשרות לא מופיעה, נסה:</p>
                    <ul>
                        <li>רענן את הדף (Ctrl+F5)</li>
                        <li>המתן 30 שניות ורענן שוב</li>
                        <li>ודא שאתה משתמש ב-Chrome</li>
                    </ul>
                `;
            } else {
                instructions = `
                    <h3>התקנה במחשב (Chrome/Edge):</h3>
                    <ol>
                        <li>חפש אייקון התקנה בשורת הכתובת (מימין)</li>
                        <li>או לחץ על שלוש הנקודות > "התקן..."</li>
                        <li>אשר את ההתקנה</li>
                    </ol>
                `;
            }
            
            const modal = document.createElement('div');
            modal.style.cssText = `
                position: fixed;
                top: 50%;
                left: 50%;
                transform: translate(-50%, -50%);
                background: white;
                padding: 30px;
                border-radius: 10px;
                box-shadow: 0 10px 50px rgba(0,0,0,0.3);
                z-index: 1000;
                max-width: 500px;
                direction: rtl;
            `;
            modal.innerHTML = instructions + `
                <button onclick="this.parentElement.remove()" 
                        style="margin-top: 20px; width: 100%;">
                    סגור
                </button>
            `;
            document.body.appendChild(modal);
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
                    icon: '/family/images/icons/icon-192x192.png',
                    badge: '/family/images/icons/badge-72x72.png',
                    dir: 'rtl',
                    lang: 'he'
                });
                runChecks();
            }
        }
        
        // ניקוי Cache
        async function clearCache() {
            if ('caches' in window) {
                const cacheNames = await caches.keys();
                await Promise.all(cacheNames.map(name => caches.delete(name)));
                
                // רשום מחדש את ה-Service Worker
                if ('serviceWorker' in navigator) {
                    const reg = await navigator.serviceWorker.getRegistration('/family/');
                    if (reg) {
                        await reg.unregister();
                    }
                    await navigator.serviceWorker.register('/family/service-worker.js', {scope: '/family/'});
                }
                
                alert('Cache נוקה והService Worker נרשם מחדש');
                setTimeout(() => location.reload(), 1000);
            }
        }
        
        // אתחול
        window.addEventListener('load', () => {
            detectBrowser();
            runChecks();
            
            // בדוק אם האפליקציה כבר מותקנת
            if (window.matchMedia('(display-mode: standalone)').matches) {
                addCheck(true, 'App Status', 'האפליקציה כבר מותקנת! 🎉', 'success');
            }
            
            // עדכון אוטומטי
            setInterval(runChecks, 5000);
        });
        
        // Log for debugging
        console.log('PWA Check Script Loaded');
        console.log('Current URL:', location.href);
        console.log('Manifest URL:', '/family/manifest.json');
        console.log('SW URL:', '/family/service-worker.js');
    </script>
</body>
</html>