/**
 * PWA Install Manager
 * מנהל התקנת PWA עם באנר מותאם אישית
 * 
 * שימוש:
 * window.pwaInstallManager = new PWAInstallManager({
 *     title: 'התקן את האפליקציה',
 *     subtitle: 'גישה מהירה ועבודה אופליין',
 *     showAfterSeconds: 5
 * });
 */

(function() {
    'use strict';

    // סגנונות CSS
    const styles = `
        .pwa-install-banner {
            position: fixed;
            top: -200px;
            left: 0;
            right: 0;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 16px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
            z-index: 10000;
            transition: all 0.4s cubic-bezier(0.68, -0.55, 0.265, 1.55);
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', 'Roboto', sans-serif;
            direction: rtl;
        }

        .pwa-install-banner.show {
            top: 0;
            animation: pwa-slideDown 0.5s ease-out;
        }

        .pwa-install-banner.hide {
            animation: pwa-slideUp 0.4s ease-in;
            top: -200px;
        }

        @keyframes pwa-slideDown {
            0% { top: -200px; opacity: 0; }
            100% { top: 0; opacity: 1; }
        }

        @keyframes pwa-slideUp {
            0% { top: 0; opacity: 1; }
            100% { top: -200px; opacity: 0; }
        }

        .pwa-banner-container {
            max-width: 1200px;
            margin: 0 auto;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            color: white;
        }

        .pwa-banner-content {
            display: flex;
            align-items: center;
            gap: 16px;
            flex: 1;
        }

        .pwa-app-icon {
            width: 56px;
            height: 56px;
            background: white;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            flex-shrink: 0;
        }

        .pwa-app-icon img {
            width: 40px;
            height: 40px;
            object-fit: contain;
        }

        .pwa-app-icon .icon-placeholder {
            font-size: 28px;
        }

        .pwa-banner-text {
            flex: 1;
        }

        .pwa-banner-title {
            font-size: 16px;
            font-weight: 600;
            margin-bottom: 4px;
            text-shadow: 0 1px 2px rgba(0, 0, 0, 0.1);
        }

        .pwa-banner-subtitle {
            font-size: 13px;
            opacity: 0.95;
            line-height: 1.4;
        }

        .pwa-banner-actions {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .pwa-install-btn {
            background: white;
            color: #667eea;
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            white-space: nowrap;
        }

        .pwa-install-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        }

        .pwa-dismiss-btn {
            background: transparent;
            border: 2px solid rgba(255, 255, 255, 0.3);
            color: white;
            padding: 10px 20px;
            border-radius: 8px;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.3s ease;
            white-space: nowrap;
        }

        .pwa-dismiss-btn:hover {
            background: rgba(255, 255, 255, 0.1);
            border-color: rgba(255, 255, 255, 0.5);
        }

        .pwa-close-btn {
            background: transparent;
            border: none;
            color: white;
            font-size: 24px;
            cursor: pointer;
            padding: 4px;
            opacity: 0.8;
            transition: opacity 0.3s;
            line-height: 1;
        }

        .pwa-close-btn:hover {
            opacity: 1;
        }

        /* iOS Modal */
        .pwa-ios-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.8);
            z-index: 10001;
            justify-content: center;
            align-items: center;
            direction: rtl;
        }

        .pwa-ios-modal.show {
            display: flex;
            animation: pwa-fadeIn 0.3s ease;
        }

        @keyframes pwa-fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        .pwa-ios-content {
            background: white;
            border-radius: 16px;
            padding: 24px;
            max-width: 400px;
            margin: 20px;
            text-align: center;
            position: relative;
        }

        .pwa-ios-title {
            font-size: 20px;
            font-weight: 600;
            margin-bottom: 16px;
            color: #333;
        }

        .pwa-ios-steps {
            text-align: right;
            margin: 20px 0;
        }

        .pwa-ios-step {
            display: flex;
            align-items: center;
            margin-bottom: 16px;
            gap: 12px;
        }

        .pwa-ios-step-number {
            background: #667eea;
            color: white;
            width: 28px;
            height: 28px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            flex-shrink: 0;
        }

        .pwa-ios-step-text {
            color: #666;
            font-size: 14px;
            line-height: 1.4;
        }

        .pwa-ios-close {
            background: #667eea;
            color: white;
            border: none;
            padding: 12px 32px;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            margin-top: 16px;
        }

        /* Mobile styles */
        @media (max-width: 768px) {
            .pwa-install-banner { padding: 12px; }
            .pwa-banner-container { flex-wrap: wrap; }
            .pwa-app-icon { width: 48px; height: 48px; }
            .pwa-app-icon img { width: 32px; height: 32px; }
            .pwa-banner-title { font-size: 14px; }
            .pwa-banner-subtitle { font-size: 12px; }
            .pwa-banner-actions { width: 100%; justify-content: stretch; gap: 8px; }
            .pwa-install-btn, .pwa-dismiss-btn { flex: 1; padding: 8px 12px; font-size: 13px; }
            .pwa-close-btn { position: absolute; top: 8px; left: 8px; font-size: 20px; }
            .pwa-dismiss-btn { display: none; }
        }
    `;

    // HTML של הבאנר
    const createBannerHTML = (config) => {
        return `
            <div class="pwa-banner-container">
                <button class="pwa-close-btn" aria-label="סגור">×</button>
                
                <div class="pwa-banner-content">
                    <div class="pwa-app-icon">
                        ${config.icon ? 
                            `<img src="${config.icon}" alt="App Icon">` : 
                            `<span class="icon-placeholder">📱</span>`
                        }
                    </div>
                    
                    <div class="pwa-banner-text">
                        <div class="pwa-banner-title">${config.title}</div>
                        <div class="pwa-banner-subtitle">${config.subtitle}</div>
                    </div>
                </div>
                
                <div class="pwa-banner-actions">
                    <button class="pwa-install-btn">${config.installText}</button>
                    <button class="pwa-dismiss-btn">${config.dismissText}</button>
                </div>
            </div>
        `;
    };

    // מודל iOS
    const createIOSModal = () => {
        return `
            <div class="pwa-ios-content">
                <div class="pwa-ios-title">📱 התקנת האפליקציה</div>
                
                <div class="pwa-ios-steps">
                    <div class="pwa-ios-step">
                        <div class="pwa-ios-step-number">1</div>
                        <div class="pwa-ios-step-text">
                            לחץ על כפתור השיתוף <span style="color: #007AFF;">⬆️</span> בתחתית המסך
                        </div>
                    </div>
                    
                    <div class="pwa-ios-step">
                        <div class="pwa-ios-step-number">2</div>
                        <div class="pwa-ios-step-text">
                            גלול למטה ובחר "הוסף למסך הבית" <span style="color: #007AFF;">➕</span>
                        </div>
                    </div>
                    
                    <div class="pwa-ios-step">
                        <div class="pwa-ios-step-number">3</div>
                        <div class="pwa-ios-step-text">
                            לחץ על "הוסף" בפינה הימנית העליונה
                        </div>
                    </div>
                </div>
                
                <div style="font-size: 16px; color: #10b981; margin: 16px 0;">
                    🎉 זהו! האפליקציה תופיע במסך הבית
                </div>
                
                <button class="pwa-ios-close">הבנתי</button>
            </div>
        `;
    };

    // מחלקת PWAInstallManager
    class PWAInstallManager {
        constructor(config = {}) {
            // הגדרות ברירת מחדל
            this.config = {
                title: 'התקן את האפליקציה שלנו! 🚀',
                subtitle: 'גישה מהירה, עבודה אופליין והתראות חכמות',
                installText: 'התקן עכשיו',
                dismissText: 'אולי מאוחר יותר',
                icon: null,
                showAfterSeconds: 3,
                minimumVisits: 2,
                ...config
            };

            // משתני מצב
            this.deferredPrompt = null;
            this.banner = null;
            this.iosModal = null;
            this.isIOS = /iPad|iPhone|iPod/.test(navigator.userAgent);
            this.isAndroid = /Android/.test(navigator.userAgent);
            this.isStandalone = window.matchMedia('(display-mode: standalone)').matches || 
                              window.navigator.standalone === true;
            
            // בדיקת localStorage
            this.installDismissed = this.checkDismissed();
            this.visitCount = this.updateVisitCount();
            
            // אתחול
            if (!this.isStandalone) {
                this.init();
            }
        }

        init() {
            // הזרקת סגנונות
            this.injectStyles();
            
            // יצירת באנר
            this.createBanner();
            
            // יצירת מודל iOS
            if (this.isIOS) {
                this.createIOSModal();
            }
            
            // הוספת מאזינים
            this.setupEventListeners();
            
            // בדיקה אם להציג באנר
            this.checkShowBanner();
        }

        injectStyles() {
            if (!document.getElementById('pwa-install-styles')) {
                const styleSheet = document.createElement('style');
                styleSheet.id = 'pwa-install-styles';
                styleSheet.textContent = styles;
                document.head.appendChild(styleSheet);
            }
        }

        createBanner() {
            this.banner = document.createElement('div');
            this.banner.className = 'pwa-install-banner';
            this.banner.innerHTML = createBannerHTML(this.config);
            document.body.appendChild(this.banner);
            
            // הוספת אירועי לחיצה
            this.banner.querySelector('.pwa-install-btn').addEventListener('click', () => this.install());
            this.banner.querySelector('.pwa-dismiss-btn').addEventListener('click', () => this.dismiss(false));
            this.banner.querySelector('.pwa-close-btn').addEventListener('click', () => this.dismiss(true));
        }

        createIOSModal() {
            this.iosModal = document.createElement('div');
            this.iosModal.className = 'pwa-ios-modal';
            this.iosModal.innerHTML = createIOSModal();
            document.body.appendChild(this.iosModal);
            
            // סגירת המודל
            this.iosModal.querySelector('.pwa-ios-close').addEventListener('click', () => {
                this.iosModal.classList.remove('show');
            });
            
            // סגירה בלחיצה על הרקע
            this.iosModal.addEventListener('click', (e) => {
                if (e.target === this.iosModal) {
                    this.iosModal.classList.remove('show');
                }
            });
        }

        setupEventListeners() {
            // האזנה ל-beforeinstallprompt
            window.addEventListener('beforeinstallprompt', (e) => {
                e.preventDefault();
                this.deferredPrompt = e;
                
                // הצג באנר אם עדיין לא הוצג
                if (!this.installDismissed) {
                    this.show();
                }
            });
            
            // האזנה להתקנה
            window.addEventListener('appinstalled', () => {
                console.log('PWA was installed');
                this.hide();
                localStorage.setItem('pwa-installed', 'true');
                this.trackEvent('app_installed');
            });
        }

        checkShowBanner() {
            // אל תציג אם כבר נדחה או מותקן
            if (this.installDismissed || localStorage.getItem('pwa-installed') === 'true') {
                return;
            }
            
            // בדיקת מספר ביקורים
            const shouldShow = this.visitCount >= this.config.minimumVisits || this.isIOS;
            
            if (shouldShow) {
                // הצג אחרי המתנה
                setTimeout(() => {
                    this.show();
                }, this.config.showAfterSeconds * 1000);
            }
        }

        show() {
            if (this.banner) {
                this.banner.classList.remove('hide');
                this.banner.classList.add('show');
                
                // עדכון טקסט לפי פלטפורמה
                const installBtn = this.banner.querySelector('.pwa-install-btn');
                if (this.isIOS) {
                    installBtn.textContent = 'הוראות התקנה';
                }
                
                this.trackEvent('banner_shown');
            }
        }

        hide() {
            if (this.banner) {
                this.banner.classList.remove('show');
                this.banner.classList.add('hide');
            }
        }

        async install() {
            if (this.isIOS) {
                // הצג מודל הוראות iOS
                if (this.iosModal) {
                    this.iosModal.classList.add('show');
                    this.trackEvent('ios_instructions_shown');
                }
            } else if (this.deferredPrompt) {
                // התקנה רגילה
                this.deferredPrompt.prompt();
                const { outcome } = await this.deferredPrompt.userChoice;
                
                if (outcome === 'accepted') {
                    this.trackEvent('install_accepted');
                } else {
                    this.trackEvent('install_dismissed');
                }
                
                this.deferredPrompt = null;
                this.hide();
            } else {
                // נסה התקנה ידנית או הצג הודעה
                this.showFallbackInstructions();
            }
        }

        dismiss(permanent) {
            this.hide();
            
            if (permanent) {
                // דחייה לצמיתות
                localStorage.setItem('pwa-install-dismissed', 'permanent');
                this.trackEvent('banner_dismissed_permanent');
            } else {
                // דחייה ל-7 ימים
                const dismissTime = Date.now() + (7 * 24 * 60 * 60 * 1000);
                localStorage.setItem('pwa-install-dismissed', dismissTime.toString());
                this.trackEvent('banner_dismissed_temporary');
            }
        }

        checkDismissed() {
            const dismissed = localStorage.getItem('pwa-install-dismissed');
            
            if (dismissed === 'permanent') {
                return true;
            }
            
            if (dismissed && !isNaN(dismissed)) {
                // בדוק אם הזמן עבר
                return Date.now() < parseInt(dismissed);
            }
            
            return false;
        }

        updateVisitCount() {
            let count = parseInt(localStorage.getItem('pwa-visit-count') || '0');
            count++;
            localStorage.setItem('pwa-visit-count', count.toString());
            return count;
        }

        showFallbackInstructions() {
            const message = `
📱 התקנת האפליקציה:

Chrome/Edge:
• לחץ על תפריט (⋮) ← "התקן אפליקציה"

Firefox:
• לחץ על תפריט (☰) ← "התקן"

Safari (iOS):
• לחץ על שיתוף (⬆️) ← "הוסף למסך הבית"
            `;
            alert(message);
        }

        trackEvent(eventName) {
            // Google Analytics
            if (typeof gtag !== 'undefined') {
                gtag('event', eventName, {
                    'event_category': 'PWA',
                    'event_label': 'Install Banner'
                });
            }
            
            // Console log for debugging
            console.log('[PWA Install]', eventName);
        }

        // API ציבורי
        
        reset() {
            localStorage.removeItem('pwa-install-dismissed');
            localStorage.removeItem('pwa-visit-count');
            localStorage.removeItem('pwa-installed');
            this.installDismissed = false;
            this.visitCount = 1;
        }

        forceShow() {
            this.show();
        }

        forceHide() {
            this.hide();
        }
    }

    // חשיפה גלובלית
    window.PWAInstallManager = PWAInstallManager;
}());