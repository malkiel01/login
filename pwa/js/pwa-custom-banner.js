/**
 * PWA Custom Banner Manager
 * מנהל באנר מותאם אישית להתקנת PWA
 * 
 * יתרון: יכול להופיע אוטומטית בלי צורך בפעולת משתמש
 */

(function() {
    'use strict';

    // סגנונות CSS
    const styles = `
        .pwa-custom-banner {
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

        .pwa-custom-banner.show {
            top: 0;
            animation: slideDown 0.5s ease-out;
        }

        .pwa-custom-banner.hide {
            animation: slideUp 0.4s ease-in;
            top: -200px;
        }

        @keyframes slideDown {
            from { top: -200px; opacity: 0; }
            to { top: 0; opacity: 1; }
        }

        @keyframes slideUp {
            from { top: 0; opacity: 1; }
            to { top: -200px; opacity: 0; }
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

        .pwa-banner-text h3 {
            margin: 0 0 4px 0;
            font-size: 16px;
            font-weight: 600;
            text-shadow: 0 1px 2px rgba(0, 0, 0, 0.1);
        }

        .pwa-banner-text p {
            margin: 0;
            font-size: 13px;
            opacity: 0.95;
        }

        .pwa-banner-actions {
            display: flex;
            gap: 12px;
        }

        .pwa-btn {
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            white-space: nowrap;
        }

        .pwa-install-btn {
            background: white;
            color: #667eea;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        .pwa-install-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        }

        .pwa-dismiss-btn {
            background: transparent;
            border: 2px solid rgba(255, 255, 255, 0.3);
            color: white;
        }

        .pwa-dismiss-btn:hover {
            background: rgba(255, 255, 255, 0.1);
            border-color: rgba(255, 255, 255, 0.5);
        }

        .pwa-close-x {
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

        .pwa-close-x:hover {
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
            animation: fadeIn 0.3s ease;
        }

        @keyframes fadeIn {
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
            color: #333;
        }

        .pwa-ios-title {
            font-size: 20px;
            font-weight: 600;
            margin-bottom: 16px;
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

        .pwa-step-number {
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

        .pwa-step-text {
            color: #666;
            font-size: 14px;
            line-height: 1.4;
            text-align: right;
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

        /* Mobile */
        @media (max-width: 768px) {
            .pwa-custom-banner { padding: 12px; }
            .pwa-app-icon { width: 48px; height: 48px; }
            .pwa-app-icon img { width: 32px; height: 32px; }
            .pwa-banner-text h3 { font-size: 14px; }
            .pwa-banner-text p { font-size: 12px; }
            .pwa-banner-actions { width: 100%; justify-content: stretch; }
            .pwa-btn { flex: 1; padding: 8px 12px; font-size: 13px; }
            .pwa-dismiss-btn { display: none; }
            .pwa-close-x { position: absolute; top: 8px; left: 8px; }
        }
    `;

    class PWACustomBanner {
        constructor(config = {}) {
            // הגדרות ברירת מחדל
            this.config = {
                title: 'התקן את האפליקציה! 🚀',
                subtitle: 'גישה מהירה, עבודה אופליין והתראות',
                installText: 'התקן עכשיו',
                dismissText: 'מאוחר יותר',
                icon: '/pwa/icons/android/android-launchericon-192-192.png',
                showDelay: 3000,
                minimumVisits: 2,
                ...config
            };

            // משתנים
            this.deferredPrompt = null;
            this.banner = null;
            this.iosModal = null;
            this.isIOS = /iPad|iPhone|iPod/.test(navigator.userAgent);
            this.isStandalone = window.matchMedia('(display-mode: standalone)').matches || 
                              window.navigator.standalone === true;
            
            // בדיקות localStorage
            this.visitCount = this.updateVisitCount();
            this.dismissed = this.checkDismissed();
            
            // אתחול
            if (!this.isStandalone && !this.isInstalled()) {
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
            
            // האזנה לאירועים
            this.setupEventListeners();
            
            // בדיקה אם להציג
            if (!this.dismissed && this.shouldShow()) {
                setTimeout(() => this.show(), this.config.showDelay);
            }
        }

        injectStyles() {
            if (!document.getElementById('pwa-custom-styles')) {
                const styleSheet = document.createElement('style');
                styleSheet.id = 'pwa-custom-styles';
                styleSheet.textContent = styles;
                document.head.appendChild(styleSheet);
            }
        }

        createBanner() {
            this.banner = document.createElement('div');
            this.banner.className = 'pwa-custom-banner';
            this.banner.innerHTML = `
                <div class="pwa-banner-container">
                    <button class="pwa-close-x" aria-label="סגור">×</button>
                    
                    <div class="pwa-banner-content">
                        <div class="pwa-app-icon">
                            <img src="${this.config.icon}" alt="App Icon" onerror="this.style.display='none'">
                        </div>
                        
                        <div class="pwa-banner-text">
                            <h3>${this.config.title}</h3>
                            <p>${this.config.subtitle}</p>
                        </div>
                    </div>
                    
                    <div class="pwa-banner-actions">
                        <button class="pwa-btn pwa-install-btn">${this.config.installText}</button>
                        <button class="pwa-btn pwa-dismiss-btn">${this.config.dismissText}</button>
                    </div>
                </div>
            `;
            
            document.body.appendChild(this.banner);
            
            // אירועים
            this.banner.querySelector('.pwa-install-btn').addEventListener('click', () => this.install());
            this.banner.querySelector('.pwa-dismiss-btn').addEventListener('click', () => this.dismiss(false));
            this.banner.querySelector('.pwa-close-x').addEventListener('click', () => this.dismiss(true));
        }

        createIOSModal() {
            this.iosModal = document.createElement('div');
            this.iosModal.className = 'pwa-ios-modal';
            this.iosModal.innerHTML = `
                <div class="pwa-ios-content">
                    <div class="pwa-ios-title">📱 התקנת האפליקציה</div>
                    
                    <div class="pwa-ios-steps">
                        <div class="pwa-ios-step">
                            <div class="pwa-step-number">1</div>
                            <div class="pwa-step-text">
                                לחץ על כפתור השיתוף ⬆️ בתחתית המסך
                            </div>
                        </div>
                        
                        <div class="pwa-ios-step">
                            <div class="pwa-step-number">2</div>
                            <div class="pwa-step-text">
                                גלול למטה ובחר "הוסף למסך הבית" ➕
                            </div>
                        </div>
                        
                        <div class="pwa-ios-step">
                            <div class="pwa-step-number">3</div>
                            <div class="pwa-step-text">
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
            
            document.body.appendChild(this.iosModal);
            
            // סגירה
            this.iosModal.querySelector('.pwa-ios-close').addEventListener('click', () => {
                this.iosModal.classList.remove('show');
            });
            
            this.iosModal.addEventListener('click', (e) => {
                if (e.target === this.iosModal) {
                    this.iosModal.classList.remove('show');
                }
            });
        }

        setupEventListeners() {
            // האזנה לאירוע התקנה
            window.addEventListener('beforeinstallprompt', (e) => {
                e.preventDefault();
                this.deferredPrompt = e;
                console.log('PWA Custom: Install prompt captured');
            });
            
            // האזנה להתקנה מוצלחת
            window.addEventListener('appinstalled', () => {
                console.log('PWA Custom: App installed');
                this.hide();
                localStorage.setItem('pwa-installed', 'true');
            });
        }

        async install() {
            if (this.isIOS) {
                this.iosModal.classList.add('show');
                return;
            }
            
            if (this.deferredPrompt) {
                this.deferredPrompt.prompt();
                const { outcome } = await this.deferredPrompt.userChoice;
                
                if (outcome === 'accepted') {
                    localStorage.setItem('pwa-installed', 'true');
                }
                
                this.deferredPrompt = null;
                this.hide();
            } else {
                // הצג הוראות ידניות
                alert('לחץ על תפריט הדפדפן (⋮) ובחר "התקן אפליקציה"');
            }
        }

        show() {
            if (this.banner && !this.dismissed && !this.isInstalled()) {
                this.banner.classList.remove('hide');
                this.banner.classList.add('show');
                console.log('PWA Custom: Banner shown');
            }
        }

        hide() {
            if (this.banner) {
                this.banner.classList.remove('show');
                this.banner.classList.add('hide');
            }
        }

        dismiss(permanent) {
            this.hide();
            
            if (permanent) {
                localStorage.setItem('pwa-custom-dismissed', 'permanent');
            } else {
                // דחה ל-7 ימים
                const dismissTime = Date.now() + (7 * 24 * 60 * 60 * 1000);
                localStorage.setItem('pwa-custom-dismissed', dismissTime.toString());
            }
        }

        shouldShow() {
            return this.visitCount >= this.config.minimumVisits;
        }

        updateVisitCount() {
            let count = parseInt(localStorage.getItem('pwa-visit-count') || '0');
            count++;
            localStorage.setItem('pwa-visit-count', count.toString());
            return count;
        }

        checkDismissed() {
            const dismissed = localStorage.getItem('pwa-custom-dismissed');
            
            if (dismissed === 'permanent') {
                return true;
            }
            
            if (dismissed && !isNaN(dismissed)) {
                return Date.now() < parseInt(dismissed);
            }
            
            return false;
        }

        isInstalled() {
            return localStorage.getItem('pwa-installed') === 'true' || this.isStandalone;
        }

        // API ציבורי
        forceShow() {
            this.show();
        }
        
        forceHide() {
            this.hide();
        }
        
        reset() {
            localStorage.removeItem('pwa-custom-dismissed');
            localStorage.removeItem('pwa-visit-count');
            localStorage.removeItem('pwa-installed');
            this.dismissed = false;
            this.visitCount = 1;
        }

        updateConfig(newConfig) {
            this.config = { ...this.config, ...newConfig };
            // עדכן טקסטים אם הבאנר קיים
            if (this.banner) {
                this.banner.querySelector('.pwa-banner-text h3').textContent = this.config.title;
                this.banner.querySelector('.pwa-banner-text p').textContent = this.config.subtitle;
                this.banner.querySelector('.pwa-install-btn').textContent = this.config.installText;
                this.banner.querySelector('.pwa-dismiss-btn').textContent = this.config.dismissText;
            }
        }
    }

    // חשוף את המחלקה
    window.PWACustomBanner = PWACustomBanner;
    
    // יצירת instance אוטומטי
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => {
            window.pwaCustomBanner = new PWACustomBanner();
        });
    } else {
        window.pwaCustomBanner = new PWACustomBanner();
    }
})();