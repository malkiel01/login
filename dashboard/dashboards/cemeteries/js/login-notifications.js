/**
 * Login Notifications - Page Navigation System
 * מערכת התראות חדשה - מבוססת ניווט לדף נפרד
 *
 * @version 5.10.0 - FIX: Don't set notifications_done in notification-view, let dashboard handle reload first
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
            v: '5.9',
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
            url: location.href,
            navIndex: window.navigation ? window.navigation.currentEntry.index : -1
        });

        // Check if notifications are done for this session
        if (sessionStorage.getItem(this.config.sessionDoneKey) === 'true') {
            // FIX v5.9: Even if done, check if we just came from last notification
            // and are at navIndex 0 - need to reload for safety
            const cameFromNotif = sessionStorage.getItem(this.config.sessionCameFromKey);
            const currentNavIndex = window.navigation ? window.navigation.currentEntry.index : -1;

            if (cameFromNotif === 'true' && currentNavIndex === 0) {
                sessionStorage.removeItem(this.config.sessionCameFromKey);
                this.log('RELOAD_AFTER_LAST', {
                    reason: 'just finished all notifications but at navIndex 0, need buffer entry',
                    navIndex: currentNavIndex
                });
                // Reload to create buffer entry - prevents back from closing app
                location.href = '/dashboard/dashboards/cemeteries/?_final=' + Date.now();
                return;
            }

            // Clear came_from flag if set
            if (cameFromNotif === 'true') {
                sessionStorage.removeItem(this.config.sessionCameFromKey);
            }

            this.log('SKIP_DONE', {
                reason: 'notifications_done is true',
                navIndex: currentNavIndex,
                cameFromNotif: cameFromNotif
            });
            return;
        }

        // FIX v6: Check if we just reloaded to create a buffer entry
        const pendingIndex = sessionStorage.getItem('pending_notification_index');
        if (pendingIndex !== null) {
            sessionStorage.removeItem('pending_notification_index');
            const idx = parseInt(pendingIndex, 10);
            this.log('PENDING_NOTIFICATION', {
                index: idx,
                navIndex: window.navigation ? window.navigation.currentEntry.index : -1
            });
            // Navigate directly - now we have a real entry to go back to
            setTimeout(() => {
                this.navigateToNotification(idx);
            }, 100);
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

            // CRITICAL DEBUG: Log full state when returning from notification
            const currentNavIndex = window.navigation ? window.navigation.currentEntry.index : -1;
            const allEntries = window.navigation ? window.navigation.entries().map(e => ({
                idx: e.index,
                url: e.url ? e.url.split('/').pop() : 'N/A'
            })) : [];

            this.log('RETURNED_FROM_NOTIFICATION', {
                nextIndex: nextIndex,
                navIndex: currentNavIndex,
                entries: allEntries,
                canGoBack: window.navigation ? window.navigation.canGoBack : 'N/A',
                canGoForward: window.navigation ? window.navigation.canGoForward : 'N/A'
            });

            if (nextIndex !== null) {
                const idx = parseInt(nextIndex, 10);

                // FIX v5.8: ALWAYS reload before next notification
                // Chrome Android PWA has issues with bfcache pages - navigation from
                // bfcached page doesn't create proper history entries
                // Solution: Always do a fresh reload before navigating to next notification
                this.log('RELOAD_BEFORE_NEXT', {
                    index: idx,
                    navIndex: currentNavIndex,
                    reason: 'Always reload to ensure fresh navigation state'
                });

                // Save the next index and reload
                sessionStorage.setItem('pending_notification_index', idx.toString());
                location.href = '/dashboard/dashboards/cemeteries/?_next=' + Date.now();
                return;
            } else {
                // All done - but check if we need to reload first for safety
                // FIX v5.10: If at navIndex 0, reload to create buffer entry before marking done
                const currentNavIndex = window.navigation ? window.navigation.currentEntry.index : -1;

                if (currentNavIndex === 0) {
                    this.log('RELOAD_AFTER_LAST_FROM_ALL_DONE', {
                        reason: 'finished all notifications but at navIndex 0, need buffer entry',
                        navIndex: currentNavIndex
                    });
                    // Set notifications_done BEFORE reload so we don't loop
                    sessionStorage.setItem(this.config.sessionDoneKey, 'true');
                    location.href = '/dashboard/dashboards/cemeteries/?_final=' + Date.now();
                    return;
                }

                this.log('ALL_DONE', { reason: 'nextIndex is null', navIndex: currentNavIndex });
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
            // Log state right before navigation
            const navIndexNow = window.navigation ? window.navigation.currentEntry.index : -1;
            this.log('TIMER_FIRED', {
                index: index,
                navIndex: navIndexNow,
                willReload: navIndexNow === 0
            });
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

        // FIX v6: Force a REAL page reload before notification
        // Chrome Android PWA only respects real navigation entries (not pushState)
        // By reloading the dashboard first with a special param, we ensure
        // there's always a real entry to go back to
        const navIndex = window.navigation ? window.navigation.currentEntry.index : -1;

        this.log('NAVIGATE_CHECK', {
            index: index,
            historyLength: history.length,
            navIndex: navIndex
        });

        // If we're at navIndex 0 (PWA start), we need to create a real entry first
        // Otherwise back from notification will try to go before entry 0 = app closes
        if (navIndex === 0) {
            this.log('NAVIGATE_AT_START', {
                action: 'reload_first',
                reason: 'at navIndex 0, need buffer entry'
            });

            // Save that we need to navigate after reload
            sessionStorage.setItem('pending_notification_index', index.toString());

            // Reload the page - this creates a REAL navigation entry
            location.href = '/dashboard/dashboards/cemeteries/?_nav=' + Date.now();
            return;
        }

        // CRITICAL: This is where 2nd notification goes - NOT reloading
        const allEntries = window.navigation ? window.navigation.entries().map(e => ({
            idx: e.index,
            url: e.url ? e.url.split('/').pop() : 'N/A'
        })) : [];

        this.log('NAVIGATE_DIRECT', {
            index: index,
            historyLength: history.length,
            navIndex: navIndex,
            entries: allEntries,
            canGoBack: window.navigation ? window.navigation.canGoBack : 'N/A',
            canGoForward: window.navigation ? window.navigation.canGoForward : 'N/A',
            ISSUE_CHECK: navIndex === 0 ? 'BUG! Should have reloaded!' : 'OK - navIndex > 0'
        });

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

    // CRITICAL: Handle bfcache restore (back button from notification)
    // When page is restored from bfcache, DOMContentLoaded doesn't fire
    // but pageshow with persisted=true does
    window.addEventListener('pageshow', function(e) {
        // Log EVERY pageshow event, not just persisted
        const navIndex = window.navigation ? window.navigation.currentEntry.index : -1;
        const entries = window.navigation ? window.navigation.entries().map(entry => ({
            idx: entry.index,
            url: entry.url ? entry.url.split('/').pop() : 'N/A'
        })) : [];

        LoginNotificationsNav.log('PAGESHOW', {
            persisted: e.persisted,
            navIndex: navIndex,
            entries: entries,
            historyLength: history.length,
            came_from: sessionStorage.getItem('came_from_notification'),
            next_idx: sessionStorage.getItem('notification_next_index')
        });

        if (e.persisted) {
            // Page restored from bfcache - need to re-check for notifications
            console.log('[LoginNotificationsNav] pageshow persisted - checking for pending notifications');

            LoginNotificationsNav.log('BFCACHE_RESTORE', {
                navIndex: navIndex,
                entries: entries,
                canGoBack: window.navigation ? window.navigation.canGoBack : 'N/A',
                canGoForward: window.navigation ? window.navigation.canGoForward : 'N/A'
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
