/**
 * Login Notifications - Page Navigation System
 * מערכת התראות חדשה - מבוססת ניווט לדף נפרד
 *
 * @version 5.11.0 - FIX: ALWAYS reload after last notification (uniform behavior)
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
     * Get full state snapshot for debugging
     */
    getFullState() {
        return {
            session: {
                came_from: sessionStorage.getItem(this.config.sessionCameFromKey),
                next_idx: sessionStorage.getItem(this.config.sessionNextIndexKey),
                done: sessionStorage.getItem(this.config.sessionDoneKey),
                pending: sessionStorage.getItem('pending_notification_index')
            },
            nav: window.navigation ? {
                index: window.navigation.currentEntry.index,
                length: window.navigation.entries().length,
                canGoBack: window.navigation.canGoBack,
                canGoForward: window.navigation.canGoForward,
                entries: window.navigation.entries().map(e => ({
                    i: e.index,
                    u: e.url ? e.url.split('/').pop().substring(0, 30) : 'N/A'
                }))
            } : { api: 'N/A' },
            hist: {
                length: history.length,
                state: history.state
            },
            url: location.href.split('/').pop(),
            referrer: document.referrer ? document.referrer.split('/').pop() : 'none'
        };
    },

    /**
     * Send debug log to server - ENHANCED v5.11
     */
    log(event, data) {
        const state = this.getFullState();

        const payload = {
            page: 'DASHBOARD',
            v: '5.11',
            e: event,
            t: Date.now() - this.state.pageLoadTime,
            ts: new Date().toISOString(),
            d: data,
            state: state
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
        // ========== ENTRY POINT ==========
        this.log('>>> INIT_ENTER', { initialized: this.state.initialized });

        if (this.state.initialized) {
            this.log('<<< INIT_EXIT_ALREADY', {});
            return;
        }
        this.state.initialized = true;

        // ========== STEP 1: Check if done ==========
        const isDone = sessionStorage.getItem(this.config.sessionDoneKey) === 'true';
        this.log('STEP1_CHECK_DONE', { isDone: isDone });

        if (isDone) {
            this.log('<<< INIT_EXIT_DONE', { reason: 'notifications already done' });
            return;
        }

        // ========== STEP 2: Check pending notification ==========
        const pendingIndex = sessionStorage.getItem('pending_notification_index');
        this.log('STEP2_CHECK_PENDING', { pendingIndex: pendingIndex });

        if (pendingIndex !== null) {
            sessionStorage.removeItem('pending_notification_index');
            const idx = parseInt(pendingIndex, 10);
            this.log('STEP2_PENDING_FOUND', { index: idx, action: 'will navigate to notification' });

            setTimeout(() => {
                this.navigateToNotification(idx);
            }, 100);
            this.log('<<< INIT_EXIT_PENDING', { scheduledNavTo: idx });
            return;
        }

        // ========== STEP 3: Check if came from notification ==========
        const cameFrom = sessionStorage.getItem(this.config.sessionCameFromKey);
        const nextIdx = sessionStorage.getItem(this.config.sessionNextIndexKey);
        this.log('STEP3_CHECK_CAME_FROM', { cameFrom: cameFrom, nextIdx: nextIdx });

        if (cameFrom === 'true') {
            // Clear the flag immediately
            sessionStorage.removeItem(this.config.sessionCameFromKey);
            this.log('STEP3_CAME_FROM_TRUE', { cleared: 'came_from_notification' });

            if (nextIdx !== null) {
                // ========== BRANCH A: More notifications to show ==========
                const idx = parseInt(nextIdx, 10);
                this.log('STEP3A_HAS_NEXT', {
                    nextIndex: idx,
                    action: 'reload dashboard then navigate to notification'
                });

                sessionStorage.setItem('pending_notification_index', idx.toString());
                this.log('STEP3A_SET_PENDING', { pendingIndex: idx });

                const reloadUrl = '/dashboard/dashboards/cemeteries/?_next=' + Date.now();
                this.log('<<< INIT_EXIT_RELOAD_FOR_NEXT', { reloadUrl: reloadUrl, nextIndex: idx });
                location.href = reloadUrl;
                return;

            } else {
                // ========== BRANCH B: Last notification - all done ==========
                // v5.11: ALWAYS reload for uniform behavior
                this.log('STEP3B_NO_NEXT', {
                    action: 'last notification done - reload for safety'
                });

                sessionStorage.setItem(this.config.sessionDoneKey, 'true');
                this.log('STEP3B_SET_DONE', { done: 'true' });

                const reloadUrl = '/dashboard/dashboards/cemeteries/?_final=' + Date.now();
                this.log('<<< INIT_EXIT_RELOAD_FINAL', { reloadUrl: reloadUrl });
                location.href = reloadUrl;
                return;
            }
        }

        // ========== STEP 4: First load - start timer ==========
        this.log('STEP4_FIRST_LOAD', { action: 'start timer for first notification' });
        this.startTimer(0);
        this.log('<<< INIT_EXIT_TIMER_STARTED', { timerIndex: 0, delayMs: this.config.delayMs });
    },

    /**
     * Start the countdown timer
     */
    startTimer(index) {
        if (this.state.timerId) {
            clearTimeout(this.state.timerId);
            this.log('TIMER_CLEARED_OLD', {});
        }

        this.log('>>> TIMER_START', { index: index, delayMs: this.config.delayMs });

        this.state.timerId = setTimeout(() => {
            this.log('TIMER_FIRED', { index: index });
            this.navigateToNotification(index);
        }, this.config.delayMs);
    },

    /**
     * Navigate to the notification page
     */
    navigateToNotification(index) {
        const url = `${this.config.notificationUrl}?index=${index}`;
        const navIndex = window.navigation ? window.navigation.currentEntry.index : -1;

        // ========== ENTRY POINT ==========
        this.log('>>> NAVIGATE_ENTER', {
            targetIndex: index,
            targetUrl: url,
            currentNavIndex: navIndex
        });

        // If we're at navIndex 0, need to reload first
        if (navIndex === 0) {
            this.log('NAVIGATE_AT_NAV0', {
                action: 'reload dashboard first',
                reason: 'at navIndex 0, need buffer entry before notification'
            });

            sessionStorage.setItem('pending_notification_index', index.toString());
            const reloadUrl = '/dashboard/dashboards/cemeteries/?_nav=' + Date.now();
            this.log('<<< NAVIGATE_EXIT_RELOAD', { reloadUrl: reloadUrl, pendingIndex: index });
            location.href = reloadUrl;
            return;
        }

        // navIndex > 0, safe to navigate directly
        this.log('NAVIGATE_DIRECT', {
            targetIndex: index,
            navIndex: navIndex,
            historyLength: history.length
        });

        this.log('<<< NAVIGATE_EXIT_GO', { url: url, notificationIndex: index });
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

    // CRITICAL: Handle bfcache restore (back button from notification)
    window.addEventListener('pageshow', function(e) {
        LoginNotificationsNav.log('>>> PAGESHOW', {
            persisted: e.persisted,
            type: e.persisted ? 'BFCACHE_RESTORE' : 'FRESH_LOAD'
        });

        if (e.persisted) {
            // Page restored from bfcache - need to re-check for notifications
            LoginNotificationsNav.log('BFCACHE_DETECTED', {
                action: 'will re-run init()',
                reason: 'page restored from back-forward cache'
            });

            // Reset initialized flag so init() will run
            LoginNotificationsNav.state.initialized = false;
            LoginNotificationsNav.state.pageLoadTime = Date.now();

            // Re-initialize to check for next notification
            setTimeout(() => {
                LoginNotificationsNav.init();
            }, 500);
        }
    });
})();
