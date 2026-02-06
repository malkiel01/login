/**
 * Login Notifications - Page Navigation System
 * מערכת התראות חדשה - מבוססת ניווט לדף נפרד
 *
 * @version 5.0.0 - Complete rewrite with page-based navigation
 *
 * Flow:
 * 1. Dashboard loads → wait 5 seconds → navigate to notification-view.php?index=0
 * 2. User sees notification → presses back → returns to dashboard
 * 3. Dashboard detects return from notification → wait 5 seconds → navigate to next notification
 * 4. Repeat until all notifications shown
 */

window.LoginNotificationsNav = {
    config: {
        delayMs: 5000, // 5 seconds
        notificationUrl: '/dashboard/dashboards/cemeteries/notifications/notification-view.php',
        sessionDoneKey: 'notifications_done',
        sessionNextIndexKey: 'notification_next_index',
        sessionCameFromKey: 'came_from_notification'
    },

    state: {
        timerId: null,
        initialized: false
    },

    /**
     * Initialize the notification system
     */
    init() {
        if (this.state.initialized) {
            console.log('[LoginNotificationsNav] Already initialized');
            return;
        }
        this.state.initialized = true;

        console.log('[LoginNotificationsNav] v5.0 - Page Navigation System');

        // Check if notifications are done for this session
        if (sessionStorage.getItem(this.config.sessionDoneKey) === 'true') {
            console.log('[LoginNotificationsNav] All notifications done for this session');
            return;
        }

        // Check if we came back from notification page (via back button)
        const cameFromNotification = sessionStorage.getItem(this.config.sessionCameFromKey);
        const nextIndex = sessionStorage.getItem(this.config.sessionNextIndexKey);

        if (cameFromNotification === 'true') {
            // Clear the flag
            sessionStorage.removeItem(this.config.sessionCameFromKey);

            console.log('[LoginNotificationsNav] Returned from notification page');

            if (nextIndex !== null) {
                // More notifications to show - start timer
                console.log('[LoginNotificationsNav] More notifications pending, next index:', nextIndex);
                this.startTimer(parseInt(nextIndex, 10));
            } else {
                // All done
                console.log('[LoginNotificationsNav] All notifications shown');
                sessionStorage.setItem(this.config.sessionDoneKey, 'true');
            }
        } else {
            // First load - check if we should show notifications
            // Start from index 0
            console.log('[LoginNotificationsNav] First load - starting from index 0');
            this.startTimer(0);
        }
    },

    /**
     * Start the countdown timer
     */
    startTimer(index) {
        // Clear any existing timer
        if (this.state.timerId) {
            clearTimeout(this.state.timerId);
        }

        console.log('[LoginNotificationsNav] Starting 5 second timer for notification index:', index);

        this.state.timerId = setTimeout(() => {
            this.navigateToNotification(index);
        }, this.config.delayMs);
    },

    /**
     * Navigate to the notification page
     */
    navigateToNotification(index) {
        const url = `${this.config.notificationUrl}?index=${index}`;
        console.log('[LoginNotificationsNav] Navigating to:', url);

        // Use regular navigation (not replace) so back button works
        window.location.href = url;
    },

    /**
     * Cancel pending navigation (e.g., if user interacts)
     */
    cancel() {
        if (this.state.timerId) {
            console.log('[LoginNotificationsNav] Timer cancelled');
            clearTimeout(this.state.timerId);
            this.state.timerId = null;
        }
    },

    /**
     * Skip all notifications
     */
    skipAll() {
        this.cancel();
        sessionStorage.setItem(this.config.sessionDoneKey, 'true');
        sessionStorage.removeItem(this.config.sessionNextIndexKey);
        console.log('[LoginNotificationsNav] Skipped all notifications');
    },

    /**
     * Reset (for testing)
     */
    reset() {
        this.cancel();
        sessionStorage.removeItem(this.config.sessionDoneKey);
        sessionStorage.removeItem(this.config.sessionNextIndexKey);
        sessionStorage.removeItem(this.config.sessionCameFromKey);
        this.state.initialized = false;
        console.log('[LoginNotificationsNav] Reset complete');
    },

    /**
     * Test: trigger notification navigation immediately
     */
    testNow(index = 0) {
        console.log('[LoginNotificationsNav] Test - navigating immediately to index:', index);
        this.navigateToNotification(index);
    }
};

// Initialize on page load
(function() {
    function init() {
        // Small delay to let the page render first
        setTimeout(() => {
            LoginNotificationsNav.init();
        }, 500);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
