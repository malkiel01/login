<?php
/**
 * Notification Retry Cron
 * Processes pending notifications and retries failed deliveries
 *
 * Can be run via cron: * /5 * * * * php /path/to/notification-retry.php
 * Or triggered manually via: notification-retry.php?key=SECRET_KEY
 *
 * @version 1.0.0
 */

// Allow running from CLI or web with secret key
$isCli = php_sapi_name() === 'cli';
$secretKey = 'NOTIFICATION_RETRY_SECRET_2026'; // Change this!

if (!$isCli) {
    $providedKey = $_GET['key'] ?? '';
    if ($providedKey !== $secretKey) {
        http_response_code(403);
        die('Unauthorized');
    }
    header('Content-Type: application/json');
}

require_once $_SERVER['DOCUMENT_ROOT'] . '/dashboard/dashboards/cemeteries/config.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/push/WebPush.php';

$pdo = getDBConnection();

// Statistics
$stats = [
    'processed' => 0,
    'delivered' => 0,
    'failed' => 0,
    'no_subscription' => 0,
    'skipped' => 0,
    'errors' => []
];

try {
    // Ensure delivery table exists
    createDeliveryTableIfNeeded($pdo);

    // 1. Process scheduled notifications that are due
    processScheduledNotifications($pdo, $stats);

    // 2. Retry failed deliveries
    retryFailedDeliveries($pdo, $stats);

    // Output results
    $output = [
        'success' => true,
        'timestamp' => date('Y-m-d H:i:s'),
        'stats' => $stats
    ];

    if ($isCli) {
        echo "Notification Retry Cron - " . date('Y-m-d H:i:s') . "\n";
        echo "Processed: {$stats['processed']}\n";
        echo "Delivered: {$stats['delivered']}\n";
        echo "Failed: {$stats['failed']}\n";
        echo "No subscription: {$stats['no_subscription']}\n";
        echo "Skipped: {$stats['skipped']}\n";
        if (!empty($stats['errors'])) {
            echo "Errors:\n" . implode("\n", $stats['errors']) . "\n";
        }
    } else {
        echo json_encode($output);
    }

} catch (Exception $e) {
    $error = ['success' => false, 'error' => $e->getMessage()];

    if ($isCli) {
        echo "Error: " . $e->getMessage() . "\n";
        exit(1);
    } else {
        http_response_code(500);
        echo json_encode($error);
    }
}

/**
 * Create delivery tracking table if it doesn't exist
 */
