<?php
/*
 * File: dashboard/dashboards/cemeteries/forms/iframe/customer-form-iframe.php
 * Version: 1.0.0
 * Updated: 2026-01-19
 * Author: Malkiel
 * Description: טופס לקוח (יצירה/עריכה) - דף עצמאי לטעינה ב-iframe
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: text/html; charset=utf-8');

require_once dirname(dirname(__DIR__)) . '/config.php';

$itemId = $_GET['itemId'] ?? $_GET['id'] ?? null;
$popupId = $_GET['popupId'] ?? null;
$isEditMode = !empty($itemId);

$customer = null;

if ($isEditMode) {
    try {
        $conn = getDBConnection();
        $stmt = $conn->prepare("SELECT * FROM customers WHERE unicId = ? AND isActive = 1");
        $stmt->execute([$itemId]);
        $customer = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$customer) {
            die('<!DOCTYPE html><html dir="rtl"><head><meta charset="UTF-8"></head><body style="font-family: Arial; padding: 20px; color: #ef4444;">שגיאה: הלקוח לא נמצא</body></html>');
        }
    } catch (Exception $e) {
        die('<!DOCTYPE html><html dir="rtl"><head><meta charset="UTF-8"></head><body style="font-family: Arial; padding: 20px; color: #ef4444;">שגיאה: ' . htmlspecialchars($e->getMessage()) . '</body></html>');
    }
}

$pageTitle = $isEditMode ? 'עריכת לקוח - ' . htmlspecialchars($customer['firstName'] . ' ' . $customer['lastName']) : 'הוספת לקוח חדש';

// מיפויים
$typeIdOptions = [1 => 'ת.ז.', 2 => 'דרכון', 3 => 'אלמוני', 4 => 'תינוק'];
$genderOptions = ['' => '-- בחר --', 1 => 'זכר', 2 => 'נקבה'];
$maritalOptions = ['' => '-- בחר --', 1 => 'רווק/ה', 2 => 'נשוי/אה', 3 => 'אלמן/ה', 4 => 'גרוש/ה'];
$statusOptions = [1 => 'פעיל', 2 => 'רוכש', 3 => 'נפטר'];
$residentOptions = [1 => 'ירושלים והסביבה', 2 => 'תושב חוץ', 3 => 'תושב חו״ל'];
$associationOptions = [1 => 'ישראל', 2 => 'כהן', 3 => 'לוי'];

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
        .form-control:disabled {
            background: #f1f5f9;
            cursor: not-allowed;
        }
        .form-control.error { border-color: #ef4444; }

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
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(255,255,255,0.8);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 1000;
        }
        .loading-overlay.show { display: flex; }
        .loading-spinner {
            width: 40px;
            height: 40px;
            border: 3px solid #e2e8f0;
            border-top-color: #3b82f6;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        @keyframes spin { to { transform: rotate(360deg); } }

        /* Smart Select Styles */
        .smart-select-container { position: relative; }
        .smart-select-display {
            padding: 10px 12px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            background: white;
            cursor: pointer;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .smart-select-display:hover { border-color: #94a3b8; }
        .smart-select-dropdown {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            z-index: 100;
            display: none;
            max-height: 250px;
            overflow-y: auto;
        }
        .smart-select-dropdown.open { display: block; }
        .smart-select-search {
            padding: 10px;
            border-bottom: 1px solid #e2e8f0;
        }
        .smart-select-search input {
            width: 100%;
            padding: 8px;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
        }
        .smart-select-option {
            padding: 10px 12px;
            cursor: pointer;
        }
        .smart-select-option:hover { background: #f1f5f9; }
        .smart-select-option.selected { background: #dbeafe; color: #1d4ed8; }
    </style>
</head>
<body>
    <div class="loading-overlay" id="loadingOverlay">
        <div class="loading-spinner"></div>
    </div>

    <div class="form-container">
        <div id="alertBox" class="alert"></div>

        <form id="customerForm" novalidate>
            <input type="hidden" name="unicId" value="<?= htmlspecialchars($customer['unicId'] ?? '') ?>">

            <div class="sortable-sections">
                <!-- סקשן 1: פרטים אישיים -->
                <div class="sortable-section" data-section="personal">
                    <div class="section-drag-handle" style="background: linear-gradient(135deg, #dbeafe, #bfdbfe);">
                        <button type="button" class="section-toggle-btn" onclick="toggleSection(this)">
                            <i class="fas fa-chevron-down"></i>
                        </button>
                        <span class="section-title" style="color: #1e40af;">
                            <i class="fas fa-user"></i> פרטים אישיים
                        </span>
                    </div>
                    <div class="section-content" style="background: linear-gradient(135deg, #eff6ff, #dbeafe);">
                        <div class="form-grid">
                            <div class="form-group">
                                <label>סוג זיהוי</label>
                                <?= renderSelect('typeId', $typeIdOptions, $customer['typeId'] ?? 1) ?>
                            </div>
                            <div class="form-group">
                                <label>מספר זיהוי <span class="required">*</span></label>
                                <input type="text" name="numId" class="form-control" required
                                    value="<?= htmlspecialchars($customer['numId'] ?? '') ?>">
                            </div>
                            <div class="form-group">
                                <label>שם פרטי <span class="required">*</span></label>
                                <input type="text" name="firstName" class="form-control" required
                                    value="<?= htmlspecialchars($customer['firstName'] ?? '') ?>">
                            </div>
                            <div class="form-group">
                                <label>שם משפחה <span class="required">*</span></label>
                                <input type="text" name="lastName" class="form-control" required
                                    value="<?= htmlspecialchars($customer['lastName'] ?? '') ?>">
                            </div>
                            <div class="form-group">
                                <label>כינוי</label>
                                <input type="text" name="nom" class="form-control"
                                    value="<?= htmlspecialchars($customer['nom'] ?? '') ?>">
                            </div>
                            <div class="form-group">
                                <label>מגדר</label>
                                <?= renderSelect('gender', $genderOptions, $customer['gender'] ?? '') ?>
                            </div>
                            <div class="form-group">
                                <label>תאריך לידה</label>
                                <input type="date" name="dateBirth" class="form-control"
                                    value="<?= htmlspecialchars($customer['dateBirth'] ?? '') ?>">
                            </div>
                            <div class="form-group">
                                <label>מצב משפחתי</label>
                                <?= renderSelect('maritalStatus', $maritalOptions, $customer['maritalStatus'] ?? '') ?>
                            </div>
                            <div class="form-group">
                                <label>שם האב</label>
                                <input type="text" name="nameFather" class="form-control"
                                    value="<?= htmlspecialchars($customer['nameFather'] ?? '') ?>">
                            </div>
                            <div class="form-group">
                                <label>שם האם</label>
                                <input type="text" name="nameMother" class="form-control"
                                    value="<?= htmlspecialchars($customer['nameMother'] ?? '') ?>">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- סקשן 2: כתובת -->
                <div class="sortable-section" data-section="address">
                    <div class="section-drag-handle" style="background: linear-gradient(135deg, #dcfce7, #bbf7d0);">
                        <button type="button" class="section-toggle-btn" onclick="toggleSection(this)">
                            <i class="fas fa-chevron-down"></i>
                        </button>
                        <span class="section-title" style="color: #166534;">
                            <i class="fas fa-map-marker-alt"></i> כתובת
                        </span>
                    </div>
                    <div class="section-content" style="background: linear-gradient(135deg, #f0fdf4, #dcfce7);">
                        <div class="form-grid">
                            <div class="form-group">
                                <label>מדינה</label>
                                <select name="countryId" id="countryId" class="form-control">
                                    <option value="">טוען מדינות...</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>עיר</label>
                                <select name="cityId" id="cityId" class="form-control" disabled>
                                    <option value="">בחר קודם מדינה...</option>
                                </select>
                            </div>
                            <div class="form-group span-2">
                                <label>כתובת מלאה</label>
                                <input type="text" name="address" class="form-control"
                                    placeholder="רחוב, מספר בית"
                                    value="<?= htmlspecialchars($customer['address'] ?? '') ?>">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- סקשן 3: פרטי התקשרות -->
                <div class="sortable-section" data-section="contact">
                    <div class="section-drag-handle" style="background: linear-gradient(135deg, #fef3c7, #fde68a);">
                        <button type="button" class="section-toggle-btn" onclick="toggleSection(this)">
                            <i class="fas fa-chevron-down"></i>
                        </button>
                        <span class="section-title" style="color: #92400e;">
                            <i class="fas fa-phone"></i> פרטי התקשרות
                        </span>
                    </div>
                    <div class="section-content" style="background: linear-gradient(135deg, #fffbeb, #fef3c7);">
                        <div class="form-grid">
                            <div class="form-group">
                                <label>טלפון</label>
                                <input type="tel" name="phone" class="form-control"
                                    value="<?= htmlspecialchars($customer['phone'] ?? '') ?>">
                            </div>
                            <div class="form-group">
                                <label>טלפון נייד</label>
                                <input type="tel" name="phoneMobile" class="form-control"
                                    value="<?= htmlspecialchars($customer['phoneMobile'] ?? '') ?>">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- סקשן 4: פרטים נוספים -->
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
                                <label>סטטוס לקוח</label>
                                <?= renderSelect('statusCustomer', $statusOptions, $customer['statusCustomer'] ?? 1) ?>
                            </div>
                            <div class="form-group">
                                <label>תושבות (מחושב אוטומטית)</label>
                                <?= renderSelect('resident', $residentOptions, $customer['resident'] ?? 3, false, true) ?>
                            </div>
                            <div class="form-group">
                                <label>שיוך</label>
                                <?= renderSelect('association', $associationOptions, $customer['association'] ?? 1) ?>
                            </div>
                            <div class="form-group">
                                <label>בן/בת זוג</label>
                                <input type="text" name="spouse" class="form-control"
                                    value="<?= htmlspecialchars($customer['spouse'] ?? '') ?>">
                            </div>
                            <div class="form-group span-2">
                                <label>הערות</label>
                                <textarea name="comment" class="form-control" rows="3"><?= htmlspecialchars($customer['comment'] ?? '') ?></textarea>
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
                        <div id="documentsContainer">
                            <div class="documents-toolbar" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                                <span style="color: #64748b; font-size: 13px;">
                                    <i class="fas fa-info-circle"></i> ניהול מסמכים של הלקוח
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
                    <i class="fas fa-save"></i> <?= $isEditMode ? 'עדכן לקוח' : 'צור לקוח' ?>
                </button>
            </div>
        </form>
    </div>

    <script>
        const isEditMode = <?= $isEditMode ? 'true' : 'false' ?>;
        const customerId = '<?= addslashes($itemId ?? '') ?>';
        const customerCountryId = '<?= addslashes($customer['countryId'] ?? '') ?>';
        const customerCityId = '<?= addslashes($customer['cityId'] ?? '') ?>';

        document.addEventListener('DOMContentLoaded', function() {
            // עדכון כותרת הפופאפ
            if (typeof PopupAPI !== 'undefined') {
                PopupAPI.setTitle('<?= addslashes($pageTitle) ?>');
            }

            // טעינת מדינות
            loadCountries();

            // האזנה לשינוי מדינה
            document.getElementById('countryId').addEventListener('change', function() {
                loadCities(this.value);
                calculateResidency(); // חישוב תושבות בשינוי מדינה
            });

            // האזנה לשינוי עיר
            document.getElementById('cityId').addEventListener('change', function() {
                calculateResidency(); // חישוב תושבות בשינוי עיר
            });

            // האזנה לשינוי סוג זיהוי
            document.getElementById('typeId').addEventListener('change', function() {
                calculateResidency(); // חישוב תושבות בשינוי סוג זיהוי
            });

            // חישוב תושבות ראשוני
            if (isEditMode) {
                calculateResidency();
            }
        });

        // Toggle section
        function toggleSection(btn) {
            btn.closest('.sortable-section').classList.toggle('collapsed');
        }

        // טעינת מדינות
        async function loadCountries() {
            try {
                const response = await fetch('/dashboard/dashboards/cemeteries/api/countries-api.php?action=list');
                const result = await response.json();

                if (result.success && result.data) {
                    const select = document.getElementById('countryId');
                    select.innerHTML = '<option value="">-- בחר מדינה --</option>';

                    result.data.forEach(country => {
                        const option = document.createElement('option');
                        option.value = country.unicId;
                        option.textContent = country.countryNameHe || country.name;
                        if (country.unicId === customerCountryId) {
                            option.selected = true;
                        }
                        select.appendChild(option);
                    });

                    // אם יש מדינה נבחרת, טען ערים
                    if (customerCountryId) {
                        loadCities(customerCountryId);
                    }
                }
            } catch (error) {
                console.error('Error loading countries:', error);
            }
        }

        // טעינת ערים
        async function loadCities(countryId) {
            const citySelect = document.getElementById('cityId');

            if (!countryId) {
                citySelect.innerHTML = '<option value="">בחר קודם מדינה...</option>';
                citySelect.disabled = true;
                return;
            }

            citySelect.innerHTML = '<option value="">טוען ערים...</option>';
            citySelect.disabled = true;

            try {
                const response = await fetch(`/dashboard/dashboards/cemeteries/api/cities-api.php?action=list&countryId=${countryId}`);
                const result = await response.json();

                citySelect.innerHTML = '<option value="">-- בחר עיר --</option>';

                if (result.success && result.data) {
                    result.data.forEach(city => {
                        const option = document.createElement('option');
                        option.value = city.unicId;
                        option.textContent = city.cityNameHe || city.name;
                        if (city.unicId === customerCityId) {
                            option.selected = true;
                        }
                        citySelect.appendChild(option);
                    });
                }

                citySelect.disabled = false;

                // חישוב תושבות אחרי טעינת ערים
                calculateResidency();
            } catch (error) {
                console.error('Error loading cities:', error);
                citySelect.innerHTML = '<option value="">שגיאה בטעינה</option>';
            }
        }

        // חישוב תושבות בזמן אמת
        async function calculateResidency() {
            const typeId = document.getElementById('typeId').value;
            const countryId = document.getElementById('countryId').value;
            const cityId = document.getElementById('cityId').value;
            const residentSelect = document.getElementById('resident');

            // בניית URL עם פרמטרים
            let url = `/dashboard/dashboards/cemeteries/api/calculate-residency.php?typeId=${typeId}`;
            if (countryId) url += `&countryId=${countryId}`;
            if (cityId) url += `&cityId=${cityId}`;

            try {
                const response = await fetch(url);
                const result = await response.json();

                if (result.success) {
                    residentSelect.value = result.residency;

                    // אינדיקציה ויזואלית
                    const colors = {
                        1: '#10b981', // ירושלים - ירוק
                        2: '#f59e0b', // חוץ - כתום
                        3: '#ef4444'  // חו"ל - אדום
                    };
                    residentSelect.style.borderColor = colors[result.residency] || '#e2e8f0';
                    residentSelect.style.backgroundColor = (colors[result.residency] || '#e2e8f0') + '20';

                    console.log('תושבות מחושבת:', result.label, result.reason || '');
                }
            } catch (error) {
                console.error('Error calculating residency:', error);
            }
        }

        // שליחת הטופס
        document.getElementById('customerForm').addEventListener('submit', async function(e) {
            e.preventDefault();

            // ולידציה
            const firstName = this.querySelector('[name="firstName"]').value.trim();
            const lastName = this.querySelector('[name="lastName"]').value.trim();

            if (!firstName || !lastName) {
                showAlert('שם פרטי ושם משפחה הם שדות חובה', 'error');
                return;
            }

            // איסוף נתונים
            const formData = new FormData(this);
            const data = {};
            formData.forEach((value, key) => {
                if (value !== '') {
                    data[key] = value;
                }
            });

            // הצגת טעינה
            showLoading(true);
            document.getElementById('submitBtn').disabled = true;

            try {
                const action = isEditMode ? 'update' : 'create';
                const url = `/dashboard/dashboards/cemeteries/api/customers-api.php?action=${action}${isEditMode ? '&id=' + customerId : ''}`;

                const response = await fetch(url, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(data)
                });

                const result = await response.json();

                if (result.success) {
                    showAlert(result.message || 'הפעולה בוצעה בהצלחה', 'success');

                    // רענון הטבלה בחלון ההורה
                    if (window.parent) {
                        if (window.parent.EntityManager) {
                            window.parent.EntityManager.refresh('customer');
                        }
                        if (window.parent.refreshTable) {
                            window.parent.refreshTable();
                        }
                    }

                    // סגירת הפופאפ אחרי 1.5 שניות
                    setTimeout(() => {
                        closeForm();
                    }, 1500);
                } else {
                    throw new Error(result.error || result.message || 'שגיאה בשמירה');
                }
            } catch (error) {
                showAlert(error.message, 'error');
            } finally {
                showLoading(false);
                document.getElementById('submitBtn').disabled = false;
            }
        });

        // הצגת הודעה
        function showAlert(message, type) {
            const alertBox = document.getElementById('alertBox');
            alertBox.textContent = message;
            alertBox.className = `alert alert-${type} show`;

            if (type === 'success') {
                setTimeout(() => {
                    alertBox.classList.remove('show');
                }, 3000);
            }
        }

        // הצגת/הסתרת טעינה
        function showLoading(show) {
            document.getElementById('loadingOverlay').classList.toggle('show', show);
        }

        // סגירת הטופס
        function closeForm() {
            if (typeof PopupAPI !== 'undefined') {
                PopupAPI.close();
            } else if (window.parent && window.parent.PopupManager) {
                // נסה לסגור את הפופאפ הנוכחי
                const popupId = new URLSearchParams(window.location.search).get('popupId');
                if (popupId) {
                    window.parent.PopupManager.close(popupId);
                }
            }
        }

        // העלאת מסמך (placeholder)
        function uploadDocument() {
            alert('פונקציית העלאת מסמכים תתווסף בהמשך');
        }
    </script>
</body>
</html>
