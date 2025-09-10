<?php
// forms/purchase-form.php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// טען את הקונפיג
require_once $_SERVER['DOCUMENT_ROOT'] . '/dashboard/dashboards/cemeteries/config.php';

$parentId = $_GET['parent_id'] ?? null;
$itemId = $_GET['item_id'] ?? null;

try {
    // קבל חיבור למסד נתונים
    $conn = getDBConnection();
    
    // טען לקוחות פנויים בלבד
    $customersQuery = "SELECT id, first_name, last_name, id_number 
                       FROM customers 
                       WHERE customer_status = 1 AND is_active = 1 
                       ORDER BY last_name, first_name";
    $customersStmt = $conn->prepare($customersQuery);
    $customersStmt->execute();
    $customers = $customersStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // טען בתי עלמין עם קברים פנויים
    $cemeteriesQuery = "
        SELECT DISTINCT c.id, c.name 
        FROM cemeteries c
        INNER JOIN blocks b ON b.cemetery_id = c.id
        INNER JOIN plots p ON p.block_id = b.id
        INNER JOIN rows r ON r.plot_id = p.id
        INNER JOIN area_graves ag ON ag.row_id = r.id
        INNER JOIN graves g ON g.area_grave_id = ag.id
        WHERE g.grave_status = 1 AND g.is_active = 1
        ORDER BY c.name";
    $cemeteriesStmt = $conn->prepare($cemeteriesQuery);
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
    echo "<div style='color: red; padding: 20px;'>";
    echo "שגיאה: " . $e->getMessage();
    echo "</div>";
    exit;
}
?>

<!-- < ?php
// forms/purchase-form.php
require_once $_SERVER['DOCUMENT_ROOT'] . '/dashboard/dashboards/cemeteries/config.php';

$parentId = $_GET['parent_id'] ?? null;
$itemId = $_GET['item_id'] ?? null;

// טען לקוחות פנויים בלבד
$customersQuery = "SELECT id, first_name, last_name, id_number 
                   FROM customers 
                   WHERE customer_status = 1 AND is_active = 1 
                   ORDER BY last_name, first_name";
$customers = $conn->query($customersQuery)->fetch_all(MYSQLI_ASSOC);

// טען בתי עלמין עם קברים פנויים
$cemeteriesQuery = "
    SELECT DISTINCT c.id, c.name 
    FROM cemeteries c
    INNER JOIN blocks b ON b.cemetery_id = c.id
    INNER JOIN plots p ON p.block_id = b.id
    INNER JOIN rows r ON r.plot_id = p.id
    INNER JOIN area_graves ag ON ag.row_id = r.id
    INNER JOIN graves g ON g.area_grave_id = ag.id
    WHERE g.grave_status = 1 AND g.is_active = 1
    ORDER BY c.name";
$cemeteries = $conn->query($cemeteriesQuery)->fetch_all(MYSQLI_ASSOC);

// אם עורכים רכישה קיימת
$purchase = null;
if ($itemId) {
    $stmt = $conn->prepare("SELECT * FROM purchases WHERE id = ?");
    $stmt->bind_param("i", $itemId);
    $stmt->execute();
    $purchase = $stmt->get_result()->fetch_assoc();
}
?> -->

<style>
    .form-section {
        background: #f8f9fa;
        padding: 20px;
        border-radius: 8px;
        margin-bottom: 20px;
    }
    .form-section h4 {
        margin-bottom: 15px;
        color: #333;
        border-bottom: 2px solid #667eea;
        padding-bottom: 8px;
    }
    .cascade-selects {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 15px;
    }
    .payments-table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 15px;
    }
    .payments-table th,
    .payments-table td {
        padding: 8px;
        border: 1px solid #ddd;
        text-align: right;
    }
    .payments-table th {
        background: #e9ecef;
    }
</style>

