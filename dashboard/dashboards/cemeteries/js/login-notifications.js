/**
 * Login Notifications Manager
 * מציג התראות שלא נקראו בעת כניסה למערכת
 *
 * @version 2.1.0
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
        currentIndex: 0,
        isShowingNotification: false,
        waitingForUserAction: false
    },

    /**
     * אתחול
     */
    async init() {
        console.log('[LoginNotifications] init');

        if (sessionStorage.getItem(this.config.sessionKey)) {
            console.log('[LoginNotifications] Already shown');
            return;
        }

        const ready = await this.waitForDependencies();
        if (!ready) return;

        // הגדר מאזין לכפתור חזור (באמצעות hash)
        this.setupBackButton();

        await this.loadAndShowNotifications();
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

    /**
     * הגדרת תמיכה בכפתור חזור באמצעות hash
     */
    setupBackButton() {
        window.addEventListener('hashchange', (e) => {
            // אם היינו במסך התראה והמשתמש לחץ חזור
            if (this.state.isShowingNotification && !location.hash.includes('notification')) {
                console.log('[LoginNotifications] Back button - closing notification');
                this.closeCurrentAndShowNext();
            }
        });
    },

    async loadAndShowNotifications() {
        try {
            const response = await fetch(`${this.config.apiUrl}?action=get_unread`, {
                credentials: 'include'
            });
            const data = await response.json();

            if (!data.success || !data.notifications || data.notifications.length === 0) {
                console.log('[LoginNotifications] No notifications');
                sessionStorage.setItem(this.config.sessionKey, 'true');
                return;
            }

            this.state.queue = this.sortByPriority(data.notifications)
                .slice(0, this.config.maxNotifications);
            this.state.currentIndex = 0;
            this.state.isActive = true;

            console.log('[LoginNotifications] Found', this.state.queue.length, 'notifications');

            this.showNextNotification();

        } catch (error) {
            console.error('[LoginNotifications] Error:', error);
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
        // מניעת קריאות כפולות
        if (this.state.waitingForUserAction) {
            console.log('[LoginNotifications] Already waiting for user action');
            return;
        }

        if (this.state.currentIndex >= this.state.queue.length) {
            console.log('[LoginNotifications] All done');
            this.finish();
            return;
        }

        const notification = this.state.queue[this.state.currentIndex];
        const counter = `${this.state.currentIndex + 1}/${this.state.queue.length}`;
        console.log('[LoginNotifications] Showing', counter, notification.title);

        this.state.isShowingNotification = true;
        this.state.waitingForUserAction = true;

        // הוסף hash לכתובת (לתמיכה בכפתור חזור)
        location.hash = 'notification-' + this.state.currentIndex;

        // נקה callbacks קודמים
        NotificationTemplates.callbacks.onClose = null;

        // הצג את ההתראה
        NotificationTemplates.show(notification, {
            autoDismiss: false,
            showCounter: this.state.queue.length > 1 ? counter : null
        });

        // הגדר callback רק אחרי שההתראה הוצגה
        setTimeout(() => {
            NotificationTemplates.onClose(() => {
                console.log('[LoginNotifications] User closed notification');
                this.closeCurrentAndShowNext();
            });
        }, 100);
    },

    /**
     * סגירת ההתראה הנוכחית ומעבר לבאה
     */
    closeCurrentAndShowNext() {
        if (!this.state.waitingForUserAction) return;

        this.state.waitingForUserAction = false;
        this.state.isShowingNotification = false;

        // נקה callback למניעת קריאות כפולות
        NotificationTemplates.callbacks.onClose = null;

        // סגור את ההתראה אם עדיין פתוחה
        if (NotificationTemplates.activeModal) {
            NotificationTemplates.activeModal.remove();
            NotificationTemplates.activeModal = null;
            document.body.style.overflow = '';
        }

        // הסר hash
        if (location.hash.includes('notification')) {
            history.replaceState(null, '', location.pathname + location.search);
        }

        // המשך להתראה הבאה
        this.state.currentIndex++;
        setTimeout(() => this.showNextNotification(), 400);
    },

    /**
     * סיום
     */
    finish() {
        this.state.isActive = false;
        this.state.isShowingNotification = false;
        this.state.waitingForUserAction = false;
        sessionStorage.setItem(this.config.sessionKey, 'true');

        if (location.hash.includes('notification')) {
            history.replaceState(null, '', location.pathname + location.search);
        }

        if (typeof updateMyNotificationsCount === 'function') {
            updateMyNotificationsCount();
        }
    },

    skipAll() {
        NotificationTemplates.callbacks.onClose = null;
        this.state.queue = [];
        this.finish();
        if (NotificationTemplates.activeModal) {
            NotificationTemplates.activeModal.remove();
            NotificationTemplates.activeModal = null;
        }
    },

    reset() {
        sessionStorage.removeItem(this.config.sessionKey);
        this.state = {
            isActive: false,
            queue: [],
            currentIndex: 0,
            isShowingNotification: false,
            waitingForUserAction: false
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
