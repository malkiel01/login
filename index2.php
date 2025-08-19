<?php
// הצגת שגיאות לצורך דיבאג
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

// בדיקת התחברות
if (!isset($_SESSION['user_id'])) {
    header('Location: ./auth/login.php');
    exit;
}

// כולל את קובץ ההגדרות
require_once 'config.php';

// יצירת חיבור למסד נתונים
$pdo = getDBConnection();
$user_id = $_SESSION['user_id'];

// טיפול בפעולות AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    switch ($_POST['action']) {
        case 'addFamily':
            $stmt = $pdo->prepare("INSERT INTO families (user_id, name, percent) VALUES (?, ?, ?)");
            $result = $stmt->execute([$user_id, $_POST['name'], $_POST['percent']]);
            echo json_encode(['success' => $result]);
            exit;
            
        case 'deleteFamily':
            // וידוא שהמשפחה שייכת למשתמש
            $stmt = $pdo->prepare("DELETE FROM families WHERE id = ? AND user_id = ?");
            $result = $stmt->execute([$_POST['id'], $user_id]);
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
            
            $stmt = $pdo->prepare("INSERT INTO purchases (user_id, family_id, amount, description, image_path) VALUES (?, ?, ?, ?, ?)");
            $result = $stmt->execute([$user_id, $_POST['family_id'], $_POST['amount'], $_POST['description'], $imagePath]);
            echo json_encode(['success' => $result]);
            exit;
            
        case 'deletePurchase':
            // וידוא שהקנייה שייכת למשתמש
            $stmt = $pdo->prepare("DELETE FROM purchases WHERE id = ? AND user_id = ?");
            $result = $stmt->execute([$_POST['id'], $user_id]);
            echo json_encode(['success' => $result]);
            exit;
    }
}

// שליפת נתונים מהמסד - רק של המשתמש המחובר
$families = $pdo->prepare("SELECT * FROM families WHERE user_id = ? ORDER BY created_at DESC");
$families->execute([$user_id]);
$families = $families->fetchAll(PDO::FETCH_ASSOC);

