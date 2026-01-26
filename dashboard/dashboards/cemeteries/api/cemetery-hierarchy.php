<?php
// ⚠️ DEBUG: הצגת שגיאות - חייב להיות ראשון!
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// cemetery_dashboard/api/cemetery-hierarchy.php
// API לניהול היררכיית בתי עלמין - משתמש ב-HierarchyManager

try {
    // אימות והרשאות - חייב להיות מחובר!
    require_once __DIR__ . '/api-auth.php';

    require_once '../classes/HierarchyManager.php';
    require_once '../includes/permissions-mapper.php';
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
    exit;
}

// קבלת פרמטרים
$action = $_GET['action'] ?? '';
$type = $_GET['type'] ?? '';
$id = $_GET['id'] ?? null;

// מיפוי סוג entity למודול הרשאות
function getModuleForEntityType(string $type): string {
    $map = [
        'cemetery' => 'cemeteries',
        'block' => 'blocks',
        'plot' => 'plots',
        'row' => 'rows',
        'areaGrave' => 'areaGraves',
        'grave' => 'graves'
    ];
    return $map[$type] ?? $type;
}

// קבלת תפקיד המשתמש - לשימוש ב-HierarchyManager (לא להרשאות!)
$userRole = getCurrentUserRole() ?? 'viewer';

