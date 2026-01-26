/**
 * Web Share UI - רכיבי ממשק משתמש לשיתוף
 * כפתורים ותפריטי שיתוף מותאמים
 *
 * @version 1.0.0
 */

class WebShareUI {
    constructor() {
        this.webShare = window.webShare || new WebShare();
        this.init();
    }

    /**
     * אתחול
     */
    init() {
        this.injectStyles();
        this.bindShareButtons();
    }

    /**
     * הוספת סגנונות
     */
    injectStyles() {
        if (document.getElementById('web-share-ui-styles')) return;

        const styles = document.createElement('style');
        styles.id = 'web-share-ui-styles';
        styles.textContent = `
            /* כפתור שיתוף */
            .share-btn {
                display: inline-flex;
                align-items: center;
                gap: 8px;
                padding: 10px 16px;
                background: linear-gradient(135deg, #007AFF 0%, #5856D6 100%);
                color: white;
                border: none;
                border-radius: 10px;
                font-size: 14px;
                font-weight: 500;
                cursor: pointer;
                transition: all 0.2s ease;
            }

            .share-btn:hover {
                transform: translateY(-1px);
                box-shadow: 0 4px 12px rgba(0, 122, 255, 0.3);
            }

            .share-btn:active {
                transform: translateY(0);
            }

            .share-btn.share-btn-icon-only {
                padding: 12px;
                border-radius: 50%;
            }

            .share-btn svg {
                width: 18px;
                height: 18px;
                fill: currentColor;
            }

            /* כפתור שיתוף קטן */
            .share-btn-sm {
                padding: 6px 12px;
                font-size: 12px;
            }

            .share-btn-sm svg {
                width: 14px;
                height: 14px;
            }

            /* FAB שיתוף */
            .share-fab {
                position: fixed;
                bottom: 90px;
                left: 20px;
                width: 56px;
                height: 56px;
                border-radius: 50%;
                background: linear-gradient(135deg, #007AFF 0%, #5856D6 100%);
                color: white;
                border: none;
                box-shadow: 0 4px 12px rgba(0, 122, 255, 0.4);
                cursor: pointer;
                z-index: 1000;
                display: flex;
                align-items: center;
                justify-content: center;
                transition: all 0.3s ease;
            }

            .share-fab:hover {
                transform: scale(1.1);
                box-shadow: 0 6px 20px rgba(0, 122, 255, 0.5);
            }

            .share-fab svg {
                width: 24px;
                height: 24px;
                fill: currentColor;
            }

            /* תפריט שיתוף */
            .share-menu {
                position: fixed;
                bottom: 0;
                left: 0;
                right: 0;
                background: white;
                border-radius: 20px 20px 0 0;
                padding: 20px;
                box-shadow: 0 -10px 40px rgba(0,0,0,0.15);
                transform: translateY(100%);
                transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
                z-index: 10001;
                max-height: 70vh;
                overflow-y: auto;
            }

            .share-menu.active {
                transform: translateY(0);
            }

            .share-menu-handle {
                width: 40px;
                height: 4px;
                background: #e0e0e0;
                border-radius: 2px;
                margin: 0 auto 15px;
            }

            .share-menu-title {
                font-size: 18px;
                font-weight: 600;
                text-align: center;
                margin-bottom: 20px;
                color: #333;
            }

            .share-menu-options {
                display: grid;
                grid-template-columns: repeat(4, 1fr);
                gap: 15px;
                margin-bottom: 20px;
            }

            .share-menu-option {
                display: flex;
                flex-direction: column;
                align-items: center;
                gap: 8px;
                padding: 12px 8px;
                background: none;
                border: none;
                cursor: pointer;
                border-radius: 12px;
                transition: background 0.2s ease;
            }

            .share-menu-option:hover {
                background: #f5f5f5;
            }

            .share-menu-option-icon {
                width: 50px;
                height: 50px;
                border-radius: 12px;
                display: flex;
                align-items: center;
                justify-content: center;
            }

            .share-menu-option-icon svg {
                width: 24px;
                height: 24px;
            }

            .share-menu-option-label {
                font-size: 11px;
                color: #666;
                text-align: center;
            }

            /* אפליקציות שיתוף */
            .share-option-native .share-menu-option-icon {
                background: linear-gradient(135deg, #007AFF 0%, #5856D6 100%);
                color: white;
            }

            .share-option-whatsapp .share-menu-option-icon {
                background: #25D366;
                color: white;
            }

            .share-option-telegram .share-menu-option-icon {
                background: #0088cc;
                color: white;
            }

            .share-option-email .share-menu-option-icon {
                background: #EA4335;
                color: white;
            }

            .share-option-sms .share-menu-option-icon {
                background: #34C759;
                color: white;
            }

            .share-option-copy .share-menu-option-icon {
                background: #8E8E93;
                color: white;
            }

            .share-option-facebook .share-menu-option-icon {
                background: #1877F2;
                color: white;
            }

            .share-option-twitter .share-menu-option-icon {
                background: #1DA1F2;
                color: white;
            }

            /* Overlay */
            .share-menu-overlay {
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background: rgba(0,0,0,0.4);
                opacity: 0;
                visibility: hidden;
                transition: all 0.3s ease;
                z-index: 10000;
            }

            .share-menu-overlay.active {
                opacity: 1;
                visibility: visible;
            }

            /* כפתור ביטול */
            .share-menu-cancel {
                width: 100%;
                padding: 15px;
                background: #f5f5f5;
                border: none;
                border-radius: 12px;
                font-size: 16px;
                font-weight: 500;
                color: #007AFF;
                cursor: pointer;
                transition: background 0.2s ease;
            }

            .share-menu-cancel:hover {
                background: #ebebeb;
            }

            /* Animation */
            @keyframes fadeInUp {
                from {
                    opacity: 0;
                    transform: translateY(20px);
                }
                to {
                    opacity: 1;
                    transform: translateY(0);
                }
            }

            @keyframes fadeOut {
                from { opacity: 1; }
                to { opacity: 0; }
            }

            /* RTL תמיכה */
            [dir="rtl"] .share-fab {
                left: auto;
                right: 20px;
            }
        `;

        document.head.appendChild(styles);
    }

