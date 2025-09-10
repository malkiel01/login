<?php
// forms/purchase-form.php
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
    $customers = $customersStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // טען בתי עלמין עם סימון קברים פנויים
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
    die("שגיאה: " . $e->getMessage());
}
?>

<link rel="stylesheet" href="/dashboard/dashboards/cemeteries/css/forms.css">

<div class="modal-overlay">
    <div class="modal-content">
        <div class="modal-header">
            <h3><?php echo $itemId ? 'עריכת רכישה' : 'רכישה חדשה'; ?></h3>
            <button type="button" class="btn-close" onclick="FormHandler.closeForm()">×</button>
        </div>
        
        <form id="genericForm" onsubmit="return FormHandler.submitForm(event, 'purchase')">
            <div class="modal-body">
                <?php if ($itemId): ?>
                    <input type="hidden" name="id" value="<?php echo $itemId; ?>">
                <?php endif; ?>
                
                <!-- בחירת לקוח -->
                <fieldset class="form-section">
                    <legend>פרטי הרוכש</legend>
                    <div class="form-row">
                        <div class="form-group">
                            <label>לקוח <span class="required">*</span></label>
                            <select name="customer_id" class="form-control" required>
                                <option value="">-- בחר לקוח --</option>
                                <?php foreach ($customers as $customer): ?>
                                    <option value="<?php echo $customer['id']; ?>" 
                                        <?php echo ($purchase && $purchase['customer_id'] == $customer['id']) ? 'selected' : ''; ?>>
                                        <?php echo $customer['full_name']; ?>
                                        <?php if ($customer['id_number']): ?>
                                            (<?php echo $customer['id_number']; ?>)
                                        <?php endif; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label>סטטוס רוכש</label>
                            <select name="buyer_status" class="form-control">
                                <option value="1">רוכש לעצמו</option>
                                <option value="2">רוכש לאחר</option>
                            </select>
                        </div>
                    </div>
                </fieldset>
                
                <!-- בחירת קבר -->
                <fieldset class="form-section">
                    <legend>בחירת קבר</legend>
                    <div class="form-row">
                        <div class="form-group">
                            <label>בית עלמין</label>
                            <select id="cemeterySelect" class="form-control" onchange="updateGraveSelectors('cemetery')">
                                <option value="">-- כל בתי העלמין --</option>
                                <?php foreach ($cemeteries as $cemetery): ?>
                                    <option value="<?php echo $cemetery['id']; ?>" 
                                        <?php echo !$cemetery['has_available_graves'] ? 'disabled style="color: #999;"' : ''; ?>>
                                        <?php echo htmlspecialchars($cemetery['name']); ?>
                                        <?php echo !$cemetery['has_available_graves'] ? ' (אין קברים פנויים)' : ''; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label>גוש</label>
                            <select id="blockSelect" class="form-control" onchange="updateGraveSelectors('block')">
                                <option value="">-- כל הגושים --</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>חלקה</label>
                            <select id="plotSelect" class="form-control" onchange="updateGraveSelectors('plot')">
                                <option value="">-- כל החלקות --</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label>שורה</label>
                            <select id="rowSelect" class="form-control" onchange="updateGraveSelectors('row')" disabled>
                                <option value="">-- בחר חלקה תחילה --</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>אחוזת קבר</label>
                            <select id="areaGraveSelect" class="form-control" onchange="updateGraveSelectors('area_grave')" disabled>
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
                </fieldset>
                
                <!-- פרטי תשלום -->
                <fieldset class="form-section">
                    <legend>פרטי תשלום</legend>
                    <div class="form-row">
                        <div class="form-group">
                            <label>סטטוס רכישה</label>
                            <select name="purchase_status" class="form-control">
                                <option value="1">טיוטה</option>
                                <option value="2">אושר</option>
                                <option value="3">שולם</option>
                                <option value="4">בוטל</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label>מחיר</label>
                            <input type="number" name="price" step="0.01" class="form-control" 
                                   value="<?php echo $purchase['price'] ?? ''; ?>">
                        </div>
                        
                        <div class="form-group">
                            <label>מספר תשלומים</label>
                            <input type="number" name="num_payments" min="1" class="form-control" 
                                   value="<?php echo $purchase['num_payments'] ?? 1; ?>">
                        </div>
                        
                        <div class="form-group">
                            <label>תאריך סיום תשלומים</label>
                            <input type="date" name="payment_end_date" class="form-control" 
                                   value="<?php echo $purchase['payment_end_date'] ?? ''; ?>">
                        </div>
                    </div>
                </fieldset>
                
                <!-- הערות -->
                <div class="form-group">
                    <label>הערות</label>
                    <textarea name="comments" class="form-control" rows="3"><?php echo $purchase['comments'] ?? ''; ?></textarea>
                </div>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="FormHandler.closeForm()">ביטול</button>
                <button type="submit" class="btn btn-primary">שמור</button>
            </div>
        </form>
    </div>
</div>

<script>
if (typeof API_BASE === 'undefined') {
    window.API_BASE = '/dashboard/dashboards/cemeteries/api/';
}

// כל הפונקציות של בחירת הקבר
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
    } else if (cemeteryId) {
        // כאן צריך לטעון את כל החלקות של כל הגושים בבית העלמין
        // זה דורש שינוי ב-API
        url += `&block_parent_id=${cemeteryId}`;
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