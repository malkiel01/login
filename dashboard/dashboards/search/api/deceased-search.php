<?php
/**
 * API לחיפוש נפטרים - גרסה מעודכנת
 * תומך בקריאה מ-API או מקובץ JSON
 * search/api/deceased-search.php
 */

// ============================================
// הגדרות בסיסיות
// ============================================
error_reporting(E_ALL);
ini_set('display_errors', 0);

// Headers
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// טיפול ב-OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// ============================================
// הגדרות מקור הנתונים
// ============================================
define('DATA_SOURCE', 'json'); // 'json' או 'api'
define('JSON_FILE_PATH', __DIR__ . '/../data/data.json');
define('API_ENDPOINT', 'http://your-api-server.com/api/graves'); // החלף לכתובת ה-API שלך

// ============================================
// Class: DataManager - מנהל מקורות נתונים
// ============================================
class DataManager {
    private $dataSource;
    private $data = [];
    
    public function __construct($source = DATA_SOURCE) {
        $this->dataSource = $source;
        $this->loadData();
    }
    
    /**
     * טעינת נתונים לפי המקור
     */
    private function loadData() {
        if ($this->dataSource === 'json') {
            $this->loadFromJson();
        } else {
            $this->loadFromApi();
        }
    }
    
    /**
     * טעינת נתונים מקובץ JSON
     */
    private function loadFromJson() {
        if (!file_exists(JSON_FILE_PATH)) {
            throw new Exception('קובץ JSON לא נמצא: ' . JSON_FILE_PATH);
        }
        
        $jsonContent = file_get_contents(JSON_FILE_PATH);
        $data = json_decode($jsonContent, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('שגיאה בפענוח JSON: ' . json_last_error_msg());
        }
        
        // סינון רשומות עם קבורה בלבד
        $this->data = array_filter($data, function($record) {
            return !is_null($record['b_burialId']);
        });
    }
    
    /**
     * טעינת נתונים מ-API
     */
    private function loadFromApi() {
        $ch = curl_init(API_ENDPOINT);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 200) {
            throw new Exception('שגיאה בקריאה ל-API: HTTP ' . $httpCode);
        }
        
        $data = json_decode($response, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('שגיאה בפענוח תגובת API: ' . json_last_error_msg());
        }
        
        // סינון רשומות עם קבורה בלבד
        $this->data = array_filter($data, function($record) {
            return !is_null($record['b_burialId']);
        });
    }
    
    /**
     * קבלת כל הנתונים
     */
    public function getAllData() {
        return $this->data;
    }
}

// ============================================
// Class: SearchEngine - מנוע חיפוש
// ============================================
class SearchEngine {
    private $dataManager;
    
    public function __construct(DataManager $dataManager) {
        $this->dataManager = $dataManager;
    }
    
