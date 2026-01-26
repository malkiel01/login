/**
 * Quick Share UI - ממשק שיתוף מהיר
 * מציג רשימת פריטים אחרונים לשיתוף מהיר
 *
 * @version 1.0.0
 */

class QuickShareUI {
    constructor() {
        this.quickShare = window.quickShare || new QuickShare();
        this.currentShareData = null;
        this.onShareComplete = null;

        this.init();
    }

    /**
     * אתחול
     */
    init() {
        this.injectStyles();
    }

    /**
     * הוספת סגנונות
     */
    injectStyles() {
        if (document.getElementById('quick-share-ui-styles')) return;

        const styles = document.createElement('style');
        styles.id = 'quick-share-ui-styles';
        styles.textContent = `
            /* Quick Share Sheet */
            .quick-share-sheet {
                position: fixed;
                bottom: 0;
                left: 0;
                right: 0;
                background: white;
                border-radius: 24px 24px 0 0;
                box-shadow: 0 -10px 40px rgba(0,0,0,0.2);
                transform: translateY(100%);
                transition: transform 0.35s cubic-bezier(0.4, 0, 0.2, 1);
                z-index: 10001;
                max-height: 85vh;
                display: flex;
                flex-direction: column;
            }

            .quick-share-sheet.active {
                transform: translateY(0);
            }

            .quick-share-handle {
                width: 40px;
                height: 4px;
                background: #e0e0e0;
                border-radius: 2px;
                margin: 12px auto;
                flex-shrink: 0;
            }

            .quick-share-header {
                padding: 0 20px 15px;
                border-bottom: 1px solid #f0f0f0;
                flex-shrink: 0;
            }

            .quick-share-title {
                font-size: 20px;
                font-weight: 600;
                color: #333;
                text-align: center;
            }

            .quick-share-subtitle {
                font-size: 13px;
                color: #999;
                text-align: center;
                margin-top: 4px;
            }

            /* תצוגת התוכן המשותף */
            .quick-share-preview {
                padding: 12px 20px;
                background: #f8f9fa;
                margin: 0 20px 15px;
                border-radius: 12px;
                flex-shrink: 0;
            }

            .quick-share-preview-text {
                font-size: 14px;
                color: #333;
                display: -webkit-box;
                -webkit-line-clamp: 2;
                -webkit-box-orient: vertical;
                overflow: hidden;
            }

            .quick-share-preview-url {
                font-size: 12px;
                color: #007AFF;
                margin-top: 4px;
                word-break: break-all;
            }

            /* פריטים אחרונים */
            .quick-share-section {
                padding: 15px 20px 10px;
                flex-shrink: 0;
            }

            .quick-share-section-title {
                font-size: 13px;
                font-weight: 600;
                color: #999;
                text-transform: uppercase;
                letter-spacing: 0.5px;
                margin-bottom: 12px;
            }

            .quick-share-items {
                display: flex;
                overflow-x: auto;
                gap: 16px;
                padding-bottom: 10px;
                scroll-snap-type: x mandatory;
                -webkit-overflow-scrolling: touch;
            }

            .quick-share-items::-webkit-scrollbar {
                display: none;
            }

            .quick-share-item {
                display: flex;
                flex-direction: column;
                align-items: center;
                min-width: 72px;
                padding: 12px 8px;
                border-radius: 16px;
                cursor: pointer;
                transition: all 0.2s ease;
                scroll-snap-align: start;
                background: none;
                border: none;
            }

            .quick-share-item:hover {
                background: #f5f5f5;
            }

            .quick-share-item:active {
                transform: scale(0.95);
            }

            .quick-share-item-icon {
                width: 52px;
                height: 52px;
                border-radius: 50%;
                display: flex;
                align-items: center;
                justify-content: center;
                margin-bottom: 8px;
                position: relative;
                overflow: hidden;
            }

            .quick-share-item-icon svg {
                width: 24px;
                height: 24px;
                fill: white;
            }

            .quick-share-item-icon img {
                width: 100%;
                height: 100%;
                object-fit: cover;
            }

            .quick-share-item-name {
                font-size: 11px;
                color: #333;
                text-align: center;
                max-width: 72px;
                white-space: nowrap;
                overflow: hidden;
                text-overflow: ellipsis;
            }

            /* תפריט אפשרויות נוספות */
            .quick-share-more {
                flex: 1;
                overflow-y: auto;
                padding: 0 20px;
            }

            .quick-share-option {
                display: flex;
                align-items: center;
                gap: 14px;
                padding: 14px 0;
                border-bottom: 1px solid #f0f0f0;
                cursor: pointer;
                transition: background 0.2s ease;
                background: none;
                border: none;
                width: 100%;
                text-align: right;
            }

            .quick-share-option:hover {
                background: #f8f9fa;
                margin: 0 -20px;
                padding: 14px 20px;
            }

            .quick-share-option:last-child {
                border-bottom: none;
            }

            .quick-share-option-icon {
                width: 40px;
                height: 40px;
                border-radius: 10px;
                display: flex;
                align-items: center;
                justify-content: center;
                color: white;
                flex-shrink: 0;
            }

            .quick-share-option-icon svg {
                width: 20px;
                height: 20px;
                fill: currentColor;
            }

            .quick-share-option-info {
                flex: 1;
            }

            .quick-share-option-name {
                font-size: 15px;
                font-weight: 500;
                color: #333;
            }

            .quick-share-option-desc {
                font-size: 12px;
                color: #999;
                margin-top: 2px;
            }

            /* כפתור ביטול */
            .quick-share-cancel {
                margin: 15px 20px;
                padding: 16px;
                background: #f5f5f5;
                border: none;
                border-radius: 14px;
                font-size: 16px;
                font-weight: 500;
                color: #007AFF;
                cursor: pointer;
                flex-shrink: 0;
                margin-bottom: max(15px, env(safe-area-inset-bottom));
            }

            .quick-share-cancel:hover {
                background: #ebebeb;
            }

            /* Overlay */
            .quick-share-overlay {
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

            .quick-share-overlay.active {
                opacity: 1;
                visibility: visible;
            }

            /* Loading state */
            .quick-share-item.loading .quick-share-item-icon::after {
                content: '';
                position: absolute;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background: rgba(255,255,255,0.8);
                display: flex;
                align-items: center;
                justify-content: center;
            }

            .quick-share-item.loading .quick-share-item-icon::before {
                content: '';
                position: absolute;
                width: 20px;
                height: 20px;
                border: 2px solid #007AFF;
                border-top-color: transparent;
                border-radius: 50%;
                animation: spin 0.8s linear infinite;
                z-index: 1;
            }

            /* Success animation */
            .quick-share-item.success .quick-share-item-icon {
                animation: successPulse 0.5s ease;
            }

            @keyframes spin {
                to { transform: rotate(360deg); }
            }

            @keyframes successPulse {
                0%, 100% { transform: scale(1); }
                50% { transform: scale(1.15); }
            }

            /* Toast */
            .quick-share-toast {
                position: fixed;
                bottom: 100px;
                left: 50%;
                transform: translateX(-50%);
                background: #333;
                color: white;
                padding: 12px 24px;
                border-radius: 25px;
                font-size: 14px;
                z-index: 10002;
                animation: fadeInUp 0.3s ease;
            }

            @keyframes fadeInUp {
                from {
                    opacity: 0;
                    transform: translateX(-50%) translateY(20px);
                }
                to {
                    opacity: 1;
                    transform: translateX(-50%) translateY(0);
                }
            }
        `;

        document.head.appendChild(styles);
    }

