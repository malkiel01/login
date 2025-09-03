<?php
/**
 * API לחיפוש נפטרים - גרסה פשוטה ללא תלויות
 * search/api/deceased-search.php
 */

// הגדרות בסיסיות
error_reporting(E_ALL);
ini_set('display_errors', 0); // בפרודקשן שנה ל-0

// Headers
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Data-Source');

// טיפול ב-OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// הגדרות מקור הנתונים
define('DATA_SOURCE', 'json'); // 'json' או 'database'
define('JSON_FILE_PATH', __DIR__ . '/../data/data.json');

// הגדרות חיבור לדאטאבייס (אם נבחר database)
$dbConfig = [
    'host' => 'localhost',
    'database' => 'cemetery_db',
    'username' => 'root',
    'password' => '',
    'charset' => 'utf8mb4'
];

/**
 * Class: DataManager - מנהל מקורות נתונים
 */
class DataManager {
    private $dataSource;
    private $data = [];
    private $dbConfig;
    
    public function __construct($source = DATA_SOURCE, $dbConfig = null) {
        $this->dataSource = $source;
        $this->dbConfig = $dbConfig;
        $this->loadData();
    }
    
    /**
     * טעינת נתונים לפי המקור
     */
    private function loadData() {
        try {
            if ($this->dataSource === 'json') {
                $this->loadFromJson();
            } else {
                $this->loadFromDatabase();
            }
        } catch (Exception $e) {
            error_log('Error loading data: ' . $e->getMessage());
            $this->data = [];
        }
    }
    
    /**
     * טעינת נתונים מקובץ JSON
     */
    private function loadFromJson() {
        if (!file_exists(JSON_FILE_PATH)) {
            // אם אין קובץ, נשתמש בנתוני דוגמה
            $this->data = $this->getSampleData();
            return;
        }
        
        $jsonContent = file_get_contents(JSON_FILE_PATH);
        if (!$jsonContent) {
            $this->data = $this->getSampleData();
            return;
        }
        
        $data = json_decode($jsonContent, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log('JSON decode error: ' . json_last_error_msg());
            $this->data = $this->getSampleData();
            return;
        }
        
        // סינון רשומות עם קבורה בלבד
        $this->data = array_filter($data, function($record) {
            return !empty($record['b_burialId']) && $record['b_burialId'] !== null;
        });
    }
    
