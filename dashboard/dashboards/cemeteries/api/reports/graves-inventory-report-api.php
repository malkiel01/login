<?php
/*
 * File: api/reports/graves-inventory-report-api.php
 * Version: 1.0.0
 * Updated: 2025-01-21
 * Author: Malkiel
 * Description: API לדוח ניהול יתרות קברים פנויים לפי תאריך
 * Change Summary:
 * - יצירה ראשונית של API הדוח
 * - תמיכה בדוח מצומצם ומורחב
 * - חישוב יתרות פתיחה וסגירה
 * - מעקב אחר כל תנועות המלאי
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type');

// חיבור לבסיס נתונים
require_once $_SERVER['DOCUMENT_ROOT'] . '/dashboard/dashboards/cemeteries/config.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/dashboard/dashboards/cemeteries/config/reports-config.php';

try {
    $pdo = getDBConnection();
} catch(PDOException $e) {
    die(json_encode(['success' => false, 'error' => 'Connection failed: ' . $e->getMessage()]));
}

/**
 * פונקציה ראשית להפקת הדוח
 */
function generateInventoryReport($pdo, $params) {
    try {
        // ולידציה של פרמטרים
        $validated = validateParams($params);
        
        // קבלת יתרת פתיחה
        $openingBalance = getOpeningBalance($pdo, $validated);
        
        // קבלת תנועות בתקופה
        $movements = getMovements($pdo, $validated);
        
        // חישוב יתרת סגירה
        $closingBalance = calculateClosingBalance($openingBalance, $movements);
        
        // בניית הדוח
        $report = [
            'success' => true,
            'reportType' => $validated['reportType'],
            'dateRange' => [
                'startDate' => $validated['startDate'],
                'endDate' => $validated['endDate'],
                'startDateFormatted' => formatHebrewDate($validated['startDate']),
                'endDateFormatted' => formatHebrewDate($validated['endDate'])
            ],
            'summary' => [
                'openingBalance' => $openingBalance,
                'totalMovements' => count($movements),
                'closingBalance' => $closingBalance
            ],
            'movements' => $movements,
            'generatedAt' => date('Y-m-d H:i:s')
        ];
        
        return $report;
        
    } catch (Exception $e) {
        return [
            'success' => false,
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ];
    }
}

/**
 * ולידציה של פרמטרים נכנסים
 */
function validateParams($params) {
    $validated = [];
    
    // תאריך התחלה - ברירת מחדל: שחר ההיסטוריה
    $validated['startDate'] = isset($params['startDate']) && !empty($params['startDate']) 
        ? $params['startDate'] 
        : '1900-01-01';
    
    // תאריך סיום - ברירת מחדל: היום
    $validated['endDate'] = isset($params['endDate']) && !empty($params['endDate']) 
        ? $params['endDate'] 
        : date('Y-m-d');
    
    // סוג דוח: 'summary' (מצומצם) או 'detailed' (מורחב)
    $validated['reportType'] = isset($params['reportType']) && $params['reportType'] === 'detailed' 
        ? 'detailed' 
        : 'summary';
    
    // סינון לפי בית עלמין (אופציונלי)
    $validated['cemeteryId'] = isset($params['cemeteryId']) ? $params['cemeteryId'] : null;
    
    // סינון לפי גוש (אופציונלי)
    $validated['blockId'] = isset($params['blockId']) ? $params['blockId'] : null;
    
    // סינון לפי חלקה (אופציונלי)
    $validated['plotId'] = isset($params['plotId']) ? $params['plotId'] : null;
    
    // ולידציה שתאריך סיום אחרי תאריך התחלה
    if (strtotime($validated['startDate']) > strtotime($validated['endDate'])) {
        throw new Exception('תאריך הסיום חייב להיות אחרי תאריך ההתחלה');
    }
    
    return $validated;
}

/**
 * חישוב יתרת פתיחה - כמה קברים היו פנויים לפני תאריך ההתחלה
 */
