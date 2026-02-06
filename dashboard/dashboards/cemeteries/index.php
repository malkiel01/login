<?php

// cemetery_dashboard/index.php
require_once $_SERVER['DOCUMENT_ROOT'] . '/dashboard/dashboards/cemeteries/config.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/dashboard/dashboards/cemeteries/includes/functions.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/auth/token-init.php'; // Persistent Auth for PWA/iOS

// בדיקת הרשאות - רק cemetery_manager או admin יכולים לגשת
requireDashboard(['cemetery_manager', 'admin']);

// טעינת הגדרות משתמש מראש למניעת FOUC
require_once $_SERVER['DOCUMENT_ROOT'] . '/dashboard/dashboards/cemeteries/user-settings/api/UserSettingsManager.php';
$userSettingsConn = getDBConnection();
$userId = getCurrentUserId();

// זיהוי סוג מכשיר מ-User-Agent (fallback אם אין cookie)
function detectDeviceType() {
    // בדוק cookie קודם
    if (isset($_COOKIE['deviceType']) && in_array($_COOKIE['deviceType'], ['mobile', 'desktop'])) {
        return $_COOKIE['deviceType'];
    }
    // זיהוי לפי User-Agent
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $mobileKeywords = ['Mobile', 'Android', 'iPhone', 'iPad', 'iPod', 'BlackBerry', 'Windows Phone'];
    foreach ($mobileKeywords as $keyword) {
        if (stripos($userAgent, $keyword) !== false) {
            return 'mobile';
        }
    }
    return 'desktop';
}
$detectedDeviceType = detectDeviceType();
$userSettingsManager = new UserSettingsManager($userSettingsConn, $userId, $detectedDeviceType);
$userPrefs = $userSettingsManager->getAllWithDefaults();

// קבלת ערכים להחלה על ה-body
$isDarkMode = isset($userPrefs['darkMode']) && ($userPrefs['darkMode']['value'] === true || $userPrefs['darkMode']['value'] === 'true');
$colorScheme = isset($userPrefs['colorScheme']) ? $userPrefs['colorScheme']['value'] : 'purple';
$fontSize = isset($userPrefs['fontSize']) ? max(10, min(30, (int)$userPrefs['fontSize']['value'])) : 14;
$isCompact = isset($userPrefs['compactMode']) && ($userPrefs['compactMode']['value'] === true || $userPrefs['compactMode']['value'] === 'true');
$sidebarCollapsed = isset($userPrefs['sidebarCollapsed']) && ($userPrefs['sidebarCollapsed']['value'] === true || $userPrefs['sidebarCollapsed']['value'] === 'true');

$bodyClasses = [];
$bodyClasses[] = $isDarkMode ? 'dark-theme' : 'light-theme';
if (!$isDarkMode) {
    $bodyClasses[] = 'color-scheme-' . $colorScheme;
}
if ($isCompact) {
    $bodyClasses[] = 'compact-mode';
}
if ($sidebarCollapsed) {
    $bodyClasses[] = 'sidebar-collapsed';
}
?>
<?php
// טען את הקונפיג תשלומים
$paymentTypesConfig = require $_SERVER['DOCUMENT_ROOT'] . '/dashboard/dashboards/cemeteries/config/payment-types-config.php';