    /**
     * טעינת נתונים מדאטאבייס
     */
    private function loadFromDatabase() {
        if (!$this->dbConfig) {
            $this->data = $this->getSampleData();
            return;
        }
        
        try {
            $dsn = "mysql:host={$this->dbConfig['host']};dbname={$this->dbConfig['database']};charset={$this->dbConfig['charset']}";
            $pdo = new PDO($dsn, $this->dbConfig['username'], $this->dbConfig['password']);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            $sql = "SELECT * FROM graves WHERE b_burialId IS NOT NULL AND isActive = 1";
            $stmt = $pdo->query($sql);
            $this->data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (PDOException $e) {
            error_log('Database error: ' . $e->getMessage());
            $this->data = $this->getSampleData();
        }
    }
    
    /**
     * נתוני דוגמה
     */
    private function getSampleData() {
        return [
            [
                'graveId' => '1',
                'graveNameHe' => '1',
                'areaGraveNameHe' => '1',
                'lineNameHe' => '1',
                'plotNameHe' => '1',
                'blockNameHe' => '1 - 1',
                'cemeteryNameHe' => 'הר המנוחות גבעת שאול',
                'graveStatus' => '2',
                'b_burialId' => 1,
                'b_dateDeath' => '2010-07-06',
                'b_timeDeath' => '21:00:00',
                'b_dateBurial' => '2010-07-06',
                'b_timeBurial' => '21:00:00',
                'b_serialBurialId' => '9,748',
                'b_buriaLicense' => '156,604',
                'c_customerId' => 1,
                'c_firstName' => 'משה',
                'c_lastName' => 'כהן',
                'c_fullNameHe' => 'משה כהן',
                'c_nameFather' => 'אברהם',
                'c_nameMother' => 'שרה',
                'c_dateBirth' => '1940-05-15',
                'c_dateBirthHe' => 'י״ג אייר ת״ש',
                'p_serialPurchaseId' => '9,748',
                'p_deedNum' => '579',
                'p_purchaseStatus' => 3
            ],
            [
                'graveId' => '2',
                'graveNameHe' => '2',
                'areaGraveNameHe' => '1',
                'lineNameHe' => '2',
                'plotNameHe' => '1',
                'blockNameHe' => '1 - 1',
                'cemeteryNameHe' => 'הר המנוחות גבעת שאול',
                'graveStatus' => '2',
                'b_burialId' => 2,
                'b_dateDeath' => '2015-03-20',
                'b_dateBurial' => '2015-03-21',
                'b_serialBurialId' => '10,123',
                'b_buriaLicense' => '167,890',
                'c_customerId' => 2,
                'c_firstName' => 'רחל',
                'c_lastName' => 'לוי',
                'c_fullNameHe' => 'רחל לוי',
                'c_nameFather' => 'יעקב',
                'c_nameMother' => 'לאה',
                'c_dateBirth' => '1935-08-22',
                'c_dateBirthHe' => 'כ״א אב תרצ״ה'
            ]
        ];
    }
    
    /**
     * קבלת כל הנתונים
     */
    public function getAllData() {
        return array_values($this->data);
    }
}

/**
 * חיפוש פשוט
 */
function handleSearch($dataManager) {
    $query = $_GET['q'] ?? '';
    $limit = min((int)($_GET['limit'] ?? 50), 100);
    $offset = (int)($_GET['offset'] ?? 0);
    
    if (empty($query)) {
        return ['error' => 'חסר פרמטר חיפוש'];
    }
    
    $allData = $dataManager->getAllData();
    $results = [];
    
    // פיצול מילות החיפוש
    $searchTerms = array_filter(explode(' ', trim($query)));
    
    foreach ($allData as $record) {
        // בניית טקסט לחיפוש
        $searchableText = implode(' ', [
            $record['c_firstName'] ?? '',
            $record['c_lastName'] ?? '',
            $record['c_fullNameHe'] ?? '',
            $record['c_nameFather'] ?? '',
            $record['c_nameMother'] ?? '',
            $record['graveNameHe'] ?? '',
            $record['areaGraveNameHe'] ?? '',
            $record['plotNameHe'] ?? '',
            $record['blockNameHe'] ?? '',
            $record['cemeteryNameHe'] ?? ''
        ]);
        
        // בדיקה שכל המילים נמצאות
        $match = true;
        foreach ($searchTerms as $term) {
            if (stripos($searchableText, $term) === false) {
                $match = false;
                break;
            }
        }
        
        if ($match) {
            $results[] = formatRecord($record);
        }
    }
    
    // חישוב סה"כ
    $total = count($results);
    
    // החלת pagination
    $results = array_slice($results, $offset, $limit);
    
    return [
        'total' => $total,
        'limit' => $limit,
        'offset' => $offset,
        'results' => $results
    ];
}

/**
 * חיפוש מתקדם
 */
function handleAdvancedSearch($dataManager) {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) {
        $input = $_POST;
    }
    
    $allData = $dataManager->getAllData();
    $results = [];
    
    $limit = $input['limit'] ?? 50;
    $offset = $input['offset'] ?? 0;
    
