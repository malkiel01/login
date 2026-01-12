<?php
/**
 * Map Data API - שמירה וטעינה של נתוני מפה
 * Map Editor v2
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once dirname(__DIR__) . '/config.php';

// Entity type to table mapping
$entityTables = [
    'cemetery' => ['table' => 'cemeteries', 'idField' => 'unicId', 'nameField' => 'cemeteryNameHe'],
    'block' => ['table' => 'blocks', 'idField' => 'unicId', 'nameField' => 'blockNameHe'],
    'plot' => ['table' => 'plots', 'idField' => 'unicId', 'nameField' => 'plotNameHe'],
    'row' => ['table' => 'rows', 'idField' => 'unicId', 'nameField' => 'lineNameHe'],
    'areaGrave' => ['table' => 'areaGraves', 'idField' => 'unicId', 'nameField' => 'areaGraveNameHe']
];

// Parent-child relationships
$childTypeMap = [
    'cemetery' => ['childType' => 'block', 'foreignKey' => 'cemeteryId'],
    'block' => ['childType' => 'plot', 'foreignKey' => 'blockId'],
    'plot' => ['childType' => 'areaGrave', 'foreignKey' => 'plotId']  // Skip rows - they don't have map boundaries
];

// Get database connection
try {
    $pdo = getDBConnection();
} catch(PDOException $e) {
    http_response_code(500);
    die(json_encode(['success' => false, 'error' => 'Database connection failed: ' . $e->getMessage()]));
}

try {
    // Handle both GET and POST
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $action = $_GET['action'] ?? '';
        $type = $_GET['type'] ?? '';
        $id = $_GET['id'] ?? '';
        $parentType = $_GET['parentType'] ?? '';
        $parentId = $_GET['parentId'] ?? '';
    } else {
        $input = json_decode(file_get_contents('php://input'), true);
        $action = $input['action'] ?? '';
        $type = $input['type'] ?? '';
        $id = $input['id'] ?? '';
        $mapData = $input['mapData'] ?? null;
        $childType = $input['childType'] ?? '';
        $childId = $input['childId'] ?? '';
        $polygon = $input['polygon'] ?? null;
    }

    switch ($action) {
        case 'load':
            // Validate entity type
            if (!isset($entityTables[$type])) {
                throw new Exception('סוג ישות לא חוקי: ' . $type);
            }
            $tableConfig = $entityTables[$type];
            $result = loadMapData($tableConfig['table'], $tableConfig['idField'], $id);
            echo json_encode($result);
            break;

        case 'save':
            // Validate entity type
            if (!isset($entityTables[$type])) {
                throw new Exception('סוג ישות לא חוקי: ' . $type);
            }
            if (!$mapData) {
                throw new Exception('חסרים נתוני מפה');
            }
            $tableConfig = $entityTables[$type];
            $result = saveMapData($tableConfig['table'], $tableConfig['idField'], $id, $mapData);
            echo json_encode($result);
            break;

        case 'getChildren':
            // Get children of a parent entity
            if (!isset($childTypeMap[$parentType])) {
                throw new Exception('סוג הורה לא חוקי או אין לו ילדים: ' . $parentType);
            }
            $result = getChildren($parentType, $parentId);
            echo json_encode($result);
            break;

        case 'saveChildPolygon':
            // Save polygon for a child entity
            if (!isset($entityTables[$childType])) {
                throw new Exception('סוג ישות ילד לא חוקי: ' . $childType);
            }
            if (!$polygon) {
                throw new Exception('חסרים נתוני פוליגון');
            }
            $result = saveChildPolygon($childType, $childId, $polygon);
            echo json_encode($result);
            break;

        case 'deleteChildPolygon':
            // Delete polygon for a child entity
            if (!isset($entityTables[$childType])) {
                throw new Exception('סוג ישות ילד לא חוקי: ' . $childType);
            }
            $result = deleteChildPolygon($childType, $childId);
            echo json_encode($result);
            break;

        default:
            throw new Exception('פעולה לא חוקית: ' . $action);
    }

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

/**
 * Load map data for entity
 */