<div id="purchaseFormModal" class="modal-overlay">
    <div class="modal-content" style="max-width: 900px;">
        <div class="modal-header">
            <h3><?php echo $itemId ? 'עריכת רכישה' : 'רכישה חדשה'; ?></h3>
            <button type="button" class="btn-close" onclick="FormHandler.closeForm()">×</button>
        </div>
        
        <form id="purchaseForm" onsubmit="return FormHandler.submitForm(event, 'purchase')">
            <?php if ($itemId): ?>
                <input type="hidden" name="id" value="<?php echo $itemId; ?>">
            <?php endif; ?>
            
            <!-- בחירת לקוח -->
            <div class="form-section">
                <h4>פרטי הרוכש</h4>
                <div class="form-row">
                    <div class="form-group">
                        <label>לקוח <span class="required">*</span></label>
                        <select name="customer_id" id="customerSelect" required class="form-control">
                            <option value="">-- בחר לקוח --</option>
                            <?php foreach ($customers as $customer): ?>
                                <option value="<?php echo $customer['id']; ?>" 
                                    <?php echo ($purchase && $purchase['customer_id'] == $customer['id']) ? 'selected' : ''; ?>>
                                    <?php echo $customer['last_name'] . ' ' . $customer['first_name']; ?>
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
            </div>
            
            <!-- בחירת קבר -->
            <div class="form-section">
                <h4>מיקום הקבר</h4>
                <div class="cascade-selects">
                    <div class="form-group">
                        <label>בית עלמין</label>
                        <select id="cemeterySelect" class="form-control" onchange="loadBlocks(this.value)">
                            <option value="">-- בחר בית עלמין --</option>
                            <?php foreach ($cemeteries as $cemetery): ?>
                                <option value="<?php echo $cemetery['id']; ?>">
                                    <?php echo $cemetery['name']; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>גוש</label>
                        <select id="blockSelect" class="form-control" onchange="loadPlots(this.value)" disabled>
                            <option value="">-- בחר גוש --</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>חלקה</label>
                        <select id="plotSelect" class="form-control" onchange="loadRows(this.value)" disabled>
                            <option value="">-- בחר חלקה --</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>שורה</label>
                        <select id="rowSelect" class="form-control" onchange="loadAreaGraves(this.value)" disabled>
                            <option value="">-- בחר שורה --</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>אחוזת קבר</label>
                        <select id="areaGraveSelect" class="form-control" onchange="loadGraves(this.value)" disabled>
                            <option value="">-- בחר אחוזת קבר --</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>קבר <span class="required">*</span></label>
                        <select name="grave_id" id="graveSelect" class="form-control" required disabled>
                            <option value="">-- בחר קבר --</option>
                        </select>
                    </div>
                </div>
            </div>
            
            <!-- תשלומים -->
            <div class="form-section">
                <h4>פרטי תשלום</h4>
                <div class="form-row">
                    <div class="form-group">
                        <label>מחיר כולל</label>
                        <input type="number" name="price" step="0.01" class="form-control" 
                               value="<?php echo $purchase['price'] ?? ''; ?>">
                    </div>
                    
                    <div class="form-group">
                        <label>מספר תשלומים</label>
                        <input type="number" name="num_payments" min="1" class="form-control" 
                               value="<?php echo $purchase['num_payments'] ?? 1; ?>">
                    </div>
                </div>
                
                <button type="button" class="btn btn-secondary" onclick="openPaymentsModal()">
                    ניהול תשלומים מפורט
                </button>
                
                <div id="paymentsPreview"></div>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="FormHandler.closeForm()">ביטול</button>
                <button type="submit" class="btn btn-primary">שמור</button>
            </div>
        </form>
    </div>
</div>

<script>
// טעינת גושים לפי בית עלמין
async function loadBlocks(cemeteryId) {
    if (!cemeteryId) {
        resetSelects(['blockSelect', 'plotSelect', 'rowSelect', 'areaGraveSelect', 'graveSelect']);
        return;
    }
    
    try {
        const response = await fetch(`${API_BASE}cemetery-hierarchy.php?action=list&type=block&parent_id=${cemeteryId}&has_available_graves=1`);
        const data = await response.json();
        
        if (data.success) {
            populateSelect('blockSelect', data.data, 'id', 'name');
        }
    } catch (error) {
        console.error('Error loading blocks:', error);
    }
}

// טעינת חלקות לפי גוש
async function loadPlots(blockId) {
    if (!blockId) {
        resetSelects(['plotSelect', 'rowSelect', 'areaGraveSelect', 'graveSelect']);
        return;
    }
    
    try {
        const response = await fetch(`${API_BASE}cemetery-hierarchy.php?action=list&type=plot&parent_id=${blockId}&has_available_graves=1`);
        const data = await response.json();
        
        if (data.success) {
            populateSelect('plotSelect', data.data, 'id', 'name');
        }
    } catch (error) {
        console.error('Error loading plots:', error);
    }
}

// טעינת שורות לפי חלקה
async function loadRows(plotId) {
    if (!plotId) {
        resetSelects(['rowSelect', 'areaGraveSelect', 'graveSelect']);
        return;
    }
    
    try {
        const response = await fetch(`${API_BASE}cemetery-hierarchy.php?action=list&type=row&parent_id=${plotId}&has_available_graves=1`);
        const data = await response.json();
        
        if (data.success) {
            populateSelect('rowSelect', data.data, 'id', 'name');
        }
    } catch (error) {
        console.error('Error loading rows:', error);
    }
}

// טעינת אחוזות קבר לפי שורה
async function loadAreaGraves(rowId) {
    if (!rowId) {
        resetSelects(['areaGraveSelect', 'graveSelect']);
        return;
    }
    
    try {
        const response = await fetch(`${API_BASE}cemetery-hierarchy.php?action=list&type=area_grave&parent_id=${rowId}&has_available_graves=1`);
        const data = await response.json();
        
        if (data.success) {
            populateSelect('areaGraveSelect', data.data, 'id', 'name');
        }
    } catch (error) {
        console.error('Error loading area graves:', error);
    }
}

// טעינת קברים לפי אחוזת קבר
async function loadGraves(areaGraveId) {
    if (!areaGraveId) {
        resetSelects(['graveSelect']);
        return;
    }
    
    try {
        const response = await fetch(`${API_BASE}cemetery-hierarchy.php?action=list&type=grave&parent_id=${areaGraveId}&grave_status=1`);
        const data = await response.json();
        
        if (data.success) {
            populateSelect('graveSelect', data.data, 'id', 'grave_number');
        }
    } catch (error) {
        console.error('Error loading graves:', error);
    }
}

// מילוי select
function populateSelect(selectId, items, valueField, textField) {
    const select = document.getElementById(selectId);
    select.disabled = false;
    select.innerHTML = '<option value="">-- בחר --</option>';
    
    items.forEach(item => {
        const option = document.createElement('option');
        option.value = item[valueField];
        option.textContent = item[textField];
        select.appendChild(option);
    });
}

// איפוס selects
function resetSelects(selectIds) {
    selectIds.forEach(id => {
        const select = document.getElementById(id);
        select.innerHTML = '<option value="">-- בחר --</option>';
        select.disabled = true;
    });
}

// פתיחת מודל תשלומים
function openPaymentsModal() {
    // TODO: יצירת מודל לניהול תשלומים מפורט
    alert('מודל תשלומים בפיתוח');
}
</script>