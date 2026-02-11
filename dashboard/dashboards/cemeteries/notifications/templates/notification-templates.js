/**
 * Notification Templates Manager
 * Manages different notification display types
 *
 * Types:
 * 1. INFO - Simple info notification (bottom toast, half screen)
 * 2. APPROVAL - Generic approval request (full screen with approve/reject)
 * 3. ENTITY_APPROVAL - Entity approval with full details (full screen with details + approve/reject)
 *
 * @version 1.1.0
 * @history-managed Uses browser History API for back button handling
 * @history-pattern flags (_hasHistoryState, _ignoreNextPopstate)
 * @history-status OK - Simplified from buffer pattern to flags pattern
 * @see /docs/HISTORY-MANAGEMENT.md
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

        // Handle back button
        this._popstateHandler = (e) => {
            // Skip if we triggered this popstate ourselves
            if (this._ignoreNextPopstate) {
                this._log('POPSTATE_IGNORED', { historyLength: history.length });
                this._ignoreNextPopstate = false;
                return;
            }

            this._log('POPSTATE_FIRED', {
                hasModal: !!this.activeModal,
                historyLength: history.length,
                state: e.state
            });

            if (this.activeModal) {
                // Mark that we're closing via popstate (browser already went back)
                this._closedViaPopstate = true;
                this.close();
            }
        };
        window.addEventListener('popstate', this._popstateHandler);

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

        // v1.1: Simplified history management - single pushState with cleanup on close
        try {
            this._log('BEFORE_PUSH_STATE', { notificationId: notification.id });
            history.pushState({
                notificationModal: true,
                notificationId: notification.id,
                openedAt: Date.now()
            }, '', window.location.href);
            this._hasHistoryState = true;
            this._log('AFTER_PUSH_STATE', { notificationId: notification.id, historyLength: history.length });
        } catch(e) {
            console.warn('[NotificationTemplates] Failed to push history:', e);
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
     * Handles history cleanup to prevent accumulation
     */
    close() {
        const hadModal = !!this.activeModal;
        const modalClass = this.activeModal ? this.activeModal.className : null;

        // Capture flags before reset
        const closedViaPopstate = this._closedViaPopstate;
        const hadHistoryState = this._hasHistoryState;
        this._closedViaPopstate = false;
        this._hasHistoryState = false;

        this._log('CLOSE', {
            hadModal: hadModal,
            modalClass: modalClass,
            viaPopstate: closedViaPopstate,
            hadHistoryState: hadHistoryState
        });

        if (this.activeModal) {
            this.activeModal.remove();
            this.activeModal = null;
        }
        document.body.style.overflow = '';

        // CRITICAL: History state cleanup to prevent accumulation
        if (hadHistoryState && !closedViaPopstate) {
            this._log('GOING_BACK_IN_HISTORY');
            this._ignoreNextPopstate = true;
            history.back();
        }

        // Fire close callback if exists
        if (this.callbacks.onClose) {
            this.callbacks.onClose();
            this.callbacks.onClose = null;
        }
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