function loadMapData($table, $idField, $id) {
    global $pdo;

    try {
        // Build column list based on table
        // cemeteries has additional columns: mapBackgroundImage, mapSettings
        if ($table === 'cemeteries') {
            $columns = 'mapCanvasData, mapPolygon, mapBackgroundImage, mapSettings';
        } else {
            $columns = 'mapCanvasData, mapPolygon';
        }

        $sql = "SELECT {$columns} FROM {$table} WHERE {$idField} = :id LIMIT 1";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['id' => $id]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            // Check if record exists
            $checkSql = "SELECT {$idField} FROM {$table} WHERE {$idField} = :id LIMIT 1";
            $checkStmt = $pdo->prepare($checkSql);
            $checkStmt->execute(['id' => $id]);

            if (!$checkStmt->fetch()) {
                return [
                    'success' => false,
                    'error' => 'רשומה לא נמצאה: ' . $id
                ];
            }

            // Record exists but no map data columns
            return [
                'success' => true,
                'mapData' => null
            ];
        }

        $mapData = null;
        if (isset($row['mapCanvasData']) && $row['mapCanvasData']) {
            $mapData = json_decode($row['mapCanvasData'], true);
        }

        // Build result
        $result = [
            'success' => true,
            'mapData' => $mapData
        ];

        if (isset($row['mapPolygon']) && $row['mapPolygon']) {
            $result['mapPolygon'] = json_decode($row['mapPolygon'], true);
        }
        if (isset($row['mapBackgroundImage']) && $row['mapBackgroundImage']) {
            $result['mapBackgroundImage'] = json_decode($row['mapBackgroundImage'], true);
        }
        if (isset($row['mapSettings']) && $row['mapSettings']) {
            $result['mapSettings'] = json_decode($row['mapSettings'], true);
        }

        return $result;
    } catch (PDOException $e) {
        // If column doesn't exist, try simpler query
        if (strpos($e->getMessage(), 'Unknown column') !== false) {
            return [
                'success' => true,
                'mapData' => null,
                'warning' => 'עמודות מפה לא קיימות בטבלה - יש להריץ את סקריפט ההעברה'
            ];
        }
        throw $e;
    }
}

/**
 * Save map data for entity
 */
function saveMapData($table, $idField, $id, $mapData) {
    global $pdo;

    // Convert map data to JSON
    $mapDataJson = json_encode($mapData, JSON_UNESCAPED_UNICODE);

    // First check if record exists
    $checkSql = "SELECT {$idField} FROM {$table} WHERE {$idField} = :id LIMIT 1";
    $checkStmt = $pdo->prepare($checkSql);
    $checkStmt->execute(['id' => $id]);

    if (!$checkStmt->fetch()) {
        throw new Exception('רשומה לא נמצאה: ' . $id);
    }

    // Update mapCanvasData column
    try {
        $sql = "UPDATE {$table} SET mapCanvasData = :mapCanvasData WHERE {$idField} = :id";
        $stmt = $pdo->prepare($sql);
        $result = $stmt->execute([
            'mapCanvasData' => $mapDataJson,
            'id' => $id
        ]);
    } catch (PDOException $e) {
        // If mapCanvasData column doesn't exist
        if (strpos($e->getMessage(), 'mapCanvasData') !== false || strpos($e->getMessage(), 'Unknown column') !== false) {
            throw new Exception('עמודת mapCanvasData לא קיימת בטבלה - יש להריץ את סקריפט ההעברה: sql/add-map-polygon-fields.sql');
        }
        throw $e;
    }

    if (!$result) {
        throw new Exception('שגיאה בשמירת נתוני מפה');
    }

    return [
        'success' => true,
        'message' => 'נתוני המפה נשמרו בהצלחה'
    ];
}

/**
 * Get children of a parent entity with their polygon data
 */
