<?php
/**
 * Entity Approval API
 * Handles approval workflow for entity operations
 *
 * @version 1.0.0
 */

require_once __DIR__ . '/api-auth.php';
require_once __DIR__ . '/services/EntityApprovalService.php';

header('Content-Type: application/json; charset=utf-8');

$action = $_GET['action'] ?? $_POST['action'] ?? '';
$postData = json_decode(file_get_contents('php://input'), true) ?? [];

try {
    $pdo = getDBConnection();
    $service = EntityApprovalService::getInstance($pdo);
    $userId = getCurrentUserId();

    switch ($action) {
        // ========================================
        // Settings Management
        // ========================================

        case 'getSettings':
            // Get approval settings for a user
            $targetUserId = (int)($_GET['userId'] ?? $userId);

            // Only admin or the user themselves can view settings
            if ($targetUserId !== $userId && !isAdmin()) {
                throw new Exception('אין לך הרשאה לצפות בהגדרות אלו');
            }

            $settings = $service->getUserApprovalSettings($targetUserId);

            echo json_encode([
                'success' => true,
                'data' => $settings
            ]);
            break;

        case 'saveSettings':
            // Save approval settings for a user
            requireApiModulePermission('users', 'edit');

            $targetUserId = (int)($postData['userId'] ?? 0);
            $settings = $postData['settings'] ?? [];

            if (!$targetUserId || empty($settings)) {
                throw new Exception('נתונים חסרים');
            }

            $service->saveUserApprovalSettings($targetUserId, $settings);

            echo json_encode([
                'success' => true,
                'message' => 'ההגדרות נשמרו בהצלחה'
            ]);
            break;

        case 'getRules':
            // Get global approval rules
            requireApiModulePermission('settings', 'view');

            $rules = $service->getAllApprovalRules();

            echo json_encode([
                'success' => true,
                'data' => $rules
            ]);
            break;

        case 'saveRules':
            // Save global approval rules
            requireApiModulePermission('settings', 'edit');

            $rules = $postData['rules'] ?? [];
            if (empty($rules)) {
                throw new Exception('נתונים חסרים');
            }

            $service->saveApprovalRules($rules);

            echo json_encode([
                'success' => true,
                'message' => 'החוקים נשמרו בהצלחה'
            ]);
            break;

        // ========================================
        // Pending Operations
        // ========================================

        case 'listPending':
            // List pending operations
            $entityType = $_GET['entityType'] ?? null;
            $status = $_GET['status'] ?? 'pending';

            $sql = "
                SELECT peo.*, u.name as requester_name,
                       (SELECT COUNT(*) FROM pending_operation_approvals WHERE pending_id = peo.id AND status = 'approved') as approved_count,
                       (SELECT COUNT(*) FROM pending_operation_approvals WHERE pending_id = peo.id AND status = 'rejected') as rejected_count
                FROM pending_entity_operations peo
                JOIN users u ON peo.requested_by = u.id
                WHERE peo.status = ?
            ";
            $params = [$status];

            if ($entityType) {
                $sql .= " AND peo.entity_type = ?";
                $params[] = $entityType;
            }

            $sql .= " ORDER BY peo.created_at DESC LIMIT 100";

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $pending = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode([
                'success' => true,
                'data' => $pending
            ]);
            break;

        case 'myPending':
            // Get my pending operations (as requester)
            $pending = $service->getUserPendingOperations($userId);

            echo json_encode([
                'success' => true,
                'data' => $pending
            ]);
            break;

        case 'awaitingMyApproval':
            // Get operations awaiting my approval
            $pending = $service->getAwaitingMyApproval($userId);

            echo json_encode([
                'success' => true,
                'data' => $pending
            ]);
            break;

        case 'getPending':
            // Get single pending operation details
            $pendingId = (int)($_GET['id'] ?? 0);
            if (!$pendingId) {
                throw new Exception('מזהה חסר');
            }

            $pending = $service->getPendingById($pendingId);
            if (!$pending) {
                throw new Exception('פעולה ממתינה לא נמצאה');
            }

            // Get approvals
            $stmt = $pdo->prepare("
                SELECT poa.*, u.name as user_name
                FROM pending_operation_approvals poa
                JOIN users u ON poa.user_id = u.id
                WHERE poa.pending_id = ?
                ORDER BY poa.created_at
            ");
            $stmt->execute([$pendingId]);
            $pending['approvals'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode([
                'success' => true,
                'data' => $pending
            ]);
            break;

        case 'getPendingForEntity':
            // Check if entity has pending operation
            $entityType = $_GET['entityType'] ?? '';
            $entityId = $_GET['entityId'] ?? '';

            if (!$entityType || !$entityId) {
                throw new Exception('נתונים חסרים');
            }

            $pending = $service->getPendingForEntity($entityType, $entityId);

            echo json_encode([
                'success' => true,
                'data' => $pending
            ]);
            break;

        // ========================================
        // Approval Actions
        // ========================================

        case 'approve':
            // Approve a pending operation
            $pendingId = (int)($postData['pendingId'] ?? 0);
            $biometricVerified = !empty($postData['biometric_verified']);

            if (!$pendingId) {
                throw new Exception('מזהה חסר');
            }

            $result = $service->recordApproval($pendingId, $userId, $biometricVerified);

            // Mark the related push notification as read for THIS user
            markEntityApprovalNotificationAsRead($pdo, $pendingId, $userId);

            // אם הפעולה הושלמה - בטל את כל ההתראות של כל המאשרים
            if ($result['complete'] ?? false) {
                markAllApprovalNotificationsAsRead($pdo, $pendingId);
            }

            echo json_encode($result);
            break;

        case 'reject':
            // Reject a pending operation
            $pendingId = (int)($postData['pendingId'] ?? 0);
            $reason = $postData['reason'] ?? null;

            if (!$pendingId) {
                throw new Exception('מזהה חסר');
            }

            $result = $service->rejectOperation($pendingId, $userId, $reason);

            // דחייה מבטלת את כל ההתראות לכל המאשרים
            markAllApprovalNotificationsAsRead($pdo, $pendingId);

            echo json_encode($result);
            break;

        case 'cancel':
            // Cancel own pending operation
            $pendingId = (int)($postData['pendingId'] ?? 0);

            if (!$pendingId) {
                throw new Exception('מזהה חסר');
            }

            $result = $service->cancelOperation($pendingId, $userId);

            // ביטול מבטל את כל ההתראות לכל המאשרים
            markAllApprovalNotificationsAsRead($pdo, $pendingId);

            echo json_encode($result);
            break;

        case 'resendNotification':
            // שליחה חוזרת של בקשת אישור
            $pendingId = (int)($postData['pendingId'] ?? 0);

            if (!$pendingId) {
                throw new Exception('מזהה חסר');
            }

            // בדיקה שהמשתמש הוא מי שיצר את הבקשה
            $pending = $service->getPendingById($pendingId);
            if (!$pending) {
                throw new Exception('בקשה לא נמצאה');
            }

            if ($pending['requested_by'] != $userId && !isAdmin()) {
                throw new Exception('אין הרשאה לשליחה חוזרת');
            }

            if ($pending['status'] !== 'pending') {
                throw new Exception('הבקשה כבר טופלה');
            }

            // שליחה חוזרת של ההתראות
            $result = $service->resendNotifications($pendingId);

            echo json_encode([
                'success' => true,
                'message' => 'בקשת האישור נשלחה מחדש',
                'sent' => $result
            ]);
            break;

        // ========================================
        // Authorizers
        // ========================================

        case 'getAuthorizers':
            // Get authorizers for entity/action
            $entityType = $_GET['entityType'] ?? '';
            $actionType = $_GET['actionType'] ?? '';

            if (!$entityType || !$actionType) {
                throw new Exception('נתונים חסרים');
            }

            $authorizers = $service->getAuthorizers($entityType, $actionType);

            echo json_encode([
                'success' => true,
                'data' => $authorizers
            ]);
            break;

        // ========================================
        // History
        // ========================================

        case 'listHistory':
            // List historical approval operations (approved, rejected, expired, cancelled)
            $entityType = $_GET['entityType'] ?? null;
            $status = $_GET['status'] ?? null; // approved, rejected, expired, cancelled, or null for all
            $dateFrom = $_GET['dateFrom'] ?? null;
            $dateTo = $_GET['dateTo'] ?? null;
            $limit = min((int)($_GET['limit'] ?? 50), 200);
            $offset = (int)($_GET['offset'] ?? 0);

            $sql = "
                SELECT peo.*,
                       u.name as requester_name,
                       (SELECT GROUP_CONCAT(CONCAT(u2.name, ':', poa.status) SEPARATOR ', ')
                        FROM pending_operation_approvals poa
                        JOIN users u2 ON poa.user_id = u2.id
                        WHERE poa.pending_id = peo.id AND poa.status != 'pending'
                       ) as approvers_summary
                FROM pending_entity_operations peo
                JOIN users u ON peo.requested_by = u.id
                WHERE peo.status != 'pending'
            ";
            $params = [];

            if ($status) {
                $sql .= " AND peo.status = ?";
                $params[] = $status;
            }

            if ($entityType) {
                $sql .= " AND peo.entity_type = ?";
                $params[] = $entityType;
            }

            if ($dateFrom) {
                $sql .= " AND DATE(peo.created_at) >= ?";
                $params[] = $dateFrom;
            }

            if ($dateTo) {
                $sql .= " AND DATE(peo.created_at) <= ?";
                $params[] = $dateTo;
            }

            // Get total count
            $countSql = str_replace("SELECT peo.*, ", "SELECT COUNT(*) as total ", $sql);
            $countSql = preg_replace('/,\s*\(SELECT GROUP_CONCAT.*?\) as approvers_summary/', '', $countSql);
            $countStmt = $pdo->prepare($countSql);
            $countStmt->execute($params);
            $total = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];

            $sql .= " ORDER BY peo.completed_at DESC, peo.created_at DESC LIMIT ? OFFSET ?";
            $params[] = $limit;
            $params[] = $offset;

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $history = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode([
                'success' => true,
                'data' => $history,
                'pagination' => [
                    'total' => (int)$total,
                    'limit' => $limit,
                    'offset' => $offset
                ]
            ]);
            break;

        // ========================================
        // Status Check
        // ========================================

        case 'checkApproval':
            // Check if user needs approval for action
            $entityType = $_GET['entityType'] ?? '';
            $actionType = $_GET['actionType'] ?? '';
            $targetUserId = (int)($_GET['userId'] ?? $userId);

            if (!$entityType || !$actionType) {
                throw new Exception('נתונים חסרים');
            }

            $needsApproval = $service->userNeedsApproval($targetUserId, $entityType, $actionType);
            $isAuthorizer = $service->isAuthorizer($targetUserId, $entityType, $actionType);

            echo json_encode([
                'success' => true,
                'needsApproval' => $needsApproval,
                'isAuthorizer' => $isAuthorizer
            ]);
            break;

        default:
            throw new Exception('פעולה לא מוכרת: ' . $action);
    }

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

/**
 * Mark the push notification related to an entity approval as read
 * This moves the notification from "new" to "history" in my-notifications
 */
function markEntityApprovalNotificationAsRead(PDO $pdo, int $pendingId, int $userId): void {
    // Find the scheduled_notification by URL containing the pending ID
    $urlPattern = "%entity-approve.php?id={$pendingId}%";

    $stmt = $pdo->prepare("
        UPDATE push_notifications pn
        JOIN scheduled_notifications sn ON sn.id = pn.scheduled_notification_id
        SET pn.is_read = 1
        WHERE pn.user_id = ?
          AND pn.is_read = 0
          AND sn.url LIKE ?
    ");
    $stmt->execute([$userId, $urlPattern]);

    $updated = $stmt->rowCount();
    if ($updated > 0) {
        error_log("[EntityApprovalAPI] Marked $updated notification(s) as read for pending $pendingId, user $userId");
    }
}

/**
 * Mark ALL push notifications related to an entity approval as read
 * This is called when the operation is approved, rejected, cancelled, or expired
 * to invalidate notifications for ALL authorizers
 */
function markAllApprovalNotificationsAsRead(PDO $pdo, int $pendingId): void {
    $urlPattern = "%entity-approve.php?id={$pendingId}%";

    // 1. סמן push_notifications שכבר נשלחו כנקראו
    $stmt = $pdo->prepare("
        UPDATE push_notifications pn
        JOIN scheduled_notifications sn ON sn.id = pn.scheduled_notification_id
        SET pn.is_read = 1
        WHERE pn.is_read = 0
          AND sn.url LIKE ?
    ");
    $stmt->execute([$urlPattern]);

    $updated = $stmt->rowCount();
    if ($updated > 0) {
        error_log("[EntityApprovalAPI] Marked ALL $updated notification(s) as read for pending $pendingId");
    }

    // 2. בטל scheduled_notifications שטרם נשלחו (status = 'pending')
    cancelScheduledNotifications($pdo, $pendingId);
}

/**
 * Cancel scheduled notifications that haven't been sent yet
 * This prevents future notifications from being sent for resolved operations
 */
function cancelScheduledNotifications(PDO $pdo, int $pendingId): void {
    $urlPattern = "%entity-approve.php?id={$pendingId}%";

    $stmt = $pdo->prepare("
        UPDATE scheduled_notifications
        SET status = 'cancelled'
        WHERE url LIKE ?
          AND status = 'pending'
    ");
    $stmt->execute([$urlPattern]);

    $cancelled = $stmt->rowCount();
    if ($cancelled > 0) {
        error_log("[EntityApprovalAPI] Cancelled $cancelled scheduled notification(s) for pending $pendingId");
    }
}
