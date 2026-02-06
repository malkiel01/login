/**
 * Notification Templates Manager
 * Manages different notification display types
 *
 * Types:
 * 1. INFO - Simple info notification (bottom toast, half screen)
 * 2. APPROVAL - Generic approval request (full screen with approve/reject)
 * 3. ENTITY_APPROVAL - Entity approval with full details (full screen with details + approve/reject)
 *
 * @version 1.0.0
 */

window.NotificationTemplates = {
    // Template type constants
    TYPES: {
        INFO: 'info',
        APPROVAL: 'approval',
        ENTITY_APPROVAL: 'entity_approval'
    },

    // Track active modals
    activeModal: null,
    callbacks: {},

    /**
     * Initialize the template system
     */
    init() {
        // Add global styles
        this.addGlobalStyles();
        console.log('[NotificationTemplates] Initialized');
        this._log('INIT', { message: 'NotificationTemplates initialized' });
    },

    /**
     * Debug logger - sends to server
     */
    _log(event, data) {
        const logEntry = {
            e: 'NOTIF_' + event,
            t: Date.now(),
            ts: new Date().toISOString(),
            d: data,
            hasModal: !!this.activeModal,
            modalClass: this.activeModal ? this.activeModal.className : null
        };
        console.log('[NotificationTemplates]', event, logEntry);
        navigator.sendBeacon && navigator.sendBeacon(
            '/dashboard/dashboards/cemeteries/api/debug-log.php',
            JSON.stringify(logEntry)
        );
    },

    /**
     * Show a notification based on its type
     * @param {Object} notification - Notification data
     * @param {Object} options - Display options
     */
    async show(notification, options = {}) {
        const type = this.detectType(notification);
        console.log('[NotificationTemplates] Showing notification type:', type);
        this._log('SHOW', { type: type, notificationId: notification.id, title: notification.title });

        // v9: גישה חדשה למניעת Chrome back button abuse
        // במקום pushState חדש בכל פעם, נשתמש ב-forward() אם יש entry קיים
        try {
            const modalState = {
                modal: true,
                notificationId: notification.id,
                openedAt: Date.now(),
                isNotificationModal: true
            };

            const navIndex = window.navigation ? window.navigation.currentEntry.index : -1;
            const canGoForward = window.navigation ? window.navigation.canGoForward : false;

            if (canGoForward && navIndex === 1) {
                // v21: יש entry קדימה - נלך אליו ונעדכן, ואז נוסיף buffer
                this._log('HISTORY_FORWARD', {
                    notificationId: notification.id,
                    from: navIndex,
                    reason: 'reusing existing entry'
                });

                // דגל למניעת handleBack על forward
                window.isDoingProgrammaticForward = true;

                // קודם נלך קדימה
                history.forward();

                // נמתין קצת ואז נעדכן את ה-state ונאפס את הדגל
                setTimeout(() => {
                    window.isDoingProgrammaticForward = false;
                    history.replaceState(modalState, '', '#modal');
                    this._log('HISTORY_REPLACE_AFTER_FORWARD', {
                        notificationId: notification.id,
                        navIndex: window.navigation ? window.navigation.currentEntry.index : -1
                    });

                    // v21: הוסף buffer גם כאן
                    setTimeout(() => {
                        const bufferState = {
                            buffer: true,
                            forNotification: notification.id,
                            modalOpenedAt: modalState.openedAt
                        };
                        history.pushState(bufferState, '', '#buffer');
                        this._log('HISTORY_PUSH_BUFFER_AFTER_FWD', {
                            notificationId: notification.id,
                            historyLength: history.length,
                            navIndex: window.navigation ? window.navigation.currentEntry.index : -1
                        });
                    }, 100);
                }, 50);

            } else if (navIndex <= 1) {
                // v21: Buffer Entry Pattern!
                // הבעיה: Chrome Android מדלג על JS events כשהוא מחליט לסגור את האפליקציה
                // הפתרון: הוספת entry נוסף (#buffer) אחרי #modal
                // כך back הולך קודם ל-buffer, ואנחנו יכולים לטפל בזה

                // שלב 1: push #modal
                history.pushState(modalState, '', '#modal');
                this._log('HISTORY_PUSH_MODAL', {
                    notificationId: notification.id,
                    historyLength: history.length,
                    navIndex: window.navigation ? window.navigation.currentEntry.index : -1
                });

                // שלב 2: push #buffer (entry נוסף כ"כרית ביטחון")
                setTimeout(() => {
                    const bufferState = {
                        buffer: true,
                        forNotification: notification.id,
                        modalOpenedAt: modalState.openedAt
                    };
                    history.pushState(bufferState, '', '#buffer');
                    this._log('HISTORY_PUSH_BUFFER', {
                        notificationId: notification.id,
                        historyLength: history.length,
                        navIndex: window.navigation ? window.navigation.currentEntry.index : -1
                    });
                }, 100);

            } else {
                // כבר ב-index 2 או יותר - רק נעדכן ונוסיף buffer
                history.replaceState(modalState, '', '#modal');
                this._log('HISTORY_REPLACE', {
                    notificationId: notification.id,
                    navIndex: navIndex
                });

                // גם כאן נוסיף buffer
                setTimeout(() => {
                    const bufferState = {
                        buffer: true,
                        forNotification: notification.id,
                        modalOpenedAt: modalState.openedAt
                    };
                    history.pushState(bufferState, '', '#buffer');
                    this._log('HISTORY_PUSH_BUFFER_AFTER_REPLACE', {
                        notificationId: notification.id,
                        historyLength: history.length,
                        navIndex: window.navigation ? window.navigation.currentEntry.index : -1
                    });
                }, 100);
            }
        } catch(e) {
            console.warn('[NotificationTemplates] Failed to update history:', e);
            this._log('HISTORY_ERROR', { error: e.message });
        }

        switch (type) {
            case this.TYPES.INFO:
                return this.showInfoNotification(notification, options);
            case this.TYPES.ENTITY_APPROVAL:
                return this.showEntityApprovalNotification(notification, options);
            case this.TYPES.APPROVAL:
            default:
                return this.showApprovalNotification(notification, options);
        }
    },

    /**
     * Detect notification type based on its properties
     * @param {Object} notification
     * @returns {string} type
     */
    detectType(notification) {
        // If has URL pointing to entity-approve.php - it's an entity approval
        if (notification.url && notification.url.includes('entity-approve.php')) {
            return this.TYPES.ENTITY_APPROVAL;
        }

        // If requires approval - it's a generic approval
        if (notification.requires_approval || notification.requiresApproval) {
            return this.TYPES.APPROVAL;
        }

        // Default to info
        return this.TYPES.INFO;
    },

    /**
     * Add global styles for all templates
     */
    addGlobalStyles() {
        if (document.getElementById('notificationTemplatesStyles')) return;

        const style = document.createElement('style');
        style.id = 'notificationTemplatesStyles';
        style.textContent = `
            /* ============================================
               Notification Templates - Global Styles
               ============================================ */

            /* Overlay base */
            .notification-overlay {
                position: fixed;
                z-index: 99999;
                display: flex;
                animation: ntFadeIn 0.2s ease;
            }

            @keyframes ntFadeIn {
                from { opacity: 0; }
                to { opacity: 1; }
            }

            @keyframes ntSlideUp {
                from { transform: translateY(100%); }
                to { transform: translateY(0); }
            }

            @keyframes ntSlideDown {
                from { transform: translateY(-20px); opacity: 0; }
                to { transform: translateY(0); opacity: 1; }
            }

            /* Button styles */
            .nt-btn {
                padding: 14px 24px;
                border: none;
                border-radius: 12px;
                font-size: 16px;
                font-weight: 600;
                cursor: pointer;
                display: flex;
                align-items: center;
                justify-content: center;
                gap: 8px;
                transition: all 0.2s ease;
                flex: 1;
            }

            .nt-btn:disabled {
                opacity: 0.6;
                cursor: not-allowed;
            }

            .nt-btn-approve {
                background: linear-gradient(135deg, #10b981 0%, #059669 100%);
                color: white;
            }

            .nt-btn-approve:hover:not(:disabled) {
                transform: translateY(-2px);
                box-shadow: 0 4px 12px rgba(16, 185, 129, 0.4);
            }

            .nt-btn-reject {
                background: var(--bg-tertiary, #f1f5f9);
                color: var(--text-muted, #64748b);
            }

            .nt-btn-reject:hover:not(:disabled) {
                background: var(--border-color, #e2e8f0);
            }

            .nt-btn-close {
                background: transparent;
                color: var(--text-muted, #64748b);
                padding: 8px 16px;
            }

            .nt-btn-close:hover {
                background: var(--bg-tertiary, #f1f5f9);
            }

            /* Loading spinner */
            .nt-spinner {
                width: 24px;
                height: 24px;
                border: 3px solid var(--border-color, #e2e8f0);
                border-top-color: var(--primary-color, #667eea);
                border-radius: 50%;
                animation: ntSpin 0.8s linear infinite;
            }

            @keyframes ntSpin {
                to { transform: rotate(360deg); }
            }

            /* RTL Support */
            [dir="rtl"] .nt-btn-icon-left {
                order: 1;
            }
        `;
        document.head.appendChild(style);
    },

    /**
     * Close any active modal
     */
    close() {
        const hadModal = !!this.activeModal;
        const modalClass = this.activeModal ? this.activeModal.className : null;

        this._log('CLOSE_START', { hadModal: hadModal, modalClass: modalClass });

        if (this.activeModal) {
            this.activeModal.remove();
            this.activeModal = null;
        }
        document.body.style.overflow = '';

        // Fire close callback if exists
        if (this.callbacks.onClose) {
            this.callbacks.onClose();
            this.callbacks.onClose = null;
        }

        this._log('CLOSE_DONE', { hadModal: hadModal, modalClass: modalClass });
    },

    /**
     * Set callback for modal events
     */
    onClose(callback) {
        this.callbacks.onClose = callback;
    }
};

// Initialize on load
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => NotificationTemplates.init());
} else {
    NotificationTemplates.init();
}
