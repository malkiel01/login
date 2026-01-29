<?php
/**
 * Notification Logs API
 * API for accessing notification logs
 *
 * @version 1.0.0
 * @created 2026-01-29
 */

header('Content-Type: application/json; charset=utf-8');

require_once $_SERVER['DOCUMENT_ROOT'] . '/dashboard/dashboards/cemeteries/config.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/auth/token-init.php';
require_once __DIR__ . '/NotificationLogger.php';

// Permission check
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

// Only admins and managers can view logs
if (!isAdmin() && !hasModulePermission('notifications', 'view')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Permission denied']);
    exit;
}

$conn = getDBConnection();
$logger = new NotificationLogger($conn);

$action = $_GET['action'] ?? $_POST['action'] ?? '';

try {
    switch ($action) {
        case 'list':
            handleList($logger);
            break;

        case 'stats':
            handleStats($logger);
            break;

        case 'timeline':
            handleTimeline($logger);
            break;

        case 'export':
            handleExport($logger);
            break;

        case 'event_types':
            handleEventTypes();
            break;

        default:
            echo json_encode(['success' => false, 'error' => 'Invalid action']);
    }
} catch (Exception $e) {
    error_log('[logs-api] Error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Server error: ' . $e->getMessage()]);
}

/**
 * List logs with filters and pagination
 */
function handleList(NotificationLogger $logger): void {
    $filters = [];

    if (!empty($_GET['notification_id'])) {
        $filters['notification_id'] = (int)$_GET['notification_id'];
    }

    if (!empty($_GET['user_id'])) {
        $filters['user_id'] = (int)$_GET['user_id'];
    }

    if (!empty($_GET['event_type'])) {
        $filters['event_type'] = $_GET['event_type'];
    }

    if (!empty($_GET['test_run_id'])) {
        $filters['test_run_id'] = $_GET['test_run_id'];
    }

    if (!empty($_GET['date_from'])) {
        $filters['date_from'] = $_GET['date_from'];
    }

    if (!empty($_GET['date_to'])) {
        $filters['date_to'] = $_GET['date_to'];
    }

    $limit = isset($_GET['limit']) ? min(100, (int)$_GET['limit']) : 50;
    $offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;

    $logs = $logger->getLogs($filters, $limit + 1, $offset);
    $hasMore = count($logs) > $limit;

    if ($hasMore) {
        array_pop($logs);
    }

    // Get user names for display
    global $conn;
    $userIds = array_unique(array_filter(array_column($logs, 'user_id')));
    $userNames = [];
    if (!empty($userIds)) {
        $placeholders = implode(',', array_fill(0, count($userIds), '?'));
        $stmt = $conn->prepare("SELECT id, name FROM users WHERE id IN ($placeholders)");
        $stmt->execute(array_values($userIds));
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $userNames[$row['id']] = $row['name'];
        }
    }

    // Add user names to logs
    foreach ($logs as &$log) {
        $log['user_name'] = $userNames[$log['user_id']] ?? null;
        if ($log['extra_data']) {
            $log['extra_data'] = json_decode($log['extra_data'], true);
        }
    }

    $total = $logger->getLogCount($filters);

    echo json_encode([
        'success' => true,
        'logs' => $logs,
        'hasMore' => $hasMore,
        'total' => $total,
        'offset' => $offset,
        'limit' => $limit
    ]);
}

/**
 * Get statistics
 */
function handleStats(NotificationLogger $logger): void {
    $period = $_GET['period'] ?? '24h';
    $validPeriods = ['1h', '24h', '7d', '30d'];

    if (!in_array($period, $validPeriods)) {
        $period = '24h';
    }

    $stats = $logger->getStats($period);

    echo json_encode([
        'success' => true,
        'stats' => $stats
    ]);
}

/**
 * Get timeline for a specific notification
 */
function handleTimeline(NotificationLogger $logger): void {
    $notificationId = isset($_GET['notification_id']) ? (int)$_GET['notification_id'] : 0;

    if (!$notificationId) {
        echo json_encode(['success' => false, 'error' => 'notification_id is required']);
        return;
    }

    $timeline = $logger->getTimeline($notificationId);

    // Decode extra_data for each entry
    foreach ($timeline as &$entry) {
        if ($entry['extra_data']) {
            $entry['extra_data'] = json_decode($entry['extra_data'], true);
        }
    }

    echo json_encode([
        'success' => true,
        'timeline' => $timeline
    ]);
}

/**
 * Export logs to CSV
 */
function handleExport(NotificationLogger $logger): void {
    $filters = [];

    if (!empty($_GET['notification_id'])) {
        $filters['notification_id'] = (int)$_GET['notification_id'];
    }

    if (!empty($_GET['user_id'])) {
        $filters['user_id'] = (int)$_GET['user_id'];
    }

    if (!empty($_GET['event_type'])) {
        $filters['event_type'] = $_GET['event_type'];
    }

    if (!empty($_GET['date_from'])) {
        $filters['date_from'] = $_GET['date_from'];
    }

    if (!empty($_GET['date_to'])) {
        $filters['date_to'] = $_GET['date_to'];
    }

    // Get all matching logs (up to 10000)
    $logs = $logger->getLogs($filters, 10000, 0);

    // Output as CSV
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="notification_logs_' . date('Y-m-d_His') . '.csv"');

    $output = fopen('php://output', 'w');

    // UTF-8 BOM for Excel
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

    // Headers
    fputcsv($output, [
        'ID',
        'Notification ID',
        'User ID',
        'Event Type',
        'Title',
        'Body',
        'Device',
        'OS',
        'Browser',
        'IP Address',
        'HTTP Status',
        'Error',
        'Created At'
    ]);

    // Data rows
    foreach ($logs as $log) {
        fputcsv($output, [
            $log['id'],
            $log['notification_id'],
            $log['user_id'],
            $log['event_type'],
            $log['notification_title'],
            $log['notification_body'],
            $log['device_type'],
            $log['os'],
            $log['browser'],
            $log['ip_address'],
            $log['http_status'],
            $log['error_message'],
            $log['created_at']
        ]);
    }

    fclose($output);
    exit;
}

/**
 * Get available event types
 */
function handleEventTypes(): void {
    $eventTypes = [
        'created' => 'התראה נוצרה',
        'scheduled' => 'התראה תוזמנה',
        'send_attempt' => 'ניסיון שליחה',
        'delivered' => 'נמסרה בהצלחה',
        'failed' => 'שליחה נכשלה',
        'retry' => 'ניסיון חוזר',
        'viewed' => 'נצפתה',
        'clicked' => 'נלחץ',
        'read' => 'סומנה כנקראה',
        'approval_sent' => 'התראת אישור נשלחה',
        'approved' => 'אושר',
        'rejected' => 'נדחה',
        'expired' => 'פג תוקף',
        'subscription_created' => 'מנוי נוצר',
        'subscription_removed' => 'מנוי הוסר',
        'test_started' => 'בדיקה התחילה',
        'test_completed' => 'בדיקה הסתיימה'
    ];

    echo json_encode([
        'success' => true,
        'event_types' => $eventTypes
    ]);
}
