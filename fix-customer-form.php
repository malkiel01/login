#!/usr/bin/php
<?php
/**
 * Customer Form Enhancement Script
 * מעדכן את טופס הלקוח עם Smart Select ושיפורים 
נוספים
 * 
 * Usage: php fix-customer-form.php
 */

class CustomerFormEnhancer {
    
    private $basePath;
    private $formsPath;
    private $backupDir;
    private $filesModified = [];
    private $filesCreated = [];
    
    public function __construct() {
        $this->basePath = realpath(__DIR__);
        $this->formsPath = $this->basePath . '/dashboard/dashboards/cemeteries/forms';
        $this->backupDir = $this->basePath . '/backup_customer_' . date('Y-m-d_His');
        
        echo "\n";
        echo "========================================\n";
        echo "   Customer Form Enhancement Script\n";
        echo "========================================\n";
        echo "📁 נתיב בסיס: {$this->basePath}\n";
        echo "📁 נתיב טפסים: {$this->formsPath}\n";
        echo "\n";
    }
    
    /**
     * הרצת השיפורים
     */
    public function run() {
        try {
            // שלב 1: בדיקות
            $this->validateEnvironment();
            
            // שלב 2: גיבוי בגיט
            $this->saveCurrentGitState();
            
            // שלב 3: גיבוי קבצים
            $this->createBackup();
            
            // שלב 4: עדכון customer-form.php
            $this->updateCustomerForm();
            
            // שלב 5: יצירת API endpoints
            $this->createApiEndpoints();
            
            // שלב 6: עדכון JavaScript
            $this->updateJavaScript();
            
            // שלב 7: יצירת קובץ validation
            $this->createValidationUtils();
            
            // שלב 8: עדכון CSS
            $this->updateStyles();
            
            // שלב 9: Commit ו-Push
            $this->commitAndPush();
            
            // סיכום
            $this->printSummary();
            
        } catch (Exception $e) {
            echo "\n❌ שגיאה: " . $e->getMessage() . "\n";
            $this->rollback();
            exit(1);
        }
    }
    
