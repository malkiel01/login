<?php
/**
 * NotificationService - Unified Notification Service
 * Central service for all notification operations
 *
 * @version 1.0.0
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/WebPush.php';
require_once __DIR__ . '/push-log.php';
require_once __DIR__ . '/../dashboard/dashboards/cemeteries/notifications/api/NotificationLogger.php';

/**
 * Notification Types
 */
class NotificationType {
    const INFO = 'info';           // Simple informational notification
    const APPROVAL = 'approval';   // Requires user approval/rejection
    const FEEDBACK = 'feedback';   // Feedback about previous notification
}

/**
 * Notification Levels (Priority)
 */
class NotificationLevel {
    const INFO = 'info';           // Normal priority
    const WARNING = 'warning';     // Medium priority
    const URGENT = 'urgent';       // High priority - requires attention
}

/**
 * NotificationService - Main Service Class
 */
class NotificationService {
    private PDO $pdo;
    private NotificationLogger $logger;
    private WebPush $webPush;
    private static ?NotificationService $instance = null;

    public function __construct(?PDO $pdo = null) {
        $this->pdo = $pdo ?? getDBConnection();
        $this->logger = NotificationLogger::getInstance($this->pdo);
        $this->webPush = new WebPush(
            VAPID_PUBLIC_KEY,
            VAPID_PRIVATE_KEY_PEM,
            VAPID_SUBJECT
        );
    }

    /**
     * Get singleton instance
     */
    public static function getInstance(?PDO $pdo = null): self {
        if (self::$instance === null) {
            self::$instance = new self($pdo);
        }
        return self::$instance;
    }

    /**
     * Send notification - Main unified method
     *
     * @param array $params Notification parameters:
     *   - type: NotificationType::INFO|APPROVAL|FEEDBACK (default: INFO)
     *   - level: NotificationLevel::INFO|WARNING|URGENT (default: INFO)
     *   - targets: array of user IDs or ['all']
     *   - title: string
     *   - body: string
     *   - url: string|null (optional)
     *   - icon: string|null (optional)
     *   - options: array (optional)
     *       - notifySender: bool - send feedback to sender
     *       - approvalMessage: string - message for approval
     *       - expiresIn: int - hours until expiry
     *       - scheduledAt: string - datetime for scheduled send
     *       - createdBy: int - user who created this notification
     *
     * @return array Result with success, sent, failed, notificationId
     */
    public function send(array $params): array {
        // Validate required params
        if (empty($params['targets'])) {
            return ['success' => false, 'error' => 'No targets specified'];
        }
        if (empty($params['title'])) {
            return ['success' => false, 'error' => 'Title is required'];
        }
        if (empty($params['body'])) {
            return ['success' => false, 'error' => 'Body is required'];
        }

        // Extract parameters with defaults
        $type = $params['type'] ?? NotificationType::INFO;
        $level = $params['level'] ?? NotificationLevel::INFO;
        $targets = $params['targets'];
        $title = $params['title'];
        $body = $params['body'];
        $url = $params['url'] ?? null;
        $icon = $params['icon'] ?? null;
        $options = $params['options'] ?? [];

        // Create scheduled_notifications record
        $notificationId = $this->createNotificationRecord($params);

        if (!$notificationId) {
            return ['success' => false, 'error' => 'Failed to create notification record'];
        }

        // If scheduled, just save and return
        if (!empty($options['scheduledAt'])) {
            return [
                'success' => true,
                'notificationId' => $notificationId,
                'scheduled' => true,
                'scheduledAt' => $options['scheduledAt']
            ];
        }

        // Send immediately
        $result = $this->sendNotification($notificationId, $type, $targets, $title, $body, $url, $icon, $options);
        $result['notificationId'] = $notificationId;

        return $result;
    }