// הכן הרשאות משתמש ל-JavaScript
$userPermissions = getUserPermissionsDetailed();
$isAdminUser = isAdmin();
?>
<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php
    // צבע שורת הסטטוס של הפלאפון - לפי ערכת הנושא
    if ($isDarkMode) {
        $statusBarColor = '#374151';
    } else if ($colorScheme === 'green') {
        $statusBarColor = '#059669';
    } else {
        $statusBarColor = '#667eea';
    }
    ?>
    <meta name="theme-color" content="<?= $statusBarColor ?>">
    <title><?php echo DASHBOARD_NAME; ?></title>
    <link rel="icon" href="data:,">

    <!-- v18: ניקוי היסטוריה מיד בטעינת הדשבורד -->
    <script>
    (function() {
        // v18: בדוק אם צריך לנקות היסטוריה (בא מ-redirect-handler)
        var needsClean = sessionStorage.getItem('__cleanHistory');

        if (needsClean) {
            console.log('[v18] 🧹 מנקה היסטוריה אחרי התחברות');
            sessionStorage.removeItem('__cleanHistory');
            sessionStorage.removeItem('__historyLength');

            // החלף את ה-entry הנוכחי - זה מוחק את הקודמים מבחינת back
            history.replaceState({clean: true, t: Date.now()}, '', '/dashboard/dashboards/cemeteries/');
            console.log('[v18] ✅ היסטוריה נוקתה');
            return;
        }

        // v18: בדיקה נוספת עם Navigation API
        if (window.navigation) {
            var entries = navigation.entries();
            var currentIndex = navigation.currentEntry ? navigation.currentEntry.index : -1;

            // חפש login.php בהיסטוריה
            var loginIndex = -1;
            for (var i = 0; i < entries.length; i++) {
                if (entries[i].url && entries[i].url.indexOf('/auth/login') !== -1) {
                    loginIndex = i;
                    break;
                }
            }

            // אם login נמצא בהיסטוריה ואנחנו אחריו - צריך לתקן!
            if (loginIndex >= 0 && currentIndex > loginIndex) {
                console.log('[v18] 🔧 מתקן היסטוריה - login.php נמצא ב-index', loginIndex);

                // נסה לנווט אחורה ולהחליף
                var stepsBack = currentIndex - loginIndex;

                // שמור שצריך להחליף כשנגיע לשם
                sessionStorage.setItem('__replaceLogin', '1');

                // חזור ל-login
                history.go(-stepsBack);
            }
        }
    })();
    </script>
    
    <!-- CSS Files - כל הקבצים כולל החדשים -->
    <link rel="stylesheet" href="/dashboard/dashboards/cemeteries/css/main.css">
    <link rel="stylesheet" href="/dashboard/dashboards/cemeteries/css/dashboard.css?v=20260122c">
    <link rel="stylesheet" href="/dashboard/dashboards/cemeteries/css/sidebar.css">
    <link rel="stylesheet" href="/dashboard/dashboards/cemeteries/css/header.css">
    <link rel="stylesheet" href="/dashboard/dashboards/cemeteries/css/cards.css">
    <link rel="stylesheet" href="/dashboard/dashboards/cemeteries/css/breadcrumb.css">

    <link rel="stylesheet" href="/dashboard/dashboards/cemeteries/css/smart-select.css">

    <link rel="stylesheet" href="/dashboard/dashboards/cemeteries/css/search.css?v=20260122c">
    <link rel="stylesheet" href="/dashboard/dashboards/cemeteries/css/universal-search.css?v=20260122ci">

    <!-- Table Module - New Modular System with Theme Support -->
    <link rel="stylesheet" href="/dashboard/dashboards/cemeteries/table-module/css/table-core.css?v=20260201b">

    <link rel="stylesheet" href="/dashboard/dashboards/cemeteries/css/reports/graves-inventory-report.css">

    <!-- User Preferences (Dark Mode, Compact, etc.) -->
    <link rel="stylesheet" href="/dashboard/dashboards/cemeteries/css/user-preferences.css">

    <!-- Pending Badges (Entity Approval) -->
    <link rel="stylesheet" href="/dashboard/dashboards/cemeteries/css/pending-badges.css">
    <link rel="stylesheet" href="/dashboard/dashboards/cemeteries/css/pending-section.css">

    <!-- User Permissions for JavaScript -->
    <script>
        window.USER_PERMISSIONS = <?= json_encode($userPermissions) ?>;
        window.IS_ADMIN = <?= $isAdminUser ? 'true' : 'false' ?>;

        // פונקציית עזר לבדיקת הרשאה ספציפית
        window.hasPermission = function(module, action) {
            if (window.IS_ADMIN) return true;
            return window.USER_PERMISSIONS[module] &&
                   window.USER_PERMISSIONS[module].includes(action);
        };

        // פונקציה לבדיקה אם יש הרשאה כלשהי למודול
        window.hasAnyPermission = function(module) {
            if (window.IS_ADMIN) return true;
            return window.USER_PERMISSIONS[module] &&
                   window.USER_PERMISSIONS[module].length > 0;
        };

        // פונקציה לבדיקה אם יכול לצפות במודול
        // edit/create כוללים צפיה - אם יש לך הרשאה לערוך, וודאי שתוכל לראות
        window.canView = function(module) {
            if (window.IS_ADMIN) return true;
            if (!window.USER_PERMISSIONS[module]) return false;
            const perms = window.USER_PERMISSIONS[module];
            return perms.includes('view') || perms.includes('edit') || perms.includes('create');
        };

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
                    // ההיסטוריה: login=0, dashboard=1, modal=2 (תמיד)
                    // כשפותחים התראה - pushState ל-#modal (אם אין) או replaceState (אם כבר יש)
                    // כשלוחצים back עם מודל פתוח - history.go(1) מבטל את הניווט + סגירת המודל
                    log('V6_NO_BUFFER', { reason: 'using go(1) to cancel back instead' });

                    // === popstate ===
                    window.addEventListener('popstate', function(e) {
                        log('POPSTATE', { state: e.state });

                        // v10: אם זה forward programmatic - לא לקרוא ל-handleBack
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
                            // לוג כל אירוע ניווט!
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
                                        // v6: לא ניתן ליירט - זה cross-document navigation
                                        // נתן ל-popstate לטפל בזה
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

                            // v21: אם באנו מ-buffer ל-modal - לא לסגור!
                            // הניווט: back מ-#buffer(2) ל-#modal(1) = עדיין מודל פתוח
                            // רק back מ-#modal(1) ל-dashboard(0) = סגור מודל
                            if (to < from && getModalInfo().hasModal) {
                                // בדוק אם עברנו מ-buffer ל-modal (עדיין צריך להישאר פתוח)
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
                                    // באנו מ-buffer ל-modal - לא לסגור, המודל עדיין פתוח
                                    log('NAV_ENTRY_CHANGE_BUFFER_TO_MODAL', {
                                        action: 'keeping modal open, setting flag',
                                        from: from,
                                        to: to
                                    });
                                    // v21: סמן שבאנו מ-buffer כדי ש-handleBack ידע לא לסגור
                                    justCameFromBuffer = true;
                                    setTimeout(function() { justCameFromBuffer = false; }, 500);
                                    return; // לא לסגור!
                                }

                                // לא מ-buffer - סגור את המודל
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
                            // v20: שחזור מ-bfcache - ודא ש-heartbeat עדיין רץ
                            log('PAGESHOW_RESTORE', {
                                persisted: true,
                                heartbeatCount: heartbeatCount,
                                heartbeatIntervalId: heartbeatIntervalId
                            });

                            // בדוק אם צריך להתחיל heartbeat מחדש
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
    </script>
</head>
<body class="<?= implode(' ', $bodyClasses) ?>" data-theme="<?= $isDarkMode ? 'dark' : 'light' ?>" data-color-scheme="<?= $isDarkMode ? '' : $colorScheme ?>" style="--base-font-size: <?= $fontSize ?>px;" data-device-type="<?= $detectedDeviceType ?>">
    <!-- SVG Icons - חייב להיות בתחילת ה-body -->
    <svg style="display: none;">
        <!-- Refresh Icon - Modern circular arrows -->
        <symbol id="icon-refresh" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M21 12a9 9 0 1 1-9-9c2.52 0 4.83 1.04 6.48 2.72"/>
            <polyline points="21 3 21 9 15 9"/>
        </symbol>
        
        <!-- Hamburger Menu -->
        <symbol id="icon-menu" viewBox="0 0 24 24">
            <path stroke="currentColor" stroke-width="2" stroke-linecap="round" d="M4 7h16M4 12h16M4 17h16"/>
        </symbol>
        
        <!-- Close -->
        <symbol id="icon-close" viewBox="0 0 24 24">
            <path stroke="currentColor" stroke-width="2" stroke-linecap="round" d="M18 6L6 18M6 6l12 12"/>
        </symbol>
        
        <!-- Plus -->
        <symbol id="icon-plus" viewBox="0 0 24 24">
            <path d="M12 5v14m-7-7h14" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
        </symbol>
        
        <!-- Edit -->
        <symbol id="icon-edit" viewBox="0 0 24 24">
            <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7" stroke="currentColor" stroke-width="2"/>
            <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z" stroke="currentColor" stroke-width="2"/>
        </symbol>
        
        <!-- Delete -->
        <symbol id="icon-delete" viewBox="0 0 24 24">
            <path d="M3 6h18m-2 0v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
        </symbol>
        
        <!-- Download -->
        <symbol id="icon-download" viewBox="0 0 24 24">
            <path stroke="currentColor" stroke-width="2" stroke-linecap="round" d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4m4-5l5 5l5-5m-5 5V3"/>
        </symbol>
        
        <!-- Fullscreen -->
        <symbol id="icon-fullscreen" viewBox="0 0 24 24">
            <path stroke="currentColor" stroke-width="2" stroke-linecap="round" d="M8 3H5a2 2 0 0 0-2 2v3m18 0V5a2 2 0 0 0-2-2h-3m0 18h3a2 2 0 0 0 2-2v-3M3 16v3a2 2 0 0 0 2 2h3"/>
        </symbol>
        
        <!-- Expand/Collapse -->
        <symbol id="icon-expand" viewBox="0 0 24 24">
            <path d="M7 10l5 5 5-5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
        </symbol>
        
        <!-- Search -->
        <symbol id="icon-search" viewBox="0 0 24 24">
            <circle cx="11" cy="11" r="8" stroke="currentColor" stroke-width="2" fill="none"/>
            <path d="M21 21l-4.35-4.35" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
        </symbol>
    </svg>

    <div class="dashboard-wrapper">
        <!-- Header -->
        <?php include $_SERVER['DOCUMENT_ROOT'] . '/dashboard/dashboards/cemeteries/includes/header.php'; ?>
        
        <div class="dashboard-container">
            <!-- Sidebar -->
            <?php include $_SERVER['DOCUMENT_ROOT'] . '/dashboard/dashboards/cemeteries/includes/sidebar.php'; ?>
            
            <!-- Overlay for mobile sidebar -->
            <div id="sidebarOverlay" class="sidebar-overlay"></div>
            
            <!-- Main Content -->
            <main class="main-content">
                <!-- Breadcrumb -->
                <div class="breadcrumb" id="breadcrumb">
                    <span class="breadcrumb-item">ראשי</span>
                </div>

                <!-- Entity Title + Actions -->
                <div class="entity-title-container" id="entityTitleContainer">
                    <div class="entity-title-text">
                        <h1 class="entity-title" id="entityTitle"></h1>
                        <span class="entity-subtitle" id="entitySubtitle"></span>
                    </div>
                    <div class="action-buttons">
                        <!-- כפתור הצג ממתינים - מופיע רק כשסקשן הממתינים מכווץ -->
                        <button class="btn-show-pending" onclick="EntityPending.expandSection()" title="הצג ממתינים לאישור">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/>
                            </svg>
                            <span>ממתינים</span>
                            <span class="pending-btn-count">0</span>
                        </button>
                        <!-- כפתור הצג חיפוש - מופיע רק כשהחיפוש מכווץ -->
                        <button class="btn-show-search" onclick="UniversalSearch.expandSearchSection(window.currentType || 'cemetery')" title="הצג חיפוש">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <circle cx="11" cy="11" r="8"></circle>
                                <path d="m21 21-4.35-4.35"></path>
                            </svg>
                            <span>הצג חיפוש</span>
                        </button>
                        <button class="btn-search-toggle" onclick="UniversalSearch.toggleOrFocusSearch(window.currentType || 'cemetery')" title="חיפוש">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <circle cx="11" cy="11" r="8"></circle>
                                <path d="m21 21-4.35-4.35"></path>
                            </svg>
                            <span>חיפוש</span>
                        </button>
                        <button id="btnAdd" class="btn btn-primary btn-add" onclick="tableRenderer.openAddModal()">
                            <svg class="icon"><use xlink:href="#icon-plus"></use></svg>
                            <span>הוספה</span>
                        </button>
                    </div>
                </div>

                <!-- Table Container -->
                <div class="table-container">
                    <div class="table-responsive">
                        <table class="data-table" id="mainTable">
                            <thead>
                                <tr id="tableHeaders">
                                    <th>טוען...</th>
                                </tr>
                            </thead>
                            <tbody id="tableBody">
                                <tr>
                                    <td colspan="6" class="text-center">טוען נתונים...</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <!-- Entity cards for mobile will be generated here -->
                    <div class="entity-cards" id="entityCards"></div>
                </div>
            </main>
        </div>
    </div>

    <!-- JavaScript Files - סדר קריטי! -->

    <!-- 1️⃣ בסיס -->
    <script src="/dashboard/dashboards/cemeteries/js/universal-search.js?v=20260122g"></script>
    <script src="/dashboard/dashboards/cemeteries/js/breadcrumb.js"></script>
    <script src="/dashboard/dashboards/cemeteries/js/main.js?v=20260122"></script>
    <script src="/dashboard/dashboards/cemeteries/js/sidebar-counts.js"></script>
    <script src="/dashboard/dashboards/cemeteries/js/clearDashboard.js"></script>

    <!-- Table Module v3.0.0 - מערכת טבלאות חדשה עם תמיכה בהעדפות משתמש -->
    <script src="/dashboard/dashboards/cemeteries/table-module/js/table-permissions.js?v=20260124"></script>
    <script src="/dashboard/dashboards/cemeteries/table-module/js/table-core.js?v=20260202a"></script>

    <script src="/dashboard/dashboards/cemeteries/js/universal-search-init.js"></script>

    <!-- 2️⃣ מערכת פופאפים -->
    <script src="/dashboard/dashboards/cemeteries/popup/popup-manager.js"></script>

    <!-- 3️⃣ מערכת טפסים -->
    <script src="/dashboard/dashboards/cemeteries/forms/FormValidations.js"></script>
    <script src="/dashboard/dashboards/cemeteries/forms/payment-display-manager.js"></script>
    <!-- FormHandler removed - migrated to PopupManager (2026-01-23) -->

    <!-- 4️⃣ כרטיסים -->
    <script src="/dashboard/dashboards/cemeteries/js/hierarchy-cards.js"></script>
    <script src="/dashboard/dashboards/cemeteries/js/cards.js"></script>
    <script src="/dashboard/dashboards/cemeteries/js/responsive.js?v=20260122"></script>
    <script src="/dashboard/dashboards/cemeteries/js/unified-table-renderer.js"></script>

    <!-- 5️⃣ OperationManager ו-Utilities (קריטי - לפני המערכת החדשה!) -->
    <script src="/dashboard/dashboards/cemeteries/js/operation-manager.js"></script>
    <script src="/dashboard/dashboards/cemeteries/js/entities-common-utils.js"></script>

    <!-- 6️⃣ 🆕 המערכת החדשה - Entity Framework (אחרי הכל!) -->
    <script src="/dashboard/dashboards/cemeteries/js/entities-framework/entity-config.js"></script>
    <script src="/dashboard/dashboards/cemeteries/js/entities-framework/entity-state-manager.js"></script>
    <script src="/dashboard/dashboards/cemeteries/js/entities-framework/entity-loader.js"></script>
    <script src="/dashboard/dashboards/cemeteries/js/entities-framework/entity-renderer.js?v=20260122"></script>
    <script src="/dashboard/dashboards/cemeteries/js/entities-framework/entity-manager.js?v=20260201"></script>
    <script src="/dashboard/dashboards/cemeteries/js/entities-framework/entity-initializer.js"></script>

    <!-- Entity Labels - קונפיג מרכזי לתוויות ישויות -->
    <script src="/dashboard/dashboards/cemeteries/js/entity-labels.js"></script>
    <!-- Entity Pending - הצגת רשומות ממתינות לאישור -->
    <script src="/dashboard/dashboards/cemeteries/js/entity-pending.js"></script>

    <!-- 7️⃣ הקבצים הישנים (יישארו כ-fallback) -->
    <script src="/dashboard/dashboards/cemeteries/js/cemeteries-management.js"></script>
    <script src="/dashboard/dashboards/cemeteries/js/blocks-management.js"></script>
    <script src="/dashboard/dashboards/cemeteries/js/plots-management.js"></script>
    <script src="/dashboard/dashboards/cemeteries/js/area-graves-management.js"></script>
    <script src="/dashboard/dashboards/cemeteries/js/graves-management.js?v=2"></script>
    <script src="/dashboard/dashboards/cemeteries/js/customers-management.js?v=2"></script>
    <script src="/dashboard/dashboards/cemeteries/js/purchases-management.js?v=2"></script>
    <script src="/dashboard/dashboards/cemeteries/js/burials-management.js?v=2"></script>
    <script src="/dashboard/dashboards/cemeteries/js/payments-management.js?v=2"></script>
    <script src="/dashboard/dashboards/cemeteries/js/residency-management.js"></script>
    <script src="/dashboard/dashboards/cemeteries/js/countries-management.js"></script>
    <script src="/dashboard/dashboards/cemeteries/js/cities-management.js"></script>

    <script src="/dashboard/dashboards/cemeteries/js/live-search.js"></script>

    <script src="/dashboard/dashboards/cemeteries/js/reports/graves-inventory-report.js"></script>

    <!-- 🗺️ מפה -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/fabric.js/5.3.1/fabric.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>
    <script>
        // הגדרת worker של PDF.js
        pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';
    </script>
    <!-- Map v2 Launcher -->
    <script src="/dashboard/dashboards/cemeteries/js/map-launcher.js"></script>

    <!-- 7️⃣ אתחול -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            initDashboard();
            if (typeof initializeEntityItems === 'function') {
                initializeEntityItems();
            }
            if (typeof handleTableResponsive === 'function') {
                handleTableResponsive();
            }
        });
    </script>

    <script>
        window.PAYMENT_TYPES_CONFIG = <?php echo json_encode($paymentTypesConfig['payment_types']); ?>;
    </script>
    <script src="/dashboard/dashboards/cemeteries/js/smart-select.js"></script>

    <!-- User Settings -->
    <script src="/dashboard/dashboards/cemeteries/user-settings/js/user-settings-storage.js"></script>
    <script src="/dashboard/dashboards/cemeteries/user-settings/js/user-settings-core.js"></script>
    <script>
        // פתיחת הגדרות משתמש
        function openUserSettings() {
            if (typeof PopupManager !== 'undefined') {
                // זיהוי סוג מכשיר והעברתו לדף ההגדרות
                const deviceType = (typeof UserSettingsStorage !== 'undefined')
                    ? UserSettingsStorage.getDeviceType()
                    : (window.innerWidth < 768 ? 'mobile' : 'desktop');

                const profileLabel = deviceType === 'mobile' ? 'מובייל' : 'דסקטופ';

                PopupManager.create({
                    id: 'user-settings-popup',
                    type: 'iframe',
                    src: '/dashboard/dashboards/cemeteries/user-settings/settings-page.php?deviceType=' + deviceType,
                    title: 'הגדרות אישיות - ' + profileLabel,
                    width: 700,
                    height: 600
                });
            }
        }

        // אתחול הגדרות משתמש בטעינת הדף
        document.addEventListener('DOMContentLoaded', function() {
            if (typeof UserSettings !== 'undefined') {
                UserSettings.init().then(() => {
                    console.log('UserSettings initialized');
                }).catch(err => {
                    console.warn('UserSettings init failed (table may not exist yet):', err.message);
                });
            }

            // בקשת הרשאת התראות (רק אחרי התחברות!)
            setTimeout(async function() {
                // בדוק אם כבר ביקשנו לאחרונה
                const lastPrompt = localStorage.getItem('last_notification_prompt');
                const now = Date.now();

                if (lastPrompt && (now - parseInt(lastPrompt)) < 3600000) {
                    console.log('[Push] כבר ביקשנו הרשאה לאחרונה, מדלג...');
                    return;
                }

                // בדוק אם כבר נדחה 3 פעמים
                const deniedCount = parseInt(localStorage.getItem('notification_denied_count') || '0');
                if (deniedCount >= 3) {
                    console.log('[Push] המשתמש דחה 3 פעמים, מפסיק לבקש');
                    return;
                }

                // רק אם ההרשאה במצב default
                if ('Notification' in window && Notification.permission === 'default') {
                    if (confirm('האם לאפשר התראות מהמערכת?')) {
                        try {
                            const permission = await Notification.requestPermission();
                            console.log('[Push] Permission result:', permission);

                            if (permission === 'granted') {
                                // עשה subscribe
                                if (typeof PushSubscriptionManager !== 'undefined') {
                                    const result = await PushSubscriptionManager.subscribe();
                                    console.log('[Push] Subscribe result:', result);
                                }
                                localStorage.removeItem('notification_denied_count');
                            } else {
                                localStorage.setItem('notification_denied_count', deniedCount + 1);
                            }
                        } catch (err) {
                            console.error('[Push] Error:', err);
                        }
                    } else {
                        localStorage.setItem('notification_denied_count', deniedCount + 1);
                    }
                    localStorage.setItem('last_notification_prompt', now);
                }
            }, 3000); // 3 שניות אחרי טעינת הדף
        });

        // האזנה לשינויי הגדרות מהפופאפ
        window.addEventListener('message', async function(event) {
            if (event.data && event.data.type === 'userSettingChanged') {
                const { key, value } = event.data;
                console.log('Setting changed from popup:', key, value);

                if (typeof UserSettings !== 'undefined') {
                    // עדכון הערך בcache המקומי
                    UserSettings.set(key, value).catch(() => {});
                    // החלה מיידית על הממשק
                    UserSettings.applyToUI();
                }
            }

            // בקשת הרשאת התראות מ-iframe
            if (event.data && event.data.type === 'requestNotificationPermission') {
                console.log('[Main] Received notification permission request from iframe');
                try {
                    const permission = await Notification.requestPermission();
                    console.log('[Main] Permission result:', permission);

                    // אם אושר, רשום ל-push לפני שליחת התשובה!
                    if (permission === 'granted' && typeof PushSubscriptionManager !== 'undefined') {
                        const result = await PushSubscriptionManager.subscribe();
                        console.log('[Main] Push subscription result:', result);
                    }

                    // שלח תשובה חזרה ל-iframe (אחרי ה-subscribe!)
                    if (event.source) {
                        event.source.postMessage({
                            type: 'notificationPermissionResult',
                            permission: permission
                        }, '*');
                    }
                } catch (error) {
                    console.error('[Main] Error requesting permission:', error);
                    if (event.source) {
                        event.source.postMessage({
                            type: 'notificationPermissionResult',
                            permission: 'error',
                            error: error.message
                        }, '*');
                    }
                }
            }

            // בקשת סטטוס push מ-iframe
            if (event.data && event.data.type === 'getPushStatus') {
                console.log('[Main] Received push status request from iframe');
                try {
                    const permission = Notification.permission;
                    let hasSubscription = false;

                    if ('serviceWorker' in navigator && 'PushManager' in window) {
                        const registration = await navigator.serviceWorker.ready;
                        const subscription = await registration.pushManager.getSubscription();
                        hasSubscription = !!subscription;
                    }

                    if (event.source) {
                        event.source.postMessage({
                            type: 'pushStatusResult',
                            permission: permission,
                            hasSubscription: hasSubscription
                        }, '*');
                    }
                    console.log('[Main] Sent push status:', { permission, hasSubscription });
                } catch (error) {
                    console.error('[Main] Error getting push status:', error);
                    if (event.source) {
                        event.source.postMessage({
                            type: 'pushStatusResult',
                            permission: 'error',
                            hasSubscription: false,
                            error: error.message
                        }, '*');
                    }
                }
            }

            // פתיחת מסך אישור מ-iframe (ההתראות שלי)
            if (event.data && event.data.type === 'OPEN_APPROVAL_MODAL') {
                console.log('[Main] Received OPEN_APPROVAL_MODAL request from iframe, notificationId:', event.data.notificationId);
                if (typeof ApprovalModal !== 'undefined' && event.data.notificationId) {
                    // הגדרת callback לכשהמודאל ייסגר - לשלוח עדכון לאייפריים
                    ApprovalModal.onClose = function() {
                        console.log('[Main] ApprovalModal closed, notifying iframe to refresh');
                        if (event.source) {
                            event.source.postMessage({ type: 'REFRESH_NOTIFICATIONS' }, '*');
                        }
                    };
                    ApprovalModal.show(event.data.notificationId);
                } else {
                    console.error('[Main] ApprovalModal not available or missing notificationId');
                }
            }

            // ביטול push מ-iframe
            if (event.data && event.data.type === 'disablePushNotifications') {
                console.log('[Main] Received disable push request from iframe');
                try {
                    let success = false;
                    if (typeof PushSubscriptionManager !== 'undefined') {
                        const result = await PushSubscriptionManager.unsubscribe();
                        success = result.success;
                    }

                    if (event.source) {
                        event.source.postMessage({
                            type: 'disablePushResult',
                            success: success
                        }, '*');
                    }
                    console.log('[Main] Disable push result:', success);
                } catch (error) {
                    console.error('[Main] Error disabling push:', error);
                    if (event.source) {
                        event.source.postMessage({
                            type: 'disablePushResult',
                            success: false,
                            error: error.message
                        }, '*');
                    }
                }
            }
        });
    </script>

    <?php
    // Persistent Auth Scripts (PWA/iOS support)
    echo getTokenInitScript();
    echo getLogoutScript();
    ?>

    <!-- Push Notification Listener -->
    <script src="/push/listener.js"></script>
    <!-- Web Push Subscription Manager -->
    <script src="/push/push-subscribe.js"></script>
    <!-- Notification Modal - Dedicated screen for all notifications -->
    <script src="/js/notification-modal.js"></script>
    <!-- Notification Templates - Different display types for notifications -->
    <script src="/dashboard/dashboards/cemeteries/notifications/templates/load-templates.js"></script>
    <!-- Approval Modal for approval-type notifications -->
    <script src="/js/biometric-auth.js"></script>
    <script src="/js/approval-modal.js?v=20260201"></script>

    <!-- Login Notifications - הצגת התראות אוטומטית בכניסה -->
    <script src="/dashboard/dashboards/cemeteries/js/login-notifications.js?v=20260203"></script>
</body>
</html>
