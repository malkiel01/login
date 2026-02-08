/**
 * Login Notifications - Page Navigation System
 * מערכת התראות חדשה - מבוססת ניווט לדף נפרד
 *
 * @version 5.14.0 - FIX: Force page reload with query param (hash-only changes don't reload)
 *
 * Key insight from 5.12 failure:
 * - pushState creates "weak" history entries that Chrome Android PWA ignores
 * - location.href creates "strong" entries that work properly
 *
 * Key insight from 5.11 issue:
 * - Reloading to DIFFERENT URLs (?_nav, ?_next) was resetting history
 * - Solution: Use hash (#b0, #b1) on SAME base URL
 *
 * Flow:
 * 1. Dashboard loads → wait 5 seconds → location.href(#b0) → navigate to notification
 * 2. User presses back → returns to dashboard#b0 (real page load)
 * 3. Dashboard detects return → location.href(#b1) → navigate to next notification
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
     * Send debug log to server - v5.14
     */
    log(event, data) {
        const state = this.getFullState();

        const payload = {
            page: 'DASHBOARD',
            v: '5.14',
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

        // ========== STEP 2: Check if pending navigation to notification ==========
        // v5.13: After buffer page load, navigate to the pending notification
        const pendingNav = sessionStorage.getItem('nav_to_notification');
        this.log('STEP2_CHECK_PENDING_NAV', { pendingNav: pendingNav, hash: location.hash });

        if (pendingNav !== null) {
            sessionStorage.removeItem('nav_to_notification');
            const idx = parseInt(pendingNav, 10);
            this.log('STEP2_PENDING_NAV_FOUND', { index: idx, action: 'will navigate to notification' });

            // Small delay to ensure page is fully loaded
            setTimeout(() => {
                const url = `${this.config.notificationUrl}?index=${idx}`;
                this.log('<<< NAVIGATE_FROM_BUFFER', { url: url, index: idx });
                window.location.href = url;
            }, 100);
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
                sessionStorage.removeItem(this.config.sessionNextIndexKey);

                this.log('STEP3A_HAS_NEXT', {
                    nextIndex: idx,
                    action: 'v5.13: navigate via buffer'
                });

                // v5.13: Navigate to next notification (will add buffer if needed)
                setTimeout(() => {
                    this.navigateToNotification(idx);
                }, 300);

                this.log('<<< INIT_EXIT_WILL_NAV_NEXT', { nextIndex: idx, delay: 300 });
                return;

            } else {
                // ========== BRANCH B: Last notification - all done ==========
                this.log('STEP3B_NO_NEXT', {
                    action: 'v5.13: all done - stay on dashboard'
                });

                sessionStorage.setItem(this.config.sessionDoneKey, 'true');
                this.log('STEP3B_SET_DONE', { done: 'true' });

                // Clean up URL if needed
                if (location.hash || location.search) {
                    history.replaceState(null, '', location.pathname);
                    this.log('STEP3B_URL_CLEANED', { newUrl: location.pathname });
                }

                this.log('<<< INIT_EXIT_DONE', { reason: 'all notifications shown' });
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
     * v5.14: Use query param (not hash) to force page reload
     */
    navigateToNotification(index) {
        const url = `${this.config.notificationUrl}?index=${index}`;
        const navIndex = window.navigation ? window.navigation.currentEntry.index : -1;
        const histLen = history.length;
        const currentSearch = location.search;

        // ========== ENTRY POINT ==========
        this.log('>>> NAVIGATE_ENTER', {
            targetIndex: index,
            targetUrl: url,
            currentNavIndex: navIndex,
            historyLength: histLen,
            currentSearch: currentSearch
        });

        // v5.14: Check if we need to add a buffer entry
        // We need buffer if we're at navIndex 0 OR if we don't have a buffer param
        const hasBuffer = currentSearch.includes('_b=');
        const needsBuffer = navIndex === 0 || !hasBuffer;

        // Use query param to force reload (hash doesn't reload!)
        const bufferUrl = location.pathname + '?_b=' + index + '_' + Date.now();

        if (needsBuffer) {
            this.log('NAVIGATE_NEED_BUFFER', {
                reason: navIndex === 0 ? 'at navIndex 0' : 'no buffer param',
                bufferUrl: bufferUrl
            });

            // v5.14: Use location.href with query param to force reload
            // Store the notification index to navigate after buffer loads
            sessionStorage.setItem('nav_to_notification', index.toString());

            this.log('<<< NAVIGATE_EXIT_TO_BUFFER', { bufferUrl: bufferUrl, pendingNotification: index });
            window.location.href = bufferUrl;
            return;
        }

        // Already have a buffer entry, navigate directly to notification
        this.log('NAVIGATE_DIRECT', {
            reason: 'already have buffer',
            currentSearch: currentSearch
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
