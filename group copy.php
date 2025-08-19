<?php
session_start();

// בדיקת התחברות
if (!isset($_SESSION['user_id'])) {
    header('Location: auth/login.php');
    exit;
}

// בדיקת ID קבוצה
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: dashboard.php');
    exit;
}

require_once 'config.php';
$pdo = getDBConnection();
$user_id = $_SESSION['user_id'];
$group_id = $_GET['id'];

// בדיקת הרשאות והבאת פרטי הקבוצה
$stmt = $pdo->prepare("
    SELECT pg.*, 
           gm.id as member_id,
           gm.participation_type,
           gm.participation_value,
           (pg.owner_id = ?) as is_owner
    FROM purchase_groups pg
    JOIN group_members gm ON pg.id = gm.group_id
    WHERE pg.id = ? AND gm.user_id = ? AND gm.is_active = 1 AND pg.is_active = 1
");
$stmt->execute([$user_id, $group_id, $user_id]);
$group = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$group) {
    header('Location: dashboard.php');
    exit;
}

$is_owner = $group['is_owner'];
$member_id = $group['member_id'];

// טיפול בפעולות AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    switch ($_POST['action']) {
        case 'addMember':
            if (!$is_owner) {
                echo json_encode(['success' => false, 'message' => 'אין הרשאה']);
                exit;
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
                    exit;
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
                exit;
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
                
                // כאן אפשר להוסיף שליחת מייל
                echo json_encode(['success' => $result, 'invitation_sent' => true]);
            }
            exit;
            
        case 'removeMember':
            if (!$is_owner) {
                echo json_encode(['success' => false, 'message' => 'אין הרשאה']);
                exit;
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
            exit;
            
        case 'cancelInvitation':
            if (!$is_owner) {
                echo json_encode(['success' => false, 'message' => 'אין הרשאה']);
                exit;
            }
            
            $stmt = $pdo->prepare("UPDATE group_invitations SET status = 'expired' WHERE id = ? AND group_id = ?");
            $result = $stmt->execute([$_POST['invitation_id'], $group_id]);
            echo json_encode(['success' => $result]);
            exit;
            
        case 'addPurchase':
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
            exit;
            
        case 'editMember':
            if (!$is_owner) {
                echo json_encode(['success' => false, 'message' => 'אין הרשאה']);
                exit;
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
                    exit;
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
            exit;
            
        case 'deletePurchase':
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
            exit;
    }
}

