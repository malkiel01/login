<?php
// cemetery_dashboard/api/cemetery-hierarchy.php
// API לניהול היררכיית בתי עלמין

require_once '../config.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type');

// קבלת פרמטרים
$action = $_GET['action'] ?? '';
$type = $_GET['type'] ?? '';
$id = $_GET['id'] ?? null;

// המרת סוג לשם טבלה
function getTableName($type) {
    $tables = [
        'cemetery' => 'cemeteries',
        'block' => 'blocks',
        'plot' => 'plots',
        'row' => 'rows',
        'area_grave' => 'area_graves',
        'grave' => 'graves'
    ];
    return $tables[$type] ?? null;
}

// המרת סוג לעמודת הורה
function getParentColumn($type) {
    $parents = [
        'block' => 'cemetery_id',
        'plot' => 'block_id',
        'row' => 'plot_id',
        'area_grave' => 'row_id',
        'grave' => 'area_grave_id'
    ];
    return $parents[$type] ?? null;
}

try {
    $pdo = getDBConnection();
    
    switch($action) {
        case 'listOld':
            $table = getTableName($type);
            if (!$table) {
                throw new Exception('סוג לא תקין');
            }
            
            $sql = "SELECT * FROM $table WHERE is_active = 1";
            $params = [];
            
            // סינון לפי הורה
            if (isset($_GET['parent_id'])) {
                $parentColumn = getParentColumn($type);
                if ($parentColumn) {
                    $sql .= " AND $parentColumn = :parent_id";
                    $params['parent_id'] = $_GET['parent_id'];
                }
            }
            
            // חיפוש
            if (isset($_GET['search']) && !empty($_GET['search'])) {
                $sql .= " AND (name LIKE :search OR code LIKE :search)";
                $params['search'] = '%' . $_GET['search'] . '%';
            }
            
            // מיון
            $orderBy = $_GET['sort'] ?? 'name';
            $orderDir = $_GET['order'] ?? 'ASC';
            $sql .= " ORDER BY $orderBy $orderDir";
            
            // עימוד
            $page = intval($_GET['page'] ?? 1);
            $limit = intval($_GET['limit'] ?? 50);
            $offset = ($page - 1) * $limit;
            
            // ספירת סך הכל
            $countSql = str_replace('SELECT *', 'SELECT COUNT(*)', $sql);
            $stmt = $pdo->prepare($countSql);
            $stmt->execute($params);
            $total = $stmt->fetchColumn();
            
            // הוספת LIMIT
            $sql .= " LIMIT $limit OFFSET $offset";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'success' => true,
                'data' => $data,
                'pagination' => [
                    'page' => $page,
                    'limit' => $limit,
                    'total' => $total,
                    'pages' => ceil($total / $limit)
                ]
            ]);
            break;
            
        case 'list':
            $table = getTableName($type);
            if (!$table) {
                throw new Exception('סוג לא תקין');
            }
            
            $sql = "SELECT * FROM $table WHERE is_active = 1";
            $params = [];
            
            // סינון לפי הורה
            if (isset($_GET['parent_id'])) {
                $parentColumn = getParentColumn($type);
                if ($parentColumn) {
                    $sql .= " AND $parentColumn = :parent_id";
                    $params['parent_id'] = $_GET['parent_id'];
                }
            }
            
            // חיפוש
            if (isset($_GET['search']) && !empty($_GET['search'])) {
                if ($type === 'grave') {
                    $sql .= " AND grave_number LIKE :search";
                } else {
                    $sql .= " AND (name LIKE :search OR code LIKE :search)";
                }
                $params['search'] = '%' . $_GET['search'] . '%';
            }
            
            // מיון - תיקון כאן!
            if ($type === 'grave') {
                $orderBy = $_GET['sort'] ?? 'grave_number';
            } else {
                $orderBy = $_GET['sort'] ?? 'name';
            }
            $orderDir = $_GET['order'] ?? 'ASC';
            
            $sql .= " ORDER BY $orderBy $orderDir";
            
            // עימוד
            $page = intval($_GET['page'] ?? 1);
            $limit = intval($_GET['limit'] ?? 50);
            $offset = ($page - 1) * $limit;
            
            // ספירת סך הכל
            $countSql = str_replace('SELECT *', 'SELECT COUNT(*)', $sql);
            $stmt = $pdo->prepare($countSql);
            $stmt->execute($params);
            $total = $stmt->fetchColumn();
            
            // הוספת LIMIT
            $sql .= " LIMIT $limit OFFSET $offset";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'success' => true,
                'data' => $data,
                'pagination' => [
                    'page' => $page,
                    'limit' => $limit,
                    'total' => $total,
                    'pages' => ceil($total / $limit)
                ]
            ]);
            break;
        case 'get':
            $table = getTableName($type);
            if (!$table || !$id) {
                throw new Exception('פרמטרים חסרים');
            }
            
            $stmt = $pdo->prepare("SELECT * FROM $table WHERE id = :id");
            $stmt->execute(['id' => $id]);
            $item = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$item) {
                throw new Exception('הפריט לא נמצא');
            }
            
            // הוספת מידע על הילדים
            $children = [];
            $childTypes = [
                'cemetery' => 'block',
                'block' => 'plot',
                'plot' => 'row',
                'row' => 'area_grave',
                'area_grave' => 'grave'
            ];
            
            if (isset($childTypes[$type])) {
                $childType = $childTypes[$type];
                $childTable = getTableName($childType);
                $parentColumn = getParentColumn($childType);
                
                $stmt = $pdo->prepare(
                    "SELECT COUNT(*) as total,
                     SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active
                     FROM $childTable WHERE $parentColumn = :id"
                );
                $stmt->execute(['id' => $id]);
                $children = $stmt->fetch(PDO::FETCH_ASSOC);
            }
            
            echo json_encode([
                'success' => true,
                'data' => $item,
                'children' => $children
            ]);
            break;
            
        case 'create':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                throw new Exception('שיטת בקשה לא תקינה');
            }
            
            $table = getTableName($type);
            if (!$table) {
                throw new Exception('סוג לא תקין');
            }
            
            $data = json_decode(file_get_contents('php://input'), true);
            if (!$data) {
                throw new Exception('נתונים לא תקינים');
            }
            
            // בניית שאילתה
            $columns = [];
            $values = [];
            $params = [];
            
            // הוספת עמודת הורה אם נדרש
            $parentColumn = getParentColumn($type);
            if ($parentColumn && isset($_GET['parent_id'])) {
                $data[$parentColumn] = $_GET['parent_id'];
            }
            
            foreach ($data as $key => $value) {
                if (!in_array($key, ['id', 'created_at', 'updated_at'])) {
                    $columns[] = $key;
                    $values[] = ":$key";
                    $params[$key] = $value;
                }
            }
            
            $sql = "INSERT INTO $table (" . implode(', ', $columns) . ") 
                    VALUES (" . implode(', ', $values) . ")";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            
            $newId = $pdo->lastInsertId();
            
            // רישום בלוג
            logActivity('create', $type, $newId);
            
            echo json_encode([
                'success' => true,
                'message' => 'הפריט נוצר בהצלחה',
                'id' => $newId
            ]);
            break;
            
        case 'update':
            if ($_SERVER['REQUEST_METHOD'] !== 'PUT') {
                throw new Exception('שיטת בקשה לא תקינה');
            }
            
            $table = getTableName($type);
            if (!$table || !$id) {
                throw new Exception('פרמטרים חסרים');
            }
            
            $data = json_decode(file_get_contents('php://input'), true);
            if (!$data) {
                throw new Exception('נתונים לא תקינים');
            }
            
            // בניית שאילתה
            $updates = [];
            $params = ['id' => $id];
            
            foreach ($data as $key => $value) {
                if (!in_array($key, ['id', 'created_at', 'updated_at'])) {
                    $updates[] = "$key = :$key";
                    $params[$key] = $value;
                }
            }
            
            if (empty($updates)) {
                throw new Exception('אין נתונים לעדכון');
            }
            
            $sql = "UPDATE $table SET " . implode(', ', $updates) . " WHERE id = :id";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            
            // רישום בלוג
            logActivity('update', $type, $id);
            
            echo json_encode([
                'success' => true,
                'message' => 'הפריט עודכן בהצלחה'
            ]);
            break;
            
        case 'delete':
            if ($_SERVER['REQUEST_METHOD'] !== 'DELETE') {
                throw new Exception('שיטת בקשה לא תקינה');
            }
            
            $table = getTableName($type);
            if (!$table || !$id) {
                throw new Exception('פרמטרים חסרים');
            }
            
            // בדיקה אם יש ילדים
            $childTypes = [
                'cemetery' => ['table' => 'blocks', 'column' => 'cemetery_id'],
                'block' => ['table' => 'plots', 'column' => 'block_id'],
                'plot' => ['table' => 'rows', 'column' => 'plot_id'],
                'row' => ['table' => 'area_graves', 'column' => 'row_id'],
                'area_grave' => ['table' => 'graves', 'column' => 'area_grave_id']
            ];
            
            if (isset($childTypes[$type])) {
                $childInfo = $childTypes[$type];
                $stmt = $pdo->prepare(
                    "SELECT COUNT(*) FROM {$childInfo['table']} 
                     WHERE {$childInfo['column']} = :id AND is_active = 1"
                );
                $stmt->execute(['id' => $id]);
                
                if ($stmt->fetchColumn() > 0) {
                    throw new Exception('לא ניתן למחוק פריט שיש לו פריטים משויכים');
                }
            }
            
            // מחיקה רכה
            $stmt = $pdo->prepare("UPDATE $table SET is_active = 0 WHERE id = :id");
            $stmt->execute(['id' => $id]);
            
            // רישום בלוג
            logActivity('delete', $type, $id);
            
            echo json_encode([
                'success' => true,
                'message' => 'הפריט נמחק בהצלחה'
            ]);
            break;
            
        case 'hierarchy':
            $cemeteryId = $_GET['cemetery_id'] ?? null;
            
            $sql = "
                SELECT 
                    c.id as cemetery_id,
                    c.name as cemetery_name,
                    b.id as block_id,
                    b.name as block_name,
                    p.id as plot_id,
                    p.name as plot_name,
                    r.id as row_id,
                    r.name as row_name,
                    ag.id as area_grave_id,
                    ag.name as area_grave_name,
                    ag.grave_type,
                    g.id as grave_id,
                    g.grave_number,
                    g.grave_status,
                    g.plot_type
                FROM cemeteries c
                LEFT JOIN blocks b ON b.cemetery_id = c.id AND b.is_active = 1
                LEFT JOIN plots p ON p.block_id = b.id AND p.is_active = 1
                LEFT JOIN rows r ON r.plot_id = p.id AND r.is_active = 1
                LEFT JOIN area_graves ag ON ag.row_id = r.id AND ag.is_active = 1
                LEFT JOIN graves g ON g.area_grave_id = ag.id AND g.is_active = 1
                WHERE c.is_active = 1
            ";
            
            $params = [];
            if ($cemeteryId) {
                $sql .= " AND c.id = :cemetery_id";
                $params['cemetery_id'] = $cemeteryId;
            }
            
            $sql .= " ORDER BY c.name, b.name, p.name, r.serial_number, r.name, ag.name, 
                      CAST(g.grave_number AS UNSIGNED), g.grave_number";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // ארגון הנתונים בצורה היררכית
            $hierarchy = [];
            $stats = [
                'total_graves' => 0,
                'available' => 0,
                'reserved' => 0,
                'occupied' => 0
            ];
            
            foreach ($result as $row) {
                // בניית ההיררכיה
                $cemId = $row['cemetery_id'];
                if (!isset($hierarchy[$cemId])) {
                    $hierarchy[$cemId] = [
                        'id' => $cemId,
                        'name' => $row['cemetery_name'],
                        'blocks' => []
                    ];
                }
                
                if ($row['block_id']) {
                    $blockId = $row['block_id'];
                    if (!isset($hierarchy[$cemId]['blocks'][$blockId])) {
                        $hierarchy[$cemId]['blocks'][$blockId] = [
                            'id' => $blockId,
                            'name' => $row['block_name'],
                            'plots' => []
                        ];
                    }
                    
                    // המשך בניית ההיררכיה לפי הצורך...
                }
                
                // עדכון סטטיסטיקות
                if ($row['grave_id']) {
                    $stats['total_graves']++;
                    switch($row['grave_status']) {
                        case 1: $stats['available']++; break;
                        case 2: $stats['reserved']++; break;
                        case 3: $stats['occupied']++; break;
                    }
                }
            }
            
            echo json_encode([
                'success' => true,
                'hierarchy' => array_values($hierarchy),
                'stats' => $stats
            ]);
            break;
            
        case 'stats':
            // סטטיסטיקות כלליות
            $stats = [];
            
            // ספירת פריטים בכל רמה
            $tables = [
                'cemeteries' => 'בתי עלמין',
                'blocks' => 'גושים',
                'plots' => 'חלקות',
                'rows' => 'שורות',
                'area_graves' => 'אחוזות קבר',
                'graves' => 'קברים'
            ];
            
            foreach ($tables as $table => $label) {
                $stmt = $pdo->query("SELECT COUNT(*) FROM $table WHERE is_active = 1");
                $stats['counts'][$table] = [
                    'label' => $label,
                    'count' => $stmt->fetchColumn()
                ];
            }
            
            // סטטוס קברים
            $stmt = $pdo->query("
                SELECT grave_status, COUNT(*) as count 
                FROM graves 
                WHERE is_active = 1 
                GROUP BY grave_status
            ");
            $graveStatuses = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $stats['grave_status'] = [];
            foreach ($graveStatuses as $status) {
                $stats['grave_status'][] = [
                    'status' => $status['grave_status'],
                    'name' => GRAVE_STATUS[$status['grave_status']]['name'] ?? 'לא ידוע',
                    'count' => $status['count'],
                    'color' => GRAVE_STATUS[$status['grave_status']]['color'] ?? '#6b7280'
                ];
            }
            
            // סוגי חלקות
            $stmt = $pdo->query("
                SELECT plot_type, COUNT(*) as count 
                FROM graves 
                WHERE is_active = 1 
                GROUP BY plot_type
            ");
            $plotTypes = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $stats['plot_types'] = [];
            foreach ($plotTypes as $plotType) {
                $stats['plot_types'][] = [
                    'type' => $plotType['plot_type'],
                    'name' => PLOT_TYPES[$plotType['plot_type']]['name'] ?? 'לא ידוע',
                    'count' => $plotType['count'],
                    'icon' => PLOT_TYPES[$plotType['plot_type']]['icon'] ?? ''
                ];
            }
            
            echo json_encode([
                'success' => true,
                'stats' => $stats
            ]);
            break;
            
        default:
            throw new Exception('פעולה לא תקינה');
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}