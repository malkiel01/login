<?php
// // api/notifications.php - מערכת שליחת התראות Push

// require_once '../config.php';
// require_once '../vendor/autoload.php'; // נדרש להתקין web-push-php דרך Composer

// use Minishlink\WebPush\WebPush;
// use Minishlink\WebPush\Subscription;

// class PushNotificationService {
//     private $pdo;
//     private $webPush;
//     private $vapidKeys;
    
//     public function __construct() {
//         $this->pdo = getDBConnection();
        
//         // טען או צור מפתחות VAPID
//         $this->loadVapidKeys();
        
//         // אתחל WebPush
//         $auth = [
//             'VAPID' => [
//                 'subject' => 'mailto:admin@panan-bakan.com',
//                 'publicKey' => $this->vapidKeys['publicKey'],
//                 'privateKey' => $this->vapidKeys['privateKey'],
//             ],
//         ];
        
//         $this->webPush = new WebPush($auth);
//     }
    
//     /**
//      * טעינת או יצירת מפתחות VAPID
//      */
//     private function loadVapidKeys() {
//         $keysFile = __DIR__ . '/../config/vapid-keys.json';
        
//         if (file_exists($keysFile)) {
//             $this->vapidKeys = json_decode(file_get_contents($keysFile), true);
//         } else {
//             // צור מפתחות חדשים
//             $this->vapidKeys = \Minishlink\WebPush\VAPID::createVapidKeys();
            
//             // שמור לקובץ
//             if (!is_dir(dirname($keysFile))) {
//                 mkdir(dirname($keysFile), 0755, true);
//             }
//             file_put_contents($keysFile, json_encode($this->vapidKeys, JSON_PRETTY_PRINT));
//         }
//     }
    
//     /**
//      * שמירת subscription של משתמש
//      */
//     public function saveSubscription($userId, $subscriptionData) {
//         try {
//             // בדוק אם כבר קיים
//             $stmt = $this->pdo->prepare("
//                 SELECT id FROM push_subscriptions 
//                 WHERE user_id = ? AND endpoint = ?
//             ");
//             $stmt->execute([$userId, $subscriptionData['endpoint']]);
            
//             if ($stmt->fetch()) {
//                 // עדכן subscription קיים
//                 $stmt = $this->pdo->prepare("
//                     UPDATE push_subscriptions 
//                     SET p256dh = ?, auth = ?, updated_at = NOW()
//                     WHERE user_id = ? AND endpoint = ?
//                 ");
//                 $stmt->execute([
//                     $subscriptionData['keys']['p256dh'],
//                     $subscriptionData['keys']['auth'],
//                     $userId,
//                     $subscriptionData['endpoint']
//                 ]);
//             } else {
//                 // צור subscription חדש
//                 $stmt = $this->pdo->prepare("
//                     INSERT INTO push_subscriptions 
//                     (user_id, endpoint, p256dh, auth, user_agent, device_type)
//                     VALUES (?, ?, ?, ?, ?, ?)
//                 ");
//                 $stmt->execute([
//                     $userId,
//                     $subscriptionData['endpoint'],
//                     $subscriptionData['keys']['p256dh'],
//                     $subscriptionData['keys']['auth'],
//                     $_SERVER['HTTP_USER_AGENT'] ?? '',
//                     $this->detectDeviceType()
//                 ]);
//             }
            
//             return ['success' => true, 'message' => 'Subscription saved'];
//         } catch (Exception $e) {
//             error_log('Error saving subscription: ' . $e->getMessage());
//             return ['success' => false, 'message' => 'Failed to save subscription'];
//         }
//     }
    
//     /**
//      * שליחת התראה על הזמנה לקבוצה
//      */
//     public function sendGroupInvitationNotification($invitationId) {
//         try {
//             // קבל פרטי ההזמנה
//             $stmt = $this->pdo->prepare("
//                 SELECT gi.*, pg.name as group_name, u.name as inviter_name
//                 FROM group_invitations gi
//                 JOIN purchase_groups pg ON gi.group_id = pg.id
//                 JOIN users u ON gi.invited_by = u.id
//                 WHERE gi.id = ?
//             ");
//             $stmt->execute([$invitationId]);
//             $invitation = $stmt->fetch();
            
//             if (!$invitation) return false;
            
