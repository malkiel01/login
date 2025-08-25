<?php
// api/debug-notifications.php - בדיקת מצב התראות
session_start();
require_once '../config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Not logged in']);
    exit;
}

$user_id = $_SESSION['user_id'];
$pdo = getDBConnection();

$debug = [
    'user_id' => $user_id,
    'user_name' => $_SESSION['name'] ?? 'Unknown',
    'timestamp' => date('Y-m-d H:i:s')
];

// בדוק אם הטבלה notification_queue קיימת
try {
    $stmt = $pdo->query("SHOW TABLES LIKE 'notification_queue'");
    $debug['notification_queue_exists'] = $stmt->fetch() ? true : false;
    
    if ($debug['notification_queue_exists']) {
        // ספור כמה התראות יש
        $stmt = $pdo->query("SELECT COUNT(*) FROM notification_queue");
        $debug['total_notifications_in_queue'] = $stmt->fetchColumn();
        
        // ספור כמה ממתינות
        $stmt = $pdo->query("SELECT COUNT(*) FROM notification_queue WHERE status = 'pending'");
        $debug['pending_notifications'] = $stmt->fetchColumn();
        
        // קבל 5 אחרונות
        $stmt = $pdo->prepare("
            SELECT id, type, status, created_at 
            FROM notification_queue 
            ORDER BY created_at DESC 
            LIMIT 5
        ");
        $stmt->execute();
        $debug['recent_notifications'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Exception $e) {
    $debug['queue_error'] = $e->getMessage();
}

// בדוק הזמנות ממתינות
try {
    $stmt = $pdo->prepare("
        SELECT COUNT(*) 
        FROM group_invitations gi
        JOIN users u ON u.email = gi.email
        WHERE u.id = ? AND gi.status = 'pending'
    ");
    $stmt->execute([$user_id]);
    $debug['pending_invitations'] = $stmt->fetchColumn();
} catch (Exception $e) {
    $debug['invitations_error'] = $e->getMessage();
}

// בדוק קבוצות
try {
    $stmt = $pdo->prepare("
        SELECT pg.id, pg.name, COUNT(gm2.id) as members
        FROM purchase_groups pg
        JOIN group_members gm ON pg.id = gm.group_id
        LEFT JOIN group_members gm2 ON pg.id = gm2.group_id AND gm2.is_active = 1
        WHERE gm.user_id = ? AND gm.is_active = 1
        GROUP BY pg.id
    ");
    $stmt->execute([$user_id]);
    $debug['groups'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $debug['groups_error'] = $e->getMessage();
}

// בדוק אם יש push_subscriptions
try {
    $stmt = $pdo->query("SHOW TABLES LIKE 'push_subscriptions'");
    $debug['push_subscriptions_exists'] = $stmt->fetch() ? true : false;
    
    if ($debug['push_subscriptions_exists']) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM push_subscriptions WHERE user_id = ? AND is_active = 1");
        $stmt->execute([$user_id]);
        $debug['active_subscriptions'] = $stmt->fetchColumn();
    }
} catch (Exception $e) {
    $debug['subscriptions_error'] = $e->getMessage();
}

echo json_encode($debug, JSON_PRETTY_PRINT);
?>