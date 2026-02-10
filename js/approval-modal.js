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
     * Send debug log to server
     */
    _log(event, data = {}) {
        const payload = {
            page: 'APPROVAL_MODAL',
            e: event,
            historyLength: history.length,
            ts: new Date().toISOString(),
            d: data
        };
        console.log('[ApprovalModal]', event, payload);
        try {
            navigator.sendBeacon('/dashboard/dashboards/cemeteries/api/debug-log.php', JSON.stringify(payload));
        } catch(e) {}
    },

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
                background: linear-gradient(135deg, var(--primary-color, #667eea) 0%, var(--primary-dark, #764ba2) 100%);
                padding: 0;
            }

            @keyframes fadeIn {
                from { opacity: 0; }
                to { opacity: 1; }
            }

            .approval-modal {
                background: var(--bg-primary, white);
                border-radius: 16px;
                box-shadow: var(--shadow-xl, 0 20px 60px rgba(0, 0, 0, 0.3));
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
                border-bottom: 1px solid var(--border-color, #e2e8f0);
                background: linear-gradient(135deg, var(--primary-color, #667eea) 0%, var(--primary-dark, #764ba2) 100%);
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
                color: var(--text-primary, #1e293b);
            }

            .approval-modal-body p {
                margin: 0;
                color: var(--text-secondary, #475569);
                line-height: 1.6;
            }

            .approval-extra-message {
                margin-top: 16px !important;
                padding: 12px;
                background: var(--bg-tertiary, #f1f5f9);
                border-radius: 8px;
                font-style: italic;
            }

            .approval-hint {
                margin-top: 12px !important;
                font-size: 13px;
                color: var(--text-muted, #94a3b8) !important;
            }

            .approval-modal-footer {
                display: flex;
                gap: 12px;
                padding: 16px 20px;
                border-top: 1px solid var(--border-color, #e2e8f0);
                background: var(--bg-secondary, #f8fafc);
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
                background: linear-gradient(135deg, var(--success-color, #10b981) 0%, #059669 100%);
                color: white;
            }

            .btn-approve:hover:not(:disabled) {
                transform: translateY(-2px);
                box-shadow: 0 4px 12px rgba(16, 185, 129, 0.4);
            }

            .btn-reject {
                background: var(--bg-tertiary, #f1f5f9);
                color: var(--text-muted, #64748b);
            }

            .btn-reject:hover:not(:disabled) {
                background: var(--border-color, #e2e8f0);
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
                border: 3px solid var(--border-color, #e2e8f0);
                border-top-color: var(--primary-color, #667eea);
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
                color: var(--danger-color, #ef4444);
            }

            /* RTL Support */
            [dir="rtl"] .approval-modal-footer {
                flex-direction: row-reverse;
            }

            /* Small popup mode (non-fullscreen, used for viewing already responded) */
            .approval-modal-overlay:not(.approval-fullscreen) {
                background: rgba(0, 0, 0, 0.6);
                padding: 20px;
            }

            .approval-modal-overlay:not(.approval-fullscreen) .approval-modal {
                max-width: 400px;
                max-height: 90vh;
                height: auto;
                border-radius: 16px;
            }

            .approval-modal-overlay:not(.approval-fullscreen) .approval-modal-body {
                flex: none;
                padding: 24px 20px;
            }

            .approval-modal-overlay:not(.approval-fullscreen) .approval-icon,
            .approval-modal-overlay:not(.approval-fullscreen) .response-icon {
                font-size: 48px;
                margin-bottom: 16px;
            }

            .approval-modal-overlay:not(.approval-fullscreen) .approval-modal-body h4 {
                font-size: 20px;
            }

            .approval-modal-overlay:not(.approval-fullscreen) .approval-modal-body p {
                font-size: 16px;
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

        // Handle back button - behavior depends on modal mode
        // FLAGS USED:
        //   _ignoreNextPopstate: Set when WE call history.back(), so we ignore the resulting popstate
        //   _closedViaPopstate: Set when USER pressed back, so close() knows not to call history.back()
        //   _allowBackClose: true for entity approvals (closeable), false for regular approvals (blocked)
        //   _hasHistoryState: true when we've pushed a state that needs cleanup on close
        this._popstateHandler = (e) => {
            // Skip if we triggered this popstate ourselves (via history.back() in close())
            // Without this, we'd re-enter close() when cleaning up history
            if (this._ignoreNextPopstate) {
                this._log('POPSTATE_IGNORED');
                this._ignoreNextPopstate = false;
                return;
            }

            const modalVisible = this.modalElement && this.modalElement.style.display !== 'none';
            this._log('POPSTATE_FIRED', { modalVisible, allowBackClose: this._allowBackClose });

            if (modalVisible) {
                if (this._allowBackClose) {
                    // Entity approval mode - back button CLOSES the modal
                    // Mark that we're closing via popstate so close() knows not to call history.back()
                    this._log('POPSTATE_CLOSING');
                    this._closedViaPopstate = true;
                    this.close();
                } else {
                    // Regular approval mode - back button is BLOCKED (user must approve/reject)
                    // Re-push the state to "undo" the back navigation
                    if (!this._blockingBackNavigation) {
                        this._blockingBackNavigation = true;
                        this._log('POPSTATE_BLOCKING');
                        history.pushState({ approvalModal: true, blocking: true }, '', window.location.href);
                        this._hasHistoryState = true;
                        // Reset flag after delay to allow next back press attempt
                        setTimeout(() => { this._blockingBackNavigation = false; }, 100);
                    } else {
                        this._log('POPSTATE_BLOCKED_ALREADY');
                    }
                }
            }
        };
        window.addEventListener('popstate', this._popstateHandler);
    },

    /**
     * Show the approval modal for a notification
     * Once shown, user MUST respond - no escape possible
     */
    async show(notificationId) {
        this.currentNotificationId = notificationId;
        console.log('[ApprovalModal] show() called with notificationId:', notificationId);

        // Load notification data first to determine type
        try {
            const url = `/dashboard/dashboards/cemeteries/notifications/api/approval-api.php?action=get_notification&id=${notificationId}`;
            console.log('[ApprovalModal] Fetching:', url);

            const response = await fetch(url, {
                credentials: 'include'
            });

            console.log('[ApprovalModal] Response status:', response.status, response.statusText);

            // Check if response is ok
            if (!response.ok) {
                console.error('[ApprovalModal] Response not ok:', response.status);
                throw new Error(`שגיאת שרת: ${response.status}`);
            }

            // Try to parse JSON, handle empty response
            const text = await response.text();
            console.log('[ApprovalModal] Response text length:', text.length, 'Preview:', text.substring(0, 100));

            if (!text || text.trim() === '') {
                console.error('[ApprovalModal] Empty response from server');
                throw new Error('תגובה ריקה מהשרת');
            }

            let data;
            try {
                data = JSON.parse(text);
                console.log('[ApprovalModal] Parsed data:', data);
            } catch (parseError) {
                console.error('[ApprovalModal] JSON parse error:', parseError, 'Response:', text.substring(0, 200));
                throw new Error('תגובה לא תקינה מהשרת');
            }

            if (!data.success) {
                console.error('[ApprovalModal] API returned error:', data.error);
                throw new Error(data.error || 'שגיאה בטעינת ההתראה');
            }

            const notification = data.notification;
            notification.id = notificationId; // Ensure ID is set

            // Check if this is an entity approval (has URL to entity-approve.php)
            if (notification.url && notification.url.includes('entity-approve.php')) {
                console.log('[ApprovalModal] Entity approval detected, showing iframe with:', notification.url);
                this.init();
                this.showEntityApprovalIframe(notification.url);
                return;
            }

            // Check if already responded
            if (data.approval && ['approved', 'rejected'].includes(data.approval.status)) {
                // Use templates if available, otherwise fallback to old modal
                if (window.NotificationTemplates) {
                    window.NotificationTemplates.showInfoNotification({
                        id: notificationId,
                        title: data.approval.status === 'approved' ? 'כבר אושר' : 'כבר נדחה',
                        body: notification.title,
                        level: data.approval.status === 'approved' ? 'success' : 'error'
                    }, { autoDismiss: true, autoDismissDelay: 3000 });
                } else {
                    this.init();
                    this.showResponded(data.approval.status);
                }
                return;
            }

            // Check if expired
            if (data.expired) {
                if (window.NotificationTemplates) {
                    window.NotificationTemplates.showInfoNotification({
                        id: notificationId,
                        title: 'פג תוקף',
                        body: 'פג תוקף בקשת האישור',
                        level: 'warning'
                    }, { autoDismiss: true, autoDismissDelay: 3000 });
                } else {
                    this.init();
                    this.showError('פג תוקף בקשת האישור');
                    document.getElementById('approvalFooter').style.display = 'none';
                }
                return;
            }

            // ===== FALLBACK: Old modal approach if NotificationTemplates not loaded =====
            this.init();

            // Check if modal body was corrupted by showNoBiometricMessage()
            const approvalLoading = document.getElementById('approvalLoading');
            if (!approvalLoading) {
                console.warn('[ApprovalModal] Modal body elements missing, recreating modal...');
                if (this.modalElement) {
                    this.modalElement.remove();
                    this.modalElement = null;
                }
                this.init();
            }

            // Clear any leftover event handlers
            if (this._escapeHandler) {
                document.removeEventListener('keydown', this._escapeHandler);
                this._escapeHandler = null;
            }
            this.modalElement.onclick = null;

            // Reset to fullscreen mode
            this.modalElement.classList.add('approval-fullscreen');
            const modalInner = this.modalElement.querySelector('.approval-modal');
            if (modalInner) {
                modalInner.classList.add('approval-modal-fullscreen');
            }

            // Hide close button
            const closeBtn = this.modalElement.querySelector('.approval-modal-close');
            if (closeBtn) {
                closeBtn.style.display = 'none';
            }

            // Reset state
            document.getElementById('approvalLoading').style.display = 'none';
            document.getElementById('approvalContent').style.display = 'block';
            document.getElementById('approvalResponded').style.display = 'none';
            document.getElementById('approvalError').style.display = 'none';
            document.getElementById('approvalFooter').style.display = 'flex';

            // Reset button states
            const btnApprove = document.getElementById('btnModalApprove');
            const btnReject = document.getElementById('btnModalReject');
            if (btnApprove) btnApprove.disabled = false;
            if (btnReject) btnReject.disabled = false;

            // Push history state to block back navigation
            this._log('BEFORE_PUSH_STATE', { notificationId });
            history.pushState({ approvalModal: true }, '', window.location.href);
            this._hasHistoryState = true;
            this._log('AFTER_PUSH_STATE', { notificationId });

            // Prevent page scroll
            document.body.style.overflow = 'hidden';

            this.modalElement.style.display = 'flex';

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

        } catch (error) {
            console.error('[ApprovalModal] Error loading approval:', error);
            // Log to debug endpoint instead of blocking with alert
            try {
                navigator.sendBeacon('/dashboard/dashboards/cemeteries/api/debug-log.php', JSON.stringify({
                    page: 'APPROVAL_MODAL',
                    e: 'SHOW_ERROR',
                    error: error.message,
                    stack: error.stack,
                    ts: new Date().toISOString()
                }));
            } catch(e) {}

            if (window.NotificationTemplates) {
                window.NotificationTemplates.showInfoNotification({
                    title: 'שגיאה',
                    body: error.message,
                    level: 'error'
                }, { autoDismiss: true, autoDismissDelay: 5000 });
            } else {
                this.init();
                this.showError(error.message);
            }
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
        let hasBiometric = false;
        try {
            hasBiometric = await window.biometricAuth.userHasBiometric();
        } catch (e) {
            console.error('[ApprovalModal] Error checking biometric:', e);
            this.showError('שגיאה בבדיקת אימות ביומטרי. נסה שוב.');
            btnApprove.disabled = false;
            btnReject.disabled = false;
            return;
        }
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
                <button type="button" class="btn-back-to-approval" onclick="ApprovalModal.backToApproval()">
                    ← חזור לבקשת האישור
                </button>
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
                    background: linear-gradient(135deg, var(--primary-color, #667eea) 0%, var(--primary-dark, #764ba2) 100%);
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
                    box-shadow: 0 8px 20px color-mix(in srgb, var(--primary-color, #667eea) 40%, transparent);
                }
                .setup-hint {
                    font-size: 14px;
                    color: #94a3b8 !important;
                    margin-top: 16px !important;
                }
                .btn-back-to-approval {
                    background: transparent;
                    border: 1px solid #cbd5e1;
                    color: #64748b;
                    padding: 12px 24px;
                    font-size: 16px;
                    border-radius: 10px;
                    cursor: pointer;
                    margin-top: 16px;
                    transition: all 0.2s;
                }
                .btn-back-to-approval:hover {
                    background: #f1f5f9;
                    border-color: #94a3b8;
                }
            `;
            document.head.appendChild(style);
        }
    },

    /**
     * Go back to approval buttons from no-biometric screen
     */
    backToApproval() {
        // Recreate the modal to restore original state
        if (this.modalElement) {
            this.modalElement.remove();
            this.modalElement = null;
        }
        // Re-show the modal
        if (this.currentNotificationId) {
            this.show(this.currentNotificationId);
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
                this.showResponded(response, true); // autoClose for fresh responses
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
     * Show entity approval page in an iframe
     * This replaces the generic approval content with the detailed entity-approve.php page
     */
    showEntityApprovalIframe(url) {
        // Reset modal to fullscreen mode
        this.modalElement.classList.add('approval-fullscreen');
        const modalInner = this.modalElement.querySelector('.approval-modal');
        if (modalInner) {
            modalInner.classList.add('approval-modal-fullscreen');
        }

        // Show the modal
        this.modalElement.style.display = 'flex';

        // Prevent page scroll
        document.body.style.overflow = 'hidden';

        // Push history state - this creates a new "screen" that back button will close
        history.pushState({ entityApproval: true }, '', window.location.href);
        this._hasHistoryState = true;

        // Allow back button to close this modal (unlike regular approval)
        this._allowBackClose = true;

        // Hide all content
        document.getElementById('approvalLoading').style.display = 'none';
        document.getElementById('approvalContent').style.display = 'none';
        document.getElementById('approvalResponded').style.display = 'none';
        document.getElementById('approvalError').style.display = 'none';
        document.getElementById('approvalFooter').style.display = 'none'; // Hide default buttons - iframe has its own

        // Hide modal header - the iframe has its own header
        const header = this.modalElement.querySelector('.approval-modal-header');
        if (header) {
            header.style.display = 'none';
        }

        // עדכון צבע שורת הסטטוס של הפלאפון
        // תגית meta בתוך iframe לא משפיעה על הדף הראשי
        this._updateThemeColor();

        // Get modal body and remove padding for full-screen iframe
        const body = document.querySelector('.approval-modal-body');
        body.style.padding = '0';
        body.style.overflow = 'hidden';

        // Create iframe container
        let iframeContainer = document.getElementById('entityApprovalIframe');
        if (!iframeContainer) {
            iframeContainer = document.createElement('div');
            iframeContainer.id = 'entityApprovalIframe';
            iframeContainer.style.cssText = `
                width: 100%;
                height: 100%;
                flex: 1;
                display: flex;
                flex-direction: column;
            `;

            const iframe = document.createElement('iframe');
            iframe.id = 'entityApproveFrame';
            iframe.style.cssText = `
                width: 100%;
                height: 100%;
                min-height: 500px;
                flex: 1;
                border: none;
                border-radius: 0;
            `;
            iframeContainer.appendChild(iframe);

            // Add CSS for iframe mode
            if (!document.getElementById('entityApprovalIframeStyles')) {
                const style = document.createElement('style');
                style.id = 'entityApprovalIframeStyles';
                style.textContent = `
                    .approval-modal-fullscreen .approval-modal-body:has(#entityApprovalIframe) {
                        padding: 0;
                        overflow: hidden;
                    }
                    #entityApprovalIframe iframe {
                        background: var(--bg-primary, white);
                    }
                `;
                document.head.appendChild(style);
            }

            body.appendChild(iframeContainer);
        }

        // Show iframe container
        iframeContainer.style.display = 'flex';

        // Load the entity approval page
        const iframe = document.getElementById('entityApproveFrame');
        const separator = url.includes('?') ? '&' : '?';
        iframe.src = url + separator + 'embed=1'; // Add embed parameter to indicate iframe mode

        // Listen for messages from the iframe
        const messageHandler = (event) => {
            if (event.data && event.data.type === 'entityApprovalComplete') {
                window.removeEventListener('message', messageHandler);
                // Just close - the iframe already showed the result
                this.close();
            }
        };
        window.addEventListener('message', messageHandler);

        // Store handler for cleanup
        this._iframeMessageHandler = messageHandler;
    },

    /**
     * Show responded state (as a small popup with X to close)
     * @param {string} status - 'approved' or 'rejected'
     * @param {boolean} autoClose - if true, auto-close after 2 seconds (for fresh responses)
     */
    showResponded(status, autoClose = false) {
        // Hide loading and other content
        document.getElementById('approvalLoading').style.display = 'none';
        document.getElementById('approvalContent').style.display = 'none';
        document.getElementById('approvalError').style.display = 'none';
        document.getElementById('approvalFooter').style.display = 'none';

        // Switch to small popup mode (not fullscreen)
        this.modalElement.classList.remove('approval-fullscreen');
        const modalInner = this.modalElement.querySelector('.approval-modal');
        modalInner.classList.remove('approval-modal-fullscreen');

        // Add close button to header if not exists
        const header = this.modalElement.querySelector('.approval-modal-header');
        if (!header.querySelector('.approval-modal-close')) {
            const closeBtn = document.createElement('button');
            closeBtn.className = 'approval-modal-close';
            closeBtn.innerHTML = '×';
            closeBtn.onclick = () => this.close();
            header.appendChild(closeBtn);
        } else {
            header.querySelector('.approval-modal-close').style.display = 'block';
        }

        // Allow closing on overlay click and escape
        this.modalElement.onclick = (e) => {
            if (e.target === this.modalElement) {
                this.close();
            }
        };
        this._escapeHandler = (e) => {
            if (e.key === 'Escape') {
                this.close();
            }
        };
        document.addEventListener('keydown', this._escapeHandler);

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

        // Auto close only for fresh responses, not when viewing history
        if (autoClose) {
            setTimeout(() => this.close(), 2000);
        }
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
     *
     * HISTORY STATE MANAGEMENT:
     * -------------------------
     * When opening the modal, we push a history state (pushState) to enable back button handling.
     * When closing, we need to clean up this state:
     *
     * 1. If closed via POPSTATE (user pressed back button):
     *    - Browser already went back in history (popstate fired = browser navigated)
     *    - We do NOT call history.back() - would go back twice!
     *    - Just close the modal and call the callback
     *
     * 2. If closed via OTHER means (autoClose, iframe message, button click, escape key):
     *    - Browser did NOT go back yet
     *    - We MUST call history.back() to remove our pushed state
     *    - Set _ignoreNextPopstate flag so the resulting popstate is ignored
     *
     * This prevents history accumulation where each notification adds states
     * that never get removed, requiring multiple back presses.
     */
    close() {
        // Prevent re-entry (close() can be called multiple times from different triggers)
        if (this._isClosing) return;
        this._isClosing = true;

        // Capture flags before reset - we need to know HOW we got here
        const closedViaPopstate = this._closedViaPopstate;  // Was back button pressed?
        const hadHistoryState = this._hasHistoryState;       // Did we push a state when opening?
        this._closedViaPopstate = false;
        this._hasHistoryState = false;

        this._log('CLOSE', { viaPopstate: closedViaPopstate, hadHistoryState: hadHistoryState });

        if (this.modalElement) {
            this.modalElement.style.display = 'none';
            // Reset to fullscreen mode for next use
            this.modalElement.classList.add('approval-fullscreen');
            const modalInner = this.modalElement.querySelector('.approval-modal');
            modalInner.classList.add('approval-modal-fullscreen');
            // Hide close button
            const closeBtn = this.modalElement.querySelector('.approval-modal-close');
            if (closeBtn) {
                closeBtn.style.display = 'none';
            }
            // Restore header (might have been hidden for iframe mode)
            const header = this.modalElement.querySelector('.approval-modal-header');
            if (header) {
                header.style.display = '';
            }
            // Restore modal body padding (might have been removed for iframe mode)
            const body = this.modalElement.querySelector('.approval-modal-body');
            if (body) {
                body.style.padding = '';
                body.style.overflow = '';
            }
            // Remove overlay click handler
            this.modalElement.onclick = null;
        }
        // Remove escape handler
        if (this._escapeHandler) {
            document.removeEventListener('keydown', this._escapeHandler);
            this._escapeHandler = null;
        }
        // Remove iframe message handler
        if (this._iframeMessageHandler) {
            window.removeEventListener('message', this._iframeMessageHandler);
            this._iframeMessageHandler = null;
        }
        // Clean up iframe if exists
        const iframeContainer = document.getElementById('entityApprovalIframe');
        if (iframeContainer) {
            iframeContainer.style.display = 'none';
            const iframe = document.getElementById('entityApproveFrame');
            if (iframe) iframe.src = 'about:blank';
        }
        // שחזור צבע שורת הסטטוס המקורי
        this._restoreThemeColor();
        // Restore page scroll
        document.body.style.overflow = '';
        this.currentNotificationId = null;
        // Reset back button behavior
        this._allowBackClose = false;

        // Save callback before cleanup
        const callback = this.onClose;
        this.onClose = null;

        // CRITICAL: History state cleanup to prevent accumulation
        // --------------------------------------------------------
        // Problem: Each notification pushes a state. If we don't clean up, history grows:
        //   Initial: 4 states
        //   After 3 notifications without cleanup: 7 states = 3 extra back presses needed!
        //
        // Solution:
        //   - closedViaPopstate=true → browser already went back → do nothing
        //   - closedViaPopstate=false → browser didn't go back → we must call history.back()
        if (hadHistoryState && !closedViaPopstate) {
            this._log('GOING_BACK_IN_HISTORY');
            // Set flag so the resulting popstate event is ignored (we triggered it, not the user)
            this._ignoreNextPopstate = true;
            history.back();
        }

        this._isClosing = false;

        // קריאה ל-callback אם הוגדר (לעדכון אייפריים וכו')
        // NOTE: onClose callback handles notification flow continuation (set by login-notifications.js)
        // Do NOT add additional flow handling here to avoid double-processing
        if (typeof callback === 'function') {
            callback();
        }
    },

    /**
     * עדכון צבע שורת הסטטוס של הפלאפון
     * צבעים ממרכז העיצוב (user-preferences.css)
     */
    _updateThemeColor() {
        // שמירת הצבע המקורי
        const existingMeta = document.querySelector('meta[name="theme-color"]');
        if (existingMeta) {
            this._originalThemeColor = existingMeta.getAttribute('content');
        }

        // קביעת הצבע לפי ערכת הנושא
        const body = document.body;
        const isDarkMode = body.classList.contains('dark-theme') || document.documentElement.getAttribute('data-theme') === 'dark';
        const isGreen = body.classList.contains('color-scheme-green') || document.documentElement.getAttribute('data-color-scheme') === 'green';

        let themeColor;
        if (isDarkMode) {
            themeColor = '#374151'; // מצב כהה - אפור
        } else if (isGreen) {
            themeColor = '#059669'; // ירוק
        } else {
            themeColor = '#667eea'; // סגול (ברירת מחדל)
        }

        // עדכון או יצירת תגית meta
        if (existingMeta) {
            existingMeta.setAttribute('content', themeColor);
        } else {
            const meta = document.createElement('meta');
            meta.name = 'theme-color';
            meta.content = themeColor;
            document.head.appendChild(meta);
        }
    },

    /**
     * שחזור הצבע המקורי
     */
    _restoreThemeColor() {
        if (this._originalThemeColor) {
            const meta = document.querySelector('meta[name="theme-color"]');
            if (meta) {
                meta.setAttribute('content', this._originalThemeColor);
            }
            this._originalThemeColor = null;
        }
    }
};

// Auto-init when script loads
// NOTE: pendingApprovalId handling moved to login-notifications.js to avoid race conditions
// Only handle direct URL parameter here (approval_id in URL)
document.addEventListener('DOMContentLoaded', () => {
    // Check URL for direct approval parameter only
    const urlParams = new URLSearchParams(window.location.search);
    const approvalId = urlParams.get('approval_id');

    if (approvalId) {
        // Direct URL access - show immediately
        sessionStorage.removeItem('pendingApprovalId');
        ApprovalModal.show(parseInt(approvalId));
    }
    // pendingApprovalId is handled by LoginNotificationsNav to avoid duplicate handling
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
