// dashboard/assets/js/dashboard.js - לוגיקה של הדשבורד

class DashboardManager {
    constructor() {
        this.data = window.dashboardData || {};
        this.sessionSeconds = 0;
        this.activityLog = [];
        this.init();
    }

    init() {
        // חישוב זמן סשן
        const now = Math.floor(Date.now() / 1000);
        this.sessionSeconds = now - this.data.sessionStart;
        
        // טעינת רכיבים
        this.loadSessionInfo();
        this.loadUsersTable();
        this.loadActivityLog();
        this.loadApiEndpoints();
        
        // הפעלת טיימרים ועדכונים
        this.startSessionTimer();
        this.initEventListeners();
        this.startAutoRefresh();
    }

    // טעינת מידע על הסשן
    loadSessionInfo() {
        const sessionInfo = document.getElementById('sessionInfo');
        if (!sessionInfo) return;

        const sessionStartTime = new Date(this.data.sessionStart * 1000);
        const lastLogin = this.data.currentUser.last_login ? 
            new Date(this.data.currentUser.last_login) : null;

        sessionInfo.innerHTML = `
            <div class="info-row">
                <span class="info-label">מזהה משתמש:</span>
                <span class="info-value">#${this.data.currentUser.id}</span>
            </div>
            <div class="info-row">
                <span class="info-label">תחילת סשן:</span>
                <span class="info-value">${sessionStartTime.toLocaleTimeString('he-IL')}</span>
            </div>
            <div class="info-row">
                <span class="info-label">התחברות אחרונה:</span>
                <span class="info-value">
                    ${lastLogin ? this.formatDateTime(lastLogin) : 'לא זמין'}
                </span>
            </div>
            <div class="info-row">
                <span class="info-label">סטטוס חשבון:</span>
                <span class="info-value">
                    <span class="status-badge ${this.data.currentUser.is_active ? 'status-active' : 'status-inactive'}">
                        ${this.data.currentUser.is_active ? 'פעיל' : 'לא פעיל'}
                    </span>
                </span>
            </div>
        `;
    }

    // טעינת טבלת משתמשים
    loadUsersTable() {
        const usersTable = document.getElementById('usersTable');
        if (!usersTable) return;

        let tableHTML = `
            <thead>
                <tr>
                    <th>שם משתמש</th>
                    <th>סוג</th>
                    <th>סטטוס</th>
                    <th>התחברות אחרונה</th>
                </tr>
            </thead>
            <tbody>
        `;

        // הצגת 5 משתמשים אחרונים
        const displayUsers = this.data.users.slice(0, 5);
        
        displayUsers.forEach(user => {
            const authBadgeClass = user.auth_type === 'google' ? 'google' : '';
            const authTypeText = user.auth_type === 'google' ? 'Google' : 'רגיל';
            const statusClass = user.is_active ? 'status-active' : 'status-inactive';
            const statusText = user.is_active ? 'פעיל' : 'לא פעיל';
            const lastLogin = user.last_login ? 
                this.formatDateTime(new Date(user.last_login), true) : 'טרם התחבר';

            tableHTML += `
                <tr>
                    <td>${this.escapeHtml(user.username)}</td>
                    <td>
                        <span class="auth-badge ${authBadgeClass}">
                            ${authTypeText}
                        </span>
                    </td>
                    <td>
                        <span class="status-badge ${statusClass}">
                            ${statusText}
                        </span>
                    </td>
                    <td>${lastLogin}</td>
                </tr>
            `;
        });

        tableHTML += '</tbody>';
        usersTable.innerHTML = tableHTML;
    }

    // טעינת לוג פעילות
    loadActivityLog() {
        const activityLog = document.getElementById('activityLog');
        if (!activityLog) return;

        // סימולציה של פעילות - במימוש אמיתי יגיע מה-API
        this.activityLog = [
            { time: new Date(), action: 'התחברות למערכת', user: this.data.currentUser.username },
            { time: new Date(Date.now() - 300000), action: 'צפייה בדשבורד', user: this.data.currentUser.username },
            { time: new Date(Date.now() - 600000), action: 'עדכון פרופיל', user: 'user1' },
            { time: new Date(Date.now() - 900000), action: 'יצירת משימה חדשה', user: 'admin' },
            { time: new Date(Date.now() - 1200000), action: 'מחיקת רשומה', user: 'editor' }
        ];

        this.renderActivityLog();
    }

