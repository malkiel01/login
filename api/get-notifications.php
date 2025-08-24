<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

require_once __DIR__ . '/../config.php';

try {
    $pdo = getDBConnection();
    
    // קבל התראות לא נקראות
    $stmt = $pdo->prepare("
        SELECT * FROM notification_queue 
        WHERE user_id = ? 
        AND status = 'pending'
        AND created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)
        ORDER BY created_at DESC
        LIMIT 5
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $result = [];
    $ids = [];
    
    foreach ($notifications as $notification) {
        $data = json_decode($notification['data'], true);
        $result[] = $data;
        $ids[] = $notification['id'];
    }
    
    // סמן כנקראו
    if (!empty($ids)) {
        $placeholders = str_repeat('?,', count($ids) - 1) . '?';
        $stmt = $pdo->prepare("
            UPDATE notification_queue 
            SET status = 'read' 
            WHERE id IN ($placeholders)
        ");
        $stmt->execute($ids);
    }
    
    echo json_encode([
        'success' => true,
        'notifications' => $result,
        'count' => count($result)
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>