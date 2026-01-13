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

        case 'getDescendants':
            // Get all descendants recursively (children, grandchildren, etc.)
            if (!isset($childTypeMap[$parentType])) {
                throw new Exception('סוג הורה לא חוקי או אין לו צאצאים: ' . $parentType);
            }
            $result = getDescendants($parentType, $parentId);
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

        case 'getAreaGravesWithDetails':
            // Get all areaGraves for a plot with their graves and customer info
            $plotId = $_GET['plotId'] ?? ($input['plotId'] ?? '');
            if (!$plotId) {
                throw new Exception('חסר מזהה חלקה');
            }
            $result = getAreaGravesWithDetails($plotId);
            echo json_encode($result);
            break;

        case 'saveAreaGravePosition':
            // Save rectangle position/size/angle for an areaGrave
            $areaGraveId = $input['areaGraveId'] ?? '';
            $position = $input['position'] ?? null;
            if (!$areaGraveId || !$position) {
                throw new Exception('חסרים נתונים');
            }
            $result = saveAreaGravePosition($areaGraveId, $position);
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

/**
 * Get all descendants recursively
 */
function getDescendants($parentType, $parentId) {
    global $pdo, $entityTables, $childTypeMap;

    $descendants = [];
    $levels = [];

    // Get direct children first
    $childrenResult = getChildren($parentType, $parentId);
    if (!$childrenResult['success']) {
        return $childrenResult;
    }

    $children = $childrenResult['children'];
    $childType = $childrenResult['childType'];

    // Add children to descendants with level info
    foreach ($children as $child) {
        $child['level'] = 1;
        $child['parentId'] = $parentId;
        $child['parentType'] = $parentType;
        $descendants[] = $child;
    }

    if (!isset($levels[$childType])) {
        $levels[$childType] = 1;
    }

    // Check if child type can have its own children (grandchildren)
    if (isset($childTypeMap[$childType])) {
        $grandchildType = $childTypeMap[$childType]['childType'];
        $grandchildForeignKey = $childTypeMap[$childType]['foreignKey'];
        $grandchildTableConfig = $entityTables[$grandchildType];

        // Fetch all grandchildren for all children that have polygon
        // (We fetch all grandchildren regardless of whether parent has polygon)
        $childIds = array_map(function($c) { return $c['id']; }, $children);

        if (!empty($childIds)) {
            try {
                $placeholders = implode(',', array_fill(0, count($childIds), '?'));
                $table = $grandchildTableConfig['table'];
                $idField = $grandchildTableConfig['idField'];
                $nameField = $grandchildTableConfig['nameField'];

                $sql = "SELECT {$idField} as id, {$nameField} as name, mapPolygon, {$grandchildForeignKey} as parentId
                        FROM {$table}
                        WHERE {$grandchildForeignKey} IN ({$placeholders})
                        AND isActive = 1
                        ORDER BY {$nameField}";

                $stmt = $pdo->prepare($sql);
                $stmt->execute($childIds);

                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    $descendants[] = [
                        'id' => $row['id'],
                        'name' => $row['name'] ?? $row['id'],
                        'type' => $grandchildType,
                        'hasPolygon' => !empty($row['mapPolygon']),
                        'polygon' => $row['mapPolygon'] ? json_decode($row['mapPolygon'], true) : null,
                        'level' => 2,
                        'parentId' => $row['parentId'],
                        'parentType' => $childType
                    ];
                }

                if (!isset($levels[$grandchildType])) {
                    $levels[$grandchildType] = 2;
                }

                // Check for great-grandchildren (level 3)
                if (isset($childTypeMap[$grandchildType])) {
                    $greatGrandchildType = $childTypeMap[$grandchildType]['childType'];
                    $greatGrandchildForeignKey = $childTypeMap[$grandchildType]['foreignKey'];
                    $greatGrandchildTableConfig = $entityTables[$greatGrandchildType];

                    // Get IDs of all grandchildren
                    $grandchildIds = array_map(function($d) use ($grandchildType) {
                        return $d['type'] === $grandchildType ? $d['id'] : null;
                    }, $descendants);
                    $grandchildIds = array_filter($grandchildIds);

                    if (!empty($grandchildIds)) {
                        $placeholders = implode(',', array_fill(0, count($grandchildIds), '?'));
                        $table = $greatGrandchildTableConfig['table'];
                        $idField = $greatGrandchildTableConfig['idField'];
                        $nameField = $greatGrandchildTableConfig['nameField'];

                        $sql = "SELECT {$idField} as id, {$nameField} as name, mapPolygon, {$greatGrandchildForeignKey} as parentId
                                FROM {$table}
                                WHERE {$greatGrandchildForeignKey} IN ({$placeholders})
                                AND isActive = 1
                                ORDER BY {$nameField}";

                        $stmt = $pdo->prepare($sql);
                        $stmt->execute(array_values($grandchildIds));

                        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                            $descendants[] = [
                                'id' => $row['id'],
                                'name' => $row['name'] ?? $row['id'],
                                'type' => $greatGrandchildType,
                                'hasPolygon' => !empty($row['mapPolygon']),
                                'polygon' => $row['mapPolygon'] ? json_decode($row['mapPolygon'], true) : null,
                                'level' => 3,
                                'parentId' => $row['parentId'],
                                'parentType' => $grandchildType
                            ];
                        }

                        if (!isset($levels[$greatGrandchildType])) {
                            $levels[$greatGrandchildType] = 3;
                        }
                    }
                }
            } catch (PDOException $e) {
                // If mapPolygon column doesn't exist, continue without polygon data
                if (strpos($e->getMessage(), 'Unknown column') === false) {
                    throw $e;
                }
            }
        }
    }

    return [
        'success' => true,
        'descendants' => $descendants,
        'levels' => $levels,
        'count' => count($descendants)
    ];
}

