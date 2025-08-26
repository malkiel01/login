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

        // יצירת באנר בסגנון נייטיב
        createNativeLikeBanner() {
            // אם כבר יש באנר, צא
            if (document.getElementById('pwa-native-banner')) return;
            
            // קבל פרטים מה-manifest
            const manifestLink = document.querySelector('link[rel="manifest"]');
            if (!manifestLink) return;
            
            fetch(manifestLink.href)
                .then(res => res.json())
                .then(manifest => {
                    this.showNativeLikeBanner(manifest);
                })
                .catch(err => {
                    console.error('Error loading manifest:', err);
                    // הצג עם ערכי ברירת מחדל
                    this.showNativeLikeBanner({
                        name: 'קניות משפחתיות',
                        icons: [{src: '/pwa/icons/android/android-launchericon-192-192.png'}]
                    });
                });
        }
        
        showNativeLikeBanner(manifest) {
            const banner = document.createElement('div');
            banner.id = 'pwa-native-banner';
            
            // מצא את האייקון הכי מתאים
            const icon = manifest.icons?.find(i => i.sizes === '192x192') || 
                        manifest.icons?.find(i => i.sizes === '144x144') ||
                        manifest.icons?.[0] || 
                        {src: '/pwa/icons/android/android-launchericon-192-192.png'};
            
            banner.innerHTML = `
                <style>
                    #pwa-native-banner {
                        position: fixed;
                        top: -100px;
                        left: 0;
                        right: 0;
                        background: white;
                        box-shadow: 0 2px 10px rgba(0,0,0,0.15);
                        z-index: 10000;
                        transition: top 0.4s ease;
                        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', 'Roboto', sans-serif;
                        direction: rtl;
                    }
                    
                    #pwa-native-banner.show {
                        top: 0;
                    }
                    
                    #pwa-native-banner .banner-content {
                        display: flex;
                        align-items: center;
                        padding: 16px 20px;
                        gap: 16px;
                        max-width: 720px;
                        margin: 0 auto;
                    }
                    
                    #pwa-native-banner .app-icon {
                        width: 48px;
                        height: 48px;
                        border-radius: 10px;
                        box-shadow: 0 2px 8px rgba(0,0,0,0.15);
                        overflow: hidden;
                        flex-shrink: 0;
                    }
                    
                    #pwa-native-banner .app-icon img {
                        width: 100%;
                        height: 100%;
                        object-fit: cover;
                    }
                    
                    #pwa-native-banner .app-info {
                        flex: 1;
                        min-width: 0;
                    }
                    
                    #pwa-native-banner .app-name {
                        font-size: 16px;
                        font-weight: 600;
                        color: #202124;
                        white-space: nowrap;
                        overflow: hidden;
                        text-overflow: ellipsis;
                        line-height: 1.4;
                    }
                    
                    #pwa-native-banner .app-url {
                        font-size: 13px;
                        color: #5f6368;
                        white-space: nowrap;
                        overflow: hidden;
                        text-overflow: ellipsis;
                        margin-top: 3px;
                    }
                    
                    #pwa-native-banner .banner-actions {
                        display: flex;
                        gap: 8px;
                        flex-shrink: 0;
                    }
                    
                    #pwa-native-banner .install-btn {
                        background: #1a73e8;
                        color: white;
                        border: none;
                        padding: 10px 24px;
                        border-radius: 6px;
                        font-size: 15px;
                        font-weight: 500;
                        cursor: pointer;
                        transition: all 0.2s;
                        white-space: nowrap;
                        box-shadow: 0 1px 3px rgba(26, 115, 232, 0.2);
                    }
                    
                    #pwa-native-banner .install-btn:hover {
                        background: #1557b0;
                        box-shadow: 0 2px 6px rgba(26, 115, 232, 0.3);
                    }
                    
                    #pwa-native-banner .install-btn:active {
                        transform: scale(0.98);
                    }
                    
                    #pwa-native-banner .close-btn {
                        background: transparent;
                        border: none;
                        color: #5f6368;
                        font-size: 24px;
                        cursor: pointer;
                        padding: 6px;
                        line-height: 1;
                        display: flex;
                        align-items: center;
                        justify-content: center;
                        border-radius: 50%;
                        transition: all 0.2s;
                        width: 36px;
                        height: 36px;
                    }
                    
                    #pwa-native-banner .close-btn:hover {
                        background: rgba(0,0,0,0.06);
                        color: #202124;
                    }
                    
                    /* אנימציה עדינה */
                    @keyframes subtleSlide {
                        from { 
                            top: -100px;
                            opacity: 0.8;
                        }
                        to { 
                            top: 0;
                            opacity: 1;
                        }
                    }
                    
                    #pwa-native-banner.show {
                        animation: subtleSlide 0.4s ease-out;
                    }
                    
                    /* רספונסיב */
                    @media (max-width: 480px) {
                        #pwa-native-banner .banner-content {
                            padding: 14px 16px;
                        }
                        
                        #pwa-native-banner .app-icon {
                            width: 42px;
                            height: 42px;
                        }
                        
                        #pwa-native-banner .app-name {
                            font-size: 15px;
                        }
                        
                        #pwa-native-banner .install-btn {
                            padding: 9px 20px;
                            font-size: 14px;
                        }
                    }
                </style>
                
                <div class="banner-content">
                    <div class="app-icon">
                        <img src="${icon.src}" alt="${manifest.name || 'App'}" />
                    </div>
                    
                    <div class="app-info">
                        <div class="app-name">${manifest.name || 'קניות משפחתיות'}</div>
                        <div class="app-url">${window.location.hostname}</div>
                    </div>
                    
                    <div class="banner-actions">
                        <button class="install-btn">התקן</button>
                        <button class="close-btn" aria-label="סגור">×</button>
                    </div>
                </div>
            `;
            
            document.body.appendChild(banner);
            
            // הצג את הבאנר אחרי רגע
            setTimeout(() => {
                banner.classList.add('show');
            }, 100);
            
            // אירועי לחיצה
            const installBtn = banner.querySelector('.install-btn');
            const closeBtn = banner.querySelector('.close-btn');
            
            installBtn.addEventListener('click', async () => {
                console.log('👆 User clicked install button');
                
                if (this.deferredPrompt) {
                    try {
                        // הסתר את הבאנר המותאם
                        banner.classList.remove('show');
                        
                        // הצג את הפרומפט הנייטיב
                        this.deferredPrompt.prompt();
                        console.log('✅ Native prompt shown!');
                        
                        const { outcome } = await this.deferredPrompt.userChoice;
                        
                        if (outcome === 'accepted') {
                            console.log('User accepted installation');
                            localStorage.setItem('pwa-installed', 'true');
                        } else {
                            console.log('User dismissed installation');
                            localStorage.setItem('pwa-prompt-dismissed', Date.now());
                        }
                        
                        // הסר את הבאנר
                        setTimeout(() => banner.remove(), 400);
                        this.deferredPrompt = null;
                        
                    } catch (err) {
                        console.error('Error showing prompt:', err);
                    }
                } else if (this.isIOS) {
                    this.showIOSInstructions();
                    banner.remove();
                } else {
                    console.log('No deferred prompt available');
                    banner.remove();
                }
            });
            
            closeBtn.addEventListener('click', () => {
                banner.classList.remove('show');
                setTimeout(() => banner.remove(), 400);
                localStorage.setItem('pwa-banner-dismissed', Date.now());
            });
            
            // הסתר אוטומטית אחרי 15 שניות
            setTimeout(() => {
                if (banner.parentNode) {
                    banner.classList.remove('show');
                    setTimeout(() => banner.remove(), 400);
                }
            }, 15000);
        }
        
        // יצירת טריגר לחיצה (Chrome דורש user gesture) - מוחלף בבאנר הנייטיבי
        createClickTrigger() {
            this.createNativeLikeBanner();
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