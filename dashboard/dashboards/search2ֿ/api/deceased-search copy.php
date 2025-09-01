<?php
/**
 * API לחיפוש נפטרים
 * api/deceased-search.php
 */

session_start();
require_once '../../config.php';
require_once '../includes/functions.php';

// Headers
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// בדיקת התחברות
if (!isAuthenticated()) {
    sendResponse(401, 'Unauthorized - נדרשת התחברות למערכת');
}

// קבלת נתוני המשתמש המבקש
$requestingUser = getCurrentUser();
$requestingSystem = $_SERVER['HTTP_X_SYSTEM_ID'] ?? 'dashboard';
$userPermissions = getUserPermissions($requestingUser['id']);

// בדיקת הרשאות
if (!hasPermission($userPermissions, 'deceased_search')) {
    sendResponse(403, 'Forbidden - אין לך הרשאה לבצע חיפוש');
}

// ניתוב לפי סוג הבקשה
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? 'search';

switch ($method) {
    case 'GET':
        handleSearch();
        break;
    
    case 'POST':
        if ($action === 'create') {
            handleCreate();
        } else {
            handleAdvancedSearch();
        }
        break;
    
    case 'PUT':
        handleUpdate();
        break;
    
    case 'DELETE':
        handleDelete();
        break;
    
    case 'OPTIONS':
        sendResponse(200, 'OK');
        break;
    
    default:
        sendResponse(405, 'Method Not Allowed');
}

/**
 * חיפוש פשוט - GET
 */
function handleSearch() {
    $query = $_GET['q'] ?? '';
    $limit = min((int)($_GET['limit'] ?? 20), 100);
    $offset = (int)($_GET['offset'] ?? 0);
    
    if (empty($query)) {
        sendResponse(400, 'חסר פרמטר חיפוש');
    }
    
    // לוג פעילות
    logActivity('deceased_search', ['query' => $query]);
    
    // כרגע נשתמש בנתונים מדומים מקובץ JSON
    $results = searchInMockData($query, $limit, $offset);
    
    sendResponse(200, 'חיפוש הושלם בהצלחה', [
        'query' => $query,
        'total' => $results['total'],
        'limit' => $limit,
        'offset' => $offset,
        'results' => $results['data']
    ]);
}

/**
 * חיפוש מתקדם - POST
 */
function handleAdvancedSearch() {
    $input = json_decode(file_get_contents('php://input'), true);
    
    // ולידציה של השדות
    $searchParams = [
        'first_name' => $input['first_name'] ?? '',
        'last_name' => $input['last_name'] ?? '',
        'date_type' => $input['date_type'] ?? 'none', // none, range, estimated
        'from_year' => $input['from_year'] ?? null,
        'to_year' => $input['to_year'] ?? null,
        'estimated_year' => $input['estimated_year'] ?? null,
        'father_name' => $input['father_name'] ?? '',
        'mother_name' => $input['mother_name'] ?? '',
        'city' => $input['city'] ?? '',
        'cemetery' => $input['cemetery'] ?? '',
        'limit' => min((int)($input['limit'] ?? 20), 100),
        'offset' => (int)($input['offset'] ?? 0)
    ];
    
    // חיפוש עם פרמטרים מתקדמים
    $results = advancedSearchInMockData($searchParams);
    
    // לוג פעילות
    logActivity('deceased_advanced_search', $searchParams);
    
    sendResponse(200, 'חיפוש מתקדם הושלם בהצלחה', [
        'params' => $searchParams,
        'total' => $results['total'],
        'results' => $results['data']
    ]);
}

/**
 * יצירת רשומה חדשה - POST
 */
