<?php
// רוץ כל דקה ב-cron
require_once '../config.php';

$pdo = getDBConnection();

// קבל התראות ממתינות
$stmt = $pdo->prepare("
    SELECT * FROM notification_queue 
    WHERE status = 'pending' 
    LIMIT 50
");
$stmt->execute();
$notifications = $stmt->fetchAll();

foreach ($notifications as $notif) {
    $data = json_decode($notif['data'], true);
    
    // כאן תשלח את ההתראה בפועל
    // (צריך להוסיף את הקוד של Web Push)
    
    // לעכשיו רק סמן כנשלח
    $stmt = $pdo->prepare("
        UPDATE notification_queue 
        SET status = 'sent', sent_at = NOW() 
        WHERE id = ?
    ");
    $stmt->execute([$notif['id']]);
}

echo "Processed " . count($notifications) . " notifications\n";