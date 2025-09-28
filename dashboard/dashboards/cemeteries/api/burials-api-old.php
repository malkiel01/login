<?php
// dashboards/cemeteries/api/burials-api-old.php
// API לניהול קבורות - מותאם למבנה הטבלה

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
        // רשימת כל הקבורות
        case 'list':
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
            
        // קבלת קבורה בודדת
        case 'get':
            if (!$id) {
                throw new Exception('Burial ID is required');
            }
            
            $sql = "
                SELECT 
                    b.*,
                    c.firstName as customerFirstName,
                    c.lastName as customerLastName,
                    c.numId as customerNumId,
                    c.phone as customerPhone,
                    c.email as customerEmail,
                    c.address as customerAddress,
                    g.graveNameHe as graveName,
                    g.graveStatus,
                    g.plotType,
                    ag.graveType,
                    p.serialPurchaseId as purchaseSerial,
                    p.price as purchasePrice,
                    p.dateOpening as purchaseDate,
                    -- מיקום מלא
                    cem.cemeteryNameHe,
                    bl.blockNameHe,
                    pl.plotNameHe,
                    r.lineNameHe,
                    ag.areaGraveNameHe
                FROM burials b
                LEFT JOIN customers c ON b.clientId = c.unicId
                LEFT JOIN graves g ON b.graveId = g.unicId
                LEFT JOIN purchases p ON b.purchaseId = p.unicId
                LEFT JOIN areaGraves ag ON g.areaGraveId = ag.unicId
                LEFT JOIN rows r ON ag.lineId = r.unicId
                LEFT JOIN plots pl ON r.plotId = pl.unicId
                LEFT JOIN blocks bl ON pl.blockId = bl.unicId
                LEFT JOIN cemeteries cem ON bl.cemeteryId = cem.unicId
                WHERE b.unicId = :id AND b.isActive = 1
            ";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute(['id' => $id]);
            $burial = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$burial) {
                throw new Exception('קבורה לא נמצאה');
            }
            
            // עיבוד JSON fields
            if ($burial['savedGravesList']) {
                $burial['savedGravesList'] = json_decode($burial['savedGravesList'], true) ?? [];
            }
            if ($burial['documentsList']) {
                $burial['documentsList'] = json_decode($burial['documentsList'], true) ?? [];
            }
            if ($burial['historyList']) {
                $burial['historyList'] = json_decode($burial['historyList'], true) ?? [];
            }
            
            echo json_encode([
                'success' => true,
                'data' => $burial
            ]);
            break;
            
        // יצירת קבורה חדשה
        case 'create':
            $data = json_decode(file_get_contents('php://input'), true);
            
            // ולידציה
            if (!$data['clientId']) {
                throw new Exception('חובה לבחור לקוח');
            }
            if (!$data['graveId']) {
                throw new Exception('חובה לבחור קבר');
            }
            if (!$data['dateDeath'] || !$data['dateBurial']) {
                throw new Exception('חובה להזין תאריכי פטירה וקבורה');
            }
            
            // בדיקת זמינות הקבר
            $stmt = $pdo->prepare("
                SELECT g.graveStatus, b.id as burial_exists
                FROM graves g
                LEFT JOIN burials b ON g.unicId = b.graveId AND b.isActive = 1
                WHERE g.unicId = :graveId
            ");
            $stmt->execute(['graveId' => $data['graveId']]);
            $grave = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$grave) {
                throw new Exception('הקבר לא נמצא');
            }
            
            if ($grave['burial_exists']) {
                throw new Exception('הקבר כבר תפוס בקבורה אחרת');
            }
            
            if ($grave['graveStatus'] == 3) {
                throw new Exception('הקבר כבר תפוס');
            }
            
            // בדיקת הלקוח
            $stmt = $pdo->prepare("
                SELECT statusCustomer, unicId
                FROM customers 
                WHERE unicId = :clientId AND isActive = 1
            ");
            $stmt->execute(['clientId' => $data['clientId']]);
            $customer = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$customer) {
                throw new Exception('הלקוח לא נמצא');
            }
            
            if ($customer['statusCustomer'] == 3) {
                throw new Exception('לא ניתן ליצור קבורה ללקוח בסטטוס נפטר');
            }
            
            // יצירת מספר סידורי
            $year = date('Y');
            $stmt = $pdo->query("
                SELECT MAX(CAST(SUBSTRING_INDEX(serialBurialId, '-', -1) AS UNSIGNED)) as max_serial
                FROM burials 
                WHERE serialBurialId LIKE 'BUR-$year-%'
            ");
            $maxSerial = $stmt->fetch(PDO::FETCH_ASSOC)['max_serial'] ?? 0;
            $serialBurialId = 'BUR-' . $year . '-' . str_pad($maxSerial + 1, 5, '0', STR_PAD_LEFT);
            
            // יצירת unicId
            $unicId = uniqid('burial_', true);
            
            // הכנת שדות
            $fields = [
                'unicId' => $unicId,
                'clientId' => $data['clientId'],
                'graveId' => $data['graveId'],
                'purchaseId' => $data['purchaseId'] ?? null,
                'serialBurialId' => $serialBurialId,
                'dateDeath' => $data['dateDeath'],
                'timeDeath' => $data['timeDeath'] ?? null,
                'dateBurial' => $data['dateBurial'],
                'timeBurial' => $data['timeBurial'] ?? '00:00:00',
                'placeDeath' => $data['placeDeath'] ?? '',
                'nationalInsuranceBurial' => $data['nationalInsuranceBurial'] ?? 'לא',
                'deathAbroad' => $data['deathAbroad'] ?? 'לא',
                'savedGravesList' => json_encode($data['savedGravesList'] ?? []),
                'dateOpening_tld' => $data['dateOpening_tld'] ?? null,
                'reportingBL' => $data['reportingBL'] ?? null,
                'contactId' => $data['contactId'] ?? null,
                'kinship' => $data['kinship'] ?? '',
                'documentsList' => json_encode($data['documentsList'] ?? []),
                'historyList' => json_encode([
                    [
                        'action' => 'created',
                        'date' => date('Y-m-d H:i:s'),
                        'user' => $_SESSION['user_id'] ?? 'system'
                    ]
                ]),
                'buriaLicense' => $data['buriaLicense'] ?? '',
                'comment' => $data['comment'] ?? '',
                'dateOpening' => date('Y-m-d'),
                'createDate' => date('Y-m-d H:i:s'),
                'updateDate' => date('Y-m-d H:i:s'),
                'isActive' => 1
            ];
            
            // הכנת SQL
            $columns = array_keys($fields);
            $placeholders = array_map(function($col) { return ":$col"; }, $columns);
            
            $sql = "INSERT INTO burials (" . implode(', ', $columns) . ") 
                    VALUES (" . implode(', ', $placeholders) . ")";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute($fields);
            
            // עדכון סטטוס הקבר לתפוס
            $stmt = $pdo->prepare("UPDATE graves SET graveStatus = 3 WHERE unicId = :graveId");
            $stmt->execute(['graveId' => $data['graveId']]);
            
            // עדכון סטטוס הלקוח לנפטר
            $stmt = $pdo->prepare("UPDATE customers SET statusCustomer = 3 WHERE unicId = :clientId");
            $stmt->execute(['clientId' => $data['clientId']]);
            
            echo json_encode([
                'success' => true,
                'message' => 'הקבורה נוספה בהצלחה',
                'id' => $unicId,
                'serialId' => $serialBurialId
            ]);
            break;
            
        // עדכון קבורה
        case 'update':
            if (!$id) {
                throw new Exception('Burial ID is required');
            }
            
            $data = json_decode(file_get_contents('php://input'), true);
            
            // בדיקה שהקבורה קיימת
            $stmt = $pdo->prepare("SELECT * FROM burials WHERE unicId = :id AND isActive = 1");
            $stmt->execute(['id' => $id]);
            $burial = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$burial) {
                throw new Exception('הקבורה לא נמצאה');
            }
            
            // אם משנים קבר
            if (isset($data['graveId']) && $data['graveId'] != $burial['graveId']) {
                // בדיקת הקבר החדש
                $stmt = $pdo->prepare("
                    SELECT graveStatus 
                    FROM graves 
                    WHERE unicId = :graveId AND graveStatus IN (1, 2)
                ");
                $stmt->execute(['graveId' => $data['graveId']]);
                
                if (!$stmt->fetch()) {
                    throw new Exception('הקבר החדש לא זמין');
                }
                
                // שחרור הקבר הישן
                $stmt = $pdo->prepare("UPDATE graves SET graveStatus = 1 WHERE unicId = :graveId");
                $stmt->execute(['graveId' => $burial['graveId']]);
                
                // תפיסת הקבר החדש
                $stmt = $pdo->prepare("UPDATE graves SET graveStatus = 3 WHERE unicId = :graveId");
                $stmt->execute(['graveId' => $data['graveId']]);
            }
            
            // אם משנים לקוח
            if (isset($data['clientId']) && $data['clientId'] != $burial['clientId']) {
                // בדיקת הלקוח החדש
                $stmt = $pdo->prepare("
                    SELECT statusCustomer 
                    FROM customers 
                    WHERE unicId = :clientId AND statusCustomer != 3
                ");
                $stmt->execute(['clientId' => $data['clientId']]);
                
                if (!$stmt->fetch()) {
                    throw new Exception('הלקוח החדש לא זמין');
                }
                
                // שחרור הלקוח הישן (אם אין לו קבורות אחרות)
                $stmt = $pdo->prepare("
                    SELECT COUNT(*) FROM burials 
                    WHERE clientId = :clientId AND unicId != :burialId AND isActive = 1
                ");
                $stmt->execute(['clientId' => $burial['clientId'], 'burialId' => $id]);
                
                if ($stmt->fetchColumn() == 0) {
                    $stmt = $pdo->prepare("UPDATE customers SET statusCustomer = 1 WHERE unicId = :clientId");
                    $stmt->execute(['clientId' => $burial['clientId']]);
                }
                
                // עדכון הלקוח החדש
                $stmt = $pdo->prepare("UPDATE customers SET statusCustomer = 3 WHERE unicId = :clientId");
                $stmt->execute(['clientId' => $data['clientId']]);
            }
            
            // עדכון היסטוריה
            $history = json_decode($burial['historyList'], true) ?? [];
            $history[] = [
                'action' => 'updated',
                'date' => date('Y-m-d H:i:s'),
                'user' => $_SESSION['user_id'] ?? 'system',
                'changes' => array_keys($data)
            ];
            
            // בניית שאילתת העדכון
            $updates = [];
            $params = ['id' => $id];
            
            // רשימת שדות שניתן לעדכן
            $allowedFields = [
                'clientId', 'graveId', 'purchaseId', 'dateDeath', 'timeDeath',
                'dateBurial', 'timeBurial', 'placeDeath', 'nationalInsuranceBurial',
                'deathAbroad', 'savedGravesList', 'dateOpening_tld', 'reportingBL',
                'contactId', 'kinship', 'buriaLicense', 'comment'
            ];
            
            foreach ($allowedFields as $field) {
                if (isset($data[$field])) {
                    $updates[] = "$field = :$field";
                    if (in_array($field, ['savedGravesList', 'documentsList'])) {
                        $params[$field] = json_encode($data[$field]);
                    } else {
                        $params[$field] = $data[$field];
                    }
                }
            }
            
            // הוסף עדכון היסטוריה ותאריך
            $updates[] = "historyList = :historyList";
            $updates[] = "updateDate = :updateDate";
            $params['historyList'] = json_encode($history);
            $params['updateDate'] = date('Y-m-d H:i:s');
            
            $sql = "UPDATE burials SET " . implode(', ', $updates) . " WHERE unicId = :id";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            
            echo json_encode([
                'success' => true,
                'message' => 'הקבורה עודכנה בהצלחה'
            ]);
            break;
            
        // מחיקת קבורה
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