/**
 * Notification Modal - Dedicated screen for viewing notifications
 * Opens above the app when a notification is clicked
 *
 * @version 1.0.0
 */

window.NotificationModal = {
    modalElement: null,
    currentData: null,

    /**
     * Initialize the modal (call once on page load)
     */
    init() {
        if (this.modalElement) return;

        // Create modal HTML
        const modalHtml = `
            <div id="notificationModal" class="notification-modal-overlay" style="display: none;">
                <div class="notification-modal">
                    <div class="notification-modal-header">
                        <h3 class="notification-modal-title">התראה</h3>
                        <button type="button" class="notification-modal-close" onclick="NotificationModal.dismiss()">×</button>
                    </div>
                    <div class="notification-modal-body">
                        <div class="notification-icon" id="notificationIcon">🔔</div>
                        <h4 id="notificationTitle"></h4>
                        <p id="notificationBody"></p>
                        <div class="notification-time" id="notificationTime"></div>
                    </div>
                    <div class="notification-modal-footer">
                        <button type="button" class="btn-notification-action" id="btnNotificationAction" onclick="NotificationModal.openUrl()">
                            <span>פתח</span>
                        </button>
                        <button type="button" class="btn-notification-close" onclick="NotificationModal.close()">
                            <span>סגור</span>
                        </button>
                    </div>
                </div>
            </div>
        `;

        // Add CSS
        const style = document.createElement('style');
        style.id = 'notificationModalStyles';
        style.textContent = `
            .notification-modal-overlay {
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background: rgba(0, 0, 0, 0.7);
                z-index: 999999;
                display: flex;
                align-items: center;
                justify-content: center;
                padding: 20px;
                animation: notificationFadeIn 0.3s ease;
            }

            @keyframes notificationFadeIn {
                from { opacity: 0; }
                to { opacity: 1; }
            }

            .notification-modal {
                background: white;
                border-radius: 20px;
                box-shadow: 0 25px 80px rgba(0, 0, 0, 0.4);
                width: 100%;
                max-width: 420px;
                overflow: hidden;
                animation: notificationSlideUp 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
            }

            @keyframes notificationSlideUp {
                from {
                    transform: translateY(50px) scale(0.95);
                    opacity: 0;
                }
                to {
                    transform: translateY(0) scale(1);
                    opacity: 1;
                }
            }

            .notification-modal-header {
                display: flex;
                justify-content: space-between;
                align-items: center;
                padding: 18px 24px;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                color: white;
            }

            .notification-modal-title {
                margin: 0;
                font-size: 20px;
                font-weight: 600;
            }

            .notification-modal-close {
                background: rgba(255, 255, 255, 0.2);
                border: none;
                color: white;
                font-size: 24px;
                cursor: pointer;
                width: 36px;
                height: 36px;
                border-radius: 50%;
                display: flex;
                align-items: center;
                justify-content: center;
                transition: all 0.2s;
            }

            .notification-modal-close:hover {
                background: rgba(255, 255, 255, 0.3);
                transform: rotate(90deg);
            }

            .notification-modal-body {
                padding: 32px 24px;
                text-align: center;
            }

            .notification-icon {
                font-size: 64px;
                margin-bottom: 20px;
                animation: notificationBounce 0.6s ease;
            }

            @keyframes notificationBounce {
                0%, 100% { transform: scale(1); }
                50% { transform: scale(1.1); }
            }

            .notification-modal-body h4 {
                margin: 0 0 16px;
                font-size: 24px;
                color: #1e293b;
                font-weight: 700;
            }

            .notification-modal-body p {
                margin: 0;
                color: #475569;
                font-size: 16px;
                line-height: 1.7;
            }

            .notification-time {
                margin-top: 20px;
                font-size: 13px;
                color: #94a3b8;
            }

            .notification-modal-footer {
                display: flex;
                gap: 12px;
                padding: 20px 24px;
                background: #f8fafc;
                border-top: 1px solid #e2e8f0;
            }

            .notification-modal-footer button {
                flex: 1;
                padding: 14px 20px;
                border: none;
                border-radius: 12px;
                font-size: 16px;
                font-weight: 600;
                cursor: pointer;
                transition: all 0.2s;
            }

            .btn-notification-action {
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                color: white;
            }

            .btn-notification-action:hover {
                transform: translateY(-2px);
                box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4);
            }

            .btn-notification-close {
                background: #e2e8f0;
                color: #475569;
            }

            .btn-notification-close:hover {
                background: #cbd5e1;
            }

            /* Notification types */
            .notification-modal.type-warning .notification-modal-header {
                background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
            }

            .notification-modal.type-urgent .notification-modal-header {
                background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
            }

            .notification-modal.type-success .notification-modal-header {
                background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            }

            /* RTL Support */
            [dir="rtl"] .notification-modal-footer {
                flex-direction: row-reverse;
            }

            /* Dark Theme Support */
            .dark-theme .notification-modal,
            [data-theme="dark"] .notification-modal {
                background: #1e293b;
            }

            .dark-theme .notification-modal-body h4,
            [data-theme="dark"] .notification-modal-body h4 {
                color: #f1f5f9;
            }

            .dark-theme .notification-modal-body p,
            [data-theme="dark"] .notification-modal-body p {
                color: #cbd5e1;
            }

            .dark-theme .notification-time,
            [data-theme="dark"] .notification-time {
                color: #64748b;
            }

            .dark-theme .notification-modal-footer,
            [data-theme="dark"] .notification-modal-footer {
                background: #0f172a;
                border-top-color: #334155;
            }

            .dark-theme .btn-notification-close,
            [data-theme="dark"] .btn-notification-close {
                background: #334155;
                color: #e2e8f0;
            }

            .dark-theme .btn-notification-close:hover,
            [data-theme="dark"] .btn-notification-close:hover {
                background: #475569;
            }

            /* Color Scheme Support */
            .color-scheme-blue .notification-modal-header,
            .color-scheme-blue .btn-notification-action {
                background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);
            }

            .color-scheme-green .notification-modal-header,
            .color-scheme-green .btn-notification-action {
                background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            }

            .color-scheme-red .notification-modal-header,
            .color-scheme-red .btn-notification-action {
                background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
            }

            .color-scheme-orange .notification-modal-header,
            .color-scheme-orange .btn-notification-action {
                background: linear-gradient(135deg, #f97316 0%, #ea580c 100%);
            }

            .color-scheme-pink .notification-modal-header,
            .color-scheme-pink .btn-notification-action {
                background: linear-gradient(135deg, #ec4899 0%, #db2777 100%);
            }

            .color-scheme-teal .notification-modal-header,
            .color-scheme-teal .btn-notification-action {
                background: linear-gradient(135deg, #14b8a6 0%, #0d9488 100%);
            }

            /* Mobile optimization */
            @media (max-width: 480px) {
                .notification-modal-overlay {
                    padding: 0;
                    align-items: flex-end;
                }

                .notification-modal {
                    max-width: 100%;
                    border-radius: 20px 20px 0 0;
                    max-height: 90vh;
                }

                .notification-modal-body {
                    padding: 24px 20px;
                }

                .notification-icon {
                    font-size: 56px;
                }

                .notification-modal-body h4 {
                    font-size: 22px;
                }
            }
        `;
        document.head.appendChild(style);

        // Add modal to body
        const div = document.createElement('div');
        div.innerHTML = modalHtml;
        document.body.appendChild(div.firstElementChild);

        this.modalElement = document.getElementById('notificationModal');

        // Close on overlay click (dismiss only, don't mark as read)
        this.modalElement.addEventListener('click', (e) => {
            if (e.target === this.modalElement) {
                this.dismiss();
            }
        });

        // Close on Escape key (dismiss only, don't mark as read)
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && this.modalElement && this.modalElement.style.display !== 'none') {
                this.dismiss();
            }
        });

        // X button - dismiss only (don't mark as read)
        const xButton = this.modalElement.querySelector('.notification-modal-close');
        if (xButton) {
            xButton.addEventListener('click', (e) => {
                e.preventDefault();
                e.stopPropagation();
                console.log('[NotificationModal] X button clicked - dismiss only');
                this.dismiss();
            });
        }

        // "סגור" button - close AND mark as read
        const closeButton = this.modalElement.querySelector('.btn-notification-close');
        if (closeButton) {
            closeButton.addEventListener('click', (e) => {
                e.preventDefault();
                e.stopPropagation();
                console.log('[NotificationModal] Close button clicked - mark as read');
                this.close();
            });
        }
    },

    /**
     * Show the notification modal
     * @param {Object} data - Notification data
     */
    show(data) {
        console.log('[NotificationModal] show() called with notificationId:', data?.notificationId);

        this.init();
        this.currentData = data;

        const title = data.title || 'התראה חדשה';
        const body = data.body || '';
        const url = data.url || '/dashboard/';
        const isApproval = data.isApproval || false;

        console.log('[NotificationModal] Stored currentData with notificationId:', this.currentData.notificationId);

        // If this is an approval notification, use ApprovalModal instead
        if (isApproval && data.notificationId && typeof ApprovalModal !== 'undefined') {
            ApprovalModal.show(data.notificationId);
            return;
        }

        // Set content
        document.getElementById('notificationTitle').textContent = title;
        document.getElementById('notificationBody').textContent = body;

        // Set icon based on notification type or content
        const iconEl = document.getElementById('notificationIcon');
        if (title.includes('אזהרה') || title.includes('warning')) {
            iconEl.textContent = '⚠️';
            this.modalElement.querySelector('.notification-modal').classList.add('type-warning');
        } else if (title.includes('דחוף') || title.includes('urgent')) {
            iconEl.textContent = '🚨';
            this.modalElement.querySelector('.notification-modal').classList.add('type-urgent');
        } else if (title.includes('הצלחה') || title.includes('success')) {
            iconEl.textContent = '✅';
            this.modalElement.querySelector('.notification-modal').classList.add('type-success');
        } else {
            iconEl.textContent = '🔔';
            this.modalElement.querySelector('.notification-modal').className = 'notification-modal';
        }

        // Set time
        const timeEl = document.getElementById('notificationTime');
        timeEl.textContent = 'עכשיו';

        // Update action button based on URL
        const actionBtn = document.getElementById('btnNotificationAction');
        if (url && url !== '/dashboard/' && url !== '/') {
            actionBtn.style.display = 'flex';
            actionBtn.textContent = 'פתח';
        } else {
            actionBtn.style.display = 'none';
        }

        // Prevent page scroll
        document.body.style.overflow = 'hidden';

        // Show modal
        this.modalElement.style.display = 'flex';

        console.log('[NotificationModal] Showing notification:', { title, body, url });
    },

    /**
     * Open the notification URL
     */
    openUrl() {
        if (this.currentData && this.currentData.url) {
            this.close();

            // Navigate to URL
            if (this.currentData.url.startsWith('/')) {
                window.location.href = this.currentData.url;
            } else {
                window.open(this.currentData.url, '_blank');
            }
        }
    },

    /**
     * Dismiss the modal without marking as read
     * Used for X button, Escape key, and overlay click
     */
    dismiss() {
        console.log('[NotificationModal] dismiss() called - closing without marking as read');

        if (this.modalElement) {
            this.modalElement.style.display = 'none';
        }
        // Restore page scroll
        document.body.style.overflow = '';
        this.currentData = null;
    },

    /**
     * Close the modal and mark as read
     * Used for "סגור" button only
     */
    close() {
        console.log('[NotificationModal] close() called, notificationId:', this.currentData?.notificationId);

        // Mark notification as read if we have an ID
        if (this.currentData && this.currentData.notificationId) {
            console.log('[NotificationModal] Marking notification as read:', this.currentData.notificationId);
            this.markAsRead(this.currentData.notificationId);
        } else {
            console.warn('[NotificationModal] No notificationId in currentData');
        }

        if (this.modalElement) {
            this.modalElement.style.display = 'none';
        }
        // Restore page scroll
        document.body.style.overflow = '';
        this.currentData = null;
    },

    /**
     * Mark notification as read
     * @param {number|string} notificationId
     */
    async markAsRead(notificationId) {
        console.log('[NotificationModal] markAsRead called with ID:', notificationId);

        if (!notificationId) {
            console.warn('[NotificationModal] No notification ID provided');
            return;
        }

        // קריאה לשני ה-APIs במקביל כדי לוודא שאחד מהם יעבוד
        const apis = [
            // API ראשי - מחפש לפי scheduled_notification_id ואחר כך לפי id
            {
                url: '/dashboard/dashboards/cemeteries/my-notifications/api/my-notifications-api.php',
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'mark_read', notification_id: notificationId })
            },
            // API משני - מחפש ישירות לפי push_notifications.id
            {
                url: '/api/notifications.php',
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({ action: 'mark_read', notification_id: notificationId })
            }
        ];

        let anySuccess = false;

        for (const api of apis) {
            try {
                const response = await fetch(api.url, {
                    method: api.method,
                    headers: api.headers,
                    credentials: 'include',
                    body: api.body
                });

                const data = await response.json();
                console.log('[NotificationModal] API response from', api.url, ':', data);

                if (data.success && (data.updated > 0 || data.success === true)) {
                    anySuccess = true;
                    console.log('[NotificationModal] ✅ Marked as read via', api.url);
                }
            } catch (e) {
                console.log('[NotificationModal] API call failed for', api.url, ':', e.message);
            }
        }

        if (anySuccess) {
            // Update sidebar count if function exists
            if (typeof updateMyNotificationsCount === 'function') {
                updateMyNotificationsCount();
            }
        } else {
            console.warn('[NotificationModal] ⚠️ Failed to mark notification as read');
        }
    }
};

