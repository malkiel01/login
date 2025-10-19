<?php
/**
 * Enhanced Customer Form with Smart Select - COMPLETE VERSION
 * Version: 3.0.0 - Fixed and Working
 */

// === DEBUG MODE ===
$DEBUG_MODE = true;

if ($DEBUG_MODE) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
}

// === INCLUDES ===
require_once __DIR__ . '/FormBuilder.php';
require_once __DIR__ . '/SmartSelect.php';
require_once dirname(__DIR__) . '/config.php';

// === PARAMETERS ===
$itemId = $_GET['itemId'] ?? $_GET['id'] ?? null;

// === DATABASE ===
try {
    $conn = getDBConnection();
    
    // Load customer
    $customer = null;
    if ($itemId) {
        $stmt = $conn->prepare("SELECT * FROM customers WHERE unicId = ? AND isActive = 1");
        $stmt->execute([$itemId]);
        $customer = $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    // Load countries for SmartSelect
    $countriesStmt = $conn->prepare("
        SELECT unicId, countryNameHe, countryNameEn,
               (SELECT COUNT(*) FROM cities WHERE countryId = countries.unicId AND isActive = 1) as cities_count
        FROM countries 
        WHERE isActive = 1 
        ORDER BY countryNameHe
    ");
    $countriesStmt->execute();
    
    $countries = [];
    while ($row = $countriesStmt->fetch(PDO::FETCH_ASSOC)) {
        $countries[$row['unicId']] = [
            'text' => $row['countryNameHe'],
            'subtitle' => $row['countryNameEn'],
            'badge' => $row['cities_count'] > 0 ? $row['cities_count'] . ' ערים' : ''
        ];
    }
    
    // Load cities if editing
    $cities = [];
    if ($customer && !empty($customer['countryId'])) {
        $citiesStmt = $conn->prepare("
            SELECT unicId, cityNameHe, cityNameEn
            FROM cities 
            WHERE countryId = ? AND isActive = 1 
            ORDER BY cityNameHe
        ");
        $citiesStmt->execute([$customer['countryId']]);
        
        while ($row = $citiesStmt->fetch(PDO::FETCH_ASSOC)) {
            $cities[$row['unicId']] = [
                'text' => $row['cityNameHe'],
                'subtitle' => $row['cityNameEn']
            ];
        }
    }
    
} catch (Exception $e) {
    die("שגיאה: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html dir="rtl" lang="he">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $itemId ? 'עריכת לקוח' : 'לקוח חדש' ?></title>
    
    <!-- SmartSelect CSS -->
    <link rel="stylesheet" href="/dashboard/dashboards/cemeteries/css/smart-select.css">
    
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: #f5f7fa;
            padding: 20px;
            direction: rtl;
        }
        
        .form-container {
            max-width: 900px;
            margin: 0 auto;
            background: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        
        .form-header {
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #e5e7eb;
        }
        
        .form-header h1 {
            color: #667eea;
            font-size: 24px;
            margin-bottom: 5px;
        }
        
        .form-section {
            margin-bottom: 25px;
            padding: 20px;
            background: #f9fafb;
            border-radius: 8px;
        }
        
        .form-section h2 {
            color: #374151;
            font-size: 16px;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 1px solid #e5e7eb;
        }
        
        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
            color: #374151;
            font-size: 14px;
        }
        
        .form-group label .required {
            color: #dc3545;
            margin-right: 3px;
        }
        
        .form-control {
            width: 100%;
            padding: 10px 12px;
            border: 2px solid #d1d5db;
            border-radius: 6px;
            font-size: 14px;
            transition: all 0.2s;
        }
        
        .form-control:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        select.form-control {
            cursor: pointer;
        }
        
        textarea.form-control {
            resize: vertical;
            min-height: 80px;
        }
        
        .form-actions {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
            margin-top: 25px;
            padding-top: 20px;
            border-top: 2px solid #e5e7eb;
        }
        
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .btn-primary {
            background: #667eea;
            color: white;
        }
        
        .btn-primary:hover {
            background: #5568d3;
            transform: translateY(-1px);
        }
        
        .btn-secondary {
            background: #6b7280;
            color: white;
        }
        
        .btn-secondary:hover {
            background: #4b5563;
        }
        
        @media (max-width: 768px) {
            .form-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="form-container">
        <div class="form-header">
            <h1><?= $itemId ? '✏️ עריכת לקוח' : '➕ לקוח חדש' ?></h1>
        </div>
        
        <form id="customerForm" method="POST" action="/dashboard/dashboards/cemeteries/api/customers-api.php">
            <input type="hidden" name="action" value="<?= $itemId ? 'update' : 'create' ?>">
            <?php if ($itemId): ?>
                <input type="hidden" name="unicId" value="<?= htmlspecialchars($customer['unicId']) ?>">
            <?php endif; ?>
            
            <!-- זיהוי -->
            <div class="form-section">
                <h2>🆔 פרטי זיהוי</h2>
                <div class="form-grid">
                    <div class="form-group">
                        <label><span class="required">*</span> סוג זיהוי</label>
                        <select name="typeId" id="typeId" class="form-control" required>
                            <option value="1" <?= ($customer['typeId'] ?? 1) == 1 ? 'selected' : '' ?>>ת.ז.</option>
                            <option value="2" <?= ($customer['typeId'] ?? 1) == 2 ? 'selected' : '' ?>>דרכון</option>
                            <option value="3" <?= ($customer['typeId'] ?? 1) == 3 ? 'selected' : '' ?>>אלמוני</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label><span class="required">*</span> מספר זיהוי</label>
                        <input type="text" name="numId" id="numId" class="form-control" 
                               value="<?= htmlspecialchars($customer['numId'] ?? '') ?>" required>
                    </div>
                </div>
            </div>
            
            <!-- פרטים אישיים -->
            <div class="form-section">
                <h2>👤 פרטים אישיים</h2>
                <div class="form-grid">
                    <div class="form-group">
                        <label><span class="required">*</span> שם פרטי</label>
                        <input type="text" name="firstName" class="form-control" 
                               value="<?= htmlspecialchars($customer['firstName'] ?? '') ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label><span class="required">*</span> שם משפחה</label>
                        <input type="text" name="lastName" class="form-control" 
                               value="<?= htmlspecialchars($customer['lastName'] ?? '') ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label>מגדר</label>
                        <select name="gender" class="form-control">
                            <option value="">בחר</option>
                            <option value="1" <?= ($customer['gender'] ?? '') == 1 ? 'selected' : '' ?>>זכר</option>
                            <option value="2" <?= ($customer['gender'] ?? '') == 2 ? 'selected' : '' ?>>נקבה</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>תאריך לידה</label>
                        <input type="date" name="dateBirth" class="form-control" 
                               value="<?= htmlspecialchars($customer['dateBirth'] ?? '') ?>">
                    </div>
                </div>
            </div>
            
            <!-- כתובת עם SmartSelect -->
            <div class="form-section">
                <h2>🏠 כתובת</h2>
                <div class="form-grid">
                    <!-- מדינה -->
                    <div class="form-group">
                        <?php
                        $countrySelect = SmartSelect::create('countryId', 'מדינה', $countries, [
                            'searchable' => true,
                            'placeholder' => 'בחר מדינה...',
                            'search_placeholder' => 'הקלד לחיפוש...',
                            'display_mode' => 'advanced',
                            'value' => $customer['countryId'] ?? null
                        ]);
                        echo $countrySelect->render();
                        ?>
                    </div>
                    
                    <!-- עיר -->
                    <div class="form-group">
                        <?php
                        $citySelect = SmartSelect::create('cityId', 'עיר', $cities, [
                            'searchable' => true,
                            'placeholder' => $cities ? 'בחר עיר...' : 'בחר תחילה מדינה...',
                            'search_placeholder' => 'הקלד לחיפוש...',
                            'display_mode' => 'advanced',
                            'depends_on' => 'countryId',
                            'ajax_url' => '/dashboard/dashboards/cemeteries/api/get-cities.php',
                            'value' => $customer['cityId'] ?? null,
                            'disabled' => empty($customer['countryId'])
                        ]);
                        echo $citySelect->render();
                        ?>
                    </div>
                </div>
                
                <div class="form-group">
                    <label>כתובת מלאה</label>
                    <input type="text" name="address" class="form-control" 
                           placeholder="רחוב, מספר בית"
                           value="<?= htmlspecialchars($customer['address'] ?? '') ?>">
                </div>
            </div>
            
            <!-- פרטי קשר -->
            <div class="form-section">
                <h2>📞 פרטי קשר</h2>
                <div class="form-grid">
                    <div class="form-group">
                        <label>טלפון</label>
                        <input type="tel" name="phone" class="form-control" 
                               placeholder="02-1234567"
                               value="<?= htmlspecialchars($customer['phone'] ?? '') ?>">
                    </div>
                    
                    <div class="form-group">
                        <label>נייד</label>
                        <input type="tel" name="phoneMobile" class="form-control" 
                               placeholder="050-1234567"
                               value="<?= htmlspecialchars($customer['phoneMobile'] ?? '') ?>">
                    </div>
                </div>
            </div>
            
            <!-- נוספים -->
            <div class="form-section">
                <h2>📝 פרטים נוספים</h2>
                <div class="form-grid">
                    <div class="form-group">
                        <label>סטטוס</label>
                        <select name="statusCustomer" class="form-control">
                            <option value="1" <?= ($customer['statusCustomer'] ?? 1) == 1 ? 'selected' : '' ?>>פעיל</option>
                            <option value="2" <?= ($customer['statusCustomer'] ?? 1) == 2 ? 'selected' : '' ?>>רוכש</option>
                            <option value="3" <?= ($customer['statusCustomer'] ?? 1) == 3 ? 'selected' : '' ?>>נפטר</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>תושבות</label>
                        <select name="resident" class="form-control">
                            <option value="1" <?= ($customer['resident'] ?? 3) == 1 ? 'selected' : '' ?>>ירושלים</option>
                            <option value="2" <?= ($customer['resident'] ?? 3) == 2 ? 'selected' : '' ?>>תושב חוץ</option>
                            <option value="3" <?= ($customer['resident'] ?? 3) == 3 ? 'selected' : '' ?>>תושב חו"ל</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-group">
                    <label>הערות</label>
                    <textarea name="comment" class="form-control"><?= htmlspecialchars($customer['comment'] ?? '') ?></textarea>
                </div>
            </div>
            
            <!-- כפתורים -->
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">
                    <?= $itemId ? '💾 שמור' : '➕ הוסף' ?>
                </button>
                <button type="button" class="btn btn-secondary" onclick="window.close()">
                    ביטול
                </button>
            </div>
        </form>
    </div>
    
    <!-- SmartSelect JavaScript -->
    <script src="/dashboard/dashboards/cemeteries/js/smart-select.js"></script>
    
    <script>
        // Form submission
        document.getElementById('customerForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            
            fetch(this.action, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('✅ נשמר בהצלחה!');
                    if (window.opener) {
                        window.opener.location.reload();
                    }
                    window.close();
                } else {
                    alert('❌ שגיאה: ' + (data.error || 'לא ידוע'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('❌ שגיאה בשמירה');
            });
        });
    </script>
</body>
</html>