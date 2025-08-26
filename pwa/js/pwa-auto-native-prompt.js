/**
 * PWA Auto Native Prompt
 * מאפשר לבאנר הנייטיב להופיע אוטומטית
 * 
 * חשוב: קובץ זה מאפשר לבאנר הדיפולטיבי של הדפדפן להופיע!
 */

(function() {
    'use strict';

    class PWAAutoNativePrompt {
        constructor() {
            this.promptShown = false;
            this.deferredPrompt = null;
            this.isIOS = /iPad|iPhone|iPod/.test(navigator.userAgent);
            this.isStandalone = window.matchMedia('(display-mode: standalone)').matches || 
                              window.navigator.standalone === true;
            
            // אתחול רק אם לא במצב standalone
            if (!this.isStandalone) {
                this.init();
            }
        }

        init() {
            console.log('PWA Auto Native: Initialized - Browser banner will show automatically');
            
            // דיבוג מפורט
            this.debugInfo();
            
            // האזנה לאירוע - אבל לא מבטלים אותו!
            window.addEventListener('beforeinstallprompt', (e) => {
                console.log('🚀 PWA Auto Native: beforeinstallprompt event fired!');
                console.log('Event object:', e);
                console.log('Platforms:', e.platforms);
                
                // בדיקת דיבוג - האם preventDefault נקרא?
                const originalPreventDefault = e.preventDefault;
                e.preventDefault = function() {
                    console.error('⚠️ WARNING: preventDefault() was called! Banner will NOT show!');
                    console.trace('Called from:');
                    return originalPreventDefault.apply(this, arguments);
                };
                
                // ⚠️ לא עושים preventDefault() - זה מה שמאפשר לבאנר להופיע!
                console.log('✅ NOT calling preventDefault - banner should show automatically');
                
                this.promptShown = true;
                
                // שמור את הפרומפט גלובלית
                this.deferredPrompt = e;
                
                // Chrome דורש פעולת משתמש! צור אזור לחיצה
                setTimeout(() => {
                    console.log('🔍 Checking if prompt was shown...');
                    if (!e.userChoice.resolved) {
                        console.log('📱 Creating click trigger for Chrome requirement...');
                        this.createClickTrigger();
                    }
                }, 2000);
                
                // אפשר לעקוב אחרי התוצאה
                e.userChoice.then((choiceResult) => {
                    console.log('📊 User choice received:', choiceResult);
                    if (choiceResult.outcome === 'accepted') {
                        console.log('PWA Auto Native: User accepted the install');
                        localStorage.setItem('pwa-installed', 'true');
                        this.onInstallSuccess();
                    } else {
                        console.log('PWA Auto Native: User dismissed the install');
                        localStorage.setItem('pwa-prompt-dismissed', Date.now());
                    }
                }).catch(err => {
                    console.error('Error waiting for user choice:', err);
                });
            });

            // האזנה להתקנה מוצלחת
            window.addEventListener('appinstalled', () => {
                console.log('🎉 PWA Auto Native: App was installed!');
                localStorage.setItem('pwa-installed', 'true');
                this.onInstallSuccess();
            });

            // טיפול ב-iOS
            if (this.isIOS) {
                this.handleIOS();
            }
            
            // בדיקה גלובלית למניעת preventDefault
            this.monitorPreventDefault();
        }
        
        // פונקציית דיבוג
        debugInfo() {
            console.group('🔍 PWA Debug Info:');
            console.log('URL:', window.location.href);
            console.log('Protocol:', window.location.protocol);
            console.log('Standalone:', this.isStandalone);
            console.log('iOS:', this.isIOS);
            console.log('User Agent:', navigator.userAgent);
            console.log('Previous dismissal:', localStorage.getItem('pwa-prompt-dismissed'));
            console.log('Already installed:', localStorage.getItem('pwa-installed'));
            console.log('Service Worker:', 'serviceWorker' in navigator);
            console.log('Manifest link exists:', !!document.querySelector('link[rel="manifest"]'));
            console.groupEnd();
        }
        
        // מוניטור גלובלי ל-preventDefault
        monitorPreventDefault() {
            const originalAddEventListener = window.addEventListener;
            window.addEventListener = function(type, listener, options) {
                if (type === 'beforeinstallprompt') {
                    console.warn('⚠️ Another beforeinstallprompt listener detected!');
                    const wrappedListener = function(e) {
                        const originalPreventDefault = e.preventDefault;
                        e.preventDefault = function() {
                            console.error('🚫 preventDefault called by another script!');
                            console.trace();
                            return originalPreventDefault.apply(this, arguments);
                        };
                        return listener.apply(this, arguments);
                    };
                    return originalAddEventListener.call(this, type, wrappedListener, options);
                }
                return originalAddEventListener.apply(this, arguments);
            };
        }

        // טיפול מיוחד ל-iOS
        handleIOS() {
            // ב-iOS אין באנר נייטיב, אז מציגים הוראות
            if (!localStorage.getItem('ios-instructions-shown')) {
                setTimeout(() => {
                    const message = `
📱 להתקנת האפליקציה ב-iPhone/iPad:

1. לחץ על כפתור השיתוף ⬆️
2. בחר "הוסף למסך הבית" ➕
3. לחץ "הוסף"

האפליקציה תופיע במסך הבית!`;
                    
                    if (confirm(message + '\n\nהבנתי, לא להציג שוב?')) {
                        localStorage.setItem('ios-instructions-shown', 'true');
                    }
                }, 10000); // אחרי 10 שניות
            }
        }

        // יצירת טריגר לחיצה (Chrome דורש user gesture)
        createClickTrigger() {
            // אם כבר יש טריגר, צא
            if (document.getElementById('pwa-click-trigger')) return;
            
            const trigger = document.createElement('div');
            trigger.id = 'pwa-click-trigger';
            trigger.innerHTML = `
                <style>
                    #pwa-click-trigger {
                        position: fixed;
                        top: 10px;
                        left: 50%;
                        transform: translateX(-50%);
                        background: linear-gradient(135deg, #667eea, #764ba2);
                        color: white;
                        padding: 12px 24px;
                        border-radius: 30px;
                        box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
                        z-index: 10000;
                        cursor: pointer;
                        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
                        font-size: 14px;
                        font-weight: 600;
                        animation: pulse 2s infinite, slideDown 0.5s ease;
                        display: flex;
                        align-items: center;
                        gap: 8px;
                    }
                    
                    @keyframes pulse {
                        0%, 100% { transform: translateX(-50%) scale(1); }
                        50% { transform: translateX(-50%) scale(1.05); }
                    }
                    
                    @keyframes slideDown {
                        from { 
                            top: -100px;
                            opacity: 0;
                        }
                        to { 
                            top: 10px;
                            opacity: 1;
                        }
                    }
                    
                    #pwa-click-trigger:hover {
                        transform: translateX(-50%) scale(1.08);
                        box-shadow: 0 6px 20px rgba(102, 126, 234, 0.5);
                    }
                    
                    #pwa-click-trigger .icon {
                        font-size: 18px;
                    }
                    
                    @media (max-width: 480px) {
                        #pwa-click-trigger {
                            padding: 10px 20px;
                            font-size: 13px;
                        }
                    }
                </style>
                <span class="icon">📱</span>
                <span>לחץ להתקנת האפליקציה</span>
            `;
            
            document.body.appendChild(trigger);
            
            // הוסף אירוע לחיצה
            trigger.addEventListener('click', async () => {
                console.log('👆 User clicked install trigger');
                
                if (this.deferredPrompt) {
                    try {
                        // עכשיו יש user gesture - אפשר להפעיל!
                        this.deferredPrompt.prompt();
                        console.log('✅ Prompt shown successfully!');
                        
                        // חכה לתשובה
                        const { outcome } = await this.deferredPrompt.userChoice;
                        console.log('User choice:', outcome);
                        
                        // הסר את הכפתור
                        trigger.remove();
                        
                        // נקה את הפרומפט
                        this.deferredPrompt = null;
                        
                    } catch (err) {
                        console.error('Error showing prompt:', err);
                    }
                } else {
                    console.log('No deferred prompt available');
                    trigger.remove();
                }
            });
            
            // הסתר אוטומטית אחרי 30 שניות
            setTimeout(() => {
                if (trigger.parentNode) {
                    trigger.style.animation = 'slideDown 0.5s ease reverse';
                    setTimeout(() => trigger.remove(), 500);
                }
            }, 30000);
        }
        
        // קריאה בהתקנה מוצלחת
        onInstallSuccess() {
            // אפשר להציג הודעת תודה
            console.log('🎉 תודה שהתקנת את האפליקציה!');
            
            // אפשר להוסיף אנליטיקס
            if (typeof gtag !== 'undefined') {
                gtag('event', 'app_installed', {
                    'event_category': 'PWA',
                    'event_label': 'Auto Native Banner'
                });
            }
        }

        // API ציבורי
        
        // בדיקה אם הבאנר הוצג
        wasPromptShown() {
            return this.promptShown;
        }
        
        // בדיקה אם האפליקציה מותקנת
        isInstalled() {
            return localStorage.getItem('pwa-installed') === 'true' || this.isStandalone;
        }
        
        // בדיקה אם הבאנר נדחה לאחרונה
        wasRecentlyDismissed() {
            const dismissed = localStorage.getItem('pwa-prompt-dismissed');
            if (!dismissed) return false;
            
            // בדוק אם עברו פחות מ-7 ימים
            const daysSinceDismissed = (Date.now() - parseInt(dismissed)) / (1000 * 60 * 60 * 24);
            return daysSinceDismissed < 7;
        }
        
        // איפוס (למטרות בדיקה)
        reset() {
            localStorage.removeItem('pwa-installed');
            localStorage.removeItem('pwa-prompt-dismissed');
            localStorage.removeItem('ios-instructions-shown');
            console.log('PWA Auto Native: Reset completed');
        }
    }

    // יצירה אוטומטית
    window.PWAAutoNativePrompt = PWAAutoNativePrompt;
    
    // יצירת instance אוטומטי
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => {
            window.pwaAutoPrompt = new PWAAutoNativePrompt();
        });
    } else {
        window.pwaAutoPrompt = new PWAAutoNativePrompt();
    }
})();