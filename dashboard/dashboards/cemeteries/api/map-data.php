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
    'cemetery' => ['table' => 'cemeteries', 'idField' => 'unicId'],
    'block' => ['table' => 'blocks', 'idField' => 'unicId'],
    'plot' => ['table' => 'plots', 'idField' => 'unicId'],
    'row' => ['table' => 'rows', 'idField' => 'unicId'],
    'areaGrave' => ['table' => 'areaGraves', 'idField' => 'unicId']
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
    } else {
        $input = json_decode(file_get_contents('php://input'), true);
        $action = $input['action'] ?? '';
        $type = $input['type'] ?? '';
        $id = $input['id'] ?? '';
        $mapData = $input['mapData'] ?? null;
    }

    // Validate entity type
    if (!isset($entityTables[$type])) {
        throw new Exception('סוג ישות לא חוקי: ' . $type);
    }

    $tableConfig = $entityTables[$type];
    $table = $tableConfig['table'];
    $idField = $tableConfig['idField'];

    switch ($action) {
        case 'load':
            $result = loadMapData($table, $idField, $id);
            echo json_encode($result);
            break;

        case 'save':
            if (!$mapData) {
                throw new Exception('חסרים נתוני מפה');
            }
            $result = saveMapData($table, $idField, $id, $mapData);
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
