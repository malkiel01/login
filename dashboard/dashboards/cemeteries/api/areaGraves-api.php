<?php
/*
 * File: api/areaGraves-api.php
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


// =====================================
// 1️⃣ קבלת נתוני POST/GET
// =====================================
$postData = json_decode(file_get_contents('php://input'), true);

// אם יש POST data - זה חיפוש מ-UniversalSearch
if ($postData && isset($postData['action'])) {
    $action = $postData['action'];
    $query = $postData['query'] ?? '';
    $filters = $postData['filters'] ?? [];
    $page = $postData['page'] ?? 1;
    $limit = $postData['limit'] ?? 200;
    $orderBy = $postData['orderBy'] ?? 'createDate';
    $sortDirection = strtoupper($postData['sortDirection'] ?? 'DESC');
} else {
    // אחרת - GET רגיל
    $action = $_GET['action'] ?? '';
    $query = $_GET['search'] ?? '';
    $filters = [];
    $page = $_GET['page'] ?? 1;
    $limit = $_GET['limit'] ?? 200;
    $orderBy = $_GET['orderBy'] ?? 'createDate';
    $sortDirection = strtoupper($_GET['sortDirection'] ?? 'DESC');
}

// ⭐ $id תמיד מגיע רק מ-GET (גם בעריכה וגם במחיקה)
$id = $_GET['id'] ?? null;

// =====================================
// 2️⃣ חיבור למסד נתונים
// =====================================
require_once $_SERVER['DOCUMENT_ROOT'] . '/dashboard/dashboards/cemeteries/config.php';

try {
    $pdo = getDBConnection();
} catch(PDOException $e) {
    die(json_encode(['success' => false, 'error' => 'Connection failed: ' . $e->getMessage()]));
}

$action = $_GET['action'] ?? '';
$id = $_GET['id'] ?? null;


// -------------------------------------------------------------

// ⭐ פרמטרי מיון אופציונליים (חדש!)
$orderBy = $_GET['orderBy'] ?? 'createDate'; // ✅ ברירת מחדל כמו בקוד המקורי
$sortDirection = strtoupper($_GET['sortDirection'] ?? 'DESC'); // ✅ DESC כמו בקוד המקורי

// ולידציה של כיוון המיון
if (!in_array($sortDirection, ['ASC', 'DESC'])) {
    $sortDirection = 'DESC';
}

// ⭐ שדות מותרים למיון - מותאמים ל-VIEW!
$allowedOrderFields = [
    'areaGraveNameHe',      // ✅ שם נכון מה-view
    'unicId',
    'createDate',           // ✅ שם נכון מה-view (לא createdDate!)
    'coordinates',
    'graveType',
    'lineNameHe',
    'plotNameHe',
    'blockNameHe',
    'cemeteryNameHe'
];

// ולידציה של שדה המיון
if (!in_array($orderBy, $allowedOrderFields)) {
    $orderBy = 'createDate'; // ✅ fallback נכון
}

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
            $plotId = $postData['plotId'] ?? $_GET['plotId'] ?? null;
            // תמיכה גם ב-rowId וגם ב-lineId (לתאימות)
            $rowId = $postData['rowId'] ?? $_GET['rowId'] ?? $postData['lineId'] ?? $_GET['lineId'] ?? null;

            // ⭐ הוסף את זה!
            error_log("DEBUG areaGraves-api.php - plotId received: " . ($plotId ?? 'NULL'));
            error_log("DEBUG areaGraves-api.php - postData: " . json_encode($postData));
            error_log("DEBUG areaGraves-api.php - _GET: " . json_encode($_GET));

            $offset = ($page - 1) * $limit;
            
            $sql = "SELECT ag.* FROM areaGraves_view ag WHERE ag.isActive = 1";
            $params = [];
            
            // סינון לפי plotId
            if ($plotId) {
                $sql .= " AND ag.plotId = :plotId";
                $params['plotId'] = $plotId;
            }
            
            // סינון לפי rowId
            if ($rowId) {
                $sql .= " AND ag.lineId = :rowId";
                $params['rowId'] = $rowId;
            }
            
            // ⭐ חיפוש כללי (query)
            if (!empty($query)) {
                $sql .= " AND (
                    ag.areaGraveNameHe LIKE :query1 OR 
                    ag.coordinates LIKE :query2 OR 
                    ag.lineNameHe LIKE :query3 OR
                    ag.plotNameHe LIKE :query4 OR
                    ag.blockNameHe LIKE :query5 OR
                    ag.cemeteryNameHe LIKE :query6
                )";
                $searchTerm = "%{$query}%";
                $params['query1'] = $searchTerm;
                $params['query2'] = $searchTerm;
                $params['query3'] = $searchTerm;
                $params['query4'] = $searchTerm;
                $params['query5'] = $searchTerm;
                $params['query6'] = $searchTerm;
            }
            
            // ⭐ פילטרים מתקדמים
            foreach ($filters as $index => $filter) {
                $field = $filter['field'];
                $value = $filter['value'];
                $matchType = $filter['matchType'] ?? 'exact';
                
                switch ($matchType) {
                    case 'exact':
                        $sql .= " AND ag.{$field} = :filter{$index}";
                        $params["filter{$index}"] = $value;
                        break;
                        
                    case 'fuzzy':
                        $sql .= " AND ag.{$field} LIKE :filter{$index}";
                        $params["filter{$index}"] = "%{$value}%";
                        break;
                        
                    case 'startsWith':
                        $sql .= " AND ag.{$field} LIKE :filter{$index}";
                        $params["filter{$index}"] = "{$value}%";
                        break;
                        
                    case 'before':
                        $sql .= " AND ag.{$field} < :filter{$index}";
                        $params["filter{$index}"] = $value;
                        break;
                        
                    case 'after':
                        $sql .= " AND ag.{$field} > :filter{$index}";
                        $params["filter{$index}"] = $value;
                        break;
                        
                    case 'between':
                        if (isset($filter['valueEnd'])) {
                            $sql .= " AND ag.{$field} BETWEEN :filter{$index}_start AND :filter{$index}_end";
                            $params["filter{$index}_start"] = $value;
                            $params["filter{$index}_end"] = $filter['valueEnd'];
                        }
                        break;
                }
            }
            
            // ספירה
            $countSql = str_replace('SELECT ag.*', 'SELECT COUNT(*)', $sql);
            $countStmt = $pdo->prepare($countSql);
            $countStmt->execute($params);
            $total = $countStmt->fetchColumn();
            
            // מיון והגבלה
            $sql .= " ORDER BY ag.{$orderBy} {$sortDirection} LIMIT :limit OFFSET :offset";
            
            $stmt = $pdo->prepare($sql);
            foreach ($params as $key => $value) {
                $stmt->bindValue(":{$key}", $value);
            }
            $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);
            $stmt->execute();
            
            $areaGraves = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // הוסף ספירת קברים
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
                    'page' => (int)$page,
                    'limit' => (int)$limit,
                    'total' => (int)$total,
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
            
        case 'available':
            $currentGraveId = $_GET['currentGraveId'] ?? null;
            $rowId = $_GET['rowId'] ?? null;
            
            // ✅ קבל את סוג הטופס
            $formType = $_GET['type'] ?? 'purchase';
            $allowedStatuses = ($formType === 'burial') ? '(1, 2)' : '(1)';
            
            $sql = "
                SELECT DISTINCT ag.*,
                CASE WHEN EXISTS(
                    SELECT 1 FROM graves g 
                    WHERE g.areaGraveId = ag.unicId 
                    AND g.unicId = :currentGrave
                ) THEN 1 ELSE 0 END as has_current_grave
                FROM areaGraves ag
                WHERE ag.isActive = 1
                AND EXISTS(
                    SELECT 1 FROM graves g 
                    WHERE g.areaGraveId = ag.unicId 
                    AND (g.graveStatus IN $allowedStatuses OR g.unicId = :currentGrave2)
                    AND g.isActive = 1
                )
            ";
            
            $params = [
                'currentGrave' => $currentGraveId,
                'currentGrave2' => $currentGraveId
            ];
            
            if ($rowId) {
                $sql .= " AND ag.lineId = :rowId";
                $params['rowId'] = $rowId;
            }
            
            $sql .= " ORDER BY has_current_grave DESC, ag.areaGraveNameHe";
            
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