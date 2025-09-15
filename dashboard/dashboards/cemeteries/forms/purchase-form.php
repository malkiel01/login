<?php
    // forms/purchase-form.php

    error_reporting(E_ALL);
    ini_set('display_errors', 1);
    header('Content-Type: text/html; charset=utf-8');

    require_once __DIR__ . '/FormBuilder.php';
    require_once $_SERVER['DOCUMENT_ROOT'] . '/dashboard/dashboards/cemeteries/config.php';

    $itemId = $_GET['item_id'] ?? null;
    $parentId = $_GET['parent_id'] ?? null;


    try {
        $conn = getDBConnection();
        
        // טען לקוחות פנויים
        $customersStmt = $conn->prepare("
            SELECT unicId, CONCAT(lastName, ' ', firstName) as full_name, numId 
            FROM customers 
            WHERE statusCustomer = 1 AND isActive = 1 
            ORDER BY lastName, firstName
        ");

        $customersStmt->execute();
        $customers = [];

        while ($row = $customersStmt->fetch(PDO::FETCH_ASSOC)) {
            $label = $row['full_name'];
            if ($row['numId']) {
                $label .= ' (' . $row['numId'] . ')';
            }
            $customers[$row['unicId']] = $label; // שים לב - משתמשים ב-unicId
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
                AND g.isActive = 1
            ) as has_available_graves
            FROM cemeteries c
            WHERE c.isActive = 1
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
            WHERE b.isActive = 1
            ORDER BY b.name
        ");
        $blocksStmt->execute();
        $hierarchyData['blocks'] = $blocksStmt->fetchAll(PDO::FETCH_ASSOC);
        
        // טען את כל החלקות
        $plotsStmt = $conn->prepare("
            SELECT p.*, b.cemetery_id 
            FROM plots p 
            INNER JOIN blocks b ON p.block_id = b.id 
            WHERE p.isActive = 1
            ORDER BY p.name
        ");
        $plotsStmt->execute();
        $hierarchyData['plots'] = $plotsStmt->fetchAll(PDO::FETCH_ASSOC);
        
        // טען את כל השורות
        $rowsStmt = $conn->prepare("
            SELECT r.* 
            FROM rows r 
            WHERE r.isActive = 1
            ORDER BY r.name
        ");
        $rowsStmt->execute();
        $hierarchyData['rows'] = $rowsStmt->fetchAll(PDO::FETCH_ASSOC);
        
        // טען את כל אחוזות הקבר
        $areaGravesStmt = $conn->prepare("
            SELECT ag.* 
            FROM area_graves ag 
            WHERE ag.isActive = 1
            ORDER BY ag.name
        ");
        $areaGravesStmt->execute();
        $hierarchyData['areaGraves'] = $areaGravesStmt->fetchAll(PDO::FETCH_ASSOC);
        
        // טען את כל הקברים הפנויים
        $gravesStmt = $conn->prepare("
            SELECT g.* 
            FROM graves g 
            WHERE g.grave_status = 1 AND g.isActive = 1
            ORDER BY g.grave_number
        ");
        $gravesStmt->execute();
        $hierarchyData['graves'] = $gravesStmt->fetchAll(PDO::FETCH_ASSOC);
        
        // טען רכישה אם קיימת
        $purchase = null;
        if ($itemId) {
            $stmt = $conn->prepare("SELECT * FROM purchases WHERE unicId = ? AND isActive = 1");
            $stmt->execute([$itemId]);
            $purchase = $stmt->fetch(PDO::FETCH_ASSOC);
        }
        
    } catch (Exception $e) {
        die("שגיאה: " . $e->getMessage());
    }

    // יצירת FormBuilder
    $formBuilder = new FormBuilder('purchase', $itemId, $parentId);

    // הוספת שדה לקוח
    $formBuilder->addField('clientId', 'לקוח', 'select', [
        'required' => true,
        'options' => $customers,
        'value' => $purchase['clientId'] ?? ''
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
                <select name="graveId" id="graveSelect" class="form-control" required disabled onchange="onGraveSelected(this.value)">
                    <option value="">-- בחר אחוזת קבר תחילה --</option>
                </select>
            </div>
        </div>
    </fieldset>';

    // הוסף את ה-HTML המותאם אישית
    $formBuilder->addCustomHTML($graveSelectorHTML);

    // המשך השדות
    $formBuilder->addField('purchaseStatus', 'סטטוס רכישה', 'select', [
        'options' => [
            1 => 'פתוח',
            2 => 'שולם', 
            3 => 'סגור',
            4 => 'בוטל'
        ],
        'value' => $purchase['purchaseStatus'] ?? 1
    ]);

    // HTML מותאם אישית לניהול תשלומים חכם
    $paymentsHTML = '
    <fieldset class="form-section" style="border: 1px solid #e2e8f0; border-radius: 8px; padding: 15px; margin-bottom: 20px;">
        <legend style="padding: 0 10px; font-weight: bold;">תשלומים</legend>
        
        <!-- הצגת פרמטרים נבחרים -->
        <div id="selectedParameters" style="background: #f0f9ff; padding: 10px; border-radius: 5px; margin-bottom: 15px; display: none;">
            <div style="font-size: 12px; color: #666;">פרמטרים לחישוב:</div>
            <div id="parametersDisplay" style="margin-top: 5px;"></div>
        </div>
        
        <!-- סכום כולל -->
        <div style="margin-bottom: 15px;">
            <label>סכום כולל</label>
            <input type="number" name="price" id="total_price" 
                value="' . ($purchase['price'] ?? '0') . '" 
                step="0.01" readonly
                style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; background: #f8f9fa; font-size: 18px; font-weight: bold;">
        </div>
        
        <!-- כפתורי ניהול תשלומים -->
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
            <button type="button" onclick="openPaymentsManager()" style="
                padding: 10px 20px;
                background: #6c757d;
                color: white;
                border: none;
                border-radius: 4px;
                cursor: pointer;
            ">
                ניהול תשלומים ידני
            </button>
            
            <button type="button" onclick="openSmartPaymentsManager()" style="
                padding: 10px 20px;
                background: #17a2b8;
                color: white;
                border: none;
                border-radius: 4px;
                cursor: pointer;
            ">
                <span id="paymentsButtonText">חשב תשלומים אוטומטית</span>
            </button>
        </div>
        
        <!-- תצוגת פירוט תשלומים -->
        <div id="paymentsDisplay" style="
            background: #f8f9fa;
            padding: 10px;
            border-radius: 4px;
            min-height: 50px;
            margin-top: 15px;
        ">' . 
        ($purchase && $purchase['payments_data'] ? 
            '<script>document.write(displayPaymentsSummary())</script>' : 
            '<p style="color: #999; text-align: center;">לחץ על אחד הכפתורים לניהול תשלומים</p>') . 
        '</div>
        
        <input type="hidden" name="payments_data" id="payments_data" 
            value=\'' . ($purchase['payments_data'] ?? '[]') . '\'>
    </fieldset>';

    $formBuilder->addCustomHTML($paymentsHTML);

    $formBuilder->addField('numOfPayments', 'מספר תשלומים', 'number', [
        'min' => 1,
        'value' => $purchase['numOfPayments'] ?? 1
    ]);

    $formBuilder->addField('PaymentEndDate', 'תאריך סיום תשלומים', 'date', [
        'value' => $purchase['PaymentEndDate'] ?? ''
    ]);

    $formBuilder->addField('comment', 'הערות', 'textarea', [
        'rows' => 3,
        'value' => $purchase['comment'] ?? ''
    ]);

    // אם זה עריכה, הוסף את ה-unicId כשדה מוסתר
    if ($purchase && $purchase['unicId']) {
        $formBuilder->addField('unicId', '', 'hidden', [
            'value' => $purchase['unicId']
        ]);
    }

//     // הצג את הטופס
//     echo $formBuilder->renderModal();


// // דיבוג - בדוק מה מוחזר
// if (strpos($modalHTML, 'purchaseFormModal') === false) {
//     // אם אין את ה-ID הנכון, עטוף ידנית
//     echo '<div id="purchaseFormModal" class="modal fade" tabindex="-1">';
//     echo $modalHTML;
//     echo '</div>';
// } else {
//     echo $modalHTML;
// }

// הצג את הטופס
$modalHTML = $formBuilder->renderModal();

// דיבוג - בדוק מה מוחזר
if (strpos($modalHTML, 'purchaseFormModal') === false) {
    // אם אין את ה-ID הנכון, עטוף ידנית
    echo '<div id="purchaseFormModal" class="modal fade" tabindex="-1">';
    echo $modalHTML;
    echo '</div>';
} else {
    echo $modalHTML;
}

?>

<script>
    // העבר את כל הנתונים ל-JavaScript
    window.hierarchyData = <?php echo json_encode($hierarchyData); ?>;

    // אתחול מיידי
    (function initializeForm() {
        populateBlocks();
        populatePlots();
    })();

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
                if (block) {
                    const selectedBlock = window.hierarchyData.blocks.find(b => b.id == block);
                    if (selectedBlock && selectedBlock.cemetery_id) {
                        document.getElementById('cemeterySelect').value = selectedBlock.cemetery_id;
                        populateBlocks(selectedBlock.cemetery_id);
                        document.getElementById('blockSelect').value = block;
                    }
                }
                populatePlots(null, block);
                clearSelectors(['row', 'area_grave', 'grave']);
                break;
                
            case 'plot':
                if (plot) {
                    const selectedPlot = window.hierarchyData.plots.find(p => p.id == plot);
                    if (selectedPlot) {
                        if (selectedPlot.block_id && document.getElementById('blockSelect').value != selectedPlot.block_id) {
                            document.getElementById('blockSelect').value = selectedPlot.block_id;
                            
                            const selectedBlock = window.hierarchyData.blocks.find(b => b.id == selectedPlot.block_id);
                            if (selectedBlock && selectedBlock.cemetery_id) {
                                document.getElementById('cemeterySelect').value = selectedBlock.cemetery_id;
                                populateBlocks(selectedBlock.cemetery_id);
                                document.getElementById('blockSelect').value = selectedPlot.block_id;
                            }
                        }
                        
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

    // מילוי גושים
    function populateBlocks(cemeteryId = null) {
        const blockSelect = document.getElementById('blockSelect');
        blockSelect.innerHTML = '<option value="">-- כל הגושים --</option>';
        
        const blocks = cemeteryId 
            ? window.hierarchyData.blocks.filter(b => b.cemetery_id == cemeteryId)
            : window.hierarchyData.blocks;
        
        blocks.forEach(block => {
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

    // מילוי חלקות
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

    // בדיקת קברים פנויים בגוש
    function checkBlockHasGraves(blockId) {
        const blockPlots = window.hierarchyData.plots.filter(p => p.block_id == blockId);
        
        for (let plot of blockPlots) {
            if (checkPlotHasGraves(plot.id)) {
                return true;
            }
        }
        return false;
    }

    // בדיקת קברים פנויים בחלקה
    function checkPlotHasGraves(plotId) {
        const plotRows = window.hierarchyData.rows.filter(r => r.plot_id == plotId);
        
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

    // מילוי שורות
    function populateRows(plotId) {
        const rowSelect = document.getElementById('rowSelect');
        rowSelect.innerHTML = '<option value="">-- בחר שורה --</option>';
        
        const rows = window.hierarchyData.rows.filter(r => r.plot_id == plotId);
        
        rows.forEach(row => {
            const hasAvailableGraves = checkRowHasGraves(row.id);
            
            if (hasAvailableGraves) {
                const option = document.createElement('option');
                option.value = row.id;
                option.textContent = row.name;
                rowSelect.appendChild(option);
            }
        });
        
        if (rowSelect.options.length === 1) {
            rowSelect.innerHTML = '<option value="">-- אין שורות עם קברים פנויים --</option>';
        }
    }

    // בדיקת קברים פנויים בשורה
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

    // מילוי אחוזות קבר
    function populateAreaGraves(rowId) {
        const areaGraveSelect = document.getElementById('areaGraveSelect');
        areaGraveSelect.innerHTML = '<option value="">-- בחר אחוזת קבר --</option>';
        
        const areaGraves = window.hierarchyData.areaGraves.filter(ag => ag.row_id == rowId);
        
        areaGraves.forEach(areaGrave => {
            const availableGraves = window.hierarchyData.graves.filter(g => g.area_grave_id == areaGrave.id);
            
            if (availableGraves.length > 0) {
                const option = document.createElement('option');
                option.value = areaGrave.id;
                option.textContent = areaGrave.name + ` (${availableGraves.length} קברים פנויים)`;
                areaGraveSelect.appendChild(option);
            }
        });
        
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

    // כשנבחר קבר
    function onGraveSelected(graveId) {
        if (graveId) {
            // מצא את פרטי הקבר
            const grave = window.hierarchyData.graves.find(g => g.id == graveId);
            if (grave) {
                // עדכן את הפרמטרים לתשלומים החכמים
                window.selectedGraveData = {
                    graveId: graveId,
                    plotType: grave.plot_type || 1,
                    graveType: grave.grave_type || 1
                };
                
                // הצג פרמטרים
                updatePaymentParameters();
            }
        } else {
            window.selectedGraveData = null;
            document.getElementById('selectedParameters').style.display = 'none';
        }
    }

    // עדכון תצוגת פרמטרים
    function updatePaymentParameters() {
        if (window.selectedGraveData) {
            const plotTypes = {1: 'פטורה', 2: 'חריגה', 3: 'סגורה'};
            const graveTypes = {1: 'שדה', 2: 'רוויה', 3: 'סנהדרין'};
            
            document.getElementById('parametersDisplay').innerHTML = `
                <span style="margin-right: 10px;">📍 חלקה: ${plotTypes[window.selectedGraveData.plotType] || 'לא ידוע'}</span>
                <span style="margin-right: 10px;">⚰️ סוג קבר: ${graveTypes[window.selectedGraveData.graveType] || 'לא ידוע'}</span>
                <span>👤 תושב: ירושלים</span>
            `;
            document.getElementById('selectedParameters').style.display = 'block';
            document.getElementById('paymentsButtonText').textContent = 'חשב מחדש תשלומים';
        }
    }

    // משתנים גלובליים לתשלומים
    window.purchasePayments = <?php echo $purchase ? json_encode(json_decode($purchase['payments_data'] ?? '[]', true)) : '[]'; ?>;
    window.selectedGraveData = null;

    // פתיחת מנהל תשלומים חכם
    window.openSmartPaymentsManager = async function() {
        const graveSelect = document.getElementById('graveSelect');
        const graveId = graveSelect ? graveSelect.value : null;
        
        if (!graveId || !window.selectedGraveData) {
            alert('יש לבחור קבר תחילה');
            return;
        }
        
        // טען תשלומים מתאימים מהשרת
        try {
            const response = await fetch('/dashboard/dashboards/cemeteries/api/payments-api.php?action=getMatching', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({
                    plotType: window.selectedGraveData.plotType,
                    graveType: window.selectedGraveData.graveType,
                    resident: 1, // תושב ירושלים
                    buyerStatus: document.querySelector('[name="buyer_status"]').value || null
                })
            });
            
            const data = await response.json();
            
            if (data.success && data.payments.length > 0) {
                // הצג את התשלומים שנמצאו
                showSmartPaymentsModal(data.payments);
            } else {
                alert('לא נמצאו הגדרות תשלום מתאימות. השתמש בניהול ידני.');
                openPaymentsManager();
            }
        } catch (error) {
            console.error('Error loading payments:', error);
            openPaymentsManager();
        }
    };

    function showSmartPaymentsModal(availablePayments) {
        // חלק את התשלומים לחובה ואופציונלי
        const mandatoryPayments = availablePayments.filter(p => p.mandatory);
        const optionalPayments = availablePayments.filter(p => !p.mandatory);
        
        // יצירת המודל
        const modal = document.createElement('div');
        modal.id = 'smartPaymentsModal';
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
        
        // חשב סכום התחלתי (רק תשלומי חובה)
        let currentTotal = mandatoryPayments.reduce((sum, p) => sum + parseFloat(p.price || 0), 0);
        
        modal.innerHTML = `
            <div class="modal-content" style="
                background: white;
                padding: 30px;
                border-radius: 8px;
                width: 700px;
                max-height: 90vh;
                overflow-y: auto;
                margin: 20px;
            ">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                    <h3 style="margin: 0;">חישוב תשלומים אוטומטי</h3>
                    <button onclick="document.getElementById('smartPaymentsModal').remove()" style="
                        background: none;
                        border: none;
                        font-size: 24px;
                        cursor: pointer;
                    ">×</button>
                </div>
                
                <!-- הצגת הפרמטרים -->
                <div style="background: #e3f2fd; padding: 10px; border-radius: 5px; margin-bottom: 20px;">
                    <strong>פרמטרים נבחרים:</strong><br>
                    סוג חלקה: ${window.selectedGraveData.plotType == 1 ? 'פטורה' : window.selectedGraveData.plotType == 2 ? 'חריגה' : 'סגורה'} | 
                    סוג קבר: ${window.selectedGraveData.graveType == 1 ? 'שדה' : window.selectedGraveData.graveType == 2 ? 'רוויה' : 'סנהדרין'} | 
                    תושבות: ירושלים
                </div>
                
                ${mandatoryPayments.length > 0 ? `
                    <!-- תשלומי חובה -->
                    <div style="margin-bottom: 20px;">
                        <h4 style="color: #dc3545; margin-bottom: 10px;">
                            <span style="background: #ffc107; padding: 2px 8px; border-radius: 3px;">חובה</span>
                            תשלומים הכרחיים
                        </h4>
                        <div style="border: 2px solid #ffc107; background: #fffbf0; padding: 15px; border-radius: 5px;">
                            ${mandatoryPayments.map(payment => `
                                <div style="padding: 8px 0; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #ffe5b4;">
                                    <label style="display: flex; align-items: center;">
                                        <input type="checkbox" checked disabled style="margin-left: 10px;">
                                        <span style="font-weight: bold; margin-right: 10px;">${payment.name}</span>
                                    </label>
                                    <span style="font-weight: bold; color: #dc3545;">₪${parseFloat(payment.price).toLocaleString()}</span>
                                </div>
                            `).join('')}
                        </div>
                    </div>
                ` : ''}
                
                ${optionalPayments.length > 0 ? `
                    <!-- תשלומים אופציונליים -->
                    <div style="margin-bottom: 20px;">
                        <h4 style="color: #28a745; margin-bottom: 10px;">
                            <span style="background: #d4edda; padding: 2px 8px; border-radius: 3px;">אופציונלי</span>
                            תשלומים נוספים
                        </h4>
                        <div style="border: 1px solid #28a745; background: #f0fff4; padding: 15px; border-radius: 5px;">
                            ${optionalPayments.map((payment, index) => `
                                <div style="padding: 8px 0; display: flex; justify-content: space-between; align-items: center; ${index < optionalPayments.length - 1 ? 'border-bottom: 1px solid #c3e6cb;' : ''}">
                                    <label style="display: flex; align-items: center; cursor: pointer;">
                                        <input type="checkbox" 
                                            id="optional_${payment.id || index}"
                                            data-price="${payment.price}"
                                            data-name="${payment.name}"
                                            data-definition="${payment.priceDefinition}"
                                            onchange="updateSmartTotal()"
                                            style="margin-left: 10px;">
                                        <span style="margin-right: 10px;">${payment.name}</span>
                                    </label>
                                    <span>₪${parseFloat(payment.price).toLocaleString()}</span>
                                </div>
                            `).join('')}
                        </div>
                    </div>
                ` : ''}
                
                <!-- סיכום -->
                <div style="background: #f8f9fa; padding: 20px; border-radius: 5px; margin: 20px 0; text-align: center;">
                    <div style="font-size: 24px; font-weight: bold;">
                        סה"כ לתשלום: ₪<span id="smartModalTotal">${currentTotal.toLocaleString()}</span>
                    </div>
                    <div style="font-size: 12px; color: #666; margin-top: 5px;">
                        כולל ${mandatoryPayments.length} תשלומי חובה
                        <span id="optionalCount"></span>
                    </div>
                </div>
                
                <!-- כפתורים -->
                <div style="display: flex; gap: 10px; justify-content: space-between;">
                    <button onclick="addCustomPaymentInSmart()" style="
                        padding: 10px 20px;
                        background: #6c757d;
                        color: white;
                        border: none;
                        border-radius: 4px;
                        cursor: pointer;
                    ">+ הוסף תשלום מותאם</button>
                    
                    <div style="display: flex; gap: 10px;">
                        <button onclick="document.getElementById('smartPaymentsModal').remove()" style="
                            padding: 10px 30px;
                            background: #dc3545;
                            color: white;
                            border: none;
                            border-radius: 4px;
                            cursor: pointer;
                        ">ביטול</button>
                        <button onclick="applySmartPayments(${JSON.stringify(mandatoryPayments).replace(/"/g, '&quot;')})" style="
                            padding: 10px 30px;
                            background: #28a745;
                            color: white;
                            border: none;
                            border-radius: 4px;
                            cursor: pointer;
                            font-weight: bold;
                        ">אישור ושמירה</button>
                    </div>
                </div>
            </div>
        `;
        
        document.body.appendChild(modal);
    }

    // עדכון הסכום הכולל במודל החכם
    window.updateSmartTotal2 = function() {
        let total = 0;
        let optionalCount = 0;
        
        // סכום תשלומי חובה
        const modal = document.getElementById('smartPaymentsModal');
        const mandatoryCheckboxes = modal.querySelectorAll('input[type="checkbox"]:disabled:checked');
        mandatoryCheckboxes.forEach(cb => {
            const row = cb.closest('div');
            const priceText = row.querySelector('span:last-child').textContent;
            const price = parseFloat(priceText.replace('₪', '').replace(',', ''));
            total += price;
        });
        
        // סכום תשלומים אופציונליים שנבחרו
        const optionalCheckboxes = modal.querySelectorAll('input[type="checkbox"]:not(:disabled):checked');
        optionalCheckboxes.forEach(cb => {
            total += parseFloat(cb.dataset.price);
            optionalCount++;
        });
        
        document.getElementById('smartModalTotal').textContent = total.toLocaleString();
        
        const optionalText = optionalCount > 0 ? ` + ${optionalCount} תשלומים נוספים` : '';
        document.getElementById('optionalCount').textContent = optionalText;
    }
    // עדכון הסכום הכולל במודל החכם
    window.updateSmartTotal3 = function() {
        let total = 0;
        let optionalCount = 0;
        
        // סכום תשלומי חובה
        const modal = document.getElementById('smartPaymentsModal');
        const mandatoryCheckboxes = modal.querySelectorAll('input[type="checkbox"]:disabled:checked');
        mandatoryCheckboxes.forEach(cb => {
            const row = cb.closest('div');
            const priceText = row.querySelector('span:last-child').textContent;
            // תיקון: הסר את סמל המטבע ופסיקים לפני המרה למספר
            const price = parseFloat(priceText.replace('₪', '').replace(/,/g, ''));
            if (!isNaN(price)) {
                total += price;
            }
        });
        
        // סכום תשלומים אופציונליים שנבחרו
        const optionalCheckboxes = modal.querySelectorAll('input[type="checkbox"]:not(:disabled):checked');
        optionalCheckboxes.forEach(cb => {
            const price = parseFloat(cb.dataset.price);
            if (!isNaN(price)) {
                total += price;
                optionalCount++;
            }
        });
        
        document.getElementById('smartModalTotal').textContent = total.toLocaleString();
        
        const optionalText = optionalCount > 0 ? ` + ${optionalCount} תשלומים נוספים` : '';
        document.getElementById('optionalCount').textContent = optionalText;
    }
    // עדכון הסכום הכולל במודל החכם - גרסה מתוקנת
    window.updateSmartTotal = function() {
        let total = 0;
        let optionalCount = 0;
        
        const modal = document.getElementById('smartPaymentsModal');
        if (!modal) return;
        
        // סכום תשלומי חובה
        const mandatoryCheckboxes = modal.querySelectorAll('input[type="checkbox"]:disabled:checked');
        mandatoryCheckboxes.forEach(cb => {
            // חפש את המחיר בתוך אותו div של הצ'קבוקס
            const parentDiv = cb.closest('div[style*="padding"]');
            if (parentDiv) {
                // חפש את כל ה-spans בתוך ה-div
                const spans = parentDiv.querySelectorAll('span');
                // המחיר נמצא בדרך כלל ב-span האחרון
                const priceSpan = spans[spans.length - 1];
                if (priceSpan) {
                    const priceText = priceSpan.textContent;
                    // הסר סמל מטבע, פסיקים ורווחים
                    const cleanPrice = priceText.replace(/[₪,\s]/g, '');
                    const price = parseFloat(cleanPrice);
                    
                    console.log('Mandatory payment found:', priceText, '→', price); // דיבוג
                    
                    if (!isNaN(price)) {
                        total += price;
                    }
                }
            }
        });
        
        // סכום תשלומים אופציונליים שנבחרו
        const optionalCheckboxes = modal.querySelectorAll('input[type="checkbox"]:not(:disabled):checked');
        optionalCheckboxes.forEach(cb => {
            const price = parseFloat(cb.dataset.price);
            
            console.log('Optional payment:', cb.dataset.name, '→', price); // דיבוג
            
            if (!isNaN(price)) {
                total += price;
                optionalCount++;
            }
        });
        
        console.log('Total calculated:', total); // דיבוג
        
        // עדכן התצוגה
        const totalElement = document.getElementById('smartModalTotal');
        if (totalElement) {
            totalElement.textContent = total.toLocaleString();
        }
        
        const optionalCountElement = document.getElementById('optionalCount');
        if (optionalCountElement) {
            const optionalText = optionalCount > 0 ? ` + ${optionalCount} תשלומים נוספים` : '';
            optionalCountElement.textContent = optionalText;
        }
    }

    // החלת התשלומים שנבחרו - הגדר כפונקציה גלובלית
    window.applySmartPayments = function(mandatoryPaymentsJSON) {
        // פענח את ה-JSON אם צריך
        let mandatoryPayments;
        if (typeof mandatoryPaymentsJSON === 'string') {
            try {
                mandatoryPayments = JSON.parse(mandatoryPaymentsJSON.replace(/&quot;/g, '"'));
            } catch (e) {
                console.error('Error parsing mandatory payments:', e);
                mandatoryPayments = [];
            }
        } else {
            mandatoryPayments = mandatoryPaymentsJSON || [];
        }
        
        // נקה תשלומים קיימים
        window.purchasePayments = [];
        
        // הוסף תשלומי חובה
        mandatoryPayments.forEach(payment => {
            window.purchasePayments.push({
                type: 'auto_' + payment.priceDefinition,
                type_name: payment.name,
                amount: parseFloat(payment.price),
                mandatory: true,
                date: new Date().toISOString()
            });
        });
        
        // הוסף תשלומים אופציונליים שנבחרו
        const modal = document.getElementById('smartPaymentsModal');
        if (modal) {
            const selectedOptional = modal.querySelectorAll('input[type="checkbox"]:not(:disabled):checked');
            selectedOptional.forEach(cb => {
                window.purchasePayments.push({
                    type: cb.dataset.custom ? 'custom' : 'auto_' + cb.dataset.definition,
                    type_name: cb.dataset.name,
                    amount: parseFloat(cb.dataset.price),
                    mandatory: false,
                    custom: cb.dataset.custom === 'true',
                    date: new Date().toISOString()
                });
            });
        }
        
        // עדכן תצוגה בטופס הראשי
        document.getElementById('total_price').value = calculatePaymentsTotal();
        document.getElementById('paymentsDisplay').innerHTML = displayPaymentsSummary();
        document.getElementById('payments_data').value = JSON.stringify(window.purchasePayments);
        
        // סגור מודל
        if (modal) {
            modal.remove();
        }
        
        // הודעה
        const total = window.purchasePayments.reduce((sum, p) => sum + p.amount, 0);
    }

    function showSmartPaymentsModal(availablePayments) {
        // חלק את התשלומים לחובה ואופציונלי
        const mandatoryPayments = availablePayments.filter(p => p.mandatory);
        const optionalPayments = availablePayments.filter(p => !p.mandatory);
        
        // יצירת המודל
        const modal = document.createElement('div');
        modal.id = 'smartPaymentsModal';
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
        
        // חשב סכום התחלתי (רק תשלומי חובה)
        let currentTotal = mandatoryPayments.reduce((sum, p) => sum + parseFloat(p.price || 0), 0);
        
        modal.innerHTML = `
            <div class="modal-content" style="
                background: white;
                padding: 30px;
                border-radius: 8px;
                width: 700px;
                max-height: 90vh;
                overflow-y: auto;
                margin: 20px;
            ">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                    <h3 style="margin: 0;">חישוב תשלומים אוטומטי</h3>
                    <button onclick="closeSmartPaymentsModal()" style="
                        background: none;
                        border: none;
                        font-size: 24px;
                        cursor: pointer;
                    ">×</button>
                </div>
                
                <!-- הצגת הפרמטרים -->
                <div style="background: #e3f2fd; padding: 10px; border-radius: 5px; margin-bottom: 20px;">
                    <strong>פרמטרים נבחרים:</strong><br>
                    סוג חלקה: ${window.selectedGraveData.plotType == 1 ? 'פטורה' : window.selectedGraveData.plotType == 2 ? 'חריגה' : 'סגורה'} | 
                    סוג קבר: ${window.selectedGraveData.graveType == 1 ? 'שדה' : window.selectedGraveData.graveType == 2 ? 'רוויה' : 'סנהדרין'} | 
                    תושבות: ירושלים
                </div>
                
                ${mandatoryPayments.length > 0 ? `
                    <!-- תשלומי חובה -->
                    <div style="margin-bottom: 20px;">
                        <h4 style="color: #dc3545; margin-bottom: 10px;">
                            <span style="background: #ffc107; padding: 2px 8px; border-radius: 3px;">חובה</span>
                            תשלומים הכרחיים
                        </h4>
                        <div style="border: 2px solid #ffc107; background: #fffbf0; padding: 15px; border-radius: 5px;">
                            ${mandatoryPayments.map(payment => `
                                <div style="padding: 8px 0; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #ffe5b4;">
                                    <label style="display: flex; align-items: center;">
                                        <input type="checkbox" checked disabled style="margin-left: 10px;">
                                        <span style="font-weight: bold; margin-right: 10px;">${payment.name}</span>
                                    </label>
                                    <span style="font-weight: bold; color: #dc3545;">₪${parseFloat(payment.price).toLocaleString()}</span>
                                </div>
                            `).join('')}
                        </div>
                    </div>
                ` : ''}
                
                <!-- תשלומים אופציונליים כולל הוספה מותאמת -->
                <div style="margin-bottom: 20px;">
                    <h4 style="color: #28a745; margin-bottom: 10px;">
                        <span style="background: #d4edda; padding: 2px 8px; border-radius: 3px;">אופציונלי</span>
                        תשלומים נוספים
                    </h4>
                    <div style="border: 1px solid #28a745; background: #f0fff4; padding: 15px; border-radius: 5px;">
                        <div id="optionalPaymentsList">
                            ${optionalPayments.map((payment, index) => `
                                <div style="padding: 8px 0; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #c3e6cb;">
                                    <label style="display: flex; align-items: center; cursor: pointer;">
                                        <input type="checkbox" 
                                            data-price="${payment.price}"
                                            data-name="${payment.name}"
                                            data-definition="${payment.priceDefinition}"
                                            onchange="updateSmartTotal()"
                                            style="margin-left: 10px;">
                                        <span style="margin-right: 10px;">${payment.name}</span>
                                    </label>
                                    <span>₪${parseFloat(payment.price).toLocaleString()}</span>
                                </div>
                            `).join('')}
                        </div>
                        
                        <!-- הוספת תשלום מותאם -->
                        <div style="border-top: 2px solid #28a745; margin-top: 15px; padding-top: 15px;">
                            <h5 style="margin-bottom: 10px;">הוסף תשלום מותאם:</h5>
                            <div style="display: grid; grid-template-columns: 2fr 1fr auto; gap: 10px; align-items: end;">
                                <div>
                                    <label style="display: block; margin-bottom: 5px; font-size: 12px;">סיבת תשלום</label>
                                    <input type="text" id="customPaymentName" 
                                        list="paymentReasons"
                                        placeholder="בחר או הקלד סיבה" 
                                        style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                                    <datalist id="paymentReasons">
                                        <option value="דמי רישום">
                                        <option value="עלויות ניהול">
                                        <option value="תחזוקה שנתית">
                                        <option value="שירותים נוספים">
                                        <option value="הובלה">
                                        <option value="טקס מיוחד">
                                    </datalist>
                                </div>
                                <div>
                                    <label style="display: block; margin-bottom: 5px; font-size: 12px;">סכום</label>
                                    <input type="number" id="customPaymentAmount" 
                                        step="0.01" min="0"
                                        placeholder="0.00" 
                                        style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                                </div>
                                <button onclick="addCustomPaymentToList()" style="
                                    padding: 8px 15px;
                                    background: #17a2b8;
                                    color: white;
                                    border: none;
                                    border-radius: 4px;
                                    cursor: pointer;
                                    white-space: nowrap;
                                ">+ הוסף</button>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- סיכום -->
                <div style="background: #f8f9fa; padding: 20px; border-radius: 5px; margin: 20px 0; text-align: center;">
                    <div style="font-size: 24px; font-weight: bold;">
                        סה"כ לתשלום: ₪<span id="smartModalTotal">${currentTotal.toLocaleString()}</span>
                    </div>
                    <div style="font-size: 12px; color: #666; margin-top: 5px;">
                        כולל ${mandatoryPayments.length} תשלומי חובה
                        <span id="optionalCount"></span>
                    </div>
                </div>
                
                <!-- כפתורים -->
                <div style="display: flex; gap: 10px; justify-content: flex-end;">
                    <button onclick="closeSmartPaymentsModal()" style="
                        padding: 10px 30px;
                        background: #6c757d;
                        color: white;
                        border: none;
                        border-radius: 4px;
                        cursor: pointer;
                    ">ביטול</button>
                    <button onclick="applySmartPayments('${JSON.stringify(mandatoryPayments).replace(/'/g, "\\'").replace(/"/g, '&quot;')}')" style="
                        padding: 10px 30px;
                        background: #28a745;
                        color: white;
                        border: none;
                        border-radius: 4px;
                        cursor: pointer;
                        font-weight: bold;
                    ">אישור ושמירה</button>
                </div>
            </div>
        `;
        
        document.body.appendChild(modal);
    }

    // פונקציה חדשה להוספת תשלום מותאם לרשימה
    window.addCustomPaymentToList = function() {
        const name = document.getElementById('customPaymentName').value.trim();
        const amount = parseFloat(document.getElementById('customPaymentAmount').value);
        
        if (!name || !amount || amount <= 0) {
            alert('יש למלא שם וסכום תקין');
            return;
        }
        
        // הוסף לרשימה
        const optionalList = document.getElementById('optionalPaymentsList');
        const newPaymentId = 'custom_' + Date.now();
        
        const newPaymentHTML = `
            <div style="padding: 8px 0; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #c3e6cb; background: #ffffcc;">
                <label style="display: flex; align-items: center; cursor: pointer;">
                    <input type="checkbox" 
                        checked
                        data-price="${amount}"
                        data-name="${name}"
                        data-definition="custom"
                        data-custom="true"
                        onchange="updateSmartTotal()"
                        style="margin-left: 10px;">
                    <span style="margin-right: 10px;">${name} (מותאם)</span>
                </label>
                <span>₪${amount.toLocaleString()}</span>
            </div>
        `;
        
        optionalList.insertAdjacentHTML('beforeend', newPaymentHTML);
        
        // נקה את השדות
        document.getElementById('customPaymentName').value = '';
        document.getElementById('customPaymentAmount').value = '';
        
        // עדכן סכום
        updateSmartTotal();
    }

    // פונקציה לסגירת המודל
    window.closeSmartPaymentsModal = function() {
        const modal = document.getElementById('smartPaymentsModal');
        if (modal) {
            modal.remove();
        }
    }

    // הוספת תשלום מותאם בתוך המודל החכם
    window.addCustomPaymentInSmart = function() {
        document.getElementById('smartPaymentsModal').remove();
        openPaymentsManager();
    }

    // הפונקציות הקיימות לניהול תשלומים ידני
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

    window.closePaymentsManager = function() {
        const modal = document.getElementById('paymentsManagerModal');
        if (modal) {
            modal.remove();
            document.getElementById('total_price').value = calculatePaymentsTotal();
            document.getElementById('paymentsDisplay').innerHTML = displayPaymentsSummary();
            document.getElementById('payments_data').value = JSON.stringify(window.purchasePayments);
        }
    }

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
        
        document.getElementById('paymentsList').innerHTML = displayPaymentsList();
        document.getElementById('paymentsTotal').textContent = calculatePaymentsTotal();
        document.getElementById('payment_type').value = '';
        document.getElementById('payment_amount').value = '';
    }

    window.removePayment = function(index) {
        window.purchasePayments.splice(index, 1);
        document.getElementById('paymentsList').innerHTML = displayPaymentsList();
        document.getElementById('paymentsTotal').textContent = calculatePaymentsTotal();
    }

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

    window.calculatePaymentsTotal = function() {
        return window.purchasePayments.reduce((total, payment) => total + payment.amount, 0).toFixed(2);
    }
</script>