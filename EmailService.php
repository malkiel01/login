<?php
// includes/EmailService.php - מערכת שליחת אימיילים מתקדמת

class EmailService {
    private $pdo;
    private $fromEmail;
    private $fromName;
    private $replyTo;
    private $smtpConfig;
    
    public function __construct($pdo = null) {
        $this->pdo = $pdo ?: getDBConnection();
        
        // הגדרות מה-ENV
        $this->fromEmail = $_ENV['MAIL_FROM'] ?? 'noreply@panan-bakan.com';
        $this->fromName = $_ENV['MAIL_FROM_NAME'] ?? 'מערכת ניהול קניות';
        $this->replyTo = $_ENV['MAIL_REPLY_TO'] ?? $this->fromEmail;
        
        // הגדרות SMTP (אם משתמשים)
        $this->smtpConfig = [
            'host' => $_ENV['SMTP_HOST'] ?? null,
            'port' => $_ENV['SMTP_PORT'] ?? 587,
            'username' => $_ENV['SMTP_USERNAME'] ?? null,
            'password' => $_ENV['SMTP_PASSWORD'] ?? null,
            'encryption' => $_ENV['SMTP_ENCRYPTION'] ?? 'tls'
        ];
    }
    
    /**
     * שליחת אימייל הזמנה לקבוצה
     */
    public function sendGroupInvitation($invitationId) {
        try {
            // קבל פרטי ההזמנה
            $stmt = $this->pdo->prepare("
                SELECT 
                    gi.*,
                    pg.name as group_name,
                    pg.description as group_description,
                    u.name as inviter_name,
                    u.email as inviter_email
                FROM group_invitations gi
                JOIN purchase_groups pg ON gi.group_id = pg.id
                JOIN users u ON gi.invited_by = u.id
                WHERE gi.id = ?
            ");
            $stmt->execute([$invitationId]);
            $invitation = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$invitation) {
                throw new Exception('Invitation not found');
            }
            
            // הכן את הנתונים לתבנית
            $templateData = [
                'user_name' => $invitation['nickname'],
                'inviter_name' => $invitation['inviter_name'],
                'group_name' => $invitation['group_name'],
                'group_description' => $invitation['group_description'],
                'participation_value' => $invitation['participation_value'],
                'participation_unit' => $invitation['participation_type'] == 'percentage' ? '%' : '₪',
                'date' => date('d/m/Y'),
                'email' => $invitation['email'],
                'accept_url' => $this->getBaseUrl() . '/dashboard.php?action=accept&token=' . $invitation['token'],
                'reject_url' => $this->getBaseUrl() . '/dashboard.php?action=reject&token=' . $invitation['token'],
                'unsubscribe_url' => $this->getBaseUrl() . '/unsubscribe.php?email=' . urlencode($invitation['email']),
                'settings_url' => $this->getBaseUrl() . '/settings.php#notifications',
                'help_url' => $this->getBaseUrl() . '/help.php'
            ];
            
            // טען את התבנית
            $template = $this->loadEmailTemplate('group_invitation', $templateData);
            
            // שלח את האימייל
            $subject = "הוזמנת לקבוצת רכישה - {$invitation['group_name']}";
            $sent = $this->sendEmail(
                $invitation['email'],
                $subject,
                $template['html'],
                $template['text']
            );
            
            // רשום בלוג
            $this->logEmail(
                'group_invitation',
                $invitation['email'],
                $subject,
                $sent ? 'sent' : 'failed'
            );
            
            return $sent;
            
        } catch (Exception $e) {
            error_log('Email sending error: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * שליחת אימייל על קנייה חדשה
     */
    public function sendNewPurchaseNotification($purchaseId) {
        try {
            // קבל פרטי הקנייה
            $stmt = $this->pdo->prepare("
                SELECT 
                    gp.*,
                    pg.name as group_name,
                    gm.nickname as member_name,
                    u.name as purchaser_name
                FROM group_purchases gp
                JOIN purchase_groups pg ON gp.group_id = pg.id
                JOIN group_members gm ON gp.member_id = gm.id
                JOIN users u ON gp.user_id = u.id
                WHERE gp.id = ?
            ");
            $stmt->execute([$purchaseId]);
            $purchase = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$purchase) {
                throw new Exception('Purchase not found');
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
            
            $results = [];
            foreach ($members as $member) {
                // בדוק העדפות התראות
                if (!$this->shouldSendEmail($member['email'], 'new_purchase')) {
                    continue;
                }
                
                $templateData = [
                    'user_name' => $member['name'],
                    'member_name' => $purchase['member_name'],
                    'group_name' => $purchase['group_name'],
                    'amount' => number_format($purchase['amount'], 2),
                    'description' => $purchase['description'] ?: 'ללא תיאור',
                    'date' => date('d/m/Y', strtotime($purchase['purchase_date'])),
                    'action_url' => $this->getBaseUrl() . '/group.php?id=' . $purchase['group_id'] . '#purchases'
                ];
                
                $template = $this->loadEmailTemplate('new_purchase', $templateData);
                $subject = "קנייה חדשה בקבוצה {$purchase['group_name']}";
                
                $sent = $this->sendEmail(
                    $member['email'],
                    $subject,
                    $template['html'],
                    $template['text']
                );
                
                $results[] = $sent;
            }
            
            return $results;
            
        } catch (Exception $e) {
            error_log('Email notification error: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * שליחת סיכום שבועי
     */
    public function sendWeeklySummary($userId) {
        try {
            // קבל פרטי משתמש
            $stmt = $this->pdo->prepare("SELECT * FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$user) {
                return false;
            }
            
            // קבל סיכום פעילות שבועית
            $summary = $this->getWeeklySummary($userId);
            
            if (empty($summary['groups'])) {
                return false; // אין פעילות לדווח עליה
            }
            
            // בנה תוכן הסיכום
            $summaryHtml = $this->buildWeeklySummaryContent($summary);
            
            $templateData = [
                'user_name' => $user['name'],
                'summary_content' => $summaryHtml,
                'action_url' => $this->getBaseUrl() . '/dashboard.php'
            ];
            
            $template = $this->loadEmailTemplate('weekly_summary', $templateData);
            $subject = "הסיכום השבועי שלך - " . date('d/m/Y');
            
            return $this->sendEmail(
                $user['email'],
                $subject,
                $template['html'],
                $template['text']
            );
            
        } catch (Exception $e) {
            error_log('Weekly summary error: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * שליחת אימייל גנרי
     */
    public function sendEmail($to, $subject, $htmlBody, $textBody = null) {
        // אם יש הגדרות SMTP, השתמש ב-PHPMailer
        if ($this->smtpConfig['host']) {
            return $this->sendSmtpEmail($to, $subject, $htmlBody, $textBody);
        }
        
        // אחרת, השתמש ב-mail() הרגיל
        return $this->sendNativeEmail($to, $subject, $htmlBody, $textBody);
    }
    
    /**
     * שליחה עם mail() רגיל
     */
    private function sendNativeEmail($to, $subject, $htmlBody, $textBody = null) {
        // הכן headers
        $headers = [
            'From: ' . $this->fromName . ' <' . $this->fromEmail . '>',
            'Reply-To: ' . $this->replyTo,
            'X-Mailer: PHP/' . phpversion(),
            'MIME-Version: 1.0'
        ];
        
        // אם יש גם טקסט וגם HTML, צור multipart
        if ($textBody) {
            $boundary = md5(time());
            $headers[] = 'Content-Type: multipart/alternative; boundary="' . $boundary . '"';
            
            $body = "--$boundary\r\n";
            $body .= "Content-Type: text/plain; charset=UTF-8\r\n";
            $body .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
            $body .= $textBody . "\r\n\r\n";
            
            $body .= "--$boundary\r\n";
            $body .= "Content-Type: text/html; charset=UTF-8\r\n";
            $body .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
            $body .= $htmlBody . "\r\n\r\n";
            
            $body .= "--$boundary--";
        } else {
            $headers[] = 'Content-Type: text/html; charset=UTF-8';
            $body = $htmlBody;
        }
        
        // שלח
        return mail($to, $subject, $body, implode("\r\n", $headers));
    }
    
    /**
     * שליחה עם SMTP (דורש PHPMailer)
     */
    private function sendSmtpEmail($to, $subject, $htmlBody, $textBody = null) {
        // כאן תוכל להוסיף PHPMailer אם נדרש
        // return $this->sendNativeEmail($to, $subject, $htmlBody, $textBody);
        
        // דוגמה עם PHPMailer (אם מותקן):
        /*
        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
        
        try {
            $mail->isSMTP();
            $mail->Host = $this->smtpConfig['host'];
            $mail->SMTPAuth = true;
            $mail->Username = $this->smtpConfig['username'];
            $mail->Password = $this->smtpConfig['password'];
            $mail->SMTPSecure = $this->smtpConfig['encryption'];
            $mail->Port = $this->smtpConfig['port'];
            
            $mail->setFrom($this->fromEmail, $this->fromName);
            $mail->addAddress($to);
            $mail->addReplyTo($this->replyTo);
            
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body = $htmlBody;
            $mail->AltBody = $textBody ?: strip_tags($htmlBody);
            
            return $mail->send();
        } catch (Exception $e) {
            error_log('SMTP Error: ' . $e->getMessage());
            return false;
        }
        */
        
        return $this->sendNativeEmail($to, $subject, $htmlBody, $textBody);
    }
    
    /**
     * טעינת תבנית אימייל
     */
    private function loadEmailTemplate($templateName, $data = []) {
        // נסה לטעון תבנית מהמסד נתונים
        $stmt = $this->pdo->prepare("
            SELECT * FROM notification_templates 
            WHERE type = ? AND channel = 'email' AND is_active = 1
            LIMIT 1
        ");
        $stmt->execute([$templateName]);
        $template = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($template) {
            $html = $this->parseTemplate($template['body_template'], $data);
            $text = strip_tags($html);
        } else {
            // תבנית ברירת מחדל
            $html = $this->getDefaultTemplate($templateName, $data);
            $text = strip_tags($html);
        }
        
        return [
            'html' => $html,
            'text' => $text
        ];
    }
    
    /**
     * החלפת placeholders בתבנית
     */
    private function parseTemplate($template, $data) {
        foreach ($data as $key => $value) {
            $template = str_replace('{{' . $key . '}}', $value, $template);
        }
        return $template;
    }
    
    /**
     * תבנית ברירת מחדל
     */
    private function getDefaultTemplate($type, $data) {
        // קרא את קובץ התבנית הבסיסית
        $templateFile = __DIR__ . '/../templates/email_template.html';
        if (file_exists($templateFile)) {
            $template = file_get_contents($templateFile);
            return $this->parseTemplate($template, $data);
        }
        
        // תבנית מינימלית
        $userName = isset($data['user_name']) ? $data['user_name'] : 'משתמש יקר';
        $message = isset($data['message']) ? $data['message'] : 'התראה חדשה ממערכת ניהול הקניות';
        $actionUrl = isset($data['action_url']) ? $data['action_url'] : '#';
        
        return "
        <html dir='rtl'>
        <body style='font-family: Arial, sans-serif; direction: rtl;'>
            <h2>שלום {$userName},</h2>
            <p>{$message}</p>
            <a href='{$actionUrl}'>לחץ כאן להמשך</a>
        </body>
        </html>";
    }
    
    /**
     * בדיקת העדפות משתמש
     */
    private function shouldSendEmail($email, $notificationType) {
        $stmt = $this->pdo->prepare("
            SELECT is_enabled 
            FROM user_notification_preferences unp
            JOIN users u ON unp.user_id = u.id
            WHERE u.email = ? 
            AND unp.notification_type = ? 
            AND unp.channel = 'email'
        ");
        $stmt->execute([$email, $notificationType]);
        $pref = $stmt->fetch();
        
        return !$pref || $pref['is_enabled'];
    }
    
    /**
     * רישום בלוג
     */
    private function logEmail($type, $to, $subject, $status) {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO notification_log 
                (type, channel, title, body, status, sent_at)
                VALUES (?, 'email', ?, ?, ?, NOW())
            ");
            $stmt->execute([$type, $subject, $to, $status]);
        } catch (Exception $e) {
            error_log('Failed to log email: ' . $e->getMessage());
        }
    }
    
    /**
     * קבלת URL בסיס
     */
    private function getBaseUrl() {
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        return $protocol . '://' . $host . '/family';
    }
    
    /**
     * קבלת סיכום שבועי
     */
    private function getWeeklySummary($userId) {
        $summary = [
            'groups' => [],
            'total_purchases' => 0,
            'total_amount' => 0,
            'balance' => 0
        ];
        
        // קבל פעילות מהשבוע האחרון
        $stmt = $this->pdo->prepare("
            SELECT 
                pg.id,
                pg.name,
                COUNT(DISTINCT gp.id) as purchase_count,
                SUM(gp.amount) as total_amount
            FROM purchase_groups pg
            JOIN group_members gm ON pg.id = gm.group_id
            LEFT JOIN group_purchases gp ON pg.id = gp.group_id 
                AND gp.created_at > DATE_SUB(NOW(), INTERVAL 7 DAY)
            WHERE gm.user_id = ? AND gm.is_active = 1
            GROUP BY pg.id
        ");
        $stmt->execute([$userId]);
        
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $summary['groups'][] = $row;
            $summary['total_purchases'] += $row['purchase_count'];
            $summary['total_amount'] += $row['total_amount'];
        }
        
        return $summary;
    }
    
    /**
     * בניית תוכן סיכום שבועי
     */
    private function buildWeeklySummaryContent($summary) {
        $html = '<table width="100%">';
        
        foreach ($summary['groups'] as $group) {
            $html .= '
            <tr>
                <td style="padding: 10px; background: #f8f9fa; border-radius: 5px; margin-bottom: 10px;">
                    <strong>' . htmlspecialchars($group['name']) . '</strong><br>
                    קניות: ' . $group['purchase_count'] . ' | 
                    סכום: ₪' . number_format($group['total_amount'], 2) . '
                </td>
            </tr>';
        }
        
        $html .= '</table>';
        
        return $html;
    }
}

// דוגמה לשימוש:
/*
$emailService = new EmailService();

// שליחת הזמנה
$emailService->sendGroupInvitation($invitationId);

// שליחת התראה על קנייה
$emailService->sendNewPurchaseNotification($purchaseId);

// שליחת סיכום שבועי
$emailService->sendWeeklySummary($userId);
*/
?>