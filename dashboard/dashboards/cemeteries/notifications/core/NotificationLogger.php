<?php
/**
 * NotificationLogger - Comprehensive Notification Event Logging System
 *
 * This class provides detailed logging for all notification-related events,
 * enabling monitoring, debugging, analytics, and audit trails.
 *
 * PURPOSE:
 * - Track the full lifecycle of notifications (created → sent → delivered → read)
 * - Record delivery attempts, successes, and failures with error details
 * - Log user interactions (viewed, clicked, approved, rejected)
 * - Support analytics with device/browser breakdowns
 * - Enable debugging with detailed error logging
 *
 * EVENT TYPES LOGGED:
 * - created: Notification created in system
 * - send_attempt: Push send attempted
 * - delivered: Successfully delivered to push service
 * - failed: Delivery failed (with error details)
 * - retry: Retry attempt made
 * - viewed: User viewed notification
 * - clicked: User clicked notification
 * - read: Marked as read
 * - scheduled: Scheduled for future delivery
 * - approval_sent: Approval request sent
 * - approved: User approved
 * - rejected: User rejected
 * - expired: Approval expired
 * - subscription_created: New push subscription registered
 * - subscription_removed: Subscription removed/expired
 * - test_started: Test run started
 * - test_completed: Test run completed
 * - feedback_*: Feedback notifications sent
 *
 * DATABASE TABLE:
 * Uses `notification_logs` table with indexes for efficient querying.
 * Create via migration: sql/notification_logs.sql
 *
 * USAGE:
 * ```php
 * $logger = NotificationLogger::getInstance();
 * $logger->logCreated($notificationId, $userId, $notification);
 * $logger->logDelivered($notificationId, $userId);
 * ```
 *
 * @package     Notifications
 * @subpackage  Core
 * @version     1.1.0 - Now uses UserAgentParser utility
 * @since       1.0.0
 * @created     2026-01-29
 * @see         UserAgentParser For User-Agent parsing
 */

// Load shared User-Agent parser utility (same directory)
require_once __DIR__ . '/UserAgentParser.php';

/**
 * NotificationLogger - Singleton class for notification event logging
 *
 * Provides methods for logging all notification events with device info,
 * error tracking, and statistics generation.
 *
 * SINGLETON PATTERN:
 * Use getInstance() to get the shared instance for consistent database
 * connections across the application.
 *
 * @package Notifications
 * @since   1.0.0
 */
class NotificationLogger {
    /** @var PDO Database connection instance */
    private PDO $conn;

    /** @var NotificationLogger|null Singleton instance */
    private static ?NotificationLogger $instance = null;

    /**
     * Constructor - Initialize logger with database connection
     *
     * @param PDO|null $conn Optional database connection. If null, calls getDBConnection()
     *
     * @note The table should be created via migration, not auto-created,
     *       to prevent implicit commits during active transactions.
     */
    public function __construct(?PDO $conn = null) {
        if ($conn) {
            $this->conn = $conn;
        } else {
            require_once __DIR__ . '/../config.php';
            $this->conn = getDBConnection();
        }
        // Note: ensureTableExists() removed to prevent implicit commit during active transactions
        // The table should be created via migration: sql/notification_logs.sql
    }

    /**
     * Get singleton instance of NotificationLogger
     *
     * Returns the shared logger instance, creating it if necessary.
     *
     * @param PDO|null $conn Optional database connection for first initialization
     *
     * @return self The singleton NotificationLogger instance
     */
    public static function getInstance(?PDO $conn = null): self {
        if (self::$instance === null) {
            self::$instance = new self($conn);
        }
        return self::$instance;
    }