/**
 * Get all areaGraves for a plot with their graves and customer info
 */
function getAreaGravesWithDetails($plotId) {
    global $pdo;

    // Status mapping
    $statusLabels = [
        1 => 'פנוי',
        2 => 'נרכש',
        3 => 'שמור',
        4 => 'קבור'
    ];

    try {
        // First get areaGraves for this plot with row info
        // Note: areaGraves are linked via lineId to rows, but we want plot's areaGraves
        // The hierarchy is: plot -> rows -> areaGraves
        // But we skip rows for map purposes, so we need to find areaGraves via plotId

        // areaGraves are linked via lineId to rows, and rows are linked via plotId to plots
        // So we need to go: plot -> rows -> areaGraves
        $sql = "SELECT
                    ag.unicId as id,
                    ag.areaGraveNameHe as name,
                    ag.lineId,
                    ag.graveType,
                    ag.mapPolygon,
                    r.lineNameHe as rowName
                FROM areaGraves ag
                INNER JOIN `rows` r ON ag.lineId = r.unicId
                WHERE r.plotId = :plotId
                AND ag.isActive = 1
                AND r.isActive = 1
                ORDER BY r.lineNameHe, ag.areaGraveNameHe";

        $stmt = $pdo->prepare($sql);
        $stmt->execute(['plotId' => $plotId]);

        $areaGraves = [];
        $areaGraveIds = [];

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $position = null;
            if ($row['mapPolygon']) {
                $position = json_decode($row['mapPolygon'], true);
            }

            $areaGraves[$row['id']] = [
                'id' => $row['id'],
                'name' => $row['name'] ?? $row['id'],
                'rowName' => $row['rowName'] ?? '',
                'graveType' => $row['graveType'],
                'position' => $position,
                'graves' => []
            ];
            $areaGraveIds[] = $row['id'];
        }

        // If no areaGraves found, return empty
        if (empty($areaGraveIds)) {
            return [
                'success' => true,
                'areaGraves' => [],
                'count' => 0
            ];
        }

        // Now get all graves for these areaGraves
        $placeholders = implode(',', array_fill(0, count($areaGraveIds), '?'));

        $gravesSql = "SELECT
                        g.unicId as id,
                        g.graveNameHe as name,
                        g.areaGraveId,
                        g.graveStatus,
                        g.graveLocation,
                        g.isSmallGrave
                      FROM graves g
                      WHERE g.areaGraveId IN ({$placeholders})
                      AND g.isActive = 1
                      ORDER BY g.graveNameHe";

        $gravesStmt = $pdo->prepare($gravesSql);
        $gravesStmt->execute($areaGraveIds);

        $graveIds = [];
        $gravesData = [];

        while ($grave = $gravesStmt->fetch(PDO::FETCH_ASSOC)) {
            $graveId = $grave['id'];
            $areaGraveId = $grave['areaGraveId'];
            $graveIds[] = $graveId;

            $gravesData[$graveId] = [
                'id' => $graveId,
                'name' => $grave['name'] ?? $graveId,
                'areaGraveId' => $areaGraveId,
                'status' => (int)$grave['graveStatus'],
                'statusLabel' => $statusLabels[$grave['graveStatus']] ?? 'לא ידוע',
                'location' => $grave['graveLocation'],
                'isSmall' => (bool)$grave['isSmallGrave'],
                'customer' => null
            ];
        }

        // Get customer info from purchases and burials
        if (!empty($graveIds)) {
            $gravePlaceholders = implode(',', array_fill(0, count($graveIds), '?'));

            // Get purchases (status 2 = purchased)
            $purchasesSql = "SELECT
                                p.graveId,
                                c.firstName,
                                c.lastName,
                                c.numId,
                                'רוכש' as customerType
                             FROM purchases p
                             LEFT JOIN customers c ON p.clientId = c.unicId
                             WHERE p.graveId IN ({$gravePlaceholders})
                             AND p.isActive = 1";

            $purchasesStmt = $pdo->prepare($purchasesSql);
            $purchasesStmt->execute($graveIds);

            while ($purchase = $purchasesStmt->fetch(PDO::FETCH_ASSOC)) {
                $graveId = $purchase['graveId'];
                if (isset($gravesData[$graveId])) {
                    $gravesData[$graveId]['customer'] = [
                        'name' => trim($purchase['firstName'] . ' ' . $purchase['lastName']),
                        'numId' => $purchase['numId'],
                        'type' => $purchase['customerType']
                    ];
                }
            }

            // Get burials (status 4 = buried) - override purchase info
            $burialsSql = "SELECT
                              b.graveId,
                              c.firstName,
                              c.lastName,
                              c.numId,
                              b.dateDeath,
                              b.dateBurial,
                              'קבור' as customerType
                           FROM burials b
                           LEFT JOIN customers c ON b.clientId = c.unicId
                           WHERE b.graveId IN ({$gravePlaceholders})
                           AND b.isActive = 1";

            $burialsStmt = $pdo->prepare($burialsSql);
            $burialsStmt->execute($graveIds);

            while ($burial = $burialsStmt->fetch(PDO::FETCH_ASSOC)) {
                $graveId = $burial['graveId'];
                if (isset($gravesData[$graveId])) {
                    $gravesData[$graveId]['customer'] = [
                        'name' => trim($burial['firstName'] . ' ' . $burial['lastName']),
                        'numId' => $burial['numId'],
                        'type' => $burial['customerType'],
                        'dateDeath' => $burial['dateDeath'],
                        'dateBurial' => $burial['dateBurial']
                    ];
                }
            }
        }

        // Assign graves to their areaGraves
        foreach ($gravesData as $graveId => $grave) {
            $areaGraveId = $grave['areaGraveId'];
            if (isset($areaGraves[$areaGraveId])) {
                unset($grave['areaGraveId']); // Remove redundant field
                $areaGraves[$areaGraveId]['graves'][] = $grave;
            }
        }

        // Convert to indexed array
        $result = array_values($areaGraves);

        return [
            'success' => true,
            'areaGraves' => $result,
            'count' => count($result)
        ];

    } catch (PDOException $e) {
        // Handle missing columns gracefully
        if (strpos($e->getMessage(), 'Unknown column') !== false) {
            // Try simpler query without problematic columns
            return [
                'success' => true,
                'areaGraves' => [],
                'count' => 0,
                'warning' => 'חלק מהעמודות חסרות - ' . $e->getMessage()
            ];
        }
        throw $e;
    }
}

