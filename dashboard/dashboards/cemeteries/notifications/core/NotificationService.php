<?php
/**
 * NotificationService - Unified Notification Service
 *
 * This is the central service for all notification operations in the application.
 * It provides a unified API for sending push notifications to users via Web Push protocol.
 *
 * ARCHITECTURE:
 * - This file is the SINGLE SOURCE OF TRUTH for notification sending logic
 * - Located in: /dashboard/dashboards/cemeteries/notifications/core/
 * - Bridge files in /push/ directory redirect here for backward compatibility
 *
 * FEATURES:
 * - Send notifications to single user, multiple users, or all users
 * - Support for scheduled notifications (via cron)
 * - Approval-type notifications with accept/reject actions
 * - Feedback notifications to notify sender when recipient acts
 * - Comprehensive logging via NotificationLogger
 * - Automatic subscription cleanup for invalid endpoints
 *
 * USAGE EXAMPLE:
 * ```php
 * $service = NotificationService::getInstance();
 *
 * // Simple notification
 * $service->sendInfo([1, 2, 3], 'Title', 'Body', '/url');
 *
 * // Approval notification
 * $service->sendApproval([5], 'Approve Request', 'Please approve', [
 *     'notifySender' => true,
 *     'expiresIn' => 24
 * ]);
 * ```
 *
 * DATABASE TABLES:
 * - scheduled_notifications: Stores notification records and status
 * - push_subscriptions: User device subscriptions
 * - push_notifications: Delivery tracking for polling fallback
 * - notification_logs: Detailed event logging
 *
 * @package     Notifications
 * @subpackage  Core
 * @version     2.0.0
 * @since       1.0.0
 * @author      System
 * @see         WebPush For the underlying push protocol implementation
 * @see         NotificationLogger For event logging
 */

// Core notification service - located in /dashboard/dashboards/cemeteries/notifications/core/
require_once __DIR__ . '/../../../../../config.php';           // Root config
require_once __DIR__ . '/../../../../../push/config.php';      // VAPID keys config
require_once __DIR__ . '/WebPush.php';                         // Web Push protocol
require_once __DIR__ . '/push-log.php';                        // Push logging
require_once __DIR__ . '/NotificationLogger.php';              // Notification event logger

/**
 * NotificationType - Defines the types of notifications
 *
 * This class contains constants that define the semantic type of a notification.
 * The type affects how the notification is displayed and what actions are available.
 *
 * TYPES:
 * - INFO: Simple informational notification, no action required
 * - APPROVAL: Requires user to approve or reject, typically used for authorization
 * - FEEDBACK: Automatic feedback sent to original sender about recipient actions
 *
 * @package Notifications
 * @since   1.0.0
 */
class NotificationType {
    /** @var string Simple informational notification - no action required */
    const INFO = 'info';

    /** @var string Requires user approval/rejection - shows approve/reject buttons */
    const APPROVAL = 'approval';

    /** @var string Feedback notification about previous notification action */
    const FEEDBACK = 'feedback';
}

/**
 * NotificationLevel - Defines the priority/urgency level of notifications
 *
 * This class contains constants that define the priority level of a notification.
 * The level affects the notification's visual presentation and persistence.
 *
 * LEVELS:
 * - INFO: Normal priority, standard notification
 * - WARNING: Medium priority, may require attention
 * - URGENT: High priority, requires immediate attention
 *
 * @package Notifications
 * @since   1.0.0
 */
class NotificationLevel {
    /** @var string Normal priority - standard notification */
    const INFO = 'info';

    /** @var string Medium priority - may require attention */
    const WARNING = 'warning';

    /** @var string High priority - requires immediate attention */
    const URGENT = 'urgent';
}

/**
 * NotificationService - Main Service Class for Push Notifications
 *
 * This singleton class provides the complete API for sending push notifications.
 * It handles all aspects of notification delivery including:
 * - Creating notification records in the database
 * - Sending via Web Push protocol to user devices
 * - Tracking delivery status and logging events
 * - Processing scheduled notifications
 * - Managing approval-type notifications with feedback
 *
 * SINGLETON PATTERN:
 * Use getInstance() to get the shared instance. This ensures consistent
 * database connections and prevents multiple WebPush instances.
 *
 * @package Notifications
 * @since   1.0.0
 */
class NotificationService {
    /** @var PDO Database connection instance */
    private PDO $pdo;

    /** @var NotificationLogger Event logger instance */
    private NotificationLogger $logger;

    /** @var WebPush Web Push sender instance */
    private WebPush $webPush;

