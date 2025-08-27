<!DOCTYPE html>
<html dir="rtl" lang="he">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Debug Menu</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        /* המנה הראשית */
        .debug-menu-container {
            position: fixed;
            bottom: 20px;
            right: 20px;
            z-index: 999999;
        }

        /* כפתור ראשי */
        .debug-main-button {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: linear-gradient(135deg, #1a1a1a, #2d2d2d);
            border: 2px solid #0f0;
            color: #0f0;
            font-size: 28px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 0 20px rgba(0, 255, 0, 0.5), inset 0 0 20px rgba(0, 255, 0, 0.1);
            transition: all 0.3s cubic-bezier(0.68, -0.55, 0.265, 1.55);
            position: relative;
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0%, 100% { 
                box-shadow: 0 0 20px rgba(0, 255, 0, 0.5), inset 0 0 20px rgba(0, 255, 0, 0.1);
            }
            50% { 
                box-shadow: 0 0 30px rgba(0, 255, 0, 0.8), inset 0 0 25px rgba(0, 255, 0, 0.2);
            }
        }

        .debug-main-button:hover {
            transform: scale(1.1);
            box-shadow: 0 0 40px rgba(0, 255, 0, 0.9), inset 0 0 30px rgba(0, 255, 0, 0.3);
        }

        .debug-main-button.active {
            transform: rotate(45deg);
            background: linear-gradient(135deg, #0f0, #0a0);
            color: #000;
        }

        /* תפריט הדיבאגים */
        .debug-items {
            position: absolute;
            bottom: 0;
            right: 0;
            display: flex;
            flex-direction: column;
            gap: 15px;
            align-items: flex-end;
            pointer-events: none;
        }

        .debug-item {
            display: flex;
            align-items: center;
            gap: 10px;
            opacity: 0;
            transform: translateX(100px) scale(0);
            transition: all 0.3s cubic-bezier(0.68, -0.55, 0.265, 1.55);
            pointer-events: none;
        }

        .debug-menu-container.active .debug-item {
            opacity: 1;
            transform: translateX(0) scale(1);
            pointer-events: all;
        }

        /* עיכוב לאנימציה */
        .debug-menu-container.active .debug-item:nth-child(1) { transition-delay: 0.05s; }
        .debug-menu-container.active .debug-item:nth-child(2) { transition-delay: 0.1s; }
        .debug-menu-container.active .debug-item:nth-child(3) { transition-delay: 0.15s; }
        .debug-menu-container.active .debug-item:nth-child(4) { transition-delay: 0.2s; }
        .debug-menu-container.active .debug-item:nth-child(5) { transition-delay: 0.25s; }

        /* תווית לכל דיבאג */
        .debug-label {
            background: rgba(0, 0, 0, 0.95);
            color: #0f0;
            padding: 8px 15px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            border: 1px solid #0f0;
            box-shadow: 0 0 10px rgba(0, 255, 0, 0.3);
            white-space: nowrap;
            opacity: 0;
            transform: translateX(10px);
            transition: all 0.3s;
            font-family: 'Courier New', monospace;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .debug-item:hover .debug-label {
            opacity: 1;
            transform: translateX(0);
            box-shadow: 0 0 20px rgba(0, 255, 0, 0.5);
        }

        /* כפתורי הדיבאג */
        .debug-button {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            border: none;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            transition: all 0.3s;
            position: relative;
            overflow: hidden;
        }

        .debug-button::before {
            content: '';
            position: absolute;
            inset: -2px;
            background: linear-gradient(45deg, transparent, #0f0, transparent);
            z-index: -1;
            animation: rotate 3s linear infinite;
            opacity: 0;
            transition: opacity 0.3s;
        }

        @keyframes rotate {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .debug-button:hover::before {
            opacity: 1;
        }

        .debug-button:hover {
            transform: scale(1.2);
            box-shadow: 0 0 30px currentColor;
        }

        /* צבעים לכל כלי */
        .debug-button.console {
            background: linear-gradient(135deg, #1a1a1a, #000);
            color: #0f0;
            border: 2px solid #0f0;
        }

        .debug-button.notifications {
            background: linear-gradient(135deg, #3b82f6, #1e40af);
            color: white;
            border: 2px solid #60a5fa;
        }

        .debug-button.pwa {
            background: linear-gradient(135deg, #8b5cf6, #6d28d9);
            color: white;
            border: 2px solid #a78bfa;
        }

        .debug-button.storage {
            background: linear-gradient(135deg, #f59e0b, #d97706);
            color: white;
            border: 2px solid #fbbf24;
        }

        .debug-button.network {
            background: linear-gradient(135deg, #ef4444, #dc2626);
            color: white;
            border: 2px solid #f87171;
        }

        /* Tooltip on hover */
        .debug-button::after {
            content: attr(data-name);
            position: absolute;
            bottom: 100%;
            left: 50%;
            transform: translateX(-50%);
            background: rgba(0, 0, 0, 0.9);
            color: #0f0;
            padding: 5px 10px;
            border-radius: 5px;
            font-size: 10px;
            white-space: nowrap;
            opacity: 0;
            pointer-events: none;
            transition: all 0.3s;
            border: 1px solid #0f0;
        }

        /* Badge למספר שגיאות */
        .debug-badge {
            position: absolute;
            top: -5px;
            right: -5px;
            background: #f00;
            color: white;
            width: 20px;
            height: 20px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 10px;
            font-weight: bold;
            box-shadow: 0 0 10px rgba(255, 0, 0, 0.5);
            animation: blink 1s infinite;
        }

        @keyframes blink {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.7; }
        }

        /* אנימציית כניסה */
        @keyframes slideIn {
            from {
                transform: translateY(100px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        .debug-menu-container {
            animation: slideIn 0.5s ease;
        }

        /* Mobile responsive */
        @media (max-width: 768px) {
            .debug-main-button {
                width: 50px;
                height: 50px;
                font-size: 24px;
            }

            .debug-button {
                width: 40px;
                height: 40px;
                font-size: 20px;
            }

            .debug-label {
                font-size: 11px;
                padding: 6px 12px;
            }

            .debug-menu-container {
                bottom: 10px;
                right: 10px;
            }
        }

        /* Dark mode effect */
        .debug-glow {
            position: fixed;
            bottom: 20px;
            right: 20px;
            width: 60px;
            height: 60px;
            background: radial-gradient(circle, rgba(0, 255, 0, 0.3), transparent);
            border-radius: 50%;
            pointer-events: none;
            z-index: 999998;
            animation: glow 2s ease-in-out infinite;
        }

        @keyframes glow {
            0%, 100% { transform: scale(1); opacity: 0.5; }
            50% { transform: scale(1.5); opacity: 0.2; }
        }
    </style>
</head>
<body>
    <!-- Glow effect -->
    <div class="debug-glow"></div>

    <!-- Debug Menu Container -->
    <div class="debug-menu-container" id="debugMenu">
        <!-- Main Button -->
        <button class="debug-main-button" id="debugMainBtn" title="Debug Tools (Ctrl+Shift+D)">
            🔧
        </button>
        
        <!-- Debug Items -->
        <div class="debug-items">
            <!-- Console Debug -->
            <div class="debug-item">
                <span class="debug-label">Console Debug</span>
                <button class="debug-button console" data-name="Console" onclick="toggleConsoleDebug()">
                    📟
                    <span class="debug-badge" id="consoleBadge" style="display: none;">0</span>
                </button>
            </div>
            
            <!-- Notifications Debug -->
            <div class="debug-item">
                <span class="debug-label">Notifications</span>
                <button class="debug-button notifications" data-name="Notifications" onclick="openNotificationsDebug()">
                    🔔
                </button>
            </div>
            
            <!-- PWA Debug -->
            <div class="debug-item">
                <span class="debug-label">PWA Tools</span>
                <button class="debug-button pwa" data-name="PWA" onclick="openPWADebug()">
                    📱
                </button>
            </div>
            
            <!-- Storage Debug -->
            <div class="debug-item">
                <span class="debug-label">Storage</span>
                <button class="debug-button storage" data-name="Storage" onclick="openStorageDebug()">
                    💾
                </button>
            </div>
            
            <!-- Network Debug -->
            <div class="debug-item">
                <span class="debug-label">Network</span>
                <button class="debug-button network" data-name="Network" onclick="openNetworkDebug()">
                    🌐
                </button>
            </div>
        </div>
    </div>

    <script>
        // Toggle menu
        const debugMenu = document.getElementById('debugMenu');
        const debugMainBtn = document.getElementById('debugMainBtn');
        const consoleBadge = document.getElementById('consoleBadge');
        let errorCount = 0;

        debugMainBtn.addEventListener('click', () => {
            debugMenu.classList.toggle('active');
            debugMainBtn.classList.toggle('active');
        });

        // Keyboard shortcut - Ctrl+Shift+D
        document.addEventListener('keydown', (e) => {
            if (e.ctrlKey && e.shiftKey && e.key === 'D') {
                e.preventDefault();
                debugMenu.classList.toggle('active');
                debugMainBtn.classList.toggle('active');
            }
        });

        // Close menu when clicking outside
        document.addEventListener('click', (e) => {
            if (!debugMenu.contains(e.target)) {
                debugMenu.classList.remove('active');
                debugMainBtn.classList.remove('active');
            }
        });

        // Debug Functions
        function toggleConsoleDebug() {
            // אם יש console debug קיים, הצג/הסתר אותו
            const consoleWindow = document.getElementById('console-debug-window');
            if (consoleWindow) {
                if (consoleWindow.style.display === 'none') {
                    consoleWindow.style.display = 'flex';
                } else {
                    consoleWindow.style.display = 'none';
                }
            } else {
                // אם אין, טען את הסקריפט
                const script = document.createElement('script');
                script.src = '../debugs/console-debug.php';
                document.head.appendChild(script);
            }
        }

        function openNotificationsDebug() {
            const width = 900;
            const height = 700;
            const left = (screen.width - width) / 2;
            const top = (screen.height - height) / 2;
            
            window.open(
                '../debugs/notifications-debug.php',
                'NotificationDebugPanel',
                `width=${width},height=${height},left=${left},top=${top},resizable=yes,scrollbars=yes`
            );
        }

        function openPWADebug() {
            // בדוק אם PWA Debug כבר נטען
            if (window.PWADebugPopup) {
                window.PWADebugPopup.show();
            } else {
                // טען את הסקריפט
                fetch('../debugs/pwa-debug-popup.js')
                    .then(r => r.text())
                    .then(eval)
                    .then(() => {
                        if (window.PWADebugPopup) {
                            window.PWADebugPopup.show();
                        }
                    });
            }
        }

        function openStorageDebug() {
            // Storage Debug Modal
            const modal = document.createElement('div');
            modal.style.cssText = `
                position: fixed;
                top: 50%;
                left: 50%;
                transform: translate(-50%, -50%);
                width: 600px;
                max-width: 90%;
                background: #000;
                border: 2px solid #f59e0b;
                border-radius: 10px;
                padding: 20px;
                z-index: 1000000;
                color: #f59e0b;
                font-family: monospace;
            `;
            
            const localStorageData = Object.keys(localStorage).map(key => 
                `<div><strong>${key}:</strong> ${localStorage.getItem(key)}</div>`
            ).join('');
            
            modal.innerHTML = `
                <h2 style="margin-bottom: 20px;">💾 Storage Debug</h2>
                <div style="max-height: 400px; overflow-y: auto; background: #111; padding: 10px; border-radius: 5px;">
                    ${localStorageData || '<p>No data in localStorage</p>'}
                </div>
                <button onclick="this.parentElement.remove()" style="
                    margin-top: 20px;
                    padding: 10px 20px;
                    background: #f59e0b;
                    color: #000;
                    border: none;
                    border-radius: 5px;
                    cursor: pointer;
                    font-weight: bold;
                ">Close</button>
                <button onclick="localStorage.clear(); location.reload();" style="
                    margin-top: 20px;
                    margin-left: 10px;
                    padding: 10px 20px;
                    background: #f00;
                    color: #fff;
                    border: none;
                    border-radius: 5px;
                    cursor: pointer;
                    font-weight: bold;
                ">Clear All</button>
            `;
            
            document.body.appendChild(modal);
        }

        function openNetworkDebug() {
            // Network Monitor
            console.log('%c🌐 Network Debug Active', 'color: #ef4444; font-size: 16px; font-weight: bold;');
            
            // Monitor fetch requests
            const originalFetch = window.fetch;
            window.fetch = function(...args) {
                console.log('%c📡 Fetch:', 'color: #ef4444; font-weight: bold;', args[0]);
                return originalFetch.apply(this, args)
                    .then(response => {
                        console.log('%c✅ Response:', 'color: #10b981;', response.status);
                        return response;
                    })
                    .catch(error => {
                        console.error('%c❌ Error:', 'color: #f00;', error);
                        throw error;
                    });
            };
            
            alert('Network monitoring activated! Check console for requests.');
        }

        // Monitor console errors
        window.addEventListener('error', () => {
            errorCount++;
            consoleBadge.textContent = errorCount;
            consoleBadge.style.display = errorCount > 0 ? 'flex' : 'none';
        });

        // Startup message
        console.log('%c🔧 Debug Menu Ready!', 'background: linear-gradient(135deg, #667eea, #764ba2); color: white; padding: 10px 20px; border-radius: 5px; font-size: 16px; font-weight: bold;');
        console.log('%cPress Ctrl+Shift+D to toggle menu', 'color: #666; font-style: italic;');
    </script>
</body>
</html>