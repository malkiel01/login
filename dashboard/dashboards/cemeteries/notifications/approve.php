<?php
/**
 * Notification Approval Page
 * Shows notification details and allows user to approve/reject with biometric auth
 *
 * @version 1.0.0
 */

require_once $_SERVER['DOCUMENT_ROOT'] . '/dashboard/dashboards/cemeteries/config.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/auth/token-init.php';

// User must be logged in - v16: use location.replace to prevent history pollution
if (!isLoggedIn()) {
    $redirect = '/auth/login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']);
    echo '<!DOCTYPE html><html><head><meta charset="UTF-8">';
    echo '<script>location.replace(' . json_encode($redirect) . ');</script>';
    echo '</head><body></body></html>';
    exit;
}

$pdo = getDBConnection();
$userId = getCurrentUserId();
$notificationId = (int)($_GET['id'] ?? 0);
$token = $_GET['token'] ?? ''; // Optional security token

if (!$notificationId) {
    die('בקשה לא תקינה');
}

// Get the notification
$stmt = $pdo->prepare("
    SELECT sn.*, u.name as creator_name
    FROM scheduled_notifications sn
    LEFT JOIN users u ON u.id = sn.created_by
    WHERE sn.id = ? AND sn.requires_approval = 1
");
$stmt->execute([$notificationId]);
$notification = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$notification) {
    die('ההתראה לא נמצאה או אינה דורשת אישור');
}

// Check if user is a target
$targetUsers = json_decode($notification['target_users'], true);
$isTarget = in_array('all', $targetUsers) || in_array($userId, $targetUsers);

if (!$isTarget) {
    die('אינך מורשה לצפות בהודעה זו');
}