    renderActivityLog() {
        const activityLog = document.getElementById('activityLog');
        if (!activityLog) return;

        let logHTML = '';
        this.activityLog.forEach(entry => {
            logHTML += `
                <div class="log-entry">
                    <div class="log-time">${entry.time.toLocaleTimeString('he-IL')}</div>
                    <div class="log-action">${entry.action}</div>
                    <div class="log-user">משתמש: ${entry.user}</div>
                </div>
            `;
        });

        activityLog.innerHTML = logHTML;
    }

    // טעינת נקודות API
    loadApiEndpoints() {
        const apiEndpoints = document.getElementById('apiEndpoints');
        if (!apiEndpoints) return;

        const endpoints = [
            { name: 'פרטי משתמש', method: 'GET', url: `/api/user/${this.data.currentUser.id}` },
            { name: 'עדכון פרופיל', method: 'PUT', url: `/api/user/${this.data.currentUser.id}/update` },
            { name: 'רשימת משתמשים', method: 'GET', url: '/api/users' },
            { name: 'סטטיסטיקות', method: 'GET', url: '/api/stats' },
            { name: 'לוג פעילות', method: 'GET', url: '/api/activity' },
            { name: 'יצירת משתמש', method: 'POST', url: '/api/user/create' }
        ];

        let endpointsHTML = '';
        endpoints.forEach(endpoint => {
            const methodClass = endpoint.method.toLowerCase();
            endpointsHTML += `
                <div class="api-endpoint" data-url="${endpoint.url}" data-method="${endpoint.method}">
                    <strong>${endpoint.name}</strong>
                    <span class="api-method ${methodClass}">${endpoint.method}</span>
                    <div class="api-url">${this.data.apiBase}${endpoint.url}</div>
                </div>
            `;
        });

        apiEndpoints.innerHTML = endpointsHTML;

        // הוספת אירוע לחיצה על endpoints
        apiEndpoints.querySelectorAll('.api-endpoint').forEach(endpoint => {
            endpoint.addEventListener('click', () => this.copyApiEndpoint(endpoint));
        });
    }

    // העתקת API endpoint
    copyApiEndpoint(element) {
        const url = element.querySelector('.api-url').textContent;
        
        if (navigator.clipboard) {
            navigator.clipboard.writeText(url).then(() => {
                this.showNotification('הכתובת הועתקה ללוח!', 'success');
            });
        } else {
            // Fallback לדפדפנים ישנים
            const textArea = document.createElement('textarea');
            textArea.value = url;
            document.body.appendChild(textArea);
            textArea.select();
            document.execCommand('copy');
            document.body.removeChild(textArea);
            this.showNotification('הכתובת הועתקה ללוח!', 'success');
        }
    }

    // טיימר הסשן
    startSessionTimer() {
        setInterval(() => {
            this.sessionSeconds++;
            this.updateSessionTimer();
        }, 1000);
        
        // עדכון ראשוני
        this.updateSessionTimer();
    }

