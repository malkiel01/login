<?php
// api/create-test-notification.php - יצירת התראת טסט
session_start();
require_once '../config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Not logged in']);
    exit;
}

$user_id = $_SESSION['user_id'];
$pdo = getDBConnection();

try {
    // צור התראת טסט
    $stmt = $pdo->prepare("
        INSERT INTO notification_queue (type, data, status, created_at) 
        VALUES ('test', ?, 'pending', NOW())
    ");
    
    $notificationData = json_encode([
        'user_id' => $user_id,
        'title' => 'התראת בדיקה 🧪',
        'body' => 'זו התראת בדיקה שנוצרה ב-' . date('H:i:s'),
        'icon' => '/family/images/icons/android/android-launchericon-192-192.png',
        'badge' => '/family/images/icons/android/android-launchericon-96-96.png',
        'url' => '/family/dashboard.php',
        'type' => 'test'
    ]);
    
    $result = $stmt->execute([$notificationData]);
    
    if ($result) {
        echo json_encode([
            'success' => true,
            'message' => 'Test notification created',
            'notification_id' => $pdo->lastInsertId()
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to create notification']);
    }
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>