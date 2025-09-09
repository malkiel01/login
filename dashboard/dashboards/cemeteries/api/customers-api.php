<?php
// dashboards/cemeteries/api/customers-api.php
// API לניהול לקוחות

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type');

// חיבור לבסיס נתונים
require_once __DIR__ . '/../config.php';

try {
    $pdo = getDBConnection();
} catch(PDOException $e) {
    die(json_encode(['success' => false, 'error' => 'Connection failed: ' . $e->getMessage()]));
}

// קבלת הפעולה
$action = $_GET['action'] ?? '';
$id = $_GET['id'] ?? null;

try {
    switch ($action) {
        // רשימת כל הלקוחות
        case 'list':
            $search = $_GET['search'] ?? '';
            $status = $_GET['status'] ?? '';
            $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
            $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
            $offset = ($page - 1) * $limit;
            
            // בניית השאילתה
            $sql = "SELECT * FROM customers WHERE is_active = 1";
            $params = [];
            
            // חיפוש
            if ($search) {
                $sql .= " AND (
                    first_name LIKE :search OR 
                    last_name LIKE :search OR 
                    id_number LIKE :search OR 
                    phone LIKE :search OR 
                    mobile_phone LIKE :search OR
                    email LIKE :search
                )";
                $params['search'] = "%$search%";
            }
            
            // סינון לפי סטטוס
            if ($status) {
                $sql .= " AND customer_status = :status";
                $params['status'] = $status;
            }
            
            // ספירת סה"כ תוצאות
            $countSql = str_replace("SELECT *", "SELECT COUNT(*) as total", $sql);
            $countStmt = $pdo->prepare($countSql);
            $countStmt->execute($params);
            $total = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
            
            // הוספת מיון ועימוד
            $sql .= " ORDER BY created_at DESC LIMIT :limit OFFSET :offset";
            
            $stmt = $pdo->prepare($sql);
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();
            
            $customers = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'success' => true,
                'data' => $customers,
                'pagination' => [
                    'total' => $total,
                    'page' => $page,
                    'limit' => $limit,
                    'pages' => ceil($total / $limit)
                ]
            ]);
            break;
            
        // קבלת לקוח בודד
        case 'get':
            if (!$id) {
                throw new Exception('Customer ID is required');
            }
            
            $stmt = $pdo->prepare("SELECT * FROM customers WHERE id = :id AND is_active = 1");
            $stmt->execute(['id' => $id]);
            $customer = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$customer) {
                throw new Exception('Customer not found');
            }
            
            // הוספת נתונים קשורים (רכישות, קבורות)
            $stmt = $pdo->prepare("
                SELECT p.*, g.grave_number, g.grave_location 
                FROM purchases p
                LEFT JOIN graves g ON p.grave_id = g.id
                WHERE p.customer_id = :customer_id AND p.is_active = 1
                ORDER BY p.purchase_date DESC
            ");
            $stmt->execute(['customer_id' => $id]);
            $customer['purchases'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode(['success' => true, 'data' => $customer]);
            break;
            
        // הוספת לקוח חדש
        case 'create':
            $data = json_decode(file_get_contents('php://input'), true);
            
            // ולידציה
            if (empty($data['first_name']) || empty($data['last_name'])) {
                throw new Exception('שם פרטי ושם משפחה הם שדות חובה');
            }
            
            // בדיקת כפל תעודת זהות
            if (!empty($data['id_number'])) {
                $stmt = $pdo->prepare("SELECT id FROM customers WHERE id_number = :id_number AND is_active = 1");
                $stmt->execute(['id_number' => $data['id_number']]);
                if ($stmt->fetch()) {
                    throw new Exception('לקוח עם תעודת זהות זו כבר קיים במערכת');
                }
            }
            
            // חישוב גיל אם יש תאריך לידה
            if (!empty($data['birth_date'])) {
                $birthDate = new DateTime($data['birth_date']);
                $today = new DateTime();
                $data['age'] = $birthDate->diff($today)->y;
            }
            
            // בניית השאילתה
            $fields = [
                'type_id', 'id_number', 'first_name', 'last_name', 'old_name', 'nickname',
                'gender', 'father_name', 'mother_name', 'marital_status', 'birth_date',
                'birth_country', 'age', 'resident_status', 'country', 'city', 'address',
                'phone', 'mobile_phone', 'email', 'customer_status', 'spouse_name', 'comments'
            ];
            
            $insertFields = [];
            $insertValues = [];
            $params = [];
            
            foreach ($fields as $field) {
                if (isset($data[$field])) {
                    $insertFields[] = $field;
                    $insertValues[] = ":$field";
                    $params[$field] = $data[$field];
                }
            }
            
            $sql = "INSERT INTO customers (" . implode(', ', $insertFields) . ") 
                    VALUES (" . implode(', ', $insertValues) . ")";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            
            $customerId = $pdo->lastInsertId();
            
            echo json_encode([
                'success' => true,
                'message' => 'הלקוח נוסף בהצלחה',
                'id' => $customerId
            ]);
            break;
            
        // עדכון לקוח
        case 'update':
            if (!$id) {
                throw new Exception('Customer ID is required');
            }
            
            $data = json_decode(file_get_contents('php://input'), true);
            
            // ולידציה
            if (empty($data['first_name']) || empty($data['last_name'])) {
                throw new Exception('שם פרטי ושם משפחה הם שדות חובה');
            }
            
            // בדיקת כפל תעודת זהות
            if (!empty($data['id_number'])) {
                $stmt = $pdo->prepare("SELECT id FROM customers WHERE id_number = :id_number AND id != :id AND is_active = 1");
                $stmt->execute(['id_number' => $data['id_number'], 'id' => $id]);
                if ($stmt->fetch()) {
                    throw new Exception('לקוח עם תעודת זהות זו כבר קיים במערכת');
                }
            }
            
            // חישוב גיל אם יש תאריך לידה
            if (!empty($data['birth_date'])) {
                $birthDate = new DateTime($data['birth_date']);
                $today = new DateTime();
                $data['age'] = $birthDate->diff($today)->y;
            }
            
            // בניית השאילתה
            $fields = [
                'type_id', 'id_number', 'first_name', 'last_name', 'old_name', 'nickname',
                'gender', 'father_name', 'mother_name', 'marital_status', 'birth_date',
                'birth_country', 'age', 'resident_status', 'country', 'city', 'address',
                'phone', 'mobile_phone', 'email', 'customer_status', 'spouse_name', 'comments'
            ];
            
            $updateFields = [];
            $params = ['id' => $id];
            
            foreach ($fields as $field) {
                if (isset($data[$field])) {
                    $updateFields[] = "$field = :$field";
                    $params[$field] = $data[$field];
                }
            }
            
            if (empty($updateFields)) {
                throw new Exception('No fields to update');
            }
            
            $sql = "UPDATE customers SET " . implode(', ', $updateFields) . " WHERE id = :id";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            
            echo json_encode([
                'success' => true,
                'message' => 'הלקוח עודכן בהצלחה'
            ]);
            break;
            
        // מחיקת לקוח (מחיקה רכה)
        case 'delete':
            if (!$id) {
                throw new Exception('Customer ID is required');
            }
            
            // בדיקה אם יש רכישות או קבורות קשורות
            $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM purchases WHERE customer_id = :id AND is_active = 1");
            $stmt->execute(['id' => $id]);
            $purchases = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
            
            if ($purchases > 0) {
                throw new Exception('לא ניתן למחוק לקוח עם רכישות פעילות');
            }
            
            // מחיקה רכה
            $stmt = $pdo->prepare("UPDATE customers SET is_active = 0 WHERE id = :id");
            $stmt->execute(['id' => $id]);
            
            echo json_encode([
                'success' => true,
                'message' => 'הלקוח נמחק בהצלחה'
            ]);
            break;
            
        // סטטיסטיקות לקוחות
        case 'stats':
            $stats = [];
            
            // סה"כ לקוחות לפי סטטוס
            $stmt = $pdo->query("
                SELECT customer_status, COUNT(*) as count 
                FROM customers 
                WHERE is_active = 1 
                GROUP BY customer_status
            ");
            $stats['by_status'] = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
            
            // סה"כ לקוחות לפי סוג
            $stmt = $pdo->query("
                SELECT type_id, COUNT(*) as count 
                FROM customers 
                WHERE is_active = 1 
                GROUP BY type_id
            ");
            $stats['by_type'] = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
            
            // לקוחות חדשים החודש
            $stmt = $pdo->query("
                SELECT COUNT(*) as count 
                FROM customers 
                WHERE is_active = 1 
                AND created_at >= DATE_SUB(NOW(), INTERVAL 1 MONTH)
            ");
            $stats['new_this_month'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
            
            echo json_encode(['success' => true, 'data' => $stats]);
            break;
            
        // חיפוש מהיר (לאוטוקומפליט)
        case 'search':
            $query = $_GET['q'] ?? '';
            if (strlen($query) < 2) {
                echo json_encode(['success' => true, 'data' => []]);
                break;
            }
            
            $stmt = $pdo->prepare("
                SELECT id, first_name, last_name, id_number, phone, mobile_phone
                FROM customers 
                WHERE is_active = 1 
                AND (
                    first_name LIKE :query OR 
                    last_name LIKE :query OR 
                    id_number LIKE :query OR
                    CONCAT(first_name, ' ', last_name) LIKE :query
                )
                LIMIT 10
            ");
            $stmt->execute(['query' => "%$query%"]);
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode(['success' => true, 'data' => $results]);
            break;
            
        default:
            throw new Exception('Invalid action');
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>