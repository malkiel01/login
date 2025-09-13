<?php
// cemetery_dashboard/api/cemetery-hierarchy.php
// API לניהול היררכיית בתי עלמין - גרסה מעודכנת למבנה החדש

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
        'area_grave' => 'areaGraves',
        'grave' => 'graves',
        'purchase' => 'purchases',
        'customer' => 'customers',
    ];
    return $tables[$type] ?? null;
}

// המרת סוג לעמודת הורה - עכשיו עם unicId
function getParentColumn($type) {
    $parents = [
        'block' => 'cemeteryId',      // מצביע ל-unicId של cemetery
        'plot' => 'blockId',           // מצביע ל-unicId של block
        'row' => 'plotId',             // מצביע ל-unicId של plot
        'area_grave' => 'lineId',      // מצביע ל-unicId של row (שימו לב: lineId)
        'grave' => 'areaGraveId'       // מצביע ל-unicId של areaGrave
    ];
    return $parents[$type] ?? null;
}

// המרת סוג לעמודת שם
function getNameColumn($type) {
    $names = [
        'cemetery' => 'cemeteryNameHe',
        'block' => 'blockNameHe',
        'plot' => 'plotNameHe',
        'row' => 'lineNameHe',
        'area_grave' => 'areaGraveNameHe',
        'grave' => 'graveNameHe',
        'customer' => "CONCAT(firstName, ' ', lastName) as name"
    ];
    return $names[$type] ?? 'name';
}

// המרת סוג לעמודת קוד
function getCodeColumn($type) {
    $codes = [
        'cemetery' => 'cemeteryCode',
        'block' => 'blockCode',
        'plot' => 'plotCode',
        'row' => 'serialNumber',
        'grave' => 'graveNameHe'
    ];
    return $codes[$type] ?? null;
}