function getOpeningBalance($pdo, $params) {
    $sql = "
        SELECT 
            COUNT(*) as totalAvailable,
            SUM(CASE WHEN g.plotType = 1 THEN 1 ELSE 0 END) as exemptCount,
            SUM(CASE WHEN g.plotType = 2 THEN 1 ELSE 0 END) as unusualCount,
            SUM(CASE WHEN g.plotType = 3 THEN 1 ELSE 0 END) as closeCount
        FROM graves g
        INNER JOIN graves_view gv ON g.unicId = gv.unicId
        WHERE g.isActive = 1
        AND g.createDate < :startDate
    ";
    
    // הוספת תנאי סינון
    $conditions = [];
    if ($params['cemeteryId']) {
        $conditions[] = "gv.cemeteryId = :cemeteryId";
    }
    if ($params['blockId']) {
        $conditions[] = "gv.blockId = :blockId";
    }
    if ($params['plotId']) {
        $conditions[] = "gv.plotId = :plotId";
    }
    
    if (!empty($conditions)) {
        $sql .= " AND " . implode(" AND ", $conditions);
    }
    
    // חישוב כמה מהם היו תפוסים לפני תאריך ההתחלה
    $sql .= "
        AND g.unicId NOT IN (
            -- קברים שנרכשו לפני תאריך ההתחלה ועדיין פעילים
            SELECT graveId FROM purchases 
            WHERE graveId IS NOT NULL 
            AND createDate < :startDate 
            AND isActive = 1
            
            UNION
            
            -- קברים שנקברו לפני תאריך ההתחלה ועדיין פעילים
            SELECT graveId FROM burials 
            WHERE graveId IS NOT NULL 
            AND createDate < :startDate 
            AND isActive = 1
        )
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':startDate', $params['startDate']);
    
    if ($params['cemeteryId']) $stmt->bindValue(':cemeteryId', $params['cemeteryId']);
    if ($params['blockId']) $stmt->bindValue(':blockId', $params['blockId']);
    if ($params['plotId']) $stmt->bindValue(':plotId', $params['plotId']);
    
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    return [
        'total' => (int)$result['totalAvailable'],
        'byType' => [
            'exempt' => (int)$result['exemptCount'],
            'unusual' => (int)$result['unusualCount'],
            'close' => (int)$result['closeCount']
        ]
    ];
}

/**
 * קבלת כל התנועות בתקופה
 */
function getMovements($pdo, $params) {
    $movements = [];
    
    // 1. קברים חדשים שנוצרו
    $newGraves = getNewGraves($pdo, $params);
    $movements = array_merge($movements, $newGraves);
    
    // 2. רכישות (הפחתה מהמלאי)
    $purchases = getPurchases($pdo, $params);
    $movements = array_merge($movements, $purchases);
    
    // 3. קבורות (הפחתה מהמלאי)
    $burials = getBurials($pdo, $params);
    $movements = array_merge($movements, $burials);
    
    // 4. ביטולי רכישות (החזרה למלאי)
    $canceledPurchases = getCanceledPurchases($pdo, $params);
    $movements = array_merge($movements, $canceledPurchases);
    
    // 5. ביטולי קבורות (החזרה למלאי)
    $canceledBurials = getCanceledBurials($pdo, $params);
    $movements = array_merge($movements, $canceledBurials);
    
    // מיון לפי תאריך
    usort($movements, function($a, $b) {
        return strtotime($a['date']) - strtotime($b['date']);
    });
    
    // סיכום לפי חלקה אם דוח מצומצם
    if ($params['reportType'] === 'summary') {
        $movements = summarizeByPlot($movements);
    }
    
    return $movements;
}

/**
 * קברים חדשים שנוצרו בתקופה
 */
function getNewGraves($pdo, $params) {
    $sql = "
        SELECT 
            g.unicId as graveId,
            g.graveNameHe,
            g.plotType,
            g.createDate as date,
            gv.cemeteryNameHe,
            gv.blockNameHe,
            gv.plotNameHe,
            gv.lineNameHe,
            gv.areaGraveNameHe,
            'קבר_חדש' as movementType,
            '+1' as quantity
        FROM graves g
        INNER JOIN graves_view gv ON g.unicId = gv.unicId
        WHERE g.createDate >= :startDate
        AND g.createDate <= :endDate
        AND g.isActive = 1
    ";
    
    $sql .= addFilterConditions($params);
    $sql .= " ORDER BY g.createDate ASC";
    
    return executeMovementQuery($pdo, $sql, $params);
}

