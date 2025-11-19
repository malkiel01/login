<?php
// dashboard/dashboards/cemeteries/api/burials-api.php
// API לניהול קבורות

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type');

require_once $_SERVER['DOCUMENT_ROOT'] . '/dashboard/dashboards/cemeteries/config.php';

try {
    $pdo = getDBConnection();
} catch(PDOException $e) {
    die(json_encode(['success' => false, 'error' => 'Connection failed: ' . $e->getMessage()]));
}

$action = $_GET['action'] ?? '';
$id = $_GET['id'] ?? null;

try {
    switch ($action) {
        case 'list':
            // חישוב offset
            $offset = ($page - 1) * $limit;
            
            // בניית השאילתה
            $sql = "
                SELECT 
                    b.*,
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
                    bl.blockNameHe,
                    ce.cemeteryNameHe,
                    CONCAT(
                        ce.cemeteryNameHe, ' → ',
                        bl.blockNameHe, ' → ',
                        pl.plotNameHe, ' → ',
                        r.lineNameHe, ' → ',
                        ag.areaGraveNameHe, ' → ',
                        g.graveNameHe
                    ) as fullLocation
                FROM burials b
                LEFT JOIN customers c ON b.clientId = c.unicId
                LEFT JOIN graves g ON b.graveId = g.unicId
                LEFT JOIN areaGraves ag ON g.areaGraveId = ag.unicId
                LEFT JOIN rows r ON ag.lineId = r.unicId
                LEFT JOIN plots pl ON r.plotId = pl.unicId
                LEFT JOIN blocks bl ON pl.blockId = bl.unicId
                LEFT JOIN cemeteries ce ON bl.cemeteryId = ce.unicId
                WHERE b.isActive = 1
            ";
            $params = [];
            
            // ✅ חיפוש - תוקן עם placeholders ייחודיים
            if ($query) {
                $sql .= " AND (
                    b.id LIKE :query1 OR 
                    b.serialBurialId LIKE :query2 OR
                    c.firstName LIKE :query3 OR 
                    c.lastName LIKE :query4 OR
                    c.numId LIKE :query5 OR
                    g.graveNameHe LIKE :query6 OR
                    b.customerFirstName LIKE :query7 OR
                    b.customerLastName LIKE :query8 OR
                    b.customerNumId LIKE :query9
                )";
                $searchTerm = "%$query%";
                $params['query1'] = $searchTerm;
                $params['query2'] = $searchTerm;
                $params['query3'] = $searchTerm;
                $params['query4'] = $searchTerm;
                $params['query5'] = $searchTerm;
                $params['query6'] = $searchTerm;
                $params['query7'] = $searchTerm;
                $params['query8'] = $searchTerm;
                $params['query9'] = $searchTerm;
            }
            
            // סינון לפי סטטוס
            if ($status) {
                $sql .= " AND b.burialStatus = :status";
                $params['status'] = $status;
            }
            
            // סינון לפי לקוח
            if ($customer_id) {
                $sql .= " AND b.clientId = :customer_id";
                $params['customer_id'] = $customer_id;
            }
            
            // ✅ ספירת סה"כ תוצאות
            $countSql = preg_replace('/SELECT\s+.*?\s+FROM/s', 'SELECT COUNT(*) FROM', $sql);
            $countStmt = $pdo->prepare($countSql);
            $countStmt->execute($params);
            $total = $countStmt->fetchColumn();
            
            // רשימת עמודות מותרות למיון
            $allowedSortColumns = ['createDate', 'dateBurial', 'dateDeath', 'burialStatus', 'id', 'serialBurialId'];
            if (!in_array($sort, $allowedSortColumns)) {
                $sort = 'createDate';
            }
            
            // בדיקת כיוון המיון
            $order = strtoupper($order) === 'ASC' ? 'ASC' : 'DESC';
            
            // הוספת מיון ועימוד
            $sql .= " ORDER BY b.$sort $order LIMIT :limit OFFSET :offset";
            
            $stmt = $pdo->prepare($sql);
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();
            
            $burials = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // הוסף תאימות לאחור
            foreach ($burials as &$burial) {
                $burial['burial_date'] = $burial['dateBurial'];
                $burial['death_date'] = $burial['dateDeath'];
                $burial['burial_number'] = $burial['serialBurialId'];
                $burial['burial_status'] = $burial['burialStatus'];
            }
            
            echo json_encode([
                'success' => true,
                'data' => $burials,
                'pagination' => [
                    'total' => $total,
                    'page' => $page,
                    'limit' => $limit,
                    'pages' => ceil($total / $limit)
                ]
            ]);
            break;

        case 'get':
            if (!$id) {
                throw new Exception('Burial ID is required');
            }
            
            $stmt = $pdo->prepare("
                SELECT b.*,
                       CONCAT(c.firstName, ' ', c.lastName) as customer_name,
                       c.numId as customer_id_number,
                       g.graveNameHe as grave_name,
                       g.graveStatus,
                       p.serialPurchaseId as purchase_number
                FROM burials b
                LEFT JOIN customers c ON b.clientId = c.unicId
                LEFT JOIN graves g ON b.graveId = g.unicId
                LEFT JOIN purchases p ON b.purchaseId = p.unicId
                WHERE b.unicId = :id AND b.isActive = 1
            ");
            $stmt->execute(['id' => $id]);
            $burial = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$burial) {
                throw new Exception('קבורה לא נמצאה');
            }
            
            echo json_encode(['success' => true, 'data' => $burial]);
            break;
            
        case 'create':
            $data = json_decode(file_get_contents('php://input'), true);
            
            // ולידציה
            if (empty($data['clientId'])) {
                throw new Exception('לקוח הוא שדה חובה');
            }
            
            if (empty($data['graveId'])) {
                throw new Exception('קבר הוא שדה חובה');
            }
            
            // בדוק שהקבר זמין לקבורה
            $stmt = $pdo->prepare("SELECT graveStatus FROM graves WHERE unicId = :id");
            $stmt->execute(['id' => $data['graveId']]);
            $grave = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$grave) {
                throw new Exception('הקבר לא נמצא');
            }
            
            // קבר חייב להיות פנוי (1) או נרכש (2)
            if (!in_array($grave['graveStatus'], [1, 2])) {
                throw new Exception('הקבר אינו זמין לקבורה');
            }
            
            // יצירת unicId
            if (!isset($data['unicId'])) {
                $data['unicId'] = uniqid('burial_', true);
            }
            
            // יצירת מספר סידורי לקבורה
            if (!isset($data['serialBurialId'])) {
                $year = date('Y');
                $stmt = $pdo->query("SELECT MAX(CAST(SUBSTRING(serialBurialId, 6) AS UNSIGNED)) as max_serial 
                                     FROM burials 
                                     WHERE serialBurialId LIKE '$year-%'");
                $maxSerial = $stmt->fetch(PDO::FETCH_ASSOC)['max_serial'] ?? 0;
                $data['serialBurialId'] = $year . '-' . str_pad($maxSerial + 1, 5, '0', STR_PAD_LEFT);
            }
            
            // הוספת תאריכים
            $data['createDate'] = date('Y-m-d H:i:s');
            $data['updateDate'] = date('Y-m-d H:i:s');
            $data['isActive'] = 1;
            
            // שדות מותרים
            $allowedFields = [
                'unicId', 'clientId', 'graveId', 'purchaseId', 'serialBurialId',
                'dateDeath', 'timeDeath', 'dateBurial', 'timeBurial', 'placeDeath',
                'nationalInsuranceBurial', 'deathAbroad', 'dateOpening_tld', 'reportingBL',
                'kinship', 'buriaLicense', 'comment', 'createDate', 'updateDate', 'isActive'
            ];
            
            $insertFields = [];
            $insertValues = [];
            $params = [];
            
            foreach ($allowedFields as $field) {
                if (isset($data[$field])) {
                    $insertFields[] = $field;
                    $insertValues[] = ":$field";
                    $params[$field] = $data[$field];
                }
            }
            
            $sql = "INSERT INTO burials (" . implode(', ', $insertFields) . ") 
                    VALUES (" . implode(', ', $insertValues) . ")";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            
            // עדכון סטטוס הקבר לקבור (3)
            $stmt = $pdo->prepare("UPDATE graves SET graveStatus = 3 WHERE unicId = :id");
            $stmt->execute(['id' => $data['graveId']]);
            
            // עדכון סטטוס הלקוח לנפטר (3)
            $stmt = $pdo->prepare("UPDATE customers SET statusCustomer = 3 WHERE unicId = :id");
            $stmt->execute(['id' => $data['clientId']]);
            
            echo json_encode([
                'success' => true,
                'message' => 'הקבורה נוספה בהצלחה',
                'unicId' => $data['unicId']
            ]);
            break;
            
        case 'update':
            if (!$id) {
                throw new Exception('Burial ID is required');
            }
            
            $data = json_decode(file_get_contents('php://input'), true);
            $data['updateDate'] = date('Y-m-d H:i:s');
            
            $allowedFields = [
                'dateDeath', 'timeDeath', 'dateBurial', 'timeBurial', 'placeDeath',
                'nationalInsuranceBurial', 'deathAbroad', 'dateOpening_tld', 'reportingBL',
                'kinship', 'buriaLicense', 'comment', 'updateDate'
            ];
            
            $updateFields = [];
            $params = ['id' => $id];
            
            foreach ($allowedFields as $field) {
                if (isset($data[$field])) {
                    $updateFields[] = "$field = :$field";
                    $params[$field] = $data[$field];
                }
            }
            
            if (empty($updateFields)) {
                throw new Exception('אין נתונים לעדכון');
            }
            
            $sql = "UPDATE burials SET " . implode(', ', $updateFields) . " WHERE unicId = :id";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            
            echo json_encode([
                'success' => true,
                'message' => 'הקבורה עודכנה בהצלחה'
            ]);
            break;
            
        case 'list2':
            $search = $_GET['search'] ?? '';
            $status = $_GET['status'] ?? '';
            $customer_id = $_GET['customer_id'] ?? '';
            $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
            $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
            $offset = ($page - 1) * $limit;
            $sort = $_GET['sort'] ?? 'createDate';
            $order = $_GET['order'] ?? 'DESC';
            
            // בניית שאילתה
            $where = ['b.isActive = 1'];
            $params = [];
            
            // חיפוש
            if ($search) {
                $where[] = "(b.serialBurialId LIKE :search OR c.firstName LIKE :search2 OR c.lastName LIKE :search3 OR c.numId LIKE :search4)";
                $params['search'] = "%$search%";
                $params['search2'] = "%$search%";
                $params['search3'] = "%$search%";
                $params['search4'] = "%$search%";
            }
            
            // סינון לפי סטטוס (נוסיף שדה סטטוס בעתיד)
            if ($status) {
                $where[] = "b.burialStatus = :status";
                $params['status'] = $status;
            }
            
            // סינון לפי לקוח
            if ($customer_id) {
                $where[] = "b.clientId = :customer_id";
                $params['customer_id'] = $customer_id;
            }
            
            $whereClause = implode(' AND ', $where);
            
            // ספירת סך הכל
            $countSql = "
                SELECT COUNT(*) 
                FROM burials b
                LEFT JOIN customers c ON b.clientId = c.unicId
                WHERE $whereClause
            ";
            
            $stmt = $pdo->prepare($countSql);
            $stmt->execute($params);
            $total = $stmt->fetchColumn();
            
            // שליפת הנתונים עם JOIN
            $sql = "
                SELECT 
                    b.*,
                    c.firstName as customerFirstName,
                    c.lastName as customerLastName,
                    c.numId as customerNumId,
                    c.phone as customerPhone,
                    c.statusCustomer as customerStatus,
                    g.graveNameHe as graveName,
                    g.graveStatus,
                    p.serialPurchaseId as purchaseSerial,
                    p.price as purchasePrice,
                    -- מיקום הקבר המלא
                    CONCAT_WS(' ← ', 
                        cem.cemeteryNameHe,
                        bl.blockNameHe,
                        pl.plotNameHe,
                        r.lineNameHe,
                        ag.areaGraveNameHe,
                        g.graveNameHe
                    ) as fullLocation
                FROM burials b
                LEFT JOIN customers c ON b.clientId = c.unicId
                LEFT JOIN graves g ON b.graveId = g.unicId
                LEFT JOIN purchases p ON b.purchaseId = p.unicId
                -- הצטרפות להיררכיה למיקום מלא
                LEFT JOIN areaGraves ag ON g.areaGraveId = ag.unicId
                LEFT JOIN rows r ON ag.lineId = r.unicId
                LEFT JOIN plots pl ON r.plotId = pl.unicId
                LEFT JOIN blocks bl ON pl.blockId = bl.unicId
                LEFT JOIN cemeteries cem ON bl.cemeteryId = cem.unicId
                WHERE $whereClause
                ORDER BY b.$sort $order
                LIMIT :limit OFFSET :offset
            ";
            
            $stmt = $pdo->prepare($sql);
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();
            
            $burials = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // עיבוד התוצאות
            foreach ($burials as &$burial) {
                // המרת JSON arrays
                if ($burial['savedGravesList']) {
                    $burial['savedGravesList'] = json_decode($burial['savedGravesList'], true) ?? [];
                }
                if ($burial['documentsList']) {
                    $burial['documentsList'] = json_decode($burial['documentsList'], true) ?? [];
                }
                if ($burial['historyList']) {
                    $burial['historyList'] = json_decode($burial['historyList'], true) ?? [];
                }
                
                // הוספת סטטוס קבורה (ברירת מחדל לפי תאריכים)
                if (!isset($burial['burialStatus'])) {
                    if ($burial['cancelDate']) {
                        $burial['burialStatus'] = 4; // בוטלה
                    } elseif (strtotime($burial['dateBurial']) < time()) {
                        $burial['burialStatus'] = 3; // בוצעה
                    } elseif ($burial['reportingBL']) {
                        $burial['burialStatus'] = 2; // אושרה
                    } else {
                        $burial['burialStatus'] = 1; // ברישום
                    }
                }
            }
            
            echo json_encode([
                'success' => true,
                'data' => $burials,
                'pagination' => [
                    'total' => $total,
                    'page' => $page,
                    'limit' => $limit,
                    'pages' => ceil($total / $limit)
                ]
            ]);
            break;
            
        case 'delete':
            if (!$id) {
                throw new Exception('Burial ID is required');
            }
            
            // קבלת פרטי הקבורה
            $stmt = $pdo->prepare("SELECT * FROM burials WHERE unicId = :id AND isActive = 1");
            $stmt->execute(['id' => $id]);
            $burial = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$burial) {
                throw new Exception('הקבורה לא נמצאה');
            }
            
            // מחיקה רכה
            $stmt = $pdo->prepare("
                UPDATE burials 
                SET isActive = 0, 
                    inactiveDate = :date,
                    cancelDate = :date2
                WHERE unicId = :id
            ");
            $stmt->execute([
                'id' => $id, 
                'date' => date('Y-m-d H:i:s'),
                'date2' => date('Y-m-d H:i:s')
            ]);
            
            // שחרור הקבר
            if ($burial['graveId']) {
                $stmt = $pdo->prepare("UPDATE graves SET graveStatus = 1 WHERE unicId = :graveId");
                $stmt->execute(['graveId' => $burial['graveId']]);
            }
            
            // בדיקה אם ללקוח יש קבורות אחרות
            if ($burial['clientId']) {
                $stmt = $pdo->prepare("
                    SELECT COUNT(*) FROM burials 
                    WHERE clientId = :clientId AND unicId != :burialId AND isActive = 1
                ");
                $stmt->execute(['clientId' => $burial['clientId'], 'burialId' => $id]);
                
                // אם אין לו קבורות אחרות, החזר אותו לסטטוס פעיל
                if ($stmt->fetchColumn() == 0) {
                    $stmt = $pdo->prepare("UPDATE customers SET statusCustomer = 1 WHERE unicId = :clientId");
                    $stmt->execute(['clientId' => $burial['clientId']]);
                }
            }
            
            echo json_encode([
                'success' => true,
                'message' => 'הקבורה נמחקה בהצלחה'
            ]);
            break;
            
        // סטטיסטיקות קבורות
        case 'stats':
            $stats = [];
            
            // סה"כ קבורות לפי סטטוס
            $stmt = $pdo->query("
                SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN cancelDate IS NOT NULL THEN 1 ELSE 0 END) as cancelled,
                    SUM(CASE WHEN dateBurial < CURDATE() AND cancelDate IS NULL THEN 1 ELSE 0 END) as completed,
                    SUM(CASE WHEN dateBurial >= CURDATE() AND reportingBL IS NOT NULL THEN 1 ELSE 0 END) as approved,
                    SUM(CASE WHEN dateBurial >= CURDATE() AND reportingBL IS NULL THEN 1 ELSE 0 END) as pending
                FROM burials 
                WHERE isActive = 1
            ");
            $stats['by_status'] = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // קבורות החודש
            $stmt = $pdo->query("
                SELECT COUNT(*) as count
                FROM burials 
                WHERE isActive = 1 
                AND MONTH(dateBurial) = MONTH(CURRENT_DATE())
                AND YEAR(dateBurial) = YEAR(CURRENT_DATE())
            ");
            $stats['this_month'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
            
            // קבורות השנה
            $stmt = $pdo->query("
                SELECT COUNT(*) as count
                FROM burials 
                WHERE isActive = 1 
                AND YEAR(dateBurial) = YEAR(CURRENT_DATE())
            ");
            $stats['this_year'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
            
            // קבורות לפי סוגים
            $stmt = $pdo->query("
                SELECT 
                    SUM(CASE WHEN nationalInsuranceBurial = 'כן' THEN 1 ELSE 0 END) as national_insurance,
                    SUM(CASE WHEN deathAbroad = 'כן' THEN 1 ELSE 0 END) as abroad
                FROM burials 
                WHERE isActive = 1
            ");
            $stats['by_type'] = $stmt->fetch(PDO::FETCH_ASSOC);

            // סה"כ קבורות פעילות (בדומה ל-purchases-api.php)
            $stmt = $pdo->query("
                SELECT 
                    COUNT(*) as total_burials
                FROM burials 
                WHERE isActive = 1
            ");
            $stats['totals'] = $stmt->fetch(PDO::FETCH_ASSOC);

            echo json_encode(['success' => true, 'data' => $stats]);
            break;
            
        case 'available':
            // ✅ קבל את הלקוח הנוכחי אם קיים
            $currentClientId = $_GET['currentClientId'] ?? null;
            
            // ✅ קבל את סוג הטופס (purchase/burial)
            $formType = $_GET['type'] ?? 'purchase';
            
            if ($currentClientId) {
                // ✅ במצב עריכה - כלול גם את הלקוח הנוכחי
                if ($formType === 'burial') {
                    // לקבורה: לקוחות שלא נפטרו (statusCustomer != 3) + הלקוח הנוכחי
                    $sql = "
                        SELECT 
                            unicId, 
                            firstName, 
                            lastName, 
                            phone, 
                            phoneMobile, 
                            resident,
                            CASE WHEN unicId = :currentClient THEN 1 ELSE 0 END as is_current
                        FROM customers 
                        WHERE (statusCustomer != 3 OR unicId = :currentClient2)
                        AND isActive = 1 
                        ORDER BY is_current DESC, lastName, firstName
                    ";
                } else {
                    // לרכישה: לקוחות פנויים (statusCustomer = 1) + הלקוח הנוכחי
                    $sql = "
                        SELECT 
                            unicId, 
                            firstName, 
                            lastName, 
                            phone, 
                            phoneMobile, 
                            resident,
                            CASE WHEN unicId = :currentClient THEN 1 ELSE 0 END as is_current
                        FROM customers 
                        WHERE (statusCustomer = 1 OR unicId = :currentClient2)
                        AND isActive = 1 
                        ORDER BY is_current DESC, lastName, firstName
                    ";
                }
                
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    'currentClient' => $currentClientId,
                    'currentClient2' => $currentClientId
                ]);
                
            } else {
                // ✅ במצב הוספה - בלי לקוח נוכחי
                if ($formType === 'burial') {
                    // לקבורה: רק לקוחות שלא נפטרו
                    $sql = "
                        SELECT 
                            unicId, 
                            firstName, 
                            lastName, 
                            phone, 
                            phoneMobile, 
                            resident
                        FROM customers 
                        WHERE statusCustomer != 3
                        AND isActive = 1 
                        ORDER BY lastName, firstName
                    ";
                } else {
                    // לרכישה: רק לקוחות פנויים
                    $sql = "
                        SELECT 
                            unicId, 
                            firstName, 
                            lastName, 
                            phone, 
                            phoneMobile, 
                            resident
                        FROM customers 
                        WHERE statusCustomer = 1
                        AND isActive = 1 
                        ORDER BY lastName, firstName
                    ";
                }
                
                $stmt = $pdo->prepare($sql);
                $stmt->execute();
            }
            
            $customers = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'data' => $customers]);
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