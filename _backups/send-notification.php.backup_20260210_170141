<?php
/**
 * Helper Functions לשליחת התראות
 * push/send-notification.php
 */

require_once '../config.php';

/**
 * שלח התראה למשתמש
 */
function sendNotificationToUser($userId, $title, $body, $url = null) {
    $pdo = getDBConnection();
    
    $stmt = $pdo->prepare("
        INSERT INTO push_notifications (user_id, title, body, url) 
        VALUES (?, ?, ?, ?)
    ");
    
    return $stmt->execute([$userId, $title, $body, $url]);
}

/**
 * שלח התראה לכל המשתמשים
 */
function sendNotificationToAll($title, $body, $url = null) {
    $pdo = getDBConnection();
    
    // קבל את כל המשתמשים הפעילים
    $users = $pdo->query("SELECT id FROM users WHERE is_active = 1")->fetchAll(PDO::FETCH_COLUMN);
    
    $count = 0;
    foreach ($users as $userId) {
        if (sendNotificationToUser($userId, $title, $body, $url)) {
            $count++;
        }
    }
    
    return $count;
}

/**
 * שלח התראה לקבוצה
 */
function sendNotificationToGroup($groupId, $title, $body, $url = null) {
    $pdo = getDBConnection();
    
    // קבל את חברי הקבוצה
    $stmt = $pdo->prepare("SELECT user_id FROM group_members WHERE group_id = ?");
    $stmt->execute([$groupId]);
    $users = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    $count = 0;
    foreach ($users as $userId) {
        if (sendNotificationToUser($userId, $title, $body, $url)) {
            $count++;
        }
    }
    
    return $count;
}

// דוגמאות שימוש:

// כשמישהו הוסיף פריט לרשימה
function notifyNewItem($listId, $itemName, $addedByUser) {
    $pdo = getDBConnection();
    
    // קבל את כל המשתמשים ברשימה
    $stmt = $pdo->prepare("
        SELECT DISTINCT user_id 
        FROM list_members 
        WHERE list_id = ? AND user_id != ?
    ");
    $stmt->execute([$listId, $addedByUser]);
    $users = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    $title = "פריט חדש ברשימה";
    $body = "נוסף: $itemName";
    $url = "/lists/view.php?id=$listId";
    
    foreach ($users as $userId) {
        sendNotificationToUser($userId, $title, $body, $url);
    }
}

// כשמישהו סימן פריט כנקנה
function notifyItemPurchased($itemId, $itemName, $purchasedByUser) {
    $pdo = getDBConnection();
    
    // קבל את בעל הרשימה
    $stmt = $pdo->prepare("
        SELECT l.owner_id 
        FROM items i 
        JOIN lists l ON i.list_id = l.id 
        WHERE i.id = ?
    ");
    $stmt->execute([$itemId]);
    $ownerId = $stmt->fetchColumn();
    
    if ($ownerId && $ownerId != $purchasedByUser) {
        $title = "פריט נקנה ✓";
        $body = "$itemName סומן כנקנה";
        sendNotificationToUser($ownerId, $title, $body);
    }
}

// התראה על משימה דחופה
function notifyUrgentTask($taskName, $deadline) {
    $title = "⚠️ משימה דחופה!";
    $body = "$taskName - דדליין: $deadline";
    
    // שלח לכל המשתמשים
    return sendNotificationToAll($title, $body);
}
?>