    /**
     * Log a notification event to the database
     *
     * Core logging method used by all specific log* methods.
     * Inserts a record into notification_logs with all available data.
     *
     * @param string $eventType Event type identifier (e.g., 'created', 'delivered')
     * @param array  $data      Event data with optional keys:
     *                          - notification_id: int
     *                          - user_id: int
     *                          - subscription_id: int
     *                          - notification_title: string
     *                          - notification_body: string
     *                          - notification_type: string
     *                          - device_type: string
     *                          - os: string
     *                          - browser: string
     *                          - user_agent: string
     *                          - ip_address: string
     *                          - push_endpoint: string
     *                          - http_status: int
     *                          - error_code: string
     *                          - error_message: string
     *                          - extra_data: array (will be JSON encoded)
     *                          - test_run_id: string
     *
     * @return int The new log entry ID, or 0 on failure
     */
    public function log(string $eventType, array $data = []): int {
        $sql = "INSERT INTO notification_logs (
            notification_id, user_id, subscription_id, event_type,
            notification_title, notification_body, notification_type,
            device_type, os, browser, user_agent, ip_address,
            push_endpoint, http_status, error_code, error_message,
            extra_data, test_run_id
        ) VALUES (
            :notification_id, :user_id, :subscription_id, :event_type,
            :notification_title, :notification_body, :notification_type,
            :device_type, :os, :browser, :user_agent, :ip_address,
            :push_endpoint, :http_status, :error_code, :error_message,
            :extra_data, :test_run_id
        )";