    /**
     * פתיחת מסך שיתוף מהיר
     *
     * @param {Object} shareData - נתוני השיתוף
     * @param {Function} onComplete - callback אחרי שיתוף מוצלח
     */
    async open(shareData, onComplete = null) {
        this.currentShareData = shareData;
        this.onShareComplete = onComplete;

        // טען פריטים אחרונים
        await this.quickShare.fetchRecentItems();
        const recentItems = this.quickShare.getRecentItems();

        // בנה את ה-UI
        this.render(recentItems);
    }

    /**
     * רנדור ה-UI
     */
    render(recentItems) {
        // Overlay
        const overlay = document.createElement('div');
        overlay.className = 'quick-share-overlay';

        // Sheet
        const sheet = document.createElement('div');
        sheet.className = 'quick-share-sheet';

        sheet.innerHTML = `
            <div class="quick-share-handle"></div>

            <div class="quick-share-header">
                <div class="quick-share-title">שיתוף מהיר</div>
                <div class="quick-share-subtitle">בחר לאן לשתף</div>
            </div>

            ${this.renderPreview()}

            ${recentItems.length > 0 ? `
                <div class="quick-share-section">
                    <div class="quick-share-section-title">אחרונים</div>
                    <div class="quick-share-items" id="recent-items">
                        ${recentItems.map(item => this.renderItem(item)).join('')}
                    </div>
                </div>
            ` : ''}

            <div class="quick-share-more">
                ${this.renderOptions()}
            </div>

            <button class="quick-share-cancel">ביטול</button>
        `;

        document.body.appendChild(overlay);
        document.body.appendChild(sheet);

        // הפעלת אנימציה
        requestAnimationFrame(() => {
            overlay.classList.add('active');
            sheet.classList.add('active');
        });

        // אירועים
        this.bindEvents(overlay, sheet);
    }