    /**
     * אייקון שיתוף
     */
    getShareIcon() {
        return `<svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
            <path d="M18 16.08c-.76 0-1.44.3-1.96.77L8.91 12.7c.05-.23.09-.46.09-.7s-.04-.47-.09-.7l7.05-4.11c.54.5 1.25.81 2.04.81 1.66 0 3-1.34 3-3s-1.34-3-3-3-3 1.34-3 3c0 .24.04.47.09.7L8.04 9.81C7.5 9.31 6.79 9 6 9c-1.66 0-3 1.34-3 3s1.34 3 3 3c.79 0 1.5-.31 2.04-.81l7.12 4.16c-.05.21-.08.43-.08.65 0 1.61 1.31 2.92 2.92 2.92s2.92-1.31 2.92-2.92-1.31-2.92-2.92-2.92z"/>
        </svg>`;
    }

    /**
     * יצירת כפתור שיתוף
     *
     * @param {Object} options - הגדרות
     * @returns {HTMLElement}
     */
    createShareButton(options = {}) {
        const {
            text = 'שתף',
            iconOnly = false,
            size = 'normal', // normal, sm
            data = {},
            onShare = null
        } = options;

        const btn = document.createElement('button');
        btn.className = `share-btn ${iconOnly ? 'share-btn-icon-only' : ''} ${size === 'sm' ? 'share-btn-sm' : ''}`;

        btn.innerHTML = iconOnly
            ? this.getShareIcon()
            : `${this.getShareIcon()}<span>${text}</span>`;

        btn.addEventListener('click', async () => {
            if (onShare) {
                const shareData = typeof onShare === 'function' ? onShare() : data;
                await this.share(shareData);
            } else {
                await this.share(data);
            }
        });

        return btn;
    }

    /**
     * יצירת FAB שיתוף
     *
     * @param {Object|Function} dataOrCallback - נתוני שיתוף או callback
     * @returns {HTMLElement}
     */
    createShareFAB(dataOrCallback = {}) {
        const fab = document.createElement('button');
        fab.className = 'share-fab';
        fab.innerHTML = this.getShareIcon();
        fab.setAttribute('aria-label', 'שיתוף');

        fab.addEventListener('click', async () => {
            const data = typeof dataOrCallback === 'function' ? dataOrCallback() : dataOrCallback;
            await this.share(data);
        });

        return fab;
    }

    /**
     * שיתוף עם תפריט מותאם
     *
     * @param {Object} data - נתוני השיתוף
     */
    async share(data = {}) {
        const { title = '', text = '', url = window.location.href, files = null } = data;

        // אם יש תמיכה מלאה, השתמש ב-native share
        if (this.webShare.isSupported) {
            if (files && files.length > 0) {
                await this.webShare.shareFiles({ files, title, text });
            } else {
                await this.webShare.shareText({ title, text, url });
            }
        } else {
            // הצג תפריט מותאם
            this.showShareMenu({ title, text, url });
        }
    }

