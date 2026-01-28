<?php
/**
 * Push Notification Test Script
 * Run this to diagnose push notification issues
 * Also handles POST requests to send test notifications to current user
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/WebPush.php';

// Handle POST request - send test to current user
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json; charset=utf-8');
    session_start();

    $userId = $_SESSION['user_id'] ?? null;

    if (!$userId) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'User not logged in']);
        exit;
    }

    try {
        require_once __DIR__ . '/send-push.php';

        $result = sendPushToUser(
            $userId,
            'בדיקת התראות',
            'ההתראות עובדות! זו הודעת בדיקה.',
            '/dashboard/'
        );

        echo json_encode($result);

    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }
    exit;
}

// Diagnostic mode (GET request)
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: text/plain; charset=utf-8');

echo "=== Push Notification Diagnostic ===\n\n";

// 1. Check VAPID keys
echo "1. VAPID Keys:\n";
echo "   Public Key: " . (defined('VAPID_PUBLIC_KEY') ? substr(VAPID_PUBLIC_KEY, 0, 30) . '...' : 'NOT DEFINED') . "\n";
echo "   Private Key: " . (defined('VAPID_PRIVATE_KEY') ? substr(VAPID_PRIVATE_KEY, 0, 10) . '...' : 'NOT DEFINED') . "\n";
echo "   Subject: " . (defined('VAPID_SUBJECT') ? VAPID_SUBJECT : 'NOT DEFINED') . "\n\n";

// 2. Check database connection
echo "2. Database Connection:\n";
try {
    $pdo = getDBConnection();
    echo "   ✅ Connected successfully\n\n";
} catch (Exception $e) {
    echo "   ❌ Connection failed: " . $e->getMessage() . "\n\n";
    exit;
}

// 3. Check push_subscriptions table
echo "3. Push Subscriptions Table:\n";
try {
    $result = $pdo->query("SHOW TABLES LIKE 'push_subscriptions'");
    if ($result->rowCount() > 0) {
        echo "   ✅ Table exists\n";

        // Count subscriptions
        $stmt = $pdo->query("SELECT COUNT(*) as cnt FROM push_subscriptions WHERE is_active = 1");
        $count = $stmt->fetch(PDO::FETCH_ASSOC);
        echo "   Active subscriptions: " . $count['cnt'] . "\n";

        // Show recent subscriptions
        $stmt = $pdo->query("SELECT id, user_id, LEFT(endpoint, 60) as endpoint, is_active, created_at FROM push_subscriptions ORDER BY created_at DESC LIMIT 3");
        $subs = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($subs)) {
            echo "   ⚠️ No subscriptions found!\n";
            echo "   → Users need to allow notifications first\n";
        } else {
            echo "\n   Recent subscriptions:\n";
            foreach ($subs as $sub) {
                echo "   - ID: {$sub['id']}, User: {$sub['user_id']}, Active: {$sub['is_active']}\n";
                echo "     Endpoint: {$sub['endpoint']}...\n";
            }
        }
    } else {
        echo "   ❌ Table does NOT exist!\n";
        echo "   → Run SQL from /push/sql/push_subscriptions.sql\n";
    }
} catch (Exception $e) {
    echo "   ❌ Error: " . $e->getMessage() . "\n";
}
echo "\n";

// 4. Check PHP extensions
echo "4. PHP Extensions:\n";
$required = ['openssl', 'curl', 'json', 'mbstring'];
foreach ($required as $ext) {
    if (extension_loaded($ext)) {
        echo "   ✅ $ext loaded\n";
    } else {
        echo "   ❌ $ext NOT loaded\n";
    }
}

// Check specific OpenSSL functions
echo "\n   OpenSSL EC Support:\n";
$curves = openssl_get_curve_names();
if (in_array('prime256v1', $curves)) {
    echo "   ✅ prime256v1 curve available\n";
} else {
    echo "   ❌ prime256v1 curve NOT available (required for Web Push)\n";
}
echo "\n";

// 5. Test sending (optional - requires subscription)
echo "5. Test Send:\n";
if (isset($_GET['test']) && isset($_GET['user_id'])) {
    $userId = (int)$_GET['user_id'];

    require_once __DIR__ . '/send-push.php';

    echo "   Sending test to user $userId...\n";

    $result = sendPushToUser($userId, 'בדיקה!', 'זוהי הודעת בדיקה', '/');

    echo "   Result: " . json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
} else {
    echo "   Add ?test=1&user_id=X to URL to send test notification\n";
}

echo "\n=== End Diagnostic ===\n";
