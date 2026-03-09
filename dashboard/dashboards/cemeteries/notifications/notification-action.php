<?php
/**
 * דף התראה ייעודי - התראות המצריכות מענה/אימות
 * דף PHP עצמאי שמציג התראה ומאפשר אישור/סירוב
 *
 * Flow:
 * - הדשבורד מפנה לכאן אחרי 5 שניות
 * - או push notification מפנה ישירות עם ?id=X
 * - אישור/סירוב → location.replace() → חזרה לדשבורד (ללא history)
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

// בדוק אם זו התראת entity approval עם pending operation
$entityApprovalUrl = null;
if (!empty($notification['url']) && strpos($notification['url'], 'entity-approve') !== false) {
    $entityApprovalUrl = $notification['url'];
    if (strpos($entityApprovalUrl, 'embed=') === false) {
        $entityApprovalUrl .= (strpos($entityApprovalUrl, '?') !== false ? '&' : '?') . 'embed=1';
    }
}

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
    <title>בקשת אישור - <?= htmlspecialchars($notification['title']) ?></title>
    <style>
        :root {
            --primary: #667eea;
            --primary-dark: #5a67d8;
            --success: #10b981;
            --danger: #ef4444;
            --bg: <?= $isDarkMode ? '#0f172a' : '#f8fafc' ?>;
            --bg-card: <?= $isDarkMode ? '#1e293b' : '#ffffff' ?>;
            --text: <?= $isDarkMode ? '#e2e8f0' : '#1e293b' ?>;
            --text-muted: <?= $isDarkMode ? '#94a3b8' : '#64748b' ?>;
            --border: <?= $isDarkMode ? '#334155' : '#e2e8f0' ?>;
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
            background: linear-gradient(135deg, var(--primary) 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            padding-top: max(20px, env(safe-area-inset-top));
            text-align: center;
        }

        .notification-header .icon { font-size: 48px; margin-bottom: 8px; }
        .notification-header h1 { font-size: 20px; font-weight: 700; }
        .notification-header .meta {
            font-size: 13px;
            opacity: 0.8;
            margin-top: 6px;
        }

        .notification-body {
            flex: 1;
            display: flex;
            flex-direction: column;
        }

        /* Entity approval iframe mode */
        .notification-body.iframe-mode {
            padding: 0;
        }

        .notification-body.iframe-mode iframe {
            flex: 1;
            width: 100%;
            border: none;
            min-height: 400px;
        }

        /* Generic approval content (no entity) */
        .notification-content {
            flex: 1;
            padding: 24px 20px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
        }

        .notification-content .message {
            background: var(--bg-card);
            border-radius: 16px;
            padding: 24px;
            max-width: 400px;
            width: 100%;
            text-align: center;
            line-height: 1.8;
            font-size: 17px;
            color: var(--text);
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }

        .notification-content .approval-message {
            margin-top: 16px;
            padding: 16px;
            background: <?= $isDarkMode ? '#334155' : '#f1f5f9' ?>;
            border-radius: 12px;
            font-size: 15px;
            color: var(--text-muted);
            max-width: 400px;
            width: 100%;
            text-align: center;
        }

        .notification-footer {
            padding: 20px;
            padding-bottom: max(20px, env(safe-area-inset-bottom));
            display: flex;
            gap: 12px;
        }

        .btn {
            flex: 1;
            padding: 16px;
            border: none;
            border-radius: 14px;
            font-size: 17px;
            font-weight: 700;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            transition: transform 0.15s, opacity 0.15s;
        }

        .btn:active { transform: scale(0.97); }
        .btn:disabled { opacity: 0.5; cursor: not-allowed; }

        .btn-approve {
            background: var(--success);
            color: white;
        }

        .btn-reject {
            background: var(--danger);
            color: white;
        }

        /* Loading spinner */
        .loading-overlay {
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.4);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 100;
        }

        .loading-overlay.active { display: flex; }

        .spinner {
            width: 48px;
            height: 48px;
            border: 4px solid rgba(255,255,255,0.3);
            border-top-color: white;
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
        }

        @keyframes spin { to { transform: rotate(360deg); } }

        /* Result overlay */
        .result-overlay {
            position: fixed;
            inset: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-direction: column;
            gap: 16px;
            z-index: 200;
        }

        .result-overlay.success { background: var(--success); }
        .result-overlay.rejected { background: var(--danger); }

        .result-overlay .result-icon { font-size: 64px; color: white; }
        .result-overlay .result-text { font-size: 20px; color: white; font-weight: 600; }
    </style>
