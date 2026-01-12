<?php
/**
 * Map Data API - שמירה וטעינה של נתוני מפה
 * Map Editor v2
 */

header('Content-Type: application/json; charset=utf-8');

require_once dirname(__DIR__) . '/config.php';

// Entity type to table mapping
$entityTables = [
    'cemetery' => ['table' => 'cemeteries', 'idField' => 'unicId'],
    'block' => ['table' => 'blocks', 'idField' => 'unicId'],
    'plot' => ['table' => 'plots', 'idField' => 'unicId'],
    'row' => ['table' => 'rows', 'idField' => 'unicId'],
    'areaGrave' => ['table' => 'areaGraves', 'idField' => 'unicId']
];

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

    $sql = "SELECT mapData FROM {$table} WHERE {$idField} = :id LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['id' => $id]);

    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        return [
            'success' => false,
            'error' => 'רשומה לא נמצאה'
        ];
    }

    $mapData = null;
    if ($row['mapData']) {
        $mapData = json_decode($row['mapData'], true);
    }

    return [
        'success' => true,
        'mapData' => $mapData
    ];
}

/**
 * Save map data for entity
 */
function saveMapData($table, $idField, $id, $mapData) {
    global $pdo;

    // Convert map data to JSON
    $mapDataJson = json_encode($mapData, JSON_UNESCAPED_UNICODE);

    // Update record
    $sql = "UPDATE {$table} SET mapData = :mapData, updateDate = NOW() WHERE {$idField} = :id";
    $stmt = $pdo->prepare($sql);
    $result = $stmt->execute([
        'mapData' => $mapDataJson,
        'id' => $id
    ]);

    if (!$result) {
        throw new Exception('שגיאה בשמירת נתוני מפה');
    }

    if ($stmt->rowCount() === 0) {
        throw new Exception('רשומה לא נמצאה או לא עודכנה');
    }

    return [
        'success' => true,
        'message' => 'נתוני המפה נשמרו בהצלחה'
    ];
}
