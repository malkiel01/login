<?php
/**
 * My Notifications API
 * API לניהול התראות המשתמש
 *
 * @version 1.1.0
 */

header('Content-Type: application/json; charset=utf-8');

require_once $_SERVER['DOCUMENT_ROOT'] . '/dashboard/dashboards/cemeteries/config.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/auth/token-init.php';

// בדיקת הרשאות - משתמש מחובר
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$userId = getCurrentUserId();
$conn = getDBConnection();

// קבלת הפעולה
$action = $_GET['action'] ?? $_POST['action'] ?? '';

// Handle JSON body for POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($_POST)) {
    $jsonBody = file_get_contents('php://input');
    if ($jsonBody) {
        $postData = json_decode($jsonBody, true);
        if ($postData) {
            $_POST = $postData;
            $action = $postData['action'] ?? $action;
        }
    }
}

try {
    switch ($action) {
        case 'get_unread':
            getUnreadNotifications($conn, $userId);
            break;

        case 'get_history':
            $offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;
            $limit = isset($_GET['limit']) ? min(50, (int)$_GET['limit']) : 20;
            getHistoryNotifications($conn, $userId, $offset, $limit);
            break;

        case 'mark_read':
            $notificationId = $_POST['notification_id'] ?? null;
            markAsRead($conn, $userId, $notificationId);
            break;

        case 'mark_all_read':
            markAllAsRead($conn, $userId);
            break;

        default:
            echo json_encode(['success' => false, 'error' => 'Invalid action']);
    }
} catch (Exception $e) {
    error_log('[my-notifications-api] Error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Server error: ' . $e->getMessage()]);
}

/**
 * קבלת התראות שלא נקראו
 */
function getUnreadNotifications($conn, $userId) {
    $sql = "
        SELECT
            pn.id,
            pn.scheduled_notification_id,
            pn.title,
            pn.body,
            pn.url,
            pn.is_read,
            pn.is_delivered,
            pn.created_at,
            pn.delivered_at,
            'info' as notification_type,
            na.status as approval_status,
            na.responded_at as approval_responded_at,
            sn.requires_approval
        FROM push_notifications pn
        LEFT JOIN scheduled_notifications sn ON sn.id = pn.scheduled_notification_id
        LEFT JOIN notification_approvals na ON na.notification_id = pn.scheduled_notification_id AND na.user_id = pn.user_id
        WHERE pn.user_id = ?
          AND pn.is_read = 0
        ORDER BY pn.created_at DESC
        LIMIT 50
    ";

    $stmt = $conn->prepare($sql);
    $stmt->execute([$userId]);
    $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Convert to expected format with UTC timezone
    foreach ($notifications as &$n) {
        $n['read_at'] = null;
        // Convert to ISO format with timezone for proper JS parsing
        if ($n['created_at']) {
            $dt = new DateTime($n['created_at'], new DateTimeZone('America/Boise'));
            $n['created_at'] = $dt->format('c'); // ISO 8601 format
        }
        if ($n['approval_responded_at']) {
            $dt = new DateTime($n['approval_responded_at'], new DateTimeZone('America/Boise'));
            $n['approval_responded_at'] = $dt->format('c');
        }
    }

    echo json_encode([
        'success' => true,
        'notifications' => $notifications
    ]);
}

/**
 * קבלת היסטוריית התראות
 */
function getHistoryNotifications($conn, $userId, $offset, $limit) {
    $sql = "
        SELECT
            pn.id,
            pn.scheduled_notification_id,
            pn.title,
            pn.body,
            pn.url,
            pn.is_read,
            pn.is_delivered,
            pn.created_at,
            pn.delivered_at,
            'info' as notification_type,
            na.status as approval_status,
            na.responded_at as approval_responded_at,
            sn.requires_approval
        FROM push_notifications pn
        LEFT JOIN scheduled_notifications sn ON sn.id = pn.scheduled_notification_id
        LEFT JOIN notification_approvals na ON na.notification_id = pn.scheduled_notification_id AND na.user_id = pn.user_id
        WHERE pn.user_id = ?
          AND pn.is_read = 1
        ORDER BY pn.created_at DESC
        LIMIT ? OFFSET ?
    ";

    $stmt = $conn->prepare($sql);
    $stmt->execute([$userId, $limit + 1, $offset]); // +1 לבדיקה אם יש עוד
    $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $hasMore = count($notifications) > $limit;
    if ($hasMore) {
        array_pop($notifications); // הסר את האחרון
    }

    // Convert to expected format with UTC timezone
    foreach ($notifications as &$n) {
        $n['read_at'] = $n['is_read'] ? $n['delivered_at'] : null;
        // Convert to ISO format with timezone for proper JS parsing
        if ($n['created_at']) {
            $dt = new DateTime($n['created_at'], new DateTimeZone('America/Boise'));
            $n['created_at'] = $dt->format('c'); // ISO 8601 format
        }
        if ($n['approval_responded_at']) {
            $dt = new DateTime($n['approval_responded_at'], new DateTimeZone('America/Boise'));
            $n['approval_responded_at'] = $dt->format('c');
        }
    }

    echo json_encode([
        'success' => true,
        'notifications' => $notifications,
        'hasMore' => $hasMore
    ]);
}

/**
 * סימון התראה כנקראה
 * מקבל או push_notifications.id או scheduled_notifications.id
 */
function markAsRead($conn, $userId, $notificationId) {
    if (!$notificationId) {
        echo json_encode(['success' => false, 'error' => 'Missing notification_id']);
        return;
    }

    // Convert to integer for proper comparison
    $notificationIdInt = (int)$notificationId;
    $userIdInt = (int)$userId;

    // נסה קודם לפי scheduled_notification_id (מה שמגיע מה-push)
    $sql = "
        UPDATE push_notifications
        SET is_read = 1
        WHERE scheduled_notification_id = ?
          AND user_id = ?
          AND is_read = 0
    ";

    $stmt = $conn->prepare($sql);
    $stmt->execute([$notificationIdInt, $userIdInt]);
    $updated = $stmt->rowCount();

    // אם לא נמצא, נסה לפי id ישיר
    if ($updated == 0) {
        $sql = "
            UPDATE push_notifications
            SET is_read = 1
            WHERE id = ?
              AND user_id = ?
              AND is_read = 0
        ";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$notificationIdInt, $userIdInt]);
        $updated = $stmt->rowCount();
    }

    echo json_encode([
        'success' => true,
        'updated' => $updated,
        'notification_id' => $notificationIdInt,
        'user_id' => $userIdInt
    ]);
}

/**
 * סימון כל ההתראות כנקראו
 */
function markAllAsRead($conn, $userId) {
    $sql = "
        UPDATE push_notifications
        SET is_read = 1
        WHERE user_id = ?
          AND is_read = 0
    ";

    $stmt = $conn->prepare($sql);
    $stmt->execute([$userId]);

    echo json_encode([
        'success' => true,
        'updated' => $stmt->rowCount()
    ]);
}
