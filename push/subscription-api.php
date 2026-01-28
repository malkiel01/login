<?php
/**
 * Push Subscription API
 * Handles subscription management for Web Push
 *
 * Endpoints:
 * GET  ?action=vapid_key          - Get VAPID public key
 * POST action=subscribe           - Save subscription
 * POST action=unsubscribe         - Remove subscription
 *
 * @version 1.0.0
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/config.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Create table if not exists
createPushSubscriptionTable();

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

if ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    $action = $action ?: ($input['action'] ?? '');
}

try {
    switch ($action) {
        case 'vapid_key':
            handleGetVapidKey();
            break;
        case 'subscribe':
            handleSubscribe($input);
            break;
        case 'unsubscribe':
            handleUnsubscribe($input);
            break;
        default:
            throw new Exception('Invalid action');
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

/**
 * Create push_subscriptions table if not exists
 */
function createPushSubscriptionTable(): void {
    $pdo = getDBConnection();
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `push_subscriptions` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `user_id` INT NOT NULL,
            `endpoint` TEXT NOT NULL,
            `p256dh_key` VARCHAR(255) NOT NULL,
            `auth_key` VARCHAR(255) NOT NULL,
            `user_agent` VARCHAR(500) DEFAULT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `last_used_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            `is_active` TINYINT(1) DEFAULT 1,
            INDEX `idx_user_id` (`user_id`),
            INDEX `idx_is_active` (`is_active`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
}

/**
 * Get VAPID public key for frontend
 */
function handleGetVapidKey(): void {
    echo json_encode([
        'success' => true,
        'publicKey' => VAPID_PUBLIC_KEY
    ]);
}

/**
 * Save push subscription
 */
function handleSubscribe(array $input): void {
    // Get user ID from session
    session_start();
    $userId = $_SESSION['user_id'] ?? null;

    if (!$userId) {
        throw new Exception('User not logged in');
    }

    if (empty($input['subscription'])) {
        throw new Exception('Missing subscription data');
    }

    $subscription = $input['subscription'];

    if (empty($subscription['endpoint'])) {
        throw new Exception('Missing endpoint');
    }

    $endpoint = $subscription['endpoint'];
    $p256dhKey = $subscription['keys']['p256dh'] ?? '';
    $authKey = $subscription['keys']['auth'] ?? '';
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;

    if (empty($p256dhKey) || empty($authKey)) {
        throw new Exception('Missing encryption keys');
    }

    $pdo = getDBConnection();

    // Check if endpoint already exists
    $stmt = $pdo->prepare("SELECT id, user_id FROM push_subscriptions WHERE endpoint = ?");
    $stmt->execute([$endpoint]);
    $existing = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($existing) {
        // Update existing subscription
        $stmt = $pdo->prepare("
            UPDATE push_subscriptions
            SET user_id = ?, p256dh_key = ?, auth_key = ?, user_agent = ?, is_active = 1, last_used_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$userId, $p256dhKey, $authKey, $userAgent, $existing['id']]);

        echo json_encode([
            'success' => true,
            'message' => 'Subscription updated',
            'id' => $existing['id']
        ]);
    } else {
        // Insert new subscription
        $stmt = $pdo->prepare("
            INSERT INTO push_subscriptions (user_id, endpoint, p256dh_key, auth_key, user_agent)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([$userId, $endpoint, $p256dhKey, $authKey, $userAgent]);

        echo json_encode([
            'success' => true,
            'message' => 'Subscription saved',
            'id' => $pdo->lastInsertId()
        ]);
    }
}

/**
 * Remove push subscription
 */
function handleUnsubscribe(array $input): void {
    if (empty($input['endpoint'])) {
        throw new Exception('Missing endpoint');
    }

    $endpoint = $input['endpoint'];
    $pdo = getDBConnection();

    // Soft delete - mark as inactive
    $stmt = $pdo->prepare("UPDATE push_subscriptions SET is_active = 0 WHERE endpoint = ?");
    $stmt->execute([$endpoint]);

    echo json_encode([
        'success' => true,
        'message' => 'Subscription removed'
    ]);
}
