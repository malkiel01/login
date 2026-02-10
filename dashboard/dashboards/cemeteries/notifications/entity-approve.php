<?php
/**
 * Entity Approval Page
 * Shows pending entity operation details and allows authorizers to approve/reject
 *
 * @version 2.1.0 - Handle back button in notification flow
 *
 * Changes in v2.1:
 * - Add history management for back button (prevent app close)
 * - Push trap state and listen for popstate
 *
 * Changes in v2.0:
 * - After approve/reject, redirect to dashboard instead of reload
 * - Continues the notification flow (came_from_notification flags)
 */

require_once $_SERVER['DOCUMENT_ROOT'] . '/dashboard/dashboards/cemeteries/config.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/auth/token-init.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/dashboard/dashboards/cemeteries/api/services/EntityApprovalService.php';

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
// Add embed mode class when loaded in iframe
if (isset($_GET['embed'])) {
    $bodyClasses[] = 'embed-mode';
}
?>
<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <?php
    // צבעים ממרכז העיצוב (user-preferences.css)
    // מצב כהה = אפור, מצב בהיר = סגול/ירוק לפי ערכת הצבע
    if ($isDarkMode) {
        $themeColorPrimary = '#374151';
        $themeColorDark = '#1f2937';
    } else {
        // מצב בהיר - 2 ערכות צבע: סגול (ברירת מחדל) או ירוק
        if ($colorScheme === 'green') {
            $themeColorPrimary = '#059669';
            $themeColorDark = '#047857';
        } else {
            // סגול (ברירת מחדל)
            $themeColorPrimary = '#667eea';
            $themeColorDark = '#764ba2';
        }
    }
    ?>
    <meta name="theme-color" content="<?= $themeColorPrimary ?>">
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
            background: linear-gradient(135deg, <?= $themeColorPrimary ?> 0%, <?= $themeColorDark ?> 100%);
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

        /* Embed mode - full screen, no card styling, sticky buttons */
        body.embed-mode {
            padding: 0 !important;
            margin: 0 !important;
            display: flex !important;
            flex-direction: column !important;
            align-items: stretch !important;
            justify-content: flex-start !important;
            background: var(--bg-primary) !important;
            min-height: 100vh;
        }

        /* Top sticky header for embed mode - MUST BE FIRST */
        .embed-header {
            display: none;
        }

        body.embed-mode .embed-header {
            display: block !important;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 100;
            width: 100%;
            background: linear-gradient(135deg, <?= $themeColorPrimary ?> 0%, <?= $themeColorDark ?> 100%);
            color: white;
            padding: 14px 20px;
            padding-top: calc(14px + env(safe-area-inset-top, 0px));
            font-size: 18px;
            font-weight: 600;
            text-align: center;
            box-sizing: border-box;
        }

        /* Add top padding to approval-card to account for fixed header */
        body.embed-mode .approval-card {
            margin-top: calc(46px + env(safe-area-inset-top, 0px));
        }

        body.embed-mode .approval-card {
            max-width: none;
            border-radius: 0;
            box-shadow: none;
            flex: 1;
            display: flex;
            flex-direction: column;
        }

        body.embed-mode .card-header {
            padding: 16px 20px;
            background: var(--bg-secondary, #f1f5f9);
            color: var(--text-primary, #1e293b);
        }

        body.embed-mode .card-header h1 {
            color: var(--text-primary, #1e293b);
        }

        body.embed-mode .card-header .subtitle {
            color: var(--text-secondary, #475569);
            opacity: 1;
        }

        body.embed-mode .card-body {
            flex: 1;
            overflow-y: auto;
            padding: 16px 20px;
            padding-bottom: 140px; /* Space for fixed buttons + biometric notice */
        }

        body.embed-mode .actions {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            margin: 0;
            padding: 16px 20px;
            background: var(--bg-primary);
            border-top: 1px solid var(--border-color);
            box-shadow: 0 -4px 20px rgba(0, 0, 0, 0.1);
            z-index: 100;
            flex-direction: row !important; /* Always in one row */
        }

        body.embed-mode .biometric-notice {
            position: fixed;
            bottom: 70px;
            left: 0;
            right: 0;
            background: var(--bg-primary);
            padding: 8px 20px;
            z-index: 99;
        }

        body.embed-mode .rejection-form {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            padding: 16px 20px;
            background: var(--bg-primary);
            border-top: 1px solid var(--border-color);
            box-shadow: 0 -4px 20px rgba(0, 0, 0, 0.1);
            z-index: 101;
        }

        /* Result message - covers entire screen */
        .result-message {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-align: center;
            padding: 20px;
            z-index: 9999;
            animation: fadeIn 0.3s ease;
        }

        .result-message.success {
            background: linear-gradient(135deg, #22c55e 0%, #16a34a 100%);
            color: white;
        }

        .result-message.rejected {
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
            color: white;
        }

        .result-icon {
            font-size: 80px;
            margin-bottom: 24px;
            animation: scaleIn 0.4s ease;
        }

        .result-text {
            font-size: 24px;
            font-weight: 600;
            line-height: 1.4;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        @keyframes scaleIn {
            from { transform: scale(0); }
            to { transform: scale(1); }
        }
    </style>
</head>
<body class="<?= implode(' ', $bodyClasses) ?>" data-theme="<?= $isDarkMode ? 'dark' : 'light' ?>">

    <div class="loading-overlay" id="loadingOverlay">
        <div class="spinner"></div>
    </div>

    <!-- Sticky header for embed mode -->
    <div class="embed-header">בקשת אישור</div>

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

        // ========== v2.1: HISTORY MANAGEMENT FOR BACK BUTTON ==========
        // This prevents back button from closing the app
        (function() {
            // Check if we came from notification flow
            const cameFromNotification = sessionStorage.getItem('came_from_notification') === 'true';

            if (cameFromNotification) {
                // Push a trap state so back button has somewhere to go
                history.pushState({ approvalTrap: true, pendingId: pendingId }, '', '#approval');

                // Listen for back button
                window.addEventListener('popstate', function(e) {
                    // User pressed back - go to dashboard
                    // Keep the session flags so notification flow continues
                    location.replace('/dashboard/dashboards/cemeteries/');
                });

                console.log('[EntityApprove] History trap set for notification flow');
            }
        })();
        // ========== END HISTORY MANAGEMENT ==========

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

        // Show nice success/error message - full screen overlay
        function showResultMessage(status, message) {
            const isApproved = status === 'approved';

            const overlay = document.createElement('div');
            overlay.className = `result-message ${isApproved ? 'success' : 'rejected'}`;
            overlay.innerHTML = `
                <div class="result-icon">${isApproved ? '✓' : '✗'}</div>
                <div class="result-text">${message}</div>
            `;
            document.body.appendChild(overlay);

            // Wait before closing (give time to read)
            setTimeout(() => {
                if (isEmbedMode) {
                    notifyParent(status);
                } else {
                    // v2: Return to dashboard to continue notification flow
                    // The session storage flags (came_from_notification, notification_next_index)
                    // were already set by notification-view.php before redirecting here
                    location.replace('/dashboard/dashboards/cemeteries/');
                }
            }, 2500);
        }

        async function handleApprove() {
            // Check biometric availability
            if (!window.biometricAuth || !window.biometricAuth.isSupported) {
                alert('נדרש מכשיר התומך באימות ביומטרי');
                return;
            }

            // Check if user has biometric registered
            let hasBiometric = false;
            try {
                hasBiometric = await window.biometricAuth.userHasBiometric();
            } catch (e) {
                console.error('Error checking biometric:', e);
                alert('שגיאה בבדיקת אימות ביומטרי. נסה שוב.');
                return;
            }

            if (!hasBiometric) {
                if (confirm('נדרש אימות ביומטרי לאישור. האם לעבור להגדרות לרישום טביעת אצבע / Face ID?')) {
                    sessionStorage.setItem('pendingEntityApprovalId', pendingId);
                    window.location.href = '/dashboard/dashboards/cemeteries/user-settings/settings-page.php?section=security';
                }
                return;
            }

            // Perform biometric authentication - MANDATORY
            showLoading(true);
            let biometricResult;
            try {
                biometricResult = await window.biometricAuth.authenticate();
            } catch (e) {
                console.error('Biometric error:', e);
                alert('שגיאה באימות הביומטרי: ' + e.message);
                showLoading(false);
                return;
            }

            if (!biometricResult.success) {
                if (biometricResult.userCancelled) {
                    alert('האימות הביומטרי בוטל. יש לאשר עם טביעת אצבע / Face ID');
                } else {
                    alert('האימות הביומטרי נכשל. נסה שוב.');
                }
                showLoading(false);
                return;
            }

            // Biometric verified - proceed with approval
            try {
                const response = await fetch('/dashboard/dashboards/cemeteries/api/entity-approval-api.php?action=approve', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        pendingId: pendingId,
                        biometric_verified: true
                    })
                });

                const data = await response.json();

                if (data.success) {
                    showLoading(false);
                    const message = data.complete ? 'הפעולה אושרה ובוצעה בהצלחה!' : 'האישור נרשם בהצלחה';
                    showResultMessage('approved', message);
                } else {
                    showLoading(false);
                    alert('שגיאה: ' + (data.error || 'לא ניתן לאשר'));
                }
            } catch (error) {
                console.error('Error:', error);
                showLoading(false);
                alert('שגיאה בעת האישור');
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
                    showLoading(false);
                    showResultMessage('rejected', 'הבקשה נדחתה');
                } else {
                    showLoading(false);
                    alert('שגיאה: ' + (data.error || 'לא ניתן לדחות'));
                }
            } catch (error) {
                console.error('Error:', error);
                showLoading(false);
                alert('שגיאה בעת הדחייה');
            }
        }
    </script>
</body>
</html>
