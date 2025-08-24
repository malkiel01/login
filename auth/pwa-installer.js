// auth/pwa-installer.js - מודול התקנת PWA עם דיבוג מלא

class PWAInstaller {
    constructor() {
        this.deferredPrompt = null;
        this.isInstallable = false;
        this.debugMode = true;
        this.installButton = null;
        this.debugLog = [];
        
        this.init();
    }
    
    init() {
        this.log('PWAInstaller: Initializing...');
        
        // רישום Service Worker
        this.registerServiceWorker();
        
        // האזנה לאירועים
        this.setupEventListeners();
        
        // בדיקת מצב התקנה
        this.checkInstallationStatus();
    }
    
    log(message, type = 'info') {
        const timestamp = new Date().toISOString();
        const logEntry = {timestamp, message, type};
        this.debugLog.push(logEntry);
        
        if (this.debugMode) {
            switch(type) {
                case 'error':
                    console.error(`[PWA] ${message}`);
                    break;
                case 'warn':
                    console.warn(`[PWA] ${message}`);
                    break;
                default:
                    console.log(`[PWA] ${message}`);
            }
        }
    }
    
    async registerServiceWorker() {
        if (!('serviceWorker' in navigator)) {
            this.log('Service Worker not supported!', 'error');
            return false;
        }
        
        try {
            const registration = await navigator.serviceWorker.register('../service-worker.js', {
                scope: '/family/'
            });
            this.log(`Service Worker registered: ${registration.scope}`);
            
            registration.addEventListener('updatefound', () => {
                this.log('New Service Worker update found!');
            });
            
            return true;
        } catch (error) {
            this.log(`Service Worker registration failed: ${error.message}`, 'error');
            return false;
        }
    }
    
    setupEventListeners() {
        // האזנה ל-beforeinstallprompt
        window.addEventListener('beforeinstallprompt', (e) => {
            this.log('beforeinstallprompt event fired! 🎉');
            e.preventDefault();
            this.deferredPrompt = e;
            this.isInstallable = true;
            this.showInstallButton();
        });
        
        // האזנה להתקנה
        window.addEventListener('appinstalled', () => {
            this.log('App was installed successfully! 🎊');
            this.deferredPrompt = null;
            this.isInstallable = false;
            this.hideInstallButton();
            this.showSuccessMessage();
        });
        
        // בדיקה אם רץ כ-PWA
        if (window.matchMedia('(display-mode: standalone)').matches) {
            this.log('App is running in standalone mode (already installed)');
        }
    }
    
    checkInstallationStatus() {
        const checks = {
            'HTTPS': window.location.protocol === 'https:',
            'Service Worker Support': 'serviceWorker' in navigator,
            'Manifest Present': !!document.querySelector('link[rel="manifest"]'),
            'Display Mode': window.matchMedia('(display-mode: standalone)').matches ? 'Standalone' : 'Browser'
        };
        
        this.log('Installation Status Check:');
        Object.entries(checks).forEach(([key, value]) => {
            this.log(`  - ${key}: ${value}`);
        });
        
        return checks;
    }
    
    createInstallButton() {
        // יצירת כפתור התקנה
        const button = document.createElement('button');
        button.id = 'pwa-install-btn';
        button.innerHTML = `
            <span style="font-size: 20px;">📱</span>
            <span>התקן אפליקציה</span>
        `;
        button.style.cssText = `
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 8px;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            margin: 10px 0;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
            transition: all 0.3s;
        `;
        
        button.onmouseover = () => {
            button.style.transform = 'translateY(-2px)';
            button.style.boxShadow = '0 6px 20px rgba(102, 126, 234, 0.4)';
        };
        
        button.onmouseout = () => {
            button.style.transform = 'translateY(0)';
            button.style.boxShadow = '0 4px 15px rgba(102, 126, 234, 0.3)';
        };
        
        button.onclick = () => this.install();
        
        return button;
    }
    
    showInstallButton() {
        // מצא את המקום להוספת הכפתור
        const container = document.querySelector('.login-body') || document.querySelector('.form-group');
        
        if (!container) {
            this.log('Could not find container for install button', 'warn');
            return;
        }
        
        // בדוק אם הכפתור כבר קיים
        if (document.getElementById('pwa-install-btn')) {
            this.log('Install button already exists');
            return;
        }
        
        this.installButton = this.createInstallButton();
        
        // הוסף את הכפתור אחרי הדיבידר
        const divider = container.querySelector('.divider');
        if (divider) {
            divider.insertAdjacentElement('afterend', this.installButton);
        } else {
            container.appendChild(this.installButton);
        }
        
        this.log('Install button added to page');
    }
    
