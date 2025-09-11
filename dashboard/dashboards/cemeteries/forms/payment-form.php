<?php
// dashboards/cemeteries/forms/payment-form.php
// טופס תשלומים

require_once $_SERVER['DOCUMENT_ROOT'] . '/dashboard/dashboards/cemeteries/config.php';

$id = $_GET['id'] ?? null;
$payment = null;

if ($id) {
    // טען נתוני תשלום לעריכה
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("SELECT * FROM payments WHERE id = :id AND isActive = 1");
    $stmt->execute(['id' => $id]);
    $payment = $stmt->fetch(PDO::FETCH_ASSOC);
}
?>

<div class="modal show" id="paymentFormModal">
    <div class="modal-overlay" onclick="FormHandler.closeForm('payment')"></div>
    <div class="modal-content" style="max-width: 900px;">
        <div class="modal-header">
            <h2><?php echo $id ? 'עריכת תשלום' : 'הוספת תשלום חדש'; ?></h2>
            <button class="modal-close" onclick="FormHandler.closeForm('payment')">×</button>
        </div>
        
        <form id="paymentForm" onsubmit="handleFormSubmit(event, 'payment')">
            <?php if ($id): ?>
                <input type="hidden" name="id" value="<?php echo $id; ?>">
            <?php endif; ?>
            
            <div class="modal-body">
                <!-- סוגים ומחיר -->
                <fieldset style="border: 1px solid #e5e7eb; border-radius: 8px; padding: 20px; margin-bottom: 20px;">
                    <legend style="padding: 0 10px; font-weight: bold;">פרטי תשלום</legend>
                    
                    <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 15px;">
                        <div>
                            <label class="form-label">סוג חלקה</label>
                            <select name="plotType" class="form-control">
                                <option value="">בחר סוג</option>
                                <?php foreach (PAYMENT_PLOT_TYPES as $key => $type): ?>
                                    <option value="<?php echo $key; ?>" <?php echo ($payment && $payment['plotType'] == $key) ? 'selected' : ''; ?>>
                                        <?php echo $type['icon'] . ' ' . $type['name']; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div>
                            <label class="form-label">סוג קבר</label>
                            <select name="graveType" class="form-control">
                                <option value="">בחר סוג</option>
                                <?php foreach (PAYMENT_GRAVE_TYPES as $key => $type): ?>
                                    <option value="<?php echo $key; ?>" <?php echo ($payment && $payment['graveType'] == $key) ? 'selected' : ''; ?>>
                                        <?php echo $type['icon'] . ' ' . $type['name']; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div>
                            <label class="form-label">סוג תושב</label>
                            <select name="resident" class="form-control">
                                <option value="">בחר סוג</option>
                                <?php foreach (PAYMENT_RESIDENT_TYPES as $key => $type): ?>
                                    <option value="<?php echo $key; ?>" <?php echo ($payment && $payment['resident'] == $key) ? 'selected' : ''; ?>>
                                        <?php echo $type['name']; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div>
                            <label class="form-label">סטטוס רוכש</label>
                            <select name="buyerStatus" class="form-control">
                                <option value="">בחר סטטוס</option>
                                <?php foreach (PAYMENT_BUYER_STATUS as $key => $status): ?>
                                    <option value="<?php echo $key; ?>" <?php echo ($payment && $payment['buyerStatus'] == $key) ? 'selected' : ''; ?>>
                                        <?php echo $status['name']; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </fieldset>
                
                <!-- הגדרת מחיר -->
                <fieldset style="border: 1px solid #e5e7eb; border-radius: 8px; padding: 20px; margin-bottom: 20px;">
                    <legend style="padding: 0 10px; font-weight: bold;">מחיר ותשלום</legend>
                    
                    <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 15px;">
                        <div>
                            <label class="form-label">הגדרת מחיר</label>
                            <select name="priceDefinition" class="form-control">
                                <option value="">בחר הגדרה</option>
                                <?php foreach (PAYMENT_PRICE_DEFINITIONS as $key => $def): ?>
                                    <option value="<?php echo $key; ?>" <?php echo ($payment && $payment['priceDefinition'] == $key) ? 'selected' : ''; ?>>
                                        <?php echo $def['icon'] . ' ' . $def['name']; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div>
                            <label class="form-label">מחיר <span class="required">*</span></label>
                            <input type="number" name="price" class="form-control" required 
                                   step="0.01" min="0" 
                                   value="<?php echo $payment ? $payment['price'] : ''; ?>">
                        </div>
                        
                        <div>
                            <label class="form-label">תאריך התחלת תשלום</label>
                            <input type="date" name="startPayment" class="form-control" 
                                   value="<?php echo $payment ? $payment['startPayment'] : ''; ?>">
                        </div>
                    </div>
                </fieldset>
                
                <!-- מיקום -->
                <fieldset style="border: 1px solid #e5e7eb; border-radius: 8px; padding: 20px;">
                    <legend style="padding: 0 10px; font-weight: bold;">מיקום (אופציונלי)</legend>
                    
                    <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 15px;">
                        <div>
                            <label class="form-label">בית עלמין</label>
                            <select name="cemeteryId" id="cemeterySelect" class="form-control" onchange="loadBlocksForPayment(this.value)">
                                <option value="">בחר בית עלמין</option>
                            </select>
                        </div>
                        
                        <div>
                            <label class="form-label">גוש</label>
                            <select name="blockId" id="blockSelect" class="form-control" onchange="loadPlotsForPayment(this.value)">
                                <option value="">בחר גוש</option>
                            </select>
                        </div>
                        
                        <div>
                            <label class="form-label">חלקה</label>
                            <select name="plotId" id="plotSelect" class="form-control" onchange="loadLinesForPayment(this.value)">
                                <option value="">בחר חלקה</option>
                            </select>
                        </div>
                        
                        <div>
                            <label class="form-label">שורה</label>
                            <select name="lineId" id="lineSelect" class="form-control">
                                <option value="">בחר שורה</option>
                            </select>
                        </div>
                    </div>
                </fieldset>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="FormHandler.closeForm('payment')">ביטול</button>
                <button type="submit" class="btn btn-primary">
                    <?php echo $id ? 'עדכן' : 'שמור'; ?>
                </button>
            </div>
        </form>
    </div>
