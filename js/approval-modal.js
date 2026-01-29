/**
 * Approval Modal - Floating approval request popup
 * Can be triggered from anywhere in the app
 *
 * @version 1.0.0
 */

window.ApprovalModal = {
    modalElement: null,
    currentNotificationId: null,

    /**
     * Initialize the modal (call once on page load)
     */
    init() {
        if (this.modalElement) return;

        // Create modal HTML - Full screen, no close button
        const modalHtml = `
            <div id="approvalModal" class="approval-modal-overlay approval-fullscreen" style="display: none;">
                <div class="approval-modal approval-modal-fullscreen">
                    <div class="approval-modal-header">
                        <h3 class="approval-modal-title">בקשת אישור</h3>
                    </div>
                    <div class="approval-modal-body">
                        <div class="approval-loading" id="approvalLoading">
                            <div class="spinner"></div>
                            <span>טוען...</span>
                        </div>
                        <div class="approval-content" id="approvalContent" style="display: none;">
                            <div class="approval-icon">🔔</div>
                            <h4 id="approvalTitle"></h4>
                            <p id="approvalBody"></p>
                            <p id="approvalMessage" class="approval-extra-message"></p>
                            <p class="approval-hint" id="approvalHint"></p>
                        </div>
                        <div class="approval-responded" id="approvalResponded" style="display: none;">
                            <div class="response-icon" id="responseIcon"></div>
                            <p id="responseMessage"></p>
                        </div>
                        <div class="approval-error" id="approvalError" style="display: none;">
                            <p id="errorMessage"></p>
                        </div>
                    </div>
                    <div class="approval-modal-footer" id="approvalFooter">
                        <button type="button" class="btn-approve" id="btnModalApprove" onclick="ApprovalModal.approve()">
                            <span class="btn-icon">✓</span> אישור
                        </button>
                        <button type="button" class="btn-reject" id="btnModalReject" onclick="ApprovalModal.reject()">
                            <span class="btn-icon">✗</span> דחייה
                        </button>
                    </div>
                </div>
            </div>
        `;

        // Add CSS
        const style = document.createElement('style');
        style.textContent = `
            .approval-modal-overlay {
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background: rgba(0, 0, 0, 0.6);
                z-index: 99999;
                display: flex;
                align-items: center;
                justify-content: center;
                padding: 20px;
                animation: fadeIn 0.2s ease;
            }

            /* Full screen version - cannot be dismissed */
            .approval-modal-overlay.approval-fullscreen {
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                padding: 0;
            }

            @keyframes fadeIn {
                from { opacity: 0; }
                to { opacity: 1; }
            }

            .approval-modal {
                background: white;
                border-radius: 16px;
                box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
                width: 100%;
                max-width: 400px;
                max-height: 90vh;
                overflow: hidden;
                animation: slideUp 0.3s ease;
            }

            /* Full screen modal */
            .approval-modal.approval-modal-fullscreen {
                max-width: 100%;
                max-height: 100%;
                height: 100%;
                border-radius: 0;
                display: flex;
                flex-direction: column;
            }

            .approval-modal-fullscreen .approval-modal-body {
                flex: 1;
                display: flex;
                flex-direction: column;
                justify-content: center;
                padding: 40px 20px;
            }

            .approval-modal-fullscreen .approval-icon {
                font-size: 80px;
                margin-bottom: 24px;
            }

            .approval-modal-fullscreen .approval-modal-body h4 {
                font-size: 28px;
                margin-bottom: 16px;
            }

            .approval-modal-fullscreen .approval-modal-body p {
                font-size: 18px;
            }

            .approval-modal-fullscreen .approval-modal-footer {
                padding: 20px;
                gap: 16px;
            }

            .approval-modal-fullscreen .approval-modal-footer button {
                padding: 18px 24px;
                font-size: 18px;
            }

            @keyframes slideUp {
                from { transform: translateY(30px); opacity: 0; }
                to { transform: translateY(0); opacity: 1; }
            }

            .approval-modal-header {
                display: flex;
                justify-content: space-between;
                align-items: center;
                padding: 16px 20px;
                border-bottom: 1px solid #e2e8f0;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                color: white;
            }

            .approval-modal-title {
                margin: 0;
                font-size: 18px;
                font-weight: 600;
            }

            .approval-modal-close {
                background: none;
                border: none;
                color: white;
                font-size: 24px;
                cursor: pointer;
                padding: 0;
                line-height: 1;
                opacity: 0.8;
                transition: opacity 0.2s;
            }

            .approval-modal-close:hover {
                opacity: 1;
            }

            .approval-modal-body {
                padding: 24px 20px;
                text-align: center;
            }

            .approval-icon {
                font-size: 48px;
                margin-bottom: 16px;
            }

            .approval-modal-body h4 {
                margin: 0 0 12px;
                font-size: 20px;
                color: #1e293b;
            }

            .approval-modal-body p {
                margin: 0;
                color: #475569;
                line-height: 1.6;
            }

            .approval-extra-message {
                margin-top: 16px !important;
                padding: 12px;
                background: #f1f5f9;
                border-radius: 8px;
                font-style: italic;
            }

            .approval-hint {
                margin-top: 12px !important;
                font-size: 13px;
                color: #94a3b8 !important;
            }

            .approval-modal-footer {
                display: flex;
                gap: 12px;
                padding: 16px 20px;
                border-top: 1px solid #e2e8f0;
                background: #f8fafc;
            }

            .approval-modal-footer button {
                flex: 1;
                padding: 14px 20px;
                border: none;
                border-radius: 10px;
                font-size: 16px;
                font-weight: 600;
                cursor: pointer;
                display: flex;
                align-items: center;
                justify-content: center;
                gap: 8px;
                transition: all 0.2s;
            }

            .btn-approve {
                background: linear-gradient(135deg, #10b981 0%, #059669 100%);
                color: white;
            }

            .btn-approve:hover:not(:disabled) {
                transform: translateY(-2px);
                box-shadow: 0 4px 12px rgba(16, 185, 129, 0.4);
            }

            .btn-reject {
                background: #f1f5f9;
                color: #64748b;
            }

            .btn-reject:hover:not(:disabled) {
                background: #e2e8f0;
            }

            .approval-modal-footer button:disabled {
                opacity: 0.6;
                cursor: not-allowed;
            }

            .approval-loading {
                display: flex;
                flex-direction: column;
                align-items: center;
                gap: 12px;
                padding: 20px;
            }

            .approval-loading .spinner {
                width: 40px;
                height: 40px;
                border: 3px solid #e2e8f0;
                border-top-color: #667eea;
                border-radius: 50%;
                animation: spin 0.8s linear infinite;
            }

            @keyframes spin {
                to { transform: rotate(360deg); }
            }

            .approval-responded .response-icon {
                font-size: 64px;
                margin-bottom: 16px;
            }

            .approval-responded p {
                font-size: 18px;
                font-weight: 500;
            }

            .approval-error {
                color: #ef4444;
            }

            /* RTL Support */
            [dir="rtl"] .approval-modal-footer {
                flex-direction: row-reverse;
            }

            /* Dark Theme Support */
            .dark-theme .approval-modal,
            [data-theme="dark"] .approval-modal {
                background: #1e293b;
            }

            .dark-theme .approval-modal-body h4,
            [data-theme="dark"] .approval-modal-body h4 {
                color: #f1f5f9;
            }

            .dark-theme .approval-modal-body p,
            [data-theme="dark"] .approval-modal-body p {
                color: #cbd5e1;
            }

            .dark-theme .approval-extra-message,
            [data-theme="dark"] .approval-extra-message {
                background: #334155;
                color: #e2e8f0;
            }

            .dark-theme .approval-hint,
            [data-theme="dark"] .approval-hint {
                color: #64748b !important;
            }

            .dark-theme .approval-modal-footer,
            [data-theme="dark"] .approval-modal-footer {
                background: #0f172a;
                border-top-color: #334155;
            }

            .dark-theme .btn-reject,
            [data-theme="dark"] .btn-reject {
                background: #334155;
                color: #e2e8f0;
            }

            .dark-theme .btn-reject:hover:not(:disabled),
            [data-theme="dark"] .btn-reject:hover:not(:disabled) {
                background: #475569;
            }

            .dark-theme .approval-loading .spinner,
            [data-theme="dark"] .approval-loading .spinner {
                border-color: #334155;
                border-top-color: #667eea;
            }

            .dark-theme .approval-loading span,
            [data-theme="dark"] .approval-loading span {
                color: #cbd5e1;
            }

            .dark-theme .approval-responded p,
            [data-theme="dark"] .approval-responded p {
                color: #f1f5f9;
            }

            /* Color Scheme Support - Header gradient based on user's color scheme */
            .color-scheme-purple .approval-modal-header,
            .color-scheme-purple .approval-modal-overlay.approval-fullscreen {
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            }

            .color-scheme-blue .approval-modal-header,
            .color-scheme-blue .approval-modal-overlay.approval-fullscreen {
                background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);
            }

            .color-scheme-green .approval-modal-header,
            .color-scheme-green .approval-modal-overlay.approval-fullscreen {
                background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            }

            .color-scheme-red .approval-modal-header,
            .color-scheme-red .approval-modal-overlay.approval-fullscreen {
                background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
            }

            .color-scheme-orange .approval-modal-header,
            .color-scheme-orange .approval-modal-overlay.approval-fullscreen {
                background: linear-gradient(135deg, #f97316 0%, #ea580c 100%);
            }

            .color-scheme-pink .approval-modal-header,
            .color-scheme-pink .approval-modal-overlay.approval-fullscreen {
                background: linear-gradient(135deg, #ec4899 0%, #db2777 100%);
            }

            .color-scheme-teal .approval-modal-header,
            .color-scheme-teal .approval-modal-overlay.approval-fullscreen {
                background: linear-gradient(135deg, #14b8a6 0%, #0d9488 100%);
            }
        `;
        document.head.appendChild(style);

        // Add modal to body
        const div = document.createElement('div');
        div.innerHTML = modalHtml;
        document.body.appendChild(div.firstElementChild);

        this.modalElement = document.getElementById('approvalModal');

        // NO close on overlay click - user MUST respond
        // NO close on Escape key - user MUST respond

        // Block back button while modal is open
        window.addEventListener('popstate', (e) => {
            if (this.modalElement && this.modalElement.style.display !== 'none') {
                // Push state again to prevent navigation
                history.pushState(null, '', window.location.href);
            }
        });
    },

    /**
     * Show the approval modal for a notification
     * Once shown, user MUST respond - no escape possible
     */
    async show(notificationId) {
        this.init();
        this.currentNotificationId = notificationId;

        // Reset state
        document.getElementById('approvalLoading').style.display = 'flex';
        document.getElementById('approvalContent').style.display = 'none';
        document.getElementById('approvalResponded').style.display = 'none';
        document.getElementById('approvalError').style.display = 'none';
        document.getElementById('approvalFooter').style.display = 'flex';

        // Push history state to block back navigation
        history.pushState({ approvalModal: true }, '', window.location.href);

        // Prevent page scroll while modal is open
        document.body.style.overflow = 'hidden';

        this.modalElement.style.display = 'flex';

        // Load notification data
        try {
            const response = await fetch(`/dashboard/dashboards/cemeteries/notifications/api/approval-api.php?action=get_notification&id=${notificationId}`, {
                credentials: 'include'
            });
            const data = await response.json();

            if (!data.success) {
                throw new Error(data.error || 'שגיאה בטעינת ההתראה');
            }

            const notification = data.notification;

            // Check if already responded
            if (data.approval && ['approved', 'rejected'].includes(data.approval.status)) {
                this.showResponded(data.approval.status);
                return;
            }

            // Check if expired
            if (data.expired) {
                this.showError('פג תוקף בקשת האישור');
                document.getElementById('approvalFooter').style.display = 'none';
                return;
            }

            // Show content
            document.getElementById('approvalTitle').textContent = notification.title;
            document.getElementById('approvalBody').textContent = notification.body;

            const messageEl = document.getElementById('approvalMessage');
            if (notification.approval_message) {
                messageEl.textContent = notification.approval_message;
                messageEl.style.display = 'block';
            } else {
                messageEl.style.display = 'none';
            }

            // Update hint based on biometric availability
            const hintEl = document.getElementById('approvalHint');
            if (window.biometricAuth && window.biometricAuth.isSupported) {
                const hasBiometric = await window.biometricAuth.userHasBiometric();
                hintEl.textContent = hasBiometric
                    ? 'לחץ אישור לאימות עם טביעת אצבע / Face ID'
                    : 'לאישור ביומטרי, הגדר קודם באזור האישי';
            } else {
                hintEl.textContent = '';
            }

            document.getElementById('approvalLoading').style.display = 'none';
            document.getElementById('approvalContent').style.display = 'block';

        } catch (error) {
            console.error('Error loading approval:', error);
            this.showError(error.message);
        }
    },

    /**
     * Handle approve action - REQUIRES biometric authentication
     */
    async approve() {
        const btnApprove = document.getElementById('btnModalApprove');
        const btnReject = document.getElementById('btnModalReject');

        btnApprove.disabled = true;
        btnReject.disabled = true;

        // Check if biometric is available
        if (!window.biometricAuth || !window.biometricAuth.isSupported) {
            this.showError('נדרש מכשיר התומך באימות ביומטרי');
            btnApprove.disabled = false;
            btnReject.disabled = false;
            return;
        }

        // Check if user has biometric registered
        const hasBiometric = await window.biometricAuth.userHasBiometric();
        if (!hasBiometric) {
            // Show message and redirect to settings
            this.showNoBiometricMessage();
            return;
        }

        // Perform biometric authentication - MANDATORY
        try {
            const result = await window.biometricAuth.authenticate();

            if (!result.success) {
                if (result.userCancelled) {
                    this.showError('האימות הביומטרי בוטל. יש לאשר עם טביעת אצבע / Face ID');
                } else {
                    this.showError('האימות הביומטרי נכשל. נסה שוב.');
                }
                btnApprove.disabled = false;
                btnReject.disabled = false;
                return;
            }

            // Biometric verified successfully
            await this.sendResponse('approved', true);

        } catch (e) {
            console.error('Biometric error:', e);
            this.showError('שגיאה באימות הביומטרי: ' + e.message);
            btnApprove.disabled = false;
            btnReject.disabled = false;
        }
    },

    /**
     * Show message when user has no biometric registered
     */
    showNoBiometricMessage() {
        document.getElementById('approvalLoading').style.display = 'none';
        document.getElementById('approvalContent').style.display = 'none';
        document.getElementById('approvalResponded').style.display = 'none';
        document.getElementById('approvalError').style.display = 'none';
        document.getElementById('approvalFooter').style.display = 'none';

        // Create special message for no biometric
        const body = document.querySelector('.approval-modal-body');
        body.innerHTML = `
            <div class="no-biometric-message">
                <div class="approval-icon">🔐</div>
                <h4>נדרש אימות ביומטרי</h4>
                <p>כדי לאשר בקשה זו, יש להגדיר קודם אימות ביומטרי (טביעת אצבע / Face ID) במערכת.</p>
                <button type="button" class="btn-setup-biometric" onclick="ApprovalModal.goToSettings()">
                    <span>🔧</span> הגדרת אימות ביומטרי
                </button>
                <p class="setup-hint">לאחר ההגדרה, חזור לכאן לאישור הבקשה</p>
            </div>
        `;

        // Add CSS for this message
        if (!document.getElementById('noBiometricStyles')) {
            const style = document.createElement('style');
            style.id = 'noBiometricStyles';
            style.textContent = `
                .no-biometric-message {
                    text-align: center;
                    padding: 20px;
                }
                .no-biometric-message h4 {
                    color: #ef4444;
                    margin-bottom: 16px;
                }
                .no-biometric-message p {
                    color: #475569;
                    margin-bottom: 24px;
                }
                .btn-setup-biometric {
                    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                    color: white;
                    border: none;
                    padding: 16px 32px;
                    font-size: 18px;
                    border-radius: 12px;
                    cursor: pointer;
                    display: inline-flex;
                    align-items: center;
                    gap: 10px;
                    transition: transform 0.2s, box-shadow 0.2s;
                }
                .btn-setup-biometric:hover {
                    transform: translateY(-2px);
                    box-shadow: 0 8px 20px rgba(102, 126, 234, 0.4);
                }
                .setup-hint {
                    font-size: 14px;
                    color: #94a3b8 !important;
                    margin-top: 16px !important;
                }
            `;
            document.head.appendChild(style);
        }
    },

    /**
     * Navigate to settings to set up biometric
     */
    goToSettings() {
        // Store current approval request to return to later
        if (this.currentNotificationId) {
            sessionStorage.setItem('pendingApprovalId', this.currentNotificationId);
        }

        // Navigate to settings page
        window.location.href = '/dashboard/dashboards/cemeteries/user-settings/settings-page.php?section=security';
    },

    /**
     * Handle reject action
     */
    async reject() {
        const btnApprove = document.getElementById('btnModalApprove');
        const btnReject = document.getElementById('btnModalReject');

        btnApprove.disabled = true;
        btnReject.disabled = true;

        await this.sendResponse('rejected', false);
    },

    /**
     * Send response to server
     */
    async sendResponse(response, biometricVerified) {
        try {
            const res = await fetch('/dashboard/dashboards/cemeteries/notifications/api/approval-api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                credentials: 'include',
                body: JSON.stringify({
                    action: 'respond',
                    notification_id: this.currentNotificationId,
                    response: response,
                    biometric_verified: biometricVerified
                })
            });

            const data = await res.json();

            if (data.success) {
                // Mark notification as read after successful response
                await this.markAsRead(this.currentNotificationId);
                this.showResponded(response);
            } else {
                throw new Error(data.error || 'שגיאה');
            }

        } catch (error) {
            console.error('Error sending response:', error);
            this.showError(error.message);
            document.getElementById('btnModalApprove').disabled = false;
            document.getElementById('btnModalReject').disabled = false;
        }
    },

    /**
     * Mark notification as read
     * @param {number|string} notificationId
     */
    async markAsRead(notificationId) {
        console.log('[ApprovalModal] markAsRead called with ID:', notificationId);

        if (!notificationId) return;

        // קריאה לשני ה-APIs כדי לוודא שאחד מהם יעבוד
        const apis = [
            {
                url: '/dashboard/dashboards/cemeteries/my-notifications/api/my-notifications-api.php',
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'mark_read', notification_id: notificationId })
            },
            {
                url: '/api/notifications.php',
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({ action: 'mark_read', notification_id: notificationId })
            }
        ];

        for (const api of apis) {
            try {
                const response = await fetch(api.url, {
                    method: api.method,
                    headers: api.headers,
                    credentials: 'include',
                    body: api.body
                });
                const data = await response.json();
                if (data.success && data.updated > 0) {
                    console.log('[ApprovalModal] ✅ Marked as read via', api.url);
                    // Update sidebar count if function exists
                    if (typeof updateMyNotificationsCount === 'function') {
                        updateMyNotificationsCount();
                    }
                    break;
                }
            } catch (e) {
                console.log('[ApprovalModal] API call failed for', api.url);
            }
        }
    },

    /**
     * Show responded state
     */
    showResponded(status) {
        document.getElementById('approvalLoading').style.display = 'none';
        document.getElementById('approvalContent').style.display = 'none';
        document.getElementById('approvalError').style.display = 'none';
        document.getElementById('approvalFooter').style.display = 'none';

        const iconEl = document.getElementById('responseIcon');
        const messageEl = document.getElementById('responseMessage');

        if (status === 'approved') {
            iconEl.textContent = '✅';
            messageEl.textContent = 'האישור נרשם בהצלחה';
            messageEl.style.color = '#10b981';
        } else {
            iconEl.textContent = '❌';
            messageEl.textContent = 'הדחייה נרשמה';
            messageEl.style.color = '#ef4444';
        }

        document.getElementById('approvalResponded').style.display = 'block';

        // Auto close after 2 seconds
        setTimeout(() => this.close(), 2000);
    },

    /**
     * Show error state
     */
    showError(message) {
        document.getElementById('approvalLoading').style.display = 'none';
        document.getElementById('approvalContent').style.display = 'none';
        document.getElementById('approvalResponded').style.display = 'none';

        document.getElementById('errorMessage').textContent = message;
        document.getElementById('approvalError').style.display = 'block';
    },

    /**
     * Close the modal (only called internally after response)
     */
    close() {
        if (this.modalElement) {
            this.modalElement.style.display = 'none';
        }
        // Restore page scroll
        document.body.style.overflow = '';
        this.currentNotificationId = null;
    }
};