// שליפת חברי הקבוצה המאושרים
$stmt = $pdo->prepare("
    SELECT gm.*, u.name as user_name, u.profile_picture, 'active' as status
    FROM group_members gm
    JOIN users u ON gm.user_id = u.id
    WHERE gm.group_id = ? AND gm.is_active = 1
    ORDER BY gm.joined_at
");
$stmt->execute([$group_id]);
$members = $stmt->fetchAll(PDO::FETCH_ASSOC);

// חישוב סך האחוזים הנוכחי
$stmt = $pdo->prepare("
    SELECT 
        SUM(CASE WHEN participation_type = 'percentage' THEN participation_value ELSE 0 END) as total_percentage,
        SUM(CASE WHEN participation_type = 'fixed' THEN participation_value ELSE 0 END) as total_fixed
    FROM group_members 
    WHERE group_id = ? AND is_active = 1
");
$stmt->execute([$group_id]);
$participation_totals = $stmt->fetch(PDO::FETCH_ASSOC);
$current_percentage = $participation_totals['total_percentage'] ?? 0;
$total_fixed = $participation_totals['total_fixed'] ?? 0;
$available_percentage = 100 - $current_percentage;

// שליפת הזמנות ממתינות (רק למנהל)
$pending_invitations = [];
if ($is_owner) {
    $stmt = $pdo->prepare("
        SELECT gi.*, 'pending' as status
        FROM group_invitations gi
        WHERE gi.group_id = ? AND gi.status = 'pending'
        ORDER BY gi.created_at DESC
    ");
    $stmt->execute([$group_id]);
    $pending_invitations = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// שליפת קניות הקבוצה
$stmt = $pdo->prepare("
    SELECT gp.*, gm.nickname, u.name as purchaser_name
    FROM group_purchases gp
    JOIN group_members gm ON gp.member_id = gm.id
    JOIN users u ON gp.user_id = u.id
    WHERE gp.group_id = ?
    ORDER BY gp.created_at DESC
");
$stmt->execute([$group_id]);
$purchases = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html dir="rtl" lang="he">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($group['name']); ?> - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/group.css">
</head>
<body>
    <!-- Navigation Bar -->
    <nav class="navbar">
        <div class="navbar-container">
            <div class="navbar-left">
                <a href="dashboard.php" class="btn-back">
                    <i class="fas fa-arrow-right"></i>
                    חזרה
                </a>
                <span class="navbar-title"><?php echo htmlspecialchars($group['name']); ?></span>
            </div>
            <div class="navbar-user">
                <div class="user-info">
                    <div class="user-avatar">
                        <?php if (!empty($_SESSION['profile_picture'])): ?>
                            <img src="<?php echo $_SESSION['profile_picture']; ?>" alt="Avatar">
                        <?php else: ?>
                            <?php echo mb_substr($_SESSION['name'], 0, 1); ?>
                        <?php endif; ?>
                    </div>
                    <span class="user-name"><?php echo htmlspecialchars($_SESSION['name']); ?></span>
                </div>
                <a href="auth/logout.php" class="btn-logout">
                    <i class="fas fa-sign-out-alt"></i>
                </a>
            </div>
        </div>
    </nav>

    <div class="container">
        <!-- כרטיסיות -->
        <div class="tabs">
            <button class="tab active" onclick="showTab('members', this)">
                <i class="fas fa-users"></i>
                משתתפים
            </button>
            <button class="tab" onclick="showTab('purchases', this)">
                <i class="fas fa-shopping-cart"></i>
                קניות
            </button>
            <button class="tab" onclick="showTab('calculations', this)">
                <i class="fas fa-calculator"></i>
                חישובים
            </button>
        </div>
        
        <!-- מסך משתתפים -->
        <div id="members" class="content">
            <?php if ($is_owner): ?>
            <div class="section-header">
                <h2>ניהול משתתפים</h2>
                <button class="btn-add" onclick="showAddMemberModal()">
                    <i class="fas fa-user-plus"></i> הוסף משתתף
                </button>
            </div>
            <?php else: ?>
            <h2>משתתפי הקבוצה</h2>
            <?php endif; ?>
            
            <div class="members-grid">
                <?php foreach ($members as $member): ?>
                <div class="member-card">
                    <div class="member-avatar">
                        <?php if ($member['profile_picture']): ?>
                            <img src="<?php echo $member['profile_picture']; ?>" alt="">
                        <?php else: ?>
                            <i class="fas fa-user"></i>
                        <?php endif; ?>
                    </div>
                    <div class="member-info">
                        <h3><?php echo htmlspecialchars($member['nickname']); ?></h3>
                        <p class="member-email"><?php echo htmlspecialchars($member['email']); ?></p>
                        <p class="member-participation">
                            <?php if ($member['participation_type'] == 'percentage'): ?>
                                <i class="fas fa-percentage"></i> <?php echo $member['participation_value']; ?>%
                            <?php else: ?>
                                <i class="fas fa-shekel-sign"></i> ₪<?php echo number_format($member['participation_value'], 2); ?>
                            <?php endif; ?>
                        </p>
                    </div>
                    <?php if ($is_owner): ?>
                    <div class="member-actions">
                        <button class="btn-edit" onclick="editMember(<?php echo $member['id']; ?>, '<?php echo $member['participation_type']; ?>', <?php echo $member['participation_value']; ?>)" title="ערוך">
                            <i class="fas fa-edit"></i>
                        </button>
                        <?php if ($member['user_id'] != $group['owner_id']): ?>
                        <button class="btn-remove" onclick="removeMember(<?php echo $member['id']; ?>)" title="הסר">
                            <i class="fas fa-times"></i>
                        </button>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
                
                <?php // הצגת התראה אם חסרים אחוזים ?>
                <?php if ($current_percentage < 100 && $current_percentage > 0): ?>
                <div class="member-card warning">
                    <div class="member-avatar">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                    <div class="member-info">
                        <h3>חסרים אחוזים להשלמה</h3>
                        <p class="member-status warning">
                            <i class="fas fa-info-circle"></i> סה"כ מוגדר: <?php echo $current_percentage; ?>%
                        </p>
                        <p class="member-participation">
                            <i class="fas fa-percentage"></i> חסרים: <?php echo $available_percentage; ?>%
                        </p>
                    </div>
                </div>
                <?php endif; ?>
                
                <?php // הצגת הזמנות ממתינות (רק למנהל) ?>
                <?php foreach ($pending_invitations as $invitation): ?>
                <div class="member-card pending">
                    <div class="member-avatar">
                        <i class="fas fa-user-clock"></i>
                    </div>
                    <div class="member-info">
                        <h3><?php echo htmlspecialchars($invitation['nickname']); ?></h3>
                        <p class="member-email"><?php echo htmlspecialchars($invitation['email']); ?></p>
                        <p class="member-status pending">
                            <i class="fas fa-clock"></i> ממתין לאישור
                        </p>
                        <p class="member-participation">
                            <?php if ($invitation['participation_type'] == 'percentage'): ?>
                                <i class="fas fa-percentage"></i> <?php echo $invitation['participation_value']; ?>%
                            <?php else: ?>
                                <i class="fas fa-shekel-sign"></i> ₪<?php echo number_format($invitation['participation_value'], 2); ?>
                            <?php endif; ?>
                        </p>
                    </div>
                    <?php if ($is_owner): ?>
                    <button class="btn-remove" onclick="cancelInvitation(<?php echo $invitation['id']; ?>)">
                        <i class="fas fa-times"></i>
                    </button>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        
        <!-- מסך קניות -->
        <div id="purchases" class="content" style="display: none;">
            <div class="section-header">
                <h2>קניות הקבוצה</h2>
                <button class="btn-add" onclick="showAddPurchaseModal()">
                    <i class="fas fa-plus"></i> הוסף קנייה
                </button>
            </div>
            
            <div class="purchases-list">
                <?php foreach ($purchases as $purchase): ?>
                <div class="purchase-card">
                    <div class="purchase-header">
                        <div class="purchase-info">
                            <h3><?php echo htmlspecialchars($purchase['nickname']); ?></h3>
                            <span class="purchase-date">
                                <i class="fas fa-calendar"></i>
                                <?php echo date('d/m/Y', strtotime($purchase['created_at'])); ?>
                            </span>
                        </div>
                        <div class="purchase-amount">
                            ₪<?php echo number_format($purchase['amount'], 2); ?>
                        </div>
                    </div>
                    
                    <?php if ($purchase['description']): ?>
                    <div class="purchase-description">
                        <?php echo nl2br(htmlspecialchars($purchase['description'])); ?>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($purchase['image_path']): ?>
                    <div class="purchase-image">
                        <img src="<?php echo $purchase['image_path']; ?>" alt="קבלה" onclick="showImageModal(this.src)">
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($is_owner || $purchase['user_id'] == $user_id): ?>
                    <div class="purchase-actions">
                        <button class="btn-delete" onclick="deletePurchase(<?php echo $purchase['id']; ?>)">
                            <i class="fas fa-trash"></i> מחק
                        </button>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        
        <!-- מסך חישובים -->
        <div id="calculations" class="content" style="display: none;">
            <h2>חישוב וחלוקת עלויות</h2>
            
            <?php
            // חישוב הסכום הכולל
            $totalAmount = array_sum(array_column($purchases, 'amount'));
            
            if ($totalAmount > 0 && count($members) > 0):
                // חישוב לכל משתתף
                $calculations = [];
                $totalPercentage = 0;
                $fixedMembers = [];
                
                // הפרד בין אחוזים לסכומים קבועים
                foreach ($members as $member) {
                    if ($member['participation_type'] == 'percentage') {
                        $totalPercentage += $member['participation_value'];
                    } else {
                        $fixedMembers[] = $member;
                    }
                }
                
                // חישוב הסכום לאחר הפחתת סכומים קבועים
                $fixedTotal = array_sum(array_column($fixedMembers, 'participation_value'));
                $percentageAmount = max(0, $totalAmount - $fixedTotal);
                
                // אם סך האחוזים קטן מ-100%, הצג התראה
                $missingPercentage = 0;
                $missingAmount = 0;
                if ($totalPercentage < 100 && $totalPercentage > 0) {
                    $missingPercentage = 100 - $totalPercentage;
                    $missingAmount = $percentageAmount * ($missingPercentage / 100);
                }
                
                foreach ($members as $member) {
                    $shouldPay = 0;
                    
                    if ($member['participation_type'] == 'percentage') {
                        // חשב לפי האחוז האמיתי, לא לפי 100%
                        $shouldPay = $percentageAmount * ($member['participation_value'] / 100);
                    } else {
                        $shouldPay = $member['participation_value'];
                    }
                    
                    // סכום ששולם בפועל
                    $actuallyPaid = 0;
                    foreach ($purchases as $purchase) {
                        if ($purchase['member_id'] == $member['id']) {
                            $actuallyPaid += $purchase['amount'];
                        }
                    }
                    
                    $balance = $actuallyPaid - $shouldPay;
                    
                    $calculations[] = [
                        'member' => $member,
                        'shouldPay' => $shouldPay,
                        'actuallyPaid' => $actuallyPaid,
                        'balance' => $balance
                    ];
                }
            ?>
            
            <div class="calculation-summary">
                <div class="total-box">
                    <i class="fas fa-receipt"></i>
                    <h3>סכום כולל</h3>
                    <div class="total-amount">₪<?php echo number_format($totalAmount, 2); ?></div>
                </div>
                
                <?php if ($fixedTotal > 0): ?>
                <div class="info-box">
                    <i class="fas fa-info-circle"></i>
                    <p>סכומים קבועים: ₪<?php echo number_format($fixedTotal, 2); ?></p>
                    <p>סכום לחלוקה באחוזים: ₪<?php echo number_format($percentageAmount, 2); ?></p>
                </div>
                <?php endif; ?>
                
                <?php if ($missingPercentage > 0): ?>
                <div class="warning-box">
                    <i class="fas fa-exclamation-triangle"></i>
                    <h3>חסרים אחוזים להשלמה</h3>
                    <p>מוגדר כרגע: <?php echo $totalPercentage; ?>%</p>
                    <p>חסרים: <?php echo $missingPercentage; ?>%</p>
                    <p>סכום לא מוקצה: ₪<?php echo number_format($missingAmount, 2); ?></p>
                </div>
                <?php endif; ?>
            </div>
            
            <div class="members-calculations">
                <h3>סיכום לפי משתתף:</h3>
                <?php foreach ($calculations as $calc): ?>
                <div class="calc-card <?php echo $calc['balance'] >= 0 ? 'positive' : 'negative'; ?>">
                    <div class="calc-member">
                        <h4><?php echo htmlspecialchars($calc['member']['nickname']); ?></h4>
                        <span class="participation-info">
                            <?php if ($calc['member']['participation_type'] == 'percentage'): ?>
                                <?php echo $calc['member']['participation_value']; ?>%
                            <?php else: ?>
                                ₪<?php echo number_format($calc['member']['participation_value'], 2); ?> קבוע
                            <?php endif; ?>
                        </span>
                    </div>
                    <div class="calc-details">
                        <div class="calc-row">
                            <span>צריך לשלם:</span>
                            <span>₪<?php echo number_format($calc['shouldPay'], 2); ?></span>
                        </div>
                        <div class="calc-row">
                            <span>שילם בפועל:</span>
                            <span>₪<?php echo number_format($calc['actuallyPaid'], 2); ?></span>
                        </div>
                        <div class="calc-row balance">
                            <span><?php echo $calc['balance'] >= 0 ? 'מגיע לו' : 'חייב'; ?>:</span>
                            <span>₪<?php echo number_format(abs($calc['balance']), 2); ?></span>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            
            <div class="transfers-section">
                <h3>העברות נדרשות:</h3>
                <?php
                // חישוב העברות
                $creditors = array_filter($calculations, function($c) { return $c['balance'] > 0; });
                $debtors = array_filter($calculations, function($c) { return $c['balance'] < 0; });
                
                usort($creditors, function($a, $b) { return $b['balance'] - $a['balance']; });
                usort($debtors, function($a, $b) { return $a['balance'] - $b['balance']; });
                
                $transfers = [];
                foreach ($creditors as &$creditor) {
                    $remainingCredit = $creditor['balance'];
                    
                    foreach ($debtors as &$debtor) {
                        if ($remainingCredit > 0.01 && $debtor['balance'] < -0.01) {
                            $remainingDebt = abs($debtor['balance']);
                            $transferAmount = min($remainingCredit, $remainingDebt);
                            
                            if ($transferAmount > 0.01) {
                                $transfers[] = [
                                    'from' => $debtor['member']['nickname'],
                                    'to' => $creditor['member']['nickname'],
                                    'amount' => $transferAmount
                                ];
                                
                                $remainingCredit -= $transferAmount;
                                $debtor['balance'] += $transferAmount;
                            }
                        }
                    }
                }
                
                if (count($transfers) > 0): ?>
                    <?php foreach ($transfers as $transfer): ?>
                    <div class="transfer-card">
                        <div class="transfer-from">
                            <i class="fas fa-user"></i>
                            <?php echo htmlspecialchars($transfer['from']); ?>
                        </div>
                        <div class="transfer-arrow">
                            <i class="fas fa-arrow-left"></i>
                            <span>₪<?php echo number_format($transfer['amount'], 2); ?></span>
                        </div>
                        <div class="transfer-to">
                            <i class="fas fa-user"></i>
                            <?php echo htmlspecialchars($transfer['to']); ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="no-transfers">
                        <i class="fas fa-check-circle"></i>
                        <p>הכל מאוזן - אין העברות נדרשות!</p>
                    </div>
                <?php endif; ?>
            </div>
            
            <?php else: ?>
            <div class="no-data">
                <i class="fas fa-info-circle"></i>
                <p>אין מספיק נתונים לחישוב</p>
                <p>יש להוסיף משתתפים וקניות</p>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Modal להוספת משתתף -->
    <?php if ($is_owner): ?>
    <div id="addMemberModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>הוספת משתתף חדש</h2>
                <span class="close" onclick="closeAddMemberModal()">&times;</span>
            </div>
            <form id="addMemberForm">
                <div class="form-group">
                    <label for="memberEmail">אימייל:</label>
                    <input type="email" id="memberEmail" required>
                </div>
                <div class="form-group">
                    <label for="memberNickname">כינוי:</label>
                    <input type="text" id="memberNickname" required>
                </div>
                <div class="form-group">
                    <label>סוג השתתפות:</label>
                    <div class="radio-group">
                        <label>
                            <input type="radio" name="participationType" value="percentage" checked onchange="toggleParticipationType()">
                            אחוז
                        </label>
                        <label>
                            <input type="radio" name="participationType" value="fixed" onchange="toggleParticipationType()">
                            סכום קבוע
                        </label>
                    </div>
                </div>
                <div class="form-group">
                    <label for="memberValue">ערך השתתפות:</label>
                    <div class="input-with-suffix">
                        <input type="number" id="memberValue" step="0.01" required>
                        <span id="valueSuffix">%</span>
                    </div>
                    <small id="percentageInfo" class="form-hint">
                        נותרו <?php echo $available_percentage; ?>% זמינים להקצאה
                    </small>
                </div>
                <div class="modal-actions">
                    <button type="submit" class="btn-primary">
                        <i class="fas fa-plus"></i> הוסף
                    </button>
                    <button type="button" class="btn-secondary" onclick="closeAddMemberModal()">
                        ביטול
                    </button>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <!-- Modal לעריכת משתתף -->
    <?php if ($is_owner): ?>
    <div id="editMemberModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>עריכת פרטי משתתף</h2>
                <span class="close" onclick="closeEditMemberModal()">&times;</span>
            </div>
            <form id="editMemberForm">
                <input type="hidden" id="editMemberId">
                <div class="form-group">
                    <label>סוג השתתפות:</label>
                    <div class="radio-group">
                        <label>
                            <input type="radio" name="editParticipationType" value="percentage" onchange="toggleEditParticipationType()">
                            אחוז
                        </label>
                        <label>
                            <input type="radio" name="editParticipationType" value="fixed" onchange="toggleEditParticipationType()">
                            סכום קבוע
                        </label>
                    </div>
                </div>
                <div class="form-group">
                    <label for="editMemberValue">ערך השתתפות:</label>
                    <div class="input-with-suffix">
                        <input type="number" id="editMemberValue" step="0.01" required>
                        <span id="editValueSuffix">%</span>
                    </div>
                    <small id="editPercentageInfo" class="form-hint"></small>
                </div>
                <div class="modal-actions">
                    <button type="submit" class="btn-primary">
                        <i class="fas fa-save"></i> שמור
                    </button>
                    <button type="button" class="btn-secondary" onclick="closeEditMemberModal()">
                        ביטול
                    </button>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <!-- Modal להוספת קנייה -->
    <div id="addPurchaseModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>הוספת קנייה חדשה</h2>
                <span class="close" onclick="closeAddPurchaseModal()">&times;</span>
            </div>
            <form id="addPurchaseForm" enctype="multipart/form-data">
                <div class="form-group">
                    <label for="purchaseMember">בחר משתתף:</label>
                    <select id="purchaseMember" required>
                        <option value="">בחר משתתף...</option>
                        <?php foreach ($members as $member): ?>
                        <option value="<?php echo $member['id']; ?>">
                            <?php echo htmlspecialchars($member['nickname']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="purchaseAmount">סכום הקנייה (₪):</label>
                    <input type="number" id="purchaseAmount" step="0.01" required>
                </div>
                <div class="form-group">
                    <label for="purchaseDescription">תיאור המוצרים:</label>
                    <textarea id="purchaseDescription" rows="4"></textarea>
                </div>
                <div class="form-group">
                    <label for="purchaseImage">תמונת קבלה:</label>
                    <input type="file" id="purchaseImage" accept="image/*" onchange="previewImage(event)">
                    <img id="imagePreview" class="image-preview" style="display: none;">
                </div>
                <div class="modal-actions">
                    <button type="submit" class="btn-primary">
                        <i class="fas fa-plus"></i> הוסף קנייה
                    </button>
                    <button type="button" class="btn-secondary" onclick="closeAddPurchaseModal()">
                        ביטול
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal להצגת תמונה -->
    <div id="imageModal" class="modal">
        <div class="modal-content image-modal">
            <span class="close" onclick="closeImageModal()">&times;</span>
            <img id="modalImage" src="">
        </div>
    </div>

    <script>
        // משתנים גלובליים
        const groupId = <?php echo $group_id; ?>;
        const isOwner = <?php echo $is_owner ? 'true' : 'false'; ?>;
        
        // פונקציית מעבר בין טאבים
        function showTab(tabName, element) {
            document.querySelectorAll('.content').forEach(content => {
                content.style.display = 'none';
            });
            document.querySelectorAll('.tab').forEach(tab => {
                tab.classList.remove('active');
            });
            
            document.getElementById(tabName).style.display = 'block';
            element.classList.add('active');
        }
        
        // ניהול משתתפים
        <?php if ($is_owner): ?>
        const availablePercentage = <?php echo $available_percentage; ?>;
        
        function showAddMemberModal() {
            document.getElementById('addMemberModal').style.display = 'block';
            updatePercentageInfo();
        }
        
        function closeAddMemberModal() {
            document.getElementById('addMemberModal').style.display = 'none';
            document.getElementById('addMemberForm').reset();
        }
        
        function toggleParticipationType() {
            const type = document.querySelector('input[name="participationType"]:checked').value;
            const suffix = document.getElementById('valueSuffix');
            const info = document.getElementById('percentageInfo');
            
            suffix.textContent = type === 'percentage' ? '%' : '₪';
            
            if (type === 'percentage') {
                info.style.display = 'block';
                document.getElementById('memberValue').max = availablePercentage;
            } else {
                info.style.display = 'none';
                document.getElementById('memberValue').removeAttribute('max');
            }
        }
        
        function updatePercentageInfo() {
            const type = document.querySelector('input[name="participationType"]:checked')?.value;
            if (type === 'percentage') {
                document.getElementById('percentageInfo').style.display = 'block';
            }
        }
        
        // עריכת משתתף
        function editMember(memberId, type, value) {
            document.getElementById('editMemberId').value = memberId;
            document.querySelector(`input[name="editParticipationType"][value="${type}"]`).checked = true;
            document.getElementById('editMemberValue').value = value;
            toggleEditParticipationType();
            document.getElementById('editMemberModal').style.display = 'block';
        }
        
        function closeEditMemberModal() {
            document.getElementById('editMemberModal').style.display = 'none';
            document.getElementById('editMemberForm').reset();
        }
        
        function toggleEditParticipationType() {
            const type = document.querySelector('input[name="editParticipationType"]:checked').value;
            const suffix = document.getElementById('editValueSuffix');
            const info = document.getElementById('editPercentageInfo');
            
            suffix.textContent = type === 'percentage' ? '%' : '₪';
            
            if (type === 'percentage') {
                info.textContent = `נותרו ${availablePercentage}% זמינים (בנוסף לערך הנוכחי)`;
                info.style.display = 'block';
            } else {
                info.style.display = 'none';
            }
        }
        
        document.getElementById('editMemberForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData();
            formData.append('action', 'editMember');
            formData.append('member_id', document.getElementById('editMemberId').value);
            formData.append('participation_type', document.querySelector('input[name="editParticipationType"]:checked').value);
            formData.append('participation_value', document.getElementById('editMemberValue').value);
            
            fetch('group.php?id=' + groupId, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert(data.message || 'שגיאה בעדכון המשתתף');
                }
            });
        });
        
        document.getElementById('addMemberForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const participationType = document.querySelector('input[name="participationType"]:checked').value;
            const participationValue = parseFloat(document.getElementById('memberValue').value);
            
            // בדיקת תקינות בצד הלקוח
            if (participationType === 'percentage' && participationValue > availablePercentage) {
                alert(`לא ניתן להוסיף יותר מ-${availablePercentage}% זמינים`);
                return;
            }
            
            const formData = new FormData();
            formData.append('action', 'addMember');
            formData.append('email', document.getElementById('memberEmail').value);
            formData.append('nickname', document.getElementById('memberNickname').value);
            formData.append('participation_type', participationType);
            formData.append('participation_value', participationValue);
            
            fetch('group.php?id=' + groupId, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    if (data.reactivated) {
                        alert('המשתמש הופעל מחדש בהצלחה');
                    }
                    location.reload();
                } else {
                    alert(data.message || 'שגיאה בהוספת המשתתף');
                }
            });
        });
        
        function removeMember(memberId) {
            if (!confirm('האם אתה בטוח שברצונך להסיר משתתף זה?')) return;
            
            const formData = new FormData();
            formData.append('action', 'removeMember');
            formData.append('member_id', memberId);
            
            fetch('group.php?id=' + groupId, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert(data.message || 'שגיאה בהסרת המשתתף');
                }
            });
        }
        <?php endif; ?>
        
        // ביטול הזמנה
        function cancelInvitation(invitationId) {
            if (!confirm('האם אתה בטוח שברצונך לבטל הזמנה זו?')) return;
            
            const formData = new FormData();
            formData.append('action', 'cancelInvitation');
            formData.append('invitation_id', invitationId);
            
            fetch('group.php?id=' + groupId, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert(data.message || 'שגיאה בביטול ההזמנה');
                }
            });
        }
        
        // ניהול קניות
        function showAddPurchaseModal() {
            document.getElementById('addPurchaseModal').style.display = 'block';
        }
        
        function closeAddPurchaseModal() {
            document.getElementById('addPurchaseModal').style.display = 'none';
            document.getElementById('addPurchaseForm').reset();
            document.getElementById('imagePreview').style.display = 'none';
        }
        
        function previewImage(event) {
            const file = event.target.files[0];
            const reader = new FileReader();
            
            reader.onload = function(e) {
                const preview = document.getElementById('imagePreview');
                preview.src = e.target.result;
                preview.style.display = 'block';
            };
            
            if (file) {
                reader.readAsDataURL(file);
            }
        }
        
        document.getElementById('addPurchaseForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData();
            formData.append('action', 'addPurchase');
            formData.append('member_id', document.getElementById('purchaseMember').value);
            formData.append('amount', document.getElementById('purchaseAmount').value);
            formData.append('description', document.getElementById('purchaseDescription').value);
            
            const imageFile = document.getElementById('purchaseImage').files[0];
            if (imageFile) {
                formData.append('image', imageFile);
            }
            
            fetch('group.php?id=' + groupId, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert('שגיאה בהוספת הקנייה');
                }
            });
        });
        
        function deletePurchase(purchaseId) {
            if (!confirm('האם אתה בטוח שברצונך למחוק קנייה זו?')) return;
            
            const formData = new FormData();
            formData.append('action', 'deletePurchase');
            formData.append('purchase_id', purchaseId);
            
            fetch('group.php?id=' + groupId, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert(data.message || 'שגיאה במחיקת הקנייה');
                }
            });
        }
        
        // הצגת תמונה במודל
        function showImageModal(src) {
            document.getElementById('modalImage').src = src;
            document.getElementById('imageModal').style.display = 'block';
        }
        
        function closeImageModal() {
            document.getElementById('imageModal').style.display = 'none';
        }
        
        // סגירת modals בלחיצה מחוץ להם
        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.style.display = 'none';
            }
        }
    </script>
</body>
</html>