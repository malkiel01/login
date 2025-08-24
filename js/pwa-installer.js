// js/pwa-installer.js - מנהל התקנת PWA

class PWAInstaller {
    constructor() {
        this.deferredPrompt = null;
        this.installBanner = null;
        this.init();
    }
    
    async init() {
        console.log('PWA Installer: Initializing...');
        
        // רישום Service Worker
        await this.registerServiceWorker();
        
        // האזנה לאירועי התקנה
        this.setupInstallListeners();
        
        // הוסף סגנונות
        this.addStyles();
    }
    
    async registerServiceWorker() {
        if (!('serviceWorker' in navigator)) {
            console.log('Service Worker not supported');
            return;
        }
        
        try {
            const registration = await navigator.serviceWorker.register(window.APP_CONFIG.basePath + 'service-worker.js', {
                scope: window.APP_CONFIG.basePath
            });
            console.log('Service Worker registered:', registration.scope);
            return registration;
        } catch (error) {
            console.error('Service Worker registration failed:', error);
            return null;
        }
    }
    
    setupInstallListeners() {
        // האזנה לאירוע beforeinstallprompt
        window.addEventListener('beforeinstallprompt', (e) => {
            console.log('beforeinstallprompt fired!');
            e.preventDefault();
            this.deferredPrompt = e;
            
            // בדוק אם המשתמש דחה בעבר
            if (this.shouldShowInstallPrompt()) {
                setTimeout(() => this.showInstallBanner(), 2000);
            }
        });
        
        // האזנה להתקנה מוצלחת
        window.addEventListener('appinstalled', () => {
            console.log('PWA installed successfully!');
            this.hideInstallBanner();
            this.showSuccessMessage();
            this.deferredPrompt = null;
        });
    }
    
    shouldShowInstallPrompt() {
        const dismissed = localStorage.getItem('pwa-install-dismissed');
        const dismissedTime = localStorage.getItem('pwa-install-dismissed-time');
        
        if (dismissed && dismissedTime) {
            const daysPassed = (Date.now() - parseInt(dismissedTime)) / (1000 * 60 * 60 * 24);
            return daysPassed >= 7; // הצג שוב אחרי 7 ימים
        }
        
        return true;
    }
    
    showInstallBanner() {
        if (this.installBanner) return; // כבר מוצג
        
        this.installBanner = document.createElement('div');
        this.installBanner.className = 'pwa-install-banner';
        this.installBanner.innerHTML = `
            <div class="pwa-install-content">
                <div class="pwa-install-icon">📱</div>
                <div class="pwa-install-text">
                    <div class="pwa-install-title">התקן את האפליקציה</div>
                    <div class="pwa-install-subtitle">גישה מהירה + התראות בזמן אמת</div>
                </div>
            </div>
            <div class="pwa-install-actions">
                <button class="pwa-install-btn" id="pwa-install-btn">
                    <span>התקן עכשיו</span>
                    <span>⚡</span>
                </button>
                <button class="pwa-close-btn" id="pwa-close-btn">
                    אולי מאוחר יותר
                </button>
            </div>
        `;
        
        document.body.appendChild(this.installBanner);
        
        // הצג עם אנימציה
        setTimeout(() => {
            this.installBanner.classList.add('show');
        }, 100);
        
        // הוסף מאזינים
        this.attachBannerListeners();
        
        // הסתר אוטומטית אחרי 30 שניות
        setTimeout(() => {
            if (this.installBanner && this.installBanner.classList.contains('show')) {
                this.hideInstallBanner();
            }
        }, 30000);
    }
    
    attachBannerListeners() {
        const installBtn = this.installBanner.querySelector('#pwa-install-btn');
        const closeBtn = this.installBanner.querySelector('#pwa-close-btn');
        
        installBtn.onclick = async () => {
            if (this.deferredPrompt) {
                this.deferredPrompt.prompt();
                const result = await this.deferredPrompt.userChoice;
                
                if (result.outcome === 'accepted') {
                    console.log('User accepted installation');
                } else {
                    console.log('User dismissed installation');
                    this.markAsDismissed();
                }
                
                this.hideInstallBanner();
                this.deferredPrompt = null;
            }
        };
        
        closeBtn.onclick = () => {
            this.markAsDismissed();
            this.hideInstallBanner();
        };
    }
    
    hideInstallBanner() {
        if (this.installBanner) {
            this.installBanner.classList.remove('show');
            setTimeout(() => {
                if (this.installBanner && this.installBanner.parentNode) {
                    this.installBanner.parentNode.removeChild(this.installBanner);
                    this.installBanner = null;
                }
            }, 500);
        }
    }
    
