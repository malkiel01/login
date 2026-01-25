<?php
/*
 * File: dashboard/dashboards/cemeteries/forms/iframe/paymentForm-iframe.php
 * Version: 1.0.0
 * Updated: 2026-01-20
 * Author: Malkiel
 * Description: טופס הגדרת תשלום (יצירה/עריכה) - דף עצמאי לטעינה ב-iframe
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: text/html; charset=utf-8');

require_once dirname(dirname(__DIR__)) . '/config.php';

$itemId = $_GET['itemId'] ?? $_GET['id'] ?? null;
$popupId = $_GET['popupId'] ?? null;
$isEditMode = !empty($itemId);

$payment = null;

if ($isEditMode) {
    try {
        $conn = getDBConnection();
        $stmt = $conn->prepare("SELECT * FROM payments WHERE id = ? AND isActive = 1");
        $stmt->execute([$itemId]);
        $payment = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$payment) {
            die('<!DOCTYPE html><html dir="rtl"><head><meta charset="UTF-8"></head><body style="font-family: Arial; padding: 20px; color: #ef4444;">שגיאה: התשלום לא נמצא</body></html>');
        }
    } catch (Exception $e) {
        die('<!DOCTYPE html><html dir="rtl"><head><meta charset="UTF-8"></head><body style="font-family: Arial; padding: 20px; color: #ef4444;">שגיאה: ' . htmlspecialchars($e->getMessage()) . '</body></html>');
    }
}

$pageTitle = $isEditMode ? 'עריכת הגדרת תשלום' : 'הגדרת תשלום חדש';

// מיפויים
$plotTypeOptions = [-1 => '-- כל הסוגים --', 1 => 'פטורה', 2 => 'חריגה', 3 => 'סגורה'];
$graveTypeOptions = [-1 => '-- כל הסוגים --', 1 => 'שדה', 2 => 'רוויה', 3 => 'סנהדרין'];
$residentOptions = [-1 => '-- כל הסוגים --', 1 => 'תושב העיר', 2 => 'תושב חוץ לעיר', 3 => 'תושב חו״ל'];
$buyerStatusOptions = [-1 => '-- כל הסוגים --', 1 => 'בחיים', 2 => 'לאחר פטירה', 3 => 'בן/בת זוג נפטר'];
$priceDefinitionOptions = [
    '' => '-- בחר --',
    1 => 'עלות קבר',
    2 => 'שירותי לוויה',
    3 => 'שירותי קבורה',
    4 => 'אגרת מצבה',
    5 => 'בדיקת עומק',
    6 => 'פירוק מצבה',
    7 => 'הובלה מנתב״ג',
    8 => 'טהרה',
    9 => 'תכריכים',
    10 => 'החלפת שם'
];

// טעינת בתי עלמין
$cemeteries = [];
try {
    $conn = getDBConnection();
    $cemeteriesStmt = $conn->prepare("SELECT unicId, cemeteryNameHe FROM cemeteries WHERE isActive = 1 ORDER BY cemeteryNameHe");
    $cemeteriesStmt->execute();
    $cemeteries = $cemeteriesStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // ignore
}

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
    <link rel="stylesheet" href="/dashboard/dashboards/cemeteries/forms/forms-mobile.css">
    <link rel="stylesheet" href="/dashboard/dashboards/cemeteries/popup/popup-forms.css?v=<?= time() ?>">
    <script src="/dashboard/dashboards/cemeteries/popup/popup-api.js?v=<?= time() ?>"></script>
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
        .form-control:disabled {
            background: #f1f5f9;
            cursor: not-allowed;
        }
        .form-control.error { border-color: #ef4444; }

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
        .alert-success { background: #dcfce7; color: #166534; border: 1px solid #86efac; }
        .alert-error { background: #fee2e2; color: #991b1b; border: 1px solid #fca5a5; }
    </style>
</head>
<body>
    <div class="form-container">
        <div id="alertBox" class="alert"></div>

        <form id="paymentForm" method="POST">
            <input type="hidden" name="id" value="<?= htmlspecialchars($itemId ?? '') ?>">

            <div class="sortable-sections" id="paymentFormSortableSections">
                <!-- סקשן 1: הגדרת תשלום -->
                <div class="sortable-section" data-section="payment-info">
                    <div class="section-drag-handle" style="background: linear-gradient(135deg, #dbeafe, #bfdbfe);">
                        <button type="button" class="section-toggle-btn" onclick="toggleSection(this)">
                            <i class="fas fa-chevron-down"></i>
                        </button>
                        <span class="section-title" style="color: #1e40af;">
                            <i class="fas fa-coins"></i> הגדרת תשלום
                        </span>
                    </div>
                    <div class="section-content" style="background: linear-gradient(135deg, #eff6ff, #dbeafe);">
                        <div class="form-grid">
                            <div class="form-group">
                                <label>הגדרת מחיר <span class="required">*</span></label>
                                <?= renderSelect('priceDefinition', $priceDefinitionOptions, $payment['priceDefinition'] ?? '', true) ?>
                            </div>
                            <div class="form-group">
                                <label>מחיר <span class="required">*</span></label>
                                <input type="number" name="price" class="form-control" step="0.01" min="0" value="<?= htmlspecialchars($payment['price'] ?? '') ?>" required>
                            </div>
                            <div class="form-group">
                                <label>תאריך תחילת תוקף</label>
                                <input type="date" name="startPayment" class="form-control" value="<?= htmlspecialchars($payment['startPayment'] ?? '') ?>">
                            </div>
                            <div class="form-group">
                                <label>חובה?</label>
                                <select name="mandatory" class="form-control">
                                    <option value="1" <?= (isset($payment['mandatory']) && $payment['mandatory'] == 1) ? 'selected' : '' ?>>כן - חובה</option>
                                    <option value="0" <?= (isset($payment['mandatory']) && $payment['mandatory'] == 0) ? 'selected' : '' ?>>לא - אופציונלי</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- סקשן 2: תנאי סינון -->
                <div class="sortable-section" data-section="filter-conditions">
                    <div class="section-drag-handle" style="background: linear-gradient(135deg, #fef3c7, #fde68a);">
                        <button type="button" class="section-toggle-btn" onclick="toggleSection(this)">
                            <i class="fas fa-chevron-down"></i>
                        </button>
                        <span class="section-title" style="color: #92400e;">
                            <i class="fas fa-filter"></i> תנאי סינון
                        </span>
                    </div>
                    <div class="section-content" style="background: linear-gradient(135deg, #fffbeb, #fef3c7);">
                        <div class="form-grid">
                            <div class="form-group">
                                <label>סוג חלקה</label>
                                <?= renderSelect('plotType', $plotTypeOptions, $payment['plotType'] ?? -1) ?>
                            </div>
                            <div class="form-group">
                                <label>סוג קבר</label>
                                <?= renderSelect('graveType', $graveTypeOptions, $payment['graveType'] ?? -1) ?>
                            </div>
                            <div class="form-group">
                                <label>סוג תושב</label>
                                <?= renderSelect('resident', $residentOptions, $payment['resident'] ?? -1) ?>
                            </div>
                            <div class="form-group">
                                <label>סטטוס רוכש</label>
                                <?= renderSelect('buyerStatus', $buyerStatusOptions, $payment['buyerStatus'] ?? -1) ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- סקשן 3: מיקום (אופציונלי) -->
                <div class="sortable-section collapsed" data-section="location">
                    <div class="section-drag-handle" style="background: linear-gradient(135deg, #dcfce7, #bbf7d0);">
                        <button type="button" class="section-toggle-btn" onclick="toggleSection(this)">
                            <i class="fas fa-chevron-down"></i>
                        </button>
                        <span class="section-title" style="color: #166534;">
                            <i class="fas fa-map-marker-alt"></i> מיקום (אופציונלי)
                        </span>
                    </div>
                    <div class="section-content" style="background: linear-gradient(135deg, #f0fdf4, #dcfce7);">
                        <div class="form-grid">
                            <div class="form-group">
                                <label>בית עלמין</label>
                                <select name="cemeteryId" id="cemeterySelect" class="form-control" onchange="filterHierarchy('cemetery')">
                                    <option value="-1">-- כל בתי העלמין --</option>
                                    <?php foreach ($cemeteries as $cemetery): ?>
                                    <option value="<?= $cemetery['unicId'] ?>" <?= ($payment && isset($payment['cemeteryId']) && $payment['cemeteryId'] == $cemetery['unicId']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($cemetery['cemeteryNameHe']) ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>גוש</label>
                                <select name="blockId" id="blockSelect" class="form-control" onchange="filterHierarchy('block')" disabled>
                                    <option value="-1">-- בחר בית עלמין --</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>חלקה</label>
                                <select name="plotId" id="plotSelect" class="form-control" onchange="filterHierarchy('plot')" disabled>
                                    <option value="-1">-- בחר גוש --</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>שורה</label>
                                <select name="lineId" id="lineSelect" class="form-control" disabled>
                                    <option value="-1">-- בחר חלקה --</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="form-actions">
                <button type="button" class="btn btn-secondary" onclick="closeForm()">
                    <i class="fas fa-times"></i> ביטול
                </button>
                <button type="submit" class="btn btn-primary" id="submitBtn">
                    <i class="fas fa-save"></i> <?= $isEditMode ? 'עדכן תשלום' : 'צור תשלום' ?>
                </button>
            </div>
        </form>
    </div>

    <script>
        const isEditMode = <?= $isEditMode ? 'true' : 'false' ?>;
        const paymentId = '<?= addslashes($itemId ?? '') ?>';
        const paymentCemeteryId = '<?= addslashes($payment['cemeteryId'] ?? '') ?>';
        const paymentBlockId = '<?= addslashes($payment['blockId'] ?? '') ?>';
        const paymentPlotId = '<?= addslashes($payment['plotId'] ?? '') ?>';
        const paymentLineId = '<?= addslashes($payment['lineId'] ?? '') ?>';

        document.addEventListener('DOMContentLoaded', function() {
            if (typeof PopupAPI !== 'undefined') {
                PopupAPI.setTitle('<?= addslashes($pageTitle) ?>');
            }

            // טעינת היררכיה אם במצב עריכה
            if (isEditMode && paymentCemeteryId && paymentCemeteryId !== '-1') {
                loadHierarchy();
            }
        });

        function toggleSection(btn) {
            btn.closest('.sortable-section').classList.toggle('collapsed');
        }

        // טעינת היררכיה במצב עריכה
        async function loadHierarchy() {
            if (paymentCemeteryId && paymentCemeteryId !== '-1') {
                await filterHierarchy('cemetery');
                if (paymentBlockId && paymentBlockId !== '-1') {
                    document.getElementById('blockSelect').value = paymentBlockId;
                    await filterHierarchy('block');
                    if (paymentPlotId && paymentPlotId !== '-1') {
                        document.getElementById('plotSelect').value = paymentPlotId;
                        await filterHierarchy('plot');
                        if (paymentLineId && paymentLineId !== '-1') {
                            document.getElementById('lineSelect').value = paymentLineId;
                        }
                    }
                }
            }
        }

        // סינון היררכיה
        async function filterHierarchy(level) {
            const selects = {
                cemetery: { next: 'blockSelect', api: 'blocks-api.php', param: 'cemeteryId', nameField: 'blockNameHe' },
                block: { next: 'plotSelect', api: 'plots-api.php', param: 'blockId', nameField: 'plotNameHe' },
                plot: { next: 'lineSelect', api: 'rows-api.php', param: 'plotId', nameField: 'lineNameHe' }
            };

            const config = selects[level];
            if (!config) return;

            const currentSelect = document.getElementById(level + 'Select');
            const selectedValue = currentSelect.value;
            const nextSelect = document.getElementById(config.next);

            // איפוס הבאים
            const order = ['blockSelect', 'plotSelect', 'lineSelect'];
            const startIndex = order.indexOf(config.next);
            for (let i = startIndex; i < order.length; i++) {
                const sel = document.getElementById(order[i]);
                sel.innerHTML = '<option value="-1">-- בחר --</option>';
                sel.disabled = true;
            }

            if (!selectedValue || selectedValue === '-1') return;

            try {
                const response = await fetch(`/dashboard/dashboards/cemeteries/api/${config.api}?action=list&${config.param}=${selectedValue}`);
                const result = await response.json();

                if (result.success && result.data) {
                    nextSelect.innerHTML = '<option value="-1">-- כל --</option>';
                    result.data.forEach(item => {
                        const option = document.createElement('option');
                        option.value = item.unicId;
                        option.textContent = item[config.nameField] || '-';
                        nextSelect.appendChild(option);
                    });
                    nextSelect.disabled = false;
                }
            } catch (error) {
                console.error('Error filtering hierarchy:', error);
            }
        }

        // שליחת הטופס
        document.getElementById('paymentForm').addEventListener('submit', async function(e) {
            e.preventDefault();

            const priceDefinition = this.querySelector('[name="priceDefinition"]').value;
            const price = this.querySelector('[name="price"]').value;

            if (!priceDefinition) {
                showAlert('יש לבחור הגדרת מחיר', 'error');
                return;
            }

            if (!price || price <= 0) {
                showAlert('יש להזין מחיר תקין', 'error');
                return;
            }

            const submitBtn = document.getElementById('submitBtn');
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> שומר...';

            try {
                const formData = new FormData(this);

                // Convert FormData to JSON object
                const data = {};
                formData.forEach((value, key) => {
                    // Convert -1 values to null for database
                    if (value === '-1') {
                        data[key] = null;
                    } else {
                        data[key] = value;
                    }
                });

                let url = '/dashboard/dashboards/cemeteries/api/payments-api.php?action=' + (isEditMode ? 'update' : 'create');
                if (isEditMode) {
                    url += '&id=<?= $itemId ?>';
                }

                const response = await fetch(url, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(data)
                });

                const result = await response.json();

                if (result.success) {
                    showAlert(isEditMode ? 'התשלום עודכן בהצלחה' : 'התשלום נוצר בהצלחה', 'success');
                    setTimeout(() => {
                        closeForm(true);
                    }, 1000);
                } else {
                    showAlert(result.error || 'שגיאה בשמירת התשלום', 'error');
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = '<i class="fas fa-save"></i> <?= $isEditMode ? 'עדכן תשלום' : 'צור תשלום' ?>';
                }
            } catch (error) {
                console.error('Error saving payment:', error);
                showAlert('שגיאה בשמירת התשלום', 'error');
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<i class="fas fa-save"></i> <?= $isEditMode ? 'עדכן תשלום' : 'צור תשלום' ?>';
            }
        });

        function showAlert(message, type) {
            const alertBox = document.getElementById('alertBox');
            alertBox.textContent = message;
            alertBox.className = 'alert alert-' + type;
            alertBox.style.display = 'block';
            setTimeout(() => {
                alertBox.style.display = 'none';
            }, 5000);
        }

        function closeForm(refresh = false) {
            if (typeof PopupAPI !== 'undefined') {
                PopupAPI.close();
            }
            if (refresh && window.parent && window.parent.loadData) {
                window.parent.loadData();
            }
        }
    </script>
    <!-- סקריפט לגרירת סקשנים -->
    <script src="/dashboard/dashboards/cemeteries/forms/sortable-sections.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            if (typeof SortableSections !== 'undefined') {
                SortableSections.init('paymentFormSortableSections', 'paymentForm');
            }
        });
    </script>
</body>
</html>
