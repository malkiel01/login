/**
 * Approval Notification Template
 * Full screen approval request from notification form
 * Requires biometric authentication to approve
 *
 * Features:
 * - Full screen display
 * - Cannot be dismissed without responding
 * - Approve button requires biometric authentication
 * - Reject button with optional reason
 *
 * @version 1.0.0
 * @history-managed Uses browser History API for back button handling
 * @history-pattern simple (pushState only, NO cleanup on close!)
 * @history-status BUGGY - Missing history.back() on close, causes accumulation
 * @see /docs/HISTORY-MANAGEMENT.md
 */

(function() {
    if (!window.NotificationTemplates) {
        console.error('[ApprovalNotification] NotificationTemplates not loaded');
        return;
    }

    /**
     * Show generic approval notification (full screen)
     * @param {Object} notification - Notification data
     * @param {Object} options - Display options
     */
    NotificationTemplates.showApprovalNotification = function(notification, options = {}) {
        // Close any existing modal
        this.close();

        // Prevent page scroll
        document.body.style.overflow = 'hidden';

        // Push history state to block back navigation
        history.pushState({ approvalModal: true }, '', window.location.href);

        // Create overlay
        const overlay = document.createElement('div');
        overlay.className = 'notification-overlay approval-notification-overlay';
        overlay.innerHTML = `
            <div class="approval-notification-modal">
                <div class="approval-notification-header">
                    <h3>בקשת אישור</h3>
                </div>
                <div class="approval-notification-body">
                    <div class="approval-notification-loading" id="approvalLoading">
                        <div class="nt-spinner"></div>
                        <span>טוען...</span>
                    </div>
                    <div class="approval-notification-content" id="approvalContent" style="display: none;">
                        <div class="approval-notification-icon">
                            <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="var(--primary-color, #667eea)" stroke-width="1.5">
                                <path d="M9 12l2 2 4-4"/>
                                <path d="M12 22c5.523 0 10-4.477 10-10S17.523 2 12 2 2 6.477 2 12s4.477 10 10 10z"/>
                            </svg>
                        </div>
                        <h4 class="approval-notification-title" id="approvalTitle"></h4>
                        <p class="approval-notification-text" id="approvalBody"></p>
                        <p class="approval-notification-message" id="approvalMessage"></p>
                        <p class="approval-notification-hint" id="approvalHint"></p>
                    </div>
                    <div class="approval-notification-responded" id="approvalResponded" style="display: none;">
                        <div class="response-icon" id="responseIcon"></div>
                        <p id="responseMessage"></p>
                    </div>
                    <div class="approval-notification-error" id="approvalError" style="display: none;">
                        <div class="error-icon">
                            <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="#ef4444" stroke-width="2">
                                <circle cx="12" cy="12" r="10"/>
                                <line x1="15" y1="9" x2="9" y2="15"/>
                                <line x1="9" y1="9" x2="15" y2="15"/>
                            </svg>
                        </div>
                        <p id="errorMessage"></p>
                    </div>
                    <div class="rejection-form" id="rejectionForm" style="display: none;">
                        <textarea id="rejectionReason" placeholder="סיבת הדחייה (אופציונלי)" rows="3"></textarea>
                        <div class="rejection-actions">
                            <button class="nt-btn nt-btn-reject" id="confirmRejectBtn">שלח דחייה</button>
                            <button class="nt-btn nt-btn-close" id="cancelRejectBtn">ביטול</button>
                        </div>
                    </div>
                </div>
                <div class="approval-notification-footer" id="approvalFooter">
                    <button class="nt-btn nt-btn-approve" id="approveBtn">
                        <span class="btn-icon">✓</span> אישור
                    </button>
                    <button class="nt-btn nt-btn-reject" id="rejectBtn">
                        <span class="btn-icon">✗</span> דחייה
                    </button>
                </div>
            </div>
        `;

        // Add styles
        this.addApprovalNotificationStyles();

        // Add to DOM
        document.body.appendChild(overlay);
        this.activeModal = overlay;

        // Store notification ID
        this.currentNotificationId = notification.id;

        // Event handlers
        const approveBtn = overlay.querySelector('#approveBtn');
        const rejectBtn = overlay.querySelector('#rejectBtn');
        const confirmRejectBtn = overlay.querySelector('#confirmRejectBtn');
        const cancelRejectBtn = overlay.querySelector('#cancelRejectBtn');

        approveBtn.addEventListener('click', () => this.handleApprove(notification.id));
        rejectBtn.addEventListener('click', () => this.showRejectForm());
        confirmRejectBtn.addEventListener('click', () => this.handleReject(notification.id));
        cancelRejectBtn.addEventListener('click', () => this.hideRejectForm());

        // Block back button
        window.addEventListener('popstate', this.handlePopState);

        // Load notification content
        this.loadApprovalContent(notification);

        return overlay;
    };

    /**
     * Load notification content
     */
    NotificationTemplates.loadApprovalContent = async function(notification) {
        const loading = document.getElementById('approvalLoading');
        const content = document.getElementById('approvalContent');
        const footer = document.getElementById('approvalFooter');

        try {
            // If notification already has content, use it
            if (notification.title) {
                document.getElementById('approvalTitle').textContent = notification.title;
                document.getElementById('approvalBody').textContent = notification.body || '';

                const messageEl = document.getElementById('approvalMessage');
                if (notification.approval_message) {
                    messageEl.textContent = notification.approval_message;
                    messageEl.style.display = 'block';
                } else {
                    messageEl.style.display = 'none';
                }

                // Update biometric hint
                await this.updateBiometricHint();

                loading.style.display = 'none';
                content.style.display = 'block';
                return;
            }

            // Otherwise, fetch from API
            const response = await fetch(`/dashboard/dashboards/cemeteries/notifications/api/approval-api.php?action=get_notification&id=${notification.id}`, {
                credentials: 'include'
            });
            const data = await response.json();

            if (!data.success) {
                throw new Error(data.error || 'שגיאה בטעינת ההתראה');
            }

            const notifData = data.notification;

            // Check if already responded
            if (data.approval && ['approved', 'rejected'].includes(data.approval.status)) {
                this.showRespondedState(data.approval.status);
                return;
            }

            // Check if expired
            if (data.expired) {
                this.showErrorState('פג תוקף בקשת האישור');
                footer.style.display = 'none';
                return;
            }

            // Show content
            document.getElementById('approvalTitle').textContent = notifData.title;
            document.getElementById('approvalBody').textContent = notifData.body || '';

            const messageEl = document.getElementById('approvalMessage');
            if (notifData.approval_message) {
                messageEl.textContent = notifData.approval_message;
                messageEl.style.display = 'block';
            } else {
                messageEl.style.display = 'none';
            }

            // Update biometric hint
            await this.updateBiometricHint();

            loading.style.display = 'none';
            content.style.display = 'block';

        } catch (error) {
            console.error('[ApprovalNotification] Error:', error);
            this.showErrorState(error.message);
        }
    };

    /**
     * Update biometric hint text
     */
    NotificationTemplates.updateBiometricHint = async function() {
        const hintEl = document.getElementById('approvalHint');
        if (!hintEl) return;

        if (window.biometricAuth && window.biometricAuth.isSupported) {
            try {
                const hasBiometric = await window.biometricAuth.userHasBiometric();
                hintEl.textContent = hasBiometric
                    ? 'לחץ אישור לאימות עם טביעת אצבע / Face ID'
                    : 'לאישור ביומטרי, הגדר קודם באזור האישי';
            } catch (e) {
                hintEl.textContent = '';
            }
        } else {
            hintEl.textContent = '';
        }
    };

    /**
     * Handle approve action
     */
    NotificationTemplates.handleApprove = async function(notificationId) {
        const approveBtn = document.getElementById('approveBtn');
        const rejectBtn = document.getElementById('rejectBtn');

        approveBtn.disabled = true;
        rejectBtn.disabled = true;

        // Check biometric availability
        if (!window.biometricAuth || !window.biometricAuth.isSupported) {
            this.showErrorState('נדרש מכשיר התומך באימות ביומטרי');
            approveBtn.disabled = false;
            rejectBtn.disabled = false;
            return;
        }

        // Check if user has biometric
        let hasBiometric = false;
        try {
            hasBiometric = await window.biometricAuth.userHasBiometric();
        } catch (e) {
            this.showErrorState('שגיאה בבדיקת אימות ביומטרי');
            approveBtn.disabled = false;
            rejectBtn.disabled = false;
            return;
        }

        if (!hasBiometric) {
            this.showNoBiometricMessage();
            return;
        }

        // Perform biometric auth
        try {
            const result = await window.biometricAuth.authenticate();

            if (!result.success) {
                if (result.userCancelled) {
                    this.showErrorState('האימות הביומטרי בוטל. יש לאשר עם טביעת אצבע / Face ID');
                } else {
                    this.showErrorState('האימות הביומטרי נכשל. נסה שוב.');
                }
                approveBtn.disabled = false;
                rejectBtn.disabled = false;
                return;
            }

            // Send approval
            await this.sendResponse(notificationId, 'approved', true);

        } catch (e) {
            console.error('[ApprovalNotification] Biometric error:', e);
            this.showErrorState('שגיאה באימות הביומטרי: ' + e.message);
            approveBtn.disabled = false;
            rejectBtn.disabled = false;
        }
    };

    /**
     * Show rejection form
     */
    NotificationTemplates.showRejectForm = function() {
        document.getElementById('rejectionForm').style.display = 'block';
        document.getElementById('rejectBtn').style.display = 'none';
    };

    /**
     * Hide rejection form
     */
    NotificationTemplates.hideRejectForm = function() {
        document.getElementById('rejectionForm').style.display = 'none';
        document.getElementById('rejectBtn').style.display = 'flex';
    };

    /**
     * Handle reject action
     */
    NotificationTemplates.handleReject = async function(notificationId) {
        const reason = document.getElementById('rejectionReason').value;
        await this.sendResponse(notificationId, 'rejected', false, reason);
    };

    /**
     * Send response to server
     */
    NotificationTemplates.sendResponse = async function(notificationId, response, biometricVerified, reason = null) {
        try {
            const res = await fetch('/dashboard/dashboards/cemeteries/notifications/api/approval-api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                credentials: 'include',
                body: JSON.stringify({
                    action: 'respond',
                    notification_id: notificationId,
                    response: response,
                    biometric_verified: biometricVerified,
                    reason: reason
                })
            });

            const data = await res.json();

            if (data.success) {
                this.showRespondedState(response, true);
            } else {
                throw new Error(data.error || 'שגיאה');
            }

        } catch (error) {
            console.error('[ApprovalNotification] Error:', error);
            this.showErrorState(error.message);
            document.getElementById('approveBtn').disabled = false;
            document.getElementById('rejectBtn').disabled = false;
        }
    };

    /**
     * Show responded state
     */
    NotificationTemplates.showRespondedState = function(status, autoClose = false) {
        document.getElementById('approvalLoading').style.display = 'none';
        document.getElementById('approvalContent').style.display = 'none';
        document.getElementById('approvalError').style.display = 'none';
        document.getElementById('rejectionForm').style.display = 'none';
        document.getElementById('approvalFooter').style.display = 'none';

        const respondedEl = document.getElementById('approvalResponded');
        const iconEl = document.getElementById('responseIcon');
        const messageEl = document.getElementById('responseMessage');

        if (status === 'approved') {
            iconEl.innerHTML = '<span style="font-size: 64px;">✅</span>';
            messageEl.textContent = 'האישור נרשם בהצלחה';
            messageEl.style.color = '#10b981';
        } else {
            iconEl.innerHTML = '<span style="font-size: 64px;">❌</span>';
            messageEl.textContent = 'הדחייה נרשמה';
            messageEl.style.color = '#ef4444';
        }

        respondedEl.style.display = 'block';

        if (autoClose) {
            setTimeout(() => this.close(), 2000);
        }
    };

    /**
     * Show error state
     */
    NotificationTemplates.showErrorState = function(message) {
        document.getElementById('approvalLoading').style.display = 'none';
        document.getElementById('approvalContent').style.display = 'none';
        document.getElementById('approvalResponded').style.display = 'none';

        document.getElementById('errorMessage').textContent = message;
        document.getElementById('approvalError').style.display = 'block';
    };

    /**
     * Show no biometric message
     */
    NotificationTemplates.showNoBiometricMessage = function() {
        const content = document.getElementById('approvalContent');
        const footer = document.getElementById('approvalFooter');

        content.innerHTML = `
            <div class="no-biometric-message">
                <div style="font-size: 64px; margin-bottom: 20px;">🔐</div>
                <h4>נדרש אימות ביומטרי</h4>
                <p>כדי לאשר בקשה זו, יש להגדיר קודם אימות ביומטרי (טביעת אצבע / Face ID) במערכת.</p>
                <button class="nt-btn" style="background: linear-gradient(135deg, var(--primary-color, #667eea) 0%, var(--primary-dark, #764ba2) 100%); color: white; margin-top: 20px;" onclick="window.location.href='/dashboard/dashboards/cemeteries/user-settings/settings-page.php?section=security'">
                    🔧 הגדרת אימות ביומטרי
                </button>
                <p style="color: #94a3b8; font-size: 14px; margin-top: 16px;">לאחר ההגדרה, חזור לכאן לאישור הבקשה</p>
            </div>
        `;
        content.style.display = 'block';
        footer.style.display = 'none';
    };

    /**
     * Handle popstate (back button)
     */
    NotificationTemplates.handlePopState = function(e) {
        if (NotificationTemplates.activeModal) {
            history.pushState(null, '', window.location.href);
        }
    };

    /**
     * Override close to clean up
     */
    const originalClose = NotificationTemplates.close;
    NotificationTemplates.close = function() {
        window.removeEventListener('popstate', this.handlePopState);
        this.currentNotificationId = null;
        originalClose.call(this);
    };

    /**
     * Add styles
     */
    NotificationTemplates.addApprovalNotificationStyles = function() {
        if (document.getElementById('approvalNotificationStyles')) return;

        const style = document.createElement('style');
        style.id = 'approvalNotificationStyles';
        style.textContent = `
            /* ============================================
               Approval Notification - Full Screen
               ============================================ */

            .approval-notification-overlay {
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background: linear-gradient(135deg, var(--primary-color, #667eea) 0%, var(--primary-dark, #764ba2) 100%);
                align-items: center;
                justify-content: center;
            }

            .approval-notification-modal {
                width: 100%;
                height: 100%;
                background: var(--bg-primary, white);
                display: flex;
                flex-direction: column;
            }

            .approval-notification-header {
                padding: 16px 20px;
                background: linear-gradient(135deg, var(--primary-color, #667eea) 0%, var(--primary-dark, #764ba2) 100%);
                color: white;
            }

            .approval-notification-header h3 {
                margin: 0;
                font-size: 18px;
                font-weight: 600;
                text-align: center;
            }

            .approval-notification-body {
                flex: 1;
                display: flex;
                flex-direction: column;
                justify-content: center;
                align-items: center;
                padding: 40px 20px;
                text-align: center;
                overflow-y: auto;
            }

            .approval-notification-loading {
                display: flex;
                flex-direction: column;
                align-items: center;
                gap: 16px;
                color: var(--text-muted, #64748b);
            }

            .approval-notification-loading .nt-spinner {
                width: 48px;
                height: 48px;
            }

            .approval-notification-icon {
                margin-bottom: 24px;
            }

            .approval-notification-title {
                margin: 0 0 16px;
                font-size: 24px;
                font-weight: 600;
                color: var(--text-primary, #1e293b);
            }

            .approval-notification-text {
                margin: 0 0 20px;
                font-size: 18px;
                color: var(--text-secondary, #475569);
                line-height: 1.6;
                max-width: 400px;
            }

            .approval-notification-message {
                padding: 16px;
                background: var(--bg-tertiary, #f1f5f9);
                border-radius: 12px;
                font-style: italic;
                color: var(--text-secondary, #475569);
                max-width: 400px;
            }

            .approval-notification-hint {
                margin-top: 20px;
                font-size: 14px;
                color: var(--text-muted, #94a3b8);
            }

            .approval-notification-responded {
                text-align: center;
            }

            .approval-notification-responded p {
                font-size: 20px;
                font-weight: 500;
                margin-top: 16px;
            }

            .approval-notification-error {
                text-align: center;
                color: #ef4444;
            }

            .approval-notification-error p {
                font-size: 16px;
                margin-top: 16px;
            }

            .approval-notification-footer {
                display: flex;
                gap: 12px;
                padding: 20px;
                border-top: 1px solid var(--border-color, #e2e8f0);
                background: var(--bg-secondary, #f8fafc);
            }

            .approval-notification-footer .nt-btn {
                padding: 18px 24px;
                font-size: 18px;
            }

            /* Rejection form */
            .rejection-form {
                width: 100%;
                max-width: 400px;
                padding: 20px;
            }

            .rejection-form textarea {
                width: 100%;
                padding: 12px;
                border: 1px solid var(--border-color, #e2e8f0);
                border-radius: 8px;
                font-size: 16px;
                resize: none;
                margin-bottom: 12px;
            }

            .rejection-actions {
                display: flex;
                gap: 12px;
            }

            .no-biometric-message h4 {
                color: #ef4444;
                font-size: 22px;
                margin-bottom: 16px;
            }

            .no-biometric-message p {
                color: var(--text-secondary, #475569);
                font-size: 16px;
                max-width: 350px;
            }
        `;
        document.head.appendChild(style);
    };

    console.log('[ApprovalNotification] Template loaded');
})();
