/**
 * Notifications Manager
 * @version 1.0.0
 */

const NotificationsManager = {
    apiUrl: '/dashboard/dashboards/cemeteries/notifications/api/notifications-api.php',
    users: [],
    selectedUsers: new Set(),
    notifications: [],

    /**
     * Initialize the manager
     */
    init() {
        this.loadUsers();
        this.loadNotifications();
        this.setupFormHandlers();
        this.setMinDate();
    },

    /**
     * Set minimum date for schedule to today
     */
    setMinDate() {
        const today = new Date().toISOString().split('T')[0];
        const dateInput = document.getElementById('scheduleDate');
        if (dateInput) {
            dateInput.min = today;
            dateInput.value = today;
        }

        const timeInput = document.getElementById('scheduleTime');
        if (timeInput) {
            const now = new Date();
            const hours = String(now.getHours()).padStart(2, '0');
            const minutes = String(now.getMinutes()).padStart(2, '0');
            timeInput.value = `${hours}:${minutes}`;
        }
    },

    /**
     * Setup form event handlers
     */
    setupFormHandlers() {
        const form = document.getElementById('notificationForm');
        if (form) {
            form.addEventListener('submit', (e) => this.handleSubmit(e));
        }
    },

    /**
     * Load users from API
     */
    async loadUsers() {
        try {
            const response = await fetch(`${this.apiUrl}?action=get_users`);
            const result = await response.json();

            if (result.success) {
                this.users = result.data;
                this.renderUsersList();
            }
        } catch (error) {
            console.error('Error loading users:', error);
        }
    },

    /**
     * Render users list in the form
     */
    renderUsersList() {
        const container = document.getElementById('usersList');
        if (!container) return;

        if (this.users.length === 0) {
            container.innerHTML = '<div class="no-users">לא נמצאו משתמשים</div>';
            return;
        }

        container.innerHTML = this.users.map(user => `
            <div class="user-item" data-user-id="${user.id}" onclick="NotificationsManager.toggleUser(${user.id})">
                <input type="checkbox" ${this.selectedUsers.has(user.id) ? 'checked' : ''}>
                <span class="user-name">${this.escapeHtml(user.name || user.username)}</span>
                <span class="user-email">${this.escapeHtml(user.email || '')}</span>
            </div>
        `).join('');
    },

    /**
     * Filter users by search query
     */
    filterUsers(query) {
        const items = document.querySelectorAll('.user-item');
        const lowerQuery = query.toLowerCase();

        items.forEach(item => {
            const name = item.querySelector('.user-name').textContent.toLowerCase();
            const email = item.querySelector('.user-email').textContent.toLowerCase();

            if (name.includes(lowerQuery) || email.includes(lowerQuery)) {
                item.style.display = '';
            } else {
                item.style.display = 'none';
            }
        });
    },

    /**
     * Toggle user selection
     */
    toggleUser(userId) {
        if (this.selectedUsers.has(userId)) {
            this.selectedUsers.delete(userId);
        } else {
            this.selectedUsers.add(userId);
        }
        this.renderUsersList();
        this.updateSelectedUsersDisplay();
    },

    /**
     * Update selected users display
     */
    updateSelectedUsersDisplay() {
        const container = document.getElementById('selectedUsersDisplay');
        if (!container) return;

        if (this.selectedUsers.size === 0) {
            container.innerHTML = '<span class="no-selection">לא נבחרו משתמשים</span>';
            return;
        }

        const selectedUserObjects = this.users.filter(u => this.selectedUsers.has(u.id));
        container.innerHTML = selectedUserObjects.map(user => `
            <span class="selected-user-tag">
                ${this.escapeHtml(user.name || user.username)}
                <span class="remove-tag" onclick="event.stopPropagation(); NotificationsManager.toggleUser(${user.id})">×</span>
            </span>
        `).join('');
    },

    /**
     * Toggle user selection visibility
     */
    toggleUserSelection() {
        const sendToAll = document.getElementById('sendToAll').checked;
        const userRow = document.getElementById('userSelectionRow');

        if (sendToAll) {
            userRow.style.display = 'none';
            this.selectedUsers.clear();
            this.renderUsersList();
            this.updateSelectedUsersDisplay();
        } else {
            userRow.style.display = '';
        }
    },

    /**
     * Toggle schedule fields visibility
     */
    toggleScheduleFields() {
        const sendTime = document.querySelector('input[name="send_time"]:checked').value;
        const scheduleFields = document.getElementById('scheduleFields');

        if (sendTime === 'scheduled') {
            scheduleFields.style.display = '';
        } else {
            scheduleFields.style.display = 'none';
        }
    },

    /**
     * Toggle approval fields visibility
     */
    toggleApprovalFields() {
        const requiresApproval = document.getElementById('requiresApproval').checked;
        const approvalFields = document.getElementById('approvalFields');

        if (requiresApproval) {
            approvalFields.style.display = '';
        } else {
            approvalFields.style.display = 'none';
        }
    },

    /**
     * Load notifications from API
     */
    async loadNotifications() {
        const loadingState = document.getElementById('loadingState');
        const emptyState = document.getElementById('emptyState');
        const tableBody = document.getElementById('notificationsTableBody');

        loadingState.style.display = '';
        emptyState.style.display = 'none';
        tableBody.innerHTML = '';

        try {
            const statusFilter = document.getElementById('statusFilter').value;
            let url = `${this.apiUrl}?action=list`;
            if (statusFilter) {
                url += `&status=${statusFilter}`;
            }

            const response = await fetch(url);
            const result = await response.json();

            loadingState.style.display = 'none';

            if (result.success && result.data.length > 0) {
                this.notifications = result.data;
                this.renderNotificationsTable();
            } else {
                emptyState.style.display = '';
            }
        } catch (error) {
            console.error('Error loading notifications:', error);
            loadingState.style.display = 'none';
            emptyState.style.display = '';
        }
    },

    /**
     * Render notifications table
     */
    renderNotificationsTable() {
        const tableBody = document.getElementById('notificationsTableBody');

        tableBody.innerHTML = this.notifications.map(notification => {
            const targetUsers = JSON.parse(notification.target_users || '[]');
            const userCount = targetUsers.includes('all') ? 'כל המשתמשים' : `${targetUsers.length} משתמשים`;

            const scheduledAt = notification.scheduled_at
                ? this.formatDateTime(notification.scheduled_at)
                : 'מיידית';

            return `
                <tr class="notification-row" data-notification-id="${notification.id}">
                    <td>
                        <div class="expandable-title" onclick="NotificationsManager.toggleExpand(${notification.id})">
                            <span class="expand-icon">▶</span>
                            <strong>${this.escapeHtml(notification.title)}</strong>
                        </div>
                        <div style="font-size: 12px; color: var(--text-muted); margin-top: 4px; margin-right: 20px;">
                            ${this.escapeHtml(notification.body.substring(0, 50))}${notification.body.length > 50 ? '...' : ''}
                        </div>
                    </td>
                    <td>
                        <span class="type-badge ${notification.notification_type}">
                            ${this.getTypeLabel(notification.notification_type)}
                        </span>
                        ${notification.requires_approval == 1 ? '<span class="approval-badge requires" title="דרוש אישור ביומטרי">🔐</span>' : ''}
                    </td>
                    <td>${userCount}</td>
                    <td>${scheduledAt}</td>
                    <td>
                        <span class="status-badge status-${notification.status}">
                            ${this.getStatusLabel(notification.status)}
                        </span>
                    </td>
                    <td>${this.formatDateTime(notification.created_at)}</td>
                    <td>
                        <div class="action-buttons">
                            ${notification.status === 'pending' && window.canEdit ? `
                                <button class="btn btn-sm btn-secondary" onclick="NotificationsManager.editNotification(${notification.id})">
                                    עריכה
                                </button>
                            ` : ''}
                            ${notification.status === 'pending' && window.canDelete ? `
                                <button class="btn btn-sm btn-danger" onclick="NotificationsManager.cancelNotification(${notification.id})">
                                    ביטול
                                </button>
                            ` : ''}
                            ${window.canDelete ? `
                                <button class="btn btn-sm btn-outline-danger" onclick="NotificationsManager.deleteNotification(${notification.id})" title="מחיקה מההיסטוריה">
                                    🗑️
                                </button>
                            ` : ''}
                        </div>
                    </td>
                </tr>
                <tr class="notification-details-row" id="details-${notification.id}" style="display: none;">
                    <td colspan="7">
                        <div class="notification-details-container" id="details-container-${notification.id}">
                            <div class="loading-spinner">טוען פרטים...</div>
                        </div>
                    </td>
                </tr>
            `;
        }).join('');
    },

    /**
     * Toggle expand notification details
     */
    async toggleExpand(id) {
        const detailsRow = document.getElementById(`details-${id}`);
        const mainRow = document.querySelector(`tr[data-notification-id="${id}"]`);
        const expandIcon = mainRow.querySelector('.expand-icon');

        if (detailsRow.style.display === 'none') {
            detailsRow.style.display = '';
            expandIcon.textContent = '▼';
            mainRow.classList.add('expanded');

            // Load details
            await this.loadDeliveryStatus(id);
        } else {
            detailsRow.style.display = 'none';
            expandIcon.textContent = '▶';
            mainRow.classList.remove('expanded');
        }
    },

    /**
     * Load delivery status for a notification
     */
    async loadDeliveryStatus(id) {
        const container = document.getElementById(`details-container-${id}`);

        try {
            const response = await fetch(`${this.apiUrl}?action=get_delivery_status&id=${id}`);
            const result = await response.json();

            if (result.success) {
                this.renderDeliveryStatus(id, result.notification, result.users);
            } else {
                container.innerHTML = `<div class="error-message">שגיאה: ${result.error}</div>`;
            }
        } catch (error) {
            console.error('Error loading delivery status:', error);
            container.innerHTML = '<div class="error-message">שגיאה בטעינת פרטים</div>';
        }
    },

    /**
     * Render delivery status per user
     */
    renderDeliveryStatus(notificationId, notification, users) {
        const container = document.getElementById(`details-container-${notificationId}`);
        const requiresApproval = notification.requires_approval == 1;

        if (users.length === 0) {
            container.innerHTML = '<div class="no-users-message">לא נמצאו משתמשים</div>';
            return;
        }

        // Calculate stats
        const deliveredCount = users.filter(u => u.is_delivered > 0).length;
        const pendingCount = users.filter(u => u.is_delivered === 0 || u.is_delivered === '0').length;
        const hasAppCount = users.filter(u => u.has_push_subscription > 0).length;

        // Approval stats (if applicable)
        const approvedCount = requiresApproval ? users.filter(u => u.approval_status === 'approved').length : 0;
        const rejectedCount = requiresApproval ? users.filter(u => u.approval_status === 'rejected').length : 0;
        const pendingApprovalCount = requiresApproval ? users.filter(u => !u.approval_status || u.approval_status === 'pending').length : 0;

        container.innerHTML = `
            <div class="delivery-status-header">
                <h4>${requiresApproval ? 'סטטוס אישורים' : 'סטטוס שליחה למשתמשים'}</h4>
                <div class="delivery-stats">
                    ${requiresApproval ? `
                        <span class="stat approved">✓ ${approvedCount} אישרו</span>
                        <span class="stat rejected">✗ ${rejectedCount} דחו</span>
                        <span class="stat pending">⏳ ${pendingApprovalCount} ממתינים</span>
                    ` : `
                        <span class="stat delivered">✓ ${deliveredCount} נמסר</span>
                        <span class="stat pending">${pendingCount} ממתין</span>
                    `}
                    <span class="stat has-app">📱 ${hasAppCount} עם אפליקציה</span>
                </div>
            </div>
            <div class="users-delivery-list">
                ${users.map(user => `
                    <div class="user-delivery-item">
                        <div class="user-info">
                            <span class="user-name">${this.escapeHtml(user.name || user.username)}</span>
                            <span class="user-email">${this.escapeHtml(user.email || '')}</span>
                        </div>
                        <div class="user-status">
                            ${user.has_push_subscription > 0
                                ? '<span class="app-badge has-app" title="מותקנת אפליקציה">📱</span>'
                                : '<span class="app-badge no-app" title="אין אפליקציה מותקנת">🌐</span>'}
                            ${requiresApproval ? this.renderApprovalStatus(user) : this.renderDeliveryBadge(user)}
                        </div>
                        <div class="user-actions">
                            <button class="btn btn-xs btn-primary" onclick="NotificationsManager.resendToUser(${notificationId}, ${user.id})" title="שלח שוב">
                                שלח שוב
                            </button>
                        </div>
                    </div>
                `).join('')}
            </div>
        `;
    },

    /**
     * Render delivery badge
     */
    renderDeliveryBadge(user) {
        return user.is_delivered > 0
            ? '<span class="delivery-badge delivered">✓ נמסר</span>'
            : '<span class="delivery-badge pending">⏳ ממתין</span>';
    },

    /**
     * Render approval status badge
     */
    renderApprovalStatus(user) {
        const status = user.approval_status || 'pending';
        const statusLabels = {
            'pending': { class: 'pending', icon: '⏳', text: 'ממתין לאישור' },
            'approved': { class: 'approved', icon: '✓', text: 'אושר' },
            'rejected': { class: 'rejected', icon: '✗', text: 'נדחה' },
            'expired': { class: 'expired', icon: '⏰', text: 'פג תוקף' }
        };
        const s = statusLabels[status] || statusLabels['pending'];

        let html = `<span class="approval-badge ${s.class}">${s.icon} ${s.text}</span>`;

        // Add biometric indicator if approved with biometric
        if (status === 'approved' && user.biometric_verified > 0) {
            html += '<span class="biometric-icon" title="אומת ביומטרית">🔐</span>';
        }

        // Add response time if available
        if (user.responded_at) {
            html += `<span class="response-time" style="font-size: 11px; color: var(--text-muted); margin-right: 8px;">${this.formatDateTime(user.responded_at)}</span>`;
        }

        return html;
    },

    /**
     * Resend notification to specific user
     */
    async resendToUser(notificationId, userId) {
        if (!confirm('האם לשלוח שוב את ההתראה למשתמש זה?')) {
            return;
        }

        try {
            const response = await fetch(this.apiUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'resend_to_user',
                    notification_id: notificationId,
                    user_id: userId
                })
            });

            const result = await response.json();

            if (result.success) {
                this.showMessage('ההתראה נשלחה בהצלחה', 'success');
                // Reload delivery status
                await this.loadDeliveryStatus(notificationId);
            } else {
                this.showMessage(result.error || 'שגיאה בשליחה', 'error');
            }
        } catch (error) {
            console.error('Error resending notification:', error);
            this.showMessage('שגיאה בשליחה', 'error');
        }
    },

    /**
     * Delete notification from history
     */
    async deleteNotification(id) {
        if (!confirm('האם למחוק את ההתראה מההיסטוריה? פעולה זו לא ניתנת לביטול.')) {
            return;
        }

        try {
            const response = await fetch(this.apiUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'delete', id })
            });

            const result = await response.json();

            if (result.success) {
                this.showMessage('ההתראה נמחקה בהצלחה', 'success');
                this.loadNotifications();
            } else {
                this.showMessage(result.error || 'שגיאה במחיקת ההתראה', 'error');
            }
        } catch (error) {
            console.error('Error deleting notification:', error);
            this.showMessage('שגיאה במחיקת ההתראה', 'error');
        }
    },

    /**
     * Get type label in Hebrew
     */
    getTypeLabel(type) {
        const labels = {
            'info': 'מידע',
            'warning': 'אזהרה',
            'urgent': 'דחוף'
        };
        return labels[type] || type;
    },

    /**
     * Get status label in Hebrew
     */
    getStatusLabel(status) {
        const labels = {
            'pending': 'ממתין',
            'sent': 'נשלח',
            'cancelled': 'בוטל',
            'failed': 'נכשל'
        };
        return labels[status] || status;
    },

    /**
     * Format datetime for display
     */
    formatDateTime(dateStr) {
        if (!dateStr) return '-';
        const date = new Date(dateStr);
        return date.toLocaleDateString('he-IL') + ' ' + date.toLocaleTimeString('he-IL', { hour: '2-digit', minute: '2-digit' });
    },

    /**
     * Open create form
     */
    openCreateForm() {
        this.resetForm();
        document.getElementById('notificationFormContainer').style.display = '';
        document.getElementById('notificationFormContainer').scrollIntoView({ behavior: 'smooth' });
    },

    /**
     * Close form
     */
    closeForm() {
        document.getElementById('notificationFormContainer').style.display = 'none';
        this.resetForm();
    },

    /**
     * Reset form to default state
     */
    resetForm() {
        const form = document.getElementById('notificationForm');
        form.reset();
        document.getElementById('notificationId').value = '';
        this.selectedUsers.clear();
        this.renderUsersList();
        this.updateSelectedUsersDisplay();
        document.getElementById('userSelectionRow').style.display = '';
        document.getElementById('scheduleFields').style.display = 'none';
        this.setMinDate();
    },

    /**
     * Edit notification
     */
    async editNotification(id) {
        const notification = this.notifications.find(n => n.id === id);
        if (!notification) return;

        // Populate form
        document.getElementById('notificationId').value = notification.id;
        document.getElementById('title').value = notification.title;
        document.getElementById('body').value = notification.body;
        document.getElementById('url').value = notification.url || '';

        // Set notification type
        const typeRadio = document.querySelector(`input[name="notification_type"][value="${notification.notification_type}"]`);
        if (typeRadio) typeRadio.checked = true;

        // Set target users
        const targetUsers = JSON.parse(notification.target_users || '[]');
        if (targetUsers.includes('all')) {
            document.getElementById('sendToAll').checked = true;
            this.toggleUserSelection();
        } else {
            document.getElementById('sendToAll').checked = false;
            this.selectedUsers = new Set(targetUsers);
            this.renderUsersList();
            this.updateSelectedUsersDisplay();
        }

        // Set schedule
        if (notification.scheduled_at) {
            document.querySelector('input[name="send_time"][value="scheduled"]').checked = true;
            const date = new Date(notification.scheduled_at);
            document.getElementById('scheduleDate').value = date.toISOString().split('T')[0];
            document.getElementById('scheduleTime').value = date.toTimeString().slice(0, 5);
            this.toggleScheduleFields();
        } else {
            document.querySelector('input[name="send_time"][value="now"]').checked = true;
        }

        document.getElementById('notificationFormContainer').style.display = '';
        document.getElementById('notificationFormContainer').scrollIntoView({ behavior: 'smooth' });
    },

    /**
     * Cancel notification
     */
    async cancelNotification(id) {
        if (!confirm('האם אתה בטוח שברצונך לבטל את ההתראה?')) {
            return;
        }

        try {
            const response = await fetch(this.apiUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'cancel', id })
            });

            const result = await response.json();

            if (result.success) {
                this.showMessage('ההתראה בוטלה בהצלחה', 'success');
                this.loadNotifications();
            } else {
                this.showMessage(result.error || 'שגיאה בביטול ההתראה', 'error');
            }
        } catch (error) {
            console.error('Error cancelling notification:', error);
            this.showMessage('שגיאה בביטול ההתראה', 'error');
        }
    },

    /**
     * Handle form submit
     */
    async handleSubmit(e) {
        e.preventDefault();

        const form = e.target;
        const formData = new FormData(form);

        // Validate
        const sendToAll = document.getElementById('sendToAll').checked;
        if (!sendToAll && this.selectedUsers.size === 0) {
            this.showMessage('יש לבחור לפחות משתמש אחד', 'error');
            return;
        }

        const sendTime = document.querySelector('input[name="send_time"]:checked').value;
        if (sendTime === 'scheduled') {
            const date = document.getElementById('scheduleDate').value;
            const time = document.getElementById('scheduleTime').value;
            if (!date || !time) {
                this.showMessage('יש לבחור תאריך ושעה לתזמון', 'error');
                return;
            }
        }

        // Prepare data
        const requiresApproval = document.getElementById('requiresApproval').checked;
        const notifySenderCheckbox = document.getElementById('notifySender');
        const notifySender = notifySenderCheckbox ? notifySenderCheckbox.checked : false;

        const data = {
            action: formData.get('id') ? 'update' : 'create',
            id: formData.get('id') || null,
            title: formData.get('title'),
            body: formData.get('body'),
            notification_type: formData.get('notification_type'),
            url: formData.get('url') || null,
            target_users: sendToAll ? ['all'] : Array.from(this.selectedUsers),
            scheduled_at: null,
            requires_approval: requiresApproval ? 1 : 0,
            approval_message: requiresApproval ? formData.get('approval_message') : null,
            approval_expiry: requiresApproval ? formData.get('approval_expiry') : null,
            notify_sender: notifySender ? 1 : 0
        };

        if (sendTime === 'scheduled') {
            const date = formData.get('schedule_date');
            const time = formData.get('schedule_time');
            data.scheduled_at = `${date} ${time}:00`;
        }

        try {
            const submitBtn = document.getElementById('submitBtn');
            submitBtn.disabled = true;
            submitBtn.textContent = 'שומר...';

            const response = await fetch(this.apiUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data)
            });

            const result = await response.json();

            if (result.success) {
                this.showMessage(data.scheduled_at ? 'ההתראה תוזמנה בהצלחה' : 'ההתראה נשלחה בהצלחה', 'success');
                this.closeForm();
                this.loadNotifications();
            } else {
                this.showMessage(result.error || 'שגיאה בשמירת ההתראה', 'error');
            }
        } catch (error) {
            console.error('Error saving notification:', error);
            this.showMessage('שגיאה בשמירת ההתראה', 'error');
        } finally {
            const submitBtn = document.getElementById('submitBtn');
            submitBtn.disabled = false;
            submitBtn.textContent = 'שלח / תזמן';
        }
    },

    /**
     * Show message to user
     */
    showMessage(message, type = 'info') {
        // Simple alert for now - can be replaced with toast/snackbar
        alert(message);
    },

    /**
     * Escape HTML
     */
    escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
};

// Initialize on DOM ready
document.addEventListener('DOMContentLoaded', () => {
    NotificationsManager.init();
});
