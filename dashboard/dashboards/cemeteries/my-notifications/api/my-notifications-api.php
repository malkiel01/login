<?php
/**
 * My Notifications API
 * API לניהול התראות המשתמש
 *
 * @version 1.0.0
 */

header('Content-Type: application/json; charset=utf-8');

require_once $_SERVER['DOCUMENT_ROOT'] . '/dashboard/dashboards/cemeteries/config.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/auth/token-init.php';

// בדיקת הרשאות - משתמש מחובר
if (!isAuthenticated()) {
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
    echo json_encode(['success' => false, 'error' => 'Server error']);
}

/**
 * קבלת התראות שלא נקראו
 */
function getUnreadNotifications($conn, $userId) {
    // בדיקה אם העמודה read_at קיימת
    $hasReadAt = checkReadAtColumn($conn);

    if ($hasReadAt) {
        // שאילתה עם read_at
        $sql = "
            SELECT
                sn.id,
                sn.title,
                sn.body,
                sn.notification_type,
                sn.url,
                sn.created_at,
                nd.delivered_at,
                nd.read_at
            FROM notification_deliveries nd
            INNER JOIN scheduled_notifications sn ON sn.id = nd.notification_id
            WHERE nd.user_id = ?
              AND nd.status = 'delivered'
              AND nd.read_at IS NULL
            ORDER BY sn.created_at DESC
            LIMIT 50
        ";
    } else {
        // fallback - כל ההתראות שנמסרו
        $sql = "
            SELECT
                sn.id,
                sn.title,
                sn.body,
                sn.notification_type,
                sn.url,
                sn.created_at,
                nd.delivered_at,
                NULL as read_at
            FROM notification_deliveries nd
            INNER JOIN scheduled_notifications sn ON sn.id = nd.notification_id
            WHERE nd.user_id = ?
              AND nd.status = 'delivered'
            ORDER BY sn.created_at DESC
            LIMIT 50
        ";
    }

    $stmt = $conn->prepare($sql);
    $stmt->execute([$userId]);
    $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'notifications' => $notifications
    ]);
}

/**
 * קבלת היסטוריית התראות
 */
function getHistoryNotifications($conn, $userId, $offset, $limit) {
    $hasReadAt = checkReadAtColumn($conn);

    $sql = "
        SELECT
            sn.id,
            sn.title,
            sn.body,
            sn.notification_type,
            sn.url,
            sn.created_at,
            nd.delivered_at" . ($hasReadAt ? ", nd.read_at" : ", NULL as read_at") . "
        FROM notification_deliveries nd
        INNER JOIN scheduled_notifications sn ON sn.id = nd.notification_id
        WHERE nd.user_id = ?
          AND nd.status = 'delivered'
        ORDER BY sn.created_at DESC
        LIMIT ? OFFSET ?
    ";

    $stmt = $conn->prepare($sql);
    $stmt->execute([$userId, $limit + 1, $offset]); // +1 לבדיקה אם יש עוד
    $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $hasMore = count($notifications) > $limit;
    if ($hasMore) {
        array_pop($notifications); // הסר את האחרון
    }

    echo json_encode([
        'success' => true,
        'notifications' => $notifications,
        'hasMore' => $hasMore
    ]);
}

/**
 * סימון התראה כנקראה
 */
function markAsRead($conn, $userId, $notificationId) {
    if (!$notificationId) {
        echo json_encode(['success' => false, 'error' => 'Missing notification_id']);
        return;
    }

    // וודא שהעמודה קיימת, אם לא - צור אותה
    ensureReadAtColumn($conn);

    $sql = "
        UPDATE notification_deliveries
        SET read_at = NOW()
        WHERE notification_id = ?
          AND user_id = ?
          AND read_at IS NULL
    ";

    $stmt = $conn->prepare($sql);
    $stmt->execute([$notificationId, $userId]);

    echo json_encode([
        'success' => true,
        'updated' => $stmt->rowCount()
    ]);
}

/**
 * סימון כל ההתראות כנקראו
 */
function markAllAsRead($conn, $userId) {
    // וודא שהעמודה קיימת
    ensureReadAtColumn($conn);

    $sql = "
        UPDATE notification_deliveries
        SET read_at = NOW()
        WHERE user_id = ?
          AND status = 'delivered'
          AND read_at IS NULL
    ";

    $stmt = $conn->prepare($sql);
    $stmt->execute([$userId]);

    echo json_encode([
        'success' => true,
        'updated' => $stmt->rowCount()
    ]);
}

/**
 * בדיקה אם העמודה read_at קיימת
 */
function checkReadAtColumn($conn) {
    static $hasColumn = null;

    if ($hasColumn !== null) {
        return $hasColumn;
    }

    try {
        $stmt = $conn->query("SHOW COLUMNS FROM notification_deliveries LIKE 'read_at'");
        $hasColumn = $stmt->rowCount() > 0;
    } catch (Exception $e) {
        $hasColumn = false;
    }

    return $hasColumn;
}

/**
 * יצירת העמודה read_at אם לא קיימת
 */
function ensureReadAtColumn($conn) {
    if (!checkReadAtColumn($conn)) {
        try {
            $conn->exec("
                ALTER TABLE notification_deliveries
                ADD COLUMN read_at DATETIME DEFAULT NULL
                COMMENT 'When the user read this notification'
                AFTER delivered_at
            ");
            // Reset static cache
            checkReadAtColumn($conn);
        } catch (Exception $e) {
            error_log('[my-notifications-api] Failed to add read_at column: ' . $e->getMessage());
        }
    }
}
