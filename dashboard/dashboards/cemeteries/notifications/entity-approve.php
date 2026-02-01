<?php
/**
 * Entity Approval Page
 * Shows pending entity operation details and allows authorizers to approve/reject
 *
 * @version 1.0.0
 */

require_once $_SERVER['DOCUMENT_ROOT'] . '/dashboard/dashboards/cemeteries/config.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/auth/token-init.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/dashboard/dashboards/cemeteries/api/services/EntityApprovalService.php';

// User must be logged in
if (!isLoggedIn()) {
    header('Location: /auth/login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
    exit;
}

$pdo = getDBConnection();
$userId = getCurrentUserId();
$pendingId = (int)($_GET['id'] ?? 0);

if (!$pendingId) {
    die('בקשה לא תקינה');
}

$approvalService = EntityApprovalService::getInstance($pdo);

// Get the pending operation
$pending = $approvalService->getPendingById($pendingId);

if (!$pending) {
    die('הפעולה הממתינה לא נמצאה');
}

// Check if user is an authorizer for this entity/action
$isAuthorizer = $approvalService->isAuthorizer($userId, $pending['entity_type'], $pending['action']);

if (!$isAuthorizer) {
    die('אינך מורשה לאשר פעולה זו');
}

// Get approval status for this user
$stmt = $pdo->prepare("
    SELECT * FROM pending_operation_approvals
    WHERE pending_id = ? AND user_id = ?
");
$stmt->execute([$pendingId, $userId]);
$myApproval = $stmt->fetch(PDO::FETCH_ASSOC);

// Get all approvals for this operation
$stmt = $pdo->prepare("
    SELECT poa.*, u.name as user_name
    FROM pending_operation_approvals poa
    JOIN users u ON poa.user_id = u.id
    WHERE poa.pending_id = ?
    ORDER BY poa.created_at
");
$stmt->execute([$pendingId]);
$allApprovals = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Check if expired
$isExpired = $pending['expires_at'] && strtotime($pending['expires_at']) < time();
$alreadyResponded = $myApproval && in_array($myApproval['status'], ['approved', 'rejected']);
$alreadyCompleted = $pending['status'] !== 'pending';

// Entity labels
$entityLabels = [
    'purchases' => 'רכישה',
    'burials' => 'קבורה',
    'customers' => 'לקוח'
];

$actionLabels = [
    'create' => 'יצירת',
    'edit' => 'עריכת',
    'delete' => 'מחיקת'
];

$entityLabel = $entityLabels[$pending['entity_type']] ?? $pending['entity_type'];
$actionLabel = $actionLabels[$pending['action']] ?? $pending['action'];

// Parse operation data
$operationData = json_decode($pending['operation_data'], true) ?? [];
$originalData = json_decode($pending['original_data'], true) ?? [];

// Load user settings for theme
require_once $_SERVER['DOCUMENT_ROOT'] . '/dashboard/dashboards/cemeteries/user-settings/api/UserSettingsManager.php';

function detectDeviceType() {
    if (isset($_COOKIE['deviceType']) && in_array($_COOKIE['deviceType'], ['mobile', 'desktop'])) {
        return $_COOKIE['deviceType'];
    }
    return 'desktop';
}

$userSettingsManager = new UserSettingsManager($pdo, $userId, detectDeviceType());
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
    <title>אישור פעולה - <?php echo DASHBOARD_NAME; ?></title>
    <link rel="icon" href="data:,">
    <link rel="stylesheet" href="/dashboard/dashboards/cemeteries/css/main.css">
    <link rel="stylesheet" href="/dashboard/dashboards/cemeteries/css/user-preferences.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: var(--bg-secondary);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .approval-card {
            background: var(--bg-primary);
            border-radius: 16px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.15);
            max-width: 600px;
            width: 100%;
            overflow: hidden;
        }

        .card-header {
            background: linear-gradient(135deg, var(--color-primary) 0%, var(--color-primary-dark) 100%);
            padding: 24px 30px;
            color: white;
        }

        .card-header h1 {
            font-size: 1.25rem;
            font-weight: 600;
            margin-bottom: 4px;
        }

        .card-header .subtitle {
            font-size: 14px;
            opacity: 0.9;
        }

        .card-body {
            padding: 24px 30px;
        }

        .status-badge {
            display: inline-block;
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 500;
            margin-bottom: 20px;
        }

        .status-pending { background: #fef3c7; color: #92400e; }
        .status-approved { background: #dcfce7; color: #166534; }
        .status-rejected { background: #fee2e2; color: #991b1b; }
        .status-expired { background: #e2e8f0; color: #475569; }

        .section {
            margin-bottom: 24px;
        }

        .section-title {
            font-size: 14px;
            font-weight: 600;
            color: var(--text-secondary);
            margin-bottom: 12px;
            text-transform: uppercase;
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 12px;
        }

        .info-item {
            background: var(--bg-secondary);
            padding: 12px 16px;
            border-radius: 10px;
        }

        .info-item.full {
            grid-column: 1 / -1;
        }

        .info-label {
            font-size: 12px;
            color: var(--text-secondary);
            margin-bottom: 4px;
        }

        .info-value {
            font-size: 14px;
            font-weight: 500;
            color: var(--text-primary);
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 14px;
        }

        .data-table th,
        .data-table td {
            padding: 10px 12px;
            text-align: right;
            border-bottom: 1px solid var(--border-color);
        }

        .data-table th {
            background: var(--bg-secondary);
            font-weight: 600;
            color: var(--text-secondary);
            font-size: 12px;
        }

        .data-table tr:last-child td {
            border-bottom: none;
        }

        .change-indicator {
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }

        .old-value {
            text-decoration: line-through;
            color: #dc2626;
            font-size: 12px;
        }

        .new-value {
            color: #16a34a;
        }

        .approvals-list {
            list-style: none;
        }

        .approval-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 10px 0;
            border-bottom: 1px solid var(--border-color);
        }

        .approval-item:last-child {
            border-bottom: none;
        }

        .approval-avatar {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: var(--color-primary);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 14px;
        }

        .approval-info {
            flex: 1;
        }

        .approval-name {
            font-weight: 500;
            color: var(--text-primary);
        }

        .approval-status {
            font-size: 12px;
            color: var(--text-secondary);
        }

        .approval-badge {
            font-size: 11px;
            padding: 4px 8px;
            border-radius: 10px;
        }

        .actions {
            display: flex;
            gap: 12px;
            margin-top: 24px;
        }

        .btn {
            flex: 1;
            padding: 14px 20px;
            border-radius: 10px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            border: none;
            transition: all 0.2s;
        }

        .btn-approve {
            background: #22c55e;
            color: white;
        }

        .btn-approve:hover {
            background: #16a34a;
        }

        .btn-reject {
            background: #ef4444;
            color: white;
        }

        .btn-reject:hover {
            background: #dc2626;
        }

        .btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .rejection-form {
            display: none;
            margin-top: 16px;
        }

        .rejection-form.active {
            display: block;
        }

        .rejection-textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            font-size: 14px;
            resize: vertical;
            min-height: 80px;
            background: var(--bg-primary);
            color: var(--text-primary);
        }

        .rejection-actions {
            display: flex;
            gap: 8px;
            margin-top: 12px;
        }

        .btn-small {
            padding: 10px 16px;
            font-size: 14px;
        }

        .btn-secondary {
            background: var(--bg-secondary);
            color: var(--text-primary);
            border: 1px solid var(--border-color);
        }

        .message-box {
            padding: 16px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-size: 14px;
        }

        .message-success {
            background: #dcfce7;
            color: #166534;
            border: 1px solid #bbf7d0;
        }

        .message-warning {
            background: #fef3c7;
            color: #92400e;
            border: 1px solid #fde68a;
        }

        .message-error {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }

        .biometric-notice {
            font-size: 12px;
            color: var(--text-secondary);
            text-align: center;
            margin-top: 12px;
        }

        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(255,255,255,0.9);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 1000;
        }

        .dark-theme .loading-overlay {
            background: rgba(0,0,0,0.9);
        }

        .spinner {
            width: 40px;
            height: 40px;
            border: 3px solid var(--border-color);
            border-top-color: var(--color-primary);
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        @media (max-width: 500px) {
            .info-grid {
                grid-template-columns: 1fr;
            }

            .actions {
                flex-direction: column;
            }
        }
    </style>
</head>
<body class="<?= implode(' ', $bodyClasses) ?>" data-theme="<?= $isDarkMode ? 'dark' : 'light' ?>">

    <div class="loading-overlay" id="loadingOverlay">
        <div class="spinner"></div>
    </div>

    <div class="approval-card">
        <div class="card-header">
            <h1><?= $actionLabel ?> <?= $entityLabel ?></h1>
            <div class="subtitle">בקשה מ<?= htmlspecialchars($pending['requester_name']) ?></div>
        </div>

        <div class="card-body">
            <!-- Status -->
            <?php
            $statusClass = 'status-pending';
            $statusText = 'ממתין לאישור';
            if ($pending['status'] === 'approved') {
                $statusClass = 'status-approved';
                $statusText = 'אושר';
            } elseif ($pending['status'] === 'rejected') {
                $statusClass = 'status-rejected';
                $statusText = 'נדחה';
            } elseif ($pending['status'] === 'expired' || $isExpired) {
                $statusClass = 'status-expired';
                $statusText = 'פג תוקף';
            }
            ?>
            <span class="status-badge <?= $statusClass ?>"><?= $statusText ?></span>

            <!-- Messages -->
            <?php if ($alreadyResponded): ?>
                <div class="message-box message-success">
                    כבר הגבת לבקשה זו (<?= $myApproval['status'] === 'approved' ? 'אושר' : 'נדחה' ?>)
                </div>
            <?php elseif ($isExpired): ?>
                <div class="message-box message-warning">
                    הבקשה פגה תוקף ואינה ניתנת יותר לאישור
                </div>
            <?php elseif ($alreadyCompleted): ?>
                <div class="message-box message-warning">
                    הבקשה כבר טופלה (<?= $statusText ?>)
                </div>
            <?php endif; ?>

            <!-- Request Info -->
            <div class="section">
                <div class="section-title">פרטי הבקשה</div>
                <div class="info-grid">
                    <div class="info-item">
                        <div class="info-label">תאריך בקשה</div>
                        <div class="info-value"><?= date('d/m/Y H:i', strtotime($pending['created_at'])) ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">תוקף עד</div>
                        <div class="info-value"><?= $pending['expires_at'] ? date('d/m/Y H:i', strtotime($pending['expires_at'])) : 'ללא הגבלה' ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">אישורים נדרשים</div>
                        <div class="info-value"><?= $pending['current_approvals'] ?> / <?= $pending['required_approvals'] ?></div>
                    </div>
                    <?php if ($pending['action'] !== 'create'): ?>
                    <div class="info-item">
                        <div class="info-label">מזהה ישות</div>
                        <div class="info-value"><?= htmlspecialchars($pending['entity_id'] ?? '-') ?></div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Operation Data -->
            <div class="section">
                <div class="section-title">נתוני הפעולה</div>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>שדה</th>
                            <th>ערך</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        // Display relevant fields based on entity type
                        $displayFields = [];

                        if ($pending['entity_type'] === 'purchases') {
                            $displayFields = [
                                'clientId' => 'לקוח',
                                'graveId' => 'קבר',
                                'price' => 'מחיר',
                                'numOfPayments' => 'מספר תשלומים',
                                'purchaseStatus' => 'סטטוס',
                                'comment' => 'הערה'
                            ];
                        } elseif ($pending['entity_type'] === 'burials') {
                            $displayFields = [
                                'clientId' => 'נפטר',
                                'graveId' => 'קבר',
                                'dateDeath' => 'תאריך פטירה',
                                'dateBurial' => 'תאריך קבורה',
                                'timeBurial' => 'שעת קבורה',
                                'placeDeath' => 'מקום פטירה',
                                'comment' => 'הערה'
                            ];
                        } elseif ($pending['entity_type'] === 'customers') {
                            $displayFields = [
                                'firstName' => 'שם פרטי',
                                'lastName' => 'שם משפחה',
                                'numId' => 'ת.ז.',
                                'phone' => 'טלפון',
                                'phoneMobile' => 'נייד',
                                'comment' => 'הערה'
                            ];
                        }

                        foreach ($displayFields as $field => $label):
                            $newValue = $operationData[$field] ?? '';
                            $oldValue = $originalData[$field] ?? '';
                            $hasChange = $pending['action'] === 'edit' && $oldValue != $newValue && !empty($originalData);
                        ?>
                        <tr>
                            <td><?= $label ?></td>
                            <td>
                                <?php if ($hasChange): ?>
                                    <div class="change-indicator">
                                        <span class="old-value"><?= htmlspecialchars($oldValue ?: '-') ?></span>
                                        <span>&rarr;</span>
                                        <span class="new-value"><?= htmlspecialchars($newValue ?: '-') ?></span>
                                    </div>
                                <?php else: ?>
                                    <?= htmlspecialchars($newValue ?: '-') ?>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Approvals Status -->
            <div class="section">
                <div class="section-title">סטטוס אישורים</div>
                <ul class="approvals-list">
                    <?php foreach ($allApprovals as $approvalItem): ?>
                    <li class="approval-item">
                        <div class="approval-avatar">
                            <?= mb_substr($approvalItem['user_name'], 0, 1, 'UTF-8') ?>
                        </div>
                        <div class="approval-info">
                            <div class="approval-name">
                                <?= htmlspecialchars($approvalItem['user_name']) ?>
                                <?php if ($approvalItem['is_mandatory']): ?>
                                    <span style="color: #dc2626; font-size: 12px;">(חובה)</span>
                                <?php endif; ?>
                            </div>
                            <div class="approval-status">
                                <?php if ($approvalItem['status'] === 'approved'): ?>
                                    אישר ב-<?= date('d/m/Y H:i', strtotime($approvalItem['responded_at'])) ?>
                                    <?php if ($approvalItem['biometric_verified']): ?>
                                        <span style="color: #22c55e;">(מאומת ביומטרית)</span>
                                    <?php endif; ?>
                                <?php elseif ($approvalItem['status'] === 'rejected'): ?>
                                    דחה ב-<?= date('d/m/Y H:i', strtotime($approvalItem['responded_at'])) ?>
                                <?php else: ?>
                                    ממתין לתגובה
                                <?php endif; ?>
                            </div>
                        </div>
                        <span class="approval-badge status-<?= $approvalItem['status'] ?>">
                            <?= $approvalItem['status'] === 'approved' ? 'אושר' : ($approvalItem['status'] === 'rejected' ? 'נדחה' : 'ממתין') ?>
                        </span>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>

            <?php if ($pending['rejection_reason']): ?>
            <div class="section">
                <div class="section-title">סיבת דחייה</div>
                <div class="info-item full">
                    <?= htmlspecialchars($pending['rejection_reason']) ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Actions -->
            <?php if (!$alreadyResponded && !$isExpired && !$alreadyCompleted): ?>
            <div class="actions">
                <button type="button" class="btn btn-approve" id="approveBtn" onclick="handleApprove()">
                    אישור
                </button>
                <button type="button" class="btn btn-reject" id="rejectBtn" onclick="showRejectForm()">
                    דחייה
                </button>
            </div>

            <div class="rejection-form" id="rejectionForm">
                <textarea class="rejection-textarea" id="rejectionReason" placeholder="סיבת הדחייה (אופציונלי)"></textarea>
                <div class="rejection-actions">
                    <button type="button" class="btn btn-small btn-reject" onclick="handleReject()">
                        שלח דחייה
                    </button>
                    <button type="button" class="btn btn-small btn-secondary" onclick="hideRejectForm()">
                        ביטול
                    </button>
                </div>
            </div>

            <div class="biometric-notice">
                ניתן לאמת באמצעות טביעת אצבע או זיהוי פנים לאבטחה נוספת
            </div>
            <?php endif; ?>
        </div>
    </div>

    <script src="/js/biometric-auth.js"></script>
    <script>
        const pendingId = <?= $pendingId ?>;

        function showLoading(show) {
            document.getElementById('loadingOverlay').style.display = show ? 'flex' : 'none';
        }

        function showRejectForm() {
            document.getElementById('rejectionForm').classList.add('active');
            document.getElementById('rejectBtn').style.display = 'none';
        }

        function hideRejectForm() {
            document.getElementById('rejectionForm').classList.remove('active');
            document.getElementById('rejectBtn').style.display = '';
        }

        // Check if running inside iframe (embed mode)
        const isEmbedMode = new URLSearchParams(window.location.search).has('embed') || window.parent !== window;

        // Notify parent window (for iframe embed mode)
        function notifyParent(status) {
            if (isEmbedMode && window.parent !== window) {
                window.parent.postMessage({
                    type: 'entityApprovalComplete',
                    status: status,
                    pendingId: pendingId
                }, '*');
            }
        }

        async function handleApprove() {
            if (!confirm('האם אתה בטוח שברצונך לאשר פעולה זו?')) {
                return;
            }

            showLoading(true);

            // Try biometric authentication first
            let biometricVerified = false;
            if (window.BiometricAuth && await window.BiometricAuth.isAvailable()) {
                try {
                    const result = await window.BiometricAuth.authenticate('אימות לאישור פעולה');
                    biometricVerified = result.success;
                } catch (e) {
                    console.log('Biometric auth skipped:', e);
                }
            }

            try {
                const response = await fetch('/dashboard/dashboards/cemeteries/api/entity-approval-api.php?action=approve', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        pendingId: pendingId,
                        biometric_verified: biometricVerified
                    })
                });

                const data = await response.json();

                if (data.success) {
                    if (isEmbedMode) {
                        // In iframe mode - notify parent
                        notifyParent('approved');
                    } else {
                        // Standalone mode - show message and reload
                        alert(data.complete ? 'הפעולה אושרה ובוצעה בהצלחה!' : 'האישור נרשם בהצלחה');
                        location.reload();
                    }
                } else {
                    alert('שגיאה: ' + (data.error || 'לא ניתן לאשר'));
                }
            } catch (error) {
                console.error('Error:', error);
                alert('שגיאה בעת האישור');
            } finally {
                showLoading(false);
            }
        }

        async function handleReject() {
            const reason = document.getElementById('rejectionReason').value;

            if (!confirm('האם אתה בטוח שברצונך לדחות פעולה זו?')) {
                return;
            }

            showLoading(true);

            try {
                const response = await fetch('/dashboard/dashboards/cemeteries/api/entity-approval-api.php?action=reject', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        pendingId: pendingId,
                        reason: reason
                    })
                });

                const data = await response.json();

                if (data.success) {
                    if (isEmbedMode) {
                        // In iframe mode - notify parent
                        notifyParent('rejected');
                    } else {
                        // Standalone mode - show message and reload
                        alert('הפעולה נדחתה');
                        location.reload();
                    }
                } else {
                    alert('שגיאה: ' + (data.error || 'לא ניתן לדחות'));
                }
            } catch (error) {
                console.error('Error:', error);
                alert('שגיאה בעת הדחייה');
            } finally {
                showLoading(false);
            }
        }
    </script>
</body>
</html>
