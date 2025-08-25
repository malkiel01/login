<?php
// test-email-simple.php - בדיקה פשוטה של EmailService עם PHPMailer
session_start();
require_once 'config.php';
require_once 'includes/EmailService.php';

// בדיקת הרשאות
if (!isset($_GET['token']) || $_GET['token'] !== 'test123') {
    die('Access denied. Use ?token=test123');
}

$message = '';
$error = '';

if (isset($_POST['send'])) {
    try {
        $pdo = getDBConnection();
        $emailService = new EmailService($pdo);
        
        $to = $_POST['email'];
        $subject = 'בדיקת מערכת - ' . date('Y-m-d H:i:s');
        $html = '
            <!DOCTYPE html>
            <html dir="rtl">
            <head>
                <meta charset="UTF-8">
                <style>
                    body { font-family: Arial, sans-serif; background: #f5f5f5; padding: 20px; }
                    .container { max-width: 600px; margin: 0 auto; background: white; border-radius: 10px; padding: 30px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
                    .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; border-radius: 10px; text-align: center; margin-bottom: 20px; }
                    .success { background: #d4edda; color: #155724; padding: 15px; border-radius: 8px; margin: 20px 0; }
                </style>
            </head>
            <body>
                <div class="container">
                    <div class="header">
                        <h1>🎉 המייל עובד!</h1>
                    </div>
                    <div class="success">
                        <h2>ההגדרות שלך תקינות!</h2>
                        <p>PHPMailer עובד כמו שצריך עם ההגדרות מקובץ .env</p>
                        <p><strong>זמן שליחה:</strong> ' . date('Y-m-d H:i:s') . '</p>
                    </div>
                    <p>עכשיו כל ההתראות במערכת ישלחו כמו שצריך! 🚀</p>
                </div>
            </body>
            </html>';
        
        $result = $emailService->sendEmail($to, $subject, $html);
        
        if ($result) {
            $message = '✅ המייל נשלח בהצלחה! בדוק את תיבת הדואר.';
        } else {
            $error = '❌ השליחה נכשלה. בדוק את ההגדרות ב-.env';
        }
        
    } catch (Exception $e) {
        $error = '❌ שגיאה: ' . $e->getMessage();
    }
}

// בדיקת הגדרות
$envSettings = [
    'SMTP_HOST' => $_ENV['SMTP_HOST'] ?? 'לא מוגדר',
    'SMTP_PORT' => $_ENV['SMTP_PORT'] ?? 'לא מוגדר',
    'SMTP_USER' => $_ENV['SMTP_USER'] ?? 'לא מוגדר',
    'SMTP_PASS' => isset($_ENV['SMTP_PASS']) ? '****' : 'לא מוגדר',
    'MAIL_FROM' => $_ENV['MAIL_FROM'] ?? 'לא מוגדר'
];
?>
<!DOCTYPE html>
<html dir="rtl" lang="he">
<head>
    <meta charset="UTF-8">
    <title>🚀 בדיקת EmailService</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }
        h1 {
            color: #333;
            border-bottom: 3px solid #667eea;
            padding-bottom: 10px;
        }
        .status-box {
            padding: 20px;
            border-radius: 10px;
            margin: 20px 0;
        }
        .success {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
        }
        .error {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
        }
        .warning {
            background: #fff3cd;
            border: 1px solid #ffeeba;
            color: #856404;
        }
        .info {
            background: #d1ecf1;
            border: 1px solid #bee5eb;
            color: #0c5460;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 10px 0;
        }
        th, td {
            padding: 10px;
            text-align: right;
            border: 1px solid #dee2e6;
        }
        th {
            background: #f8f9fa;
        }
        .btn {
            background: #667eea;
            color: white;
            padding: 12px 25px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 16px;
            font-weight: bold;
            margin: 5px;
        }
        .btn:hover {
            background: #5569d0;
        }
        input[type="email"] {
            width: 100%;
            padding: 10px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 14px;
            margin: 10px 0;
        }
        code {
            background: #f4f4f4;
            padding: 2px 6px;
            border-radius: 3px;
            font-family: monospace;
        }
        .good { color: green; }
        .bad { color: red; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🚀 בדיקת EmailService</h1>
        
        <?php if ($message): ?>
            <div class="status-box success"><?php echo $message; ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="status-box error"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <div class="status-box info">
            <h3>📋 הגדרות ENV נוכחיות:</h3>
            <table>
                <?php foreach ($envSettings as $key => $value): ?>
                <tr>
                    <td><code><?php echo $key; ?></code></td>
                    <td class="<?php echo $value === 'לא מוגדר' ? 'bad' : 'good'; ?>">
                        <?php echo $value; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </table>
            
            <?php if (in_array('לא מוגדר', $envSettings)): ?>
            <div class="status-box warning" style="margin-top: 15px;">
                <strong>⚠️ חסרות הגדרות!</strong><br>
                הוסף את ההגדרות החסרות לקובץ <code>.env</code>
            </div>
            <?php endif; ?>
        </div>
        
        <h2>📧 שלח מייל בדיקה:</h2>
        <form method="POST">
            <input type="email" name="email" placeholder="הכנס אימייל" 
                   value="<?php echo $_SESSION['email'] ?? ''; ?>" required>
            <button type="submit" name="send" class="btn">
                📤 שלח מייל בדיקה
            </button>
        </form>
        
        <div style="margin-top: 30px;">
            <a href="test-notifications.php?token=test123" class="btn" style="text-decoration: none; display: inline-block;">
                ↩️ חזרה לבדיקת התראות
            </a>
        </div>
    </div>
</body>
</html>