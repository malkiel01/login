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
        // קבלת כל התשלומים
       case 'getMatching1':
            // טען את הקונפיג
            $paymentTypesConfig = require $_SERVER['DOCUMENT_ROOT'] . '/dashboard/dashboards/cemeteries/config/payment-types-config.php';
            $paymentTypes = $paymentTypesConfig['payment_types'];


            $params = json_decode(file_get_contents('php://input'), true);
            
            $sql = "SELECT * FROM payments WHERE isActive = 1";
            $conditions = [];
            $queryParams = [];
            
            // סנן לפי הפרמטרים שהתקבלו
            if (isset($params['plotType'])) {
                $conditions[] = "(plotType = :plotType OR plotType IS NULL)";
                $queryParams['plotType'] = $params['plotType'];
            }
            
            if (isset($params['graveType'])) {
                $conditions[] = "(graveType = :graveType OR graveType IS NULL)";
                $queryParams['graveType'] = $params['graveType'];
            }
            
            if (isset($params['resident'])) {
                $conditions[] = "(resident = :resident OR resident IS NULL)";
                $queryParams['resident'] = $params['resident'];
            }
            
            if (isset($params['buyerStatus'])) {
                $conditions[] = "(buyerStatus = :buyerStatus OR buyerStatus IS NULL)";
                $queryParams['buyerStatus'] = $params['buyerStatus'];
            }
            
            if (count($conditions) > 0) {
                $sql .= " AND " . implode(" AND ", $conditions);
            }
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute($queryParams);
            $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // בתוך הלולאה על התשלומים
            foreach ($payments as &$payment) {
                $typeId = $payment['priceDefinition'];
                
                // קבע אם התשלום הוא חובה מהקונפיג
                $payment['mandatory'] = $paymentTypes[$typeId]['mandatory'] ?? false;
                
                // קבל את השם מהקונפיג
                $payment['name'] = $paymentTypes[$typeId]['name'] ?? 'לא מוגדר';
            }

            
            echo json_encode([
                'success' => true,
                'payments' => $payments
            ]);
            break;
        case 'getMatching':
            error_log("=== START PAYMENT MATCHING ===");
            
            // קבל פרמטרים
            $data = json_decode(file_get_contents('php://input'), true);
            
            $plotType = $data['plotType'] ?? -1;
            $graveType = $data['graveType'] ?? -1;
            $resident = $data['resident'] ?? -1;
            $buyerStatus = $data['buyerStatus'] ?? -1;
            $cemeteryId = $data['cemeteryId'] ?? '-1';
            $blockId = $data['blockId'] ?? '-1';
            $plotId = $data['plotId'] ?? '-1';
            $lineId = $data['lineId'] ?? '-1';
            
            error_log("Parameters received:");
            error_log("- Resident: $resident");
            error_log("- Plot Type: $plotType");
            error_log("- Grave Type: $graveType");
            error_log("- Buyer Status: $buyerStatus");
            error_log("- Cemetery: $cemeteryId");
            error_log("- Block: $blockId");
            error_log("- Plot: $plotId");
            error_log("- Line: $lineId");
            
            // בניית השאילתה עם סדר עדיפויות
            $sql = "
                SELECT p.*, 
                    pd.name as definition_name,
                    pd.mandatory,
                    -- חישוב ציון התאמה (גבוה יותר = יותר ספציפי)
                    (
                        CASE WHEN p.resident = :resident1 THEN 4 WHEN p.resident = -1 THEN 0 ELSE -100 END +
                        CASE WHEN p.plotType = :plotType1 THEN 3 WHEN p.plotType = -1 THEN 0 ELSE -100 END +
                        CASE WHEN p.graveType = :graveType1 THEN 2 WHEN p.graveType = -1 THEN 0 ELSE -100 END +
                        CASE WHEN p.buyerStatus = :buyerStatus1 THEN 1 WHEN p.buyerStatus = -1 OR p.buyerStatus IS NULL THEN 0 ELSE -100 END +
                        CASE WHEN p.lineId = :lineId1 AND p.lineId != '-1' THEN 8 ELSE 0 END +
                        CASE WHEN p.plotId = :plotId1 AND p.plotId != '-1' THEN 4 ELSE 0 END +
                        CASE WHEN p.blockId = :blockId1 AND p.blockId != '-1' THEN 2 ELSE 0 END +
                        CASE WHEN p.cemeteryId = :cemeteryId1 AND p.cemeteryId != '-1' THEN 1 ELSE 0 END
                    ) as match_score
                FROM payments p
                LEFT JOIN payment_definitions pd ON p.priceDefinition = pd.id
                WHERE p.isActive = 1
                AND (p.startPayment IS NULL OR p.startPayment <= CURDATE())
                AND (
                    -- תושבות
                    (p.resident = :resident2 OR p.resident = -1) AND
                    -- סוג חלקה
                    (p.plotType = :plotType2 OR p.plotType = -1) AND
                    -- סוג קבר
                    (p.graveType = :graveType2 OR p.graveType = -1) AND
                    -- סטטוס רוכש
                    (p.buyerStatus = :buyerStatus2 OR p.buyerStatus = -1 OR p.buyerStatus IS NULL) AND
                    -- מיקום - היררכי
                    (
                        -- אם מוגדרת שורה ספציפית
                        (p.lineId = :lineId2 AND p.lineId != '-1') OR
                        -- אם מוגדרת חלקה ספציפית (וכולל את כל השורות שלה)
                        (p.plotId = :plotId2 AND p.lineId = '-1' AND p.plotId != '-1') OR
                        -- אם מוגדר גוש ספציפי (וכולל את כל החלקות שלו)
                        (p.blockId = :blockId2 AND p.plotId = '-1' AND p.blockId != '-1') OR
                        -- אם מוגדר בית עלמין ספציפי (וכולל את כל הגושים שלו)
                        (p.cemeteryId = :cemeteryId2 AND p.blockId = '-1' AND p.cemeteryId != '-1') OR
                        -- או שזה חוק כללי לכולם
                        (p.cemeteryId = '-1' OR p.cemeteryId IS NULL)
                    )
                )
                ORDER BY 
                    match_score DESC,           -- הכי ספציפי קודם
                    p.startPayment DESC,         -- תאריך חדש יותר קודם
                    p.priceDefinition ASC        -- סדר לפי סוג התשלום
            ";
            
            // הכן פרמטרים
            $params = [
                // לחישוב ציון
                ':resident1' => $resident,
                ':plotType1' => $plotType,
                ':graveType1' => $graveType,
                ':buyerStatus1' => $buyerStatus,
                ':cemeteryId1' => $cemeteryId,
                ':blockId1' => $blockId,
                ':plotId1' => $plotId,
                ':lineId1' => $lineId,
                // לסינון
                ':resident2' => $resident,
                ':plotType2' => $plotType,
                ':graveType2' => $graveType,
                ':buyerStatus2' => $buyerStatus,
                ':cemeteryId2' => $cemeteryId,
                ':blockId2' => $blockId,
                ':plotId2' => $plotId,
                ':lineId2' => $lineId
            ];
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $allPayments = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            error_log("Found " . count($allPayments) . " matching payment rules");
            
            // סנן כפילויות - השאר רק את החדש ביותר לכל סוג תשלום
            $uniquePayments = [];
            $seenDefinitions = [];
            
            foreach ($allPayments as $payment) {
                $defId = $payment['priceDefinition'];
                
                // דיבוג
                error_log("Payment: Definition=$defId, Score={$payment['match_score']}, Date={$payment['startPayment']}");
                
                if (!isset($seenDefinitions[$defId])) {
                    $seenDefinitions[$defId] = true;
                    $uniquePayments[] = [
                        'id' => $payment['id'],
                        'name' => $payment['definition_name'] ?? "תשלום מסוג $defId",
                        'price' => $payment['price'],
                        'priceDefinition' => $defId,
                        'mandatory' => $payment['mandatory'] ?? false,
                        'match_score' => $payment['match_score'],
                        'debug' => [
                            'resident' => $payment['resident'],
                            'plotType' => $payment['plotType'],
                            'graveType' => $payment['graveType'],
                            'buyerStatus' => $payment['buyerStatus'],
                            'location' => "{$payment['cemeteryId']}/{$payment['blockId']}/{$payment['plotId']}/{$payment['lineId']}",
                            'startDate' => $payment['startPayment']
                        ]
                    ];
                } else {
                    error_log("- Skipped (duplicate definition)");
                }
            }
            
            error_log("After deduplication: " . count($uniquePayments) . " unique payments");
            error_log("=== END PAYMENT MATCHING ===");
            
            // החזר תוצאות
            echo json_encode([
                'success' => true,
                'payments' => $uniquePayments,
                'debug' => [
                    'total_found' => count($allPayments),
                    'unique_payments' => count($uniquePayments),
                    'parameters' => $data
                ]
            ]);
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