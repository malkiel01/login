<?php
// // api/simple-notifications.php - מערכת התראות פשוטה ללא תלויות חיצוניות
// // גרסה זו משתמשת ב-FCM (Firebase Cloud Messaging) במקום Web Push

// require_once '../config.php';

// class SimpleNotificationService {
//     private $pdo;
//     private $fcmServerKey;
    
//     public function __construct() {
//         $this->pdo = getDBConnection();
//         // מפתח השרת של Firebase - צריך להגדיר ב-.env
//         $this->fcmServerKey = $_ENV['FCM_SERVER_KEY'] ?? '';
//     }
    
//     /**
//      * שמירת טוקן FCM של משתמש
//      */
//     public function saveUserToken($userId, $token, $deviceInfo = []) {
//         try {
//             // בדוק אם הטוקן כבר קיים
//             $stmt = $this->pdo->prepare("
//                 SELECT id FROM push_subscriptions 
//                 WHERE user_id = ? AND endpoint = ?
//             ");
//             $stmt->execute([$userId, $token]);
            
//             if ($stmt->fetch()) {
//                 // עדכן טוקן קיים
//                 $stmt = $this->pdo->prepare("
//                     UPDATE push_subscriptions 
//                     SET updated_at = NOW(), is_active = 1
//                     WHERE user_id = ? AND endpoint = ?
//                 ");
//                 $stmt->execute([$userId, $token]);
//             } else {
//                 // הוסף טוקן חדש
//                 $stmt = $this->pdo->prepare("
//                     INSERT INTO push_subscriptions 
//                     (user_id, endpoint, device_type, user_agent, is_active)
//                     VALUES (?, ?, ?, ?, 1)
//                 ");
//                 $stmt->execute([
//                     $userId,
//                     $token,
//                     $deviceInfo['device_type'] ?? $this->detectDeviceType(),
//                     $_SERVER['HTTP_USER_AGENT'] ?? ''
//                 ]);
//             }
            
//             return ['success' => true, 'message' => 'Token saved successfully'];
            
//         } catch (Exception $e) {
//             error_log('Error saving token: ' . $e->getMessage());
//             return ['success' => false, 'message' => 'Failed to save token'];
//         }
//     }
    
//     /**
//      * שליחת התראה למשתמש
//      */
//     public function sendNotification($userId, $title, $body, $data = []) {
//         try {
//             // קבל את הטוקנים של המשתמש
//             $stmt = $this->pdo->prepare("
//                 SELECT endpoint FROM push_subscriptions 
//                 WHERE user_id = ? AND is_active = 1
//             ");
//             $stmt->execute([$userId]);
//             $tokens = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
//             if (empty($tokens)) {
//                 return ['success' => false, 'message' => 'No active tokens'];
//             }
            
//             // שלח התראה לכל הטוקנים
//             $results = [];
//             foreach ($tokens as $token) {
//                 $result = $this->sendFCMNotification($token, $title, $body, $data);
//                 $results[] = $result;
                
//                 // אם הטוקן לא תקין, בטל אותו
//                 if (!$result['success'] && isset($result['invalidToken'])) {
//                     $this->deactivateToken($token);
//                 }
//             }
            
//             return [
//                 'success' => true,
//                 'sent' => count(array_filter($results, function($r) { return $r['success']; })),
//                 'total' => count($tokens)
//             ];
            
//         } catch (Exception $e) {
//             error_log('Error sending notification: ' . $e->getMessage());
//             return ['success' => false, 'message' => $e->getMessage()];
//         }
//     }
    
//     /**
//      * שליחת התראת FCM
//      */
//     private function sendFCMNotification($token, $title, $body, $data = []) {
//         if (empty($this->fcmServerKey)) {
//             // אם אין FCM, נשתמש בפתרון חלופי
//             return $this->sendAlternativeNotification($token, $title, $body, $data);
//         }
        
//         $url = 'https://fcm.googleapis.com/fcm/send';
        
//         $notification = [
//             'title' => $title,
//             'body' => $body,
//             'icon' => '/images/icons/icon-192x192.png',
//             'badge' => '/images/icons/badge-72x72.png',
//             'sound' => 'default',
//             'click_action' => $data['url'] ?? '/dashboard.php'
//         ];
        
//         $fields = [
//             'to' => $token,
//             'notification' => $notification,
//             'data' => array_merge($data, [
//                 'title' => $title,
//                 'body' => $body
//             ]),
//             'priority' => 'high'
//         ];
        
//         $headers = [
//             'Authorization: key=' . $this->fcmServerKey,
//             'Content-Type: application/json'
//         ];
        
//         $ch = curl_init();
//         curl_setopt($ch, CURLOPT_URL, $url);
//         curl_setopt($ch, CURLOPT_POST, true);
//         curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
//         curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
//         curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
//         curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($fields));
        
//         $result = curl_exec($ch);
//         $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
//         curl_close($ch);
        
