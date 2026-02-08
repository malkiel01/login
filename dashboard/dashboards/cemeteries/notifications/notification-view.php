<?php
/**
 * Notification View Page
 * Displays one notification at a time - designed for PWA back button flow
 *
 * @version 5.23.0 - Add View Transitions API + Prefetch for smooth transitions
 */

require_once $_SERVER['DOCUMENT_ROOT'] . '/dashboard/dashboards/cemeteries/config.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/auth/token-init.php';

// User must be logged in
if (!isLoggedIn()) {
    $redirect = '/auth/login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']);
    echo '<!DOCTYPE html><html><head><meta charset="UTF-8">';
    echo '<script>location.replace(' . json_encode($redirect) . ');</script>';
    echo '</head><body></body></html>';
    exit;
}

$pdo = getDBConnection();
$userId = getCurrentUserId();

// Get notification index from URL (0-based)
$index = (int)($_GET['index'] ?? 0);

// FAKE NOTIFICATIONS FOR TESTING
$fakeNotifications = [
    [
        'id' => 'fake_1',
        'title' => 'התראת בדיקה #1',
        'body' => 'זוהי התראה ראשונה לבדיקת המערכת החדשה. לחץ על כפתור החזרה כדי לחזור לדשבורד.',
        'notification_type' => 'info',
        'created_at' => date('Y-m-d H:i:s'),
        'icon' => '🔔'
    ],
    [
        'id' => 'fake_2',
        'title' => 'התראה דחופה #2',
        'body' => 'התראה שנייה - סוג דחוף. המערכת עובדת כמו שצריך!',
        'notification_type' => 'urgent',
        'created_at' => date('Y-m-d H:i:s'),
        'icon' => '🚨'
    ],
    [
        'id' => 'fake_3',
        'title' => 'אזהרה #3',
        'body' => 'התראה שלישית ואחרונה - סוג אזהרה. אחרי זה תחזור לדשבורד ולא יהיו יותר התראות.',
        'notification_type' => 'warning',
        'created_at' => date('Y-m-d H:i:s'),
        'icon' => '⚠️'
    ]
];

$totalNotifications = count($fakeNotifications);

// Check if index is valid
if ($index >= $totalNotifications) {
    // No more notifications - redirect back to dashboard
    echo '<!DOCTYPE html><html><head><meta charset="UTF-8">';
    echo '<script>sessionStorage.setItem("notifications_done", "true"); location.replace("/dashboard/dashboards/cemeteries/");</script>';
    echo '</head><body></body></html>';
    exit;
}

$notification = $fakeNotifications[$index];
$nextIndex = $index + 1;
$counter = ($index + 1) . '/' . $totalNotifications;

// Store the next index in sessionStorage when page loads
// After user presses back and returns to dashboard, dashboard will read this

// Load user settings for theme
require_once $_SERVER['DOCUMENT_ROOT'] . '/dashboard/dashboards/cemeteries/user-settings/api/UserSettingsManager.php';

