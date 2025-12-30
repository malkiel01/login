<?php
/**
 * Map API - ממשק לשמירה וטעינת נתוני מפה
 *
 * פעולות נתמכות:
 * - GET: טעינת נתוני מפה
 * - POST saveMap: שמירת פוליגונים ומפה
 * - POST savePolygon: שמירת פוליגון בודד
 * - POST deletePolygon: מחיקת פוליגון
 * - POST uploadBackground: העלאת תמונת רקע
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once dirname(__DIR__) . '/../../config.php';

// Get database connection
$pdo = getDBConnection();

/**
 * Get table name from entity type
 */
function getTableName($entityType) {
    $tables = [
        'cemetery' => 'cemeteries',
        'block' => 'blocks',
        'plot' => 'plots',
        'areaGrave' => 'areaGraves',
        'row' => 'rows'
    ];
    return $tables[$entityType] ?? null;
}

/**
 * Get parent field name for entity type
 */
function getParentField($entityType) {
    $fields = [
        'block' => 'cemeteryId',
        'plot' => 'blockId',
        'row' => 'plotId',
        'areaGrave' => 'lineId'
    ];
    return $fields[$entityType] ?? null;
}

/**
 * Get name field for entity type
 */
function getNameField($entityType) {
    $fields = [
        'cemetery' => 'cemeteryNameHe',
        'block' => 'blockNameHe',
        'plot' => 'plotNameHe',
        'row' => 'lineNameHe',
        'areaGrave' => 'areaGraveNameHe'
    ];
    return $fields[$entityType] ?? 'name';
}