    /**
     * רנדור תצוגה מקדימה
     */
    renderPreview() {
        const { title, text, url, files } = this.currentShareData;

        if (!title && !text && !url && (!files || files.length === 0)) {
            return '';
        }

        let content = '';
        if (title || text) {
            content += `<div class="quick-share-preview-text">${title || ''} ${text || ''}</div>`;
        }
        if (url) {
            content += `<div class="quick-share-preview-url">${url}</div>`;
        }
        if (files && files.length > 0) {
            content += `<div class="quick-share-preview-text">${files.length} קבצים</div>`;
        }

        return `<div class="quick-share-preview">${content}</div>`;
    }

    /**
     * רנדור פריט
     */
    renderItem(item) {
        const icon = this.quickShare.getItemIcon(item);
        const bgColor = item.color || '#007AFF';

        return `
            <button class="quick-share-item" data-id="${item.id}" data-type="${item.type}">
                <div class="quick-share-item-icon" style="background: ${bgColor}">
                    ${item.image ? `<img src="${item.image}" alt="">` : icon}
                </div>
                <span class="quick-share-item-name">${item.name}</span>
            </button>
        `;
    }

    /**
     * רנדור אפשרויות נוספות
     */
    renderOptions() {
        const options = [
            {
                id: 'notes',
                name: 'הערה חדשה',
                desc: 'צור הערה חדשה עם התוכן',
                icon: `<svg viewBox="0 0 24 24"><path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm-5 14H7v-2h7v2zm3-4H7v-2h10v2zm0-4H7V7h10v2z"/></svg>`,
                color: '#FF9500'
            },
            {
                id: 'files',
                name: 'שמור בקבצים',
                desc: 'העלה לתיקיית הקבצים שלי',
                icon: `<svg viewBox="0 0 24 24"><path d="M10 4H4c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V8c0-1.1-.9-2-2-2h-8l-2-2z"/></svg>`,
                color: '#007AFF'
            },
            {
                id: 'copy',
                name: 'העתק',
                desc: 'העתק את התוכן ללוח',
                icon: `<svg viewBox="0 0 24 24"><path d="M16 1H4c-1.1 0-2 .9-2 2v14h2V3h12V1zm3 4H8c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h11c1.1 0 2-.9 2-2V7c0-1.1-.9-2-2-2zm0 16H8V7h11v14z"/></svg>`,
                color: '#8E8E93'
            },
            {
                id: 'more',
                name: 'עוד אפשרויות...',
                desc: 'תפריט שיתוף מלא',
                icon: `<svg viewBox="0 0 24 24"><path d="M18 16.08c-.76 0-1.44.3-1.96.77L8.91 12.7c.05-.23.09-.46.09-.7s-.04-.47-.09-.7l7.05-4.11c.54.5 1.25.81 2.04.81 1.66 0 3-1.34 3-3s-1.34-3-3-3-3 1.34-3 3c0 .24.04.47.09.7L8.04 9.81C7.5 9.31 6.79 9 6 9c-1.66 0-3 1.34-3 3s1.34 3 3 3c.79 0 1.5-.31 2.04-.81l7.12 4.16c-.05.21-.08.43-.08.65 0 1.61 1.31 2.92 2.92 2.92s2.92-1.31 2.92-2.92-1.31-2.92-2.92-2.92z"/></svg>`,
                color: '#5856D6'
            }
        ];

        return options.map(opt => `
            <button class="quick-share-option" data-action="${opt.id}">
                <div class="quick-share-option-icon" style="background: ${opt.color}">
                    ${opt.icon}
                </div>
                <div class="quick-share-option-info">
                    <div class="quick-share-option-name">${opt.name}</div>
                    <div class="quick-share-option-desc">${opt.desc}</div>
                </div>
            </button>
        `).join('');
    }

