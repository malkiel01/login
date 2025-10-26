<?php
/*
 * File: api/blocks-api.php
 * Version: 1.0.0
 * Updated: 2025-10-26
 * Author: Malkiel
 * Change Summary:
 * - v1.0.0: יצירה ראשונית - זהה למבנה cemeteries-api.php
 * - תמיכה בפעולות: list, get, create, update, delete, stats, search
 * - הוספת plots_count בתגובת list
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
        // רשימת כל הגושים
        case 'list':
            $search = $_GET['search'] ?? '';
            $cemeteryId = $_GET['cemeteryId'] ?? '';
            $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
            $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
            $offset = ($page - 1) * $limit;
            
            // בניית השאילתה הראשית
            $sql = "SELECT b.*, c.cemeteryNameHe as cemetery_name 
                    FROM blocks b
                    LEFT JOIN cemeteries c ON b.cemeteryId = c.unicId
                    WHERE b.isActive = 1";
            $params = [];
            
            // סינון לפי בית עלמין
            if ($cemeteryId) {
                $sql .= " AND b.cemeteryId = :cemeteryId";
                $params['cemeteryId'] = $cemeteryId;
            }
            
            // חיפוש - כל שדה מקבל פרמטר משלו
            if ($search) {
                $sql .= " AND (
                    b.blockNameHe LIKE :search1 OR 
                    b.blockNameEn LIKE :search2 OR 
                    b.blockCode LIKE :search3 OR 
                    b.blockNumber LIKE :search4 OR
                    c.cemeteryNameHe LIKE :search5
                )";
                $searchTerm = "%$search%";
                $params['search1'] = $searchTerm;
                $params['search2'] = $searchTerm;
                $params['search3'] = $searchTerm;
                $params['search4'] = $searchTerm;
                $params['search5'] = $searchTerm;
            }
            
            // ✅ ספירת תוצאות מסוננות
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
                    b.blockNumber LIKE :search4 OR
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
            
            // ✅ ספירת כל הגושים (ללא סינון חיפוש, אבל עם סינון בית עלמין אם קיים)
            $totalAllSql = "SELECT COUNT(*) FROM blocks WHERE isActive = 1";
            $totalAllParams = [];
            if ($cemeteryId) {
                $totalAllSql .= " AND cemeteryId = :cemeteryId";
                $totalAllParams['cemeteryId'] = $cemeteryId;
            }
            $totalAllStmt = $pdo->prepare($totalAllSql);
            $totalAllStmt->execute($totalAllParams);
            $totalAll = $totalAllStmt->fetchColumn();
            
            // הוספת מיון ועימוד
            $sql .= " ORDER BY b.createDate DESC LIMIT :limit OFFSET :offset";
            
            $stmt = $pdo->prepare($sql);
            foreach ($params as $key => $value) {
                $stmt->bindValue(":$key", $value);
            }
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();
            
            $blocks = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // ✅ הוספת plots_count לכל גוש
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
            
        // קבלת גוש בודד
        case 'get':
            if (!$id) {
                throw new Exception('Block ID is required');
            }
            
            $stmt = $pdo->prepare("
                SELECT b.*, c.cemeteryNameHe as cemetery_name
                FROM blocks b
                LEFT JOIN cemeteries c ON b.cemeteryId = c.unicId
                WHERE (b.unicId = :id OR b.id = :id2) AND b.isActive = 1
            ");
            $stmt->execute(['id' => $id, 'id2' => $id]);
            $block = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$block) {
                throw new Exception('הגוש לא נמצא');
            }
            
            // ספירת חלקות
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM plots WHERE blockId = :id AND isActive = 1");
            $stmt->execute(['id' => $block['unicId']]);
            $block['plots_count'] = $stmt->fetchColumn();
            
            echo json_encode([
                'success' => true,
                'data' => $block
            ]);
            break;
            
        // הוספת גוש חדש
        case 'create':
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (empty($data['blockNameHe'])) {
                throw new Exception('שם הגוש (עברית) הוא שדה חובה');
            }
            
            if (empty($data['cemeteryId'])) {
                throw new Exception('בית עלמין הוא שדה חובה');
            }
            
            // בדיקת קיום בית העלמין
            $stmt = $pdo->prepare("SELECT unicId FROM cemeteries WHERE unicId = :id AND isActive = 1");
            $stmt->execute(['id' => $data['cemeteryId']]);
            if (!$stmt->fetch()) {
                throw new Exception('בית העלמין לא נמצא');
            }
            
            // בדיקת כפל קוד
            if (!empty($data['blockCode'])) {
                $stmt = $pdo->prepare("SELECT unicId FROM blocks WHERE blockCode = :code AND cemeteryId = :cemId AND isActive = 1");
                $stmt->execute(['code' => $data['blockCode'], 'cemId' => $data['cemeteryId']]);
                if ($stmt->fetch()) {
                    throw new Exception('קוד גוש זה כבר קיים בבית עלמין זה');
                }
            }
            
            $data['unicId'] = uniqid('blk_', true);
            $data['createDate'] = date('Y-m-d H:i:s');
            $data['updateDate'] = date('Y-m-d H:i:s');
            $data['isActive'] = 1;
            
            $fields = [
                'unicId', 'cemeteryId', 'blockNameHe', 'blockNameEn', 'blockCode',
                'blockNumber', 'description', 'coordinates', 'documents',
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
            
        // עדכון גוש
        case 'update':
            if (!$id) {
                throw new Exception('Block ID is required');
            }
            
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (empty($data['blockNameHe'])) {
                throw new Exception('שם הגוש (עברית) הוא שדה חובה');
            }
            
            // בדיקת כפל קוד - רק אם השתנה
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
            
            $data['updateDate'] = date('Y-m-d H:i:s');
            
            $fields = [
                'blockNameHe', 'blockNameEn', 'blockCode',
                'blockNumber', 'description', 'coordinates', 'documents', 'updateDate'
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
            
        // מחיקת גוש
        case 'delete':
            if (!$id) {
                throw new Exception('Block ID is required');
            }
            
            // בדיקה אם יש חלקות
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
            
        // סטטיסטיקות
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
            
        // חיפוש מהיר
        case 'search':
            $query = $_GET['q'] ?? '';
            $cemeteryId = $_GET['cemeteryId'] ?? '';
            
            if (strlen($query) < 2) {
                echo json_encode(['success' => true, 'data' => []]);
                break;
            }
            
            $sql = "SELECT b.unicId, b.blockNameHe, b.blockNameEn, b.blockCode, b.blockNumber,
                           c.cemeteryNameHe as cemetery_name
                    FROM blocks b
                    LEFT JOIN cemeteries c ON b.cemeteryId = c.unicId
                    WHERE b.isActive = 1 
                    AND (
                        b.blockNameHe LIKE :query1 OR 
                        b.blockNameEn LIKE :query2 OR 
                        b.blockCode LIKE :query3 OR
                        b.blockNumber LIKE :query4
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