<?php
/*
 * File: dashboards/dashboard/cemeteries/api/rows-api.php
 * Version: 1.0.1
 * Updated: 2025-11-06
 * Author: Malkiel
 * Change Summary:
 * - v1.0.1: תיקון לימיט - אם לא מבקשים pagination, מחזיר הכל
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
            $plotId = $_GET['plotId'] ?? '';
            
            $usePagination = isset($_GET['page']) || (isset($_GET['limit']) && $_GET['limit'] !== 'all');
            
            if ($usePagination) {
                $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
                $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
                $offset = ($page - 1) * $limit;
            }

            $sql = "SELECT r.*, p.plotNameHe, b.blockNameHe
                    FROM rows r
                    LEFT JOIN plots p ON r.plotId = p.unicId
                    LEFT JOIN blocks b ON p.blockId = b.unicId
                    WHERE r.isActive = 1";

            $params = [];
            
            if ($plotId) {
                $sql .= " AND r.plotId = :plotId";
                $params['plotId'] = $plotId;
            }
            
            if ($search) {
                $sql .= " AND (
                    r.lineNameHe LIKE :search1 OR 
                    r.serialNumber LIKE :search2 OR
                    p.plotNameHe LIKE :search3
                )";
                $searchTerm = "%$search%";
                $params['search1'] = $searchTerm;
                $params['search2'] = $searchTerm;
                $params['search3'] = $searchTerm;
            }
            
            $countSql = "SELECT COUNT(*) FROM rows r WHERE r.isActive = 1";
            $countParams = [];
            
            if ($plotId) {
                $countSql .= " AND r.plotId = :plotId";
                $countParams['plotId'] = $plotId;
            }
            
            if ($search) {
                $countSql .= " AND (r.lineNameHe LIKE :search1 OR r.serialNumber LIKE :search2)";
                $countParams['search1'] = $searchTerm;
                $countParams['search2'] = $searchTerm;
            }
            
            $countStmt = $pdo->prepare($countSql);
            $countStmt->execute($countParams);
            $total = $countStmt->fetchColumn();
            
            $sql .= " ORDER BY r.serialNumber, r.lineNameHe";
            
            if ($usePagination) {
                $sql .= " LIMIT :limit OFFSET :offset";
            }
            
            $stmt = $pdo->prepare($sql);
            foreach ($params as $key => $value) {
                $stmt->bindValue(":$key", $value);
            }
            
            if ($usePagination) {
                $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
                $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            }
            
            $stmt->execute();
            
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($rows as &$row) {
                $row['displayName'] = $row['lineNameHe'] ?: "שורה {$row['serialNumber']}";
            }
            
            $response = [
                'success' => true,
                'data' => $rows,
                'total' => $total
            ];
            
            if ($usePagination) {
                $response['pagination'] = [
                    'page' => $page,
                    'limit' => $limit,
                    'total' => $total,
                    'pages' => ceil($total / $limit)
                ];
            }
            
            echo json_encode($response);
            break;
            
        case 'get':
            if (!$id) {
                throw new Exception('Row ID is required');
            }
            
            $stmt = $pdo->prepare("
                SELECT r.*, p.plotNameHe, b.blockNameHe
                FROM rows r
                LEFT JOIN plots p ON r.plotId = p.unicId
                LEFT JOIN blocks b ON p.blockId = b.unicId
                WHERE r.unicId = :id AND r.isActive = 1
            ");
            $stmt->execute(['id' => $id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$row) {
                throw new Exception('השורה לא נמצאה');
            }
            
            $row['displayName'] = $row['lineNameHe'] ?: "שורה {$row['serialNumber']}";
            
            echo json_encode([
                'success' => true,
                'data' => $row
            ]);
            break;
            
        case 'create':
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (empty($data['plotId'])) {
                throw new Exception('חלקה היא שדה חובה');
            }
            
            $stmt = $pdo->prepare("SELECT unicId FROM plots WHERE unicId = :id AND isActive = 1");
            $stmt->execute(['id' => $data['plotId']]);
            if (!$stmt->fetch()) {
                throw new Exception('החלקה לא נמצאה');
            }
            
            if (!empty($data['serialNumber'])) {
                $stmt = $pdo->prepare("SELECT unicId FROM rows WHERE serialNumber = :sn AND plotId = :plotId AND isActive = 1");
                $stmt->execute(['sn' => $data['serialNumber'], 'plotId' => $data['plotId']]);
                if ($stmt->fetch()) {
                    throw new Exception('מספר סידורי זה כבר קיים בחלקה זו');
                }
            }
            
            if (!empty($data['lineNameHe'])) {
                $stmt = $pdo->prepare("SELECT unicId FROM rows WHERE lineNameHe = :name AND plotId = :plotId AND isActive = 1");
                $stmt->execute(['name' => $data['lineNameHe'], 'plotId' => $data['plotId']]);
                if ($stmt->fetch()) {
                    throw new Exception('שורה בשם זה כבר קיימת בחלקה זו');
                }
            }
            
            $data['unicId'] = uniqid('row_', true);
            $data['createDate'] = date('Y-m-d H:i:s');
            $data['updateDate'] = date('Y-m-d H:i:s');
            $data['isActive'] = 1;
            
            $fields = ['unicId', 'plotId', 'lineNameHe', 'lineNameEn', 'serialNumber', 'comments', 'createDate', 'updateDate', 'isActive'];
            
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
            
            $sql = "INSERT INTO rows (" . implode(', ', $insertFields) . ") VALUES (" . implode(', ', $insertValues) . ")";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            
            echo json_encode([
                'success' => true,
                'message' => 'השורה נוספה בהצלחה',
                'unicId' => $data['unicId']
            ]);
            break;
            
        case 'update':
            if (!$id) {
                throw new Exception('Row ID is required');
            }
            
            $data = json_decode(file_get_contents('php://input'), true);
            
            $checkStmt = $pdo->prepare("SELECT serialNumber, lineNameHe, plotId FROM rows WHERE unicId = :id");
            $checkStmt->execute(['id' => $id]);
            $current = $checkStmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$current) {
                throw new Exception('השורה לא נמצאה');
            }
            
            if (!empty($data['serialNumber']) && $current['serialNumber'] != $data['serialNumber']) {
                $stmt = $pdo->prepare("SELECT unicId FROM rows WHERE serialNumber = :sn AND plotId = :plotId AND isActive = 1 AND unicId != :currentId");
                $stmt->execute(['sn' => $data['serialNumber'], 'plotId' => $current['plotId'], 'currentId' => $id]);
                if ($stmt->fetch()) {
                    throw new Exception('מספר סידורי זה כבר קיים בחלקה זו');
                }
            }
            
            if (!empty($data['lineNameHe']) && $current['lineNameHe'] != $data['lineNameHe']) {
                $stmt = $pdo->prepare("SELECT unicId FROM rows WHERE lineNameHe = :name AND plotId = :plotId AND isActive = 1 AND unicId != :currentId");
                $stmt->execute(['name' => $data['lineNameHe'], 'plotId' => $current['plotId'], 'currentId' => $id]);
                if ($stmt->fetch()) {
                    throw new Exception('שורה בשם זה כבר קיימת בחלקה זו');
                }
            }
            
            $data['updateDate'] = date('Y-m-d H:i:s');
            
            $fields = ['lineNameHe', 'lineNameEn', 'serialNumber', 'comments', 'updateDate'];
            
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
            
            $sql = "UPDATE rows SET " . implode(', ', $updateFields) . " WHERE unicId = :id";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            
            echo json_encode([
                'success' => true,
                'message' => 'השורה עודכנה בהצלחה'
            ]);
            break;
            
        case 'delete':
            if (!$id) {
                throw new Exception('Row ID is required');
            }
            
            $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM areaGraves WHERE lineId = :id AND isActive = 1");
            $stmt->execute(['id' => $id]);
            $areaGravesCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
            
            if ($areaGravesCount > 0) {
                throw new Exception('לא ניתן למחוק שורה עם אחוזות קבר פעילות');
            }
            
            $stmt = $pdo->prepare("UPDATE rows SET isActive = 0, inactiveDate = :date WHERE unicId = :id");
            $stmt->execute(['id' => $id, 'date' => date('Y-m-d H:i:s')]);
            
            echo json_encode([
                'success' => true,
                'message' => 'השורה נמחקה בהצלחה'
            ]);
            break;
            
        case 'stats':
            $plotId = $_GET['plotId'] ?? '';
            $stats = [];
            
            $sql = "SELECT COUNT(*) FROM rows WHERE isActive = 1";
            $params = [];
            if ($plotId) {
                $sql .= " AND plotId = :plotId";
                $params['plotId'] = $plotId;
            }
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $stats['total_rows'] = $stmt->fetchColumn();
            
            $sql = "SELECT COUNT(*) as count FROM rows WHERE isActive = 1 AND createDate >= DATE_SUB(NOW(), INTERVAL 1 MONTH)";
            if ($plotId) {
                $sql .= " AND plotId = :plotId";
            }
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $stats['new_this_month'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
            
            echo json_encode(['success' => true, 'data' => $stats]);
            break;
            
        case 'search':
            $query = $_GET['q'] ?? '';
            $plotId = $_GET['plotId'] ?? '';
            
            if (strlen($query) < 2) {
                echo json_encode(['success' => true, 'data' => []]);
                break;
            }
            
            $sql = "SELECT r.unicId, r.lineNameHe, r.lineNameEn, r.serialNumber, p.plotNameHe, b.blockNameHe
                    FROM rows r
                    LEFT JOIN plots p ON r.plotId = p.unicId
                    LEFT JOIN blocks b ON p.blockId = b.unicId
                    WHERE r.isActive = 1 AND (r.lineNameHe LIKE :query1 OR r.lineNameEn LIKE :query2 OR r.serialNumber LIKE :query3)";
            
            $params = ['query1' => "%$query%", 'query2' => "%$query%", 'query3' => "%$query%"];
            
            if ($plotId) {
                $sql .= " AND r.plotId = :plotId";
                $params['plotId'] = $plotId;
            }
            
            $sql .= " LIMIT 10";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($results as &$result) {
                $result['displayName'] = $result['lineNameHe'] ?: "שורה {$result['serialNumber']}";
            }
            
            echo json_encode(['success' => true, 'data' => $results]);
            break;
            
        case 'available':
            $currentGraveId = $_GET['currentGraveId'] ?? null;
            $plotId = $_GET['plotId'] ?? null;
            
            $sql = "
                SELECT DISTINCT r.*,
                CASE WHEN EXISTS(
                    SELECT 1 FROM graves g 
                    INNER JOIN areaGraves ag ON g.areaGraveId = ag.unicId
                    WHERE ag.lineId = r.unicId AND g.unicId = :currentGrave
                ) THEN 1 ELSE 0 END as has_current_grave
                FROM rows r
                WHERE r.isActive = 1
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
            ";
            
            $params = [
                'currentGrave' => $currentGraveId,
                'currentGrave2' => $currentGraveId
            ];
            
            if ($plotId) {
                $sql .= " AND r.plotId = :plotId";
                $params['plotId'] = $plotId;
            }
            
            $sql .= " ORDER BY has_current_grave DESC, r.serialNumber, r.lineNameHe";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
            break;
        default:
            throw new Exception('Invalid action');
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>