function handleCreate() {
    // בדיקת הרשאות
    if (!hasPermission(getUserPermissions(getCurrentUser()['id']), 'deceased_create')) {
        sendResponse(403, 'אין לך הרשאה ליצור רשומות');
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    // ולידציה
    $requiredFields = ['first_name', 'last_name'];
    foreach ($requiredFields as $field) {
        if (empty($input[$field])) {
            sendResponse(400, "השדה $field הוא חובה");
        }
    }
    
    // בנתיים רק מחזירים הודעת הצלחה
    $newRecord = [
        'id' => uniqid('deceased_'),
        'first_name' => $input['first_name'],
        'last_name' => $input['last_name'],
        'death_date' => $input['death_date'] ?? null,
        'birth_date' => $input['birth_date'] ?? null,
        'father_name' => $input['father_name'] ?? '',
        'mother_name' => $input['mother_name'] ?? '',
        'burial_location' => $input['burial_location'] ?? '',
        'created_at' => date('Y-m-d H:i:s'),
        'created_by' => getCurrentUser()['id']
    ];
    
    logActivity('deceased_created', $newRecord);
    
    sendResponse(201, 'הרשומה נוצרה בהצלחה', $newRecord);
}

/**
 * עדכון רשומה - PUT
 */
function handleUpdate() {
    // בדיקת הרשאות
    if (!hasPermission(getUserPermissions(getCurrentUser()['id']), 'deceased_update')) {
        sendResponse(403, 'אין לך הרשאה לעדכן רשומות');
    }
    
    $id = $_GET['id'] ?? '';
    if (empty($id)) {
        sendResponse(400, 'חסר מזהה רשומה');
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    // בנתיים רק מחזירים הודעת הצלחה
    $updatedRecord = array_merge(
        ['id' => $id],
        $input,
        [
            'updated_at' => date('Y-m-d H:i:s'),
            'updated_by' => getCurrentUser()['id']
        ]
    );
    
    logActivity('deceased_updated', ['id' => $id, 'changes' => $input]);
    
    sendResponse(200, 'הרשומה עודכנה בהצלחה', $updatedRecord);
}

/**
 * מחיקת רשומה - DELETE
 */
function handleDelete() {
    // בדיקת הרשאות
    if (!hasPermission(getUserPermissions(getCurrentUser()['id']), 'deceased_delete')) {
        sendResponse(403, 'אין לך הרשאה למחוק רשומות');
    }
    
    $id = $_GET['id'] ?? '';
    if (empty($id)) {
        sendResponse(400, 'חסר מזהה רשומה');
    }
    
    logActivity('deceased_deleted', ['id' => $id]);
    
    sendResponse(200, 'הרשומה נמחקה בהצלחה', ['id' => $id]);
}

/**
 * חיפוש בנתונים מדומים
 */
function searchInMockData($query, $limit, $offset) {
    $mockData = getMockData();
    $results = [];
    
    // פיצול המילות חיפוש
    $searchTerms = explode(' ', trim($query));
    
    foreach ($mockData as $record) {
        $fullName = $record['first_name'] . ' ' . $record['last_name'];
        $reverseName = $record['last_name'] . ' ' . $record['first_name'];
        
        // בדיקה אם כל המילים נמצאות בשם (בכל סדר)
        $match = true;
        foreach ($searchTerms as $term) {
            if (!empty($term) && 
                stripos($fullName, $term) === false && 
                stripos($reverseName, $term) === false) {
                $match = false;
                break;
            }
        }
        
        if ($match) {
            $results[] = $record;
        }
    }
    
    // חישוב סה"כ לפני החיתוך
    $total = count($results);
    
    // החלת limit ו-offset
    $results = array_slice($results, $offset, $limit);
    
    return [
        'total' => $total,
        'data' => $results
    ];
}

/**
 * חיפוש מתקדם בנתונים מדומים
 */
function advancedSearchInMockData($params) {
    $mockData = getMockData();
    $results = [];
    
    foreach ($mockData as $record) {
        $match = true;
        
        // בדיקת שם פרטי
        if (!empty($params['first_name'])) {
            $firstNames = explode(' ', $record['first_name']);
            $searchNames = explode(' ', $params['first_name']);
            $nameMatch = false;
            
            foreach ($searchNames as $searchName) {
                foreach ($firstNames as $firstName) {
                    if (stripos($firstName, $searchName) !== false) {
                        $nameMatch = true;
                        break 2;
                    }
                }
            }
            
            if (!$nameMatch) $match = false;
        }
        
        // בדיקת שם משפחה
        if ($match && !empty($params['last_name'])) {
            if (stripos($record['last_name'], $params['last_name']) === false) {
                $match = false;
            }
        }
        
        // בדיקת תאריך פטירה לפי סוג
        if ($match && $params['date_type'] !== 'none') {
            $recordYear = (int)substr($record['death_date'], 0, 4);
            
            if ($params['date_type'] === 'range') {
                // טווח תאריכים
                if (!empty($params['from_year']) && $recordYear < (int)$params['from_year']) {
                    $match = false;
                }
                if (!empty($params['to_year']) && $recordYear > (int)$params['to_year']) {
                    $match = false;
                }
            } elseif ($params['date_type'] === 'estimated' && !empty($params['estimated_year'])) {
                // שנה משוערת - טווח של 5 שנים קדימה ואחורה
                $searchYear = (int)$params['estimated_year'];
                if (abs($recordYear - $searchYear) > 5) {
                    $match = false;
                }
            }
        }
        
        // בדיקת עיר
        if ($match && !empty($params['city'])) {
            // כאן צריך להוסיף לוגיקה למיפוי בין עיר לבתי עלמין
            // בינתיים נבדוק אם העיר מוזכרת במיקום הקבורה
            $cityNames = [
                'tel-aviv' => 'תל אביב',
                'jerusalem' => 'ירושלים',
                'haifa' => 'חיפה',
                'beer-sheva' => 'באר שבע',
                'netanya' => 'נתניה',
                'rishon' => 'ראשון',
                'petah-tikva' => 'פתח תקווה',
                'ashdod' => 'אשדוד',
                'bnei-brak' => 'בני ברק',
                'holon' => 'חולון'
            ];
            
            if (isset($cityNames[$params['city']])) {
                if (stripos($record['burial_location'], $cityNames[$params['city']]) === false) {
                    $match = false;
                }
            }
        }
        
        // בדיקת בית עלמין
        if ($match && !empty($params['cemetery'])) {
            $cemeteryNames = [
                'yarkon' => 'ירקון',
                'kiryat-shaul' => 'קרית שאול',
                'nahalat-yitzhak' => 'נחלת יצחק',
                'har-menuchot' => 'הר המנוחות',
                'sanhedria' => 'סנהדריה',
                'har-hazeitim' => 'הר הזיתים',
                // ... ועוד
            ];
            
            if (isset($cemeteryNames[$params['cemetery']])) {
                if (stripos($record['burial_location'], $cemeteryNames[$params['cemetery']]) === false) {
                    $match = false;
                }
            }
        }
        
        // בדיקת שם אב
        if ($match && !empty($params['father_name'])) {
            if (stripos($record['father_name'], $params['father_name']) === false) {
                $match = false;
            }
        }
        
        // בדיקת שם אם
        if ($match && !empty($params['mother_name'])) {
            if (stripos($record['mother_name'], $params['mother_name']) === false) {
                $match = false;
            }
        }
        
        if ($match) {
            $results[] = $record;
        }
    }
    
    // החלת limit ו-offset
    $total = count($results);
    $results = array_slice($results, $params['offset'], $params['limit']);
    
    return [
        'total' => $total,
        'data' => $results
    ];
}

/**
 * קבלת נתונים מדומים מקובץ JSON
 */
function getMockData() {
    $jsonFile = __DIR__ . '/mock-data/deceased.json';
    
    if (!file_exists($jsonFile)) {
        return getDefaultMockData();
    }
    
    $jsonContent = file_get_contents($jsonFile);
    $data = json_decode($jsonContent, true);
    
    return $data ?: getDefaultMockData();
}

/**
 * נתונים מדומים דיפולטיביים
 */
function getDefaultMockData() {
    return [
        [
            'id' => '1',
            'first_name' => 'משה חיים',
            'last_name' => 'כהן',
            'death_date' => '2020-03-15',
            'birth_date' => '1945-07-20',
            'father_name' => 'אברהם',
            'mother_name' => 'שרה',
            'burial_location' => 'בית עלמין ירקון',
            'plot_section' => 'חלקה א',
            'plot_row' => '12',
            'plot_number' => '45'
        ],
        [
            'id' => '2',
            'first_name' => 'חיים',
            'last_name' => 'כהן',
            'death_date' => '2019-11-08',
            'birth_date' => '1932-04-15',
            'father_name' => 'יצחק',
            'mother_name' => 'רבקה',
            'burial_location' => 'בית עלמין ירקון',
            'plot_section' => 'חלקה ב',
            'plot_row' => '8',
            'plot_number' => '23'
        ],
        [
            'id' => '3',
            'first_name' => 'שרה',
            'last_name' => 'לוי',
            'death_date' => '2021-06-22',
            'birth_date' => '1950-09-10',
            'father_name' => 'יעקב',
            'mother_name' => 'לאה',
            'burial_location' => 'בית עלמין כנרת',
            'plot_section' => 'חלקה ג',
            'plot_row' => '15',
            'plot_number' => '67'
        ],
        [
            'id' => '4',
            'first_name' => 'אברהם יוסף',
            'last_name' => 'גולדברג',
            'death_date' => '2018-12-30',
            'birth_date' => '1940-02-28',
            'father_name' => 'משה',
            'mother_name' => 'רחל',
            'burial_location' => 'בית עלמין השרון',
            'plot_section' => 'חלקה ד',
            'plot_row' => '20',
            'plot_number' => '89'
        ],
        [
            'id' => '5',
            'first_name' => 'מרים',
            'last_name' => 'רוזנברג',
            'death_date' => '2022-04-18',
            'birth_date' => '1955-11-05',
            'father_name' => 'דוד',
            'mother_name' => 'אסתר',
            'burial_location' => 'בית עלמין ירקון',
            'plot_section' => 'חלקה ה',
            'plot_row' => '5',
            'plot_number' => '12'
        ]
    ];
}

/**
 * בדיקת הרשאות
 */
function hasPermission($permissions, $action) {
    // בינתיים מחזירים true - להטמיע בהמשך
    return true;
}

/**
 * קבלת הרשאות משתמש
 */
function getUserPermissions($userId) {
    // בינתיים מחזירים הרשאות מלאות - להטמיע בהמשך
    return ['deceased_search', 'deceased_create', 'deceased_update', 'deceased_delete'];
}

/**
 * רישום פעילות
 */
function logActivity($action, $details = []) {
    // בינתיים רק לוג ל-error_log - להטמיע בהמשך במסד נתונים
    error_log(json_encode([
        'timestamp' => date('Y-m-d H:i:s'),
        'user_id' => getCurrentUser()['id'] ?? 'unknown',
        'action' => $action,
        'details' => $details
    ]));
}

/**
 * שליחת תגובה
 */
function sendResponse($code, $message, $data = null) {
    http_response_code($code);
    
    $response = [
        'success' => $code >= 200 && $code < 300,
        'code' => $code,
        'message' => $message,
        'timestamp' => date('Y-m-d H:i:s'),
        'system' => $_SERVER['HTTP_X_SYSTEM_ID'] ?? 'dashboard',
        'user_id' => getCurrentUser()['id'] ?? null
    ];
    
    if ($data !== null) {
        $response['data'] = $data;
    }
    
    echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}