<?php
/**
 * API לניהול התראות Push
 * api/notifications.php
 */

session_start();
require_once '../config.php';

header('Content-Type: application/json');

// רק למשתמשים מחוברים
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';
$userId = $_SESSION['user_id'];

switch ($action) {
    
    // בדיקת התראות חדשות
    case 'check':
        $lastCheck = $_POST['last_check'] ?? 0;
        
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("
            SELECT * FROM push_notifications 
            WHERE user_id = ? 
            AND is_delivered = 0
            AND created_at > FROM_UNIXTIME(?)
            ORDER BY created_at DESC
            LIMIT 10
        ");
        
        $stmt->execute([$userId, $lastCheck / 1000]);
        $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // סמן כנמסרו
        if (!empty($notifications)) {
            $ids = array_column($notifications, 'id');
            $placeholders = str_repeat('?,', count($ids) - 1) . '?';
            $updateStmt = $pdo->prepare("
                UPDATE push_notifications 
                SET is_delivered = 1, delivered_at = NOW() 
                WHERE id IN ($placeholders)
            ");
            $updateStmt->execute($ids);
        }
        
        echo json_encode([
            'success' => true,
            'notifications' => $notifications,
            'count' => count($notifications)
        ]);
        break;
    
    // שליחת התראה למשתמש אחר
    case 'send':
        $targetUserId = $_POST['target_user_id'] ?? null;
        $title = $_POST['title'] ?? '';
        $body = $_POST['body'] ?? '';
        $url = $_POST['url'] ?? null;
        
        if (!$targetUserId || !$title || !$body) {
            echo json_encode(['success' => false, 'error' => 'Missing parameters']);
            exit;
        }
        
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("
            INSERT INTO push_notifications (user_id, title, body, url) 
            VALUES (?, ?, ?, ?)
        ");
        
        $result = $stmt->execute([$targetUserId, $title, $body, $url]);
        
        echo json_encode(['success' => $result]);
        break;
    
    // סימון כנקרא
    case 'mark_read':
        $notificationId = $_POST['notification_id'] ?? null;
        
        if (!$notificationId) {
            echo json_encode(['success' => false, 'error' => 'Missing notification ID']);
            exit;
        }
        
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("
            UPDATE push_notifications 
            SET is_read = 1 
            WHERE id = ? AND user_id = ?
        ");
        
        $result = $stmt->execute([$notificationId, $userId]);
        
        echo json_encode(['success' => $result]);
        break;
    
    // קבלת כל ההתראות
    case 'get_all':
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("
            SELECT * FROM push_notifications 
            WHERE user_id = ? 
            ORDER BY created_at DESC
            LIMIT 50
        ");
        
        $stmt->execute([$userId]);
        $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'notifications' => $notifications
        ]);
        break;
    
    // ניקוי התראות ישנות
    case 'cleanup':
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("
            DELETE FROM push_notifications 
            WHERE user_id = ? 
            AND created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)
        ");
        
        $stmt->execute([$userId]);
        
        echo json_encode(['success' => true, 'deleted' => $stmt->rowCount()]);
        break;
    
    default:
        echo json_encode(['success' => false, 'error' => 'Invalid action']);
}
?>