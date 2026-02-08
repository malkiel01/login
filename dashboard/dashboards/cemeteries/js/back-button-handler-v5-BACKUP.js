/**
 * BACKUP - Back Button Handler v5.0
 * נוצר ב-2026-02-08
 * הוסר מ-index.php עקב התנגשות עם login-notifications.js
 *
 * היה מוטמע ב-index.php שורות 183-745
 */

// ========== SIMPLE TEST LOG ==========
fetch('/dashboard/dashboards/cemeteries/api/debug-log.php', {
    method: 'POST',
    headers: {'Content-Type': 'application/json'},
    body: JSON.stringify({event: 'SCRIPT_START', time: Date.now()})
});

// ========== BACK BUTTON HANDLER v5 ==========
// גרסה פשוטה - בלי FAKE pages, רק מודלים
(function() {
    try {
        var DEBUG_URL = '/dashboard/dashboards/cemeteries/api/debug-log.php';
        var HEARTBEAT_INTERVAL = 5000; // כל 5 שניות

        // === מידע גלובלי ===
        var SESSION_ID = 'session_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
        var PAGE_LOAD_TIME = Date.now();
        var backPressCount = 0;
        var eventLog = [];
        var isPWA = window.matchMedia('(display-mode: standalone)').matches ||
                    window.navigator.standalone === true ||
                    localStorage.getItem('isPWA') === 'true' ||
                    (document.referrer === '' && history.length === 1);

        // שמור לסשנים הבאים
        if (isPWA) {
            try { localStorage.setItem('isPWA', 'true'); } catch(e) {}
        }

        var currentBufferIndex = 0;
        var initialHistoryLength = history.length;
        var trapStateCreated = false;
        var lastKnownNavIndex = -1;
        var pagesAdded = []; // רשימת כל הדפים שנוספו

        // === פונקציות עזר ===

        // רשימת כל ה-entries בהיסטוריה (עד 100)
        function getAllEntries() {
            try {
                if (!('navigation' in window)) return [];
                var entries = window.navigation.entries();
                var result = [];
                var currentIdx = window.navigation.currentEntry.index;
                for (var i = 0; i < Math.min(entries.length, 100); i++) {
                    var entry = entries[i];
                    var urlPart = entry.url ? entry.url.split('/').pop() : 'N/A';
                    var fullPath = entry.url ? new URL(entry.url).pathname + (new URL(entry.url).hash || '') : 'N/A';
                    result.push({
                        idx: entry.index,
                        cur: entry.index === currentIdx ? '>>>' : '',  // סימון ה-entry הנוכחי
                        url: urlPart,
                        path: fullPath,
                        same: entry.sameDocument,
                        type: urlPart.indexOf('#app-') === 0 ? 'FAKE' : (urlPart.indexOf('login') >= 0 ? 'LOGIN' : 'REAL')
                    });
                }
                return result;
            } catch(e) { return [{ error: e.message }]; }
        }

        // מצב נוכחי מלא
        function getFullState() {
            try {
                var navIndex = 'navigation' in window ? window.navigation.currentEntry.index : -1;
                var navLength = 'navigation' in window ? window.navigation.entries().length : 0;
                return {
                    historyLength: history.length,
                    historyState: history.state,
                    navIndex: navIndex,
                    navLength: navLength,
                    currentUrl: location.href,
                    hash: location.hash || 'none',
                    bufferIndex: currentBufferIndex,
                    canGoBack: 'navigation' in window ? window.navigation.canGoBack : 'N/A',
                    canGoForward: 'navigation' in window ? window.navigation.canGoForward : 'N/A'
                };
            } catch(e) { return { error: e.message }; }
        }

        // מידע על מודל
        function getModalInfo() {
            try {
                var hasTemplates = typeof NotificationTemplates !== 'undefined';
                var activeModal = hasTemplates ? NotificationTemplates.activeModal : null;
                var domOverlay = document.querySelector('.notification-overlay');
                var currentHash = location.hash || '';
                var isNotifHash = currentHash.indexOf('#notif-') === 0;
                var stateHasModal = history.state && history.state.isNotificationModal;
                return {
                    hasModal: !!activeModal || !!domOverlay || isNotifHash || stateHasModal,
                    modalClass: activeModal ? activeModal.className : (domOverlay ? domOverlay.className : null),
                    bodyOverflow: document.body.style.overflow,
                    isNotifHash: isNotifHash,
                    stateHasModal: stateHasModal,
                    hash: currentHash
                };
            } catch(e) { return { error: e.message }; }
        }

        // === פונקציית לוג ===
        function log(event, data) {
            var logEntry = {
                v: '5.0',
                sid: SESSION_ID,
                n: eventLog.length + 1,
                e: event,
                t: Date.now() - PAGE_LOAD_TIME,
                ts: new Date().toISOString(),
                d: data || {},
                s: getFullState(),
                m: getModalInfo()
            };

            // הוסף רשימת entries לכל אירועי שינוי מצב
            var stateChangeEvents = [
                'PAGE_LOAD', 'BACK', 'BACK_DEBOUNCE', 'PAGE_ADD', 'HEARTBEAT', 'INIT_DONE',
                'NAV_TRAVERSE', 'NAV_CHANGE', 'NAV_EVENT', 'POPSTATE', 'HASHCHANGE',
                'MODAL_CLOSE_ON_BACK', 'MODAL_CLOSED', 'BACK_NO_MODAL_EXIT',
                'BEFOREUNLOAD', 'PAGEHIDE', 'PAGESHOW_RESTORE'
            ];
            if (stateChangeEvents.indexOf(event) >= 0) {
                logEntry.entries = getAllEntries();
            }

            eventLog.push({ e: event, t: Date.now() });
            console.log('[V4]', event, logEntry);

            navigator.sendBeacon ?
                navigator.sendBeacon(DEBUG_URL, JSON.stringify(logEntry)) :
                fetch(DEBUG_URL, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(logEntry), keepalive: true }).catch(function() {});

            return logEntry;
        }

        // === הוספת דף להיסטוריה (עם לוג) ===
        function addPage(type, reason) {
            var beforeState = getFullState();

            currentBufferIndex++;
            var state = {
                app: 'cemeteries',
                isTrap: false,
                bufferIndex: currentBufferIndex,
                pushedAt: Date.now(),
                sessionId: SESSION_ID
            };
            var newHash = '#app-' + currentBufferIndex;

            history.pushState(state, '', newHash);

            var afterState = getFullState();

            var pageInfo = {
                type: type, // 'FAKE'
                reason: reason, // 'init', 'back_pressed', 'modal_closed'
                url: newHash,
                bufferIdx: currentBufferIndex,
                before: { navIdx: beforeState.navIndex, navLen: beforeState.navLength },
                after: { navIdx: afterState.navIndex, navLen: afterState.navLength }
            };

            pagesAdded.push(pageInfo);
            log('PAGE_ADD', pageInfo);

            return pageInfo;
        }

        // === יצירת trap state ===
        function createTrap() {
            var trapState = {
                app: 'cemeteries',
                isTrap: true,
                createdAt: Date.now(),
                sessionId: SESSION_ID
            };
            history.replaceState(trapState, '', location.href);
            trapStateCreated = true;
            log('TRAP_CREATE', { historyLength: history.length });
        }

        // === v8: מניעת קריאה כפולה ל-handleBack ===
        var lastBackTime = 0;
        var BACK_DEBOUNCE_MS = 100;  // מינימום זמן בין קריאות

        // v21: flag לסימון שבאנו מ-buffer (כדי לא לסגור מודל)
        var justCameFromBuffer = false;

        function handleBack(source, info) {
            // מניעת קריאה כפולה - גם popstate וגם nav-api קוראים לנו
            var now = Date.now();
            if (now - lastBackTime < BACK_DEBOUNCE_MS) {
                log('BACK_DEBOUNCE', { source: source, timeSinceLast: now - lastBackTime });
                return;
            }
            lastBackTime = now;

            backPressCount++;

            // בדוק אם יש מודל פתוח בDOM
            var overlay = document.querySelector('.notification-overlay');
            var hasModalInDOM = !!overlay;

            // v21: בדוק אם אנחנו על buffer
            var currentHash = location.hash || '';
            var isOnBuffer = currentHash === '#buffer' || (history.state && history.state.buffer);
            var isOnModal = currentHash === '#modal' || (history.state && history.state.modal);

            // v19: דיבוג מפורט
            var navIdx = window.navigation ? window.navigation.currentEntry.index : -1;
            var navLen = window.navigation ? window.navigation.entries().length : -1;
            var canBack = window.navigation ? window.navigation.canGoBack : null;

            log('BACK', {
                src: source,
                num: backPressCount,
                hasModalInDOM: hasModalInDOM,
                hash: currentHash,
                isOnBuffer: isOnBuffer,
                isOnModal: isOnModal,
                navIdx: navIdx,
                navLen: navLen,
                canBack: canBack,
                histLen: history.length
            });

            // v21: אם באנו מ-buffer - לא לסגור את המודל
            // currententrychange כבר טיפל בזה והפעיל את ה-flag
            if (justCameFromBuffer) {
                log('BACK_FROM_BUFFER', {
                    action: 'buffer consumed, modal stays open',
                    navIdx: navIdx,
                    flag: justCameFromBuffer
                });
                return; // לא לעשות כלום - המודל יישאר פתוח
            }

            if (hasModalInDOM) {
                // יש מודל פתוח - סגור אותו
                // הניווט כבר קרה (מ-#modal לדשבורד), רק צריך לסגור את המודל
                log('MODAL_CLOSE_ON_BACK', {
                    action: 'closing modal, navigation continues to dashboard',
                    navIdx: navIdx,
                    canBack: canBack
                });

                // סגור את המודל
                if (typeof NotificationTemplates !== 'undefined' && NotificationTemplates.close) {
                    NotificationTemplates.close();
                } else {
                    overlay.remove();
                    document.body.style.overflow = '';
                }

                // v19: לוג אחרי סגירת מודל
                var newNavIdx = window.navigation ? window.navigation.currentEntry.index : -1;
                var newCanBack = window.navigation ? window.navigation.canGoBack : null;
                log('MODAL_CLOSED', {
                    navIdxBefore: navIdx,
                    navIdxAfter: newNavIdx,
                    canBackAfter: newCanBack
                });

            } else {
                // אין מודל - תן לניווט להמשיך כרגיל (יציאה מהאפליקציה)
                log('BACK_NO_MODAL_EXIT', {
                    action: 'letting app exit',
                    navIdx: navIdx,
                    canBack: canBack,
                    hash: currentHash
                });
            }
        }

        // === HEARTBEAT - דופק כל כמה שניות ===
        var heartbeatCount = 0;
        var heartbeatIntervalId = null;

        function startHeartbeat() {
            heartbeatIntervalId = setInterval(function() {
                try {
                    heartbeatCount++;
                    var currentNavIndex = 'navigation' in window ? window.navigation.currentEntry.index : -1;

                    // v20: תמיד רשום כל 6 פעימות (30 שניות)
                    var shouldLog = (heartbeatCount % 6 === 0) || (currentNavIndex !== lastKnownNavIndex);

                    if (shouldLog) {
                        log('HEARTBEAT', {
                            lastIdx: lastKnownNavIndex,
                            currIdx: currentNavIndex,
                            changed: currentNavIndex !== lastKnownNavIndex,
                            beatNum: heartbeatCount,
                            intervalId: heartbeatIntervalId
                        });
                        lastKnownNavIndex = currentNavIndex;
                    }
                } catch(e) {
                    // שגיאה ב-heartbeat - רשום אותה!
                    navigator.sendBeacon(DEBUG_URL, JSON.stringify({
                        e: 'HEARTBEAT_ERROR',
                        error: e.message,
                        beatNum: heartbeatCount,
                        t: Date.now()
                    }));
                }
            }, HEARTBEAT_INTERVAL);

            log('HEARTBEAT_STARTED', { intervalId: heartbeatIntervalId });
        }

        // === אתחול ===
        var currentHash = location.hash || '';
        var isAppHash = currentHash.indexOf('#app-') === 0;
        var needsRealNavigation = history.length === 1 && !isAppHash && isPWA;

        log('PAGE_LOAD', {
            isPWA: isPWA,
            historyLen: history.length,
            hash: currentHash,
            needsRealNav: needsRealNavigation,
            referrer: document.referrer || 'none',
            userAgent: navigator.userAgent
        });

        // === רישום listeners מוקדמים ===

        // Error listener
        window.addEventListener('error', function(e) {
            log('ERROR', { msg: e.message, file: e.filename, line: e.lineno });
        });

        // Navigation API - currententrychange (לתפוס כל שינוי!)
        if ('navigation' in window) {
            window.navigation.addEventListener('currententrychange', function(e) {
                log('NAV_CHANGE', {
                    from: e.from ? { idx: e.from.index, url: e.from.url ? e.from.url.split('/').pop() : 'N/A' } : null,
                    to: { idx: window.navigation.currentEntry.index, url: window.navigation.currentEntry.url ? window.navigation.currentEntry.url.split('/').pop() : 'N/A' }
                });
            });
        }

        // === פתרון ל-historyLength === 1 ===
        if (needsRealNavigation) {
            log('CREATE_REAL_HIST', { reason: 'historyLength is 1' });

            var baseUrl = location.href.split('#')[0];
            location.href = baseUrl + '#app-init';

            log('HASH_NAV_START', { target: baseUrl + '#app-init' });

            setTimeout(function() {
                log('HASH_NAV_DONE', { historyLen: history.length, hash: location.hash });
                continueInit();
            }, 100);

            return;
        }

        continueInit();

        function continueInit() {
            log('INIT_CONTINUE', { historyLen: history.length, hash: location.hash });

            // v6: אין צורך ב-buffer entries
            log('V6_NO_BUFFER', { reason: 'using go(1) to cancel back instead' });

            // === popstate ===
            window.addEventListener('popstate', function(e) {
                log('POPSTATE', { state: e.state });

                if (window.isDoingProgrammaticForward) {
                    log('POPSTATE_FORWARD_SKIP', { reason: 'programmatic forward in progress' });
                    return;
                }

                handleBack('popstate', e.state);
            });

            // === hashchange ===
            window.addEventListener('hashchange', function(e) {
                var oldHash = e.oldURL ? e.oldURL.split('#')[1] || '' : '';
                var newHash = e.newURL ? e.newURL.split('#')[1] || '' : '';
                log('HASHCHANGE', { old: oldHash, new: newHash });
            });

            // === Navigation API traverse ===
            if ('navigation' in window) {
                log('NAV_API_INIT', {
                    idx: window.navigation.currentEntry.index,
                    len: window.navigation.entries().length
                });

                window.navigation.addEventListener('navigate', function(e) {
                    log('NAV_EVENT', {
                        type: e.navigationType,
                        canIntercept: e.canIntercept,
                        hashChange: e.hashChange,
                        destUrl: e.destination.url ? e.destination.url.split('/').pop() : 'N/A',
                        destIdx: e.destination.index
                    });

                    if (e.navigationType === 'traverse') {
                        var currIdx = window.navigation.currentEntry.index;
                        var destIdx = e.destination.index;
                        var isBack = destIdx < currIdx;

                        log('NAV_TRAVERSE', {
                            type: e.navigationType,
                            canIntercept: e.canIntercept,
                            from: currIdx,
                            to: destIdx,
                            isBack: isBack
                        });

                        if (isBack) {
                            if (e.canIntercept) {
                                e.intercept({
                                    handler: function() {
                                        setTimeout(function() {
                                            handleBack('nav-api', { from: currIdx, to: destIdx });
                                        }, 0);
                                        return Promise.resolve();
                                    }
                                });
                            } else {
                                log('CANT_INTERCEPT', { from: currIdx, to: destIdx, action: 'let popstate handle' });
                            }
                        }
                    } else if (e.navigationType === 'push' || e.navigationType === 'replace') {
                        log('NAV_PUSH_REPLACE', { type: e.navigationType, url: e.destination.url ? e.destination.url.split('/').pop() : 'N/A' });
                    }
                });
            }

            // === v21: currententrychange - עם תמיכה ב-Buffer Entry ===
            if ('navigation' in window) {
                window.navigation.addEventListener('currententrychange', function(e) {
                    var from = e.from ? e.from.index : -1;
                    var to = window.navigation.currentEntry ? window.navigation.currentEntry.index : -1;
                    var currentHash = location.hash || '';
                    var isFromBuffer = currentHash === '' && history.state && history.state.buffer;
                    var isAtModal = currentHash === '#modal' || (history.state && history.state.modal);

                    log('NAV_ENTRY_CHANGE', {
                        from: from,
                        to: to,
                        hasModal: getModalInfo().hasModal,
                        currentHash: currentHash,
                        isFromBuffer: isFromBuffer,
                        isAtModal: isAtModal,
                        historyState: history.state
                    });

                    if (to < from && getModalInfo().hasModal) {
                        var fromUrl = e.from && e.from.url ? e.from.url : '';
                        var toUrl = window.navigation.currentEntry.url || '';

                        var fromIsBuffer = fromUrl.indexOf('#buffer') >= 0;
                        var toIsModal = toUrl.indexOf('#modal') >= 0;

                        log('NAV_ENTRY_CHANGE_CHECK', {
                            fromUrl: fromUrl.split('/').pop(),
                            toUrl: toUrl.split('/').pop(),
                            fromIsBuffer: fromIsBuffer,
                            toIsModal: toIsModal
                        });

                        if (fromIsBuffer && toIsModal) {
                            log('NAV_ENTRY_CHANGE_BUFFER_TO_MODAL', {
                                action: 'keeping modal open, setting flag',
                                from: from,
                                to: to
                            });
                            justCameFromBuffer = true;
                            setTimeout(function() { justCameFromBuffer = false; }, 500);
                            return;
                        }

                        log('NAV_ENTRY_CHANGE_CLOSE_MODAL', { from: from, to: to });
                        if (typeof NotificationTemplates !== 'undefined' && NotificationTemplates.close) {
                            NotificationTemplates.close();
                        }
                    }
                });
            }

            // === beforeunload ===
            window.addEventListener('beforeunload', function(e) {
                log('BEFOREUNLOAD', { hasModal: getModalInfo().hasModal });
                if (getModalInfo().hasModal) {
                    e.preventDefault();
                    e.returnValue = '';
                    return '';
                }
            });

            // === pagehide ===
            window.addEventListener('pagehide', function(e) {
                log('PAGEHIDE', { persisted: e.persisted });
            });

            // === pageshow ===
            window.addEventListener('pageshow', function(e) {
                if (e.persisted) {
                    log('PAGESHOW_RESTORE', {
                        persisted: true,
                        heartbeatCount: heartbeatCount,
                        heartbeatIntervalId: heartbeatIntervalId
                    });

                    if (!heartbeatIntervalId) {
                        log('PAGESHOW_RESTART_HEARTBEAT', { reason: 'interval was null' });
                        startHeartbeat();
                    }
                }
            });

            // === visibility ===
            document.addEventListener('visibilitychange', function() {
                log('VISIBILITY', { state: document.visibilityState });
            });

            // === סיום אתחול ===
            lastKnownNavIndex = 'navigation' in window ? window.navigation.currentEntry.index : -1;

            log('INIT_DONE', {
                finalNavIdx: lastKnownNavIndex,
                totalPagesAdded: pagesAdded.length,
                methods: ['popstate', 'hashchange', 'nav-api', 'beforeunload', 'pagehide', 'pageshow', 'visibility', 'currententrychange']
            });

            // התחל heartbeat
            startHeartbeat();

        } // סוף continueInit

    } catch(err) {
        fetch('/dashboard/dashboards/cemeteries/api/debug-log.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ e: 'FATAL_ERROR', error: err.message, stack: err.stack, t: Date.now() })
        });
        console.error('[V4] Fatal Error:', err);
    }
})();
// ========== END BACK BUTTON HANDLER v4 ==========
