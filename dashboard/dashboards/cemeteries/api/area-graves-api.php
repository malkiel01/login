<?php
/*
 * File: api/area-graves-api.php
 * Version: 1.0.0
 * Updated: 2025-10-28
 * Author: Malkiel
 * Change Summary:
 * - v1.0.0: יצירה ראשונית - API מלא לניהול אחוזות קבר
 *   - תמיכה בכל פעולות CRUD
 *   - סינון לפי plotId (דרך lineId → rows.plotId)
 *   - הוספת graves_count לכל אחוזה
 *   - הוספת row_name (שם השורה)
 *   - תמיכה בשדות האמיתיים: graveType, lineId, comments
 */

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
        // =====================================================
        // רשימת כל אחוזות הקבר
        // =====================================================
        case 'list2':
            $search = $_GET['search'] ?? '';
            $plotId = $_GET['plotId'] ?? null; // ⭐ סינון לפי חלקה
            $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
            $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
            $offset = ($page - 1) * $limit;
            
            // בניית השאילתה הראשית עם JOIN לשורות
            $sql = "SELECT 
                        ag.*,
                        r.lineNameHe as row_name,
                        r.plotId as plot_id
                    FROM areaGraves ag
                    LEFT JOIN rows r ON ag.lineId = r.unicId
                    WHERE ag.isActive = 1";

            // $sql = "SELECT ag.* FROM areaGraves_view ag WHERE ag.isActive = 1";

            $params = [];
            
            // ⭐ סינון לפי חלקה (אם plotId נשלח)
            if ($plotId) {
                $sql .= " AND r.plotId = :plotId";
                $params['plotId'] = $plotId;
            }
            
            // חיפוש - כל שדה מקבל פרמטר משלו
            if ($search) {
                $sql .= " AND (
                    ag.areaGraveNameHe LIKE :search1 OR 
                    ag.coordinates LIKE :search2 OR 
                    ag.gravesList LIKE :search3 OR 
                    ag.comments LIKE :search4 OR
                    r.lineNameHe LIKE :search5
                )";
                $searchTerm = "%$search%";
                $params['search1'] = $searchTerm;
                $params['search2'] = $searchTerm;
                $params['search3'] = $searchTerm;
                $params['search4'] = $searchTerm;
                $params['search5'] = $searchTerm;
            }
            
            // ✅ ספירת תוצאות מסוננות
            $countSql = "SELECT COUNT(*) 
                        FROM areaGraves ag
                        LEFT JOIN rows r ON ag.lineId = r.unicId
                        WHERE ag.isActive = 1";
            $countParams = [];
            
            if ($plotId) {
                $countSql .= " AND r.plotId = :plotId";
                $countParams['plotId'] = $plotId;
            }
            
            if ($search) {
                $countSql .= " AND (
                    ag.areaGraveNameHe LIKE :search1 OR 
                    ag.coordinates LIKE :search2 OR 
                    ag.gravesList LIKE :search3 OR 
                    ag.comments LIKE :search4 OR
                    r.lineNameHe LIKE :search5
                )";
                $countParams['search1'] = $searchTerm;
                $countParams['search2'] = $searchTerm;
                $countParams['search3'] = $searchTerm;
                $countParams['search4'] = $searchTerm;
                $countParams['search5'] = $searchTerm;
            }
            
            $countStmt = $pdo->prepare($countSql);
            $countStmt->execute($countParams);
            $total = $countStmt->fetchColumn();
            
            // ✅ ספירת כל אחוזות הקבר (ללא סינון)
            $totalAllSql = "SELECT COUNT(*) FROM areaGraves WHERE isActive = 1";
            $totalAll = $pdo->query($totalAllSql)->fetchColumn();
            
            // הוספת מיון ועימוד
            $sql .= " ORDER BY ag.createDate DESC LIMIT :limit OFFSET :offset";
            
            $stmt = $pdo->prepare($sql);
            foreach ($params as $key => $value) {
                $stmt->bindValue(":$key", $value);
            }
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();
            
            $areaGraves = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // ✅ הוספת graves_count לכל אחוזת קבר
            foreach ($areaGraves as &$areaGrave) {
                // ספירת קברים באחוזה (בהנחה שיש טבלת graves עם שדה areaGraveId)
                $graveStmt = $pdo->prepare("
                    SELECT COUNT(*) 
                    FROM graves 
                    WHERE areaGraveId = :id AND isActive = 1
                ");
                $graveStmt->execute(['id' => $areaGrave['unicId']]);
                $areaGrave['graves_count'] = $graveStmt->fetchColumn();
            }
            
            echo json_encode([
                'success' => true,
                'data' => $areaGraves,
                'pagination' => [
                    'page' => $page,
                    'limit' => $limit,
                    'total' => $total,
                    'totalAll' => $totalAll,
                    'pages' => ceil($total / $limit)
                ]
            ]);
            break;
        case 'list':
            $search = $_GET['search'] ?? '';
            $plotId = $_GET['plotId'] ?? null; // ⭐ סינון לפי חלקה
            $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
            $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
            $offset = ($page - 1) * $limit;
            
            // בניית השאילתה הראשית עם JOIN לשורות
            // $sql = "SELECT 
            //             ag.*,
            //             r.lineNameHe as row_name,
            //             r.plotId as plot_id
            //         FROM areaGraves ag
            //         LEFT JOIN rows r ON ag.lineId = r.unicId
            //         WHERE ag.isActive = 1";

            $sql = "SELECT ag.* FROM areaGraves_view ag WHERE ag.isActive = 1";

            $params = [];
            
            // ⭐ סינון לפי חלקה (אם plotId נשלח)
            if ($plotId) {
                $sql .= " AND ag.plot_id = :plotId";  // ⭐ plot_id במקום plotId
                $params['plotId'] = $plotId;
            }
            
            // חיפוש - כל שדה מקבל פרמטר משלו
            if ($search) {
                $sql .= " AND (
                    ag.areaGraveNameHe LIKE :search1 OR 
                    ag.coordinates LIKE :search2 OR 
                    ag.gravesList LIKE :search3 OR 
                    ag.comments LIKE :search4 OR
                    ag.lineNameHe LIKE :search5 OR
                    ag.plotNameHe LIKE :search6 OR
                    ag.blockNameHe LIKE :search7 OR
                    ag.cemeteryNameHe LIKE :search8
                )";
                $searchTerm = "%$search%";
                $params['search1'] = $searchTerm;
                $params['search2'] = $searchTerm;
                $params['search3'] = $searchTerm;
                $params['search4'] = $searchTerm;
                $params['search5'] = $searchTerm;
                $params['search6'] = $searchTerm;
                $params['search7'] = $searchTerm;
                $params['search8'] = $searchTerm;
            }
            
            // ✅ ספירת תוצאות מסוננות
            $countSql = "SELECT COUNT(*) 
                        FROM areaGraves_view ag
                        WHERE ag.isActive = 1";
            $countParams = [];
            
            if ($plotId) {
                $countSql .= " AND ag.plot_id = :plotId";
                $countParams['plotId'] = $plotId;
            }
            
            if ($search) {
                $countSql .= " AND (
                    ag.areaGraveNameHe LIKE :search1 OR 
                    ag.coordinates LIKE :search2 OR 
                    ag.gravesList LIKE :search3 OR 
                    ag.comments LIKE :search4 OR
                    ag.lineNameHe LIKE :search5 OR
                    ag.plotNameHe LIKE :search6 OR
                    ag.blockNameHe LIKE :search7 OR
                    ag.cemeteryNameHe LIKE :search8
                )";
                $countParams['search1'] = $searchTerm;
                $countParams['search2'] = $searchTerm;
                $countParams['search3'] = $searchTerm;
                $countParams['search4'] = $searchTerm;
                $countParams['search5'] = $searchTerm;
                $countParams['search6'] = $searchTerm;
                $countParams['search7'] = $searchTerm;
                $countParams['search8'] = $searchTerm;
            }
            
            $countStmt = $pdo->prepare($countSql);
            $countStmt->execute($countParams);
            $total = $countStmt->fetchColumn();
            
            // ✅ ספירת כל אחוזות הקבר (ללא סינון)
            $totalAllSql = "SELECT COUNT(*) FROM areaGraves_view WHERE isActive = 1";
            $totalAll = $pdo->query($totalAllSql)->fetchColumn();
            
            // הוספת מיון ועימוד
            $sql .= " ORDER BY ag.createDate DESC LIMIT :limit OFFSET :offset";
            
            $stmt = $pdo->prepare($sql);
            foreach ($params as $key => $value) {
                $stmt->bindValue(":$key", $value);
            }
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();
            
            $areaGraves = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // ✅ הוספת graves_count לכל אחוזת קבר
            foreach ($areaGraves as &$areaGrave) {
                // ספירת קברים באחוזה (בהנחה שיש טבלת graves עם שדה areaGraveId)
                $graveStmt = $pdo->prepare("
                    SELECT COUNT(*) 
                    FROM graves 
                    WHERE areaGraveId = :id AND isActive = 1
                ");
                $graveStmt->execute(['id' => $areaGrave['unicId']]);
                $areaGrave['graves_count'] = $graveStmt->fetchColumn();
            }
            
            echo json_encode([
                'success' => true,
                'data' => $areaGraves,
                'pagination' => [
                    'page' => $page,
                    'limit' => $limit,
                    'total' => $total,
                    'totalAll' => $totalAll,
                    'pages' => ceil($total / $limit)
                ]
            ]);
            break;
            
        // =====================================================
        // קבלת אחוזת קבר בודדת
        // =====================================================
        case 'get':
            if (!$id) {
                throw new Exception('Area Grave ID is required');
            }
            
            $stmt = $pdo->prepare("
                SELECT 
                    ag.*,
                    r.lineNameHe as row_name,
                    r.plotId as plot_id
                FROM areaGraves ag
                LEFT JOIN rows r ON ag.lineId = r.unicId
                WHERE ag.unicId = :id AND ag.isActive = 1
            ");
            $stmt->execute(['id' => $id]);
            $areaGrave = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$areaGrave) {
                throw new Exception('אחוזת הקבר לא נמצאה');
            }
            
            // ספירת קברים באחוזה
            $stmt = $pdo->prepare("
                SELECT COUNT(*) 
                FROM graves 
                WHERE areaGraveId = :id AND isActive = 1
            ");
            $stmt->execute(['id' => $areaGrave['unicId']]);
            $areaGrave['graves_count'] = $stmt->fetchColumn();
            
            echo json_encode([
                'success' => true,
                'data' => $areaGrave
            ]);
            break;
            
        // =====================================================
        // הוספת אחוזת קבר חדשה
        // =====================================================
        case 'create':
            $data = json_decode(file_get_contents('php://input'), true);
            
            // ולידציה - שדות חובה
            if (empty($data['areaGraveNameHe'])) {
                throw new Exception('שם אחוזת הקבר (עברית) הוא שדה חובה');
            }
            
            if (empty($data['lineId'])) {
                throw new Exception('יש לבחור שורה לאחוזת הקבר');
            }
            
            // בדיקה שהשורה קיימת
            $stmt = $pdo->prepare("SELECT unicId FROM rows WHERE unicId = :lineId AND isActive = 1");
            $stmt->execute(['lineId' => $data['lineId']]);
            if (!$stmt->fetch()) {
                throw new Exception('השורה שנבחרה אינה קיימת במערכת');
            }
            
            // יצירת unicId ייחודי
            $data['unicId'] = uniqid('ag_', true);
            $data['createDate'] = date('Y-m-d H:i:s');
            $data['updateDate'] = date('Y-m-d H:i:s');
            $data['isActive'] = 1;
            
            // רשימת שדות אפשריים
            $fields = [
                'unicId', 'areaGraveNameHe', 'coordinates', 'gravesList',
                'graveType', 'lineId', 'comments', 'documentsList',
                'createDate', 'updateDate', 'isActive'
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
            
            $sql = "INSERT INTO areaGraves (" . implode(', ', $insertFields) . ")
                    VALUES (" . implode(', ', $insertValues) . ")";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            
            echo json_encode([
                'success' => true,
                'message' => 'אחוזת הקבר נוספה בהצלחה',
                'id' => $pdo->lastInsertId(),
                'unicId' => $data['unicId']
            ]);
            break;
            
        // =====================================================
        // עדכון אחוזת קבר
        // =====================================================
        case 'update':
            if (!$id) {
                throw new Exception('Area Grave ID is required');
            }
            
            $data = json_decode(file_get_contents('php://input'), true);
            
            // ולידציה - שדות חובה
            if (empty($data['areaGraveNameHe'])) {
                throw new Exception('שם אחוזת הקבר (עברית) הוא שדה חובה');
            }
            
            // אם מעדכנים את lineId - בדוק שהשורה קיימת
            if (isset($data['lineId'])) {
                $stmt = $pdo->prepare("SELECT unicId FROM rows WHERE unicId = :lineId AND isActive = 1");
                $stmt->execute(['lineId' => $data['lineId']]);
                if (!$stmt->fetch()) {
                    throw new Exception('השורה שנבחרה אינה קיימת במערכת');
                }
            }
            
            $data['updateDate'] = date('Y-m-d H:i:s');
            
            // רשימת שדות שניתן לעדכן
            $fields = [
                'areaGraveNameHe', 'coordinates', 'gravesList',
                'graveType', 'lineId', 'comments', 'documentsList', 'updateDate'
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
            
            $sql = "UPDATE areaGraves SET " . implode(', ', $updateFields) . " WHERE unicId = :id";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            
            echo json_encode([
                'success' => true,
                'message' => 'אחוזת הקבר עודכנה בהצלחה'
            ]);
            break;
            
        // =====================================================
        // מחיקת אחוזת קבר (מחיקה לוגית)
        // =====================================================
        case 'delete':
            if (!$id) {
                throw new Exception('Area Grave ID is required');
            }
            
            // בדיקה אם יש קברים פעילים באחוזה
            $stmt = $pdo->prepare("
                SELECT COUNT(*) as count 
                FROM graves 
                WHERE areaGraveId = :id AND isActive = 1
            ");
            $stmt->execute(['id' => $id]);
            $graves = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
            
            if ($graves > 0) {
                throw new Exception('לא ניתן למחוק אחוזת קבר עם קברים פעילים');
            }
            
            // מחיקה לוגית
            $stmt = $pdo->prepare("
                UPDATE areaGraves 
                SET isActive = 0, inactiveDate = :date 
                WHERE unicId = :id
            ");
            $stmt->execute(['id' => $id, 'date' => date('Y-m-d H:i:s')]);
            
            echo json_encode([
                'success' => true,
                'message' => 'אחוזת הקבר נמחקה בהצלחה'
            ]);
            break;
            
        // =====================================================
        // סטטיסטיקות אחוזות קבר
        // =====================================================
        case 'stats':
            $plotId = $_GET['plotId'] ?? null;
            $stats = [];
            
            // ✅ סטטיסטיקות כלליות או מסוננות לפי plotId
            if ($plotId) {
                // סטטיסטיקות לחלקה ספציפית
                $stmt = $pdo->prepare("
                    SELECT COUNT(*) 
                    FROM areaGraves ag
                    INNER JOIN rows r ON ag.lineId = r.unicId
                    WHERE ag.isActive = 1 AND r.plotId = :plotId
                ");
                $stmt->execute(['plotId' => $plotId]);
                $stats['total_area_graves'] = $stmt->fetchColumn();
                
                // ספירת קברים בכל אחוזות הקבר של החלקה
                $stmt = $pdo->prepare("
                    SELECT COUNT(*) 
                    FROM graves g
                    INNER JOIN areaGraves ag ON g.areaGraveId = ag.unicId
                    INNER JOIN rows r ON ag.lineId = r.unicId
                    WHERE g.isActive = 1 AND r.plotId = :plotId
                ");
                $stmt->execute(['plotId' => $plotId]);
                $stats['total_graves'] = $stmt->fetchColumn();
                
                // חדשות החודש
                $stmt = $pdo->prepare("
                    SELECT COUNT(*) 
                    FROM areaGraves ag
                    INNER JOIN rows r ON ag.lineId = r.unicId
                    WHERE ag.isActive = 1 
                    AND r.plotId = :plotId
                    AND ag.createDate >= DATE_SUB(NOW(), INTERVAL 1 MONTH)
                ");
                $stmt->execute(['plotId' => $plotId]);
                $stats['new_this_month'] = $stmt->fetchColumn();
                
            } else {
                // סטטיסטיקות כלליות
                $stmt = $pdo->query("SELECT COUNT(*) FROM areaGraves WHERE isActive = 1");
                $stats['total_area_graves'] = $stmt->fetchColumn();
                
                $stmt = $pdo->query("SELECT COUNT(*) FROM graves WHERE isActive = 1");
                $stats['total_graves'] = $stmt->fetchColumn();
                
                $stmt = $pdo->query("
                    SELECT COUNT(*) 
                    FROM areaGraves 
                    WHERE isActive = 1 
                    AND createDate >= DATE_SUB(NOW(), INTERVAL 1 MONTH)
                ");
                $stats['new_this_month'] = $stmt->fetchColumn();
            }
            
            echo json_encode(['success' => true, 'data' => $stats]);
            break;
            
        // =====================================================
        // חיפוש מהיר
        // =====================================================
        case 'search':
            $query = $_GET['q'] ?? '';
            if (strlen($query) < 2) {
                echo json_encode(['success' => true, 'data' => []]);
                break;
            }
            
            $stmt = $pdo->prepare("
                SELECT 
                    ag.unicId, 
                    ag.areaGraveNameHe, 
                    ag.coordinates,
                    ag.graveType,
                    r.lineNameHe as row_name
                FROM areaGraves ag
                LEFT JOIN rows r ON ag.lineId = r.unicId
                WHERE ag.isActive = 1 
                AND (
                    ag.areaGraveNameHe LIKE :query OR 
                    ag.coordinates LIKE :query OR 
                    ag.gravesList LIKE :query OR
                    r.lineNameHe LIKE :query
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