<?php
/**
 * Notifications API v1 - Unified Notification Endpoint
 *
 * Endpoints:
 *   POST /api/v1/notifications.php?action=send      - Send notification
 *   POST /api/v1/notifications.php?action=schedule  - Schedule notification
 *   GET  /api/v1/notifications.php?action=list      - List notifications
 *   GET  /api/v1/notifications.php?action=get&id=X  - Get single notification
 *   POST /api/v1/notifications.php?action=cancel&id=X - Cancel notification
 *
 * @version 1.0.0
 */

require_once __DIR__ . '/../../auth/middleware.php';
require_once __DIR__ . '/../../push/NotificationService.php';

// Set headers
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Require authentication
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$userId = getCurrentUserId();
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

// Get JSON body for POST requests
$input = [];
if ($method === 'POST') {
    $rawInput = file_get_contents('php://input');
    $input = json_decode($rawInput, true) ?? [];
}

try {
    switch ($action) {
        case 'send':
            handleSend($input, $userId);
            break;

        case 'schedule':
            handleSchedule($input, $userId);
            break;

        case 'list':
            handleList($userId);
            break;

        case 'get':
            handleGet($userId);
            break;

        case 'cancel':
            handleCancel($input, $userId);
            break;

        default:
            throw new Exception('Invalid action. Use: send, schedule, list, get, cancel');
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

/**
 * Send notification immediately
 *
 * Request body:
 * {
 *   "type": "info|approval|feedback",
 *   "level": "info|warning|urgent",
 *   "targets": [1, 2, 3] or ["all"],
 *   "title": "Notification title",
 *   "body": "Notification body",
 *   "url": "/optional/url",
 *   "options": {
 *     "notifySender": true,
 *     "approvalMessage": "Please approve",
 *     "expiresIn": 24
 *   }
 * }
 */
function handleSend(array $input, int $userId): void {
    // Validate required fields
    if (empty($input['targets'])) {
        throw new Exception('targets is required');
    }
    if (empty($input['title'])) {
        throw new Exception('title is required');
    }
    if (empty($input['body'])) {
        throw new Exception('body is required');
    }

    // Check permission for sending to others
    if (!isAdmin() && !hasModulePermission('notifications', 'create')) {
        throw new Exception('Permission denied');
    }

    $service = NotificationService::getInstance();

    $params = [
        'type' => $input['type'] ?? NotificationType::INFO,
        'level' => $input['level'] ?? NotificationLevel::INFO,
        'targets' => $input['targets'],
        'title' => $input['title'],
        'body' => $input['body'],
        'url' => $input['url'] ?? null,
        'options' => array_merge($input['options'] ?? [], ['createdBy' => $userId])
    ];

    $result = $service->send($params);

    echo json_encode([
        'success' => $result['success'] ?? false,
        'data' => $result
    ]);
}

/**
 * Schedule notification for later
 */
function handleSchedule(array $input, int $userId): void {
    if (empty($input['scheduled_at'])) {
        throw new Exception('scheduled_at is required');
    }

    // Validate date
    $scheduledAt = strtotime($input['scheduled_at']);
    if ($scheduledAt === false || $scheduledAt <= time()) {
        throw new Exception('scheduled_at must be a valid future datetime');
    }

    // Add scheduled_at to options
    $input['options'] = $input['options'] ?? [];
    $input['options']['scheduledAt'] = date('Y-m-d H:i:s', $scheduledAt);

    handleSend($input, $userId);
}

/**
 * List notifications
 */
function handleList(int $userId): void {
    $pdo = getDBConnection();

    $page = max(1, (int)($_GET['page'] ?? 1));
    $limit = min(100, max(1, (int)($_GET['limit'] ?? 20)));
    $offset = ($page - 1) * $limit;

    $where = "1=1";
    $params = [];

    // Filter by status
    if (!empty($_GET['status'])) {
        $where .= " AND status = ?";
        $params[] = $_GET['status'];
    }

    // Filter by type
    if (!empty($_GET['type'])) {
        $where .= " AND message_type = ?";
        $params[] = $_GET['type'];
    }

    // Non-admin users can only see their own notifications
    if (!isAdmin()) {
        $where .= " AND created_by = ?";
        $params[] = $userId;
    }

    // Count total
    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM scheduled_notifications WHERE $where");
    $countStmt->execute($params);
    $total = $countStmt->fetchColumn();

    // Get notifications
    $params[] = $limit;
    $params[] = $offset;

    $stmt = $pdo->prepare("
        SELECT sn.*, u.name as creator_name
        FROM scheduled_notifications sn
        LEFT JOIN users u ON u.id = sn.created_by
        WHERE $where
        ORDER BY sn.created_at DESC
        LIMIT ? OFFSET ?
    ");
    $stmt->execute($params);
    $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Parse target_users JSON
    foreach ($notifications as &$notification) {
        $notification['target_users'] = json_decode($notification['target_users'], true);
    }

    echo json_encode([
        'success' => true,
        'data' => $notifications,
        'pagination' => [
            'total' => (int)$total,
            'page' => $page,
            'limit' => $limit,
            'pages' => ceil($total / $limit)
        ]
    ]);
}

/**
 * Get single notification
 */
function handleGet(int $userId): void {
    $id = (int)($_GET['id'] ?? 0);
    if (!$id) {
        throw new Exception('id is required');
    }

    $pdo = getDBConnection();

    $stmt = $pdo->prepare("
        SELECT sn.*, u.name as creator_name
        FROM scheduled_notifications sn
        LEFT JOIN users u ON u.id = sn.created_by
        WHERE sn.id = ?
    ");
    $stmt->execute([$id]);
    $notification = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$notification) {
        throw new Exception('Notification not found');
    }

    // Check permission
    if (!isAdmin() && $notification['created_by'] != $userId) {
        throw new Exception('Permission denied');
    }

    $notification['target_users'] = json_decode($notification['target_users'], true);

    // Get approval stats if applicable
    if ($notification['requires_approval']) {
        $stmt = $pdo->prepare("
            SELECT status, COUNT(*) as count
            FROM notification_approvals
            WHERE notification_id = ?
            GROUP BY status
        ");
        $stmt->execute([$id]);
        $notification['approval_stats'] = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    }

    echo json_encode([
        'success' => true,
        'data' => $notification
    ]);
}

/**
 * Cancel a scheduled notification
 */
function handleCancel(array $input, int $userId): void {
    $id = (int)($input['id'] ?? $_GET['id'] ?? 0);
    if (!$id) {
        throw new Exception('id is required');
    }

    $pdo = getDBConnection();

    // Get notification
    $stmt = $pdo->prepare("SELECT * FROM scheduled_notifications WHERE id = ?");
    $stmt->execute([$id]);
    $notification = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$notification) {
        throw new Exception('Notification not found');
    }

    // Check permission
    if (!isAdmin() && $notification['created_by'] != $userId) {
        throw new Exception('Permission denied');
    }

    // Can only cancel pending notifications
    if ($notification['status'] !== 'pending') {
        throw new Exception('Can only cancel pending notifications');
    }

    // Update status
    $stmt = $pdo->prepare("UPDATE scheduled_notifications SET status = 'cancelled' WHERE id = ?");
    $stmt->execute([$id]);

    echo json_encode([
        'success' => true,
        'message' => 'Notification cancelled'
    ]);
}
