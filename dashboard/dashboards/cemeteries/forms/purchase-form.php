<?php
// forms/purchase-form.php
require_once __DIR__ . '/FormBuilder.php';
require_once __DIR__ . '/forms-config.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/dashboard/dashboards/cemeteries/config.php';

$itemId = $_GET['item_id'] ?? null;
$parentId = $_GET['parent_id'] ?? null;

try {
    $conn = getDBConnection();
    
    // טען רק לקוחות עם סטטוס 1 (פנוי)
    $customersStmt = $conn->prepare("
        SELECT id, CONCAT(last_name, ' ', first_name) as full_name, id_number 
        FROM customers 
        WHERE customer_status = 1 
        AND is_active = 1 
        ORDER BY last_name, first_name
    ");
    $customersStmt->execute();
    $customers = [];
    $customers[''] = '-- בחר לקוח --';
    while ($row = $customersStmt->fetch(PDO::FETCH_ASSOC)) {
        $label = $row['full_name'];
        if ($row['id_number']) {
            $label .= ' (' . $row['id_number'] . ')';
        }
        $customers[$row['id']] = $label;
    }
    
    // טען בתי עלמין עם סימון האם יש קברים פנויים
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
    
    // אם עורכים רכישה קיימת
    $purchase = null;
    if ($itemId) {
        $stmt = $conn->prepare("SELECT * FROM purchases WHERE id = ?");
        $stmt->execute([$itemId]);
        $purchase = $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
} catch (Exception $e) {
    echo "<div class='alert alert-danger'>שגיאה: " . $e->getMessage() . "</div>";
    exit;
}

// צור את הטופס עם FormBuilder
$formBuilder = new FormBuilder('purchase', $itemId, $parentId);

// הוסף את השדות
$formBuilder->addField('customer_id', 'לקוח', 'select', [
    'required' => true,
    'options' => $customers,
    'value' => $purchase['customer_id'] ?? ''
]);

// הוסף בחירת קבר כ-HTML מותאם
$graveSelectorHTML = '
<fieldset class="form-section">
    <legend>בחירת קבר</legend>
    <div class="form-row">
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
    </div>
    
    <div class="form-row">
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
    </div>
    
    <div class="form-row">
        <div class="form-group">
            <label>אחוזת קבר</label>
            <select id="areaGraveSelect" class="form-control" onchange="updateGraveSelectors(\'area_grave\')" disabled>
                <option value="">-- בחר שורה תחילה --</option>
            </select>
        </div>
        <div class="form-group">
            <label>קבר <span class="required">*</span></label>
            <select name="grave_id" id="graveSelect" class="form-control" required disabled>
                <option value="">-- בחר אחוזת קבר תחילה --</option>
            </select>
        </div>
    </div>
</fieldset>';

// המרת ל-FormBuilder 
$formBuilder->fields[] = ['type' => 'raw_html', 'html' => $graveSelectorHTML];

$formBuilder->addField('buyer_status', 'סטטוס רוכש', 'select', [
    'options' => [
        '1' => 'רוכש לעצמו',
        '2' => 'רוכש לאחר'
    ],
    'value' => $purchase['buyer_status'] ?? 1
]);

$formBuilder->addField('purchase_status', 'סטטוס רכישה', 'select', [
    'options' => [
        '1' => 'טיוטה',
        '2' => 'אושר',
        '3' => 'שולם',
        '4' => 'בוטל'
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
if (typeof API_BASE === 'undefined') {
    window.API_BASE = '/dashboard/dashboards/cemeteries/api/';
}

// פונקציה מרכזית לעדכון כל הבוררים
async function updateGraveSelectors(changedLevel) {
    const cemetery = document.getElementById('cemeterySelect').value;
    const block = document.getElementById('blockSelect').value;
    const plot = document.getElementById('plotSelect').value;
    const row = document.getElementById('rowSelect').value;
    const areaGrave = document.getElementById('areaGraveSelect').value;
    
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
            if (row) {
                await loadAreaGraves(row);
                document.getElementById('areaGraveSelect').disabled = false;
            } else {
                clearSelectors(['area_grave', 'grave']);
                document.getElementById('areaGraveSelect').disabled = true;
            }
            break;
            
        case 'area_grave':
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

// טעינת גושים
async function loadBlocks(cemeteryId) {
    const blockSelect = document.getElementById('blockSelect');
    
    let url = `${API_BASE}cemetery-hierarchy.php?action=list&type=block`;
    if (cemeteryId) {
        url += `&parent_id=${cemeteryId}`;
    }
    
    try {
        const response = await fetch(url);
        const data = await response.json();
        
        blockSelect.innerHTML = '<option value="">-- כל הגושים --</option>';
        
        if (data.success && data.data) {
            for (const block of data.data) {
                const hasGraves = await checkAvailableGraves('block', block.id);
                const option = document.createElement('option');
                option.value = block.id;
                option.textContent = block.name + (!hasGraves ? ' (אין קברים פנויים)' : '');
                if (!hasGraves) {
                    option.disabled = true;
                    option.style.color = '#999';
                }
                blockSelect.appendChild(option);
            }
        }
    } catch (error) {
        console.error('Error loading blocks:', error);
    }
}

// טעינת חלקות
async function loadPlots(cemeteryId, blockId) {
    const plotSelect = document.getElementById('plotSelect');
    
    let url = `${API_BASE}cemetery-hierarchy.php?action=list&type=plot`;
    if (blockId) {
        url += `&parent_id=${blockId}`;
    } else if (cemeteryId) {
        // טען חלקות של כל הגושים בבית העלמין
        url += `&cemetery_id=${cemeteryId}`;
    }
    
    try {
        const response = await fetch(url);
        const data = await response.json();
        
        plotSelect.innerHTML = '<option value="">-- כל החלקות --</option>';
        
        if (data.success && data.data) {
            for (const plot of data.data) {
                const hasGraves = await checkAvailableGraves('plot', plot.id);
                const option = document.createElement('option');
                option.value = plot.id;
                option.textContent = plot.name + (!hasGraves ? ' (אין קברים פנויים)' : '');
                if (!hasGraves) {
                    option.disabled = true;
                    option.style.color = '#999';
                }
                plotSelect.appendChild(option);
            }
        }
    } catch (error) {
        console.error('Error loading plots:', error);
    }
}

// טעינת שורות
async function loadRows(plotId) {
    const rowSelect = document.getElementById('rowSelect');
    
    try {
        const response = await fetch(`${API_BASE}cemetery-hierarchy.php?action=list&type=row&parent_id=${plotId}`);
        const data = await response.json();
        
        rowSelect.innerHTML = '<option value="">-- בחר שורה --</option>';
        
        if (data.success && data.data) {
            for (const row of data.data) {
                const hasGraves = await checkAvailableGraves('row', row.id);
                const option = document.createElement('option');
                option.value = row.id;
                option.textContent = row.name + (!hasGraves ? ' (אין קברים פנויים)' : '');
                if (!hasGraves) {
                    option.disabled = true;
                    option.style.color = '#999';
                }
                rowSelect.appendChild(option);
            }
        }
    } catch (error) {
        console.error('Error loading rows:', error);
    }
}

// טעינת אחוזות קבר
async function loadAreaGraves(rowId) {
    const areaGraveSelect = document.getElementById('areaGraveSelect');
    
    try {
        const response = await fetch(`${API_BASE}cemetery-hierarchy.php?action=list&type=area_grave&parent_id=${rowId}`);
        const data = await response.json();
        
        areaGraveSelect.innerHTML = '<option value="">-- בחר אחוזת קבר --</option>';
        
        if (data.success && data.data) {
            for (const areaGrave of data.data) {
                const hasGraves = await checkAvailableGraves('area_grave', areaGrave.id);
                if (hasGraves) { // מציג רק אחוזות עם קברים פנויים
                    const option = document.createElement('option');
                    option.value = areaGrave.id;
                    option.textContent = areaGrave.name;
                    areaGraveSelect.appendChild(option);
                }
            }
        }
    } catch (error) {
        console.error('Error loading area graves:', error);
    }
}

// טעינת קברים
async function loadGraves(areaGraveId) {
    const graveSelect = document.getElementById('graveSelect');
    
    try {
        const response = await fetch(`${API_BASE}cemetery-hierarchy.php?action=list&type=grave&parent_id=${areaGraveId}`);
        const data = await response.json();
        
        graveSelect.innerHTML = '<option value="">-- בחר קבר --</option>';
        
        if (data.success && data.data) {
            data.data.forEach(grave => {
                if (grave.grave_status == 1) { // רק קברים פנויים
                    const option = document.createElement('option');
                    option.value = grave.id;
                    option.textContent = `קבר ${grave.grave_number}`;
                    graveSelect.appendChild(option);
                }
            });
        }
    } catch (error) {
        console.error('Error loading graves:', error);
    }
}

// בדיקת קברים פנויים
async function checkAvailableGraves(type, id) {
    try {
        const response = await fetch(`${API_BASE}cemetery-hierarchy.php?action=check_available_graves&type=${type}&id=${id}`);
        const data = await response.json();
        return data.has_available_graves || false;
    } catch (error) {
        return false;
    }
}

// ניקוי בוררים
function clearSelectors(levels) {
    const selectors = {
        'row': { id: 'rowSelect', default: '-- בחר חלקה תחילה --', disabled: true },
        'area_grave': { id: 'areaGraveSelect', default: '-- בחר שורה תחילה --', disabled: true },
        'grave': { id: 'graveSelect', default: '-- בחר אחוזת קבר תחילה --', disabled: true }
    };
    
    levels.forEach(level => {
        const selector = selectors[level];
        if (selector) {
            const element = document.getElementById(selector.id);
            element.innerHTML = `<option value="">${selector.default}</option>`;
            element.disabled = selector.disabled;
        }
    });
}

// טעינה ראשונית - טען את כל הגושים והחלקות
window.addEventListener('DOMContentLoaded', function() {
    updateGraveSelectors('init');
});
</script>