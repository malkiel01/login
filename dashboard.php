<?php
// חלק מ-dashboard.php - טיפול בקבלת הזמנה

case 'respondInvitation':
    $invitation_id = $_POST['invitation_id'];
    $response = $_POST['response'];
    
    try {
        $pdo->beginTransaction();
        
        // קבל את פרטי ההזמנה
        $stmt = $pdo->prepare("
            SELECT * FROM group_invitations 
            WHERE id = ? AND email = ? AND status = 'pending'
        ");
        $stmt->execute([$invitation_id, $_SESSION['email']]);
        $invitation = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$invitation) {
            throw new Exception('הזמנה לא נמצאה או כבר טופלה');
        }
        
        if ($response === 'accept') {
            // בדיקה אם המשתמש כבר היה חבר בעבר
            $stmt = $pdo->prepare("
                SELECT id FROM group_members 
                WHERE group_id = ? AND user_id = ?
            ");
            $stmt->execute([$invitation['group_id'], $user_id]);
            $existingMember = $stmt->fetch();
            
            if ($existingMember) {
                // עדכון חבר קיים
                $stmt = $pdo->prepare("
                    UPDATE group_members 
                    SET is_active = 1,
                        nickname = ?,
                        email = ?,
                        participation_type = ?,
                        participation_value = ?,
                        joined_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([
                    $invitation['nickname'],
                    $_SESSION['email'],
                    $invitation['participation_type'],
                    $invitation['participation_value'],
                    $existingMember['id']
                ]);
            } else {
                // הוספת חבר חדש
                $stmt = $pdo->prepare("
                    INSERT INTO group_members 
                    (group_id, user_id, nickname, email, participation_type, participation_value) 
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $invitation['group_id'],
                    $user_id,
                    $invitation['nickname'],
                    $_SESSION['email'],
                    $invitation['participation_type'],
                    $invitation['participation_value']
                ]);
            }
        }
        
        // עדכן סטטוס הזמנה
        $stmt = $pdo->prepare("
            UPDATE group_invitations 
            SET status = ?, responded_at = NOW() 
            WHERE id = ?
        ");
        $stmt->execute([
            $response === 'accept' ? 'accepted' : 'rejected', 
            $invitation_id
        ]);
        
        $pdo->commit();
        echo json_encode(['success' => true]);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
?>