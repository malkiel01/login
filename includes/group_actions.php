<?php
// includes/group_actions.php
// טיפול בכל פעולות ה-AJAX של הקבוצה

function handleGroupActions($pdo, $group_id, $user_id, $is_owner) {
    header('Content-Type: application/json');
    
    switch ($_POST['action']) {
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
}

function addMember($pdo, $group_id, $user_id, $is_owner) {
    try {
        if (!$is_owner) {
            echo json_encode(['success' => false, 'message' => 'אין הרשאה']);
            return;
        }
        
        // בדיקת אחוזים קיימים
        if ($_POST['participation_type'] == 'percentage') {
            $stmt = $pdo->prepare("
                SELECT SUM(participation_value) as total_percentage 
                FROM group_members 
                WHERE group_id = ? AND participation_type = 'percentage' AND is_active = 1
            ");
            $stmt->execute([$group_id]);
            $currentPercentage = $stmt->fetch()['total_percentage'] ?? 0;
            
            if ($currentPercentage + $_POST['participation_value'] > 100) {
                $available = 100 - $currentPercentage;
                echo json_encode([
                    'success' => false, 
                    'message' => "סכום האחוזים חורג מ-100%. נותרו $available% זמינים"
                ]);
                return;
            }
        }
        
        // בדיקה אם כבר קיימת הזמנה ממתינה לאימייל זה
        $stmt = $pdo->prepare("
            SELECT id FROM group_invitations 
            WHERE group_id = ? AND email = ? AND status = 'pending'
        ");
        $stmt->execute([$group_id, $_POST['email']]);
        if ($stmt->fetch()) {
            echo json_encode(['success' => false, 'message' => 'כבר קיימת הזמנה ממתינה למשתמש זה']);
            return;
        }
        
        // בדיקה אם המשתמש קיים במערכת
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$_POST['email']]);
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
                } else {
                    // אם המשתמש לא פעיל, הפעל אותו מחדש עם הנתונים החדשים
                    $stmt = $pdo->prepare("
                        UPDATE group_members 
                        SET is_active = 1, 
                            nickname = ?, 
                            participation_type = ?, 
                            participation_value = ?,
                            joined_at = NOW()
                        WHERE id = ?
                    ");
                    $result = $stmt->execute([
                        $_POST['nickname'],
                        $_POST['participation_type'],
                        $_POST['participation_value'],
                        $existingMember['id']
                    ]);
                    echo json_encode(['success' => $result, 'reactivated' => true]);
                }
            } else {
                // הוסף משתמש חדש
                $stmt = $pdo->prepare("
                    INSERT INTO group_members (group_id, user_id, nickname, email, participation_type, participation_value) 
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                $result = $stmt->execute([
                    $group_id, 
                    $user['id'], 
                    $_POST['nickname'], 
                    $_POST['email'],
                    $_POST['participation_type'],
                    $_POST['participation_value']
                ]);
                echo json_encode(['success' => $result]);
            }
        } else {
            // שלח הזמנה
            $token = bin2hex(random_bytes(32));
            $stmt = $pdo->prepare("
                INSERT INTO group_invitations (group_id, email, nickname, participation_type, participation_value, token, invited_by) 
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $result = $stmt->execute([
                $group_id,
                $_POST['email'],
                $_POST['nickname'],
                $_POST['participation_type'],
                $_POST['participation_value'],
                $token,
                $user_id
            ]);
            
            echo json_encode(['success' => $result, 'invitation_sent' => true]);
        }
    } catch (Exception $e) {
        error_log("Error in addMember: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'שגיאה בהוספת המשתתף']);
    }
}

function removeMember($pdo, $group_id, $is_owner) {
    try {
        if (!$is_owner) {
            echo json_encode(['success' => false, 'message' => 'אין הרשאה']);
            return;
        }
        
        // בדיקה אם יש קניות פעילות
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM group_purchases WHERE member_id = ?");
        $stmt->execute([$_POST['member_id']]);
        
        if ($stmt->fetchColumn() > 0) {
            echo json_encode(['success' => false, 'message' => 'לא ניתן למחוק חבר עם קניות פעילות']);
        } else {
            $stmt = $pdo->prepare("UPDATE group_members SET is_active = 0 WHERE id = ? AND group_id = ?");
            $result = $stmt->execute([$_POST['member_id'], $group_id]);
            echo json_encode(['success' => $result]);
        }
    } catch (Exception $e) {
        error_log("Error in removeMember: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'שגיאה בהסרת המשתתף']);
    }
}

function editMember($pdo, $group_id, $is_owner) {
    try {
        if (!$is_owner) {
            echo json_encode(['success' => false, 'message' => 'אין הרשאה']);
            return;
        }
        
        // בדיקת אחוזים אם שינוי לאחוזים
        if ($_POST['participation_type'] == 'percentage') {
            $stmt = $pdo->prepare("
                SELECT SUM(participation_value) as total_percentage 
                FROM group_members 
                WHERE group_id = ? AND participation_type = 'percentage' AND is_active = 1 AND id != ?
            ");
            $stmt->execute([$group_id, $_POST['member_id']]);
            $currentPercentage = $stmt->fetch()['total_percentage'] ?? 0;
            
            if ($currentPercentage + $_POST['participation_value'] > 100) {
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
            $_POST['participation_type'],
            $_POST['participation_value'],
            $_POST['member_id'],
            $group_id
        ]);
        echo json_encode(['success' => $result]);
    } catch (Exception $e) {
        error_log("Error in editMember: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'שגיאה בעדכון המשתתף']);
    }
}

function cancelInvitation($pdo, $group_id, $is_owner) {
    try {
        if (!$is_owner) {
            echo json_encode(['success' => false, 'message' => 'אין הרשאה']);
            return;
        }
        
        $stmt = $pdo->prepare("UPDATE group_invitations SET status = 'expired' WHERE id = ? AND group_id = ?");
        $result = $stmt->execute([$_POST['invitation_id'], $group_id]);
        echo json_encode(['success' => $result]);
    } catch (Exception $e) {
        error_log("Error in cancelInvitation: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'שגיאה בביטול ההזמנה']);
    }
}

function addPurchase($pdo, $group_id, $user_id) {
    try {
        // בדיקה אם המשתמש הוא מנהל הקבוצה
        $stmt = $pdo->prepare("SELECT owner_id FROM purchase_groups WHERE id = ?");
        $stmt->execute([$group_id]);
        $group = $stmt->fetch();
        $is_owner = ($group['owner_id'] == $user_id);
        
        // אם המשתמש לא מנהל, וודא שהוא מוסיף קנייה רק בשם עצמו
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
            
            // החלף את ה-member_id למזהה של המשתמש עצמו
            $_POST['member_id'] = $member['id'];
        }
        
        // טיפול בהעלאת תמונה
        $imagePath = null;
        if (isset($_FILES['image']) && $_FILES['image']['error'] === 0) {
            $uploadDir = 'uploads/';
            if (!file_exists($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }
            $imagePath = $uploadDir . time() . '_' . $_FILES['image']['name'];
            move_uploaded_file($_FILES['image']['tmp_name'], $imagePath);
        }
        
        $stmt = $pdo->prepare("
            INSERT INTO group_purchases (group_id, member_id, user_id, amount, description, image_path, purchase_date) 
            VALUES (?, ?, ?, ?, ?, ?, CURRENT_DATE())
        ");
        $result = $stmt->execute([
            $group_id,
            $_POST['member_id'],
            $user_id,
            $_POST['amount'],
            $_POST['description'],
            $imagePath
        ]);
        echo json_encode(['success' => $result]);
    } catch (Exception $e) {
        error_log("Error in addPurchase: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'שגיאה בהוספת הקנייה']);
    }
}

function deletePurchase($pdo, $group_id, $user_id, $is_owner) {
    try {
        // בדיקת הרשאות - מנהל או בעל הקנייה
        $stmt = $pdo->prepare("SELECT user_id FROM group_purchases WHERE id = ? AND group_id = ?");
        $stmt->execute([$_POST['purchase_id'], $group_id]);
        $purchase = $stmt->fetch();
        
        if (!$purchase) {
            echo json_encode(['success' => false, 'message' => 'קנייה לא נמצאה']);
        } elseif ($is_owner || $purchase['user_id'] == $user_id) {
            $stmt = $pdo->prepare("DELETE FROM group_purchases WHERE id = ?");
            $result = $stmt->execute([$_POST['purchase_id']]);
            echo json_encode(['success' => $result]);
        } else {
            echo json_encode(['success' => false, 'message' => 'אין הרשאה למחוק קנייה זו']);
        }
    } catch (Exception $e) {
        error_log("Error in deletePurchase: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'שגיאה במחיקת הקנייה']);
    }
}
?>