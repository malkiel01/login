<?php
/*
 * File: api/area-graves-api.php
 * Version: 2.0.0
 * Updated: 2025-11-05
 * Author: Malkiel
 * Change Summary:
 * - v2.0.0: תמיכה מלאה בניהול קברים יחד עם אחוזת קבר
 *   - יצירת עד 5 קברים בו-זמנית עם אחוזת הקבר
 *   - עדכון קברים קיימים בעריכה
 *   - ולידציה על שמות ייחודיים
 *   - מניעת מחיקת קברים לא פנויים
 * - v1.0.0: גרסה ראשונית - API בסיסי
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
        case 'count':
            $lineId = $_GET['lineId'] ?? null;
            
            if (!$lineId) {
                echo json_encode(['success' => false, 'error' => 'lineId required']);
                exit;
            }
            
            $stmt = $pdo->prepare("
                SELECT COUNT(*) 
                FROM areaGraves 
                WHERE lineId = :lineId AND isActive = 1
            ");
            $stmt->execute(['lineId' => $lineId]);
            $count = $stmt->fetchColumn();
            
            echo json_encode([
                'success' => true,
                'count' => (int)$count
            ]);
            break;
            
        // =====================================================
        // רשימת כל אחוזות הקבר
        // =====================================================
        case 'list':
            $search = $_GET['search'] ?? '';
            $plotId = $_GET['plotId'] ?? null;
            $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
            $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
            $offset = ($page - 1) * $limit;
            
            $sql = "SELECT ag.* FROM areaGraves_view ag WHERE ag.isActive = 1";
            $params = [];
            
            if ($plotId) {
                $sql .= " AND ag.plotId = :plotId";
                $params['plotId'] = $plotId;
            }
            
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
            
            $countSql = "SELECT COUNT(*) FROM areaGraves_view ag WHERE ag.isActive = 1";
            $countParams = [];
            
            if ($plotId) {
                $countSql .= " AND ag.plotId = :plotId";
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
            
            $totalAllSql = "SELECT COUNT(*) FROM areaGraves_view WHERE isActive = 1";
            $totalAll = $pdo->query($totalAllSql)->fetchColumn();
            
            $sql .= " ORDER BY ag.createDate DESC LIMIT :limit OFFSET :offset";
            
            $stmt = $pdo->prepare($sql);
            foreach ($params as $key => $value) {
                $stmt->bindValue(":$key", $value);
            }
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();
            
            $areaGraves = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($areaGraves as &$areaGrave) {
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
        // יצירת אחוזת קבר + קברים
        // =====================================================
        case 'create':
            $data = json_decode(file_get_contents('php://input'), true);
            
            // ולידציה - שדות חובה
            if (empty($data['areaGraveNameHe'])) {
                throw new Exception('שם אחוזת הקבר (עברית) הוא שדה חובה');
            }
            
            if (empty($data['lineId'])) {
                throw new Exception('בחירת שורה היא חובה');
            }
            
            if (empty($data['graveType'])) {
                throw new Exception('סוג אחוזת קבר הוא שדה חובה');
            }
            
            // קבל נתוני קברים
            $gravesData = [];
            if (isset($data['gravesData'])) {
                if (is_string($data['gravesData'])) {
                    $gravesData = json_decode($data['gravesData'], true);
                } else {
                    $gravesData = $data['gravesData'];
                }
            }
            
            // ולידציית קברים
            if (empty($gravesData) || count($gravesData) === 0) {
                throw new Exception('חובה להוסיף לפחות קבר אחד');
            }
            
            if (count($gravesData) > 5) {
                throw new Exception('ניתן להוסיף עד 5 קברים בלבד');
            }
            
            // בדוק שמות ייחודיים בקברים
            $graveNames = array_map(function($g) {
                return trim($g['graveNameHe']);
            }, $gravesData);
            
            if (count($graveNames) !== count(array_unique($graveNames))) {
                throw new Exception('שמות קברים חייבים להיות ייחודיים באחוזה');
            }
            
            // בדוק שכל הקברים קיבלו שם
            foreach ($gravesData as $idx => $grave) {
                if (empty($grave['graveNameHe'])) {
                    throw new Exception("שם קבר מספר " . ($idx + 1) . " הוא חובה");
                }
            }
            
            // התחל טרנזקציה
            $pdo->beginTransaction();
            
            try {
                // צור אחוזת קבר
                $data['unicId'] = uniqid('areaGrave_', true);
                $data['createDate'] = date('Y-m-d H:i:s');
                $data['updateDate'] = date('Y-m-d H:i:s');
                $data['isActive'] = 1;
                $data['gravesList'] = ''; // ישאר ריק כפי שהתבקש
                
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
                
                $areaGraveUnicId = $data['unicId'];
                
                // צור קברים
                $createdGraves = [];
                foreach ($gravesData as $index => $grave) {
                    $graveUnicId = uniqid('grave_', true);
                    
                    $graveStmt = $pdo->prepare("
                        INSERT INTO graves (
                            unicId, areaGraveId, graveNameHe, plotType, 
                            graveStatus, graveLocation, constructionCost, 
                            isSmallGrave, comments, documentsList,
                            createDate, updateDate, isActive
                        ) VALUES (
                            :unicId, :areaGraveId, :graveNameHe, :plotType,
                            :graveStatus, :graveLocation, :constructionCost,
                            :isSmallGrave, :comments, :documentsList,
                            :createDate, :updateDate, :isActive
                        )
                    ");
                    
                    $graveStmt->execute([
                        'unicId' => $graveUnicId,
                        'areaGraveId' => $areaGraveUnicId,
                        'graveNameHe' => trim($grave['graveNameHe']),
                        'plotType' => $grave['plotType'] ?? 1,
                        'graveStatus' => 1, // תמיד פנוי ביצירה
                        'graveLocation' => 0, // לא בשימוש כרגע
                        'constructionCost' => $grave['constructionCost'] ?? 0,
                        'isSmallGrave' => isset($grave['isSmallGrave']) && $grave['isSmallGrave'] ? 1 : 0,
                        'comments' => '',
                        'documentsList' => '',
                        'createDate' => date('Y-m-d H:i:s'),
                        'updateDate' => date('Y-m-d H:i:s'),
                        'isActive' => 1
                    ]);
                    
                    $createdGraves[] = [
                        'unicId' => $graveUnicId,
                        'graveNameHe' => $grave['graveNameHe']
                    ];
                }
                
                $pdo->commit();
                
                echo json_encode([
                    'success' => true,
                    'message' => 'אחוזת הקבר נוספה בהצלחה עם ' . count($createdGraves) . ' קברים',
                    'id' => $pdo->lastInsertId(),
                    'unicId' => $areaGraveUnicId,
                    'graves' => $createdGraves
                ]);
                
            } catch (Exception $e) {
                $pdo->rollBack();
                throw $e;
            }
            break;
    
        // =====================================================
        // עדכון אחוזת קבר + קברים
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
            
            // קבל נתוני קברים
            $gravesData = [];
            if (isset($data['gravesData'])) {
                if (is_string($data['gravesData'])) {
                    $gravesData = json_decode($data['gravesData'], true);
                } else {
                    $gravesData = $data['gravesData'];
                }
            }
            
            // ולידציית קברים
            if (empty($gravesData) || count($gravesData) === 0) {
                throw new Exception('חובה להיות לפחות קבר אחד');
            }
            
            if (count($gravesData) > 5) {
                throw new Exception('ניתן להוסיף עד 5 קברים בלבד');
            }
            
            // בדוק שמות ייחודיים
            $graveNames = array_map(function($g) {
                return trim($g['graveNameHe']);
            }, $gravesData);
            
            if (count($graveNames) !== count(array_unique($graveNames))) {
                throw new Exception('שמות קברים חייבים להיות ייחודיים באחוזה');
            }
            
            // בדוק שכל הקברים קיבלו שם
            foreach ($gravesData as $idx => $grave) {
                if (empty($grave['graveNameHe'])) {
                    throw new Exception("שם קבר מספר " . ($idx + 1) . " הוא חובה");
                }
            }
            
            // התחל טרנזקציה
            $pdo->beginTransaction();
            
            try {
                // עדכן אחוזת קבר
                $data['updateDate'] = date('Y-m-d H:i:s');
                
                $fields = [
                    'areaGraveNameHe', 'coordinates', 'graveType', 
                    'lineId', 'comments', 'documentsList', 'updateDate'
                ];
                
                $updateFields = [];
                $params = ['id' => $id];
                
                foreach ($fields as $field) {
                    if (isset($data[$field])) {
                        $updateFields[] = "$field = :$field";
                        $params[$field] = $data[$field];
                    }
                }
                
                if (!empty($updateFields)) {
                    $sql = "UPDATE areaGraves SET " . implode(', ', $updateFields) . " WHERE unicId = :id";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute($params);
                }
                
                // טען קברים קיימים
                $stmt = $pdo->prepare("
                    SELECT unicId, graveNameHe, graveStatus 
                    FROM graves 
                    WHERE areaGraveId = :id AND isActive = 1
                ");
                $stmt->execute(['id' => $id]);
                $existingGraves = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                $existingIds = array_column($existingGraves, 'unicId');
                $newGravesIds = array_filter(array_column($gravesData, 'id'));
                
                // מחק קברים שהוסרו (רק פנויים)
                $gravesToDelete = array_diff($existingIds, $newGravesIds);
                foreach ($gravesToDelete as $graveIdToDelete) {
                    // בדוק סטטוס לפני מחיקה
                    $statusStmt = $pdo->prepare("
                        SELECT graveStatus FROM graves WHERE unicId = :id
                    ");
                    $statusStmt->execute(['id' => $graveIdToDelete]);
                    $status = $statusStmt->fetchColumn();
                    
                    if ($status != 1) {
                        throw new Exception('לא ניתן למחוק קבר שאינו פנוי');
                    }
                    
                    // מחיקה לוגית
                    $deleteStmt = $pdo->prepare("
                        UPDATE graves 
                        SET isActive = 0, inactiveDate = :date 
                        WHERE unicId = :id
                    ");
                    $deleteStmt->execute([
                        'id' => $graveIdToDelete,
                        'date' => date('Y-m-d H:i:s')
                    ]);
                }
                
                // עדכן או צור קברים
                foreach ($gravesData as $grave) {
                    if (!empty($grave['id']) && in_array($grave['id'], $existingIds)) {
                        // עדכן קבר קיים
                        $updateGraveStmt = $pdo->prepare("
                            UPDATE graves SET
                                graveNameHe = :graveNameHe,
                                plotType = :plotType,
                                constructionCost = :constructionCost,
                                isSmallGrave = :isSmallGrave,
                                updateDate = :updateDate
                            WHERE unicId = :id
                        ");
                        
                        $updateGraveStmt->execute([
                            'id' => $grave['id'],
                            'graveNameHe' => trim($grave['graveNameHe']),
                            'plotType' => $grave['plotType'] ?? 1,
                            'constructionCost' => $grave['constructionCost'] ?? 0,
                            'isSmallGrave' => isset($grave['isSmallGrave']) && $grave['isSmallGrave'] ? 1 : 0,
                            'updateDate' => date('Y-m-d H:i:s')
                        ]);
                        
                    } else {
                        // צור קבר חדש
                        $graveUnicId = uniqid('grave_', true);
                        
                        $insertGraveStmt = $pdo->prepare("
                            INSERT INTO graves (
                                unicId, areaGraveId, graveNameHe, plotType, 
                                graveStatus, graveLocation, constructionCost, 
                                isSmallGrave, comments, documentsList,
                                createDate, updateDate, isActive
                            ) VALUES (
                                :unicId, :areaGraveId, :graveNameHe, :plotType,
                                :graveStatus, :graveLocation, :constructionCost,
                                :isSmallGrave, :comments, :documentsList,
                                :createDate, :updateDate, :isActive
                            )
                        ");
                        
                        $insertGraveStmt->execute([
                            'unicId' => $graveUnicId,
                            'areaGraveId' => $id,
                            'graveNameHe' => trim($grave['graveNameHe']),
                            'plotType' => $grave['plotType'] ?? 1,
                            'graveStatus' => 1, // תמיד פנוי ביצירה
                            'graveLocation' => 0,
                            'constructionCost' => $grave['constructionCost'] ?? 0,
                            'isSmallGrave' => isset($grave['isSmallGrave']) && $grave['isSmallGrave'] ? 1 : 0,
                            'comments' => '',
                            'documentsList' => '',
                            'createDate' => date('Y-m-d H:i:s'),
                            'updateDate' => date('Y-m-d H:i:s'),
                            'isActive' => 1
                        ]);
                    }
                }
                
                $pdo->commit();
                
                echo json_encode([
                    'success' => true,
                    'message' => 'אחוזת הקבר עודכנה בהצלחה'
                ]);
                
            } catch (Exception $e) {
                $pdo->rollBack();
                throw $e;
            }
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
            
            if ($plotId) {
                $stmt = $pdo->prepare("
                    SELECT COUNT(*) 
                    FROM areaGraves ag
                    INNER JOIN rows r ON ag.lineId = r.unicId
                    WHERE ag.isActive = 1 AND r.plotId = :plotId
                ");
                $stmt->execute(['plotId' => $plotId]);
                $stats['total_area_graves'] = $stmt->fetchColumn();
                
                $stmt = $pdo->prepare("
                    SELECT COUNT(*) 
                    FROM graves g
                    INNER JOIN areaGraves ag ON g.areaGraveId = ag.unicId
                    INNER JOIN rows r ON ag.lineId = r.unicId
                    WHERE g.isActive = 1 AND r.plotId = :plotId
                ");
                $stmt->execute(['plotId' => $plotId]);
                $stats['total_graves'] = $stmt->fetchColumn();
                
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