/**
 * רכישות בתקופה
 */
function getPurchases($pdo, $params) {
    $sql = "
        SELECT 
            p.graveId,
            gv.graveNameHe,
            gv.plotType,
            p.createDate as date,
            gv.cemeteryNameHe,
            gv.blockNameHe,
            gv.plotNameHe,
            gv.lineNameHe,
            gv.areaGraveNameHe,
            p.serialPurchaseId,
            c.fullNameHe as customerName,
            'רכישה' as movementType,
            '-1' as quantity
        FROM purchases p
        INNER JOIN graves_view gv ON p.graveId = gv.unicId
        LEFT JOIN customers c ON p.clientId = c.unicId
        WHERE p.createDate >= :startDate
        AND p.createDate <= :endDate
        AND p.isActive = 1
        AND p.graveId IS NOT NULL
    ";
    
    $sql .= addFilterConditions($params, 'gv');
    $sql .= " ORDER BY p.createDate ASC";
    
    return executeMovementQuery($pdo, $sql, $params);
}

/**
 * קבורות בתקופה
 */
function getBurials($pdo, $params) {
    $sql = "
        SELECT 
            b.graveId,
            gv.graveNameHe,
            gv.plotType,
            b.createDate as date,
            gv.cemeteryNameHe,
            gv.blockNameHe,
            gv.plotNameHe,
            gv.lineNameHe,
            gv.areaGraveNameHe,
            b.serialBurialId,
            c.fullNameHe as customerName,
            'קבורה' as movementType,
            '-1' as quantity
        FROM burials b
        INNER JOIN graves_view gv ON b.graveId = gv.unicId
        LEFT JOIN customers c ON b.clientId = c.unicId
        WHERE b.createDate >= :startDate
        AND b.createDate <= :endDate
        AND b.isActive = 1
        AND b.graveId IS NOT NULL
    ";
    
    $sql .= addFilterConditions($params, 'gv');
    $sql .= " ORDER BY b.createDate ASC";
    
    return executeMovementQuery($pdo, $sql, $params);
}

/**
 * ביטולי רכישות בתקופה
 */
function getCanceledPurchases($pdo, $params) {
    $sql = "
        SELECT 
            p.graveId,
            gv.graveNameHe,
            gv.plotType,
            COALESCE(p.cancelDate, p.inactiveDate) as date,
            gv.cemeteryNameHe,
            gv.blockNameHe,
            gv.plotNameHe,
            gv.lineNameHe,
            gv.areaGraveNameHe,
            p.serialPurchaseId,
            c.fullNameHe as customerName,
            'ביטול_רכישה' as movementType,
            '+1' as quantity
        FROM purchases p
        INNER JOIN graves_view gv ON p.graveId = gv.unicId
        LEFT JOIN customers c ON p.clientId = c.unicId
        WHERE (
            (p.cancelDate >= :startDate AND p.cancelDate <= :endDate)
            OR (p.inactiveDate >= :startDate AND p.inactiveDate <= :endDate)
        )
        AND p.isActive = 0
        AND p.graveId IS NOT NULL
    ";
    
    $sql .= addFilterConditions($params, 'gv');
    $sql .= " ORDER BY COALESCE(p.cancelDate, p.inactiveDate) ASC";
    
    return executeMovementQuery($pdo, $sql, $params);
}

/**
 * ביטולי קבורות בתקופה
 */
function getCanceledBurials($pdo, $params) {
    $sql = "
        SELECT 
            b.graveId,
            gv.graveNameHe,
            gv.plotType,
            COALESCE(b.cancelDate, b.inactiveDate) as date,
            gv.cemeteryNameHe,
            gv.blockNameHe,
            gv.plotNameHe,
            gv.lineNameHe,
            gv.areaGraveNameHe,
            b.serialBurialId,
            c.fullNameHe as customerName,
            'ביטול_קבורה' as movementType,
            '+1' as quantity
        FROM burials b
        INNER JOIN graves_view gv ON b.graveId = gv.unicId
        LEFT JOIN customers c ON b.clientId = c.unicId
        WHERE (
            (b.cancelDate >= :startDate AND b.cancelDate <= :endDate)
            OR (b.inactiveDate >= :startDate AND b.inactiveDate <= :endDate)
        )
        AND b.isActive = 0
        AND b.graveId IS NOT NULL
    ";
    
    $sql .= addFilterConditions($params, 'gv');
    $sql .= " ORDER BY COALESCE(b.cancelDate, b.inactiveDate) ASC";
    
    return executeMovementQuery($pdo, $sql, $params);
}

