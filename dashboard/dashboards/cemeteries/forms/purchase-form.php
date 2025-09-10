<?php
// forms/purchase-form.php
require_once __DIR__ . '/FormBuilder.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/dashboard/dashboards/cemeteries/config.php';

$itemId = $_GET['item_id'] ?? null;
$parentId = $_GET['parent_id'] ?? null;

try {
    $conn = getDBConnection();
    
    // טען לקוחות פנויים
    $customersStmt = $conn->prepare("
        SELECT id, CONCAT(last_name, ' ', first_name) as full_name, id_number 
        FROM customers 
        WHERE customer_status = 1 AND is_active = 1 
        ORDER BY last_name, first_name
    ");
    $customersStmt->execute();
    $customers = [];
    while ($row = $customersStmt->fetch(PDO::FETCH_ASSOC)) {
        $label = $row['full_name'];
        if ($row['id_number']) {
            $label .= ' (' . $row['id_number'] . ')';
        }
        $customers[$row['id']] = $label;
    }
    
    // טען בתי עלמין
    $cemeteriesStmt = $conn->prepare("
        SELECT c.id, c.name,
        EXISTS (
            SELECT 1 FROM graves g
            INNER JOIN area_graves ag ON g.area_grave_id = ag.id
            INNER JOIN rows r ON ag.row_id = r.id
            INNER JOIN plots p ON r.plot_id = p.id
            INNER JOIN blocks b ON p.block_id = b.id
            WHERE b.cemetery_id = c.id 
            AND g.grave_status = 1 
            AND g.is_active = 1
        ) as has_available_graves
        FROM cemeteries c
        WHERE c.is_active = 1
        ORDER BY c.name
    ");
    $cemeteriesStmt->execute();
    $cemeteries = $cemeteriesStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // טען רכישה אם קיימת
    $purchase = null;
    if ($itemId) {
        $stmt = $conn->prepare("SELECT * FROM purchases WHERE id = ?");
        $stmt->execute([$itemId]);
        $purchase = $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
} catch (Exception $e) {
    die("שגיאה: " . $e->getMessage());
}

// יצירת FormBuilder
$formBuilder = new FormBuilder('purchase', $itemId, $parentId);

// הוספת שדה לקוח
$formBuilder->addField('customer_id', 'לקוח', 'select', [
    'required' => true,
    'options' => $customers,
    'value' => $purchase['customer_id'] ?? ''
]);

// הוספת שדה סטטוס רוכש
$formBuilder->addField('buyer_status', 'סטטוס רוכש', 'select', [
    'options' => [
        1 => 'רוכש לעצמו',
        2 => 'רוכש לאחר'
    ],
    'value' => $purchase['buyer_status'] ?? 1
]);

// HTML מותאם אישית לבחירת קבר
$graveSelectorHTML = '
<fieldset class="form-section" style="border: 1px solid #e2e8f0; border-radius: 8px; padding: 15px; margin-bottom: 20px;">
    <legend style="padding: 0 10px; font-weight: bold;">בחירת קבר</legend>
    <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 15px;">
        <div class="form-group">
            <label>בית עלמין</label>
            <select id="cemeterySelect" class="form-control" onchange="updateGraveSelectors(\'cemetery\')">
                <option value="">-- כל בתי העלמין --</option>';

foreach ($cemeteries as $cemetery) {
    $disabled = !$cemetery['has_available_graves'] ? 'disabled style="color: #999;"' : '';
    $graveSelectorHTML .= '<option value="' . $cemetery['id'] . '" ' . $disabled . '>' . 
                          htmlspecialchars($cemetery['name']) . 
                          (!$cemetery['has_available_graves'] ? ' (אין קברים פנויים)' : '') . 
                          '</option>';
}

$graveSelectorHTML .= '
            </select>
        </div>
        <div class="form-group">
            <label>גוש</label>
            <select id="blockSelect" class="form-control" onchange="updateGraveSelectors(\'block\')">
                <option value="">-- כל הגושים --</option>
            </select>
        </div>
        <div class="form-group">
            <label>חלקה</label>
            <select id="plotSelect" class="form-control" onchange="updateGraveSelectors(\'plot\')">
                <option value="">-- כל החלקות --</option>
            </select>
        </div>
        <div class="form-group">
            <label>שורה</label>
            <select id="rowSelect" class="form-control" onchange="updateGraveSelectors(\'row\')" disabled>
                <option value="">-- בחר חלקה תחילה --</option>
            </select>
        </div>
        <div class="form-group">
            <label>אחוזת קבר</label>
            <select id="areaGraveSelect" class="form-control" onchange="updateGraveSelectors(\'area_grave\')" disabled>
                <option value="">-- בחר שורה תחילה --</option>
            </select>
        </div>
        <div class="form-group">
            <label>קבר <span class="text-danger">*</span></label>
            <select name="grave_id" id="graveSelect" class="form-control" required disabled>
                <option value="">-- בחר אחוזת קבר תחילה --</option>
            </select>
        </div>
    </div>
</fieldset>';

// הוסף את ה-HTML המותאם אישית באמצעות המתודה הנכונה
$formBuilder->addCustomHTML($graveSelectorHTML);

// המשך השדות
$formBuilder->addField('purchase_status', 'סטטוס רכישה', 'select', [
    'options' => [
        1 => 'טיוטה',
        2 => 'אושר',
        3 => 'שולם',
        4 => 'בוטל'
    ],
    'value' => $purchase['purchase_status'] ?? 1
]);

$formBuilder->addField('price', 'מחיר', 'number', [
    'step' => '0.01',
    'value' => $purchase['price'] ?? ''
]);

$formBuilder->addField('num_payments', 'מספר תשלומים', 'number', [
    'min' => 1,
    'value' => $purchase['num_payments'] ?? 1
]);

$formBuilder->addField('payment_end_date', 'תאריך סיום תשלומים', 'date', [
    'value' => $purchase['payment_end_date'] ?? ''
]);

$formBuilder->addField('comments', 'הערות', 'textarea', [
    'rows' => 3,
    'value' => $purchase['comments'] ?? ''
]);

// הצג את הטופס
echo $formBuilder->renderModal();
?>

<script>
// JavaScript לבחירת קבר
if (typeof API_BASE === 'undefined') {
    window.API_BASE = '/dashboard/dashboards/cemeteries/api/';
}

async function updateGraveSelectors(changedLevel) {
    const cemetery = document.getElementById('cemeterySelect').value;
    const block = document.getElementById('blockSelect').value;
    const plot = document.getElementById('plotSelect').value;
    
    switch(changedLevel) {
        case 'cemetery':
            await loadBlocks(cemetery);
            await loadPlots(cemetery, null);
            clearSelectors(['row', 'area_grave', 'grave']);
            break;
            
        case 'block':
            await loadPlots(cemetery, block);
            clearSelectors(['row', 'area_grave', 'grave']);
            break;
            
        case 'plot':
            if (plot) {
                await loadRows(plot);
                document.getElementById('rowSelect').disabled = false;
            } else {
                clearSelectors(['row', 'area_grave', 'grave']);
                document.getElementById('rowSelect').disabled = true;
            }
            break;
            
        case 'row':
            const row = document.getElementById('rowSelect').value;
            if (row) {
                await loadAreaGraves(row);
                document.getElementById('areaGraveSelect').disabled = false;
            } else {
                clearSelectors(['area_grave', 'grave']);
                document.getElementById('areaGraveSelect').disabled = true;
            }
            break;
            
        case 'area_grave':
            const areaGrave = document.getElementById('areaGraveSelect').value;
            if (areaGrave) {
                await loadGraves(areaGrave);
                document.getElementById('graveSelect').disabled = false;
            } else {
                clearSelectors(['grave']);
                document.getElementById('graveSelect').disabled = true;
            }
            break;
    }
}

async function loadBlocks(cemeteryId) {
    const blockSelect = document.getElementById('blockSelect');
    let url = `${API_BASE}cemetery-hierarchy.php?action=list&type=block`;
    if (cemeteryId) url += `&parent_id=${cemeteryId}`;
    
    try {
        const response = await fetch(url);
        const data = await response.json();
        
        blockSelect.innerHTML = '<option value="">-- כל הגושים --</option>';
        if (data.success && data.data) {
            data.data.forEach(block => {
                blockSelect.innerHTML += `<option value="${block.id}">${block.name}</option>`;
            });
        }
    } catch (error) {
        console.error('Error loading blocks:', error);
    }
}

async function loadPlots(cemeteryId, blockId) {
    const plotSelect = document.getElementById('plotSelect');
    let url = `${API_BASE}cemetery-hierarchy.php?action=list&type=plot`;
    
    if (blockId) {
        url += `&parent_id=${blockId}`;
    }
    
    try {
        const response = await fetch(url);
        const data = await response.json();
        
        plotSelect.innerHTML = '<option value="">-- כל החלקות --</option>';
        if (data.success && data.data) {
            data.data.forEach(plot => {
                plotSelect.innerHTML += `<option value="${plot.id}">${plot.name}</option>`;
            });
        }
    } catch (error) {
        console.error('Error loading plots:', error);
    }
}

async function loadRows(plotId) {
    const rowSelect = document.getElementById('rowSelect');
    
    try {
        const response = await fetch(`${API_BASE}cemetery-hierarchy.php?action=list&type=row&parent_id=${plotId}`);
        const data = await response.json();
        
        rowSelect.innerHTML = '<option value="">-- בחר שורה --</option>';
        if (data.success && data.data) {
            data.data.forEach(row => {
                rowSelect.innerHTML += `<option value="${row.id}">${row.name}</option>`;
            });
        }
    } catch (error) {
        console.error('Error loading rows:', error);
    }
}

async function loadAreaGraves(rowId) {
    const areaGraveSelect = document.getElementById('areaGraveSelect');
    
    try {
        const response = await fetch(`${API_BASE}cemetery-hierarchy.php?action=list&type=area_grave&parent_id=${rowId}`);
        const data = await response.json();
        
        areaGraveSelect.innerHTML = '<option value="">-- בחר אחוזת קבר --</option>';
        if (data.success && data.data) {
            data.data.forEach(areaGrave => {
                areaGraveSelect.innerHTML += `<option value="${areaGrave.id}">${areaGrave.name}</option>`;
            });
        }
    } catch (error) {
        console.error('Error loading area graves:', error);
    }
}

async function loadGraves(areaGraveId) {
    const graveSelect = document.getElementById('graveSelect');
    
    try {
        const response = await fetch(`${API_BASE}cemetery-hierarchy.php?action=list&type=grave&parent_id=${areaGraveId}`);
        const data = await response.json();
        
        graveSelect.innerHTML = '<option value="">-- בחר קבר --</option>';
        if (data.success && data.data) {
            data.data.forEach(grave => {
                if (grave.grave_status == 1) {
                    graveSelect.innerHTML += `<option value="${grave.id}">קבר ${grave.grave_number}</option>`;
                }
            });
        }
    } catch (error) {
        console.error('Error loading graves:', error);
    }
}

function clearSelectors(levels) {
    const configs = {
        'row': { id: 'rowSelect', default: '-- בחר חלקה תחילה --', disabled: true },
        'area_grave': { id: 'areaGraveSelect', default: '-- בחר שורה תחילה --', disabled: true },
        'grave': { id: 'graveSelect', default: '-- בחר אחוזת קבר תחילה --', disabled: true }
    };
    
    levels.forEach(level => {
        const config = configs[level];
        if (config) {
            const element = document.getElementById(config.id);
            element.innerHTML = `<option value="">${config.default}</option>`;
            element.disabled = config.disabled;
        }
    });
}

// טעינה ראשונית
window.addEventListener('DOMContentLoaded', function() {
    loadBlocks('');
    loadPlots('', '');
});
</script>