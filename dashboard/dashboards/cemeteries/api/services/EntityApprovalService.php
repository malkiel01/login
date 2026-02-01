<?php
/**
 * Entity Approval Service
 * Handles approval workflow for entity operations (create/edit/delete)
 *
 * @version 1.0.0
 */

require_once __DIR__ . '/../../notifications/api/NotificationLogger.php';
require_once __DIR__ . '/../../../../../push/NotificationService.php';

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

        return [
            'success' => true,
            'message' => 'האישור נרשם',
            'currentApprovals' => $pending['current_approvals'],
            'requiredApprovals' => $pending['required_approvals'],
            'complete' => false
        ];
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
            $stmt = $this->pdo->prepare("
                SELECT COUNT(*) as pending_mandatory
                FROM pending_operation_approvals
                WHERE pending_id = ? AND is_mandatory = 1 AND status != 'approved'
            ");
            $stmt->execute([$pendingId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($result['pending_mandatory'] > 0) {
                return false;
            }
        }

        return true;
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

        // Execute the actual operation
        $result = $this->executeOperation($entityType, $action, $operationData, $pending['entity_id']);

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

        return [
            'success' => true,
            'message' => 'הפעולה בוטלה'
        ];
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
     */
    private function executeOperation(string $entityType, string $action, array $data, ?string $entityId): array
    {
        // This will call the appropriate API method based on entity type and action
        // For now, we'll implement basic logic - in production this should call the actual API methods

        switch ($entityType) {
            case 'purchases':
                return $this->executePurchaseOperation($action, $data, $entityId);
            case 'burials':
                return $this->executeBurialOperation($action, $data, $entityId);
            case 'customers':
                return $this->executeCustomerOperation($action, $data, $entityId);
            default:
                throw new Exception("Unknown entity type: $entityType");
        }
    }

    private function executePurchaseOperation(string $action, array $data, ?string $entityId): array
    {
        switch ($action) {
            case 'create':
                // Insert new purchase
                $unicId = uniqid('', true);
                $stmt = $this->pdo->prepare("
                    INSERT INTO purchases (unicId, clientId, graveId, contactId, price,
                                          numOfPayments, PaymentEndDate, paymentsList,
                                          purchaseStatus, buyer_status, comment, isActive, createDate)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, NOW())
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
                    $data['buyer_status'] ?? 1,
                    $data['comment'] ?? null
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
                foreach (['clientId', 'graveId', 'contactId', 'price', 'numOfPayments', 'PaymentEndDate', 'paymentsList', 'purchaseStatus', 'buyer_status', 'comment'] as $field) {
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

    private function executeBurialOperation(string $action, array $data, ?string $entityId): array
    {
        // Similar implementation for burials
        switch ($action) {
            case 'create':
                $unicId = uniqid('', true);
                $stmt = $this->pdo->prepare("
                    INSERT INTO burials (unicId, clientId, graveId, contactId, purchaseId,
                                        dateDeath, timeDeath, dateBurial, timeBurial, placeDeath,
                                        nationalInsuranceBurial, deathAbroad, comment, isActive, createDate)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, NOW())
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
                    $data['comment'] ?? null
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

    private function executeCustomerOperation(string $action, array $data, ?string $entityId): array
    {
        switch ($action) {
            case 'create':
                $unicId = uniqid('', true);
                $stmt = $this->pdo->prepare("
                    INSERT INTO customers (unicId, typeId, numId, firstName, lastName,
                                          phone, phoneMobile, email, gender, maritalStatus,
                                          nameFather, nameMother, countryId, cityId,
                                          residencyType, statusCustomer, association,
                                          isActive, createDate)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, NOW())
                ");
                $stmt->execute([
                    $unicId,
                    $data['typeId'] ?? 1,
                    $data['numId'] ?? '',
                    $data['firstName'] ?? '',
                    $data['lastName'] ?? '',
                    $data['phone'] ?? null,
                    $data['phoneMobile'] ?? null,
                    $data['email'] ?? null,
                    $data['gender'] ?? null,
                    $data['maritalStatus'] ?? null,
                    $data['nameFather'] ?? null,
                    $data['nameMother'] ?? null,
                    $data['countryId'] ?? null,
                    $data['cityId'] ?? null,
                    $data['residencyType'] ?? 1,
                    $data['statusCustomer'] ?? 1,
                    $data['association'] ?? 1
                ]);
                return ['entityId' => $unicId];

            case 'edit':
            case 'delete':
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

        // Entity type labels
        $entityLabels = [
            'purchases' => 'רכישה',
            'burials' => 'קבורה',
            'customers' => 'לקוח'
        ];

        // Action labels
        $actionLabels = [
            'create' => 'יצירת',
            'edit' => 'עריכת',
            'delete' => 'מחיקת'
        ];

        $entityLabel = $entityLabels[$entityType] ?? $entityType;
        $actionLabel = $actionLabels[$action] ?? $action;

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

        $entityLabels = [
            'purchases' => 'רכישה',
            'burials' => 'קבורה',
            'customers' => 'לקוח'
        ];
        $entityLabel = $entityLabels[$pending['entity_type']] ?? $pending['entity_type'];

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
        $stmt = $this->pdo->query("
            UPDATE pending_entity_operations
            SET status = 'expired', completed_at = NOW()
            WHERE status = 'pending' AND expires_at < NOW()
        ");

        return $stmt->rowCount();
    }
}
