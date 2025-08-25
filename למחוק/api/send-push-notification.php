<?php
// api/send-push-notification.php - מערכת שליחת Push Notifications
// require_once '../config.php';
require_once __DIR__ . '/../config.php';

/**
 * פונקציה לשליחת התראה על הזמנה לקבוצה
 */
function notifyGroupInvitation($invitationId) {
    try {
        $pdo = getDBConnection();
        
        // קבל פרטי ההזמנה
        $stmt = $pdo->prepare("
            SELECT 
                gi.*,
                pg.name as group_name,
                inviter.name as inviter_name,
                invitee.id as invitee_id
            FROM group_invitations gi
            JOIN purchase_groups pg ON gi.group_id = pg.id
            JOIN users inviter ON gi.invited_by = inviter.id
            LEFT JOIN users invitee ON invitee.email = gi.email
            WHERE gi.id = ?
        ");
        $stmt->execute([$invitationId]);
        $invitation = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$invitation) {
            error_log("Invitation not found: $invitationId");
            return ['success' => false, 'message' => 'Invitation not found'];
        }
        
        // אם המשתמש לא רשום עדיין - אין למי לשלוח
        if (!$invitation['invitee_id']) {
            error_log("User not registered yet for email: " . $invitation['email']);
            return ['success' => false, 'message' => 'User not registered'];
        }
        
        // הכן את ההתראה
        $notificationData = [
            'type' => 'group_invitation',
            'title' => 'הזמנה לקבוצת רכישה',
            'body' => sprintf(
                '%s הזמין אותך להצטרף לקבוצה "%s"',
                $invitation['inviter_name'],
                $invitation['group_name']
            ),
            'icon' => '/login/images/icons/android/android-launchericon-192-192.png',
            'badge' => '/login/images/icons/android/android-launchericon-96-96.png',
            'url' => '/login/dashboard.php#invitations',
            'invitation_id' => $invitationId,
            'group_id' => $invitation['group_id'],
            'group_name' => $invitation['group_name'],
            'inviter_name' => $invitation['inviter_name']
        ];
        
        // שמור בטבלת ההתראות
        return saveNotificationToQueue($invitation['invitee_id'], 'group_invitation', $notificationData);
        
    } catch (Exception $e) {
        error_log("Error in notifyGroupInvitation: " . $e->getMessage());
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

/**
 * פונקציה לשמירת התראה בתור
 */
function saveNotificationToQueue($userId, $type, $data) {
    try {
        $pdo = getDBConnection();
        
        // שמור בטבלת notification_queue
        $stmt = $pdo->prepare("
            INSERT INTO notification_queue 
            (type, data, status, user_id, priority, attempts, created_at) 
            VALUES (?, ?, 'pending', ?, ?, 0, NOW())
        ");
        
        $priority = getPriorityForType($type);
        
        $result = $stmt->execute([
            $type,
            json_encode($data, JSON_UNESCAPED_UNICODE),
            $userId,
            $priority
        ]);
        
        if ($result) {
            $queueId = $pdo->lastInsertId();
            
            // נסה לשלוח מיידית
            $sendResult = sendPushToUser($userId, $data);
            
            // עדכן סטטוס בהתאם
            if ($sendResult['success']) {
                updateNotificationStatus($queueId, 'completed');
                logNotification($queueId, $userId, $type, 'push', $data, 'sent');
            } else {
                updateNotificationStatus($queueId, 'pending', $sendResult['message']);
            }
            
            return [
                'success' => true,
                'queue_id' => $queueId,
                'sent' => $sendResult['success']
            ];
        }
        
        return ['success' => false, 'message' => 'Failed to queue notification'];
        
    } catch (Exception $e) {
        error_log("Error in saveNotificationToQueue: " . $e->getMessage());
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

/**
 * פונקציה לשליחת Push למשתמש
 */
function sendPushToUser($userId, $notificationData) {
    try {
        $pdo = getDBConnection();
        
        // קבל את כל ה-subscriptions הפעילים של המשתמש
        $stmt = $pdo->prepare("
            SELECT * FROM push_subscriptions 
            WHERE user_id = ? AND is_active = 1
            ORDER BY last_used DESC
        ");
        $stmt->execute([$userId]);
        $subscriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($subscriptions)) {
            return ['success' => false, 'message' => 'No active subscriptions'];
        }
        
        $successCount = 0;
        $failCount = 0;
        
        foreach ($subscriptions as $subscription) {
            try {
                // שלח דרך Web Push API או שמור לטבלה מיוחדת
                $result = deliverPushNotification($subscription, $notificationData);
                
                if ($result) {
                    $successCount++;
                    // עדכן last_used
                    updateSubscriptionLastUsed($subscription['id']);
                } else {
                    $failCount++;
                }
                
            } catch (Exception $e) {
                error_log("Failed to send to subscription {$subscription['id']}: " . $e->getMessage());
                $failCount++;
            }
        }
        
        return [
            'success' => $successCount > 0,
            'sent' => $successCount,
            'failed' => $failCount,
            'message' => "Sent to $successCount devices"
        ];
        
    } catch (Exception $e) {
        error_log("Error in sendPushToUser: " . $e->getMessage());
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

/**
 * פונקציה לשליחת Push בפועל
 * כאן אפשר להשתמש ב-Web Push או בפתרון חלופי
 */
function deliverPushNotification($subscription, $data) {
    try {
        $pdo = getDBConnection();
        
        // פתרון זמני: שמור בטבלה מיוחדת שה-Service Worker בודק
        $stmt = $pdo->prepare("
            INSERT INTO pending_push_notifications 
            (user_id, subscription_id, data, created_at) 
            VALUES (?, ?, ?, NOW())
        ");
        
        // קודם צור את הטבלה אם לא קיימת
        createPendingPushTableIfNeeded($pdo);
        
        $result = $stmt->execute([
            $subscription['user_id'],
            $subscription['id'],
            json_encode($data, JSON_UNESCAPED_UNICODE)
        ]);
        
        return $result;
        
    } catch (Exception $e) {
        error_log("Error in deliverPushNotification: " . $e->getMessage());
        return false;
    }
}

/**
 * יצירת טבלה זמנית להתראות ממתינות
 */
function createPendingPushTableIfNeeded($pdo) {
    try {
        $sql = "
            CREATE TABLE IF NOT EXISTS pending_push_notifications (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                subscription_id INT,
                data JSON,
                delivered BOOLEAN DEFAULT FALSE,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                delivered_at TIMESTAMP NULL,
                INDEX idx_user_pending (user_id, delivered),
                INDEX idx_created (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ";
        $pdo->exec($sql);
    } catch (Exception $e) {
        // הטבלה כנראה כבר קיימת
    }
}

/**
 * עדכון סטטוס התראה
 */
function updateNotificationStatus($queueId, $status, $errorMessage = null) {
    try {
        $pdo = getDBConnection();
        
        $sql = "UPDATE notification_queue SET status = ?, last_attempt = NOW()";
        $params = [$status];
        
        if ($status === 'completed') {
            $sql .= ", processed_at = NOW()";
        }
        
        if ($errorMessage) {
            $sql .= ", error_message = ?";
            $params[] = $errorMessage;
        }
        
        $sql .= " WHERE id = ?";
        $params[] = $queueId;
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        
    } catch (Exception $e) {
        error_log("Error updating notification status: " . $e->getMessage());
    }
}

/**
 * רישום התראה בלוג
 */
function logNotification($queueId, $userId, $type, $channel, $data, $status) {
    try {
        $pdo = getDBConnection();
        
        $stmt = $pdo->prepare("
            INSERT INTO notification_log 
            (queue_id, user_id, type, channel, title, body, data, status, sent_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        
        $stmt->execute([
            $queueId,
            $userId,
            $type,
            $channel,
            $data['title'] ?? '',
            $data['body'] ?? '',
            json_encode($data, JSON_UNESCAPED_UNICODE),
            $status
        ]);
        
    } catch (Exception $e) {
        error_log("Error logging notification: " . $e->getMessage());
    }
}

/**
 * עדכון last_used של subscription
 */
function updateSubscriptionLastUsed($subscriptionId) {
    try {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("
            UPDATE push_subscriptions 
            SET last_used = NOW() 
            WHERE id = ?
        ");
        $stmt->execute([$subscriptionId]);
    } catch (Exception $e) {
        error_log("Error updating subscription last_used: " . $e->getMessage());
    }
}

/**
 * קבלת עדיפות לפי סוג התראה
 */
function getPriorityForType($type) {
    $priorities = [
        'group_invitation' => 1,
        'new_purchase' => 2,
        'invitation_response' => 2,
        'group_calculation' => 3,
        'reminder' => 3,
        'general' => 5
    ];
    
    return $priorities[$type] ?? 5;
}

/**
 * API endpoint לקבלת התראות ממתינות (עבור Service Worker)
 */
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action'])) {
    session_start();
    header('Content-Type: application/json');
    
    if ($_GET['action'] === 'get-pending' && isset($_SESSION['user_id'])) {
        try {
            $pdo = getDBConnection();
            
            // נסה קודם לקבל מהטבלה הזמנית
            createPendingPushTableIfNeeded($pdo);
            
            $stmt = $pdo->prepare("
                SELECT * FROM pending_push_notifications 
                WHERE user_id = ? AND delivered = FALSE 
                AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)
                ORDER BY created_at DESC
                LIMIT 10
            ");
            $stmt->execute([$_SESSION['user_id']]);
            $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (!empty($notifications)) {
                // סמן כנמסרו
                $ids = array_column($notifications, 'id');
                $placeholders = str_repeat('?,', count($ids) - 1) . '?';
                $stmt = $pdo->prepare("
                    UPDATE pending_push_notifications 
                    SET delivered = TRUE, delivered_at = NOW() 
                    WHERE id IN ($placeholders)
                ");
                $stmt->execute($ids);
                
                // החזר את ההתראות
                $result = array_map(function($n) {
                    return json_decode($n['data'], true);
                }, $notifications);
                
                echo json_encode(['success' => true, 'notifications' => $result]);
            } else {
                echo json_encode(['success' => true, 'notifications' => []]);
            }
            
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
    }
}
?>