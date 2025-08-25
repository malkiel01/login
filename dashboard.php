<?php
/**
 * דף דשבורד מוגן
 * dashboard.php
 */

// בדיקת הרשאות
require_once 'includes/auth_check.php';
require_once 'config.php';

$pdo = getDBConnection();
$user_id = $_SESSION['user_id'];

// טיפול ביצירת קבוצה חדשה
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    // בדיקת CSRF כבר נעשתה ב-auth_check.php
    
    switch ($_POST['action']) {
        case 'createGroup':
            try {
                $pdo->beginTransaction();
                
                $stmt = $pdo->prepare("INSERT INTO purchase_groups (name, description, owner_id) VALUES (?, ?, ?)");
                $result = $stmt->execute([$_POST['name'], $_POST['description'], $user_id]);
                
                if ($result) {
                    $group_id = $pdo->lastInsertId();
                    
                    if ($_POST['participation_type'] == 'percentage' && $_POST['participation_value'] > 100) {
                        throw new Exception('לא ניתן להגדיר יותר מ-100% השתתפות');
                    }
                    
                    $stmt = $pdo->prepare("
                        INSERT INTO group_members (group_id, user_id, nickname, email, participation_type, participation_value) 
                        VALUES (?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([
                        $group_id, 
                        $user_id, 
                        $_SESSION['name'], 
                        $_SESSION['email'],
                        $_POST['participation_type'],
                        $_POST['participation_value']
                    ]);
                    
                    $pdo->commit();
                    echo json_encode(['success' => true, 'group_id' => $group_id]);
                } else {
                    throw new Exception('שגיאה ביצירת הקבוצה');
                }
            } catch (Exception $e) {
                $pdo->rollBack();
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            }
            exit;
            
        case 'leaveGroup':
            $stmt = $pdo->prepare("
                SELECT COUNT(*) FROM group_purchases gp
                JOIN group_members gm ON gp.member_id = gm.id
                WHERE gm.group_id = ? AND gm.user_id = ?
            ");
            $stmt->execute([$_POST['group_id'], $user_id]);
            
            if ($stmt->fetchColumn() > 0) {
                echo json_encode(['success' => false, 'message' => 'לא ניתן לעזוב קבוצה עם קניות פעילות']);
            } else {
                $stmt = $pdo->prepare("UPDATE group_members SET is_active = 0 WHERE group_id = ? AND user_id = ?");
                $result = $stmt->execute([$_POST['group_id'], $user_id]);
                echo json_encode(['success' => $result]);
            }
            exit;

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
    }
}

// שליפת קבוצות המשתמש
$stmt = $pdo->prepare("
    SELECT 
        pg.*,
        gm.nickname,
        gm.participation_type,
        gm.participation_value,
        u.name as owner_name,
        (pg.owner_id = ?) as is_owner,
        gs.member_count,
        gs.purchase_count,
        gs.total_amount
    FROM purchase_groups pg
    JOIN group_members gm ON pg.id = gm.group_id
    JOIN users u ON pg.owner_id = u.id
    LEFT JOIN group_statistics gs ON pg.id = gs.group_id
    WHERE gm.user_id = ? AND gm.is_active = 1 AND pg.is_active = 1
    ORDER BY pg.created_at DESC
");
$stmt->execute([$user_id, $user_id]);
$groups = $stmt->fetchAll(PDO::FETCH_ASSOC);

// שליפת הזמנות ממתינות
$stmt = $pdo->prepare("
    SELECT gi.*, pg.name as group_name
    FROM group_invitations gi
    JOIN purchase_groups pg ON gi.group_id = pg.id
    WHERE gi.email = ? AND gi.status = 'pending'
    ORDER BY gi.created_at DESC
");
$stmt->execute([$_SESSION['email']]);
$invitations = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html dir="rtl" lang="he">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>דשבורד - מערכת ניהול קניות קבוצתיות</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <div class="dashboard-container">
        <header class="dashboard-header">
            <h1>שלום, <?php echo htmlspecialchars($_SESSION['name']); ?></h1>
            <nav>
                <a href="profile.php">פרופיל</a>
                <a href="logout.php">יציאה</a>
            </nav>
        </header>

        <?php if (count($invitations) > 0): ?>
        <section class="invitations-section">
            <h2>הזמנות ממתינות</h2>
            <div class="invitations-list">
                <?php foreach ($invitations as $invitation): ?>
                <div class="invitation-card">
                    <h3><?php echo htmlspecialchars($invitation['group_name']); ?></h3>
                    <p>כינוי: <?php echo htmlspecialchars($invitation['nickname']); ?></p>
                    <p>השתתפות: 
                        <?php echo $invitation['participation_value']; ?>
                        <?php echo $invitation['participation_type'] == 'percentage' ? '%' : '₪'; ?>
                    </p>
                    <div class="invitation-actions">
                        <button onclick="respondInvitation(<?php echo $invitation['id']; ?>, 'accept')" class="btn-accept">אשר</button>
                        <button onclick="respondInvitation(<?php echo $invitation['id']; ?>, 'reject')" class="btn-reject">דחה</button>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </section>
        <?php endif; ?>

        <section class="groups-section">
            <div class="section-header">
                <h2>הקבוצות שלי</h2>
                <button onclick="showCreateGroupModal()" class="btn-primary">צור קבוצה חדשה</button>
            </div>
            
            <?php if (count($groups) > 0): ?>
            <div class="groups-grid">
                <?php foreach ($groups as $group): ?>
                <div class="group-card">
                    <h3><?php echo htmlspecialchars($group['name']); ?></h3>
                    <p><?php echo htmlspecialchars($group['description']); ?></p>
                    <div class="group-stats">
                        <span>חברים: <?php echo $group['member_count'] ?? 0; ?></span>
                        <span>קניות: <?php echo $group['purchase_count'] ?? 0; ?></span>
                        <span>סה"כ: ₪<?php echo number_format($group['total_amount'] ?? 0, 2); ?></span>
                    </div>
                    <div class="group-participation">
                        <strong>השתתפותי:</strong>
                        <?php echo $group['participation_value']; ?>
                        <?php echo $group['participation_type'] == 'percentage' ? '%' : '₪'; ?>
                    </div>
                    <div class="group-actions">
                        <a href="group.php?id=<?php echo $group['id']; ?>" class="btn-view">צפה בקבוצה</a>
                        <?php if (!$group['is_owner']): ?>
                        <button onclick="leaveGroup(<?php echo $group['id']; ?>)" class="btn-leave">עזוב קבוצה</button>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <p class="no-groups">אין לך קבוצות פעילות. צור קבוצה חדשה כדי להתחיל!</p>
            <?php endif; ?>
        </section>
    </div>

    <!-- מודל יצירת קבוצה -->
    <div id="createGroupModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeCreateGroupModal()">&times;</span>
            <h2>יצירת קבוצה חדשה</h2>
            <form id="createGroupForm">
                <div class="form-group">
                    <label for="groupName">שם הקבוצה:</label>
                    <input type="text" id="groupName" name="name" required>
                </div>
                
                <div class="form-group">
                    <label for="groupDescription">תיאור:</label>
                    <textarea id="groupDescription" name="description" rows="3"></textarea>
                </div>
                
                <div class="form-group">
                    <label for="participationType">סוג השתתפות:</label>
                    <select id="participationType" name="participation_type" onchange="updateParticipationLabel()">
                        <option value="percentage">אחוזים</option>
                        <option value="fixed">סכום קבוע</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="participationValue">ערך השתתפות:</label>
                    <input type="number" id="participationValue" name="participation_value" min="0" step="0.01" required>
                    <span id="participationUnit">%</span>
                </div>
                
                <button type="submit" class="btn-primary">צור קבוצה</button>
            </form>
        </div>
    </div>

    <script src="assets/js/dashboard.js"></script>
</body>
</html>