<?php
// dashboard/api/index.php - API מרכזי לדשבורד

session_start();
require_once '../../config.php';
require_once '../includes/functions.php';

// הגדרת Headers
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// טיפול ב-OPTIONS request (CORS)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// בדיקת אימות
if (!isset($_SESSION['user_id'])) {
    sendResponse(401, 'Unauthorized');
}

// ניתוב הבקשות
$method = $_SERVER['REQUEST_METHOD'];
$path = isset($_GET['path']) ? $_GET['path'] : '';
$path = trim($path, '/');
$pathParts = explode('/', $path);

// קבלת נתוני הבקשה
$input = json_decode(file_get_contents('php://input'), true);

// ניתוב ראשי
try {
    switch ($pathParts[0]) {
        case 'user':
            handleUserEndpoint($method, $pathParts, $input);
            break;
            
        case 'users':
            handleUsersEndpoint($method, $input);
            break;
            
        case 'stats':
            handleStatsEndpoint($method);
            break;
            
        case 'activity':
            handleActivityEndpoint($method, $input);
            break;
            
        case 'session':
            handleSessionEndpoint($method);
            break;
            
        case 'system':
            handleSystemEndpoint($method);
            break;
            
        default:
            sendResponse(404, 'Endpoint not found');
    }
} catch (Exception $e) {
    sendResponse(500, 'Internal server error', ['error' => $e->getMessage()]);
}

/**
 * טיפול ב-User Endpoints
 */
function handleUserEndpoint($method, $pathParts, $input) {
    $userId = isset($pathParts[1]) ? intval($pathParts[1]) : $_SESSION['user_id'];
    $action = isset($pathParts[2]) ? $pathParts[2] : '';
    
    switch ($method) {
        case 'GET':
            if ($action === 'activity') {
                getUserActivity($userId);
            } else {
                getUser($userId);
            }
            break;
            
        case 'PUT':
            if ($action === 'update') {
                updateUser($userId, $input);
            } else {
                sendResponse(400, 'Invalid action');
            }
            break;
            
        case 'POST':
            if ($pathParts[1] === 'create') {
                createUser($input);
            } else {
                sendResponse(400, 'Invalid action');
            }
            break;
            
        case 'DELETE':
            deleteUser($userId);
            break;
            
        default:
            sendResponse(405, 'Method not allowed');
    }
}

/**
 * קבלת פרטי משתמש
 */
function getUser($userId) {
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("
        SELECT id, username, name, email, auth_type, is_active, 
               profile_picture, created_at, last_login
        FROM users 
        WHERE id = ?
    ");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user) {
        sendResponse(200, 'Success', $user);
    } else {
        sendResponse(404, 'User not found');
    }
}

/**
 * עדכון משתמש
 */
