<?php
// test-email-debug.php - בדיקה מפורטת של יכולות שליחת המייל
session_start();
require_once 'config.php';

// בדיקת הרשאות
if (!isset($_GET['token']) || $_GET['token'] !== 'debug123') {
    die('Access denied. Use ?token=debug123');
}

?>
<!DOCTYPE html>
<html dir="rtl" lang="he">
<head>
    <meta charset="UTF-8">
    <title>🔍 בדיקת מערכת מייל</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        .container {
            max-width: 800px;
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
        .test-box {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin: 20px 0;
            border: 2px solid #e9ecef;
        }
        .success {
            background: #d4edda;
            color: #155724;
            padding: 15px;
            border-radius: 8px;
            margin: 10px 0;
            border: 1px solid #c3e6cb;
        }
        .error {
            background: #f8d7da;
            color: #721c24;
            padding: 15px;
            border-radius: 8px;
            margin: 10px 0;
            border: 1px solid #f5c6cb;
        }
        .warning {
            background: #fff3cd;
            color: #856404;
            padding: 15px;
            border-radius: 8px;
            margin: 10px 0;
            border: 1px solid #ffeeba;
        }
        .info {
            background: #d1ecf1;
            color: #0c5460;
            padding: 15px;
            border-radius: 8px;
            margin: 10px 0;
            border: 1px solid #bee5eb;
        }
        code {
            background: #f4f4f4;
            padding: 2px 6px;
            border-radius: 3px;
            font-family: monospace;
        }
        .btn {
            background: #667eea;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            margin: 5px;
        }
        .btn:hover {
            background: #5569d0;
        }
        pre {
            background: #263238;
            color: #aed581;
            padding: 15px;
            border-radius: 8px;
            overflow-x: auto;
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
    </style>
</head>
<body>
    <div class="container">
        <h1>🔍 בדיקת מערכת שליחת מיילים</h1>
        
        <?php
        echo "<h2>1️⃣ בדיקת פונקציית mail()</h2>";
        echo "<div class='test-box'>";
        
        if (function_exists('mail')) {
            echo "<div class='success'>✅ פונקציית mail() קיימת</div>";
        } else {
            echo "<div class='error'>❌ פונקציית mail() לא קיימת!</div>";
        }
        
        // בדיקת הגדרות PHP
        $ini_settings = [
            'sendmail_path' => ini_get('sendmail_path'),
            'SMTP' => ini_get('SMTP'),
            'smtp_port' => ini_get('smtp_port'),
            'mail.add_x_header' => ini_get('mail.add_x_header'),
            'mail.force_extra_parameters' => ini_get('mail.force_extra_parameters')
        ];
        
        echo "<h3>הגדרות PHP Mail:</h3>";
        echo "<table>";
        foreach ($ini_settings as $key => $value) {
            $value = $value ?: '(ריק)';
            echo "<tr><td><code>$key</code></td><td>$value</td></tr>";
        }
        echo "</table>";
        echo "</div>";
        
        // בדיקת משתני סביבה
        echo "<h2>2️⃣ בדיקת הגדרות ENV</h2>";
        echo "<div class='test-box'>";
        
        $env_vars = [
            'MAIL_FROM' => $_ENV['MAIL_FROM'] ?? 'לא מוגדר',
            'MAIL_FROM_NAME' => $_ENV['MAIL_FROM_NAME'] ?? 'לא מוגדר',
            'SMTP_HOST' => $_ENV['SMTP_HOST'] ?? 'לא מוגדר',
            'SMTP_PORT' => $_ENV['SMTP_PORT'] ?? 'לא מוגדר',
            'SMTP_USER' => $_ENV['SMTP_USER'] ?? 'לא מוגדר',
            'SMTP_PASS' => isset($_ENV['SMTP_PASS']) ? '****' : 'לא מוגדר'
        ];
        
        echo "<table>";
        foreach ($env_vars as $key => $value) {
            $class = ($value === 'לא מוגדר') ? 'style="color: red;"' : '';
            echo "<tr><td><code>$key</code></td><td $class>$value</td></tr>";
        }
        echo "</table>";
        echo "</div>";
        
        // בדיקת שליחה בסיסית
        if (isset($_POST['test_simple'])) {
            echo "<h2>3️⃣ תוצאות בדיקת שליחה פשוטה</h2>";
            echo "<div class='test-box'>";
            
            $to = $_POST['email'];
            $subject = 'Test Email - ' . date('Y-m-d H:i:s');
            $message = 'This is a simple test email.';
            $headers = 'From: noreply@' . $_SERVER['HTTP_HOST'];
            
            echo "<div class='info'>שולח ל: $to</div>";
            
            // נסה לשלוח
            $result = @mail($to, $subject, $message, $headers);
            
            if ($result) {
                echo "<div class='success'>✅ הפונקציה החזירה TRUE - כנראה נשלח</div>";
                echo "<div class='warning'>⚠️ בדוק את תיבת הדואר שלך (כולל ספאם)</div>";
            } else {
                echo "<div class='error'>❌ הפונקציה החזירה FALSE - השליחה נכשלה</div>";
                
                // בדוק שגיאות
                $error = error_get_last();
                if ($error) {
                    echo "<div class='error'>שגיאה: " . htmlspecialchars($error['message']) . "</div>";
                }
            }
            echo "</div>";
        }
        
        // בדיקת שליחה עם EmailService
        if (isset($_POST['test_service'])) {
            echo "<h2>4️⃣ תוצאות בדיקה עם EmailService</h2>";
            echo "<div class='test-box'>";
            
            try {
                require_once 'includes/EmailService.php';
                $pdo = getDBConnection();
                $emailService = new EmailService($pdo);
                
                $to = $_POST['email'];
                $subject = 'Test from EmailService - ' . date('Y-m-d H:i:s');
                $html = '<h1>בדיקה</h1><p>זוהי הודעת בדיקה מ-EmailService</p>';
                
                echo "<div class='info'>שולח עם EmailService ל: $to</div>";
                
                $result = $emailService->sendEmail($to, $subject, $html);
                
                if ($result) {
                    echo "<div class='success'>✅ EmailService החזיר TRUE</div>";
                } else {
                    echo "<div class='error'>❌ EmailService החזיר FALSE</div>";
                }
                
            } catch (Exception $e) {
                echo "<div class='error'>❌ שגיאה: " . $e->getMessage() . "</div>";
            }
            echo "</div>";
        }
        
        // בדיקת לוג אימיילים
        echo "<h2>5️⃣ לוג אימיילים אחרונים</h2>";
        echo "<div class='test-box'>";
        
        try {
            $pdo = getDBConnection();
            $stmt = $pdo->query("
                SELECT * FROM email_log 
                ORDER BY sent_at DESC 
                LIMIT 10
            ");
            $logs = $stmt->fetchAll();
            
            if (empty($logs)) {
                echo "<div class='info'>אין רשומות בלוג</div>";
            } else {
                echo "<table>";
                echo "<tr><th>תאריך</th><th>נמען</th><th>נושא</th><th>סטטוס</th></tr>";
                foreach ($logs as $log) {
                    $status = $log['status'] === 'sent' ? '✅' : '❌';
                    echo "<tr>";
                    echo "<td>" . date('d/m H:i', strtotime($log['sent_at'])) . "</td>";
                    echo "<td>" . htmlspecialchars($log['to_email']) . "</td>";
                    echo "<td>" . htmlspecialchars(substr($log['subject'], 0, 30)) . "</td>";
                    echo "<td>$status</td>";
                    echo "</tr>";
                }
                echo "</table>";
            }
        } catch (Exception $e) {
            echo "<div class='warning'>לא ניתן לקרוא את הלוג: " . $e->getMessage() . "</div>";
        }
        echo "</div>";
        ?>
        
        <h2>🧪 בדיקות שליחה</h2>
        
        <div class="test-box">
            <h3>בדיקה פשוטה (mail() ישירות)</h3>
            <form method="POST">
                <input type="email" name="email" placeholder="הכנס אימייל לבדיקה" required 
                       value="<?php echo $_SESSION['email'] ?? ''; ?>" style="width: 300px; padding: 8px;">
                <button type="submit" name="test_simple" class="btn">🚀 שלח בדיקה פשוטה</button>
            </form>
        </div>
        
        <div class="test-box">
            <h3>בדיקה עם EmailService</h3>
            <form method="POST">
                <input type="email" name="email" placeholder="הכנס אימייל לבדיקה" required 
                       value="<?php echo $_SESSION['email'] ?? ''; ?>" style="width: 300px; padding: 8px;">
                <button type="submit" name="test_service" class="btn">📧 שלח עם EmailService</button>
            </form>
        </div>
        
        <h2>💡 פתרונות אפשריים</h2>
        
        <div class="warning">
            <h3>אם mail() לא עובד:</h3>
            <ol>
                <li><strong>השתמש ב-PHPMailer עם SMTP:</strong><br>
                    <code>composer require phpmailer/phpmailer</code>
                </li>
                <li><strong>הגדר SMTP בשרת:</strong><br>
                    וודא ש-sendmail או postfix מותקנים ומוגדרים
                </li>
                <li><strong>השתמש בשירות חיצוני:</strong><br>
                    SendGrid, Mailgun, או שירות SMTP אחר
                </li>
            </ol>
        </div>
        
        <div class="info">
            <h3>בדיקות נוספות:</h3>
            <ul>
                <li>בדוק את תיבת הספאם</li>
                <li>וודא שהדומיין מוגדר עם SPF ו-DKIM</li>
                <li>בדוק את לוגים של השרת: <code>/var/log/mail.log</code></li>
                <li>נסה עם כתובת מייל שונה (לא Gmail)</li>
            </ul>
        </div>
        
        <div style="margin-top: 30px;">
            <a href="test-notifications.php?token=test123" class="btn">↩️ חזרה לבדיקת התראות</a>
            <a href="setup_notifications.php?token=setup123" class="btn">🔧 התקנת טבלאות</a>
        </div>
    </div>
</body>
</html>