    foreach ($allData as $record) {
        $match = true;
        
        // בדיקת שם פרטי
        if (!empty($input['first_name'])) {
            if (stripos($record['c_firstName'] ?? '', $input['first_name']) === false) {
                $match = false;
            }
        }
        
        // בדיקת שם משפחה
        if ($match && !empty($input['last_name'])) {
            if (stripos($record['c_lastName'] ?? '', $input['last_name']) === false) {
                $match = false;
            }
        }
        
        // בדיקת שם אב
        if ($match && !empty($input['father_name'])) {
            if (stripos($record['c_nameFather'] ?? '', $input['father_name']) === false) {
                $match = false;
            }
        }
        
        // בדיקת שם אם
        if ($match && !empty($input['mother_name'])) {
            if (stripos($record['c_nameMother'] ?? '', $input['mother_name']) === false) {
                $match = false;
            }
        }
        
        // בדיקת בית עלמין
        if ($match && !empty($input['cemetery'])) {
            if (stripos($record['cemeteryNameHe'] ?? '', $input['cemetery']) === false) {
                $match = false;
            }
        }
        
        if ($match) {
            $results[] = formatRecord($record);
        }
    }
    
    // חישוב סה"כ
    $total = count($results);
    
    // החלת pagination
    $results = array_slice($results, $offset, $limit);
    
    return [
        'total' => $total,
        'limit' => $limit,
        'offset' => $offset,
        'params' => $input,
        'results' => $results
    ];
}

/**
 * עיצוב רשומה
 */
function formatRecord($record) {
    return [
        'grave' => [
            'id' => $record['graveId'] ?? null,
            'unicId' => $record['graveUnicId'] ?? null,
            'name' => $record['graveNameHe'] ?? null,
            'area' => $record['areaGraveNameHe'] ?? null,
            'line' => $record['lineNameHe'] ?? null,
            'plot' => $record['plotNameHe'] ?? null,
            'block' => $record['blockNameHe'] ?? null,
            'cemetery' => $record['cemeteryNameHe'] ?? null,
            'status' => translateGraveStatus($record['graveStatus'] ?? 0),
            'location' => buildFullLocation($record)
        ],
        'deceased' => [
            'id' => $record['c_customerId'] ?? null,
            'firstName' => $record['c_firstName'] ?? null,
            'lastName' => $record['c_lastName'] ?? null,
            'fullName' => $record['c_fullNameHe'] ?? trim(($record['c_firstName'] ?? '') . ' ' . ($record['c_lastName'] ?? '')),
            'fatherName' => $record['c_nameFather'] ?? null,
            'motherName' => $record['c_nameMother'] ?? null,
            'birthDate' => $record['c_dateBirth'] ?? null,
            'birthDateHe' => $record['c_dateBirthHe'] ?? null
        ],
        'burial' => [
            'id' => $record['b_burialId'] ?? null,
            'serialNumber' => $record['b_serialBurialId'] ?? null,
            'deathDate' => $record['b_dateDeath'] ?? null,
            'deathTime' => $record['b_timeDeath'] ?? null,
            'burialDate' => $record['b_dateBurial'] ?? null,
            'burialTime' => $record['b_timeBurial'] ?? null,
            'burialLicense' => $record['b_buriaLicense'] ?? null
        ],
        'purchase' => [
            'serialNumber' => $record['p_serialPurchaseId'] ?? null,
            'deedNumber' => $record['p_deedNum'] ?? null,
            'status' => translatePurchaseStatus($record['p_purchaseStatus'] ?? 0)
        ]
    ];
}

/**
 * בניית מיקום מלא
 */
function buildFullLocation($record) {
    $parts = [];
    
    if (!empty($record['cemeteryNameHe'])) $parts[] = $record['cemeteryNameHe'];
    if (!empty($record['blockNameHe'])) $parts[] = 'גוש: ' . $record['blockNameHe'];
    if (!empty($record['plotNameHe'])) $parts[] = 'חלקה: ' . $record['plotNameHe'];
    if (!empty($record['lineNameHe']) && $record['lineNameHe'] !== '0') $parts[] = 'שורה: ' . $record['lineNameHe'];
    if (!empty($record['areaGraveNameHe'])) $parts[] = 'אזור: ' . $record['areaGraveNameHe'];
    if (!empty($record['graveNameHe'])) $parts[] = 'קבר: ' . $record['graveNameHe'];
    
    return implode(', ', $parts);
}

