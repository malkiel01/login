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

            // Mark the related push notification as read
            markEntityApprovalNotificationAsRead($pdo, $pendingId, $userId);

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

            // Mark the related push notification as read
            markEntityApprovalNotificationAsRead($pdo, $pendingId, $userId);

            echo json_encode($result);
            break;

        case 'cancel':
            // Cancel own pending operation
            $pendingId = (int)($postData['pendingId'] ?? 0);

            if (!$pendingId) {
                throw new Exception('מזהה חסר');
            }

            $result = $service->cancelOperation($pendingId, $userId);

            echo json_encode($result);
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
