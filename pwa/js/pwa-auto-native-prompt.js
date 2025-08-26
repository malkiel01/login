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
            
            // האזנה לאירוע - אבל לא מבטלים אותו!
            window.addEventListener('beforeinstallprompt', (e) => {
                console.log('PWA Auto Native: Browser install banner is available');
                
                // ⚠️ לא עושים preventDefault() - זה מה שמאפשר לבאנר להופיע!
                // הבאנר הנייטיב יופיע אוטומטית עכשיו
                
                this.promptShown = true;
                
                // אפשר לעקוב אחרי התוצאה
                e.userChoice.then((choiceResult) => {
                    if (choiceResult.outcome === 'accepted') {
                        console.log('PWA Auto Native: User accepted the install');
                        localStorage.setItem('pwa-installed', 'true');
                        this.onInstallSuccess();
                    } else {
                        console.log('PWA Auto Native: User dismissed the install');
                        localStorage.setItem('pwa-prompt-dismissed', Date.now());
                    }
                });
            });

            // האזנה להתקנה מוצלחת
            window.addEventListener('appinstalled', () => {
                console.log('PWA Auto Native: App was installed!');
                localStorage.setItem('pwa-installed', 'true');
                this.onInstallSuccess();
            });

            // טיפול ב-iOS
            if (this.isIOS) {
                this.handleIOS();
            }
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