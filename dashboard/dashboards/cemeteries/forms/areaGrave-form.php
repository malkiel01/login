<?php
/*
 * File: dashboards/dashboard/cemeteries/forms/areaGrave-form.php
 * Version: 2.0.0
 * Updated: 2025-11-05
 * Author: Malkiel
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: text/html; charset=utf-8');

require_once __DIR__ . '/FormBuilder.php';
require_once __DIR__ . '/FormUtils.php';
require_once dirname(__DIR__) . '/config.php';

try {
    $conn = getDBConnection();
    $itemId = $_GET['itemId'] ?? $_GET['id'] ?? null;
    $parentId = $_GET['parentId'] ?? $_GET['parent_id'] ?? null;
    
    $areaGrave = null;
    $graves = [];
    
    if ($itemId) {
        $stmt = $conn->prepare("SELECT * FROM areaGraves WHERE unicId = ? AND isActive = 1");
        $stmt->execute([$itemId]);
        $areaGrave = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($areaGrave) {
            $stmt = $conn->prepare("SELECT * FROM graves WHERE areaGraveId = ? AND isActive = 1 ORDER BY id ASC");
            $stmt->execute([$areaGrave['unicId']]);
            $graves = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    }
    
    // טען שורות
    $rows = [];
    if ($parentId) {
        $stmt = $conn->prepare("SELECT r.unicId, r.lineNameHe, r.serialNumber FROM rows r WHERE r.plotId = ? AND r.isActive = 1 ORDER BY r.serialNumber, r.lineNameHe");
        $stmt->execute([$parentId]);
    } else {
        $stmt = $conn->query("SELECT r.unicId, r.lineNameHe, r.serialNumber FROM rows r WHERE r.isActive = 1 ORDER BY r.serialNumber, r.lineNameHe");
    }
    
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $label = $row['lineNameHe'] ?: "שורה {$row['serialNumber']}";
        $rows[$row['unicId']] = $label;
    }
    
} catch (Exception $e) {
    FormUtils::handleError($e);
}

$formBuilder = new FormBuilder('areaGrave', $itemId, $parentId);

$formBuilder->addField('lineId', 'שורה', 'select', [
    'required' => true,
    'options' => array_merge(['' => '-- בחר שורה --'], $rows),
    'value' => $areaGrave['lineId'] ?? ''
]);

$formBuilder->addField('areaGraveNameHe', 'שם אחוזת קבר', 'text', [
    'required' => true,
    'placeholder' => 'הזן שם אחוזת קבר',
    'value' => $areaGrave['areaGraveNameHe'] ?? ''
]);

$formBuilder->addField('graveType', 'סוג אחוזת קבר', 'select', [
    'required' => true,
    'options' => ['' => '-- בחר סוג --', 1 => 'שדה', 2 => 'רוויה', 3 => 'סנהדרין'],
    'value' => $areaGrave['graveType'] ?? ''
]);

$formBuilder->addField('coordinates', 'קואורדינטות', 'text', [
    'placeholder' => 'הזן קואורדינטות',
    'value' => $areaGrave['coordinates'] ?? ''
]);

$formBuilder->addField('comments', 'הערות', 'textarea', [
    'rows' => 3,
    'value' => $areaGrave['comments'] ?? ''
]);

// ========== טבלת קברים ==========
$isEditMode = !empty($itemId);
$gravesJson = json_encode($graves, JSON_UNESCAPED_UNICODE);
?>

<style>
/* הרחבת המודאל */
#areaGraveFormModal .modal-dialog {
    max-width: 95% !important;
    width: 1200px !important;
}

#areaGraveFormModal .modal-body {
    max-height: 80vh !important;
}

.graves-section {
    border: 2px solid #667eea;
    border-radius: 12px;
    padding: 20px;
    margin: 20px 0;
    background: #f8f9ff;
}

.graves-section h3 {
    color: #667eea;
    margin-bottom: 15px;
}

.graves-controls {
    display: flex;
    gap: 15px;
    margin-bottom: 15px;
    align-items: center;
}

.btn-add-grave {
    background: #10b981;
    color: white;
    padding: 10px 20px;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    font-weight: 600;
}

.btn-add-grave:hover:not(:disabled) {
    background: #059669;
}

.btn-add-grave:disabled {
    background: #d1d5db;
    cursor: not-allowed;
}

.graves-table-wrap {
    overflow-x: auto;
}

.graves-table {
    width: 100%;
    border-collapse: collapse;
    background: white;
    min-width: 800px;
}

