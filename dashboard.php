<?php
/**
 * דף דשבורד מוגן
 * dashboard.php
 */

// בדיקת הרשאות
// require_once 'includes/auth_check.php';
// require_once 'config.php';

// $pdo = getDBConnection();
// $user_id = $_SESSION['user_id'];

// // טיפול ביצירת קבוצה חדשה
// if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
//     header('Content-Type: application/json');
    
//     // בדיקת CSRF כבר נעשתה ב-auth_check.php
    
//     switch ($_POST['action']) {
//         case 'createGroup':
//             try {
//                 $pdo->beginTransaction();
                
//                 $stmt = $pdo->prepare("INSERT INTO purchase_groups (name, description, owner_id) VALUES (?, ?, ?)");
//                 $result = $stmt->execute([$_POST['name'], $_POST['description'], $user_id]);
                
//                 if ($result) {
//                     $group_id = $pdo->lastInsertId();
                    
//                     if ($_POST['participation_type'] == 'percentage' && $_POST['participation_value'] > 100) {
//                         throw new Exception('לא ניתן להגדיר יותר מ-100% השתתפות');
//                     }
                    
//                     $stmt = $pdo->prepare("
//                         INSERT INTO group_members (group_id, user_id, nickname, email, participation_type, participation_value) 
//                         VALUES (?, ?, ?, ?, ?, ?)
//                     ");
//                     $stmt->execute([
//                         $group_id, 
//                         $user_id, 
//                         $_SESSION['name'], 
//                         $_SESSION['email'],
//                         $_POST['participation_type'],
//                         $_POST['participation_value']
//                     ]);
                    
//                     $pdo->commit();
//                     echo json_encode(['success' => true, 'group_id' => $group_id]);
//                 } else {
//                     throw new Exception('שגיאה ביצירת הקבוצה');
//                 }
//             } catch (Exception $e) {
//                 $pdo->rollBack();
//                 echo json_encode(['success' => false, 'message' => $e->getMessage()]);
//             }
//             exit;
            
//         case 'leaveGroup':
//             $stmt = $pdo->prepare("
//                 SELECT COUNT(*) FROM group_purchases gp
//                 JOIN group_members gm ON gp.member_id = gm.id
//                 WHERE gm.group_id = ? AND gm.user_id = ?
//             ");
//             $stmt->execute([$_POST['group_id'], $user_id]);
            
//             if ($stmt->fetchColumn() > 0) {
//                 echo json_encode(['success' => false, 'message' => 'לא ניתן לעזוב קבוצה עם קניות פעילות']);
//             } else {
//                 $stmt = $pdo->prepare("UPDATE group_members SET is_active = 0 WHERE group_id = ? AND user_id = ?");
//                 $result = $stmt->execute([$_POST['group_id'], $user_id]);
//                 echo json_encode(['success' => $result]);
//             }
//             exit;

//         case 'respondInvitation':
//             $invitation_id = $_POST['invitation_id'];
//             $response = $_POST['response'];
            
//             try {
//                 $pdo->beginTransaction();
                
//                 // קבל את פרטי ההזמנה
//                 $stmt = $pdo->prepare("
//                     SELECT * FROM group_invitations 
//                     WHERE id = ? AND email = ? AND status = 'pending'
//                 ");
//                 $stmt->execute([$invitation_id, $_SESSION['email']]);
//                 $invitation = $stmt->fetch(PDO::FETCH_ASSOC);
                
//                 if (!$invitation) {
//                     throw new Exception('הזמנה לא נמצאה או כבר טופלה');
//                 }
                
//                 if ($response === 'accept') {
//                     // בדיקה אם המשתמש כבר היה חבר בעבר
//                     $stmt = $pdo->prepare("
//                         SELECT id FROM group_members 
//                         WHERE group_id = ? AND user_id = ?
//                     ");
//                     $stmt->execute([$invitation['group_id'], $user_id]);
//                     $existingMember = $stmt->fetch();
                    
//                     if ($existingMember) {
//                         // עדכון חבר קיים
//                         $stmt = $pdo->prepare("
//                             UPDATE group_members 
//                             SET is_active = 1,
//                                 nickname = ?,
//                                 email = ?,
//                                 participation_type = ?,
//                                 participation_value = ?,
//                                 joined_at = NOW()
//                             WHERE id = ?
//                         ");
//                         $stmt->execute([
//                             $invitation['nickname'],
//                             $_SESSION['email'],
//                             $invitation['participation_type'],
//                             $invitation['participation_value'],
//                             $existingMember['id']
//                         ]);
//                     } else {
//                         // הוספת חבר חדש
//                         $stmt = $pdo->prepare("
//                             INSERT INTO group_members 
//                             (group_id, user_id, nickname, email, participation_type, participation_value) 
//                             VALUES (?, ?, ?, ?, ?, ?)
//                         ");
//                         $stmt->execute([
//                             $invitation['group_id'],
//                             $user_id,
//                             $invitation['nickname'],
//                             $_SESSION['email'],
//                             $invitation['participation_type'],
//                             $invitation['participation_value']
//                         ]);
//                     }
//                 }
                
//                 // עדכן סטטוס הזמנה
//                 $stmt = $pdo->prepare("
//                     UPDATE group_invitations 
//                     SET status = ?, responded_at = NOW() 
//                     WHERE id = ?
//                 ");
//                 $stmt->execute([
//                     $response === 'accept' ? 'accepted' : 'rejected', 
//                     $invitation_id
//                 ]);
                
//                 $pdo->commit();
//                 echo json_encode(['success' => true]);
                
//             } catch (Exception $e) {
//                 $pdo->rollBack();
//                 echo json_encode(['success' => false, 'message' => $e->getMessage()]);
//             }
//             exit;
//     }
// }

// // שליפת קבוצות המשתמש
// $stmt = $pdo->prepare("
//     SELECT 
//         pg.*,
//         gm.nickname,
//         gm.participation_type,
//         gm.participation_value,
//         u.name as owner_name,
//         (pg.owner_id = ?) as is_owner,
//         gs.member_count,
//         gs.purchase_count,
//         gs.total_amount
//     FROM purchase_groups pg
//     JOIN group_members gm ON pg.id = gm.group_id
//     JOIN users u ON pg.owner_id = u.id
//     LEFT JOIN group_statistics gs ON pg.id = gs.group_id
//     WHERE gm.user_id = ? AND gm.is_active = 1 AND pg.is_active = 1
//     ORDER BY pg.created_at DESC
// ");
// $stmt->execute([$user_id, $user_id]);
// $groups = $stmt->fetchAll(PDO::FETCH_ASSOC);

// // שליפת הזמנות ממתינות
// $stmt = $pdo->prepare("
//     SELECT gi.*, pg.name as group_name
//     FROM group_invitations gi
//     JOIN purchase_groups pg ON gi.group_id = pg.id
//     WHERE gi.email = ? AND gi.status = 'pending'
//     ORDER BY gi.created_at DESC
// ");
// $stmt->execute([$_SESSION['email']]);
// $invitations = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html dir="rtl" lang="he">
<head>
    <meta charset="UTF-8">
  
</head>
<body>
  
</body>
</html>