        try {
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([
                ':notification_id' => $data['notification_id'] ?? null,
                ':user_id' => $data['user_id'] ?? null,
                ':subscription_id' => $data['subscription_id'] ?? null,
                ':event_type' => $eventType,
                ':notification_title' => $data['notification_title'] ?? null,
                ':notification_body' => $data['notification_body'] ?? null,
                ':notification_type' => $data['notification_type'] ?? null,
                ':device_type' => $data['device_type'] ?? null,
                ':os' => $data['os'] ?? null,
                ':browser' => $data['browser'] ?? null,
                ':user_agent' => $data['user_agent'] ?? null,
                ':ip_address' => $data['ip_address'] ?? ($_SERVER['REMOTE_ADDR'] ?? null),
                ':push_endpoint' => $data['push_endpoint'] ?? null,
                ':http_status' => $data['http_status'] ?? null,
                ':error_code' => $data['error_code'] ?? null,
                ':error_message' => $data['error_message'] ?? null,
                ':extra_data' => isset($data['extra_data']) ? json_encode($data['extra_data']) : null,
                ':test_run_id' => $data['test_run_id'] ?? null
            ]);
            return (int) $this->conn->lastInsertId();
        } catch (PDOException $e) {
            error_log("[NotificationLogger] Failed to log: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Log notification creation event
     *
     * Called when a new notification is created in scheduled_notifications.
     * Records creator info, notification content, and target users.
     *
     * @param int   $notificationId The new notification ID
     * @param int   $creatorId      User ID who created the notification
     * @param array $notification   Notification data with title, body, type, targets
     *
     * @return int The log entry ID
     */
    public function logCreated(int $notificationId, int $creatorId, array $notification): int {
        $deviceInfo = $this->parseUserAgent();
        return $this->log('created', [
            'notification_id' => $notificationId,
            'user_id' => $creatorId,
            'notification_title' => $notification['title'] ?? null,
            'notification_body' => $notification['body'] ?? null,
            'notification_type' => $notification['notification_type'] ?? null,
            'device_type' => $deviceInfo['device'],
            'os' => $deviceInfo['os'],
            'browser' => $deviceInfo['browser'],
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
            'extra_data' => [
                'target_users' => $notification['target_users'] ?? null,
                'scheduled_at' => $notification['scheduled_at'] ?? null,
                'requires_approval' => $notification['requires_approval'] ?? false
            ]
        ]);
    }

    /**
     * Log send attempt
     */
    public function logSendAttempt(int $notificationId, int $userId, ?int $subscriptionId, ?string $endpoint, ?string $userAgent = null): int {
        $deviceInfo = $this->parseUserAgent($userAgent);
        return $this->log('send_attempt', [
            'notification_id' => $notificationId,
            'user_id' => $userId,
            'subscription_id' => $subscriptionId,
            'push_endpoint' => $endpoint ? substr($endpoint, 0, 500) : null,
            'device_type' => $deviceInfo['device'],
            'os' => $deviceInfo['os'],
            'browser' => $deviceInfo['browser'],
            'user_agent' => $userAgent
        ]);
    }

    /**
     * Log successful delivery
     */
    public function logDelivered(int $notificationId, int $userId, ?int $subscriptionId = null, ?int $httpStatus = 201): int {
        return $this->log('delivered', [
            'notification_id' => $notificationId,
            'user_id' => $userId,
            'subscription_id' => $subscriptionId,
            'http_status' => $httpStatus
        ]);
    }

    /**
     * Log failed delivery
     */
    public function logFailed(int $notificationId, int $userId, ?string $errorMessage, ?int $httpStatus = null, ?string $errorCode = null): int {
        return $this->log('failed', [
            'notification_id' => $notificationId,
            'user_id' => $userId,
            'http_status' => $httpStatus,
            'error_code' => $errorCode,
            'error_message' => $errorMessage
        ]);
    }

    /**
     * Log retry attempt
     */
    public function logRetry(int $notificationId, int $userId, int $retryCount, ?string $reason = null): int {
        return $this->log('retry', [
            'notification_id' => $notificationId,
            'user_id' => $userId,
            'extra_data' => [
                'retry_count' => $retryCount,
                'reason' => $reason
            ]
        ]);
    }

    /**
     * Log notification viewed
     */
    public function logViewed(int $notificationId, int $userId, ?string $userAgent = null): int {
        $ua = $userAgent ?? ($_SERVER['HTTP_USER_AGENT'] ?? null);
        $deviceInfo = $this->parseUserAgent($ua);
        return $this->log('viewed', [
            'notification_id' => $notificationId,
            'user_id' => $userId,
            'device_type' => $deviceInfo['device'],
            'os' => $deviceInfo['os'],
            'browser' => $deviceInfo['browser'],
            'user_agent' => $ua
        ]);
    }

    /**
     * Log notification clicked
     */
    public function logClicked(int $notificationId, int $userId): int {
        $deviceInfo = $this->parseUserAgent();
        return $this->log('clicked', [
            'notification_id' => $notificationId,
            'user_id' => $userId,
            'device_type' => $deviceInfo['device'],
            'os' => $deviceInfo['os'],
            'browser' => $deviceInfo['browser'],
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null
        ]);
    }

    /**
     * Log notification marked as read
     */
    public function logRead(int $notificationId, int $userId): int {
        return $this->log('read', [
            'notification_id' => $notificationId,
            'user_id' => $userId
        ]);
    }

    /**
     * Log notification scheduled
     */
    public function logScheduled(int $notificationId, string $title, string $scheduledAt): int {
        return $this->log('scheduled', [
            'notification_id' => $notificationId,
            'notification_title' => $title,
            'extra_data' => [
                'scheduled_at' => $scheduledAt
            ]
        ]);
    }

    /**
     * Log approval notification sent
     */
    public function logApprovalSent(int $notificationId, int $userId, ?string $expiresAt = null): int {
        return $this->log('approval_sent', [
            'notification_id' => $notificationId,
            'user_id' => $userId,
            'extra_data' => [
                'expires_at' => $expiresAt
            ]
        ]);
    }

    /**
     * Log user approved notification
     */
    public function logApproved(int $notificationId, int $userId, bool $biometric = false, ?string $userAgent = null): int {
        $ua = $userAgent ?? ($_SERVER['HTTP_USER_AGENT'] ?? null);
        $deviceInfo = $this->parseUserAgent($ua);
        return $this->log('approved', [
            'notification_id' => $notificationId,
            'user_id' => $userId,
            'device_type' => $deviceInfo['device'],
            'os' => $deviceInfo['os'],
            'browser' => $deviceInfo['browser'],
            'user_agent' => $ua,
            'extra_data' => [
                'biometric_verified' => $biometric
            ]
        ]);
    }

    /**
     * Log user rejected notification
     */
    public function logRejected(int $notificationId, int $userId, ?string $userAgent = null): int {
        $ua = $userAgent ?? ($_SERVER['HTTP_USER_AGENT'] ?? null);
        $deviceInfo = $this->parseUserAgent($ua);
        return $this->log('rejected', [
            'notification_id' => $notificationId,
            'user_id' => $userId,
            'device_type' => $deviceInfo['device'],
            'os' => $deviceInfo['os'],
            'browser' => $deviceInfo['browser'],
            'user_agent' => $ua
        ]);
    }

    /**
     * Log approval expired
     */
    public function logExpired(int $notificationId, int $userId): int {
        return $this->log('expired', [
            'notification_id' => $notificationId,
            'user_id' => $userId
        ]);
    }

    /**
     * Log subscription created
     */
    public function logSubscriptionCreated(int $userId, int $subscriptionId, string $endpoint): int {
        $deviceInfo = $this->parseUserAgent();
        return $this->log('subscription_created', [
            'user_id' => $userId,
            'subscription_id' => $subscriptionId,
            'push_endpoint' => substr($endpoint, 0, 500),
            'device_type' => $deviceInfo['device'],
            'os' => $deviceInfo['os'],
            'browser' => $deviceInfo['browser'],
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null
        ]);
    }

    /**
     * Log subscription removed
     */
    public function logSubscriptionRemoved(int $userId, ?int $subscriptionId = null, ?string $reason = null): int {
        return $this->log('subscription_removed', [
            'user_id' => $userId,
            'subscription_id' => $subscriptionId,
            'extra_data' => [
                'reason' => $reason
            ]
        ]);
    }

    /**
     * Log test started
     */
    public function logTestStarted(string $testRunId, int $creatorId, array $config = []): int {
        return $this->log('test_started', [
            'user_id' => $creatorId,
            'test_run_id' => $testRunId,
            'extra_data' => $config
        ]);
    }

    /**
     * Log test completed
     */
    public function logTestCompleted(string $testRunId, array $results = []): int {
        return $this->log('test_completed', [
            'test_run_id' => $testRunId,
            'extra_data' => $results
        ]);
    }

    /**
     * Parse User-Agent string to extract device information
     *
     * Uses the shared UserAgentParser utility for consistent parsing
     * across all notification system components.
     *
     * @param string|null $ua User-Agent string (defaults to $_SERVER['HTTP_USER_AGENT'])
     *
     * @return array Device info with keys:
     *               - device: 'iPhone'|'iPad'|'Android Phone'|'Android Tablet'|'Desktop'|'Unknown'
     *               - os: 'iOS'|'Android'|'Windows'|'macOS'|'Linux'|'Unknown'
     *               - browser: 'Chrome'|'Safari'|'Firefox'|'Edge'|'Unknown'
     *
     * @see UserAgentParser::parse() For the underlying implementation
     */
    public function parseUserAgent(?string $ua = null): array {
        return UserAgentParser::parse($ua);
    }

    /**
     * Retrieve log entries with optional filters
     *
     * Queries notification_logs table with flexible filtering options.
     * Results are sorted by created_at DESC (newest first).
     *
     * @param array $filters Optional filters:
     *                       - notification_id: int
     *                       - user_id: int
     *                       - event_type: string
     *                       - test_run_id: string
     *                       - date_from: string (Y-m-d H:i:s)
     *                       - date_to: string (Y-m-d H:i:s)
     * @param int   $limit   Maximum records to return (default: 50)
     * @param int   $offset  Number of records to skip (for pagination)
     *
     * @return array Array of log entries as associative arrays
     */
    public function getLogs(array $filters = [], int $limit = 50, int $offset = 0): array {
        $where = ['1=1'];
        $params = [];

        if (!empty($filters['notification_id'])) {
            $where[] = 'notification_id = :notification_id';
            $params[':notification_id'] = $filters['notification_id'];
        }

        if (!empty($filters['user_id'])) {
            $where[] = 'user_id = :user_id';
            $params[':user_id'] = $filters['user_id'];
        }

        if (!empty($filters['event_type'])) {
            $where[] = 'event_type = :event_type';
            $params[':event_type'] = $filters['event_type'];
        }

        if (!empty($filters['test_run_id'])) {
            $where[] = 'test_run_id = :test_run_id';
            $params[':test_run_id'] = $filters['test_run_id'];
        }

        if (!empty($filters['date_from'])) {
            $where[] = 'created_at >= :date_from';
            $params[':date_from'] = $filters['date_from'];
        }

        if (!empty($filters['date_to'])) {
            $where[] = 'created_at <= :date_to';
            $params[':date_to'] = $filters['date_to'];
        }

        $whereClause = implode(' AND ', $where);

        $sql = "SELECT * FROM notification_logs
                WHERE $whereClause
                ORDER BY created_at DESC
                LIMIT " . (int)$limit . " OFFSET " . (int)$offset;

        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get log count with filters
     */
    public function getLogCount(array $filters = []): int {
        $where = ['1=1'];
        $params = [];

        if (!empty($filters['notification_id'])) {
            $where[] = 'notification_id = :notification_id';
            $params[':notification_id'] = $filters['notification_id'];
        }

        if (!empty($filters['user_id'])) {
            $where[] = 'user_id = :user_id';
            $params[':user_id'] = $filters['user_id'];
        }

        if (!empty($filters['event_type'])) {
            $where[] = 'event_type = :event_type';
            $params[':event_type'] = $filters['event_type'];
        }

        if (!empty($filters['test_run_id'])) {
            $where[] = 'test_run_id = :test_run_id';
            $params[':test_run_id'] = $filters['test_run_id'];
        }

        if (!empty($filters['date_from'])) {
            $where[] = 'created_at >= :date_from';
            $params[':date_from'] = $filters['date_from'];
        }

        if (!empty($filters['date_to'])) {
            $where[] = 'created_at <= :date_to';
            $params[':date_to'] = $filters['date_to'];
        }

        $whereClause = implode(' AND ', $where);

        $sql = "SELECT COUNT(*) FROM notification_logs WHERE $whereClause";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Get notification statistics for a time period
     *
     * Calculates aggregate statistics including event counts,
     * delivery rates, approval rates, and device breakdowns.
     *
     * @param string $period Time period: '1h', '24h', '7d', or '30d'
     *
     * @return array Statistics with keys:
     *               - period: string - The requested period
     *               - total_events: int - Total logged events
     *               - event_counts: array - Count per event type
     *               - delivery_rate: float - % of sends that delivered
     *               - approval_rate: float - % of approvals approved
     *               - device_breakdown: array - Count per device type
     */
    public function getStats(string $period = '24h'): array {
        $periodMap = [
            '1h' => 'DATE_SUB(NOW(), INTERVAL 1 HOUR)',
            '24h' => 'DATE_SUB(NOW(), INTERVAL 24 HOUR)',
            '7d' => 'DATE_SUB(NOW(), INTERVAL 7 DAY)',
            '30d' => 'DATE_SUB(NOW(), INTERVAL 30 DAY)'
        ];

        $fromDate = $periodMap[$period] ?? $periodMap['24h'];

        // Event counts
        $sql = "SELECT event_type, COUNT(*) as count
                FROM notification_logs
                WHERE created_at >= $fromDate
                GROUP BY event_type";
        $stmt = $this->conn->query($sql);
        $eventCounts = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $eventCounts[$row['event_type']] = (int) $row['count'];
        }

        // Calculate delivery rate
        $sendAttempts = $eventCounts['send_attempt'] ?? 0;
        $delivered = $eventCounts['delivered'] ?? 0;
        $deliveryRate = $sendAttempts > 0 ? round(($delivered / $sendAttempts) * 100, 2) : 0;

        // Approval rate
        $approvalSent = $eventCounts['approval_sent'] ?? 0;
        $approved = $eventCounts['approved'] ?? 0;
        $rejected = $eventCounts['rejected'] ?? 0;
        $approvalRate = $approvalSent > 0 ? round(($approved / $approvalSent) * 100, 2) : 0;

        // Device breakdown
        $sql = "SELECT device_type, COUNT(*) as count
                FROM notification_logs
                WHERE created_at >= $fromDate AND device_type IS NOT NULL
                GROUP BY device_type";
        $stmt = $this->conn->query($sql);
        $deviceBreakdown = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $deviceBreakdown[$row['device_type']] = (int) $row['count'];
        }

        return [
            'period' => $period,
            'total_events' => array_sum($eventCounts),
            'event_counts' => $eventCounts,
            'delivery_rate' => $deliveryRate,
            'approval_rate' => $approvalRate,
            'device_breakdown' => $deviceBreakdown
        ];
    }

    /**
     * Get the complete timeline/history for a specific notification
     *
     * Returns all log entries for a notification in chronological order,
     * including user names for display. Useful for debugging and auditing.
     *
     * @param int $notificationId The notification ID to get timeline for
     *
     * @return array Array of log entries with user_name included
     */
    public function getTimeline(int $notificationId): array {
        $sql = "SELECT nl.*, u.name as user_name
                FROM notification_logs nl
                LEFT JOIN users u ON u.id = nl.user_id
                WHERE nl.notification_id = :notification_id
                ORDER BY nl.created_at ASC";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':notification_id' => $notificationId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Delete old log entries to maintain database size
     *
     * Removes log entries older than the specified number of days.
     * Should be called periodically via cron job.
     *
     * @param int $daysToKeep Number of days of logs to retain (default: 30)
     *
     * @return int Number of rows deleted
     */
    public function cleanOldLogs(int $daysToKeep = 30): int {
        $sql = "DELETE FROM notification_logs
                WHERE created_at < DATE_SUB(NOW(), INTERVAL :days DAY)";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':days' => $daysToKeep]);
        return $stmt->rowCount();
    }

    /**
     * Send feedback notification to the original sender
     *
     * When a notification has notify_sender=true and a recipient interacts
     * with it (views, approves, or rejects), this method sends a push
     * notification back to the original sender informing them of the action.
     *
     * PROCESS:
     * 1. Check if feedback was already sent (prevent duplicates)
     * 2. Fetch original notification and check notify_sender flag
     * 3. Build appropriate feedback message based on event type
     * 4. Log the feedback event
     * 5. Send push notification to sender
     *
     * FEEDBACK MESSAGES (Hebrew):
     * - viewed: "ההודעה שלך נצפתה" (Your message was viewed)
     * - approved: "ההודעה שלך אושרה ✓" (Your message was approved)
     * - rejected: "ההודעה שלך נדחתה ✗" (Your message was rejected)
     *
     * @param int    $notificationId The scheduled_notification ID
     * @param int    $userId         The user who triggered the event (recipient)
     * @param string $eventType      Event type: 'viewed', 'approved', or 'rejected'
     *
     * @return bool True if feedback was sent, false if skipped or failed
     */
    public function sendFeedbackToSender(int $notificationId, int $userId, string $eventType): bool {
        // Check if feedback was already sent for this event (prevent duplicates)
        $feedbackEventType = 'feedback_' . $eventType;
        $stmt = $this->conn->prepare("
            SELECT COUNT(*) FROM notification_logs
            WHERE notification_id = :notification_id
              AND user_id = :user_id
              AND event_type = :event_type
        ");
        $stmt->execute([
            ':notification_id' => $notificationId,
            ':user_id' => $userId,
            ':event_type' => $feedbackEventType
        ]);
        if ($stmt->fetchColumn() > 0) {
            return false; // Feedback already sent for this event
        }

        // Get the original notification with notify_sender flag
        $stmt = $this->conn->prepare("
            SELECT sn.*, u.name as recipient_name
            FROM scheduled_notifications sn
            LEFT JOIN users u ON u.id = :user_id
            WHERE sn.id = :notification_id
        ");
        $stmt->execute([':notification_id' => $notificationId, ':user_id' => $userId]);
        $notification = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$notification || !$notification['notify_sender']) {
            return false; // No notification or feedback disabled
        }

        $senderId = $notification['created_by'];
        $recipientName = $notification['recipient_name'] ?? 'משתמש';
        $originalTitle = $notification['title'];

        // Build feedback message based on event type
        switch ($eventType) {
            case 'viewed':
                $feedbackTitle = 'ההודעה שלך נצפתה';
                $feedbackBody = "{$recipientName} צפה בהודעה: \"{$originalTitle}\"";
                break;
            case 'approved':
                $feedbackTitle = 'ההודעה שלך אושרה ✓';
                $feedbackBody = "{$recipientName} אישר את ההודעה: \"{$originalTitle}\"";
                break;
            case 'rejected':
                $feedbackTitle = 'ההודעה שלך נדחתה ✗';
                $feedbackBody = "{$recipientName} דחה את ההודעה: \"{$originalTitle}\"";
                break;
            default:
                return false;
        }

        // Log that feedback is being sent (to prevent duplicates)
        $this->log($feedbackEventType, [
            'notification_id' => $notificationId,
            'user_id' => $userId,
            'extra_data' => ['sender_id' => $senderId]
        ]);

        // Send push notification to the sender (wrapped in try-catch to prevent breaking caller)
        try {
            require_once __DIR__ . '/../../../../push/send-push.php';

            $result = sendPushToUser(
                $senderId,
                $feedbackTitle,
                $feedbackBody,
                null,  // url
                null,  // icon
                [      // options
                    'type' => 'feedback',
                    'original_notification_id' => $notificationId,
                    'event_type' => $eventType,
                    'triggered_by' => $userId
                ]
            );

            error_log("[Feedback] Sending to sender {$senderId}: {$feedbackTitle} - Result: " . json_encode($result));

            return $result['sent'] > 0;
        } catch (Exception $e) {
            error_log("[Feedback] Failed to send feedback: " . $e->getMessage());
            return false;
        }
    }
}