function detectDeviceType() {
    if (isset($_COOKIE['deviceType']) && in_array($_COOKIE['deviceType'], ['mobile', 'desktop'])) {
        return $_COOKIE['deviceType'];
    }
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
$userSettingsManager = new UserSettingsManager($pdo, $userId, $detectedDeviceType);
$userPrefs = $userSettingsManager->getAllWithDefaults();

$isDarkMode = isset($userPrefs['darkMode']) && ($userPrefs['darkMode']['value'] === true || $userPrefs['darkMode']['value'] === 'true');
$colorScheme = isset($userPrefs['colorScheme']) ? $userPrefs['colorScheme']['value'] : 'purple';

$bodyClasses = [];
$bodyClasses[] = $isDarkMode ? 'dark-theme' : 'light-theme';
if (!$isDarkMode) {
    $bodyClasses[] = 'color-scheme-' . $colorScheme;
}

// Type colors
$typeColors = [
    'urgent' => ['bg' => '#dc2626', 'gradient' => 'linear-gradient(135deg, #dc2626 0%, #b91c1c 100%)'],
    'warning' => ['bg' => '#f59e0b', 'gradient' => 'linear-gradient(135deg, #f59e0b 0%, #d97706 100%)'],
    'info' => ['bg' => '#667eea', 'gradient' => 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)']
];
$typeColor = $typeColors[$notification['notification_type']] ?? $typeColors['info'];
?>
<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
    <meta name="view-transition" content="same-origin">
    <title>התראה - <?php echo DASHBOARD_NAME; ?></title>
    <?php if ($nextIndex < $totalNotifications): ?>
    <link rel="prefetch" href="/dashboard/dashboards/cemeteries/notifications/notification-view.php?index=<?php echo $nextIndex; ?>">
    <?php endif; ?>
    <link rel="icon" href="data:,">
    <link rel="stylesheet" href="/dashboard/dashboards/cemeteries/css/main.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        /* View Transitions API - smooth fade between pages */
        @view-transition {
            navigation: auto;
        }

        ::view-transition-old(root),
        ::view-transition-new(root) {
            animation-duration: 0.25s;
            animation-timing-function: ease-in-out;
        }

        ::view-transition-old(root) {
            animation-name: fade-out;
        }

        ::view-transition-new(root) {
            animation-name: fade-in;
        }

        @keyframes fade-out {
            from { opacity: 1; }
            to { opacity: 0; }
        }

        @keyframes fade-in {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: <?php echo $typeColor['gradient']; ?>;
            min-height: 100vh;
            min-height: 100dvh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .notification-card {
            background: var(--bg-primary, white);
            border-radius: 24px;
            box-shadow: 0 25px 80px rgba(0, 0, 0, 0.3);
            max-width: 420px;
            width: 100%;
            overflow: hidden;
            animation: slideUp 0.4s ease-out;
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .card-header {
            background: <?php echo $typeColor['gradient']; ?>;
            padding: 35px 30px;
            text-align: center;
            color: white;
            position: relative;
        }

        .counter {
            position: absolute;
            top: 16px;
            left: 16px;
            background: rgba(255, 255, 255, 0.2);
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 600;
        }

        .icon {
            font-size: 56px;
            margin-bottom: 16px;
            display: block;
        }

        .card-header h1 {
            font-size: 22px;
            font-weight: 700;
            margin: 0;
        }

        .card-body {
            padding: 30px;
        }

        .notification-body {
            font-size: 16px;
            color: var(--text-secondary, #475569);
            line-height: 1.7;
            margin-bottom: 24px;
            padding: 20px;
            background: var(--bg-secondary, #f8fafc);
            border-radius: 16px;
        }

        .meta-info {
            font-size: 13px;
            color: var(--text-muted, #94a3b8);
            text-align: center;
            margin-bottom: 24px;
        }

        .back-hint {
            text-align: center;
            padding: 18px;
            background: var(--bg-tertiary, #f1f5f9);
            border-radius: 14px;
            color: var(--text-muted, #64748b);
            font-size: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .back-hint .arrow {
            font-size: 20px;
        }

        /* Dark Theme */
        .dark-theme body,
        body.dark-theme {
            background: <?php echo $typeColor['gradient']; ?>;
        }

        .dark-theme .notification-card {
            background: #1e293b;
        }

        .dark-theme .notification-body {
            background: #334155;
            color: #e2e8f0;
        }

        .dark-theme .back-hint {
            background: #334155;
            color: #94a3b8;
        }

        .dark-theme .meta-info {
            color: #64748b;
        }

        /* Skip button */
        .skip-all-btn {
            display: block;
            text-align: center;
            margin-top: 16px;
            color: var(--text-muted, #94a3b8);
            font-size: 13px;
            cursor: pointer;
            text-decoration: underline;
        }

        .skip-all-btn:hover {
            color: var(--text-secondary, #64748b);
        }
    </style>
</head>
<body class="<?php echo implode(' ', $bodyClasses); ?>">
    <div class="notification-card">
        <div class="card-header">
            <span class="counter"><?php echo $counter; ?></span>
            <span class="icon"><?php echo $notification['icon']; ?></span>
            <h1><?php echo htmlspecialchars($notification['title']); ?></h1>
        </div>

        <div class="card-body">
            <div class="notification-body">
                <?php echo nl2br(htmlspecialchars($notification['body'])); ?>
            </div>

            <div class="meta-info">
                <?php echo date('d/m/Y H:i', strtotime($notification['created_at'])); ?>
            </div>

            <div class="back-hint">
                <span class="arrow">←</span>
                <span>לחץ על כפתור החזרה להמשיך</span>
            </div>

            <div class="skip-all-btn" onclick="skipAll()">
                דלג על כל ההתראות
            </div>
        </div>
    </div>

    <script>
        const DEBUG_URL = '/dashboard/dashboards/cemeteries/api/debug-log.php';
        const nextIndex = <?php echo $nextIndex; ?>;
        const totalNotifications = <?php echo $totalNotifications; ?>;
        const currentIndex = <?php echo $index; ?>;
        const PAGE_LOAD_TIME = Date.now();
        const VERSION = '5.21';

        // ========== COMPREHENSIVE STATE SNAPSHOT ==========
        function getFullState() {
            const navApi = window.navigation;
            const entries = navApi ? navApi.entries() : [];

            return {
                // Session storage state
                session: {
                    came_from: sessionStorage.getItem('came_from_notification'),
                    next_idx: sessionStorage.getItem('notification_next_index'),
                    done: sessionStorage.getItem('notifications_done'),
                    nav_pending: sessionStorage.getItem('nav_to_notification')
                },
                // Navigation API state
                nav: navApi ? {
                    currentIndex: navApi.currentEntry.index,
                    currentUrl: navApi.currentEntry.url,
                    currentKey: navApi.currentEntry.key,
                    entriesCount: entries.length,
                    canGoBack: navApi.canGoBack,
                    canGoForward: navApi.canGoForward,
                    allEntries: entries.map((e, i) => ({
                        idx: i,
                        url: e.url ? e.url.split('/').pop().substring(0, 40) : 'N/A',
                        key: e.key ? e.key.substring(0, 8) : 'N/A'
                    }))
                } : { api: 'NOT_AVAILABLE' },
                // History API state
                history: {
                    length: history.length,
                    state: history.state,
                    scrollRestoration: history.scrollRestoration
                },
                // Current notification info
                notification: {
                    current: currentIndex,
                    next: nextIndex,
                    total: totalNotifications,
                    isFirst: currentIndex === 0,
                    isLast: nextIndex >= totalNotifications,
                    remaining: totalNotifications - currentIndex - 1
                },
                // Page info
                page: {
                    url: location.href,
                    pathname: location.pathname,
                    search: location.search,
                    referrer: document.referrer || 'none',
                    visibilityState: document.visibilityState
                }
            };
        }

        // ========== ENHANCED LOGGING - v5.16 ==========
        let logSequence = 0;
        function log(event, data, extra = {}) {
            logSequence++;
            const state = getFullState();
            const now = Date.now();

            const payload = {
                // Meta
                page: 'NOTIF_VIEW',
                v: VERSION,
                seq: logSequence,
                notifIdx: currentIndex,

                // Event info
                e: event,
                t: now - PAGE_LOAD_TIME,
                ts: new Date().toISOString(),

                // Data
                d: data,
                extra: extra,

                // Full state
                state: state
            };

            console.log(`[NotificationView #${currentIndex}]`, event, payload);

            try {
                navigator.sendBeacon(DEBUG_URL, JSON.stringify(payload));
            } catch(e) {
                console.error('Log failed:', e);
            }
        }

        // ========== PAGE LOAD - DETAILED LOGGING ==========
        log('>>> PAGE_LOAD_START', {
            question: 'האם הגענו לדף התראה?',
            answer: 'כן!',
            notificationIndex: currentIndex,
            isFirstNotification: currentIndex === 0,
            isLastNotification: nextIndex >= totalNotifications,
            howManyLeft: totalNotifications - currentIndex - 1
        });

        // ========== v5.20: TRANSITION TIMING ==========
        const transitionStartTime = sessionStorage.getItem('_transition_start');
        if (transitionStartTime) {
            const transitionDuration = Date.now() - parseInt(transitionStartTime, 10);
            const fromIndex = sessionStorage.getItem('_transition_from_index');
            sessionStorage.removeItem('_transition_start');
            sessionStorage.removeItem('_transition_from_index');

            log('⏱️ TRANSITION_COMPLETE', {
                question: 'כמה זמן לקח המעבר?',
                answer: transitionDuration + 'ms',
                fromNotification: fromIndex,
                toNotification: currentIndex,
                duration_ms: transitionDuration,
                isFlickerNoticeable: transitionDuration > 100 ? 'כן - יותר מ-100ms' : 'לא - פחות מ-100ms',
                performanceGrade: transitionDuration < 50 ? 'מצוין' :
                                  transitionDuration < 150 ? 'סביר' :
                                  transitionDuration < 300 ? 'איטי' : 'מאוד איטי'
            });
        }

        // Compare with previous notification (if stored)
        const prevNotifData = sessionStorage.getItem('_prev_notif_state');
        if (prevNotifData) {
            try {
                const prev = JSON.parse(prevNotifData);
                log('COMPARE_WITH_PREVIOUS', {
                    question: 'מה השתנה מההתראה הקודמת?',
                    previous: {
                        notifIndex: prev.notifIndex,
                        historyLength: prev.historyLength,
                        navIndex: prev.navIndex,
                        entriesCount: prev.entriesCount
                    },
                    current: {
                        notifIndex: currentIndex,
                        historyLength: history.length,
                        navIndex: window.navigation ? window.navigation.currentEntry.index : -1,
                        entriesCount: window.navigation ? window.navigation.entries().length : -1
                    },
                    changes: {
                        notifIndexChange: currentIndex - prev.notifIndex,
                        historyLengthChange: history.length - prev.historyLength,
                        navIndexChange: window.navigation ?
                            window.navigation.currentEntry.index - prev.navIndex : 'N/A'
                    }
                });
            } catch(e) {}
        }

        // Store current state for next notification comparison
        sessionStorage.setItem('_prev_notif_state', JSON.stringify({
            notifIndex: currentIndex,
            historyLength: history.length,
            navIndex: window.navigation ? window.navigation.currentEntry.index : -1,
            entriesCount: window.navigation ? window.navigation.entries().length : -1,
            timestamp: Date.now()
        }));

        // Set up history state
        history.replaceState(
            { notification: true, index: currentIndex, t: Date.now() },
            '',
            location.href
        );
        log('HISTORY_STATE_SET', {
            index: currentIndex,
            newState: history.state
        });

        // ========== SESSION STORAGE SETUP ==========
        sessionStorage.setItem('came_from_notification', 'true');

        if (nextIndex < totalNotifications) {
            sessionStorage.setItem('notification_next_index', nextIndex.toString());
            log('SESSION_CONFIGURED', {
                came_from: 'true',
                next_index: nextIndex,
                hasMore: true,
                question: 'מה יקרה כשילחצו חזור?',
                answer: 'נעבור להתראה ' + nextIndex
            });
        } else {
            sessionStorage.removeItem('notification_next_index');
            log('SESSION_CONFIGURED', {
                came_from: 'true',
                next_index: null,
                hasMore: false,
                question: 'מה יקרה כשילחצו חזור?',
                answer: 'נחזור לדשבורד - זו ההתראה האחרונה!'
            });
        }

        // ========== v5.20: Performance API timing ==========
        if (window.performance && performance.getEntriesByType) {
            const navTiming = performance.getEntriesByType('navigation')[0];
            if (navTiming) {
                log('📊 PERFORMANCE_TIMING', {
                    question: 'מה הביצועים של טעינת הדף?',
                    type: navTiming.type, // navigate, reload, back_forward
                    dns_ms: Math.round(navTiming.domainLookupEnd - navTiming.domainLookupStart),
                    tcp_ms: Math.round(navTiming.connectEnd - navTiming.connectStart),
                    request_ms: Math.round(navTiming.responseStart - navTiming.requestStart),
                    response_ms: Math.round(navTiming.responseEnd - navTiming.responseStart),
                    domParse_ms: Math.round(navTiming.domInteractive - navTiming.responseEnd),
                    domComplete_ms: Math.round(navTiming.domComplete - navTiming.domInteractive),
                    total_ms: Math.round(navTiming.loadEventStart - navTiming.startTime),
                    transferSize: navTiming.transferSize,
                    wasFromCache: navTiming.transferSize === 0
                });
            }
        }

        log('<<< PAGE_LOAD_COMPLETE', {
            status: 'מחכה ללחיצת כפתור חזור',
            whatWillHappen: nextIndex < totalNotifications ?
                'יעבור להתראה ' + nextIndex :
                'יחזור לדשבורד'
        });

        // ========== SKIP ALL ==========
        function skipAll() {
            log('USER_ACTION_SKIP_ALL', {
                question: 'המשתמש לחץ דלג על הכל?',
                answer: 'כן!',
                action: 'מנקה sessionStorage וחוזר לדשבורד'
            });
            sessionStorage.setItem('notifications_done', 'true');
            sessionStorage.removeItem('notification_next_index');
            sessionStorage.removeItem('_prev_notif_state');
            location.replace('/dashboard/dashboards/cemeteries/');
        }

        // ========== NAVIGATION EVENTS MONITORING ==========
        window.addEventListener('pagehide', function(e) {
            log('>>> EVENT_PAGEHIDE', {
                question: 'הדף נסגר/מוסתר?',
                persisted: e.persisted,
                meaning: e.persisted ? 'נשמר ב-bfcache' : 'לא נשמר',
                willBeCached: e.persisted
            });
        });

        window.addEventListener('beforeunload', function(e) {
            log('>>> EVENT_BEFOREUNLOAD', {
                question: 'הדף עומד להיסגר?',
                answer: 'כן!',
                reason: 'navigation or close'
            });
        });

        // Monitor ALL navigation events
        if (window.navigation) {
            navigation.addEventListener('navigateerror', function(e) {
                log('!!! NAVIGATE_ERROR', {
                    question: 'האם הניווט נכשל?',
                    answer: 'כן!',
                    error: e.error ? e.error.toString() : 'unknown'
                });
            });

            navigation.addEventListener('navigatesuccess', function(e) {
                log('NAVIGATE_SUCCESS', {
                    question: 'האם הניווט הצליח?',
                    answer: 'כן!'
                });
            });
        }

        // ========== v5.17: POPSTATE TRAP ==========
        // Navigation API canIntercept is FALSE for traverse (back/forward)
        // Solution: Use pushState to create a "trap" entry, then listen for popstate

        const TRAP_STATE = { trap: true, notifIndex: currentIndex, timestamp: Date.now() };

        // Push a trap entry - when user presses back, they hit this first
        history.pushState(TRAP_STATE, '', location.href);

        log('TRAP_CREATED', {
            question: 'האם יצרנו מלכודת להיסטוריה?',
            answer: 'כן! pushState נקרא',
            trapState: TRAP_STATE,
            newHistoryLength: history.length
        });

        // Listen for popstate - fires when user presses back and hits our trap
        window.addEventListener('popstate', function(e) {
            log('>>> POPSTATE_FIRED', {
                question: 'האם המשתמש לחץ חזור?',
                answer: 'כן! popstate התקבל',
                state: e.state,
                isTrap: e.state && e.state.trap,
                historyLength: history.length
            });

            // Check if we're back at the notification (from trap)
            // or if this is a real back navigation
            if (e.state && e.state.notification) {
                // User pressed back and hit our notification state
                // This means they came back from the trap
                log('POPSTATE_AT_NOTIFICATION', {
                    question: 'האם חזרנו להתראה?',
                    answer: 'כן! המשתמש בהתראה',
                    notificationIndex: e.state.index,
                    action: 'ננווט להתראה הבאה או לדשבורד'
                });

                // Navigate to next notification or dashboard
                if (nextIndex < totalNotifications) {
                    // ========== v5.22: Go back to dashboard, let it handle 5-second timer ==========
                    const currentNavIndex = window.navigation ? window.navigation.currentEntry.index : 1;

                    log('📋 SESSION_STATE_BEFORE_DASHBOARD', {
                        question: 'מה מצב ה-sessionStorage לפני חזרה לדשבורד?',
                        came_from_notification: sessionStorage.getItem('came_from_notification'),
                        notification_next_index: sessionStorage.getItem('notification_next_index'),
                        notifications_done: sessionStorage.getItem('notifications_done'),
                        note: 'הדשבורד יקרא את הערכים האלה ויתחיל טיימר של 5 שניות'
                    });

                    log('<<< POPSTATE_TO_DASHBOARD', {
                        question: 'לאן ננווט?',
                        answer: 'לדשבורד! (עם טיימר 5 שניות)',
                        from: currentIndex,
                        nextNotification: nextIndex,
                        method: 'history.go(-' + currentNavIndex + ')',
                        flow: 'דשבורד יציג 5 שניות → אז יעבור להתראה ' + nextIndex
                    });
                    history.go(-currentNavIndex);
                } else {
                    // ========== v5.18: Detailed logging before dashboard redirect ==========
                    const navEntries = window.navigation ? window.navigation.entries() : [];
                    const currentNavIndex = window.navigation ? window.navigation.currentEntry.index : -1;

                    log('BEFORE_DASHBOARD_REDIRECT', {
                        question: 'מה מצב ההיסטוריה לפני חזרה לדשבורד?',
                        historyLength: history.length,
                        historyState: history.state,
                        navCurrentIndex: currentNavIndex,
                        navEntriesCount: navEntries.length,
                        allNavEntries: navEntries.map((e, i) => ({
                            idx: i,
                            url: e.url || 'N/A',
                            key: e.key ? e.key.substring(0, 8) : 'N/A',
                            isCurrent: i === currentNavIndex
                        })),
                        stepsBackToDashboard: currentNavIndex,
                        recommendation: currentNavIndex > 0 ?
                            'אפשר לעשות history.go(-' + currentNavIndex + ') לחזור לדשבורד' :
                            'כבר בדשבורד או אין מידע'
                    });

                    log('<<< POPSTATE_REDIRECT_DASHBOARD', {
                        question: 'האם חוזרים לדשבורד?',
                        answer: 'כן! זו הייתה ההתראה האחרונה',
                        method: currentNavIndex > 0 ? 'history.go(-' + currentNavIndex + ')' : 'location.replace (fallback)'
                    });
                    sessionStorage.setItem('notifications_done', 'true');
                    sessionStorage.removeItem('notification_next_index');
                    sessionStorage.removeItem('came_from_notification');
                    sessionStorage.removeItem('_prev_notif_state');

                    // v5.19: Use history.go() to jump directly to original dashboard
                    // This clears all intermediate entries (buffer, notifications, traps)
                    if (currentNavIndex > 0) {
                        history.go(-currentNavIndex);
                    } else {
                        // Fallback if Navigation API not available
                        location.replace('/dashboard/dashboards/cemeteries/');
                    }
                }
            }
        });

        log('<<< POPSTATE_TRAP_READY', {
            question: 'האם המלכודת מוכנה?',
            answer: 'כן! מחכים ל-popstate',
            currentHistoryLength: history.length,
            trapActive: true
        });
    </script>
</body>
</html>
