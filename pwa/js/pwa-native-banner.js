/**
 * PWA Native Banner Manager
 * מנהל באנר נייטיב של הדפדפן
 * 
 * חשוב: הבאנר הנייטיב דורש פעולת משתמש (לחיצה) ולא יכול להופיע אוטומטית!
 */

(function() {
    'use strict';

    class PWANativeBanner {
        constructor(config = {}) {
            this.deferredPrompt = null;
            this.isIOS = /iPad|iPhone|iPod/.test(navigator.userAgent);
            this.isStandalone = window.matchMedia('(display-mode: standalone)').matches || 
                              window.navigator.standalone === true;
            
            // הגדרות
            this.config = {
                showFloatingButton: true,  // הצג כפתור צף
                buttonText: '📱 התקן אפליקציה',
                buttonPosition: 'bottom-right', // bottom-right, bottom-left, top-right, top-left
                ...config
            };
            
            // אתחול רק אם לא מותקן
            if (!this.isStandalone && !this.isInstalled()) {
                this.init();
            }
        }

        init() {
            // האזנה לאירוע התקנה
            window.addEventListener('beforeinstallprompt', (e) => {
                console.log('PWA Native: Install prompt available');
                
                // מנע הצגה אוטומטית
                // e.preventDefault();
                
                // שמור את האירוע
                this.deferredPrompt = e;

                // צור כפתור צף אם מוגדר
                if (this.config.showFloatingButton) {
                    this.createFloatingButton();
                }
                
                // הפעל כפתורים קיימים בדף
                this.activateExistingButtons();
            });

            // האזנה להתקנה מוצלחת
            window.addEventListener('appinstalled', () => {
                console.log('PWA Native: App installed successfully');
                this.deferredPrompt = null;
                localStorage.setItem('pwa-installed', 'true');
                this.hideFloatingButton();
            });

            // טיפול ב-iOS
            if (this.isIOS) {
                this.setupIOSHandler();
            }
        }

        // יצירת כפתור צף
        createFloatingButton() {
            if (document.getElementById('pwa-native-btn')) return;
            
            const button = document.createElement('button');
            button.id = 'pwa-native-btn';
            button.innerHTML = this.config.buttonText;
            
            // מיקום הכפתור
            const positions = {
                'bottom-right': 'bottom: 20px; right: 20px;',
                'bottom-left': 'bottom: 20px; left: 20px;',
                'top-right': 'top: 80px; right: 20px;',
                'top-left': 'top: 80px; left: 20px;'
            };
            
            button.style.cssText = `
                position: fixed;
                ${positions[this.config.buttonPosition] || positions['bottom-right']}
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
                transition: all 0.3s ease;
                animation: pulse 2s infinite;
            `;
            
            // הוסף אנימציה
            if (!document.getElementById('pwa-native-styles')) {
                const style = document.createElement('style');
                style.id = 'pwa-native-styles';
                style.textContent = `
                    @keyframes pulse {
                        0%, 100% { transform: scale(1); }
                        50% { transform: scale(1.05); }
                    }
                    #pwa-native-btn:hover {
                        transform: scale(1.1);
                        box-shadow: 0 6px 20px rgba(102, 126, 234, 0.6);
                    }
                `;
                document.head.appendChild(style);
            }
            
            // אירוע לחיצה
            button.addEventListener('click', () => this.showInstallPrompt());
            
            document.body.appendChild(button);
        }

        // הסתרת כפתור צף
        hideFloatingButton() {
            const button = document.getElementById('pwa-native-btn');
            if (button) {
                button.style.display = 'none';
            }
        }

        // הפעלת כפתורים קיימים
        activateExistingButtons() {
            const selectors = [
                '.pwa-install-btn',
                '.install-app-btn',
                '[data-pwa-install]',
                '#installButton'
            ];
            
            document.querySelectorAll(selectors.join(',')).forEach(btn => {
                btn.style.display = 'inline-block';
                btn.addEventListener('click', (e) => {
                    e.preventDefault();
                    this.showInstallPrompt();
                });
            });
        }

        // הצגת באנר ההתקנה
        async showInstallPrompt() {
            if (this.isIOS) {
                this.showIOSInstructions();
                return false;
            }
            
            if (!this.deferredPrompt) {
                console.log('PWA Native: No install prompt available');
                this.showManualInstructions();
                return false;
            }
            
            try {
                // הצג את הבאנר הנייטיב
                this.deferredPrompt.prompt();
                
                // חכה לתגובה
                const { outcome } = await this.deferredPrompt.userChoice;
                console.log(`PWA Native: User ${outcome} the installation`);
                
                if (outcome === 'accepted') {
                    localStorage.setItem('pwa-installed', 'true');
                    this.hideFloatingButton();
                }
                
                this.deferredPrompt = null;
                return outcome === 'accepted';
                
            } catch (error) {
                console.error('PWA Native: Error showing prompt', error);
                return false;
            }
        }

        // טיפול ב-iOS
        setupIOSHandler() {
            // בדוק אם להציג הוראות
            if (!localStorage.getItem('ios-instructions-shown')) {
                setTimeout(() => this.showIOSInstructions(), 5000);
            }
        }

        // הוראות ל-iOS
        showIOSInstructions() {
            const message = `
📱 להתקנת האפליקציה ב-iPhone/iPad:

1. לחץ על כפתור השיתוף ⬆️ למטה
2. גלול וחפש "הוסף למסך הבית" ➕
3. לחץ "הוסף" בפינה הימנית למעלה

האפליקציה תופיע במסך הבית!`;
            
            alert(message);
            localStorage.setItem('ios-instructions-shown', 'true');
        }

        // הוראות ידניות לדפדפנים אחרים
        showManualInstructions() {
            const ua = navigator.userAgent;
            let instructions = '📱 להתקנת האפליקציה:\n\n';
            
            if (/Chrome/i.test(ua)) {
                instructions += '1. לחץ על ⋮ (תפריט) למעלה\n2. בחר "התקן אפליקציה"';
            } else if (/Firefox/i.test(ua)) {
                instructions += '1. לחץ על ☰ (תפריט)\n2. בחר "התקן"';
            } else if (/Edge/i.test(ua)) {
                instructions += '1. לחץ על ••• (תפריט)\n2. בחר "Apps" > "Install this site as an app"';
            } else {
                instructions += 'חפש בתפריט הדפדפן את אפשרות "התקן אפליקציה"';
            }
            
            alert(instructions);
        }

        // בדיקה אם מותקן
        isInstalled() {
            return localStorage.getItem('pwa-installed') === 'true' || this.isStandalone;
        }

        // API ציבורי
        install() {
            return this.showInstallPrompt();
        }
        
        reset() {
            localStorage.removeItem('pwa-installed');
            localStorage.removeItem('ios-instructions-shown');
        }
    }

    // יצירה אוטומטית
    window.PWANativeBanner = PWANativeBanner;
    
    // יצירת instance אוטומטי
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => {
            window.pwaNativeBanner = new PWANativeBanner();
        });
    } else {
        window.pwaNativeBanner = new PWANativeBanner();
    }
})();