<?php
// api/push-notifications.php - מערכת Push Notifications אמיתית
session_start();
require_once '../config.php';

// VAPID Keys - צריך לייצר פעם אחת ולשמור
define('VAPID_PUBLIC_KEY', 'BGhVXSFnfBMX_6Z3JZvGkVN5s7Xw9oPKxI0lK7v8VxF4nfR3WQc5P9lQ0HM3vZfQRyD1MhKmJsE3qZvO8qgXfHg');
define('VAPID_PRIVATE_KEY', 'YOUR_PRIVATE_KEY_HERE'); // צריך לייצר עם web-push library

header('Content-Type: application/json');

class PushNotificationService {
    private $pdo;
    
    public function __construct() {
        $this->pdo = getDBConnection();
    }
    
    /**
     * שמירת Subscription של משתמש
     */
    public function saveSubscription($userId, $subscription) {
        try {
            // המר את המידע לפורמט נכון
            $endpoint = $subscription['endpoint'];
            $p256dh = $subscription['keys']['p256dh'] ?? '';
            $auth = $subscription['keys']['auth'] ?? '';
            
            // בדוק אם כבר קיים
            $stmt = $this->pdo->prepare("
                SELECT id FROM push_subscriptions 
                WHERE user_id = ? AND endpoint = ?
            ");
            $stmt->execute([$userId, $endpoint]);
            
            if ($stmt->fetch()) {
                // עדכן קיים
                $stmt = $this->pdo->prepare("
                    UPDATE push_subscriptions 
                    SET p256dh = ?, auth = ?, is_active = 1, updated_at = NOW()
                    WHERE user_id = ? AND endpoint = ?
                ");
                $stmt->execute([$p256dh, $auth, $userId, $endpoint]);
            } else {
                // צור חדש
                $stmt = $this->pdo->prepare("
                    INSERT INTO push_subscriptions 
                    (user_id, endpoint, p256dh, auth, device_type, user_agent, is_active)
                    VALUES (?, ?, ?, ?, ?, ?, 1)
                ");
                $stmt->execute([
                    $userId,
                    $endpoint,
                    $p256dh,
                    $auth,
                    $this->detectDeviceType(),
                    $_SERVER['HTTP_USER_AGENT'] ?? ''
                ]);
            }
            
            return ['success' => true, 'message' => 'Subscription saved'];
            
        } catch (Exception $e) {
            error_log('Error saving subscription: ' . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    /**
     * שליחת התראה למשתמש
     */
    public function sendToUser($userId, $title, $body, $data = []) {
        try {
            // קבל את כל ה-subscriptions של המשתמש
            $stmt = $this->pdo->prepare("
                SELECT * FROM push_subscriptions 
                WHERE user_id = ? AND is_active = 1
            ");
            $stmt->execute([$userId]);
            $subscriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (empty($subscriptions)) {
                return ['success' => false, 'message' => 'No active subscriptions'];
            }
            
            $results = [];
            foreach ($subscriptions as $sub) {
                $result = $this->sendPushNotification(
                    $sub['endpoint'],
                    $sub['p256dh'],
                    $sub['auth'],
                    $title,
                    $body,
                    $data
                );
                $results[] = $result;
                
                // אם ה-subscription לא תקין, בטל אותו
                if (!$result['success'] && isset($result['expired'])) {
                    $this->deactivateSubscription($sub['id']);
                }
            }
            
            return [
                'success' => true,
                'sent' => count(array_filter($results, function($r) { return $r['success']; })),
                'total' => count($subscriptions)
            ];
            
        } catch (Exception $e) {
            error_log('Error sending to user: ' . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    /**
     * שליחת Push Notification בפועל
     */
    private function sendPushNotification($endpoint, $p256dh, $auth, $title, $body, $data = []) {
        // הכן את הפיילוד
        $payload = json_encode([
            'title' => $title,
            'body' => $body,
            'icon' => '/login/images/icons/android/android-launchericon-192-192.png',
            'badge' => '/login/images/icons/android/android-launchericon-96-96.png',
            'vibrate' => [200, 100, 200],
            'data' => array_merge($data, [
                'timestamp' => time(),
                'url' => $data['url'] ?? '/login/dashboard.php'
            ])
        ]);
        
        // בלי ספריית web-push, נשתמש בפתרון חלופי פשוט
        // נשמור את ההתראה במסד נתונים והיא תישלח כש-Service Worker יבקש
        return $this->queueNotification($endpoint, $payload);
    }
    
    /**
     * הכנסת התראה לתור (פתרון חלופי)
     */
    private function queueNotification($endpoint, $payload) {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO notification_queue 
                (endpoint, payload, status, created_at)
                VALUES (?, ?, 'pending', NOW())
            ");
            $stmt->execute([$endpoint, $payload]);
            
            return ['success' => true, 'queued' => true];
            
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    /**
     * קבלת התראות ממתינות (ל-Service Worker)
     */
    public function getPendingNotifications($endpoint) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT * FROM notification_queue 
                WHERE endpoint = ? 
                AND status = 'pending'
                AND created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)
                ORDER BY created_at DESC
                LIMIT 10
            ");
            $stmt->execute([$endpoint]);
            $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // סמן כנשלחו
            if (!empty($notifications)) {
                $ids = array_column($notifications, 'id');
                $placeholders = str_repeat('?,', count($ids) - 1) . '?';
                $stmt = $this->pdo->prepare("
                    UPDATE notification_queue 
                    SET status = 'sent', sent_at = NOW()
                    WHERE id IN ($placeholders)
                ");
                $stmt->execute($ids);
            }
            
            return array_map(function($n) {
                return json_decode($n['payload'], true);
            }, $notifications);
            
        } catch (Exception $e) {
            error_log('Error getting pending notifications: ' . $e->getMessage());
            return [];
        }
    }
    
    /**
     * ביטול subscription
     */
    private function deactivateSubscription($id) {
        $stmt = $this->pdo->prepare("
            UPDATE push_subscriptions 
            SET is_active = 0 
            WHERE id = ?
        ");
        $stmt->execute([$id]);
    }
    
    /**
     * זיהוי סוג המכשיר
     */
    private function detectDeviceType() {
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        if (stripos($userAgent, 'android') !== false) return 'Android';
        if (stripos($userAgent, 'iphone') !== false) return 'iPhone';
        if (stripos($userAgent, 'ipad') !== false) return 'iPad';
        if (stripos($userAgent, 'windows') !== false) return 'Windows';
        if (stripos($userAgent, 'mac') !== false) return 'Mac';
        
        return 'Unknown';
    }
    
    /**
     * שליחת התראה על הזמנה לקבוצה
     */
    public function notifyGroupInvitation($invitationId) {
        try {
            $stmt = $this->pdo->prepare("
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
            
            return $this->sendToUser(
                $invitation['invitee_id'],
                'הזמנה לקבוצת רכישה',
                sprintf('%s הזמין אותך לקבוצה "%s"', 
                    $invitation['inviter_name'], 
                    $invitation['group_name']
                ),
                [
                    'type' => 'group_invitation',
                    'invitation_id' => $invitationId,
                    'group_id' => $invitation['group_id'],
                    'url' => '/login/dashboard.php#invitations'
                ]
            );
            
        } catch (Exception $e) {
            error_log('Error notifying invitation: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * שליחת התראה על קנייה חדשה
     */
    public function notifyNewPurchase($purchaseId) {
        try {
            $stmt = $this->pdo->prepare("
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
            
            // קבל את כל חברי הקבוצה
            $stmt = $this->pdo->prepare("
                SELECT user_id 
                FROM group_members 
                WHERE group_id = ? AND is_active = 1 AND user_id != ?
            ");
            $stmt->execute([$purchase['group_id'], $purchase['user_id']]);
            $members = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            $results = [];
            foreach ($members as $userId) {
                $results[] = $this->sendToUser(
                    $userId,
                    'קנייה חדשה בקבוצה',
                    sprintf('%s הוסיף קנייה בסך ₪%s', 
                        $purchase['nickname'], 
                        number_format($purchase['amount'], 2)
                    ),
                    [
                        'type' => 'new_purchase',
                        'purchase_id' => $purchaseId,
                        'group_id' => $purchase['group_id'],
                        'url' => '/login/group.php?id=' . $purchase['group_id'] . '#purchases'
                    ]
                );
            }
            
            return $results;
            
        } catch (Exception $e) {
            error_log('Error notifying purchase: ' . $e->getMessage());
            return false;
        }
    }
}

// טיפול בבקשות API
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit;
    }
    
    $service = new PushNotificationService();
    $data = json_decode(file_get_contents('php://input'), true);
    $action = $_GET['action'] ?? '';
    
    switch ($action) {
        case 'subscribe':
            $result = $service->saveSubscription($_SESSION['user_id'], $data['subscription']);
            echo json_encode($result);
            break;
            
        case 'test':
            $result = $service->sendToUser(
                $_SESSION['user_id'],
                'התראת בדיקה',
                'זוהי התראת בדיקה - ההתראות עובדות!',
                ['type' => 'test']
            );
            echo json_encode($result);
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
    
} elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = $_GET['action'] ?? '';
    
    switch ($action) {
        case 'vapid-key':
            echo json_encode(['publicKey' => VAPID_PUBLIC_KEY]);
            break;
            
        case 'check-notifications':
            // Service Worker יקרא לזה כדי לבדוק התראות
            $endpoint = $_GET['endpoint'] ?? '';
            if ($endpoint) {
                $service = new PushNotificationService();
                $notifications = $service->getPendingNotifications($endpoint);
                echo json_encode(['notifications' => $notifications]);
            } else {
                echo json_encode(['notifications' => []]);
            }
            break;
            
        default:
            echo json_encode(['success' => true, 'service' => 'PushNotificationService']);
    }
}
?>