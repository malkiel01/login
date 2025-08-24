<?php
// api/simple-notifications.php - מערכת התראות פשוטה
session_start();
require_once '../config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

$action = $_GET['action'] ?? '';
$data = json_decode(file_get_contents('php://input'), true);

switch ($action) {
    case 'save-token':
        $_SESSION['notification_token'] = $data['token'] ?? '';
        echo json_encode(['success' => true, 'message' => 'Token saved']);
        break;
        
    case 'test':
        echo json_encode(['success' => true, 'message' => 'Test notification']);
        break;
        
    default:
        echo json_encode(['success' => false, 'message' => 'Unknown action']);
}
?>