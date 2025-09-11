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
    
    // הכן את כל הנתונים להיררכיה
    $hierarchyData = [];
    
    // טען את כל הגושים
    $blocksStmt = $conn->prepare("
        SELECT b.*, c.id as cemetery_id 
        FROM blocks b 
        INNER JOIN cemeteries c ON b.cemetery_id = c.id 
        WHERE b.is_active = 1
        ORDER BY b.name
    ");
    $blocksStmt->execute();
    $hierarchyData['blocks'] = $blocksStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // טען את כל החלקות
    $plotsStmt = $conn->prepare("
        SELECT p.*, b.cemetery_id 
        FROM plots p 
        INNER JOIN blocks b ON p.block_id = b.id 
        WHERE p.is_active = 1
        ORDER BY p.name
    ");
    $plotsStmt->execute();
    $hierarchyData['plots'] = $plotsStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // טען את כל השורות
    $rowsStmt = $conn->prepare("
        SELECT r.* 
        FROM rows r 
        WHERE r.is_active = 1
        ORDER BY r.name
    ");
    $rowsStmt->execute();
    $hierarchyData['rows'] = $rowsStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // טען את כל אחוזות הקבר
    $areaGravesStmt = $conn->prepare("
        SELECT ag.* 
        FROM area_graves ag 
        WHERE ag.is_active = 1
        ORDER BY ag.name
    ");
    $areaGravesStmt->execute();
    $hierarchyData['areaGraves'] = $areaGravesStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // טען את כל הקברים הפנויים
    $gravesStmt = $conn->prepare("
        SELECT g.* 
        FROM graves g 
        WHERE g.grave_status = 1 AND g.is_active = 1
        ORDER BY g.grave_number
    ");
    $gravesStmt->execute();
    $hierarchyData['graves'] = $gravesStmt->fetchAll(PDO::FETCH_ASSOC);
    
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
            <select id="cemeterySelect" class="form-control" onchange="filterHierarchy(\'cemetery\')">
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
            <select id="areaGraveSelect" class="form-control" onchange="filterHierarchy(\'area_grave\')" disabled>
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

// הוסף את ה-HTML המותאם אישית
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
// העבר את כל הנתונים ל-JavaScript
window.hierarchyData = <?php echo json_encode($hierarchyData); ?>;

// אתחול מיידי
(function initializeForm() {
    // מלא את הגושים והחלקות הראשוניים
    populateBlocks();
    populatePlots();
})();

// פונקציה לסינון ההיררכיה
// פונקציה לסינון ההיררכיה
window.filterHierarchy = function(level) {
    const cemetery = document.getElementById('cemeterySelect').value;
    const block = document.getElementById('blockSelect').value;
    const plot = document.getElementById('plotSelect').value;
    const row = document.getElementById('rowSelect').value;
    const areaGrave = document.getElementById('areaGraveSelect').value;
    
    switch(level) {
        case 'cemetery':
            populateBlocks(cemetery);
            populatePlots(cemetery, null);
            clearSelectors(['row', 'area_grave', 'grave']);
            break;
            
        case 'block':
            // אם נבחר גוש, בחר אוטומטית את בית העלמין שלו
            if (block) {
                const selectedBlock = window.hierarchyData.blocks.find(b => b.id == block);
                if (selectedBlock && selectedBlock.cemetery_id) {
                    // עדכן את בית העלמין
                    document.getElementById('cemeterySelect').value = selectedBlock.cemetery_id;
                    // טען גושים של בית העלמין הנבחר
                    populateBlocks(selectedBlock.cemetery_id);
                    // שמור מחדש את הגוש הנבחר
                    document.getElementById('blockSelect').value = block;
                }
            }
            populatePlots(null, block);
            clearSelectors(['row', 'area_grave', 'grave']);
            break;
            
        case 'plot':
            // אם נבחרה חלקה, בחר אוטומטית את הגוש ובית העלמין
            if (plot) {
                const selectedPlot = window.hierarchyData.plots.find(p => p.id == plot);
                if (selectedPlot) {
                    // עדכן את הגוש
                    if (selectedPlot.block_id && document.getElementById('blockSelect').value != selectedPlot.block_id) {
                        document.getElementById('blockSelect').value = selectedPlot.block_id;
                        
                        // עדכן גם את בית העלמין
                        const selectedBlock = window.hierarchyData.blocks.find(b => b.id == selectedPlot.block_id);
                        if (selectedBlock && selectedBlock.cemetery_id) {
                            document.getElementById('cemeterySelect').value = selectedBlock.cemetery_id;
                            populateBlocks(selectedBlock.cemetery_id);
                            document.getElementById('blockSelect').value = selectedPlot.block_id;
                        }
                    }
                    
                    // טען חלקות של הגוש הנבחר
                    populatePlots(null, selectedPlot.block_id);
                    document.getElementById('plotSelect').value = plot;
                }
                
                populateRows(plot);
                document.getElementById('rowSelect').disabled = false;
            } else {
                clearSelectors(['row', 'area_grave', 'grave']);
                document.getElementById('rowSelect').disabled = true;
            }
            break;
            
        case 'row':
            if (row) {
                populateAreaGraves(row);
                document.getElementById('areaGraveSelect').disabled = false;
            } else {
                clearSelectors(['area_grave', 'grave']);
                document.getElementById('areaGraveSelect').disabled = true;
            }
            break;
            
        case 'area_grave':
            if (areaGrave) {
                populateGraves(areaGrave);
                document.getElementById('graveSelect').disabled = false;
            } else {
                clearSelectors(['grave']);
                document.getElementById('graveSelect').disabled = true;
            }
            break;
    }
}

// מילוי גושים - עם בדיקת קברים פנויים
function populateBlocks(cemeteryId = null) {
    const blockSelect = document.getElementById('blockSelect');
    blockSelect.innerHTML = '<option value="">-- כל הגושים --</option>';
    
    const blocks = cemeteryId 
        ? window.hierarchyData.blocks.filter(b => b.cemetery_id == cemeteryId)
        : window.hierarchyData.blocks;
    
    blocks.forEach(block => {
        // בדוק אם יש קברים פנויים בגוש זה
        const hasAvailableGraves = checkBlockHasGraves(block.id);
        
        const option = document.createElement('option');
        option.value = block.id;
        option.textContent = block.name + (!hasAvailableGraves ? ' (אין קברים פנויים)' : '');
        
        if (!hasAvailableGraves) {
            option.disabled = true;
            option.style.color = '#999';
        }
        
        blockSelect.appendChild(option);
    });
}


// מילוי חלקות - עם בדיקת קברים פנויים
function populatePlots(cemeteryId = null, blockId = null) {
    const plotSelect = document.getElementById('plotSelect');
    plotSelect.innerHTML = '<option value="">-- כל החלקות --</option>';
    
    let plots = window.hierarchyData.plots;
    
    if (blockId) {
        plots = plots.filter(p => p.block_id == blockId);
    } else if (cemeteryId) {
        plots = plots.filter(p => p.cemetery_id == cemeteryId);
    }
    
    plots.forEach(plot => {
        // בדוק אם יש קברים פנויים בחלקה זו
        const hasAvailableGraves = checkPlotHasGraves(plot.id);
        
        const option = document.createElement('option');
        option.value = plot.id;
        option.textContent = plot.name + (!hasAvailableGraves ? ' (אין קברים פנויים)' : '');
        
        if (!hasAvailableGraves) {
            option.disabled = true;
            option.style.color = '#999';
        }
        
        plotSelect.appendChild(option);
    });
}

// פונקציות עזר לבדיקת קברים פנויים
function checkBlockHasGraves(blockId) {
    // מצא את כל החלקות של הגוש
    const blockPlots = window.hierarchyData.plots.filter(p => p.block_id == blockId);
    
    // בדוק אם יש קברים פנויים באחת החלקות
    for (let plot of blockPlots) {
        if (checkPlotHasGraves(plot.id)) {
            return true;
        }
    }
    return false;
}

function checkPlotHasGraves(plotId) {
    // מצא את כל השורות של החלקה
    const plotRows = window.hierarchyData.rows.filter(r => r.plot_id == plotId);
    
    // בדוק אם יש קברים פנויים באחת השורות
    for (let row of plotRows) {
        const rowAreaGraves = window.hierarchyData.areaGraves.filter(ag => ag.row_id == row.id);
        
        for (let areaGrave of rowAreaGraves) {
            const graves = window.hierarchyData.graves.filter(g => g.area_grave_id == areaGrave.id);
            if (graves.length > 0) {
                return true;
            }
        }
    }
    return false;
}

// מילוי שורות - רק עם קברים פנויים
function populateRows(plotId) {
    const rowSelect = document.getElementById('rowSelect');
    rowSelect.innerHTML = '<option value="">-- בחר שורה --</option>';
    
    const rows = window.hierarchyData.rows.filter(r => r.plot_id == plotId);
    
    rows.forEach(row => {
        // בדוק אם יש קברים פנויים בשורה זו
        const hasAvailableGraves = checkRowHasGraves(row.id);
        
        if (hasAvailableGraves) {
            const option = document.createElement('option');
            option.value = row.id;
            option.textContent = row.name;
            rowSelect.appendChild(option);
        }
    });
    
    // אם אין שורות עם קברים פנויים
    if (rowSelect.options.length === 1) {
        rowSelect.innerHTML = '<option value="">-- אין שורות עם קברים פנויים --</option>';
    }
}

// פונקציית עזר לבדיקת קברים פנויים בשורה
function checkRowHasGraves(rowId) {
    const rowAreaGraves = window.hierarchyData.areaGraves.filter(ag => ag.row_id == rowId);
    
    for (let areaGrave of rowAreaGraves) {
        const graves = window.hierarchyData.graves.filter(g => g.area_grave_id == areaGrave.id);
        if (graves.length > 0) {
            return true;
        }
    }
    return false;
}

// מילוי אחוזות קבר - רק עם קברים פנויים
function populateAreaGraves(rowId) {
    const areaGraveSelect = document.getElementById('areaGraveSelect');
    areaGraveSelect.innerHTML = '<option value="">-- בחר אחוזת קבר --</option>';
    
    const areaGraves = window.hierarchyData.areaGraves.filter(ag => ag.row_id == rowId);
    
    areaGraves.forEach(areaGrave => {
        // בדוק אם יש קברים פנויים באחוזת קבר זו
        const availableGraves = window.hierarchyData.graves.filter(g => g.area_grave_id == areaGrave.id);
        
        // הצג רק אחוזות קבר עם קברים פנויים
        if (availableGraves.length > 0) {
            const option = document.createElement('option');
            option.value = areaGrave.id;
            option.textContent = areaGrave.name + ` (${availableGraves.length} קברים פנויים)`;
            areaGraveSelect.appendChild(option);
        }
    });
    
    // אם אין אחוזות קבר עם קברים פנויים
    if (areaGraveSelect.options.length === 1) {
        areaGraveSelect.innerHTML = '<option value="">-- אין אחוזות קבר פנויות --</option>';
    }
}

// מילוי קברים
function populateGraves(areaGraveId) {
    const graveSelect = document.getElementById('graveSelect');
    graveSelect.innerHTML = '<option value="">-- בחר קבר --</option>';
    
    const graves = window.hierarchyData.graves.filter(g => g.area_grave_id == areaGraveId);
    
    graves.forEach(grave => {
        const option = document.createElement('option');
        option.value = grave.id;
        option.textContent = `קבר ${grave.grave_number}`;
        graveSelect.appendChild(option);
    });
}

// ניקוי בוררים
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
            if (element) {
                element.innerHTML = `<option value="">${config.default}</option>`;
                element.disabled = config.disabled;
            }
        }
    });
}
</script>

<script>
    // משתנים גלובליים לתשלומים
    window.purchasePayments = <?php echo $purchase ? json_encode(json_decode($purchase['payments_data'] ?? '[]', true)) : '[]'; ?>;

    // פתיחת מנהל תשלומים
    window.openPaymentsManager = function() {
        const modal = document.createElement('div');
        modal.id = 'paymentsManagerModal';
        modal.className = 'modal-overlay';
        modal.style.cssText = `
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.5);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 10001;
        `;
        
        modal.innerHTML = `
            <div class="modal-content" style="
                background: white;
                padding: 30px;
                border-radius: 8px;
                width: 600px;
                max-height: 80vh;
                overflow-y: auto;
            ">
                <h3 style="margin-bottom: 20px;">ניהול תשלומים</h3>
                
                <form onsubmit="addPayment(event)">
                    <div style="display: grid; grid-template-columns: 2fr 1fr 1fr; gap: 10px; margin-bottom: 20px;">
                        <div>
                            <label style="display: block; margin-bottom: 5px;">סוג תשלום</label>
                            <select id="payment_type" required style="
                                width: 100%;
                                padding: 8px;
                                border: 1px solid #ddd;
                                border-radius: 4px;
                            ">
                                <option value="">-- בחר --</option>
                                <option value="grave_cost">עלות קבר</option>
                                <option value="service_cost">עלות שירות</option>
                                <option value="tombstone_cost">עלות מצבה</option>
                                <option value="maintenance">תחזוקה</option>
                                <option value="other">אחר</option>
                            </select>
                        </div>
                        <div>
                            <label style="display: block; margin-bottom: 5px;">סכום</label>
                            <input type="number" id="payment_amount" step="0.01" required style="
                                width: 100%;
                                padding: 8px;
                                border: 1px solid #ddd;
                                border-radius: 4px;
                            ">
                        </div>
                        <div>
                            <button type="submit" style="
                                margin-top: 24px;
                                padding: 8px 15px;
                                background: #28a745;
                                color: white;
                                border: none;
                                border-radius: 4px;
                                cursor: pointer;
                                width: 100%;
                            ">הוסף</button>
                        </div>
                    </div>
                </form>
                
                <div id="paymentsList" style="
                    max-height: 300px;
                    overflow-y: auto;
                    margin-bottom: 20px;
                ">
                    ${displayPaymentsList()}
                </div>
                
                <div style="
                    padding: 10px;
                    background: #f8f9fa;
                    border-radius: 4px;
                    margin-bottom: 20px;
                    font-weight: bold;
                ">
                    סה"כ: ₪<span id="paymentsTotal">${calculatePaymentsTotal()}</span>
                </div>
                
                <div style="display: flex; gap: 10px; justify-content: flex-end;">
                    <button onclick="closePaymentsManager()" style="
                        padding: 10px 30px;
                        background: #667eea;
                        color: white;
                        border: none;
                        border-radius: 4px;
                        cursor: pointer;
                    ">אישור</button>
                </div>
            </div>
        `;
        
        document.body.appendChild(modal);
    }

    // סגירת מנהל תשלומים
    window.closePaymentsManager = function() {
        const modal = document.getElementById('paymentsManagerModal');
        if (modal) {
            modal.remove();
            
            // עדכן את הסכום הכולל
            document.getElementById('total_price').value = calculatePaymentsTotal();
            
            // עדכן את התצוגה
            document.getElementById('paymentsDisplay').innerHTML = displayPaymentsSummary();
            
            // עדכן את ה-JSON
            document.getElementById('payments_data').value = JSON.stringify(window.purchasePayments);
        }
    }

    // הוספת תשלום
    window.addPayment = function(event) {
        event.preventDefault();
        
        const type = document.getElementById('payment_type').value;
        const amount = parseFloat(document.getElementById('payment_amount').value);
        
        const typeNames = {
            'grave_cost': 'עלות קבר',
            'service_cost': 'עלות שירות',
            'tombstone_cost': 'עלות מצבה',
            'maintenance': 'תחזוקה',
            'other': 'אחר'
        };
        
        window.purchasePayments.push({
            type: type,
            type_name: typeNames[type],
            amount: amount,
            date: new Date().toISOString()
        });
        
        // רענן את התצוגה
        document.getElementById('paymentsList').innerHTML = displayPaymentsList();
        document.getElementById('paymentsTotal').textContent = calculatePaymentsTotal();
        
        // נקה את הטופס
        document.getElementById('payment_type').value = '';
        document.getElementById('payment_amount').value = '';
    }

    // הסרת תשלום
    window.removePayment = function(index) {
        window.purchasePayments.splice(index, 1);
        document.getElementById('paymentsList').innerHTML = displayPaymentsList();
        document.getElementById('paymentsTotal').textContent = calculatePaymentsTotal();
    }

    // הצגת רשימת תשלומים
    window.displayPaymentsList = function() {
        if (window.purchasePayments.length === 0) {
            return '<p style="text-align: center; color: #999;">אין תשלומים</p>';
        }
        
        return `
            <table style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr style="background: #f8f9fa;">
                        <th style="padding: 8px; text-align: right; border-bottom: 1px solid #ddd;">סוג</th>
                        <th style="padding: 8px; text-align: right; border-bottom: 1px solid #ddd;">סכום</th>
                        <th style="padding: 8px; text-align: center; border-bottom: 1px solid #ddd;">פעולה</th>
                    </tr>
                </thead>
                <tbody>
                    ${window.purchasePayments.map((payment, index) => `
                        <tr>
                            <td style="padding: 8px; border-bottom: 1px solid #eee;">${payment.type_name}</td>
                            <td style="padding: 8px; border-bottom: 1px solid #eee;">₪${payment.amount.toFixed(2)}</td>
                            <td style="padding: 8px; border-bottom: 1px solid #eee; text-align: center;">
                                <button onclick="removePayment(${index})" style="
                                    background: #dc3545;
                                    color: white;
                                    border: none;
                                    padding: 4px 8px;
                                    border-radius: 4px;
                                    cursor: pointer;
                                ">הסר</button>
                            </td>
                        </tr>
                    `).join('')}
                </tbody>
            </table>
        `;
    }

    // הצגת סיכום תשלומים
    window.displayPaymentsSummary = function() {
        if (window.purchasePayments.length === 0) {
            return '<p style="color: #999;">לא הוגדרו תשלומים</p>';
        }
        
        const summary = {};
        window.purchasePayments.forEach(payment => {
            if (!summary[payment.type_name]) {
                summary[payment.type_name] = 0;
            }
            summary[payment.type_name] += payment.amount;
        });
        
        return Object.entries(summary).map(([type, amount]) => 
            `${type}: ₪${amount.toFixed(2)}`
        ).join(' | ') + `<br><strong>סה"כ: ₪${calculatePaymentsTotal()}</strong>`;
    }

    // חישוב סכום כולל
    window.calculatePaymentsTotal = function() {
        return window.purchasePayments.reduce((total, payment) => total + payment.amount, 0).toFixed(2);
    }
</script>