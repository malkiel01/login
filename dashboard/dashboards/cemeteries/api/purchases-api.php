<?php
// dashboards/cemeteries/api/purchases-api.php
// API לניהול רכישות - מותאם למבנה הטבלה האמיתי

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
        // רשימת כל הרכישות
        case 'list':
            $search = $_GET['search'] ?? '';
            $status = $_GET['status'] ?? '';
            $customer_id = $_GET['customer_id'] ?? '';
            $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
            $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
            $offset = ($page - 1) * $limit;
            $sort = $_GET['sort'] ?? 'createDate';
            $order = $_GET['order'] ?? 'DESC';
            
            // בניית השאילתה
            $sql = "
                SELECT 
                    p.*,
                    CONCAT(c.firstName, ' ', c.lastName) as customer_name,
                    c.numId as customer_id_number,
                    c.phone as customer_phone,
                    c.phoneMobile as customer_mobile,
                    g.graveNameHe as grave_number,
                    g.graveLocation as grave_location,
                    g.graveStatus,
                    ag.areaGraveNameHe,
                    r.lineNameHe,
                    pl.plotNameHe,
                    b.blockNameHe,
                    ce.cemeteryNameHe
                FROM purchases p
                LEFT JOIN customers c ON p.clientId = c.unicId
                LEFT JOIN graves g ON p.graveId = g.unicId
                LEFT JOIN areaGraves ag ON g.areaGraveId = ag.unicId
                LEFT JOIN rows r ON ag.lineId = r.unicId
                LEFT JOIN plots pl ON r.plotId = pl.unicId
                LEFT JOIN blocks b ON pl.blockId = b.unicId
                LEFT JOIN cemeteries ce ON b.cemeteryId = ce.unicId
                WHERE p.isActive = 1
            ";
            $params = [];
            
            // חיפוש
            if ($search) {
                $sql .= " AND (
                    p.id LIKE :search OR 
                    p.serialPurchaseId LIKE :search OR
                    c.firstName LIKE :search OR 
                    c.lastName LIKE :search OR
                    c.numId LIKE :search OR
                    g.graveNameHe LIKE :search
                )";
                $params['search'] = "%$search%";
            }
            
            // סינון לפי סטטוס
            if ($status) {
                $sql .= " AND p.purchaseStatus = :status";
                $params['status'] = $status;
            }
            
            // סינון לפי לקוח
            if ($customer_id) {
                $sql .= " AND p.clientId = :customer_id";
                $params['customer_id'] = $customer_id;
            }
            
            // ספירת סה"כ תוצאות
            $countSql = "SELECT COUNT(*) as total FROM purchases p WHERE p.isActive = 1";
            if (!empty($params)) {
                $countStmt = $pdo->prepare($countSql);
                $countStmt->execute($params);
            } else {
                $countStmt = $pdo->query($countSql);
            }
            $total = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
            
            // רשימת עמודות מותרות למיון
            $allowedSortColumns = ['createDate', 'dateOpening', 'price', 'purchaseStatus', 'id', 'serialPurchaseId'];
            if (!in_array($sort, $allowedSortColumns)) {
                $sort = 'createDate';
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
            
            // הוסף תאימות לאחור
            foreach ($purchases as &$purchase) {
                $purchase['purchase_date'] = $purchase['dateOpening'];
                $purchase['amount'] = $purchase['price'];
                $purchase['purchase_number'] = $purchase['serialPurchaseId'];
                $purchase['purchase_status'] = $purchase['purchaseStatus'];
            }
            
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
                    c.firstName, c.lastName, c.numId, c.phone, c.phoneMobile,
                    g.graveNameHe, g.graveLocation, g.graveStatus
                FROM purchases p
                LEFT JOIN customers c ON p.clientId = c.unicId
                LEFT JOIN graves g ON p.graveId = g.unicId
                WHERE p.unicId = :id AND p.isActive = 1
            ");
            $stmt->execute(['id' => $id]);
            $purchase = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$purchase) {
                throw new Exception('Purchase not found');
            }
            
            // הוסף תאימות
            $purchase['purchase_date'] = $purchase['dateOpening'];
            $purchase['amount'] = $purchase['price'];
            
            echo json_encode(['success' => true, 'data' => $purchase]);
            break;
            
        // הוספת רכישה חדשה
        case 'create':
            $data = json_decode(file_get_contents('php://input'), true);
            
            // ולידציה
            if (empty($data['clientId'])) {
                throw new Exception('לקוח הוא שדה חובה');
            }
            
            if (empty($data['graveId'])) {
                throw new Exception('קבר הוא שדה חובה');
            }
            
            // בדיקה שהקבר פנוי
            $stmt = $pdo->prepare("SELECT graveStatus, graveNameHe FROM graves WHERE unicId = :id AND isActive = 1");
            $stmt->execute(['id' => $data['graveId']]);
            $grave = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$grave) {
                throw new Exception('הקבר לא נמצא');
            }
            
            if ($grave['graveStatus'] != 1) { // 1 = פנוי
                throw new Exception('הקבר אינו פנוי לרכישה');
            }
            
            // יצירת unicId
            if (!isset($data['unicId'])) {
                $data['unicId'] = uniqid('purchase_', true);
            }
            
            // יצירת מספר סידורי לרכישה
            if (!isset($data['serialPurchaseId'])) {
                $year = date('Y');
                $stmt = $pdo->query("SELECT MAX(CAST(SUBSTRING(serialPurchaseId, 6) AS UNSIGNED)) as max_serial 
                                     FROM purchases 
                                     WHERE serialPurchaseId LIKE '$year-%'");
                $maxSerial = $stmt->fetch(PDO::FETCH_ASSOC)['max_serial'] ?? 0;
                $data['serialPurchaseId'] = $year . '-' . str_pad($maxSerial + 1, 5, '0', STR_PAD_LEFT);
            }
            
            // הוספת תאריכים
            $data['createDate'] = date('Y-m-d H:i:s');
            $data['updateDate'] = date('Y-m-d H:i:s');
            
            // ברירת מחדל לתאריך פתיחה
            if (!isset($data['dateOpening'])) {
                $data['dateOpening'] = date('Y-m-d');
            }
            
            // ברירת מחדל לסטטוס
            if (!isset($data['purchaseStatus'])) {
                $data['purchaseStatus'] = 1; // פתוח
            }
            
            // בניית השאילתה
            $fields = [
                'unicId', 'clientId', 'graveId', 'serialPurchaseId', 'purchaseStatus',
                'buyerStatus', 'price', 'numOfPayments', 'PaymentEndDate',
                'refundAmount', 'refundInvoiceNumber', 'contactId', 'dateOpening',
                'ifCertificate', 'deedNum', 'kinship', 'comment', 'createDate', 'updateDate'
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
            
            $sql = "INSERT INTO purchases (" . implode(', ', $insertFields) . ") 
                    VALUES (" . implode(', ', $insertValues) . ")";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            
            $purchaseId = $pdo->lastInsertId();
            
            // עדכון סטטוס הקבר לנרכש
            $stmt = $pdo->prepare("UPDATE graves SET graveStatus = 2 WHERE unicId = :id");
            $stmt->execute(['id' => $data['graveId']]);
            
            // עדכון סטטוס הלקוח לרוכש
            $stmt = $pdo->prepare("UPDATE customers SET statusCustomer = 2 WHERE unicId = :id");
            $stmt->execute(['id' => $data['clientId']]);
            
            echo json_encode([
                'success' => true,
                'message' => 'הרכישה נוספה בהצלחה',
                'id' => $purchaseId,
                'unicId' => $data['unicId']
            ]);
            break;
            
        // עדכון רכישה
        case 'update':
            if (!$id) {
                throw new Exception('Purchase ID is required');
            }
            
            $data = json_decode(file_get_contents('php://input'), true);
            
            // עדכון תאריך
            $data['updateDate'] = date('Y-m-d H:i:s');
            
            // בניית השאילתה
            $fields = [
                'clientId', 'graveId', 'serialPurchaseId', 'purchaseStatus',
                'buyerStatus', 'price', 'numOfPayments', 'PaymentEndDate',
                'refundAmount', 'refundInvoiceNumber', 'contactId', 'dateOpening',
                'ifCertificate', 'deedNum', 'kinship', 'comment', 'updateDate'
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
            $stmt = $pdo->prepare("SELECT graveId, clientId FROM purchases WHERE id = :id AND isActive = 1");
            $stmt->execute(['id' => $id]);
            $purchase = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$purchase) {
                throw new Exception('הרכישה לא נמצאה');
            }
            
            // מחיקה רכה
            $stmt = $pdo->prepare("UPDATE purchases SET isActive = 0, inactiveDate = :date WHERE id = :id");
            $stmt->execute(['id' => $id, 'date' => date('Y-m-d H:i:s')]);
            
            // עדכון סטטוס הקבר חזרה לפנוי
            if ($purchase['graveId']) {
                $stmt = $pdo->prepare("UPDATE graves SET graveStatus = 1 WHERE unicId = :id");
                $stmt->execute(['id' => $purchase['graveId']]);
            }
            
            // בדוק אם ללקוח יש רכישות אחרות
            if ($purchase['clientId']) {
                $stmt = $pdo->prepare("
                    SELECT COUNT(*) FROM purchases 
                    WHERE clientId = :clientId AND id != :purchaseId AND isActive = 1
                ");
                $stmt->execute(['clientId' => $purchase['clientId'], 'purchaseId' => $id]);
                
                // אם אין לו רכישות אחרות, החזר אותו לסטטוס פעיל
                if ($stmt->fetchColumn() == 0) {
                    $stmt = $pdo->prepare("UPDATE customers SET statusCustomer = 1 WHERE unicId = :id");
                    $stmt->execute(['id' => $purchase['clientId']]);
                }
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
                SELECT purchaseStatus, COUNT(*) as count, SUM(price) as total
                FROM purchases 
                WHERE isActive = 1 
                GROUP BY purchaseStatus
            ");
            $stats['by_status'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // רכישות החודש
            $stmt = $pdo->query("
                SELECT COUNT(*) as count, SUM(price) as total
                FROM purchases 
                WHERE isActive = 1 
                AND MONTH(dateOpening) = MONTH(CURRENT_DATE())
                AND YEAR(dateOpening) = YEAR(CURRENT_DATE())
            ");
            $stats['this_month'] = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // רכישות השנה
            $stmt = $pdo->query("
                SELECT COUNT(*) as count, SUM(price) as total
                FROM purchases 
                WHERE isActive = 1 
                AND YEAR(dateOpening) = YEAR(CURRENT_DATE())
            ");
            $stats['this_year'] = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // סה"כ רכישות פעילות
            $stmt = $pdo->query("
                SELECT 
                    COUNT(*) as total_purchases,
                    COUNT(DISTINCT clientId) as total_customers,
                    COUNT(DISTINCT graveId) as total_graves,
                    SUM(price) as total_revenue
                FROM purchases 
                WHERE isActive = 1
            ");
            $stats['totals'] = $stmt->fetch(PDO::FETCH_ASSOC);
            
            echo json_encode(['success' => true, 'data' => $stats]);
            break;
            
        // חיפוש מהיר לאוטוקומפליט
        case 'search':
            $query = $_GET['q'] ?? '';
            if (strlen($query) < 2) {
                echo json_encode(['success' => true, 'data' => []]);
                break;
            }
            
            $stmt = $pdo->prepare("
                SELECT 
                    p.id, p.serialPurchaseId, p.dateOpening, p.price,
                    CONCAT(c.firstName, ' ', c.lastName) as customer_name,
                    g.graveNameHe as grave_name
                FROM purchases p
                LEFT JOIN customers c ON p.clientId = c.unicId
                LEFT JOIN graves g ON p.graveId = g.unicId
                WHERE p.isActive = 1 
                AND (
                    p.serialPurchaseId LIKE :query OR 
                    c.firstName LIKE :query OR 
                    c.lastName LIKE :query OR
                    c.numId LIKE :query OR
                    g.graveNameHe LIKE :query
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