function updateUser($userId, $data) {
    // בדיקת הרשאות - משתמש יכול לעדכן רק את עצמו
    if ($userId != $_SESSION['user_id'] && !checkPermission('admin')) {
        sendResponse(403, 'Forbidden');
    }
    
    $allowedFields = ['name', 'email', 'profile_picture'];
    $updates = [];
    $params = [];
    
    foreach ($allowedFields as $field) {
        if (isset($data[$field])) {
            $updates[] = "$field = ?";
            $params[] = $data[$field];
        }
    }
    
    if (empty($updates)) {
        sendResponse(400, 'No fields to update');
    }
    
    $params[] = $userId;
    
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("
        UPDATE users 
        SET " . implode(', ', $updates) . "
        WHERE id = ?
    ");
    
    if ($stmt->execute($params)) {
        logActivity('profile_update', "Updated user #$userId");
        sendResponse(200, 'User updated successfully');
    } else {
        sendResponse(500, 'Failed to update user');
    }
}

/**
 * יצירת משתמש חדש
 */
function createUser($data) {
    // בדיקת הרשאות
    if (!checkPermission('admin')) {
        sendResponse(403, 'Forbidden');
    }
    
    $required = ['username', 'email', 'password', 'name'];
    foreach ($required as $field) {
        if (!isset($data[$field]) || empty($data[$field])) {
            sendResponse(400, "Missing required field: $field");
        }
    }
    
    $pdo = getDBConnection();
    
    // בדיקה אם המשתמש קיים
    $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
    $stmt->execute([$data['username'], $data['email']]);
    if ($stmt->fetch()) {
        sendResponse(409, 'User already exists');
    }
    
    // יצירת המשתמש
    $hashedPassword = password_hash($data['password'], PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("
        INSERT INTO users (username, email, password, name, auth_type, is_active) 
        VALUES (?, ?, ?, ?, 'local', 1)
    ");
    
    if ($stmt->execute([$data['username'], $data['email'], $hashedPassword, $data['name']])) {
        $userId = $pdo->lastInsertId();
        logActivity('user_created', "Created user #$userId");
        sendResponse(201, 'User created successfully', ['user_id' => $userId]);
    } else {
        sendResponse(500, 'Failed to create user');
    }
}

/**
 * מחיקת משתמש
 */
function deleteUser($userId) {
    // בדיקת הרשאות
    if (!checkPermission('admin')) {
        sendResponse(403, 'Forbidden');
    }
    
    // מניעת מחיקה עצמית
    if ($userId == $_SESSION['user_id']) {
        sendResponse(400, 'Cannot delete yourself');
    }
    
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
    
    if ($stmt->execute([$userId])) {
        logActivity('user_deleted', "Deleted user #$userId");
        sendResponse(200, 'User deleted successfully');
    } else {
        sendResponse(500, 'Failed to delete user');
    }
}

/**
 * טיפול ב-Users Endpoints (רשימה)
 */
function handleUsersEndpoint($method, $input) {
    if ($method !== 'GET') {
        sendResponse(405, 'Method not allowed');
    }
    
    $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 50;
    $offset = isset($_GET['offset']) ? intval($_GET['offset']) : 0;
    $search = isset($_GET['search']) ? $_GET['search'] : '';
    
    $pdo = getDBConnection();
    
    $sql = "SELECT id, username, name, email, auth_type, is_active, 
                   created_at, last_login
            FROM users";
    $params = [];
    
    if ($search) {
        $sql .= " WHERE username LIKE ? OR name LIKE ? OR email LIKE ?";
        $searchTerm = "%$search%";
        $params = [$searchTerm, $searchTerm, $searchTerm];
    }
    
    $sql .= " ORDER BY created_at DESC LIMIT $limit OFFSET $offset";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // קבלת סה"כ משתמשים
    $countSql = "SELECT COUNT(*) as total FROM users";
    if ($search) {
        $countSql .= " WHERE username LIKE ? OR name LIKE ? OR email LIKE ?";
    }
    $countStmt = $pdo->prepare($countSql);
    $countStmt->execute($params);
    $total = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    sendResponse(200, 'Success', [
        'users' => $users,
        'total' => $total,
        'limit' => $limit,
        'offset' => $offset
    ]);
}

/**
 * טיפול בסטטיסטיקות
 */
function handleStatsEndpoint($method) {
    if ($method !== 'GET') {
        sendResponse(405, 'Method not allowed');
    }
    
    $stats = getDashboardStats();
    
    // הוספת נתונים נוספים
    $pdo = getDBConnection();
    
    // משתמשים חדשים השבוע
    $stmt = $pdo->query("
        SELECT COUNT(*) as count 
        FROM users 
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    ");
    $stats['new_users_week'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    // משתמשים לפי סוג
    $stmt = $pdo->query("
        SELECT auth_type, COUNT(*) as count 
        FROM users 
        GROUP BY auth_type
    ");
    $stats['users_by_type'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    sendResponse(200, 'Success', $stats);
}

/**
 * טיפול בלוג פעילות
 */
function handleActivityEndpoint($method, $input) {
    switch ($method) {
        case 'GET':
            $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 20;
            $activities = getActivityLog($limit);
            sendResponse(200, 'Success', $activities);
            break;
            
        case 'POST':
            if (!isset($input['action'])) {
                sendResponse(400, 'Missing action');
            }
            $details = isset($input['details']) ? $input['details'] : null;
            if (logActivity($input['action'], $details)) {
                sendResponse(201, 'Activity logged');
            } else {
                sendResponse(500, 'Failed to log activity');
            }
            break;
            
        default:
            sendResponse(405, 'Method not allowed');
    }
}

/**
 * טיפול בנתוני סשן
 */
function handleSessionEndpoint($method) {
    if ($method !== 'GET') {
        sendResponse(405, 'Method not allowed');
    }
    
    $sessionData = getSessionData();
    $sessionData['duration'] = isset($_SESSION['login_time']) ? 
        time() - $_SESSION['login_time'] : 0;
    
    sendResponse(200, 'Success', $sessionData);
}

/**
 * טיפול במידע מערכת
 */
function handleSystemEndpoint($method) {
    if ($method !== 'GET') {
        sendResponse(405, 'Method not allowed');
    }
    
    // בדיקת הרשאות
    if (!checkPermission('admin')) {
        sendResponse(403, 'Forbidden');
    }
    
    $systemInfo = getSystemInfo();
    sendResponse(200, 'Success', $systemInfo);
}

/**
 * קבלת פעילות משתמש
 */
function getUserActivity($userId) {
    $pdo = getDBConnection();
    
    if (!checkTableExists('activity_logs')) {
        sendResponse(200, 'Success', []);
        return;
    }
    
    $stmt = $pdo->prepare("
        SELECT * FROM activity_logs 
        WHERE user_id = ? 
        ORDER BY created_at DESC 
        LIMIT 50
    ");
    $stmt->execute([$userId]);
    $activities = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    sendResponse(200, 'Success', $activities);
}

/**
 * שליחת תגובה
 */
function sendResponse($code, $message, $data = null) {
    http_response_code($code);
    $response = [
        'success' => $code >= 200 && $code < 300,
        'message' => $message
    ];
    
    if ($data !== null) {
        $response['data'] = $data;
    }
    
    echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}