    /**
     * בדיקת סביבה
     */
    private function validateEnvironment() {
        echo "🔍 בודק סביבה...\n";
        
        if (!is_dir($this->formsPath)) {
            throw new Exception("תיקיית טפסים לא נמצאה: 
{$this->formsPath}");
        }
        
        if (!file_exists($this->formsPath . '/customer-form.php')) {
            throw new Exception("customer-form.php לא נמצא");
        }
        
        // בדוק שיש SmartSelect
        if (!file_exists($this->formsPath . '/SmartSelect.php')) {
            echo "⚠️ SmartSelect.php לא נמצא. מריץ את 
סקריפט ההתקנה...\n";
            exec('php fix-smart-select.php 2>&1', $output, $returnCode);
            if ($returnCode !== 0) {
                throw new Exception("לא ניתן להתקין SmartSelect");
            }
        }
        
        echo "✅ הסביבה מוכנה\n\n";
    }
    
    /**
     * שמירה בגיט
     */
    private function saveCurrentGitState() {
        echo "💾 שומר מצב נוכחי בגיט...\n";
        
        chdir($this->basePath);
        
        exec('git status --porcelain 2>&1', $output, $returnCode);
        
        if (!empty($output)) {
            exec('git add . 2>&1', $output, $returnCode);
            exec('git commit -m "Backup before customer form enhancement" 2>&1', $output, 
$returnCode);
            echo "✅ מצב נוכחי נשמר\n";
        }
        
        echo "\n";
    }
    
    /**
     * יצירת גיבוי
     */
    private function createBackup() {
        echo "📦 יוצר גיבוי...\n";
        
        if (!mkdir($this->backupDir, 0755, true)) {
            throw new Exception("לא ניתן ליצור תיקיית 
גיבוי");
        }
        
        // גבה את customer-form.php
        copy(
            $this->formsPath . '/customer-form.php',
            $this->backupDir . '/customer-form.php.bak'
        );
        
        echo "✅ גיבוי נוצר\n\n";
    }
    
    /**
     * עדכון customer-form.php
     */
    private function updateCustomerForm() {
        echo "📝 מעדכן customer-form.php...\n";
        
        $newContent = '<?php
/**
 * Customer Form - Enhanced Version with Smart Select
 * טופס לקוח משופר עם Smart Select ותכונות 
מתקדמות
 * 
 * @version 2.0.0
 * @updated ' . date('Y-m-d H:i:s') . '
 */

error_reporting(E_ALL);
ini_set(\'display_errors\', 1);
header(\'Content-Type: text/html; charset=utf-8\');

// טעינת קבצים נדרשים
require_once __DIR__ . \'/FormBuilder.php\';
require_once __DIR__ . \'/SmartSelect.php\';
require_once __DIR__ . \'/ValidationUtils.php\';
require_once __DIR__ . \'/FormUtils.php\';
require_once dirname(__DIR__) . \'/config.php\';

// קבלת פרמטרים
$itemId = $_GET[\'itemId\'] ?? $_GET[\'id\'] ?? null;
$parentId = $_GET[\'parentId\'] ?? $_GET[\'parent_id\'] ?? null;
$formType = \'customer\';

try {
    $conn = getDBConnection();
    
    // טעינת מדינות עם מידע נוסף
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
    $countriesAdvanced = [];
    while ($row = $countriesStmt->fetch(PDO::FETCH_ASSOC)) {
        // פורמט פשוט לתאימות לאחור
        $countries[$row[\'unicId\']] = $row[\'countryNameHe\'];
        
        // פורמט מתקדם ל-Smart Select
        $countriesAdvanced[$row[\'unicId\']] = [
            \'text\' => $row[\'countryNameHe\'],
            \'subtitle\' => $row[\'countryNameEn\'],
            \'badge\' => $row[\'cities_count\'] . \' ערים\'
        ];
    }
    
    // טען לקוח אם בעריכה
    $customer = null;
    if ($itemId) {
        $stmt = $conn->prepare("
            SELECT c.*, 
                   cn.countryNameHe, cn.countryNameEn,
                   ct.cityNameHe, ct.cityNameEn
            FROM customers c
            LEFT JOIN countries cn ON c.countryId = cn.unicId
            LEFT JOIN cities ct ON c.cityId = ct.unicId
            WHERE c.id = ? AND c.isActive = 1
        ");
        $stmt->execute([$itemId]);
        $customer = $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
} catch (Exception $e) {
    FormUtils::handleError($e);
}

// יצירת FormBuilder
$formBuilder = new FormBuilder(\'customer\', $itemId, $parentId);

// ========== פרטי זיהוי ==========
$formBuilder->addFieldGroup(\'identification\', \'פרטי זיהוי\', [
    \'layout\' => \'grid-2\',
    \'fields\' => [
        [
            \'name\' => \'typeId\',
            \'type\' => \'select\',
            \'label\' => \'סוג זיהוי\',
            \'options\' => [
                1 => \'ת.ז.\',
                2 => \'דרכון\',
                3 => \'אלמוני\',
                4 => \'תינוק\'
            ],
            \'value\' => $customer[\'typeId\'] ?? 1,
            \'onchange\' => \'CustomerFormHandler.handleIdTypeChange(this.value)\'
        ],
        [
            \'name\' => \'numId\',
            \'type\' => \'text\',
            \'label\' => \'מספר זיהוי\',
            \'required\' => true,
            \'pattern\' => \'[0-9]{9}\',
            \'validation\' => \'israeli_id\',
            \'placeholder\' => \'9 ספרות\',
            \'value\' => $customer[\'numId\'] ?? \'\'
        ]
    ]
]);

// ========== פרטים אישיים ==========
$formBuilder->addFieldGroup(\'personal\', \'פרטים אישיים\', [
    \'layout\' => \'grid-2\',
    \'fields\' => [
        [
            \'name\' => \'firstName\',
            \'type\' => \'text\',
            \'label\' => \'שם פרטי\',
            \'required\' => true,
            \'value\' => $customer[\'firstName\'] ?? \'\'
        ],
        [
            \'name\' => \'lastName\',
            \'type\' => \'text\',
            \'label\' => \'שם משפחה\',
            \'required\' => true,
            \'value\' => $customer[\'lastName\'] ?? \'\'
        ],
        [
            \'name\' => \'nom\',
            \'type\' => \'text\',
            \'label\' => \'כינוי\',
            \'value\' => $customer[\'nom\'] ?? \'\'
        ],
        [
            \'name\' => \'gender\',
            \'type\' => \'select\',
            \'label\' => \'מגדר\',
            \'options\' => [
                \'\' => \'-- בחר --\',
                1 => \'זכר\',
                2 => \'נקבה\'
            ],
            \'value\' => $customer[\'gender\'] ?? \'\'
        ],
        [
            \'name\' => \'dateBirth\',
            \'type\' => \'date\',
            \'label\' => \'תאריך לידה\',
            \'max\' => date(\'Y-m-d\'),
            \'value\' => $customer[\'dateBirth\'] ?? \'\'
        ],
        [
            \'name\' => \'maritalStatus\',
            \'type\' => \'select\',
            \'label\' => \'מצב משפחתי\',
            \'options\' => [
                \'\' => \'-- בחר --\',
                1 => \'רווק/ה\',
                2 => \'נשוי/אה\',
                3 => \'אלמן/ה\',
                4 => \'גרוש/ה\'
            ],
            \'value\' => $customer[\'maritalStatus\'] ?? \'\'
        ]
    ]
]);

// ========== פרטי משפחה ==========
$formBuilder->addFieldGroup(\'family\', \'פרטי משפחה\', [
    \'layout\' => \'grid-2\',
    \'collapsible\' => true,
    \'fields\' => [
        [
            \'name\' => \'nameFather\',
            \'type\' => \'text\',
            \'label\' => \'שם אב\',
            \'value\' => $customer[\'nameFather\'] ?? \'\'
        ],
        [
            \'name\' => \'nameMother\',
            \'type\' => \'text\',
            \'label\' => \'שם אם\',
            \'value\' => $customer[\'nameMother\'] ?? \'\'
        ],
        [
            \'name\' => \'spouse\',
            \'type\' => \'text\',
            \'label\' => \'בן/בת זוג\',
            \'span\' => 2,
            \'value\' => $customer[\'spouse\'] ?? \'\'
        ]
    ]
]);

// ========== כתובת - עם Smart Select ==========
$formBuilder->addFieldGroup(\'address\', \'כתובת\', [
    \'layout\' => \'grid-2\',
    \'fields\' => [
        [
            \'name\' => \'countryId\',
            \'type\' => \'smart_select\',
            \'label\' => \'מדינה\',
            \'searchable\' => true,
            \'placeholder\' => \'הקלד לחיפוש מדינה...\',
            \'display_mode\' => \'advanced\',
            \'options\' => $countriesAdvanced,
            \'value\' => $customer[\'countryId\'] ?? \'\',
            \'onchange\' => \'CustomerFormHandler.handleCountryChange(this.value)\'
        ],
        [
            \'name\' => \'cityId\',
            \'type\' => \'smart_select\',
            \'label\' => \'עיר\',
            \'searchable\' => true,
            \'placeholder\' => \'בחר קודם מדינה...\',
            \'depends_on\' => \'countryId\',
            \'ajax_url\' => \'/dashboard/dashboards/cemeteries/api/get-cities.php\',
            \'min_search_length\' => 2,
            \'display_mode\' => \'advanced\',
            \'value\' => $customer[\'cityId\'] ?? \'\',
            \'onchange\' => \'CustomerFormHandler.handleCityChange(this.value)\'
        ],
        [
            \'name\' => \'address\',
            \'type\' => \'text\',
            \'label\' => \'כתובת מלאה\',
            \'placeholder\' => \'רחוב, מספר בית, דירה\',
            \'span\' => 2,
            \'value\' => $customer[\'address\'] ?? \'\'
        ]
    ]
]);

// ========== פרטי התקשרות ==========
$formBuilder->addFieldGroup(\'contact\', \'פרטי התקשרות\', [
    \'layout\' => \'grid-2\',
    \'fields\' => [
        [
            \'name\' => \'phone\',
            \'type\' => \'tel\',
            \'label\' => \'טלפון\',
            \'pattern\' => \'[0-9-]+\',
            \'format\' => \'0X-XXXXXXX\',
            \'value\' => $customer[\'phone\'] ?? \'\'
        ],
        [
            \'name\' => \'phoneMobile\',
            \'type\' => \'tel\',
            \'label\' => \'טלפון נייד\',
            \'pattern\' => \'05[0-9]-?[0-9]{7}\',
            \'format\' => \'05X-XXXXXXX\',
            \'placeholder\' => \'050-1234567\',
            \'value\' => $customer[\'phoneMobile\'] ?? \'\'
        ]
    ]
]);

// ========== סטטוסים ושיוכים ==========
$formBuilder->addFieldGroup(\'status\', \'סטטוסים\', [
    \'layout\' => \'grid-3\',
    \'fields\' => [
        [
            \'name\' => \'statusCustomer\',
            \'type\' => \'select\',
            \'label\' => \'סטטוס לקוח\',
            \'options\' => [
                1 => \'פעיל\',
                2 => \'רוכש\',
                3 => \'נפטר\'
            ],
            \'value\' => $customer[\'statusCustomer\'] ?? 1
        ],
        [
            \'name\' => \'resident\',
            \'type\' => \'smart_display\',
            \'label\' => \'תושבות\',
            \'display_mode\' => \'badge\',
            \'options\' => [
                1 => [\'text\' => \'ירושלים והסביבה\', \'color\' 
=> \'green\'],
                2 => [\'text\' => \'תושב חוץ\', \'color\' => \'blue\'],
                3 => [\'text\' => \'תושב חו"ל\', \'color\' => \'orange\']
            ],
            \'value\' => $customer[\'resident\'] ?? 3,
            \'readonly\' => true,
            \'help_text\' => \'מחושב אוטומטית לפי העיר\'
        ],
        [
            \'name\' => \'association\',
            \'type\' => \'select\',
            \'label\' => \'שיוך\',
            \'options\' => [
                1 => \'ישראל\',
                2 => \'כהן\',
                3 => \'לוי\'
            ],
            \'value\' => $customer[\'association\'] ?? 1
        ]
    ]
]);

