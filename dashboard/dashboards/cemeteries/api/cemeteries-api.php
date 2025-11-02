<?php
/*
 * File: api/cemeteries-api.php
 * Version: 3.0.0
 * Updated: 2025-10-24
 * Author: Malkiel
 * Change Summary:
 * - v3.0.0: שיטה זהה ללקוחות
 * - הוספת blocks_count בתגובת list
 * - שיפור מבנה התגובות
 * - תיקון זהות שדות
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
        // רשימת כל בתי העלמין
        case 'list':
            $search = $_GET['search'] ?? '';
            $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
            $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
            $offset = ($page - 1) * $limit;
            
            // בניית השאילתה הראשית
            // $sql = "SELECT c.* FROM cemeteries c WHERE c.isActive = 1";
            $sql = "SELECT * FROM cemeteries_view WHERE isActive = 1";
            $params = [];
            
            // חיפוש - כל שדה מקבל פרמטר משלו
            if ($search) {
                $sql .= " AND (
                    c.cemeteryNameHe LIKE :search1 OR 
                    c.cemeteryNameEn LIKE :search2 OR 
                    c.cemeteryCode LIKE :search3 OR 
                    c.address LIKE :search4 OR 
                    c.contactName LIKE :search5 OR
                    c.contactPhoneName LIKE :search6
                )";
                $searchTerm = "%$search%";
                $params['search1'] = $searchTerm;
                $params['search2'] = $searchTerm;
                $params['search3'] = $searchTerm;
                $params['search4'] = $searchTerm;
                $params['search5'] = $searchTerm;
                $params['search6'] = $searchTerm;
            }
            
            // ✅ ספירת תוצאות מסוננות
            $countSql = "SELECT COUNT(*) FROM cemeteries c WHERE c.isActive = 1";
            $countParams = [];
            
            if ($search) {
                $countSql .= " AND (
                    c.cemeteryNameHe LIKE :search1 OR 
                    c.cemeteryNameEn LIKE :search2 OR 
                    c.cemeteryCode LIKE :search3 OR 
                    c.address LIKE :search4 OR 
                    c.contactName LIKE :search5 OR
                    c.contactPhoneName LIKE :search6
                )";
                $countParams['search1'] = $searchTerm;
                $countParams['search2'] = $searchTerm;
                $countParams['search3'] = $searchTerm;
                $countParams['search4'] = $searchTerm;
                $countParams['search5'] = $searchTerm;
                $countParams['search6'] = $searchTerm;
            }
            
            $countStmt = $pdo->prepare($countSql);
            $countStmt->execute($countParams);
            $total = $countStmt->fetchColumn();
            
            // ✅ ספירת כל בתי העלמין (ללא סינון)
            $totalAllSql = "SELECT COUNT(*) FROM cemeteries WHERE isActive = 1";
            $totalAll = $pdo->query($totalAllSql)->fetchColumn();
            
            // הוספת מיון ועימוד
            $sql .= " ORDER BY c.createDate DESC LIMIT :limit OFFSET :offset";
            
            $stmt = $pdo->prepare($sql);
            foreach ($params as $key => $value) {
                $stmt->bindValue(":$key", $value);
            }
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();
            
            $cemeteries = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // ✅ הוספת blocks_count לכל בית עלמין
            foreach ($cemeteries as &$cemetery) {
                $blockStmt = $pdo->prepare("SELECT COUNT(*) FROM blocks WHERE cemeteryId = :id AND isActive = 1");
                $blockStmt->execute(['id' => $cemetery['unicId']]);
                $cemetery['blocks_count'] = $blockStmt->fetchColumn();
            }
            
            echo json_encode([
                'success' => true,
                'data' => $cemeteries,
                'pagination' => [
                    'page' => $page,
                    'limit' => $limit,
                    'total' => $total,
                    'totalAll' => $totalAll,
                    'pages' => ceil($total / $limit)
                ]
            ]);
            break;
            
        // קבלת בית עלמין בודד
        case 'get':
            if (!$id) {
                throw new Exception('Cemetery ID is required');
            }
            
            // ⭐ חיפוש רק לפי unicId!
            $stmt = $pdo->prepare("
                SELECT c.* FROM cemeteries c
                WHERE c.unicId = :id AND c.isActive = 1
            ");
            $stmt->execute(['id' => $id]);
            $cemetery = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$cemetery) {
                throw new Exception('בית העלמין לא נמצא');
            }

            // ספירת גושים
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM blocks WHERE cemeteryId = :id AND isActive = 1");
            $stmt->execute(['id' => $cemetery['unicId']]);
            $cemetery['blocks_count'] = $stmt->fetchColumn();
            
            echo json_encode([
                'success' => true,
                'data' => $cemetery
            ]);
            break;
            
        // הוספת בית עלמין חדש
        case 'create':
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (empty($data['cemeteryNameHe'])) {
                throw new Exception('שם בית העלמין (עברית) הוא שדה חובה');
            }
            
            // בדיקת כפל קוד
            if (!empty($data['cemeteryCode'])) {
                $stmt = $pdo->prepare("SELECT unicId FROM cemeteries WHERE cemeteryCode = :code AND isActive = 1");
                $stmt->execute(['code' => $data['cemeteryCode']]);
                if ($stmt->fetch()) {
                    throw new Exception('קוד בית עלמין זה כבר קיים במערכת');
                }
            }
            
            $data['unicId'] = uniqid('cem_', true);
            $data['createDate'] = date('Y-m-d H:i:s');
            $data['updateDate'] = date('Y-m-d H:i:s');
            $data['isActive'] = 1;
            
            $fields = [
                'unicId', 'cemeteryNameHe', 'cemeteryNameEn', 'cemeteryCode',
                'address', 'contactName', 'contactPhoneName',
                'nationalInsuranceCode', 'coordinates', 'documents',
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
            
            $sql = "INSERT INTO cemeteries (" . implode(', ', $insertFields) . ")
                    VALUES (" . implode(', ', $insertValues) . ")";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            
            echo json_encode([
                'success' => true,
                'message' => 'בית העלמין נוסף בהצלחה',
                'id' => $pdo->lastInsertId(),
                'unicId' => $data['unicId']
            ]);
            break;
            
        // עדכון בית עלמין
        case 'update':
            if (!$id) {
                throw new Exception('Cemetery ID is required');
            }
            
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (empty($data['cemeteryNameHe'])) {
                throw new Exception('שם בית העלמין (עברית) הוא שדה חובה');
            }
            
            // בדיקת כפל קוד - רק אם השתנה
            if (!empty($data['cemeteryCode'])) {
                $checkStmt = $pdo->prepare("SELECT cemeteryCode FROM cemeteries WHERE unicId = :id");
                $checkStmt->execute(['id' => $id]);
                $currentCode = $checkStmt->fetchColumn();
                
                if ($currentCode != $data['cemeteryCode']) {
                    $stmt = $pdo->prepare("SELECT unicId FROM cemeteries WHERE cemeteryCode = :code AND isActive = 1");
                    $stmt->execute(['code' => $data['cemeteryCode']]);
                    if ($stmt->fetch()) {
                        throw new Exception('קוד בית עלמין זה כבר קיים במערכת');
                    }
                }
            }
            
            $data['updateDate'] = date('Y-m-d H:i:s');
            
            $fields = [
                'cemeteryNameHe', 'cemeteryNameEn', 'cemeteryCode',
                'address', 'contactName', 'contactPhoneName',
                'nationalInsuranceCode', 'coordinates', 'documents', 'updateDate'
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
            
            $sql = "UPDATE cemeteries SET " . implode(', ', $updateFields) . " WHERE unicId = :id";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            
            echo json_encode([
                'success' => true,
                'message' => 'בית העלמין עודכן בהצלחה'
            ]);
            break;
            
        // מחיקת בית עלמין
        case 'delete':
            if (!$id) {
                throw new Exception('Cemetery ID is required');
            }
            
            // בדיקה אם יש גושים
            $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM blocks WHERE cemeteryId = :id AND isActive = 1");
            $stmt->execute(['id' => $id]);
            $blocks = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
            
            if ($blocks > 0) {
                throw new Exception('לא ניתן למחוק בית עלמין עם גושים פעילים');
            }
            
            $stmt = $pdo->prepare("UPDATE cemeteries SET isActive = 0, inactiveDate = :date WHERE unicId = :id");
            $stmt->execute(['id' => $id, 'date' => date('Y-m-d H:i:s')]);
            
            echo json_encode([
                'success' => true,
                'message' => 'בית העלמין נמחק בהצלחה'
            ]);
            break;
            
        // סטטיסטיקות
        case 'stats':
            $stats = [];
            
            $stmt = $pdo->query("SELECT COUNT(*) FROM cemeteries WHERE isActive = 1");
            $stats['total_cemeteries'] = $stmt->fetchColumn();
            
            $stmt = $pdo->query("SELECT COUNT(*) FROM blocks WHERE isActive = 1");
            $stats['total_blocks'] = $stmt->fetchColumn();
            
            $stmt = $pdo->query("
                SELECT COUNT(*) as count 
                FROM cemeteries 
                WHERE isActive = 1 
                AND createDate >= DATE_SUB(NOW(), INTERVAL 1 MONTH)
            ");
            $stats['new_this_month'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
            
            echo json_encode(['success' => true, 'data' => $stats]);
            break;
            
        // חיפוש מהיר
        case 'search':
            $query = $_GET['q'] ?? '';
            if (strlen($query) < 2) {
                echo json_encode(['success' => true, 'data' => []]);
                break;
            }
            
            $stmt = $pdo->prepare("
                SELECT unicId, cemeteryNameHe, cemeteryNameEn, cemeteryCode, address
                FROM cemeteries 
                WHERE isActive = 1 
                AND (
                    cemeteryNameHe LIKE :query OR 
                    cemeteryNameEn LIKE :query OR 
                    cemeteryCode LIKE :query OR
                    address LIKE :query
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