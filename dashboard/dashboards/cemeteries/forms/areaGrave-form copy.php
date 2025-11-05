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

// ========================================
// HTML של טבלת קברים (ללא JavaScript!)
// ========================================

$isEditMode = !empty($itemId);
$gravesJson = json_encode($graves, JSON_UNESCAPED_UNICODE);

$gravesHTML = '
<fieldset class="graves-section" style="border: 2px solid #667eea; border-radius: 12px; padding: 20px; margin: 20px 0; background: #f8f9ff;">
    <legend style="padding: 0 10px; font-weight: bold; color: #667eea; font-size: 1.1em;">🪦 קברים באחוזה (חובה לפחות 1, מקסימום 5)</legend>
    
    <div style="display: flex; gap: 15px; margin-bottom: 15px; align-items: center;">
        <button type="button" id="btnAddGrave" style="background: #10b981; color: white; padding: 10px 20px; border: none; border-radius: 6px; cursor: pointer; font-weight: 600;">
            ➕ הוסף קבר
        </button>
        <span id="graveCount" style="color: #666; font-weight: 500;"></span>
    </div>
    
    <div style="overflow-x: auto;">
        <table class="graves-table" style="width: 100%; border-collapse: collapse; background: white; min-width: 800px;">
            <thead style="background: linear-gradient(135deg, #667eea, #764ba2); color: white;">
                <tr>
                    <th style="padding: 10px; width: 50px; text-align: center;">#</th>
                    <th style="padding: 10px; text-align: right;">שם קבר <span style="color: #ffd700;">*</span></th>
                    <th style="padding: 10px; width: 150px; text-align: right;">סוג חלקה <span style="color: #ffd700;">*</span></th>
                    ' . ($isEditMode ? '<th style="padding: 10px; width: 100px; text-align: center;">סטטוס</th>' : '') . '
                    <th style="padding: 10px; width: 100px; text-align: center;">קבר קטן</th>
                    <th style="padding: 10px; width: 130px; text-align: right;">עלות בנייה</th>
                    <th style="padding: 10px; width: 80px; text-align: center;">פעולות</th>
                </tr>
            </thead>
            <tbody id="gravesBody"></tbody>
        </table>
    </div>
    
    <input type="hidden" name="gravesData" id="gravesData">
</fieldset>

<style>
#areaGraveFormModal .modal-dialog {
    max-width: 95% !important;
    width: 1200px !important;
}

#areaGraveFormModal .modal-body {
    max-height: 80vh !important;
}

.graves-table td {
    padding: 10px;
    border: 1px solid #e2e8f0;
}

.graves-table input,
.graves-table select {
    width: 100%;
    padding: 8px;
    border: 1px solid #cbd5e0;
    border-radius: 6px;
    box-sizing: border-box;
}

.graves-table input:focus,
.graves-table select:focus {
    outline: none;
    border-color: #667eea;
    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
}

.status-badge {
    padding: 4px 10px;
    border-radius: 10px;
    font-size: 12px;
    font-weight: 600;
    white-space: nowrap;
}

