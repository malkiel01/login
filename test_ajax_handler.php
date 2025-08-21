<?php
// test_ajax_handler.php - מטפל AJAX ייעודי

// מניעת כל פלט
error_reporting(0);
ini_set('display_errors', 0);

// מניעת פלט נוסף
ob_start();
ob_clean();

// בדיקה שזו בקשת POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Content-Type: application/json');
    die(json_encode(['error' => 'Method not allowed']));
}

// הגדרת headers
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

// טיפול בפעולות
$action = $_POST['action'] ?? '';

switch($action) {
    case 'test':
        $response = [
            'success' => true,
            'message' => 'Test successful',
            'timestamp' => time()
        ];
        break;
        
    case 'addMember':
        // כאן רק נדמה הוספת משתתף
        $response = [
            'success' => true,
            'message' => 'Member would be added',
            'data' => [
                'email' => $_POST['email'] ?? '',
                'nickname' => $_POST['nickname'] ?? '',
                'participation_type' => $_POST['participation_type'] ?? '',
                'participation_value' => $_POST['participation_value'] ?? ''
            ]
        ];
        break;
        
    default:
        $response = [
            'success' => false,
            'message' => 'Unknown action: ' . $action
        ];
}

// ניקוי הבאפר והחזרת JSON
ob_end_clean();
echo json_encode($response);
exit();
?>