function createDeliveryTableIfNeeded(PDO $pdo): void {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `notification_deliveries` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `notification_id` INT NOT NULL,
            `user_id` INT NOT NULL,
            `status` ENUM('pending', 'delivered', 'failed', 'no_subscription') DEFAULT 'pending',
            `retry_count` INT DEFAULT 0,
            `max_retries` INT DEFAULT 5,
            `last_attempt_at` DATETIME DEFAULT NULL,
            `next_retry_at` DATETIME DEFAULT NULL,
            `delivered_at` DATETIME DEFAULT NULL,
            `error_message` TEXT DEFAULT NULL,
            `push_endpoint` VARCHAR(500) DEFAULT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY `unique_notification_user` (`notification_id`, `user_id`),
            INDEX `idx_status_retry` (`status`, `next_retry_at`),
            INDEX `idx_user_id` (`user_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
}

/**
 * Process scheduled notifications that are due
 */
function processScheduledNotifications(PDO $pdo, array &$stats): void {
    // Find notifications that are scheduled and due
    $stmt = $pdo->prepare("
        SELECT * FROM scheduled_notifications
        WHERE status = 'pending'
        AND (scheduled_at IS NULL OR scheduled_at <= NOW())
        LIMIT 50
    ");
    $stmt->execute();
    $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($notifications as $notification) {
        $notificationId = $notification['id'];
        $targetUsers = json_decode($notification['target_users'], true);

        // Get user IDs
        if (in_array('all', $targetUsers)) {
            $userStmt = $pdo->query("SELECT id FROM users WHERE is_active = 1");
            $userIds = $userStmt->fetchAll(PDO::FETCH_COLUMN);
        } else {
            $userIds = array_map('intval', $targetUsers);
        }

        // Create delivery records for each user
        foreach ($userIds as $userId) {
            createOrUpdateDeliveryRecord($pdo, $notificationId, $userId);
        }

        // Mark notification as processing
        $pdo->prepare("
            UPDATE scheduled_notifications
            SET status = 'sent', sent_at = NOW()
            WHERE id = ?
        ")->execute([$notificationId]);
    }
}

/**
 * Retry failed deliveries
 */
function retryFailedDeliveries(PDO $pdo, array &$stats): void {
    // Get pending or failed deliveries that are ready for retry
    $stmt = $pdo->prepare("
        SELECT
            nd.*,
            sn.title,
            sn.body,
            sn.url,
            sn.requires_approval
        FROM notification_deliveries nd
        JOIN scheduled_notifications sn ON sn.id = nd.notification_id
        WHERE nd.status IN ('pending', 'failed')
        AND nd.retry_count < nd.max_retries
        AND (nd.next_retry_at IS NULL OR nd.next_retry_at <= NOW())
        ORDER BY nd.created_at ASC
        LIMIT 100
    ");
    $stmt->execute();
    $deliveries = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($deliveries as $delivery) {
        $stats['processed']++;

        try {
            $result = sendPushToUser(
                $pdo,
                $delivery['user_id'],
                $delivery['title'],
                $delivery['body'],
                $delivery['url'],
                $delivery['notification_id'],
                $delivery['requires_approval']
            );

            if ($result['success']) {
                // Mark as delivered
                updateDeliveryStatus($pdo, $delivery['id'], 'delivered', null, $result['endpoint'] ?? null);
                $stats['delivered']++;
            } elseif ($result['no_subscription']) {
                // User has no push subscription
                updateDeliveryStatus($pdo, $delivery['id'], 'no_subscription', 'No active push subscription');
                $stats['no_subscription']++;
            } else {
                // Failed - schedule retry
                $retryCount = $delivery['retry_count'] + 1;
                $nextRetry = calculateNextRetry($retryCount);

                updateDeliveryForRetry($pdo, $delivery['id'], $retryCount, $nextRetry, $result['error'] ?? 'Unknown error');
                $stats['failed']++;
            }

        } catch (Exception $e) {
            $stats['errors'][] = "User {$delivery['user_id']}: " . $e->getMessage();
            $stats['failed']++;

            // Schedule retry
            $retryCount = $delivery['retry_count'] + 1;
            $nextRetry = calculateNextRetry($retryCount);
            updateDeliveryForRetry($pdo, $delivery['id'], $retryCount, $nextRetry, $e->getMessage());
        }
    }
}

/**
 * Create or update delivery record
 */
function createOrUpdateDeliveryRecord(PDO $pdo, int $notificationId, int $userId): void {
    $stmt = $pdo->prepare("
        INSERT INTO notification_deliveries (notification_id, user_id, status, next_retry_at)
        VALUES (?, ?, 'pending', NOW())
        ON DUPLICATE KEY UPDATE
            status = IF(status = 'delivered', status, 'pending'),
            next_retry_at = IF(status = 'delivered', next_retry_at, NOW())
    ");
    $stmt->execute([$notificationId, $userId]);
}

/**
 * Send push notification to a specific user
 */
function sendPushToUser(PDO $pdo, int $userId, string $title, string $body, ?string $url, int $notificationId, bool $requiresApproval = false): array {
    // Get user's push subscription
    $stmt = $pdo->prepare("
        SELECT * FROM push_subscriptions
        WHERE user_id = ? AND is_active = 1
        ORDER BY created_at DESC
        LIMIT 1
    ");
    $stmt->execute([$userId]);
    $subscription = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$subscription) {
        return ['success' => false, 'no_subscription' => true];
    }

    // Build URL for approval notifications
    $notificationUrl = $url;
    if ($requiresApproval) {
        $notificationUrl = "/dashboard/dashboards/cemeteries/notifications/approve.php?id={$notificationId}";
    }

    // Prepare subscription data (DB columns are p256dh_key and auth_key)
    $subscriptionData = [
        'endpoint' => $subscription['endpoint'],
        'keys' => [
            'p256dh' => $subscription['p256dh_key'],
            'auth' => $subscription['auth_key']
        ]
    ];

    // Prepare payload
    $payload = json_encode([
        'title' => $title,
        'body' => $body,
        'url' => $notificationUrl,
        'id' => $notificationId,
        'requiresApproval' => $requiresApproval,
        'requireInteraction' => $requiresApproval
    ]);

    // Send via WebPush
    try {
        $webPush = new WebPush(
            VAPID_PUBLIC_KEY,
            VAPID_PRIVATE_KEY_PEM,
            VAPID_SUBJECT
        );

        $result = $webPush->send($subscriptionData, $payload);

        if ($result['success']) {
            return [
                'success' => true,
                'endpoint' => $subscription['endpoint']
            ];
        } else {
            // Check if subscription is expired/invalid
            if (isset($result['statusCode']) && in_array($result['statusCode'], [404, 410])) {
                // Mark subscription as inactive
                $pdo->prepare("UPDATE push_subscriptions SET is_active = 0 WHERE id = ?")->execute([$subscription['id']]);
            }

            return [
                'success' => false,
                'error' => $result['error'] ?? 'Push failed',
                'statusCode' => $result['statusCode'] ?? null
            ];
        }
    } catch (Exception $e) {
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}

/**
 * Update delivery status
 */
function updateDeliveryStatus(PDO $pdo, int $deliveryId, string $status, ?string $error = null, ?string $endpoint = null): void {
    $stmt = $pdo->prepare("
        UPDATE notification_deliveries
        SET status = ?,
            error_message = ?,
            push_endpoint = ?,
            delivered_at = IF(? = 'delivered', NOW(), delivered_at),
            last_attempt_at = NOW()
        WHERE id = ?
    ");
    $stmt->execute([$status, $error, $endpoint, $status, $deliveryId]);
}

/**
 * Update delivery for retry
 */
function updateDeliveryForRetry(PDO $pdo, int $deliveryId, int $retryCount, string $nextRetry, string $error): void {
    $stmt = $pdo->prepare("
        UPDATE notification_deliveries
        SET status = IF(? >= max_retries, 'failed', 'pending'),
            retry_count = ?,
            next_retry_at = ?,
            error_message = ?,
            last_attempt_at = NOW()
        WHERE id = ?
    ");
    $stmt->execute([$retryCount, $retryCount, $nextRetry, $error, $deliveryId]);
}

/**
 * Calculate next retry time using exponential backoff
 * 1st retry: 1 minute
 * 2nd retry: 5 minutes
 * 3rd retry: 15 minutes
 * 4th retry: 1 hour
 * 5th retry: 4 hours
 */
function calculateNextRetry(int $retryCount): string {
    $delays = [60, 300, 900, 3600, 14400]; // seconds
    $delay = $delays[min($retryCount - 1, count($delays) - 1)];
    return date('Y-m-d H:i:s', time() + $delay);
}