    /**
     * חיפוש כללי
     */
    public function search($query, $limit = 50, $offset = 0) {
        $data = $this->dataManager->getAllData();
        $results = [];
        
        // פיצול מילות החיפוש
        $searchTerms = array_filter(explode(' ', trim($query)));
        
        foreach ($data as $record) {
            if ($this->matchesQuery($record, $searchTerms)) {
                $results[] = $this->formatRecord($record);
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
            'data' => $results
        ];
    }
    
    /**
     * חיפוש מתקדם
     */
    public function advancedSearch($params) {
        $data = $this->dataManager->getAllData();
        $results = [];
        
        $limit = $params['limit'] ?? 50;
        $offset = $params['offset'] ?? 0;
        
        foreach ($data as $record) {
            if ($this->matchesAdvancedCriteria($record, $params)) {
                $results[] = $this->formatRecord($record);
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
            'params' => $params,
            'data' => $results
        ];
    }
    
    /**
     * בדיקת התאמה לשאילתה
     */
    private function matchesQuery($record, $searchTerms) {
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
        foreach ($searchTerms as $term) {
            if (stripos($searchableText, $term) === false) {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * בדיקת התאמה לקריטריונים מתקדמים
     */
    private function matchesAdvancedCriteria($record, $params) {
        // בדיקת שם פרטי
        if (!empty($params['first_name'])) {
            if (stripos($record['c_firstName'] ?? '', $params['first_name']) === false) {
                return false;
            }
        }
        
        // בדיקת שם משפחה
        if (!empty($params['last_name'])) {
            if (stripos($record['c_lastName'] ?? '', $params['last_name']) === false) {
                return false;
            }
        }
        
        // בדיקת שם אב
        if (!empty($params['father_name'])) {
            if (stripos($record['c_nameFather'] ?? '', $params['father_name']) === false) {
                return false;
            }
        }
        
        // בדיקת שם אם
        if (!empty($params['mother_name'])) {
            if (stripos($record['c_nameMother'] ?? '', $params['mother_name']) === false) {
                return false;
            }
        }
        
        // בדיקת תאריך פטירה
        if (!empty($params['death_date'])) {
            if ($record['b_dateDeath'] !== $params['death_date']) {
                return false;
            }
        }
        
        // בדיקת טווח תאריכים
        if (!empty($params['from_date'])) {
            if ($record['b_dateDeath'] < $params['from_date']) {
                return false;
            }
        }
        
        if (!empty($params['to_date'])) {
            if ($record['b_dateDeath'] > $params['to_date']) {
                return false;
            }
        }
        
        // בדיקת בית עלמין
        if (!empty($params['cemetery'])) {
            if (stripos($record['cemeteryNameHe'] ?? '', $params['cemetery']) === false) {
                return false;
            }
        }
        
        // בדיקת חלקה
        if (!empty($params['plot'])) {
            if (stripos($record['plotNameHe'] ?? '', $params['plot']) === false) {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * עיצוב רשומה לפורמט אחיד
     */
    private function formatRecord($record) {
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
                'status' => $this->translateGraveStatus($record['graveStatus'] ?? 0),
                'location' => $this->buildFullLocation($record)
            ],
            'deceased' => [
                'id' => $record['c_customerId'] ?? null,
                'firstName' => $record['c_firstName'] ?? null,
                'lastName' => $record['c_lastName'] ?? null,
                'fullName' => $record['c_fullNameHe'] ?? trim(($record['c_firstName'] ?? '') . ' ' . ($record['c_lastName'] ?? '')),
                'fatherName' => $record['c_nameFather'] ?? null,
                'motherName' => $record['c_nameMother'] ?? null,
                'birthDate' => $record['c_dateBirth'] ?? null,
                'birthDateHe' => $record['c_dateBirthHe'] ?? null,
                'gender' => $record['c_gender'] ?? null,
                'address' => $record['c_address'] ?? null,
                'phone' => $record['c_phone'] ?? null,
                'mobile' => $record['c_phoneMobile'] ?? null
            ],
            'burial' => [
                'id' => $record['b_burialId'] ?? null,
                'serialNumber' => $record['b_serialBurialId'] ?? null,
                'deathDate' => $record['b_dateDeath'] ?? null,
                'deathTime' => $record['b_timeDeath'] ?? null,
                'burialDate' => $record['b_dateBurial'] ?? null,
                'burialTime' => $record['b_timeBurial'] ?? null,
                'deathPlace' => $record['b_placeDeath'] ?? null,
                'burialLicense' => $record['b_buriaLicense'] ?? null
            ],
            'purchase' => [
                'serialNumber' => $record['p_serialPurchaseId'] ?? null,
                'deedNumber' => $record['p_deedNum'] ?? null,
                'status' => $this->translatePurchaseStatus($record['p_purchaseStatus'] ?? 0),
                'price' => $record['p_price'] ?? null
            ]
        ];
    }
    
    /**
     * בניית מיקום מלא
     */
    private function buildFullLocation($record) {
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
    private function translateGraveStatus($status) {
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
    private function translatePurchaseStatus($status) {
        $statuses = [
            1 => 'פעיל',
            2 => 'ממתין',
            3 => 'הושלם',
            4 => 'בוטל'
        ];
        return $statuses[$status] ?? 'לא ידוע';
    }
}

// ============================================
// Main API Handler
// ============================================
try {
    // קבלת פרמטרים
    $action = $_GET['action'] ?? 'search';
    $method = $_SERVER['REQUEST_METHOD'];
    
    // יצירת מנהל נתונים ומנוע חיפוש
    $dataManager = new DataManager();
    $searchEngine = new SearchEngine($dataManager);
    
    // טיפול בפעולות
    switch ($action) {
        case 'search':
            if ($method === 'GET') {
                // חיפוש פשוט
                $query = $_GET['q'] ?? '';
                $limit = min((int)($_GET['limit'] ?? 50), 100);
                $offset = (int)($_GET['offset'] ?? 0);
                
                if (empty($query)) {
                    throw new Exception('חסר פרמטר חיפוש');
                }
                
                $results = $searchEngine->search($query, $limit, $offset);
                sendResponse(200, 'חיפוש הושלם בהצלחה', $results);
                
            } elseif ($method === 'POST') {
                // חיפוש מתקדם
                $input = json_decode(file_get_contents('php://input'), true);
                $results = $searchEngine->advancedSearch($input);
                sendResponse(200, 'חיפוש מתקדם הושלם בהצלחה', $results);
                
            } else {
                sendResponse(405, 'Method not allowed');
            }
            break;
            
        case 'test':
            // בדיקת המערכת
            $testResults = [
                'status' => 'operational',
                'data_source' => DATA_SOURCE,
                'timestamp' => date('Y-m-d H:i:s'),
                'php_version' => phpversion()
            ];
            
            // בדיקת קובץ JSON
            if (DATA_SOURCE === 'json') {
                $testResults['json_file'] = [
                    'exists' => file_exists(JSON_FILE_PATH),
                    'path' => JSON_FILE_PATH,
                    'readable' => is_readable(JSON_FILE_PATH)
                ];
                
                if (file_exists(JSON_FILE_PATH)) {
                    $testResults['json_file']['size'] = filesize(JSON_FILE_PATH) . ' bytes';
                }
            }
            
            sendResponse(200, 'המערכת פעילה', $testResults);
            break;
            
        case 'info':
            // מידע על המערכת
            $dataManager = new DataManager();
            $allData = $dataManager->getAllData();
            
            $info = [
                'total_records' => count($allData),
                'data_source' => DATA_SOURCE,
                'cemeteries' => array_unique(array_column($allData, 'cemeteryNameHe')),
                'api_version' => '2.0'
            ];
            
            sendResponse(200, 'מידע על המערכת', $info);
            break;
            
        default:
            sendResponse(400, 'פעולה לא חוקית');
    }
    
} catch (Exception $e) {
    sendResponse(500, 'שגיאת שרת', ['error' => $e->getMessage()]);
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
?>