// Get user's approval status
$stmt = $pdo->prepare("
    SELECT * FROM notification_approvals
    WHERE notification_id = ? AND user_id = ?
");
$stmt->execute([$notificationId, $userId]);
$approval = $stmt->fetch(PDO::FETCH_ASSOC);

// Check if expired
$isExpired = false;
if ($notification['approval_expires_at'] && strtotime($notification['approval_expires_at']) < time()) {
    $isExpired = true;
    // Update status to expired if still pending
    if ($approval && $approval['status'] === 'pending') {
        $pdo->prepare("
            UPDATE notification_approvals SET status = 'expired' WHERE id = ?
        ")->execute([$approval['id']]);
        $approval['status'] = 'expired';
    }
}

$alreadyResponded = $approval && in_array($approval['status'], ['approved', 'rejected']);

// Load user settings for theme
require_once $_SERVER['DOCUMENT_ROOT'] . '/dashboard/dashboards/cemeteries/user-settings/api/UserSettingsManager.php';
require_once __DIR__ . '/core/UserAgentParser.php';

$detectedDeviceType = UserAgentParser::detectDeviceType();
$userSettingsManager = new UserSettingsManager($pdo, $userId, $detectedDeviceType);
$userPrefs = $userSettingsManager->getAllWithDefaults();

$isDarkMode = isset($userPrefs['darkMode']) && ($userPrefs['darkMode']['value'] === true || $userPrefs['darkMode']['value'] === 'true');
$colorScheme = isset($userPrefs['colorScheme']) ? $userPrefs['colorScheme']['value'] : 'purple';

$bodyClasses = [];
$bodyClasses[] = $isDarkMode ? 'dark-theme' : 'light-theme';
if (!$isDarkMode) {
    $bodyClasses[] = 'color-scheme-' . $colorScheme;
}
?>
<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>אישור הודעה - <?php echo DASHBOARD_NAME; ?></title>
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
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        /* Popup style for responded/expired notifications */
        body.popup-mode {
            background: rgba(0, 0, 0, 0.6);
        }

        .approval-card {
            background: var(--bg-primary, white);
            border-radius: 20px;
            box-shadow: var(--shadow-xl, 0 20px 60px rgba(0, 0, 0, 0.2));
            max-width: 480px;
            width: 100%;
            overflow: hidden;
        }

        .card-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 30px;
            text-align: center;
            color: white;
        }

        .card-header .icon {
            font-size: 48px;
            margin-bottom: 12px;
        }

        .card-header h1 {
            font-size: 20px;
            font-weight: 600;
            margin-bottom: 4px;
        }

        .card-header .subtitle {
            font-size: 14px;
            opacity: 0.9;
        }

        .card-body {
            padding: 30px;
        }

        .notification-title {
            font-size: 18px;
            font-weight: 600;
            color: var(--text-primary, #1e293b);
            margin-bottom: 12px;
        }

        .notification-body {
            font-size: 15px;
            color: var(--text-secondary, #475569);
            line-height: 1.6;
            margin-bottom: 20px;
            padding: 16px;
            background: var(--bg-secondary, #f8fafc);
            border-radius: 12px;
        }

        .approval-message {
            font-size: 14px;
            color: #6d28d9;
            background: #ede9fe;
            padding: 12px 16px;
            border-radius: 10px;
            margin-bottom: 20px;
            border-right: 4px solid #8b5cf6;
        }

        .meta-info {
            font-size: 13px;
            color: var(--text-muted, #64748b);
            margin-bottom: 24px;
        }

        .meta-info .item {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 6px;
        }

        .expires-warning {
            color: var(--danger-color, #dc2626);
            background: rgba(220, 38, 38, 0.1);
            padding: 10px 14px;
            border-radius: 8px;
            font-size: 13px;
            margin-bottom: 20px;
        }

        .action-buttons {
            display: flex;
            gap: 12px;
        }

        .btn {
            flex: 1;
            padding: 14px 20px;
            border: none;
            border-radius: 12px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            transition: all 0.2s;
        }

        .btn-approve {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
        }

        .btn-approve:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(16, 185, 129, 0.3);
        }

        .btn-reject {
            background: var(--bg-tertiary, #f1f5f9);
            color: var(--text-muted, #64748b);
        }

        .btn-reject:hover {
            background: var(--border-color, #e2e8f0);
        }

        .btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            transform: none !important;
        }

        .status-message {
            text-align: center;
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 20px;
        }

        .status-approved {
            background: #d1fae5;
            color: #065f46;
        }

        .status-rejected {
            background: #fee2e2;
            color: #991b1b;
        }

        .status-expired {
            background: #f3f4f6;
            color: #6b7280;
        }

        .status-icon {
            font-size: 48px;
            margin-bottom: 10px;
        }

        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 1000;
        }

        .loading-spinner {
            background: var(--bg-primary, white);
            padding: 30px 40px;
            border-radius: 16px;
            text-align: center;
        }

        .spinner {
            width: 40px;
            height: 40px;
            border: 3px solid var(--border-color, #e2e8f0);
            border-top-color: var(--primary-color, #667eea);
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin: 0 auto 12px;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        .biometric-hint {
            font-size: 12px;
            color: var(--text-muted, #64748b);
            text-align: center;
            margin-top: 16px;
        }

        .close-btn {
            display: block;
            text-align: center;
            margin-top: 20px;
            color: var(--text-muted, #64748b);
            text-decoration: none;
            font-size: 14px;
        }

        /* Close X button for responded cards */
        .close-x-btn {
            position: absolute;
            top: 12px;
            left: 12px;
            background: rgba(255, 255, 255, 0.2);
            border: none;
            color: white;
            font-size: 24px;
            width: 36px;
            height: 36px;
            border-radius: 50%;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: background 0.2s;
        }

        .close-x-btn:hover {
            background: rgba(255, 255, 255, 0.3);
        }

        .card-header {
            position: relative;
        }

        /* Smaller card for responded/expired */
        .responded-card {
            max-width: 380px;
        }

        .responded-card .card-header {
            padding: 24px;
        }

        .responded-card .card-header .icon {
            font-size: 40px;
            margin-bottom: 8px;
        }

        .responded-card .card-body {
            padding: 24px;
        }

        /* Dark Theme Support */
        .dark-theme body,
        body.dark-theme {
            background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%);
        }

        body.dark-theme.popup-mode {
            background: rgba(0, 0, 0, 0.8);
        }

        .dark-theme .approval-card {
            background: #1e293b;
        }

        .dark-theme .notification-title {
            color: #f1f5f9;
        }

        .dark-theme .notification-body {
            background: #334155;
            color: #e2e8f0;
        }

        .dark-theme .approval-message {
            background: #312e81;
            color: #c4b5fd;
            border-right-color: #7c3aed;
        }

        .dark-theme .meta-info {
            color: #94a3b8;
        }

        .dark-theme .btn-reject {
            background: #334155;
            color: #e2e8f0;
        }

        .dark-theme .btn-reject:hover {
            background: #475569;
        }

        .dark-theme .status-approved {
            background: #064e3b;
            color: #6ee7b7;
        }

        .dark-theme .status-rejected {
            background: #7f1d1d;
            color: #fca5a5;
        }

        .dark-theme .status-expired {
            background: #374151;
            color: #9ca3af;
        }

        .dark-theme .close-btn {
            color: #94a3b8;
        }

        .dark-theme .biometric-hint {
            color: #94a3b8;
        }

        .dark-theme .expires-warning {
            background: #7f1d1d;
            color: #fca5a5;
        }

        /* Color Scheme Support */
        .color-scheme-purple .card-header,
        .color-scheme-purple body,
        body.color-scheme-purple {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }

        .color-scheme-blue .card-header {
            background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);
        }
        body.color-scheme-blue {
            background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);
        }

        .color-scheme-green .card-header {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
        }
        body.color-scheme-green {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
        }

        .color-scheme-red .card-header {
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
        }
        body.color-scheme-red {
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
        }

        .color-scheme-orange .card-header {
            background: linear-gradient(135deg, #f97316 0%, #ea580c 100%);
        }
        body.color-scheme-orange {
            background: linear-gradient(135deg, #f97316 0%, #ea580c 100%);
        }

        .color-scheme-pink .card-header {
            background: linear-gradient(135deg, #ec4899 0%, #db2777 100%);
        }
        body.color-scheme-pink {
            background: linear-gradient(135deg, #ec4899 0%, #db2777 100%);
        }

        .color-scheme-teal .card-header {
            background: linear-gradient(135deg, #14b8a6 0%, #0d9488 100%);
        }
        body.color-scheme-teal {
            background: linear-gradient(135deg, #14b8a6 0%, #0d9488 100%);
        }
    </style>
</head>
<body class="<?php echo implode(' ', $bodyClasses); ?> <?php echo ($alreadyResponded || $isExpired) ? 'popup-mode' : ''; ?>" data-theme="<?php echo $isDarkMode ? 'dark' : 'light'; ?>">
    <div class="approval-card <?php echo ($alreadyResponded || $isExpired) ? 'responded-card' : ''; ?>">
        <div class="card-header">
            <?php if ($alreadyResponded || $isExpired): ?>
                <button type="button" class="close-x-btn" onclick="closeWindow()" title="סגור">×</button>
            <?php endif; ?>
            <div class="icon">🔐</div>
            <h1>בקשת אישור</h1>
            <div class="subtitle">נשלח על ידי <?php echo htmlspecialchars($notification['creator_name'] ?? 'מנהל'); ?></div>
        </div>

        <div class="card-body">
            <?php if ($alreadyResponded || $isExpired): ?>
                <div class="status-message status-<?php echo $alreadyResponded ? $approval['status'] : 'expired'; ?>">
                    <div class="status-icon">
                        <?php
                        if ($isExpired && !$alreadyResponded) {
                            echo '⏰';
                        } else {
                            echo $approval['status'] === 'approved' ? '✅' : '❌';
                        }
                        ?>
                    </div>
                    <strong>
                        <?php
                        if ($isExpired && !$alreadyResponded) {
                            echo 'פג תוקף האישור';
                        } elseif ($approval['status'] === 'approved') {
                            echo 'האישור נרשם בהצלחה';
                        } else {
                            echo 'הדחייה נרשמה';
                        }
                        ?>
                    </strong>
                    <?php if ($alreadyResponded && $approval['responded_at']): ?>
                        <div style="margin-top: 8px; font-size: 13px;">
                            ב-<?php echo date('d/m/Y H:i', strtotime($approval['responded_at'])); ?>
                        </div>
                    <?php elseif ($isExpired && $notification['approval_expires_at']): ?>
                        <div style="margin-top: 8px; font-size: 13px;">
                            ב-<?php echo date('d/m/Y H:i', strtotime($notification['approval_expires_at'])); ?>
                        </div>
                    <?php endif; ?>
                </div>

            <?php else: ?>
                <div class="notification-title">
                    <?php echo htmlspecialchars($notification['title']); ?>
                </div>

                <div class="notification-body">
                    <?php echo nl2br(htmlspecialchars($notification['body'])); ?>
                </div>

                <?php if ($notification['approval_message']): ?>
                    <div class="approval-message">
                        <?php echo nl2br(htmlspecialchars($notification['approval_message'])); ?>
                    </div>
                <?php endif; ?>

                <div class="meta-info">
                    <div class="item">
                        <span>📅</span>
                        <span>נשלח: <?php echo date('d/m/Y H:i', strtotime($notification['sent_at'] ?? $notification['created_at'])); ?></span>
                    </div>
                    <?php if ($notification['approval_expires_at']): ?>
                        <div class="item">
                            <span>⏰</span>
                            <span>תקף עד: <?php echo date('d/m/Y H:i', strtotime($notification['approval_expires_at'])); ?></span>
                        </div>
                    <?php endif; ?>
                </div>

                <?php
                // Check if expiring soon (within 2 hours)
                if ($notification['approval_expires_at']) {
                    $timeLeft = strtotime($notification['approval_expires_at']) - time();
                    if ($timeLeft < 7200 && $timeLeft > 0):
                ?>
                    <div class="expires-warning">
                        ⚠️ האישור יפוג בעוד <?php echo round($timeLeft / 60); ?> דקות
                    </div>
                <?php endif; } ?>

                <div class="action-buttons">
                    <button class="btn btn-approve" onclick="handleApprove()" id="btnApprove">
                        <span>🔐</span>
                        אשר
                    </button>
                    <button class="btn btn-reject" onclick="handleReject()" id="btnReject">
                        <span>✗</span>
                        דחה
                    </button>
                </div>

                <div class="biometric-hint">
                    האישור דורש אימות ביומטרי (טביעת אצבע / Face ID)
                </div>

                <a href="/dashboard/dashboards/cemeteries/" class="close-btn">חזרה לדשבורד</a>
            <?php endif; ?>
        </div>
    </div>

    <div class="loading-overlay" id="loadingOverlay">
        <div class="loading-spinner">
            <div class="spinner"></div>
            <div>מאמת...</div>
        </div>
    </div>

    <script src="/js/biometric-auth.js"></script>
    <script src="/dashboard/dashboards/cemeteries/popup/popup-api.js"></script>
    <script>
        const notificationId = <?php echo $notificationId; ?>;
        const apiUrl = '/dashboard/dashboards/cemeteries/notifications/api/approval-api.php';
        let biometricAvailable = false;
        let userHasBiometric = false;

        // Close the popup/iframe
        function closeWindow() {
            console.log('[Approve] closeWindow called');

            // Method 1: Try parent's PopupManager directly
            try {
                if (window.top && window.top.PopupManager) {
                    console.log('[Approve] Using top.PopupManager.closeAll()');
                    window.top.PopupManager.closeAll();
                    return;
                }
            } catch(e) {
                console.log('[Approve] top.PopupManager error:', e);
            }

            try {
                if (window.parent && window.parent.PopupManager) {
                    console.log('[Approve] Using parent.PopupManager.closeAll()');
                    window.parent.PopupManager.closeAll();
                    return;
                }
            } catch(e) {
                console.log('[Approve] parent.PopupManager error:', e);
            }

            // Method 2: Find and click close button in parent
            try {
                const parentDoc = window.top.document || window.parent.document;
                const closeBtn = parentDoc.querySelector('.popup-header-btn.close');
                if (closeBtn) {
                    console.log('[Approve] Found close button, clicking');
                    closeBtn.click();
                    return;
                }
            } catch(e) {
                console.log('[Approve] Cannot find close button:', e);
            }

            // Method 3: Hide the popup container directly
            try {
                const parentDoc = window.top.document || window.parent.document;
                const popupOverlay = parentDoc.querySelector('.popup-overlay');
                if (popupOverlay) {
                    console.log('[Approve] Found popup overlay, hiding');
                    popupOverlay.remove();
                    return;
                }
            } catch(e) {
                console.log('[Approve] Cannot find popup overlay:', e);
            }

            // Method 4: Navigate back or to dashboard
            console.log('[Approve] Fallback: navigating back');
            if (window.history.length > 1) {
                window.history.back();
            } else {
                window.location.href = '/dashboard/dashboards/cemeteries/';
            }
        }

        // Allow closing with Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                const isResponded = <?php echo ($alreadyResponded || $isExpired) ? 'true' : 'false'; ?>;
                if (isResponded) {
                    closeWindow();
                }
            }
        });

        // Check biometric availability on load
        async function checkBiometricSupport() {
            if (typeof window.biometricAuth !== 'undefined' && window.biometricAuth.isSupported) {
                biometricAvailable = true;

                // Check if user has registered biometric credentials
                try {
                    userHasBiometric = await window.biometricAuth.userHasBiometric();
                } catch (e) {
                    console.warn('[Approval] Could not check biometric status:', e);
                }

                console.log('[Approval] Biometric available:', biometricAvailable, 'User has biometric:', userHasBiometric);

                // Update UI hint
                const hint = document.querySelector('.biometric-hint');
                if (hint) {
                    if (userHasBiometric) {
                        hint.textContent = 'לחץ לאישור עם טביעת אצבע / Face ID';
                    } else if (biometricAvailable) {
                        hint.textContent = 'לאישור ביומטרי, יש להגדיר קודם באזור האישי';
                    } else {
                        hint.textContent = 'אימות ביומטרי אינו נתמך במכשיר זה';
                    }
                }
            }
        }

        function showLoading(show) {
            document.getElementById('loadingOverlay').style.display = show ? 'flex' : 'none';
        }

        function setButtonsDisabled(disabled) {
            const btnApprove = document.getElementById('btnApprove');
            const btnReject = document.getElementById('btnReject');
            if (btnApprove) btnApprove.disabled = disabled;
            if (btnReject) btnReject.disabled = disabled;
        }

        async function handleApprove() {
            showLoading(true);
            setButtonsDisabled(true);

            try {
                let biometricVerified = false;

                // Try biometric authentication if available and user has credentials
                if (biometricAvailable && userHasBiometric) {
                    try {
                        console.log('[Approval] Attempting biometric auth...');
                        const authResult = await window.biometricAuth.authenticate();

                        if (authResult.success) {
                            biometricVerified = true;
                            console.log('[Approval] Biometric auth succeeded');
                        } else {
                            // User cancelled or failed - ask if they want to continue without
                            const continueWithout = confirm('האימות הביומטרי נכשל או בוטל.\n\nהאם להמשיך ולאשר ללא אימות ביומטרי?');
                            if (!continueWithout) {
                                showLoading(false);
                                setButtonsDisabled(false);
                                return;
                            }
                        }
                    } catch (bioError) {
                        console.warn('[Approval] Biometric auth error:', bioError);
                        // Ask if want to continue
                        const continueWithout = confirm('שגיאה באימות ביומטרי.\n\nהאם להמשיך ולאשר ללא אימות ביומטרי?');
                        if (!continueWithout) {
                            showLoading(false);
                            setButtonsDisabled(false);
                            return;
                        }
                    }
                }

                // Send approval to server
                const response = await fetch(apiUrl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    credentials: 'include',
                    body: JSON.stringify({
                        action: 'respond',
                        notification_id: notificationId,
                        response: 'approved',
                        biometric_verified: biometricVerified
                    })
                });

                const result = await response.json();

                if (result.success) {
                    // Reload to show updated status
                    window.location.reload();
                } else {
                    alert(result.error || 'שגיאה באישור');
                    setButtonsDisabled(false);
                }
            } catch (error) {
                console.error('Approve error:', error);
                alert('שגיאה באישור: ' + error.message);
                setButtonsDisabled(false);
            }

            showLoading(false);
        }

        async function handleReject() {
            if (!confirm('האם אתה בטוח שברצונך לדחות בקשה זו?')) {
                return;
            }

            showLoading(true);
            setButtonsDisabled(true);

            try {
                const response = await fetch(apiUrl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    credentials: 'include',
                    body: JSON.stringify({
                        action: 'respond',
                        notification_id: notificationId,
                        response: 'rejected',
                        biometric_verified: false
                    })
                });

                const result = await response.json();

                if (result.success) {
                    window.location.reload();
                } else {
                    alert(result.error || 'שגיאה בדחייה');
                    setButtonsDisabled(false);
                }
            } catch (error) {
                console.error('Reject error:', error);
                alert('שגיאה: ' + error.message);
                setButtonsDisabled(false);
            }

            showLoading(false);
        }

        // Initialize on page load
        document.addEventListener('DOMContentLoaded', checkBiometricSupport);
    </script>
</body>
</html>
