<?php
/**
 * Notification View Page
 * Displays one notification at a time - designed for PWA back button flow
 *
 * @version 1.0.0
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
    <title>התראה - <?php echo DASHBOARD_NAME; ?></title>
    <link rel="icon" href="data:,">
    <link rel="stylesheet" href="/dashboard/dashboards/cemeteries/css/main.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
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

        // Logging function
        function log(event, data) {
            const navInfo = window.navigation ? {
                navIndex: window.navigation.currentEntry.index,
                navLength: window.navigation.entries().length,
                canGoBack: window.navigation.canGoBack,
                canGoForward: window.navigation.canGoForward
            } : { navApi: 'not supported' };

            const payload = {
                page: 'NOTIFICATION_VIEW',
                e: event,
                t: Date.now() - PAGE_LOAD_TIME,
                ts: new Date().toISOString(),
                idx: currentIndex,
                next: nextIndex,
                total: totalNotifications,
                d: data,
                nav: navInfo,
                hist: {
                    length: history.length,
                    state: history.state
                },
                session: {
                    came_from: sessionStorage.getItem('came_from_notification'),
                    next_idx: sessionStorage.getItem('notification_next_index'),
                    done: sessionStorage.getItem('notifications_done')
                }
            };

            console.log('[NotificationView]', event, payload);

            try {
                navigator.sendBeacon(DEBUG_URL, JSON.stringify(payload));
            } catch(e) {
                console.error('Log failed:', e);
            }
        }

        // Log page load
        log('PAGE_LOAD', {
            referrer: document.referrer,
            url: location.href,
            hash: location.hash
        });

        // CRITICAL: Ensure this page has a proper history entry
        history.replaceState(
            { notification: true, index: currentIndex, t: Date.now() },
            '',
            location.href
        );
        log('HISTORY_REPLACE_STATE', { index: currentIndex });

        // Save next index to sessionStorage
        if (nextIndex < totalNotifications) {
            sessionStorage.setItem('notification_next_index', nextIndex.toString());
            log('SESSION_SET_NEXT', { nextIndex: nextIndex });
        } else {
            sessionStorage.setItem('notifications_done', 'true');
            sessionStorage.removeItem('notification_next_index');
            log('SESSION_SET_DONE', { reason: 'all shown' });
        }

        // Mark that we came from notification view
        sessionStorage.setItem('came_from_notification', 'true');

        // Skip all notifications
        function skipAll() {
            log('SKIP_ALL', {});
            sessionStorage.setItem('notifications_done', 'true');
            sessionStorage.removeItem('notification_next_index');
            location.replace('/dashboard/dashboards/cemeteries/');
        }

        // Listen for back button / navigation events
        window.addEventListener('popstate', function(e) {
            log('POPSTATE', { state: e.state });
        });

        window.addEventListener('pagehide', function(e) {
            log('PAGEHIDE', { persisted: e.persisted });
        });

        window.addEventListener('beforeunload', function(e) {
            log('BEFOREUNLOAD', {});
        });

        if ('navigation' in window) {
            window.navigation.addEventListener('navigate', function(e) {
                log('NAV_EVENT', {
                    type: e.navigationType,
                    destUrl: e.destination.url ? e.destination.url.split('/').pop() : 'N/A'
                });
            });
        }

        log('INIT_COMPLETE', {});
    </script>
</body>
</html>