// Auto-init and check URL parameters when script loads
document.addEventListener('DOMContentLoaded', () => {
    // Check URL for notification parameter
    const urlParams = new URLSearchParams(window.location.search);
    const showNotification = urlParams.get('show_notification');

    if (showNotification) {
        const title = urlParams.get('notification_title') || 'התראה חדשה';
        const body = urlParams.get('notification_body') || '';
        const url = urlParams.get('notification_url') || '/dashboard/';
        const isApproval = urlParams.get('is_approval') === '1';

        // Clean URL without reloading
        const cleanUrl = window.location.pathname;
        window.history.replaceState({}, '', cleanUrl);

        // Show the notification modal
        NotificationModal.show({
            notificationId: showNotification,
            title: decodeURIComponent(title),
            body: decodeURIComponent(body),
            url: decodeURIComponent(url),
            isApproval: isApproval
        });
    }
});

// Listen for messages from service worker
if ('serviceWorker' in navigator) {
    navigator.serviceWorker.addEventListener('message', (event) => {
        console.log('[NotificationModal] Received SW message:', event.data);

        if (event.data && event.data.type === 'SHOW_NOTIFICATION_MODAL') {
            console.log('[NotificationModal] Showing notification modal from SW');

            // Ensure DOM is ready before showing modal
            if (document.readyState === 'complete' || document.readyState === 'interactive') {
                NotificationModal.show({
                    notificationId: event.data.notificationId,
                    title: event.data.title,
                    body: event.data.body,
                    url: event.data.url,
                    isApproval: event.data.isApproval
                });
            } else {
                document.addEventListener('DOMContentLoaded', () => {
                    NotificationModal.show({
                        notificationId: event.data.notificationId,
                        title: event.data.title,
                        body: event.data.body,
                        url: event.data.url,
                        isApproval: event.data.isApproval
                    });
                });
            }
        }
    });
}

// Expose globally
window.NotificationModal = NotificationModal;
