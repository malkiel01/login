<?php
/**
 * Feedback API - Get feedback notifications for sender
 *
 * Endpoints:
 *   GET ?action=list - List feedback notifications for current user
 *   GET ?action=stats - Get feedback statistics
 *
 * @version 1.0.0
 */

require_once __DIR__ . '/../../api/api-auth.php';

$pdo = getDBConnection();
$userId = getCurrentUserId();
$action = $_GET['action'] ?? 'list';

try {
    switch ($action) {
        case 'list':
            handleList($pdo, $userId);
            break;

        case 'stats':
            handleStats($pdo, $userId);
            break;

        default:
            throw new Exception('Invalid action');
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

/**
 * List feedback notifications for the current user as sender
 */
function handleList(PDO $pdo, int $userId): void {
    $page = max(1, (int)($_GET['page'] ?? 1));
    $limit = min(50, max(1, (int)($_GET['limit'] ?? 20)));
    $offset = ($page - 1) * $limit;

    // Get feedbacks where current user is the sender
    $stmt = $pdo->prepare("
        SELECT
            nl.id,
            nl.notification_id,
            nl.user_id as triggered_by_user_id,
            nl.event_type,
            nl.created_at,
            JSON_EXTRACT(nl.extra_data, '$.sender_id') as sender_id,
            sn.title as notification_title,
            sn.body as notification_body,
            u.name as triggered_by_name,
            u.username as triggered_by_username
        FROM notification_logs nl
        LEFT JOIN scheduled_notifications sn ON sn.id = nl.notification_id
        LEFT JOIN users u ON u.id = nl.user_id
        WHERE nl.event_type LIKE 'feedback_%'
          AND JSON_EXTRACT(nl.extra_data, '$.sender_id') = ?
        ORDER BY nl.created_at DESC
        LIMIT ? OFFSET ?
    ");
    $stmt->execute([$userId, $limit, $offset]);
    $feedbacks = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get total count
    $countStmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM notification_logs
        WHERE event_type LIKE 'feedback_%'
          AND JSON_EXTRACT(extra_data, '$.sender_id') = ?
    ");
    $countStmt->execute([$userId]);
    $total = $countStmt->fetchColumn();

    // Format event types
    foreach ($feedbacks as &$feedback) {
        $feedback['event_type_display'] = match($feedback['event_type']) {
            'feedback_viewed' => 'נצפתה',
            'feedback_approved' => 'אושרה',
            'feedback_rejected' => 'נדחתה',
            default => $feedback['event_type']
        };
        $feedback['event_icon'] = match($feedback['event_type']) {
            'feedback_viewed' => '👁️',
            'feedback_approved' => '✅',
            'feedback_rejected' => '❌',
            default => '📋'
        };
    }

    echo json_encode([
        'success' => true,
        'data' => $feedbacks,
        'pagination' => [
            'total' => (int)$total,
            'page' => $page,
            'limit' => $limit,
            'pages' => ceil($total / $limit)
        ]
    ], JSON_UNESCAPED_UNICODE);
}

/**
 * Get feedback statistics for the current user
 */
function handleStats(PDO $pdo, int $userId): void {
    $stmt = $pdo->prepare("
        SELECT
            event_type,
            COUNT(*) as count
        FROM notification_logs
        WHERE event_type LIKE 'feedback_%'
          AND JSON_EXTRACT(extra_data, '$.sender_id') = ?
        GROUP BY event_type
    ");
    $stmt->execute([$userId]);
    $stats = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

    // Get unique notifications with feedback
    $notifStmt = $pdo->prepare("
        SELECT COUNT(DISTINCT notification_id)
        FROM notification_logs
        WHERE event_type LIKE 'feedback_%'
          AND JSON_EXTRACT(extra_data, '$.sender_id') = ?
    ");
    $notifStmt->execute([$userId]);
    $uniqueNotifications = $notifStmt->fetchColumn();

    echo json_encode([
        'success' => true,
        'stats' => [
            'viewed' => (int)($stats['feedback_viewed'] ?? 0),
            'approved' => (int)($stats['feedback_approved'] ?? 0),
            'rejected' => (int)($stats['feedback_rejected'] ?? 0),
            'unique_notifications' => (int)$uniqueNotifications
        ]
    ]);
}