    hideInstallButton() {
        if (this.installButton) {
            this.installButton.style.display = 'none';
        }
    }
    
    async install() {
        this.log('Install button clicked');
        
        if (!this.deferredPrompt) {
            this.log('No installation prompt available', 'error');
            this.showDebugInfo();
            return false;
        }
        
        try {
            // הצג את חלון ההתקנה
            this.deferredPrompt.prompt();
            this.log('Installation prompt shown');
            
            // חכה לתשובת המשתמש
            const result = await this.deferredPrompt.userChoice;
            this.log(`User response: ${result.outcome}`);
            
            if (result.outcome === 'accepted') {
                this.log('User accepted the installation');
            } else {
                this.log('User dismissed the installation');
            }
            
            // נקה את הפרומפט
            this.deferredPrompt = null;
            
            return result.outcome === 'accepted';
            
        } catch (error) {
            this.log(`Installation error: ${error.message}`, 'error');
            this.showDebugInfo();
            return false;
        }
    }
    
    showDebugInfo() {
        // הצג מידע דיבוג למשתמש
        const debugDiv = document.createElement('div');
        debugDiv.style.cssText = `
            background: #f8d7da;
            color: #721c24;
            padding: 15px;
            border: 1px solid #f5c6cb;
            border-radius: 8px;
            margin: 20px 0;
            direction: rtl;
            text-align: right;
        `;
        
        const status = this.checkInstallationStatus();
        const lastLogs = this.debugLog.slice(-10);
        
        debugDiv.innerHTML = `
            <h3 style="margin-bottom: 10px;">🔍 מידע דיבוג להתקנת PWA</h3>
            <p><strong>סטטוס:</strong></p>
            <ul style="margin: 10px 0;">
                ${Object.entries(status).map(([key, value]) => 
                    `<li>${key}: ${value ? '✅' : '❌'}</li>`
                ).join('')}
            </ul>
            <p><strong>לוג אחרון:</strong></p>
            <div style="background: white; padding: 10px; border-radius: 4px; font-size: 12px; max-height: 200px; overflow-y: auto;">
                ${lastLogs.map(log => 
                    `<div style="color: ${log.type === 'error' ? 'red' : log.type === 'warn' ? 'orange' : 'black'}">
                        ${log.message}
                    </div>`
                ).join('')}
            </div>
            <p style="margin-top: 10px;">
                <strong>פתרונות אפשריים:</strong><br>
                • ודא שאתה משתמש ב-Chrome או Edge<br>
                • נסה לרענן את הדף<br>
                • נקה את נתוני האתר ונסה שוב<br>
                • בקר באתר פעמיים עם הפרש של 30 שניות
            </p>
        `;
        
        // הוסף את הדיבוג לדף
        const container = document.querySelector('.login-body') || document.body;
        container.appendChild(debugDiv);
        
        // הסר אחרי 30 שניות
        setTimeout(() => debugDiv.remove(), 30000);
    }
    
    showSuccessMessage() {
        const successDiv = document.createElement('div');
        successDiv.style.cssText = `
            background: #d4edda;
            color: #155724;
            padding: 15px;
            border: 1px solid #c3e6cb;
            border-radius: 8px;
            margin: 20px 0;
            text-align: center;
        `;
        successDiv.innerHTML = `
            <h3>🎉 האפליקציה הותקנה בהצלחה!</h3>
            <p>כעת תוכל לגשת לאפליקציה ישירות מהמסך הראשי</p>
        `;
        
        const container = document.querySelector('.login-body') || document.body;
        container.appendChild(successDiv);
        
        setTimeout(() => successDiv.remove(), 5000);
    }
    
    // פונקציה לבדיקה ידנית
    async testInstallation() {
        this.log('=== Manual Installation Test ===');
        
        // בדוק תנאים
        const status = this.checkInstallationStatus();
        
        // נסה להתקין
        if (this.isInstallable) {
            this.log('App is installable, trying to install...');
            const result = await this.install();
            this.log(`Installation result: ${result}`);
        } else {
            this.log('App is not installable yet', 'warn');
            this.showDebugInfo();
        }
        
        return this.debugLog;
    }
}

// יצירת אינסטנס גלובלי
window.pwaInstaller = new PWAInstaller();

// חשוף פונקציה גלובלית לדיבוג
window.debugPWA = () => window.pwaInstaller.testInstallation();