/**
 * Save position/size/angle for an areaGrave rectangle
 */
function saveAreaGravePosition($areaGraveId, $position) {
    global $pdo;

    // Validate position structure
    $validPosition = [
        'type' => 'rectangle',
        'x' => isset($position['x']) ? (float)$position['x'] : 0,
        'y' => isset($position['y']) ? (float)$position['y'] : 0,
        'width' => isset($position['width']) ? (float)$position['width'] : 9,
        'height' => isset($position['height']) ? (float)$position['height'] : 12,
        'angle' => isset($position['angle']) ? (float)$position['angle'] : 0
    ];

    $positionJson = json_encode($validPosition, JSON_UNESCAPED_UNICODE);

    // Check if record exists
    $checkSql = "SELECT unicId FROM areaGraves WHERE unicId = :id LIMIT 1";
    $checkStmt = $pdo->prepare($checkSql);
    $checkStmt->execute(['id' => $areaGraveId]);

    if (!$checkStmt->fetch()) {
        throw new Exception('אחוזת קבר לא נמצאה: ' . $areaGraveId);
    }

    // Update mapPolygon with position data
    try {
        $sql = "UPDATE areaGraves SET mapPolygon = :position WHERE unicId = :id";
        $stmt = $pdo->prepare($sql);
        $result = $stmt->execute([
            'position' => $positionJson,
            'id' => $areaGraveId
        ]);
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'mapPolygon') !== false || strpos($e->getMessage(), 'Unknown column') !== false) {
            throw new Exception('עמודת mapPolygon לא קיימת - יש להריץ את סקריפט ההעברה');
        }
        throw $e;
    }

    if (!$result) {
        throw new Exception('שגיאה בשמירת מיקום');
    }

    return [
        'success' => true,
        'message' => 'המיקום נשמר בהצלחה',
        'position' => $validPosition
    ];
}
