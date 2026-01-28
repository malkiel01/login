<?php
/**
 * Debug Push Notifications
 * Access: /debug-push.php
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/push/config.php';

header('Content-Type: application/json');

$pdo = getDBConnection();

$result = [];

// Check push subscriptions
$stmt = $pdo->query("SELECT COUNT(*) as cnt, user_id FROM push_subscriptions WHERE is_active = 1 GROUP BY user_id");
$subs = $stmt->fetchAll(PDO::FETCH_ASSOC);
$result['active_subscriptions'] = count($subs);
$result['subscriptions_by_user'] = $subs;

// Check recent notifications
$stmt = $pdo->query("SELECT id, title, status, sent_at, error_message FROM scheduled_notifications ORDER BY id DESC LIMIT 5");
$result['recent_notifications'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Check VAPID config
$result['vapid_configured'] = defined('VAPID_PUBLIC_KEY') && !empty(VAPID_PUBLIC_KEY);

// Test sending (dry run)
if (isset($_GET['test_user_id'])) {
    $userId = (int)$_GET['test_user_id'];

    $stmt = $pdo->prepare("SELECT * FROM push_subscriptions WHERE user_id = ? AND is_active = 1 LIMIT 1");
    $stmt->execute([$userId]);
    $sub = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($sub) {
        $result['test_subscription'] = [
            'endpoint' => substr($sub['endpoint'], 0, 80) . '...',
            'has_p256dh_key' => !empty($sub['p256dh_key']),
            'has_auth_key' => !empty($sub['auth_key'])
        ];

        // Actually try to send
        if (isset($_GET['send'])) {
            require_once __DIR__ . '/push/send-push.php';
            $sendResult = sendPushToUser($userId, 'בדיקת מערכת', 'התראת בדיקה מהמערכת', '/dashboard/');
            $result['send_result'] = $sendResult;
        }
    } else {
        $result['test_subscription'] = 'No subscription found for user ' . $userId;
    }
}

echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
