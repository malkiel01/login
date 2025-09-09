<?php
// dashboards/cemeteries/api/purchases-api.php
// API לניהול רכישות

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
        // רשימת כל הרכישות
        case 'list':
            $search = $_GET['search'] ?? '';
            $status = $_GET['status'] ?? '';
            $customer_id = $_GET['customer_id'] ?? '';
            $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
            $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
            $offset = ($page - 1) * $limit;
            $sort = $_GET['sort'] ?? 'purchase_date';
            $order = $_GET['order'] ?? 'DESC';
            
            // בניית השאילתה
            $sql = "
                SELECT 
                    p.*,
                    CONCAT(c.first_name, ' ', c.last_name) as customer_name,
                    c.id_number as customer_id_number,
                    c.phone as customer_phone,
                    g.grave_number,
                    g.grave_location,
                    g.grave_status
                FROM purchases p
                LEFT JOIN customers c ON p.customer_id = c.id
                LEFT JOIN graves g ON p.grave_id = g.id
                WHERE p.is_active = 1
            ";
            $params = [];
            
            // חיפוש
            if ($search) {
                $sql .= " AND (
                    p.id LIKE :search OR 
                    c.first_name LIKE :search OR 
                    c.last_name LIKE :search OR
                    c.id_number LIKE :search OR
                    g.grave_number LIKE :search OR
                    g.grave_location LIKE :search
                )";
                $params['search'] = "%$search%";
            }
            
            // סינון לפי סטטוס
            if ($status) {
                $sql .= " AND p.purchase_status = :status";
                $params['status'] = $status;
            }
            
            // סינון לפי לקוח
            if ($customer_id) {
                $sql .= " AND p.customer_id = :customer_id";
                $params['customer_id'] = $customer_id;
            }
            
            // ספירת סה"כ תוצאות
            $countSql = str_replace("SELECT p.*, CONCAT(c.first_name, ' ', c.last_name) as customer_name, c.id_number as customer_id_number, c.phone as customer_phone, g.grave_number, g.grave_location, g.grave_status", "SELECT COUNT(*) as total", $sql);
            $countStmt = $pdo->prepare($countSql);
            $countStmt->execute($params);
            $total = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
            
            // רשימת עמודות מותרות למיון
            $allowedSortColumns = ['purchase_date', 'amount', 'purchase_status', 'created_at', 'id'];
            if (!in_array($sort, $allowedSortColumns)) {
                $sort = 'purchase_date';
            }
            
            // בדיקת כיוון המיון
            $order = strtoupper($order) === 'ASC' ? 'ASC' : 'DESC';
            
            // הוספת מיון ועימוד
            $sql .= " ORDER BY p.$sort $order LIMIT :limit OFFSET :offset";
            
            $stmt = $pdo->prepare($sql);
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();
            
            $purchases = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'success' => true,
                'data' => $purchases,
                'pagination' => [
                    'total' => $total,
                    'page' => $page,
                    'limit' => $limit,
                    'pages' => ceil($total / $limit)
                ]
            ]);
            break;
            
        // קבלת רכישה בודדת
        case 'get':
            if (!$id) {
                throw new Exception('Purchase ID is required');
            }
            
            $stmt = $pdo->prepare("
                SELECT 
                    p.*,
                    c.first_name, c.last_name, c.id_number, c.phone, c.email,
                    g.grave_number, g.grave_location, g.grave_status
                FROM purchases p
                LEFT JOIN customers c ON p.customer_id = c.id
                LEFT JOIN graves g ON p.grave_id = g.id
                WHERE p.id = :id AND p.is_active = 1
            ");
            $stmt->execute(['id' => $id]);
            $purchase = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$purchase) {
                throw new Exception('Purchase not found');
            }
            
            echo json_encode(['success' => true, 'data' => $purchase]);
            break;
            
        // הוספת רכישה חדשה
        case 'create':
            $data = json_decode(file_get_contents('php://input'), true);
            
            // ולידציה
            if (empty($data['customer_id'])) {
                throw new Exception('לקוח הוא שדה חובה');
            }
            
            if (empty($data['grave_id'])) {
                throw new Exception('קבר הוא שדה חובה');
            }
            
            // בדיקה שהקבר פנוי
            $stmt = $pdo->prepare("SELECT grave_status FROM graves WHERE id = :id AND is_active = 1");
            $stmt->execute(['id' => $data['grave_id']]);
            $grave = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$grave) {
                throw new Exception('הקבר לא נמצא');
            }
            
            if ($grave['grave_status'] != 1) { // 1 = פנוי
                throw new Exception('הקבר אינו פנוי לרכישה');
            }
            
            // בניית השאילתה
            $fields = [
                'customer_id', 'grave_id', 'purchase_date', 'amount',
                'payment_method', 'purchase_status', 'contract_number',
                'notes', 'created_by'
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
            
            // ברירת מחדל לתאריך רכישה
            if (!isset($data['purchase_date'])) {
                $insertFields[] = 'purchase_date';
                $insertValues[] = ':purchase_date';
                $params['purchase_date'] = date('Y-m-d');
            }
            
            // ברירת מחדל לסטטוס
            if (!isset($data['purchase_status'])) {
                $insertFields[] = 'purchase_status';
                $insertValues[] = ':purchase_status';
                $params['purchase_status'] = 1; // טיוטה
            }
            
            $sql = "INSERT INTO purchases (" . implode(', ', $insertFields) . ") 
                    VALUES (" . implode(', ', $insertValues) . ")";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            
            $purchaseId = $pdo->lastInsertId();
            
            // עדכון סטטוס הקבר
            $stmt = $pdo->prepare("UPDATE graves SET grave_status = 2 WHERE id = :id"); // 2 = נרכש
            $stmt->execute(['id' => $data['grave_id']]);
            
            echo json_encode([
                'success' => true,
                'message' => 'הרכישה נוספה בהצלחה',
                'id' => $purchaseId
            ]);
            break;
            
        // עדכון רכישה
        case 'update':
            if (!$id) {
                throw new Exception('Purchase ID is required');
            }
            
            $data = json_decode(file_get_contents('php://input'), true);
            
            // בניית השאילתה
            $fields = [
                'customer_id', 'grave_id', 'purchase_date', 'amount',
                'payment_method', 'purchase_status', 'contract_number',
                'notes', 'updated_by'
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
            
            $sql = "UPDATE purchases SET " . implode(', ', $updateFields) . " WHERE id = :id";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            
            echo json_encode([
                'success' => true,
                'message' => 'הרכישה עודכנה בהצלחה'
            ]);
            break;
            
        // מחיקת רכישה (מחיקה רכה)
        case 'delete':
            if (!$id) {
                throw new Exception('Purchase ID is required');
            }
            
            // קבלת פרטי הרכישה
            $stmt = $pdo->prepare("SELECT grave_id FROM purchases WHERE id = :id AND is_active = 1");
            $stmt->execute(['id' => $id]);
            $purchase = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$purchase) {
                throw new Exception('הרכישה לא נמצאה');
            }
            
            // מחיקה רכה
            $stmt = $pdo->prepare("UPDATE purchases SET is_active = 0 WHERE id = :id");
            $stmt->execute(['id' => $id]);
            
            // עדכון סטטוס הקבר חזרה לפנוי
            if ($purchase['grave_id']) {
                $stmt = $pdo->prepare("UPDATE graves SET grave_status = 1 WHERE id = :id"); // 1 = פנוי
                $stmt->execute(['id' => $purchase['grave_id']]);
            }
            
            echo json_encode([
                'success' => true,
                'message' => 'הרכישה נמחקה בהצלחה'
            ]);
            break;
            
        // סטטיסטיקות רכישות
        case 'stats':
            $stats = [];
            
            // סה"כ רכישות לפי סטטוס
            $stmt = $pdo->query("
                SELECT purchase_status, COUNT(*) as count, SUM(amount) as total
                FROM purchases 
                WHERE is_active = 1 
                GROUP BY purchase_status
            ");
            $stats['by_status'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // רכישות החודש
            $stmt = $pdo->query("
                SELECT COUNT(*) as count, SUM(amount) as total
                FROM purchases 
                WHERE is_active = 1 
                AND MONTH(purchase_date) = MONTH(CURRENT_DATE())
                AND YEAR(purchase_date) = YEAR(CURRENT_DATE())
            ");
            $stats['this_month'] = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // רכישות השנה
            $stmt = $pdo->query("
                SELECT COUNT(*) as count, SUM(amount) as total
                FROM purchases 
                WHERE is_active = 1 
                AND YEAR(purchase_date) = YEAR(CURRENT_DATE())
            ");
            $stats['this_year'] = $stmt->fetch(PDO::FETCH_ASSOC);
            
            echo json_encode(['success' => true, 'data' => $stats]);
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