<?php
/*
 * File: api/plots-api.php
 * Version: 1.0.1
 * Updated: 2025-10-27
 * Author: Malkiel
 * Change Summary:
 * - v1.0.1: תיקון התאמה לשדות בבסיס הנתונים
 *   plotLocation (לא plotNumber)
 *   comments (לא description)
 *   documentsList (לא documents)
 */

// אימות והרשאות - חייב להיות מחובר!
require_once __DIR__ . '/api-auth.php';

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
            requireViewPermission('plots');
            $search = $_GET['search'] ?? '';
            $blockId = $_GET['blockId'] ?? '';
            $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
            $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 999999;
            $offset = ($page - 1) * $limit;

            // ⭐ פרמטרי מיון
            $allowedSortColumns = ['plotNameHe', 'plotNameEn', 'plotCode', 'plotLocation', 'createDate', 'availableSum', 'savedSum', 'purchasedSum', 'buriedSum', 'graveSum'];
            $orderBy = $_GET['orderBy'] ?? 'createDate';
            $sortDirection = strtoupper($_GET['sortDirection'] ?? 'DESC');
            if (!in_array($orderBy, $allowedSortColumns)) $orderBy = 'createDate';
            if (!in_array($sortDirection, ['ASC', 'DESC'])) $sortDirection = 'DESC';

            $sql = "SELECT p.* FROM plots_view p WHERE isActive = 1";            

            $params = [];
            
            if ($blockId) {
                $sql .= " AND p.blockId = :blockId";
                $params['blockId'] = $blockId;
            }
            
            if ($search) {
                $sql .= " AND (
                    p.plotNameHe LIKE :search1 OR 
                    p.plotNameEn LIKE :search2 OR 
                    p.plotCode LIKE :search3 OR 
                    p.plotLocation LIKE :search4 OR
                    p.blockNameHe LIKE :search5 OR
                    p.cemeteryNameHe LIKE :search6
                )";
                $searchTerm = "%$search%";
                $params['search1'] = $searchTerm;
                $params['search2'] = $searchTerm;
                $params['search3'] = $searchTerm;
                $params['search4'] = $searchTerm;
                $params['search5'] = $searchTerm;
                $params['search6'] = $searchTerm;
            }
            
            $countSql = "SELECT COUNT(*) FROM plots_view p 
                        --  LEFT JOIN blocks b ON p.blockId = b.unicId
                         WHERE p.isActive = 1";
            $countParams = [];
            
            if ($blockId) {
                $countSql .= " AND p.blockId = :blockId";
                $countParams['blockId'] = $blockId;
            }
            
            if ($search) {
                $countSql .= " AND (
                    p.plotNameHe LIKE :search1 OR 
                    p.plotNameEn LIKE :search2 OR 
                    p.plotCode LIKE :search3 OR 
                    p.plotLocation LIKE :search4 OR
                    p.blockNameHe LIKE :search5 OR
                    p.cemeteryNameHe LIKE :search6
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
            
            $totalAllSql = "SELECT COUNT(*) FROM plots WHERE isActive = 1";
            $totalAllParams = [];
            if ($blockId) {
                $totalAllSql .= " AND blockId = :blockId";
                $totalAllParams['blockId'] = $blockId;
            }
            $totalAllStmt = $pdo->prepare($totalAllSql);
            $totalAllStmt->execute($totalAllParams);
            $totalAll = $totalAllStmt->fetchColumn();
            
            $sql .= " ORDER BY p.{$orderBy} {$sortDirection} LIMIT :limit OFFSET :offset";
            
            $stmt = $pdo->prepare($sql);
            foreach ($params as $key => $value) {
                $stmt->bindValue(":$key", $value);
            }
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();
            
            $plots = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // ⭐ ספירת שורות לכל חלקה (כרגע תמיד 0 - מוכן לעתיד)
            foreach ($plots as &$plot) {
                // כאשר תיצור טבלת rows, הסר את ההערה מהשורה הבאה:
                // $rowStmt = $pdo->prepare("SELECT COUNT(*) FROM rows WHERE plotId = :id AND isActive = 1");
                // $rowStmt->execute(['id' => $plot['unicId']]);
                // $plot['rows_count'] = $rowStmt->fetchColumn();
                
                // זמני - עד שתיצור טבלת rows:
                $plot['rows_count'] = 0;
            }
            
            echo json_encode([
                'success' => true,
                'data' => $plots,
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
            requireViewPermission('plots');
            if (!$id) {
                throw new Exception('Plot ID is required');
            }

            $stmt = $pdo->prepare("
                SELECT p.*, b.blockNameHe
                FROM plots p
                LEFT JOIN blocks b ON p.blockId = b.unicId
                WHERE p.unicId = :id AND p.isActive = 1
            ");
            $stmt->execute(['id' => $id]);
            $plot = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$plot) {
                throw new Exception('החלקה לא נמצאה');
            }
            
            $plot['rows_count'] = 0;
            
            echo json_encode([
                'success' => true,
                'data' => $plot
            ]);
            break;
            
        // רשימת שורות של חלקה
        case 'list_rows':
            $plotId = $_GET['plotId'] ?? null;
            
            if (!$plotId) {
                throw new Exception('Plot ID is required');
            }
            
            $sql = "SELECT r.unicId, r.lineNameHe, r.serialNumber, r.plotId 
                    FROM rows r 
                    WHERE r.plotId = :plotId AND r.isActive = 1
                    ORDER BY r.serialNumber, r.lineNameHe";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute(['plotId' => $plotId]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // הוסף שם מלא לכל שורה
            foreach ($rows as &$row) {
                $row['name'] = $row['lineNameHe'] ?: "שורה {$row['serialNumber']}";
            }
            
            echo json_encode([
                'success' => true,
                'data' => $rows,
                'total' => count($rows)
            ]);
            break;

        case 'create':
            requireCreatePermission('plots');
            $data = json_decode(file_get_contents('php://input'), true);

            if (empty($data['plotNameHe'])) {
                throw new Exception('שם החלקה (עברית) הוא שדה חובה');
            }
            
            if (empty($data['blockId'])) {
                throw new Exception('גוש הוא שדה חובה');
            }
            
            $stmt = $pdo->prepare("SELECT unicId FROM blocks WHERE unicId = :id AND isActive = 1");
            $stmt->execute(['id' => $data['blockId']]);
            if (!$stmt->fetch()) {
                throw new Exception('הגוש לא נמצא');
            }
            
            // בדיקת קוד חלקה
            if (!empty($data['plotCode'])) {
                $stmt = $pdo->prepare("SELECT unicId FROM plots WHERE plotCode = :code AND blockId = :blkId AND isActive = 1");
                $stmt->execute(['code' => $data['plotCode'], 'blkId' => $data['blockId']]);
                if ($stmt->fetch()) {
                    throw new Exception('קוד חלקה זה כבר קיים בגוש זה');
                }
            }

            // בדיקת שם חלקה בעברית
            $stmt = $pdo->prepare("SELECT unicId FROM plots WHERE plotNameHe = :name AND blockId = :blkId AND isActive = 1");
            $stmt->execute(['name' => $data['plotNameHe'], 'blkId' => $data['blockId']]);
            if ($stmt->fetch()) {
                throw new Exception('חלקה בשם זה כבר קיימת בגוש זה');
            }
            
            $data['unicId'] = uniqid('plt_', true);
            $data['createDate'] = date('Y-m-d H:i:s');
            $data['updateDate'] = date('Y-m-d H:i:s');
            $data['isActive'] = 1;
            
            // ⭐ שדות מותאמים למבנה הטבלה האמיתי
            $fields = [
                'unicId', 'blockId', 'plotNameHe', 'plotNameEn', 'plotCode',
                'plotLocation', 'nationalInsuranceCode', 'comments', 
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
            
            $sql = "INSERT INTO plots (" . implode(', ', $insertFields) . ")
                    VALUES (" . implode(', ', $insertValues) . ")";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            
            echo json_encode([
                'success' => true,
                'message' => 'החלקה נוספה בהצלחה',
                'id' => $pdo->lastInsertId(),
                'unicId' => $data['unicId']
            ]);
            break;
            
        case 'update':
            requireEditPermission('plots');
            if (!$id) {
                throw new Exception('Plot ID is required');
            }

            $data = json_decode(file_get_contents('php://input'), true);

            if (empty($data['plotNameHe'])) {
                throw new Exception('שם החלקה (עברית) הוא שדה חובה');
            }
            
            // ⭐ קריאה אחת ל-DB - טען את כל הנתונים הנוכחיים
            $checkStmt = $pdo->prepare("SELECT plotNameHe, plotCode, blockId FROM plots WHERE unicId = :id");
            $checkStmt->execute(['id' => $id]);
            $current = $checkStmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$current) {
                throw new Exception('החלקה לא נמצאה');
            }
            
            // ✅ בדיקת קוד חלקה (פעם אחת!)
            if (!empty($data['plotCode']) && $current['plotCode'] != $data['plotCode']) {
                $stmt = $pdo->prepare("SELECT unicId FROM plots WHERE plotCode = :code AND blockId = :blkId AND isActive = 1");
                $stmt->execute(['code' => $data['plotCode'], 'blkId' => $current['blockId']]);
                if ($stmt->fetch()) {
                    throw new Exception('קוד חלקה זה כבר קיים בגוש זה');
                }
            }
            
            // ✅ בדיקת שם חלקה
            if ($current['plotNameHe'] != $data['plotNameHe']) {
                $stmt = $pdo->prepare("SELECT unicId FROM plots WHERE plotNameHe = :name AND blockId = :blkId AND isActive = 1 AND unicId != :currentId");
                $stmt->execute([
                    'name' => $data['plotNameHe'], 
                    'blkId' => $current['blockId'],
                    'currentId' => $id
                ]);
                if ($stmt->fetch()) {
                    throw new Exception('חלקה בשם זה כבר קיימת בגוש זה');
                }
            }
  
            $data['updateDate'] = date('Y-m-d H:i:s');
            
            // ⭐ שדות מותאמים למבנה הטבלה האמיתי
            $fields = [
                'plotNameHe', 'plotNameEn', 'plotCode',
                'plotLocation', 'nationalInsuranceCode', 'comments',
                'coordinates', 'documentsList', 'updateDate', 'blockId'
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
            
            $sql = "UPDATE plots SET " . implode(', ', $updateFields) . " WHERE unicId = :id";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            
            echo json_encode([
                'success' => true,
                'message' => 'החלקה עודכנה בהצלחה'
            ]);
            break;
            
        case 'delete':
            requireDeletePermission('plots');
            if (!$id) {
                throw new Exception('Plot ID is required');
            }

            // שלב 1: בדוק אם יש שורות לחלקה
            $stmt = $pdo->prepare("
                SELECT unicId 
                FROM rows 
                WHERE plotId = :id AND isActive = 1
            ");
            $stmt->execute(['id' => $id]);
            $activeRows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // שלב 2: אם יש שורות - בדוק אחוזות קבר
            if (count($activeRows) > 0) {
                $rowIds = array_column($activeRows, 'unicId');
                $placeholders = implode(',', array_fill(0, count($rowIds), '?'));
                
                // בדוק אם יש אחוזות קבר פעילות
                $stmt = $pdo->prepare("
                    SELECT COUNT(*) as count 
                    FROM areaGraves 
                    WHERE lineId IN ($placeholders) AND isActive = 1
                ");
                $stmt->execute($rowIds);
                $areaGravesCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
                
                // אם יש אחוזות קבר - אסור למחוק
                if ($areaGravesCount > 0) {
                    throw new Exception('לא ניתן למחוק חלקה עם אחוזות קבר פעילות. יש למחוק תחילה את אחוזות הקבר.');
                }
                
                // אין אחוזות קבר - מחק את השורות
                $stmt = $pdo->prepare("
                    UPDATE rows 
                    SET isActive = 0, inactiveDate = :date 
                    WHERE plotId = :id AND isActive = 1
                ");
                $stmt->execute([
                    'id' => $id, 
                    'date' => date('Y-m-d H:i:s')
                ]);
            }
            
            // שלב 3: מחק את החלקה
            $stmt = $pdo->prepare("
                UPDATE plots 
                SET isActive = 0, inactiveDate = :date 
                WHERE unicId = :id
            ");
            $stmt->execute(['id' => $id, 'date' => date('Y-m-d H:i:s')]);
            
            echo json_encode([
                'success' => true,
                'message' => 'החלקה נמחקה בהצלחה'
            ]);
            break;
        
        case 'stats':
            requireViewPermission('plots');
            $blockId = $_GET['blockId'] ?? '';
            $stats = [];
            
            $sql = "SELECT COUNT(*) FROM plots WHERE isActive = 1";
            $params = [];
            if ($blockId) {
                $sql .= " AND blockId = :blockId";
                $params['blockId'] = $blockId;
            }
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $stats['total_plots'] = $stmt->fetchColumn();
            
            // ⭐ ספירת שורות (כרגע 0 - מוכן לעתיד)
            // $sql = "SELECT COUNT(*) FROM rows WHERE isActive = 1";
            // if ($blockId) {
            //     $sql .= " AND plotId IN (SELECT unicId FROM plots WHERE blockId = :blockId AND isActive = 1)";
            // }
            // $stmt = $pdo->prepare($sql);
            // $stmt->execute($params);
            // $stats['total_rows'] = $stmt->fetchColumn();
            $stats['total_rows'] = 0;
            
            $sql = "SELECT COUNT(*) as count 
                    FROM plots 
                    WHERE isActive = 1 
                    AND createDate >= DATE_SUB(NOW(), INTERVAL 1 MONTH)";
            if ($blockId) {
                $sql .= " AND blockId = :blockId";
            }
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $stats['new_this_month'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
            
            echo json_encode(['success' => true, 'data' => $stats]);
            break;
            
        case 'search':
            $query = $_GET['q'] ?? '';
            $blockId = $_GET['blockId'] ?? '';
            
            if (strlen($query) < 2) {
                echo json_encode(['success' => true, 'data' => []]);
                break;
            }
            
            $sql = "SELECT p.unicId, p.plotNameHe, p.plotNameEn, p.plotCode, p.plotLocation,
                           b.blockNameHe
                    FROM plots p
                    LEFT JOIN blocks b ON p.blockId = b.unicId
                    WHERE p.isActive = 1 
                    AND (
                        p.plotNameHe LIKE :query1 OR 
                        p.plotNameEn LIKE :query2 OR 
                        p.plotCode LIKE :query3 OR
                        p.plotLocation LIKE :query4
                    )";
            
            $params = [
                'query1' => "%$query%",
                'query2' => "%$query%",
                'query3' => "%$query%",
                'query4' => "%$query%"
            ];
            
            if ($blockId) {
                $sql .= " AND p.blockId = :blockId";
                $params['blockId'] = $blockId;
            }
            
            $sql .= " LIMIT 10";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode(['success' => true, 'data' => $results]);
            break;
            
        case 'available2':
            $currentGraveId = $_GET['currentGraveId'] ?? null;
            $blockId = $_GET['blockId'] ?? null;
            
            $sql = "
                SELECT DISTINCT p.*,
                CASE WHEN EXISTS(
                    SELECT 1 FROM graves g 
                    INNER JOIN areaGraves ag ON g.areaGraveId = ag.unicId
                    INNER JOIN rows r ON ag.lineId = r.unicId
                    WHERE r.plotId = p.unicId AND g.unicId = :currentGrave
                ) THEN 1 ELSE 0 END as has_current_grave
                FROM plots p
                WHERE p.isActive = 1
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
            ";
            
            $params = [
                'currentGrave' => $currentGraveId,
                'currentGrave2' => $currentGraveId
            ];
            
            if ($blockId) {
                $sql .= " AND p.blockId = :blockId";
                $params['blockId'] = $blockId;
            }
            
            $sql .= " ORDER BY has_current_grave DESC, p.plotNameHe";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
            break;
        case 'available':
            $currentGraveId = $_GET['currentGraveId'] ?? null;
            $blockId = $_GET['blockId'] ?? null;
            
            // ✅ קבל את סוג הטופס
            $formType = $_GET['type'] ?? 'purchase';
            $allowedStatuses = ($formType === 'burial') ? '(1, 2)' : '(1)';
            
            $sql = "
                SELECT DISTINCT p.*,
                CASE WHEN EXISTS(
                    SELECT 1 FROM graves g 
                    INNER JOIN areaGraves ag ON g.areaGraveId = ag.unicId
                    INNER JOIN rows r ON ag.lineId = r.unicId
                    WHERE r.plotId = p.unicId AND g.unicId = :currentGrave
                ) THEN 1 ELSE 0 END as has_current_grave
                FROM plots p
                WHERE p.isActive = 1
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
            ";
            
            $params = [
                'currentGrave' => $currentGraveId,
                'currentGrave2' => $currentGraveId
            ];
            
            if ($blockId) {
                $sql .= " AND p.blockId = :blockId";
                $params['blockId'] = $blockId;
            }
            
            $sql .= " ORDER BY has_current_grave DESC, p.plotNameHe";
            
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