// ========== הערות ==========
$formBuilder->addField(\'comment\', \'הערות\', \'textarea\', [
    \'rows\' => 3,
    \'placeholder\' => \'הערות נוספות...\',
    \'value\' => $customer[\'comment\'] ?? \'\'
]);

// שדה מוסתר - unicId
if ($customer && $customer[\'unicId\']) {
    $formBuilder->addField(\'unicId\', \'\', \'hidden\', [
        \'value\' => $customer[\'unicId\']
    ]);
}

// הצגת הטופס
echo $formBuilder->renderModal();

// הוספת JavaScript Handler
?>
<script>
window.CustomerFormHandler = {
    
    // טיפול בשינוי סוג זיהוי
    handleIdTypeChange: function(typeId) {
        const numIdField = document.getElementById(\'numId\');
        if (!numIdField) return;
        
        switch(parseInt(typeId)) {
            case 1: // ת.ז.
                numIdField.pattern = \'[0-9]{9}\';
                numIdField.placeholder = \'9 ספרות\';
                numIdField.maxLength = 9;
                break;
            case 2: // דרכון
                numIdField.pattern = \'[A-Z0-9]+\';
                numIdField.placeholder = \'מספר דרכון\';
                numIdField.maxLength = 20;
                break;
            case 3: // אלמוני
            case 4: // תינוק
                numIdField.removeAttribute(\'required\');
                numIdField.value = \'000000000\';
                break;
        }
    },
    
    // טיפול בשינוי מדינה
    handleCountryChange: function(countryId) {
        console.log(\'Country changed:\', countryId);
        
        // עדכן את שדה העיר
        const cityField = document.getElementById(\'cityId\');
        if (cityField && window.SmartSelectManager) {
            // נקה את הערים הנוכחיות
            window.SmartSelectManager.updateOptions(\'cityId\', []);
            
            if (countryId) {
                // טען ערים חדשות
                fetch(\'/dashboard/dashboards/cemeteries/api/get-cities.php?countryId=\' + 
countryId)
                    .then(response => response.json())
                    .then(data => {
                        if (data.success && data.cities) {
                            window.SmartSelectManager.updateOptions(\'cityId\', 
data.cities);
                        }
                    })
                    .catch(error => console.error(\'Error loading cities:\', error));
            }
        }
    },
    
    // טיפול בשינוי עיר
    handleCityChange: function(cityId) {
        console.log(\'City changed:\', cityId);
        
        // חשב תושבות אוטומטית
        if (cityId) {
            fetch(\'/dashboard/dashboards/cemeteries/api/calculate-residency.php?cityId=\' 
+ cityId)
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.residency) {
                        // עדכן את שדה התושבות
                        const residentField = document.getElementById(\'resident\');
                        if (residentField) {
                            residentField.value = data.residency;
                            // עדכן את התצוגה
                            this.updateResidencyDisplay(data.residency);
                        }
                    }
                })
                .catch(error => console.error(\'Error calculating residency:\', error));
        }
    },
    
    // עדכון תצוגת תושבות
    updateResidencyDisplay: function(residency) {
        const displayElement = document.querySelector(\'.resident-display\');
        if (!displayElement) return;
        
        const residencyTypes = {
            1: {text: \'ירושלים והסביבה\', color: \'#10b981\'},
            2: {text: \'תושב חוץ\', color: \'#3b82f6\'},
            3: {text: \'תושב חו"ל\', color: \'#f59e0b\'}
        };
        
        const type = residencyTypes[residency] || residencyTypes[3];
        displayElement.innerHTML = `
            <span class="badge" style="background-color: ${type.color}; color: white; 
padding: 4px 12px; border-radius: 4px;">
                ${type.text}
            </span>
        `;
    },
    
    // אתחול
    init: function() {
        // הוסף formatters לשדות טלפון
        const phoneFields = document.querySelectorAll(\'input[type="tel"]\');
        phoneFields.forEach(field => {
            field.addEventListener(\'input\', function(e) {
                let value = e.target.value.replace(/[^0-9]/g, \'\');
                if (value.length > 3 && value.length <= 10) {
                    if (value.startsWith(\'05\')) {
                        // נייד
                        value = value.slice(0, 3) + \'-\' + value.slice(3);
                    } else if (value.startsWith(\'0\')) {
                        // קווי
                        value = value.slice(0, 2) + \'-\' + value.slice(2);
                    }
                }
                e.target.value = value;
            });
        });
        
        // בדיקת תקינות ת.ז.
        const idField = document.getElementById(\'numId\');
        if (idField) {
            idField.addEventListener(\'blur\', function() {
                const typeId = document.getElementById(\'typeId\').value;
                if (typeId == 1) { // ת.ז.
                    if (!ValidationUtils.validateIsraeliId(this.value)) {
                        this.setCustomValidity(\'מספר ת.ז. לא תקין\');
                        this.reportValidity();
                    } else {
                        this.setCustomValidity(\'\');
                    }
                }
            });
        }
    }
};

// אתחול בטעינת הדף
document.addEventListener(\'DOMContentLoaded\', function() {
    CustomerFormHandler.init();
});
</script>
<?php
?>';

        // שמור את הקובץ המעודכן
        $filePath = $this->formsPath . '/customer-form.php';
        if (file_put_contents($filePath, $newContent)) {
            $this->filesModified[] = 'customer-form.php';
            echo "✅ customer-form.php עודכן בהצלחה\n";
        }
    }
    
    /**
     * יצירת API endpoints
     */
    private function createApiEndpoints() {
        echo "📝 יוצר API endpoints...\n";
        
        // יצירת תיקיית API
        $apiPath = dirname($this->formsPath) . '/api';
        if (!is_dir($apiPath)) {
            mkdir($apiPath, 0755, true);
        }
        
        // 1. get-cities.php
        $getCitiesContent = '<?php
/**
 * API: Get Cities by Country
 */
header(\'Content-Type: application/json; charset=utf-8\');
require_once dirname(__DIR__) . \'/config.php\';

try {
    $countryId = $_GET[\'countryId\'] ?? null;
    $search = $_GET[\'q\'] ?? \'\';
    
    $conn = getDBConnection();
    
    $sql = "SELECT 
            c.unicId as value, 
            c.cityNameHe as text,
            cn.countryNameHe as subtitle,
            COUNT(DISTINCT cu.id) as customers_count
        FROM cities c
        LEFT JOIN countries cn ON c.countryId = cn.unicId
        LEFT JOIN customers cu ON cu.cityId = c.unicId AND cu.isActive = 1
        WHERE c.isActive = 1";
    
    $params = [];
    
    if ($countryId) {
        $sql .= " AND c.countryId = :countryId";
        $params[\'countryId\'] = $countryId;
    }
    
    if ($search) {
        $sql .= " AND (c.cityNameHe LIKE :search OR c.cityNameEn LIKE :search)";
        $params[\'search\'] = \'%\' . $search . \'%\';
    }
    
    $sql .= " GROUP BY c.unicId, c.cityNameHe, cn.countryNameHe
              ORDER BY c.cityNameHe";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    
    $cities = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $cities[] = [
            \'value\' => $row[\'value\'],
            \'text\' => $row[\'text\'],
            \'subtitle\' => $row[\'subtitle\'],
            \'badge\' => $row[\'customers_count\'] > 0 ? $row[\'customers_count\'] . \' 
לקוחות\' : \'\'
        ];
    }
    
    echo json_encode([
        \'success\' => true,
        \'cities\' => $cities,
        \'total\' => count($cities)
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        \'success\' => false,
        \'error\' => $e->getMessage()
    ]);
}
?>';
        
        file_put_contents($apiPath . '/get-cities.php', $getCitiesContent);
        $this->filesCreated[] = 'api/get-cities.php';
        
        // 2. calculate-residency.php
        $calculateResidencyContent = '<?php
/**
 * API: Calculate Residency by City
 */
header(\'Content-Type: application/json; charset=utf-8\');
require_once dirname(__DIR__) . \'/config.php\';

try {
    $cityId = $_GET[\'cityId\'] ?? null;
    
    if (!$cityId) {
        throw new Exception(\'City ID required\');
    }
    
    $conn = getDBConnection();
    
    // קבל פרטי העיר והמדינה
    $stmt = $conn->prepare("
        SELECT 
            c.cityNameHe,
            cn.countryNameHe,
            cn.unicId as countryId
        FROM cities c
        JOIN countries cn ON c.countryId = cn.unicId
        WHERE c.unicId = :cityId
    ");
    $stmt->execute([\'cityId\' => $cityId]);
    $city = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$city) {
        throw new Exception(\'City not found\');
    }
    
    // חישוב תושבות
    $residency = 3; // ברירת מחדל - חו"ל
    
    // בדוק אם זה ישראל
    if ($city[\'countryNameHe\'] == \'ישראל\') {
        // בדוק אם זה ירושלים או סביבתה
        $jerusalemArea = [\'ירושלים\', \'בית שמש\', \'מעלה 
אדומים\', \'מבשרת ציון\', \'גבעת זאב\'];
        
        if (in_array($city[\'cityNameHe\'], $jerusalemArea)) {
            $residency = 1; // ירושלים והסביבה
        } else {
            $residency = 2; // תושב חוץ
        }
    }
    
    echo json_encode([
        \'success\' => true,
        \'residency\' => $residency,
        \'city\' => $city[\'cityNameHe\'],
        \'country\' => $city[\'countryNameHe\']
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        \'success\' => false,
        \'error\' => $e->getMessage()
    ]);
}
?>';
        
        file_put_contents($apiPath . '/calculate-residency.php', 
$calculateResidencyContent);
        $this->filesCreated[] = 'api/calculate-residency.php';
        
        echo "✅ API endpoints נוצרו\n";
    }
    
    /**
     * יצירת ValidationUtils
     */
    private function createValidationUtils() {
        echo "📝 יוצר ValidationUtils.php...\n";
        
        $content = '<?php
/**
 * Validation Utilities
 * פונקציות validation לטפסים
 */

class ValidationUtils {
    
    /**
     * בדיקת תקינות ת.ז. ישראלית
     */
    public static function validateIsraeliId($id) {
        $id = trim($id);
        
        // בדוק אורך
        if (strlen($id) != 9) {
            return false;
        }
        
        // בדוק שכולו ספרות
        if (!ctype_digit($id)) {
            return false;
        }
        
        // אלגוריתם בדיקת ת.ז.
        $sum = 0;
        for ($i = 0; $i < 9; $i++) {
            $digit = intval($id[$i]);
            $digit *= (($i % 2) + 1);
            if ($digit > 9) {
                $digit = ($digit / 10) + ($digit % 10);
            }
            $sum += $digit;
        }
        
        return ($sum % 10 == 0);
    }
    
    /**
     * בדיקת תקינות טלפון ישראלי
     */
    public static function validateIsraeliPhone($phone) {
        $phone = preg_replace(\'/[^0-9]/\', \'\', $phone);
        
        // נייד
        if (preg_match(\'/^05[0-9]{8}$/\', $phone)) {
            return true;
        }
        
        // קווי
        if (preg_match(\'/^0[2-9][0-9]{7}$/\', $phone)) {
            return true;
        }
        
        return false;
    }
    
    /**
     * פורמט טלפון
     */
    public static function formatPhone($phone) {
        $phone = preg_replace(\'/[^0-9]/\', \'\', $phone);
        
        if (strlen($phone) == 10 && substr($phone, 0, 2) == \'05\') {
            // נייד: 05X-XXXXXXX
            return substr($phone, 0, 3) . \'-\' . substr($phone, 3);
        }
        
        if (strlen($phone) == 9 && $phone[0] == \'0\') {
            // קווי: 0X-XXXXXXX
            return substr($phone, 0, 2) . \'-\' . substr($phone, 2);
        }
        
        return $phone;
    }
}

// JavaScript validation functions
?>
<script>
window.ValidationUtils = {
    
    validateIsraeliId: function(id) {
        id = id.trim();
        
        if (id.length !== 9) return false;
        if (!/^\d+$/.test(id)) return false;
        
        let sum = 0;
        for (let i = 0; i < 9; i++) {
            let digit = parseInt(id[i]);
            digit *= ((i % 2) + 1);
            if (digit > 9) {
                digit = Math.floor(digit / 10) + (digit % 10);
            }
            sum += digit;
        }
        
        return sum % 10 === 0;
    },
    
    validateIsraeliPhone: function(phone) {
        phone = phone.replace(/[^0-9]/g, \'\');
        
        // נייד
        if (/^05[0-9]{8}$/.test(phone)) return true;
        
        // קווי
        if (/^0[2-9][0-9]{7}$/.test(phone)) return true;
        
        return false;
    },
    
    formatPhone: function(phone) {
        phone = phone.replace(/[^0-9]/g, \'\');
        
        if (phone.length === 10 && phone.startsWith(\'05\')) {
            return phone.slice(0, 3) + \'-\' + phone.slice(3);
        }
        
        if (phone.length === 9 && phone.startsWith(\'0\')) {
            return phone.slice(0, 2) + \'-\' + phone.slice(2);
        }
        
        return phone;
    }
};
</script>
<?php
?>';
        
        file_put_contents($this->formsPath . '/ValidationUtils.php', $content);
        $this->filesCreated[] = 'ValidationUtils.php';
        echo "✅ ValidationUtils.php נוצר\n";
    }
    
    /**
     * עדכון JavaScript
     */
    private function updateJavaScript() {
        echo "📝 מעדכן JavaScript...\n";
        
        // בדוק אם יש קובץ main.js
        $jsPath = dirname($this->formsPath) . '/js';
        if (!is_dir($jsPath)) {
            mkdir($jsPath, 0755, true);
        }
        
        // הוסף customer-form.js
        $jsContent = '/**
 * Customer Form JavaScript Enhancements
 * תוספות JavaScript לטופס הלקוח
 */

(function() {
    "use strict";
    
    // הוסף תמיכה ב-field groups
    document.addEventListener("DOMContentLoaded", function() {
        
        // Collapsible field groups
        const collapsibleGroups = document.querySelectorAll(".field-group.collapsible");
        collapsibleGroups.forEach(group => {
            const legend = group.querySelector("legend");
            if (legend) {
                legend.style.cursor = "pointer";
                legend.addEventListener("click", function() {
                    group.classList.toggle("collapsed");
                });
            }
        });
        
        // Auto-format phone numbers
        const phoneInputs = document.querySelectorAll(\'input[type="tel"]\');
        phoneInputs.forEach(input => {
            input.addEventListener("input", function(e) {
                if (window.ValidationUtils) {
                    e.target.value = window.ValidationUtils.formatPhone(e.target.value);
                }
            });
        });
        
        // Real-time validation
        const form = document.querySelector("#customerForm");
        if (form) {
            form.addEventListener("submit", function(e) {
                // Validate Israeli ID
                const idField = document.getElementById("numId");
                const typeField = document.getElementById("typeId");
                
                if (idField && typeField && typeField.value == "1") {
                    if (!window.ValidationUtils.validateIsraeliId(idField.value)) {
                        e.preventDefault();
                        alert("מספר ת.ז. לא תקין");
                        idField.focus();
                        return false;
                    }
                }
                
                // Validate phones
                const phones = form.querySelectorAll(\'input[type="tel"]\');
                for (let phone of phones) {
                    if (phone.value && 
!window.ValidationUtils.validateIsraeliPhone(phone.value)) {
                        e.preventDefault();
                        alert("מספר טלפון לא תקין: " + 
phone.value);
                        phone.focus();
                        return false;
                    }
                }
            });
        }
    });
    
})();';
        
        file_put_contents($jsPath . '/customer-form.js', $jsContent);
        $this->filesCreated[] = 'js/customer-form.js';
        echo "✅ JavaScript עודכן\n";
    }
    
    /**
     * עדכון CSS
     */
    private function updateStyles() {
        echo "📝 מעדכן CSS...\n";
        
        $cssPath = dirname($this->formsPath) . '/css';
        $cssFile = $cssPath . '/customer-form.css';
        
        $cssContent = '/**
 * Customer Form Styles
 * עיצוב נוסף לטופס הלקוח
 */

/* Field Groups */
.field-group {
    margin-bottom: 2rem;
    padding: 1rem;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    background: #f8fafc;
}

.field-group legend {
    font-weight: 600;
    color: #475569;
    padding: 0 0.5rem;
    background: white;
    border-radius: 4px;
}

.field-group.collapsible legend::before {
    content: "▼ ";
    display: inline-block;
    transition: transform 0.3s;
}

.field-group.collapsible.collapsed legend::before {
    transform: rotate(-90deg);
}

.field-group.collapsible.collapsed .form-fields {
    display: none;
}

/* Grid Layouts */
.grid-2 {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 1rem;
}

.grid-3 {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 1rem;
}

.span-2 {
    grid-column: span 2;
}

.span-3 {
    grid-column: span 3;
}

/* Smart Display Badge */
.smart-display {
    display: inline-block;
    padding: 0.25rem 0.75rem;
    border-radius: 4px;
    font-weight: 500;
    font-size: 0.875rem;
}

.smart-display.badge {
    background: #f3f4f6;
    border: 1px solid #d1d5db;
}

.smart-display.badge.green {
    background: #d1fae5;
    color: #065f46;
    border-color: #6ee7b7;
}

.smart-display.badge.blue {
    background: #dbeafe;
    color: #1e3a8a;
    border-color: #60a5fa;
}

.smart-display.badge.orange {
    background: #fed7aa;
    color: #7c2d12;
    border-color: #fb923c;
}

/* Help Text */
.help-text {
    font-size: 0.75rem;
    color: #6b7280;
    margin-top: 0.25rem;
    font-style: italic;
}

/* Validation States */
.form-control.valid {
    border-color: #10b981;
}

.form-control.invalid {
    border-color: #ef4444;
}

.form-control:focus.valid {
    box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.1);
}

.form-control:focus.invalid {
    box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.1);
}

/* Responsive */
@media (max-width: 768px) {
    .grid-2,
    .grid-3 {
        grid-template-columns: 1fr;
    }
    
    .span-2,
    .span-3 {
        grid-column: span 1;
    }
}

/* Loading State */
.loading-spinner {
    display: inline-block;
    width: 16px;
    height: 16px;
    border: 2px solid #e2e8f0;
    border-top-color: #667eea;
    border-radius: 50%;
    animation: spin 0.8s linear infinite;
}

@keyframes spin {
    to { transform: rotate(360deg); }
}';
        
        file_put_contents($cssFile, $cssContent);
        $this->filesCreated[] = 'css/customer-form.css';
        echo "✅ CSS נוצר\n";
    }
    
    /**
     * Commit ו-Push
     */
    private function commitAndPush() {
        echo "\n💾 שומר בגיט...\n";
        
        chdir($this->basePath);
        
        // Add files
        exec('git add . 2>&1', $output, $returnCode);
        
        // Commit
        $message = 'Customer Form Enhancement - ' . date('Y-m-d H:i:s');
        $message .= "\n\nEnhancements:";
        $message .= "\n- Smart Select for countries and cities";
        $message .= "\n- Field validation (Israeli ID, phones)";
        $message .= "\n- Field groups with better layout";
        $message .= "\n- Auto-formatting for phone numbers";
        $message .= "\n- Dynamic residency calculation";
        $message .= "\n- API endpoints for Ajax loading";
        $message .= "\n\nFiles created: " . implode(', ', $this->filesCreated);
        $message .= "\nFiles modified: " . implode(', ', $this->filesModified);
        
        exec('git commit -m "' . $message . '" 2>&1', $output, $returnCode);
        
        if ($returnCode === 0) {
            echo "✅ Commit בוצע\n";
            
            // Push
            exec('git push origin main 2>&1', $output, $returnCode);
            
            if ($returnCode === 0) {
                echo "✅ Push בוצע\n";
            } else {
                echo "⚠️ Push נכשל, בצע ידנית: git push origin 
main\n";
            }
        }
    }
    
    /**
     * הדפסת סיכום
     */
    private function printSummary() {
        echo "\n";
        echo "========================================\n";
        echo "   ✅ העדכון הושלם בהצלחה!\n";
        echo "========================================\n";
        echo "\n";
        
        echo "📋 **שיפורים שבוצעו:**\n";
        echo "• Smart Select למדינות עם חיפוש ותצוגה 
דו-לשונית\n";
        echo "• Smart Select לערים עם טעינה דינמית\n";
        echo "• Validation לת.ז. ישראלית\n";
        echo "• פורמט אוטומטי לטלפונים\n";
        echo "• חישוב תושבות אוטומטי\n";
        echo "• Field Groups לארגון טוב יותר\n";
        echo "• API endpoints לטעינת נתונים\n";
        echo "\n";
        
        echo "📁 **קבצים שנוצרו:**\n";
        foreach ($this->filesCreated as $file) {
            echo "   • $file\n";
        }
        echo "\n";
        
        echo "📝 **קבצים שעודכנו:**\n";
        foreach ($this->filesModified as $file) {
            echo "   • $file\n";
        }
        echo "\n";
        
        echo "🎯 **השלבים הבאים:**\n";
        echo "1. בדוק את הטופס המעודכן:\n";
        echo "   /dashboard/dashboards/cemeteries/forms/customer-form.php\n";
        echo "\n";
        echo "2. בדוק את ה-API:\n";
        echo "   /dashboard/dashboards/cemeteries/api/get-cities.php\n";
        echo "   /dashboard/dashboards/cemeteries/api/calculate-residency.php\n";
        echo "\n";
        echo "3. במחשב המקומי:\n";
        echo "   cd /Users/malkiel/projects/login/login\n";
        echo "   git pull origin main\n";
        echo "\n";
    }
    
    /**
     * Rollback
     */
    private function rollback() {
        echo "\n⚠️ מבצע rollback...\n";
        
        // החזר מגיבוי
        if (file_exists($this->backupDir . '/customer-form.php.bak')) {
            copy(
                $this->backupDir . '/customer-form.php.bak',
                $this->formsPath . '/customer-form.php'
            );
            echo "✅ הקובץ שוחזר מגיבוי\n";
        }
        
        // מחק קבצים שנוצרו
        foreach ($this->filesCreated as $file) {
            $fullPath = dirname($this->formsPath) . '/' . $file;
            if (file_exists($fullPath)) {
                unlink($fullPath);
            }
        }
    }
}

// הרצת הסקריפט
try {
    $enhancer = new CustomerFormEnhancer();
    $enhancer->run();
} catch (Exception $e) {
    echo "\n❌ שגיאה: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\n✨ הסקריפט הסתיים בהצלחה! ✨\n";
