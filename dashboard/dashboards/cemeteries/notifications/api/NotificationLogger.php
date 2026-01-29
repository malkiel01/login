<?php
/**
 * NotificationLogger - Comprehensive notification event logging
 *
 * @version 1.0.0
 * @created 2026-01-29
 */

class NotificationLogger {
    private PDO $conn;
    private static ?NotificationLogger $instance = null;

    public function __construct(?PDO $conn = null) {
        if ($conn) {
            $this->conn = $conn;
        } else {
            require_once $_SERVER['DOCUMENT_ROOT'] . '/dashboard/dashboards/cemeteries/config.php';
            $this->conn = getDBConnection();
        }
        $this->ensureTableExists();
    }

    /**
     * Get singleton instance
     */
    public static function getInstance(?PDO $conn = null): self {
        if (self::$instance === null) {
            self::$instance = new self($conn);
        }
        return self::$instance;
    }

    /**
     * Ensure the logs table exists
     */
    private function ensureTableExists(): void {
        $sql = "CREATE TABLE IF NOT EXISTS notification_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            notification_id INT NULL,
            user_id INT NULL,
            subscription_id INT NULL,
            event_type VARCHAR(50) NOT NULL,
            notification_title VARCHAR(255) NULL,
            notification_body TEXT NULL,
            notification_type VARCHAR(50) NULL,
            device_type VARCHAR(50) NULL,
            os VARCHAR(50) NULL,
            browser VARCHAR(50) NULL,
            user_agent TEXT NULL,
            ip_address VARCHAR(45) NULL,
            push_endpoint VARCHAR(500) NULL,
            http_status INT NULL,
            error_code VARCHAR(50) NULL,
            error_message TEXT NULL,
            extra_data JSON NULL,
            test_run_id VARCHAR(36) NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_notification_id (notification_id),
            INDEX idx_user_id (user_id),
            INDEX idx_event_type (event_type),
            INDEX idx_created_at (created_at),
            INDEX idx_test_run_id (test_run_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        try {
            $this->conn->exec($sql);
        } catch (PDOException $e) {
            error_log("[NotificationLogger] Failed to create table: " . $e->getMessage());
        }
    }

    /**
     * Log a notification event
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
     * Log notification created event
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
     * Parse User-Agent to get device info
     */
    public function parseUserAgent(?string $ua = null): array {
        $ua = $ua ?? ($_SERVER['HTTP_USER_AGENT'] ?? 'Unknown');

        $device = 'Unknown';
        $os = 'Unknown';
        $browser = 'Unknown';

        // Detect OS
        if (preg_match('/iPhone|iPad|iPod/i', $ua)) {
            $os = 'iOS';
            $device = preg_match('/iPad/i', $ua) ? 'Tablet' : 'Mobile';
        } elseif (preg_match('/Android/i', $ua)) {
            $os = 'Android';
            $device = preg_match('/Mobile/i', $ua) ? 'Mobile' : 'Tablet';
        } elseif (preg_match('/Windows/i', $ua)) {
            $os = 'Windows';
            $device = 'Desktop';
        } elseif (preg_match('/Mac OS X/i', $ua)) {
            $os = 'macOS';
            $device = 'Desktop';
        } elseif (preg_match('/Linux/i', $ua)) {
            $os = 'Linux';
            $device = 'Desktop';
        }

        // Detect Browser
        if (preg_match('/Edg/i', $ua)) {
            $browser = 'Edge';
        } elseif (preg_match('/Chrome/i', $ua)) {
            $browser = 'Chrome';
        } elseif (preg_match('/Safari/i', $ua) && !preg_match('/Chrome/i', $ua)) {
            $browser = 'Safari';
        } elseif (preg_match('/Firefox/i', $ua)) {
            $browser = 'Firefox';
        }

        return [
            'device' => $device,
            'os' => $os,
            'browser' => $browser
        ];
    }

    /**
     * Get logs with filters
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
     * Get statistics
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
     * Get timeline for a specific notification
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
     * Clean old logs
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
     * @param int $notificationId - The scheduled_notification ID
     * @param int $userId - The user who triggered the event
     * @param string $eventType - 'viewed', 'approved', 'rejected'
     * @return bool
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

        // Send push notification to the sender
        require_once $_SERVER['DOCUMENT_ROOT'] . '/push/send-push.php';

        $result = sendPushToUser($senderId, $feedbackTitle, $feedbackBody, [
            'type' => 'feedback',
            'original_notification_id' => $notificationId,
            'event_type' => $eventType,
            'triggered_by' => $userId
        ]);

        return $result['success'] ?? false;
    }
}
