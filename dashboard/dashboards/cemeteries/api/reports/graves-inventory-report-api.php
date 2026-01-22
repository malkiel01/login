<?php
/*
 * File: api/reports/graves-inventory-report-api.php
 * Version: 1.1.0
 * Updated: 2025-01-21
 * Author: Malkiel
 * Description: API לדוח ניהול יתרות קברים פנויים לפי תאריך
 * Change Summary:
 * - v1.1.0: תיקון שיטת התחברות - זהה ל-cemeteries-api.php
 * - שימוש ב-getDBConnection() במקום getDatabaseConnection()
 */

// אימות והרשאות - חייב להיות מחובר!
require_once __DIR__ . '/../api-auth.php';

try {
    $pdo = getDBConnection();
} catch(PDOException $e) {
    die(json_encode(['success' => false, 'error' => 'Connection failed: ' . $e->getMessage()]));
}

// ========== פונקציות עזר ==========

/**
 * המרת תאריך לפורמט עברי קריא
 */
function formatHebrewDate($date) {
    if (empty($date)) return '';
    
    $timestamp = strtotime($date);
    if ($timestamp === false) return $date;
    
    $hebrewMonths = [
        1 => 'ינואר', 2 => 'פברואר', 3 => 'מרץ', 4 => 'אפריל',
        5 => 'מאי', 6 => 'יוני', 7 => 'יולי', 8 => 'אוגוסט',
        9 => 'ספטמבר', 10 => 'אוקטובר', 11 => 'נובמבר', 12 => 'דצמבר'
    ];
    
    $day = date('d', $timestamp);
    $month = (int)date('m', $timestamp);
    $year = date('Y', $timestamp);
    
    return "{$day} ב{$hebrewMonths[$month]} {$year}";
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
            'error' => $e->getMessage()
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
    $validated['cemeteryId'] = isset($params['cemeteryId']) && !empty($params['cemeteryId']) 
        ? $params['cemeteryId'] : null;
    
    // סינון לפי גוש (אופציונלי)
    $validated['blockId'] = isset($params['blockId']) && !empty($params['blockId']) 
        ? $params['blockId'] : null;
    
    // סינון לפי חלקה (אופציונלי)
    $validated['plotId'] = isset($params['plotId']) && !empty($params['plotId']) 
        ? $params['plotId'] : null;
    
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
        LEFT JOIN graves_view gv ON g.unicId = gv.unicId
        WHERE g.isActive = 1
        AND g.createDate < :startDate
    ";
    
    // הוספת תנאי סינון
    $sql .= addFilterConditions($params);
    
    // חישוב כמה מהם היו תפוסים לפני תאריך ההתחלה
    $sql .= "
        AND g.unicId NOT IN (
            SELECT graveId FROM purchases 
            WHERE graveId IS NOT NULL 
            AND createDate < :startDate2 
            AND isActive = 1
            
            UNION
            
            SELECT graveId FROM burials 
            WHERE graveId IS NOT NULL 
            AND createDate < :startDate3 
            AND isActive = 1
        )
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':startDate', $params['startDate']);
    $stmt->bindValue(':startDate2', $params['startDate']);
    $stmt->bindValue(':startDate3', $params['startDate']);
    
    if ($params['cemeteryId']) $stmt->bindValue(':cemeteryId', $params['cemeteryId']);
    if ($params['blockId']) $stmt->bindValue(':blockId', $params['blockId']);
    if ($params['plotId']) $stmt->bindValue(':plotId', $params['plotId']);
    
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    return [
        'total' => (int)($result['totalAvailable'] ?? 0),
        'byType' => [
            'exempt' => (int)($result['exemptCount'] ?? 0),
            'unusual' => (int)($result['unusualCount'] ?? 0),
            'close' => (int)($result['closeCount'] ?? 0)
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
        LEFT JOIN graves_view gv ON g.unicId = gv.unicId
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
        LEFT JOIN graves_view gv ON p.graveId = gv.unicId
        LEFT JOIN customers c ON p.clientId = c.unicId
        WHERE p.createDate >= :startDate
        AND p.createDate <= :endDate
        AND p.isActive = 1
        AND p.graveId IS NOT NULL
    ";
    
    $sql .= addFilterConditions($params);
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
        LEFT JOIN graves_view gv ON b.graveId = gv.unicId
        LEFT JOIN customers c ON b.clientId = c.unicId
        WHERE b.createDate >= :startDate
        AND b.createDate <= :endDate
        AND b.isActive = 1
        AND b.graveId IS NOT NULL
    ";
    
    $sql .= addFilterConditions($params);
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
        LEFT JOIN graves_view gv ON p.graveId = gv.unicId
        LEFT JOIN customers c ON p.clientId = c.unicId
        WHERE (
            (p.cancelDate >= :startDate AND p.cancelDate <= :endDate)
            OR (p.inactiveDate >= :startDate2 AND p.inactiveDate <= :endDate2)
        )
        AND p.isActive = 0
        AND p.graveId IS NOT NULL
    ";
    
    $sql .= addFilterConditions($params);
    $sql .= " ORDER BY COALESCE(p.cancelDate, p.inactiveDate) ASC";
    
    return executeMovementQueryExtended($pdo, $sql, $params);
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
        LEFT JOIN graves_view gv ON b.graveId = gv.unicId
        LEFT JOIN customers c ON b.clientId = c.unicId
        WHERE (
            (b.cancelDate >= :startDate AND b.cancelDate <= :endDate)
            OR (b.inactiveDate >= :startDate2 AND b.inactiveDate <= :endDate2)
        )
        AND b.isActive = 0
        AND b.graveId IS NOT NULL
    ";
    
    $sql .= addFilterConditions($params);
    $sql .= " ORDER BY COALESCE(b.cancelDate, b.inactiveDate) ASC";
    
    return executeMovementQueryExtended($pdo, $sql, $params);
}

/**
 * הוספת תנאי סינון לשאילתה
 */
function addFilterConditions($params, $tableAlias = 'gv') {
    $conditions = [];
    
    if (!empty($params['cemeteryId'])) {
        $conditions[] = "{$tableAlias}.cemeteryId = :cemeteryId";
    }
    if (!empty($params['blockId'])) {
        $conditions[] = "{$tableAlias}.blockId = :blockId";
    }
    if (!empty($params['plotId'])) {
        $conditions[] = "{$tableAlias}.plotId = :plotId";
    }
    
    return !empty($conditions) ? " AND " . implode(" AND ", $conditions) : "";
}

/**
 * ביצוע שאילתת תנועה רגילה
 */
function executeMovementQuery($pdo, $sql, $params) {
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':startDate', $params['startDate']);
    $stmt->bindValue(':endDate', $params['endDate']);
    
    if (!empty($params['cemeteryId'])) $stmt->bindValue(':cemeteryId', $params['cemeteryId']);
    if (!empty($params['blockId'])) $stmt->bindValue(':blockId', $params['blockId']);
    if (!empty($params['plotId'])) $stmt->bindValue(':plotId', $params['plotId']);
    
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * ביצוע שאילתת תנועה מורחבת (לביטולים)
 */
function executeMovementQueryExtended($pdo, $sql, $params) {
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':startDate', $params['startDate']);
    $stmt->bindValue(':endDate', $params['endDate']);
    $stmt->bindValue(':startDate2', $params['startDate']);
    $stmt->bindValue(':endDate2', $params['endDate']);
    
    if (!empty($params['cemeteryId'])) $stmt->bindValue(':cemeteryId', $params['cemeteryId']);
    if (!empty($params['blockId'])) $stmt->bindValue(':blockId', $params['blockId']);
    if (!empty($params['plotId'])) $stmt->bindValue(':plotId', $params['plotId']);
    
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * סיכום תנועות לפי חלקה (דוח מצומצם)
 */
function summarizeByPlot($movements) {
    $summary = [];
    
    foreach ($movements as $movement) {
        $key = ($movement['cemeteryNameHe'] ?? '') . '_' . 
               ($movement['blockNameHe'] ?? '') . '_' . 
               ($movement['plotNameHe'] ?? 'לא_מוגדר');
        
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
        
        $type = $movement['movementType'] ?? '';
        $qty = (int)($movement['quantity'] ?? 0);
        
        if (isset($summary[$key]['movements'][$type])) {
            $summary[$key]['movements'][$type]++;
        }
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
        // בדוח מצומצם, השתמש ב-netChange
        if (isset($movement['netChange'])) {
            $netChange['total'] += (int)$movement['netChange'];
        } else {
            // בדוח מפורט
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
    // קבלת פרמטרים
    $input = file_get_contents('php://input');
    $params = json_decode($input, true);
    
    // אם אין JSON, ננסה GET/POST רגיל
    if ($params === null) {
        $params = array_merge($_GET, $_POST);
    }
    
    if (empty($params)) {
        $params = [];
    }
    
    // הפקת הדוח
    $report = generateInventoryReport($pdo, $params);
    
    // החזרת התוצאה
    echo json_encode($report, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
?>