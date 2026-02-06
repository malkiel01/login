/**
 * Login Notifications - Page Navigation System
 * מערכת התראות חדשה - מבוססת ניווט לדף נפרד
 *
 * @version 5.1.0 - With comprehensive debug logging
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
        sessionCameFromKey: 'came_from_notification',
        debugUrl: '/dashboard/dashboards/cemeteries/api/debug-log.php'
    },

    state: {
        timerId: null,
        initialized: false,
        pageLoadTime: Date.now()
    },

    /**
     * Send debug log to server
     */
    log(event, data) {
        const navInfo = window.navigation ? {
            navIndex: window.navigation.currentEntry.index,
            navLength: window.navigation.entries().length,
            canGoBack: window.navigation.canGoBack,
            canGoForward: window.navigation.canGoForward,
            entries: window.navigation.entries().map(e => ({
                idx: e.index,
                url: e.url ? e.url.split('/').pop() : 'N/A'
            }))
        } : { navApi: 'not supported' };

        const payload = {
            page: 'LOGIN_NOTIF_NAV',
            v: '5.1',
            e: event,
            t: Date.now() - this.state.pageLoadTime,
            ts: new Date().toISOString(),
            d: data,
            nav: navInfo,
            hist: {
                length: history.length,
                state: history.state
            },
            session: {
                came_from: sessionStorage.getItem(this.config.sessionCameFromKey),
                next_idx: sessionStorage.getItem(this.config.sessionNextIndexKey),
                done: sessionStorage.getItem(this.config.sessionDoneKey)
            }
        };

        console.log('[LoginNotificationsNav]', event, payload);

        try {
            navigator.sendBeacon(this.config.debugUrl, JSON.stringify(payload));
        } catch(e) {
            console.error('Log failed:', e);
        }
    },

    /**
     * Initialize the notification system
     */
    init() {
        if (this.state.initialized) {
            this.log('ALREADY_INIT', {});
            return;
        }
        this.state.initialized = true;

        this.log('INIT_START', {
            referrer: document.referrer,
            url: location.href
        });

        // Check if notifications are done for this session
        if (sessionStorage.getItem(this.config.sessionDoneKey) === 'true') {
            this.log('SKIP_DONE', { reason: 'notifications_done is true' });
            return;
        }

        // Check if we came back from notification page (via back button)
        const cameFromNotification = sessionStorage.getItem(this.config.sessionCameFromKey);
        const nextIndex = sessionStorage.getItem(this.config.sessionNextIndexKey);

        this.log('SESSION_CHECK', {
            cameFromNotification: cameFromNotification,
            nextIndex: nextIndex
        });

        if (cameFromNotification === 'true') {
            // Clear the flag
            sessionStorage.removeItem(this.config.sessionCameFromKey);

            this.log('RETURNED_FROM_NOTIFICATION', { nextIndex: nextIndex });

            if (nextIndex !== null) {
                // More notifications to show - start timer
                const idx = parseInt(nextIndex, 10);
                this.log('START_TIMER_FOR_NEXT', { index: idx });
                this.startTimer(idx);
            } else {
                // All done
                this.log('ALL_DONE', { reason: 'nextIndex is null' });
                sessionStorage.setItem(this.config.sessionDoneKey, 'true');
            }
        } else {
            // First load - start from index 0
            this.log('FIRST_LOAD', { startingIndex: 0 });
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

        this.log('TIMER_START', { index: index, delayMs: this.config.delayMs });

        this.state.timerId = setTimeout(() => {
            this.navigateToNotification(index);
        }, this.config.delayMs);
    },

    /**
     * Navigate to the notification page
     */
    navigateToNotification(index) {
        const url = `${this.config.notificationUrl}?index=${index}`;

        this.log('NAVIGATE_PREP', {
            index: index,
            url: url,
            historyLengthBefore: history.length,
            currentHash: location.hash,
            currentUrl: location.href
        });

        // FIX v2: Replace current #app-init with clean dashboard URL
        // The #app-init hash confuses Chrome Android's back navigation
        // By replacing it with a clean URL, back button works properly
        try {
            if (location.hash === '#app-init' || location.hash.startsWith('#app-')) {
                history.replaceState(
                    { dashboard: true, cleanedForNotification: index, t: Date.now() },
                    '',
                    '/dashboard/dashboards/cemeteries/'
                );
                this.log('NAVIGATE_REPLACE_HASH', {
                    removedHash: location.hash,
                    historyLength: history.length
                });
            }
        } catch(e) {
            this.log('NAVIGATE_REPLACE_ERROR', { error: e.message });
        }

        this.log('NAVIGATE', {
            index: index,
            url: url,
            historyLengthNow: history.length,
            urlAfterReplace: location.href
        });

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
