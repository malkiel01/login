<?php
// includes/EmailService.php - שירות שליחת אימיילים

class EmailService {
    private $pdo;
    private $fromEmail;
    private $fromName;
    private $baseUrl;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
        $this->fromEmail = $_ENV['MAIL_FROM'] ?? 'noreply@panan-bakan.com';
        $this->fromName = $_ENV['MAIL_FROM_NAME'] ?? 'מערכת ניהול קניות';
        $this->baseUrl = $_ENV['BASE_URL'] ?? 'https://form.mbe-plus.com/family';
    }
    
    /**
     * שליחת אימייל בסיסית
     */
    public function sendEmail($to, $subject, $htmlBody, $textBody = null) {
        try {
            // אם אין גוף טקסט, צור אותו מה-HTML
            if (!$textBody) {
                $textBody = strip_tags($htmlBody);
            }
            
            // הגדרת headers
            $headers = "From: {$this->fromName} <{$this->fromEmail}>\r\n";
            $headers .= "Reply-To: {$this->fromEmail}\r\n";
            $headers .= "MIME-Version: 1.0\r\n";
            $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
            $headers .= "X-Mailer: PHP/" . phpversion();
            
            // שלח את האימייל
            $result = mail($to, $subject, $htmlBody, $headers);
            
            // רשום בלוג
            $this->logEmail($to, $subject, $result);
            
            return $result;
            
        } catch (Exception $e) {
            error_log("Email sending error: " . $e->getMessage());
            return false;
        }
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
     * שליחת התראה על תגובה להזמנה
     */
    public function sendInvitationResponseNotification($invitationId, $accepted) {
        try {
            // קבל פרטי הזמנה
            $stmt = $this->pdo->prepare("
                SELECT gi.*, pg.name as group_name, pg.owner_id,
                       u.name as user_name, u2.email as owner_email
                FROM group_invitations gi
                JOIN purchase_groups pg ON gi.group_id = pg.id
                LEFT JOIN users u ON u.email = gi.email
                JOIN users u2 ON u2.id = pg.owner_id
                WHERE gi.id = ?
            ");
            $stmt->execute([$invitationId]);
            $invitation = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$invitation) {
                throw new Exception("Invitation not found: $invitationId");
            }
            
            $subject = $accepted ? 
                "משתתף חדש הצטרף לקבוצה - {$invitation['group_name']}" :
                "הזמנה נדחתה - {$invitation['group_name']}";
            
            $htmlBody = $this->getEmailTemplate('invitation_response', [
                'user_name' => $invitation['user_name'] ?? $invitation['nickname'],
                'action' => $accepted ? 'אישר' : 'דחה',
                'group_name' => $invitation['group_name'],
                'group_url' => "{$this->baseUrl}/group.php?id={$invitation['group_id']}"
            ]);
            
            return $this->sendEmail(
                $invitation['owner_email'],
                $subject,
                $htmlBody
            );
            
        } catch (Exception $e) {
            error_log("SendInvitationResponseNotification error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * שליחת סיכום חישובים
     */
    public function sendCalculationSummary($groupId, $userId) {
        try {
            // כאן תהיה לוגיקה לחישוב ושליחת סיכום
            // ...
            return true;
        } catch (Exception $e) {
            error_log("SendCalculationSummary error: " . $e->getMessage());
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
                </html>',
                
            'invitation_response' => '
                <!DOCTYPE html>
                <html dir="rtl">
                <head>
                    <meta charset="UTF-8">
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
                    <div class="container">
                        <div class="header">
                            <h1>📢 עדכון הזמנה</h1>
                        </div>
                        <div class="content">
                            <h2>{user_name} {action} את ההזמנה</h2>
                            <p>קבוצה: <strong>{group_name}</strong></p>
                            
                            <center>
                                <a href="{group_url}" class="button">
                                    כניסה לקבוצה
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
    private function logEmail($to, $subject, $success) {
        try {
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