    showSuccessMessage() {
        const successBanner = document.createElement('div');
        successBanner.className = 'pwa-install-banner pwa-success show';
        successBanner.innerHTML = `
            <div class="pwa-install-content">
                <div class="pwa-install-icon">✅</div>
                <div class="pwa-install-text">
                    <div class="pwa-install-title">האפליקציה הותקנה בהצלחה!</div>
                    <div class="pwa-install-subtitle">תמצא אותה במסך הבית שלך</div>
                </div>
            </div>
        `;
        
        document.body.appendChild(successBanner);
        
        setTimeout(() => {
            successBanner.classList.remove('show');
            setTimeout(() => {
                if (successBanner.parentNode) {
                    successBanner.parentNode.removeChild(successBanner);
                }
            }, 500);
        }, 4000);
    }
    
    markAsDismissed() {
        localStorage.setItem('pwa-install-dismissed', 'true');
        localStorage.setItem('pwa-install-dismissed-time', Date.now().toString());
    }
    
    addStyles() {
        if (document.getElementById('pwa-installer-styles')) return;
        
        const style = document.createElement('style');
        style.id = 'pwa-installer-styles';
        style.textContent = `
            .pwa-install-banner {
                position: fixed;
                top: -100px;
                left: 0;
                right: 0;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                color: white;
                padding: 15px 20px;
                box-shadow: 0 4px 20px rgba(0,0,0,0.2);
                z-index: 10000;
                display: flex;
                align-items: center;
                justify-content: space-between;
                transition: top 0.5s cubic-bezier(0.68, -0.55, 0.265, 1.55);
                font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            }
            
            .pwa-install-banner.show {
                top: 0;
            }
            
            .pwa-install-banner.pwa-success {
                background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            }
            
            .pwa-install-content {
                display: flex;
                align-items: center;
                gap: 15px;
                flex: 1;
            }
            
            .pwa-install-icon {
                width: 50px;
                height: 50px;
                background: white;
                border-radius: 12px;
                display: flex;
                align-items: center;
                justify-content: center;
                font-size: 28px;
                box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            }
            
            .pwa-install-text {
                flex: 1;
            }
            
            .pwa-install-title {
                font-size: 18px;
                font-weight: bold;
                margin-bottom: 4px;
                text-shadow: 0 1px 2px rgba(0,0,0,0.1);
            }
            
            .pwa-install-subtitle {
                font-size: 14px;
                opacity: 0.95;
            }
            
            .pwa-install-actions {
                display: flex;
                gap: 10px;
                align-items: center;
            }
            
            .pwa-install-btn {
                background: white;
                color: #667eea;
                border: none;
                padding: 10px 20px;
                border-radius: 25px;
                font-size: 15px;
                font-weight: bold;
                cursor: pointer;
                transition: all 0.3s;
                box-shadow: 0 2px 10px rgba(0,0,0,0.1);
                display: flex;
                align-items: center;
                gap: 6px;
                animation: pulse 2s infinite;
            }
            
            .pwa-install-btn:hover {
                transform: scale(1.05);
                box-shadow: 0 4px 15px rgba(0,0,0,0.2);
            }
            
            .pwa-close-btn {
                background: transparent;
                color: white;
                border: 2px solid rgba(255,255,255,0.3);
                padding: 8px 16px;
                border-radius: 20px;
                font-size: 14px;
                cursor: pointer;
                transition: all 0.3s;
            }
            
            .pwa-close-btn:hover {
                background: rgba(255,255,255,0.1);
                border-color: rgba(255,255,255,0.5);
            }
            
            @keyframes pulse {
                0% { transform: scale(1); }
                50% { transform: scale(1.05); }
                100% { transform: scale(1); }
            }
            
            @media (max-width: 768px) {
                .pwa-install-banner {
                    padding: 12px 15px;
                }
                
                .pwa-install-icon {
                    width: 40px;
                    height: 40px;
                    font-size: 24px;
                }
                
                .pwa-install-title {
                    font-size: 16px;
                }
                
                .pwa-install-subtitle {
                    font-size: 13px;
                }
                
                .pwa-install-btn {
                    padding: 8px 16px;
                    font-size: 14px;
                }
                
                .pwa-close-btn {
                    padding: 6px 12px;
                    font-size: 13px;
                }
            }
        `;
        
        document.head.appendChild(style);
    }
    
    // פונקציה ציבורית לבדיקה
    isInstalled() {
        return window.matchMedia('(display-mode: standalone)').matches ||
               window.navigator.standalone === true;
    }
    
    // פונקציה ציבורית להצגת פרומפט ידנית
    async showInstallPrompt() {
        if (this.deferredPrompt) {
            this.showInstallBanner();
            return true;
        }
        return false;
    }
}

// יצור אינסטנס גלובלי
window.pwaInstaller = new PWAInstaller();

console.log('PWA Installer loaded! 📱');