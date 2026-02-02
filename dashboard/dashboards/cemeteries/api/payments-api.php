<?php
// dashboards/cemeteries/api/payments-api.php
// API לניהול תשלומים

// אימות והרשאות - חייב להיות מחובר!
require_once __DIR__ . '/api-auth.php';
require_once __DIR__ . '/services/EntityApprovalService.php';

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
            requireViewPermission('payments');
            $search = $_GET['search'] ?? '';
            $plotType = $_GET['plotType'] ?? '';
            $graveType = $_GET['graveType'] ?? '';
            $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
            $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
            $offset = ($page - 1) * $limit;

            // ⭐ פרמטרי מיון
            $allowedSortColumns = ['price', 'plotType', 'graveType', 'createDate'];
            $orderBy = $_GET['orderBy'] ?? 'createDate';
            $sortDirection = strtoupper($_GET['sortDirection'] ?? 'DESC');
            if (!in_array($orderBy, $allowedSortColumns)) $orderBy = 'createDate';
            if (!in_array($sortDirection, ['ASC', 'DESC'])) $sortDirection = 'DESC';

            // ⭐ מיון רב-שלבי - תמיכה במערך של רמות מיון
            $sortLevelsParam = $_GET['sortLevels'] ?? null;
            $orderByClause = "{$orderBy} {$sortDirection}"; // ברירת מחדל

            if ($sortLevelsParam) {
                $sortLevels = is_string($sortLevelsParam) ? json_decode($sortLevelsParam, true) : $sortLevelsParam;
                if (is_array($sortLevels) && count($sortLevels) > 0) {
                    $orderByClauses = [];
                    foreach ($sortLevels as $level) {
                        $field = $level['field'] ?? '';
                        $levelOrder = strtoupper($level['order'] ?? 'ASC') === 'ASC' ? 'ASC' : 'DESC';
                        if (in_array($field, $allowedSortColumns)) {
                            $orderByClauses[] = "{$field} {$levelOrder}";
                        }
                    }
                    if (count($orderByClauses) > 0) {
                        $orderByClause = implode(', ', $orderByClauses);
                    }
                }
            }

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
            
            // ⭐ הוספת מיון ועימוד
            $sql .= " ORDER BY {$orderByClause} LIMIT :limit OFFSET :offset";
            
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
            requireViewPermission('payments');
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
        case 'getMatching2':
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
                    (p.resident = :resident2 OR p.resident = -1 OR p.resident IS NULL) AND
                    -- סוג חלקה
                    (p.plotType = :plotType2 OR p.plotType = -1 OR p.plotType IS NULL) AND
                    -- סוג קבר
                    (p.graveType = :graveType2 OR p.graveType = -1 OR p.graveType IS NULL) AND
                    -- סטטוס רוכש
                    (p.buyerStatus = :buyerStatus2 OR p.buyerStatus = -1 OR p.buyerStatus IS NULL) AND
                    -- מיקום - היררכי
                    (
                        -- אם מוגדרת שורה ספציפית
                        (p.lineId = :lineId2 AND p.lineId != '-1' AND p.lineId IS NOT NULL) OR
                        -- אם מוגדרת חלקה ספציפית (וכולל את כל השורות שלה)
                        (p.plotId = :plotId2 AND (p.lineId = '-1' OR p.lineId IS NULL) AND p.plotId != '-1' AND p.plotId IS NOT NULL) OR
                        -- אם מוגדר גוש ספציפי (וכולל את כל החלקות שלו)
                        (p.blockId = :blockId2 AND (p.plotId = '-1' OR p.plotId IS NULL) AND p.blockId != '-1' AND p.blockId IS NOT NULL) OR
                        -- אם מוגדר בית עלמין ספציפי (וכולל את כל הגושים שלו)
                        (p.cemeteryId = :cemeteryId2 AND (p.blockId = '-1' OR p.blockId IS NULL) AND p.cemeteryId != '-1' AND p.cemeteryId IS NOT NULL) OR
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
            
            try {
                // שאילתה פשוטה יותר
                $sql = "
                    SELECT p.*
                    FROM payments p
                    WHERE p.isActive = 1
                    AND (p.startPayment IS NULL OR p.startPayment <= CURDATE())
                    AND (p.resident = :resident OR p.resident = -1 OR p.resident IS NULL)
                    AND (p.plotType = :plotType OR p.plotType = -1 OR p.plotType IS NULL)
                    AND (p.graveType = :graveType OR p.graveType = -1 OR p.graveType IS NULL)
                    AND (p.buyerStatus = :buyerStatus OR p.buyerStatus = -1 OR p.buyerStatus IS NULL)
                    AND (
                        (p.lineId = :lineId AND p.lineId != '-1' AND p.lineId IS NOT NULL) OR
                        (p.plotId = :plotId AND (p.lineId = '-1' OR p.lineId IS NULL) AND p.plotId != '-1' AND p.plotId IS NOT NULL) OR
                        (p.blockId = :blockId AND (p.plotId = '-1' OR p.plotId IS NULL) AND p.blockId != '-1' AND p.blockId IS NOT NULL) OR
                        (p.cemeteryId = :cemeteryId AND (p.blockId = '-1' OR p.blockId IS NULL) AND p.cemeteryId != '-1' AND p.cemeteryId IS NOT NULL) OR
                        (p.cemeteryId = '-1' OR p.cemeteryId IS NULL)
                    )
                    ORDER BY 
                        p.startPayment DESC,
                        p.id DESC
                ";
                
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    ':resident' => $resident,
                    ':plotType' => $plotType,
                    ':graveType' => $graveType,
                    ':buyerStatus' => $buyerStatus,
                    ':cemeteryId' => $cemeteryId,
                    ':blockId' => $blockId,
                    ':plotId' => $plotId,
                    ':lineId' => $lineId
                ]);
                
                $allPayments = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                error_log("Found " . count($allPayments) . " matching payment rules");
                
                // סנן כפילויות - השאר רק את החדש ביותר לכל סוג תשלום
                $uniquePayments = [];
                $seenDefinitions = [];
                
                foreach ($allPayments as $payment) {
                    $defId = $payment['priceDefinition'];

                    if (!isset($seenDefinitions[$defId])) {
                        $seenDefinitions[$defId] = true;

                        // קבל שם התשלום לפי סוג ההגדרה
                        $defaultNames = [
                            1 => 'עלות קבר',
                            2 => 'שירותי לוויה',
                            3 => 'שירותי קבורה',
                            4 => 'אגרת מצבה',
                            5 => 'בדיקת עומק',
                            6 => 'פירוק מצבה',
                            7 => 'הובלה מנתב״ג',
                            8 => 'טהרה',
                            9 => 'תכריכים',
                            10 => 'החלפת שם'
                        ];
                        $definitionName = $defaultNames[$defId] ?? "תשלום מסוג $defId";

                        // השתמש בשדה mandatory מהרשומה עצמה!
                        $mandatory = isset($payment['mandatory']) ? (int)$payment['mandatory'] : 1;

                        $uniquePayments[] = [
                            'id' => $payment['id'],
                            'name' => $definitionName,
                            'price' => $payment['price'],
                            'priceDefinition' => $defId,
                            'mandatory' => $mandatory
                        ];

                        error_log("Added payment: $definitionName, Price: {$payment['price']}, Mandatory: $mandatory");
                    }
                }
                
                error_log("After deduplication: " . count($uniquePayments) . " unique payments");
                error_log("=== END PAYMENT MATCHING ===");
                
                // החזר תוצאות
                echo json_encode([
                    'success' => true,
                    'payments' => $uniquePayments
                ]);
                
            } catch (Exception $e) {
                error_log("ERROR in getMatching: " . $e->getMessage());
                error_log("SQL: " . $sql);
                
                echo json_encode([
                    'success' => false,
                    'error' => 'שגיאה בטעינת חוקי תשלום: ' . $e->getMessage()
                ]);
            }
            break;
        // הוספת תשלום חדש
        case 'create':
            requireCreatePermission('payments');
            $data = json_decode(file_get_contents('php://input'), true);

            // ולידציה
            if (empty($data['price'])) {
                throw new Exception('מחיר הוא שדה חובה');
            }

            // יצירת unicId אוטומטי
            if (empty($data['unicId'])) {
                $data['unicId'] = 'PAY_' . date('YmdHis') . '_' . rand(1000, 9999);
            }

            // === בדיקת אישור מורשה חתימה ===
            $approvalService = EntityApprovalService::getInstance($pdo);
            $currentUserId = getCurrentUserId();
            $isAuthorizer = $approvalService->isAuthorizer($currentUserId, 'payments', 'create');

            if (!$isAuthorizer && $approvalService->userNeedsApproval($currentUserId, 'payments', 'create')) {
                $result = $approvalService->createPendingOperation([
                    'entity_type' => 'payments',
                    'action' => 'create',
                    'operation_data' => $data,
                    'requested_by' => $currentUserId
                ]);

                echo json_encode([
                    'success' => true,
                    'pending' => true,
                    'pendingId' => $result['pendingId'],
                    'message' => 'הבקשה נשלחה לאישור מורשה חתימה',
                    'expiresAt' => $result['expiresAt']
                ]);
                break;
            }
            // === סוף בדיקת אישור ===

            // בניית השאילתה
            $fields = [
                'plotType', 'graveType', 'resident', 'buyerStatus', 'price',
                'priceDefinition', 'cemeteryId', 'blockId', 'plotId', 'lineId',
                'startPayment', 'unicId', 'mandatory'
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
            requireEditPermission('payments');
            $data = json_decode(file_get_contents('php://input'), true);

            // קבל id מה-query string או מה-body
            if (!$id && isset($data['id'])) {
                $id = $data['id'];
            }

            if (!$id) {
                throw new Exception('Payment ID is required');
            }

            // === בדיקת כפילויות לפני יצירת pending ===
            $stmt = $pdo->prepare("
                SELECT id, action FROM pending_entity_operations
                WHERE entity_type = 'payments'
                  AND action IN ('edit', 'delete')
                  AND entity_id = ?
                  AND status = 'pending'
            ");
            $stmt->execute([$id]);
            $existingPending = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($existingPending) {
                $actionLabel = $existingPending['action'] === 'edit' ? 'עריכה' : 'מחיקה';
                throw new Exception('כבר קיימת בקשה ממתינה ל' . $actionLabel . ' של תשלום זה (מזהה: ' . $existingPending['id'] . ')');
            }
            // === סוף בדיקת כפילויות ===

            // === בדיקת אישור מורשה חתימה ===
            $approvalService = EntityApprovalService::getInstance($pdo);
            $currentUserId = getCurrentUserId();
            $isAuthorizer = $approvalService->isAuthorizer($currentUserId, 'payments', 'edit');

            if (!$isAuthorizer && $approvalService->userNeedsApproval($currentUserId, 'payments', 'edit')) {
                // שמירת המידע המקורי
                $stmt = $pdo->prepare("SELECT * FROM payments WHERE id = :id");
                $stmt->execute(['id' => $id]);
                $originalData = $stmt->fetch(PDO::FETCH_ASSOC);

                $result = $approvalService->createPendingOperation([
                    'entity_type' => 'payments',
                    'action' => 'edit',
                    'entity_id' => $id,
                    'operation_data' => $data,
                    'original_data' => $originalData,
                    'requested_by' => $currentUserId
                ]);

                echo json_encode([
                    'success' => true,
                    'pending' => true,
                    'pendingId' => $result['pendingId'],
                    'message' => 'הבקשה נשלחה לאישור מורשה חתימה',
                    'expiresAt' => $result['expiresAt']
                ]);
                break;
            }
            // === סוף בדיקת אישור ===

            // בניית השאילתה
            $fields = [
                'plotType', 'graveType', 'resident', 'buyerStatus', 'price',
                'priceDefinition', 'cemeteryId', 'blockId', 'plotId', 'lineId',
                'startPayment', 'mandatory'
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
            requireDeletePermission('payments');
            if (!$id) {
                throw new Exception('Payment ID is required');
            }

            // קבלת פרטי התשלום
            $stmt = $pdo->prepare("SELECT * FROM payments WHERE id = :id AND isActive = 1");
            $stmt->execute(['id' => $id]);
            $payment = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$payment) {
                throw new Exception('התשלום לא נמצא');
            }

            // === בדיקת כפילויות לפני יצירת pending ===
            $stmt = $pdo->prepare("
                SELECT id, action FROM pending_entity_operations
                WHERE entity_type = 'payments'
                  AND action IN ('edit', 'delete')
                  AND entity_id = ?
                  AND status = 'pending'
            ");
            $stmt->execute([$id]);
            $existingPending = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($existingPending) {
                $actionLabel = $existingPending['action'] === 'edit' ? 'עריכה' : 'מחיקה';
                throw new Exception('כבר קיימת בקשה ממתינה ל' . $actionLabel . ' של תשלום זה (מזהה: ' . $existingPending['id'] . ')');
            }
            // === סוף בדיקת כפילויות ===

            // === בדיקת אישור מורשה חתימה ===
            $approvalService = EntityApprovalService::getInstance($pdo);
            $currentUserId = getCurrentUserId();
            $isAuthorizer = $approvalService->isAuthorizer($currentUserId, 'payments', 'delete');

            if (!$isAuthorizer && $approvalService->userNeedsApproval($currentUserId, 'payments', 'delete')) {
                $result = $approvalService->createPendingOperation([
                    'entity_type' => 'payments',
                    'action' => 'delete',
                    'entity_id' => $id,
                    'operation_data' => ['id' => $id],
                    'original_data' => $payment,
                    'requested_by' => $currentUserId
                ]);

                echo json_encode([
                    'success' => true,
                    'pending' => true,
                    'pendingId' => $result['pendingId'],
                    'message' => 'הבקשה נשלחה לאישור מורשה חתימה',
                    'expiresAt' => $result['expiresAt']
                ]);
                break;
            }
            // === סוף בדיקת אישור ===

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
            requireViewPermission('payments');
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