function getChildren($parentType, $parentId) {
    global $pdo, $entityTables, $childTypeMap;

    $childConfig = $childTypeMap[$parentType];
    $childType = $childConfig['childType'];
    $foreignKey = $childConfig['foreignKey'];

    $childTableConfig = $entityTables[$childType];
    $table = $childTableConfig['table'];
    $idField = $childTableConfig['idField'];
    $nameField = $childTableConfig['nameField'];

    try {
        $sql = "SELECT {$idField} as id, {$nameField} as name, mapPolygon
                FROM {$table}
                WHERE {$foreignKey} = :parentId
                AND isActive = 1
                ORDER BY {$nameField}";

        $stmt = $pdo->prepare($sql);
        $stmt->execute(['parentId' => $parentId]);

        $children = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $children[] = [
                'id' => $row['id'],
                'name' => $row['name'] ?? $row['id'],
                'type' => $childType,
                'hasPolygon' => !empty($row['mapPolygon']),
                'polygon' => $row['mapPolygon'] ? json_decode($row['mapPolygon'], true) : null
            ];
        }

        return [
            'success' => true,
            'children' => $children,
            'childType' => $childType,
            'count' => count($children)
        ];
    } catch (PDOException $e) {
        // If mapPolygon column doesn't exist, try without it
        if (strpos($e->getMessage(), 'Unknown column') !== false) {
            $sql = "SELECT {$idField} as id, {$nameField} as name
                    FROM {$table}
                    WHERE {$foreignKey} = :parentId
                    AND isActive = 1
                    ORDER BY {$nameField}";

            $stmt = $pdo->prepare($sql);
            $stmt->execute(['parentId' => $parentId]);

            $children = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $children[] = [
                    'id' => $row['id'],
                    'name' => $row['name'] ?? $row['id'],
                    'type' => $childType,
                    'hasPolygon' => false,
                    'polygon' => null
                ];
            }

            return [
                'success' => true,
                'children' => $children,
                'childType' => $childType,
                'count' => count($children),
                'warning' => 'עמודת mapPolygon לא קיימת - יש להריץ את סקריפט ההעברה'
            ];
        }
        throw $e;
    }
}

/**
 * Save polygon data for a child entity
 */
function saveChildPolygon($childType, $childId, $polygon) {
    global $pdo, $entityTables;

    $tableConfig = $entityTables[$childType];
    $table = $tableConfig['table'];
    $idField = $tableConfig['idField'];

    // Convert polygon to JSON
    $polygonJson = json_encode($polygon, JSON_UNESCAPED_UNICODE);

    // Check if record exists
    $checkSql = "SELECT {$idField} FROM {$table} WHERE {$idField} = :id LIMIT 1";
    $checkStmt = $pdo->prepare($checkSql);
    $checkStmt->execute(['id' => $childId]);

    if (!$checkStmt->fetch()) {
        throw new Exception('רשומת ילד לא נמצאה: ' . $childId);
    }

    // Update mapPolygon
    try {
        $sql = "UPDATE {$table} SET mapPolygon = :polygon WHERE {$idField} = :id";
        $stmt = $pdo->prepare($sql);
        $result = $stmt->execute([
            'polygon' => $polygonJson,
            'id' => $childId
        ]);
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'mapPolygon') !== false || strpos($e->getMessage(), 'Unknown column') !== false) {
            throw new Exception('עמודת mapPolygon לא קיימת בטבלה - יש להריץ את סקריפט ההעברה: sql/add-map-polygon-fields.sql');
        }
        throw $e;
    }

    if (!$result) {
        throw new Exception('שגיאה בשמירת פוליגון');
    }

    return [
        'success' => true,
        'message' => 'הפוליגון נשמר בהצלחה'
    ];
}

/**
 * Delete polygon data for a child entity
 */
function deleteChildPolygon($childType, $childId) {
    global $pdo, $entityTables;

    $tableConfig = $entityTables[$childType];
    $table = $tableConfig['table'];
    $idField = $tableConfig['idField'];

    // Check if record exists
    $checkSql = "SELECT {$idField} FROM {$table} WHERE {$idField} = :id LIMIT 1";
    $checkStmt = $pdo->prepare($checkSql);
    $checkStmt->execute(['id' => $childId]);

    if (!$checkStmt->fetch()) {
        throw new Exception('רשומת ילד לא נמצאה: ' . $childId);
    }

    // Set mapPolygon to NULL
    $sql = "UPDATE {$table} SET mapPolygon = NULL WHERE {$idField} = :id";
    $stmt = $pdo->prepare($sql);
    $result = $stmt->execute(['id' => $childId]);

    if (!$result) {
        throw new Exception('שגיאה במחיקת פוליגון');
    }

    return [
        'success' => true,
        'message' => 'הפוליגון נמחק בהצלחה'
    ];
}
