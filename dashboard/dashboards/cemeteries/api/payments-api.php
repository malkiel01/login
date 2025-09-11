<?php
// dashboards/cemeteries/api/payments-api.php
// API לניהול תשלומים

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type');

// חיבור לבסיס נתונים
require_once $_SERVER['DOCUMENT_ROOT'] . '/dashboard/dashboards/cemeteries/config.php';

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
        // רשימת כל התשלומים
        case 'list':
            $search = $_GET['search'] ?? '';
            $plotType = $_GET['plotType'] ?? '';
            $graveType = $_GET['graveType'] ?? '';
            $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
            $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
            $offset = ($page - 1) * $limit;
            
            // בניית השאילתה
            $sql = "SELECT * FROM payments WHERE isActive = 1";
            $params = [];
            
            // חיפוש
            if ($search) {
                $sql .= " AND (
                    unicId LIKE :search OR 
                    price LIKE :search
                )";
                $params['search'] = "%$search%";
            }
            
            // סינון לפי סוג חלקה
            if ($plotType) {
                $sql .= " AND plotType = :plotType";
                $params['plotType'] = $plotType;
            }
            
            // סינון לפי סוג קבר
            if ($graveType) {
                $sql .= " AND graveType = :graveType";
                $params['graveType'] = $graveType;
            }
            
            // ספירת סה"כ תוצאות
            $countSql = str_replace("SELECT *", "SELECT COUNT(*) as total", $sql);
            $countStmt = $pdo->prepare($countSql);
            $countStmt->execute($params);
            $total = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
            
            // הוספת מיון ועימוד
            $sql .= " ORDER BY createDate DESC LIMIT :limit OFFSET :offset";
            
            $stmt = $pdo->prepare($sql);
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();
            
            $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'success' => true,
                'data' => $payments,
                'pagination' => [
                    'total' => $total,
                    'page' => $page,
                    'limit' => $limit,
                    'pages' => ceil($total / $limit)
                ]
            ]);
            break;
            
        // קבלת תשלום בודד
        case 'get':
            if (!$id) {
                throw new Exception('Payment ID is required');
            }
            
            $stmt = $pdo->prepare("SELECT * FROM payments WHERE id = :id AND isActive = 1");
            $stmt->execute(['id' => $id]);
            $payment = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$payment) {
                throw new Exception('Payment not found');
            }
            
            echo json_encode(['success' => true, 'data' => $payment]);
            break;
            
        // הוספת תשלום חדש
        case 'create':
            $data = json_decode(file_get_contents('php://input'), true);
            
            // ולידציה
            if (empty($data['price'])) {
                throw new Exception('מחיר הוא שדה חובה');
            }
            
            // יצירת unicId אוטומטי
            if (empty($data['unicId'])) {
                $data['unicId'] = 'PAY_' . date('YmdHis') . '_' . rand(1000, 9999);
            }
            
            // בניית השאילתה
            $fields = [
                'plotType', 'graveType', 'resident', 'buyerStatus', 'price',
                'priceDefinition', 'cemeteryId', 'blockId', 'plotId', 'lineId',
                'startPayment', 'unicId'
            ];
            
            $insertFields = ['isActive', 'createDate'];
            $insertValues = [':isActive', ':createDate'];
            $params = [
                'isActive' => 1,
                'createDate' => date('Y-m-d H:i:s')
            ];
            
            foreach ($fields as $field) {
                if (isset($data[$field])) {
                    $insertFields[] = $field;
                    $insertValues[] = ":$field";
                    $params[$field] = $data[$field];
                }
            }
            
            $sql = "INSERT INTO payments (" . implode(', ', $insertFields) . ") 
                    VALUES (" . implode(', ', $insertValues) . ")";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            
            $paymentId = $pdo->lastInsertId();
            
            echo json_encode([
                'success' => true,
                'message' => 'התשלום נוסף בהצלחה',
                'id' => $paymentId
            ]);
            break;
            
        // עדכון תשלום
        case 'update':
            if (!$id) {
                throw new Exception('Payment ID is required');
            }
            
            $data = json_decode(file_get_contents('php://input'), true);
            
            // בניית השאילתה
            $fields = [
                'plotType', 'graveType', 'resident', 'buyerStatus', 'price',
                'priceDefinition', 'cemeteryId', 'blockId', 'plotId', 'lineId',
                'startPayment'
            ];
            
            $updateFields = ['updateDate = :updateDate'];
            $params = [
                'id' => $id,
                'updateDate' => date('Y-m-d H:i:s')
            ];
            
            foreach ($fields as $field) {
                if (isset($data[$field])) {
                    $updateFields[] = "$field = :$field";
                    $params[$field] = $data[$field];
                }
            }
            
            $sql = "UPDATE payments SET " . implode(', ', $updateFields) . " WHERE id = :id";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            
            echo json_encode([
                'success' => true,
                'message' => 'התשלום עודכן בהצלחה'
            ]);
            break;
            
        // מחיקת תשלום (מחיקה רכה)
        case 'delete':
            if (!$id) {
                throw new Exception('Payment ID is required');
            }
            
            $stmt = $pdo->prepare("
                UPDATE payments 
                SET isActive = 0, inactiveDate = :inactiveDate 
                WHERE id = :id
            ");
            $stmt->execute([
                'id' => $id,
                'inactiveDate' => date('Y-m-d H:i:s')
            ]);
            
            echo json_encode([
                'success' => true,
                'message' => 'התשלום נמחק בהצלחה'
            ]);
            break;
            
        // סטטיסטיקות תשלומים
        case 'stats':
            $stats = [];
            
            // סה"כ תשלומים לפי סוג חלקה
            $stmt = $pdo->query("
                SELECT plotType, COUNT(*) as count, SUM(price) as total
                FROM payments 
                WHERE isActive = 1 
                GROUP BY plotType
            ");
            $stats['by_plot_type'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // תשלומים החודש
            $stmt = $pdo->query("
                SELECT COUNT(*) as count, SUM(price) as total
                FROM payments 
                WHERE isActive = 1 
                AND MONTH(createDate) = MONTH(CURRENT_DATE())
                AND YEAR(createDate) = YEAR(CURRENT_DATE())
            ");
            $stats['this_month'] = $stmt->fetch(PDO::FETCH_ASSOC);
            
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