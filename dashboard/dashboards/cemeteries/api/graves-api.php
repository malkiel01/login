<?php
/*
 * File: api/graves-api.php
 * Version: 1.0.1
 * Updated: 2025-10-29
 * Author: Malkiel
 * Change Summary:
 * - v1.0.1: התאמה למבנה הטבלה האמיתי
 *   - graveNameHe (לא graveNumber)
 *   - graveStatus (1=פנוי, 2=נרכש, 3=קבור, 4=שמור)
 *   - plotType, graveLocation, constructionCost, isSmallGrave
 * - v1.0.0: יצירת API עם pagination
 */

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
            $search = $_GET['search'] ?? '';
            $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
            $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 999999;
            $offset = ($page - 1) * $limit;
            
            $areaGraveId = $_GET['areaGraveId'] ?? null;
            
            // בניית השאילתה
            $sql = "SELECT g.* FROM graves_view g WHERE g.isActive = 1";
            $params = [];
            
            // סינון לפי אחוזת קבר
            if ($areaGraveId) {
                $sql .= " AND g.areaGraveId = :areaGraveId";
                $params['areaGraveId'] = $areaGraveId;
            }
            
            // חיפוש
            if ($search) {
                $sql .= " AND (
                    g.graveNameHe LIKE :search1 OR 
                    g.comments LIKE :search2 OR
                    ag.areaGraveNameHe LIKE :search3
                )";
                $searchTerm = "%$search%";
                $params['search1'] = $searchTerm;
                $params['search2'] = $searchTerm;
                $params['search3'] = $searchTerm;
            }
            
            // ספירת תוצאות
            $countSql = "SELECT COUNT(*) FROM graves g WHERE g.isActive = 1";
            $countParams = [];
            
            if ($areaGraveId) {
                $countSql .= " AND g.areaGraveId = :areaGraveId";
                $countParams['areaGraveId'] = $areaGraveId;
            }
            
            if ($search) {
                $countSql .= " AND (
                    g.graveNameHe LIKE :search1 OR 
                    g.comments LIKE :search2
                )";
                $countParams['search1'] = $searchTerm;
                $countParams['search2'] = $searchTerm;
            }
            
            $countStmt = $pdo->prepare($countSql);
            $countStmt->execute($countParams);
            $total = $countStmt->fetchColumn();
            
            // מיון ועימוד
            $sql .= " ORDER BY g.createDate DESC LIMIT :limit OFFSET :offset";
            
            $stmt = $pdo->prepare($sql);
            foreach ($params as $key => $value) {
                $stmt->bindValue(":$key", $value);
            }
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();
            
            $graves = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'success' => true,
                'data' => $graves,
                'pagination' => [
                    'page' => $page,
                    'limit' => $limit,
                    'total' => $total,
                    'pages' => ceil($total / $limit)
                ]
            ]);
            break;
            
        case 'get':
            if (!$id) {
                throw new Exception('Grave ID is required');
            }
            
            $stmt = $pdo->prepare("
                SELECT g.*,
                ag.areaGraveNameHe as area_grave_name,
                ag.graveType
                FROM graves g
                LEFT JOIN areaGraves ag ON g.areaGraveId = ag.unicId
                WHERE g.unicId = :id AND g.isActive = 1
            ");
            $stmt->execute(['id' => $id]);
            $grave = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$grave) {
                throw new Exception('הקבר לא נמצא');
            }
            
            echo json_encode([
                'success' => true,
                'data' => $grave
            ]);
            break;
            
        case 'create':
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (empty($data['graveNameHe'])) {
                throw new Exception('שם הקבר הוא שדה חובה');
            }
            
            if (empty($data['areaGraveId'])) {
                throw new Exception('אחוזת קבר היא שדה חובה');
            }
            
            // בדיקת כפל שם קבר באותה אחוזה
            $stmt = $pdo->prepare("
                SELECT unicId FROM graves 
                WHERE graveNameHe = :name 
                AND areaGraveId = :areaGraveId 
                AND isActive = 1
            ");
            $stmt->execute([
                'name' => $data['graveNameHe'],
                'areaGraveId' => $data['areaGraveId']
            ]);
            if ($stmt->fetch()) {
                throw new Exception('שם קבר זה כבר קיים באחוזת הקבר');
            }
            
            $data['unicId'] = uniqid('grave_', true);
            $data['createDate'] = date('Y-m-d H:i:s');
            $data['updateDate'] = date('Y-m-d H:i:s');
            $data['isActive'] = 1;
            
            $fields = [
                'unicId', 'areaGraveId', 'graveNameHe', 'plotType', 'graveStatus',
                'graveLocation', 'constructionCost', 'isSmallGrave',
                'comments', 'documentsList', 'createDate', 'updateDate', 'isActive'
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
            
            $sql = "INSERT INTO graves (" . implode(', ', $insertFields) . ")
                    VALUES (" . implode(', ', $insertValues) . ")";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            
            echo json_encode([
                'success' => true,
                'message' => 'הקבר נוסף בהצלחה',
                'id' => $pdo->lastInsertId(),
                'unicId' => $data['unicId']
            ]);
            break;
            
        case 'update':
            if (!$id) {
                throw new Exception('Grave ID is required');
            }
            
            $data = json_decode(file_get_contents('php://input'), true);
            $data['updateDate'] = date('Y-m-d H:i:s');
            
            $fields = [
                'graveNameHe', 'plotType', 'graveStatus', 'graveLocation',
                'constructionCost', 'isSmallGrave', 'comments', 'documentsList', 'areaGraveId', 'updateDate'
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
            
            $sql = "UPDATE graves SET " . implode(', ', $updateFields) . " WHERE unicId = :id";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            
            echo json_encode([
                'success' => true,
                'message' => 'הקבר עודכן בהצלחה'
            ]);
            break;
            
        case 'delete':
            if (!$id) {
                throw new Exception('Grave ID is required');
            }
            
            $stmt = $pdo->prepare("
                UPDATE graves 
                SET isActive = 0, inactiveDate = :date 
                WHERE unicId = :id
            ");
            $stmt->execute(['id' => $id, 'date' => date('Y-m-d H:i:s')]);
            
            echo json_encode([
                'success' => true,
                'message' => 'הקבר נמחק בהצלחה'
            ]);
            break;
            
        case 'stats':
            $areaGraveId = $_GET['areaGraveId'] ?? null;
            $stats = [];
            
            $sql = "SELECT COUNT(*) FROM graves WHERE isActive = 1";
            $params = [];
            
            if ($areaGraveId) {
                $sql .= " AND areaGraveId = :areaGraveId";
                $params['areaGraveId'] = $areaGraveId;
            }
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $stats['total_graves'] = $stmt->fetchColumn();
            
            // סטטוס: 1=פנוי, 2=נרכש, 3=קבור, 4=שמור
            $statuses = [1 => 'available', 2 => 'purchased', 3 => 'buried', 4 => 'reserved'];
            
            foreach ($statuses as $statusNum => $statusName) {
                $sql = "SELECT COUNT(*) FROM graves WHERE isActive = 1 AND graveStatus = :status";
                if ($areaGraveId) {
                    $sql .= " AND areaGraveId = :areaGraveId";
                }
                $stmt = $pdo->prepare($sql);
                $statusParams = ['status' => $statusNum];
                if ($areaGraveId) {
                    $statusParams['areaGraveId'] = $areaGraveId;
                }
                $stmt->execute($statusParams);
                $stats[$statusName] = $stmt->fetchColumn();
            }
            
            echo json_encode(['success' => true, 'data' => $stats]);
            break;
            
        case 'available':
            $currentGraveId = $_GET['currentGraveId'] ?? null;
            $areaGraveId = $_GET['areaGraveId'] ?? null;
            
            // ✅ קבל את סוג הטופס (purchase/burial)
            $formType = $_GET['type'] ?? 'purchase';
            
            // ✅ קבע את סטטוס הקברים המותרים
            if ($formType === 'burial') {
                // לקבורה: פנויים (1) + נרכשו (2)
                $allowedStatuses = '(1, 2)';
            } else {
                // לרכישה: רק פנויים (1)
                $allowedStatuses = '(1)';
            }
            
            if ($currentGraveId) {
                // ✅ במצב עריכה - כלול גם את הקבר הנוכחי
                $sql = "
                    SELECT
                        g.*,
                        ag.areaGraveNameHe as area_grave_name,
                        CASE WHEN g.unicId = :currentGrave THEN 1 ELSE 0 END as is_current,
                        p.unicId as purchaseId,
                        c.fullNameHe as purchaserName
                    FROM graves g
                    LEFT JOIN areaGraves ag ON g.areaGraveId = ag.unicId
                    LEFT JOIN purchases p ON g.unicId = p.graveId AND p.isActive = 1
                    LEFT JOIN customers c ON p.clientId = c.unicId
                    WHERE (g.graveStatus IN $allowedStatuses OR g.unicId = :currentGrave2)
                    AND g.isActive = 1
                ";
                $params = ['currentGrave' => $currentGraveId, 'currentGrave2' => $currentGraveId];
            } else {
                // ✅ במצב הוספה - רק קברים מותרים
                $sql = "
                    SELECT
                        g.*,
                        ag.areaGraveNameHe as area_grave_name,
                        p.unicId as purchaseId,
                        c.fullNameHe as purchaserName
                    FROM graves g
                    LEFT JOIN areaGraves ag ON g.areaGraveId = ag.unicId
                    LEFT JOIN purchases p ON g.unicId = p.graveId AND p.isActive = 1
                    LEFT JOIN customers c ON p.clientId = c.unicId
                    WHERE g.graveStatus IN $allowedStatuses
                    AND g.isActive = 1
                ";
                $params = [];
            }
            
            if ($areaGraveId) {
                $sql .= " AND g.areaGraveId = :areaGraveId";
                $params['areaGraveId'] = $areaGraveId;
            }
            
            $sql .= " ORDER BY " . ($currentGraveId ? "is_current DESC, " : "") . "g.graveNameHe";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
            break;
        case 'countAvailable':
            // ספירת קברים זמינים לפי גוש, חלקה או שורה
            $blockId = $_GET['blockId'] ?? null;
            $plotId = $_GET['plotId'] ?? null;
            $lineId = $_GET['lineId'] ?? null;
            $formType = $_GET['type'] ?? 'purchase';

            if (!$blockId && !$plotId && !$lineId) {
                throw new Exception('חסר מזהה גוש, חלקה או שורה');
            }

            // קבע את סטטוס הקברים המותרים
            if ($formType === 'burial') {
                // לקבורה: פנויים (1) + נרכשו (2)
                $allowedStatuses = '(1, 2)';
            } else {
                // לרכישה: רק פנויים (1)
                $allowedStatuses = '(1)';
            }

            // בנה שאילתה לפי הפרמטר שהתקבל
            if ($blockId) {
                // ספור קברים זמינים בגוש (דרך כל החלקות, השורות ואחוזות הקבר שלו)
                $sql = "
                    SELECT COUNT(*)
                    FROM graves g
                    INNER JOIN areaGraves ag ON g.areaGraveId = ag.unicId
                    INNER JOIN `rows` r ON ag.lineId = r.unicId
                    INNER JOIN plots p ON r.plotId = p.unicId
                    WHERE p.blockId = :blockId
                    AND p.isActive = 1
                    AND r.isActive = 1
                    AND ag.isActive = 1
                    AND g.isActive = 1
                    AND g.graveStatus IN $allowedStatuses
                ";
                $params = ['blockId' => $blockId];
            } elseif ($plotId) {
                // ספור קברים זמינים בחלקה (דרך כל השורות ואחוזות הקבר שלה)
                $sql = "
                    SELECT COUNT(*)
                    FROM graves g
                    INNER JOIN areaGraves ag ON g.areaGraveId = ag.unicId
                    INNER JOIN `rows` r ON ag.lineId = r.unicId
                    WHERE r.plotId = :plotId
                    AND r.isActive = 1
                    AND ag.isActive = 1
                    AND g.isActive = 1
                    AND g.graveStatus IN $allowedStatuses
                ";
                $params = ['plotId' => $plotId];
            } else {
                // ספור קברים זמינים בשורה
                $sql = "
                    SELECT COUNT(*)
                    FROM graves g
                    INNER JOIN areaGraves ag ON g.areaGraveId = ag.unicId
                    WHERE ag.lineId = :lineId
                    AND ag.isActive = 1
                    AND g.isActive = 1
                    AND g.graveStatus IN $allowedStatuses
                ";
                $params = ['lineId' => $lineId];
            }

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $count = $stmt->fetchColumn();

            echo json_encode([
                'success' => true,
                'count' => (int)$count
            ]);
            break;

        case 'getDetails':
            // קבלת פרטי קבר מלאים כולל רכישה וקבורה
            if (!$id) {
                throw new Exception('מזהה קבר חסר');
            }
            
            // שליפת פרטי הקבר עם כל ההיררכיה
            $stmt = $pdo->prepare("
                SELECT
                    g.*,
                    agv.areaGraveNameHe,
                    agv.lineNameHe,
                    agv.plotNameHe,
                    agv.blockNameHe,
                    agv.cemeteryNameHe,
                    agv.cemeteryId,
                    agv.blockId,
                    agv.plotId,
                    agv.lineId,
                    ag.graveType
                FROM graves g
                LEFT JOIN areaGraves_view agv ON g.areaGraveId = agv.unicId
                LEFT JOIN areaGraves ag ON g.areaGraveId = ag.unicId
                WHERE g.unicId = :id
                AND g.isActive = 1
            ");
            $stmt->execute(['id' => $id]);
            $grave = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$grave) {
                throw new Exception('הקבר לא נמצא');
            }
            
            // שליפת פרטי רכישה אם קיימים
            $stmt = $pdo->prepare("
                SELECT 
                    p.*,
                    c.fullNameHe as clientFullNameHe,
                    c.numId as clientNumId,
                    contact.fullNameHe as contactFullNameHe
                FROM purchases p
                LEFT JOIN customers c ON p.clientId = c.unicId
                LEFT JOIN customers contact ON p.contactId = contact.unicId
                WHERE p.graveId = :graveId AND p.isActive = 1
                LIMIT 1
            ");
            $stmt->execute(['graveId' => $grave['unicId']]);
            $purchase = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($purchase) {
                $grave['purchase'] = $purchase;
            }
            
            // שליפת פרטי קבורה אם קיימים
            $stmt = $pdo->prepare("
                SELECT 
                    b.*,
                    c.fullNameHe as clientFullNameHe,
                    c.numId as clientNumId,
                    c.nameFather as clientNameFather,
                    c.nameMother as clientNameMother,
                    contact.fullNameHe as contactFullNameHe
                FROM burials b
                LEFT JOIN customers c ON b.clientId = c.unicId
                LEFT JOIN customers contact ON b.contactId = contact.unicId
                WHERE b.graveId = :graveId AND b.isActive = 1
                LIMIT 1
            ");
            $stmt->execute(['graveId' => $grave['unicId']]);
            $burial = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($burial) {
                $grave['burial'] = $burial;
            }
            
            echo json_encode([
                'success' => true,
                'data' => $grave
            ], JSON_UNESCAPED_UNICODE);
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