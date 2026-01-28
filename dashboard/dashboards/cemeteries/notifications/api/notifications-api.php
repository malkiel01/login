<?php
/**
 * Scheduled Notifications API - ניהול התראות מתוזמנות
 *
 * Endpoints:
 * GET    ?action=list              - רשימת התראות
 * GET    ?action=get&id=X          - פרטי התראה
 * GET    ?action=get_users         - רשימת משתמשים לבחירה
 * POST   action=create             - יצירת התראה
 * POST   action=update             - עדכון התראה
 * POST   action=cancel             - ביטול התראה
 * POST   action=delete             - מחיקת התראה
 *
 * @version 1.0.0
 */

require_once $_SERVER['DOCUMENT_ROOT'] . '/dashboard/dashboards/cemeteries/api/api-auth.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/push/send-push.php';

// בדוק הרשאות לניהול התראות
if (!isAdmin() && !hasModulePermission('notifications', 'view')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'אין הרשאה לניהול התראות']);
    exit;
}

$pdo = getDBConnection();
$method = $_SERVER['REQUEST_METHOD'];

// Handle JSON body for POST
$input = [];
if ($method === 'POST') {
    $rawInput = file_get_contents('php://input');
    $input = json_decode($rawInput, true) ?? [];
}

$action = $_GET['action'] ?? $input['action'] ?? '';

// Create table if not exists
createTableIfNeeded($pdo);