</head>
<body>
    <div class="notification-header">
        <div class="icon">🔔</div>
        <h1><?= htmlspecialchars($notification['title']) ?></h1>
        <div class="meta">
            <?php if ($notification['created_at']): ?>
                <?= date('d/m/Y H:i', strtotime($notification['created_at'])) ?>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($entityApprovalUrl): ?>
        <!-- Entity approval - show in iframe -->
        <div class="notification-body iframe-mode">
            <iframe id="approvalFrame" src="<?= htmlspecialchars($entityApprovalUrl) ?>"
                    sandbox="allow-scripts allow-same-origin allow-forms"></iframe>
        </div>
    <?php else: ?>
        <!-- Generic approval notification -->
        <div class="notification-body">
            <div class="notification-content">
                <div class="message"><?= nl2br(htmlspecialchars($notification['body'])) ?></div>
                <?php if (!empty($notification['approval_message'])): ?>
                    <div class="approval-message"><?= nl2br(htmlspecialchars($notification['approval_message'])) ?></div>
                <?php endif; ?>
            </div>
            <div class="notification-footer">
                <button class="btn btn-approve" onclick="handleResponse('approved')" id="btnApprove">
                    <span>✓</span> אישור
                </button>
                <button class="btn btn-reject" onclick="handleResponse('rejected')" id="btnReject">
                    <span>✗</span> דחייה
                </button>
            </div>
        </div>
    <?php endif; ?>

    <div class="loading-overlay" id="loadingOverlay">
        <div class="spinner"></div>
    </div>

    <script>
    var notificationId = <?= $notificationId ?>;
    var scheduledId = <?= (int)($notification['scheduled_notification_id'] ?? 0) ?>;

    // Mark as dismissed when user navigates away (back button)
    // This runs BEFORE the page unloads
    window.addEventListener('pagehide', function() {
        // Use sendBeacon for reliable delivery during page unload
        navigator.sendBeacon(
            '/dashboard/dashboards/cemeteries/my-notifications/api/my-notifications-api.php',
            JSON.stringify({
                action: 'dismiss',
                notification_id: notificationId
            })
        );
    });

    // Handle response (approve/reject) for generic notifications
    async function handleResponse(status) {
        document.getElementById('btnApprove').disabled = true;
        document.getElementById('btnReject').disabled = true;
        document.getElementById('loadingOverlay').classList.add('active');

        try {
            var response = await fetch('/dashboard/dashboards/cemeteries/my-notifications/api/my-notifications-api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                credentials: 'include',
                body: JSON.stringify({
                    action: 'respond',
                    notification_id: notificationId,
                    scheduled_notification_id: scheduledId,
                    status: status
                })
            });

            var data = await response.json();

            document.getElementById('loadingOverlay').classList.remove('active');
            showResult(status === 'approved');
        } catch (e) {
            document.getElementById('loadingOverlay').classList.remove('active');
            alert('שגיאה: ' + e.message);
            document.getElementById('btnApprove').disabled = false;
            document.getElementById('btnReject').disabled = false;
        }
    }

    function showResult(isApproved) {
        var overlay = document.createElement('div');
        overlay.className = 'result-overlay ' + (isApproved ? 'success' : 'rejected');
        overlay.innerHTML =
            '<div class="result-icon">' + (isApproved ? '✓' : '✗') + '</div>' +
            '<div class="result-text">' + (isApproved ? 'אושר בהצלחה' : 'נדחה') + '</div>';
        document.body.appendChild(overlay);

        setTimeout(function() {
            // Replace removes this page from history - back goes to dashboard
            location.replace('/dashboard/dashboards/cemeteries/');
        }, 2000);
    }

    // Listen for messages from entity-approve iframe
    window.addEventListener('message', function(event) {
        if (event.data && event.data.type === 'entityApprovalComplete') {
            showResult(event.data.status === 'approved');
        }
    });

    // If opened from push notification and app should close after
    var urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('from_push') === '1') {
        // Override: after response, close the window instead of going to dashboard
        window._fromPush = true;
    }
    </script>
</body>
</html>
