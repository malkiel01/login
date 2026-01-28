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
                <tr>
                    <td>
                        <strong>${this.escapeHtml(notification.title)}</strong>
                        <div style="font-size: 12px; color: var(--text-muted); margin-top: 4px;">
                            ${this.escapeHtml(notification.body.substring(0, 50))}${notification.body.length > 50 ? '...' : ''}
                        </div>
                    </td>
                    <td>
                        <span class="type-badge ${notification.notification_type}">
                            ${this.getTypeLabel(notification.notification_type)}
                        </span>
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
                        </div>
                    </td>
                </tr>
            `;
        }).join('');
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
        const data = {
            action: formData.get('id') ? 'update' : 'create',
            id: formData.get('id') || null,
            title: formData.get('title'),
            body: formData.get('body'),
            notification_type: formData.get('notification_type'),
            url: formData.get('url') || null,
            target_users: sendToAll ? ['all'] : Array.from(this.selectedUsers),
            scheduled_at: null
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
