/**
 * PWA Debug Popup Tool
 * כלי דיבאג קופץ לבדיקת באנרי PWA
 * 
 * שימוש:
 * 1. הוסף לכל דף: <script src="/pwa-debug-popup.js"></script>
 * 2. או הרץ בקונסול: fetch('/pwa-debug-popup.js').then(r=>r.text()).then(eval)
 * 3. לחץ על הכפתור הצף או הקש Ctrl+Shift+D
 */

(function() {
    'use strict';

    // מנע טעינה כפולה
    if (window.PWADebugPopup) {
        console.log('PWA Debug Popup already loaded');
        window.PWADebugPopup.show();
        return;
    }

    class PWADebugPopup {
        constructor() {
            this.isVisible = false;
            this.consoleBuffer = [];
            this.originalConsole = {};
            this.config = {
                title: 'התקן את האפליקציה! 🚀',
                subtitle: 'גישה מהירה, עבודה אופליין והתראות חכמות',
                showAfterSeconds: 3,
                minimumVisits: 2
            };
            
            this.init();
        }

        init() {
            this.injectStyles();
            this.createPopup();
            // this.createFloatingButton();
            this.attachEventListeners();
            this.interceptConsole();
            this.startMonitoring();
            
            console.log('%c🧪 PWA Debug Popup Ready!', 'background: #667eea; color: white; padding: 5px 10px; border-radius: 3px; font-size: 14px;');
            console.log('%cPress Ctrl+Shift+D to toggle', 'color: #666; font-style: italic;');
        }

        injectStyles() {
            if (document.getElementById('pwa-debug-styles')) return;
            
            const styles = document.createElement('style');
            styles.id = 'pwa-debug-styles';
            styles.innerHTML = `
                #pwa-debug-popup {
                    position: fixed;
                    top: 50%;
                    left: 50%;
                    transform: translate(-50%, -50%) scale(0);
                    width: 90%;
                    max-width: 900px;
                    height: 80%;
                    max-height: 600px;
                    background: white;
                    border-radius: 16px;
                    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
                    z-index: 999999;
                    display: none;
                    flex-direction: column;
                    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
                    direction: rtl;
                    transition: all 0.3s cubic-bezier(0.68, -0.55, 0.265, 1.55);
                }

                #pwa-debug-popup.show {
                    display: flex;
                    transform: translate(-50%, -50%) scale(1);
                }

                .pwa-debug-header {
                    background: linear-gradient(135deg, #667eea, #764ba2);
                    color: white;
                    padding: 20px;
                    border-radius: 16px 16px 0 0;
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                    flex-shrink: 0;
                }

                .pwa-debug-title {
                    font-size: 20px;
                    font-weight: 600;
                    display: flex;
                    align-items: center;
                    gap: 10px;
                }

                .pwa-debug-close {
                    background: rgba(255, 255, 255, 0.2);
                    border: none;
                    color: white;
                    width: 32px;
                    height: 32px;
                    border-radius: 50%;
                    cursor: pointer;
                    font-size: 18px;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    transition: all 0.3s;
                }

                .pwa-debug-close:hover {
                    background: rgba(255, 255, 255, 0.3);
                    transform: rotate(90deg);
                }

                .pwa-debug-body {
                    flex: 1;
                    overflow: auto;
                    padding: 20px;
                }

                .pwa-debug-tabs {
                    display: flex;
                    gap: 10px;
                    margin-bottom: 20px;
                    border-bottom: 2px solid #e5e7eb;
                    padding-bottom: 10px;
                }

                .pwa-debug-tab {
                    padding: 8px 16px;
                    background: none;
                    border: none;
                    color: #6b7280;
                    font-size: 14px;
                    font-weight: 600;
                    cursor: pointer;
                    position: relative;
                    transition: all 0.3s;
                }

                .pwa-debug-tab.active {
                    color: #667eea;
                }

                .pwa-debug-tab.active::after {
                    content: '';
                    position: absolute;
                    bottom: -12px;
                    left: 0;
                    right: 0;
                    height: 2px;
                    background: #667eea;
                }

                .pwa-debug-tab-content {
                    display: none;
                }

                .pwa-debug-tab-content.active {
                    display: block;
                    animation: fadeIn 0.3s ease;
                }

                @keyframes fadeIn {
                    from { opacity: 0; }
                    to { opacity: 1; }
                }

                .pwa-debug-section {
                    background: #f3f4f6;
                    border-radius: 8px;
                    padding: 15px;
                    margin-bottom: 15px;
                }

                .pwa-debug-section-title {
                    font-size: 14px;
                    font-weight: 600;
                    color: #4b5563;
                    margin-bottom: 10px;
                }

                .pwa-debug-grid {
                    display: grid;
                    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
                    gap: 10px;
                }

                .pwa-debug-btn {
                    padding: 10px 16px;
                    border: none;
                    border-radius: 8px;
                    font-size: 14px;
                    font-weight: 600;
                    cursor: pointer;
                    transition: all 0.3s;
                    color: white;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    gap: 6px;
                }

                .pwa-debug-btn:hover {
                    transform: translateY(-2px);
                    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
                }

                .pwa-debug-btn-primary {
                    background: linear-gradient(135deg, #667eea, #764ba2);
                }

                .pwa-debug-btn-success {
                    background: linear-gradient(135deg, #10b981, #059669);
                }

                .pwa-debug-btn-warning {
                    background: linear-gradient(135deg, #f59e0b, #d97706);
                }

                .pwa-debug-btn-danger {
                    background: linear-gradient(135deg, #ef4444, #dc2626);
                }

                .pwa-debug-btn-info {
                    background: linear-gradient(135deg, #3b82f6, #2563eb);
                }

                .pwa-debug-status {
                    display: flex;
                    justify-content: space-between;
                    padding: 8px 0;
                    border-bottom: 1px solid #e5e7eb;
                }

                .pwa-debug-status:last-child {
                    border-bottom: none;
                }

                .pwa-debug-status-label {
                    font-size: 13px;
                    color: #6b7280;
                }

                .pwa-debug-status-value {
                    font-size: 13px;
                    font-weight: 600;
                }

                .pwa-debug-status-value.success {
                    color: #10b981;
                }

                .pwa-debug-status-value.error {
                    color: #ef4444;
                }

                .pwa-debug-status-value.warning {
                    color: #f59e0b;
                }

                .pwa-debug-console {
                    background: #1a1a1a;
                    color: #10b981;
                    font-family: 'Courier New', monospace;
                    font-size: 12px;
                    padding: 10px;
                    border-radius: 6px;
                    height: 200px;
                    overflow-y: auto;
                }

                .pwa-debug-console-line {
                    margin-bottom: 4px;
                    display: flex;
                    gap: 8px;
                }

                .pwa-debug-console-time {
                    color: #666;
                }

                .pwa-debug-console-type {
                    font-weight: bold;
                }

                .pwa-debug-console-type.log { color: #fff; }
                .pwa-debug-console-type.info { color: #3b82f6; }
                .pwa-debug-console-type.success { color: #10b981; }
                .pwa-debug-console-type.warning { color: #f59e0b; }
                .pwa-debug-console-type.error { color: #ef4444; }

                .pwa-debug-float-btn {
                    position: fixed;
                    bottom: 20px;
                    right: 20px;
                    width: 56px;
                    height: 56px;
                    border-radius: 50%;
                    background: linear-gradient(135deg, #667eea, #764ba2);
                    color: white;
                    border: none;
                    font-size: 24px;
                    cursor: pointer;
                    box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
                    z-index: 999998;
                    transition: all 0.3s;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                }

                .pwa-debug-float-btn:hover {
                    transform: scale(1.1);
                    box-shadow: 0 6px 20px rgba(102, 126, 234, 0.5);
                }

                .pwa-debug-float-btn.pulse {
                    animation: pulse 2s infinite;
                }

                @keyframes pulse {
                    0%, 100% { transform: scale(1); }
                    50% { transform: scale(1.05); }
                }

                .pwa-debug-config-input {
                    width: 100%;
                    padding: 8px 12px;
                    border: 1px solid #d1d5db;
                    border-radius: 6px;
                    font-size: 13px;
                    margin-bottom: 10px;
                }

                .pwa-debug-config-input:focus {
                    outline: none;
                    border-color: #667eea;
                    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
                }

                .pwa-debug-alert {
                    padding: 10px;
                    border-radius: 6px;
                    margin-bottom: 10px;
                    font-size: 13px;
                    display: flex;
                    align-items: center;
                    gap: 8px;
                }

                .pwa-debug-alert-info {
                    background: #dbeafe;
                    color: #1e40af;
                }

                .pwa-debug-alert-warning {
                    background: #fef3c7;
                    color: #92400e;
                }

                .pwa-debug-minimize {
                    background: rgba(255, 255, 255, 0.2);
                    border: none;
                    color: white;
                    padding: 4px 8px;
                    border-radius: 4px;
                    cursor: pointer;
                    font-size: 12px;
                    margin-left: 10px;
                    transition: all 0.3s;
                }

                .pwa-debug-minimize:hover {
                    background: rgba(255, 255, 255, 0.3);
                }

                @media (max-width: 768px) {
                    #pwa-debug-popup {
                        width: 95%;
                        height: 90%;
                    }
                    
                    .pwa-debug-grid {
                        grid-template-columns: 1fr;
                    }
                }
            `;
            document.head.appendChild(styles);
        }

        createPopup() {
            const popup = document.createElement('div');
            popup.id = 'pwa-debug-popup';
            popup.innerHTML = `
                <div class="pwa-debug-header">
                    <div class="pwa-debug-title">
                        <span>🧪</span>
                        <span>PWA Debug Tool</span>
                    </div>
                    <div>
                        <button class="pwa-debug-minimize" onclick="PWADebugPopup.minimize()">_</button>
                        <button class="pwa-debug-close" onclick="PWADebugPopup.hide()">✕</button>
                    </div>
                </div>
                
                <div class="pwa-debug-body">
                    <div class="pwa-debug-tabs">
                        <button class="pwa-debug-tab active" data-tab="status">📊 סטטוס</button>
                        <button class="pwa-debug-tab" data-tab="native">🎯 באנר נייטיב</button>
                        <button class="pwa-debug-tab" data-tab="custom">🎨 באנר מותאם</button>
                        <button class="pwa-debug-tab" data-tab="data">🗄️ נתונים</button>
                        <button class="pwa-debug-tab" data-tab="console">📋 קונסול</button>
                    </div>
                    
                    <!-- Status Tab -->
                    <div class="pwa-debug-tab-content active" data-tab-content="status">
                        <div class="pwa-debug-section">
                            <div class="pwa-debug-section-title">מצב מערכת</div>
                            <div class="pwa-debug-status">
                                <span class="pwa-debug-status-label">אפליקציה:</span>
                                <span id="debug-app-status" class="pwa-debug-status-value">בודק...</span>
                            </div>
                            <div class="pwa-debug-status">
                                <span class="pwa-debug-status-label">Service Worker:</span>
                                <span id="debug-sw-status" class="pwa-debug-status-value">בודק...</span>
                            </div>
                            <div class="pwa-debug-status">
                                <span class="pwa-debug-status-label">פלטפורמה:</span>
                                <span id="debug-platform" class="pwa-debug-status-value">בודק...</span>
                            </div>
                            <div class="pwa-debug-status">
                                <span class="pwa-debug-status-label">דפדפן:</span>
                                <span id="debug-browser" class="pwa-debug-status-value">בודק...</span>
                            </div>
                            <div class="pwa-debug-status">
                                <span class="pwa-debug-status-label">ביקורים:</span>
                                <span id="debug-visits" class="pwa-debug-status-value">0</span>
                            </div>
                            <div class="pwa-debug-status">
                                <span class="pwa-debug-status-label">באנר:</span>
                                <span id="debug-banner-status" class="pwa-debug-status-value">לא פעיל</span>
                            </div>
                        </div>
                        
                        <div class="pwa-debug-alert pwa-debug-alert-info">
                            <span>💡</span>
                            <span>לחץ Ctrl+Shift+D לפתיחה/סגירה מהירה</span>
                        </div>
                    </div>
                    
                    <!-- Native Banner Tab -->
                    <div class="pwa-debug-tab-content" data-tab-content="native">
                        <div class="pwa-debug-section">
                            <div class="pwa-debug-section-title">בדיקות באנר נייטיב</div>
                            <div class="pwa-debug-grid">
                                <button class="pwa-debug-btn pwa-debug-btn-success" onclick="PWADebugPopup.testNative()">
                                    <span>🎯</span> הצג באנר נייטיב
                                </button>
                                <button class="pwa-debug-btn pwa-debug-btn-info" onclick="PWADebugPopup.checkSupport()">
                                    <span>🔍</span> בדוק תמיכה
                                </button>
                                <button class="pwa-debug-btn pwa-debug-btn-warning" onclick="PWADebugPopup.simulatePrompt()">
                                    <span>⚡</span> סימולציה
                                </button>
                            </div>
                        </div>
                        
                        <div class="pwa-debug-alert pwa-debug-alert-warning">
                            <span>⚠️</span>
                            <span>הבאנר הנייטיב עובד רק ב-Chrome, Edge ו-Samsung Browser</span>
                        </div>
                    </div>
                    
                    <!-- Custom Banner Tab -->
                    <div class="pwa-debug-tab-content" data-tab-content="custom">
                        <div class="pwa-debug-section">
                            <div class="pwa-debug-section-title">הגדרות באנר מותאם</div>
                            <input type="text" id="debug-config-title" class="pwa-debug-config-input" 
                                   placeholder="כותרת" value="${this.config.title}">
                            <input type="text" id="debug-config-subtitle" class="pwa-debug-config-input" 
                                   placeholder="תיאור" value="${this.config.subtitle}">
                            <input type="number" id="debug-config-delay" class="pwa-debug-config-input" 
                                   placeholder="השהייה (שניות)" value="${this.config.showAfterSeconds}" min="0" max="60">
                        </div>
                        
                        <div class="pwa-debug-section">
                            <div class="pwa-debug-section-title">בדיקות באנר מותאם</div>
                            <div class="pwa-debug-grid">
                                <button class="pwa-debug-btn pwa-debug-btn-primary" onclick="PWADebugPopup.testCustom()">
                                    <span>🎨</span> הצג מיידי
                                </button>
                                <button class="pwa-debug-btn pwa-debug-btn-warning" onclick="PWADebugPopup.testCustomDelayed()">
                                    <span>⏰</span> הצג עם השהייה
                                </button>
                                <button class="pwa-debug-btn pwa-debug-btn-info" onclick="PWADebugPopup.testIOSModal()">
                                    <span>📱</span> מודל iOS
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Data Tab -->
                    <div class="pwa-debug-tab-content" data-tab-content="data">
                        <div class="pwa-debug-section">
                            <div class="pwa-debug-section-title">ניהול נתונים</div>
                            <div class="pwa-debug-grid">
                                <button class="pwa-debug-btn pwa-debug-btn-danger" onclick="PWADebugPopup.clearAll()">
                                    <span>🗑️</span> נקה הכל
                                </button>
                                <button class="pwa-debug-btn pwa-debug-btn-warning" onclick="PWADebugPopup.resetVisits()">
                                    <span>🔄</span> אפס ביקורים
                                </button>
                                <button class="pwa-debug-btn pwa-debug-btn-info" onclick="PWADebugPopup.resetDismiss()">
                                    <span>↩️</span> אפס דחיות
                                </button>
                                <button class="pwa-debug-btn pwa-debug-btn-success" onclick="PWADebugPopup.exportData()">
                                    <span>📤</span> ייצא נתונים
                                </button>
                            </div>
                        </div>
                        
                        <div class="pwa-debug-section">
                            <div class="pwa-debug-section-title">LocalStorage</div>
                            <div id="debug-localstorage" style="font-family: monospace; font-size: 11px; color: #4b5563;">
                                <!-- יתעדכן דינמית -->
                            </div>
                        </div>
                    </div>
                    
                    <!-- Console Tab -->
                    <div class="pwa-debug-tab-content" data-tab-content="console">
                        <div class="pwa-debug-section">
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                                <div class="pwa-debug-section-title" style="margin: 0;">Console Output</div>
                                <button class="pwa-debug-btn pwa-debug-btn-info" style="padding: 6px 12px;" onclick="PWADebugPopup.clearConsole()">
                                    <span>🧹</span> נקה
                                </button>
                            </div>
                            <div id="debug-console" class="pwa-debug-console">
                                <!-- Console logs יוזנו כאן -->
                            </div>
                        </div>
                    </div>
                </div>
            `;
            document.body.appendChild(popup);
            this.popup = popup;
        }

        createFloatingButton() {
            const button = document.createElement('button');
            button.className = 'pwa-debug-float-btn';
            button.innerHTML = '🧪';
            button.onclick = () => this.toggle();
            button.title = 'PWA Debug Tool (Ctrl+Shift+D)';
            document.body.appendChild(button);
            this.floatButton = button;
        }

        attachEventListeners() {
            // Tab switching
            this.popup.querySelectorAll('.pwa-debug-tab').forEach(tab => {
                tab.addEventListener('click', (e) => {
                    const tabName = e.target.dataset.tab;
                    this.switchTab(tabName);
                });
            });

            // Keyboard shortcut
            document.addEventListener('keydown', (e) => {
                if (e.ctrlKey && e.shiftKey && e.key === 'D') {
                    e.preventDefault();
                    this.toggle();
                }
            });

            // Listen for install prompt
            window.addEventListener('beforeinstallprompt', (e) => {
                e.preventDefault();
                this.deferredPrompt = e;
                this.log('Install prompt captured', 'success');
                this.updateStatus('debug-banner-status', 'מוכן להצגה', 'success');
            });

            // Listen for app installed
            window.addEventListener('appinstalled', () => {
                this.log('PWA was installed!', 'success');
                this.updateStatus('debug-app-status', 'מותקנת', 'success');
            });
        }

        switchTab(tabName) {
            // Update tabs
            this.popup.querySelectorAll('.pwa-debug-tab').forEach(tab => {
                tab.classList.toggle('active', tab.dataset.tab === tabName);
            });
            
            // Update content
            this.popup.querySelectorAll('.pwa-debug-tab-content').forEach(content => {
                content.classList.toggle('active', content.dataset.tabContent === tabName);
            });
        }

        interceptConsole() {
            const methods = ['log', 'info', 'warn', 'error'];
            methods.forEach(method => {
                this.originalConsole[method] = console[method];
                console[method] = (...args) => {
                    this.originalConsole[method](...args);
                    this.log(args.join(' '), method === 'warn' ? 'warning' : method);
                };
            });
        }

        log(message, type = 'log') {
            const time = new Date().toLocaleTimeString('he-IL');
            this.consoleBuffer.push({ time, type, message });
            
            if (this.consoleBuffer.length > 100) {
                this.consoleBuffer.shift();
            }
            
            this.updateConsoleDisplay();
        }

        updateConsoleDisplay() {
            const consoleEl = document.getElementById('debug-console');
            if (!consoleEl) return;
            
            const html = this.consoleBuffer.map(entry => `
                <div class="pwa-debug-console-line">
                    <span class="pwa-debug-console-time">[${entry.time}]</span>
                    <span class="pwa-debug-console-type ${entry.type}">${entry.type.toUpperCase()}:</span>
                    <span>${entry.message}</span>
                </div>
            `).join('');
            
            consoleEl.innerHTML = html;
            consoleEl.scrollTop = consoleEl.scrollHeight;
        }

        startMonitoring() {
            this.updateSystemStatus();
            this.updateLocalStorageDisplay();
            
            setInterval(() => {
                this.updateSystemStatus();
                this.updateLocalStorageDisplay();
            }, 2000);
        }

        updateSystemStatus() {
            // App status
            const isStandalone = window.matchMedia('(display-mode: standalone)').matches;
            const isInstalled = localStorage.getItem('pwa-installed') === 'true';
            
            if (isStandalone) {
                this.updateStatus('debug-app-status', 'מותקנת (Standalone)', 'success');
            } else if (isInstalled) {
                this.updateStatus('debug-app-status', 'מותקנת', 'success');
            } else {
                this.updateStatus('debug-app-status', 'לא מותקנת', 'error');
            }
            
            // Service Worker
            if ('serviceWorker' in navigator) {
                navigator.serviceWorker.ready.then(() => {
                    this.updateStatus('debug-sw-status', 'פעיל', 'success');
                });
            } else {
                this.updateStatus('debug-sw-status', 'לא נתמך', 'error');
            }
            
            // Platform
            const isIOS = /iPad|iPhone|iPod/.test(navigator.userAgent);
            const isAndroid = /Android/.test(navigator.userAgent);
            
            if (isIOS) {
                this.updateStatus('debug-platform', 'iOS', 'warning');
            } else if (isAndroid) {
                this.updateStatus('debug-platform', 'Android', 'success');
            } else {
                this.updateStatus('debug-platform', 'Desktop', 'success');
            }
            
            // Browser
            const browser = this.getBrowserInfo();
            this.updateStatus('debug-browser', browser, browser.includes('Chrome') || browser.includes('Edge') ? 'success' : 'warning');
            
            // Visits
            const visits = localStorage.getItem('pwa-visit-count') || '0';
            this.updateStatus('debug-visits', visits);
            
            // Banner status
            const dismissed = localStorage.getItem('pwa-custom-dismissed');
            if (dismissed === 'permanent') {
                this.updateStatus('debug-banner-status', 'נדחה לצמיתות', 'error');
            } else if (dismissed && !isNaN(dismissed)) {
                const date = new Date(parseInt(dismissed));
                this.updateStatus('debug-banner-status', `נדחה עד ${date.toLocaleDateString('he-IL')}`, 'warning');
            } else if (this.deferredPrompt) {
                this.updateStatus('debug-banner-status', 'מוכן להצגה', 'success');
            } else {
                this.updateStatus('debug-banner-status', 'לא זמין');
            }
        }

        getBrowserInfo() {
            const ua = navigator.userAgent;
            if (ua.indexOf('Chrome') > -1 && ua.indexOf('Edg') === -1) return 'Chrome';
            if (ua.indexOf('Safari') > -1 && ua.indexOf('Chrome') === -1) return 'Safari';
            if (ua.indexOf('Firefox') > -1) return 'Firefox';
            if (ua.indexOf('Edg') > -1) return 'Edge';
            if (ua.indexOf('Samsung') > -1) return 'Samsung';
            return 'Unknown';
        }

        updateStatus(id, value, type = 'default') {
            const element = document.getElementById(id);
            if (!element) return;
            
            element.textContent = value;
            element.className = 'pwa-debug-status-value';
            if (type !== 'default') {
                element.classList.add(type);
            }
        }

        updateLocalStorageDisplay() {
            const display = document.getElementById('debug-localstorage');
            if (!display) return;
            
            const pwaKeys = Object.keys(localStorage).filter(key => 
                key.includes('pwa') || key.includes('ios')
            );
            
            if (pwaKeys.length === 0) {
                display.innerHTML = '<span style="color: #9ca3af;">אין נתוני PWA</span>';
                return;
            }
            
            const html = pwaKeys.map(key => {
                const value = localStorage.getItem(key);
                return `<div style="margin-bottom: 5px;"><strong>${key}:</strong> ${value}</div>`;
            }).join('');
            
            display.innerHTML = html;
        }

        // Public Methods
        show() {
            this.popup.classList.add('show');
            this.isVisible = true;
            this.floatButton.style.display = 'none';
        }

        hide() {
            this.popup.classList.remove('show');
            this.isVisible = false;
            this.floatButton.style.display = 'flex';
        }

        toggle() {
            if (this.isVisible) {
                this.hide();
            } else {
                this.show();
            }
        }

        minimize() {
            this.hide();
            this.floatButton.classList.add('pulse');
            setTimeout(() => {
                this.floatButton.classList.remove('pulse');
            }, 3000);
        }

        // Test Methods
        testNative() {
            this.log('Testing native banner...', 'info');
            
            if (this.deferredPrompt) {
                this.deferredPrompt.prompt();
                this.deferredPrompt.userChoice.then(result => {
                    if (result.outcome === 'accepted') {
                        this.log('User accepted the install prompt', 'success');
                    } else {
                        this.log('User dismissed the install prompt', 'warning');
                    }
                    this.deferredPrompt = null;
                });
            } else {
                this.log('No install prompt available', 'warning');
                alert('אין אפשרות התקנה זמינה. נסה לרענן את הדף.');
            }
        }

        checkSupport() {
            this.log('Checking PWA support...', 'info');
            
            const checks = {
                'Service Worker': 'serviceWorker' in navigator,
                'Install Prompt': 'BeforeInstallPromptEvent' in window,
                'Manifest': document.querySelector('link[rel="manifest"]') !== null,
                'HTTPS': location.protocol === 'https:' || location.hostname === 'localhost'
            };
            
            Object.entries(checks).forEach(([feature, supported]) => {
                this.log(`${feature}: ${supported ? '✅' : '❌'}`, supported ? 'success' : 'error');
            });
        }

        simulatePrompt() {
            this.log('Simulating install prompt...', 'warning');
            
            const event = new Event('beforeinstallprompt');
            event.prompt = () => {
                this.log('Simulated prompt triggered', 'info');
                return Promise.resolve();
            };
            event.userChoice = Promise.resolve({ outcome: 'accepted' });
            
            window.dispatchEvent(event);
            this.deferredPrompt = event;
            this.updateStatus('debug-banner-status', 'סימולציה פעילה', 'warning');
        }

        testCustom() {
            this.updateConfig();
            this.log('Loading custom banner...', 'info');
            
            if (!window.PWAInstallManager) {
                const script = document.createElement('script');
                script.src = '/pwa/js/pwa-install-manager.js';
                script.onload = () => {
                    this.log('Custom banner script loaded', 'success');
                    window.pwaInstallManager = new PWAInstallManager(this.config);
                    window.pwaInstallManager.forceShow();
                };
                document.head.appendChild(script);
            } else {
                if (window.pwaInstallManager) {
                    Object.assign(window.pwaInstallManager.config, this.config);
                    window.pwaInstallManager.forceShow();
                } else {
                    window.pwaInstallManager = new PWAInstallManager(this.config);
                    window.pwaInstallManager.forceShow();
                }
                this.log('Custom banner displayed', 'success');
            }
        }

        testCustomDelayed() {
            this.updateConfig();
            const delay = this.config.showAfterSeconds * 1000;
            this.log(`Showing custom banner in ${this.config.showAfterSeconds} seconds...`, 'info');
            
            setTimeout(() => {
                this.testCustom();
            }, delay);
        }

        testIOSModal() {
            this.log('Testing iOS modal...', 'info');
            
            if (!window.PWAInstallManager) {
                this.testCustom();
                setTimeout(() => {
                    if (window.pwaInstallManager && window.pwaInstallManager.iosModal) {
                        window.pwaInstallManager.iosModal.classList.add('show');
                        this.log('iOS modal displayed', 'success');
                    }
                }, 500);
            } else {
                if (window.pwaInstallManager && window.pwaInstallManager.iosModal) {
                    window.pwaInstallManager.iosModal.classList.add('show');
                    this.log('iOS modal displayed', 'success');
                }
            }
        }

        updateConfig() {
            this.config.title = document.getElementById('debug-config-title').value;
            this.config.subtitle = document.getElementById('debug-config-subtitle').value;
            this.config.showAfterSeconds = parseInt(document.getElementById('debug-config-delay').value);
        }

        clearAll() {
            if (!confirm('האם לנקות את כל נתוני ה-PWA?')) return;
            
            const keysToRemove = [
                'pwa-installed',
                'pwa-install-accepted', 
                'pwa-install-dismissed',
                'pwa-custom-dismissed',
                'pwa-visit-count',
                'ios-instructions-shown',
                'ios-prompt-dismissed',
                'ios-prompt-shown'
            ];
            
            keysToRemove.forEach(key => localStorage.removeItem(key));
            
            this.log('All PWA data cleared', 'success');
            this.updateSystemStatus();
            this.updateLocalStorageDisplay();
        }

        resetVisits() {
            localStorage.setItem('pwa-visit-count', '0');
            this.log('Visit counter reset', 'success');
            this.updateSystemStatus();
            this.updateLocalStorageDisplay();
        }

        resetDismiss() {
            localStorage.removeItem('pwa-custom-dismissed');
            localStorage.removeItem('pwa-install-dismissed');
            localStorage.removeItem('ios-prompt-dismissed');
            this.log('Dismissals reset', 'success');
            this.updateSystemStatus();
            this.updateLocalStorageDisplay();
        }

        clearConsole() {
            this.consoleBuffer = [];
            this.updateConsoleDisplay();
            this.log('Console cleared', 'info');
        }

        exportData() {
            const data = {
                timestamp: new Date().toISOString(),
                userAgent: navigator.userAgent,
                platform: navigator.platform,
                localStorage: {},
                features: {
                    serviceWorker: 'serviceWorker' in navigator,
                    notification: 'Notification' in window,
                    push: 'PushManager' in window,
                    installPrompt: 'BeforeInstallPromptEvent' in window
                },
                console: this.consoleBuffer
            };
            
            Object.keys(localStorage).forEach(key => {
                data.localStorage[key] = localStorage.getItem(key);
            });
            
            const json = JSON.stringify(data, null, 2);
            const blob = new Blob([json], { type: 'application/json' });
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `pwa-debug-${new Date().toISOString()}.json`;
            a.click();
            URL.revokeObjectURL(url);
            
            this.log('Debug data exported', 'success');
        }
    }

    // Create and expose instance
    window.PWADebugPopup = new PWADebugPopup();
    
    // Static methods for inline onclick handlers
    PWADebugPopup.show = () => window.PWADebugPopup.show();
    PWADebugPopup.hide = () => window.PWADebugPopup.hide();
    PWADebugPopup.minimize = () => window.PWADebugPopup.minimize();
    PWADebugPopup.testNative = () => window.PWADebugPopup.testNative();
    PWADebugPopup.checkSupport = () => window.PWADebugPopup.checkSupport();
    PWADebugPopup.simulatePrompt = () => window.PWADebugPopup.simulatePrompt();
    PWADebugPopup.testCustom = () => window.PWADebugPopup.testCustom();
    PWADebugPopup.testCustomDelayed = () => window.PWADebugPopup.testCustomDelayed();
    PWADebugPopup.testIOSModal = () => window.PWADebugPopup.testIOSModal();
    PWADebugPopup.clearAll = () => window.PWADebugPopup.clearAll();
    PWADebugPopup.resetVisits = () => window.PWADebugPopup.resetVisits();
    PWADebugPopup.resetDismiss = () => window.PWADebugPopup.resetDismiss();
    PWADebugPopup.exportData = () => window.PWADebugPopup.exportData();
    PWADebugPopup.clearConsole = () => window.PWADebugPopup.clearConsole();
})();