    /**
     * הצגת תפריט שיתוף מותאם
     */
    showShareMenu(data) {
        const { title = '', text = '', url = '' } = data;

        // יצירת overlay
        const overlay = document.createElement('div');
        overlay.className = 'share-menu-overlay';

        // יצירת תפריט
        const menu = document.createElement('div');
        menu.className = 'share-menu';

        menu.innerHTML = `
            <div class="share-menu-handle"></div>
            <div class="share-menu-title">שיתוף</div>
            <div class="share-menu-options">
                ${this.webShare.isSupported ? `
                <button class="share-menu-option share-option-native" data-action="native">
                    <div class="share-menu-option-icon">${this.getShareIcon()}</div>
                    <span class="share-menu-option-label">עוד...</span>
                </button>
                ` : ''}
                <button class="share-menu-option share-option-whatsapp" data-action="whatsapp">
                    <div class="share-menu-option-icon">
                        <svg viewBox="0 0 24 24"><path fill="currentColor" d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
                    </div>
                    <span class="share-menu-option-label">WhatsApp</span>
                </button>
                <button class="share-menu-option share-option-telegram" data-action="telegram">
                    <div class="share-menu-option-icon">
                        <svg viewBox="0 0 24 24"><path fill="currentColor" d="M11.944 0A12 12 0 0 0 0 12a12 12 0 0 0 12 12 12 12 0 0 0 12-12A12 12 0 0 0 12 0a12 12 0 0 0-.056 0zm4.962 7.224c.1-.002.321.023.465.14a.506.506 0 0 1 .171.325c.016.093.036.306.02.472-.18 1.898-.962 6.502-1.36 8.627-.168.9-.499 1.201-.82 1.23-.696.065-1.225-.46-1.9-.902-1.056-.693-1.653-1.124-2.678-1.8-1.185-.78-.417-1.21.258-1.91.177-.184 3.247-2.977 3.307-3.23.007-.032.014-.15-.056-.212s-.174-.041-.249-.024c-.106.024-1.793 1.14-5.061 3.345-.48.33-.913.49-1.302.48-.428-.008-1.252-.241-1.865-.44-.752-.245-1.349-.374-1.297-.789.027-.216.325-.437.893-.663 3.498-1.524 5.83-2.529 6.998-3.014 3.332-1.386 4.025-1.627 4.476-1.635z"/></svg>
                    </div>
                    <span class="share-menu-option-label">Telegram</span>
                </button>
                <button class="share-menu-option share-option-email" data-action="email">
                    <div class="share-menu-option-icon">
                        <svg viewBox="0 0 24 24"><path fill="currentColor" d="M20 4H4c-1.1 0-1.99.9-1.99 2L2 18c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 4l-8 5-8-5V6l8 5 8-5v2z"/></svg>
                    </div>
                    <span class="share-menu-option-label">אימייל</span>
                </button>
                <button class="share-menu-option share-option-sms" data-action="sms">
                    <div class="share-menu-option-icon">
                        <svg viewBox="0 0 24 24"><path fill="currentColor" d="M20 2H4c-1.1 0-2 .9-2 2v18l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zm-9 9H7V9h4v2zm4 0h-2V9h2v2zm4 0h-2V9h2v2z"/></svg>
                    </div>
                    <span class="share-menu-option-label">SMS</span>
                </button>
                <button class="share-menu-option share-option-facebook" data-action="facebook">
                    <div class="share-menu-option-icon">
                        <svg viewBox="0 0 24 24"><path fill="currentColor" d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>
                    </div>
                    <span class="share-menu-option-label">Facebook</span>
                </button>
                <button class="share-menu-option share-option-twitter" data-action="twitter">
                    <div class="share-menu-option-icon">
                        <svg viewBox="0 0 24 24"><path fill="currentColor" d="M23.953 4.57a10 10 0 01-2.825.775 4.958 4.958 0 002.163-2.723c-.951.555-2.005.959-3.127 1.184a4.92 4.92 0 00-8.384 4.482C7.69 8.095 4.067 6.13 1.64 3.162a4.822 4.822 0 00-.666 2.475c0 1.71.87 3.213 2.188 4.096a4.904 4.904 0 01-2.228-.616v.06a4.923 4.923 0 003.946 4.827 4.996 4.996 0 01-2.212.085 4.936 4.936 0 004.604 3.417 9.867 9.867 0 01-6.102 2.105c-.39 0-.779-.023-1.17-.067a13.995 13.995 0 007.557 2.209c9.053 0 13.998-7.496 13.998-13.985 0-.21 0-.42-.015-.63A9.935 9.935 0 0024 4.59z"/></svg>
                    </div>
                    <span class="share-menu-option-label">Twitter</span>
                </button>
                <button class="share-menu-option share-option-copy" data-action="copy">
                    <div class="share-menu-option-icon">
                        <svg viewBox="0 0 24 24"><path fill="currentColor" d="M16 1H4c-1.1 0-2 .9-2 2v14h2V3h12V1zm3 4H8c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h11c1.1 0 2-.9 2-2V7c0-1.1-.9-2-2-2zm0 16H8V7h11v14z"/></svg>
                    </div>
                    <span class="share-menu-option-label">העתק קישור</span>
                </button>
            </div>
            <button class="share-menu-cancel">ביטול</button>
        `;

        document.body.appendChild(overlay);
        document.body.appendChild(menu);

        // הפעלת אנימציה
        requestAnimationFrame(() => {
            overlay.classList.add('active');
            menu.classList.add('active');
        });

        // סגירה
        const close = () => {
            overlay.classList.remove('active');
            menu.classList.remove('active');
            setTimeout(() => {
                overlay.remove();
                menu.remove();
            }, 300);
        };

        overlay.addEventListener('click', close);
        menu.querySelector('.share-menu-cancel').addEventListener('click', close);

        // טיפול באפשרויות
        menu.querySelectorAll('.share-menu-option').forEach(option => {
            option.addEventListener('click', () => {
                const action = option.dataset.action;
                this.handleShareAction(action, { title, text, url });
                close();
            });
        });
    }

