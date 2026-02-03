/**
 * Login Notifications Manager
 * מציג התראות שלא נקראו בעת כניסה למערכת
 *
 * @version 3.2.0 - DEBUG
 */

window.LoginNotifications = {
    config: {
        maxNotifications: 5,
        sessionKey: 'login_notifications_shown',
        apiUrl: '/dashboard/dashboards/cemeteries/my-notifications/api/my-notifications-api.php'
    },

    state: {
        isActive: false,
        queue: [],
        currentIndex: 0
    },

    /**
     * לוג לשרת
     */
    async serverLog(message, data = {}) {
        try {
            const logData = {
                message,
                data,
                timestamp: new Date().toISOString(),
                url: location.href,
                hash: location.hash,
                historyLength: history.length,
                historyState: history.state
            };
            console.log('[LoginNotifications]', message, logData);

            await fetch('/dashboard/dashboards/cemeteries/api/debug-log.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ source: 'LoginNotifications', ...logData })
            });
        } catch (e) {
            console.error('[LoginNotifications] serverLog error:', e);
        }
    },

    /**
     * אתחול
     */
    async init() {
        this.serverLog('init started');

        // הגדר מאזינים לניווט - לדיבוג
        this.setupNavigationDebug();

        if (sessionStorage.getItem(this.config.sessionKey)) {
            this.serverLog('Already shown this session');
            return;
        }

        const ready = await this.waitForDependencies();
        if (!ready) {
            this.serverLog('Dependencies not ready');
            return;
        }

        await this.loadAndShowNotifications();
    },

    /**
     * מאזינים לניווט + הגנה על כפתור חזור
     */
    setupNavigationDebug() {
        // Popstate - כפתור חזור/קדימה
        window.addEventListener('popstate', (e) => {
            this.serverLog('POPSTATE EVENT', {
                state: e.state,
                isActive: this.state.isActive,
                currentIndex: this.state.currentIndex,
                queueLength: this.state.queue.length
            });

            // אם אנחנו באמצע הצגת התראות - דחוף state חדש למניעת סגירת האפליקציה
            if (this.state.isActive) {
                this.serverLog('Pushing buffer state to prevent app close');
                history.pushState({ loginNotificationsBuffer: Date.now() }, '');
            }
        });

        // Hashchange
        window.addEventListener('hashchange', (e) => {
            this.serverLog('HASHCHANGE EVENT', {
                oldURL: e.oldURL,
                newURL: e.newURL
            });
        });

        // Beforeunload - לפני סגירת האפליקציה
        window.addEventListener('beforeunload', (e) => {
            this.serverLog('BEFOREUNLOAD EVENT - APP CLOSING', {
                isActive: this.state.isActive
            });
        });

        // Pagehide - דף מוסתר (iOS)
        window.addEventListener('pagehide', (e) => {
            this.serverLog('PAGEHIDE EVENT', {
                persisted: e.persisted
            });
        });

        // Visibilitychange
        document.addEventListener('visibilitychange', () => {
            this.serverLog('VISIBILITY CHANGE', {
                visibilityState: document.visibilityState
            });
        });

        this.serverLog('Navigation debug listeners set up');
    },

    async waitForDependencies() {
        const maxWait = 5000;
        let waited = 0;
        while (waited < maxWait) {
            if (typeof NotificationTemplates !== 'undefined' &&
                typeof NotificationTemplates.show === 'function') {
                return true;
            }
            await new Promise(r => setTimeout(r, 100));
            waited += 100;
        }
        return false;
    },

    async loadAndShowNotifications() {
        this.serverLog('loadAndShowNotifications started');

        try {
            const response = await fetch(`${this.config.apiUrl}?action=get_unread`, {
                credentials: 'include'
            });
            const data = await response.json();

            if (!data.success || !data.notifications || data.notifications.length === 0) {
                this.serverLog('No notifications found', { data });
                sessionStorage.setItem(this.config.sessionKey, 'true');
                return;
            }

            this.state.queue = this.sortByPriority(data.notifications)
                .slice(0, this.config.maxNotifications);
            this.state.currentIndex = 0;
            this.state.isActive = true;

            // דחוף buffer entries להיסטוריה למניעת סגירת האפליקציה
            const bufferCount = this.state.queue.length + 5;
            this.serverLog('Pushing history buffer', { bufferCount, currentHistoryLength: history.length });
            for (let i = 0; i < bufferCount; i++) {
                history.pushState({ loginNotificationsBuffer: i }, '');
            }

            this.serverLog('Notifications loaded', {
                count: this.state.queue.length,
                titles: this.state.queue.map(n => n.title),
                historyLengthAfterBuffer: history.length
            });

            this.showNextNotification();

        } catch (error) {
            this.serverLog('Error loading notifications', { error: error.message });
        }
    },

    sortByPriority(notifications) {
        const priority = { 'urgent': 1, 'warning': 2, 'info': 3 };
        return notifications.sort((a, b) => {
            if (a.requires_approval !== b.requires_approval) {
                return a.requires_approval ? -1 : 1;
            }
            const pa = priority[a.notification_type] || 3;
            const pb = priority[b.notification_type] || 3;
            if (pa !== pb) return pa - pb;
            return new Date(b.created_at) - new Date(a.created_at);
        });
    },

    /**
     * הצגת ההתראה הבאה
     */
    showNextNotification() {
        this.serverLog('showNextNotification called', {
            currentIndex: this.state.currentIndex,
            queueLength: this.state.queue.length
        });

        if (this.state.currentIndex >= this.state.queue.length) {
            this.serverLog('All notifications done');
            this.finish();
            return;
        }

        const notification = this.state.queue[this.state.currentIndex];
        const counter = `${this.state.currentIndex + 1}/${this.state.queue.length}`;

        this.serverLog('Showing notification', {
            counter,
            title: notification.title,
            id: notification.id
        });

        // נקה callback קודם
        NotificationTemplates.callbacks.onClose = null;

        // הצג את ההתראה
        NotificationTemplates.show(notification, {
            autoDismiss: false,
            showCounter: this.state.queue.length > 1 ? counter : null
        });

        // הגדר callback לסגירה - רק אחרי שההתראה מוצגת
        setTimeout(() => {
            NotificationTemplates.onClose(() => {
                this.serverLog('Notification closed by user', {
                    closedIndex: this.state.currentIndex,
                    title: notification.title
                });
                this.state.currentIndex++;
                setTimeout(() => this.showNextNotification(), 300);
            });
        }, 100);
    },

    /**
     * סיום
     */
    finish() {
        this.serverLog('finish() called - all notifications complete');
        this.state.isActive = false;
        sessionStorage.setItem(this.config.sessionKey, 'true');

        if (typeof updateMyNotificationsCount === 'function') {
            updateMyNotificationsCount();
        }
    },

    /**
     * דילוג על כל ההתראות
     */
    skipAll() {
        NotificationTemplates.callbacks.onClose = null;
        this.state.queue = [];
        this.finish();
        if (NotificationTemplates.activeModal) {
            NotificationTemplates.activeModal.remove();
            NotificationTemplates.activeModal = null;
            document.body.style.overflow = '';
        }
    },

    /**
     * איפוס (לבדיקות)
     */
    reset() {
        sessionStorage.removeItem(this.config.sessionKey);
        this.state = {
            isActive: false,
            queue: [],
            currentIndex: 0
        };
    }
};

// אתחול
(function() {
    function setup() {
        let initialized = false;
        const initOnce = () => {
            if (initialized) return;
            initialized = true;
            LoginNotifications.init();
        };

        window.addEventListener('notificationTemplatesReady', () => {
            setTimeout(initOnce, 500);
        });

        setTimeout(initOnce, 3000);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', setup);
    } else {
        setup();
    }
})();
