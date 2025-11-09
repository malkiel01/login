<?php
/*
 * File: api/blocks-api.php
 * Version: 1.0.1
 * Updated: 2025-10-26
 * Author: Malkiel
 * Change Summary:
 * - v1.0.1: תיקון התאמה לשדות בבסיס הנתונים
 *   blockLocation (לא blockNumber)
 *   comments (לא description)
 *   documentsList (לא documents)
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
        case 'list':
            $search = $_GET['search'] ?? '';
            $cemeteryId = $_GET['cemeteryId'] ?? '';
            $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
            $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 999999;
            $offset = ($page - 1) * $limit;
            
            $sql = "SELECT b.*, c.cemeteryNameHe
                    FROM blocks b
                    LEFT JOIN cemeteries c ON b.cemeteryId = c.unicId
                    WHERE b.isActive = 1";

            // $sql = "SELECT b.* FROM blocks_view b WHERE b.isActive = 1";

            $params = [];
            
            if ($cemeteryId) {
                $sql .= " AND b.cemeteryId = :cemeteryId";
                $params['cemeteryId'] = $cemeteryId;
            }
            
            if ($search) {
                $sql .= " AND (
                    b.blockNameHe LIKE :search1 OR 
                    b.blockNameEn LIKE :search2 OR 
                    b.blockCode LIKE :search3 OR 
                    b.blockLocation LIKE :search4 OR
                    c.cemeteryNameHe LIKE :search5
                )";
                $searchTerm = "%$search%";
                $params['search1'] = $searchTerm;
                $params['search2'] = $searchTerm;
                $params['search3'] = $searchTerm;
                $params['search4'] = $searchTerm;
                $params['search5'] = $searchTerm;
            }
            
            $countSql = "SELECT COUNT(*) FROM blocks b 
                         LEFT JOIN cemeteries c ON b.cemeteryId = c.unicId
                         WHERE b.isActive = 1";
            $countParams = [];
            
            if ($cemeteryId) {
                $countSql .= " AND b.cemeteryId = :cemeteryId";
                $countParams['cemeteryId'] = $cemeteryId;
            }
            
            if ($search) {
                $countSql .= " AND (
                    b.blockNameHe LIKE :search1 OR 
                    b.blockNameEn LIKE :search2 OR 
                    b.blockCode LIKE :search3 OR 
                    b.blockLocation LIKE :search4 OR
                    c.cemeteryNameHe LIKE :search5
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
            
            $totalAllSql = "SELECT COUNT(*) FROM blocks WHERE isActive = 1";
            $totalAllParams = [];
            if ($cemeteryId) {
                $totalAllSql .= " AND cemeteryId = :cemeteryId";
                $totalAllParams['cemeteryId'] = $cemeteryId;
            }
            $totalAllStmt = $pdo->prepare($totalAllSql);
            $totalAllStmt->execute($totalAllParams);
            $totalAll = $totalAllStmt->fetchColumn();
            
            $sql .= " ORDER BY b.createDate DESC LIMIT :limit OFFSET :offset";
            
            $stmt = $pdo->prepare($sql);
            foreach ($params as $key => $value) {
                $stmt->bindValue(":$key", $value);
            }
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();
            
            $blocks = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($blocks as &$block) {
                $plotStmt = $pdo->prepare("SELECT COUNT(*) FROM plots WHERE blockId = :id AND isActive = 1");
                $plotStmt->execute(['id' => $block['unicId']]);
                $block['plots_count'] = $plotStmt->fetchColumn();
            }
            
            echo json_encode([
                'success' => true,
                'data' => $blocks,
                'pagination' => [
                    'page' => $page,
                    'limit' => $limit,
                    'total' => $total,
                    'totalAll' => $totalAll,
                    'pages' => ceil($total / $limit)
                ]
            ]);
            break;
            
        case 'get':
            if (!$id) {
                throw new Exception('Block ID is required');
            }
            
            $stmt = $pdo->prepare("
                SELECT b.*, c.cemeteryNameHe
                FROM blocks b
                LEFT JOIN cemeteries c ON b.cemeteryId = c.unicId
                WHERE b.unicId = :id AND b.isActive = 1
            ");
            $stmt->execute(['id' => $id]);
            $block = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$block) {
                throw new Exception('הגוש לא נמצא');
            }
            
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM plots WHERE blockId = :id AND isActive = 1");
            $stmt->execute(['id' => $block['unicId']]);
            $block['plots_count'] = $stmt->fetchColumn();
            
            echo json_encode([
                'success' => true,
                'data' => $block
            ]);
            break;
            
        case 'create':
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (empty($data['blockNameHe'])) {
                throw new Exception('שם הגוש (עברית) הוא שדה חובה');
            }
            
            if (empty($data['cemeteryId'])) {
                throw new Exception('בית עלמין הוא שדה חובה');
            }
            
            $stmt = $pdo->prepare("SELECT unicId FROM cemeteries WHERE unicId = :id AND isActive = 1");
            $stmt->execute(['id' => $data['cemeteryId']]);
            if (!$stmt->fetch()) {
                throw new Exception('בית העלמין לא נמצא');
            }
            
            if (!empty($data['blockCode'])) {
                $stmt = $pdo->prepare("SELECT unicId FROM blocks WHERE blockCode = :code AND cemeteryId = :cemId AND isActive = 1");
                $stmt->execute(['code' => $data['blockCode'], 'cemId' => $data['cemeteryId']]);
                if ($stmt->fetch()) {
                    throw new Exception('קוד גוש זה כבר קיים בבית עלמין זה');
                }
            }

            // הוספה אחרי בדיקת blockCode:
            $stmt = $pdo->prepare("SELECT unicId FROM blocks WHERE blockNameHe = :name AND cemeteryId = :cemId AND isActive = 1");
            $stmt->execute(['name' => $data['blockNameHe'], 'cemId' => $data['cemeteryId']]);
            if ($stmt->fetch()) {
                throw new Exception('גוש בשם זה כבר קיים בבית עלמין זה');
            }
            
            $data['unicId'] = uniqid('blk_', true);
            $data['createDate'] = date('Y-m-d H:i:s');
            $data['updateDate'] = date('Y-m-d H:i:s');
            $data['isActive'] = 1;
            
            // ⭐ שדות מותאמים למבנה הטבלה האמיתי
            $fields = [
                'unicId', 'cemeteryId', 'blockNameHe', 'blockNameEn', 'blockCode',
                'blockLocation', 'nationalInsuranceCode', 'comments', 
                'coordinates', 'documentsList',
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
            
            $sql = "INSERT INTO blocks (" . implode(', ', $insertFields) . ")
                    VALUES (" . implode(', ', $insertValues) . ")";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            
            echo json_encode([
                'success' => true,
                'message' => 'הגוש נוסף בהצלחה',
                'id' => $pdo->lastInsertId(),
                'unicId' => $data['unicId']
            ]);
            break;
            
        case 'update':
            if (!$id) {
                throw new Exception('Block ID is required');
            }
            
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (empty($data['blockNameHe'])) {
                throw new Exception('שם הגוש (עברית) הוא שדה חובה');
            }

            
            if (!empty($data['blockCode'])) {
                $checkStmt = $pdo->prepare("SELECT blockCode, cemeteryId FROM blocks WHERE unicId = :id");
                $checkStmt->execute(['id' => $id]);
                $current = $checkStmt->fetch(PDO::FETCH_ASSOC);
                
                if ($current && $current['blockCode'] != $data['blockCode']) {
                    $stmt = $pdo->prepare("SELECT unicId FROM blocks WHERE blockCode = :code AND cemeteryId = :cemId AND isActive = 1");
                    $stmt->execute(['code' => $data['blockCode'], 'cemId' => $current['cemeteryId']]);
                    if ($stmt->fetch()) {
                        throw new Exception('קוד גוש זה כבר קיים בבית עלמין זה');
                    }
                }
            }

            // טען את הגוש הנוכחי
            $checkStmt = $pdo->prepare("SELECT blockNameHe, cemeteryId FROM blocks WHERE unicId = :id");
            $checkStmt->execute(['id' => $id]);
            $current = $checkStmt->fetch(PDO::FETCH_ASSOC);

            // בדוק רק אם השם השתנה
            if ($current && $current['blockNameHe'] != $data['blockNameHe']) {
                $stmt = $pdo->prepare("SELECT unicId FROM blocks WHERE blockNameHe = :name AND cemeteryId = :cemId AND isActive = 1 AND unicId != :currentId");
                $stmt->execute([
                    'name' => $data['blockNameHe'], 
                    'cemId' => $current['cemeteryId'],
                    'currentId' => $id
                ]);
                if ($stmt->fetch()) {
                    throw new Exception('גוש בשם זה כבר קיים בבית עלמין זה');
                }
            }
            
            $data['updateDate'] = date('Y-m-d H:i:s');
            
            // ⭐ שדות מותאמים למבנה הטבלה האמיתי
            $fields = [
                'blockNameHe', 'blockNameEn', 'blockCode',
                'blockLocation', 'nationalInsuranceCode', 'comments',
                'coordinates', 'documentsList', 'cemeteryId','updateDate'
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
            
            $sql = "UPDATE blocks SET " . implode(', ', $updateFields) . " WHERE unicId = :id";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            
            echo json_encode([
                'success' => true,
                'message' => 'הגוש עודכן בהצלחה'
            ]);
            break;
            
        case 'delete':
            if (!$id) {
                throw new Exception('Block ID is required');
            }
            
            $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM plots WHERE blockId = :id AND isActive = 1");
            $stmt->execute(['id' => $id]);
            $plots = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
            
            if ($plots > 0) {
                throw new Exception('לא ניתן למחוק גוש עם חלקות פעילות');
            }
            
            $stmt = $pdo->prepare("UPDATE blocks SET isActive = 0, inactiveDate = :date WHERE unicId = :id");
            $stmt->execute(['id' => $id, 'date' => date('Y-m-d H:i:s')]);
            
            echo json_encode([
                'success' => true,
                'message' => 'הגוש נמחק בהצלחה'
            ]);
            break;
            
        case 'stats':
            $cemeteryId = $_GET['cemeteryId'] ?? '';
            $stats = [];
            
            $sql = "SELECT COUNT(*) FROM blocks WHERE isActive = 1";
            $params = [];
            if ($cemeteryId) {
                $sql .= " AND cemeteryId = :cemeteryId";
                $params['cemeteryId'] = $cemeteryId;
            }
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $stats['total_blocks'] = $stmt->fetchColumn();
            
            $sql = "SELECT COUNT(*) FROM plots WHERE isActive = 1";
            if ($cemeteryId) {
                $sql .= " AND blockId IN (SELECT unicId FROM blocks WHERE cemeteryId = :cemeteryId AND isActive = 1)";
            }
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $stats['total_plots'] = $stmt->fetchColumn();
            
            $sql = "SELECT COUNT(*) as count 
                    FROM blocks 
                    WHERE isActive = 1 
                    AND createDate >= DATE_SUB(NOW(), INTERVAL 1 MONTH)";
            if ($cemeteryId) {
                $sql .= " AND cemeteryId = :cemeteryId";
            }
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $stats['new_this_month'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
            
            echo json_encode(['success' => true, 'data' => $stats]);
            break;
            
        case 'search':
            $query = $_GET['q'] ?? '';
            $cemeteryId = $_GET['cemeteryId'] ?? '';
            
            if (strlen($query) < 2) {
                echo json_encode(['success' => true, 'data' => []]);
                break;
            }
            
            $sql = "SELECT b.unicId, b.blockNameHe, b.blockNameEn, b.blockCode, b.blockLocation,
                           c.cemeteryNameHe
                    FROM blocks b
                    LEFT JOIN cemeteries c ON b.cemeteryId = c.unicId
                    WHERE b.isActive = 1 
                    AND (
                        b.blockNameHe LIKE :query1 OR 
                        b.blockNameEn LIKE :query2 OR 
                        b.blockCode LIKE :query3 OR
                        b.blockLocation LIKE :query4
                    )";
            
            $params = [
                'query1' => "%$query%",
                'query2' => "%$query%",
                'query3' => "%$query%",
                'query4' => "%$query%"
            ];
            
            if ($cemeteryId) {
                $sql .= " AND b.cemeteryId = :cemeteryId";
                $params['cemeteryId'] = $cemeteryId;
            }
            
            $sql .= " LIMIT 10";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode(['success' => true, 'data' => $results]);
            break;
            
        case 'available2':
            $currentGraveId = $_GET['currentGraveId'] ?? null;
            $cemeteryId = $_GET['cemeteryId'] ?? null;
            
            $sql = "
                SELECT DISTINCT b.*,
                CASE WHEN EXISTS(
                    SELECT 1 FROM graves g 
                    INNER JOIN areaGraves ag ON g.areaGraveId = ag.unicId
                    INNER JOIN rows r ON ag.lineId = r.unicId
                    INNER JOIN plots p ON r.plotId = p.unicId
                    WHERE p.blockId = b.unicId AND g.unicId = :currentGrave
                ) THEN 1 ELSE 0 END as has_current_grave
                FROM blocks b
                WHERE b.isActive = 1
                AND EXISTS(
                    SELECT 1 FROM plots p
                    WHERE p.blockId = b.unicId AND p.isActive = 1
                    AND EXISTS(
                        SELECT 1 FROM rows r
                        WHERE r.plotId = p.unicId AND r.isActive = 1
                        AND EXISTS(
                            SELECT 1 FROM areaGraves ag
                            WHERE ag.lineId = r.unicId AND ag.isActive = 1
                            AND EXISTS(
                                SELECT 1 FROM graves g 
                                WHERE g.areaGraveId = ag.unicId 
                                AND (g.graveStatus = 1 OR g.unicId = :currentGrave2)
                                AND g.isActive = 1
                            )
                        )
                    )
                )
            ";
            
            $params = [
                'currentGrave' => $currentGraveId,
                'currentGrave2' => $currentGraveId
            ];
            
            if ($cemeteryId) {
                $sql .= " AND b.cemeteryId = :cemeteryId";
                $params['cemeteryId'] = $cemeteryId;
            }
            
            $sql .= " ORDER BY has_current_grave DESC, b.blockNameHe";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
            break;
        case 'available':
            $currentGraveId = $_GET['currentGraveId'] ?? null;
            $cemeteryId = $_GET['cemeteryId'] ?? null;
            
            // ✅ קבל את סוג הטופס
            $formType = $_GET['type'] ?? 'purchase';
            $allowedStatuses = ($formType === 'burial') ? '(1, 2)' : '(1)';
            
            $sql = "
                SELECT DISTINCT b.*,
                CASE WHEN EXISTS(
                    SELECT 1 FROM graves g 
                    INNER JOIN areaGraves ag ON g.areaGraveId = ag.unicId
                    INNER JOIN rows r ON ag.lineId = r.unicId
                    INNER JOIN plots p ON r.plotId = p.unicId
                    WHERE p.blockId = b.unicId AND g.unicId = :currentGrave
                ) THEN 1 ELSE 0 END as has_current_grave
                FROM blocks b
                WHERE b.isActive = 1
                AND EXISTS(
                    SELECT 1 FROM plots p
                    WHERE p.blockId = b.unicId AND p.isActive = 1
                    AND EXISTS(
                        SELECT 1 FROM rows r
                        WHERE r.plotId = p.unicId AND r.isActive = 1
                        AND EXISTS(
                            SELECT 1 FROM areaGraves ag
                            WHERE ag.lineId = r.unicId AND ag.isActive = 1
                            AND EXISTS(
                                SELECT 1 FROM graves g 
                                WHERE g.areaGraveId = ag.unicId 
                                AND (g.graveStatus IN $allowedStatuses OR g.unicId = :currentGrave2)
                                AND g.isActive = 1
                            )
                        )
                    )
                )
            ";
            
            $params = [
                'currentGrave' => $currentGraveId,
                'currentGrave2' => $currentGraveId
            ];
            
            if ($cemeteryId) {
                $sql .= " AND b.cemeteryId = :cemeteryId";
                $params['cemeteryId'] = $cemeteryId;
            }
            
            $sql .= " ORDER BY has_current_grave DESC, b.blockNameHe";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
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