    /**
     * טיפול בפעולת שיתוף
     */
    async handleShareAction(action, data) {
        const { title, text, url } = data;
        const shareText = [title, text, url].filter(Boolean).join('\n');

        switch (action) {
            case 'native':
                await this.webShare.shareText(data);
                break;

            case 'whatsapp':
                window.open(`https://wa.me/?text=${encodeURIComponent(shareText)}`, '_blank');
                break;

            case 'telegram':
                window.open(`https://t.me/share/url?url=${encodeURIComponent(url)}&text=${encodeURIComponent([title, text].filter(Boolean).join('\n'))}`, '_blank');
                break;

            case 'email':
                window.location.href = `mailto:?subject=${encodeURIComponent(title)}&body=${encodeURIComponent([text, url].filter(Boolean).join('\n\n'))}`;
                break;

            case 'sms':
                window.location.href = `sms:?body=${encodeURIComponent(shareText)}`;
                break;

            case 'facebook':
                window.open(`https://www.facebook.com/sharer/sharer.php?u=${encodeURIComponent(url)}`, '_blank', 'width=600,height=400');
                break;

            case 'twitter':
                window.open(`https://twitter.com/intent/tweet?text=${encodeURIComponent([title, text].filter(Boolean).join(' '))}&url=${encodeURIComponent(url)}`, '_blank', 'width=600,height=400');
                break;

            case 'copy':
                if (navigator.clipboard) {
                    await navigator.clipboard.writeText(url || shareText);
                    this.webShare.showToast('הקישור הועתק');
                }
                break;
        }
    }

    /**
     * איתור והפעלת כפתורי שיתוף קיימים
     */
    bindShareButtons() {
        document.addEventListener('click', async (e) => {
            const btn = e.target.closest('[data-share]');
            if (!btn) return;

            e.preventDefault();

            const data = {
                title: btn.dataset.shareTitle || document.title,
                text: btn.dataset.shareText || '',
                url: btn.dataset.shareUrl || window.location.href
            };

            await this.share(data);
        });
    }

    /**
     * הוספת כפתור שיתוף לאלמנט
     *
     * @param {HTMLElement} container - מיכל
     * @param {Object} options - הגדרות
     */
    addShareButton(container, options = {}) {
        const btn = this.createShareButton(options);
        container.appendChild(btn);
        return btn;
    }

    /**
     * הוספת FAB שיתוף לדף
     *
     * @param {Object|Function} dataOrCallback
     */
    addShareFAB(dataOrCallback = {}) {
        const fab = this.createShareFAB(dataOrCallback);
        document.body.appendChild(fab);
        return fab;
    }
}

// יצירת instance גלובלי
window.webShareUI = new WebShareUI();

// Export
if (typeof module !== 'undefined' && module.exports) {
    module.exports = WebShareUI;
}
