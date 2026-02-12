/**
 * Login Notifications - Modal-Based System
 * Shows notifications in modals without page navigation
 *
 * @version 7.0.0 - Complete rewrite to modal-based approach
 *
 * Key changes from v6.x:
 * - No page navigation at all - everything is modals
 * - Uses InfoModal for regular notifications
 * - Uses ApprovalModal for approval notifications
 * - Each notification waits for modal close before showing next
 * - No buffer pages, no page reloads, no flickering
 *
 * Flow:
 * 1. Dashboard loads → wait 5 seconds
 * 2. Fetch notifications from API
 * 3. Show first notification in modal
 * 4. User closes modal → wait 5 seconds → show next
 * 5. Repeat until all shown
 */

window.LoginNotificationsNav = {
    config: {
        delayMs: 5000, // 5 seconds between notifications
        apiUrl: '/dashboard/dashboards/cemeteries/my-notifications/api/my-notifications-api.php',
        sessionDoneKey: 'notifications_done',
        debugUrl: '/dashboard/dashboards/cemeteries/api/debug-log.php'
    },

    state: {
        timerId: null,
        initialized: false,
        notifications: [],
        currentIndex: 0,
        modalOpen: false,
        pageLoadTime: Date.now()
    },

    /**
     * Send debug log to server
     */
    log(event, data) {
        const payload = {
            page: 'DASHBOARD',
            v: '7.0',
            e: event,
            t: Date.now() - this.state.pageLoadTime,
            ts: new Date().toISOString(),
            d: data,
            state: {
                initialized: this.state.initialized,
                notificationsCount: this.state.notifications.length,
                currentIndex: this.state.currentIndex,
                modalOpen: this.state.modalOpen,
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
     * v7.1: Added try-catch to prevent breaking the page if something fails
     */
    init() {
        try {
            this.log('>>> INIT_ENTER', { initialized: this.state.initialized });

            if (this.state.initialized) {
                this.log('<<< INIT_EXIT_ALREADY', {});
                return;
            }
            this.state.initialized = true;

            // Clear done flag on fresh login
            const referrer = document.referrer || '';
            const isFromLogin = referrer.includes('login.php') || referrer.includes('/auth/');

            if (isFromLogin) {
                sessionStorage.removeItem(this.config.sessionDoneKey);
                this.log('FRESH_LOGIN_DETECTED', { referrer: referrer });
            }

            // Check if done
            const isDone = sessionStorage.getItem(this.config.sessionDoneKey) === 'true';
            if (isDone) {
                this.log('<<< INIT_EXIT_DONE', { reason: 'notifications already done' });
                return;
            }

            // Check if we're in the notification flow with a pending approval
            const cameFromNotification = sessionStorage.getItem('came_from_notification') === 'true';
            const pendingApprovalId = sessionStorage.getItem('pendingApprovalId');
            const nextIndex = sessionStorage.getItem('notification_next_index');

            if (cameFromNotification && pendingApprovalId) {
                // We're in the notification flow and there's an approval to show
                this.log('NOTIFICATION_FLOW_APPROVAL', {
                    approvalId: pendingApprovalId,
                    nextIndex: nextIndex
                });

                // Clear the pending approval (we'll show it now)
                sessionStorage.removeItem('pendingApprovalId');

                this.state.modalOpen = true;

                // Set up callback for when modal closes
                const self = this;
                const hasMore = nextIndex !== null;
                const idx = hasMore ? parseInt(nextIndex, 10) : 0;

                if (window.ApprovalModal) {
                    window.ApprovalModal.onClose = function() {
                        self.log('APPROVAL_MODAL_CLOSED', { approvalId: pendingApprovalId });
                        self.state.modalOpen = false;
                        sessionStorage.removeItem('came_from_notification');

                        if (hasMore) {
                            sessionStorage.removeItem('notification_next_index');
                            self.log('CONTINUE_FLOW', { nextIndex: idx });
                            self.startTimer(idx);
                        } else {
                            sessionStorage.setItem(self.config.sessionDoneKey, 'true');
                            self.log('ALL_NOTIFICATIONS_DONE', {});
                        }
                    };

                    // Show the modal
                    window.ApprovalModal.show(parseInt(pendingApprovalId));
                }
                return;
            }

            // DEBUG v8.6: Show initial history state
            alert('🟢 שלב 1: התחלה\nhistory.length = ' + history.length + '\n\n[...] → [דשבורד] ← אתה כאן');

            // Start the 5 second timer
            this.log('STEP_START_TIMER', { delayMs: this.config.delayMs });
            this.startTimer();
        } catch (error) {
            console.error('[LoginNotificationsNav] Init error:', error);
            // Don't break the page - just log the error
        }
    },

    /**
     * Start timer for showing notification
     */
    startTimer(startFromIndex = 0) {
        // Don't start timer if a modal is open
        if (this.state.modalOpen) {
            this.log('TIMER_SKIP_MODAL_OPEN', {});
            return;
        }

        if (this.state.timerId) {
            clearTimeout(this.state.timerId);
        }

        this.state.currentIndex = startFromIndex;

        this.log('>>> TIMER_START', {
            startFromIndex: startFromIndex,
            delayMs: this.config.delayMs
        });

        this.state.timerId = setTimeout(() => {
            this.log('TIMER_FIRED', {
                index: startFromIndex,
                historyLength: history.length
            });
            this.fetchAndShowNotifications(startFromIndex);
        }, this.config.delayMs);
    },

    /**
     * Fetch notifications and show the one at index
     */
    async fetchAndShowNotifications(index) {
        try {
            const response = await fetch(this.config.apiUrl + '?action=get_unread', {
                credentials: 'include'
            });
            const data = await response.json();

            if (!data.success || !data.notifications || data.notifications.length === 0) {
                this.log('NO_NOTIFICATIONS', {});
                sessionStorage.setItem(this.config.sessionDoneKey, 'true');
                return;
            }

            this.state.notifications = data.notifications;
            const total = this.state.notifications.length;

            this.log('FETCHED_NOTIFICATIONS', {
                count: total,
                showingIndex: index
            });

            if (index >= total) {
                this.log('INDEX_OUT_OF_BOUNDS', { index: index, total: total });
                sessionStorage.setItem(this.config.sessionDoneKey, 'true');
                return;
            }

            this.showNotification(index);

        } catch (error) {
            this.log('FETCH_ERROR', { error: error.message });
            console.error('[LoginNotificationsNav] Fetch error:', error);
        }
    },

    /**
     * Show notification at index
     */
    showNotification(index) {
        const notification = this.state.notifications[index];
        const total = this.state.notifications.length;
        const counter = (index + 1) + '/' + total;
        const hasMore = (index + 1) < total;

        this.log('>>> SHOW_NOTIFICATION', {
            index: index,
            id: notification.id,
            title: notification.title,
            requiresApproval: notification.requires_approval,
            hasMore: hasMore,
            historyLengthBeforeShow: history.length
        });

        this.state.modalOpen = true;

        if (notification.requires_approval == 1 || notification.requires_approval === true) {
            // Use ApprovalModal for approval notifications
            this.showApprovalNotification(notification, index, hasMore);
        } else {
            // Use InfoModal for regular notifications
            this.showInfoNotification(notification, index, counter, hasMore);
        }
    },

    /**
     * Show approval notification using ApprovalModal
     * v8.6: Debug alerts + dummy pushState only for first notification
     */
    showApprovalNotification(notification, index, hasMore) {
        const notificationId = notification.scheduled_notification_id || notification.id;

        // Set up session storage for the flow
        sessionStorage.setItem('came_from_notification', 'true');
        if (hasMore) {
            sessionStorage.setItem('notification_next_index', (index + 1).toString());
        } else {
            sessionStorage.removeItem('notification_next_index');
        }

        // v8.7: Add dummy pushState for ALL notifications (not just first)
        // This ensures we always have a "cushion" for the back button
        if (index === 0) {
            alert('🟡 שלב 2: לפני הוספת סימן דמה\nhistory.length = ' + history.length + '\n\n[...] → [דשבורד] ← אתה כאן');
        } else {
            alert('🔵 שלב 5.5: לפני הוספת סימן דמה להתראה ' + (index + 1) + '\nhistory.length = ' + history.length + '\n\n[...] → [דשבורד] ← אתה כאן');
        }

        // Add dummy pushState for EVERY notification
        history.pushState({ dummyForNotification: index }, '', window.location.href);
        this._addedDummyState = true;

        // v8.8: Tell ApprovalModal that we added a dummy that needs cleanup
        if (window.ApprovalModal) {
            window.ApprovalModal._hasDummyState = true;
        }

        if (index === 0) {
            alert('🟠 שלב 3: אחרי הוספת סימן דמה\nhistory.length = ' + history.length + '\n\n[...] → [דשבורד] → [דמה] ← אתה כאן');
        } else {
            alert('🟠 שלב 6: אחרי הוספת סימן דמה להתראה ' + (index + 1) + '\nhistory.length = ' + history.length + '\n\n[...] → [דשבורד] → [דמה' + (index + 1) + '] ← אתה כאן');
        }

        // Set up callback for when ApprovalModal closes
        if (window.ApprovalModal) {
            const self = this;
            window.ApprovalModal.onClose = function() {
                self.log('>>> APPROVAL_ONCLOSE_ENTER', {
                    notificationId: notificationId,
                    index: index,
                    hasMore: hasMore,
                    historyLength: history.length,
                    modalOpen: self.state.modalOpen,
                    timerId: self.state.timerId ? 'exists' : 'null'
                });

                // DEBUG v8.7: Alert after modal closed
                if (index === 0) {
                    alert('🔴 שלב 5: אחרי סגירת התראה 1\nhistory.length = ' + history.length + '\nhasMore = ' + hasMore + '\n\n[...] → [דשבורד] ← אתה כאן\n(דמה נמחק)');
                } else {
                    alert('⬛ שלב 9: אחרי סגירת התראה ' + (index + 1) + '\nhistory.length = ' + history.length + '\nhasMore = ' + hasMore + '\n\n[...] → [דשבורד] ← אתה כאן\n(דמה' + (index + 1) + ' נמחק)');
                }

                self.state.modalOpen = false;

                if (hasMore) {
                    self.log('STARTING_NEXT_TIMER', { nextIndex: index + 1, historyLength: history.length });
                    self.startTimer(index + 1);
                } else {
                    sessionStorage.setItem(self.config.sessionDoneKey, 'true');
                    sessionStorage.removeItem('came_from_notification');
                    self.log('ALL_NOTIFICATIONS_DONE', { historyLength: history.length });
                }

                self.log('<<< APPROVAL_ONCLOSE_EXIT', { historyLength: history.length });
            };

            // DEBUG v8.7: Alert before showing modal
            if (index === 0) {
                alert('🟣 שלב 3.5: לפני הצגת iframe\nhistory.length = ' + history.length + '\n\n[...] → [דשבורד] → [דמה] ← אתה כאן');
            } else {
                alert('🟤 שלב 7: לפני הצגת iframe להתראה ' + (index + 1) + '\nhistory.length = ' + history.length + '\n\n[...] → [דשבורד] → [דמה' + (index + 1) + '] ← אתה כאן');
            }

            // Pass notification index to ApprovalModal for debug alerts
            window.ApprovalModal._debugNotificationIndex = index;

            this.log('SHOWING_APPROVAL_MODAL', { notificationId: notificationId });
            window.ApprovalModal.show(notificationId);
        } else {
            console.error('[LoginNotificationsNav] ApprovalModal not found');
            this.state.modalOpen = false;
        }
    },

    /**
     * Show info notification using InfoModal
     */
    showInfoNotification(notification, index, counter, hasMore) {
        if (!window.InfoModal) {
            console.error('[LoginNotificationsNav] InfoModal not found');
            this.state.modalOpen = false;
            return;
        }

        const self = this;

        this.log('SHOWING_INFO_MODAL', {
            id: notification.id,
            counter: counter
        });

        // Show the modal with a callback for when it closes
        window.InfoModal.show(notification, counter, function() {
            self.log('INFO_MODAL_CLOSED', {
                id: notification.id,
                historyLength: history.length,
                hasMore: hasMore
            });
            self.state.modalOpen = false;

            // Check if skip all was clicked
            if (sessionStorage.getItem(self.config.sessionDoneKey) === 'true') {
                self.log('SKIP_ALL_DETECTED', {});
                return;
            }

            if (hasMore) {
                self.log('WAIT_FOR_NEXT', {
                    nextIndex: index + 1,
                    historyLengthAfterClose: history.length
                });
                self.startTimer(index + 1);
            } else {
                sessionStorage.setItem(self.config.sessionDoneKey, 'true');
                self.log('ALL_NOTIFICATIONS_DONE', {});
            }
        });
    },

    /**
     * Cancel pending timer
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
        sessionStorage.removeItem('notification_next_index');
        console.log('[LoginNotificationsNav] Skipped all notifications');
    },

    /**
     * Reset (for testing)
     */
    reset() {
        this.cancel();
        sessionStorage.removeItem(this.config.sessionDoneKey);
        sessionStorage.removeItem('notification_next_index');
        sessionStorage.removeItem('came_from_notification');
        sessionStorage.removeItem('pendingApprovalId');
        this.state.initialized = false;
        this.state.notifications = [];
        this.state.currentIndex = 0;
        this.state.modalOpen = false;
        console.log('[LoginNotificationsNav] Reset complete');
    },

    /**
     * Test: show notification immediately
     */
    testNow(index = 0) {
        console.log('[LoginNotificationsNav] Test - showing immediately from index:', index);
        this.fetchAndShowNotifications(index);
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

    // Handle bfcache restore
    window.addEventListener('pageshow', function(e) {
        if (e.persisted) {
            LoginNotificationsNav.log('BFCACHE_RESTORE', {});
            LoginNotificationsNav.state.initialized = false;
            LoginNotificationsNav.state.pageLoadTime = Date.now();
            setTimeout(() => {
                LoginNotificationsNav.init();
            }, 500);
        }
    });
})();
