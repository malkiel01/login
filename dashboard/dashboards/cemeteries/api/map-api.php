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

// אימות והרשאות - חייב להיות מחובר!
require_once __DIR__ . '/api-auth.php';

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
    // GET - Load map data OR list entities
    // =====================================================
    if ($method === 'GET') {
        requireViewPermission('map');
        $action = $_GET['action'] ?? 'loadMap';

        // -------------------------------------------------
        // List entities by type
        // -------------------------------------------------
        if ($action === 'listEntities') {
            $entityType = $_GET['type'] ?? null;

            if (!$entityType) {
                throw new Exception('Missing entity type');
            }

            $table = getTableName($entityType);
            if (!$table) {
                throw new Exception('Invalid entity type');
            }

            $nameField = getNameField($entityType);

            // Get all active entities
            $stmt = $pdo->prepare("SELECT unicId, {$nameField} as name FROM {$table} WHERE isActive = 1 ORDER BY {$nameField}");
            $stmt->execute();
            $entities = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode([
                'success' => true,
                'entities' => $entities,
                'count' => count($entities)
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        // -------------------------------------------------
        // Load map data (default action)
        // -------------------------------------------------
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

        // Parse JSON fields - check both old (mapData) and new field names
        $debugInfo = [];
        $debugInfo['hasMapData'] = !empty($entity['mapData']);

        if (!empty($entity['mapData'])) {
            $mapData = json_decode($entity['mapData'], true);
            $debugInfo['jsonDecodeSuccess'] = ($mapData !== null);
            $debugInfo['hasCanvasJSON'] = isset($mapData['canvasJSON']);
            $debugInfo['hasObjects'] = isset($mapData['canvasJSON']['objects']);

            if ($mapData) {
                // Old format: data stored as canvasJSON with objects
                if (isset($mapData['canvasJSON']['objects'])) {
                    $debugInfo['objectsCount'] = count($mapData['canvasJSON']['objects']);
                    $canvasObjects = $mapData['canvasJSON']['objects'];

                    $debugInfo['objectTypes'] = [];
                    foreach ($canvasObjects as $idx => $obj) {
                        $objectType = $obj['objectType'] ?? '';
                        $fabricType = $obj['type'] ?? '';
                        $debugInfo['objectTypes'][] = "[$idx] objectType=$objectType, type=$fabricType";

                        // Extract boundary polygon
                        if ($objectType === 'boundaryOutline' || $objectType === 'polygon' || $fabricType === 'polygon') {
                            $entity['mapPolygon'] = [
                                'points' => $obj['points'] ?? [],
                                'style' => [
                                    'fillColor' => $obj['fill'] ?? '#1976D2',
                                    'fillOpacity' => $obj['opacity'] ?? 0.3,
                                    'strokeColor' => $obj['stroke'] ?? '#1976D2',
                                    'strokeWidth' => $obj['strokeWidth'] ?? 2
                                ]
                            ];
                        }

                        // Extract background image (check for image type or backgroundLayer)
                        if ($fabricType === 'image' || $objectType === 'backgroundImage' || $objectType === 'backgroundLayer') {
                            $entity['mapBackgroundImage'] = [
                                'path' => $obj['src'] ?? '',
                                'width' => $obj['width'] ?? 800,
                                'height' => $obj['height'] ?? 600,
                                'scaleX' => $obj['scaleX'] ?? 1,
                                'scaleY' => $obj['scaleY'] ?? 1,
                                'left' => $obj['left'] ?? 0,
                                'top' => $obj['top'] ?? 0
                            ];
                        }
                    }

                    // Also keep the raw canvas data
                    $entity['mapCanvasData'] = $mapData['canvasJSON'];
                } else {
                    // New format: separate fields
                    $entity['mapPolygon'] = $mapData['boundary'] ?? $mapData['polygon'] ?? $mapData['mapPolygon'] ?? null;
                    $entity['mapBackgroundImage'] = $mapData['background'] ?? $mapData['backgroundImage'] ?? $mapData['mapBackgroundImage'] ?? null;
                    $entity['mapSettings'] = $mapData['settings'] ?? $mapData['mapSettings'] ?? null;
                    $entity['mapCanvasData'] = $mapData['canvas'] ?? $mapData['canvasData'] ?? null;
                }
            }
        }
        // Also check direct fields if they exist
        if (isset($entity['mapPolygon']) && is_string($entity['mapPolygon'])) {
            $entity['mapPolygon'] = json_decode($entity['mapPolygon'], true);
        }
        if (isset($entity['mapBackgroundImage']) && is_string($entity['mapBackgroundImage'])) {
            $entity['mapBackgroundImage'] = json_decode($entity['mapBackgroundImage'], true);
        }
        if (isset($entity['mapSettings']) && is_string($entity['mapSettings'])) {
            $entity['mapSettings'] = json_decode($entity['mapSettings'], true);
        }

        $response = [
            'success' => true,
            'entity' => $entity,
            '_debug' => $debugInfo ?? []
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
    // POST - Handle file uploads first (before JSON parsing)
    // =====================================================
    if ($method === 'POST' && isset($_FILES['backgroundImage'])) {
        requireEditPermission('map');
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
        $imageInfo = getimagesize($filepath);
        $width = $imageInfo[0] ?? 800;
        $height = $imageInfo[1] ?? 600;

        // Build relative path for frontend
        $relativePath = '../uploads/maps/' . $filename;

        // Save to database
        $table = getTableName($entityType);
        if (!$table) {
            unlink($filepath);
            throw new Exception('Invalid entity type');
        }

        $backgroundData = [
            'path' => $relativePath,
            'width' => $width,
            'height' => $height,
            'offsetX' => 0,
            'offsetY' => 0,
            'scale' => 1
        ];

        $stmt = $pdo->prepare("UPDATE {$table} SET mapBackgroundImage = :bg, updateDate = NOW() WHERE unicId = :id");
        $result = $stmt->execute([
            ':bg' => json_encode($backgroundData, JSON_UNESCAPED_UNICODE),
            ':id' => $entityId
        ]);

        echo json_encode([
            'success' => true,
            'path' => $relativePath,
            'width' => $width,
            'height' => $height,
            'message' => 'Background uploaded successfully'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // =====================================================
    // POST - Save/Update operations (JSON body)
    // =====================================================
    if ($method === 'POST') {
        requireEditPermission('map');
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

    throw new Exception('Invalid request method');

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
