<?php
/**
 * Notification Approval Page
 * Shows notification details and allows user to approve/reject with biometric auth
 *
 * @version 1.0.0
 */

require_once $_SERVER['DOCUMENT_ROOT'] . '/dashboard/dashboards/cemeteries/config.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/auth/token-init.php';

// User must be logged in
if (!isLoggedIn()) {
    header('Location: /auth/login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
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

        .approval-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.2);
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
            color: #1e293b;
            margin-bottom: 12px;
        }

        .notification-body {
            font-size: 15px;
            color: #475569;
            line-height: 1.6;
            margin-bottom: 20px;
            padding: 16px;
            background: #f8fafc;
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
            color: #64748b;
            margin-bottom: 24px;
        }

        .meta-info .item {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 6px;
        }

        .expires-warning {
            color: #dc2626;
            background: #fef2f2;
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
            background: #f1f5f9;
            color: #64748b;
        }

        .btn-reject:hover {
            background: #e2e8f0;
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
            background: white;
            padding: 30px 40px;
            border-radius: 16px;
            text-align: center;
        }

        .spinner {
            width: 40px;
            height: 40px;
            border: 3px solid #e2e8f0;
            border-top-color: #667eea;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin: 0 auto 12px;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        .biometric-hint {
            font-size: 12px;
            color: #64748b;
            text-align: center;
            margin-top: 16px;
        }

        .close-btn {
            display: block;
            text-align: center;
            margin-top: 20px;
            color: #64748b;
            text-decoration: none;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <div class="approval-card">
        <div class="card-header">
            <div class="icon">🔐</div>
            <h1>בקשת אישור</h1>
            <div class="subtitle">נשלח על ידי <?php echo htmlspecialchars($notification['creator_name'] ?? 'מנהל'); ?></div>
        </div>

        <div class="card-body">
            <?php if ($alreadyResponded): ?>
                <div class="status-message status-<?php echo $approval['status']; ?>">
                    <div class="status-icon">
                        <?php echo $approval['status'] === 'approved' ? '✓' : '✗'; ?>
                    </div>
                    <strong>
                        <?php
                        echo $approval['status'] === 'approved'
                            ? 'אישרת בקשה זו'
                            : 'דחית בקשה זו';
                        ?>
                    </strong>
                    <?php if ($approval['responded_at']): ?>
                        <div style="margin-top: 8px; font-size: 13px;">
                            ב-<?php echo date('d/m/Y H:i', strtotime($approval['responded_at'])); ?>
                        </div>
                    <?php endif; ?>
                </div>

            <?php elseif ($isExpired): ?>
                <div class="status-message status-expired">
                    <div class="status-icon">⏰</div>
                    <strong>פג תוקף האישור</strong>
                    <div style="margin-top: 8px; font-size: 13px;">
                        ב-<?php echo date('d/m/Y H:i', strtotime($notification['approval_expires_at'])); ?>
                    </div>
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
            <?php endif; ?>

            <a href="/dashboard/dashboards/cemeteries/" class="close-btn">חזרה לדשבורד</a>
        </div>
    </div>

    <div class="loading-overlay" id="loadingOverlay">
        <div class="loading-spinner">
            <div class="spinner"></div>
            <div>מאמת...</div>
        </div>
    </div>

    <script src="/js/biometric-auth.js"></script>
    <script>
        const notificationId = <?php echo $notificationId; ?>;
        const apiUrl = '/dashboard/dashboards/cemeteries/notifications/api/approval-api.php';
        let biometricAvailable = false;
        let userHasBiometric = false;

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
