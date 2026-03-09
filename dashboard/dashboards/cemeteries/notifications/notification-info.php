<?php
/**
 * דף התראה ייעודי - התראות לידיעה בלבד
 * דף PHP עצמאי שמציג התראת info/warning/urgent
 *
 * Flow:
 * - הדשבורד מפנה לכאן אחרי 5 שניות
 * - או push notification מפנה ישירות עם ?id=X
 * - "הבנתי" → location.replace() → חזרה לדשבורד (ללא history)
 * - כפתור חזרה → dismissed_at מתעדכן → חזרה טבעית לדשבורד
 *
 * @version 1.0.0
 */

require_once $_SERVER['DOCUMENT_ROOT'] . '/dashboard/dashboards/cemeteries/config.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/auth/token-init.php';
require_once __DIR__ . '/NotificationCenter.php';

// אימות משתמש
if (!isLoggedIn()) {
    $redirect = '/auth/login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']);
    header('Location: ' . $redirect);
    exit;
}

$userId = getCurrentUserId();
$notificationId = (int)($_GET['id'] ?? 0);
$center = new NotificationCenter($userId);

// קבלת ההתראה - ספציפית או הבאה בתור
if ($notificationId) {
    $notification = $center->getNotificationById($notificationId);
} else {
    $notification = $center->getNextNotification();
}

// אין התראה - חזרה לדשבורד
if (!$notification) {
    header('Location: /dashboard/dashboards/cemeteries/');
    exit;
}

$notificationId = (int)$notification['id'];

// סוג ההתראה קובע את העיצוב
$type = $notification['notification_type'] ?? 'info';
$typeConfig = [
    'info' => [
        'icon' => '🔔',
        'gradient' => 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)',
        'label' => 'הודעה'
    ],
    'warning' => [
        'icon' => '⚠️',
        'gradient' => 'linear-gradient(135deg, #f59e0b 0%, #d97706 100%)',
        'label' => 'אזהרה'
    ],
    'urgent' => [
        'icon' => '🚨',
        'gradient' => 'linear-gradient(135deg, #dc2626 0%, #b91c1c 100%)',
        'label' => 'דחוף'
    ]
];
$config = $typeConfig[$type] ?? $typeConfig['info'];

// User preferences for theme
$conn = getDBConnection();
$stmt = $conn->prepare("SELECT preferences FROM user_settings WHERE user_id = ?");
$stmt->execute([$userId]);
$prefs = $stmt->fetch(PDO::FETCH_ASSOC);
$isDarkMode = false;
$fontSize = 16;
if ($prefs && $prefs['preferences']) {
    $p = json_decode($prefs['preferences'], true);
    $isDarkMode = !empty($p['darkMode']);
    $fontSize = $p['fontSize'] ?? 16;
}
?>
<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="theme-color" content="<?= $isDarkMode ? '#1e293b' : '#667eea' ?>">
    <title><?= htmlspecialchars($config['label']) ?> - <?= htmlspecialchars($notification['title']) ?></title>
    <style>
        :root {
            --bg: <?= $isDarkMode ? '#0f172a' : '#f8fafc' ?>;
            --bg-card: <?= $isDarkMode ? '#1e293b' : '#ffffff' ?>;
            --text: <?= $isDarkMode ? '#e2e8f0' : '#1e293b' ?>;
            --text-muted: <?= $isDarkMode ? '#94a3b8' : '#64748b' ?>;
            --font-size: <?= $fontSize ?>px;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', system-ui, sans-serif;
            background: var(--bg);
            color: var(--text);
            font-size: var(--font-size);
            min-height: 100vh;
            min-height: 100dvh;
            display: flex;
            flex-direction: column;
        }

        .notification-header {
            background: <?= $config['gradient'] ?>;
            color: white;
            padding: 40px 20px;
            padding-top: max(40px, calc(env(safe-area-inset-top) + 20px));
            text-align: center;
            flex-shrink: 0;
        }

        .notification-header .icon { font-size: 64px; display: block; margin-bottom: 16px; }
        .notification-header h1 { font-size: 24px; font-weight: 700; }
        .notification-header .meta {
            font-size: 13px;
            opacity: 0.8;
            margin-top: 8px;
        }

        .notification-body {
            flex: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 30px 20px;
        }

        .notification-content {
            background: var(--bg-card);
            border-radius: 16px;
            padding: 24px;
            max-width: 400px;
            width: 100%;
            text-align: center;
            line-height: 1.8;
            font-size: 18px;
            color: var(--text);
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            margin-bottom: 24px;
        }

        <?php if (!empty($notification['url'])): ?>
        .notification-link {
            display: inline-block;
            margin-top: 16px;
            padding: 10px 20px;
            background: <?= $isDarkMode ? '#334155' : '#f1f5f9' ?>;
            border-radius: 10px;
            color: #667eea;
            text-decoration: none;
            font-size: 15px;
        }
        <?php endif; ?>

        .notification-hint {
            text-align: center;
            padding: 16px 24px;
            background: <?= $isDarkMode ? '#1e293b' : '#f1f5f9' ?>;
            border-radius: 14px;
            color: var(--text-muted);
            font-size: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            max-width: 300px;
        }

        .notification-hint .arrow { font-size: 22px; }

        .notification-footer {
            padding: 20px;
            padding-bottom: max(20px, env(safe-area-inset-bottom));
        }

        .btn-acknowledge {
            width: 100%;
            max-width: 400px;
            margin: 0 auto;
            display: block;
            padding: 16px;
            border: none;
            border-radius: 14px;
            background: <?= $config['gradient'] ?>;
            color: white;
            font-size: 17px;
            font-weight: 700;
            cursor: pointer;
            transition: transform 0.15s;
        }

        .btn-acknowledge:active { transform: scale(0.97); }
    </style>
</head>
<body>
    <div class="notification-header">
        <span class="icon"><?= $config['icon'] ?></span>
        <h1><?= htmlspecialchars($notification['title']) ?></h1>
        <div class="meta">
            <?php if ($notification['created_at']): ?>
                <?= date('d/m/Y H:i', strtotime($notification['created_at'])) ?>
            <?php endif; ?>
        </div>
    </div>

    <div class="notification-body">
        <div class="notification-content">
            <?= nl2br(htmlspecialchars($notification['body'])) ?>

            <?php if (!empty($notification['url'])): ?>
                <a href="<?= htmlspecialchars($notification['url']) ?>" class="notification-link">
                    פתח קישור →
                </a>
            <?php endif; ?>
        </div>

        <div class="notification-hint">
            <span class="arrow">←</span>
            <span>לחץ על כפתור החזרה להמשיך</span>
        </div>
    </div>

    <div class="notification-footer">
        <button class="btn-acknowledge" onclick="acknowledge()">
            הבנתי ✓
        </button>
    </div>

    <script>
    var notificationId = <?= $notificationId ?>;

    // Mark as dismissed when user navigates away (back button)
    window.addEventListener('pagehide', function() {
        navigator.sendBeacon(
            '/dashboard/dashboards/cemeteries/my-notifications/api/my-notifications-api.php',
            JSON.stringify({
                action: 'dismiss',
                notification_id: notificationId
            })
        );
    });

    // "הבנתי" - mark as read and go back
    function acknowledge() {
        // Mark as read via beacon (reliable)
        navigator.sendBeacon(
            '/dashboard/dashboards/cemeteries/my-notifications/api/my-notifications-api.php',
            JSON.stringify({
                action: 'mark_read',
                notification_id: notificationId
            })
        );

        // Replace removes this page from history
        location.replace('/dashboard/dashboards/cemeteries/');
    }
    </script>
</body>
</html>