try {
    $pdo = getDBConnection();
    
    switch($action) {
        case 'list':
            $table = getTableName($type);
            if (!$table) {
                throw new Exception('סוג לא תקין');
            }
            
            $nameColumn = getNameColumn($type);
            $codeColumn = getCodeColumn($type);
            
            // בניית השאילתה הבסיסית
            if ($type === 'customer') {
                $sql = "SELECT *, $nameColumn FROM $table WHERE isActive = 1";
            } else {
                $sql = "SELECT * FROM $table WHERE isActive = 1";
            }
            $params = [];
            
            // טיפול מיוחד באחוזות קבר
            if ($type === 'area_grave') {
                if (isset($_GET['plot_id'])) {
                    // קודם מצא את כל השורות של החלקה
                    $rows_query = "SELECT unicId FROM rows WHERE plotId = :plot_id AND isActive = 1";
                    $stmt = $pdo->prepare($rows_query);
                    $stmt->execute(['plot_id' => $_GET['plot_id']]);
                    $row_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
                    
                    if (!empty($row_ids)) {
                        $placeholders = array_map(function($i) { return ":row_id_$i"; }, array_keys($row_ids));
                        $sql = "SELECT * FROM areaGraves 
                                WHERE lineId IN (" . implode(',', $placeholders) . ")
                                AND isActive = 1";
                        foreach ($row_ids as $i => $row_id) {
                            $params["row_id_$i"] = $row_id;
                        }
                    } else {
                        echo json_encode([
                            'success' => true,
                            'data' => [],
                            'pagination' => [
                                'page' => 1,
                                'limit' => 50,
                                'total' => 0,
                                'pages' => 0
                            ]
                        ]);
                        exit;
                    }
                } elseif (isset($_GET['parent_id'])) {
                    // זה לשורה ספציפית
                    $sql = "SELECT * FROM areaGraves WHERE lineId = :parent_id AND isActive = 1";
                    $params['parent_id'] = $_GET['parent_id'];
                } else {
                    // כל אחוזות הקבר
                    $sql = "SELECT * FROM areaGraves WHERE isActive = 1";
                }
            } else {
                // לשאר הסוגים
                if (isset($_GET['parent_id'])) {
                    $parentColumn = getParentColumn($type);
                    if ($parentColumn) {
                        $sql .= " AND $parentColumn = :parent_id";
                        $params['parent_id'] = $_GET['parent_id'];
                    }
                }
            }
            
            // חיפוש
            if (isset($_GET['search']) && !empty($_GET['search'])) {
                if ($type === 'grave') {
                    $sql .= " AND graveNameHe LIKE :search";
                } elseif ($type === 'customer') {
                    $sql .= " AND (firstName LIKE :search OR lastName LIKE :search OR numId LIKE :search)";
                } else {
                    $searchColumns = [];
                    if ($nameColumn && $nameColumn !== "CONCAT(firstName, ' ', lastName) as name") {
                        $searchColumns[] = "$nameColumn LIKE :search";
                    }
                    if ($codeColumn) {
                        $searchColumns[] = "$codeColumn LIKE :search";
                    }
                    if (!empty($searchColumns)) {
                        $sql .= " AND (" . implode(' OR ', $searchColumns) . ")";
                    }
                }
                $params['search'] = '%' . $_GET['search'] . '%';
            }
            
            // מיון
            $orderBy = $_GET['sort'] ?? null;
            $orderDir = $_GET['order'] ?? 'ASC';
            
            // בחירת עמודת מיון ברירת מחדל
            if (!$orderBy) {
                if ($type === 'grave') {
                    $orderBy = 'graveNameHe';
                } elseif ($type === 'customer') {
                    $orderBy = 'lastName';
                } elseif ($type === 'row') {
                    $orderBy = 'serialNumber';
                } else {
                    $orderBy = $nameColumn;
                }
            }
            
            // וידוא שהעמודה קיימת (אבטחה)
            $allowedColumns = [
                'cemetery' => ['cemeteryNameHe', 'cemeteryCode', 'createDate', 'unicId'],
                'block' => ['blockNameHe', 'blockCode', 'createDate', 'unicId'],
                'plot' => ['plotNameHe', 'plotCode', 'createDate', 'unicId'],
                'row' => ['lineNameHe', 'serialNumber', 'createDate', 'unicId'],
                'area_grave' => ['areaGraveNameHe', 'graveType', 'createDate', 'unicId'],
                'grave' => ['graveNameHe', 'graveStatus', 'plotType', 'createDate', 'unicId'],
                'customer' => ['firstName', 'lastName', 'numId', 'createDate', 'unicId']
            ];
            
            if (isset($allowedColumns[$type]) && !in_array($orderBy, $allowedColumns[$type])) {
                $orderBy = $allowedColumns[$type][0]; // ברירת מחדל
            }
            
            $sql .= " ORDER BY $orderBy $orderDir";
            
            // עימוד
            $page = intval($_GET['page'] ?? 1);
            $limit = intval($_GET['limit'] ?? 50);
            $offset = ($page - 1) * $limit;
            
            // ספירת סך הכל
            $countSql = str_replace('SELECT *', 'SELECT COUNT(*)', $sql);
            if ($type === 'customer') {
                $countSql = str_replace("SELECT *, $nameColumn", 'SELECT COUNT(*)', $sql);
            }
            $stmt = $pdo->prepare($countSql);
            $stmt->execute($params);
            $total = $stmt->fetchColumn();
            
            // הוספת LIMIT
            $sql .= " LIMIT $limit OFFSET $offset";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // הוספת שדה name לתצוגה
            foreach ($data as &$item) {
                if ($type !== 'customer' && !isset($item['name'])) {
                    $item['name'] = $item[$nameColumn] ?? '';
                }
                if ($codeColumn && !isset($item['code'])) {
                    $item['code'] = $item[$codeColumn] ?? '';
                }
            }
            
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
            
            // תמיד חפש לפי unicId במבנה החדש
            $stmt = $pdo->prepare("SELECT * FROM $table WHERE unicId = :id");
            $stmt->execute(['id' => $id]);
            $item = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$item) {
                // נסה לחפש לפי id רגיל (תאימות לאחור)
                $stmt = $pdo->prepare("SELECT * FROM $table WHERE id = :id");
                $stmt->execute(['id' => $id]);
                $item = $stmt->fetch(PDO::FETCH_ASSOC);
            }
            
            if (!$item) {
                throw new Exception('הפריט לא נמצא');
            }
            
            // הוספת מידע על הילדים
            $children = [];
            $childTypes = [
                'cemetery' => ['type' => 'block', 'column' => 'cemeteryId'],
                'block' => ['type' => 'plot', 'column' => 'blockId'],
                'plot' => ['type' => 'row', 'column' => 'plotId'],
                'row' => ['type' => 'area_grave', 'column' => 'lineId'],
                'area_grave' => ['type' => 'grave', 'column' => 'areaGraveId']
            ];
            
            if (isset($childTypes[$type])) {
                $childInfo = $childTypes[$type];
                $childTable = getTableName($childInfo['type']);
                $parentColumn = $childInfo['column'];
                
                $stmt = $pdo->prepare(
                    "SELECT COUNT(*) as total,
                     SUM(CASE WHEN isActive = 1 THEN 1 ELSE 0 END) as active
                     FROM $childTable WHERE $parentColumn = :id"
                );
                $stmt->execute(['id' => $item['unicId']]);
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
            
            // יצירת unicId חדש
            $data['unicId'] = uniqid($type . '_', true);
            
            // הוספת תאריכים
            $data['createDate'] = date('Y-m-d H:i:s');
            $data['updateDate'] = date('Y-m-d H:i:s');
            
            // הוספת עמודת הורה אם נדרש
            $parentColumn = getParentColumn($type);
            if ($parentColumn && isset($_GET['parent_id'])) {
                $data[$parentColumn] = $_GET['parent_id'];
            }
            
            // בניית שאילתה
            $columns = [];
            $values = [];
            $params = [];
            
            foreach ($data as $key => $value) {
                if (!in_array($key, ['id', 'inactiveDate'])) {
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
                'id' => $newId,
                'unicId' => $data['unicId']
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
            
            // הוספת תאריך עדכון
            $data['updateDate'] = date('Y-m-d H:i:s');
            
            // בניית שאילתה
            $updates = [];
            $params = ['id' => $id];
            
            foreach ($data as $key => $value) {
                if (!in_array($key, ['id', 'unicId', 'createDate'])) {
                    $updates[] = "$key = :$key";
                    $params[$key] = $value;
                }
            }
            
            if (empty($updates)) {
                throw new Exception('אין נתונים לעדכון');
            }
            
            // עדכון לפי unicId או id
            $sql = "UPDATE $table SET " . implode(', ', $updates) . " WHERE unicId = :id OR id = :id";
            
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
                'cemetery' => ['table' => 'blocks', 'column' => 'cemeteryId'],
                'block' => ['table' => 'plots', 'column' => 'blockId'],
                'plot' => ['table' => 'rows', 'column' => 'plotId'],
                'row' => ['table' => 'areaGraves', 'column' => 'lineId'],
                'area_grave' => ['table' => 'graves', 'column' => 'areaGraveId']
            ];
            
            if (isset($childTypes[$type])) {
                $childInfo = $childTypes[$type];
                
                // קודם מצא את ה-unicId של הפריט
                $stmt = $pdo->prepare("SELECT unicId FROM $table WHERE unicId = :id OR id = :id");
                $stmt->execute(['id' => $id]);
                $unicId = $stmt->fetchColumn();
                
                if ($unicId) {
                    $stmt = $pdo->prepare(
                        "SELECT COUNT(*) FROM {$childInfo['table']} 
                         WHERE {$childInfo['column']} = :id AND isActive = 1"
                    );
                    $stmt->execute(['id' => $unicId]);
                    
                    if ($stmt->fetchColumn() > 0) {
                        throw new Exception('לא ניתן למחוק פריט שיש לו פריטים משויכים');
                    }
                }
            }
            
            // מחיקה רכה
            $stmt = $pdo->prepare("UPDATE $table SET isActive = 0, inactiveDate = :date WHERE unicId = :id OR id = :id");
            $stmt->execute(['id' => $id, 'date' => date('Y-m-d H:i:s')]);
            
            // רישום בלוג
            logActivity('delete', $type, $id);
            
            echo json_encode([
                'success' => true,
                'message' => 'הפריט נמחק בהצלחה'
            ]);
            break;
            
        case 'hierarchy':
            $cemeteryId = $_GET['cemetery_id'] ?? null;
            
            // שאילתה מעודכנת למבנה החדש
            $sql = "
                SELECT 
                    c.unicId as cemetery_id,
                    c.cemeteryNameHe as cemetery_name,
                    b.unicId as block_id,
                    b.blockNameHe as block_name,
                    p.unicId as plot_id,
                    p.plotNameHe as plot_name,
                    r.unicId as row_id,
                    r.lineNameHe as row_name,
                    ag.unicId as area_grave_id,
                    ag.areaGraveNameHe as area_grave_name,
                    ag.graveType,
                    g.unicId as grave_id,
                    g.graveNameHe as grave_number,
                    g.graveStatus as grave_status,
                    g.plotType as plot_type
                FROM cemeteries c
                LEFT JOIN blocks b ON b.cemeteryId = c.unicId AND b.isActive = 1
                LEFT JOIN plots p ON p.blockId = b.unicId AND p.isActive = 1
                LEFT JOIN rows r ON r.plotId = p.unicId AND r.isActive = 1
                LEFT JOIN areaGraves ag ON ag.lineId = r.unicId AND ag.isActive = 1
                LEFT JOIN graves g ON g.areaGraveId = ag.unicId AND g.isActive = 1
                WHERE c.isActive = 1
            ";
            
            $params = [];
            if ($cemeteryId) {
                $sql .= " AND (c.unicId = :cemetery_id OR c.id = :cemetery_id_alt)";
                $params['cemetery_id'] = $cemeteryId;
                $params['cemetery_id_alt'] = $cemeteryId;
            }
            
            $sql .= " ORDER BY c.cemeteryNameHe, b.blockNameHe, p.plotNameHe, r.serialNumber, 
                      r.lineNameHe, ag.areaGraveNameHe, g.graveNameHe";
            
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
                'areaGraves' => 'אחוזות קבר',
                'graves' => 'קברים'
            ];
            
            foreach ($tables as $table => $label) {
                $stmt = $pdo->query("SELECT COUNT(*) FROM $table WHERE isActive = 1");
                $stats['counts'][$table] = [
                    'label' => $label,
                    'count' => $stmt->fetchColumn()
                ];
            }
            
            // סטטוס קברים
            $stmt = $pdo->query("
                SELECT graveStatus, COUNT(*) as count 
                FROM graves 
                WHERE isActive = 1 
                GROUP BY graveStatus
            ");
            $graveStatuses = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $stats['grave_status'] = [];
            foreach ($graveStatuses as $status) {
                $stats['grave_status'][] = [
                    'status' => $status['graveStatus'],
                    'name' => GRAVE_STATUS[$status['graveStatus']]['name'] ?? 'לא ידוע',
                    'count' => $status['count'],
                    'color' => GRAVE_STATUS[$status['graveStatus']]['color'] ?? '#6b7280'
                ];
            }
            
            // סוגי חלקות
            $stmt = $pdo->query("
                SELECT plotType, COUNT(*) as count 
                FROM graves 
                WHERE isActive = 1 
                GROUP BY plotType
            ");
            $plotTypes = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $stats['plot_types'] = [];
            foreach ($plotTypes as $plotType) {
                $stats['plot_types'][] = [
                    'type' => $plotType['plotType'],
                    'name' => PLOT_TYPES[$plotType['plotType']]['name'] ?? 'לא ידוע',
                    'count' => $plotType['count'],
                    'icon' => PLOT_TYPES[$plotType['plotType']]['icon'] ?? ''
                ];
            }
            
            echo json_encode([
                'success' => true,
                'stats' => $stats
            ]);
            break;
            
        case 'item_stats':
            $itemType = $_GET['item_type'] ?? '';
            $itemId = $_GET['item_id'] ?? null;
            
            if (!$itemType || !$itemId) {
                throw new Exception('פרמטרים חסרים');
            }
            
            $stats = [];
            
            switch($itemType) {
                case 'cemetery':
                    // ספירת גושים ישירים
                    $stmt = $pdo->prepare("SELECT COUNT(*) FROM blocks WHERE cemeteryId = :id AND isActive = 1");
                    $stmt->execute(['id' => $itemId]);
                    $stats['blocks'] = $stmt->fetchColumn();
                    
                    // ספירת כל החלקות
                    $stmt = $pdo->prepare("
                        SELECT COUNT(*) FROM plots p 
                        JOIN blocks b ON p.blockId = b.unicId 
                        WHERE b.cemeteryId = :id AND p.isActive = 1
                    ");
                    $stmt->execute(['id' => $itemId]);
                    $stats['plots'] = $stmt->fetchColumn();
                    
                    // ספירת כל הקברים
                    $stmt = $pdo->prepare("
                        SELECT 
                            COUNT(*) as total,
                            SUM(CASE WHEN g.graveStatus = 1 THEN 1 ELSE 0 END) as available,
                            SUM(CASE WHEN g.graveStatus = 3 THEN 1 ELSE 0 END) as occupied
                        FROM graves g
                        JOIN areaGraves ag ON g.areaGraveId = ag.unicId
                        JOIN rows r ON ag.lineId = r.unicId
                        JOIN plots p ON r.plotId = p.unicId
                        JOIN blocks b ON p.blockId = b.unicId
                        WHERE b.cemeteryId = :id AND g.isActive = 1
                    ");
                    $stmt->execute(['id' => $itemId]);
                    $result = $stmt->fetch(PDO::FETCH_ASSOC);
                    $stats['graves'] = $result['total'] ?? 0;
                    $stats['available'] = $result['available'] ?? 0;
                    $stats['occupied'] = $result['occupied'] ?? 0;
                    break;
                    
                case 'block':
                    // ספירת חלקות
                    $stmt = $pdo->prepare("SELECT COUNT(*) FROM plots WHERE blockId = :id AND isActive = 1");
                    $stmt->execute(['id' => $itemId]);
                    $stats['plots'] = $stmt->fetchColumn();
                    
                    // ספירת קברים
                    $stmt = $pdo->prepare("
                        SELECT 
                            COUNT(*) as total,
                            SUM(CASE WHEN g.graveStatus = 1 THEN 1 ELSE 0 END) as available,
                            SUM(CASE WHEN g.graveStatus = 3 THEN 1 ELSE 0 END) as occupied
                        FROM graves g
                        JOIN areaGraves ag ON g.areaGraveId = ag.unicId
                        JOIN rows r ON ag.lineId = r.unicId
                        JOIN plots p ON r.plotId = p.unicId
                        WHERE p.blockId = :id AND g.isActive = 1
                    ");
                    $stmt->execute(['id' => $itemId]);
                    $result = $stmt->fetch(PDO::FETCH_ASSOC);
                    $stats['graves'] = $result['total'] ?? 0;
                    $stats['available'] = $result['available'] ?? 0;
                    $stats['occupied'] = $result['occupied'] ?? 0;
                    break;
                    
                case 'plot':
                    // ספירת שורות
                    $stmt = $pdo->prepare("SELECT COUNT(*) FROM rows WHERE plotId = :id AND isActive = 1");
                    $stmt->execute(['id' => $itemId]);
                    $stats['rows'] = $stmt->fetchColumn();
                    
                    // ספירת אחוזות קבר
                    $stmt = $pdo->prepare("
                        SELECT COUNT(*) FROM areaGraves ag
                        JOIN rows r ON ag.lineId = r.unicId
                        WHERE r.plotId = :id AND ag.isActive = 1
                    ");
                    $stmt->execute(['id' => $itemId]);
                    $stats['areaGraves'] = $stmt->fetchColumn();
                    
                    // ספירת קברים
                    $stmt = $pdo->prepare("
                        SELECT 
                            COUNT(*) as total,
                            SUM(CASE WHEN g.graveStatus = 1 THEN 1 ELSE 0 END) as available,
                            SUM(CASE WHEN g.graveStatus = 3 THEN 1 ELSE 0 END) as occupied
                        FROM graves g
                        JOIN areaGraves ag ON g.areaGraveId = ag.unicId
                        JOIN rows r ON ag.lineId = r.unicId
                        WHERE r.plotId = :id AND g.isActive = 1
                    ");
                    $stmt->execute(['id' => $itemId]);
                    $result = $stmt->fetch(PDO::FETCH_ASSOC);
                    $stats['graves'] = $result['total'] ?? 0;
                    $stats['available'] = $result['available'] ?? 0;
                    $stats['occupied'] = $result['occupied'] ?? 0;
                    break;
                    
                case 'area_grave':
                    // ספירת קברים באחוזת קבר
                    $stmt = $pdo->prepare("
                        SELECT 
                            COUNT(*) as total,
                            SUM(CASE WHEN graveStatus = 1 THEN 1 ELSE 0 END) as available,
                            SUM(CASE WHEN graveStatus = 2 THEN 1 ELSE 0 END) as purchased,
                            SUM(CASE WHEN graveStatus = 3 THEN 1 ELSE 0 END) as occupied
                        FROM graves 
                        WHERE areaGraveId = :id AND isActive = 1
                    ");
                    $stmt->execute(['id' => $itemId]);
                    $result = $stmt->fetch(PDO::FETCH_ASSOC);
                    $stats['total'] = $result['total'] ?? 0;
                    $stats['available'] = $result['available'] ?? 0;
                    $stats['purchased'] = $result['purchased'] ?? 0;
                    $stats['occupied'] = $result['occupied'] ?? 0;
                    break;
                    
                default:
                    throw new Exception('סוג פריט לא תקין');
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
?>