<?php
// process_notifications.php - מעבד תור התראות
require_once 'config.php';
require_once 'includes/EmailService.php';

// אפשר הרצה גם מ-CLI וגם מהדפדפן
$isCLI = php_sapi_name() === 'cli';
if (!$isCLI) {
    session_start();
    
    // בדיקת הרשאות בסיסית
    if (!isset($_GET['token']) || $_GET['token'] !== 'process123') {
        if (!isset($_SESSION['user_id'])) {
            die('Access denied. Use ?token=process123');
        }
    }
}

// הגדרות
$maxAttempts = 3;
$batchSize = 10;

// התחל עיבוד
$pdo = getDBConnection();
$emailService = new EmailService($pdo);

echo $isCLI ? "" : "<pre style='font-family: monospace;'>";
echo "=== Notification Processor Started ===\n";
echo "Time: " . date('Y-m-d H:i:s') . "\n\n";

try {
    // קבל התראות ממתינות
    $stmt = $pdo->prepare("
        SELECT * FROM notification_queue 
        WHERE status = 'pending' 
        AND (attempts < ? OR attempts IS NULL)
        ORDER BY priority ASC, created_at ASC
        LIMIT ?
    ");
    $stmt->execute([$maxAttempts, $batchSize]);
    $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($notifications)) {
        echo "No pending notifications found.\n";
    } else {
        echo "Found " . count($notifications) . " pending notifications.\n\n";
        
        foreach ($notifications as $notification) {
            echo "Processing notification #" . $notification['id'] . " (Type: " . $notification['type'] . ")...\n";
            
            // עדכן סטטוס ל-processing
            $updateStmt = $pdo->prepare("
                UPDATE notification_queue 
                SET status = 'processing', 
                    last_attempt = NOW(),
                    attempts = COALESCE(attempts, 0) + 1
                WHERE id = ?
            ");
            $updateStmt->execute([$notification['id']]);
            
            // פענח את הנתונים
            $data = json_decode($notification['data'], true);
            
            // שלח לפי סוג ההתראה
            $success = false;
            $errorMessage = null;
            
            try {
                switch ($notification['type']) {
                    case 'invitation':
                        $success = sendInvitationEmail($data, $emailService, $pdo);
                        break;
                        
                    case 'new_purchase':
                        $success = sendPurchaseEmail($data, $emailService, $pdo);
                        break;
                        
                    case 'calculation_update':
                        $success = sendCalculationEmail($data, $emailService, $pdo);
                        break;
                        
                    default:
                        // נסה לשלוח אימייל גנרי
                        $success = sendGenericEmail($data, $emailService);
                        break;
                }
                
                if ($success) {
                    echo "  ✅ Sent successfully!\n";
                } else {
                    echo "  ❌ Failed to send.\n";
                    $errorMessage = "Send failed";
                }
                
            } catch (Exception $e) {
                echo "  ❌ Error: " . $e->getMessage() . "\n";
                $errorMessage = $e->getMessage();
                $success = false;
            }
            
            // עדכן סטטוס סופי
            if ($success) {
                $finalStmt = $pdo->prepare("
                    UPDATE notification_queue 
                    SET status = 'completed',
                        processed_at = NOW()
                    WHERE id = ?
                ");
                $finalStmt->execute([$notification['id']]);
                
                // רשום בלוג
                logNotification($pdo, $notification, 'sent');
                
            } else {
                // בדוק אם להמשיך לנסות
                $attempts = ($notification['attempts'] ?? 0) + 1;
                
                if ($attempts >= $maxAttempts) {
                    $finalStmt = $pdo->prepare("
                        UPDATE notification_queue 
                        SET status = 'failed',
                            error_message = ?
                        WHERE id = ?
                    ");
                    $finalStmt->execute([$errorMessage, $notification['id']]);
                    
                    // רשום בלוג
                    logNotification($pdo, $notification, 'failed', $errorMessage);
                    
                } else {
                    // החזר ל-pending לניסיון נוסף
                    $finalStmt = $pdo->prepare("
                        UPDATE notification_queue 
                        SET status = 'pending',
                            error_message = ?
                        WHERE id = ?
                    ");
                    $finalStmt->execute([$errorMessage, $notification['id']]);
                }
            }
            
            echo "\n";
        }
    }
    
    echo "\n=== Processing Complete ===\n";
    
    // הצג סיכום
    $stmt = $pdo->query("
        SELECT status, COUNT(*) as count 
        FROM notification_queue 
        WHERE created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)
        GROUP BY status
    ");
    
    echo "\nSummary (last 24 hours):\n";
    while ($row = $stmt->fetch()) {
        echo "  " . $row['status'] . ": " . $row['count'] . "\n";
    }
    
} catch (Exception $e) {
    echo "Fatal error: " . $e->getMessage() . "\n";
}

echo $isCLI ? "" : "</pre>";

// =================== פונקציות עזר ===================

function sendInvitationEmail($data, $emailService, $pdo) {
    // חפש הזמנה קיימת במערכת
    if (isset($data['invitation_id'])) {
        return $emailService->sendGroupInvitation($data['invitation_id']);
    }
    
    // אחרת, שלח אימייל ישיר
    $to = $data['email'] ?? '';
    $groupName = $data['group_name'] ?? 'קבוצה חדשה';
    $inviterName = $data['inviter_name'] ?? 'מנהל המערכת';
    
    if (empty($to)) {
        throw new Exception('Missing email address');
    }
    
    $subject = "הזמנה לקבוצת רכישה - $groupName";
    
    $htmlBody = "
    <!DOCTYPE html>
    <html dir='rtl'>
    <head>
        <meta charset='UTF-8'>
        <style>
            body { font-family: Arial, sans-serif; background: #f5f5f5; margin: 0; padding: 20px; }
            .container { max-width: 600px; margin: 0 auto; background: white; border-radius: 10px; overflow: hidden; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
            .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; text-align: center; }
            .content { padding: 30px; }
            .button { display: inline-block; background: #667eea; color: white; padding: 12px 30px; border-radius: 25px; text-decoration: none; margin: 20px 0; }
            .footer { background: #f8f9fa; padding: 20px; text-align: center; color: #666; font-size: 14px; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>🎉 הזמנה לקבוצת רכישה</h1>
            </div>
            <div class='content'>
                <h2>שלום!</h2>
                <p><strong>$inviterName</strong> הזמין אותך להצטרף לקבוצת הרכישה:</p>
                <h3 style='color: #667eea;'>$groupName</h3>
                <p>בקבוצה זו תוכל לחלוק קניות ולנהל חישובים בצורה פשוטה ונוחה.</p>
                <center>
                    <a href='https://form.mbe-plus.com/family/dashboard.php' class='button'>
                        כניסה למערכת
                    </a>
                </center>
            </div>
            <div class='footer'>
                <p>הודעה זו נשלחה ממערכת ניהול קניות מרוכזות</p>
                <p>זמן שליחה: " . date('d/m/Y H:i:s') . "</p>
            </div>
        </div>
    </body>
    </html>";
    
    return $emailService->sendEmail($to, $subject, $htmlBody);
}

function sendPurchaseEmail($data, $emailService, $pdo) {
    if (isset($data['purchase_id'])) {
        return $emailService->sendNewPurchaseNotification($data['purchase_id']);
    }
    
    $to = $data['email'] ?? '';
    $groupName = $data['group_name'] ?? 'הקבוצה';
    $memberName = $data['member_name'] ?? 'משתתף';
    $amount = $data['amount'] ?? 0;
    
    $subject = "קנייה חדשה בקבוצה $groupName";
    
    $htmlBody = "
    <html dir='rtl'>
    <body style='font-family: Arial, sans-serif;'>
        <h2>קנייה חדשה בקבוצה</h2>
        <p><strong>$memberName</strong> הוסיף קנייה בסך ₪" . number_format($amount, 2) . "</p>
        <p>קבוצה: $groupName</p>
        <a href='https://form.mbe-plus.com/family/dashboard.php'>כניסה למערכת</a>
    </body>
    </html>";
    
    return $emailService->sendEmail($to, $subject, $htmlBody);
}

function sendCalculationEmail($data, $emailService, $pdo) {
    $to = $data['email'] ?? '';
    $groupName = $data['group_name'] ?? 'הקבוצה';
    $amount = $data['amount'] ?? 0;
    $status = $data['status_text'] ?? 'עדכון';
    
    $subject = "עדכון חישובים - $groupName";
    
    $htmlBody = "
    <html dir='rtl'>
    <body style='font-family: Arial, sans-serif;'>
        <h2>עדכון חישובים</h2>
        <p>$status: ₪" . number_format($amount, 2) . "</p>
        <p>קבוצה: $groupName</p>
        <a href='https://form.mbe-plus.com/family/dashboard.php'>כניסה למערכת</a>
    </body>
    </html>";
    
    return $emailService->sendEmail($to, $subject, $htmlBody);
}

function sendGenericEmail($data, $emailService) {
    $to = $data['email'] ?? '';
    $subject = $data['subject'] ?? 'התראה ממערכת ניהול קניות';
    $body = $data['body'] ?? $data['message'] ?? 'התראה חדשה';
    
    if (empty($to)) {
        throw new Exception('Missing email address');
    }
    
    $htmlBody = "
    <html dir='rtl'>
    <body style='font-family: Arial, sans-serif;'>
        <div style='max-width: 600px; margin: 0 auto; padding: 20px;'>
            <h2>$subject</h2>
            <p>$body</p>
            <hr>
            <p><small>נשלח בתאריך: " . date('d/m/Y H:i:s') . "</small></p>
        </div>
    </body>
    </html>";
    
    return $emailService->sendEmail($to, $subject, $htmlBody);
}

function logNotification($pdo, $notification, $status, $error = null) {
    try {
        $data = json_decode($notification['data'], true);
        $email = $data['email'] ?? 'unknown';
        
        $stmt = $pdo->prepare("
            INSERT INTO notification_log 
            (queue_id, type, channel, title, body, status, error_details, sent_at)
            VALUES (?, ?, 'email', ?, ?, ?, ?, NOW())
        ");
        
        $stmt->execute([
            $notification['id'],
            $notification['type'],
            $notification['type'],
            $email,
            $status,
            $error
        ]);
    } catch (Exception $e) {
        // אל תעצור את התהליך בגלל שגיאת לוג
        echo "  Warning: Failed to log notification: " . $e->getMessage() . "\n";
    }
}

// אם רץ מ-CLI, אפשר לולאה אינסופית
if ($isCLI && isset($argv[1]) && $argv[1] === 'daemon') {
    echo "\nRunning in daemon mode. Press Ctrl+C to stop.\n\n";
    
    while (true) {
        sleep(30); // המתן 30 שניות בין ריצות
        echo "\n--- Running next batch ---\n";
        include __FILE__;
    }
}
?>