    updateSessionTimer() {
        const timerElement = document.getElementById('sessionTimer');
        if (!timerElement) return;

        const hours = Math.floor(this.sessionSeconds / 3600);
        const minutes = Math.floor((this.sessionSeconds % 3600) / 60);
        const seconds = this.sessionSeconds % 60;

        let display = '';
        if (hours > 0) {
            display = `${hours.toString().padStart(2, '0')}:`;
        }
        display += `${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;

        timerElement.textContent = display;
    }

    // אירועים
    initEventListeners() {
        // עדכון גודל חלון
        window.addEventListener('resize', () => this.handleResize());

        // קיצורי מקלדת
        document.addEventListener('keydown', (e) => this.handleKeyPress(e));

        // סטטיסטיקות - הוספת אירוע לחיצה
        document.querySelectorAll('.stat-card').forEach(card => {
            card.addEventListener('click', () => this.handleStatClick(card));
        });
    }

    handleStatClick(card) {
        // אנימציה של לחיצה
        card.style.transform = 'scale(0.95)';
        setTimeout(() => {
            card.style.transform = '';
        }, 200);

        // כאן אפשר להוסיף לוגיקה נוספת
        console.log('Stat card clicked:', card.querySelector('.stat-label').textContent);
    }

    handleKeyPress(e) {
        // Ctrl+K - חיפוש מהיר
        if (e.ctrlKey && e.key === 'k') {
            e.preventDefault();
            this.openQuickSearch();
        }
        
        // Esc - סגירת חלונות פופאפ
        if (e.key === 'Escape') {
            this.closeAllModals();
        }
    }

    handleResize() {
        // טיפול ברספונסיביות
        const width = window.innerWidth;
        if (width < 768) {
            document.body.classList.add('mobile-view');
        } else {
            document.body.classList.remove('mobile-view');
        }
    }

    // רענון אוטומטי
    startAutoRefresh() {
        // רענון נתונים כל 30 שניות
        setInterval(() => {
            this.refreshData();
        }, 30000);

        // בדיקת timeout של סשן כל דקה
        setInterval(() => {
            this.checkSessionTimeout();
        }, 60000);
    }

    async refreshData() {
        try {
            // כאן יש לקרוא ל-API לקבלת נתונים מעודכנים
            console.log('Refreshing dashboard data...');
            
            // סימולציה של פעילות חדשה
            if (Math.random() > 0.7) {
                this.addActivityLogEntry({
                    time: new Date(),
                    action: 'פעולה אוטומטית',
                    user: 'system'
                });
            }
        } catch (error) {
            console.error('Error refreshing data:', error);
        }
    }

    addActivityLogEntry(entry) {
        this.activityLog.unshift(entry);
        if (this.activityLog.length > 10) {
            this.activityLog.pop();
        }
        this.renderActivityLog();
    }

    checkSessionTimeout() {
        // בדיקה אם הסשן פעיל יותר מ-2 שעות
        if (this.sessionSeconds > 7200) {
            if (confirm('הסשן שלך פעיל כבר יותר משעתיים. האם לרענן את החיבור?')) {
                window.location.reload();
            }
        }
    }

    // פונקציות עזר
    formatDateTime(date, short = false) {
        if (short) {
            const today = new Date();
            if (date.toDateString() === today.toDateString()) {
                return `היום ${date.toLocaleTimeString('he-IL', { hour: '2-digit', minute: '2-digit' })}`;
            }
            return date.toLocaleDateString('he-IL', { 
                day: '2-digit', 
                month: '2-digit',
                hour: '2-digit',
                minute: '2-digit'
            });
        }
        return date.toLocaleString('he-IL');
    }

    escapeHtml(text) {
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return text.replace(/[&<>"']/g, m => map[m]);
    }

    showNotification(message, type = 'info') {
        // יצירת אלמנט התראה
        const notification = document.createElement('div');
        notification.className = `notification ${type}`;
        notification.textContent = message;
        notification.style.cssText = `
            position: fixed;
            top: 20px;
            left: 50%;
            transform: translateX(-50%);
            padding: 15px 30px;
            background: ${type === 'success' ? '#10b981' : '#667eea'};
            color: white;
            border-radius: 8px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
            z-index: 1000;
            animation: slideDown 0.3s ease;
        `;
        
        document.body.appendChild(notification);
        
        setTimeout(() => {
            notification.style.animation = 'slideUp 0.3s ease';
            setTimeout(() => notification.remove(), 300);
        }, 3000);
    }

    // Placeholder לפונקציות עתידיות
    openQuickSearch() {
        console.log('Quick search opened');
        this.showNotification('חיפוש מהיר - בקרוב!', 'info');
    }

    closeAllModals() {
        console.log('Closing all modals');
    }
}

// אתחול הדשבורד
document.addEventListener('DOMContentLoaded', () => {
    window.dashboard = new DashboardManager();
});

// הוספת אנימציות CSS דינמיות
const style = document.createElement('style');
style.textContent = `
    @keyframes slideDown {
        from { transform: translate(-50%, -100%); opacity: 0; }
        to { transform: translate(-50%, 0); opacity: 1; }
    }
    @keyframes slideUp {
        from { transform: translate(-50%, 0); opacity: 1; }
        to { transform: translate(-50%, -100%); opacity: 0; }
    }
`;
document.head.appendChild(style);