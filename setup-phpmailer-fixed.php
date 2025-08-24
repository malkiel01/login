<?php
// setup-phpmailer-fixed.php - עם נתיב נכון לתיקיית vendor
require_once 'config.php';

// בדיקת הרשאות
if (!isset($_GET['token']) || $_GET['token'] !== 'mailer123') {
    die('Access denied. Use ?token=mailer123');
}

$message = '';
$error = '';

// נתיב נכון ל-vendor - רמה אחת למעלה
$vendorPath = dirname(__DIR__) . '/vendor/autoload.php';
$phpmailerInstalled = false;

// בדיקה אם PHPMailer מותקן
if (file_exists($vendorPath)) {
    require_once $vendorPath;
    $phpmailerInstalled = class_exists('PHPMailer\\PHPMailer\\PHPMailer');
}

// טיפול בשליחת מייל בדיקה
if (isset($_POST['test_phpmailer'])) {
    if (!$phpmailerInstalled) {
        $error = 'PHPMailer לא מותקן! נתיב vendor: ' . $vendorPath;
    } else {
        try {
            $mail = new PHPMailer\PHPMailer\PHPMailer(true);
            
            // הפעל דיבאג למידע מפורט
            $mail->SMTPDebug = isset($_POST['debug']) ? 2 : 0;
            $mail->Debugoutput = function($str, $level) {
                echo "<pre style='background: #f8f9fa; padding: 10px; margin: 5px 0;'>$str</pre>";
            };
            
            // הגדרות שרת
            $mail->isSMTP();
            $mail->Host       = $_POST['smtp_host'];
            $mail->SMTPAuth   = true;
            $mail->Username   = $_POST['smtp_user'];
            $mail->Password   = $_POST['smtp_pass'];
            $mail->SMTPSecure = $_POST['smtp_secure'];
            $mail->Port       = $_POST['smtp_port'];
            $mail->CharSet    = 'UTF-8';
            
            // נמענים
            $mail->setFrom($_POST['from_email'], $_POST['from_name']);
            $mail->addAddress($_POST['to_email']);
            
            // תוכן
            $mail->isHTML(true);
            $mail->Subject = 'בדיקת PHPMailer - ' . date('Y-m-d H:i:s');
            $mail->Body    = '
                <!DOCTYPE html>
                <html dir="rtl">
                <head>
                    <meta charset="UTF-8">
                    <style>
                        body { font-family: Arial, sans-serif; background: #f5f5f5; padding: 20px; }
                        .container { max-width: 600px; margin: 0 auto; background: white; border-radius: 10px; padding: 30px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
                        h1 { color: #667eea; }
                        .info { background: #f8f9fa; padding: 15px; border-radius: 8px; margin: 20px 0; }
                    </style>
                </head>
                <body>
                    <div class="container">
                        <h1>🎉 בדיקת PHPMailer הצליחה!</h1>
                        <p>ההודעה נשלחה בהצלחה דרך SMTP.</p>
                        <div class="info">
                            <strong>פרטי השליחה:</strong><br>
                            שרת: ' . $_POST['smtp_host'] . '<br>
                            פורט: ' . $_POST['smtp_port'] . '<br>
                            זמן: ' . date('Y-m-d H:i:s') . '
                        </div>
                    </div>
                </body>
                </html>';
            $mail->AltBody = 'בדיקת PHPMailer - ההודעה נשלחה בהצלחה!';
            
            $mail->send();
            $message = '✅ המייל נשלח בהצלחה! בדוק את תיבת הדואר שלך.';
            
            // שמור הגדרות שעבדו
            $_SESSION['smtp_settings'] = [
                'host' => $_POST['smtp_host'],
                'port' => $_POST['smtp_port'],
                'user' => $_POST['smtp_user'],
                'secure' => $_POST['smtp_secure']
            ];
            
        } catch (Exception $e) {
            $error = "❌ שגיאה: {$mail->ErrorInfo}";
            if ($mail->SMTPDebug == 0) {
                $error .= "<br>💡 סמן 'הצג דיבאג' לקבלת מידע מפורט";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html dir="rtl" lang="he">
<head>
    <meta charset="UTF-8">
    <title>🚀 הגדרת PHPMailer</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        .container {
            max-width: 900px;
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
        .status-box.success {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
        }
        .status-box.error {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
        }
        .status-box.warning {
            background: #fff3cd;
            border: 1px solid #ffeeba;
            color: #856404;
        }
        .status-box.info {
            background: #d1ecf1;
            border: 1px solid #bee5eb;
            color: #0c5460;
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: bold;
            color: #333;
        }
        .form-group input, .form-group select {
            width: 100%;
            padding: 10px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 14px;
        }
        .form-group input:focus, .form-group select:focus {
            outline: none;
            border-color: #667eea;
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
        .btn-success {
            background: #28a745;
        }
        .btn-success:hover {
            background: #218838;
        }
        code {
            background: #f4f4f4;
            padding: 3px 6px;
            border-radius: 3px;
            font-family: monospace;
        }
        .preset-buttons {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin: 20px 0;
        }
        .preset-btn {
            padding: 20px;
            background: #f8f9fa;
            border: 2px solid #dee2e6;
            border-radius: 8px;
            cursor: pointer;
            text-align: center;
            transition: all 0.3s;
        }
        .preset-btn:hover {
            background: #e9ecef;
            border-color: #667eea;
            transform: translateY(-2px);
        }
        .preset-btn.active {
            background: #667eea;
            color: white;
            border-color: #667eea;
        }
        pre {
            background: #263238;
            color: #aed581;
            padding: 15px;
            border-radius: 8px;
            overflow-x: auto;
        }
        .path-info {
            background: #f8f9fa;
            padding: 10px;
            border-radius: 5px;
            font-family: monospace;
            font-size: 12px;
            color: #666;
            margin: 10px 0;
        }
        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 10px;
            margin: 15px 0;
        }
        .checkbox-group input {
            width: auto;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🚀 הגדרת PHPMailer עם SMTP</h1>
        
        <?php if ($message): ?>
            <div class="status-box success"><?php echo $message; ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="status-box error"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <!-- בדיקת סטטוס -->
        <div class="status-box <?php echo $phpmailerInstalled ? 'success' : 'warning'; ?>">
            <h3>📦 סטטוס PHPMailer:</h3>
            <?php if ($phpmailerInstalled): ?>
                <p>✅ PHPMailer מותקן ומוכן לשימוש!</p>
                <div class="path-info">
                    נתיב vendor: <?php echo $vendorPath; ?>
                </div>
            <?php else: ?>
                <p>⚠️ PHPMailer לא נמצא בנתיב:</p>
                <div class="path-info"><?php echo $vendorPath; ?></div>
                <p>הרץ את הפקודה בתיקייה <code>public_html/form</code>:</p>
                <pre>cd /path/to/public_html/form
composer require phpmailer/phpmailer</pre>
            <?php endif; ?>
        </div>
        
        <!-- הגדרות SMTP מוכנות -->
        <h2>📧 בחר ספק דואר:</h2>
        <div class="preset-buttons">
            <div class="preset-btn" onclick="setGmailSettings()" id="gmail-btn">
                <div style="font-size: 30px;">📧</div>
                <strong>Gmail</strong>
                <small>מומלץ!</small>
            </div>
            <div class="preset-btn" onclick="setOutlookSettings()" id="outlook-btn">
                <div style="font-size: 30px;">📮</div>
                <strong>Outlook</strong>
            </div>
            <div class="preset-btn" onclick="setYahooSettings()" id="yahoo-btn">
                <div style="font-size: 30px;">📬</div>
                <strong>Yahoo</strong>
            </div>
            <div class="preset-btn" onclick="setOffice365Settings()" id="office365-btn">
                <div style="font-size: 30px;">📪</div>
                <strong>Office 365</strong>
            </div>
        </div>
        
        <!-- טופס הגדרות -->
        <form method="POST">
            <h2>⚙️ הגדרות SMTP</h2>
            
            <div class="form-group">
                <label>שרת SMTP:</label>
                <input type="text" name="smtp_host" id="smtp_host" 
                       value="<?php echo $_SESSION['smtp_settings']['host'] ?? 'smtp.gmail.com'; ?>" required>
            </div>
            
            <div class="form-group">
                <label>פורט:</label>
                <input type="number" name="smtp_port" id="smtp_port" 
                       value="<?php echo $_SESSION['smtp_settings']['port'] ?? '587'; ?>" required>
            </div>
            
            <div class="form-group">
                <label>אבטחה:</label>
                <select name="smtp_secure" id="smtp_secure">
                    <option value="tls" <?php echo ($_SESSION['smtp_settings']['secure'] ?? 'tls') == 'tls' ? 'selected' : ''; ?>>TLS (מומלץ)</option>
                    <option value="ssl" <?php echo ($_SESSION['smtp_settings']['secure'] ?? '') == 'ssl' ? 'selected' : ''; ?>>SSL</option>
                    <option value="">ללא</option>
                </select>
            </div>
            
            <div class="form-group">
                <label>שם משתמש (אימייל מלא):</label>
                <input type="email" name="smtp_user" id="smtp_user" 
                       placeholder="your-email@gmail.com" required>
            </div>
            
            <div class="form-group">
                <label>סיסמה / App Password:</label>
                <input type="password" name="smtp_pass" id="smtp_pass" required>
                <small style="color: #666;">
                    ל-Gmail: חובה להשתמש ב-<a href="https://myaccount.google.com/apppasswords" target="_blank">App Password</a> (לא הסיסמה הרגילה!)
                </small>
            </div>
            
            <h2>📨 פרטי השולח והנמען</h2>
            
            <div class="form-group">
                <label>אימייל שולח:</label>
                <input type="email" name="from_email" id="from_email" 
                       value="<?php echo $_ENV['MAIL_FROM'] ?? 'noreply@example.com'; ?>" required>
                <small style="color: #666;">עדיף להשתמש באותו אימייל כמו שם המשתמש</small>
            </div>
            
            <div class="form-group">
                <label>שם השולח:</label>
                <input type="text" name="from_name" 
                       value="<?php echo $_ENV['MAIL_FROM_NAME'] ?? 'מערכת ניהול קניות'; ?>" required>
            </div>
            
            <div class="form-group">
                <label>שלח אל (לבדיקה):</label>
                <input type="email" name="to_email" 
                       value="<?php echo $_SESSION['email'] ?? ''; ?>" required>
            </div>
            
            <div class="checkbox-group">
                <input type="checkbox" name="debug" id="debug">
                <label for="debug">הצג דיבאג מפורט (לאיתור בעיות)</label>
            </div>
            
            <button type="submit" name="test_phpmailer" class="btn btn-success">
                📤 שלח מייל בדיקה
            </button>
        </form>
        
        <?php if (isset($_SESSION['smtp_settings'])): ?>
        <div class="status-box info">
            <h3>💾 הגדרות שעבדו!</h3>
            <p>הוסף את השורות הבאות לקובץ <code>.env</code> שלך:</p>
            <pre>
# SMTP Settings
SMTP_HOST=<?php echo $_SESSION['smtp_settings']['host']; ?>

SMTP_PORT=<?php echo $_SESSION['smtp_settings']['port']; ?>

SMTP_SECURE=<?php echo $_SESSION['smtp_settings']['secure']; ?>

SMTP_USER=your-email@gmail.com
SMTP_PASS=your-app-password

# Mail From Settings
MAIL_FROM=your-email@gmail.com
MAIL_FROM_NAME=מערכת ניהול קניות
            </pre>
        </div>
        <?php endif; ?>
        
        <!-- הוראות לפי ספק -->
        <div class="status-box warning" id="gmail-instructions" style="display: none;">
            <h3>📧 הוראות עבור Gmail:</h3>
            <ol>
                <li>הפעל אימות דו-שלבי בחשבון Gmail שלך</li>
                <li>צור App Password חדש: <a href="https://myaccount.google.com/apppasswords" target="_blank">לחץ כאן</a></li>
                <li>בחר "Mail" ו-"Other" (הכנס שם לאפליקציה)</li>
                <li>העתק את הסיסמה בת 16 התווים</li>
                <li>הדבק אותה בשדה "סיסמה" למעלה</li>
            </ol>
        </div>
        
        <div style="margin-top: 30px;">
            <a href="test-notifications.php?token=test123" class="btn">↩️ חזרה לבדיקת התראות</a>
            <a href="test-email-debug.php?token=debug123" class="btn">🔍 בדיקת מייל</a>
        </div>
    </div>
    
    <script>
        function clearActiveButtons() {
            document.querySelectorAll('.preset-btn').forEach(btn => {
                btn.classList.remove('active');
            });
        }
        
        function setGmailSettings() {
            clearActiveButtons();
            document.getElementById('gmail-btn').classList.add('active');
            document.getElementById('smtp_host').value = 'smtp.gmail.com';
            document.getElementById('smtp_port').value = '587';
            document.getElementById('smtp_secure').value = 'tls';
            document.getElementById('smtp_user').placeholder = 'your-email@gmail.com';
            
            // הצג הוראות Gmail
            document.getElementById('gmail-instructions').style.display = 'block';
        }
        
        function setOutlookSettings() {
            clearActiveButtons();
            document.getElementById('outlook-btn').classList.add('active');
            document.getElementById('smtp_host').value = 'smtp-mail.outlook.com';
            document.getElementById('smtp_port').value = '587';
            document.getElementById('smtp_secure').value = 'tls';
            document.getElementById('smtp_user').placeholder = 'your-email@outlook.com';
            document.getElementById('gmail-instructions').style.display = 'none';
        }
        
        function setYahooSettings() {
            clearActiveButtons();
            document.getElementById('yahoo-btn').classList.add('active');
            document.getElementById('smtp_host').value = 'smtp.mail.yahoo.com';
            document.getElementById('smtp_port').value = '587';
            document.getElementById('smtp_secure').value = 'tls';
            document.getElementById('smtp_user').placeholder = 'your-email@yahoo.com';
            document.getElementById('gmail-instructions').style.display = 'none';
        }
        
        function setOffice365Settings() {
            clearActiveButtons();
            document.getElementById('office365-btn').classList.add('active');
            document.getElementById('smtp_host').value = 'smtp.office365.com';
            document.getElementById('smtp_port').value = '587';
            document.getElementById('smtp_secure').value = 'tls';
            document.getElementById('smtp_user').placeholder = 'your-email@yourdomain.com';
            document.getElementById('gmail-instructions').style.display = 'none';
        }
        
        // אם יש Gmail בשדה, הצג הוראות
        window.onload = function() {
            const user = document.getElementById('smtp_user');
            if (user.value.includes('gmail') || user.placeholder.includes('gmail')) {
                setGmailSettings();
            }
        }
    </script>
</body>
</html>