/**
 * תרגום סטטוס קבר
 */
function translateGraveStatus($status) {
    $statuses = [
        '1' => 'פנוי',
        '2' => 'תפוס',
        '3' => 'שמור',
        '4' => 'נרכש'
    ];
    return $statuses[$status] ?? 'לא ידוע';
}

/**
 * תרגום סטטוס רכישה
 */
function translatePurchaseStatus($status) {
    $statuses = [
        1 => 'פעיל',
        2 => 'ממתין',
        3 => 'הושלם',
        4 => 'בוטל'
    ];
    return $statuses[$status] ?? 'לא ידוע';
}

/**
 * שליחת תגובת JSON
 */
function sendResponse($code, $message, $data = null) {
    http_response_code($code);
    
    $response = [
        'success' => $code >= 200 && $code < 300,
        'code' => $code,
        'message' => $message,
        'timestamp' => date('Y-m-d H:i:s')
    ];
    
    if ($data !== null) {
        $response['data'] = $data;
    }
    
    echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

// ============================================
// Main API Handler
// ============================================
try {
    // קבלת פרמטרים
    $action = $_GET['action'] ?? 'search';
    $method = $_SERVER['REQUEST_METHOD'];
    
    // יצירת מנהל נתונים
    $dataSource = $_SERVER['HTTP_X_DATA_SOURCE'] ?? DATA_SOURCE;
    $dataManager = new DataManager($dataSource, $dbConfig);
    
    // טיפול בפעולות
    switch ($action) {
        case 'search':
            if ($method === 'GET') {
                // חיפוש פשוט
                $results = handleSearch($dataManager);
                
                if (isset($results['error'])) {
                    sendResponse(400, $results['error']);
                }
                
                sendResponse(200, 'חיפוש הושלם בהצלחה', $results);
                
            } elseif ($method === 'POST') {
                // חיפוש מתקדם
                $results = handleAdvancedSearch($dataManager);
                sendResponse(200, 'חיפוש מתקדם הושלם בהצלחה', $results);
                
            } else {
                sendResponse(405, 'Method not allowed');
            }
            break;
            
        case 'test':
            // בדיקת המערכת
            $testResults = [
                'status' => 'operational',
                'data_source' => $dataSource,
                'timestamp' => date('Y-m-d H:i:s'),
                'php_version' => phpversion(),
                'sample_data_count' => count($dataManager->getAllData())
            ];
            
            // בדיקת קובץ JSON
            if ($dataSource === 'json') {
                $testResults['json_file'] = [
                    'exists' => file_exists(JSON_FILE_PATH),
                    'path' => JSON_FILE_PATH,
                    'readable' => is_readable(JSON_FILE_PATH)
                ];
            }
            
            sendResponse(200, 'המערכת פעילה', $testResults);
            break;
            
        case 'info':
            // מידע על המערכת
            $allData = $dataManager->getAllData();
            
            $info = [
                'total_records' => count($allData),
                'data_source' => $dataSource,
                'api_version' => '2.0'
            ];
            
            // רשימת בתי עלמין
            $cemeteries = [];
            foreach ($allData as $record) {
                if (!empty($record['cemeteryNameHe'])) {
                    $cemeteries[$record['cemeteryNameHe']] = true;
                }
            }
            $info['cemeteries'] = array_keys($cemeteries);
            
            sendResponse(200, 'מידע על המערכת', $info);
            break;
            
        default:
            sendResponse(400, 'פעולה לא חוקית');
    }
    
} catch (Exception $e) {
    error_log('API Error: ' . $e->getMessage());
    sendResponse(500, 'שגיאת שרת', ['error' => $e->getMessage()]);
}
?>