    /** @var NotificationService|null Singleton instance */
    private static ?NotificationService $instance = null;

    /**
     * Constructor - Initialize the notification service
     *
     * Creates a new NotificationService instance with database connection,
     * logger, and WebPush sender. Uses VAPID keys from push/config.php.
     *
     * @param PDO|null $pdo Optional database connection. If null, uses getDBConnection()
     */
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
     * Get singleton instance of NotificationService
     *
     * Returns the shared NotificationService instance, creating it if necessary.
     * Using the singleton pattern ensures consistent database connections and
     * prevents multiple WebPush instances from being created.
     *
     * @param PDO|null $pdo Optional database connection for first initialization
     * @return self The singleton NotificationService instance
     *
     * @example
     * ```php
     * $service = NotificationService::getInstance();
     * $service->sendInfo([1, 2], 'Hello', 'World');
     * ```
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
     * Create notification record in the scheduled_notifications table
     *
     * Creates a new database record for the notification with all parameters.
     * This record is used for tracking, scheduling, and delivery status.
     *
     * @param array $params Notification parameters:
     *                      - type: NotificationType constant
     *                      - level: NotificationLevel constant
     *                      - targets: array of user IDs
     *                      - title: string - notification title
     *                      - body: string - notification body
     *                      - url: string|null - click URL
     *                      - options: array - additional options
     *
     * @return int|null The new notification ID, or null on failure
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
            (title, body, notification_type, message_type, target_users, scheduled_at, url, status, created_by,
             requires_approval, approval_message, approval_expires_at, notify_sender)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $result = $stmt->execute([
            $title,
            $body,
            $level,        // notification_type = level (info/warning/urgent)
            $type,         // message_type = type (info/approval/feedback)
            json_encode($targets),
            $scheduledAt,
            $url,
            'pending',     // status - always starts as pending
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
     * Send notification to target users via Web Push
     *
     * Internal method that handles the actual sending of notifications.
     * Routes to sendToAll() or sendToUsers() based on targets,
     * then updates the notification status in the database.
     *
     * @param int         $notificationId The scheduled_notification record ID
     * @param string      $type           NotificationType constant (info/approval/feedback)
     * @param array       $targets        Array of user IDs, or ['all'] for all users
     * @param string      $title          Notification title
     * @param string      $body           Notification body message
     * @param string|null $url            Optional URL to open on click
     * @param string|null $icon           Optional custom icon URL
     * @param array       $options        Additional options (requiresApproval, etc.)
     *
     * @return array Result with keys: success, sent, failed, errors
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
     * Send push notification to a specific user
     *
     * Sends a push notification to all active subscriptions for the given user.
     * Each subscription represents a device/browser that the user has registered.
     * Handles logging, delivery tracking, and subscription cleanup.
     *
     * PROCESS:
     * 1. Fetch all active subscriptions for the user
     * 2. For each subscription, encrypt payload and send via Web Push
     * 3. Log delivery success/failure for each subscription
     * 4. Mark invalid subscriptions as inactive (404/410 errors)
     * 5. Create push_notifications record for polling fallback
     *
     * @param int         $userId   The target user ID
     * @param string      $title    Notification title (max ~50 chars recommended)
     * @param string      $body     Notification body message
     * @param string|null $url      URL to open when notification is clicked
     * @param string|null $icon     Custom icon URL (defaults to app icon)
     * @param array       $options  Additional options:
     *                              - id: notification ID
     *                              - scheduled_notification_id: linked notification
     *                              - requiresApproval: bool - show approval buttons
     *
     * @return array Result with keys:
     *               - success: bool - true if at least one delivery succeeded
     *               - sent: int - number of successful deliveries
     *               - failed: int - number of failed deliveries
     *               - errors: array - error messages from failures
     *
     * @example
     * ```php
     * $result = $service->sendToUser(123, 'Hello', 'You have a new message');
     * if ($result['success']) {
     *     echo "Sent to {$result['sent']} devices";
     * }
     * ```
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

            // Log with device info (for consistency with send-push.php)
            logPushSendResult(
                $userId,
                $subscription['id'],
                $subscription['endpoint'],
                $result['success'],
                $result['error'] ?? null,
                $subscription['user_agent'] ?? null
            );

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

        // Insert into push_notifications table for my-notifications page tracking
        if ($notificationId) {
            try {
                $insertStmt = $this->pdo->prepare("
                    INSERT INTO push_notifications (scheduled_notification_id, user_id, title, body, url, is_delivered, delivered_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ");
                $insertStmt->execute([
                    $notificationId,
                    $userId,
                    $title,
                    $body,
                    $url,
                    $sent > 0 ? 1 : 0,
                    $sent > 0 ? date('Y-m-d H:i:s') : null
                ]);
            } catch (Exception $e) {
                error_log("[NotificationService] Failed to insert push_notification for user $userId: " . $e->getMessage());
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
     * Send push notification to multiple users
     *
     * Iterates through the list of user IDs and sends the notification
     * to each user via sendToUser(). Aggregates the results.
     *
     * @param array       $userIds  Array of user IDs to notify
     * @param string      $title    Notification title
     * @param string      $body     Notification body message
     * @param string|null $url      URL to open when clicked
     * @param string|null $icon     Custom icon URL
     * @param array       $options  Additional options (passed to sendToUser)
     *
     * @return array Result with keys:
     *               - success: bool - true if at least one delivery succeeded
     *               - sent: int - total successful deliveries across all users
     *               - failed: int - total failed deliveries
     *               - results: array - per-user results keyed by user ID
     *
     * @see sendToUser() For individual user sending
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
     * Send push notification to all users with active subscriptions
     *
     * Queries all distinct user IDs with active push subscriptions
     * and sends the notification to each. Useful for broadcast messages.
     *
     * WARNING: Use sparingly - sends to ALL subscribed users.
     *
     * @param string      $title    Notification title
     * @param string      $body     Notification body message
     * @param string|null $url      URL to open when clicked
     * @param string|null $icon     Custom icon URL
     * @param array       $options  Additional options (passed to sendToUsers)
     *
     * @return array Result with keys:
     *               - success: bool - true if at least one delivery succeeded
     *               - sent: int - total successful deliveries
     *               - failed: int - total failed deliveries
     *               - error: string - error message if no subscribers
     *               - results: array - per-user results
     *
     * @see sendToUsers() For the underlying multi-user send
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
     * Send feedback notification to the original sender
     *
     * When a recipient views/approves/rejects a notification, this method
     * sends a feedback notification to the original sender (if notify_sender=true).
     *
     * @param int    $notificationId The original notification ID
     * @param int    $triggerUserId  The user who triggered the event (recipient)
     * @param string $eventType      Event type: 'viewed', 'approved', or 'rejected'
     *
     * @return bool True if feedback was sent successfully
     *
     * @see NotificationLogger::sendFeedbackToSender() For implementation details
     */
    public function sendFeedback(int $notificationId, int $triggerUserId, string $eventType): bool {
        return $this->logger->sendFeedbackToSender($notificationId, $triggerUserId, $eventType);
    }

    /**
     * Process pending scheduled notifications
     *
     * Called by cron job to send notifications that are due.
     * Queries scheduled_notifications table for pending items where
     * scheduled_at is null or in the past.
     *
     * PROCESS:
     * 1. Fetch up to 10 pending notifications
     * 2. For each, decode target users and determine type
     * 3. Send via sendNotification()
     * 4. Update status to 'sent' or 'failed'
     *
     * @return array Result with keys:
     *               - processed: int - number of notifications processed
     *               - results: array - per-notification results keyed by ID
     *
     * @example
     * ```php
     * // In cron job:
     * $service = NotificationService::getInstance();
     * $result = $service->processScheduled();
     * echo "Processed {$result['processed']} notifications";
     * ```
     */
    public function processScheduled(): array {
        $stmt = $this->pdo->query("
            SELECT * FROM scheduled_notifications
            WHERE status = 'pending'
            AND (scheduled_at IS NULL OR scheduled_at <= UTC_TIMESTAMP())
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

    // =========================================================================
    // QUICK SEND METHODS - Convenience wrappers for common notification types
    // =========================================================================

    /**
     * Send simple informational notification (INFO level)
     *
     * Convenience method for sending standard notifications.
     * Uses NotificationType::INFO and NotificationLevel::INFO.
     *
     * @param array       $targets Array of user IDs, or ['all'] for broadcast
     * @param string      $title   Notification title
     * @param string      $body    Notification body message
     * @param string|null $url     Optional URL to open on click
     *
     * @return array Result with success, sent, failed, notificationId
     *
     * @example
     * ```php
     * $service->sendInfo([1, 2, 3], 'Update', 'New features available!');
     * ```
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
     * Send warning notification (WARNING level)
     *
     * Convenience method for sending medium-priority notifications.
     * Uses NotificationType::INFO and NotificationLevel::WARNING.
     *
     * @param array       $targets Array of user IDs, or ['all'] for broadcast
     * @param string      $title   Notification title
     * @param string      $body    Notification body message
     * @param string|null $url     Optional URL to open on click
     *
     * @return array Result with success, sent, failed, notificationId
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
     * Send urgent notification (URGENT level)
     *
     * Convenience method for sending high-priority notifications.
     * Uses NotificationType::INFO and NotificationLevel::URGENT.
     * These notifications should be used sparingly for important alerts.
     *
     * @param array       $targets Array of user IDs, or ['all'] for broadcast
     * @param string      $title   Notification title
     * @param string      $body    Notification body message
     * @param string|null $url     Optional URL to open on click
     *
     * @return array Result with success, sent, failed, notificationId
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
     *
     * Sends a notification that requires user action (approve/reject).
     * Uses NotificationType::APPROVAL. The notification will show
     * approve/reject buttons and can optionally notify the sender
     * when the recipient responds.
     *
     * @param array  $targets Array of user IDs to request approval from
     * @param string $title   Notification title
     * @param string $body    Notification body message (the request)
     * @param array  $options Additional options:
     *                        - level: NotificationLevel (default: URGENT)
     *                        - url: string - custom approval page URL
     *                        - icon: string - custom icon URL
     *                        - notifySender: bool - notify sender on response
     *                        - approvalMessage: string - custom approval message
     *                        - expiresIn: int - hours until approval expires
     *                        - createdBy: int - sender user ID
     *
     * @return array Result with success, sent, failed, notificationId
     *
     * @example
     * ```php
     * $service->sendApproval([5], 'Access Request', 'User wants to access X', [
     *     'notifySender' => true,
     *     'expiresIn' => 24
     * ]);
     * ```
     */
    public function sendApproval(array $targets, string $title, string $body, array $options = []): array {
        return $this->send([
            'type' => NotificationType::APPROVAL,
            'level' => $options['level'] ?? NotificationLevel::URGENT,
            'targets' => $targets,
            'title' => $title,
            'body' => $body,
            'url' => $options['url'] ?? null,
            'icon' => $options['icon'] ?? null,
            'options' => $options
        ]);
    }
}

// =============================================================================
// BACKWARD COMPATIBILITY FUNCTIONS
// =============================================================================
// These standalone functions provide backward compatibility for code that
// uses the old function-based API. They delegate to the NotificationService.
// New code should use NotificationService::getInstance() directly.
// =============================================================================

if (!function_exists('sendPushToUser')) {
    /**
     * Send push notification to a single user (legacy function)
     *
     * @deprecated Use NotificationService::getInstance()->sendToUser() instead
     *
     * @param int         $userId  Target user ID
     * @param string      $title   Notification title
     * @param string      $body    Notification body
     * @param string|null $url     Click URL
     * @param string|null $icon    Custom icon
     * @param array       $options Additional options
     *
     * @return array Result with success, sent, failed
     */
    function sendPushToUser(int $userId, string $title, string $body, ?string $url = null, ?string $icon = null, array $options = []): array {
        return NotificationService::getInstance()->sendToUser($userId, $title, $body, $url, $icon, $options);
    }
}

if (!function_exists('sendPushToUsers')) {
    /**
     * Send push notification to multiple users (legacy function)
     *
     * @deprecated Use NotificationService::getInstance()->sendToUsers() instead
     *
     * @param array       $userIds Array of target user IDs
     * @param string      $title   Notification title
     * @param string      $body    Notification body
     * @param string|null $url     Click URL
     * @param array       $options Additional options
     *
     * @return array Result with success, sent, failed
     */
    function sendPushToUsers(array $userIds, string $title, string $body, ?string $url = null, array $options = []): array {
        return NotificationService::getInstance()->sendToUsers($userIds, $title, $body, $url, null, $options);
    }
}

if (!function_exists('sendPushToAll')) {
    /**
     * Send push notification to all subscribed users (legacy function)
     *
     * @deprecated Use NotificationService::getInstance()->sendToAll() instead
     *
     * @param string      $title   Notification title
     * @param string      $body    Notification body
     * @param string|null $url     Click URL
     * @param array       $options Additional options
     *
     * @return array Result with success, sent, failed
     */
    function sendPushToAll(string $title, string $body, ?string $url = null, array $options = []): array {
        return NotificationService::getInstance()->sendToAll($title, $body, $url, null, $options);
    }
}

if (!function_exists('processScheduledNotifications')) {
    /**
     * Process pending scheduled notifications (legacy function)
     *
     * @deprecated Use NotificationService::getInstance()->processScheduled() instead
     *
     * @return array Result with processed count and results
     */
    function processScheduledNotifications(): array {
        return NotificationService::getInstance()->processScheduled();
    }
}
