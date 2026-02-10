/**
 * Info Modal - Full screen notification display
 * For non-approval notifications (info, warning, urgent)
 *
 * @version 1.0.0
 */

window.InfoModal = {
    modalElement: null,
    currentNotification: null,
    onCloseCallback: null,

    /**
     * Initialize the modal (call once on page load)
     */
    init() {
        if (this.modalElement) return;

        const modalHtml = `
            <div id="infoModal" class="info-modal-overlay" style="display: none;">
                <div class="info-modal">
                    <div class="info-modal-header" id="infoModalHeader">
                        <span class="info-modal-counter" id="infoModalCounter"></span>
                        <span class="info-modal-icon" id="infoModalIcon"></span>
                        <h1 id="infoModalTitle"></h1>
                    </div>
                    <div class="info-modal-body">
                        <div class="info-modal-content" id="infoModalContent"></div>
                        <div class="info-modal-meta" id="infoModalMeta"></div>
                        <div class="info-modal-hint">
                            <span class="arrow">←</span>
                            <span>לחץ על כפתור החזרה להמשיך</span>
                        </div>
                        <div class="info-modal-skip" onclick="InfoModal.skipAll()">
                            דלג על כל ההתראות
                        </div>
                    </div>
                </div>
            </div>
        `;

        const style = document.createElement('style');
        style.textContent = `
            .info-modal-overlay {
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                z-index: 99999;
                display: flex;
                align-items: center;
                justify-content: center;
                animation: infoFadeIn 0.3s ease;
            }

            @keyframes infoFadeIn {
                from { opacity: 0; }
                to { opacity: 1; }
            }

            .info-modal {
                width: 100%;
                height: 100%;
                display: flex;
                flex-direction: column;
                animation: infoSlideUp 0.4s ease-out;
            }

            @keyframes infoSlideUp {
                from { transform: translateY(30px); opacity: 0; }
                to { transform: translateY(0); opacity: 1; }
            }

            .info-modal-header {
                padding: 40px 30px;
                text-align: center;
                color: white;
                position: relative;
                flex-shrink: 0;
            }

            .info-modal-counter {
                position: absolute;
                top: 16px;
                left: 16px;
                background: rgba(255, 255, 255, 0.2);
                padding: 6px 14px;
                border-radius: 20px;
                font-size: 13px;
                font-weight: 600;
            }

            .info-modal-icon {
                font-size: 64px;
                display: block;
                margin-bottom: 16px;
            }

            .info-modal-header h1 {
                font-size: 24px;
                font-weight: 700;
                margin: 0;
            }

            .info-modal-body {
                flex: 1;
                display: flex;
                flex-direction: column;
                align-items: center;
                justify-content: center;
                padding: 30px;
                background: var(--bg-primary, white);
            }

            .info-modal-content {
                font-size: 18px;
                color: var(--text-secondary, #475569);
                line-height: 1.8;
                text-align: center;
                padding: 24px;
                background: var(--bg-secondary, #f8fafc);
                border-radius: 16px;
                max-width: 400px;
                width: 100%;
                margin-bottom: 20px;
            }

            .info-modal-meta {
                font-size: 14px;
                color: var(--text-muted, #94a3b8);
                margin-bottom: 30px;
            }

            .info-modal-hint {
                text-align: center;
                padding: 18px 24px;
                background: var(--bg-tertiary, #f1f5f9);
                border-radius: 14px;
                color: var(--text-muted, #64748b);
                font-size: 15px;
                display: flex;
                align-items: center;
                justify-content: center;
                gap: 10px;
                max-width: 300px;
            }

            .info-modal-hint .arrow {
                font-size: 22px;
            }

            .info-modal-skip {
                margin-top: 20px;
                color: var(--text-muted, #94a3b8);
                font-size: 14px;
                cursor: pointer;
                text-decoration: underline;
            }

            .info-modal-skip:hover {
                color: var(--text-secondary, #64748b);
            }

            /* Type colors */
            .info-modal-overlay.type-info {
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            }
            .info-modal-overlay.type-warning {
                background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
            }
            .info-modal-overlay.type-urgent {
                background: linear-gradient(135deg, #dc2626 0%, #b91c1c 100%);
            }

            /* Dark theme */
            .dark-theme .info-modal-body {
                background: #1e293b;
            }
            .dark-theme .info-modal-content {
                background: #334155;
                color: #e2e8f0;
            }
            .dark-theme .info-modal-hint {
                background: #334155;
                color: #94a3b8;
            }
        `;
        document.head.appendChild(style);

        const div = document.createElement('div');
        div.innerHTML = modalHtml;
        document.body.appendChild(div.firstElementChild);

        this.modalElement = document.getElementById('infoModal');

        // Handle back button to close
        this._popstateHandler = (e) => {
            if (this.modalElement && this.modalElement.style.display !== 'none') {
                e.preventDefault();
                this.close();
            }
        };
        window.addEventListener('popstate', this._popstateHandler);
    },

    /**
     * Show a notification
     * @param {Object} notification - The notification data
     * @param {string} counter - e.g., "1/3"
     * @param {Function} onClose - Callback when closed
     */
    show(notification, counter, onClose) {
        this.init();
        this.currentNotification = notification;
        this.onCloseCallback = onClose;

        const typeIcons = {
            urgent: '🚨',
            warning: '⚠️',
            info: '🔔'
        };

        const type = notification.notification_type || 'info';
        const icon = typeIcons[type] || '🔔';

        // Set type class for background color
        this.modalElement.className = 'info-modal-overlay type-' + type;

        // Fill content
        document.getElementById('infoModalCounter').textContent = counter;
        document.getElementById('infoModalIcon').textContent = icon;
        document.getElementById('infoModalTitle').textContent = notification.title;
        document.getElementById('infoModalContent').innerHTML = notification.body.replace(/\n/g, '<br>');

        if (notification.created_at) {
            const date = new Date(notification.created_at);
            document.getElementById('infoModalMeta').textContent =
                date.toLocaleDateString('he-IL') + ' ' + date.toLocaleTimeString('he-IL', {hour: '2-digit', minute: '2-digit'});
        }

        // Push history state for back button
        history.pushState({ infoModal: true, notificationId: notification.id }, '', window.location.href);

        // Prevent page scroll
        document.body.style.overflow = 'hidden';

        // Show modal
        this.modalElement.style.display = 'flex';

        console.log('[InfoModal] Showing notification:', notification.id, notification.title);
    },

    /**
     * Close the modal
     */
    close() {
        if (this.modalElement) {
            this.modalElement.style.display = 'none';
        }

        document.body.style.overflow = '';

        // Mark as read
        if (this.currentNotification && this.currentNotification.id) {
            this.markAsRead(this.currentNotification.id);
        }

        console.log('[InfoModal] Closed');

        // Call the callback
        if (typeof this.onCloseCallback === 'function') {
            const callback = this.onCloseCallback;
            this.onCloseCallback = null;
            this.currentNotification = null;
            callback();
        }
    },

    /**
     * Skip all notifications
     */
    skipAll() {
        console.log('[InfoModal] Skip all clicked');
        sessionStorage.setItem('notifications_done', 'true');
        sessionStorage.removeItem('notification_next_index');
        this.onCloseCallback = null; // Don't trigger next notification
        this.close();
    },

    /**
     * Mark notification as read
     */
    async markAsRead(notificationId) {
        if (!notificationId) return;

        try {
            const response = await fetch('/dashboard/dashboards/cemeteries/my-notifications/api/my-notifications-api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                credentials: 'include',
                body: JSON.stringify({ action: 'mark_read', notification_id: notificationId })
            });
            const data = await response.json();
            if (data.success) {
                console.log('[InfoModal] Marked as read:', notificationId);
                if (typeof updateMyNotificationsCount === 'function') {
                    updateMyNotificationsCount();
                }
            }
        } catch (e) {
            console.error('[InfoModal] Failed to mark as read:', e);
        }
    },

    /**
     * Check if modal is currently open
     */
    isOpen() {
        return this.modalElement && this.modalElement.style.display !== 'none';
    }
};
