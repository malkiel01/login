/**
 * PWA Native Prompt Manager - Fixed Version
 * מנהל התקנת PWA עם באנר נייטיב של הדפדפן
 * 
 * גרסה מתוקנת שבאמת מציגה את הבאנר הנייטיב
 */

(function() {
    'use strict';

    class PWANativePrompt {
        constructor() {
            this.deferredPrompt = null;
            this.isIOS = /iPad|iPhone|iPod/.test(navigator.userAgent);
            this.isStandalone = window.matchMedia('(display-mode: standalone)').matches || 
                              window.navigator.standalone === true;
            
            // הגדרות ניתנות להתאמה
            this.config = {
                showDelay: 3000,  // השהייה לפני הצגת הבאנר (ms)
                autoShow: true     // הצג אוטומטית או חכה לטריגר ידני
            };
            
            // אתחול רק אם לא מותקן
            if (!this.isStandalone) {
                this.init();
            }
        }

        init() {
            // האזנה לאירוע beforeinstallprompt
            window.addEventListener('beforeinstallprompt', (e) => {
                console.log('PWA: Native install prompt available');
                
                // תמיד שמור את האירוע
                this.deferredPrompt = e;
                
                // אפשרות 1: תן לדפדפן להציג מיד (אם הוא רוצה)
                if (!this.config.preventAutoShow) {
                    // לא עושים preventDefault - הדפדפן יציג אם ירצה
                    console.log('PWA: Browser may show native banner');
                    return;
                }
                
                // אפשרות 2: מנע הצגה אוטומטית והוסף כפתור/טריגר
                e.preventDefault();
                console.log('PWA: Native banner prevented, waiting for user action');
                
                // יצירת כפתור התקנה צף
                this.createFloatingButton();
            });

            // האזנה להתקנה מוצלחת
            window.addEventListener('appinstalled', () => {
                console.log('PWA: App was installed successfully');
                this.deferredPrompt = null;
                
                // שמור סטטוס
                localStorage.setItem('pwa-installed', 'true');
                
                // הצג הודעת תודה
                this.showThankYouMessage();
            });

            // בדיקה ל-iOS
            if (this.isIOS && !this.isStandalone) {
                this.checkIOSPrompt();
            }

            // הוסף כפתורי התקנה בדף (אם יש)
            this.setupInstallButtons();
        }

        // הצגת הבאנר הנייטיב
        async showPrompt() {
            if (!this.deferredPrompt) {
                console.log('PWA: No installation prompt available');
                
                // אם אין באנר נייטיב זמין, בדוק אם iOS
                if (this.isIOS) {
                    this.showIOSInstructions();
                }
                return false;
            }

            try {
                // הצג את הבאנר הנייטיב של הדפדפן
                console.log('PWA: Showing native install prompt');
                this.deferredPrompt.prompt();
                
                // חכה לתגובת המשתמש
                const { outcome } = await this.deferredPrompt.userChoice;
                
                console.log(`PWA: User response - ${outcome}`);
                
                if (outcome === 'accepted') {
                    console.log('PWA: User accepted installation');
                    localStorage.setItem('pwa-install-accepted', 'true');
                } else {
                    console.log('PWA: User dismissed installation');
                    localStorage.setItem('pwa-install-dismissed', Date.now().toString());
                }
                
                // נקה את הרפרנס
                this.deferredPrompt = null;
                
                return outcome === 'accepted';
            } catch (error) {
                console.error('PWA: Error showing prompt', error);
                return false;
            }
        }

        // בדיקה והצגת הנחיות ל-iOS
        checkIOSPrompt() {
            // בדוק אם כבר הוצג או המשתמש דחה
            const dismissed = localStorage.getItem('ios-prompt-dismissed');
            const installed = localStorage.getItem('pwa-installed');
            
            if (!dismissed && !installed) {
                // הצג הודעה אחרי השהייה
                setTimeout(() => {
                    this.showIOSInstructions();
                }, this.config.showDelay);
            }
        }

        // הצגת הוראות התקנה ל-iOS
        showIOSInstructions() {
            const message = `
📱 להוספת האפליקציה למסך הבית:

1. לחץ על כפתור השיתוף ⬆️ בתחתית המסך
2. גלול למטה ובחר "הוסף למסך הבית" ➕
3. לחץ על "הוסף" בפינה הימנית העליונה

האפליקציה תהיה זמינה גם בלי חיבור לאינטרנט!
            `;
            
            if (confirm(message + '\n\nלחץ אישור אם הבנת, או ביטול להסתיר לצמיתות')) {
                localStorage.setItem('ios-prompt-shown', Date.now().toString());
            } else {
                localStorage.setItem('ios-prompt-dismissed', 'true');
            }
        }

        // הגדרת כפתורי התקנה בדף
        setupInstallButtons() {
            // מצא כל הכפתורים עם המחלקה המתאימה
            const installButtons = document.querySelectorAll('.pwa-install-trigger, .install-pwa-btn, [data-pwa-install]');
            
            installButtons.forEach(button => {
                // הסתר אם כבר מותקן
                if (this.isStandalone || localStorage.getItem('pwa-installed') === 'true') {
                    button.style.display = 'none';
                    return;
                }

                // הצג את הכפתור
                button.style.display = 'inline-block';
                
                // הוסף אירוע לחיצה
                button.addEventListener('click', async (e) => {
                    e.preventDefault();
                    
                    if (this.isIOS) {
                        this.showIOSInstructions();
                    } else if (this.deferredPrompt) {
                        const installed = await this.showPrompt();
                        if (installed) {
                            button.style.display = 'none';
                        }
                    } else {
                        // אין באנר זמין - הצג הוראות ידניות
                        this.showManualInstructions();
                    }
                });
            });
        }

        // יצירת כפתור צף להתקנה
        createFloatingButton() {
            // בדוק אם כבר יש כפתור
            if (document.getElementById('pwa-native-install-btn')) {
                return;
            }
            
            const button = document.createElement('button');
            button.id = 'pwa-native-install-btn';
            button.innerHTML = '📱 התקן אפליקציה';
            button.style.cssText = `
                position: fixed;
                bottom: 20px;
                right: 20px;
                background: linear-gradient(135deg, #667eea, #764ba2);
                color: white;
                border: none;
                padding: 12px 24px;
                border-radius: 50px;
                font-size: 16px;
                font-weight: 600;
                cursor: pointer;
                box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
                z-index: 9999;
                animation: pwa-bounce 2s infinite;
                transition: all 0.3s ease;
            `;
            
            // הוסף אנימציה
            const style = document.createElement('style');
            style.textContent = `
                @keyframes pwa-bounce {
                    0%, 100% { transform: translateY(0); }
                    50% { transform: translateY(-10px); }
                }
                #pwa-native-install-btn:hover {
                    transform: scale(1.1);
                    box-shadow: 0 6px 20px rgba(102, 126, 234, 0.6);
                }
            `;
            document.head.appendChild(style);
            
            // הוסף אירוע לחיצה
            button.addEventListener('click', () => {
                this.showPrompt();
                button.style.display = 'none';
            });
            
            document.body.appendChild(button);
            
            // הסתר אחרי זמן אם לא לחצו
            setTimeout(() => {
                if (button.parentNode) {
                    button.style.opacity = '0.7';
                }
            }, 10000);
        }

        // הוראות התקנה ידניות
        showManualInstructions() {
            const isChrome = /Chrome/.test(navigator.userAgent) && /Google Inc/.test(navigator.vendor);
            const isEdge = /Edg/.test(navigator.userAgent);
            const isFirefox = /Firefox/.test(navigator.userAgent);
            
            let instructions = '📱 להתקנת האפליקציה:\n\n';
            
            if (isChrome || isEdge) {
                instructions += '1. לחץ על תפריט (⋮) בפינה העליונה\n';
                instructions += '2. בחר "התקן אפליקציה" או "Add to Home screen"\n';
            } else if (isFirefox) {
                instructions += '1. לחץ על תפריט (☰)\n';
                instructions += '2. בחר "התקן" או "Install"\n';
            } else if (this.isIOS) {
                instructions += '1. לחץ על כפתור השיתוף ⬆️\n';
                instructions += '2. בחר "הוסף למסך הבית"\n';
            } else {
                instructions += 'חפש באפשרויות הדפדפן את "התקן אפליקציה" או "Add to Home screen"';
            }
            
            alert(instructions);
        }

        // הודעת תודה אחרי התקנה
        showThankYouMessage() {
            console.log('🎉 תודה שהתקנת את האפליקציה!');
            
            // אפשר להוסיף הודעה ויזואלית אם רוצים
            if (typeof window.showNotification === 'function') {
                window.showNotification('האפליקציה הותקנה בהצלחה! 🎉', 'success');
            }
        }

        // API ציבורי
        
        // בדיקה אם ניתן להתקין
        canInstall() {
            return !!this.deferredPrompt || this.isIOS;
        }

        // בדיקה אם מותקן
        isInstalled() {
            return this.isStandalone || localStorage.getItem('pwa-installed') === 'true';
        }

        // התקנה ידנית (מופעלת מבחוץ)
        async install() {
            if (this.isIOS) {
                this.showIOSInstructions();
                return false;
            }
            return await this.showPrompt();
        }

        // עדכון הגדרות
        updateConfig(newConfig) {
            this.config = { ...this.config, ...newConfig };
        }

        // איפוס הגדרות שמורות
        reset() {
            localStorage.removeItem('pwa-installed');
            localStorage.removeItem('pwa-install-accepted');
            localStorage.removeItem('pwa-install-dismissed');
            localStorage.removeItem('ios-prompt-shown');
            localStorage.removeItem('ios-prompt-dismissed');
            console.log('PWA: Settings reset');
        }
    }

    // יצירת instance יחיד
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => {
            if (!window.pwaPrompt) {
                window.pwaPrompt = new PWANativePrompt();
            }
        });
    } else {
        if (!window.pwaPrompt) {
            window.pwaPrompt = new PWANativePrompt();
        }
    }

    // חשוף גם את המחלקה
    window.PWANativePrompt = PWANativePrompt;
})();