.graves-table thead {
    background: linear-gradient(135deg, #667eea, #764ba2);
    color: white;
}

.graves-table th,
.graves-table td {
    padding: 10px;
    text-align: right;
    border: 1px solid #e2e8f0;
}

.graves-table input,
.graves-table select {
    width: 100%;
    padding: 6px;
    border: 1px solid #cbd5e0;
    border-radius: 4px;
}

.graves-table input:focus,
.graves-table select:focus {
    outline: none;
    border-color: #667eea;
}

.btn-delete {
    background: #ef4444;
    color: white;
    border: none;
    padding: 5px 10px;
    border-radius: 4px;
    cursor: pointer;
}

.btn-delete:disabled {
    background: #d1d5db;
    cursor: not-allowed;
}

.status-badge {
    padding: 4px 10px;
    border-radius: 10px;
    font-size: 12px;
    font-weight: 600;
}

.status-available { background: #dcfce7; color: #166534; }
.status-purchased { background: #dbeafe; color: #1e40af; }
.status-buried { background: #f3f4f6; color: #374151; }
</style>

<div class="graves-section">
    <h3>🪦 קברים באחוזה (חובה לפחות 1, מקסימום 5)</h3>
    
    <div class="graves-controls">
        <button type="button" class="btn-add-grave" id="btnAddGrave">➕ הוסף קבר</button>
        <span id="graveCount"></span>
    </div>
    
    <div class="graves-table-wrap">
        <table class="graves-table">
            <thead>
                <tr>
                    <th style="width: 50px;">#</th>
                    <th>שם קבר *</th>
                    <th style="width: 150px;">סוג חלקה *</th>
                    <?php if ($isEditMode): ?>
                    <th style="width: 100px;">סטטוס</th>
                    <?php endif; ?>
                    <th style="width: 100px;">קבר קטן</th>
                    <th style="width: 130px;">עלות בנייה</th>
                    <th style="width: 80px;">פעולות</th>
                </tr>
            </thead>
            <tbody id="gravesBody"></tbody>
        </table>
    </div>
    
    <input type="hidden" name="gravesData" id="gravesData">
</div>

<script>
console.log('🚀 Starting graves script...');

var GRAVES_DATA = {
    existing: <?php echo $gravesJson; ?>,
    isEdit: <?php echo $isEditMode ? 'true' : 'false'; ?>,
    current: [],
    MAX: 5
};

function initGraves() {
    console.log('📋 Init graves, edit mode:', GRAVES_DATA.isEdit);
    
    if (GRAVES_DATA.isEdit && GRAVES_DATA.existing.length > 0) {
        GRAVES_DATA.existing.forEach(function(g) {
            GRAVES_DATA.current.push({
                id: g.unicId,
                graveNameHe: g.graveNameHe || '',
                plotType: parseInt(g.plotType) || 1,
                graveStatus: parseInt(g.graveStatus) || 1,
                isSmallGrave: g.isSmallGrave == 1,
                constructionCost: g.constructionCost || '',
                isExisting: true
            });
        });
    } else {
        console.log('➕ Adding first grave...');
        GRAVES_DATA.current.push({
            id: null,
            graveNameHe: '',
            plotType: 1,
            graveStatus: 1,
            isSmallGrave: false,
            constructionCost: '',
            isExisting: false
        });
    }
    
    renderGraves();
    updateCounter();
}

function addGrave() {
    if (GRAVES_DATA.current.length >= GRAVES_DATA.MAX) {
        alert('ניתן להוסיף עד 5 קברים בלבד');
        return;
    }
    
    GRAVES_DATA.current.push({
        id: null,
        graveNameHe: '',
        plotType: 1,
        graveStatus: 1,
        isSmallGrave: false,
        constructionCost: '',
        isExisting: false
    });
    
    renderGraves();
    updateCounter();
}

function deleteGrave(idx) {
    var g = GRAVES_DATA.current[idx];
    
    if (idx === 0) {
        alert('לא ניתן למחוק את הקבר הראשון');
        return;
    }
    
    if (GRAVES_DATA.isEdit && g.isExisting && g.graveStatus !== 1) {
        alert('לא ניתן למחוק קבר לא פנוי');
        return;
    }
    
    if (confirm('למחוק קבר זה?')) {
        GRAVES_DATA.current.splice(idx, 1);
        renderGraves();
        updateCounter();
    }
}

function renderGraves() {
    var tbody = document.getElementById('gravesBody');
    if (!tbody) {
        console.error('❌ tbody not found');
        return;
    }
    
    tbody.innerHTML = '';
    
    GRAVES_DATA.current.forEach(function(g, i) {
        var tr = document.createElement('tr');
        
        // מספר
        tr.innerHTML += '<td style="text-align:center; font-weight:bold;">' + (i + 1) + '</td>';
        
        // שם
        tr.innerHTML += '<td><input type="text" value="' + (g.graveNameHe || '') + '" onchange="GRAVES_DATA.current[' + i + '].graveNameHe = this.value" required></td>';
        
        // סוג
        tr.innerHTML += '<td><select onchange="GRAVES_DATA.current[' + i + '].plotType = parseInt(this.value)">' +
            '<option value="1"' + (g.plotType == 1 ? ' selected' : '') + '>פטורה</option>' +
            '<option value="2"' + (g.plotType == 2 ? ' selected' : '') + '>חריגה</option>' +
            '<option value="3"' + (g.plotType == 3 ? ' selected' : '') + '>סגורה</option>' +
            '</select></td>';
        
        // סטטוס (עריכה בלבד)
        <?php if ($isEditMode): ?>
        var statuses = {1: 'פנוי', 2: 'נרכש', 3: 'קבור'};
        var classes = {1: 'available', 2: 'purchased', 3: 'buried'};
        tr.innerHTML += '<td style="text-align:center"><span class="status-badge status-' + classes[g.graveStatus] + '">' + statuses[g.graveStatus] + '</span></td>';
        <?php endif; ?>
        
        // קבר קטן
        tr.innerHTML += '<td style="text-align:center"><input type="checkbox"' + (g.isSmallGrave ? ' checked' : '') + ' onchange="GRAVES_DATA.current[' + i + '].isSmallGrave = this.checked"></td>';
        
        // עלות
        tr.innerHTML += '<td><input type="number" step="0.01" value="' + (g.constructionCost || '') + '" onchange="GRAVES_DATA.current[' + i + '].constructionCost = this.value"></td>';
        
        // מחיקה
        var canDelete = i > 0 && (!GRAVES_DATA.isEdit || !g.isExisting || g.graveStatus === 1);
        tr.innerHTML += '<td style="text-align:center"><button type="button" class="btn-delete" onclick="deleteGrave(' + i + ')"' + (!canDelete ? ' disabled' : '') + '>🗑️</button></td>';
        
        tbody.appendChild(tr);
    });
}

function updateCounter() {
    var btn = document.getElementById('btnAddGrave');
    var counter = document.getElementById('graveCount');
    
    if (counter) {
        counter.textContent = '(' + GRAVES_DATA.current.length + '/' + GRAVES_DATA.MAX + ' קברים)';
    }
    
    if (btn) {
        btn.disabled = GRAVES_DATA.current.length >= GRAVES_DATA.MAX;
    }
}

window.validateGravesData = function() {
    if (GRAVES_DATA.current.length === 0) {
        alert('חובה לפחות קבר אחד');
        return false;
    }
    
    for (var i = 0; i < GRAVES_DATA.current.length; i++) {
        if (!GRAVES_DATA.current[i].graveNameHe || !GRAVES_DATA.current[i].graveNameHe.trim()) {
            alert('שם קבר ' + (i + 1) + ' הוא חובה');
            return false;
        }
    }
    
    // בדוק שמות כפולים
    var names = GRAVES_DATA.current.map(function(g) { return g.graveNameHe.trim().toLowerCase(); });
    var unique = names.filter(function(v, i, a) { return a.indexOf(v) === i; });
    if (names.length !== unique.length) {
        alert('שמות קברים חייבים להיות ייחודיים');
        return false;
    }
    
    document.getElementById('gravesData').value = JSON.stringify(GRAVES_DATA.current);
    console.log('✅ Validated:', GRAVES_DATA.current.length, 'graves');
    return true;
};

// אתחול
setTimeout(function() {
    var btn = document.getElementById('btnAddGrave');
    if (btn) {
        console.log('✅ Found button, initializing...');
        btn.addEventListener('click', addGrave);
        initGraves();
    } else {
        console.error('❌ Button not found!');
    }
}, 500);
</script>

<?php
// המשך הטופס
if ($areaGrave && $areaGrave['unicId']) {
    $formBuilder->addField('unicId', '', 'hidden', ['value' => $areaGrave['unicId']]);
}

echo $formBuilder->renderModal();
?>