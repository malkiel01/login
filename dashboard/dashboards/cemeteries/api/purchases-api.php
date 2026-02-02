<?php
/*
 * File: dashboard/dashboards/cemeteries/api/purchases-api.php
 * Version: 1.2.1
 * Updated: 2025-11-19
 * Author: Malkiel
 * Change Summary:
 * - v1.2.1: 🐛 **תיקון קריטי** - המרת טיפוס ב-pagination
 *   - הוספת (int) להמרת page, limit, total ב-pagination
 *   - תיקון בעיית "1" + 1 = "11" במקום 1 + 1 = 2
 *   - עכשיו זהה 100% ל-areaGraves-api.php
 * - v1.2.0: תמיכה ב-POST data מ-UniversalSearch
 * - v1.1.0: תיקון countSql עם preg_replace
 */

// אימות והרשאות - חייב להיות מחובר!
require_once __DIR__ . '/api-auth.php';
require_once __DIR__ . '/services/EntityApprovalService.php';

// =====================================
// 1️⃣ קבלת נתוני POST/GET
// =====================================
$postData = json_decode(file_get_contents('php://input'), true);

// אם יש POST data - זה חיפוש מ-UniversalSearch
if ($postData && isset($postData['action'])) {
    $action = $postData['action'];
    $query = $postData['query'] ?? '';
    $filters = $postData['filters'] ?? [];
    $page = $postData['page'] ?? 1;
    $limit = $postData['limit'] ?? 200;
    $sort = $postData['orderBy'] ?? 'createDate';
    $order = strtoupper($postData['sortDirection'] ?? 'DESC');
    $status = '';
    $customer_id = '';
} else {
    // אחרת - GET רגיל
    $action = $_GET['action'] ?? '';
    $query = $_GET['search'] ?? '';
    $filters = [];
    $page = $_GET['page'] ?? 1;
    $limit = $_GET['limit'] ?? 200;
    $sort = $_GET['orderBy'] ?? $_GET['sort'] ?? 'createDate';
    $order = strtoupper($_GET['sortDirection'] ?? $_GET['order'] ?? 'DESC');
    $status = $_GET['status'] ?? '';
    // תמיכה בשני שמות פרמטרים: customer_id ו-clientId
    $customer_id = $_GET['customer_id'] ?? $_GET['clientId'] ?? '';
    // תמיכה בסינון לפי קבר
    $grave_id = $_GET['graveId'] ?? '';
}

// ⭐ $id תמיד מגיע רק מ-GET (גם בעריכה וגם במחיקה)
$id = $_GET['id'] ?? null;

try {
    $pdo = getDBConnection();
} catch(PDOException $e) {
    die(json_encode(['success' => false, 'error' => 'Connection failed: ' . $e->getMessage()]));
}