try {
    switch ($action) {
        case 'list':
            handleList($pdo);
            break;
        case 'get':
            handleGet($pdo);
            break;
        case 'get_users':
            handleGetUsers($pdo);
            break;
        case 'create':
            requireCreatePermission('notifications');
            handleCreate($pdo, $input);
            break;
        case 'update':
            requireEditPermission('notifications');
            handleUpdate($pdo, $input);
            break;
        case 'cancel':
            requireEditPermission('notifications');
            handleCancel($pdo, $input);
            break;
        case 'delete':
            requireDeletePermission('notifications');
            handleDelete($pdo, $input);
            break;
        case 'get_delivery_status':
            handleGetDeliveryStatus($pdo);
            break;
        case 'resend_to_user':
            requireEditPermission('notifications');
            handleResendToUser($pdo, $input);
            break;
        default:
            throw new Exception('פעולה לא חוקית');
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

/**
 * Create scheduled_notifications table if not exists
 */
function createTableIfNeeded(PDO $pdo): void {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `scheduled_notifications` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `title` VARCHAR(255) NOT NULL,
            `body` TEXT NOT NULL,
            `notification_type` ENUM('info', 'warning', 'urgent') DEFAULT 'info',
            `target_users` JSON NOT NULL,
            `scheduled_at` DATETIME NULL,
            `url` VARCHAR(255) DEFAULT NULL,
            `status` ENUM('pending', 'sent', 'cancelled', 'failed') DEFAULT 'pending',
            `created_by` INT NOT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `sent_at` DATETIME DEFAULT NULL,
            `error_message` TEXT DEFAULT NULL,
            INDEX `idx_status_scheduled` (`status`, `scheduled_at`),
            INDEX `idx_created_by` (`created_by`),
            INDEX `idx_created_at` (`created_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
}

/**
 * רשימת התראות מתוזמנות
 */
function handleList(PDO $pdo): void {
    $status = $_GET['status'] ?? null;
    $limit = (int)($_GET['limit'] ?? 50);
    $offset = (int)($_GET['offset'] ?? 0);

    $sql = "
        SELECT
            sn.*,
            u.name as creator_name,
            u.username as creator_username
        FROM scheduled_notifications sn
        LEFT JOIN users u ON sn.created_by = u.id
        WHERE 1=1
    ";

    $params = [];

    if ($status) {
        $sql .= " AND sn.status = ?";
        $params[] = $status;
    }

    $sql .= " ORDER BY sn.created_at DESC LIMIT ? OFFSET ?";
    $params[] = $limit;
    $params[] = $offset;

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Count total
    $countSql = "SELECT COUNT(*) FROM scheduled_notifications WHERE 1=1";
    if ($status) {
        $countSql .= " AND status = '$status'";
    }
    $total = $pdo->query($countSql)->fetchColumn();

    echo json_encode([
        'success' => true,
        'data' => $notifications,
        'total' => (int)$total,
        'limit' => $limit,
        'offset' => $offset
    ]);
}

/**
 * פרטי התראה בודדת
 */
function handleGet(PDO $pdo): void {
    $id = (int)($_GET['id'] ?? 0);

    if (!$id) {
        throw new Exception('מזהה התראה חסר');
    }

    $stmt = $pdo->prepare("
        SELECT
            sn.*,
            u.name as creator_name
        FROM scheduled_notifications sn
        LEFT JOIN users u ON sn.created_by = u.id
        WHERE sn.id = ?
    ");
    $stmt->execute([$id]);
    $notification = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$notification) {
        throw new Exception('התראה לא נמצאה');
    }

    echo json_encode([
        'success' => true,
        'data' => $notification
    ]);
}

/**
 * רשימת משתמשים לבחירה
 */
function handleGetUsers(PDO $pdo): void {
    $stmt = $pdo->query("
        SELECT id, username, name, email
        FROM users
        WHERE is_active = 1
        ORDER BY name, username
    ");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'data' => $users
    ]);
}

/**
 * יצירת התראה חדשה
 */
function handleCreate(PDO $pdo, array $input): void {
    validateNotificationInput($input);

    $title = trim($input['title']);
    $body = trim($input['body']);
    $notificationType = $input['notification_type'] ?? 'info';
    $targetUsers = $input['target_users'] ?? [];
    $scheduledAt = $input['scheduled_at'] ?? null;
    $url = !empty($input['url']) ? trim($input['url']) : null;
    $createdBy = getCurrentUserId();

    // If scheduled_at is null or empty, send immediately
    $sendNow = empty($scheduledAt);

    $stmt = $pdo->prepare("
        INSERT INTO scheduled_notifications
        (title, body, notification_type, target_users, scheduled_at, url, status, created_by)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");

    $status = $sendNow ? 'pending' : 'pending';

    $stmt->execute([
        $title,
        $body,
        $notificationType,
        json_encode($targetUsers),
        $scheduledAt,
        $url,
        $status,
        $createdBy
    ]);

    $notificationId = $pdo->lastInsertId();

    // If send now, actually send the notifications
    if ($sendNow) {
        $sentCount = sendNotifications($pdo, $notificationId, $title, $body, $url, $targetUsers);

        // Update status to sent
        $pdo->prepare("
            UPDATE scheduled_notifications
            SET status = 'sent', sent_at = NOW()
            WHERE id = ?
        ")->execute([$notificationId]);

        echo json_encode([
            'success' => true,
            'id' => (int)$notificationId,
            'sent_count' => $sentCount,
            'message' => "ההתראה נשלחה ל-{$sentCount} משתמשים"
        ]);
    } else {
        echo json_encode([
            'success' => true,
            'id' => (int)$notificationId,
            'message' => 'ההתראה תוזמנה בהצלחה'
        ]);
    }
}

/**
 * עדכון התראה
 */
function handleUpdate(PDO $pdo, array $input): void {
    $id = (int)($input['id'] ?? 0);

    if (!$id) {
        throw new Exception('מזהה התראה חסר');
    }

    // Check if notification exists and is pending
    $stmt = $pdo->prepare("SELECT status FROM scheduled_notifications WHERE id = ?");
    $stmt->execute([$id]);
    $notification = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$notification) {
        throw new Exception('התראה לא נמצאה');
    }

    if ($notification['status'] !== 'pending') {
        throw new Exception('ניתן לערוך רק התראות בסטטוס ממתין');
    }

    validateNotificationInput($input);

    $title = trim($input['title']);
    $body = trim($input['body']);
    $notificationType = $input['notification_type'] ?? 'info';
    $targetUsers = $input['target_users'] ?? [];
    $scheduledAt = $input['scheduled_at'] ?? null;
    $url = !empty($input['url']) ? trim($input['url']) : null;

    $stmt = $pdo->prepare("
        UPDATE scheduled_notifications
        SET title = ?, body = ?, notification_type = ?, target_users = ?, scheduled_at = ?, url = ?
        WHERE id = ?
    ");

    $stmt->execute([
        $title,
        $body,
        $notificationType,
        json_encode($targetUsers),
        $scheduledAt,
        $url,
        $id
    ]);

    echo json_encode([
        'success' => true,
        'message' => 'ההתראה עודכנה בהצלחה'
    ]);
}

/**
 * ביטול התראה
 */
function handleCancel(PDO $pdo, array $input): void {
    $id = (int)($input['id'] ?? 0);

    if (!$id) {
        throw new Exception('מזהה התראה חסר');
    }

    // Check if notification exists and is pending
    $stmt = $pdo->prepare("SELECT status FROM scheduled_notifications WHERE id = ?");
    $stmt->execute([$id]);
    $notification = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$notification) {
        throw new Exception('התראה לא נמצאה');
    }

    if ($notification['status'] !== 'pending') {
        throw new Exception('ניתן לבטל רק התראות בסטטוס ממתין');
    }

    $stmt = $pdo->prepare("UPDATE scheduled_notifications SET status = 'cancelled' WHERE id = ?");
    $stmt->execute([$id]);

    echo json_encode([
        'success' => true,
        'message' => 'ההתראה בוטלה בהצלחה'
    ]);
}

/**
 * מחיקת התראה
 */
function handleDelete(PDO $pdo, array $input): void {
    $id = (int)($input['id'] ?? 0);

    if (!$id) {
        throw new Exception('מזהה התראה חסר');
    }

    $stmt = $pdo->prepare("DELETE FROM scheduled_notifications WHERE id = ?");
    $stmt->execute([$id]);

    echo json_encode([
        'success' => true,
        'message' => 'ההתראה נמחקה בהצלחה'
    ]);
}

/**
 * Validate notification input
 */
function validateNotificationInput(array $input): void {
    if (empty($input['title'])) {
        throw new Exception('כותרת ההתראה חסרה');
    }

    if (empty($input['body'])) {
        throw new Exception('תוכן ההתראה חסר');
    }

    if (empty($input['target_users']) || !is_array($input['target_users'])) {
        throw new Exception('יש לבחור לפחות משתמש אחד');
    }

    $validTypes = ['info', 'warning', 'urgent'];
    if (!empty($input['notification_type']) && !in_array($input['notification_type'], $validTypes)) {
        throw new Exception('סוג התראה לא חוקי');
    }
}

/**
 * Send notifications to users
 * Uses real Web Push notifications + fallback to polling table
 */
function sendNotifications(PDO $pdo, int $notificationId, string $title, string $body, ?string $url, array $targetUsers): int {
    $count = 0;
    $pushSent = 0;

    // Get user IDs to send to
    if (in_array('all', $targetUsers)) {
        // Send to all active users
        $stmt = $pdo->query("SELECT id FROM users WHERE is_active = 1");
        $userIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
    } else {
        $userIds = array_map('intval', $targetUsers);
    }

    // Send real Web Push notifications
    if (!empty($userIds)) {
        $pushResult = sendPushToUsers($userIds, $title, $body, $url);
        $pushSent = $pushResult['sent'] ?? 0;

        if ($pushSent > 0) {
            error_log("Web Push sent successfully to $pushSent devices");
        }
    }

    // Also insert into push_notifications table as fallback for polling
    $insertStmt = $pdo->prepare("
        INSERT INTO push_notifications (user_id, title, body, url)
        VALUES (?, ?, ?, ?)
    ");

    foreach ($userIds as $userId) {
        try {
            $insertStmt->execute([$userId, $title, $body, $url]);
            $count++;
        } catch (Exception $e) {
            // Log error but continue
            error_log("Failed to insert notification for user $userId: " . $e->getMessage());
        }
    }

    return $count;
}

/**
 * Get delivery status per user for a notification
 */
function handleGetDeliveryStatus(PDO $pdo): void {
    $id = (int)($_GET['id'] ?? 0);

    if (!$id) {
        throw new Exception('מזהה התראה חסר');
    }

    // Get the notification
    $stmt = $pdo->prepare("SELECT * FROM scheduled_notifications WHERE id = ?");
    $stmt->execute([$id]);
    $notification = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$notification) {
        throw new Exception('התראה לא נמצאה');
    }

    $targetUsers = json_decode($notification['target_users'], true);

    // Get target user IDs
    if (in_array('all', $targetUsers)) {
        $stmt = $pdo->query("SELECT id FROM users WHERE is_active = 1");
        $userIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
    } else {
        $userIds = array_map('intval', $targetUsers);
    }

    // Get user details with push subscription status
    $users = [];
    if (!empty($userIds)) {
        $placeholders = implode(',', array_fill(0, count($userIds), '?'));

        // Get users with their push subscription status
        $stmt = $pdo->prepare("
            SELECT
                u.id,
                u.name,
                u.username,
                u.email,
                (SELECT COUNT(*) FROM push_subscriptions ps WHERE ps.user_id = u.id AND ps.is_active = 1) as has_push_subscription,
                (SELECT COUNT(*) FROM push_notifications pn
                 WHERE pn.user_id = u.id
                 AND pn.title = ?
                 AND pn.is_delivered = 1) as is_delivered
            FROM users u
            WHERE u.id IN ($placeholders)
            ORDER BY u.name, u.username
        ");

        $params = array_merge([$notification['title']], $userIds);
        $stmt->execute($params);
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    echo json_encode([
        'success' => true,
        'notification' => $notification,
        'users' => $users
    ]);
}

/**
 * Resend notification to specific user
 */
function handleResendToUser(PDO $pdo, array $input): void {
    $notificationId = (int)($input['notification_id'] ?? 0);
    $userId = (int)($input['user_id'] ?? 0);

    if (!$notificationId || !$userId) {
        throw new Exception('חסרים פרטים');
    }

    // Get the notification
    $stmt = $pdo->prepare("SELECT * FROM scheduled_notifications WHERE id = ?");
    $stmt->execute([$notificationId]);
    $notification = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$notification) {
        throw new Exception('התראה לא נמצאה');
    }

    // Send push notification
    $pushResult = sendPushToUser(
        $userId,
        $notification['title'],
        $notification['body'],
        $notification['url']
    );

    // Insert to push_notifications table
    $stmt = $pdo->prepare("
        INSERT INTO push_notifications (user_id, title, body, url)
        VALUES (?, ?, ?, ?)
    ");
    $stmt->execute([
        $userId,
        $notification['title'],
        $notification['body'],
        $notification['url']
    ]);

    echo json_encode([
        'success' => true,
        'push_sent' => $pushResult['sent'] ?? 0,
        'message' => 'ההתראה נשלחה שוב למשתמש'
    ]);
}

// Permission helpers are already defined in api-auth.php:
// - requireViewPermission()
// - requireCreatePermission()
// - requireEditPermission()
// - requireDeletePermission()
