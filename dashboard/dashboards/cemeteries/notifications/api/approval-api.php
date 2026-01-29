<?php
/**
 * Notification Approval API
 * Handles user responses to approval notifications
 *
 * @version 1.0.0
 */

require_once $_SERVER['DOCUMENT_ROOT'] . '/dashboard/dashboards/cemeteries/api/api-auth.php';
require_once __DIR__ . '/NotificationLogger.php';

header('Content-Type: application/json');

// User must be logged in
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'לא מחובר']);
    exit;
}

$pdo = getDBConnection();
$userId = getCurrentUserId();
$method = $_SERVER['REQUEST_METHOD'];
$logger = NotificationLogger::getInstance($pdo);

// Handle JSON body for POST
$input = [];
if ($method === 'POST') {
    $rawInput = file_get_contents('php://input');
    $input = json_decode($rawInput, true) ?? [];
}

$action = $_GET['action'] ?? $input['action'] ?? '';

try {
    switch ($action) {
        case 'respond':
            handleRespond($pdo, $userId, $input, $logger);
            break;
        case 'get_status':
            handleGetStatus($pdo, $userId, $logger);
            break;
        case 'get_notification':
            handleGetNotification($pdo, $userId, $logger);
            break;
        default:
            throw new Exception('פעולה לא חוקית');
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

/**
 * Handle user response (approve/reject)
 */
function handleRespond(PDO $pdo, int $userId, array $input, NotificationLogger $logger): void {
    $notificationId = (int)($input['notification_id'] ?? 0);
    $response = $input['response'] ?? ''; // 'approved' or 'rejected'
    $biometricVerified = !empty($input['biometric_verified']);

    if (!$notificationId) {
        throw new Exception('מזהה התראה חסר');
    }

    if (!in_array($response, ['approved', 'rejected'])) {
        throw new Exception('תגובה לא תקינה');
    }

    // Get the notification
    $stmt = $pdo->prepare("
        SELECT * FROM scheduled_notifications
        WHERE id = ? AND requires_approval = 1
    ");
    $stmt->execute([$notificationId]);
    $notification = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$notification) {
        throw new Exception('ההתראה לא נמצאה או אינה דורשת אישור');
    }

    // Check if user is a target
    $targetUsers = json_decode($notification['target_users'], true);
    if (!in_array('all', $targetUsers) && !in_array($userId, $targetUsers)) {
        throw new Exception('אינך מורשה להגיב להודעה זו');
    }

    // Check if expired
    if ($notification['approval_expires_at'] && strtotime($notification['approval_expires_at']) < time()) {
        throw new Exception('פג תוקף האישור');
    }

    // Check if already responded
    $stmt = $pdo->prepare("
        SELECT * FROM notification_approvals
        WHERE notification_id = ? AND user_id = ?
    ");
    $stmt->execute([$notificationId, $userId]);
    $existing = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($existing && in_array($existing['status'], ['approved', 'rejected'])) {
        throw new Exception('כבר הגבת להודעה זו');
    }

    // Record the response
    $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;

    if ($existing) {
        // Update existing record
        $stmt = $pdo->prepare("
            UPDATE notification_approvals
            SET status = ?,
                responded_at = NOW(),
                ip_address = ?,
                user_agent = ?,
                biometric_verified = ?
            WHERE id = ?
        ");
        $stmt->execute([$response, $ipAddress, $userAgent, $biometricVerified ? 1 : 0, $existing['id']]);
    } else {
        // Insert new record
        $stmt = $pdo->prepare("
            INSERT INTO notification_approvals
            (notification_id, user_id, status, responded_at, ip_address, user_agent, biometric_verified)
            VALUES (?, ?, ?, NOW(), ?, ?, ?)
        ");
        $stmt->execute([$notificationId, $userId, $response, $ipAddress, $userAgent, $biometricVerified ? 1 : 0]);
    }

    // Mark the push notification as read
    $stmt = $pdo->prepare("
        UPDATE push_notifications
        SET is_read = 1
        WHERE scheduled_notification_id = ?
          AND user_id = ?
          AND is_read = 0
    ");
    $stmt->execute([$notificationId, $userId]);
    $markedRead = $stmt->rowCount();

    // Log the action to notification_logs
    if ($response === 'approved') {
        $logger->logApproved($notificationId, $userId, $biometricVerified, $userAgent);
    } else {
        $logger->logRejected($notificationId, $userId, $userAgent);
    }

    // Send feedback to sender if enabled
    $logger->sendFeedbackToSender($notificationId, $userId, $response);

    // Also keep error_log for backwards compatibility
    error_log("[Approval] User {$userId} {$response} notification {$notificationId}, biometric={$biometricVerified}, markedRead={$markedRead}");

    echo json_encode([
        'success' => true,
        'status' => $response,
        'biometric_verified' => $biometricVerified,
        'marked_read' => $markedRead,
        'message' => $response === 'approved' ? 'האישור נרשם בהצלחה' : 'הדחייה נרשמה'
    ]);
}

/**
 * Get approval status for a notification
 */
function handleGetStatus(PDO $pdo, int $userId, NotificationLogger $logger): void {
    $notificationId = (int)($_GET['notification_id'] ?? 0);

    if (!$notificationId) {
        throw new Exception('מזהה התראה חסר');
    }

    $stmt = $pdo->prepare("
        SELECT na.*, sn.title, sn.approval_expires_at
        FROM notification_approvals na
        JOIN scheduled_notifications sn ON sn.id = na.notification_id
        WHERE na.notification_id = ? AND na.user_id = ?
    ");
    $stmt->execute([$notificationId, $userId]);
    $approval = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$approval) {
        echo json_encode([
            'success' => true,
            'status' => null,
            'message' => 'לא נמצא רשום אישור'
        ]);
        return;
    }

    // Check if expired
    if ($approval['approval_expires_at'] && strtotime($approval['approval_expires_at']) < time() && $approval['status'] === 'pending') {
        // Update to expired
        $pdo->prepare("UPDATE notification_approvals SET status = 'expired' WHERE id = ?")->execute([$approval['id']]);
        $approval['status'] = 'expired';

        // Log expiration
        $logger->logExpired($notificationId, $userId);
    }

    echo json_encode([
        'success' => true,
        'status' => $approval['status'],
        'responded_at' => $approval['responded_at'],
        'biometric_verified' => (bool)$approval['biometric_verified']
    ]);
}

/**
 * Get notification details for modal display
 */
function handleGetNotification(PDO $pdo, int $userId, NotificationLogger $logger): void {
    $notificationId = (int)($_GET['id'] ?? 0);

    if (!$notificationId) {
        throw new Exception('מזהה התראה חסר');
    }

    // Get the notification
    $stmt = $pdo->prepare("
        SELECT sn.*, u.name as creator_name
        FROM scheduled_notifications sn
        LEFT JOIN users u ON u.id = sn.created_by
        WHERE sn.id = ? AND sn.requires_approval = 1
    ");
    $stmt->execute([$notificationId]);
    $notification = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$notification) {
        throw new Exception('ההתראה לא נמצאה או אינה דורשת אישור');
    }

    // Check if user is a target
    $targetUsers = json_decode($notification['target_users'], true);
    $isTarget = in_array('all', $targetUsers) || in_array($userId, $targetUsers);

    if (!$isTarget) {
        throw new Exception('אינך מורשה לצפות בהודעה זו');
    }

    // Get user's approval status
    $stmt = $pdo->prepare("
        SELECT * FROM notification_approvals
        WHERE notification_id = ? AND user_id = ?
    ");
    $stmt->execute([$notificationId, $userId]);
    $approval = $stmt->fetch(PDO::FETCH_ASSOC);

    // Check if expired
    $isExpired = false;
    if ($notification['approval_expires_at'] && strtotime($notification['approval_expires_at']) < time()) {
        $isExpired = true;
        if ($approval && $approval['status'] === 'pending') {
            $pdo->prepare("UPDATE notification_approvals SET status = 'expired' WHERE id = ?")->execute([$approval['id']]);
            $approval['status'] = 'expired';

            // Log expiration
            $logger->logExpired($notificationId, $userId);
        }
    }

    // Log notification viewed
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;
    $logger->logViewed($notificationId, $userId, $userAgent);

    // Send feedback to sender if enabled (first view)
    $logger->sendFeedbackToSender($notificationId, $userId, 'viewed');

    echo json_encode([
        'success' => true,
        'notification' => [
            'id' => $notification['id'],
            'title' => $notification['title'],
            'body' => $notification['body'],
            'approval_message' => $notification['approval_message'],
            'approval_expires_at' => $notification['approval_expires_at'],
            'creator_name' => $notification['creator_name'],
            'created_at' => $notification['created_at']
        ],
        'approval' => $approval,
        'expired' => $isExpired
    ]);
}
