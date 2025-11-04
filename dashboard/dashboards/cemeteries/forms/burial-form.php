<?php
// forms/burial-form.php
// טופס הוספה/עריכת קבורה

error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: text/html; charset=utf-8');

require_once __DIR__ . '/FormBuilder.php';
require_once __DIR__ . 
'/FormUtils.php';
require_once dirname(__DIR__) . '/config.php';


// === קבלת פרמטרים אחידה ===
$itemId = $_GET['itemId'] ?? $_GET['id'] ?? null;
$parentId = $_GET['parentId'] ?? $_GET['parent_id'] ?? null;
$formType = basename(__FILE__, '.php'); // מזהה אוטומטי של סוג הטופס


try {
    $conn = getDBConnection();

    // טען קבורה אם קיימת
    $burial = null;
    if ($itemId) {
        $stmt = $conn->prepare("SELECT * FROM burials WHERE unicId = ? AND isActive = 1");
        $stmt->execute([$itemId]);
        $burial = $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // טען לקוחות - רק לא בסטטוס נפטר (או הלקוח הנוכחי בעריכה)
    $customers = [];
    if ($burial && $burial['clientId']) {
        $customersStmt = $conn->prepare("
            SELECT unicId, CONCAT(lastName, ' ', firstName) as full_name, numId,
                CASE WHEN unicId = :currentClient THEN 1 ELSE 0 END as is_current
            FROM customers 
            WHERE (statusCustomer != 3 OR unicId = :currentClient2)
            AND isActive = 1 
            ORDER BY is_current DESC, lastName, firstName
        ");
        $customersStmt->execute([
            'currentClient' => $burial['clientId'],
            'currentClient2' => $burial['clientId']
        ]);
    } else {
        $customersStmt = $conn->prepare("
            SELECT unicId, CONCAT(lastName, ' ', firstName) as full_name, numId 
            FROM customers 
            WHERE statusCustomer != 3 
            AND isActive = 1 
            ORDER BY lastName, firstName
        ");
        $customersStmt->execute();
    }

    while ($row = $customersStmt->fetch(PDO::FETCH_ASSOC)) {
        $label = $row['full_name'];
        if ($row['numId']) {
            $label .= ' (' . $row['numId'] . ')';
        }
        $customers[$row['unicId']] = $label;
    }

    // טען רכישות (אופציונלי)
    $purchases = [];
    $purchasesStmt = $conn->prepare("
        SELECT p.unicId, p.serialPurchaseId, 
               CONCAT(c.lastName, ' ', c.firstName) as customer_name,
               g.graveNameHe
        FROM purchases p
        LEFT JOIN customers c ON p.clientId = c.unicId
        LEFT JOIN graves g ON p.graveId = g.unicId
        WHERE p.isActive = 1
        ORDER BY p.createDate DESC
    ");
    $purchasesStmt->execute();
    
    while ($row = $purchasesStmt->fetch(PDO::FETCH_ASSOC)) {
        $label = $row['serialPurchaseId'];
        if ($row['customer_name']) {
            $label .= ' - ' . $row['customer_name'];
        }
        if ($row['graveNameHe']) {
            $label .= ' (' . $row['graveNameHe'] . ')';
        }
        $purchases[$row['unicId']] = $label;
    }

    // הכן את נתוני ההיררכיה לבחירת קבר
    $hierarchyData = [];
    $cemeteries = [];

    // בתי עלמין
    if ($burial && $burial['graveId']) {
        $cemeteriesStmt = $conn->prepare("
            SELECT c.unicId, c.cemeteryNameHe as name,
            (EXISTS (
                SELECT 1 FROM graves g
                INNER JOIN areaGraves ag ON g.areaGraveId = ag.unicId
                INNER JOIN rows r ON ag.lineId = r.unicId
                INNER JOIN plots p ON r.plotId = p.unicId
                INNER JOIN blocks b ON p.blockId = b.unicId
                WHERE b.cemeteryId = c.unicId
                AND g.graveStatus IN (1, 2)
                AND g.isActive = 1
            ) OR EXISTS (
                SELECT 1 FROM graves g
                INNER JOIN areaGraves ag ON g.areaGraveId = ag.unicId
                INNER JOIN rows r ON ag.lineId = r.unicId
                INNER JOIN plots p ON r.plotId = p.unicId
                INNER JOIN blocks b ON p.blockId = b.unicId
                WHERE b.cemeteryId = c.unicId
                AND g.unicId = :currentGrave
            )) as has_available_graves
            FROM cemeteries c
            WHERE c.isActive = 1
            ORDER BY c.cemeteryNameHe
        ");
        $cemeteriesStmt->execute(['currentGrave' => $burial['graveId']]);
    } else {
        $cemeteriesStmt = $conn->prepare("
            SELECT c.unicId, c.cemeteryNameHe as name,
            EXISTS (
                SELECT 1 FROM graves g
                INNER JOIN areaGraves ag ON g.areaGraveId = ag.unicId
                INNER JOIN rows r ON ag.lineId = r.unicId
                INNER JOIN plots p ON r.plotId = p.unicId
                INNER JOIN blocks b ON p.blockId = b.unicId
                WHERE b.cemeteryId = c.unicId
                AND g.graveStatus IN (1, 2)
                AND g.isActive = 1
            ) as has_available_graves
            FROM cemeteries c
            WHERE c.isActive = 1
            ORDER BY c.cemeteryNameHe
        ");
        $cemeteriesStmt->execute();
    }
    $cemeteries = $cemeteriesStmt->fetchAll(PDO::FETCH_ASSOC);

    // טען את כל ההיררכיה
    // גושים
    $blocksStmt = $conn->prepare("
        SELECT b.*, c.id as cemetery_id 
        FROM blocks b 
        INNER JOIN cemeteries c ON b.cemeteryId = c.unicId 
        WHERE b.isActive = 1
        ORDER BY b.blockNameHe
    ");
    $blocksStmt->execute();
    $hierarchyData['blocks'] = $blocksStmt->fetchAll(PDO::FETCH_ASSOC);

    // חלקות
    $plotsStmt = $conn->prepare("
        SELECT p.*, b.cemeteryId as cemetery_id, p.plotNameHe as name 
        FROM plots p 
        INNER JOIN blocks b ON p.blockId = b.unicId 
        WHERE p.isActive = 1
        ORDER BY p.plotNameHe
    ");
    $plotsStmt->execute();
    $hierarchyData['plots'] = $plotsStmt->fetchAll(PDO::FETCH_ASSOC);

    // שורות
    $rowsStmt = $conn->prepare("
        SELECT r.*, r.plotId as plot_id, r.lineNameHe as name
        FROM rows r 
        WHERE r.isActive = 1
        ORDER BY r.lineNameHe
    ");
    $rowsStmt->execute();
    $hierarchyData['rows'] = $rowsStmt->fetchAll(PDO::FETCH_ASSOC);

    // אחוזות קבר
    $areaGravesStmt = $conn->prepare("
        SELECT ag.*, ag.lineId as row_id, ag.areaGraveNameHe as name
        FROM areaGraves ag 
        WHERE ag.isActive = 1
        ORDER BY ag.areaGraveNameHe
    ");
    $areaGravesStmt->execute();
    $hierarchyData['areaGraves'] = $areaGravesStmt->fetchAll(PDO::FETCH_ASSOC);

    // קברים
    if ($burial && $burial['graveId']) {
        $gravesStmt = $conn->prepare("
            SELECT g.*, g.areaGraveId as area_grave_id, g.graveNameHe as name,
                CASE WHEN g.unicId = :currentGrave THEN 1 ELSE 0 END as is_current
            FROM graves g 
            WHERE (g.graveStatus IN (1, 2) OR g.unicId = :currentGrave2)
            AND g.isActive = 1
            ORDER BY g.graveNameHe
        ");
        $gravesStmt->execute([
            'currentGrave' => $burial['graveId'],
            'currentGrave2' => $burial['graveId']
        ]);
    } else {
        $gravesStmt = $conn->prepare("
            SELECT g.*, g.areaGraveId as area_grave_id, g.graveNameHe as name
            FROM graves g 
            WHERE g.graveStatus IN (1, 2)
            AND g.isActive = 1
            ORDER BY g.graveNameHe
        ");
        $gravesStmt->execute();
    }
    $hierarchyData['graves'] = $gravesStmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    FormUtils::handleError($e);
}

// הכן את ה-JSON של ההיררכיה
$hierarchyJson = json_encode($hierarchyData);

// יצירת FormBuilder
$formBuilder = new FormBuilder('burial', $itemId, $parentId);

// הוספת שדה לקוח
$formBuilder->addField('clientId', 'נפטר/ת', 'select', [
    'required' => true,
    'options' => array_merge(
        ['' => '-- בחר נפטר/ת --'],
        $customers
    ),
    'value' => $burial['clientId'] ?? ''
]);

// HTML מותאם אישית לבחירת קבר
$graveSelectorHTML = '
<fieldset class="form-section" 
    id="grave-selector-fieldset"
    data-hierarchy=\'' . htmlspecialchars($hierarchyJson, ENT_QUOTES, 'UTF-8') . '\'
    style="border: 1px solid #e2e8f0; border-radius: 8px; padding: 15px; margin-bottom: 20px;">
    <legend style="padding: 0 10px; font-weight: bold;">בחירת קבר</legend>
    <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 15px;">
        <div class="form-group">
            <label>בית עלמין</label>
            <select id="cemeterySelect" class="form-control" onchange="filterHierarchy(\'cemetery\')">
                <option value="">-- כל בתי העלמין --</option>';

foreach ($cemeteries as $cemetery) {
    $disabled = !$cemetery['has_available_graves'] ? 'disabled style="color: #999;"' : '';
    $graveSelectorHTML .= '<option value="' . $cemetery['unicId'] . '" ' . $disabled . '>' . 
                        htmlspecialchars($cemetery['name']) .
                        (!$cemetery['has_available_graves'] ? ' (אין קברים זמינים)' : '') . 
                        '</option>';
}

$graveSelectorHTML .= '
            </select>
        </div>
        <div class="form-group">
            <label>גוש</label>
            <select id="blockSelect" class="form-control" onchange="filterHierarchy(\'block\')">
                <option value="">-- כל הגושים --</option>
            </select>
        </div>
        <div class="form-group">
            <label>חלקה</label>
            <select id="plotSelect" class="form-control" onchange="filterHierarchy(\'plot\')">
                <option value="">-- כל החלקות --</option>
            </select>
        </div>
        <div class="form-group">
            <label>שורה</label>
            <select id="rowSelect" class="form-control" onchange="filterHierarchy(\'row\')" disabled>
                <option value="">-- בחר חלקה תחילה --</option>
            </select>
        </div>
        <div class="form-group">
            <label>אחוזת קבר</label>
            <select id="areaGraveSelect" class="form-control" onchange="filterHierarchy(\'areaGrave\')" disabled>
                <option value="">-- בחר שורה תחילה --</option>
            </select>
        </div>
        <div class="form-group">
            <label>קבר <span class="text-danger">*</span></label>
            <select name="graveId" id="graveSelect" class="form-control" required disabled onchange="onGraveSelected(this.value)">
                <option value="">-- בחר אחוזת קבר תחילה --</option>
            </select>
        </div>
    </div>
</fieldset>';

// הוסף את ה-HTML המותאם אישית
$formBuilder->addCustomHTML($graveSelectorHTML);

// שדה רכישה (אופציונלי)
$formBuilder->addField('purchaseId', 'רכישה קשורה', 'select', [
    'options' => array_merge(
        ['' => '-- ללא רכישה --'],
        $purchases
    ),
    'value' => $burial['purchaseId'] ?? ''
]);

// מספר תיק קבורה (יווצר אוטומטית בקבורה חדשה)
if ($itemId) {
    $formBuilder->addField('serialBurialId', 'מספר תיק קבורה', 'text', [
        'readonly' => true,
        'value' => $burial['serialBurialId'] ?? ''
    ]);
}

// פרטי פטירה
$formBuilder->addField('dateDeath', 'תאריך פטירה', 'date', [
    'required' => true,
    'value' => $burial['dateDeath'] ?? ''
]);

$formBuilder->addField('timeDeath', 'שעת פטירה', 'time', [
    'value' => $burial['timeDeath'] ?? ''
]);

$formBuilder->addField('placeDeath', 'מקום הפטירה', 'text', [
    'required' => true,
    'placeholder' => 'עיר/בית חולים',
    'value' => $burial['placeDeath'] ?? ''
]);

$formBuilder->addField('deathAbroad', 'פטירה בחו"ל', 'select', [
    'options' => [
        'לא' => 'לא',
        'כן' => 'כן'
    ],
    'value' => $burial['deathAbroad'] ?? 'לא'
]);

// פרטי קבורה
$formBuilder->addField('dateBurial', 'תאריך קבורה', 'date', [
    'required' => true,
    'value' => $burial['dateBurial'] ?? ''
]);

$formBuilder->addField('timeBurial', 'שעת קבורה', 'time', [
    'required' => true,
    'value' => $burial['timeBurial'] ?? ''
]);

$formBuilder->addField('nationalInsuranceBurial', 'קבורת ביטוח לאומי', 'select', [
    'options' => [
        'לא' => 'לא',
        'כן' => 'כן'
    ],
    'value' => $burial['nationalInsuranceBurial'] ?? 'לא'
]);

$formBuilder->addField('buriaLicense', 'רשיון קבורה', 'text', [
    'placeholder' => 'מספר רשיון',
    'value' => $burial['buriaLicense'] ?? ''
]);

// איש קשר
$formBuilder->addField('kinship', 'קרבת איש קשר', 'text', [
    'placeholder' => 'בן/בת/אח/הורה וכו\'',
    'value' => $burial['kinship'] ?? ''
]);

// תאריכים נוספים
$formBuilder->addField('dateOpening_tld', 'תאריך פתיחת תיק', 'date', [
    'value' => $burial['dateOpening_tld'] ?? date('Y-m-d')
]);

$formBuilder->addField('reportingBL', 'תאריך דיווח לביטוח לאומי', 'date', [
    'value' => $burial['reportingBL'] ?? ''
]);

// הערות
$formBuilder->addField('comment', 'הערות', 'textarea', [
    'rows' => 3,
    'placeholder' => 'הערות נוספות...',
    'value' => $burial['comment'] ?? ''
]);

// הצג את הטופס

// הוסף שדה מזהה מוסתר בעריכה
if ($itemId && isset($data['unicId'])) {
    $formBuilder->addField('unicId', '', 'hidden', [
        'value' => $data['unicId']
    ]);
}

echo $formBuilder->renderModal();
?>
