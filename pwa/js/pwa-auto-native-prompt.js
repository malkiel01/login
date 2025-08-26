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
                
                // נסיון להפעיל ידנית אחרי 2 שניות אם לא הופיע
                setTimeout(() => {
                    console.log('🔍 Checking if prompt was shown...');
                    if (!e.userChoice.resolved) {
                        console.log('⏰ Prompt not shown yet, trying manual trigger...');
                        try {
                            e.prompt();
                            console.log('✅ Manual prompt() called successfully');
                        } catch (err) {
                            console.error('❌ Manual prompt failed:', err.message);
                        }
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