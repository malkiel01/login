<?php
/*
 * File: dashboard/dashboards/cemeteries/forms/iframe/purchaseForm-iframe.php
 * Version: 1.0.0
 * Updated: 2026-01-19
 * Author: Malkiel
 * Description: טופס רכישה (יצירה/עריכה) - דף עצמאי לטעינה ב-iframe
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: text/html; charset=utf-8');

require_once dirname(dirname(__DIR__)) . '/config.php';

$itemId = $_GET['itemId'] ?? $_GET['id'] ?? null;
$parentId = $_GET['parentId'] ?? $_GET['parent_id'] ?? $_GET['graveId'] ?? null;
$popupId = $_GET['popupId'] ?? null;
$isEditMode = !empty($itemId);

$purchase = null;

if ($isEditMode) {
    try {
        $conn = getDBConnection();
        $stmt = $conn->prepare("
            SELECT p.*,
                   c.firstName as customerFirstName,
                   c.lastName as customerLastName,
                   g.graveNumber
            FROM purchases p
            LEFT JOIN customers c ON p.clientId = c.unicId
            LEFT JOIN graves g ON p.graveId = g.unicId
            WHERE p.unicId = ? AND p.isActive = 1
        ");
        $stmt->execute([$itemId]);
        $purchase = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$purchase) {
            die('<!DOCTYPE html><html dir="rtl"><head><meta charset="UTF-8"></head><body style="font-family: Arial; padding: 20px; color: #ef4444;">שגיאה: הרכישה לא נמצאה</body></html>');
        }
    } catch (Exception $e) {
        die('<!DOCTYPE html><html dir="rtl"><head><meta charset="UTF-8"></head><body style="font-family: Arial; padding: 20px; color: #ef4444;">שגיאה: ' . htmlspecialchars($e->getMessage()) . '</body></html>');
    }
}

$pageTitle = $isEditMode ? 'עריכת רכישה #' . ($purchase['serialPurchaseId'] ?? $itemId) : 'רכישה חדשה';

// מיפויים
$buyerStatusOptions = [1 => 'רוכש לעצמו', 2 => 'רוכש לאחר'];
$purchaseStatusOptions = [1 => 'פתוח', 2 => 'שולם', 3 => 'סגור', 4 => 'בוטל'];

function renderSelect($name, $options, $value = '', $required = false, $disabled = false) {
    $req = $required ? 'required' : '';
    $dis = $disabled ? 'disabled' : '';
    $html = "<select name=\"$name\" id=\"$name\" class=\"form-control\" $req $dis>";
    foreach ($options as $k => $v) {
        $sel = ($value == $k) ? 'selected' : '';
        $html .= "<option value=\"$k\" $sel>$v</option>";
    }
    $html .= "</select>";
    return $html;
}
?>
<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/dashboard/dashboards/cemeteries/explorer/explorer.css">
    <script src="/dashboard/dashboards/cemeteries/popup/popup-api.js"></script>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Segoe UI', Tahoma, sans-serif;
            background: #f1f5f9;
            color: #334155;
            padding: 20px;
            direction: rtl;
        }

        .form-container { max-width: 100%; }
        .sortable-sections { display: flex; flex-direction: column; gap: 15px; }
        .sortable-section {
            background: white;
            border-radius: 12px;
            border: 2px solid transparent;
            overflow: hidden;
            transition: border-color 0.2s;
        }
        .sortable-section:hover { border-color: #94a3b8; }

        .section-drag-handle {
            height: 32px;
            background: linear-gradient(135deg, #e2e8f0, #cbd5e1);
            cursor: grab;
            display: flex;
            align-items: center;
            justify-content: center;
            border-bottom: 1px solid #cbd5e1;
            position: relative;
        }
        .section-drag-handle::before {
            content: "";
            width: 40px;
            height: 4px;
            background: #94a3b8;
            border-radius: 2px;
        }
        .section-toggle-btn {
            position: absolute;
            left: 10px;
            top: 50%;
            transform: translateY(-50%);
            width: 24px;
            height: 24px;
            border: none;
            background: rgba(100,116,139,0.2);
            border-radius: 4px;
            cursor: pointer;
            color: #64748b;
            font-size: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .section-toggle-btn:hover { background: rgba(100,116,139,0.4); }
        .section-title {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            font-size: 13px;
            font-weight: 600;
            color: #64748b;
        }
        .section-content { padding: 20px; }
        .sortable-section.collapsed .section-content { display: none; }
        .sortable-section.collapsed .section-toggle-btn i { transform: rotate(-90deg); }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
        }
        .form-group { display: flex; flex-direction: column; }
        .form-group.span-2 { grid-column: span 2; }
        .form-group label {
            font-size: 12px;
            color: #64748b;
            margin-bottom: 5px;
            font-weight: 500;
        }
        .form-group label .required { color: #ef4444; }

        .form-control {
            padding: 10px 12px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            font-size: 14px;
            transition: border-color 0.2s, box-shadow 0.2s;
            background: white;
        }
        .form-control:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59,130,246,0.1);
        }
        .form-control:disabled { background: #f1f5f9; cursor: not-allowed; }
        textarea.form-control { resize: vertical; min-height: 80px; }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            transition: all 0.2s;
        }
        .btn-primary { background: #3b82f6; color: white; }
        .btn-primary:hover { background: #2563eb; }
        .btn-primary:disabled { background: #94a3b8; cursor: not-allowed; }
        .btn-secondary { background: #64748b; color: white; }
        .btn-secondary:hover { background: #475569; }
        .btn-info { background: #0891b2; color: white; }
        .btn-info:hover { background: #0e7490; }

        .form-actions {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #e2e8f0;
        }

        .alert {
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 15px;
            display: none;
        }
        .alert-error { background: #fef2f2; color: #dc2626; border: 1px solid #fecaca; }
        .alert-success { background: #f0fdf4; color: #16a34a; border: 1px solid #bbf7d0; }
        .alert.show { display: block; }

        .loading-overlay {
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(255,255,255,0.8);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 1000;
        }
        .loading-overlay.show { display: flex; }
        .loading-spinner {
            width: 40px; height: 40px;
            border: 3px solid #e2e8f0;
            border-top-color: #3b82f6;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        @keyframes spin { to { transform: rotate(360deg); } }

        .price-display {
            font-size: 24px;
            font-weight: bold;
            color: #059669;
            text-align: center;
            padding: 15px;
            background: #ecfdf5;
            border-radius: 8px;
            margin-bottom: 15px;
        }

        .payments-list {
            background: #f8fafc;
            border-radius: 8px;
            padding: 15px;
            min-height: 80px;
        }
    </style>
</head>
<body>
    <div class="loading-overlay" id="loadingOverlay">
        <div class="loading-spinner"></div>
    </div>

    <div class="form-container">
        <div id="alertBox" class="alert"></div>

        <form id="purchaseForm" novalidate>
            <input type="hidden" name="unicId" value="<?= htmlspecialchars($purchase['unicId'] ?? '') ?>">

            <div class="sortable-sections">
                <!-- סקשן 1: פרטי לקוח -->
                <div class="sortable-section" data-section="customer">
                    <div class="section-drag-handle" style="background: linear-gradient(135deg, #dbeafe, #bfdbfe);">
                        <button type="button" class="section-toggle-btn" onclick="toggleSection(this)">
                            <i class="fas fa-chevron-down"></i>
                        </button>
                        <span class="section-title" style="color: #1e40af;">
                            <i class="fas fa-user"></i> פרטי לקוח
                        </span>
                    </div>
                    <div class="section-content" style="background: linear-gradient(135deg, #eff6ff, #dbeafe);">
                        <div class="form-grid">
                            <div class="form-group span-2">
                                <label>לקוח <span class="required">*</span></label>
                                <select name="clientId" id="clientId" class="form-control" required>
                                    <option value="">טוען לקוחות...</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>סטטוס רוכש</label>
                                <?= renderSelect('buyer_status', $buyerStatusOptions, $purchase['buyer_status'] ?? 1) ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- סקשן 2: בחירת קבר -->
                <div class="sortable-section" data-section="grave">
                    <div class="section-drag-handle" style="background: linear-gradient(135deg, #dcfce7, #bbf7d0);">
                        <button type="button" class="section-toggle-btn" onclick="toggleSection(this)">
                            <i class="fas fa-chevron-down"></i>
                        </button>
                        <span class="section-title" style="color: #166534;">
                            <i class="fas fa-cross"></i> בחירת קבר
                        </span>
                    </div>
                    <div class="section-content" style="background: linear-gradient(135deg, #f0fdf4, #dcfce7);">
                        <div class="form-grid">
                            <div class="form-group">
                                <label>בית עלמין</label>
                                <select id="cemeterySelect" class="form-control" onchange="filterHierarchy('cemetery')">
                                    <option value="">טוען...</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>גוש</label>
                                <select id="blockSelect" class="form-control" onchange="filterHierarchy('block')" disabled>
                                    <option value="">בחר בית עלמין תחילה</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>חלקה</label>
                                <select id="plotSelect" class="form-control" onchange="filterHierarchy('plot')" disabled>
                                    <option value="">בחר גוש תחילה</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>שורה</label>
                                <select id="rowSelect" class="form-control" onchange="filterHierarchy('row')" disabled>
                                    <option value="">בחר חלקה תחילה</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>אחוזת קבר</label>
                                <select id="areaGraveSelect" class="form-control" onchange="filterHierarchy('areaGrave')" disabled>
                                    <option value="">בחר שורה תחילה</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>קבר <span class="required">*</span></label>
                                <select name="graveId" id="graveSelect" class="form-control" required disabled>
                                    <option value="">בחר אחוזת קבר תחילה</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- סקשן 3: תשלומים -->
                <div class="sortable-section" data-section="payments">
                    <div class="section-drag-handle" style="background: linear-gradient(135deg, #fef3c7, #fde68a);">
                        <button type="button" class="section-toggle-btn" onclick="toggleSection(this)">
                            <i class="fas fa-chevron-down"></i>
                        </button>
                        <span class="section-title" style="color: #92400e;">
                            <i class="fas fa-shekel-sign"></i> תשלומים
                        </span>
                    </div>
                    <div class="section-content" style="background: linear-gradient(135deg, #fffbeb, #fef3c7);">
                        <div class="price-display">
                            <span>סה"כ לתשלום: </span>
                            <span id="totalPriceDisplay">₪<?= number_format($purchase['price'] ?? 0, 2) ?></span>
                        </div>
                        <input type="hidden" name="price" id="price" value="<?= $purchase['price'] ?? 0 ?>">

                        <div class="form-grid">
                            <div class="form-group">
                                <label>מספר תשלומים</label>
                                <input type="number" name="numOfPayments" class="form-control" min="1"
                                    value="<?= htmlspecialchars($purchase['numOfPayments'] ?? 1) ?>">
                            </div>
                            <div class="form-group">
                                <label>תאריך סיום תשלומים</label>
                                <input type="date" name="PaymentEndDate" class="form-control"
                                    value="<?= htmlspecialchars($purchase['PaymentEndDate'] ?? '') ?>">
                            </div>
                        </div>

                        <div style="margin-top: 15px;">
                            <button type="button" class="btn btn-info" onclick="openPaymentsManager()">
                                <i class="fas fa-calculator"></i> חשב תשלומים אוטומטית
                            </button>
                        </div>

                        <div class="payments-list" style="margin-top: 15px;">
                            <div id="paymentsDisplay" style="text-align: center; color: #94a3b8;">
                                <i class="fas fa-coins" style="font-size: 24px; margin-bottom: 10px;"></i>
                                <div>פירוט תשלומים יופיע כאן</div>
                            </div>
                        </div>
                        <input type="hidden" name="paymentsList" id="paymentsList" value="<?= htmlspecialchars($purchase['paymentsList'] ?? '[]') ?>">
                    </div>
                </div>

                <!-- סקשן 4: סטטוס והערות -->
                <div class="sortable-section" data-section="status">
                    <div class="section-drag-handle" style="background: linear-gradient(135deg, #ede9fe, #c4b5fd);">
                        <button type="button" class="section-toggle-btn" onclick="toggleSection(this)">
                            <i class="fas fa-chevron-down"></i>
                        </button>
                        <span class="section-title" style="color: #5b21b6;">
                            <i class="fas fa-info-circle"></i> סטטוס והערות
                        </span>
                    </div>
                    <div class="section-content" style="background: linear-gradient(135deg, #f5f3ff, #ede9fe);">
                        <div class="form-grid">
                            <div class="form-group">
                                <label>סטטוס רכישה</label>
                                <?= renderSelect('purchaseStatus', $purchaseStatusOptions, $purchase['purchaseStatus'] ?? 1) ?>
                            </div>
                            <div class="form-group span-2">
                                <label>הערות</label>
                                <textarea name="comment" class="form-control" rows="3"><?= htmlspecialchars($purchase['comment'] ?? '') ?></textarea>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- סקשן 5: מסמכים -->
                <?php if ($isEditMode): ?>
                <div class="sortable-section" data-section="documents">
                    <div class="section-drag-handle" style="background: linear-gradient(135deg, #fce7f3, #fbcfe8);">
                        <button type="button" class="section-toggle-btn" onclick="toggleSection(this)">
                            <i class="fas fa-chevron-down"></i>
                        </button>
                        <span class="section-title" style="color: #9d174d;">
                            <i class="fas fa-folder-open"></i> מסמכים
                        </span>
                    </div>
                    <div class="section-content" style="background: linear-gradient(135deg, #fdf2f8, #fce7f3);">
                        <div id="purchaseExplorer" style="min-height: 200px;">
                            <div style="text-align: center; padding: 40px; color: #64748b;">
                                <i class="fas fa-spinner fa-spin" style="font-size: 24px; margin-bottom: 10px; display: block;"></i>
                                טוען סייר קבצים...
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <div class="form-actions">
                <button type="button" class="btn btn-secondary" onclick="closeForm()">
                    <i class="fas fa-times"></i> ביטול
                </button>
                <button type="submit" class="btn btn-primary" id="submitBtn">
                    <i class="fas fa-save"></i> <?= $isEditMode ? 'עדכן רכישה' : 'צור רכישה' ?>
                </button>
            </div>
        </form>
    </div>

    <script>
        const isEditMode = <?= $isEditMode ? 'true' : 'false' ?>;
        const purchaseId = '<?= addslashes($itemId ?? '') ?>';
        const purchaseClientId = '<?= addslashes($purchase['clientId'] ?? '') ?>';
        const purchaseGraveId = '<?= addslashes($parentId ?? $purchase['graveId'] ?? '') ?>';

        // מטמון היררכיה
        let hierarchyCache = {
            cemeteries: [],
            blocks: [],
            plots: [],
            rows: [],
            areaGraves: [],
            graves: []
        };

        // נתוני לקוח וקבר לחישוב תשלומים
        let selectedCustomerData = null;
        let selectedGraveData = null;
        let purchasePayments = [];

        document.addEventListener('DOMContentLoaded', function() {
            if (typeof PopupAPI !== 'undefined') {
                PopupAPI.setTitle('<?= addslashes($pageTitle) ?>');
            }

            loadCustomers();
            loadCemeteries();

            // האזנה לשינוי לקוח
            document.getElementById('clientId').addEventListener('change', onCustomerSelected);

            // האזנה לשינוי קבר
            document.getElementById('graveSelect').addEventListener('change', onGraveSelected);

            // האזנה לשינוי סטטוס רוכש
            document.getElementById('buyer_status')?.addEventListener('change', tryCalculatePayments);

            // טעינת סייר מסמכים במצב עריכה
            if (isEditMode) {
                initFileExplorer();
            }
        });

        function toggleSection(btn) {
            btn.closest('.sortable-section').classList.toggle('collapsed');
        }

        // טעינת לקוחות - רק לקוחות פעילים (סטטוס 1)
        async function loadCustomers() {
            try {
                const response = await fetch('/dashboard/dashboards/cemeteries/api/customers-api.php?action=list');
                const result = await response.json();

                if (result.success && result.data) {
                    const select = document.getElementById('clientId');
                    select.innerHTML = '<option value="">-- בחר לקוח --</option>';

                    // סטטוסים: 1=פעיל, 2=רוכש (כבר רכש), 3=נפטר
                    const statusLabels = { 1: 'פעיל', 2: 'רוכש', 3: 'נפטר' };

                    result.data.forEach(customer => {
                        const status = parseInt(customer.statusCustomer) || 1;

                        // במצב עריכה - הצג את הלקוח הנוכחי גם אם הסטטוס השתנה
                        // במצב חדש - הצג רק לקוחות פעילים (סטטוס 1)
                        if (!isEditMode && status !== 1) {
                            return; // דלג על לקוחות שכבר רכשו או נפטרו
                        }

                        const option = document.createElement('option');
                        option.value = customer.unicId;

                        // הוסף אינדיקציה לסטטוס
                        let displayText = `${customer.firstName} ${customer.lastName} (${customer.numId || '-'})`;
                        if (status !== 1) {
                            displayText += ` [${statusLabels[status] || 'לא ידוע'}]`;
                            option.style.color = status === 3 ? '#dc2626' : '#f59e0b';
                        }
                        option.textContent = displayText;

                        if (customer.unicId === purchaseClientId) {
                            option.selected = true;
                        }
                        select.appendChild(option);
                    });

                    // בדיקה אם יש לקוחות זמינים
                    if (select.options.length === 1 && !isEditMode) {
                        select.innerHTML = '<option value="">אין לקוחות פעילים לרכישה</option>';
                    }

                    // אם במצב עריכה - טען נתוני לקוח נבחר עם חישוב תושבות בזמן אמת
                    if (isEditMode && purchaseClientId) {
                        const selectedCustomer = result.data.find(c => c.unicId === purchaseClientId);
                        if (selectedCustomer) {
                            let calculatedResident = parseInt(selectedCustomer.resident) || 3;

                            // חישוב תושבות בזמן אמת
                            if (selectedCustomer.countryId || selectedCustomer.cityId) {
                                try {
                                    const residencyParams = new URLSearchParams({
                                        typeId: selectedCustomer.typeId || 1,
                                        countryId: selectedCustomer.countryId || '',
                                        cityId: selectedCustomer.cityId || ''
                                    });
                                    const residencyResponse = await fetch(`/dashboard/dashboards/cemeteries/api/calculate-residency.php?${residencyParams}`);
                                    const residencyResult = await residencyResponse.json();

                                    if (residencyResult.success) {
                                        calculatedResident = residencyResult.residency;
                                    }
                                } catch (e) {
                                    console.error('Error calculating residency:', e);
                                }
                            }

                            selectedCustomerData = {
                                unicId: selectedCustomer.unicId,
                                resident: calculatedResident,
                                name: `${selectedCustomer.firstName} ${selectedCustomer.lastName}`
                            };
                        }
                    }
                }
            } catch (error) {
                console.error('Error loading customers:', error);
            }
        }

        // טיפול בבחירת לקוח
        async function onCustomerSelected() {
            const customerId = document.getElementById('clientId').value;
            if (!customerId) {
                selectedCustomerData = null;
                return;
            }

            try {
                const response = await fetch(`/dashboard/dashboards/cemeteries/api/customers-api.php?action=get&id=${customerId}`);
                const result = await response.json();

                if (result.success && result.data) {
                    // חישוב תושבות בזמן אמת לפי מדינה ועיר
                    let calculatedResident = parseInt(result.data.resident) || 3;

                    if (result.data.countryId || result.data.cityId) {
                        try {
                            const residencyParams = new URLSearchParams({
                                typeId: result.data.typeId || 1,
                                countryId: result.data.countryId || '',
                                cityId: result.data.cityId || ''
                            });
                            const residencyResponse = await fetch(`/dashboard/dashboards/cemeteries/api/calculate-residency.php?${residencyParams}`);
                            const residencyResult = await residencyResponse.json();

                            if (residencyResult.success) {
                                calculatedResident = residencyResult.residency;
                            }
                        } catch (e) {
                            console.error('Error calculating residency:', e);
                        }
                    }

                    selectedCustomerData = {
                        unicId: result.data.unicId,
                        resident: calculatedResident,
                        name: `${result.data.firstName} ${result.data.lastName}`
                    };
                    tryCalculatePayments();
                }
            } catch (error) {
                console.error('Error loading customer details:', error);
            }
        }

        // טיפול בבחירת קבר
        async function onGraveSelected() {
            const graveId = document.getElementById('graveSelect').value;
            if (!graveId) {
                selectedGraveData = null;
                return;
            }

            try {
                const response = await fetch(`/dashboard/dashboards/cemeteries/api/graves-api.php?action=get&id=${graveId}`);
                const result = await response.json();

                if (result.success && result.data) {
                    selectedGraveData = {
                        unicId: result.data.unicId,
                        plotType: parseInt(result.data.plotType) || 1,
                        graveType: parseInt(result.data.graveType) || 1,
                        name: result.data.graveNameHe
                    };
                    console.log('Selected grave:', selectedGraveData);
                    tryCalculatePayments();
                }
            } catch (error) {
                console.error('Error loading grave details:', error);
            }
        }

        // טעינת בתי עלמין
        async function loadCemeteries() {
            try {
                const response = await fetch('/dashboard/dashboards/cemeteries/api/cemeteries-api.php?action=list');
                const result = await response.json();

                if (result.success && result.data) {
                    hierarchyCache.cemeteries = result.data;
                    const select = document.getElementById('cemeterySelect');
                    select.innerHTML = '<option value="">-- בחר בית עלמין --</option>';

                    result.data.forEach(item => {
                        const option = document.createElement('option');
                        option.value = item.unicId;
                        option.textContent = item.cemeteryNameHe;
                        select.appendChild(option);
                    });
                    select.disabled = false;

                    // אם יש קבר נבחר, טען את ההיררכיה
                    if (purchaseGraveId) {
                        loadGraveHierarchy(purchaseGraveId);
                    }
                }
            } catch (error) {
                console.error('Error loading cemeteries:', error);
            }
        }

        // טעינת היררכיה לפי קבר
        async function loadGraveHierarchy(graveId) {
            try {
                const response = await fetch(`/dashboard/dashboards/cemeteries/api/graves-api.php?action=get&id=${graveId}`);
                const result = await response.json();

                if (result.success && result.data) {
                    const grave = result.data;

                    // שמירת נתוני קבר לחישוב תשלומים
                    selectedGraveData = {
                        unicId: grave.unicId,
                        plotType: parseInt(grave.plotType) || 1,
                        graveType: parseInt(grave.graveType) || 1,
                        name: grave.graveNameHe
                    };

                    // בניית היררכיה מהקבר
                    if (grave.cemeteryId) {
                        document.getElementById('cemeterySelect').value = grave.cemeteryId;
                        await filterHierarchy('cemetery');
                    }
                    if (grave.blockId) {
                        document.getElementById('blockSelect').value = grave.blockId;
                        await filterHierarchy('block');
                    }
                    if (grave.plotId) {
                        document.getElementById('plotSelect').value = grave.plotId;
                        await filterHierarchy('plot');
                    }
                    if (grave.lineId) {
                        document.getElementById('rowSelect').value = grave.lineId;
                        await filterHierarchy('row');
                    }
                    if (grave.areaGraveId) {
                        document.getElementById('areaGraveSelect').value = grave.areaGraveId;
                        await filterHierarchy('areaGrave');
                    }
                    document.getElementById('graveSelect').value = graveId;

                    // טעינת תשלומים קיימים במצב עריכה
                    loadExistingPayments();
                }
            } catch (error) {
                console.error('Error loading grave hierarchy:', error);
            }
        }

        // סינון היררכיה - עם סינון לפי קברים פנויים
        async function filterHierarchy(level) {
            const selects = {
                cemetery: { next: 'blockSelect', api: 'blocks-api.php', param: 'cemeteryId', cache: 'blocks', nameField: 'blockNameHe' },
                block: { next: 'plotSelect', api: 'plots-api.php', param: 'blockId', cache: 'plots', nameField: 'plotNameHe' },
                plot: { next: 'rowSelect', api: 'rows-api.php', param: 'plotId', cache: 'rows', nameField: 'lineNameHe' },
                row: { next: 'areaGraveSelect', api: 'areaGraves-api.php', param: 'lineId', cache: 'areaGraves', nameField: 'areaGraveNameHe' },
                areaGrave: { next: 'graveSelect', api: 'graves-api.php', param: 'areaGraveId', cache: 'graves', nameField: 'graveNameHe' }
            };

            const config = selects[level];
            if (!config) return;

            const currentSelect = document.getElementById(level === 'cemetery' ? 'cemeterySelect' : level + 'Select');
            const selectedValue = currentSelect.value;
            const nextSelect = document.getElementById(config.next);

            // איפוס הרשימה הבאה
            nextSelect.innerHTML = '<option value="">טוען...</option>';
            nextSelect.disabled = true;

            // איפוס כל הרשימות אחרי הבאה
            const levels = ['cemetery', 'block', 'plot', 'row', 'areaGrave'];
            const currentIndex = levels.indexOf(level);
            for (let i = currentIndex + 2; i < levels.length; i++) {
                const selectId = levels[i] + 'Select';
                const sel = document.getElementById(selectId);
                if (sel) {
                    sel.innerHTML = '<option value="">--</option>';
                    sel.disabled = true;
                }
            }
            document.getElementById('graveSelect').innerHTML = '<option value="">--</option>';
            document.getElementById('graveSelect').disabled = true;

            if (!selectedValue) return;

            try {
                let url;

                // עבור קברים - השתמש ב-available action לסינון לפי סטטוס
                if (level === 'areaGrave') {
                    url = `/dashboard/dashboards/cemeteries/api/${config.api}?action=available&type=purchase&${config.param}=${selectedValue}`;
                    if (isEditMode && purchaseGraveId) {
                        url += `&currentGraveId=${purchaseGraveId}`;
                    }
                } else {
                    url = `/dashboard/dashboards/cemeteries/api/${config.api}?action=list&${config.param}=${selectedValue}`;
                }

                const response = await fetch(url);
                const result = await response.json();

                if (result.success && result.data) {
                    hierarchyCache[config.cache] = result.data;

                    // עבור חלקות - סנן רק את אלה עם קברים פנויים
                    if (level === 'block') {
                        await filterPlotsWithAvailableGraves(result.data, nextSelect);
                    }
                    // עבור שורות - סנן רק את אלה עם קברים פנויים
                    else if (level === 'plot') {
                        await filterRowsWithAvailableGraves(result.data, nextSelect);
                    }
                    // עבור אחוזות קבר - סנן רק את אלה עם קברים פנויים
                    else if (level === 'row') {
                        await filterAreaGravesWithAvailableGraves(result.data, nextSelect, selectedValue);
                    } else {
                        nextSelect.innerHTML = '<option value="">-- בחר --</option>';
                        result.data.forEach(item => {
                            const option = document.createElement('option');
                            option.value = item.unicId;
                            option.textContent = item[config.nameField] || item.name || '-';
                            nextSelect.appendChild(option);
                        });
                        nextSelect.disabled = false;
                    }
                }
            } catch (error) {
                console.error('Error filtering hierarchy:', error);
            }
        }

        // סינון שורות - רק אלה שיש להן קברים פנויים (קריאות מקבילות)
        async function filterRowsWithAvailableGraves(rows, selectElement) {
            selectElement.innerHTML = '<option value="">טוען שורות...</option>';
            selectElement.disabled = true;

            if (rows.length === 0) {
                selectElement.innerHTML = '<option value="">אין שורות בחלקה זו</option>';
                return;
            }

            // קריאות מקבילות לכל השורות
            const results = await Promise.all(
                rows.map(async (row) => {
                    try {
                        const response = await fetch(`/dashboard/dashboards/cemeteries/api/graves-api.php?action=countAvailable&type=purchase&lineId=${row.unicId}`);
                        const result = await response.json();
                        return {
                            row,
                            availableCount: result.success ? (result.count || 0) : 0
                        };
                    } catch (e) {
                        console.error('Error checking row availability:', e);
                        return { row, availableCount: 0 };
                    }
                })
            );

            // בניית ה-options - רק שורות עם קברים פנויים
            selectElement.innerHTML = '<option value="">-- בחר שורה --</option>';
            let hasAvailable = false;

            results.forEach(({ row, availableCount }) => {
                // רק שורות עם קברים פנויים יופיעו
                if (availableCount > 0) {
                    const option = document.createElement('option');
                    option.value = row.unicId;
                    option.textContent = `${row.lineNameHe || '-'} (${availableCount} פנויים)`;
                    selectElement.appendChild(option);
                    hasAvailable = true;
                }
            });

            selectElement.disabled = !hasAvailable;

            if (!hasAvailable) {
                selectElement.innerHTML = '<option value="">אין קברים פנויים בחלקה זו</option>';
            }
        }

        // סינון אחוזות קבר - רק אלה שיש להן קברים פנויים (קריאות מקבילות)
        async function filterAreaGravesWithAvailableGraves(areaGraves, selectElement, lineId) {
            selectElement.innerHTML = '<option value="">טוען אחוזות קבר...</option>';
            selectElement.disabled = true;

            if (areaGraves.length === 0) {
                selectElement.innerHTML = '<option value="">אין אחוזות קבר בשורה זו</option>';
                return;
            }

            // קריאות מקבילות לכל אחוזות הקבר
            const results = await Promise.all(
                areaGraves.map(async (areaGrave) => {
                    try {
                        const response = await fetch(`/dashboard/dashboards/cemeteries/api/graves-api.php?action=available&type=purchase&areaGraveId=${areaGrave.unicId}`);
                        const result = await response.json();
                        return {
                            areaGrave,
                            availableCount: result.success ? (result.data?.length || 0) : 0
                        };
                    } catch (e) {
                        console.error('Error checking area grave availability:', e);
                        return { areaGrave, availableCount: 0 };
                    }
                })
            );

            // בניית ה-options - רק אחוזות קבר עם קברים פנויים
            selectElement.innerHTML = '<option value="">-- בחר אחוזת קבר --</option>';
            let hasAvailable = false;

            results.forEach(({ areaGrave, availableCount }) => {
                // רק אחוזות קבר עם קברים פנויים יופיעו
                if (availableCount > 0) {
                    const option = document.createElement('option');
                    option.value = areaGrave.unicId;
                    option.textContent = `${areaGrave.areaGraveNameHe || '-'} (${availableCount} פנויים)`;
                    selectElement.appendChild(option);
                    hasAvailable = true;
                }
            });

            selectElement.disabled = !hasAvailable;

            if (!hasAvailable) {
                selectElement.innerHTML = '<option value="">אין קברים פנויים בשורה זו</option>';
            }
        }

        // סינון חלקות - הצגת כולן, disabled לאלה ללא קברים פנויים
        async function filterPlotsWithAvailableGraves(plots, selectElement) {
            selectElement.innerHTML = '<option value="">טוען חלקות...</option>';
            selectElement.disabled = true;

            if (plots.length === 0) {
                selectElement.innerHTML = '<option value="">אין חלקות בגוש זה</option>';
                return;
            }

            // קריאות מקבילות לכל החלקות
            const results = await Promise.all(
                plots.map(async (plot) => {
                    try {
                        const response = await fetch(`/dashboard/dashboards/cemeteries/api/graves-api.php?action=countAvailable&type=purchase&plotId=${plot.unicId}`);
                        const result = await response.json();
                        return {
                            plot,
                            availableCount: result.success ? (result.count || 0) : 0
                        };
                    } catch (e) {
                        console.error('Error checking plot availability:', e);
                        return { plot, availableCount: 0 };
                    }
                })
            );

            // בניית ה-options - כל החלקות, disabled לאלה ללא קברים
            selectElement.innerHTML = '<option value="">-- בחר חלקה --</option>';
            let hasAvailable = false;

            results.forEach(({ plot, availableCount }) => {
                const option = document.createElement('option');
                option.value = plot.unicId;

                if (availableCount > 0) {
                    option.textContent = `${plot.plotNameHe || '-'} (${availableCount} פנויים)`;
                    hasAvailable = true;
                } else {
                    option.textContent = `${plot.plotNameHe || '-'} (אין פנויים)`;
                    option.disabled = true;
                    option.style.color = '#999';
                }
                selectElement.appendChild(option);
            });

            selectElement.disabled = false;
        }

        // ניסיון לחשב תשלומים אוטומטית
        async function tryCalculatePayments() {
            if (isEditMode) return;
            if (!selectedGraveData || !selectedCustomerData) return;

            try {
                const response = await fetch('/dashboard/dashboards/cemeteries/api/payments-api.php?action=getMatching', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        plotType: selectedGraveData.plotType,
                        graveType: selectedGraveData.graveType,
                        resident: selectedCustomerData.resident,
                        buyerStatus: document.getElementById('buyer_status')?.value || 1
                    })
                });

                const data = await response.json();

                if (data.success && data.payments) {
                    purchasePayments = [];
                    const mandatoryPayments = data.payments.filter(p => p.mandatory);

                    mandatoryPayments.forEach(payment => {
                        purchasePayments.push({
                            paymentType: payment.priceDefinition || 1,
                            paymentAmount: parseFloat(payment.price) || 0,
                            customPaymentType: payment.name,
                            paymentDate: '',
                            isPaymentComplete: false,
                            mandatory: true
                        });
                    });

                    displayPaymentsSummary();
                    updateTotalPrice();
                }
            } catch (error) {
                console.error('Error calculating payments:', error);
            }
        }

        // פתיחת מנהל תשלומים חכם
        async function openPaymentsManager() {
            if (!selectedCustomerData) {
                showAlert('יש לבחור לקוח תחילה', 'error');
                return;
            }

            try {
                const response = await fetch('/dashboard/dashboards/cemeteries/api/payments-api.php?action=getMatching', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        plotType: selectedGraveData?.plotType || 1,
                        graveType: selectedGraveData?.graveType || 1,
                        resident: selectedCustomerData.resident,
                        buyerStatus: document.getElementById('buyer_status')?.value || 1
                    })
                });

                const data = await response.json();

                if (data.success) {
                    openPaymentsModal(data.payments || []);
                } else {
                    showAlert('שגיאה בטעינת הגדרות תשלום', 'error');
                }
            } catch (error) {
                console.error('Error loading payments:', error);
                showAlert('שגיאה בטעינת התשלומים', 'error');
            }
        }

        // סוגי תשלומים אופציונליים
        const PAYMENT_TYPES = {
            2: { name: 'שירותי לוויה', icon: '🕯️' },
            4: { name: 'אגרת מצבה', icon: '🪦' },
            5: { name: 'בדיקת עומק', icon: '📏' },
            6: { name: 'פירוק מצבה', icon: '🔨' },
            7: { name: 'הובלה מנתב״ג', icon: '✈️' },
            8: { name: 'טהרה', icon: '💧' },
            9: { name: 'תכריכים', icon: '🏳️' },
            10: { name: 'החלפת שם', icon: '✏️' },
            99: { name: 'אחר', icon: '📝' }
        };

        // שמירת מצב התשלומים בין פתיחות המודל
        let savedPaymentContext = null;
        let savedOptionalSelections = new Set(); // סוגי תשלומים אופציונליים שנבחרו

        // בדיקה אם הקונטקסט השתנה (לקוח/קבר אחר)
        function getPaymentContext() {
            return JSON.stringify({
                customerId: selectedCustomerData?.unicId || null,
                graveId: selectedGraveData?.unicId || null,
                plotType: selectedGraveData?.plotType || null,
                graveType: selectedGraveData?.graveType || null,
                resident: selectedCustomerData?.resident || null
            });
        }

        // פתיחת מודל תשלומים
        function openPaymentsModal(availablePayments) {
            const currentContext = getPaymentContext();

            // אם הקונטקסט השתנה - איפוס הכל
            if (savedPaymentContext !== currentContext) {
                savedPaymentContext = currentContext;
                savedOptionalSelections = new Set();
                customPayments = [];
            }
            // אם אותו קונטקסט - שמור את הבחירות הקודמות

            const mandatoryPayments = availablePayments.filter(p => p.mandatory);
            const optionalPayments = availablePayments.filter(p => !p.mandatory);

            // מצא אילו סוגים כבר קיימים
            const existingTypes = new Set(availablePayments.map(p => parseInt(p.priceDefinition)));

            const modal = document.createElement('div');
            modal.id = 'paymentsModal';
            modal.style.cssText = `
                position: fixed; top: 0; left: 0; right: 0; bottom: 0;
                background: rgba(0,0,0,0.5); z-index: 10000;
                display: flex; align-items: center; justify-content: center;
            `;

            modal.innerHTML = `
                <div style="background: white; border-radius: 12px; width: 90%; max-width: 600px; max-height: 80vh; overflow: auto; direction: rtl;">
                    <div style="padding: 20px; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center;">
                        <h3 style="margin: 0; color: #1e293b;">מנהל תשלומים חכם</h3>
                        <button onclick="closePaymentsModal()" style="background: none; border: none; font-size: 24px; cursor: pointer;">&times;</button>
                    </div>

                    <div style="padding: 20px;">
                        <div style="background: #f1f5f9; padding: 10px; border-radius: 8px; margin-bottom: 15px; font-size: 13px;">
                            <strong>פרמטרים:</strong>
                            תושבות: ${getResidentLabel(selectedCustomerData?.resident)} |
                            סוג חלקה: ${getPlotTypeLabel(selectedGraveData?.plotType)} |
                            סוג קבר: ${getGraveTypeLabel(selectedGraveData?.graveType)}
                        </div>

                        ${mandatoryPayments.length > 0 ? `
                            <h4 style="color: #dc2626; margin-bottom: 10px;">🔒 תשלומי חובה</h4>
                            <div style="margin-bottom: 20px;">
                                ${mandatoryPayments.map((p, i) => `
                                    <label style="display: flex; align-items: center; padding: 10px; background: #fef2f2; border-radius: 8px; margin-bottom: 8px;">
                                        <input type="checkbox" checked disabled data-mandatory="true" data-price="${p.price}" data-name="${p.name}" data-type="${p.priceDefinition}" style="margin-left: 10px;">
                                        <span style="flex: 1;">${p.name}</span>
                                        <strong style="color: #dc2626;">₪${parseFloat(p.price).toLocaleString()}</strong>
                                    </label>
                                `).join('')}
                            </div>
                        ` : ''}

                        ${optionalPayments.length > 0 ? `
                            <h4 style="color: #059669; margin-bottom: 10px;">✓ תשלומים אופציונליים (מחירון)</h4>
                            <div style="margin-bottom: 20px;">
                                ${optionalPayments.map((p, i) => {
                                    const isChecked = savedOptionalSelections.has(String(p.priceDefinition));
                                    return `
                                    <label style="display: flex; align-items: center; padding: 10px; background: #f0fdf4; border-radius: 8px; margin-bottom: 8px; cursor: pointer;">
                                        <input type="checkbox" class="optional-payment" data-price="${p.price}" data-name="${p.name}" data-type="${p.priceDefinition}" ${isChecked ? 'checked' : ''} style="margin-left: 10px;">
                                        <span style="flex: 1;">${p.name}</span>
                                        <strong style="color: #059669;">₪${parseFloat(p.price).toLocaleString()}</strong>
                                    </label>
                                `;}).join('')}
                            </div>
                        ` : ''}

                        <!-- הוספת תשלום ידני -->
                        <h4 style="color: #3b82f6; margin-bottom: 10px;">➕ הוסף תשלום</h4>
                        <div style="background: #eff6ff; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
                            <div style="display: flex; gap: 10px; align-items: flex-end; flex-wrap: wrap;">
                                <div style="flex: 1; min-width: 150px;">
                                    <label style="display: block; font-size: 12px; color: #64748b; margin-bottom: 4px;">סוג תשלום</label>
                                    <select id="addPaymentType" style="width: 100%; padding: 8px; border: 1px solid #cbd5e1; border-radius: 6px;">
                                        <option value="">-- בחר סוג --</option>
                                        ${Object.entries(PAYMENT_TYPES)
                                            .filter(([id]) => !existingTypes.has(parseInt(id)))
                                            .map(([id, type]) => `
                                                <option value="${id}">${type.icon} ${type.name}</option>
                                            `).join('')}
                                    </select>
                                </div>
                                <div style="width: 120px;">
                                    <label style="display: block; font-size: 12px; color: #64748b; margin-bottom: 4px;">מחיר (₪)</label>
                                    <input type="number" id="addPaymentPrice" min="0" step="1" placeholder="0"
                                        style="width: 100%; padding: 8px; border: 1px solid #cbd5e1; border-radius: 6px;">
                                </div>
                                <button type="button" onclick="addCustomPayment()"
                                    style="padding: 8px 16px; background: #3b82f6; color: white; border: none; border-radius: 6px; cursor: pointer; white-space: nowrap;">
                                    <i class="fas fa-plus"></i> הוסף
                                </button>
                            </div>
                            <div id="customPaymentsList" style="margin-top: 10px;"></div>
                        </div>

                        <div style="background: #1e293b; color: white; padding: 15px; border-radius: 8px; text-align: center; margin-top: 20px;">
                            <div style="font-size: 14px; margin-bottom: 5px;">סה"כ לתשלום</div>
                            <div id="modalTotalPrice" style="font-size: 28px; font-weight: bold;">₪0</div>
                        </div>
                    </div>

                    <div style="padding: 20px; border-top: 1px solid #e2e8f0; display: flex; gap: 10px; justify-content: flex-end;">
                        <button onclick="closePaymentsModal()" style="padding: 10px 20px; background: #e2e8f0; border: none; border-radius: 8px; cursor: pointer;">ביטול</button>
                        <button onclick="applyPayments()" style="padding: 10px 20px; background: #059669; color: white; border: none; border-radius: 8px; cursor: pointer;">אשר תשלומים</button>
                    </div>
                </div>
            `;

            document.body.appendChild(modal);

            // עדכון סה"כ בשינוי בחירה + שמירת הבחירות
            modal.querySelectorAll('input[type="checkbox"]').forEach(cb => {
                cb.addEventListener('change', updateModalTotal);
            });

            // מאזין מיוחד לתשלומים אופציונליים - שמירת הבחירות
            modal.querySelectorAll('.optional-payment').forEach(cb => {
                cb.addEventListener('change', function() {
                    const type = this.dataset.type;
                    if (this.checked) {
                        savedOptionalSelections.add(type);
                    } else {
                        savedOptionalSelections.delete(type);
                    }
                });
            });

            // רינדור תשלומים מותאמים אישית (אם יש)
            if (customPayments.length > 0) {
                renderCustomPayments();
            }

            updateModalTotal();
        }

        // רשימת תשלומים מותאמים אישית
        let customPayments = [];

        // הוספת תשלום מותאם אישית
        function addCustomPayment() {
            const typeSelect = document.getElementById('addPaymentType');
            const priceInput = document.getElementById('addPaymentPrice');

            const typeId = typeSelect.value;
            const price = parseFloat(priceInput.value) || 0;

            if (!typeId) {
                alert('יש לבחור סוג תשלום');
                return;
            }
            if (price <= 0) {
                alert('יש להזין מחיר תקין');
                return;
            }

            const typeName = PAYMENT_TYPES[typeId]?.name || 'תשלום';
            const typeIcon = PAYMENT_TYPES[typeId]?.icon || '💰';

            customPayments.push({
                type: typeId,
                name: typeName,
                icon: typeIcon,
                price: price
            });

            // עדכון התצוגה
            renderCustomPayments();
            updateModalTotal();

            // איפוס השדות
            typeSelect.value = '';
            priceInput.value = '';
        }

        // הסרת תשלום מותאם אישית
        function removeCustomPayment(index) {
            customPayments.splice(index, 1);
            renderCustomPayments();
            updateModalTotal();
        }

        // רינדור תשלומים מותאמים אישית
        function renderCustomPayments() {
            const container = document.getElementById('customPaymentsList');
            if (!container) return;

            if (customPayments.length === 0) {
                container.innerHTML = '';
                return;
            }

            container.innerHTML = customPayments.map((p, i) => `
                <div style="display: flex; align-items: center; padding: 8px; background: white; border-radius: 6px; margin-top: 8px;">
                    <input type="checkbox" checked class="custom-payment" data-index="${i}" data-price="${p.price}" data-name="${p.name}" data-type="${p.type}" style="margin-left: 10px;">
                    <span style="flex: 1;">${p.icon} ${p.name}</span>
                    <strong style="color: #3b82f6; margin-left: 10px;">₪${p.price.toLocaleString()}</strong>
                    <button type="button" onclick="removeCustomPayment(${i})" style="background: #fee2e2; color: #dc2626; border: none; border-radius: 4px; padding: 4px 8px; cursor: pointer; margin-right: 8px;">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            `).join('');

            // הוספת מאזינים
            container.querySelectorAll('input[type="checkbox"]').forEach(cb => {
                cb.addEventListener('change', updateModalTotal);
            });
        }

        function closePaymentsModal() {
            document.getElementById('paymentsModal')?.remove();
            // לא מאפסים את customPayments - שומרים את הבחירות לפתיחה הבאה
        }

        function updateModalTotal() {
            const modal = document.getElementById('paymentsModal');
            if (!modal) return;

            let total = 0;
            modal.querySelectorAll('input[type="checkbox"]:checked').forEach(cb => {
                total += parseFloat(cb.dataset.price) || 0;
            });

            document.getElementById('modalTotalPrice').textContent = `₪${total.toLocaleString()}`;
        }

        function applyPayments() {
            const modal = document.getElementById('paymentsModal');
            if (!modal) return;

            purchasePayments = [];
            modal.querySelectorAll('input[type="checkbox"]:checked').forEach(cb => {
                purchasePayments.push({
                    paymentType: parseInt(cb.dataset.type) || 1,
                    paymentAmount: parseFloat(cb.dataset.price) || 0,
                    customPaymentType: cb.dataset.name,
                    paymentDate: '',
                    isPaymentComplete: false,
                    mandatory: cb.dataset.mandatory === 'true'
                });
            });

            displayPaymentsSummary();
            updateTotalPrice();
            closePaymentsModal();
            showAlert('התשלומים נשמרו בהצלחה', 'success');
        }

        function displayPaymentsSummary() {
            const container = document.getElementById('paymentsDisplay');
            if (!container || purchasePayments.length === 0) {
                container.innerHTML = `
                    <div style="text-align: center; color: #94a3b8;">
                        <i class="fas fa-coins" style="font-size: 24px; margin-bottom: 10px;"></i>
                        <div>פירוט תשלומים יופיע כאן</div>
                    </div>
                `;
                return;
            }

            container.innerHTML = purchasePayments.map(p => `
                <div style="display: flex; justify-content: space-between; padding: 8px 12px; background: ${p.mandatory ? '#fef2f2' : '#f0fdf4'}; border-radius: 6px; margin-bottom: 6px;">
                    <span>${p.customPaymentType}</span>
                    <strong style="color: ${p.mandatory ? '#dc2626' : '#059669'};">₪${parseFloat(p.paymentAmount).toLocaleString()}</strong>
                </div>
            `).join('');

            document.getElementById('paymentsList').value = JSON.stringify(purchasePayments);
        }

        function updateTotalPrice() {
            const total = purchasePayments.reduce((sum, p) => sum + (parseFloat(p.paymentAmount) || 0), 0);
            document.getElementById('price').value = total;
            document.getElementById('totalPriceDisplay').textContent = `₪${total.toLocaleString()}`;
        }

        function getResidentLabel(val) {
            const labels = { 1: 'תושב העיר', 2: 'תושב חוץ לעיר', 3: 'תושב חו"ל' };
            return labels[val] || 'לא ידוע';
        }

        function getPlotTypeLabel(val) {
            const labels = { 1: 'פטורה', 2: 'חריגה', 3: 'סגורה' };
            return labels[val] || 'לא ידוע';
        }

        function getGraveTypeLabel(val) {
            const labels = { 1: 'שדה', 2: 'רוויה', 3: 'סנהדרין' };
            return labels[val] || 'לא ידוע';
        }

        // טעינת תשלומים קיימים במצב עריכה
        function loadExistingPayments() {
            if (!isEditMode) return;

            try {
                const paymentsListValue = document.getElementById('paymentsList').value;
                if (paymentsListValue && paymentsListValue !== '[]') {
                    purchasePayments = JSON.parse(paymentsListValue);
                    displayPaymentsSummary();
                    updateTotalPrice();
                }
            } catch (error) {
                console.error('Error loading existing payments:', error);
            }
        }

        // שליחת הטופס
        document.getElementById('purchaseForm').addEventListener('submit', async function(e) {
            e.preventDefault();

            const clientId = this.querySelector('[name="clientId"]').value;
            const graveId = this.querySelector('[name="graveId"]').value;

            if (!clientId || !graveId) {
                showAlert('יש לבחור לקוח וקבר', 'error');
                return;
            }

            const formData = new FormData(this);
            const data = {};
            formData.forEach((value, key) => {
                if (value !== '') {
                    data[key] = value;
                }
            });

            showLoading(true);
            document.getElementById('submitBtn').disabled = true;

            try {
                const action = isEditMode ? 'update' : 'create';
                const url = `/dashboard/dashboards/cemeteries/api/purchases-api.php?action=${action}${isEditMode ? '&id=' + purchaseId : ''}`;

                const response = await fetch(url, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(data)
                });

                const result = await response.json();

                if (result.success) {
                    showAlert(result.message || 'הפעולה בוצעה בהצלחה', 'success');

                    if (window.parent) {
                        if (window.parent.EntityManager) {
                            window.parent.EntityManager.refresh('purchase');
                        }
                    }

                    setTimeout(() => closeForm(), 1500);
                } else {
                    throw new Error(result.error || 'שגיאה בשמירה');
                }
            } catch (error) {
                showAlert(error.message, 'error');
            } finally {
                showLoading(false);
                document.getElementById('submitBtn').disabled = false;
            }
        });

        function showAlert(message, type) {
            const alertBox = document.getElementById('alertBox');
            alertBox.textContent = message;
            alertBox.className = `alert alert-${type} show`;
            if (type === 'success') {
                setTimeout(() => alertBox.classList.remove('show'), 3000);
            }
        }

        function showLoading(show) {
            document.getElementById('loadingOverlay').classList.toggle('show', show);
        }

        function closeForm() {
            if (typeof PopupAPI !== 'undefined') {
                PopupAPI.close();
            }
        }

        function initFileExplorer() {
            if (!purchaseId) return;
            const script = document.createElement('script');
            script.src = '/dashboard/dashboards/cemeteries/explorer/explorer.js?v=' + Date.now();
            script.onload = function() {
                if (typeof FileExplorer !== 'undefined') {
                    window.purchaseExplorer = new FileExplorer('purchaseExplorer', purchaseId, {});
                    window.explorer = window.purchaseExplorer;
                } else {
                    document.getElementById('purchaseExplorer').innerHTML = '<div style="text-align: center; color: #ef4444; padding: 40px;"><i class="fas fa-exclamation-triangle" style="font-size: 24px; margin-bottom: 10px; display: block;"></i>שגיאה בטעינת סייר הקבצים</div>';
                }
            };
            script.onerror = function() {
                document.getElementById('purchaseExplorer').innerHTML = '<div style="text-align: center; color: #ef4444; padding: 40px;"><i class="fas fa-exclamation-triangle" style="font-size: 24px; margin-bottom: 10px; display: block;"></i>שגיאה בטעינת סייר הקבצים</div>';
            };
            document.head.appendChild(script);
        }
    </script>
</body>
</html>