// Auto-init when script loads
document.addEventListener('DOMContentLoaded', () => {
    // Check URL for approval parameter
    const urlParams = new URLSearchParams(window.location.search);
    const approvalId = urlParams.get('approval_id');

    // Check if returning from settings with pending approval
    const pendingApprovalId = sessionStorage.getItem('pendingApprovalId');

    if (approvalId) {
        // Clear any pending approval since we have a direct one
        sessionStorage.removeItem('pendingApprovalId');
        ApprovalModal.show(parseInt(approvalId));
    } else if (pendingApprovalId) {
        // Returning from settings - show the pending approval
        sessionStorage.removeItem('pendingApprovalId');
        ApprovalModal.show(parseInt(pendingApprovalId));
    }
});

// Listen for messages from service worker
if ('serviceWorker' in navigator) {
    navigator.serviceWorker.addEventListener('message', (event) => {
        console.log('[ApprovalModal] Received SW message:', event.data);
        if (event.data && event.data.type === 'SHOW_APPROVAL') {
            const notificationId = event.data.notificationId;
            console.log('[ApprovalModal] Showing approval modal for notification:', notificationId);

            // Ensure DOM is ready before showing modal
            if (document.readyState === 'complete' || document.readyState === 'interactive') {
                ApprovalModal.show(notificationId);
            } else {
                document.addEventListener('DOMContentLoaded', () => {
                    ApprovalModal.show(notificationId);
                });
            }
        }
    });

    // Also listen on navigator.serviceWorker.ready for more reliability
    navigator.serviceWorker.ready.then(registration => {
        console.log('[ApprovalModal] Service Worker ready, controller:', navigator.serviceWorker.controller ? 'yes' : 'no');
    });
}

// Expose globally for debugging
window.ApprovalModal = ApprovalModal;