    /**
     * Create notification record in database
     */
    private function createNotificationRecord(array $params): ?int {
        $type = $params['type'] ?? NotificationType::INFO;
        $level = $params['level'] ?? NotificationLevel::INFO;
        $targets = $params['targets'];
        $title = $params['title'];
        $body = $params['body'];
        $url = $params['url'] ?? null;
        $options = $params['options'] ?? [];

        $requiresApproval = ($type === NotificationType::APPROVAL) ? 1 : 0;
        $notifySender = !empty($options['notifySender']) ? 1 : 0;
        $approvalMessage = $options['approvalMessage'] ?? null;
        $scheduledAt = $options['scheduledAt'] ?? null;
        $createdBy = $options['createdBy'] ?? ($_SESSION['user_id'] ?? 0);

        // Calculate expiry
        $approvalExpiresAt = null;
        if ($requiresApproval && !empty($options['expiresIn'])) {
            $approvalExpiresAt = date('Y-m-d H:i:s', strtotime("+{$options['expiresIn']} hours"));
        }

        $stmt = $this->pdo->prepare("
            INSERT INTO scheduled_notifications
            (title, body, notification_type, target_users, scheduled_at, url, status, created_by,
             requires_approval, approval_message, approval_expires_at, notify_sender)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $status = empty($scheduledAt) ? 'pending' : 'pending';

        $result = $stmt->execute([
            $title,
            $body,
            $level,  // notification_type in DB is actually the level
            json_encode($targets),
            $scheduledAt,
            $url,
            $status,
            $createdBy,
            $requiresApproval,
            $approvalMessage,
            $approvalExpiresAt,
            $notifySender
        ]);

        if (!$result) {
            return null;
        }

        $id = (int)$this->pdo->lastInsertId();

        // Log creation
        $this->logger->logCreated($id, $createdBy, [
            'type' => $type,
            'level' => $level,
            'targets' => $targets
        ]);

        return $id;
    }

    /**
     * Send notification to targets
     */
    private function sendNotification(int $notificationId, string $type, array $targets, string $title, string $body, ?string $url, ?string $icon, array $options): array {
        $requiresApproval = ($type === NotificationType::APPROVAL);

        // Build URL for approval notifications
        if ($requiresApproval && !$url) {
            $url = "/dashboard/dashboards/cemeteries/notifications/approve.php?id={$notificationId}";
        }

        $pushOptions = [
            'id' => $notificationId,
            'requiresApproval' => $requiresApproval,
            'scheduled_notification_id' => $notificationId,
            'type' => $type
        ];

        // Send to targets
        if (in_array('all', $targets)) {
            $result = $this->sendToAll($title, $body, $url, $icon, $pushOptions);
        } else {
            $result = $this->sendToUsers($targets, $title, $body, $url, $icon, $pushOptions);
        }

        // Update notification status
        $status = $result['sent'] > 0 ? 'sent' : 'failed';
        $errorMessage = $result['sent'] === 0 ? json_encode($result['errors'] ?? []) : null;

        $this->pdo->prepare("
            UPDATE scheduled_notifications
            SET status = ?, sent_at = NOW(), error_message = ?
            WHERE id = ?
        ")->execute([$status, $errorMessage, $notificationId]);

        return $result;
    }

    /**
     * Send to specific user
     */
    public function sendToUser(int $userId, string $title, string $body, ?string $url = null, ?string $icon = null, array $options = []): array {
        pushLog('SEND', "Starting sendToUser", ['userId' => $userId, 'title' => $title]);

        $notificationId = $options['id'] ?? $options['scheduled_notification_id'] ?? null;

        // Get active subscriptions
        $stmt = $this->pdo->prepare("
            SELECT * FROM push_subscriptions
            WHERE user_id = ? AND is_active = 1
        ");
        $stmt->execute([$userId]);
        $subscriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($subscriptions)) {
            return ['success' => false, 'error' => 'No subscriptions found', 'sent' => 0];
        }

        $requiresApproval = !empty($options['requiresApproval']);

        $payload = json_encode([
            'title' => $title,
            'body' => $body,
            'icon' => $icon ?? '/pwa/icons/android/android-launchericon-192-192.png',
            'badge' => '/pwa/icons/android/android-launchericon-72-72.png',
            'url' => $url ?? '/dashboard/',
            'id' => $notificationId,
            'requiresApproval' => $requiresApproval,
            'requireInteraction' => $requiresApproval,
            'timestamp' => time()
        ]);

        $sent = 0;
        $failed = 0;
        $errors = [];

        foreach ($subscriptions as $subscription) {
            // Log send attempt
            if ($notificationId) {
                $this->logger->logSendAttempt(
                    $notificationId,
                    $userId,
                    $subscription['id'],
                    $subscription['endpoint'],
                    $subscription['user_agent'] ?? null
                );
            }

            $result = $this->webPush->send($subscription, $payload);

            if ($result['success']) {
                $sent++;
                $this->pdo->prepare("UPDATE push_subscriptions SET last_used_at = NOW() WHERE id = ?")->execute([$subscription['id']]);

                if ($notificationId) {
                    $this->logger->logDelivered($notificationId, $userId, $subscription['id'], $result['httpCode'] ?? 201);
                }
            } else {
                $failed++;
                $errors[] = $result['error'] ?? 'Unknown error';

                if ($notificationId) {
                    $this->logger->logFailed(
                        $notificationId,
                        $userId,
                        $result['error'] ?? 'Unknown error',
                        $result['httpCode'] ?? null
                    );
                }

                // Mark invalid subscriptions as inactive
                if (isset($result['httpCode']) && in_array($result['httpCode'], [404, 410])) {
                    $this->pdo->prepare("UPDATE push_subscriptions SET is_active = 0 WHERE id = ?")->execute([$subscription['id']]);
                    $this->logger->logSubscriptionRemoved($userId, $subscription['id'], 'HTTP ' . $result['httpCode']);
                }
            }
        }

        return [
            'success' => $sent > 0,
            'sent' => $sent,
            'failed' => $failed,
            'errors' => $errors
        ];
    }

    /**
     * Send to multiple users
     */
    public function sendToUsers(array $userIds, string $title, string $body, ?string $url = null, ?string $icon = null, array $options = []): array {
        $totalSent = 0;
        $totalFailed = 0;
        $results = [];

        foreach ($userIds as $userId) {
            $result = $this->sendToUser($userId, $title, $body, $url, $icon, $options);
            $totalSent += $result['sent'];
            $totalFailed += $result['failed'];
            $results[$userId] = $result;
        }

        return [
            'success' => $totalSent > 0,
            'sent' => $totalSent,
            'failed' => $totalFailed,
            'results' => $results
        ];
    }

    /**
     * Send to all active users
     */
    public function sendToAll(string $title, string $body, ?string $url = null, ?string $icon = null, array $options = []): array {
        $stmt = $this->pdo->query("
            SELECT DISTINCT user_id FROM push_subscriptions WHERE is_active = 1
        ");
        $userIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

        if (empty($userIds)) {
            return ['success' => false, 'error' => 'No subscribed users', 'sent' => 0];
        }

        return $this->sendToUsers($userIds, $title, $body, $url, $icon, $options);
    }

    /**
     * Send feedback to notification sender
     */
    public function sendFeedback(int $notificationId, int $triggerUserId, string $eventType): bool {
        return $this->logger->sendFeedbackToSender($notificationId, $triggerUserId, $eventType);
    }

    /**
     * Process scheduled notifications (for cron)
     */
    public function processScheduled(): array {
        $stmt = $this->pdo->query("
            SELECT * FROM scheduled_notifications
            WHERE status = 'pending'
            AND (scheduled_at IS NULL OR scheduled_at <= NOW())
            ORDER BY created_at ASC
            LIMIT 10
        ");
        $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $processed = 0;
        $results = [];

        foreach ($notifications as $notification) {
            $targetUsers = json_decode($notification['target_users'], true);
            $requiresApproval = !empty($notification['requires_approval']);
            $type = $requiresApproval ? NotificationType::APPROVAL : NotificationType::INFO;

            $url = $notification['url'];
            if ($requiresApproval) {
                $url = "/dashboard/dashboards/cemeteries/notifications/approve.php?id={$notification['id']}";
            }

            $result = $this->sendNotification(
                $notification['id'],
                $type,
                $targetUsers,
                $notification['title'],
                $notification['body'],
                $url,
                null,
                ['requiresApproval' => $requiresApproval]
            );

            $processed++;
            $results[$notification['id']] = $result;
        }

        return [
            'processed' => $processed,
            'results' => $results
        ];
    }

    /**
     * Quick send methods for convenience
     */

    /**
     * Send simple info notification
     */
    public function sendInfo(array $targets, string $title, string $body, ?string $url = null): array {
        return $this->send([
            'type' => NotificationType::INFO,
            'level' => NotificationLevel::INFO,
            'targets' => $targets,
            'title' => $title,
            'body' => $body,
            'url' => $url
        ]);
    }

    /**
     * Send warning notification
     */
    public function sendWarning(array $targets, string $title, string $body, ?string $url = null): array {
        return $this->send([
            'type' => NotificationType::INFO,
            'level' => NotificationLevel::WARNING,
            'targets' => $targets,
            'title' => $title,
            'body' => $body,
            'url' => $url
        ]);
    }

    /**
     * Send urgent notification
     */
    public function sendUrgent(array $targets, string $title, string $body, ?string $url = null): array {
        return $this->send([
            'type' => NotificationType::INFO,
            'level' => NotificationLevel::URGENT,
            'targets' => $targets,
            'title' => $title,
            'body' => $body,
            'url' => $url
        ]);
    }

    /**
     * Send approval request notification
     */
    public function sendApproval(array $targets, string $title, string $body, array $options = []): array {
        return $this->send([
            'type' => NotificationType::APPROVAL,
            'level' => $options['level'] ?? NotificationLevel::URGENT,
            'targets' => $targets,
            'title' => $title,
            'body' => $body,
            'options' => $options
        ]);
    }
}

// Backward compatibility - keep old functions working
if (!function_exists('sendPushToUser')) {
    function sendPushToUser(int $userId, string $title, string $body, ?string $url = null, ?string $icon = null, array $options = []): array {
        return NotificationService::getInstance()->sendToUser($userId, $title, $body, $url, $icon, $options);
    }
}

if (!function_exists('sendPushToUsers')) {
    function sendPushToUsers(array $userIds, string $title, string $body, ?string $url = null, array $options = []): array {
        return NotificationService::getInstance()->sendToUsers($userIds, $title, $body, $url, null, $options);
    }
}

if (!function_exists('sendPushToAll')) {
    function sendPushToAll(string $title, string $body, ?string $url = null, array $options = []): array {
        return NotificationService::getInstance()->sendToAll($title, $body, $url, null, $options);
    }
}

if (!function_exists('processScheduledNotifications')) {
    function processScheduledNotifications(): array {
        return NotificationService::getInstance()->processScheduled();
    }
}
