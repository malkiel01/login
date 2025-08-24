<?php
// api/send-push-notification.php - שליחת התראות Push
require_once '../config.php';

/**
 * פונקציה לשליחת Push Notification
 */
function sendPushNotification($userId, $title, $body, $url = '/dashboard.php', $icon = null) {
    try {
        $pdo = getDBConnection();
        
        // שלוף את המנויים הפעילים של המשתמש
        $stmt = $pdo->prepare("
            SELECT * FROM push_subscriptions 
            WHERE user_id = ? AND is_active = 1
        ");
        $stmt->execute([$userId]);
        $subscriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($subscriptions)) {
            return ['success' => false, 'message' => 'No active subscriptions'];
        }
        
        $results = [];
        foreach ($subscriptions as $subscription) {
            // הכן את הנתונים להתראה
            $notificationData = [
                'title' => $title,
                'body' => $body,
                'icon' => $icon ?: '/images/icons/android/android-launchericon-192-192.png',
                'badge' => '/images/icons/android/android-launchericon-96-96.png',
                'url' => $url,
                'timestamp' => time(),
                'tag' => 'notification-' . time(),
                'requireInteraction' => false,
                'dir' => 'rtl',
                'lang' => 'he',
                'vibrate' => [200, 100, 200]
            ];
            
            // שלח דרך FCM (Google)
            $result = sendFCMNotification($subscription, $notificationData);
            $results[] = $result;
        }
        
        return [
            'success' => true, 
            'sent' => count(array_filter($results, function($r) { return $r['success']; })),
            'total' => count($subscriptions)
        ];
        
    } catch (Exception $e) {
        error_log('Push notification error: ' . $e->getMessage());
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

/**
 * שליחה דרך FCM
 */
function sendFCMNotification($subscription, $data) {
    // המר את ה-endpoint ל-FCM token
    $endpoint = $subscription['endpoint'];
    preg_match('/fcm\/send\/(.+)/', $endpoint, $matches);
    
    if (!isset($matches[1])) {
        return ['success' => false, 'message' => 'Invalid FCM endpoint'];
    }
    
    $token = $matches[1];
    
    // הגדרות FCM - צריך להוסיף ל-.env
    $serverKey = $_ENV['FCM_SERVER_KEY'] ?? 'YOUR_FCM_SERVER_KEY_HERE';
    
    $url = 'https://fcm.googleapis.com/fcm/send';
    
    $notification = [
        'title' => $data['title'],
        'body' => $data['body'],
        'icon' => $data['icon'],
        'click_action' => 'https://form.mbe-plus.com/family' . $data['url']
    ];
    
    $fields = [
        'to' => $token,
        'notification' => $notification,
        'data' => $data,
        'priority' => 'high'
    ];
    
    $headers = [
        'Authorization: key=' . $serverKey,
        'Content-Type: application/json'
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($fields));
    
    $result = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 200) {
        $response = json_decode($result, true);
        if (isset($response['success']) && $response['success'] == 1) {
            return ['success' => true];
        }
    }
    
    return ['success' => false, 'httpCode' => $httpCode];
}

/**
 * פונקציות עזר לסוגי התראות שונים
 */
function notifyGroupInvitation($invitationId) {
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
        
        return sendPushNotification($invitation['invitee_id'], $title, $body, $url);
        
    } catch (Exception $e) {
        error_log('Error notifying invitation: ' . $e->getMessage());
        return false;
    }
}

function notifyNewPurchase($purchaseId) {
    try {
        $pdo = getDBConnection();
        
        // שלוף פרטי הקנייה
        $stmt = $pdo->prepare("
            SELECT gp.*, pg.name as group_name, gm.nickname
            FROM group_purchases gp
            JOIN purchase_groups pg ON gp.group_id = pg.id
            JOIN group_members gm ON gp.member_id = gm.id
            WHERE gp.id = ?
        ");
        $stmt->execute([$purchaseId]);
        $purchase = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$purchase) {
            return false;
        }
        
        // שלוף את כל חברי הקבוצה (חוץ מזה שהוסיף)
        $stmt = $pdo->prepare("
            SELECT DISTINCT user_id 
            FROM group_members 
            WHERE group_id = ? AND is_active = 1 AND user_id != ?
        ");
        $stmt->execute([$purchase['group_id'], $purchase['user_id']]);
        $members = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        $title = '🛒 קנייה חדשה בקבוצה';
        $body = sprintf('%s הוסיף קנייה בסך ₪%s בקבוצה "%s"',
            $purchase['nickname'],
            number_format($purchase['amount'], 2),
            $purchase['group_name']
        );
        $url = '/group.php?id=' . $purchase['group_id'] . '#purchases';
        
        $results = [];
        foreach ($members as $userId) {
            $results[] = sendPushNotification($userId, $title, $body, $url);
        }
        
        return $results;
        
    } catch (Exception $e) {
        error_log('Error notifying purchase: ' . $e->getMessage());
        return false;
    }
}

// טיפול בבקשות ישירות ל-API
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    session_start();
    header('Content-Type: application/json');
    
    $data = json_decode(file_get_contents('php://input'), true);
    $action = $_GET['action'] ?? '';
    
    switch ($action) {
        case 'test':
            if (!isset($_SESSION['user_id'])) {
                echo json_encode(['success' => false, 'message' => 'Not logged in']);
                exit;
            }
            
            $result = sendPushNotification(
                $_SESSION['user_id'],
                'התראת בדיקה 🔔',
                'זו התראה לבדיקה שנשלחה מהשרת',
                '/dashboard.php'
            );
            echo json_encode($result);
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Unknown action']);
    }
}
?>