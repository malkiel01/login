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
                   g.graveNameHe
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
            overflow: visible;
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

        /* Custom Select Styles for Customer */
        .custom-select-container { position: relative; }
        .custom-select-display {
            padding: 10px 12px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            cursor: pointer;
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: white;
            min-height: 42px;
            transition: border-color 0.2s, box-shadow 0.2s;
        }
        .custom-select-display:hover { border-color: #94a3b8; }
        .custom-select-display.open { border-color: #3b82f6; box-shadow: 0 0 0 3px rgba(59,130,246,0.1); }
        .custom-select-dropdown {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            z-index: 9999;
            margin-top: 4px;
            max-height: 300px;
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }
        .custom-select-search {
            padding: 10px 12px;
            border: none;
            border-bottom: 1px solid #e2e8f0;
            font-size: 14px;
            outline: none;
        }
        .custom-select-options {
            overflow-y: auto;
            max-height: 250px;
        }
        .custom-select-option {
            padding: 10px 12px;
            cursor: pointer;
            border-bottom: 1px solid #f1f5f9;
            transition: background 0.15s;
        }
        .custom-select-option:hover { background: #f1f5f9; }
        .custom-select-option.selected { background: #eff6ff; color: #3b82f6; }
        .custom-select-option .option-name { font-weight: 500; }
        .custom-select-option .option-details { font-size: 12px; color: #64748b; margin-top: 2px; }
        .custom-select-option .option-status { font-size: 11px; padding: 2px 6px; border-radius: 4px; margin-right: 8px; }
        .custom-select-option .status-buyer { background: #dcfce7; color: #166534; }
        .custom-option-hint { padding: 15px; text-align: center; color: #94a3b8; font-size: 13px; }
        .custom-option-loading { padding: 15px; text-align: center; color: #64748b; }
        .custom-option-loading i { animation: spin 1s linear infinite; margin-left: 8px; }
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
                                <input type="hidden" name="clientId" id="clientId" value="<?= htmlspecialchars($burial['clientId'] ?? '') ?>">
                                <div class="custom-select-container" id="customerSelectContainer">
                                    <div class="custom-select-display" onclick="toggleCustomerDropdown()">
                                        <span id="customerDisplayText">טוען...</span>
                                        <i class="fas fa-chevron-down"></i>
                                    </div>
                                    <div class="custom-select-dropdown" id="customerDropdown" style="display: none;">
                                        <input type="text" id="customerSearch" class="custom-select-search"
                                               placeholder="חפש לפי שם, ת.ז או טלפון..." oninput="filterCustomerOptions()">
                                        <div class="custom-select-options" id="customerOptions">
                                            <div class="custom-option-hint">הקלד לחיפוש לקוח...</div>
                                        </div>
                                    </div>
                                </div>
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

                        <input type="hidden" name="purchaseId" id="purchaseId" value="<?= htmlspecialchars($burial['purchaseId'] ?? '') ?>">
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

        // דגל למניעת עדכון לקוח בזמן טעינת היררכיה מבחירת לקוח
        let isLoadingHierarchyFromCustomer = false;

        document.addEventListener('DOMContentLoaded', function() {
            if (typeof PopupAPI !== 'undefined') {
                PopupAPI.setTitle('<?= addslashes($pageTitle) ?>');
            }

            loadCustomerDisplay();
            loadCemeteries();

            // סגירת dropdown בלחיצה מחוץ
            document.addEventListener('click', function(e) {
                if (!e.target.closest('#customerSelectContainer')) {
                    closeCustomerDropdown();
                }
            });

            // האזנה לשינוי קבר - בדיקת רכישה ומילוי לקוח
            document.getElementById('graveSelect').addEventListener('change', function() {
                onGraveSelected(this.value);
            });
        });

        function toggleSection(btn) {
            btn.closest('.sortable-section').classList.toggle('collapsed');
        }

        // טעינת תצוגת לקוח נבחר (עם חיפוש בצד השרת)
        async function loadCustomerDisplay() {
            const displayText = document.getElementById('customerDisplayText');

            // אם יש לקוח נוכחי (מצב עריכה) - טען את הפרטים שלו
            if (burialClientId) {
                try {
                    const response = await fetch(`/dashboard/dashboards/cemeteries/api/customers-api.php?action=get&id=${burialClientId}`);
                    const result = await response.json();

                    if (result.success && result.data) {
                        const customer = result.data;
                        const status = parseInt(customer.statusCustomer) || 1;
                        let statusText = '';
                        if (status === 3) {
                            statusText = ' [כבר נקבר/ה]';
                        } else if (status === 2) {
                            statusText = ' [יש רכישה]';
                        }
                        displayText.textContent = `${customer.firstName} ${customer.lastName} (${customer.numId || '-'})${statusText}`;
                        onCustomerSelected(result.data);
                    }
                } catch (error) {
                    console.error('Error loading customer:', error);
                    displayText.textContent = '-- בחר נפטר/ת --';
                }
            } else {
                displayText.textContent = '-- בחר נפטר/ת --';
            }
        }

        // פתיחה/סגירה של dropdown
        function toggleCustomerDropdown() {
            const dropdown = document.getElementById('customerDropdown');
            const display = document.querySelector('.custom-select-display');
            const searchInput = document.getElementById('customerSearch');

            if (dropdown.style.display === 'none') {
                dropdown.style.display = 'flex';
                display.classList.add('open');
                searchInput.focus();
            } else {
                closeCustomerDropdown();
            }
        }

        function closeCustomerDropdown() {
            const dropdown = document.getElementById('customerDropdown');
            const display = document.querySelector('.custom-select-display');
            dropdown.style.display = 'none';
            display.classList.remove('open');
        }

        // Debounce לחיפוש
        let customerSearchTimeout = null;

        function filterCustomerOptions() {
            const searchInput = document.getElementById('customerSearch');
            const searchValue = searchInput.value.trim();

            // Debounce - המתן 300ms לפני חיפוש
            clearTimeout(customerSearchTimeout);
            customerSearchTimeout = setTimeout(() => {
                searchCustomers(searchValue);
            }, 300);
        }

        // חיפוש לקוחות מהשרת
        async function searchCustomers(searchValue) {
            const optionsContainer = document.getElementById('customerOptions');

            if (searchValue.length < 2) {
                optionsContainer.innerHTML = '<div class="custom-option-hint">הקלד לפחות 2 תווים לחיפוש...</div>';
                return;
            }

            optionsContainer.innerHTML = '<div class="custom-option-loading"><i class="fas fa-spinner"></i> מחפש...</div>';

            try {
                const params = new URLSearchParams({
                    action: 'search_customers_for_burial',
                    search: searchValue,
                    limit: 50
                });

                // במצב עריכה - הוסף את הלקוח הנוכחי
                if (burialClientId) {
                    params.append('currentClient', burialClientId);
                }

                const response = await fetch(`/dashboard/dashboards/cemeteries/api/customers-api.php?${params}`);
                const result = await response.json();

                if (result.success && result.data) {
                    if (result.data.length === 0) {
                        optionsContainer.innerHTML = '<div class="custom-option-hint">לא נמצאו לקוחות תואמים</div>';
                        return;
                    }

                    optionsContainer.innerHTML = '';
                    const currentValue = document.getElementById('clientId').value;

                    result.data.forEach(customer => {
                        const div = document.createElement('div');
                        div.className = 'custom-select-option' + (customer.unicId === currentValue ? ' selected' : '');
                        div.dataset.value = customer.unicId;

                        const status = parseInt(customer.statusCustomer) || 1;
                        let statusHtml = '';
                        if (status === 2) {
                            statusHtml = '<span class="option-status status-buyer">יש רכישה</span>';
                        }

                        div.innerHTML = `
                            <div class="option-name">${statusHtml}${customer.firstName} ${customer.lastName}</div>
                            <div class="option-details">ת.ז: ${customer.numId || '-'} | טל: ${customer.phoneMobile || customer.phone || '-'}</div>
                        `;

                        div.onclick = () => selectCustomer(customer);
                        optionsContainer.appendChild(div);
                    });
                }
            } catch (error) {
                console.error('Error searching customers:', error);
                optionsContainer.innerHTML = '<div class="custom-option-hint">שגיאה בחיפוש</div>';
            }
        }

        // בחירת לקוח
        function selectCustomer(customer) {
            const clientIdInput = document.getElementById('clientId');
            const displayText = document.getElementById('customerDisplayText');

            clientIdInput.value = customer.unicId;

            const status = parseInt(customer.statusCustomer) || 1;
            let statusText = '';
            if (status === 2) {
                statusText = ' [יש רכישה]';
            }
            displayText.textContent = `${customer.firstName} ${customer.lastName} (${customer.numId || '-'})${statusText}`;

            closeCustomerDropdown();
            onCustomerSelected(customer);
        }

        // פעולות לאחר בחירת לקוח - בדיקה אם יש רכישה וטעינת מיקום
        async function onCustomerSelected(customerData) {
            console.log('Customer selected:', customerData?.unicId);

            if (!customerData?.unicId) return;

            // בדוק אם ללקוח יש רכישה
            try {
                const response = await fetch(`/dashboard/dashboards/cemeteries/api/purchases-api.php?action=list&clientId=${customerData.unicId}`);
                const result = await response.json();

                if (result.success && result.data && result.data.length > 0) {
                    // יש רכישה - טען את מיקום הקבר
                    const purchase = result.data[0]; // הרכישה הראשונה

                    if (purchase.graveId) {
                        console.log('Customer has purchase with grave:', purchase.graveId);
                        // שמור את ה-purchaseId
                        document.getElementById('purchaseId').value = purchase.unicId;
                        // טען את ההיררכיה של הקבר - ללא עדכון לקוח (כי הלקוח כבר נבחר)
                        await loadGraveHierarchy(purchase.graveId, false);
                    }
                } else {
                    // אין רכישה ללקוח - אפס את כל ההיררכיה
                    console.log('Customer has no purchase - clearing hierarchy');
                    document.getElementById('purchaseId').value = '';

                    // אפס את כל שדות ההיררכיה (חוץ מבית עלמין שנשאר עם האופציות)
                    const hierarchySelects = ['blockSelect', 'plotSelect', 'rowSelect', 'areaGraveSelect', 'graveSelect'];
                    hierarchySelects.forEach(id => {
                        const select = document.getElementById(id);
                        select.innerHTML = '<option value="">-- בחר --</option>';
                        select.disabled = true;
                    });

                    // אפס גם את בחירת בית העלמין (אבל השאר את האופציות)
                    document.getElementById('cemeterySelect').value = '';
                }
            } catch (error) {
                console.error('Error checking customer purchase:', error);
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
                    // אוטו-בחירה: אם יש רק בית עלמין אחד - בחר אותו אוטומטית
                    else if (result.data.length === 1) {
                        select.value = result.data[0].unicId;
                        await filterHierarchy('cemetery');
                    }
                }
            } catch (error) {
                console.error('Error loading cemeteries:', error);
            }
        }

        // טעינת היררכיה לפי קבר - טעינה מקבילית מהירה
        // updateCustomer: האם לעדכן את הלקוח מהרכישה (false כשבאים מבחירת לקוח)
        async function loadGraveHierarchy(graveId, updateCustomer = true) {
            console.log('loadGraveHierarchy started for:', graveId, 'updateCustomer:', updateCustomer);

            // אם באים מבחירת לקוח - הגן על כל הטעינה
            if (!updateCustomer) {
                isLoadingHierarchyFromCustomer = true;
            }

            // השבתת כל השדות והצגת סימן טעינה
            const hierarchySelects = ['cemeterySelect', 'blockSelect', 'plotSelect', 'rowSelect', 'areaGraveSelect', 'graveSelect'];
            hierarchySelects.forEach(id => {
                const select = document.getElementById(id);
                select.disabled = true;
                // לא מנקים את cemeterySelect כי האופציות שלו כבר טעונות
                if (id !== 'cemeterySelect') {
                    select.innerHTML = '<option value="">טוען...</option>';
                }
            });

            try {
                // שימוש ב-getDetails כדי לקבל את כל ההיררכיה (cemeteryId, blockId, plotId, lineId)
                const response = await fetch(`/dashboard/dashboards/cemeteries/api/graves-api.php?action=getDetails&id=${graveId}`);
                const result = await response.json();

                if (result.success && result.data) {
                    const grave = result.data;
                    console.log('Grave data with hierarchy:', grave);

                    if (!grave.cemeteryId) {
                        console.error('Grave data missing cemeteryId:', grave);
                        return;
                    }

                    // בחר את בית העלמין
                    document.getElementById('cemeterySelect').value = grave.cemeteryId;

                    // טעינה מקבילית של כל הרמות
                    const [blocksRes, plotsRes, rowsRes, areaGravesRes, gravesRes] = await Promise.all([
                        fetch(`/dashboard/dashboards/cemeteries/api/blocks-api.php?action=list&cemeteryId=${grave.cemeteryId}`).then(r => r.json()),
                        fetch(`/dashboard/dashboards/cemeteries/api/plots-api.php?action=list&blockId=${grave.blockId}`).then(r => r.json()),
                        fetch(`/dashboard/dashboards/cemeteries/api/rows-api.php?action=list&plotId=${grave.plotId}`).then(r => r.json()),
                        fetch(`/dashboard/dashboards/cemeteries/api/areaGraves-api.php?action=list&lineId=${grave.lineId}`).then(r => r.json()),
                        fetch(`/dashboard/dashboards/cemeteries/api/graves-api.php?action=list&areaGraveId=${grave.areaGraveId}`).then(r => r.json())
                    ]);

                    // מילוי כל הסלקטים
                    const selects = [
                        { el: 'blockSelect', data: blocksRes, nameField: 'blockNameHe', value: grave.blockId },
                        { el: 'plotSelect', data: plotsRes, nameField: 'plotNameHe', value: grave.plotId },
                        { el: 'rowSelect', data: rowsRes, nameField: 'lineNameHe', value: grave.lineId },
                        { el: 'areaGraveSelect', data: areaGravesRes, nameField: 'areaGraveNameHe', value: grave.areaGraveId },
                        { el: 'graveSelect', data: gravesRes, nameField: 'graveNameHe', value: grave.unicId }
                    ];

                    selects.forEach(({ el, data, nameField, value }) => {
                        const select = document.getElementById(el);
                        if (data.success && data.data) {
                            select.innerHTML = '<option value="">-- בחר --</option>';
                            data.data.forEach(item => {
                                const option = document.createElement('option');
                                option.value = item.unicId;
                                option.textContent = item[nameField] || item.name || '-';
                                select.appendChild(option);
                            });
                            select.value = value;
                            select.disabled = false;
                        }
                    });

                    console.log('All hierarchy loaded in parallel');

                    // בדוק רכישה אם צריך
                    if (!isEditMode) {
                        await onGraveSelected(grave.unicId, updateCustomer);
                    }
                } else {
                    console.error('Failed to load grave details:', result);
                }
            } catch (error) {
                console.error('Error loading grave hierarchy:', error);
            } finally {
                // הפעלת כל השדות מחדש
                hierarchySelects.forEach(id => {
                    document.getElementById(id).disabled = false;
                });
                // שחרר את הדגל בסיום
                if (!updateCustomer) {
                    isLoadingHierarchyFromCustomer = false;
                }
            }
        }

        // בחירת קבר - בדיקה אם יש רכישה ומילוי אוטומטי של לקוח
        // updateCustomer: האם לעדכן את הלקוח מהרכישה (true ברירת מחדל, false כשבאים מבחירת לקוח)
        async function onGraveSelected(graveId, updateCustomer = true) {
            if (!graveId) return;

            // אם הדגל פעיל - מנע עדכון לקוח (אנחנו בזמן טעינת היררכיה מבחירת לקוח)
            if (isLoadingHierarchyFromCustomer) {
                updateCustomer = false;
            }

            console.log('onGraveSelected:', graveId, 'updateCustomer:', updateCustomer, 'isLoadingHierarchyFromCustomer:', isLoadingHierarchyFromCustomer);

            // לא מנקים את הלקוח מראש - רק מחליפים אותו אם לקבר יש רכישה קיימת

            try {
                // בדוק אם יש רכישה לקבר זה
                const response = await fetch(`/dashboard/dashboards/cemeteries/api/purchases-api.php?action=list&graveId=${graveId}`);
                const result = await response.json();

                console.log('Purchase check result:', result);

                if (result.success && result.data && result.data.length > 0) {
                    const purchase = result.data[0];

                    // שמור את ה-purchaseId
                    document.getElementById('purchaseId').value = purchase.unicId;
                    console.log('Set purchaseId:', purchase.unicId);

                    // טען את הלקוח מהרכישה - רק אם updateCustomer=true
                    if (updateCustomer && purchase.clientId) {
                        // הצגת סימן טעינה בשדה הלקוח
                        const customerDisplayText = document.getElementById('customerDisplayText');
                        const customerBtn = document.getElementById('selectCustomerBtn');
                        customerDisplayText.textContent = 'טוען...';
                        if (customerBtn) customerBtn.disabled = true;

                        try {
                            const customerResponse = await fetch(`/dashboard/dashboards/cemeteries/api/customers-api.php?action=get&id=${purchase.clientId}`);
                            const customerResult = await customerResponse.json();

                            if (customerResult.success && customerResult.data) {
                                const customer = customerResult.data;
                                document.getElementById('clientId').value = customer.unicId;

                                const status = parseInt(customer.statusCustomer) || 1;
                                let statusText = '';
                                if (status === 2) statusText = ' [יש רכישה]';
                                customerDisplayText.textContent =
                                    `${customer.firstName} ${customer.lastName} (${customer.numId || '-'})${statusText}`;

                                console.log('Auto-filled customer from purchase:', customer.unicId);
                            } else {
                                customerDisplayText.textContent = '-- בחר נפטר/ת --';
                            }
                        } finally {
                            if (customerBtn) customerBtn.disabled = false;
                        }
                    } else if (!updateCustomer) {
                        console.log('Skipping customer update (came from customer selection)');
                    }
                } else {
                    // אין רכישה - נקה את השדה purchaseId, הלקוח הנוכחי נשאר
                    document.getElementById('purchaseId').value = '';
                    console.log('No purchase found for grave - keeping current customer');
                }
            } catch (error) {
                console.error('Error checking grave purchase:', error);
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

            // כשמשנים את ההיררכיה - הקבר מתאפס, לכן רק הרכישה מתאפסת
            // הלקוח לא מתאפס - הוא יוחלף רק אם נבחר קבר עם רכישה קיימת
            document.getElementById('purchaseId').value = '';
            console.log('Hierarchy changed - cleared purchase (customer kept)');

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
                    // עבור גושים - סנן לפי חלקות עם קברים זמינים
                    if (level === 'cemetery') {
                        await filterBlocksWithAvailableGraves(result.data, nextSelect);
                    }
                    // עבור חלקות - סנן לפי זמינות קברים
                    else if (level === 'block') {
                        await filterPlotsWithAvailableGraves(result.data, nextSelect);
                    }
                    // עבור שורות - סנן רק את אלה עם קברים זמינים
                    else if (level === 'plot') {
                        await filterRowsWithAvailableGraves(result.data, nextSelect);
                    }
                    // עבור אחוזות קבר - סנן רק את אלה עם קברים זמינים
                    else if (level === 'row') {
                        await filterAreaGravesWithAvailableGraves(result.data, nextSelect, selectedValue);
                    } else {
                        // קברים
                        nextSelect.innerHTML = '<option value="">-- בחר קבר --</option>';
                        result.data.forEach(item => {
                            const option = document.createElement('option');
                            option.value = item.unicId;
                            option.textContent = item[config.nameField] || item.name || '-';
                            nextSelect.appendChild(option);
                        });
                        nextSelect.disabled = false;

                        // אוטו-בחירה לקבר יחיד
                        if (result.data.length === 1) {
                            nextSelect.value = result.data[0].unicId;
                            onGraveSelected(result.data[0].unicId);
                        }
                    }
                }
            } catch (error) {
                console.error('Error filtering hierarchy:', error);
            }
        }

        // סינון גושים - הצגת כולם, disabled לאלה ללא קברים זמינים
        async function filterBlocksWithAvailableGraves(blocks, selectElement) {
            selectElement.innerHTML = '<option value="">טוען גושים...</option>';
            selectElement.disabled = true;

            if (blocks.length === 0) {
                selectElement.innerHTML = '<option value="">אין גושים בבית עלמין זה</option>';
                return;
            }

            // קריאות מקבילות לכל הגושים
            const results = await Promise.all(
                blocks.map(async (block) => {
                    try {
                        const response = await fetch(`/dashboard/dashboards/cemeteries/api/graves-api.php?action=countAvailable&type=burial&blockId=${block.unicId}`);
                        const result = await response.json();
                        return {
                            block,
                            availableCount: result.success ? (result.count || 0) : 0
                        };
                    } catch (e) {
                        console.error('Error checking block availability:', e);
                        return { block, availableCount: 0 };
                    }
                })
            );

            selectElement.innerHTML = '<option value="">-- בחר גוש --</option>';
            const availableBlocks = [];

            results.forEach(({ block, availableCount }) => {
                const option = document.createElement('option');
                option.value = block.unicId;

                if (availableCount > 0) {
                    option.textContent = `${block.blockNameHe || '-'} (${availableCount} זמינים)`;
                    availableBlocks.push(block);
                } else {
                    option.textContent = `${block.blockNameHe || '-'} (אין זמינים)`;
                    option.disabled = true;
                    option.style.color = '#999';
                }
                selectElement.appendChild(option);
            });

            selectElement.disabled = false;

            // אוטו-בחירה: אם יש רק גוש אחד עם קברים זמינים
            if (availableBlocks.length === 1) {
                selectElement.value = availableBlocks[0].unicId;
                await filterHierarchy('block');
            }
        }

        // סינון חלקות - הצגת כולן, disabled לאלה ללא קברים זמינים
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
                        const response = await fetch(`/dashboard/dashboards/cemeteries/api/graves-api.php?action=countAvailable&type=burial&plotId=${plot.unicId}`);
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

            selectElement.innerHTML = '<option value="">-- בחר חלקה --</option>';
            const availablePlots = [];

            results.forEach(({ plot, availableCount }) => {
                const option = document.createElement('option');
                option.value = plot.unicId;

                if (availableCount > 0) {
                    option.textContent = `${plot.plotNameHe || '-'} (${availableCount} זמינים)`;
                    availablePlots.push(plot);
                } else {
                    option.textContent = `${plot.plotNameHe || '-'} (אין זמינים)`;
                    option.disabled = true;
                    option.style.color = '#999';
                }
                selectElement.appendChild(option);
            });

            selectElement.disabled = false;

            // אוטו-בחירה: אם יש רק חלקה אחת עם קברים זמינים
            if (availablePlots.length === 1) {
                selectElement.value = availablePlots[0].unicId;
                await filterHierarchy('plot');
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
            const availableRows = [];

            results.forEach(({ row, availableCount }) => {
                if (availableCount > 0) {
                    const option = document.createElement('option');
                    option.value = row.unicId;
                    option.textContent = `${row.lineNameHe || '-'} (${availableCount} זמינים)`;
                    selectElement.appendChild(option);
                    availableRows.push(row);
                }
            });

            selectElement.disabled = availableRows.length === 0;

            if (availableRows.length === 0) {
                selectElement.innerHTML = '<option value="">אין קברים זמינים בחלקה זו</option>';
            }
            // אוטו-בחירה: אם יש רק שורה אחת עם קברים זמינים
            else if (availableRows.length === 1) {
                selectElement.value = availableRows[0].unicId;
                await filterHierarchy('row');
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
            const availableAreaGraves = [];

            results.forEach(({ areaGrave, availableCount }) => {
                if (availableCount > 0) {
                    const option = document.createElement('option');
                    option.value = areaGrave.unicId;
                    option.textContent = `${areaGrave.areaGraveNameHe || '-'} (${availableCount} זמינים)`;
                    selectElement.appendChild(option);
                    availableAreaGraves.push(areaGrave);
                }
            });

            selectElement.disabled = availableAreaGraves.length === 0;

            if (availableAreaGraves.length === 0) {
                selectElement.innerHTML = '<option value="">אין קברים זמינים בשורה זו</option>';
            }
            // אוטו-בחירה: אם יש רק אחוזת קבר אחת עם קברים זמינים
            else if (availableAreaGraves.length === 1) {
                selectElement.value = availableAreaGraves[0].unicId;
                await filterHierarchy('areaGrave');
            }
        }

        // שליחת הטופס
        document.getElementById('burialForm').addEventListener('submit', async function(e) {
            e.preventDefault();

            const clientId = document.getElementById('clientId').value;
            const graveId = this.querySelector('[name="graveId"]').value;
            const dateDeath = this.querySelector('[name="dateDeath"]').value;
            const dateBurial = this.querySelector('[name="dateBurial"]').value;
            const placeDeath = this.querySelector('[name="placeDeath"]').value;
            const timeBurial = this.querySelector('[name="timeBurial"]').value;

            if (!clientId) {
                showAlert('יש לבחור נפטר/ת', 'error');
                return;
            }

            if (!graveId || !dateDeath || !dateBurial || !placeDeath || !timeBurial) {
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