try {
    switch ($action) {
        case 'list':
            requireViewPermission('purchases');
            // חישוב offset
            $offset = ($page - 1) * $limit;
            
            // בניית השאילתה
            $sql = "
                SELECT 
                    p.*,                           -- כל שדות purchases
                    gv.cemeteryNameHe,            -- שם בית עלמין
                    gv.blockNameHe,               -- שם גוש
                    gv.plotNameHe,                -- שם חלקה
                    gv.lineNameHe,                -- שם שורה
                    gv.areaGraveNameHe,           -- שם אזור קבר
                    gv.graveNameHe,               -- שם קבר
                    gv.graveStatus,               -- סטטוס קבר
                    cust1.fullNameHe AS clientFullNameHe,    -- שם לקוח מלא
                    cust1.numId AS clientNumId,              -- ת.ז. לקוח
                    cust2.fullNameHe AS contactFullNameHe    -- שם איש קשר
                FROM purchases p
                LEFT JOIN graves_view gv ON p.graveId = gv.unicId
                LEFT JOIN customers cust1 ON p.clientId = cust1.unicId
                LEFT JOIN customers cust2 ON p.contactId = cust2.unicId
                WHERE p.isActive = 1
            ";

            $params = [];

            // חיפוש
            if ($query) {
                $sql .= " AND (
                    p.id LIKE :query OR 
                    p.serialPurchaseId LIKE :query OR
                    c.firstName LIKE :query OR 
                    c.lastName LIKE :query OR
                    c.numId LIKE :query OR
                    g.graveNameHe LIKE :query
                )";
                $params['query'] = "%$query%";
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

            // סינון לפי קבר
            if ($grave_id) {
                $sql .= " AND p.graveId = :grave_id";
                $params['grave_id'] = $grave_id;
            }
            
            // ✅ ספירת סה"כ תוצאות
            $countSql = preg_replace('/SELECT\s+.*?\s+FROM/s', 'SELECT COUNT(*) FROM', $sql);
            $countStmt = $pdo->prepare($countSql);
            $countStmt->execute($params);
            $total = $countStmt->fetchColumn();
            
            // ⭐ מיפוי עמודות מיון - כולל שדות מ-JOIN
            $sortColumnMapping = [
                // שדות ישירים מ-purchases
                'createDate' => 'p.createDate',
                'dateOpening' => 'p.dateOpening',
                'price' => 'p.price',
                'purchaseStatus' => 'p.purchaseStatus',
                'id' => 'p.id',
                'serialPurchaseId' => 'p.serialPurchaseId',
                // שדות מ-customers (לקוח)
                'clientFullNameHe' => 'cust1.fullNameHe',
                'clientNumId' => 'cust1.numId',
                // שדות מ-graves_view
                'graveNameHe' => 'gv.graveNameHe',
                'areaGraveNameHe' => 'gv.areaGraveNameHe',
                'plotNameHe' => 'gv.plotNameHe',
                'blockNameHe' => 'gv.blockNameHe',
                'cemeteryNameHe' => 'gv.cemeteryNameHe'
            ];

            // ⭐ מיון רב-שלבי - תמיכה במערך של רמות מיון
            $sortLevelsParam = $_GET['sortLevels'] ?? ($postData['sortLevels'] ?? null);
            $orderByClause = '';

            if ($sortLevelsParam) {
                // נסה לפרסר כ-JSON
                $sortLevels = is_string($sortLevelsParam) ? json_decode($sortLevelsParam, true) : $sortLevelsParam;

                if (is_array($sortLevels) && count($sortLevels) > 0) {
                    $orderByClauses = [];
                    foreach ($sortLevels as $level) {
                        $field = $level['field'] ?? '';
                        $levelOrder = strtoupper($level['order'] ?? 'ASC') === 'ASC' ? 'ASC' : 'DESC';

                        // מצא את עמודת המיון המתאימה
                        $col = $sortColumnMapping[$field] ?? null;
                        if ($col) {
                            $orderByClauses[] = "{$col} {$levelOrder}";
                        }
                    }

                    if (count($orderByClauses) > 0) {
                        $orderByClause = implode(', ', $orderByClauses);
                    }
                }
            }

            // אם אין מיון רב-שלבי - השתמש במיון בודד (תאימות לאחור)
            if (empty($orderByClause)) {
                $orderByColumn = $sortColumnMapping[$sort] ?? 'p.createDate';
                $order = strtoupper($order) === 'ASC' ? 'ASC' : 'DESC';
                $orderByClause = "{$orderByColumn} {$order}";
            }

            // הוספת מיון ועימוד
            $sql .= " ORDER BY {$orderByClause} LIMIT :limit OFFSET :offset";
            
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
                    'page' => (int)$page,      // ✅ המרה ל-INT
                    'limit' => (int)$limit,    // ✅ המרה ל-INT
                    'total' => (int)$total,    // ✅ המרה ל-INT
                    'pages' => (int)ceil($total / $limit)  // ✅ המרה ל-INT
                ]
            ]);
            break;
        
        // קבלת רכישה בודדת
        case 'get':
            requireViewPermission('purchases');
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
            requireCreatePermission('purchases');
            $data = json_decode(file_get_contents('php://input'), true);
            
            // === דיבאג להבנת השגיאה ===
            error_log("=== DEBUG DUPLICATE KEY ERROR ===");
            error_log("Action: CREATE");
            error_log("Data received: " . json_encode($data));
            
            // בדוק מה המבנה של האינדקס clientId
            $stmt = $pdo->query("SHOW INDEX FROM purchases WHERE Key_name = 'clientId'");
            $indexes = $stmt->fetchAll(PDO::FETCH_ASSOC);
            error_log("Index 'clientId' structure: " . json_encode($indexes));

                        // בדוק אם כבר יש רכישה עם אותו clientId
            if (isset($data['clientId'])) {
                $checkStmt = $pdo->prepare("SELECT unicId, graveId, serialPurchaseId FROM purchases WHERE clientId = ? AND isActive = 1");
                $checkStmt->execute([$data['clientId']]);
                $existing = $checkStmt->fetchAll(PDO::FETCH_ASSOC);
                error_log("Existing purchases for this client: " . json_encode($existing));
            }
            
            // בדוק אם יש אינדקס ייחודי על השילוב clientId + משהו אחר
            $stmt = $pdo->query("SHOW CREATE TABLE purchases");
            $tableStructure = $stmt->fetch(PDO::FETCH_ASSOC);
            error_log("Table structure: " . $tableStructure['Create Table']);
            // === סוף דיבאג ===

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

            // ברירת מחדל ל-isActive
            if (!isset($data['isActive'])) {
                $data['isActive'] = 1;
            }
            
            // בניית השאילתה
            $fields = [
                'unicId', 'clientId', 'graveId', 'serialPurchaseId', 'purchaseStatus',
                'buyerStatus', 'price', 'numOfPayments', 'PaymentEndDate', 'paymentsList',
                'refundAmount', 'refundInvoiceNumber', 'contactId', 'dateOpening',
                'ifCertificate', 'deedNum', 'kinship', 'comment', 'createDate', 'updateDate'
            ];

            // ניקוי paymentsList לפני השמירה
            if (isset($data['paymentsList']) && $data['paymentsList']) {
                $payments = json_decode($data['paymentsList'], true);
                if ($payments) {
                    $cleanPayments = array_map(function($payment) {
                        return [
                            'paymentType' => $payment['paymentType'] ?? 1,
                            'paymentAmount' => $payment['paymentAmount'] ?? 0,
                            'customPaymentType' => $payment['customPaymentType'] ?? '',
                            'paymentDate' => $payment['paymentDate'] ?? '',
                            'isPaymentComplete' => $payment['isPaymentComplete'] ?? false,
                            'receiptDocuments' => $payment['receiptDocuments'] ?? []
                        ];
                    }, $payments);
                    $data['paymentsList'] = json_encode($cleanPayments);
                }
            }
            
            // === בדיקת כפילויות עם נעילה אופטימיסטית ===
            // נעילת הקבר והלקוח למניעת race conditions
            $pdo->beginTransaction();
            try {
                // נעילת הקבר - ימתין אם יש transaction אחר
                $stmt = $pdo->prepare("SELECT unicId FROM graves WHERE unicId = ? FOR UPDATE");
                $stmt->execute([$data['graveId']]);

                // נעילת הלקוח
                $stmt = $pdo->prepare("SELECT unicId FROM customers WHERE unicId = ? FOR UPDATE");
                $stmt->execute([$data['clientId']]);

                // בדיקה 1: האם יש pending על הקבר הזה?
                // משתמש בעמודת grave_id המחושבת לביצועים טובים יותר
                $stmt = $pdo->prepare("
                    SELECT id FROM pending_entity_operations
                    WHERE entity_type = 'purchases'
                      AND action = 'create'
                      AND status = 'pending'
                      AND grave_id = ?
                ");
                $stmt->execute([$data['graveId']]);
                $existingGravePending = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($existingGravePending) {
                    $pdo->rollBack();
                    throw new Exception('כבר קיימת בקשה ממתינה לרכישה על קבר זה (מזהה: ' . $existingGravePending['id'] . ')');
                }

                // בדיקה 2: האם יש pending על הלקוח הזה (רכישה או קבורה)?
                // משתמש בעמודת client_id המחושבת לביצועים טובים יותר
                $stmt = $pdo->prepare("
                    SELECT id, entity_type FROM pending_entity_operations
                    WHERE entity_type IN ('purchases', 'burials')
                      AND action = 'create'
                      AND status = 'pending'
                      AND client_id = ?
                ");
                $stmt->execute([$data['clientId']]);
                $existingClientPending = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($existingClientPending) {
                    $pdo->rollBack();
                    $entityLabel = $existingClientPending['entity_type'] === 'purchases' ? 'רכישה' : 'קבורה';
                    throw new Exception('כבר קיימת בקשה ממתינה ל' . $entityLabel . ' עבור לקוח זה (מזהה: ' . $existingClientPending['id'] . ')');
                }
                // === סוף בדיקת כפילויות ===

                // === בדיקת אישור מורשה חתימה ===
                $approvalService = EntityApprovalService::getInstance($pdo);
                $currentUserId = getCurrentUserId();

                // אם המשתמש הוא מורשה חתימה - מבצע מיידית (נחשב כחתימתו)
                $isAuthorizer = $approvalService->isAuthorizer($currentUserId, 'purchases', 'create');

                // אם דורש אישור ואינו מורשה חתימה - שמור כפעולה ממתינה
                if (!$isAuthorizer && $approvalService->userNeedsApproval($currentUserId, 'purchases', 'create')) {
                    $result = $approvalService->createPendingOperation([
                        'entity_type' => 'purchases',
                        'action' => 'create',
                        'operation_data' => $data,
                        'requested_by' => $currentUserId
                    ]);

                    $pdo->commit();
                    echo json_encode([
                        'success' => true,
                        'pending' => true,
                        'pendingId' => $result['pendingId'],
                        'message' => 'הבקשה נשלחה לאישור מורשה חתימה',
                        'expiresAt' => $result['expiresAt']
                    ]);
                    exit; // Stop execution after pending response
                }
                // === סוף בדיקת אישור ===

                $pdo->commit();
            } catch (Exception $lockException) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                throw $lockException;
            }

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
            requireEditPermission('purchases');
            if (!$id) {
                throw new Exception('Purchase ID is required');
            }
            
            $data = json_decode(file_get_contents('php://input'), true);
  
            // בדוק אם graveId נשאר אותו דבר
            if (isset($data['graveId'])) {
                $stmt = $pdo->prepare("SELECT graveId FROM purchases WHERE unicId = :id");
                $stmt->execute(['id' => $id]);
                $current = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($current && $current['graveId'] == $data['graveId']) {
                    unset($data['graveId']); // הסר אותו מהעדכון
                }
            }
            if (isset($data['clientId'])) {
                $stmt = $pdo->prepare("SELECT clientId FROM purchases WHERE unicId = :id");
                $stmt->execute(['id' => $id]);
                $current = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($current && $current['clientId'] == $data['clientId']) {
                    unset($data['clientId']); // הסר אותו מהעדכון
                }
            }
            
            // בדיקה שהקבר החדש פנוי (אם משנים קבר)
            if (isset($data['graveId'])) {
                $stmt = $pdo->prepare("SELECT graveStatus FROM graves WHERE unicId = :id AND isActive = 1");
                $stmt->execute(['id' => $data['graveId']]);
                $grave = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$grave || $grave['graveStatus'] != 1) {
                    throw new Exception('הקבר אינו פנוי לרכישה');
                }
            }
            
            $data['updateDate'] = date('Y-m-d H:i:s');
            
            // בניית השאילתה
            $updateFields = [];
            $params = [];
            
            $allowedFields = [
                'graveId', 'purchaseStatus', 'buyerStatus', 'price', 
                'numOfPayments', 'PaymentEndDate', 'paymentsList', 'refundAmount', 
                'refundInvoiceNumber', 'contactId', 'dateOpening', 'ifCertificate', 
                'deedNum', 'kinship', 'comment', 'updateDate'
            ];
            
            foreach ($allowedFields as $field) {
                if (isset($data[$field])) {
                    $updateFields[] = "$field = :$field";
                    $params[$field] = $data[$field];
                }
            }
            
            if (empty($updateFields)) {
                throw new Exception('אין שדות לעדכון');
            }

            // === בדיקת כפילויות לפני יצירת pending ===
            // בדיקה אם כבר קיימת בקשה ממתינה (עריכה או מחיקה) עבור רכישה זו
            $stmt = $pdo->prepare("
                SELECT id, action FROM pending_entity_operations
                WHERE entity_type = 'purchases'
                  AND action IN ('edit', 'delete')
                  AND entity_id = ?
                  AND status = 'pending'
            ");
            $stmt->execute([$id]);
            $existingPending = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($existingPending) {
                $actionLabel = $existingPending['action'] === 'edit' ? 'עריכה' : 'מחיקה';
                throw new Exception('כבר קיימת בקשה ממתינה ל' . $actionLabel . ' של רכישה זו (מזהה: ' . $existingPending['id'] . ')');
            }
            // === סוף בדיקת כפילויות ===

            // === בדיקת אישור מורשה חתימה ===
            $approvalService = EntityApprovalService::getInstance($pdo);
            $currentUserId = getCurrentUserId();
            $isAuthorizer = $approvalService->isAuthorizer($currentUserId, 'purchases', 'edit');

            if (!$isAuthorizer && $approvalService->userNeedsApproval($currentUserId, 'purchases', 'edit')) {
                // שמירת המידע המקורי
                $stmt = $pdo->prepare("SELECT * FROM purchases WHERE unicId = :id");
                $stmt->execute(['id' => $id]);
                $originalData = $stmt->fetch(PDO::FETCH_ASSOC);

                $result = $approvalService->createPendingOperation([
                    'entity_type' => 'purchases',
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
                    'message' => 'הבקשה לעריכה נשלחה לאישור מורשה חתימה'
                ]);
                exit; // Stop execution after pending response
            }
            // === סוף בדיקת אישור ===

            $params['id'] = $id;
            $sql = "UPDATE purchases SET " . implode(', ', $updateFields) . " WHERE unicId = :id";

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);

            echo json_encode([
                'success' => true,
                'message' => 'הרכישה עודכנה בהצלחה'
            ]);
            break;
        
        case 'delete':
            requireDeletePermission('purchases');
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

            // === בדיקת כפילויות לפני יצירת pending ===
            // בדיקה אם כבר קיימת בקשה ממתינה (עריכה או מחיקה) עבור רכישה זו
            $stmt = $pdo->prepare("
                SELECT id, action FROM pending_entity_operations
                WHERE entity_type = 'purchases'
                  AND action IN ('edit', 'delete')
                  AND entity_id = ?
                  AND status = 'pending'
            ");
            $stmt->execute([$id]);
            $existingPending = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($existingPending) {
                $actionLabel = $existingPending['action'] === 'edit' ? 'עריכה' : 'מחיקה';
                throw new Exception('כבר קיימת בקשה ממתינה ל' . $actionLabel . ' של רכישה זו (מזהה: ' . $existingPending['id'] . ')');
            }
            // === סוף בדיקת כפילויות ===

            // === בדיקת אישור מורשה חתימה ===
            $approvalService = EntityApprovalService::getInstance($pdo);
            $currentUserId = getCurrentUserId();
            $isAuthorizer = $approvalService->isAuthorizer($currentUserId, 'purchases', 'delete');

            if (!$isAuthorizer && $approvalService->userNeedsApproval($currentUserId, 'purchases', 'delete')) {
                $result = $approvalService->createPendingOperation([
                    'entity_type' => 'purchases',
                    'action' => 'delete',
                    'entity_id' => $id,
                    'operation_data' => ['id' => $id],
                    'original_data' => $purchase,
                    'requested_by' => $currentUserId
                ]);

                echo json_encode([
                    'success' => true,
                    'pending' => true,
                    'pendingId' => $result['pendingId'],
                    'message' => 'הבקשה למחיקה נשלחה לאישור מורשה חתימה'
                ]);
                exit; // Stop execution after pending response
            }
            // === סוף בדיקת אישור ===

            // מחיקה רכה
            $stmt = $pdo->prepare("UPDATE purchases SET isActive = 0, inactiveDate = :date WHERE id = :id");
            $stmt->execute(['id' => $id, 'date' => date('Y-m-d H:i:s')]);
            
            // עדכון סטטוס הקבר - בדוק אם יש קבורה פעילה
            if ($purchase['graveId']) {
                // בדוק אם יש קבורה פעילה לקבר זה
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM burials WHERE graveId = :graveId AND isActive = 1");
                $stmt->execute(['graveId' => $purchase['graveId']]);
                $hasBurial = $stmt->fetchColumn() > 0;

                if (!$hasBurial) {
                    // אין קבורה - חזרה לפנוי (1)
                    $stmt = $pdo->prepare("UPDATE graves SET graveStatus = 1 WHERE unicId = :id");
                    $stmt->execute(['id' => $purchase['graveId']]);
                }
                // אם יש קבורה - הסטטוס נשאר קבור (3), לא משנים
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
            requireViewPermission('purchases');
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
            
        // הוסף לאחר case 'search': וליפני default:
        // ============================
        case 'getByGrave':
            $graveId = $_GET['graveId'] ?? null;
            if (!$graveId) {
                throw new Exception('Grave ID is required');
            }
            
            $stmt = $pdo->prepare("
                SELECT p.*, 
                    CONCAT(c.firstName, ' ', c.lastName) as customer_name,
                    g.graveNameHe as grave_name
                FROM purchases p
                INNER JOIN customers c ON p.clientId = c.unicId
                INNER JOIN graves g ON p.graveId = g.unicId
                WHERE p.graveId = :graveId 
                AND p.isActive = 1
                LIMIT 1
            ");
            $stmt->execute(['graveId' => $graveId]);
            $purchase = $stmt->fetch(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'success' => true,
                'data' => $purchase  // ← שינוי מ-'purchase' ל-'data'
            ]);
            break;

        case 'getByCustomer':
            $customerId = $_GET['customerId'] ?? null;
            if (!$customerId) {
                throw new Exception('Customer ID is required');
            }
            
            $stmt = $pdo->prepare("
                SELECT p.*, 
                    CONCAT(c.firstName, ' ', c.lastName) as customer_name,
                    g.graveNameHe as grave_name
                FROM purchases p
                INNER JOIN customers c ON p.clientId = c.unicId
                INNER JOIN graves g ON p.graveId = g.unicId
                WHERE p.clientId = :customerId 
                AND p.isActive = 1
                LIMIT 1
            ");
            $stmt->execute(['customerId' => $customerId]);
            $purchase = $stmt->fetch(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'success' => true,
                'data' => $purchase  // ← שינוי מ-'purchase' ל-'data'
            ]);
            break;
        
        // רשימת כל הרכישות
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