<?php
/**
 * Entity Approval Service
 * Handles approval workflow for entity operations (create/edit/delete)
 *
 * @version 1.0.0
 */

require_once __DIR__ . '/../../notifications/api/NotificationLogger.php';
require_once __DIR__ . '/../../../../../push/NotificationService.php';
require_once __DIR__ . '/../../config.php';

class EntityApprovalService
{
    private PDO $pdo;
    private static ?self $instance = null;
    private NotificationLogger $logger;

    private function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->logger = NotificationLogger::getInstance($pdo);
    }

    public static function getInstance(?PDO $pdo = null): self
    {
        if (self::$instance === null) {
            if ($pdo === null) {
                throw new Exception('PDO connection required for first initialization');
            }
            self::$instance = new self($pdo);
        }
        return self::$instance;
    }

    // ========================================
    // Permission Checks
    // ========================================

    /**
     * Check if user needs approval for a given entity/action
     */
    public function userNeedsApproval(int $userId, string $entityType, string $action): bool
    {
        $stmt = $this->pdo->prepare("
            SELECT approval_mode
            FROM entity_approval_settings
            WHERE user_id = ? AND entity_type = ? AND action = ?
        ");
        $stmt->execute([$userId, $entityType, $action]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$result) {
            return false; // No setting = no approval needed
        }

        return $result['approval_mode'] === 'requires_approval';
    }

    /**
     * Check if user is an authorizer for a given entity/action
     */
    public function isAuthorizer(int $userId, string $entityType, string $action): bool
    {
        $stmt = $this->pdo->prepare("
            SELECT approval_mode
            FROM entity_approval_settings
            WHERE user_id = ? AND entity_type = ? AND action = ?
        ");
        $stmt->execute([$userId, $entityType, $action]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$result) {
            return false;
        }

        return $result['approval_mode'] === 'authorizer';
    }

    /**
     * Get all authorizers for a given entity/action
     */
    public function getAuthorizers(string $entityType, string $action): array
    {
        $stmt = $this->pdo->prepare("
            SELECT eas.user_id, eas.is_mandatory, u.name, u.username, u.email
            FROM entity_approval_settings eas
            JOIN users u ON eas.user_id = u.id
            WHERE eas.entity_type = ?
              AND eas.action = ?
              AND eas.approval_mode = 'authorizer'
              AND u.is_active = 1
        ");
        $stmt->execute([$entityType, $action]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get approval rules for entity/action
     */
    public function getApprovalRules(string $entityType, string $action): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM entity_approval_rules
            WHERE entity_type = ? AND action = ? AND is_active = 1
        ");
        $stmt->execute([$entityType, $action]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    // ========================================
    // Pending Operations Management
    // ========================================

    /**
     * Create a pending operation
     */
    public function createPendingOperation(array $params): array
    {
        $entityType = $params['entity_type'];
        $action = $params['action'];
        $entityId = $params['entity_id'] ?? null;
        $requestedBy = $params['requested_by'];
        $operationData = $params['operation_data'];
        $originalData = $params['original_data'] ?? null;

        // Get approval rules
        $rules = $this->getApprovalRules($entityType, $action);
        $requiredApprovals = $rules['required_approvals'] ?? 1;
        $expiresHours = $rules['expires_hours'] ?? 48;

        // Generate unique ID
        $unicId = uniqid('pending_', true);

        // Calculate expiration
        $expiresAt = date('Y-m-d H:i:s', strtotime("+{$expiresHours} hours"));

        // Insert pending operation
        $stmt = $this->pdo->prepare("
            INSERT INTO pending_entity_operations
            (unicId, entity_type, action, entity_id, requested_by, operation_data, original_data,
             required_approvals, expires_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $unicId,
            $entityType,
            $action,
            $entityId,
            $requestedBy,
            json_encode($operationData),
            $originalData ? json_encode($originalData) : null,
            $requiredApprovals,
            $expiresAt
        ]);

        $pendingId = $this->pdo->lastInsertId();

        // === עדכון סטטוס ישויות קשורות ל"ממתין" ===
        // סטטוסי קבר: 4 = ממתין לאישור רכישה, 5 = ממתין לאישור קבורה
        // סטטוסי לקוח: 4 = ממתין לאישור רכישה, 5 = ממתין לאישור קבורה
        if ($entityType === 'purchases' && $action === 'create' && !empty($operationData['graveId'])) {
            $graveStmt = $this->pdo->prepare("UPDATE graves SET graveStatus = 4 WHERE unicId = ? AND graveStatus = 1");
            $graveStmt->execute([$operationData['graveId']]);
            $graveRows = $graveStmt->rowCount();
            error_log("[EntityApproval] Updating grave {$operationData['graveId']} to status 4. Rows affected: {$graveRows}");
        }

        if ($entityType === 'burials' && $action === 'create' && !empty($operationData['graveId'])) {
            $this->pdo->prepare("UPDATE graves SET graveStatus = 5 WHERE unicId = ? AND graveStatus IN (1, 2)")
                      ->execute([$operationData['graveId']]);
        }

        if ($entityType === 'purchases' && $action === 'create' && !empty($operationData['clientId'])) {
            $updateStmt = $this->pdo->prepare("UPDATE customers SET statusCustomer = 4 WHERE unicId = ? AND statusCustomer = 1");
            $updateStmt->execute([$operationData['clientId']]);
            $rowsAffected = $updateStmt->rowCount();
            error_log("[EntityApproval] Updating customer {$operationData['clientId']} to status 4. Rows affected: {$rowsAffected}");
        }

        if ($entityType === 'burials' && $action === 'create' && !empty($operationData['clientId'])) {
            // רק אם הלקוח פעיל (1) או רכש (2) - לא לשנות אם כבר נפטר (3)
            $this->pdo->prepare("UPDATE customers SET statusCustomer = 5 WHERE unicId = ? AND statusCustomer IN (1, 2)")
                      ->execute([$operationData['clientId']]);
        }
        // === סוף עדכון סטטוס ישויות קשורות ===

        // Get authorizers and create approval records
        $authorizers = $this->getAuthorizers($entityType, $action);
        $authorizerIds = [];

        foreach ($authorizers as $authorizer) {
            $stmt = $this->pdo->prepare("
                INSERT INTO pending_operation_approvals
                (pending_id, user_id, is_mandatory)
                VALUES (?, ?, ?)
            ");
            $stmt->execute([
                $pendingId,
                $authorizer['user_id'],
                $authorizer['is_mandatory']
            ]);
            $authorizerIds[] = $authorizer['user_id'];
        }

        // Send notifications to authorizers
        if (!empty($authorizerIds)) {
            $this->sendApprovalNotification($pendingId, $entityType, $action, $operationData, $authorizerIds, $requestedBy);
        }

        return [
            'success' => true,
            'pendingId' => $pendingId,
            'unicId' => $unicId,
            'authorizersNotified' => count($authorizerIds),
            'expiresAt' => $expiresAt
        ];
    }

    /**
     * Record an approval from an authorizer
     */
    public function recordApproval(int $pendingId, int $userId, bool $biometricVerified = false): array
    {
        // Get pending operation
        $pending = $this->getPendingById($pendingId);
        if (!$pending) {
            throw new Exception('פעולה ממתינה לא נמצאה');
        }

        if ($pending['status'] !== 'pending') {
            throw new Exception('הפעולה כבר טופלה');
        }

        // Check if user is an authorizer
        if (!$this->isAuthorizer($userId, $pending['entity_type'], $pending['action'])) {
            throw new Exception('אינך מורשה לאשר פעולה זו');
        }

        // Update approval record
        $stmt = $this->pdo->prepare("
            UPDATE pending_operation_approvals
            SET status = 'approved',
                biometric_verified = ?,
                responded_at = NOW(),
                ip_address = ?,
                user_agent = ?
            WHERE pending_id = ? AND user_id = ?
        ");
        $stmt->execute([
            $biometricVerified ? 1 : 0,
            $_SERVER['REMOTE_ADDR'] ?? null,
            $_SERVER['HTTP_USER_AGENT'] ?? null,
            $pendingId,
            $userId
        ]);

        // Increment approval count
        $stmt = $this->pdo->prepare("
            UPDATE pending_entity_operations
            SET current_approvals = current_approvals + 1
            WHERE id = ?
        ");
        $stmt->execute([$pendingId]);

        // Check if we have enough approvals
        $pending = $this->getPendingById($pendingId); // Refresh
        $isComplete = $this->checkApprovalComplete($pendingId, $pending);

        if ($isComplete) {
            return $this->applyOperation($pendingId, $userId);
        }

        // בנה תגובה מפורטת עם מידע על מאשרים חובה
        $response = [
            'success' => true,
            'message' => 'האישור נרשם',
            'currentApprovals' => $pending['current_approvals'],
            'requiredApprovals' => $pending['required_approvals'],
            'complete' => false
        ];

        // הוסף מידע על מאשרים חובה שטרם אישרו
        $pendingMandatory = $this->getPendingMandatoryCount($pendingId);
        if ($pendingMandatory > 0) {
            $response['pendingMandatoryCount'] = $pendingMandatory;
            $response['pendingMandatoryAuthorizers'] = $this->getPendingMandatoryAuthorizers($pendingId);
            $response['message'] = "האישור נרשם. ממתין לאישור {$pendingMandatory} מאשרי חובה נוספים.";
        }

        return $response;
    }

    /**
     * Check if approval is complete (enough approvals received)
     */
    private function checkApprovalComplete(int $pendingId, array $pending): bool
    {
        // Check if we have enough approvals
        if ($pending['current_approvals'] < $pending['required_approvals']) {
            return false;
        }

        // Check if all mandatory authorizers have approved
        $rules = $this->getApprovalRules($pending['entity_type'], $pending['action']);
        if ($rules && $rules['require_all_mandatory']) {
            $pendingMandatory = $this->getPendingMandatoryCount($pendingId);
            if ($pendingMandatory > 0) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get count of mandatory authorizers who haven't approved yet
     */
    public function getPendingMandatoryCount(int $pendingId): int
    {
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) as pending_mandatory
            FROM pending_operation_approvals
            WHERE pending_id = ? AND is_mandatory = 1 AND status != 'approved'
        ");
        $stmt->execute([$pendingId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return (int)$result['pending_mandatory'];
    }

    /**
     * Get names of mandatory authorizers who haven't approved yet
     */
    public function getPendingMandatoryAuthorizers(int $pendingId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT u.name, u.username
            FROM pending_operation_approvals poa
            JOIN users u ON poa.user_id = u.id
            WHERE poa.pending_id = ? AND poa.is_mandatory = 1 AND poa.status != 'approved'
        ");
        $stmt->execute([$pendingId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Apply an approved operation
     */
    public function applyOperation(int $pendingId, int $approverId): array
    {
        $pending = $this->getPendingById($pendingId);
        if (!$pending) {
            throw new Exception('פעולה ממתינה לא נמצאה');
        }

        $operationData = json_decode($pending['operation_data'], true);
        $entityType = $pending['entity_type'];
        $action = $pending['action'];

        // Execute the actual operation (pass pendingId for history linking)
        $result = $this->executeOperation($entityType, $action, $operationData, $pending['entity_id'], $pendingId);

        // Update pending status
        $stmt = $this->pdo->prepare("
            UPDATE pending_entity_operations
            SET status = 'approved', completed_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$pendingId]);

        // Notify the requester
        $this->notifyRequester($pending, 'approved', $approverId);

        return [
            'success' => true,
            'message' => 'הפעולה אושרה ובוצעה',
            'complete' => true,
            'entityId' => $result['entityId'] ?? null
        ];
    }

    /**
     * Reject a pending operation
     */
    public function rejectOperation(int $pendingId, int $rejectorId, ?string $reason = null): array
    {
        $pending = $this->getPendingById($pendingId);
        if (!$pending) {
            throw new Exception('פעולה ממתינה לא נמצאה');
        }

        if ($pending['status'] !== 'pending') {
            throw new Exception('הפעולה כבר טופלה');
        }

        // Update approval record
        $stmt = $this->pdo->prepare("
            UPDATE pending_operation_approvals
            SET status = 'rejected',
                responded_at = NOW(),
                ip_address = ?,
                user_agent = ?
            WHERE pending_id = ? AND user_id = ?
        ");
        $stmt->execute([
            $_SERVER['REMOTE_ADDR'] ?? null,
            $_SERVER['HTTP_USER_AGENT'] ?? null,
            $pendingId,
            $rejectorId
        ]);

        // Update pending status
        $stmt = $this->pdo->prepare("
            UPDATE pending_entity_operations
            SET status = 'rejected',
                rejection_reason = ?,
                completed_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$reason, $pendingId]);

        // === Rollback סטטוס ישויות קשורות ===
        $this->rollbackRelatedEntityStatuses($pending);
        // === סוף Rollback ===

        // Notify the requester
        $this->notifyRequester($pending, 'rejected', $rejectorId, $reason);

        return [
            'success' => true,
            'message' => 'הפעולה נדחתה'
        ];
    }

    /**
     * Cancel a pending operation (by requester)
     */
    public function cancelOperation(int $pendingId, int $userId): array
    {
        $pending = $this->getPendingById($pendingId);
        if (!$pending) {
            throw new Exception('פעולה ממתינה לא נמצאה');
        }

        if ($pending['requested_by'] != $userId) {
            throw new Exception('אין לך הרשאה לבטל פעולה זו');
        }

        if ($pending['status'] !== 'pending') {
            throw new Exception('הפעולה כבר טופלה');
        }

        $stmt = $this->pdo->prepare("
            UPDATE pending_entity_operations
            SET status = 'cancelled', completed_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$pendingId]);

        // === Rollback סטטוס ישויות קשורות ===
        $this->rollbackRelatedEntityStatuses($pending);
        // === סוף Rollback ===

        return [
            'success' => true,
            'message' => 'הפעולה בוטלה'
        ];
    }

    /**
     * שליחה חוזרת של התראות למאשרים שטרם הגיבו
     */
    public function resendNotifications(int $pendingId): int
    {
        $pending = $this->getPendingById($pendingId);
        if (!$pending) {
            throw new Exception('פעולה ממתינה לא נמצאה');
        }

        if ($pending['status'] !== 'pending') {
            throw new Exception('הפעולה כבר טופלה');
        }

        // מציאת מאשרים שטרם הגיבו
        $stmt = $this->pdo->prepare("
            SELECT user_id FROM pending_operation_approvals
            WHERE pending_id = ? AND status = 'pending'
        ");
        $stmt->execute([$pendingId]);
        $pendingApprovals = $stmt->fetchAll(PDO::FETCH_COLUMN);

        if (empty($pendingApprovals)) {
            return 0;
        }

        // שליחת התראות חוזרות
        $operationData = json_decode($pending['operation_data'], true) ?? [];
        $this->sendApprovalNotification(
            $pendingId,
            $pending['entity_type'],
            $pending['action'],
            $operationData,
            $pendingApprovals,
            $pending['requested_by']
        );

        return count($pendingApprovals);
    }

    // ========================================
    // Query Methods
    // ========================================

    /**
     * Get pending operation by ID
     */
    public function getPendingById(int $pendingId): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT peo.*, u.name as requester_name
            FROM pending_entity_operations peo
            JOIN users u ON peo.requested_by = u.id
            WHERE peo.id = ?
        ");
        $stmt->execute([$pendingId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Get pending operations for an entity
     */
    public function getPendingForEntity(string $entityType, string $entityId): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM pending_entity_operations
            WHERE entity_type = ? AND entity_id = ? AND status = 'pending'
            ORDER BY created_at DESC
            LIMIT 1
        ");
        $stmt->execute([$entityType, $entityId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Get all pending operations for a user (as requester)
     */
    public function getUserPendingOperations(int $userId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM pending_entity_operations
            WHERE requested_by = ? AND status = 'pending'
            ORDER BY created_at DESC
        ");
        $stmt->execute([$userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get pending operations awaiting user's approval
     */
    public function getAwaitingMyApproval(int $userId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT peo.*, poa.is_mandatory, poa.status as my_approval_status,
                   u.name as requester_name
            FROM pending_entity_operations peo
            JOIN pending_operation_approvals poa ON peo.id = poa.pending_id
            JOIN users u ON peo.requested_by = u.id
            WHERE poa.user_id = ?
              AND peo.status = 'pending'
              AND poa.status = 'pending'
            ORDER BY peo.created_at DESC
        ");
        $stmt->execute([$userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ========================================
    // Settings Management
    // ========================================

    /**
     * Get user's approval settings
     */
    public function getUserApprovalSettings(int $userId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT entity_type, action, approval_mode, is_mandatory
            FROM entity_approval_settings
            WHERE user_id = ?
        ");
        $stmt->execute([$userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Save user's approval settings
     */
    public function saveUserApprovalSettings(int $userId, array $settings): bool
    {
        foreach ($settings as $setting) {
            $stmt = $this->pdo->prepare("
                INSERT INTO entity_approval_settings
                (user_id, entity_type, action, approval_mode, is_mandatory)
                VALUES (?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    approval_mode = VALUES(approval_mode),
                    is_mandatory = VALUES(is_mandatory),
                    updated_at = NOW()
            ");
            $stmt->execute([
                $userId,
                $setting['entity_type'],
                $setting['action'],
                $setting['approval_mode'],
                $setting['is_mandatory'] ?? 0
            ]);
        }
        return true;
    }

    /**
     * Get approval rules
     */
    public function getAllApprovalRules(): array
    {
        $stmt = $this->pdo->query("SELECT * FROM entity_approval_rules ORDER BY entity_type, action");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Save approval rules
     */
    public function saveApprovalRules(array $rules): bool
    {
        foreach ($rules as $rule) {
            $stmt = $this->pdo->prepare("
                UPDATE entity_approval_rules
                SET required_approvals = ?,
                    require_all_mandatory = ?,
                    expires_hours = ?,
                    is_active = ?
                WHERE entity_type = ? AND action = ?
            ");
            $stmt->execute([
                $rule['required_approvals'] ?? 1,
                $rule['require_all_mandatory'] ?? 1,
                $rule['expires_hours'] ?? 48,
                $rule['is_active'] ?? 1,
                $rule['entity_type'],
                $rule['action']
            ]);
        }
        return true;
    }

    // ========================================
    // Private Helper Methods
    // ========================================

    /**
     * Execute the actual entity operation
     * @param int|null $pendingId - ID of the pending operation (for linking history)
     */
    private function executeOperation(string $entityType, string $action, array $data, ?string $entityId, ?int $pendingId = null): array
    {
        // This will call the appropriate API method based on entity type and action
        // For now, we'll implement basic logic - in production this should call the actual API methods

        switch ($entityType) {
            case 'purchases':
                return $this->executePurchaseOperation($action, $data, $entityId, $pendingId);
            case 'burials':
                return $this->executeBurialOperation($action, $data, $entityId, $pendingId);
            case 'customers':
                return $this->executeCustomerOperation($action, $data, $entityId, $pendingId);
            case 'cemeteries':
                return $this->executeCemeteryOperation($action, $data, $entityId, $pendingId);
            case 'blocks':
                return $this->executeBlockOperation($action, $data, $entityId, $pendingId);
            case 'plots':
                return $this->executePlotOperation($action, $data, $entityId, $pendingId);
            case 'graves':
                return $this->executeGraveOperation($action, $data, $entityId, $pendingId);
            case 'payments':
                return $this->executePaymentOperation($action, $data, $entityId, $pendingId);
            case 'areaGraves':
                return $this->executeAreaGraveOperation($action, $data, $entityId, $pendingId);
            default:
                throw new Exception("Unknown entity type: $entityType");
        }
    }

    private function executePurchaseOperation(string $action, array $data, ?string $entityId, ?int $pendingId = null): array
    {
        switch ($action) {
            case 'create':
                // Insert new purchase
                $unicId = uniqid('', true);
                $stmt = $this->pdo->prepare("
                    INSERT INTO purchases (unicId, clientId, graveId, contactId, price,
                                          numOfPayments, PaymentEndDate, paymentsList,
                                          purchaseStatus, buyerStatus, comment, isActive, createDate, approved_pending_id)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, NOW(), ?)
                ");
                $stmt->execute([
                    $unicId,
                    $data['clientId'] ?? null,
                    $data['graveId'] ?? null,
                    $data['contactId'] ?? null,
                    $data['price'] ?? 0,
                    $data['numOfPayments'] ?? 1,
                    $data['PaymentEndDate'] ?? null,
                    json_encode($data['paymentsList'] ?? []),
                    $data['purchaseStatus'] ?? 1,
                    $data['buyerStatus'] ?? 1,
                    $data['comment'] ?? null,
                    $pendingId
                ]);

                // Update grave status
                if (!empty($data['graveId'])) {
                    $this->pdo->prepare("UPDATE graves SET graveStatus = 2 WHERE unicId = ?")->execute([$data['graveId']]);
                }

                return ['entityId' => $unicId];

            case 'edit':
                // Update existing purchase
                $fields = [];
                $values = [];
                foreach (['clientId', 'graveId', 'contactId', 'price', 'numOfPayments', 'PaymentEndDate', 'paymentsList', 'purchaseStatus', 'buyerStatus', 'comment'] as $field) {
                    if (isset($data[$field])) {
                        $fields[] = "$field = ?";
                        $values[] = is_array($data[$field]) ? json_encode($data[$field]) : $data[$field];
                    }
                }
                if (!empty($fields)) {
                    $values[] = $entityId;
                    $sql = "UPDATE purchases SET " . implode(', ', $fields) . ", updateDate = NOW() WHERE unicId = ?";
                    $this->pdo->prepare($sql)->execute($values);
                }
                return ['entityId' => $entityId];

            case 'delete':
                // Soft delete
                $this->pdo->prepare("UPDATE purchases SET isActive = 0, updateDate = NOW() WHERE unicId = ?")->execute([$entityId]);
                // Free the grave
                $stmt = $this->pdo->prepare("SELECT graveId FROM purchases WHERE unicId = ?");
                $stmt->execute([$entityId]);
                $purchase = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($purchase && $purchase['graveId']) {
                    $this->pdo->prepare("UPDATE graves SET graveStatus = 1 WHERE unicId = ?")->execute([$purchase['graveId']]);
                }
                return ['entityId' => $entityId];
        }

        throw new Exception("Unknown action: $action");
    }

    private function executeBurialOperation(string $action, array $data, ?string $entityId, ?int $pendingId = null): array
    {
        // Similar implementation for burials
        switch ($action) {
            case 'create':
                $unicId = uniqid('', true);
                $stmt = $this->pdo->prepare("
                    INSERT INTO burials (unicId, clientId, graveId, contactId, purchaseId,
                                        dateDeath, timeDeath, dateBurial, timeBurial, placeDeath,
                                        nationalInsuranceBurial, deathAbroad, comment, isActive, createDate, approved_pending_id)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, NOW(), ?)
                ");
                $stmt->execute([
                    $unicId,
                    $data['clientId'] ?? null,
                    $data['graveId'] ?? null,
                    $data['contactId'] ?? null,
                    $data['purchaseId'] ?? null,
                    $data['dateDeath'] ?? null,
                    $data['timeDeath'] ?? null,
                    $data['dateBurial'] ?? null,
                    $data['timeBurial'] ?? null,
                    $data['placeDeath'] ?? null,
                    $data['nationalInsuranceBurial'] ?? 0,
                    $data['deathAbroad'] ?? 0,
                    $data['comment'] ?? null,
                    $pendingId
                ]);

                // Update grave and customer status
                if (!empty($data['graveId'])) {
                    $this->pdo->prepare("UPDATE graves SET graveStatus = 3 WHERE unicId = ?")->execute([$data['graveId']]);
                }
                if (!empty($data['clientId'])) {
                    $this->pdo->prepare("UPDATE customers SET statusCustomer = 3 WHERE unicId = ?")->execute([$data['clientId']]);
                }

                return ['entityId' => $unicId];

            case 'edit':
            case 'delete':
                // Similar to purchases
                return ['entityId' => $entityId];
        }

        throw new Exception("Unknown action: $action");
    }

    private function executeCustomerOperation(string $action, array $data, ?string $entityId, ?int $pendingId = null): array
    {
        switch ($action) {
            case 'create':
                // Use unicId from data if exists (created in API), otherwise generate
                $unicId = $data['unicId'] ?? uniqid('customer_', true);

                // Build dynamic insert based on available fields
                $fields = [
                    'unicId', 'typeId', 'numId', 'firstName', 'lastName', 'oldName', 'nom',
                    'gender', 'nameFather', 'nameMother', 'maritalStatus', 'dateBirth',
                    'countryBirth', 'countryBirthId', 'age', 'resident', 'countryId', 'cityId',
                    'address', 'phone', 'phoneMobile', 'statusCustomer', 'spouse', 'comment',
                    'association', 'dateBirthHe', 'tourist', 'createDate', 'updateDate'
                ];

                $insertFields = ['isActive'];
                $insertValues = ['1'];
                $params = [];

                // Always include unicId
                $insertFields[] = 'unicId';
                $insertValues[] = ':unicId';
                $params['unicId'] = $unicId;

                // Add approved_pending_id if provided
                if ($pendingId) {
                    $insertFields[] = 'approved_pending_id';
                    $insertValues[] = ':approved_pending_id';
                    $params['approved_pending_id'] = $pendingId;
                }

                foreach ($fields as $field) {
                    if ($field === 'unicId') continue; // Already added
                    if (isset($data[$field])) {
                        $insertFields[] = $field;
                        $insertValues[] = ":$field";
                        $params[$field] = $data[$field];
                    }
                }

                $sql = "INSERT INTO customers (" . implode(', ', $insertFields) . ")
                        VALUES (" . implode(', ', $insertValues) . ")";

                $stmt = $this->pdo->prepare($sql);
                $stmt->execute($params);

                $clientId = $this->pdo->lastInsertId();

                // Handle spouse bidirectional update (same as customers-api.php)
                if (!empty($data['spouse'])) {
                    $maritalStatus = $data['maritalStatus'] ?? null;
                    $spouseMaritalStatus = ($maritalStatus == 'נשוי') ? 'נשואה' :
                                           (($maritalStatus == 'נשואה') ? 'נשוי' : $maritalStatus);

                    $updateSpouseStmt = $this->pdo->prepare("
                        UPDATE customers
                        SET spouse = :newClientId, maritalStatus = :maritalStatus, updateDate = :updateDate
                        WHERE unicId = :spouseId
                    ");
                    $updateSpouseStmt->execute([
                        'newClientId' => $unicId,
                        'maritalStatus' => $spouseMaritalStatus,
                        'updateDate' => date('Y-m-d H:i:s'),
                        'spouseId' => $data['spouse']
                    ]);
                }

                return ['entityId' => $unicId, 'id' => $clientId];

            case 'edit':
                if (!$entityId) {
                    throw new Exception('Entity ID is required for edit');
                }

                $fields = [
                    'typeId', 'numId', 'firstName', 'lastName', 'oldName', 'nom',
                    'gender', 'nameFather', 'nameMother', 'maritalStatus', 'dateBirth',
                    'countryBirth', 'countryBirthId', 'age', 'resident', 'countryId', 'cityId',
                    'address', 'phone', 'phoneMobile', 'statusCustomer', 'spouse', 'comment',
                    'association', 'dateBirthHe', 'tourist', 'updateDate'
                ];

                $updateFields = [];
                $params = ['id' => $entityId];

                foreach ($fields as $field) {
                    if (isset($data[$field])) {
                        $updateFields[] = "$field = :$field";
                        $params[$field] = $data[$field];
                    }
                }

                if (empty($updateFields)) {
                    return ['entityId' => $entityId];
                }

                $sql = "UPDATE customers SET " . implode(', ', $updateFields) . " WHERE unicId = :id";
                $stmt = $this->pdo->prepare($sql);
                $stmt->execute($params);

                return ['entityId' => $entityId];

            case 'delete':
                if (!$entityId) {
                    throw new Exception('Entity ID is required for delete');
                }

                $stmt = $this->pdo->prepare("UPDATE customers SET isActive = 0, inactiveDate = :date WHERE unicId = :id");
                $stmt->execute(['id' => $entityId, 'date' => date('Y-m-d H:i:s')]);

                return ['entityId' => $entityId];
        }

        throw new Exception("Unknown action: $action");
    }

    private function executeCemeteryOperation(string $action, array $data, ?string $entityId, ?int $pendingId = null): array
    {
        switch ($action) {
            case 'create':
                $unicId = $data['unicId'] ?? uniqid('cem_', true);
                $fields = ['unicId', 'cemeteryName', 'cemeteryNameHe', 'cityId', 'address', 'phone', 'email', 'website', 'contactName', 'comments', 'isActive', 'createDate'];
                $insertFields = [];
                $insertValues = [];
                $params = [];

                foreach ($fields as $field) {
                    if (isset($data[$field])) {
                        $insertFields[] = $field;
                        $insertValues[] = ":$field";
                        $params[$field] = $data[$field];
                    }
                }
                if (!in_array('unicId', $insertFields)) {
                    $insertFields[] = 'unicId';
                    $insertValues[] = ':unicId';
                    $params['unicId'] = $unicId;
                }
                if (!in_array('isActive', $insertFields)) {
                    $insertFields[] = 'isActive';
                    $insertValues[] = '1';
                }
                if (!in_array('createDate', $insertFields)) {
                    $insertFields[] = 'createDate';
                    $insertValues[] = 'NOW()';
                }
                // Add approved_pending_id if provided
                if ($pendingId) {
                    $insertFields[] = 'approved_pending_id';
                    $insertValues[] = ':approved_pending_id';
                    $params['approved_pending_id'] = $pendingId;
                }

                $sql = "INSERT INTO cemeteries (" . implode(', ', $insertFields) . ") VALUES (" . implode(', ', $insertValues) . ")";
                $this->pdo->prepare($sql)->execute($params);
                return ['entityId' => $unicId];

            case 'edit':
                $fields = ['cemeteryName', 'cemeteryNameHe', 'cityId', 'address', 'phone', 'email', 'website', 'contactName', 'comments'];
                $updateFields = [];
                $params = ['id' => $entityId];
                foreach ($fields as $field) {
                    if (isset($data[$field])) {
                        $updateFields[] = "$field = :$field";
                        $params[$field] = $data[$field];
                    }
                }
                if (!empty($updateFields)) {
                    $sql = "UPDATE cemeteries SET " . implode(', ', $updateFields) . ", updateDate = NOW() WHERE unicId = :id";
                    $this->pdo->prepare($sql)->execute($params);
                }
                return ['entityId' => $entityId];

            case 'delete':
                $this->pdo->prepare("UPDATE cemeteries SET isActive = 0, updateDate = NOW() WHERE unicId = ?")->execute([$entityId]);
                return ['entityId' => $entityId];
        }
        throw new Exception("Unknown action: $action");
    }

    private function executeBlockOperation(string $action, array $data, ?string $entityId, ?int $pendingId = null): array
    {
        switch ($action) {
            case 'create':
                $unicId = $data['unicId'] ?? uniqid('blk_', true);
                $fields = ['unicId', 'cemeteryId', 'blockName', 'blockNameHe', 'totalPlots', 'comments', 'isActive', 'createDate'];
                $insertFields = [];
                $insertValues = [];
                $params = [];

                foreach ($fields as $field) {
                    if (isset($data[$field])) {
                        $insertFields[] = $field;
                        $insertValues[] = ":$field";
                        $params[$field] = $data[$field];
                    }
                }
                if (!in_array('unicId', $insertFields)) {
                    $insertFields[] = 'unicId';
                    $insertValues[] = ':unicId';
                    $params['unicId'] = $unicId;
                }
                if (!in_array('isActive', $insertFields)) {
                    $insertFields[] = 'isActive';
                    $insertValues[] = '1';
                }
                if (!in_array('createDate', $insertFields)) {
                    $insertFields[] = 'createDate';
                    $insertValues[] = 'NOW()';
                }
                // Add approved_pending_id if provided
                if ($pendingId) {
                    $insertFields[] = 'approved_pending_id';
                    $insertValues[] = ':approved_pending_id';
                    $params['approved_pending_id'] = $pendingId;
                }

                $sql = "INSERT INTO blocks (" . implode(', ', $insertFields) . ") VALUES (" . implode(', ', $insertValues) . ")";
                $this->pdo->prepare($sql)->execute($params);
                return ['entityId' => $unicId];

            case 'edit':
                $fields = ['cemeteryId', 'blockName', 'blockNameHe', 'totalPlots', 'comments'];
                $updateFields = [];
                $params = ['id' => $entityId];
                foreach ($fields as $field) {
                    if (isset($data[$field])) {
                        $updateFields[] = "$field = :$field";
                        $params[$field] = $data[$field];
                    }
                }
                if (!empty($updateFields)) {
                    $sql = "UPDATE blocks SET " . implode(', ', $updateFields) . ", updateDate = NOW() WHERE unicId = :id";
                    $this->pdo->prepare($sql)->execute($params);
                }
                return ['entityId' => $entityId];

            case 'delete':
                $this->pdo->prepare("UPDATE blocks SET isActive = 0, updateDate = NOW() WHERE unicId = ?")->execute([$entityId]);
                return ['entityId' => $entityId];
        }
        throw new Exception("Unknown action: $action");
    }

    private function executePlotOperation(string $action, array $data, ?string $entityId, ?int $pendingId = null): array
    {
        switch ($action) {
            case 'create':
                $unicId = $data['unicId'] ?? uniqid('plt_', true);
                $fields = ['unicId', 'blockId', 'plotName', 'plotNameHe', 'plotType', 'totalGraves', 'comments', 'isActive', 'createDate'];
                $insertFields = [];
                $insertValues = [];
                $params = [];

                foreach ($fields as $field) {
                    if (isset($data[$field])) {
                        $insertFields[] = $field;
                        $insertValues[] = ":$field";
                        $params[$field] = $data[$field];
                    }
                }
                if (!in_array('unicId', $insertFields)) {
                    $insertFields[] = 'unicId';
                    $insertValues[] = ':unicId';
                    $params['unicId'] = $unicId;
                }
                if (!in_array('isActive', $insertFields)) {
                    $insertFields[] = 'isActive';
                    $insertValues[] = '1';
                }
                if (!in_array('createDate', $insertFields)) {
                    $insertFields[] = 'createDate';
                    $insertValues[] = 'NOW()';
                }
                // Add approved_pending_id if provided
                if ($pendingId) {
                    $insertFields[] = 'approved_pending_id';
                    $insertValues[] = ':approved_pending_id';
                    $params['approved_pending_id'] = $pendingId;
                }

                $sql = "INSERT INTO plots (" . implode(', ', $insertFields) . ") VALUES (" . implode(', ', $insertValues) . ")";
                $this->pdo->prepare($sql)->execute($params);
                return ['entityId' => $unicId];

            case 'edit':
                $fields = ['blockId', 'plotName', 'plotNameHe', 'plotType', 'totalGraves', 'comments'];
                $updateFields = [];
                $params = ['id' => $entityId];
                foreach ($fields as $field) {
                    if (isset($data[$field])) {
                        $updateFields[] = "$field = :$field";
                        $params[$field] = $data[$field];
                    }
                }
                if (!empty($updateFields)) {
                    $sql = "UPDATE plots SET " . implode(', ', $updateFields) . ", updateDate = NOW() WHERE unicId = :id";
                    $this->pdo->prepare($sql)->execute($params);
                }
                return ['entityId' => $entityId];

            case 'delete':
                $this->pdo->prepare("UPDATE plots SET isActive = 0, updateDate = NOW() WHERE unicId = ?")->execute([$entityId]);
                return ['entityId' => $entityId];
        }
        throw new Exception("Unknown action: $action");
    }

    private function executeGraveOperation(string $action, array $data, ?string $entityId, ?int $pendingId = null): array
    {
        switch ($action) {
            case 'create':
                $unicId = $data['unicId'] ?? uniqid('grv_', true);
                $fields = ['unicId', 'areaGraveId', 'graveNameHe', 'plotType', 'graveStatus', 'graveLocation', 'constructionCost', 'isSmallGrave', 'comments', 'documentsList', 'isActive', 'createDate'];
                $insertFields = [];
                $insertValues = [];
                $params = [];

                foreach ($fields as $field) {
                    if (isset($data[$field])) {
                        $insertFields[] = $field;
                        $insertValues[] = ":$field";
                        $params[$field] = is_array($data[$field]) ? json_encode($data[$field]) : $data[$field];
                    }
                }
                if (!in_array('unicId', $insertFields)) {
                    $insertFields[] = 'unicId';
                    $insertValues[] = ':unicId';
                    $params['unicId'] = $unicId;
                }
                if (!in_array('isActive', $insertFields)) {
                    $insertFields[] = 'isActive';
                    $insertValues[] = '1';
                }
                if (!in_array('createDate', $insertFields)) {
                    $insertFields[] = 'createDate';
                    $insertValues[] = 'NOW()';
                }
                // Add approved_pending_id if provided
                if ($pendingId) {
                    $insertFields[] = 'approved_pending_id';
                    $insertValues[] = ':approved_pending_id';
                    $params['approved_pending_id'] = $pendingId;
                }

                $sql = "INSERT INTO graves (" . implode(', ', $insertFields) . ") VALUES (" . implode(', ', $insertValues) . ")";
                $this->pdo->prepare($sql)->execute($params);
                return ['entityId' => $unicId];

            case 'edit':
                $fields = ['areaGraveId', 'graveNameHe', 'plotType', 'graveStatus', 'graveLocation', 'constructionCost', 'isSmallGrave', 'comments', 'documentsList'];
                $updateFields = [];
                $params = ['id' => $entityId];
                foreach ($fields as $field) {
                    if (isset($data[$field])) {
                        $updateFields[] = "$field = :$field";
                        $params[$field] = is_array($data[$field]) ? json_encode($data[$field]) : $data[$field];
                    }
                }
                if (!empty($updateFields)) {
                    $sql = "UPDATE graves SET " . implode(', ', $updateFields) . ", updateDate = NOW() WHERE unicId = :id";
                    $this->pdo->prepare($sql)->execute($params);
                }
                return ['entityId' => $entityId];

            case 'delete':
                $this->pdo->prepare("UPDATE graves SET isActive = 0, updateDate = NOW() WHERE unicId = ?")->execute([$entityId]);
                return ['entityId' => $entityId];
        }
        throw new Exception("Unknown action: $action");
    }

    private function executePaymentOperation(string $action, array $data, ?string $entityId, ?int $pendingId = null): array
    {
        switch ($action) {
            case 'create':
                // שדות טבלת payments
                $fields = [
                    'plotType', 'graveType', 'resident', 'buyerStatus', 'price',
                    'priceDefinition', 'cemeteryId', 'blockId', 'plotId', 'lineId',
                    'startPayment', 'unicId', 'mandatory'
                ];

                $insertFields = ['isActive', 'createDate'];
                $insertValues = [':isActive', ':createDate'];
                $params = [
                    'isActive' => 1,
                    'createDate' => date('Y-m-d H:i:s')
                ];

                // Add approved_pending_id if provided
                if ($pendingId) {
                    $insertFields[] = 'approved_pending_id';
                    $insertValues[] = ':approved_pending_id';
                    $params['approved_pending_id'] = $pendingId;
                }

                foreach ($fields as $field) {
                    if (isset($data[$field])) {
                        $insertFields[] = $field;
                        $insertValues[] = ":$field";
                        $params[$field] = $data[$field];
                    }
                }

                $sql = "INSERT INTO payments (" . implode(', ', $insertFields) . ")
                        VALUES (" . implode(', ', $insertValues) . ")";
                $this->pdo->prepare($sql)->execute($params);
                $paymentId = $this->pdo->lastInsertId();
                return ['entityId' => $paymentId];

            case 'edit':
                $fields = [
                    'plotType', 'graveType', 'resident', 'buyerStatus', 'price',
                    'priceDefinition', 'cemeteryId', 'blockId', 'plotId', 'lineId',
                    'startPayment', 'mandatory'
                ];
                $updateFields = ['updateDate = :updateDate'];
                $params = [
                    'id' => $entityId,
                    'updateDate' => date('Y-m-d H:i:s')
                ];
                foreach ($fields as $field) {
                    if (isset($data[$field])) {
                        $updateFields[] = "$field = :$field";
                        $params[$field] = $data[$field];
                    }
                }
                $sql = "UPDATE payments SET " . implode(', ', $updateFields) . " WHERE id = :id";
                $this->pdo->prepare($sql)->execute($params);
                return ['entityId' => $entityId];

            case 'delete':
                $this->pdo->prepare("
                    UPDATE payments
                    SET isActive = 0, inactiveDate = :inactiveDate
                    WHERE id = :id
                ")->execute([
                    'id' => $entityId,
                    'inactiveDate' => date('Y-m-d H:i:s')
                ]);
                return ['entityId' => $entityId];
        }
        throw new Exception("Unknown action: $action");
    }

    private function executeAreaGraveOperation(string $action, array $data, ?string $entityId, ?int $pendingId = null): array
    {
        switch ($action) {
            case 'create':
                // Create area grave
                $unicId = $data['unicId'] ?? uniqid('areaGrave_', true);
                $now = date('Y-m-d H:i:s');

                $stmt = $this->pdo->prepare("
                    INSERT INTO areaGraves (unicId, areaGraveNameHe, coordinates, gravesList, graveType,
                                           lineId, comments, documentsList, createDate, updateDate, isActive, approved_pending_id)
                    VALUES (:unicId, :areaGraveNameHe, :coordinates, :gravesList, :graveType,
                            :lineId, :comments, :documentsList, :createDate, :updateDate, 1, :approved_pending_id)
                ");
                $stmt->execute([
                    'unicId' => $unicId,
                    'areaGraveNameHe' => $data['areaGraveNameHe'] ?? '',
                    'coordinates' => $data['coordinates'] ?? '',
                    'gravesList' => '',
                    'graveType' => $data['graveType'] ?? '',
                    'lineId' => $data['lineId'] ?? null,
                    'comments' => $data['comments'] ?? '',
                    'documentsList' => $data['documentsList'] ?? '',
                    'createDate' => $now,
                    'updateDate' => $now,
                    'approved_pending_id' => $pendingId
                ]);

                // Create graves if provided
                $gravesData = $data['gravesData'] ?? [];
                if (is_string($gravesData)) {
                    $gravesData = json_decode($gravesData, true) ?? [];
                }

                foreach ($gravesData as $grave) {
                    $graveUnicId = uniqid('grave_', true);
                    $graveStmt = $this->pdo->prepare("
                        INSERT INTO graves (unicId, areaGraveId, graveNameHe, plotType, graveStatus,
                                           graveLocation, constructionCost, isSmallGrave, comments,
                                           documentsList, createDate, updateDate, isActive)
                        VALUES (:unicId, :areaGraveId, :graveNameHe, :plotType, :graveStatus,
                                :graveLocation, :constructionCost, :isSmallGrave, :comments,
                                :documentsList, :createDate, :updateDate, 1)
                    ");
                    $graveStmt->execute([
                        'unicId' => $graveUnicId,
                        'areaGraveId' => $unicId,
                        'graveNameHe' => trim($grave['graveNameHe'] ?? ''),
                        'plotType' => $grave['plotType'] ?? 1,
                        'graveStatus' => 1,
                        'graveLocation' => 0,
                        'constructionCost' => $grave['constructionCost'] ?? 0,
                        'isSmallGrave' => isset($grave['isSmallGrave']) && $grave['isSmallGrave'] ? 1 : 0,
                        'comments' => '',
                        'documentsList' => '',
                        'createDate' => $now,
                        'updateDate' => $now
                    ]);
                }

                return ['entityId' => $unicId];

            case 'edit':
                if (!$entityId) {
                    throw new Exception('Entity ID is required for edit');
                }

                $now = date('Y-m-d H:i:s');
                $fields = ['areaGraveNameHe', 'coordinates', 'graveType', 'lineId', 'comments', 'documentsList'];
                $updateFields = ['updateDate = :updateDate'];
                $params = ['id' => $entityId, 'updateDate' => $now];

                foreach ($fields as $field) {
                    if (isset($data[$field])) {
                        $updateFields[] = "$field = :$field";
                        $params[$field] = $data[$field];
                    }
                }

                $sql = "UPDATE areaGraves SET " . implode(', ', $updateFields) . " WHERE unicId = :id";
                $this->pdo->prepare($sql)->execute($params);

                // Handle graves update if provided
                $gravesData = $data['gravesData'] ?? [];
                if (is_string($gravesData)) {
                    $gravesData = json_decode($gravesData, true) ?? [];
                }

                if (!empty($gravesData)) {
                    // Get existing graves
                    $stmt = $this->pdo->prepare("SELECT unicId, graveStatus FROM graves WHERE areaGraveId = :id AND isActive = 1");
                    $stmt->execute(['id' => $entityId]);
                    $existingGraves = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    $existingIds = array_column($existingGraves, 'unicId');
                    $existingStatuses = array_column($existingGraves, 'graveStatus', 'unicId');
                    $newGravesIds = array_filter(array_column($gravesData, 'id'));

                    // Delete removed graves (only if status = 1)
                    $gravesToDelete = array_diff($existingIds, $newGravesIds);
                    foreach ($gravesToDelete as $graveIdToDelete) {
                        if (($existingStatuses[$graveIdToDelete] ?? 0) == 1) {
                            $this->pdo->prepare("UPDATE graves SET isActive = 0, inactiveDate = :date WHERE unicId = :id")
                                      ->execute(['id' => $graveIdToDelete, 'date' => $now]);
                        }
                    }

                    // Update or create graves
                    foreach ($gravesData as $grave) {
                        if (!empty($grave['id']) && in_array($grave['id'], $existingIds)) {
                            // Update existing
                            $this->pdo->prepare("
                                UPDATE graves SET graveNameHe = :graveNameHe, plotType = :plotType,
                                                 constructionCost = :constructionCost, isSmallGrave = :isSmallGrave,
                                                 updateDate = :updateDate
                                WHERE unicId = :id
                            ")->execute([
                                'id' => $grave['id'],
                                'graveNameHe' => trim($grave['graveNameHe'] ?? ''),
                                'plotType' => $grave['plotType'] ?? 1,
                                'constructionCost' => $grave['constructionCost'] ?? 0,
                                'isSmallGrave' => isset($grave['isSmallGrave']) && $grave['isSmallGrave'] ? 1 : 0,
                                'updateDate' => $now
                            ]);
                        } else {
                            // Create new
                            $graveUnicId = uniqid('grave_', true);
                            $this->pdo->prepare("
                                INSERT INTO graves (unicId, areaGraveId, graveNameHe, plotType, graveStatus,
                                                   graveLocation, constructionCost, isSmallGrave, comments,
                                                   documentsList, createDate, updateDate, isActive)
                                VALUES (:unicId, :areaGraveId, :graveNameHe, :plotType, 1, 0, :constructionCost,
                                        :isSmallGrave, '', '', :createDate, :updateDate, 1)
                            ")->execute([
                                'unicId' => $graveUnicId,
                                'areaGraveId' => $entityId,
                                'graveNameHe' => trim($grave['graveNameHe'] ?? ''),
                                'plotType' => $grave['plotType'] ?? 1,
                                'constructionCost' => $grave['constructionCost'] ?? 0,
                                'isSmallGrave' => isset($grave['isSmallGrave']) && $grave['isSmallGrave'] ? 1 : 0,
                                'createDate' => $now,
                                'updateDate' => $now
                            ]);
                        }
                    }
                }

                return ['entityId' => $entityId];

            case 'delete':
                $this->pdo->prepare("UPDATE areaGraves SET isActive = 0, inactiveDate = :date WHERE unicId = :id")
                          ->execute(['id' => $entityId, 'date' => date('Y-m-d H:i:s')]);
                return ['entityId' => $entityId];
        }
        throw new Exception("Unknown action: $action");
    }

    /**
     * Send approval notification to authorizers
     */
    private function sendApprovalNotification(int $pendingId, string $entityType, string $action, array $data, array $authorizerIds, int $requestedBy): void
    {
        // Get requester name
        $stmt = $this->pdo->prepare("SELECT name FROM users WHERE id = ?");
        $stmt->execute([$requestedBy]);
        $requester = $stmt->fetch(PDO::FETCH_ASSOC);
        $requesterName = $requester['name'] ?? 'משתמש';

        // Use central config labels
        $entityLabel = ENTITY_LABELS[$entityType] ?? $entityType;
        $actionLabel = ACTION_LABELS[$action] ?? $action;

        $title = "דרוש אישור: {$actionLabel} {$entityLabel}";
        $body = "המשתמש {$requesterName} מבקש לבצע {$actionLabel} {$entityLabel}";

        // Add entity-specific details
        if ($entityType === 'purchases' && isset($data['price'])) {
            $body .= "\nמחיר: ₪" . number_format($data['price']);
        }

        try {
            $notificationService = NotificationService::getInstance($this->pdo);
            $notificationService->sendApproval(
                $authorizerIds,
                $title,
                $body,
                [
                    'level' => NotificationLevel::URGENT,
                    'expiresIn' => 48,
                    'approvalMessage' => 'נא לאשר או לדחות את הבקשה',
                    'createdBy' => $requestedBy,
                    'notifySender' => true,
                    'url' => "/dashboard/dashboards/cemeteries/notifications/entity-approve.php?id={$pendingId}"
                ]
            );
        } catch (Exception $e) {
            error_log("Failed to send approval notification: " . $e->getMessage());
        }
    }

    /**
     * Notify the requester about approval/rejection
     */
    private function notifyRequester(array $pending, string $status, int $responderId, ?string $reason = null): void
    {
        // Get responder name
        $stmt = $this->pdo->prepare("SELECT name FROM users WHERE id = ?");
        $stmt->execute([$responderId]);
        $responder = $stmt->fetch(PDO::FETCH_ASSOC);
        $responderName = $responder['name'] ?? 'מורשה חתימה';

        // Use central config labels
        $entityLabel = ENTITY_LABELS[$pending['entity_type']] ?? $pending['entity_type'];

        if ($status === 'approved') {
            $title = "הבקשה אושרה: {$entityLabel}";
            $body = "הבקשה שלך לביצוע {$pending['action']} {$entityLabel} אושרה על ידי {$responderName}";
            $level = NotificationLevel::INFO;
        } else {
            $title = "הבקשה נדחתה: {$entityLabel}";
            $body = "הבקשה שלך לביצוע {$pending['action']} {$entityLabel} נדחתה על ידי {$responderName}";
            if ($reason) {
                $body .= "\nסיבה: {$reason}";
            }
            $level = NotificationLevel::WARNING;
        }

        try {
            $notificationService = NotificationService::getInstance($this->pdo);
            $notificationService->sendInfo(
                [$pending['requested_by']],
                $title,
                $body,
                null
            );
        } catch (Exception $e) {
            error_log("Failed to send requester notification: " . $e->getMessage());
        }
    }

    /**
     * Process expired pending operations (for cron)
     */
    public function processExpiredOperations(): int
    {
        // שליפת הפעולות שעומדות לפוג
        $stmt = $this->pdo->query("
            SELECT id, entity_type, action, operation_data
            FROM pending_entity_operations
            WHERE status = 'pending' AND expires_at < NOW()
        ");
        $expiredOps = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $count = 0;
        foreach ($expiredOps as $op) {
            // עדכון סטטוס ל-expired
            $this->pdo->prepare("
                UPDATE pending_entity_operations
                SET status = 'expired', completed_at = NOW()
                WHERE id = ?
            ")->execute([$op['id']]);

            // Rollback סטטוס ישויות קשורות
            $this->rollbackRelatedEntityStatuses($op);

            $count++;
        }

        return $count;
    }

    /**
     * Rollback related entity statuses when operation is rejected/cancelled/expired
     */
    private function rollbackRelatedEntityStatuses(array $pending): void
    {
        $operationData = is_string($pending['operation_data'])
            ? json_decode($pending['operation_data'], true) ?? []
            : ($pending['operation_data'] ?? []);

        $entityType = $pending['entity_type'];
        $action = $pending['action'];

        if ($entityType === 'purchases' && $action === 'create') {
            // החזר קבר לפנוי (מ-4 ל-1)
            if (!empty($operationData['graveId'])) {
                $this->pdo->prepare("UPDATE graves SET graveStatus = 1 WHERE unicId = ? AND graveStatus = 4")
                          ->execute([$operationData['graveId']]);
            }
            // החזר לקוח לפעיל (מ-4 ל-1)
            if (!empty($operationData['clientId'])) {
                $this->pdo->prepare("UPDATE customers SET statusCustomer = 1 WHERE unicId = ? AND statusCustomer = 4")
                          ->execute([$operationData['clientId']]);
            }
        }

        if ($entityType === 'burials' && $action === 'create') {
            // החזר קבר לסטטוס קודם (מ-5 ל-2 אם יש רכישה, אחרת 1)
            if (!empty($operationData['graveId'])) {
                // בדוק אם יש קבורה פעילה - אם כן, לא להחזיר סטטוס
                $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM burials WHERE graveId = ? AND isActive = 1");
                $stmt->execute([$operationData['graveId']]);
                $hasBurial = $stmt->fetchColumn() > 0;

                if (!$hasBurial) {
                    // בדוק אם יש רכישה פעילה
                    $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM purchases WHERE graveId = ? AND isActive = 1");
                    $stmt->execute([$operationData['graveId']]);
                    $hasPurchase = $stmt->fetchColumn() > 0;

                    $newStatus = $hasPurchase ? 2 : 1;
                    $this->pdo->prepare("UPDATE graves SET graveStatus = ? WHERE unicId = ? AND graveStatus = 5")
                              ->execute([$newStatus, $operationData['graveId']]);
                }
            }
            // החזר לקוח לסטטוס קודם (מ-5 ל-2 אם יש רכישה, אחרת 1)
            if (!empty($operationData['clientId'])) {
                // בדוק אם הלקוח כבר נפטר (יש לו קבורה פעילה) - אם כן, לא להחזיר סטטוס
                $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM burials WHERE clientId = ? AND isActive = 1");
                $stmt->execute([$operationData['clientId']]);
                $hasBurial = $stmt->fetchColumn() > 0;

                if (!$hasBurial) {
                    // בדוק אם יש רכישה פעילה
                    $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM purchases WHERE clientId = ? AND isActive = 1");
                    $stmt->execute([$operationData['clientId']]);
                    $hasPurchase = $stmt->fetchColumn() > 0;

                    $newStatus = $hasPurchase ? 2 : 1;
                    $this->pdo->prepare("UPDATE customers SET statusCustomer = ? WHERE unicId = ? AND statusCustomer = 5")
                              ->execute([$newStatus, $operationData['clientId']]);
                }
            }
        }
    }
}