.status-available { background: #dcfce7; color: #166534; }
.status-purchased { background: #dbeafe; color: #1e40af; }
.status-buried { background: #f3f4f6; color: #374151; }

.btn-delete {
    background: #ef4444;
    color: white;
    border: none;
    padding: 6px 12px;
    border-radius: 6px;
    cursor: pointer;
    font-weight: 600;
}

.btn-delete:hover:not(:disabled) {
    background: #dc2626;
}

.btn-delete:disabled {
    background: #d1d5db;
    cursor: not-allowed;
    opacity: 0.6;
}
</style>
';

$formBuilder->addCustomHTML($gravesHTML);

if ($areaGrave && $areaGrave['unicId']) {
    $formBuilder->addField('unicId', '', 'hidden', ['value' => $areaGrave['unicId']]);
}

// ========================================
// JavaScript לפני renderModal!
// ========================================
?>

<script>
console.log('🚀 Graves script loading...');

// נתונים גלובליים
window.GRAVES_CONFIG = {
    existing: <?php echo $gravesJson; ?>,
    isEdit: <?php echo $isEditMode ? 'true' : 'false'; ?>,
    current: [],
    MAX: 5
};

// פונקציות עזר
function initGravesSystem() {
    console.log('📋 Initializing graves system...');
    
    if (window.GRAVES_CONFIG.isEdit && window.GRAVES_CONFIG.existing.length > 0) {
        console.log('📥 Loading', window.GRAVES_CONFIG.existing.length, 'existing graves');
        window.GRAVES_CONFIG.existing.forEach(function(g) {
            window.GRAVES_CONFIG.current.push({
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
        console.log('➕ Creating first grave');
        window.GRAVES_CONFIG.current.push({
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
    
    // חבר כפתור הוספה
    var btn = document.getElementById('btnAddGrave');
    if (btn) {
        btn.onclick = addGrave;
        console.log('✅ Button connected');
    }
}

function addGrave() {
    if (window.GRAVES_CONFIG.current.length >= window.GRAVES_CONFIG.MAX) {
        alert('ניתן להוסיף עד 5 קברים בלבד');
        return;
    }
    
    console.log('➕ Adding grave');
    window.GRAVES_CONFIG.current.push({
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
    var g = window.GRAVES_CONFIG.current[idx];
    
    if (idx === 0) {
        alert('לא ניתן למחוק את הקבר הראשון');
        return;
    }
    
    if (window.GRAVES_CONFIG.isEdit && g.isExisting && g.graveStatus !== 1) {
        alert('לא ניתן למחוק קבר לא פנוי');
        return;
    }
    
    if (confirm('למחוק קבר זה?')) {
        window.GRAVES_CONFIG.current.splice(idx, 1);
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
    
    window.GRAVES_CONFIG.current.forEach(function(g, i) {
        var tr = document.createElement('tr');
        
        // מספר
        var td1 = document.createElement('td');
        td1.style.textAlign = 'center';
        td1.style.fontWeight = 'bold';
        td1.style.color = '#667eea';
        td1.textContent = i + 1;
        tr.appendChild(td1);
        
        // שם
        var td2 = document.createElement('td');
        var inp1 = document.createElement('input');
        inp1.type = 'text';
        inp1.value = g.graveNameHe || '';
        inp1.placeholder = 'שם קבר (חובה)';
        inp1.required = true;
        inp1.onchange = (function(idx) {
            return function() {
                window.GRAVES_CONFIG.current[idx].graveNameHe = this.value;
            };
        })(i);
        td2.appendChild(inp1);
        tr.appendChild(td2);
        
        // סוג
        var td3 = document.createElement('td');
        var sel = document.createElement('select');
        sel.required = true;
        sel.innerHTML = 
            '<option value="1"' + (g.plotType == 1 ? ' selected' : '') + '>פטורה</option>' +
            '<option value="2"' + (g.plotType == 2 ? ' selected' : '') + '>חריגה</option>' +
            '<option value="3"' + (g.plotType == 3 ? ' selected' : '') + '>סגורה</option>';
        sel.onchange = (function(idx) {
            return function() {
                window.GRAVES_CONFIG.current[idx].plotType = parseInt(this.value);
            };
        })(i);
        td3.appendChild(sel);
        tr.appendChild(td3);
        
        // סטטוס (עריכה בלבד)
        <?php if ($isEditMode): ?>
        var td4 = document.createElement('td');
        td4.style.textAlign = 'center';
        var statuses = {1: 'פנוי', 2: 'נרכש', 3: 'קבור'};
        var classes = {1: 'available', 2: 'purchased', 3: 'buried'};
        var badge = document.createElement('span');
        badge.className = 'status-badge status-' + classes[g.graveStatus];
        badge.textContent = statuses[g.graveStatus];
        td4.appendChild(badge);
        tr.appendChild(td4);
        <?php endif; ?>
        
        // קבר קטן
        var td5 = document.createElement('td');
        td5.style.textAlign = 'center';
        var chk = document.createElement('input');
        chk.type = 'checkbox';
        chk.checked = g.isSmallGrave;
        chk.onchange = (function(idx) {
            return function() {
                window.GRAVES_CONFIG.current[idx].isSmallGrave = this.checked;
            };
        })(i);
        td5.appendChild(chk);
        tr.appendChild(td5);
        
        // עלות
        var td6 = document.createElement('td');
        var inp2 = document.createElement('input');
        inp2.type = 'number';
        inp2.step = '0.01';
        inp2.value = g.constructionCost || '';
        inp2.placeholder = '0.00';
        inp2.onchange = (function(idx) {
            return function() {
                window.GRAVES_CONFIG.current[idx].constructionCost = this.value;
            };
        })(i);
        td6.appendChild(inp2);
        tr.appendChild(td6);
        
        // מחיקה
        var td7 = document.createElement('td');
        td7.style.textAlign = 'center';
        var btnDel = document.createElement('button');
        btnDel.type = 'button';
        btnDel.className = 'btn-delete';
        btnDel.textContent = '🗑️';
        var canDelete = i > 0 && (!window.GRAVES_CONFIG.isEdit || !g.isExisting || g.graveStatus === 1);
        btnDel.disabled = !canDelete;
        btnDel.onclick = (function(idx) {
            return function() { deleteGrave(idx); };
        })(i);
        td7.appendChild(btnDel);
        tr.appendChild(td7);
        
        tbody.appendChild(tr);
    });
    
    console.log('✅ Rendered', window.GRAVES_CONFIG.current.length, 'graves');
}

function updateCounter() {
    var btn = document.getElementById('btnAddGrave');
    var counter = document.getElementById('graveCount');
    
    if (counter) {
        counter.textContent = '(' + window.GRAVES_CONFIG.current.length + '/' + window.GRAVES_CONFIG.MAX + ' קברים)';
    }
    
    if (btn) {
        btn.disabled = window.GRAVES_CONFIG.current.length >= window.GRAVES_CONFIG.MAX;
    }
}

// ולידציה
window.validateGravesData = function() {
    console.log('🔍 Validating...');
    
    if (window.GRAVES_CONFIG.current.length === 0) {
        alert('חובה לפחות קבר אחד');
        return false;
    }
    
    for (var i = 0; i < window.GRAVES_CONFIG.current.length; i++) {
        if (!window.GRAVES_CONFIG.current[i].graveNameHe || !window.GRAVES_CONFIG.current[i].graveNameHe.trim()) {
            alert('שם קבר ' + (i + 1) + ' הוא חובה');
            return false;
        }
    }
    
    var names = window.GRAVES_CONFIG.current.map(function(g) { 
        return g.graveNameHe.trim().toLowerCase(); 
    });
    var unique = names.filter(function(v, i, a) { 
        return a.indexOf(v) === i; 
    });
    
    if (names.length !== unique.length) {
        alert('שמות קברים חייבים להיות ייחודיים');
        return false;
    }
    
    document.getElementById('gravesData').value = JSON.stringify(window.GRAVES_CONFIG.current);
    console.log('✅ Validation passed');
    return true;
};

// אתחול מושהה
setTimeout(function() {
    initGravesSystem();
}, 300);
</script>

<?php
// הצג את הטופס
echo $formBuilder->renderModal();
?>