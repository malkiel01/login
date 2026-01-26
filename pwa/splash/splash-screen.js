/**
 * PWA Splash Screen Manager
 * מנהל מסך פתיחה עבור iOS PWA
 *
 * @version 1.0.0
 */

class PWASplashScreen {
    constructor(options = {}) {
        this.options = {
            minDisplayTime: 1000,      // זמן מינימלי להצגה (ms)
            maxDisplayTime: 5000,      // זמן מקסימלי להצגה (ms)
            fadeOutDuration: 500,      // משך אנימציית יציאה (ms)
            showProgress: false,       // הצג progress bar
            autoHide: true,            // הסתר אוטומטית אחרי טעינה
            onlyInPWA: true,           // הצג רק במצב PWA
            ...options
        };

        this.element = null;
        this.progressBar = null;
        this.startTime = Date.now();
        this.isHiding = false;

        this.init();
    }

    /**
     * בדיקה אם רץ כ-PWA
     */
    isPWA() {
        return window.matchMedia('(display-mode: standalone)').matches ||
               window.navigator.standalone === true ||
               document.referrer.includes('android-app://');
    }

    /**
     * אתחול
     */
    init() {
        // אם רק ב-PWA ואנחנו לא ב-PWA - לא להציג
        if (this.options.onlyInPWA && !this.isPWA()) {
            return;
        }

        this.createSplashScreen();
        this.bindEvents();

        // הסתר אחרי טעינת הדף
        if (this.options.autoHide) {
            this.waitForPageLoad();
        }
    }

    /**
     * יצירת מסך הפתיחה
     */
    createSplashScreen() {
        // בדוק אם כבר קיים
        this.element = document.querySelector('.pwa-splash-screen');

        if (!this.element) {
            this.element = document.createElement('div');
            this.element.className = 'pwa-splash-screen';
            this.element.innerHTML = `
                <div class="pwa-splash-icon">
                    <img src="/pwa/icons/ios/180.png" alt="Logo">
                </div>
                <div class="pwa-splash-title">חברה קדישא</div>
                <div class="pwa-splash-subtitle">מערכת לאיתור נפטרים</div>
                <div class="pwa-splash-loader">
                    <span></span>
                    <span></span>
                    <span></span>
                </div>
                ${this.options.showProgress ? `
                    <div class="pwa-splash-progress">
                        <div class="pwa-splash-progress-bar"></div>
                    </div>
                ` : ''}
            `;

            // הוסף לתחילת ה-body
            document.body.insertBefore(this.element, document.body.firstChild);
        }

        if (this.options.showProgress) {
            this.progressBar = this.element.querySelector('.pwa-splash-progress-bar');
        }
    }

    /**
     * קישור אירועים
     */
    bindEvents() {
        // אם הדף כבר נטען
        if (document.readyState === 'complete') {
            this.onPageLoaded();
        }
    }

    /**
     * המתנה לטעינת הדף
     */
    waitForPageLoad() {
        // מעקב אחרי התקדמות (אם יש תמיכה)
        if (this.options.showProgress && window.PerformanceObserver) {
            this.trackLoadProgress();
        }

        // כשהדף נטען
        window.addEventListener('load', () => {
            this.onPageLoaded();
        });

        // timeout מקסימלי
        setTimeout(() => {
            if (!this.isHiding) {
                this.hide();
            }
        }, this.options.maxDisplayTime);
    }

    /**
     * מעקב אחרי התקדמות הטעינה
     */
    trackLoadProgress() {
        let progress = 0;
        const interval = setInterval(() => {
            if (this.isHiding) {
                clearInterval(interval);
                return;
            }

            // הגדל בהדרגה
            progress = Math.min(progress + Math.random() * 15, 90);
            this.setProgress(progress);
        }, 200);
    }

    /**
     * עדכון התקדמות
     */
    setProgress(percent) {
        if (this.progressBar) {
            this.progressBar.style.width = `${percent}%`;
        }
    }

    /**
     * כשהדף נטען
     */
    onPageLoaded() {
        // וודא שעבר זמן מינימלי
        const elapsed = Date.now() - this.startTime;
        const remaining = Math.max(0, this.options.minDisplayTime - elapsed);

        // השלם את ה-progress
        this.setProgress(100);

        // הסתר אחרי ההשהייה
        setTimeout(() => {
            this.hide();
        }, remaining);
    }

    /**
     * הסתרת מסך הפתיחה
     */
    hide() {
        if (this.isHiding || !this.element) return;
        this.isHiding = true;

        // הוסף class לאנימציית יציאה
        this.element.classList.add('fade-out');

        // הסר מה-DOM אחרי האנימציה
        setTimeout(() => {
            this.element.classList.add('hidden');

            // שחרר משאבים
            if (this.options.removeAfterHide) {
                this.element.remove();
                this.element = null;
            }

            // קרא callback
            if (typeof this.options.onHide === 'function') {
                this.options.onHide();
            }
        }, this.options.fadeOutDuration);
    }

    /**
     * הצגה מחדש (לשימוש ב-navigation)
     */
    show() {
        if (!this.element) {
            this.createSplashScreen();
        }

        this.isHiding = false;
        this.startTime = Date.now();
        this.element.classList.remove('fade-out', 'hidden');
        this.setProgress(0);
    }
}

// יצירת instance גלובלי
window.pwaSplash = new PWASplashScreen();

// Export
if (typeof module !== 'undefined' && module.exports) {
    module.exports = PWASplashScreen;
}
