/**
 * Login Notifications Manager
 * מציג התראות שלא נקראו בעת כניסה למערכת
 *
 * תכונות:
 * - הצגת עד 5 התראות בפתיחת האפליקציה
 * - כל התראה היא "מסך" נפרד (תמיכה בכפתור חזור)
 * - הצגה אחת אחרי השנייה
 *
 * @version 2.0.0
 * @author Malkiel
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
        historyStateAdded: false
    },

    /**
     * אתחול - נקרא בטעינת הדף
     */
    async init() {
        console.log('[LoginNotifications] init started');

        // בדוק אם כבר הוצגו התראות בסשן הזה
        if (sessionStorage.getItem(this.config.sessionKey)) {
            console.log('[LoginNotifications] Already shown in this session');
            return;
        }

        // המתן לטעינת התלויות
        const ready = await this.waitForDependencies();
        if (!ready) {
            console.log('[LoginNotifications] Dependencies not ready');
            return;
        }

        // הגדר מאזין לכפתור חזור
        this.setupBackButtonHandler();

        // טען והצג התראות
        await this.loadAndShowNotifications();
    },

    /**
     * המתנה לטעינת תלויות
     */
    async waitForDependencies() {
        const maxWait = 5000;
        const interval = 100;
        let waited = 0;

        while (waited < maxWait) {
            if (typeof NotificationTemplates !== 'undefined' &&
                typeof NotificationTemplates.show === 'function') {
                return true;
            }
            await new Promise(r => setTimeout(r, interval));
            waited += interval;
        }
        return false;
    },

    /**
     * הגדרת מאזין לכפתור חזור
     */
    setupBackButtonHandler() {
        window.addEventListener('popstate', (event) => {
            // אם יש התראה פעילה וזה state של התראה
            if (this.state.isActive && this.state.historyStateAdded) {
                console.log('[LoginNotifications] Back button pressed - closing notification');
                this.state.historyStateAdded = false;

                // סגור את ההתראה הנוכחית בלי לקרוא ל-onClose callback
                if (NotificationTemplates.activeModal) {
                    NotificationTemplates.activeModal.remove();
                    NotificationTemplates.activeModal = null;
                    document.body.style.overflow = '';
                }

                // המשך להתראה הבאה
                this.state.currentIndex++;
                setTimeout(() => this.showNextNotification(), 300);
            }
        });
    },

    /**
     * טעינת והצגת התראות
     */
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

            // מיון והגבלה
            this.state.queue = this.sortByPriority(data.notifications)
                .slice(0, this.config.maxNotifications);
            this.state.currentIndex = 0;
            this.state.isActive = true;

            console.log('[LoginNotifications] Found', this.state.queue.length, 'notifications');

            // התחל להציג
            this.showNextNotification();

        } catch (error) {
            console.error('[LoginNotifications] Error:', error);
        }
    },

    /**
     * מיון לפי עדיפות
     */
    sortByPriority(notifications) {
        const priority = { 'urgent': 1, 'warning': 2, 'info': 3 };
        return notifications.sort((a, b) => {
            // אישורים קודם
            if (a.requires_approval !== b.requires_approval) {
                return a.requires_approval ? -1 : 1;
            }
            // לפי סוג
            const pa = priority[a.notification_type] || 3;
            const pb = priority[b.notification_type] || 3;
            if (pa !== pb) return pa - pb;
            // לפי תאריך
            return new Date(b.created_at) - new Date(a.created_at);
        });
    },

    /**
     * הצגת ההתראה הבאה
     */
    showNextNotification() {
        // בדיקה אם סיימנו
        if (this.state.currentIndex >= this.state.queue.length) {
            console.log('[LoginNotifications] All notifications shown');
            this.state.isActive = false;
            sessionStorage.setItem(this.config.sessionKey, 'true');

            // עדכון מונה בסיידבר
            if (typeof updateMyNotificationsCount === 'function') {
                updateMyNotificationsCount();
            }
            return;
        }

        const notification = this.state.queue[this.state.currentIndex];
        console.log('[LoginNotifications] Showing notification', this.state.currentIndex + 1, '/', this.state.queue.length);

        // הוסף state להיסטוריה (לתמיכה בכפתור חזור)
        history.pushState({ loginNotification: true }, '');
        this.state.historyStateAdded = true;

        // הגדר callback לסגירה (כשלוחצים X או על הרקע)
        NotificationTemplates.onClose(() => {
            console.log('[LoginNotifications] Notification closed by user');

            // הסר את ה-state מההיסטוריה
            if (this.state.historyStateAdded) {
                this.state.historyStateAdded = false;
                history.back();
            }

            // המשך להתראה הבאה
            this.state.currentIndex++;
            setTimeout(() => this.showNextNotification(), 300);
        });

        // הצג את ההתראה
        NotificationTemplates.show(notification, {
            autoDismiss: false,
            showCounter: this.state.queue.length > 1
                ? `${this.state.currentIndex + 1}/${this.state.queue.length}`
                : null
        });
    },

    /**
     * דילוג על כל ההתראות
     */
    skipAll() {
        console.log('[LoginNotifications] Skip all');
        this.state.queue = [];
        this.state.currentIndex = 0;
        this.state.isActive = false;

        if (this.state.historyStateAdded) {
            this.state.historyStateAdded = false;
            history.back();
        }

        NotificationTemplates.close();
        sessionStorage.setItem(this.config.sessionKey, 'true');
    },

    /**
     * איפוס (לבדיקות)
     */
    reset() {
        sessionStorage.removeItem(this.config.sessionKey);
        this.state.queue = [];
        this.state.currentIndex = 0;
        this.state.isActive = false;
        this.state.historyStateAdded = false;
        console.log('[LoginNotifications] Reset');
    }
};

// אתחול אוטומטי
(function() {
    function setup() {
        // המתן ל-event שהתבניות מוכנות
        let initialized = false;

        const initOnce = () => {
            if (initialized) return;
            initialized = true;
            LoginNotifications.init();
        };

        // מאזין ל-event
        window.addEventListener('notificationTemplatesReady', () => {
            setTimeout(initOnce, 500);
        });

        // fallback
        setTimeout(initOnce, 3000);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', setup);
    } else {
        setup();
    }
})();