</div>

<script>
// טעינת בתי עלמין
async function loadCemeteriesForPayment() {
    try {
        const response = await fetch('/dashboard/dashboards/cemeteries/api/cemetery-hierarchy.php?action=list&type=cemetery');
        const data = await response.json();
        
        if (data.success) {
            const select = document.getElementById('cemeterySelect');
            select.innerHTML = '<option value="">בחר בית עלמין</option>';
            data.data.forEach(cemetery => {
                select.innerHTML += `<option value="${cemetery.id}">${cemetery.name}</option>`;
            });
            
            <?php if ($payment && $payment['cemeteryId']): ?>
            select.value = '<?php echo $payment['cemeteryId']; ?>';
            loadBlocksForPayment('<?php echo $payment['cemeteryId']; ?>');
            <?php endif; ?>
        }
    } catch (error) {
        console.error('Error loading cemeteries:', error);
    }
}

// טעינת גושים
async function loadBlocksForPayment(cemeteryId) {
    if (!cemeteryId) {
        document.getElementById('blockSelect').innerHTML = '<option value="">בחר גוש</option>';
        return;
    }
    
    try {
        const response = await fetch(`/dashboard/dashboards/cemeteries/api/cemetery-hierarchy.php?action=list&type=block&parent_id=${cemeteryId}`);
        const data = await response.json();
        
        if (data.success) {
            const select = document.getElementById('blockSelect');
            select.innerHTML = '<option value="">בחר גוש</option>';
            data.data.forEach(block => {
                select.innerHTML += `<option value="${block.id}">${block.name}</option>`;
            });
            
            <?php if ($payment && $payment['blockId']): ?>
            select.value = '<?php echo $payment['blockId']; ?>';
            loadPlotsForPayment('<?php echo $payment['blockId']; ?>');
            <?php endif; ?>
        }
    } catch (error) {
        console.error('Error loading blocks:', error);
    }
}

// טעינת חלקות
async function loadPlotsForPayment(blockId) {
    if (!blockId) {
        document.getElementById('plotSelect').innerHTML = '<option value="">בחר חלקה</option>';
        return;
    }
    
    try {
        const response = await fetch(`/dashboard/dashboards/cemeteries/api/cemetery-hierarchy.php?action=list&type=plot&parent_id=${blockId}`);
        const data = await response.json();
        
        if (data.success) {
            const select = document.getElementById('plotSelect');
            select.innerHTML = '<option value="">בחר חלקה</option>';
            data.data.forEach(plot => {
                select.innerHTML += `<option value="${plot.id}">${plot.name}</option>`;
            });
            
            <?php if ($payment && $payment['plotId']): ?>
            select.value = '<?php echo $payment['plotId']; ?>';
            loadLinesForPayment('<?php echo $payment['plotId']; ?>');
            <?php endif; ?>
        }
    } catch (error) {
        console.error('Error loading plots:', error);
    }
}

// טעינת שורות
async function loadLinesForPayment(plotId) {
    if (!plotId) {
        document.getElementById('lineSelect').innerHTML = '<option value="">בחר שורה</option>';
        return;
    }
    
    try {
        const response = await fetch(`/dashboard/dashboards/cemeteries/api/cemetery-hierarchy.php?action=list&type=row&parent_id=${plotId}`);
        const data = await response.json();
        
        if (data.success) {
            const select = document.getElementById('lineSelect');
            select.innerHTML = '<option value="">בחר שורה</option>';
            data.data.forEach(line => {
                select.innerHTML += `<option value="${line.id}">${line.name}</option>`;
            });
            
            <?php if ($payment && $payment['lineId']): ?>
            select.value = '<?php echo $payment['lineId']; ?>';
            <?php endif; ?>
        }
    } catch (error) {
        console.error('Error loading lines:', error);
    }
}

// טען בתי עלמין בטעינת הדף
loadCemeteriesForPayment();
</script>