//         if ($httpCode === 200) {
//             $response = json_decode($result, true);
//             if ($response['success'] == 1) {
//                 return ['success' => true];
//             } else {
//                 return [
//                     'success' => false,
//                     'invalidToken' => isset($response['results'][0]['error'])
//                 ];
//             }
//         }
        
//         return ['success' => false];
//     }
    
//     /**
//      * פתרון חלופי - שמירת התראות במסד נתונים
//      */
//     private function sendAlternativeNotification($token, $title, $body, $data = []) {
//         try {
//             // שמור את ההתראה במסד נתונים
//             $stmt = $this->pdo->prepare("
//                 INSERT INTO notification_queue 
//                 (type, data, status, created_at)
//                 VALUES ('pending_notification', ?, 'pending', NOW())
//             ");
            
//             $notificationData = json_encode([
//                 'token' => $token,
//                 'title' => $title,
//                 'body' => $body,
//                 'data' => $data
//             ]);
            
//             $stmt->execute([$notificationData]);
            
//             // ההתראה תישלח כש-Service Worker יבקש אותה
//             return ['success' => true, 'queued' => true];
            
//         } catch (Exception $e) {
//             return ['success' => false, 'message' => $e->getMessage()];
//         }
//     }
    
//     /**
//      * קבלת התראות ממתינות למשתמש
//      */
//     public function getPendingNotifications($userId) {
//         try {
//             $stmt = $this->pdo->prepare("
//                 SELECT nq.* 
//                 FROM notification_queue nq
//                 JOIN push_subscriptions ps ON JSON_EXTRACT(nq.data, '$.token') = ps.endpoint
//                 WHERE ps.user_id = ? 
//                 AND nq.status = 'pending'
//                 AND nq.created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)
//                 ORDER BY nq.created_at DESC
//                 LIMIT 10
//             ");
//             $stmt->execute([$userId]);
//             $notifications = $stmt->fetchAll();
            
//             // סמן כנשלחו
//             if (!empty($notifications)) {
//                 $ids = array_column($notifications, 'id');
//                 $placeholders = str_repeat('?,', count($ids) - 1) . '?';
//                 $stmt = $this->pdo->prepare("
//                     UPDATE notification_queue 
//                     SET status = 'completed', processed_at = NOW()
//                     WHERE id IN ($placeholders)
//                 ");
//                 $stmt->execute($ids);
//             }
            
//             return array_map(function($n) {
//                 return json_decode($n['data'], true);
//             }, $notifications);
            
//         } catch (Exception $e) {
//             error_log('Error getting pending notifications: ' . $e->getMessage());
//             return [];
//         }
//     }
    
//     /**
//      * ביטול טוקן
//      */
//     private function deactivateToken($token) {
//         $stmt = $this->pdo->prepare("
//             UPDATE push_subscriptions 
//             SET is_active = 0 
//             WHERE endpoint = ?
//         ");
//         $stmt->execute([$token]);
//     }
    
//     /**
//      * זיהוי סוג המכשיר
//      */
//     private function detectDeviceType() {
//         $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
//         if (stripos($userAgent, 'android') !== false) return 'Android';
//         if (stripos($userAgent, 'iphone') !== false) return 'iPhone';
//         if (stripos($userAgent, 'ipad') !== false) return 'iPad';
//         if (stripos($userAgent, 'windows') !== false) return 'Windows';
//         if (stripos($userAgent, 'mac') !== false) return 'Mac';
        
//         return 'Unknown';
//     }
    
//     /**
//      * שליחת התראות על אירועים ספציפיים
//      */
//     public function notifyGroupInvitation($invitationId) {
//         try {
//             $stmt = $this->pdo->prepare("
//                 SELECT gi.*, pg.name as group_name, u.name as inviter_name, u2.id as invitee_id
//                 FROM group_invitations gi
//                 JOIN purchase_groups pg ON gi.group_id = pg.id
//                 JOIN users u ON gi.invited_by = u.id
//                 LEFT JOIN users u2 ON u2.email = gi.email
//                 WHERE gi.id = ?
//             ");
//             $stmt->execute([$invitationId]);
//             $invitation = $stmt->fetch();
            
//             if (!$invitation || !$invitation['invitee_id']) {
//                 return false;
//             }
            
//             $title = 'הזמנה לקבוצת רכישה';
//             $body = sprintf('%s הזמין אותך לקבוצה "%s"', 
//                 $invitation['inviter_name'], 
//                 $invitation['group_name']
//             );
            
//             return $this->sendNotification(
//                 $invitation['invitee_id'],
//                 $title,
//                 $body,
//                 [
//                     'type' => 'group_invitation',
//                     'invitation_id' => $invitationId,
//                     'group_id' => $invitation['group_id'],
//                     'url' => '/dashboard.php#invitations'
//                 ]
//             );
            
