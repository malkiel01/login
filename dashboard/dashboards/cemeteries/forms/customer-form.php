<?php
/**
 * Customer Form - Updated with SmartSelect
 * טופס לקוח משודרג עם סלקט חכם
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: text/html; charset=utf-8');

// טעינת קבצים נדרשים
require_once __DIR__ . '/FormBuilder.php';
require_once __DIR__ . '/SmartSelect.php';  // ⭐ הקובץ החדש שלנו!
require_once dirname(__DIR__) . '/config.php';

// קבלת פרמטרים
$itemId = $_GET['itemId'] ?? $_GET['id'] ?? null;
$parentId = $_GET['parentId'] ?? $_GET['parent_id'] ?? null;

try {
    $conn = getDBConnection();
    
    // טען לקוח אם בעריכה
    $customer = null;
    if ($itemId) {
        $stmt = $conn->prepare("SELECT * FROM customers WHERE unicId = ? AND isActive = 1");
        $stmt->execute([$itemId]);
        $customer = $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    // טען מדינות - פורמט מתקדם
    $countriesStmt = $conn->prepare("
        SELECT 
            c.unicId, 
            c.countryNameHe, 
            c.countryNameEn,
            COUNT(DISTINCT ct.unicId) as cities_count
        FROM countries c
        LEFT JOIN cities ct ON ct.countryId = c.unicId AND ct.isActive = 1
        WHERE c.isActive = 1
        GROUP BY c.unicId, c.countryNameHe, c.countryNameEn
        ORDER BY c.countryNameHe
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
    
    // טען ערים של המדינה הנוכחית (אם בעריכה)
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
    
    <!-- ⭐ טען את ה-CSS של SmartSelect -->
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
        }
        
        .form-container {
            max-width: 800px;
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
            font-size: 28px;
            margin-bottom: 8px;
        }
        
        .form-section {
            margin-bottom: 30px;
        }
        
        .form-section h2 {
            color: #374151;
            font-size: 18px;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 1px solid #e5e7eb;
        }
        
        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
        }
        
        .form-grid.single-column {
            grid-template-columns: 1fr;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #374151;
            font-size: 14px;
        }
        
        .form-group label .required {
            color: #dc3545;
            margin-right: 4px;
        }
        
        .form-control {
            width: 100%;
            padding: 10px 14px;
            border: 2px solid #d1d5db;
            border-radius: 8px;
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
            min-height: 100px;
        }
        
        .form-actions {
            display: flex;
            gap: 15px;
            justify-content: flex-end;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 2px solid #e5e7eb;
        }
        
        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
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
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
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
            
            .form-container {
                padding: 20px;
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
                <input type="hidden" name="id" value="<?= htmlspecialchars($itemId) ?>">
            <?php endif; ?>
            
            <!-- פרטי זיהוי -->
            <div class="form-section">
                <h2>📋 פרטי זיהוי</h2>
                <div class="form-grid">
                    <div class="form-group">
                        <label>
                            <span class="required">*</span>
                            סוג זיהוי
                        </label>
                        <select name="typeId" id="typeId" class="form-control" required>
                            <option value="">-- בחר --</option>
                            <option value="1" <?= ($customer['typeId'] ?? '') == 1 ? 'selected' : '' ?>>ת.ז.</option>
                            <option value="2" <?= ($customer['typeId'] ?? '') == 2 ? 'selected' : '' ?>>דרכון</option>
                            <option value="3" <?= ($customer['typeId'] ?? '') == 3 ? 'selected' : '' ?>>אלמוני</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>
                            <span class="required">*</span>
                            מספר זיהוי
                        </label>
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
                        <label>
                            <span class="required">*</span>
                            שם פרטי
                        </label>
                        <input type="text" name="firstName" class="form-control" 
                               value="<?= htmlspecialchars($customer['firstName'] ?? '') ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label>
                            <span class="required">*</span>
                            שם משפחה
                        </label>
                        <input type="text" name="lastName" class="form-control" 
                               value="<?= htmlspecialchars($customer['lastName'] ?? '') ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label>מגדר</label>
                        <select name="gender" class="form-control">
                            <option value="">-- בחר --</option>
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
            
            <!-- כתובת - ⭐ כאן משתמשים ב-SmartSelect! -->
            <div class="form-section">
                <h2>🏠 כתובת</h2>
                <div class="form-grid">
                    <!-- מדינה עם SmartSelect -->
                    <div class="form-group">
                        <?php
                        $countrySelect = SmartSelect::create('countryId', 'מדינה', $countries, [
                            'searchable' => true,
                            'placeholder' => 'בחר מדינה...',
                            'search_placeholder' => 'הקלד לחיפוש מדינה...',
                            'display_mode' => 'advanced',
                            'value' => $customer['countryId'] ?? null
                        ]);
                        echo $countrySelect->render();
                        ?>
                    </div>
                    
                    <!-- עיר עם SmartSelect תלוי -->
                    <div class="form-group">
                        <?php
                        $citySelect = SmartSelect::create('cityId', 'עיר', $cities, [
                            'searchable' => true,
                            'placeholder' => $cities ? 'בחר עיר...' : 'בחר תחילה מדינה...',
                            'search_placeholder' => 'הקלד לחיפוש עיר...',
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
                           placeholder="רחוב, מספר בית, דירה"
                           value="<?= htmlspecialchars($customer['address'] ?? '') ?>">
                </div>
            </div>
            
            <!-- פרטי התקשרות -->
            <div class="form-section">
                <h2>📞 פרטי התקשרות</h2>
                <div class="form-grid">
                    <div class="form-group">
                        <label>טלפון</label>
                        <input type="tel" name="phone" class="form-control" 
                               placeholder="02-1234567"
                               value="<?= htmlspecialchars($customer['phone'] ?? '') ?>">
                    </div>
                    
                    <div class="form-group">
                        <label>טלפון נייד</label>
                        <input type="tel" name="phoneMobile" class="form-control" 
                               placeholder="050-1234567"
                               value="<?= htmlspecialchars($customer['phoneMobile'] ?? '') ?>">
                    </div>
                    
                    <div class="form-group">
                        <label>אימייל</label>
                        <input type="email" name="email" class="form-control" 
                               placeholder="example@domain.com"
                               value="<?= htmlspecialchars($customer['email'] ?? '') ?>">
                    </div>
                </div>
            </div>
            
            <!-- הערות -->
            <div class="form-section">
                <h2>📝 הערות</h2>
                <div class="form-group">
                    <textarea name="comment" class="form-control" 
                              placeholder="הערות נוספות..."><?= htmlspecialchars($customer['comment'] ?? '') ?></textarea>
                </div>
            </div>
            
            <!-- כפתורי פעולה -->
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">
                    <?= $itemId ? '💾 שמור שינויים' : '➕ הוסף לקוח' ?>
                </button>
                <button type="button" class="btn btn-secondary" onclick="window.close()">
                    ✖️ ביטול
                </button>
            </div>
        </form>
    </div>
    
    <!-- ⭐ טען את ה-JavaScript של SmartSelect -->
    <script src="/dashboard/dashboards/cemeteries/js/smart-select.js"></script>
    
    <script>
        // טיפול בשליחת הטופס
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
                    alert('✅ הלקוח נשמר בהצלחה!');
                    
                    // רענן את החלון האב
                    if (window.opener) {
                        window.opener.location.reload();
                    }
                    
                    // סגור את החלון
                    window.close();
                } else {
                    alert('❌ שגיאה: ' + (data.error || 'שגיאה לא ידועה'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('❌ שגיאה בשמירת הנתונים');
            });
        });
    </script>
</body>
</html>