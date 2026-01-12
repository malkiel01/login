<?php
/**
 * Entity Selector for Map v2
 * בורר ישויות לעורך המפות
 */

require_once dirname(__DIR__) . '/config.php';

// טען נתונים לפי סוג ישות
$entityType = $_GET['entityType'] ?? '';
$parentId = $_GET['parentId'] ?? '';

$entities = [];

try {
    switch ($entityType) {
        case 'cemetery':
            $stmt = $pdo->query("SELECT unicId, cemeteryNameHe as name FROM cemeteries WHERE isActive = 1 ORDER BY cemeteryNameHe");
            $entities = $stmt->fetchAll(PDO::FETCH_ASSOC);
            break;

        case 'block':
            if ($parentId) {
                $stmt = $pdo->prepare("SELECT unicId, blockNameHe as name FROM blocks WHERE isActive = 1 AND cemeteryId = ? ORDER BY blockNameHe");
                $stmt->execute([$parentId]);
            } else {
                $stmt = $pdo->query("SELECT b.unicId, CONCAT(b.blockNameHe, ' - ', c.cemeteryNameHe) as name
                    FROM blocks b
                    LEFT JOIN cemeteries c ON b.cemeteryId = c.unicId
                    WHERE b.isActive = 1
                    ORDER BY c.cemeteryNameHe, b.blockNameHe");
            }
            $entities = $stmt->fetchAll(PDO::FETCH_ASSOC);
            break;

        case 'plot':
            if ($parentId) {
                $stmt = $pdo->prepare("SELECT unicId, plotNameHe as name FROM plots WHERE isActive = 1 AND blockId = ? ORDER BY plotNameHe");
                $stmt->execute([$parentId]);
            } else {
                $stmt = $pdo->query("SELECT p.unicId, CONCAT(p.plotNameHe, ' - ', b.blockNameHe, ' - ', c.cemeteryNameHe) as name
                    FROM plots p
                    LEFT JOIN blocks b ON p.blockId = b.unicId
                    LEFT JOIN cemeteries c ON b.cemeteryId = c.unicId
                    WHERE p.isActive = 1
                    ORDER BY c.cemeteryNameHe, b.blockNameHe, p.plotNameHe");
            }
            $entities = $stmt->fetchAll(PDO::FETCH_ASSOC);
            break;

        case 'areaGrave':
            $stmt = $pdo->query("SELECT a.unicId, CONCAT(a.areaGraveNameHe, ' - ', p.plotNameHe, ' - ', b.blockNameHe) as name
                FROM areaGraves a
                LEFT JOIN `rows` r ON a.lineId = r.unicId
                LEFT JOIN plots p ON r.plotId = p.unicId
                LEFT JOIN blocks b ON p.blockId = b.unicId
                WHERE a.isActive = 1
                ORDER BY b.blockNameHe, p.plotNameHe, a.areaGraveNameHe
                LIMIT 100");
            $entities = $stmt->fetchAll(PDO::FETCH_ASSOC);
            break;
    }
} catch (Exception $e) {
    // Silent fail
}

header('Content-Type: application/json');
echo json_encode(['entities' => $entities]);
