<?php
// forms/burial-form.php
// טופס הוספה/עריכת קבורה

error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: text/html; charset=utf-8');

require_once __DIR__ . '/FormBuilder.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/dashboard/dashboards/cemeteries/config.php';

$itemId = $_GET['item_id'] ?? null;
$parentId = $_GET['parent_id'] ?? null;

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
        // אם זה עריכה, כלול גם את הלקוח הנוכחי
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
        // קבורה חדשה - רק לקוחות שאינם נפטרים
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

    // הכן את נתוני ההיררכיה לבחירת קבר (זהה לרכישות)
    $hierarchyData = [];
    $cemeteries = [];

    // בתי עלמין
    if ($burial && $burial['graveId']) {
        // אם עורכים קבורה, כלול את הקבר הנוכחי
        $cemeteriesStmt = $conn->prepare("
            SELECT c.unicId, c.cemeteryNameHe as name,
            (EXISTS (
                SELECT 1 FROM graves g
                INNER JOIN areaGraves ag ON g.areaGraveId = ag.unicId
                INNER JOIN rows r ON ag.lineId = r.unicId
                INNER JOIN plots p ON r.plotId = p.unicId
                INNER JOIN blocks b ON p.blockId = b.unicId
                WHERE b.cemeteryId = c.unicId
                AND g.graveStatus IN (1, 2)  -- פנוי או שמור
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
        // קבורה חדשה - קברים פנויים או ברכישה (לא תפוסים בקבורה)
        $cemeteriesStmt = $conn->prepare("
            SELECT c.unicId, c.cemeteryNameHe as name,
            EXISTS (
                SELECT 1 FROM graves g
                INNER JOIN areaGraves ag ON g.areaGraveId = ag.unicId
                INNER JOIN rows r ON ag.lineId = r.unicId
                INNER JOIN plots p ON r.plotId = p.unicId
                INNER JOIN blocks b ON p.blockId = b.unicId
                WHERE b.cemeteryId = c.unicId
                AND g.graveStatus IN (1, 2)  -- פנוי או שמור (רכישה)
                AND g.isActive = 1
            ) as has_available_graves
            FROM cemeteries c
            WHERE c.isActive = 1
            ORDER BY c.cemeteryNameHe
        ");
        $cemeteriesStmt->execute();
    }
    $cemeteries = $cemeteriesStmt->fetchAll(PDO::FETCH_ASSOC);

    // טען את כל ההיררכיה (גושים, חלקות, שורות, אחוזות קבר, קברים)
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

    // קברים - כלול את הקבר הנוכחי או קברים זמינים
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
        // קבורה חדשה - רק קברים פנויים או ברכישה (לא תפוסים)
        $gravesStmt = $conn->prepare("
            SELECT g.*, g.areaGraveId as area_grave_id, g.graveNameHe as name
            FROM graves g 
            WHERE g.graveStatus IN (1, 2)  -- פנוי או שמור (רכישה)
            AND g.isActive = 1
            ORDER BY g.graveNameHe
        ");
        $gravesStmt->execute();
    }
    $hierarchyData['graves'] = $gravesStmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    die(json_encode(['error' => $e->getMessage()]));
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

// HTML מותאם אישית לבחירת קבר (זהה לרכישות)
$graveSelectorHTML = '
<fieldset class="form-section" 
    id="grave-selector-fieldset"
    data-hierarchy=\'' . htmlspecialchars($hierarchyJson, ENT_QUOTES, 'UTF-8') . '\'
    data-cemeteries=\'' . htmlspecialchars(json_encode($cemeteries), ENT_QUOTES, 'UTF-8') . '\'>
    <legend>בחירת מיקום קבר</legend>
    
    <div class="form-row">
        <div class="form-group col-md-4">
            <label for="cemeterySelect">בית עלמין</label>
            <select id="cemeterySelect" class="form-control" onchange="filterHierarchy(\'cemetery\')">
                <option value="">-- בחר בית עלמין --</option>
            </select>
        </div>
        
        <div class="form-group col-md-4">
            <label for="blockSelect">גוש</label>
            <select id="blockSelect" class="form-control" onchange="filterHierarchy(\'block\')" disabled>
                <option value="">-- בחר גוש --</option>
            </select>
        </div>
        
        <div class="form-group col-md-4">
            <label for="plotSelect">חלקה</label>
            <select id="plotSelect" class="form-control" onchange="filterHierarchy(\'plot\')" disabled>
                <option value="">-- בחר חלקה --</option>
            </select>
        </div>
    </div>
    
    <div class="form-row">
        <div class="form-group col-md-4">
            <label for="rowSelect">שורה</label>
            <select id="rowSelect" class="form-control" onchange="filterHierarchy(\'row\')" disabled>
                <option value="">-- בחר שורה --</option>
            </select>
        </div>
        
        <div class="form-group col-md-4">
            <label for="areaGraveSelect">אחוזת קבר</label>
            <select id="areaGraveSelect" class="form-control" onchange="filterHierarchy(\'area_grave\')" disabled>
                <option value="">-- בחר אחוזת קבר --</option>
            </select>
        </div>
        
        <div class="form-group col-md-4">
            <label for="graveSelect">קבר <span class="text-danger">*</span></label>
            <select id="graveSelect" name="graveId" class="form-control" required disabled>
                <option value="">-- בחר קבר --</option>
            </select>
        </div>
    </div>
</fieldset>';

// הוסף את בורר הקבר לטופס
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
// $formBuilder->render();
echo $formBuilder->renderModal();
?>

<script>
// JavaScript לטיפול בהיררכיית בחירת הקבר
document.addEventListener('DOMContentLoaded', function() {
    const fieldset = document.getElementById('grave-selector-fieldset');
    if (!fieldset) return;
    
    // טען את נתוני ההיררכיה
    window.hierarchyData = JSON.parse(fieldset.getAttribute('data-hierarchy'));
    window.cemeteries = JSON.parse(fieldset.getAttribute('data-cemeteries'));
    
    // אתחל בתי עלמין
    populateCemeteries();
    
    // אם זו עריכה, טען את הבחירה הקיימת
    <?php if ($burial && $burial['graveId']): ?>
    loadExistingSelection('<?php echo $burial['graveId']; ?>');
    <?php endif; ?>
});

// אכלס בתי עלמין
function populateCemeteries() {
    const select = document.getElementById('cemeterySelect');
    select.innerHTML = '<option value="">-- בחר בית עלמין --</option>';
    
    window.cemeteries.forEach(cemetery => {
        const option = document.createElement('option');
        option.value = cemetery.unicId;
        option.textContent = cemetery.name;
        if (!cemetery.has_available_graves) {
            option.disabled = true;
            option.textContent += ' (אין קברים זמינים)';
        }
        select.appendChild(option);
    });
}

// פונקציה לסינון היררכיה
function filterHierarchy(level) {
    const cemetery = document.getElementById('cemeterySelect').value;
    const block = document.getElementById('blockSelect').value;
    const plot = document.getElementById('plotSelect').value;
    const row = document.getElementById('rowSelect').value;
    const areaGrave = document.getElementById('areaGraveSelect').value;
    
    switch(level) {
        case 'cemetery':
            populateBlocks(cemetery);
            clearSelectors(['plot', 'row', 'area_grave', 'grave']);
            break;
            
        case 'block':
            populatePlots(cemetery, block);
            clearSelectors(['row', 'area_grave', 'grave']);
            break;
            
        case 'plot':
            populateRows(plot);
            clearSelectors(['area_grave', 'grave']);
            break;
            
        case 'row':
            populateAreaGraves(row);
            clearSelectors(['grave']);
            break;
            
        case 'area_grave':
            populateGraves(areaGrave);
            break;
    }
}

// אכלס גושים
function populateBlocks(cemeteryId) {
    const select = document.getElementById('blockSelect');
    select.innerHTML = '<option value="">-- בחר גוש --</option>';
    select.disabled = !cemeteryId;
    
    if (!cemeteryId) return;
    
    const blocks = window.hierarchyData.blocks.filter(b => b.cemeteryId === cemeteryId);
    blocks.forEach(block => {
        const option = document.createElement('option');
        option.value = block.unicId;
        option.textContent = block.blockNameHe || block.unicId;
        select.appendChild(option);
    });
}

// אכלס חלקות
function populatePlots(cemeteryId, blockId) {
    const select = document.getElementById('plotSelect');
    select.innerHTML = '<option value="">-- בחר חלקה --</option>';
    select.disabled = !blockId;
    
    if (!blockId) return;
    
    const plots = window.hierarchyData.plots.filter(p => p.blockId === blockId);
    plots.forEach(plot => {
        const option = document.createElement('option');
        option.value = plot.unicId;
        option.textContent = plot.name || plot.unicId;
        select.appendChild(option);
    });
}

// אכלס שורות
function populateRows(plotId) {
    const select = document.getElementById('rowSelect');
    select.innerHTML = '<option value="">-- בחר שורה --</option>';
    select.disabled = !plotId;
    
    if (!plotId) return;
    
    const rows = window.hierarchyData.rows.filter(r => r.plot_id === plotId);
    rows.forEach(row => {
        const option = document.createElement('option');
        option.value = row.unicId;
        option.textContent = row.name || row.unicId;
        select.appendChild(option);
    });
}

// אכלס אחוזות קבר
function populateAreaGraves(rowId) {
    const select = document.getElementById('areaGraveSelect');
    select.innerHTML = '<option value="">-- בחר אחוזת קבר --</option>';
    select.disabled = !rowId;
    
    if (!rowId) return;
    
    const areaGraves = window.hierarchyData.areaGraves.filter(ag => ag.row_id === rowId);
    areaGraves.forEach(areaGrave => {
        const option = document.createElement('option');
        option.value = areaGrave.unicId;
        option.textContent = areaGrave.name || areaGrave.unicId;
        select.appendChild(option);
    });
}

// אכלס קברים
function populateGraves(areaGraveId) {
    const select = document.getElementById('graveSelect');
    select.innerHTML = '<option value="">-- בחר קבר --</option>';
    select.disabled = !areaGraveId;
    
    if (!areaGraveId) return;
    
    const graves = window.hierarchyData.graves.filter(g => g.area_grave_id === areaGraveId);
    graves.forEach(grave => {
        const option = document.createElement('option');
        option.value = grave.unicId;
        option.textContent = grave.name || grave.unicId;
        
        // סמן קברים לא זמינים
        if (grave.graveStatus == 3 && !grave.is_current) {
            option.disabled = true;
            option.textContent += ' (תפוס)';
        } else if (grave.graveStatus == 2) {
            option.textContent += ' (רכישה)';
        }
        
        select.appendChild(option);
    });
}

// נקה בוררים
function clearSelectors(levels) {
    levels.forEach(level => {
        const selectId = level + 'Select';
        const select = document.getElementById(selectId);
        if (select) {
            select.innerHTML = '<option value="">-- בחר --</option>';
            select.disabled = true;
        }
    });
}

// טען בחירה קיימת (בעריכה)
function loadExistingSelection(graveId) {
    const grave = window.hierarchyData.graves.find(g => g.unicId === graveId);
    if (!grave) return;
    
    const areaGrave = window.hierarchyData.areaGraves.find(ag => ag.unicId === grave.area_grave_id);
    if (!areaGrave) return;
    
    const row = window.hierarchyData.rows.find(r => r.unicId === areaGrave.row_id);
    if (!row) return;
    
    const plot = window.hierarchyData.plots.find(p => p.unicId === row.plot_id);
    if (!plot) return;
    
    const block = window.hierarchyData.blocks.find(b => b.unicId === plot.blockId);
    if (!block) return;
    
    // הגדר את הערכים בסדר הנכון
    document.getElementById('cemeterySelect').value = block.cemeteryId;
    filterHierarchy('cemetery');
    
    document.getElementById('blockSelect').value = block.unicId;
    filterHierarchy('block');
    
    document.getElementById('plotSelect').value = plot.unicId;
    filterHierarchy('plot');
    
    document.getElementById('rowSelect').value = row.unicId;
    filterHierarchy('row');
    
    document.getElementById('areaGraveSelect').value = areaGrave.unicId;
    filterHierarchy('area_grave');
    
    document.getElementById('graveSelect').value = graveId;
}
</script>