//         } catch (Exception $e) {
//             error_log('Error notifying invitation: ' . $e->getMessage());
//             return false;
//         }
//     }
    
//     public function notifyInvitationResponse($invitationId, $accepted) {
//         try {
//             $stmt = $this->pdo->prepare("
//                 SELECT gi.*, pg.name as group_name, pg.owner_id, u.name as user_name
//                 FROM group_invitations gi
//                 JOIN purchase_groups pg ON gi.group_id = pg.id
//                 LEFT JOIN users u ON u.email = gi.email
//                 WHERE gi.id = ?
//             ");
//             $stmt->execute([$invitationId]);
//             $invitation = $stmt->fetch();
            
//             if (!$invitation) {
//                 return false;
//             }
            
//             $title = $accepted ? 'משתתף חדש הצטרף' : 'הזמנה נדחתה';
//             $body = sprintf('%s %s את ההזמנה לקבוצה "%s"',
//                 $invitation['user_name'] ?? $invitation['nickname'],
//                 $accepted ? 'אישר' : 'דחה',
//                 $invitation['group_name']
//             );
            
//             return $this->sendNotification(
//                 $invitation['owner_id'],
//                 $title,
//                 $body,
//                 [
//                     'type' => 'invitation_response',
//                     'group_id' => $invitation['group_id'],
//                     'accepted' => $accepted,
//                     'url' => '/group.php?id=' . $invitation['group_id']
//                 ]
//             );
            
//         } catch (Exception $e) {
//             error_log('Error notifying response: ' . $e->getMessage());
//             return false;
//         }
//     }
    
//     public function notifyNewPurchase($purchaseId) {
//         try {
//             $stmt = $this->pdo->prepare("
//                 SELECT gp.*, pg.name as group_name, gm.nickname
//                 FROM group_purchases gp
//                 JOIN purchase_groups pg ON gp.group_id = pg.id
//                 JOIN group_members gm ON gp.member_id = gm.id
//                 WHERE gp.id = ?
//             ");
//             $stmt->execute([$purchaseId]);
//             $purchase = $stmt->fetch();
            
//             if (!$purchase) {
//                 return false;
//             }
            
//             // קבל את כל חברי הקבוצה
//             $stmt = $this->pdo->prepare("
//                 SELECT user_id 
//                 FROM group_members 
//                 WHERE group_id = ? AND is_active = 1 AND user_id != ?
//             ");
//             $stmt->execute([$purchase['group_id'], $purchase['user_id']]);
//             $members = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
//             $title = 'קנייה חדשה בקבוצה';
//             $body = sprintf('%s הוסיף קנייה בסך ₪%s',
//                 $purchase['nickname'],
//                 number_format($purchase['amount'], 2)
//             );
            
//             $results = [];
//             foreach ($members as $userId) {
//                 $results[] = $this->sendNotification(
//                     $userId,
//                     $title,
//                     $body,
//                     [
//                         'type' => 'new_purchase',
//                         'purchase_id' => $purchaseId,
//                         'group_id' => $purchase['group_id'],
//                         'url' => '/group.php?id=' . $purchase['group_id'] . '#purchases'
//                     ]
//                 );
//             }
            
//             return $results;
            
//         } catch (Exception $e) {
//             error_log('Error notifying purchase: ' . $e->getMessage());
//             return false;
//         }
//     }
// }

// // טיפול בבקשות API
// if ($_SERVER['REQUEST_METHOD'] === 'POST') {
//     session_start();
//     header('Content-Type: application/json');
    
//     if (!isset($_SESSION['user_id'])) {
//         http_response_code(401);
//         echo json_encode(['success' => false, 'message' => 'Unauthorized']);
//         exit;
//     }
    
//     $service = new SimpleNotificationService();
//     $data = json_decode(file_get_contents('php://input'), true);
//     $action = $_GET['action'] ?? '';
    
//     switch ($action) {
//         case 'save-token':
//             $result = $service->saveUserToken(
//                 $_SESSION['user_id'], 
//                 $data['token'],
//                 $data['deviceInfo'] ?? []
//             );
//             echo json_encode($result);
//             break;
            
//         case 'test':
//             $result = $service->sendNotification(
//                 $_SESSION['user_id'],
//                 'התראת בדיקה',
//                 'זוהי התראת בדיקה ממערכת פנאן בפקאן',
//                 ['type' => 'test']
//             );
//             echo json_encode($result);
//             break;
            
//         case 'get-pending':
//             $notifications = $service->getPendingNotifications($_SESSION['user_id']);
//             echo json_encode(['success' => true, 'notifications' => $notifications]);
//             break;
            
//         default:
//             echo json_encode(['success' => false, 'message' => 'Invalid action']);
//     }
    
// } elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
//     // בקשות GET
//     if ($_GET['action'] === 'check') {
//         echo json_encode(['success' => true, 'service' => 'SimpleNotificationService']);
//     }
// }
?>