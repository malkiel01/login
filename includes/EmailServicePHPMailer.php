<?php
// includes/EmailServicePHPMailer.php - שירות מייל עם PHPMailer

// טען PHPMailer אם קיים
$vendorPath = dirname(dirname(__DIR__)) . '/vendor/autoload.php';
if (file_exists($vendorPath)) {
    require_once $vendorPath;
}

class EmailService {
    private $pdo;
    private $fromEmail;
    private $fromName;
    private $baseUrl;
    private $usePhpMailer = false;
    private $smtpSettings = [];
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
        $this->fromEmail = $_ENV['MAIL_FROM'] ?? 'noreply@panan-bakan.com';
        $this->fromName = $_ENV['MAIL_FROM_NAME'] ?? 'מערכת ניהול קניות';
        $this->baseUrl = $_ENV['BASE_URL'] ?? 'https://form.mbe-plus.com/family';
        
        // בדוק אם PHPMailer זמין
        if (class_exists('PHPMailer\\PHPMailer\\PHPMailer')) {
            $this->usePhpMailer = true;
            
            // טען הגדרות SMTP מ-ENV
            $this->smtpSettings = [
                'host' => $_ENV['SMTP_HOST'] ?? '',
                'port' => $_ENV['SMTP_PORT'] ?? 587,
                'secure' => $_ENV['SMTP_SECURE'] ?? 'tls',
                'user' => $_ENV['SMTP_USER'] ?? '',
                'pass' => $_ENV['SMTP_PASS'] ?? '',
                'auth' => !empty($_ENV['SMTP_USER'])
            ];
        }
    }
    
    /**
     * שליחת אימייל - עם PHPMailer או mail()
     */
    public function sendEmail($to, $subject, $htmlBody, $textBody = null) {
        try {
            // אם אין גוף טקסט, צור אותו מה-HTML
            if (!$textBody) {
                $textBody = strip_tags($htmlBody);
            }
            
            // נסה עם PHPMailer אם זמין והגדרות קיימות
            if ($this->usePhpMailer && !empty($this->smtpSettings['host'])) {
                $result = $this->sendWithPhpMailer($to, $subject, $htmlBody, $textBody);
            } else {
                // חזור ל-mail() הרגיל
                $result = $this->sendWithMail($to, $subject, $htmlBody);
            }
            
            // רשום בלוג
            $this->logEmail($to, $subject, $result);
            
            return $result;
            
        } catch (Exception $e) {
            error_log("Email sending error: " . $e->getMessage());
            $this->logEmail($to, $subject, false, $e->getMessage());
            return false;
        }
    }
    
    /**
     * שליחה עם PHPMailer
     */
    private function sendWithPhpMailer($to, $subject, $htmlBody, $textBody) {
        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
        
        try {
            // הגדרות שרת
            $mail->isSMTP();
            $mail->Host       = $this->smtpSettings['host'];
            $mail->SMTPAuth   = $this->smtpSettings['auth'];
            $mail->Username   = $this->smtpSettings['user'];
            $mail->Password   = $this->smtpSettings['pass'];
            $mail->SMTPSecure = $this->smtpSettings['secure'];
            $mail->Port       = $this->smtpSettings['port'];
            $mail->CharSet    = 'UTF-8';
            
            // נמענים
            $mail->setFrom($this->fromEmail, $this->fromName);
            $mail->addAddress($to);
            $mail->addReplyTo($this->fromEmail, $this->fromName);
            
            // תוכן
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body    = $htmlBody;
            $mail->AltBody = $textBody;
            
            $mail->send();
            return true;
            
        } catch (Exception $e) {
            error_log("PHPMailer Error: " . $mail->ErrorInfo);
            // נסה עם mail() רגיל כ-fallback
            return $this->sendWithMail($to, $subject, $htmlBody);
        }
    }
    
    /**
     * שליחה עם mail() רגיל
     */
    private function sendWithMail($to, $subject, $htmlBody) {
        $headers = "From: {$this->fromName} <{$this->fromEmail}>\r\n";
        $headers .= "Reply-To: {$this->fromEmail}\r\n";
        $headers .= "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
        $headers .= "X-Mailer: PHP/" . phpversion();
        
        return mail($to, $subject, $htmlBody, $headers);
    }
    
    /**
     * שליחת הזמנה לקבוצה
     */
    public function sendGroupInvitation($invitationId) {
        try {
            // קבל פרטי הזמנה
            $stmt = $this->pdo->prepare("
                SELECT gi.*, pg.name as group_name, pg.description, 
                       u.name as inviter_name
                FROM group_invitations gi
                JOIN purchase_groups pg ON gi.group_id = pg.id
                JOIN users u ON gi.invited_by = u.id
                WHERE gi.id = ?
            ");
            $stmt->execute([$invitationId]);
            $invitation = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$invitation) {
                throw new Exception("Invitation not found: $invitationId");
            }
            
            // בנה את ה-URL לאישור
            $acceptUrl = "{$this->baseUrl}/accept-invitation.php?token=" . $invitation['token'];
            
            $subject = "הזמנה לקבוצת רכישה - {$invitation['group_name']}";
            
            $htmlBody = $this->getEmailTemplate('invitation', [
                'inviter_name' => $invitation['inviter_name'],
                'group_name' => $invitation['group_name'],
                'group_description' => $invitation['description'],
                'nickname' => $invitation['nickname'],
                'participation_type' => $invitation['participation_type'],
                'participation_value' => $invitation['participation_value'],
                'accept_url' => $acceptUrl,
                'dashboard_url' => "{$this->baseUrl}/dashboard.php"
            ]);
            
            return $this->sendEmail(
                $invitation['email'],
                $subject,
                $htmlBody
            );
            
        } catch (Exception $e) {
            error_log("SendGroupInvitation error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * שליחת התראה על קנייה חדשה
     */
    public function sendNewPurchaseNotification($purchaseId) {
        try {
            // קבל פרטי קנייה
            $stmt = $this->pdo->prepare("
                SELECT gp.*, pg.name as group_name, 
                       gm.nickname, u.name as purchaser_name
                FROM group_purchases gp
                JOIN purchase_groups pg ON gp.group_id = pg.id
                JOIN group_members gm ON gp.member_id = gm.id
                JOIN users u ON gp.user_id = u.id
                WHERE gp.id = ?
            ");
            $stmt->execute([$purchaseId]);
            $purchase = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$purchase) {
                throw new Exception("Purchase not found: $purchaseId");
            }
            
            // קבל את כל חברי הקבוצה
            $stmt = $this->pdo->prepare("
                SELECT DISTINCT u.email, u.name
                FROM group_members gm
                JOIN users u ON gm.user_id = u.id
                WHERE gm.group_id = ? 
                AND gm.is_active = 1 
                AND gm.user_id != ?
            ");
            $stmt->execute([$purchase['group_id'], $purchase['user_id']]);
            $members = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $subject = "קנייה חדשה בקבוצה - {$purchase['group_name']}";
            
            $htmlBody = $this->getEmailTemplate('new_purchase', [
                'purchaser_name' => $purchase['nickname'],
                'amount' => number_format($purchase['amount'], 2),
                'description' => $purchase['description'],
                'group_name' => $purchase['group_name'],
                'group_url' => "{$this->baseUrl}/group.php?id={$purchase['group_id']}#purchases"
            ]);
            
            // שלח לכל החברים
            $results = [];
            foreach ($members as $member) {
                $results[] = $this->sendEmail(
                    $member['email'],
                    $subject,
                    $htmlBody
                );
            }
            
            return in_array(true, $results); // החזר true אם לפחות אחד הצליח
            
        } catch (Exception $e) {
            error_log("SendNewPurchaseNotification error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * קבלת תבנית אימייל
     */
    private function getEmailTemplate($type, $data = []) {
        $templates = [
            'invitation' => '
                <!DOCTYPE html>
                <html dir="rtl">
                <head>
                    <meta charset="UTF-8">
                    <style>
                        body { font-family: Arial, sans-serif; background: #f5f5f5; margin: 0; padding: 20px; }
                        .container { max-width: 600px; margin: 0 auto; background: white; border-radius: 10px; overflow: hidden; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
                        .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; text-align: center; }
                        .header h1 { margin: 0; font-size: 24px; }
                        .content { padding: 30px; }
                        .info-box { background: #f8f9fa; padding: 20px; border-radius: 8px; margin: 20px 0; }
                        .button { display: inline-block; background: #667eea; color: white; padding: 12px 30px; border-radius: 25px; text-decoration: none; margin: 20px 0; }
                        .footer { background: #f8f9fa; padding: 20px; text-align: center; color: #666; font-size: 14px; }
                    </style>
                </head>
                <body>
                    <div class="container">
                        <div class="header">
                            <h1>🎉 הזמנה לקבוצת רכישה</h1>
                        </div>
                        <div class="content">
                            <h2>שלום {nickname}!</h2>
                            <p><strong>{inviter_name}</strong> הזמין אותך להצטרף לקבוצת הרכישה:</p>
                            <h3 style="color: #667eea;">{group_name}</h3>
                            <p>{group_description}</p>
                            
                            <div class="info-box">
                                <h4>פרטי ההשתתפות שלך:</h4>
                                <p>כינוי: <strong>{nickname}</strong></p>
                                <p>סוג השתתפות: <strong>{participation_type}</strong></p>
                                <p>ערך: <strong>{participation_value}</strong></p>
                            </div>
                            
                            <center>
                                <a href="{accept_url}" class="button">
                                    אשר הזמנה
                                </a>
                            </center>
                            
                            <p style="text-align: center; color: #666; font-size: 14px;">
                                או היכנס למערכת ואשר מהדשבורד:<br>
                                <a href="{dashboard_url}">{dashboard_url}</a>
                            </p>
                        </div>
                        <div class="footer">
                            <p>הודעה זו נשלחה ממערכת ניהול קניות מרוכזות</p>
                            <p>זמן שליחה: ' . date('d/m/Y H:i:s') . '</p>
                        </div>
                    </div>
                </body>
                </html>',
                
            'new_purchase' => '
                <!DOCTYPE html>
                <html dir="rtl">
                <head>
                    <meta charset="UTF-8">
                    <style>
                        body { font-family: Arial, sans-serif; background: #f5f5f5; margin: 0; padding: 20px; }
                        .container { max-width: 600px; margin: 0 auto; background: white; border-radius: 10px; overflow: hidden; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
                        .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; text-align: center; }
                        .content { padding: 30px; }
                        .purchase-box { background: #f8f9fa; padding: 20px; border-radius: 8px; margin: 20px 0; }
                        .amount { font-size: 36px; color: #667eea; font-weight: bold; }
                        .button { display: inline-block; background: #667eea; color: white; padding: 12px 30px; border-radius: 25px; text-decoration: none; margin: 20px 0; }
                        .footer { background: #f8f9fa; padding: 20px; text-align: center; color: #666; font-size: 14px; }
                    </style>
                </head>
                <body>
                    <div class="container">
                        <div class="header">
                            <h1>🛒 קנייה חדשה בקבוצה</h1>
                        </div>
                        <div class="content">
                            <h2>התווספה קנייה חדשה!</h2>
                            <p>קבוצה: <strong>{group_name}</strong></p>
                            
                            <div class="purchase-box">
                                <p>משתתף: <strong>{purchaser_name}</strong></p>
                                <p>סכום:</p>
                                <div class="amount">₪{amount}</div>
                                <p>תיאור: {description}</p>
                            </div>
                            
                            <center>
                                <a href="{group_url}" class="button">
                                    צפה בפרטים
                                </a>
                            </center>
                        </div>
                        <div class="footer">
                            <p>הודעה זו נשלחה ממערכת ניהול קניות מרוכזות</p>
                        </div>
                    </div>
                </body>
                </html>'
        ];
        
        $template = $templates[$type] ?? '';
        
        // החלף משתנים בתבנית
        foreach ($data as $key => $value) {
            $template = str_replace('{' . $key . '}', $value, $template);
        }
        
        return $template;
    }
    
    /**
     * רישום בלוג
     */
    private function logEmail($to, $subject, $success, $error = null) {
        try {
            // בדוק אם הטבלה קיימת
            $stmt = $this->pdo->query("SHOW TABLES LIKE 'email_log'");
            if (!$stmt->fetch()) {
                // אם הטבלה לא קיימת, אל תנסה לרשום
                return;
            }
            
            $stmt = $this->pdo->prepare("
                INSERT INTO email_log (to_email, subject, status, sent_at)
                VALUES (?, ?, ?, NOW())
            ");
            $stmt->execute([$to, $subject, $success ? 'sent' : 'failed']);
        } catch (Exception $e) {
            // אל תעצור את התהליך בגלל שגיאת לוג
            error_log("Failed to log email: " . $e->getMessage());
        }
    }
}
?>