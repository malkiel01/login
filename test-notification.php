<?php
session_start();
require_once 'config.php';
require_once '/api/send-push-notification.php';

echo "<h1>בדיקת מערכת התראות</h1>";

// // בדיקה 1: האם הפונקציה קיימת?
// if (function_exists('notifyGroupInvitation')) {
//     echo "✅ הפונקציה notifyGroupInvitation קיימת<br>";
// } else {
//     echo "❌ הפונקציה notifyGroupInvitation לא נמצאה<br>";
//     die();
// }

// // בדיקה 2: האם יש הזמנות בטבלה?
// $pdo = getDBConnection();
// $stmt = $pdo->query("SELECT id, email FROM group_invitations ORDER BY id DESC LIMIT 1");
// $invitation = $stmt->fetch();

// if ($invitation) {
//     echo "✅ נמצאה הזמנה מספר: " . $invitation['id'] . " לאימייל: " . $invitation['email'] . "<br>";
    
//     // בדיקה 3: נסה לשלוח התראה
//     echo "<h3>מנסה לשלוח התראה...</h3>";
//     $result = notifyGroupInvitation($invitation['id']);
    
//     echo "<pre>";
//     print_r($result);
//     echo "</pre>";
    
//     if ($result['success']) {
//         echo "✅ ההתראה נשלחה בהצלחה!<br>";
        
//         // בדיקה 4: האם נשמרה בטבלה?
//         $stmt = $pdo->query("SELECT * FROM notification_queue ORDER BY id DESC LIMIT 1");
//         $notification = $stmt->fetch();
//         echo "<h3>ההתראה האחרונה בטבלה:</h3>";
//         echo "<pre>";
//         print_r($notification);
//         echo "</pre>";
//     } else {
//         echo "❌ ההתראה נכשלה: " . $result['message'] . "<br>";
//     }
// } else {
//     echo "❌ אין הזמנות בטבלה<br>";
// }
?>