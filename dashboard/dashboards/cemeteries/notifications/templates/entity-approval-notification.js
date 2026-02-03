/**
 * Entity Approval Notification Template
 * Full screen approval for entity operations (create/edit/delete)
 * Shows all entity details with approve/reject buttons
 *
 * Features:
 * - Full screen display
 * - Shows complete entity details (customer, purchase, burial)
 * - Compare original vs new data for edits
 * - Approve/Reject buttons at bottom
 * - Cannot be dismissed without responding
 *
 * @version 1.0.0
 */

(function() {
    if (!window.NotificationTemplates) {
        console.error('[EntityApprovalNotification] NotificationTemplates not loaded');
        return;
    }

    /**
     * Show entity approval notification (full screen with details)
     * @param {Object} notification - Notification data (must have url with pendingId)
     * @param {Object} options - Display options
     */
    NotificationTemplates.showEntityApprovalNotification = function(notification, options = {}) {
        // Close any existing modal
        this.close();

        // Prevent page scroll
        document.body.style.overflow = 'hidden';

        // NOTE: History manipulation removed - handled by index.php navigation guard

        // Extract pending ID from URL
        const urlMatch = notification.url.match(/id=(\d+)/);
        const pendingId = urlMatch ? urlMatch[1] : null;

        if (!pendingId) {
            console.error('[EntityApprovalNotification] No pending ID in URL:', notification.url);
            return;
        }

        // Create overlay
        const overlay = document.createElement('div');
        overlay.className = 'notification-overlay entity-approval-overlay';
        overlay.innerHTML = `
            <div class="entity-approval-modal">
                <div class="entity-approval-header">
                    <h3>אישור פעולה</h3>
                </div>
                <div class="entity-approval-body" id="entityApprovalBody">
                    <div class="entity-approval-loading">
                        <div class="nt-spinner"></div>
                        <span>טוען פרטי הבקשה...</span>
                    </div>
                </div>
                <div class="entity-approval-footer" id="entityApprovalFooter" style="display: none;">
                    <button class="nt-btn nt-btn-approve" id="entityApproveBtn">
                        <span class="btn-icon">✓</span> אישור
                    </button>
                    <button class="nt-btn nt-btn-reject" id="entityRejectBtn">
                        <span class="btn-icon">✗</span> דחייה
                    </button>
                </div>
            </div>
        `;

        // Add styles
        this.addEntityApprovalStyles();

        // Add to DOM
        document.body.appendChild(overlay);
        this.activeModal = overlay;

        // Store pending ID
        this.currentPendingId = pendingId;
        this.currentNotificationId = notification.id;

        // Event handlers
        const approveBtn = overlay.querySelector('#entityApproveBtn');
        const rejectBtn = overlay.querySelector('#entityRejectBtn');

        approveBtn.addEventListener('click', () => this.handleEntityApprove(pendingId));
        rejectBtn.addEventListener('click', () => this.handleEntityReject(pendingId));

        // NOTE: popstate handler removed - handled by index.php navigation guard

        // Load entity details
        this.loadEntityDetails(pendingId);

        return overlay;
    };

    /**
     * Load entity details from API
     */
    NotificationTemplates.loadEntityDetails = async function(pendingId) {
        const body = document.getElementById('entityApprovalBody');
        const footer = document.getElementById('entityApprovalFooter');

        try {
            const response = await fetch(`/dashboard/dashboards/cemeteries/api/entity-approval-api.php?action=getPending&id=${pendingId}`, {
                credentials: 'include'
            });
            const data = await response.json();

            if (!data.success) {
                throw new Error(data.error || 'שגיאה בטעינת הבקשה');
            }

            const pending = data.data;

            // Check status
            if (pending.status !== 'pending') {
                body.innerHTML = this.renderStatusMessage(pending.status);
                return;
            }

            // Check expiration
            if (new Date(pending.expires_at) < new Date()) {
                body.innerHTML = this.renderStatusMessage('expired');
                return;
            }

            // Parse data
            const operationData = JSON.parse(pending.operation_data || '{}');
            const originalData = pending.original_data ? JSON.parse(pending.original_data) : null;

            // Render content
            body.innerHTML = this.renderEntityDetails(pending, operationData, originalData);
            footer.style.display = 'flex';

        } catch (error) {
            console.error('[EntityApprovalNotification] Error:', error);
            body.innerHTML = `
                <div class="entity-approval-error">
                    <div style="font-size: 48px; margin-bottom: 16px;">❌</div>
                    <p>${this.escapeHtml(error.message)}</p>
                </div>
            `;
        }
    };

    /**
     * Render entity details based on type
     */
    NotificationTemplates.renderEntityDetails = function(pending, operationData, originalData) {
        const entityLabels = {
            customers: 'לקוח',
            purchases: 'רכישה',
            burials: 'קבורה'
        };

        const actionLabels = {
            create: 'יצירת',
            edit: 'עריכת',
            delete: 'מחיקת'
        };

        const entityType = pending.entity_type;
        const action = pending.action;

        let html = `
            <div class="entity-details-container">
                <div class="entity-header-info">
                    <div class="entity-action-badge ${action}">
                        ${actionLabels[action] || action} ${entityLabels[entityType] || entityType}
                    </div>
                    <div class="entity-requester">
                        מבקש: <strong>${this.escapeHtml(pending.requester_name)}</strong>
                    </div>
                    <div class="entity-time">
                        ${new Date(pending.created_at).toLocaleString('he-IL')}
                    </div>
                </div>
        `;

        // Render based on entity type
        switch (entityType) {
            case 'customers':
                html += this.renderCustomerDetails(operationData, originalData, action);
                break;
            case 'purchases':
                html += this.renderPurchaseDetails(operationData, originalData, action);
                break;
            case 'burials':
                html += this.renderBurialDetails(operationData, originalData, action);
                break;
            default:
                html += this.renderGenericDetails(operationData, originalData);
        }

        // Add rejection form
        html += `
                <div class="rejection-form" id="entityRejectionForm" style="display: none;">
                    <h4>סיבת דחייה</h4>
                    <textarea id="entityRejectionReason" placeholder="הזן סיבה (אופציונלי)" rows="3"></textarea>
                    <div class="rejection-actions">
                        <button class="nt-btn nt-btn-reject" id="confirmEntityReject">שלח דחייה</button>
                        <button class="nt-btn nt-btn-close" id="cancelEntityReject">ביטול</button>
                    </div>
                </div>
            </div>
        `;

        return html;
    };

    /**
     * Render customer details
     */
    NotificationTemplates.renderCustomerDetails = function(data, original, action) {
        const fields = [
            { key: 'firstName', label: 'שם פרטי' },
            { key: 'lastName', label: 'שם משפחה' },
            { key: 'numId', label: 'ת.ז.' },
            { key: 'phone', label: 'טלפון' },
            { key: 'phoneMobile', label: 'נייד' },
            { key: 'address', label: 'כתובת' },
            { key: 'dateBirth', label: 'תאריך לידה', format: 'date' },
            { key: 'gender', label: 'מין' },
            { key: 'maritalStatus', label: 'מצב משפחתי' }
        ];

        return this.renderFieldsTable(fields, data, original, action);
    };

    /**
     * Render purchase details
     */
    NotificationTemplates.renderPurchaseDetails = function(data, original, action) {
        const fields = [
            { key: 'price', label: 'מחיר', format: 'currency' },
            { key: 'numOfPayments', label: 'מספר תשלומים' },
            { key: 'purchaseStatus', label: 'סטטוס' },
            { key: 'comment', label: 'הערות' }
        ];

        return this.renderFieldsTable(fields, data, original, action);
    };

    /**
     * Render burial details
     */
    NotificationTemplates.renderBurialDetails = function(data, original, action) {
        const fields = [
            { key: 'dateDeath', label: 'תאריך פטירה', format: 'date' },
            { key: 'dateBurial', label: 'תאריך קבורה', format: 'date' },
            { key: 'placeDeath', label: 'מקום פטירה' },
            { key: 'comment', label: 'הערות' }
        ];

        return this.renderFieldsTable(fields, data, original, action);
    };

    /**
     * Render generic details
     */
    NotificationTemplates.renderGenericDetails = function(data, original) {
        let html = '<div class="entity-fields-table"><table>';
        for (const [key, value] of Object.entries(data)) {
            if (key === 'unicId' || key === 'createDate' || key === 'updateDate') continue;
            html += `
                <tr>
                    <td class="field-label">${this.escapeHtml(key)}</td>
                    <td class="field-value">${this.escapeHtml(String(value || '-'))}</td>
                </tr>
            `;
        }
        html += '</table></div>';
        return html;
    };

    /**
     * Render fields table with comparison for edits
     */
    NotificationTemplates.renderFieldsTable = function(fields, data, original, action) {
        let html = '<div class="entity-fields-table"><table>';

        if (action === 'edit' && original) {
            // Show comparison
            html += `
                <thead>
                    <tr>
                        <th>שדה</th>
                        <th>ערך קודם</th>
                        <th>ערך חדש</th>
                    </tr>
                </thead>
                <tbody>
            `;

            for (const field of fields) {
                const oldVal = this.formatValue(original[field.key], field.format);
                const newVal = this.formatValue(data[field.key], field.format);
                const changed = oldVal !== newVal;

                html += `
                    <tr class="${changed ? 'changed' : ''}">
                        <td class="field-label">${field.label}</td>
                        <td class="field-old">${this.escapeHtml(oldVal)}</td>
                        <td class="field-new">${this.escapeHtml(newVal)}</td>
                    </tr>
                `;
            }
            html += '</tbody>';
        } else {
            // Show single values
            html += '<tbody>';
            for (const field of fields) {
                const value = this.formatValue(data[field.key], field.format);
                if (value && value !== '-') {
                    html += `
                        <tr>
                            <td class="field-label">${field.label}</td>
                            <td class="field-value">${this.escapeHtml(value)}</td>
                        </tr>
                    `;
                }
            }
            html += '</tbody>';
        }

        html += '</table></div>';
        return html;
    };

    /**
     * Format value based on type
     */
    NotificationTemplates.formatValue = function(value, format) {
        if (value === null || value === undefined || value === '') return '-';

        switch (format) {
            case 'date':
                try {
                    return new Date(value).toLocaleDateString('he-IL');
                } catch {
                    return value;
                }
            case 'currency':
                return '₪' + Number(value).toLocaleString('he-IL');
            default:
                return String(value);
        }
    };

    /**
     * Render status message
     */
    NotificationTemplates.renderStatusMessage = function(status) {
        const messages = {
            approved: { icon: '✅', text: 'הבקשה כבר אושרה', color: '#10b981' },
            rejected: { icon: '❌', text: 'הבקשה נדחתה', color: '#ef4444' },
            expired: { icon: '⏰', text: 'פג תוקף הבקשה', color: '#f59e0b' },
            cancelled: { icon: '🚫', text: 'הבקשה בוטלה', color: '#64748b' }
        };

        const msg = messages[status] || { icon: '❓', text: 'סטטוס לא ידוע', color: '#64748b' };

        return `
            <div class="entity-status-message" style="color: ${msg.color}">
                <div style="font-size: 64px; margin-bottom: 20px;">${msg.icon}</div>
                <p style="font-size: 20px; font-weight: 500;">${msg.text}</p>
            </div>
        `;
    };

    /**
     * Handle entity approve - requires biometric authentication
     */
    NotificationTemplates.handleEntityApprove = async function(pendingId) {
        const approveBtn = document.getElementById('entityApproveBtn');
        const rejectBtn = document.getElementById('entityRejectBtn');

        approveBtn.disabled = true;
        rejectBtn.disabled = true;

        // Check if biometric is available
        if (!window.biometricAuth || !window.biometricAuth.isSupported) {
            this.showEntityError('נדרש מכשיר התומך באימות ביומטרי');
            approveBtn.disabled = false;
            rejectBtn.disabled = false;
            return;
        }

        // Check if user has biometric registered
        let hasBiometric = false;
        try {
            hasBiometric = await window.biometricAuth.userHasBiometric();
        } catch (e) {
            console.error('[EntityApprovalNotification] Error checking biometric:', e);
            this.showEntityError('שגיאה בבדיקת אימות ביומטרי. נסה שוב.');
            approveBtn.disabled = false;
            rejectBtn.disabled = false;
            return;
        }

        if (!hasBiometric) {
            this.showNoBiometricForEntity();
            return;
        }

        // Perform biometric authentication - MANDATORY
        try {
            const result = await window.biometricAuth.authenticate();

            if (!result.success) {
                if (result.userCancelled) {
                    this.showEntityError('האימות הביומטרי בוטל. יש לאשר עם טביעת אצבע / Face ID');
                } else {
                    this.showEntityError('האימות הביומטרי נכשל. נסה שוב.');
                }
                approveBtn.disabled = false;
                rejectBtn.disabled = false;
                return;
            }

            // Biometric verified - proceed with approval
            const response = await fetch('/dashboard/dashboards/cemeteries/api/entity-approval-api.php?action=approve', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                credentials: 'include',
                body: JSON.stringify({
                    pendingId: parseInt(pendingId),
                    biometric_verified: true
                })
            });

            const data = await response.json();

            if (data.success) {
                // Mark notification as read
                if (this.currentNotificationId) {
                    this.markNotificationAsRead(this.currentNotificationId);
                }
                this.showEntityResponse('approved');
            } else {
                throw new Error(data.error || 'שגיאה באישור');
            }

        } catch (error) {
            console.error('[EntityApprovalNotification] Error:', error);
            this.showEntityError(error.message);
            approveBtn.disabled = false;
            rejectBtn.disabled = false;
        }
    };

    /**
     * Show error message in entity approval modal
     */
    NotificationTemplates.showEntityError = function(message) {
        const body = document.getElementById('entityApprovalBody');
        const currentContent = body.innerHTML;

        // Add error message at top
        const errorDiv = document.createElement('div');
        errorDiv.className = 'entity-error-message';
        errorDiv.innerHTML = `
            <div style="background: #fee2e2; color: #991b1b; padding: 12px 16px; border-radius: 8px; margin-bottom: 16px; text-align: center;">
                ⚠️ ${this.escapeHtml(message)}
            </div>
        `;

        // Insert at top of body
        body.insertBefore(errorDiv, body.firstChild);

        // Remove after 5 seconds
        setTimeout(() => {
            errorDiv.remove();
        }, 5000);
    };

    /**
     * Show message when user has no biometric registered
     */
    NotificationTemplates.showNoBiometricForEntity = function() {
        const body = document.getElementById('entityApprovalBody');
        const footer = document.getElementById('entityApprovalFooter');

        footer.style.display = 'none';

        body.innerHTML = `
            <div class="entity-no-biometric">
                <div style="font-size: 64px; margin-bottom: 20px;">🔐</div>
                <h4>נדרש אימות ביומטרי</h4>
                <p>כדי לאשר בקשה זו, יש להגדיר קודם אימות ביומטרי (טביעת אצבע / Face ID) במערכת.</p>
                <button type="button" class="nt-btn nt-btn-primary" id="goToSettingsBtn">
                    <span>🔧</span> הגדרת אימות ביומטרי
                </button>
                <p style="font-size: 14px; color: #94a3b8; margin-top: 16px;">לאחר ההגדרה, חזור לכאן לאישור הבקשה</p>
                <button type="button" class="nt-btn nt-btn-close" id="backToEntityApprovalBtn">
                    ← חזור לבקשת האישור
                </button>
            </div>
        `;

        // Add event handlers
        document.getElementById('goToSettingsBtn').onclick = () => {
            // Store current pending ID
            if (this.currentPendingId) {
                sessionStorage.setItem('pendingEntityApprovalId', this.currentPendingId);
            }
            if (this.currentNotificationId) {
                sessionStorage.setItem('pendingApprovalId', this.currentNotificationId);
            }
            window.location.href = '/dashboard/dashboards/cemeteries/user-settings/settings-page.php?section=security';
        };

        document.getElementById('backToEntityApprovalBtn').onclick = () => {
            // Reload the entity details
            if (this.currentPendingId) {
                this.loadEntityDetails(this.currentPendingId);
                footer.style.display = 'flex';
            }
        };
    };

    /**
     * Handle entity reject - show form
     */
    NotificationTemplates.handleEntityReject = function(pendingId) {
        const form = document.getElementById('entityRejectionForm');
        const rejectBtn = document.getElementById('entityRejectBtn');

        form.style.display = 'block';
        rejectBtn.style.display = 'none';

        // Add event listeners
        document.getElementById('confirmEntityReject').onclick = () => this.submitEntityReject(pendingId);
        document.getElementById('cancelEntityReject').onclick = () => {
            form.style.display = 'none';
            rejectBtn.style.display = 'flex';
        };
    };

    /**
     * Submit entity rejection
     */
    NotificationTemplates.submitEntityReject = async function(pendingId) {
        const reason = document.getElementById('entityRejectionReason').value;
        const approveBtn = document.getElementById('entityApproveBtn');
        const footer = document.getElementById('entityApprovalFooter');

        approveBtn.disabled = true;
        document.getElementById('confirmEntityReject').disabled = true;

        try {
            const response = await fetch('/dashboard/dashboards/cemeteries/api/entity-approval-api.php?action=reject', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                credentials: 'include',
                body: JSON.stringify({
                    pendingId: parseInt(pendingId),
                    reason: reason
                })
            });

            const data = await response.json();

            if (data.success) {
                this.showEntityResponse('rejected');
            } else {
                throw new Error(data.error || 'שגיאה בדחייה');
            }

        } catch (error) {
            console.error('[EntityApprovalNotification] Error:', error);
            alert('שגיאה: ' + error.message);
            approveBtn.disabled = false;
            document.getElementById('confirmEntityReject').disabled = false;
        }
    };

    /**
     * Show entity response result
     */
    NotificationTemplates.showEntityResponse = function(status) {
        const body = document.getElementById('entityApprovalBody');
        const footer = document.getElementById('entityApprovalFooter');

        footer.style.display = 'none';

        if (status === 'approved') {
            body.innerHTML = `
                <div class="entity-status-message" style="color: #10b981">
                    <div style="font-size: 64px; margin-bottom: 20px;">✅</div>
                    <p style="font-size: 20px; font-weight: 500;">הפעולה אושרה ובוצעה בהצלחה!</p>
                </div>
            `;
        } else {
            body.innerHTML = `
                <div class="entity-status-message" style="color: #ef4444">
                    <div style="font-size: 64px; margin-bottom: 20px;">❌</div>
                    <p style="font-size: 20px; font-weight: 500;">הפעולה נדחתה</p>
                </div>
            `;
        }

        // Auto close after 2 seconds
        setTimeout(() => this.close(), 2000);
    };

    // NOTE: handleEntityPopState removed - navigation guard in index.php handles this

    /**
     * Override close to clean up entity approval specific data
     */
    const originalEntityClose = NotificationTemplates.close;
    NotificationTemplates.close = function() {
        this.currentPendingId = null;
        this.currentNotificationId = null;
        originalEntityClose.call(this);
    };

    /**
     * Add styles
     */
    NotificationTemplates.addEntityApprovalStyles = function() {
        if (document.getElementById('entityApprovalStyles')) return;

        const style = document.createElement('style');
        style.id = 'entityApprovalStyles';
        style.textContent = `
            /* ============================================
               Entity Approval - Full Screen with Details
               ============================================ */

            .entity-approval-overlay {
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background: linear-gradient(135deg, var(--primary-color, #667eea) 0%, var(--primary-dark, #764ba2) 100%);
            }

            .entity-approval-modal {
                width: 100%;
                height: 100%;
                background: var(--bg-primary, white);
                display: flex;
                flex-direction: column;
            }

            .entity-approval-header {
                padding: 16px 20px;
                background: linear-gradient(135deg, var(--primary-color, #667eea) 0%, var(--primary-dark, #764ba2) 100%);
                color: white;
                flex-shrink: 0;
            }

            .entity-approval-header h3 {
                margin: 0;
                font-size: 18px;
                font-weight: 600;
                text-align: center;
            }

            .entity-approval-body {
                flex: 1;
                overflow-y: auto;
                padding: 20px;
            }

            .entity-approval-loading {
                display: flex;
                flex-direction: column;
                align-items: center;
                justify-content: center;
                height: 100%;
                gap: 16px;
                color: var(--text-muted, #64748b);
            }

            .entity-approval-loading .nt-spinner {
                width: 48px;
                height: 48px;
            }

            .entity-approval-footer {
                display: flex;
                gap: 12px;
                padding: 16px 20px;
                border-top: 1px solid var(--border-color, #e2e8f0);
                background: var(--bg-secondary, #f8fafc);
                flex-shrink: 0;
            }

            .entity-approval-footer .nt-btn {
                padding: 16px 24px;
                font-size: 17px;
            }

            /* Entity details container */
            .entity-details-container {
                max-width: 600px;
                margin: 0 auto;
            }

            .entity-header-info {
                text-align: center;
                margin-bottom: 24px;
                padding-bottom: 20px;
                border-bottom: 1px solid var(--border-color, #e2e8f0);
            }

            .entity-action-badge {
                display: inline-block;
                padding: 8px 20px;
                border-radius: 20px;
                font-weight: 600;
                font-size: 16px;
                margin-bottom: 12px;
            }

            .entity-action-badge.create {
                background: #dcfce7;
                color: #166534;
            }

            .entity-action-badge.edit {
                background: #fef3c7;
                color: #92400e;
            }

            .entity-action-badge.delete {
                background: #fee2e2;
                color: #991b1b;
            }

            .entity-requester {
                font-size: 15px;
                color: var(--text-secondary, #475569);
                margin-bottom: 4px;
            }

            .entity-time {
                font-size: 13px;
                color: var(--text-muted, #94a3b8);
            }

            /* Fields table */
            .entity-fields-table {
                background: var(--bg-secondary, #f8fafc);
                border-radius: 12px;
                overflow: hidden;
                margin-bottom: 20px;
            }

            .entity-fields-table table {
                width: 100%;
                border-collapse: collapse;
            }

            .entity-fields-table th {
                background: var(--bg-tertiary, #f1f5f9);
                padding: 12px 16px;
                text-align: right;
                font-weight: 600;
                font-size: 13px;
                color: var(--text-muted, #64748b);
            }

            .entity-fields-table td {
                padding: 12px 16px;
                border-bottom: 1px solid var(--border-color, #e2e8f0);
                font-size: 15px;
            }

            .entity-fields-table tr:last-child td {
                border-bottom: none;
            }

            .entity-fields-table .field-label {
                font-weight: 500;
                color: var(--text-secondary, #475569);
                width: 120px;
            }

            .entity-fields-table .field-value {
                color: var(--text-primary, #1e293b);
            }

            .entity-fields-table .field-old {
                color: var(--text-muted, #94a3b8);
                text-decoration: line-through;
            }

            .entity-fields-table .field-new {
                color: var(--text-primary, #1e293b);
                font-weight: 500;
            }

            .entity-fields-table tr.changed {
                background: #fef9c3;
            }

            /* Rejection form */
            .entity-details-container .rejection-form {
                margin-top: 20px;
                padding: 20px;
                background: var(--bg-secondary, #f8fafc);
                border-radius: 12px;
            }

            .entity-details-container .rejection-form h4 {
                margin: 0 0 12px;
                font-size: 16px;
                color: var(--text-primary, #1e293b);
            }

            .entity-details-container .rejection-form textarea {
                width: 100%;
                padding: 12px;
                border: 1px solid var(--border-color, #e2e8f0);
                border-radius: 8px;
                font-size: 15px;
                resize: none;
                margin-bottom: 12px;
            }

            .entity-details-container .rejection-actions {
                display: flex;
                gap: 12px;
            }

            /* Status message */
            .entity-status-message {
                display: flex;
                flex-direction: column;
                align-items: center;
                justify-content: center;
                height: 100%;
                text-align: center;
            }

            .entity-approval-error {
                display: flex;
                flex-direction: column;
                align-items: center;
                justify-content: center;
                height: 100%;
                text-align: center;
                color: #ef4444;
            }

            /* No biometric message */
            .entity-no-biometric {
                display: flex;
                flex-direction: column;
                align-items: center;
                justify-content: center;
                height: 100%;
                text-align: center;
                padding: 20px;
            }

            .entity-no-biometric h4 {
                color: #ef4444;
                font-size: 22px;
                margin-bottom: 16px;
            }

            .entity-no-biometric p {
                color: var(--text-secondary, #475569);
                font-size: 16px;
                max-width: 350px;
                margin-bottom: 20px;
            }

            .entity-no-biometric .nt-btn-primary {
                background: linear-gradient(135deg, var(--primary-color, #667eea) 0%, var(--primary-dark, #764ba2) 100%);
                color: white;
            }
        `;
        document.head.appendChild(style);
    };

    console.log('[EntityApprovalNotification] Template loaded');
})();
