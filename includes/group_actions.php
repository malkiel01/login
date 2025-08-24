<?php
// includes/group_actions.php
// טיפול בכל פעולות ה-AJAX של הקבוצה

function handleGroupActions($pdo, $group_id, $user_id, $is_owner) {
    // וודא שה-header נשלח
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=utf-8');
    }
    
    $action = $_POST['action'] ?? '';
    
    try {
        switch ($action) {
            case 'addMember':
                addMember($pdo, $group_id, $user_id, $is_owner);
                break;
                
            case 'removeMember':
                removeMember($pdo, $group_id, $is_owner);
                break;
                
            case 'editMember':
                editMember($pdo, $group_id, $is_owner);
                break;
                
            case 'cancelInvitation':
                cancelInvitation($pdo, $group_id, $is_owner);
                break;
                
            case 'addPurchase':
                addPurchase($pdo, $group_id, $user_id);
                break;
                
            case 'deletePurchase':
                deletePurchase($pdo, $group_id, $user_id, $is_owner);
                break;
                
            default:
                echo json_encode(['success' => false, 'message' => 'פעולה לא מוכרת']);
        }
    } catch (Exception $e) {
        error_log("Group action error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'שגיאה: ' . $e->getMessage()]);
    }
}

function addMember2($pdo, $group_id, $user_id, $is_owner) {
    if (!$is_owner) {
        echo json_encode(['success' => false, 'message' => 'אין הרשאה']);
        return;
    }
    
    $email = trim($_POST['email'] ?? '');
    $nickname = trim($_POST['nickname'] ?? '');
    $participation_type = $_POST['participation_type'] ?? 'percentage';
    $participation_value = floatval($_POST['participation_value'] ?? 0);
    
    // בדיקת תקינות
    if (empty($email) || empty($nickname)) {
        echo json_encode(['success' => false, 'message' => 'יש למלא את כל השדות']);
        return;
    }
    
    // בדיקת אחוזים קיימים
    if ($participation_type == 'percentage') {
        $stmt = $pdo->prepare("
            SELECT SUM(participation_value) as total_percentage 
            FROM group_members 
            WHERE group_id = ? AND participation_type = 'percentage' AND is_active = 1
        ");
        $stmt->execute([$group_id]);
        $currentPercentage = $stmt->fetch()['total_percentage'] ?? 0;
        
        if ($currentPercentage + $participation_value > 100) {
            $available = 100 - $currentPercentage;
            echo json_encode([
                'success' => false, 
                'message' => "סכום האחוזים חורג מ-100%. נותרו $available% זמינים"
            ]);
            return;
        }
    }
    
    // בדיקה אם כבר קיימת הזמנה ממתינה
    $stmt = $pdo->prepare("
        SELECT id FROM group_invitations 
        WHERE group_id = ? AND email = ? AND status = 'pending'
    ");
    $stmt->execute([$group_id, $email]);
    if ($stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'כבר קיימת הזמנה ממתינה למשתמש זה']);
        return;
    }

    // בדיקה אם המשתמש קיים במערכת
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    
    if ($user) {
        // בדיקה אם כבר חבר בקבוצה
        $stmt = $pdo->prepare("
            SELECT id, is_active FROM group_members 
            WHERE group_id = ? AND user_id = ?
        ");
        $stmt->execute([$group_id, $user['id']]);
        $existingMember = $stmt->fetch();
        
        if ($existingMember) {
            if ($existingMember['is_active']) {
                echo json_encode(['success' => false, 'message' => 'המשתמש כבר חבר פעיל בקבוצה']);
                return;
            } else {
                // אם המשתמש היה חבר בעבר ועזב, נשלח לו הזמנה חדשה
                // במקום להפעיל אותו אוטומטית
                $token = bin2hex(random_bytes(32));
                $stmt = $pdo->prepare("
                    INSERT INTO group_invitations (group_id, email, nickname, participation_type, participation_value, token, invited_by) 
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE 
                        nickname = VALUES(nickname),
                        participation_type = VALUES(participation_type),
                        participation_value = VALUES(participation_value),
                        token = VALUES(token),
                        status = 'pending',
                        created_at = NOW()
                ");
                $result = $stmt->execute([
                    $group_id,
                    $email,
                    $nickname,
                    $participation_type,
                    $participation_value,
                    $token,
                    $user_id
                ]);
                echo json_encode([
                    'success' => $result, 
                    'invitation_sent' => true,
                    'message' => 'הזמנה נשלחה למשתמש'
                ]);
                return;
            }
        }
    }
    
    // תמיד שלח הזמנה - בין אם המשתמש קיים או לא
    $token = bin2hex(random_bytes(32));
    $stmt = $pdo->prepare("
        INSERT INTO group_invitations (group_id, email, nickname, participation_type, participation_value, token, invited_by) 
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    $result = $stmt->execute([
        $group_id,
        $email,
        $nickname,
        $participation_type,
        $participation_value,
        $token,
        $user_id
    ]);
    
    echo json_encode([
        'success' => $result, 
        'invitation_sent' => true,
        'message' => 'הזמנה נשלחה למשתמש'
    ]);



    if ($result) {
        $invitation_id = $pdo->lastInsertId();
        
        // נסה לשלוח התראת Push
        if ($user) {
            // המשתמש רשום במערכת - שלח לו התראה
            require_once __DIR__ . '/../api/send-push-notification.php';
            
            try {
                $notificationResult = notifyGroupInvitation($invitation_id);
                
                if ($notificationResult && $notificationResult['success']) {
                    error_log("Push notification sent for invitation ID: $invitation_id");
                } else {
                    error_log("Failed to send push notification for invitation ID: $invitation_id");
                }
            } catch (Exception $e) {
                error_log("Error sending notification: " . $e->getMessage());
                // אל תעצור את התהליך אם ההתראה נכשלה
            }
        }
        
        echo json_encode([
            'success' => true, 
            'invitation_sent' => true,
            'notification_sent' => isset($notificationResult) && $notificationResult['success'],
            'message' => 'הזמנה נשלחה למשתמש' . 
                        (isset($notificationResult) && $notificationResult['success'] ? ' והתראה נשלחה!' : '')
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'שגיאה בשליחת ההזמנה']);
    }

    // שלח התראה על הזמנה
    // שלח התראה על הזמנה (אם המשתמש רשום במערכת)
    if ($result && $user) {
        // נסה לשלוח התראה
        try {
            // הכנס את ה-ID של ההזמנה האחרונה
            $invitation_id = $pdo->lastInsertId();
            
            // רשום ביומן לבדיקה
            error_log("New invitation created: ID=$invitation_id for email=$email");
            
            // בעתיד כאן תהיה קריאה לשירות ההתראות
            // $notificationService->notifyGroupInvitation($invitation_id);
        } catch (Exception $e) {
            // אם ההתראה נכשלה, לא נעצור את התהליך
            error_log("Failed to send notification: " . $e->getMessage());
        }
    }
}

function addMember($pdo, $group_id, $user_id, $is_owner) {
    if (!$is_owner) {
        echo json_encode(['success' => false, 'message' => 'אין הרשאה']);
        return;
    }
    
    $email = trim($_POST['email'] ?? '');
    $nickname = trim($_POST['nickname'] ?? '');
    $participation_type = $_POST['participation_type'] ?? 'percentage';
    $participation_value = floatval($_POST['participation_value'] ?? 0);
    
    // בדיקת תקינות
    if (empty($email) || empty($nickname)) {
        echo json_encode(['success' => false, 'message' => 'יש למלא את כל השדות']);
        return;
    }
    
    // בדיקת אחוזים קיימים
    if ($participation_type == 'percentage') {
        $stmt = $pdo->prepare("
            SELECT SUM(participation_value) as total_percentage 
            FROM group_members 
            WHERE group_id = ? AND participation_type = 'percentage' AND is_active = 1
        ");
        $stmt->execute([$group_id]);
        $currentPercentage = $stmt->fetch()['total_percentage'] ?? 0;
        
        if ($currentPercentage + $participation_value > 100) {
            $available = 100 - $currentPercentage;
            echo json_encode([
                'success' => false, 
                'message' => "סכום האחוזים חורג מ-100%. נותרו $available% זמינים"
            ]);
            return;
        }
    }
    
    // בדיקה אם כבר קיימת הזמנה ממתינה
    $stmt = $pdo->prepare("
        SELECT id FROM group_invitations 
        WHERE group_id = ? AND email = ? AND status = 'pending'
    ");
    $stmt->execute([$group_id, $email]);
    if ($stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'כבר קיימת הזמנה ממתינה למשתמש זה']);
        return;
    }

    // בדיקה אם המשתמש קיים במערכת
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    
    if ($user) {
        // בדיקה אם כבר חבר בקבוצה
        $stmt = $pdo->prepare("
            SELECT id, is_active FROM group_members 
            WHERE group_id = ? AND user_id = ?
        ");
        $stmt->execute([$group_id, $user['id']]);
        $existingMember = $stmt->fetch();
        
        if ($existingMember) {
            if ($existingMember['is_active']) {
                echo json_encode(['success' => false, 'message' => 'המשתמש כבר חבר פעיל בקבוצה']);
                return;
            } else {
                // אם המשתמש היה חבר בעבר ועזב, נשלח לו הזמנה חדשה
                $token = bin2hex(random_bytes(32));
                $stmt = $pdo->prepare("
                    INSERT INTO group_invitations (group_id, email, nickname, participation_type, participation_value, token, invited_by) 
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE 
                        nickname = VALUES(nickname),
                        participation_type = VALUES(participation_type),
                        participation_value = VALUES(participation_value),
                        token = VALUES(token),
                        status = 'pending',
                        created_at = NOW()
                ");
                $result = $stmt->execute([
                    $group_id,
                    $email,
                    $nickname,
                    $participation_type,
                    $participation_value,
                    $token,
                    $user_id
                ]);
                echo json_encode([
                    'success' => $result, 
                    'invitation_sent' => true,
                    'message' => 'הזמנה נשלחה למשתמש'
                ]);
                return;
            }
        }
    }
    
    // תמיד שלח הזמנה - בין אם המשתמש קיים או לא
    $token = bin2hex(random_bytes(32));
    $stmt = $pdo->prepare("
        INSERT INTO group_invitations (group_id, email, nickname, participation_type, participation_value, token, invited_by) 
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    $result = $stmt->execute([
        $group_id,
        $email,
        $nickname,
        $participation_type,
        $participation_value,
        $token,
        $user_id
    ]);
    
    // === כאן מוסיפים את ההתראות בצורה בטוחה ===
    $notificationSent = false;
    
    // רק אם ההזמנה נשמרה והמשתמש קיים
    if ($result && $user) {
        try {
            $invitation_id = $pdo->lastInsertId();
            
            // בדוק אם קובץ ההתראות קיים
            $notificationFile = dirname(__DIR__) . '/api/send-push-notification.php';
            if (file_exists($notificationFile)) {
                // השתמש ב-@ כדי לדכא שגיאות
                @include_once $notificationFile;
                
                // בדוק אם הפונקציה קיימת
                if (function_exists('notifyGroupInvitation')) {
                    // נסה לקרוא לפונקציה
                    $notificationResult = @notifyGroupInvitation($invitation_id);
                    
                    // בדוק תוצאה
                    if ($notificationResult && is_array($notificationResult) && isset($notificationResult['success'])) {
                        $notificationSent = $notificationResult['success'] === true;
                    }
                }
            }
        } catch (Exception $e) {
            // התעלם משגיאות - אל תחזיר אותן למשתמש
            error_log("Notification error: " . $e->getMessage());
        }
    }
    
    // === תגובה סופית אחת ויחידה ===
    echo json_encode([
        'success' => $result, 
        'invitation_sent' => true,
        'notification_sent' => $notificationSent,
        'message' => 'הזמנה נשלחה למשתמש' . ($notificationSent ? ' 🔔 והתראה נשלחה!' : ''),
        'show_popup' => true
    ]);
}

function removeMember($pdo, $group_id, $is_owner) {
    if (!$is_owner) {
        echo json_encode(['success' => false, 'message' => 'אין הרשאה']);
        return;
    }
    
    $member_id = intval($_POST['member_id'] ?? 0);
    
    // בדיקה אם יש קניות פעילות
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM group_purchases WHERE member_id = ?");
    $stmt->execute([$member_id]);
    
    if ($stmt->fetchColumn() > 0) {
        echo json_encode(['success' => false, 'message' => 'לא ניתן למחוק חבר עם קניות פעילות']);
    } else {
        $stmt = $pdo->prepare("UPDATE group_members SET is_active = 0 WHERE id = ? AND group_id = ?");
        $result = $stmt->execute([$member_id, $group_id]);
        echo json_encode(['success' => $result]);
    }
}

function editMember($pdo, $group_id, $is_owner) {
    if (!$is_owner) {
        echo json_encode(['success' => false, 'message' => 'אין הרשאה']);
        return;
    }
    
    $member_id = intval($_POST['member_id'] ?? 0);
    $participation_type = $_POST['participation_type'] ?? 'percentage';
    $participation_value = floatval($_POST['participation_value'] ?? 0);
    
    // בדיקת אחוזים
    if ($participation_type == 'percentage') {
        $stmt = $pdo->prepare("
            SELECT SUM(participation_value) as total_percentage 
            FROM group_members 
            WHERE group_id = ? AND participation_type = 'percentage' AND is_active = 1 AND id != ?
        ");
        $stmt->execute([$group_id, $member_id]);
        $currentPercentage = $stmt->fetch()['total_percentage'] ?? 0;
        
        if ($currentPercentage + $participation_value > 100) {
            $available = 100 - $currentPercentage;
            echo json_encode([
                'success' => false, 
                'message' => "סכום האחוזים חורג מ-100%. נותרו $available% זמינים"
            ]);
            return;
        }
    }
    
    $stmt = $pdo->prepare("
        UPDATE group_members 
        SET participation_type = ?, participation_value = ?
        WHERE id = ? AND group_id = ?
    ");
    $result = $stmt->execute([
        $participation_type,
        $participation_value,
        $member_id,
        $group_id
    ]);
    echo json_encode(['success' => $result]);
}

function cancelInvitation($pdo, $group_id, $is_owner) {
    if (!$is_owner) {
        echo json_encode(['success' => false, 'message' => 'אין הרשאה']);
        return;
    }
    
    $invitation_id = intval($_POST['invitation_id'] ?? 0);
    
    $stmt = $pdo->prepare("UPDATE group_invitations SET status = 'expired' WHERE id = ? AND group_id = ?");
    $result = $stmt->execute([$invitation_id, $group_id]);
    echo json_encode(['success' => $result]);
}

function addPurchase($pdo, $group_id, $user_id) {
    // בדיקה אם המשתמש הוא מנהל הקבוצה
    $stmt = $pdo->prepare("SELECT owner_id FROM purchase_groups WHERE id = ?");
    $stmt->execute([$group_id]);
    $group = $stmt->fetch();
    $is_owner = ($group['owner_id'] == $user_id);
    
    $member_id = intval($_POST['member_id'] ?? 0);
    $amount = floatval($_POST['amount'] ?? 0);
    $description = $_POST['description'] ?? '';
    
    // אם לא מנהל, וודא שהוא מוסיף קנייה רק בשם עצמו
    if (!$is_owner) {
        $stmt = $pdo->prepare("
            SELECT id FROM group_members 
            WHERE group_id = ? AND user_id = ? AND is_active = 1
        ");
        $stmt->execute([$group_id, $user_id]);
        $member = $stmt->fetch();
        
        if (!$member) {
            echo json_encode(['success' => false, 'message' => 'אינך חבר פעיל בקבוצה']);
            return;
        }
        
        $member_id = $member['id'];
    }
    
    // טיפול בהעלאת תמונה
    $imagePath = null;
    if (isset($_FILES['image']) && $_FILES['image']['error'] === 0) {
        $uploadDir = 'uploads/';
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        $imagePath = $uploadDir . time() . '_' . basename($_FILES['image']['name']);
        move_uploaded_file($_FILES['image']['tmp_name'], $imagePath);
    }
    
    $stmt = $pdo->prepare("
        INSERT INTO group_purchases (group_id, member_id, user_id, amount, description, image_path, purchase_date) 
        VALUES (?, ?, ?, ?, ?, ?, CURRENT_DATE())
    ");
    $result = $stmt->execute([
        $group_id,
        $member_id,
        $user_id,
        $amount,
        $description,
        $imagePath
    ]);

    if ($result) {
        $purchase_id = $pdo->lastInsertId();
        
        // שלח התראות לכל חברי הקבוצה
        require_once __DIR__ . '/../api/send-push-notification.php';
        
        try {
            $notificationResults = notifyNewPurchase($purchase_id);
            error_log("Push notifications sent for purchase ID: $purchase_id");
        } catch (Exception $e) {
            error_log("Error sending purchase notifications: " . $e->getMessage());
        }
        
        echo json_encode([
            'success' => true,
            'purchase_id' => $purchase_id,
            'notifications_sent' => isset($notificationResults)
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'שגיאה בהוספת הקנייה']);
    }

    echo json_encode(['success' => $result]);
}

function deletePurchase($pdo, $group_id, $user_id, $is_owner) {
    $purchase_id = intval($_POST['purchase_id'] ?? 0);
    
    // בדיקת הרשאות - מנהל או בעל הקנייה
    $stmt = $pdo->prepare("SELECT user_id FROM group_purchases WHERE id = ? AND group_id = ?");
    $stmt->execute([$purchase_id, $group_id]);
    $purchase = $stmt->fetch();
    
    if (!$purchase) {
        echo json_encode(['success' => false, 'message' => 'קנייה לא נמצאה']);
    } elseif ($is_owner || $purchase['user_id'] == $user_id) {
        $stmt = $pdo->prepare("DELETE FROM group_purchases WHERE id = ?");
        $result = $stmt->execute([$purchase_id]);
        echo json_encode(['success' => $result]);
    } else {
        echo json_encode(['success' => false, 'message' => 'אין הרשאה למחוק קנייה זו']);
    }
}
?>