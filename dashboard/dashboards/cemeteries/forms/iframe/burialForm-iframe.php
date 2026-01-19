<?php
/*
 * File: dashboard/dashboards/cemeteries/forms/iframe/burialForm-iframe.php
 * Version: 1.0.0
 * Updated: 2026-01-19
 * Author: Malkiel
 * Description: טופס קבורה (יצירה/עריכה) - דף עצמאי לטעינה ב-iframe
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: text/html; charset=utf-8');

require_once dirname(dirname(__DIR__)) . '/config.php';

$itemId = $_GET['itemId'] ?? $_GET['id'] ?? null;
$parentId = $_GET['parentId'] ?? $_GET['parent_id'] ?? $_GET['graveId'] ?? null;
$popupId = $_GET['popupId'] ?? null;
$isEditMode = !empty($itemId);

$burial = null;

if ($isEditMode) {
    try {
        $conn = getDBConnection();
        $stmt = $conn->prepare("
            SELECT b.*,
                   c.firstName as deceasedFirstName,
                   c.lastName as deceasedLastName,
                   g.graveNumber
            FROM burials b
            LEFT JOIN customers c ON b.clientId = c.unicId
            LEFT JOIN graves g ON b.graveId = g.unicId
            WHERE b.unicId = ? AND b.isActive = 1
        ");
        $stmt->execute([$itemId]);
        $burial = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$burial) {
            die('<!DOCTYPE html><html dir="rtl"><head><meta charset="UTF-8"></head><body style="font-family: Arial; padding: 20px; color: #ef4444;">שגיאה: הקבורה לא נמצאה</body></html>');
        }
    } catch (Exception $e) {
        die('<!DOCTYPE html><html dir="rtl"><head><meta charset="UTF-8"></head><body style="font-family: Arial; padding: 20px; color: #ef4444;">שגיאה: ' . htmlspecialchars($e->getMessage()) . '</body></html>');
    }
}

$deceasedName = $isEditMode ? ($burial['deceasedFirstName'] . ' ' . $burial['deceasedLastName']) : '';
$pageTitle = $isEditMode ? 'עריכת קבורה - ' . $deceasedName : 'קבורה חדשה';

// מיפויים
$yesNoOptions = ['לא' => 'לא', 'כן' => 'כן'];

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
    <title><?= htmlspecialchars($pageTitle) ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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
        .form-control[readonly] { background: #f1f5f9; }
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
    </style>
</head>
<body>
    <div class="loading-overlay" id="loadingOverlay">
        <div class="loading-spinner"></div>
    </div>

    <div class="form-container">
        <div id="alertBox" class="alert"></div>

        <form id="burialForm" novalidate>
            <input type="hidden" name="unicId" value="<?= htmlspecialchars($burial['unicId'] ?? '') ?>">

            <div class="sortable-sections">
                <!-- סקשן 1: פרטי נפטר -->
                <div class="sortable-section" data-section="deceased">
                    <div class="section-drag-handle" style="background: linear-gradient(135deg, #fee2e2, #fecaca);">
                        <button type="button" class="section-toggle-btn" onclick="toggleSection(this)">
                            <i class="fas fa-chevron-down"></i>
                        </button>
                        <span class="section-title" style="color: #991b1b;">
                            <i class="fas fa-user"></i> פרטי הנפטר/ת
                        </span>
                    </div>
                    <div class="section-content" style="background: linear-gradient(135deg, #fef2f2, #fee2e2);">
                        <div class="form-grid">
                            <div class="form-group span-2">
                                <label>נפטר/ת <span class="required">*</span></label>
                                <select name="clientId" id="clientId" class="form-control" required>
                                    <option value="">טוען לקוחות...</option>
                                </select>
                            </div>
                            <?php if ($isEditMode && !empty($burial['serialBurialId'])): ?>
                            <div class="form-group">
                                <label>מספר תיק קבורה</label>
                                <input type="text" name="serialBurialId" class="form-control" readonly
                                    value="<?= htmlspecialchars($burial['serialBurialId']) ?>">
                            </div>
                            <?php endif; ?>
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

                        <div class="form-grid" style="margin-top: 15px; padding-top: 15px; border-top: 1px dashed #a7f3d0;">
                            <div class="form-group">
                                <label>רכישה קשורה</label>
                                <select name="purchaseId" id="purchaseId" class="form-control">
                                    <option value="">-- ללא רכישה --</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- סקשן 3: פרטי פטירה -->
                <div class="sortable-section" data-section="death">
                    <div class="section-drag-handle" style="background: linear-gradient(135deg, #e0e7ff, #c7d2fe);">
                        <button type="button" class="section-toggle-btn" onclick="toggleSection(this)">
                            <i class="fas fa-chevron-down"></i>
                        </button>
                        <span class="section-title" style="color: #3730a3;">
                            <i class="fas fa-calendar-times"></i> פרטי פטירה
                        </span>
                    </div>
                    <div class="section-content" style="background: linear-gradient(135deg, #eef2ff, #e0e7ff);">
                        <div class="form-grid">
                            <div class="form-group">
                                <label>תאריך פטירה <span class="required">*</span></label>
                                <input type="date" name="dateDeath" class="form-control" required
                                    value="<?= htmlspecialchars($burial['dateDeath'] ?? '') ?>">
                            </div>
                            <div class="form-group">
                                <label>שעת פטירה</label>
                                <input type="time" name="timeDeath" class="form-control"
                                    value="<?= htmlspecialchars($burial['timeDeath'] ?? '') ?>">
                            </div>
                            <div class="form-group">
                                <label>מקום הפטירה <span class="required">*</span></label>
                                <input type="text" name="placeDeath" class="form-control" required
                                    placeholder="עיר/בית חולים"
                                    value="<?= htmlspecialchars($burial['placeDeath'] ?? '') ?>">
                            </div>
                            <div class="form-group">
                                <label>פטירה בחו"ל</label>
                                <?= renderSelect('deathAbroad', $yesNoOptions, $burial['deathAbroad'] ?? 'לא') ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- סקשן 4: פרטי קבורה -->
                <div class="sortable-section" data-section="burial">
                    <div class="section-drag-handle" style="background: linear-gradient(135deg, #fef3c7, #fde68a);">
                        <button type="button" class="section-toggle-btn" onclick="toggleSection(this)">
                            <i class="fas fa-chevron-down"></i>
                        </button>
                        <span class="section-title" style="color: #92400e;">
                            <i class="fas fa-book-dead"></i> פרטי קבורה
                        </span>
                    </div>
                    <div class="section-content" style="background: linear-gradient(135deg, #fffbeb, #fef3c7);">
                        <div class="form-grid">
                            <div class="form-group">
                                <label>תאריך קבורה <span class="required">*</span></label>
                                <input type="date" name="dateBurial" class="form-control" required
                                    value="<?= htmlspecialchars($burial['dateBurial'] ?? '') ?>">
                            </div>
                            <div class="form-group">
                                <label>שעת קבורה <span class="required">*</span></label>
                                <input type="time" name="timeBurial" class="form-control" required
                                    value="<?= htmlspecialchars($burial['timeBurial'] ?? '') ?>">
                            </div>
                            <div class="form-group">
                                <label>קבורת ביטוח לאומי</label>
                                <?= renderSelect('nationalInsuranceBurial', $yesNoOptions, $burial['nationalInsuranceBurial'] ?? 'לא') ?>
                            </div>
                            <div class="form-group">
                                <label>רשיון קבורה</label>
                                <input type="text" name="buriaLicense" class="form-control"
                                    placeholder="מספר רשיון"
                                    value="<?= htmlspecialchars($burial['buriaLicense'] ?? '') ?>">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- סקשן 5: פרטים נוספים -->
                <div class="sortable-section" data-section="additional">
                    <div class="section-drag-handle" style="background: linear-gradient(135deg, #ede9fe, #c4b5fd);">
                        <button type="button" class="section-toggle-btn" onclick="toggleSection(this)">
                            <i class="fas fa-chevron-down"></i>
                        </button>
                        <span class="section-title" style="color: #5b21b6;">
                            <i class="fas fa-info-circle"></i> פרטים נוספים
                        </span>
                    </div>
                    <div class="section-content" style="background: linear-gradient(135deg, #f5f3ff, #ede9fe);">
                        <div class="form-grid">
                            <div class="form-group">
                                <label>קרבת איש קשר</label>
                                <input type="text" name="kinship" class="form-control"
                                    placeholder="בן/בת/אח/הורה וכו'"
                                    value="<?= htmlspecialchars($burial['kinship'] ?? '') ?>">
                            </div>
                            <div class="form-group">
                                <label>תאריך פתיחת תיק</label>
                                <input type="date" name="dateOpening_tld" class="form-control"
                                    value="<?= htmlspecialchars($burial['dateOpening_tld'] ?? date('Y-m-d')) ?>">
                            </div>
                            <div class="form-group">
                                <label>תאריך דיווח לביטוח לאומי</label>
                                <input type="date" name="reportingBL" class="form-control"
                                    value="<?= htmlspecialchars($burial['reportingBL'] ?? '') ?>">
                            </div>
                            <div class="form-group span-2">
                                <label>הערות</label>
                                <textarea name="comment" class="form-control" rows="3"
                                    placeholder="הערות נוספות..."><?= htmlspecialchars($burial['comment'] ?? '') ?></textarea>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- סקשן 6: מסמכים -->
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
                        <div id="documentsContainer">
                            <div class="documents-toolbar" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                                <span style="color: #64748b; font-size: 13px;">
                                    <i class="fas fa-info-circle"></i> ניהול מסמכים של הקבורה
                                </span>
                                <button type="button" class="btn btn-primary" style="padding: 8px 16px; font-size: 13px;" onclick="uploadDocument()">
                                    <i class="fas fa-upload"></i> העלאת מסמך
                                </button>
                            </div>
                            <div id="documentsList" class="documents-list" style="min-height: 100px; border: 2px dashed #e2e8f0; border-radius: 8px; display: flex; align-items: center; justify-content: center;">
                                <div style="text-align: center; color: #94a3b8; padding: 20px;">
                                    <i class="fas fa-folder-open" style="font-size: 32px; margin-bottom: 10px; display: block;"></i>
                                    <span>סייר קבצים יטען בהמשך</span>
                                </div>
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
                    <i class="fas fa-save"></i> <?= $isEditMode ? 'עדכן קבורה' : 'צור קבורה' ?>
                </button>
            </div>
        </form>
    </div>

    <script>
        const isEditMode = <?= $isEditMode ? 'true' : 'false' ?>;
        const burialId = '<?= addslashes($itemId ?? '') ?>';
        const burialClientId = '<?= addslashes($burial['clientId'] ?? '') ?>';
        const burialGraveId = '<?= addslashes($parentId ?? $burial['graveId'] ?? '') ?>';
        const burialPurchaseId = '<?= addslashes($burial['purchaseId'] ?? '') ?>';

        document.addEventListener('DOMContentLoaded', function() {
            if (typeof PopupAPI !== 'undefined') {
                PopupAPI.setTitle('<?= addslashes($pageTitle) ?>');
            }

            loadCustomers();
            loadCemeteries();
        });

        function toggleSection(btn) {
            btn.closest('.sortable-section').classList.toggle('collapsed');
        }

        // טעינת לקוחות - לא לקוחות שכבר נפטרו (סטטוס 3)
        async function loadCustomers() {
            try {
                const response = await fetch('/dashboard/dashboards/cemeteries/api/customers-api.php?action=list');
                const result = await response.json();

                if (result.success && result.data) {
                    const select = document.getElementById('clientId');
                    select.innerHTML = '<option value="">-- בחר נפטר/ת --</option>';

                    // סטטוסים: 1=פעיל, 2=רוכש, 3=נפטר (כבר נקבר)
                    const statusLabels = { 1: 'פעיל', 2: 'רוכש', 3: 'נפטר' };

                    result.data.forEach(customer => {
                        const status = parseInt(customer.statusCustomer) || 1;

                        // במצב עריכה - הצג את הלקוח הנוכחי גם אם הסטטוס השתנה
                        // במצב חדש - לא להציג לקוחות עם סטטוס 3 (כבר נקברו)
                        if (!isEditMode && status === 3) {
                            return; // דלג על לקוחות שכבר נקברו
                        }

                        const option = document.createElement('option');
                        option.value = customer.unicId;

                        // הוסף אינדיקציה לסטטוס
                        let displayText = `${customer.firstName} ${customer.lastName} (${customer.numId || '-'})`;
                        if (status === 3) {
                            displayText += ' [כבר נקבר/ה]';
                            option.style.color = '#dc2626';
                        } else if (status === 2) {
                            displayText += ' [יש רכישה]';
                            option.style.color = '#059669'; // ירוק - מוכן לקבורה
                        }
                        option.textContent = displayText;

                        if (customer.unicId === burialClientId) {
                            option.selected = true;
                        }
                        select.appendChild(option);
                    });

                    // בדיקה אם יש לקוחות זמינים
                    if (select.options.length === 1 && !isEditMode) {
                        select.innerHTML = '<option value="">אין לקוחות זמינים לקבורה</option>';
                    }
                }
            } catch (error) {
                console.error('Error loading customers:', error);
            }
        }

        // טעינת בתי עלמין
        async function loadCemeteries() {
            try {
                const response = await fetch('/dashboard/dashboards/cemeteries/api/cemeteries-api.php?action=list');
                const result = await response.json();

                if (result.success && result.data) {
                    const select = document.getElementById('cemeterySelect');
                    select.innerHTML = '<option value="">-- בחר בית עלמין --</option>';

                    result.data.forEach(item => {
                        const option = document.createElement('option');
                        option.value = item.unicId;
                        option.textContent = item.cemeteryNameHe;
                        select.appendChild(option);
                    });
                    select.disabled = false;

                    if (burialGraveId) {
                        loadGraveHierarchy(burialGraveId);
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

                    // טעינת רכישות לקבר זה
                    loadPurchasesForGrave(graveId);
                }
            } catch (error) {
                console.error('Error loading grave hierarchy:', error);
            }
        }

        // טעינת רכישות לקבר
        async function loadPurchasesForGrave(graveId) {
            try {
                const response = await fetch(`/dashboard/dashboards/cemeteries/api/purchases-api.php?action=list&graveId=${graveId}`);
                const result = await response.json();

                const select = document.getElementById('purchaseId');
                select.innerHTML = '<option value="">-- ללא רכישה --</option>';

                if (result.success && result.data) {
                    result.data.forEach(purchase => {
                        const option = document.createElement('option');
                        option.value = purchase.unicId;
                        option.textContent = `רכישה #${purchase.serialPurchaseId || purchase.unicId}`;
                        if (purchase.unicId === burialPurchaseId) {
                            option.selected = true;
                        }
                        select.appendChild(option);
                    });
                }
            } catch (error) {
                console.error('Error loading purchases:', error);
            }
        }

        // סינון היררכיה - עם סינון לפי קברים פנויים/נרכשו
        async function filterHierarchy(level) {
            const selects = {
                cemetery: { next: 'blockSelect', api: 'blocks-api.php', param: 'cemeteryId', nameField: 'blockNameHe' },
                block: { next: 'plotSelect', api: 'plots-api.php', param: 'blockId', nameField: 'plotNameHe' },
                plot: { next: 'rowSelect', api: 'rows-api.php', param: 'plotId', nameField: 'lineNameHe' },
                row: { next: 'areaGraveSelect', api: 'areaGraves-api.php', param: 'lineId', nameField: 'areaGraveNameHe' },
                areaGrave: { next: 'graveSelect', api: 'graves-api.php', param: 'areaGraveId', nameField: 'graveNameHe' }
            };

            const config = selects[level];
            if (!config) return;

            const currentSelect = document.getElementById(level === 'cemetery' ? 'cemeterySelect' : level + 'Select');
            const selectedValue = currentSelect.value;
            const nextSelect = document.getElementById(config.next);

            nextSelect.innerHTML = '<option value="">טוען...</option>';
            nextSelect.disabled = true;

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
                // לקבורה: פנויים (1) + נרכשו (2) - לא קבורים (3)
                if (level === 'areaGrave') {
                    url = `/dashboard/dashboards/cemeteries/api/${config.api}?action=available&type=burial&${config.param}=${selectedValue}`;
                    if (isEditMode && burialGraveId) {
                        url += `&currentGraveId=${burialGraveId}`;
                    }
                } else {
                    url = `/dashboard/dashboards/cemeteries/api/${config.api}?action=list&${config.param}=${selectedValue}`;
                }

                const response = await fetch(url);
                const result = await response.json();

                if (result.success && result.data) {
                    // עבור שורות - סנן רק את אלה עם קברים זמינים
                    if (level === 'plot') {
                        await filterRowsWithAvailableGraves(result.data, nextSelect);
                    }
                    // עבור אחוזות קבר - סנן רק את אלה עם קברים זמינים
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

        // סינון שורות - רק אלה שיש להן קברים זמינים (קריאות מקבילות)
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
                        const response = await fetch(`/dashboard/dashboards/cemeteries/api/graves-api.php?action=countAvailable&type=burial&lineId=${row.unicId}`);
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

            // בניית ה-options - רק שורות עם קברים זמינים
            selectElement.innerHTML = '<option value="">-- בחר שורה --</option>';
            let hasAvailable = false;

            results.forEach(({ row, availableCount }) => {
                if (availableCount > 0) {
                    const option = document.createElement('option');
                    option.value = row.unicId;
                    option.textContent = `${row.lineNameHe || '-'} (${availableCount} זמינים)`;
                    selectElement.appendChild(option);
                    hasAvailable = true;
                }
            });

            selectElement.disabled = !hasAvailable;

            if (!hasAvailable) {
                selectElement.innerHTML = '<option value="">אין קברים זמינים בחלקה זו</option>';
            }
        }

        // סינון אחוזות קבר - רק אלה שיש להן קברים זמינים (קריאות מקבילות)
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
                        const response = await fetch(`/dashboard/dashboards/cemeteries/api/graves-api.php?action=available&type=burial&areaGraveId=${areaGrave.unicId}`);
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

            // בניית ה-options - רק אחוזות קבר עם קברים זמינים
            selectElement.innerHTML = '<option value="">-- בחר אחוזת קבר --</option>';
            let hasAvailable = false;

            results.forEach(({ areaGrave, availableCount }) => {
                if (availableCount > 0) {
                    const option = document.createElement('option');
                    option.value = areaGrave.unicId;
                    option.textContent = `${areaGrave.areaGraveNameHe || '-'} (${availableCount} זמינים)`;
                    selectElement.appendChild(option);
                    hasAvailable = true;
                }
            });

            selectElement.disabled = !hasAvailable;

            if (!hasAvailable) {
                selectElement.innerHTML = '<option value="">אין קברים זמינים בשורה זו</option>';
            }
        }

        // שליחת הטופס
        document.getElementById('burialForm').addEventListener('submit', async function(e) {
            e.preventDefault();

            const clientId = this.querySelector('[name="clientId"]').value;
            const graveId = this.querySelector('[name="graveId"]').value;
            const dateDeath = this.querySelector('[name="dateDeath"]').value;
            const dateBurial = this.querySelector('[name="dateBurial"]').value;
            const placeDeath = this.querySelector('[name="placeDeath"]').value;
            const timeBurial = this.querySelector('[name="timeBurial"]').value;

            if (!clientId || !graveId || !dateDeath || !dateBurial || !placeDeath || !timeBurial) {
                showAlert('יש למלא את כל שדות החובה', 'error');
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
                const url = `/dashboard/dashboards/cemeteries/api/burials-api.php?action=${action}${isEditMode ? '&id=' + burialId : ''}`;

                const response = await fetch(url, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(data)
                });

                const result = await response.json();

                if (result.success) {
                    showAlert(result.message || 'הפעולה בוצעה בהצלחה', 'success');

                    if (window.parent && window.parent.EntityManager) {
                        window.parent.EntityManager.refresh('burial');
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

        function uploadDocument() {
            alert('פונקציית העלאת מסמכים תתווסף בהמשך');
        }
    </script>
</body>
</html>
