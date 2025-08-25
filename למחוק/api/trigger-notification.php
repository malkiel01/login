<?php
// api/trigger-notification.php - שליחת התראות בלי Firebase
session_start();
require_once '../config.php';

/**
 * שליחת התראה פשוטה דרך JavaScript
 * לא צריך Firebase או FCM Server Key!
 */
function triggerSimpleNotification($userId, $title, $body, $url = '/dashboard.php') {
    try {
        $pdo = getDBConnection();
        
        // שמור את ההתראה ב-DB
        $stmt = $pdo->prepare("
            INSERT INTO notification_queue 
            (user_id, title, body, url, status, created_at)
            VALUES (?, ?, ?, ?, 'pending', NOW())
        ");
        $stmt->execute([$userId, $title, $body, $url]);
        
        return [
            'success' => true,
            'notification_id' => $pdo->lastInsertId(),
            'message' => 'Notification queued'
        ];
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

/**
 * פונקציה לשליחת התראה בהזמנה לקבוצה
 */
function sendInvitationNotification($invitationId) {
    try {
        $pdo = getDBConnection();
        
        // שלוף פרטי ההזמנה
        $stmt = $pdo->prepare("
            SELECT gi.*, pg.name as group_name, u.name as inviter_name, u2.id as invitee_id
            FROM group_invitations gi
            JOIN purchase_groups pg ON gi.group_id = pg.id
            JOIN users u ON gi.invited_by = u.id
            LEFT JOIN users u2 ON u2.email = gi.email
            WHERE gi.id = ?
        ");
        $stmt->execute([$invitationId]);
        $invitation = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$invitation || !$invitation['invitee_id']) {
            return false;
        }
        
        $title = '🎉 הזמנה לקבוצת רכישה!';
        $body = sprintf('%s הזמין אותך להצטרף לקבוצה "%s"', 
            $invitation['inviter_name'], 
            $invitation['group_name']
        );
        $url = '/dashboard.php#invitations';
        
        return triggerSimpleNotification($invitation['invitee_id'], $title, $body, $url);
        
    } catch (Exception $e) {
        error_log('Error: ' . $e->getMessage());
        return false;
    }
}

// אם זו בקשת AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['success' => false, 'message' => 'Not logged in']);
        exit;
    }
    
    $data = json_decode(file_get_contents('php://input'), true);
    $action = $_GET['action'] ?? '';
    
    switch ($action) {
        case 'test':
            $result = triggerSimpleNotification(
                $_SESSION['user_id'],
                'התראת בדיקה 🔔',
                'זו התראה שנשלחה בלי Firebase!',
                '/dashboard.php'
            );
            echo json_encode($result);
            break;
            
        case 'check':
            // בדוק אם יש התראות ממתינות
            $stmt = $pdo->prepare("
                SELECT * FROM notification_queue 
                WHERE user_id = ? AND status = 'pending'
                ORDER BY created_at DESC
                LIMIT 10
            ");
            $stmt->execute([$_SESSION['user_id']]);
            $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // סמן כנקראו
            if (!empty($notifications)) {
                $ids = array_column($notifications, 'id');
                $placeholders = str_repeat('?,', count($ids) - 1) . '?';
                $stmt = $pdo->prepare("
                    UPDATE notification_queue 
                    SET status = 'delivered' 
                    WHERE id IN ($placeholders)
                ");
                $stmt->execute($ids);
            }
            
            echo json_encode([
                'success' => true,
                'notifications' => $notifications
            ]);
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Unknown action']);
    }
}
?>