    /**
     * קישור אירועים
     */
    bindEvents(overlay, sheet) {
        const close = () => {
            overlay.classList.remove('active');
            sheet.classList.remove('active');
            setTimeout(() => {
                overlay.remove();
                sheet.remove();
            }, 350);
        };

        // סגירה
        overlay.addEventListener('click', close);
        sheet.querySelector('.quick-share-cancel').addEventListener('click', close);

        // שיתוף לפריט אחרון
        sheet.querySelectorAll('.quick-share-item').forEach(item => {
            item.addEventListener('click', async () => {
                const id = item.dataset.id;
                const type = item.dataset.type;

                item.classList.add('loading');

                const targetItem = this.quickShare.getRecentItems().find(i => i.id == id && i.type === type);
                if (targetItem) {
                    const result = await this.quickShare.quickShareTo(targetItem, this.currentShareData);

                    item.classList.remove('loading');

                    if (result.success) {
                        item.classList.add('success');
                        this.showToast(`שותף ל${targetItem.name}`);

                        setTimeout(() => {
                            close();
                            if (this.onShareComplete) {
                                this.onShareComplete(result);
                            }
                        }, 500);
                    } else {
                        this.showToast('שגיאה בשיתוף');
                    }
                }
            });
        });

        // אפשרויות נוספות
        sheet.querySelectorAll('.quick-share-option').forEach(opt => {
            opt.addEventListener('click', async () => {
                const action = opt.dataset.action;
                await this.handleAction(action, close);
            });
        });
    }

    /**
     * טיפול בפעולות
     */
    async handleAction(action, closeCallback) {
        const { title, text, url, files } = this.currentShareData;
        const shareText = [title, text, url].filter(Boolean).join('\n');

        switch (action) {
            case 'notes':
                // פתח טופס הערה חדשה
                window.location.href = `/notes/new.php?text=${encodeURIComponent(shareText)}`;
                break;

            case 'files':
                // פתח העלאת קבצים
                window.location.href = `/files/?upload=1`;
                break;

            case 'copy':
                if (navigator.clipboard) {
                    await navigator.clipboard.writeText(url || shareText);
                    this.showToast('הועתק ללוח');
                    closeCallback();
                }
                break;

            case 'more':
                closeCallback();
                // פתח את תפריט השיתוף המלא
                if (window.webShareUI) {
                    window.webShareUI.share(this.currentShareData);
                } else if (navigator.share) {
                    navigator.share(this.currentShareData);
                }
                break;
        }
    }

    /**
     * הצגת toast
     */
    showToast(message) {
        const toast = document.createElement('div');
        toast.className = 'quick-share-toast';
        toast.textContent = message;
        document.body.appendChild(toast);

        setTimeout(() => {
            toast.style.animation = 'fadeOut 0.3s ease';
            setTimeout(() => toast.remove(), 300);
        }, 2000);
    }
}

// יצירת instance גלובלי
window.quickShareUI = new QuickShareUI();

// Export
if (typeof module !== 'undefined' && module.exports) {
    module.exports = QuickShareUI;
}