/**
 * הוספת תנאי סינון לשאילתה
 */
function addFilterConditions($params, $tableAlias = 'gv') {
    $conditions = [];
    
    if ($params['cemeteryId']) {
        $conditions[] = "{$tableAlias}.cemeteryId = :cemeteryId";
    }
    if ($params['blockId']) {
        $conditions[] = "{$tableAlias}.blockId = :blockId";
    }
    if ($params['plotId']) {
        $conditions[] = "{$tableAlias}.plotId = :plotId";
    }
    
    return !empty($conditions) ? " AND " . implode(" AND ", $conditions) : "";
}

/**
 * ביצוע שאילתת תנועה
 */
function executeMovementQuery($pdo, $sql, $params) {
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':startDate', $params['startDate']);
    $stmt->bindValue(':endDate', $params['endDate']);
    
    if ($params['cemeteryId']) $stmt->bindValue(':cemeteryId', $params['cemeteryId']);
    if ($params['blockId']) $stmt->bindValue(':blockId', $params['blockId']);
    if ($params['plotId']) $stmt->bindValue(':plotId', $params['plotId']);
    
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * סיכום תנועות לפי חלקה (דוח מצומצם)
 */
function summarizeByPlot($movements) {
    $summary = [];
    
    foreach ($movements as $movement) {
        $key = $movement['plotNameHe'] ?? 'לא_מוגדר';
        
        if (!isset($summary[$key])) {
            $summary[$key] = [
                'plotName' => $movement['plotNameHe'] ?? '',
                'blockName' => $movement['blockNameHe'] ?? '',
                'cemeteryName' => $movement['cemeteryNameHe'] ?? '',
                'movements' => [
                    'קבר_חדש' => 0,
                    'רכישה' => 0,
                    'קבורה' => 0,
                    'ביטול_רכישה' => 0,
                    'ביטול_קבורה' => 0
                ],
                'netChange' => 0
            ];
        }
        
        $type = $movement['movementType'];
        $qty = (int)$movement['quantity'];
        
        $summary[$key]['movements'][$type]++;
        $summary[$key]['netChange'] += $qty;
    }
    
    return array_values($summary);
}

/**
 * חישוב יתרת סגירה
 */
function calculateClosingBalance($openingBalance, $movements) {
    $netChange = [
        'total' => 0,
        'byType' => [
            'exempt' => 0,
            'unusual' => 0,
            'close' => 0
        ]
    ];
    
    foreach ($movements as $movement) {
        $qty = (int)($movement['quantity'] ?? 0);
        $plotType = (int)($movement['plotType'] ?? 0);
        
        $netChange['total'] += $qty;
        
        switch ($plotType) {
            case 1:
                $netChange['byType']['exempt'] += $qty;
                break;
            case 2:
                $netChange['byType']['unusual'] += $qty;
                break;
            case 3:
                $netChange['byType']['close'] += $qty;
                break;
        }
    }
    
    return [
        'total' => $openingBalance['total'] + $netChange['total'],
        'byType' => [
            'exempt' => $openingBalance['byType']['exempt'] + $netChange['byType']['exempt'],
            'unusual' => $openingBalance['byType']['unusual'] + $netChange['byType']['unusual'],
            'close' => $openingBalance['byType']['close'] + $netChange['byType']['close']
        ]
    ];
}

// ========== נקודת הכניסה הראשית ==========

try {
    // קבלת חיבור למסד הנתונים
    $pdo = getDatabaseConnection();
    
    // קבלת פרמטרים
    $input = file_get_contents('php://input');
    $params = json_decode($input, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('שגיאה בפענוח JSON: ' . json_last_error_msg());
    }
    
    // הפקת הדוח
    $report = generateInventoryReport($pdo, $params);
    
    // החזרת התוצאה
    echo json_encode($report, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}