try {
    $method = $_SERVER['REQUEST_METHOD'];

    // =====================================================
    // GET - Load map data
    // =====================================================
    if ($method === 'GET') {
        $entityType = $_GET['type'] ?? 'cemetery';
        $entityId = $_GET['id'] ?? null;
        $includeChildren = isset($_GET['children']);

        if (!$entityId) {
            throw new Exception('Missing entity ID');
        }

        $table = getTableName($entityType);
        if (!$table) {
            throw new Exception('Invalid entity type');
        }

        // Get main entity
        $stmt = $pdo->prepare("SELECT * FROM {$table} WHERE unicId = :id AND isActive = 1");
        $stmt->execute([':id' => $entityId]);
        $entity = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$entity) {
            throw new Exception('Entity not found');
        }

        // Parse JSON fields
        if (isset($entity['mapPolygon'])) {
            $entity['mapPolygon'] = json_decode($entity['mapPolygon'], true);
        }
        if (isset($entity['mapBackgroundImage'])) {
            $entity['mapBackgroundImage'] = json_decode($entity['mapBackgroundImage'], true);
        }
        if (isset($entity['mapSettings'])) {
            $entity['mapSettings'] = json_decode($entity['mapSettings'], true);
        }

        $response = [
            'success' => true,
            'entity' => $entity
        ];

        // Include children if requested
        if ($includeChildren) {
            $childTypeMap = [
                'cemetery' => 'block',
                'block' => 'plot',
                'plot' => 'row',
                'row' => 'areaGrave'
            ];

            $childType = $childTypeMap[$entityType] ?? null;
            if ($childType) {
                $childTable = getTableName($childType);
                $parentField = getParentField($childType);

                if ($childTable && $parentField) {
                    $stmt = $pdo->prepare("SELECT * FROM {$childTable} WHERE {$parentField} = :parentId AND isActive = 1");
                    $stmt->execute([':parentId' => $entityId]);
                    $children = $stmt->fetchAll(PDO::FETCH_ASSOC);

                    // Parse mapPolygon for each child
                    foreach ($children as &$child) {
                        if (isset($child['mapPolygon'])) {
                            $child['mapPolygon'] = json_decode($child['mapPolygon'], true);
                        }
                    }

                    $response['children'] = $children;
                    $response['childType'] = $childType;
                }
            }
        }

        echo json_encode($response, JSON_UNESCAPED_UNICODE);
        exit;
    }

    // =====================================================
    // POST - Save/Update operations
    // =====================================================
    if ($method === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        $action = $input['action'] ?? '';

        switch ($action) {
            // -------------------------------------------------
            // Save entire map (multiple polygons)
            // -------------------------------------------------
            case 'saveMap':
                $entityType = $input['entityType'] ?? null;
                $entityId = $input['entityId'] ?? null;
                $polygons = $input['polygons'] ?? [];
                $backgroundImage = $input['backgroundImage'] ?? null;
                $mapSettings = $input['mapSettings'] ?? null;

                if (!$entityType || !$entityId) {
                    throw new Exception('Missing required parameters');
                }

                $mainTable = getTableName($entityType);
                if (!$mainTable) {
                    throw new Exception('Invalid entity type');
                }

                $pdo->beginTransaction();

                try {
                    // Update main entity's background and settings
                    if ($backgroundImage !== null || $mapSettings !== null) {
                        $updates = [];
                        $params = [':id' => $entityId];

                        if ($backgroundImage !== null) {
                            $updates[] = 'mapBackgroundImage = :bg';
                            $params[':bg'] = json_encode($backgroundImage, JSON_UNESCAPED_UNICODE);
                        }

                        if ($mapSettings !== null) {
                            $updates[] = 'mapSettings = :settings';
                            $params[':settings'] = json_encode($mapSettings, JSON_UNESCAPED_UNICODE);
                        }

                        if (!empty($updates)) {
                            $sql = "UPDATE {$mainTable} SET " . implode(', ', $updates) . " WHERE unicId = :id";
                            $stmt = $pdo->prepare($sql);
                            $stmt->execute($params);
                        }
                    }

                    // Update each polygon
                    foreach ($polygons as $polygon) {
                        $polygonType = $polygon['type'] ?? null;
                        $polygonId = $polygon['unicId'] ?? null;
                        $mapPolygon = $polygon['mapPolygon'] ?? null;

                        if (!$polygonType || !$polygonId) {
                            continue;
                        }

                        $polygonTable = getTableName($polygonType);
                        if (!$polygonTable) {
                            continue;
                        }

                        $stmt = $pdo->prepare("UPDATE {$polygonTable} SET mapPolygon = :polygon WHERE unicId = :id");
                        $stmt->execute([
                            ':polygon' => $mapPolygon ? json_encode($mapPolygon, JSON_UNESCAPED_UNICODE) : null,
                            ':id' => $polygonId
                        ]);
                    }

                    $pdo->commit();

                    echo json_encode([
                        'success' => true,
                        'message' => 'Map saved successfully',
                        'savedCount' => count($polygons)
                    ], JSON_UNESCAPED_UNICODE);

                } catch (Exception $e) {
                    $pdo->rollBack();
                    throw $e;
                }
                break;

            // -------------------------------------------------
            // Save single polygon
            // -------------------------------------------------
            case 'savePolygon':
                $entityType = $input['entityType'] ?? null;
                $entityId = $input['entityId'] ?? null;
                $mapPolygon = $input['mapPolygon'] ?? null;

                if (!$entityType || !$entityId) {
                    throw new Exception('Missing required parameters');
                }

                $table = getTableName($entityType);
                if (!$table) {
                    throw new Exception('Invalid entity type');
                }

                $stmt = $pdo->prepare("UPDATE {$table} SET mapPolygon = :polygon, updateDate = NOW() WHERE unicId = :id");
                $result = $stmt->execute([
                    ':polygon' => $mapPolygon ? json_encode($mapPolygon, JSON_UNESCAPED_UNICODE) : null,
                    ':id' => $entityId
                ]);

                if (!$result) {
                    throw new Exception('Failed to save polygon');
                }

                echo json_encode([
                    'success' => true,
                    'message' => 'Polygon saved successfully'
                ], JSON_UNESCAPED_UNICODE);
                break;

            // -------------------------------------------------
            // Delete polygon
            // -------------------------------------------------
            case 'deletePolygon':
                $entityType = $input['entityType'] ?? null;
                $entityId = $input['entityId'] ?? null;

                if (!$entityType || !$entityId) {
                    throw new Exception('Missing required parameters');
                }

                $table = getTableName($entityType);
                if (!$table) {
                    throw new Exception('Invalid entity type');
                }

                $stmt = $pdo->prepare("UPDATE {$table} SET mapPolygon = NULL, updateDate = NOW() WHERE unicId = :id");
                $result = $stmt->execute([':id' => $entityId]);

                if (!$result) {
                    throw new Exception('Failed to delete polygon');
                }

                echo json_encode([
                    'success' => true,
                    'message' => 'Polygon deleted successfully'
                ], JSON_UNESCAPED_UNICODE);
                break;

            // -------------------------------------------------
            // Save background image
            // -------------------------------------------------
            case 'saveBackground':
                $entityType = $input['entityType'] ?? null;
                $entityId = $input['entityId'] ?? null;
                $backgroundImage = $input['backgroundImage'] ?? null;

                if (!$entityType || !$entityId) {
                    throw new Exception('Missing required parameters');
                }

                $table = getTableName($entityType);
                if (!$table) {
                    throw new Exception('Invalid entity type');
                }

                $stmt = $pdo->prepare("UPDATE {$table} SET mapBackgroundImage = :bg, updateDate = NOW() WHERE unicId = :id");
                $result = $stmt->execute([
                    ':bg' => $backgroundImage ? json_encode($backgroundImage, JSON_UNESCAPED_UNICODE) : null,
                    ':id' => $entityId
                ]);

                echo json_encode([
                    'success' => true,
                    'message' => 'Background saved successfully'
                ], JSON_UNESCAPED_UNICODE);
                break;

            // -------------------------------------------------
            // Save map settings
            // -------------------------------------------------
            case 'saveSettings':
                $entityType = $input['entityType'] ?? null;
                $entityId = $input['entityId'] ?? null;
                $mapSettings = $input['mapSettings'] ?? null;

                if (!$entityType || !$entityId) {
                    throw new Exception('Missing required parameters');
                }

                $table = getTableName($entityType);
                if (!$table) {
                    throw new Exception('Invalid entity type');
                }

                $stmt = $pdo->prepare("UPDATE {$table} SET mapSettings = :settings, updateDate = NOW() WHERE unicId = :id");
                $result = $stmt->execute([
                    ':settings' => $mapSettings ? json_encode($mapSettings, JSON_UNESCAPED_UNICODE) : null,
                    ':id' => $entityId
                ]);

                echo json_encode([
                    'success' => true,
                    'message' => 'Settings saved successfully'
                ], JSON_UNESCAPED_UNICODE);
                break;

            default:
                throw new Exception('Invalid action');
        }
        exit;
    }

    // =====================================================
    // Handle file upload for background image
    // =====================================================
    if ($method === 'POST' && isset($_FILES['backgroundImage'])) {
        $entityType = $_POST['entityType'] ?? null;
        $entityId = $_POST['entityId'] ?? null;

        if (!$entityType || !$entityId) {
            throw new Exception('Missing required parameters');
        }

        $file = $_FILES['backgroundImage'];

        // Validate file
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        if (!in_array($file['type'], $allowedTypes)) {
            throw new Exception('Invalid file type. Allowed: JPG, PNG, GIF, WEBP');
        }

        $maxSize = 10 * 1024 * 1024; // 10MB
        if ($file['size'] > $maxSize) {
            throw new Exception('File too large. Maximum size: 10MB');
        }

        // Create upload directory
        $uploadDir = dirname(__DIR__) . '/uploads/maps/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        // Generate unique filename
        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = $entityType . '_' . $entityId . '_' . time() . '.' . $extension;
        $filepath = $uploadDir . $filename;

        // Move uploaded file
        if (!move_uploaded_file($file['tmp_name'], $filepath)) {
            throw new Exception('Failed to upload file');
        }

        // Get image dimensions
        list($width, $height) = getimagesize($filepath);

        // Build response
        $imageData = [
            'path' => '/dashboard/dashboards/cemeteries/uploads/maps/' . $filename,
            'width' => $width,
            'height' => $height,
            'scale' => 1,
            'offsetX' => 0,
            'offsetY' => 0
        ];

        // Update database
        $table = getTableName($entityType);
        if ($table) {
            $stmt = $pdo->prepare("UPDATE {$table} SET mapBackgroundImage = :bg, updateDate = NOW() WHERE unicId = :id");
            $stmt->execute([
                ':bg' => json_encode($imageData, JSON_UNESCAPED_UNICODE),
                ':id' => $entityId
            ]);
        }

        echo json_encode([
            'success' => true,
            'message' => 'Background image uploaded successfully',
            'data' => $imageData
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    throw new Exception('Invalid request method');

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