//             // חפש את המשתמש לפי אימייל
//             $stmt = $this->pdo->prepare("SELECT id FROM users WHERE email = ?");
//             $stmt->execute([$invitation['email']]);
//             $user = $stmt->fetch();
            
//             if (!$user) return false; // המשתמש עדיין לא רשום
            
//             // שלח התראה
//             $notification = [
//                 'title' => 'הזמנה לקבוצת רכישה',
//                 'body' => sprintf(
//                     '%s הזמין אותך להצטרף לקבוצה "%s"',
//                     $invitation['inviter_name'],
//                     $invitation['group_name']
//                 ),
//                 'icon' => '/images/icons/icon-192x192.png',
//                 'badge' => '/images/icons/badge-72x72.png',
//                 'data' => [
//                     'type' => 'group_invitation',
//                     'invitation_id' => $invitationId,
//                     'group_id' => $invitation['group_id']
//                 ]
//             ];
            
//             return $this->sendToUser($user['id'], $notification);
//         } catch (Exception $e) {
//             error_log('Error sending invitation notification: ' . $e->getMessage());
//             return false;
//         }
//     }
    
//     /**
//      * שליחת התראה על תגובה להזמנה
//      */
//     public function sendInvitationResponseNotification($invitationId, $accepted) {
//         try {
//             // קבל פרטי ההזמנה
//             $stmt = $this->pdo->prepare("
//                 SELECT gi.*, pg.name as group_name, pg.owner_id, u.name as user_name
//                 FROM group_invitations gi
//                 JOIN purchase_groups pg ON gi.group_id = pg.id
//                 LEFT JOIN users u ON u.email = gi.email
//                 WHERE gi.id = ?
//             ");
//             $stmt->execute([$invitationId]);
//             $invitation = $stmt->fetch();
            
//             if (!$invitation) return false;
            
//             // שלח התראה למנהל הקבוצה
//             $notification = [
//                 'title' => $accepted ? 'משתתף חדש הצטרף' : 'הזמנה נדחתה',
//                 'body' => sprintf(
//                     '%s %s את ההזמנה להצטרף לקבוצה "%s"',
//                     $invitation['user_name'] ?? $invitation['nickname'],
//                     $accepted ? 'אישר' : 'דחה',
//                     $invitation['group_name']
//                 ),
//                 'icon' => '/images/icons/icon-192x192.png',
//                 'badge' => '/images/icons/badge-72x72.png',
//                 'data' => [
//                     'type' => 'invitation_response',
//                     'group_id' => $invitation['group_id'],
//                     'accepted' => $accepted
//                 ]
//             ];
            
//             return $this->sendToUser($invitation['owner_id'], $notification);
//         } catch (Exception $e) {
//             error_log('Error sending response notification: ' . $e->getMessage());
//             return false;
//         }
//     }
    
//     /**
//      * שליחת התראה על קנייה חדשה
//      */
//     public function sendNewPurchaseNotification($purchaseId) {
//         try {
//             // קבל פרטי הקנייה
//             $stmt = $this->pdo->prepare("
//                 SELECT gp.*, pg.name as group_name, gm.nickname, u.name as purchaser_name
//                 FROM group_purchases gp
//                 JOIN purchase_groups pg ON gp.group_id = pg.id
//                 JOIN group_members gm ON gp.member_id = gm.id
//                 JOIN users u ON gp.user_id = u.id
//                 WHERE gp.id = ?
//             ");
//             $stmt->execute([$purchaseId]);
//             $purchase = $stmt->fetch();
            
//             if (!$purchase) return false;
            
//             // קבל את כל חברי הקבוצה
//             $stmt = $this->pdo->prepare("
//                 SELECT user_id 
//                 FROM group_members 
//                 WHERE group_id = ? AND is_active = 1 AND user_id != ?
//             ");
//             $stmt->execute([$purchase['group_id'], $purchase['user_id']]);
//             $members = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
//             // הכן התראה
//             $notification = [
//                 'title' => 'קנייה חדשה בקבוצה',
//                 'body' => sprintf(
//                     '%s הוסיף קנייה בסך ₪%s בקבוצה "%s"',
//                     $purchase['nickname'],
//                     number_format($purchase['amount'], 2),
//                     $purchase['group_name']
//                 ),
//                 'icon' => '/images/icons/icon-192x192.png',
//                 'badge' => '/images/icons/badge-72x72.png',
//                 'data' => [
//                     'type' => 'new_purchase',
//                     'purchase_id' => $purchaseId,
//                     'group_id' => $purchase['group_id']
//                 ]
//             ];
            
