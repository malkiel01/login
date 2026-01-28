<?php
/**
 * Push Notification Sender
 * Sends real Web Push notifications to subscribed users
 *
 * @version 1.0.0
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/WebPush.php';

/**
 * Send push notification to a specific user
 */
function sendPushToUser(int $userId, string $title, string $body, ?string $url = null, ?string $icon = null): array {
    $pdo = getDBConnection();

    // Get all active subscriptions for user
    $stmt = $pdo->prepare("
        SELECT * FROM push_subscriptions
        WHERE user_id = ? AND is_active = 1
    ");
    $stmt->execute([$userId]);
    $subscriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($subscriptions)) {
        return ['success' => false, 'error' => 'No subscriptions found', 'sent' => 0];
    }

    $webPush = new WebPush(
        VAPID_PUBLIC_KEY,
        VAPID_PRIVATE_KEY_PEM,
        VAPID_SUBJECT
    );

    $payload = json_encode([
        'title' => $title,
        'body' => $body,
        'icon' => $icon ?? '/pwa/icons/android/android-launchericon-192-192.png',
        'badge' => '/pwa/icons/android/android-launchericon-72-72.png',
        'url' => $url ?? '/',
        'timestamp' => time()
    ]);

    $sent = 0;
    $failed = 0;
    $errors = [];

    foreach ($subscriptions as $subscription) {
        $result = $webPush->send($subscription, $payload);

        if ($result['success']) {
            $sent++;
            // Update last_used_at
            $pdo->prepare("UPDATE push_subscriptions SET last_used_at = NOW() WHERE id = ?")->execute([$subscription['id']]);
        } else {
            $failed++;
            $errors[] = $result['error'];

            // If subscription is expired/invalid, mark as inactive
            if (isset($result['httpCode']) && in_array($result['httpCode'], [404, 410])) {
                $pdo->prepare("UPDATE push_subscriptions SET is_active = 0 WHERE id = ?")->execute([$subscription['id']]);
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
 * Send push notification to multiple users
 */
function sendPushToUsers(array $userIds, string $title, string $body, ?string $url = null): array {
    $totalSent = 0;
    $totalFailed = 0;
    $results = [];

    foreach ($userIds as $userId) {
        $result = sendPushToUser($userId, $title, $body, $url);
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
 * Send push notification to all active users
 */
function sendPushToAll(string $title, string $body, ?string $url = null): array {
    $pdo = getDBConnection();

    // Get all users with active subscriptions
    $stmt = $pdo->query("
        SELECT DISTINCT user_id FROM push_subscriptions WHERE is_active = 1
    ");
    $userIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

    if (empty($userIds)) {
        return ['success' => false, 'error' => 'No subscribed users', 'sent' => 0];
    }

    return sendPushToUsers($userIds, $title, $body, $url);
}

/**
 * Process scheduled notifications and send them
 * This should be called by a cron job
 */
function processScheduledNotifications(): array {
    $pdo = getDBConnection();

    // Get pending notifications that are due
    $stmt = $pdo->query("
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

        if (in_array('all', $targetUsers)) {
            $result = sendPushToAll($notification['title'], $notification['body'], $notification['url']);
        } else {
            $result = sendPushToUsers($targetUsers, $notification['title'], $notification['body'], $notification['url']);
        }

        // Update notification status
        $status = $result['sent'] > 0 ? 'sent' : 'failed';
        $errorMessage = $result['sent'] === 0 ? json_encode($result['errors'] ?? []) : null;

        $stmt = $pdo->prepare("
            UPDATE scheduled_notifications
            SET status = ?, sent_at = NOW(), error_message = ?
            WHERE id = ?
        ");
        $stmt->execute([$status, $errorMessage, $notification['id']]);

        $processed++;
        $results[$notification['id']] = $result;
    }

    return [
        'processed' => $processed,
        'results' => $results
    ];
}

// If called directly (for cron)
if (php_sapi_name() === 'cli' || (isset($_GET['cron']) && $_GET['cron'] === 'process')) {
    $result = processScheduledNotifications();
    echo json_encode($result, JSON_PRETTY_PRINT) . "\n";
}
