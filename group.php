<?php
// התחלת session
session_start();

// בדיקה אם זו בקשת AJAX - בדיקה ראשונה!
$is_ajax = (
    (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') ||
    (isset($_POST['action']) && $_SERVER['REQUEST_METHOD'] === 'POST')
);

// אם זו בקשת AJAX עם action, טפל בה מיד
if ($is_ajax && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    // הגדר headers ל-JSON
    header('Content-Type: application/json; charset=utf-8');
    
    // בדיקת התחברות בסיסית
    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['success' => false, 'message' => 'לא מחובר למערכת']);
        exit;
    }
    
    // בדיקת ID קבוצה
    $group_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
    if ($group_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'מזהה קבוצה לא תקין']);
        exit;
    }
    
    // טען את הקבצים הנדרשים
    require_once 'config.php';
    
    try {
        $pdo = getDBConnection();
        $user_id = $_SESSION['user_id'];
        
        // בדיקת הרשאות מהירה
        $stmt = $pdo->prepare("
            SELECT pg.owner_id, 
                   (pg.owner_id = ?) as is_owner
            FROM purchase_groups pg
            JOIN group_members gm ON pg.id = gm.group_id
            WHERE pg.id = ? AND gm.user_id = ? AND gm.is_active = 1 AND pg.is_active = 1
            LIMIT 1
        ");
        $stmt->execute([$user_id, $group_id, $user_id]);
        $group_check = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$group_check) {
            echo json_encode(['success' => false, 'message' => 'אין הרשאה לקבוצה זו']);
            exit;
        }
        
        $is_owner = $group_check['is_owner'];
        
        // טען את קובץ הפעולות
        require_once 'includes/group_actions.php';
        
        // טיפול בפעולה
        handleGroupActions($pdo, $group_id, $user_id, $is_owner);
        
    } catch (Exception $e) {
        error_log("Group AJAX Error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'שגיאת שרת']);
    }
    
    exit; // יציאה מיידית אחרי טיפול ב-AJAX
}

// === מכאן זה רק עבור טעינת הדף הרגיל (לא AJAX) ===

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
require_once 'includes/group_actions.php';
require_once 'includes/group_calculations.php';
require_once 'includes/group_modals.php';

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
    // אם אין הרשאה, חזור ל-dashboard
    header('Location: dashboard.php');
    exit;
}

$is_owner = $group['is_owner'];
$member_id = $group['member_id'];

// שליפת חברי הקבוצה המאושרים
$stmt = $pdo->prepare("
    SELECT 
        gm.*, 
        COALESCE(u.name, gm.nickname) as user_name, 
        u.profile_picture, 
        COALESCE(u.email, gm.email) as email, 
        'active' as status
    FROM group_members gm
    LEFT JOIN users u ON gm.user_id = u.id
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

// חישוב הסכום הכולל
$totalAmount = array_sum(array_column($purchases, 'amount'));
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
                        <?php if (!empty($member['profile_picture'])): ?>
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
                    <?php if ($is_owner && $member['user_id'] != $group['owner_id']): ?>
                    <div class="member-actions">
                        <button class="btn-edit" onclick="editMember(<?php echo $member['id']; ?>, '<?php echo $member['participation_type']; ?>', <?php echo $member['participation_value']; ?>)" title="ערוך">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button class="btn-remove" onclick="removeMember(<?php echo $member['id']; ?>)" title="הסר">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
                
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
            <?php echo renderCalculationsView($members, $purchases, $totalAmount); ?>
        </div>
    </div>

    <!-- Modals -->
    <?php 
    if ($is_owner) {
        renderAddMemberModal($available_percentage);
        renderEditMemberModal();
    }
    renderAddPurchaseModal($members, $is_owner, $member_id);
    renderImageModal();
    ?>

    <!-- JavaScript Variables -->
    <script>
        const groupId = <?php echo $group_id; ?>;
        const isOwner = <?php echo $is_owner ? 'true' : 'false'; ?>;
        const availablePercentage = <?php echo $available_percentage; ?>;
    </script>
    
    <!-- JavaScript File - השתמש בגרסה המתוקנת! -->
    <script src="js/group.js"></script>
    
    <!-- בתחתית כל דף (dashboard.php, group.php וכו') -->
    <script src="/family/js/push-notifications-manager.js"></script>
</body>
</html>