$purchases = $pdo->prepare("
    SELECT p.*, f.name as family_name 
    FROM purchases p 
    JOIN families f ON p.family_id = f.id 
    WHERE p.user_id = ?
    ORDER BY p.created_at DESC
");
$purchases->execute([$user_id]);
$purchases = $purchases->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html dir="rtl" lang="he">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding-top: 70px;
        }
        
        /* Header Navigation */
        .navbar {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            background: white;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            z-index: 1000;
            padding: 15px 0;
        }
        
        .navbar-container {
            max-width: 1200px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0 20px;
        }
        
        .navbar-brand {
            font-size: 20px;
            font-weight: bold;
            color: #667eea;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .navbar-user {
            display: flex;
            align-items: center;
            gap: 20px;
        }
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .user-avatar {
            width: 35px;
            height: 35px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
        }
        
        .user-avatar img {
            width: 100%;
            height: 100%;
            border-radius: 50%;
            object-fit: cover;
        }
        
        .user-name {
            font-weight: 600;
            color: #333;
        }
        
        .btn-logout {
            background: #dc3545;
            color: white;
            padding: 8px 20px;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            cursor: pointer;
            transition: background 0.3s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        
        .btn-logout:hover {
            background: #c82333;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        
        h1 {
            text-align: center;
            color: white;
            margin-bottom: 30px;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
        }
        
        .tabs {
            display: flex;
            justify-content: center;
            margin-bottom: 30px;
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 10px 25px rgba(0,0,0,0.2);
        }
        
        .tab {
            flex: 1;
            padding: 15px 30px;
            background: white;
            border: none;
            cursor: pointer;
            font-size: 16px;
            font-weight: 600;
            transition: all 0.3s;
            color: #333;
        }
        
        .tab:hover {
            background: #f0f0f0;
        }
        
        .tab.active {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .content {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.2);
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #333;
        }
        
        input[type="text"],
        input[type="number"],
        textarea,
        select {
            width: 100%;
            padding: 12px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 14px;
            transition: border-color 0.3s;
        }
        
        input:focus,
        textarea:focus,
        select:focus {
            outline: none;
            border-color: #667eea;
        }
        
        textarea {
            resize: vertical;
            min-height: 100px;
        }
        
        .btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 12px 30px;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.3s, box-shadow 0.3s;
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }
        
        .family-list,
        .purchase-list {
            margin-top: 30px;
        }
        
        .item {
            background: #f8f9fa;
            padding: 15px;
            margin-bottom: 15px;
            border-radius: 8px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .delete-btn {
            background: #dc3545;
            color: white;
            padding: 5px 15px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            transition: background 0.3s;
        }
        
        .delete-btn:hover {
            background: #c82333;
        }
        
        .calculation-result {
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            padding: 20px;
            border-radius: 10px;
            margin-top: 20px;
        }
        
        .result-item {
            background: white;
            padding: 15px;
            margin-bottom: 15px;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        
        .result-header {
            font-size: 18px;
            font-weight: bold;
            color: #333;
            margin-bottom: 10px;
        }
        
        .payment-detail {
            color: #666;
            margin: 5px 0;
            padding-right: 20px;
        }
        
        .total-amount {
            font-size: 24px;
            font-weight: bold;
            color: #667eea;
            text-align: center;
            margin: 20px 0;
        }
        
        .image-preview {
            max-width: 200px;
            max-height: 200px;
            margin-top: 10px;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        
        .purchase-item {
            background: #f8f9fa;
            padding: 15px;
            margin-bottom: 15px;
            border-radius: 8px;
        }
        
        .purchase-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }
        
        .purchase-amount {
            font-size: 20px;
            font-weight: bold;
            color: #667eea;
        }
        
        .purchase-products {
            color: #666;
            white-space: pre-wrap;
            margin: 10px 0;
        }
    </style>
</head>
<body>
    <!-- Navigation Bar -->
    <nav class="navbar">
        <div class="navbar-container">
            <a href="index.php" class="navbar-brand">
                <i class="fas fa-shopping-cart"></i>
                <?php echo SITE_NAME; ?>
            </a>
            <div class="navbar-user">
                <div class="user-info">
                    <div class="user-avatar">
                        <?php if (!empty($_SESSION['profile_picture'])): ?>
                            <img src="<?php echo $_SESSION['profile_picture']; ?>" alt="Avatar">
                        <?php else: ?>
                            <?php echo mb_substr($_SESSION['name'], 0, 1); ?>
                        <?php endif; ?>
                    </div>
                    <span class="user-name">שלום, <?php echo htmlspecialchars($_SESSION['name']); ?></span>
                </div>
                <a href="auth/logout.php" class="btn-logout">
                    <i class="fas fa-sign-out-alt"></i>
                    התנתק
                </a>
            </div>
        </div>
    </nav>

    <div class="container">
        <h1>ניהול קניות משפחתיות</h1>
        
        <div class="tabs">
            <button class="tab active" onclick="showTab('families', this)">הוספת משפחה</button>
            <button class="tab" onclick="showTab('purchases', this)">הוספת קניות</button>
            <button class="tab" onclick="showTab('calculations', this)">חישוב וחלוקה</button>
        </div>
        
        <!-- מסך משפחות -->
        <div id="families" class="content">
            <h2>הוספת משפחה</h2>
            <form id="familyForm" method="POST">
                <div class="form-group">
                    <label for="familyName">שם המשפחה:</label>
                    <input type="text" id="familyName" name="name" required>
                </div>
                <div class="form-group">
                    <label for="familyPercent">אחוז יחסי (%):</label>
                    <input type="number" id="familyPercent" name="percent" min="0" max="100" step="0.01" required>
                </div>
                <button type="submit" class="btn">הוסף משפחה</button>
            </form>
            
            <div class="family-list">
                <h3>משפחות רשומות:</h3>
                <div id="familyList">
                    <?php foreach ($families as $family): ?>
                        <div class="item">
                            <div>
                                <strong><?php echo htmlspecialchars($family['name']); ?></strong> - 
                                <?php echo $family['percent']; ?>%
                            </div>
                            <button class="delete-btn" onclick="deleteFamily(<?php echo $family['id']; ?>)">מחק</button>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        
        <!-- מסך קניות -->
        <div id="purchases" class="content" style="display: none;">
            <h2>הוספת קניות</h2>
            <form id="purchaseForm" method="POST" enctype="multipart/form-data">
                <div class="form-group">
                    <label for="purchaseFamily">בחר משפחה:</label>
                    <select id="purchaseFamily" name="family_id" required>
                        <option value="">בחר משפחה...</option>
                        <?php foreach ($families as $family): ?>
                            <option value="<?php echo $family['id']; ?>">
                                <?php echo htmlspecialchars($family['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="purchaseAmount">סכום הקנייה (<?php echo CURRENCY_SYMBOL; ?>):</label>
                    <input type="number" id="purchaseAmount" name="amount" min="0" step="0.01" required>
                </div>
                <div class="form-group">
                    <label for="purchaseDescription">תיאור המוצרים:</label>
                    <textarea id="purchaseDescription" name="description" placeholder="רשום כאן את רשימת המוצרים..."></textarea>
                </div>
                <div class="form-group">
                    <label for="purchaseImage">תמונת קבלה:</label>
                    <input type="file" id="purchaseImage" name="image" accept="image/*" onchange="previewImage(event)">
                    <img id="imagePreview" class="image-preview" style="display: none;">
                </div>
                <button type="submit" class="btn">הוסף קנייה</button>
            </form>
            
            <div class="purchase-list">
                <h3>קניות רשומות:</h3>
                <div id="purchaseList">
                    <?php foreach ($purchases as $purchase): ?>
                        <div class="purchase-item">
                            <div class="purchase-header">
                                <strong><?php echo htmlspecialchars($purchase['family_name']); ?></strong>
                                <span class="purchase-amount">
                                    <?php echo CURRENCY_SYMBOL . number_format($purchase['amount'], 2); ?>
                                </span>
                            </div>
                            <?php if ($purchase['description']): ?>
                                <div class="purchase-products">
                                    <?php echo htmlspecialchars($purchase['description']); ?>
                                </div>
                            <?php endif; ?>
                            <?php if ($purchase['image_path']): ?>
                                <img src="<?php echo $purchase['image_path']; ?>" class="image-preview">
                            <?php endif; ?>
                            <button class="delete-btn" onclick="deletePurchase(<?php echo $purchase['id']; ?>)">מחק</button>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        
        <!-- מסך חישובים -->
        <div id="calculations" class="content" style="display: none;">
            <h2>חישוב וחלוקת עלויות</h2>
            <div id="calculationResults">
                <?php
                // חישוב הסכום הכולל
                $totalAmount = 0;
                foreach ($purchases as $purchase) {
                    $totalAmount += $purchase['amount'];
                }
                
                if ($totalAmount > 0 && count($families) > 0):
                    // חישוב לכל משפחה
                    $familyCalculations = [];
                    foreach ($families as $family) {
                        $shouldPay = $totalAmount * ($family['percent'] / 100);
                        $actuallyPaid = 0;
                        
                        foreach ($purchases as $purchase) {
                            if ($purchase['family_id'] == $family['id']) {
                                $actuallyPaid += $purchase['amount'];
                            }
                        }
                        
                        $balance = $actuallyPaid - $shouldPay;
                        
                        $familyCalculations[] = [
                            'name' => $family['name'],
                            'percent' => $family['percent'],
                            'shouldPay' => $shouldPay,
                            'actuallyPaid' => $actuallyPaid,
                            'balance' => $balance
                        ];
                    }
                ?>
                    <div class="total-amount">
                        סכום כולל של הקניות: <?php echo CURRENCY_SYMBOL . number_format($totalAmount, 2); ?>
                    </div>
                    
                    <h3>סיכום לפי משפחה:</h3>
                    <?php foreach ($familyCalculations as $calc): ?>
                        <div class="result-item">
                            <div class="result-header"><?php echo htmlspecialchars($calc['name']); ?></div>
                            <div class="payment-detail">אחוז מהסכום: <?php echo $calc['percent']; ?>%</div>
                            <div class="payment-detail">
                                צריך לשלם: <?php echo CURRENCY_SYMBOL . number_format($calc['shouldPay'], 2); ?>
                            </div>
                            <div class="payment-detail">
                                שילם בפועל: <?php echo CURRENCY_SYMBOL . number_format($calc['actuallyPaid'], 2); ?>
                            </div>
                            <div class="payment-detail" style="font-weight: bold; color: <?php echo $calc['balance'] >= 0 ? 'green' : 'red'; ?>">
                                <?php echo $calc['balance'] >= 0 ? 'מגיע לו' : 'חייב'; ?>: 
                                <?php echo CURRENCY_SYMBOL . number_format(abs($calc['balance']), 2); ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    
                    <h3>העברות נדרשות:</h3>
                    <div class="calculation-result">
                        <?php
                        // חישוב העברות
                        $creditors = array_filter($familyCalculations, function($f) { return $f['balance'] > 0; });
                        $debtors = array_filter($familyCalculations, function($f) { return $f['balance'] < 0; });
                        
                        usort($creditors, function($a, $b) { return $b['balance'] - $a['balance']; });
                        usort($debtors, function($a, $b) { return $a['balance'] - $b['balance']; });
                        
                        $transfers = [];
                        foreach ($creditors as &$creditor) {
                            $remainingCredit = $creditor['balance'];
                            
                            foreach ($debtors as &$debtor) {
                                if ($remainingCredit > 0 && $debtor['balance'] < 0) {
                                    $remainingDebt = abs($debtor['balance']);
                                    $transferAmount = min($remainingCredit, $remainingDebt);
                                    
                                    if ($transferAmount > 0) {
                                        $transfers[] = [
                                            'from' => $debtor['name'],
                                            'to' => $creditor['name'],
                                            'amount' => $transferAmount
                                        ];
                                        
                                        $remainingCredit -= $transferAmount;
                                        $debtor['balance'] += $transferAmount;
                                    }
                                }
                            }
                        }
                        
                        if (count($transfers) > 0):
                            foreach ($transfers as $transfer): ?>
                                <div class="payment-detail">
                                    <strong><?php echo htmlspecialchars($transfer['from']); ?></strong> 
                                    צריך להעביר 
                                    <strong><?php echo CURRENCY_SYMBOL . number_format($transfer['amount'], 2); ?></strong> 
                                    ל<strong><?php echo htmlspecialchars($transfer['to']); ?></strong>
                                </div>
                            <?php endforeach;
                        else: ?>
                            <p>אין העברות נדרשות - הכל מאוזן!</p>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <p>אין מספיק נתונים לחישוב. יש להוסיף משפחות וקניות.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <script>
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
        
        // הוספת משפחה
        document.getElementById('familyForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData();
            formData.append('action', 'addFamily');
            formData.append('name', document.getElementById('familyName').value);
            formData.append('percent', document.getElementById('familyPercent').value);
            
            fetch('index2.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                }
            });
        });
        
        // מחיקת משפחה
        function deleteFamily(id) {
            if (confirm('האם אתה בטוח שברצונך למחוק משפחה זו?')) {
                const formData = new FormData();
                formData.append('action', 'deleteFamily');
                formData.append('id', id);
                
                fetch('index.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        location.reload();
                    }
                });
            }
        }
        
        // הוספת קנייה
        document.getElementById('purchaseForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData();
            formData.append('action', 'addPurchase');
            formData.append('family_id', document.getElementById('purchaseFamily').value);
            formData.append('amount', document.getElementById('purchaseAmount').value);
            formData.append('description', document.getElementById('purchaseDescription').value);
            
            const imageFile = document.getElementById('purchaseImage').files[0];
            if (imageFile) {
                formData.append('image', imageFile);
            }
            
            fetch('index.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                }
            });
        });
        
        // מחיקת קנייה
        function deletePurchase(id) {
            if (confirm('האם אתה בטוח שברצונך למחוק קנייה זו?')) {
                const formData = new FormData();
                formData.append('action', 'deletePurchase');
                formData.append('id', id);
                
                fetch('index.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        location.reload();
                    }
                });
            }
        }
        
        // תצוגה מקדימה של תמונה
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
    </script>
</body>
</html>