//             // שלח לכל החברים
//             $results = [];
//             foreach ($members as $userId) {
//                 $results[] = $this->sendToUser($userId, $notification);
//             }
            
//             return $results;
//         } catch (Exception $e) {
//             error_log('Error sending purchase notification: ' . $e->getMessage());
//             return false;
//         }
//     }
    
//     /**
//      * שליחת התראה למשתמש ספציפי
//      */
//     private function sendToUser($userId, $notificationData) {
//         try {
//             // קבל את כל ה-subscriptions של המשתמש
//             $stmt = $this->pdo->prepare("
//                 SELECT * FROM push_subscriptions 
//                 WHERE user_id = ? AND is_active = 1
//             ");
//             $stmt->execute([$userId]);
//             $subscriptions = $stmt->fetchAll();
            
//             if (empty($subscriptions)) {
//                 return false; // אין subscriptions פעילים
//             }
            
//             $results = [];
//             foreach ($subscriptions as $sub) {
//                 try {
//                     // צור Subscription object
//                     $subscription = Subscription::create([
//                         'endpoint' => $sub['endpoint'],
//                         'publicKey' => $sub['p256dh'],
//                         'authToken' => $sub['auth']
//                     ]);
                    
//                     // שלח התראה
//                     $report = $this->webPush->sendOneNotification(
//                         $subscription,
//                         json_encode($notificationData)
//                     );
                    
//                     // בדוק תוצאה
//                     if ($report->isSuccess()) {
//                         $results[] = true;
//                     } else {
//                         // אם ה-subscription לא תקין, בטל אותו
//                         if ($report->isSubscriptionExpired()) {
//                             $this->deactivateSubscription($sub['id']);
//                         }
//                         $results[] = false;
//                         error_log('Push failed: ' . $report->getReason());
//                     }
//                 } catch (Exception $e) {
//                     error_log('Error sending to subscription: ' . $e->getMessage());
//                     $results[] = false;
//                 }
//             }
            
//             return in_array(true, $results); // החזר true אם לפחות אחת הצליחה
//         } catch (Exception $e) {
//             error_log('Error in sendToUser: ' . $e->getMessage());
//             return false;
//         }
//     }
    
//     /**
//      * ביטול subscription
//      */
//     private function deactivateSubscription($subscriptionId) {
//         $stmt = $this->pdo->prepare("
//             UPDATE push_subscriptions 
//             SET is_active = 0 
//             WHERE id = ?
//         ");
//         $stmt->execute([$subscriptionId]);
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
//      * קבלת המפתח הציבורי של VAPID
//      */
//     public function getPublicKey() {
//         return $this->vapidKeys['publicKey'];
//     }
// }

// // טיפול בבקשות API
// if ($_SERVER['REQUEST_METHOD'] === 'POST') {
//     session_start();
    
//     if (!isset($_SESSION['user_id'])) {
//         http_response_code(401);
//         echo json_encode(['success' => false, 'message' => 'Unauthorized']);
//         exit;
//     }
    
//     $service = new PushNotificationService();
//     $data = json_decode(file_get_contents('php://input'), true);
    
//     $action = $_GET['action'] ?? '';
    
//     switch ($action) {
//         case 'save-subscription':
//             $result = $service->saveSubscription($_SESSION['user_id'], $data['subscription']);
//             echo json_encode($result);
//             break;
            
//         case 'test-notification':
//             // שלח התראת בדיקה
//             $notification = [
//                 'title' => 'התראת בדיקה',
//                 'body' => 'זוהי התראת בדיקה ממערכת פנאן בפקאן',
//                 'icon' => '/images/icons/icon-192x192.png',
//                 'data' => ['type' => 'test']
//             ];
//             $result = $service->sendToUser($_SESSION['user_id'], $notification);
//             echo json_encode(['success' => $result]);
//             break;
            
//         default:
//             http_response_code(400);
//             echo json_encode(['success' => false, 'message' => 'Invalid action']);
//     }
// } elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
//     $service = new PushNotificationService();
    
//     if ($_GET['action'] === 'get-vapid-key') {
//         echo json_encode(['publicKey' => $service->getPublicKey()]);
//     }
// }
?>