try {
    $pdo = getDBConnection();
    
    // יצירת מופע של HierarchyManager
    $manager = new HierarchyManager($pdo, $userRole);
    
    switch($action) {
        case 'list':
            // בדיקה שהסוג תקין
            $config = $manager->getConfig($type);
            if (!$config) {
                throw new Exception('סוג לא תקין');
            }
            
            // אתחול משתנים
            $conditions = [];
            $sql = '';
            $params = [];
            $page = intval($_GET['page'] ?? 1);
            $limit = intval($_GET['limit'] ?? 50);
            $offset = ($page - 1) * $limit;
            
            // טיפול מיוחד באחוזות קבר עם חלקות
            if ($type === 'areaGrave' && isset($_GET['plot_id'])) {
                // קודם מצא את כל השורות של החלקה
                $rows_query = "SELECT unicId FROM rows WHERE plotId = :plot_id AND isActive = 1";
                $stmt = $pdo->prepare($rows_query);
                $stmt->execute(['plot_id' => $_GET['plot_id']]);
                $row_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
                
                if (empty($row_ids)) {
                    // אין שורות - החזר תוצאה ריקה
                    echo json_encode([
                        'success' => true,
                        'data' => [],
                        'pagination' => [
                            'page' => $page,
                            'limit' => $limit,
                            'total' => 0,
                            'pages' => 0
                        ]
                    ]);
                    exit;
                }
                
                // יש שורות - בנה שאילתה מיוחדת
                $params = [];
                $placeholders = [];
                foreach ($row_ids as $index => $row_id) {
                    $param_name = "row_id_$index";
                    $placeholders[] = ":$param_name";
                    $params[$param_name] = $row_id;
                }
                
                $sql = "SELECT * FROM areaGraves 
                        WHERE lineId IN (" . implode(',', $placeholders) . ") 
                        AND isActive = 1
                        LIMIT $limit OFFSET $offset";
                        
            } else {
                // טיפול רגיל - כל שאר המקרים
                if (isset($_GET['parent_id'])) {
                    $parentKey = $manager->getParentKey($type);
                    if ($parentKey) {
                        $conditions[$parentKey] = $_GET['parent_id'];
                    }
                }
                
                if (isset($_GET['search']) && !empty($_GET['search'])) {
                    $conditions['search'] = $_GET['search'];
                }
                
                // בניית השאילתה עם HierarchyManager
                $orderBy = $_GET['sort'] ?? null;
                $orderDir = $_GET['order'] ?? 'ASC';
                if ($orderBy) {
                    $orderBy = "$orderBy $orderDir";
                }
                
                $queryData = $manager->buildSelectQuery($type, $conditions, $orderBy, $limit, $offset);
                $sql = $queryData['sql'];
                $params = $queryData['params'];
            }
            
            // ספירת סך הכל
            $countSql = str_replace('SELECT *', 'SELECT COUNT(*)', $sql);
            $countSql = preg_replace('/SELECT .* FROM/', 'SELECT COUNT(*) FROM', $countSql);
            $countSql = preg_replace('/LIMIT \d+ OFFSET \d+/', '', $countSql);
            
            $stmt = $pdo->prepare($countSql);
            $stmt->execute($params);
            $total = $stmt->fetchColumn();
            
            // ביצוע השאילתה
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // הוספת שדה name לתצוגה (לתאימות לאחור)
            $displayFields = $config['displayFields'] ?? [];
            foreach ($data as &$item) {
                if (!isset($item['name']) && isset($displayFields['name'])) {
                    $item['name'] = $item[$displayFields['name']] ?? '';
                }
                if (!isset($item['code']) && isset($displayFields['code'])) {
                    $item['code'] = $item[$displayFields['code']] ?? '';
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
            $config = $manager->getConfig($type);
            if (!$config || !$id) {
                throw new Exception('פרמטרים חסרים');
            }
            
            $table = $config['table'];
            $primaryKey = $config['primaryKey'];
            
            // חיפוש לפי מפתח ראשי
            $stmt = $pdo->prepare("SELECT * FROM $table WHERE $primaryKey = :id AND isActive = 1");
            $stmt->execute(['id' => $id]);
            $item = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$item) {
                // נסה לחפש לפי id רגיל (תאימות לאחור)
                $stmt = $pdo->prepare("SELECT * FROM $table WHERE id = :id AND isActive = 1");
                $stmt->execute(['id' => $id]);
                $item = $stmt->fetch(PDO::FETCH_ASSOC);
            }
            
            if (!$item) {
                throw new Exception('הפריט לא נמצא');
            }
            
            // הוספת מידע על הילדים
            $children = [];
            $childTypes = [
                'cemetery' => 'block',
                'block' => 'plot',
                'plot' => 'row',
                'row' => 'areaGrave',
                'areaGrave' => 'grave'
            ];
            
            if (isset($childTypes[$type])) {
                $childType = $childTypes[$type];
                $childConfig = $manager->getConfig($childType);
                if ($childConfig) {
                    $childTable = $childConfig['table'];
                    $parentColumn = $childConfig['parentKey'];
                    
                    $stmt = $pdo->prepare(
                        "SELECT COUNT(*) as total,
                         SUM(CASE WHEN isActive = 1 THEN 1 ELSE 0 END) as active
                         FROM $childTable WHERE $parentColumn = :id"
                    );
                    $stmt->execute(['id' => $item[$primaryKey]]);
                    $children = $stmt->fetch(PDO::FETCH_ASSOC);
                }
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

            // בדיקת הרשאה - שימוש במערכת ההרשאות החדשה
            $module = getModuleForEntityType($type);
            if (!isAdmin() && !hasModulePermission($module, 'create')) {
                http_response_code(403);
                throw new Exception('אין הרשאה ליצור פריט חדש');
            }
            
            $config = $manager->getConfig($type);
            if (!$config) {
                throw new Exception('סוג לא תקין');
            }
            
            $data = json_decode(file_get_contents('php://input'), true);
            if (!$data) {
                throw new Exception('נתונים לא תקינים');
            }
            
            $table = $config['table'];
            $primaryKey = $config['primaryKey'];
            
            // יצירת מפתח ייחודי
            $data[$primaryKey] = uniqid($type . '_', true);
            
            // הוספת תאריכים
            $data['createDate'] = date('Y-m-d H:i:s');
            $data['updateDate'] = date('Y-m-d H:i:s');
            $data['isActive'] = 1;
            
            // הוספת עמודת הורה אם נדרש
            $parentKey = $config['parentKey'];
            if ($parentKey && isset($_GET['parent_id'])) {
                $data[$parentKey] = $_GET['parent_id'];
            }
            
            // סינון שדות לפי הרשאות
            $formFields = $manager->getFormFields($type, 'create');
            $allowedFields = array_column($formFields, 'name');
            $allowedFields[] = $primaryKey;
            $allowedFields[] = 'createDate';
            $allowedFields[] = 'updateDate';
            $allowedFields[] = 'isActive';
            if ($parentKey) {
                $allowedFields[] = $parentKey;
            }
            
            // בניית שאילתה
            $columns = [];
            $values = [];
            $params = [];
            
            foreach ($data as $key => $value) {
                if (in_array($key, $allowedFields) && !in_array($key, ['id', 'inactiveDate'])) {
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
                $primaryKey => $data[$primaryKey]
            ]);
            break;
            
        case 'update':
            if ($_SERVER['REQUEST_METHOD'] !== 'PUT') {
                throw new Exception('שיטת בקשה לא תקינה');
            }

            // בדיקת הרשאה - שימוש במערכת ההרשאות החדשה
            $module = getModuleForEntityType($type);
            if (!isAdmin() && !hasModulePermission($module, 'edit')) {
                http_response_code(403);
                throw new Exception('אין הרשאה לערוך');
            }
            
            $config = $manager->getConfig($type);
            if (!$config || !$id) {
                throw new Exception('פרמטרים חסרים');
            }
            
            $data = json_decode(file_get_contents('php://input'), true);
            if (!$data) {
                throw new Exception('נתונים לא תקינים');
            }
            
            $table = $config['table'];
            $primaryKey = $config['primaryKey'];
            
            // התעלמות משינוי אקטיב
            unset($data['isActive']); 

            // הוספת תאריך עדכון
            $data['updateDate'] = date('Y-m-d H:i:s');

            
            // סינון שדות לפי הרשאות
            $formFields = $manager->getFormFields($type, 'edit');
            $allowedFields = array_column($formFields, 'name');
            $allowedFields[] = 'updateDate';
            
            // בניית שאילתה
            $updates = [];
            $params = ['id' => $id];
            
            foreach ($data as $key => $value) {
                if (in_array($key, $allowedFields) && !in_array($key, ['id', $primaryKey, 'createDate'])) {
                    $updates[] = "$key = :$key";
                    $params[$key] = $value;
                }
            }
            
            if (empty($updates)) {
                throw new Exception('אין נתונים לעדכון');
            }
            
            // עדכון לפי מפתח ראשי
            $sql = "UPDATE $table SET " . implode(', ', $updates) . 
                    " WHERE $primaryKey = :id";
            
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

            // בדיקת הרשאה - שימוש במערכת ההרשאות החדשה
            $module = getModuleForEntityType($type);
            if (!isAdmin() && !hasModulePermission($module, 'delete')) {
                http_response_code(403);
                throw new Exception('אין הרשאה למחוק');
            }
            
            $config = $manager->getConfig($type);
            if (!$config || !$id) {
                throw new Exception('פרמטרים חסרים');
            }
            
            $table = $config['table'];
            $primaryKey = $config['primaryKey'];
            
            // בדיקה אם יש ילדים
            $childTypes = [
                'cemetery' => 'block',
                'block' => 'plot',
                'plot' => 'row',
                'row' => 'areaGrave',
                'areaGrave' => 'grave'
            ];
            
            if (isset($childTypes[$type])) {
                $childType = $childTypes[$type];
                $childConfig = $manager->getConfig($childType);
                
                if ($childConfig) {
                    // קודם מצא את המפתח הראשי של הפריט
                    $stmt = $pdo->prepare("SELECT $primaryKey FROM $table WHERE $primaryKey = :id");
                    $stmt->execute(['id' => $id]);
                    $itemKey = $stmt->fetchColumn();
                    
                    if ($itemKey) {
                        $childTable = $childConfig['table'];
                        $parentColumn = $childConfig['parentKey'];
                        
                        $stmt = $pdo->prepare(
                            "SELECT COUNT(*) FROM $childTable 
                             WHERE $parentColumn = :id AND isActive = 1"
                        );
                        $stmt->execute(['id' => $itemKey]);
                        
                        if ($stmt->fetchColumn() > 0) {
                            throw new Exception('לא ניתן למחוק פריט שיש לו פריטים משויכים');
                        }
                    }
                }
            }
            
            // מחיקה רכה
            $stmt = $pdo->prepare(
                "UPDATE $table SET isActive = 0, inactiveDate = :date 
                 WHERE $primaryKey = :id"
            );
            $stmt->execute(['id' => $id, 'date' => date('Y-m-d H:i:s')]);
            
            // רישום בלוג
            logActivity('delete', $type, $id);
            
            echo json_encode([
                'success' => true,
                'message' => 'הפריט נמחק בהצלחה'
            ]);
            break;
            
        case 'get_config':
            // מחזיר את הגדרות הטבלה לצד הלקוח
            $config = $manager->getConfig($type);
            if (!$config) {
                throw new Exception('סוג לא תקין');
            }
            
            // מחזיר רק את מה שהמשתמש רשאי לראות
            $response = [
                'title' => $config['title'],
                'singular' => $config['singular'],
                'icon' => $config['icon'],
                'table_columns' => $manager->getTableColumns($type),
                'form_fields' => $manager->getFormFields($type),
                'permissions' => [
                    'can_view' => $manager->canView(),
                    'can_edit' => $manager->canEdit(),
                    'can_delete' => $manager->canDelete(),
                    'can_create' => $manager->canCreate()
                ]
            ];
            
            echo json_encode([
                'success' => true,
                'config' => $response
            ]);
            break;
            
        case 'item_stats':
            $itemType = $_GET['item_type'] ?? '';
            $itemId = $_GET['itemId'] ?? '';
            
            if (!$itemType || !$itemId) {
                throw new Exception('פרמטרים חסרים');
            }
            
            $stats = [];
            
            switch($itemType) {
                case 'cemetery':
                    // ספירת גושים
                    $stmt = $pdo->prepare("SELECT COUNT(*) FROM blocks WHERE cemeteryId = :id AND isActive = 1");
                    $stmt->execute(['id' => $itemId]);
                    $stats['blocks'] = $stmt->fetchColumn();
                    
                    // ספירת חלקות
                    $stmt = $pdo->prepare("
                        SELECT COUNT(*) FROM plots p 
                        JOIN blocks b ON p.blockId = b.unicId 
                        WHERE b.cemeteryId = :id AND p.isActive = 1
                    ");
                    $stmt->execute(['id' => $itemId]);
                    $stats['plots'] = $stmt->fetchColumn();
                    
                    // ספירת קברים וסטטוסים
                    $stmt = $pdo->prepare("
                        SELECT 
                            COUNT(*) as total,
                            SUM(CASE WHEN g.graveStatus = 1 THEN 1 ELSE 0 END) as available
                        FROM graves g
                        JOIN areaGraves ag ON g.areaGraveId = ag.unicId
                        JOIN rows r ON ag.lineId = r.unicId  
                        JOIN plots p ON r.plotId = p.unicId
                        JOIN blocks b ON p.blockId = b.unicId
                        WHERE b.cemeteryId = :id AND g.isActive = 1
                    ");
                    $stmt->execute(['id' => $itemId]);
                    $result = $stmt->fetch(PDO::FETCH_ASSOC);
                    $stats['graves'] = $result['total'];
                    $stats['available'] = $result['available'];
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
                    $stats['graves'] = $result['total'];
                    $stats['available'] = $result['available'];
                    $stats['occupied'] = $result['occupied'];
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
                            SUM(CASE WHEN g.graveStatus = 1 THEN 1 ELSE 0 END) as available
                        FROM graves g
                        JOIN areaGraves ag ON g.areaGraveId = ag.unicId
                        JOIN rows r ON ag.lineId = r.unicId
                        WHERE r.plotId = :id AND g.isActive = 1
                    ");
                    $stmt->execute(['id' => $itemId]);
                    $result = $stmt->fetch(PDO::FETCH_ASSOC);
                    $stats['graves'] = $result['total'];
                    $stats['available'] = $result['available'];
                    break;
                    
                case 'areaGrave':
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
                    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
                    break;
            }
            
            echo json_encode([
                'success' => true,
                'stats' => $stats
            ]);
            break;  
              
        case 'hierarchy':
            // שאילתה מעודכנת למבנה החדש עם שימוש בקונפיג
            $cemeteryConfig = $manager->getConfig('cemetery');
            $blockConfig = $manager->getConfig('block');
            $plotConfig = $manager->getConfig('plot');
            $rowConfig = $manager->getConfig('row');
            $areaGraveConfig = $manager->getConfig('areaGrave');
            $graveConfig = $manager->getConfig('grave');
            
            $sql = "
                SELECT 
                    c.unicId as cemetery_id,
                    c.{$cemeteryConfig['displayFields']['name']} as cemeteryNameHe,
                    b.unicId as block_id,
                    b.{$blockConfig['displayFields']['name']} as blockNameHe,
                    p.unicId as plot_id,
                    p.{$plotConfig['displayFields']['name']} as plot_name,
                    r.unicId as row_id,
                    r.{$rowConfig['displayFields']['name']} as row_name,
                    ag.unicId as area_grave_id,
                    ag.{$areaGraveConfig['displayFields']['name']} as area_grave_name,
                    ag.graveType,
                    g.unicId as grave_id,
                    g.{$graveConfig['displayFields']['name']} as grave_number,
                    g.graveStatus as grave_status,
                    g.plotType as plot_type
                FROM {$cemeteryConfig['table']} c
                LEFT JOIN {$blockConfig['table']} b ON b.{$blockConfig['parentKey']} = c.unicId AND b.isActive = 1
                LEFT JOIN {$plotConfig['table']} p ON p.{$plotConfig['parentKey']} = b.unicId AND p.isActive = 1
                LEFT JOIN {$rowConfig['table']} r ON r.{$rowConfig['parentKey']} = p.unicId AND r.isActive = 1
                LEFT JOIN {$areaGraveConfig['table']} ag ON ag.{$areaGraveConfig['parentKey']} = r.unicId AND ag.isActive = 1
                LEFT JOIN {$graveConfig['table']} g ON g.{$graveConfig['parentKey']} = ag.unicId AND g.isActive = 1
                WHERE c.isActive = 1
            ";
            
            $params = [];
            if (isset($_GET['cemetery_id'])) {
                $sql .= " AND (c.unicId = :cemetery_id OR c.id = :cemetery_id_alt)";
                $params['cemetery_id'] = $_GET['cemetery_id'];
                $params['cemetery_id_alt'] = $_GET['cemetery_id'];
            }
            
            $sql .= " ORDER BY cemeteryNameHe, blockNameHe, plot_name, r.serialNumber, 
                      row_name, area_grave_name, grave_number";
            
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
                $cemId = $row['cemetery_id'];
                if (!isset($hierarchy[$cemId])) {
                    $hierarchy[$cemId] = [
                        'id' => $cemId,
                        'name' => $row['cemeteryNameHe'],
                        'blocks' => []
                    ];
                }
                
                if ($row['block_id']) {
                    $blockId = $row['block_id'];
                    if (!isset($hierarchy[$cemId]['blocks'][$blockId])) {
                        $hierarchy[$cemId]['blocks'][$blockId] = [
                            'id' => $blockId,
                            'name' => $row['blockNameHe'],
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
            
        case 'save_map':
            // שמירת נתוני מפה
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                throw new Exception('שיטת בקשה לא תקינה');
            }

            $config = $manager->getConfig($type);
            if (!$config || !$id) {
                throw new Exception('פרמטרים חסרים');
            }

            $data = json_decode(file_get_contents('php://input'), true);
            if (!$data || !isset($data['mapData'])) {
                throw new Exception('נתוני מפה חסרים');
            }

            $table = $config['table'];
            $primaryKey = $config['primaryKey'];

            // שמירת נתוני המפה
            $stmt = $pdo->prepare(
                "UPDATE $table SET mapData = :mapData, updateDate = :updateDate
                 WHERE $primaryKey = :id"
            );
            $stmt->execute([
                'id' => $id,
                'mapData' => json_encode($data['mapData']),
                'updateDate' => date('Y-m-d H:i:s')
            ]);

            echo json_encode([
                'success' => true,
                'message' => 'המפה נשמרה בהצלחה'
            ]);
            break;

        case 'get_map':
            // טעינת נתוני מפה
            $config = $manager->getConfig($type);
            if (!$config || !$id) {
                throw new Exception('פרמטרים חסרים');
            }

            $table = $config['table'];
            $primaryKey = $config['primaryKey'];

            $stmt = $pdo->prepare("SELECT mapData FROM $table WHERE $primaryKey = :id AND isActive = 1");
            $stmt->execute(['id' => $id]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$result) {
                throw new Exception('הפריט לא נמצא');
            }

            $mapData = null;
            if ($result['mapData']) {
                $mapData = json_decode($result['mapData'], true);
            }

            echo json_encode([
                'success' => true,
                'mapData' => $mapData
            ]);
            break;

        case 'get_parent_map':
            // טעינת נתוני מפה של ההורה והסבא
            $config = $manager->getConfig($type);
            if (!$config || !$id) {
                throw new Exception('פרמטרים חסרים');
            }

            // הגדרת היררכיית הורים
            $parentTypes = [
                'block' => ['parentKey' => 'cemeteryId', 'parentType' => 'cemetery', 'parentTable' => 'cemeteries'],
                'plot' => ['parentKey' => 'blockId', 'parentType' => 'block', 'parentTable' => 'blocks']
            ];

            // בדיקה אם לסוג זה יש הורה
            if (!isset($parentTypes[$type])) {
                // cemetery הוא שורש - אין לו הורה
                echo json_encode([
                    'success' => true,
                    'hasParent' => false,
                    'parentMapData' => null,
                    'grandparentMapData' => null
                ]);
                break;
            }

            $parentInfo = $parentTypes[$type];
            $table = $config['table'];
            $primaryKey = $config['primaryKey'];

            // קבל את הרשומה הנוכחית כדי למצוא את ההורה
            $stmt = $pdo->prepare("SELECT {$parentInfo['parentKey']} FROM $table WHERE $primaryKey = :id AND isActive = 1");
            $stmt->execute(['id' => $id]);
            $item = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$item) {
                throw new Exception('הפריט לא נמצא');
            }

            $parentId = $item[$parentInfo['parentKey']];
            if (!$parentId) {
                throw new Exception('לא נמצא הורה לפריט זה');
            }

            // קבל את נתוני המפה של ההורה
            $parentConfig = $manager->getConfig($parentInfo['parentType']);
            $stmt = $pdo->prepare("SELECT * FROM {$parentInfo['parentTable']} WHERE {$parentConfig['primaryKey']} = :id AND isActive = 1");
            $stmt->execute(['id' => $parentId]);
            $parentResult = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$parentResult) {
                throw new Exception('ההורה לא נמצא');
            }

            $parentMapData = null;
            if ($parentResult['mapData']) {
                $parentMapData = json_decode($parentResult['mapData'], true);
            }

            // בדיקה אם יש גבול מוגדר להורה
            $hasBoundary = false;
            if ($parentMapData && isset($parentMapData['canvasJSON']) && isset($parentMapData['canvasJSON']['objects'])) {
                foreach ($parentMapData['canvasJSON']['objects'] as $obj) {
                    if (isset($obj['objectType']) && $obj['objectType'] === 'boundaryOutline') {
                        $hasBoundary = true;
                        break;
                    }
                }
            }

            // בדיקה אם להורה יש הורה (סבא)
            $grandparentMapData = null;
            $grandparentHasBoundary = false;
            $grandparentType = null;
            $grandparentId = null;

            if (isset($parentTypes[$parentInfo['parentType']])) {
                $grandparentInfo = $parentTypes[$parentInfo['parentType']];
                $grandparentId = $parentResult[$grandparentInfo['parentKey']] ?? null;

                if ($grandparentId) {
                    $grandparentConfig = $manager->getConfig($grandparentInfo['parentType']);
                    $stmt = $pdo->prepare("SELECT mapData FROM {$grandparentInfo['parentTable']} WHERE {$grandparentConfig['primaryKey']} = :id AND isActive = 1");
                    $stmt->execute(['id' => $grandparentId]);
                    $grandparentResult = $stmt->fetch(PDO::FETCH_ASSOC);

                    if ($grandparentResult && $grandparentResult['mapData']) {
                        $grandparentMapData = json_decode($grandparentResult['mapData'], true);
                        $grandparentType = $grandparentInfo['parentType'];

                        // בדיקה אם יש גבול מוגדר לסבא
                        if ($grandparentMapData && isset($grandparentMapData['canvasJSON']) && isset($grandparentMapData['canvasJSON']['objects'])) {
                            foreach ($grandparentMapData['canvasJSON']['objects'] as $obj) {
                                if (isset($obj['objectType']) && $obj['objectType'] === 'boundaryOutline') {
                                    $grandparentHasBoundary = true;
                                    break;
                                }
                            }
                        }
                    }
                }
            }

            echo json_encode([
                'success' => true,
                'hasParent' => true,
                'parentId' => $parentId,
                'parentType' => $parentInfo['parentType'],
                'parentMapData' => $parentMapData,
                'parentHasBoundary' => $hasBoundary,
                'hasGrandparent' => $grandparentMapData !== null,
                'grandparentId' => $grandparentId,
                'grandparentType' => $grandparentType,
                'grandparentMapData' => $grandparentMapData,
                'grandparentHasBoundary' => $grandparentHasBoundary
            ]);
            break;

        case 'stats':
            // סטטיסטיקות כלליות
            $stats = [];
            
            // ספירת פריטים בכל רמה
            $types = ['cemetery', 'block', 'plot', 'row', 'areaGrave', 'grave'];
            
            foreach ($types as $statType) {
                $statConfig = $manager->getConfig($statType);
                if ($statConfig) {
                    $stmt = $pdo->query("SELECT COUNT(*) FROM {$statConfig['table']} WHERE isActive = 1");
                    $stats['counts'][$statType] = [
                        'label' => $statConfig['title'],
                        'count' => $stmt->fetchColumn()
                    ];
                }
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