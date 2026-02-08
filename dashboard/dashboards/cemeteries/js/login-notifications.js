/**
 * Login Notifications - Page Navigation System
 * מערכת התראות חדשה - מבוססת ניווט לדף נפרד
 *
 * @version 5.12.0 - FIX: No reloads! Use pushState to preserve history stack on Chrome Android
 *
 * Key changes in 5.12:
 * - Removed all location.href reloads that were resetting history
 * - Use history.pushState to add buffer entries before navigating
 * - Navigate directly to next notification without page reload
 * - This preserves Chrome Android PWA history stack
 *
 * Flow:
 * 1. Dashboard loads → wait 5 seconds → pushState(buffer) → navigate to notification
 * 2. User presses back → returns to dashboard (buffer entry)
 * 3. Dashboard detects return → pushState(buffer) → navigate to next notification
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
                done: sessionStorage.getItem(this.config.sessionDoneKey)
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
     * Send debug log to server - v5.12
     */
    log(event, data) {
        const state = this.getFullState();

        const payload = {
            page: 'DASHBOARD',
            v: '5.12',
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

        // ========== STEP 2: Check if came from notification ==========
        // v5.12: Simplified - removed pending_notification_index (no longer needed without reloads)
        const cameFrom = sessionStorage.getItem(this.config.sessionCameFromKey);
        const nextIdx = sessionStorage.getItem(this.config.sessionNextIndexKey);
        this.log('STEP2_CHECK_CAME_FROM', { cameFrom: cameFrom, nextIdx: nextIdx });

        if (cameFrom === 'true') {
            // Clear the flag immediately
            sessionStorage.removeItem(this.config.sessionCameFromKey);
            this.log('STEP2_CAME_FROM_TRUE', { cleared: 'came_from_notification' });

            if (nextIdx !== null) {
                // ========== BRANCH A: More notifications to show ==========
                const idx = parseInt(nextIdx, 10);
                sessionStorage.removeItem(this.config.sessionNextIndexKey);

                this.log('STEP2A_HAS_NEXT', {
                    nextIndex: idx,
                    action: 'v5.12: navigate directly without reload'
                });

                // v5.12: NO RELOAD! Navigate directly to next notification
                // This preserves Chrome Android history stack
                setTimeout(() => {
                    this.navigateToNotification(idx);
                }, 300);

                this.log('<<< INIT_EXIT_WILL_NAV_NEXT', { nextIndex: idx, delay: 300 });
                return;

            } else {
                // ========== BRANCH B: Last notification - all done ==========
                this.log('STEP2B_NO_NEXT', {
                    action: 'v5.12: all done - stay on dashboard'
                });

                sessionStorage.setItem(this.config.sessionDoneKey, 'true');
                this.log('STEP2B_SET_DONE', { done: 'true' });

                // v5.12: NO RELOAD! Just clean up URL if needed
                if (location.hash || location.search) {
                    history.replaceState(null, '', location.pathname);
                    this.log('STEP2B_URL_CLEANED', { newUrl: location.pathname });
                }

                this.log('<<< INIT_EXIT_DONE', { reason: 'all notifications shown' });
                return;
            }
        }

        // ========== STEP 3: First load - start timer ==========
        this.log('STEP3_FIRST_LOAD', { action: 'start timer for first notification' });
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
     * v5.12: Always add buffer entry via pushState before navigating
     */
    navigateToNotification(index) {
        const url = `${this.config.notificationUrl}?index=${index}`;
        const navIndex = window.navigation ? window.navigation.currentEntry.index : -1;
        const histLen = history.length;

        // ========== ENTRY POINT ==========
        this.log('>>> NAVIGATE_ENTER', {
            targetIndex: index,
            targetUrl: url,
            currentNavIndex: navIndex,
            historyLength: histLen
        });

        // v5.12: Always add a buffer entry using pushState
        // This ensures back from notification returns here, not to PWA start
        // pushState doesn't cause page reload - just adds to history
        const bufferState = {
            buffer: true,
            forNotification: index,
            t: Date.now()
        };

        this.log('NAVIGATE_PUSH_BUFFER', {
            index: index,
            navIndex: navIndex,
            newHash: '#b' + index
        });

        history.pushState(bufferState, '', location.pathname + '#b' + index);

        // Small delay to ensure history is updated before navigation
        setTimeout(() => {
            const newNavIndex = window.navigation ? window.navigation.currentEntry.index : -1;
            this.log('<<< NAVIGATE_EXIT_GO', {
                url: url,
                notificationIndex: index,
                navIndexAfterPush: newNavIndex,
                historyLengthAfterPush: history.length
            });
            window.location.href = url;
        }, 50);
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
