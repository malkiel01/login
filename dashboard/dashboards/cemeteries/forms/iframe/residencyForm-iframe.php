<?php
/*
 * File: dashboard/dashboards/cemeteries/forms/iframe/residencyForm-iframe.php
 * Version: 1.0.0
 * Updated: 2026-01-20
 * Author: Malkiel
 * Description: טופס חוק תושבות (יצירה/עריכה) - דף עצמאי לטעינה ב-iframe
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: text/html; charset=utf-8');

require_once dirname(dirname(__DIR__)) . '/config.php';

$itemId = $_GET['itemId'] ?? $_GET['id'] ?? null;
$popupId = $_GET['popupId'] ?? null;
$isEditMode = !empty($itemId);

$residency = null;

try {
    $conn = getDBConnection();

    // טען מדינות
    $countriesStmt = $conn->prepare("
        SELECT unicId, countryNameHe
        FROM countries
        WHERE isActive = 1
        ORDER BY countryNameHe
    ");
    $countriesStmt->execute();
    $countries = $countriesStmt->fetchAll(PDO::FETCH_ASSOC);

    // טען את כל הערים
    $citiesStmt = $conn->prepare("
        SELECT unicId, countryId, cityNameHe
        FROM cities
        WHERE isActive = 1
        ORDER BY cityNameHe
    ");
    $citiesStmt->execute();
    $allCities = $citiesStmt->fetchAll(PDO::FETCH_ASSOC);

    // טען הגדרת תושבות אם בעריכה
    if ($isEditMode) {
        $stmt = $conn->prepare("
            SELECT r.*,
                   c.countryNameHe,
                   ct.cityNameHe
            FROM residency_settings r
            LEFT JOIN countries c ON r.countryId = c.unicId
            LEFT JOIN cities ct ON r.cityId = ct.unicId
            WHERE r.unicId = ? AND r.isActive = 1
        ");
        $stmt->execute([$itemId]);
        $residency = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$residency) {
            die('<!DOCTYPE html><html dir="rtl"><head><meta charset="UTF-8"></head><body style="font-family: Arial; padding: 20px; color: #ef4444;">שגיאה: חוק התושבות לא נמצא</body></html>');
        }
    }
} catch (Exception $e) {
    die('<!DOCTYPE html><html dir="rtl"><head><meta charset="UTF-8"></head><body style="font-family: Arial; padding: 20px; color: #ef4444;">שגיאה: ' . htmlspecialchars($e->getMessage()) . '</body></html>');
}

$pageTitle = $isEditMode ? 'עריכת חוק תושבות' : 'הוספת חוק תושבות חדש';

// מיפוי סוגי תושבות
$residencyTypes = [
    '' => '-- בחר סוג תושבות --',
    1 => 'תושב העיר',
    2 => 'תושב חוץ לעיר',
    3 => 'תושב חו״ל'
];

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

$citiesJson = json_encode($allCities);
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

        .info-box {
            background: #f0f9ff;
            border: 1px solid #bae6fd;
            border-radius: 8px;
            padding: 12px;
            margin-bottom: 15px;
            font-size: 13px;
            color: #0369a1;
        }
        .info-box i { margin-left: 8px; }
    </style>
</head>
<body>
    <div class="loading-overlay" id="loadingOverlay">
        <div class="loading-spinner"></div>
    </div>

    <div class="form-container">
        <div id="alertBox" class="alert"></div>

        <form id="residencyForm" novalidate>
            <input type="hidden" name="unicId" value="<?= htmlspecialchars($residency['unicId'] ?? '') ?>">

            <div class="sortable-sections" id="residencyFormSortableSections">
                <!-- סקשן 1: פרטי חוק התושבות -->
                <div class="sortable-section" data-section="details">
                    <div class="section-drag-handle" style="background: linear-gradient(135deg, #dbeafe, #bfdbfe);">
                        <button type="button" class="section-toggle-btn" onclick="toggleSection(this)">
                            <i class="fas fa-chevron-down"></i>
                        </button>
                        <span class="section-title" style="color: #1e40af;">
                            <i class="fas fa-gavel"></i> פרטי חוק התושבות
                        </span>
                    </div>
                    <div class="section-content" style="background: linear-gradient(135deg, #eff6ff, #dbeafe);">
                        <div class="info-box">
                            <i class="fas fa-info-circle"></i>
                            חוקי תושבות מגדירים כיצד לקוחות מסווגים לפי המיקום שלהם. בחר מדינה ו/או עיר כדי להגדיר את סוג התושבות.
                        </div>
                        <div class="form-grid">
                            <div class="form-group span-2">
                                <label>שם החוק <span class="required">*</span></label>
                                <input type="text" name="residencyName" class="form-control" required
                                    placeholder="לדוגמה: תושבי ירושלים - אזור מרכז"
                                    value="<?= htmlspecialchars($residency['residencyName'] ?? '') ?>">
                            </div>
                            <div class="form-group">
                                <label>סוג תושבות <span class="required">*</span></label>
                                <?= renderSelect('residencyType', $residencyTypes, $residency['residencyType'] ?? '', true) ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- סקשן 2: מיקום -->
                <div class="sortable-section" data-section="location">
                    <div class="section-drag-handle" style="background: linear-gradient(135deg, #dcfce7, #bbf7d0);">
                        <button type="button" class="section-toggle-btn" onclick="toggleSection(this)">
                            <i class="fas fa-chevron-down"></i>
                        </button>
                        <span class="section-title" style="color: #166534;">
                            <i class="fas fa-map-marker-alt"></i> מיקום
                        </span>
                    </div>
                    <div class="section-content" style="background: linear-gradient(135deg, #f0fdf4, #dcfce7);">
                        <div class="info-box" style="background: #f0fdf4; border-color: #86efac; color: #166534;">
                            <i class="fas fa-lightbulb"></i>
                            בחר מדינה בלבד להגדרת חוק ברמת מדינה, או גם עיר להגדרה ספציפית יותר.
                        </div>
                        <div class="form-grid">
                            <div class="form-group">
                                <label>מדינה</label>
                                <select name="countryId" id="countryId" class="form-control">
                                    <option value="">-- בחר מדינה (אופציונלי) --</option>
                                    <?php foreach ($countries as $country): ?>
                                        <option value="<?= $country['unicId'] ?>"
                                            <?= ($residency && $residency['countryId'] == $country['unicId']) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($country['countryNameHe']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>עיר</label>
                                <select name="cityId" id="cityId" class="form-control" <?= (!$residency || !$residency['countryId']) ? 'disabled' : '' ?>>
                                    <option value="">-- בחר עיר (אופציונלי) --</option>
                                    <?php if ($residency && $residency['countryId']): ?>
                                        <?php foreach ($allCities as $city): ?>
                                            <?php if ($city['countryId'] == $residency['countryId']): ?>
                                                <option value="<?= $city['unicId'] ?>"
                                                    <?= ($residency['cityId'] == $city['unicId']) ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($city['cityNameHe']) ?>
                                                </option>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- סקשן 3: פרטים נוספים -->
                <div class="sortable-section" data-section="additional">
                    <div class="section-drag-handle" style="background: linear-gradient(135deg, #fef3c7, #fde68a);">
                        <button type="button" class="section-toggle-btn" onclick="toggleSection(this)">
                            <i class="fas fa-chevron-down"></i>
                        </button>
                        <span class="section-title" style="color: #92400e;">
                            <i class="fas fa-info-circle"></i> פרטים נוספים
                        </span>
                    </div>
                    <div class="section-content" style="background: linear-gradient(135deg, #fffbeb, #fef3c7);">
                        <div class="form-grid">
                            <div class="form-group span-2">
                                <label>תיאור</label>
                                <textarea name="description" class="form-control" rows="3"
                                    placeholder="תיאור נוסף (אופציונלי)"><?= htmlspecialchars($residency['description'] ?? '') ?></textarea>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Hidden fields for names -->
            <input type="hidden" id="countryNameHe" name="countryNameHe" value="<?= htmlspecialchars($residency['countryNameHe'] ?? '') ?>">
            <input type="hidden" id="cityNameHe" name="cityNameHe" value="<?= htmlspecialchars($residency['cityNameHe'] ?? '') ?>">

            <div class="form-actions">
                <button type="button" class="btn btn-secondary" onclick="closeForm()">
                    <i class="fas fa-times"></i> ביטול
                </button>
                <button type="submit" class="btn btn-primary" id="submitBtn">
                    <i class="fas fa-save"></i> <?= $isEditMode ? 'עדכן חוק' : 'צור חוק' ?>
                </button>
            </div>
        </form>
    </div>

    <script>
        const isEditMode = <?= $isEditMode ? 'true' : 'false' ?>;
        const residencyId = '<?= addslashes($itemId ?? '') ?>';
        const allCities = <?= $citiesJson ?>;

        document.addEventListener('DOMContentLoaded', function() {
            if (typeof PopupAPI !== 'undefined') {
                PopupAPI.setTitle('<?= addslashes($pageTitle) ?>');
            }

            // האזנה לשינוי מדינה
            document.getElementById('countryId').addEventListener('change', function() {
                filterCities(this.value);
                updateCountryName();
            });

            // האזנה לשינוי עיר
            document.getElementById('cityId').addEventListener('change', updateCityName);
        });

        function toggleSection(btn) {
            btn.closest('.sortable-section').classList.toggle('collapsed');
        }

        // סינון ערים לפי מדינה
        function filterCities(countryId) {
            const citySelect = document.getElementById('cityId');
            citySelect.innerHTML = '<option value="">-- בחר עיר (אופציונלי) --</option>';

            if (!countryId) {
                citySelect.disabled = true;
                return;
            }

            const filteredCities = allCities.filter(city => city.countryId === countryId);

            if (filteredCities.length === 0) {
                citySelect.innerHTML = '<option value="">אין ערים במדינה זו</option>';
            } else {
                filteredCities.forEach(city => {
                    const option = document.createElement('option');
                    option.value = city.unicId;
                    option.textContent = city.cityNameHe;
                    citySelect.appendChild(option);
                });
            }

            citySelect.disabled = false;
        }

        // עדכון שם המדינה בשדה הנסתר
        function updateCountryName() {
            const select = document.getElementById('countryId');
            const nameField = document.getElementById('countryNameHe');
            const selectedOption = select.options[select.selectedIndex];
            nameField.value = (selectedOption.value && !selectedOption.text.includes('--')) ? selectedOption.text : '';
        }

        // עדכון שם העיר בשדה הנסתר
        function updateCityName() {
            const select = document.getElementById('cityId');
            const nameField = document.getElementById('cityNameHe');
            const selectedOption = select.options[select.selectedIndex];
            nameField.value = (selectedOption.value && !selectedOption.text.includes('--') && !selectedOption.text.includes('אין ערים')) ? selectedOption.text : '';
        }

        // שליחת הטופס
        document.getElementById('residencyForm').addEventListener('submit', async function(e) {
            e.preventDefault();

            const residencyName = this.querySelector('[name="residencyName"]').value.trim();
            const residencyType = this.querySelector('[name="residencyType"]').value;

            if (!residencyName) {
                showAlert('שם החוק הוא שדה חובה', 'error');
                return;
            }

            if (!residencyType) {
                showAlert('סוג תושבות הוא שדה חובה', 'error');
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
                const response = await fetch('/dashboard/dashboards/cemeteries/api/residency-api.php?action=save', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(data)
                });

                const result = await response.json();

                if (result.success) {
                    showAlert(result.message || 'הפעולה בוצעה בהצלחה', 'success');

                    if (window.parent) {
                        if (window.parent.EntityManager) {
                            window.parent.EntityManager.refresh('residency');
                        }
                        if (window.parent.refreshTable) {
                            window.parent.refreshTable();
                        }
                    }

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

        function showLoading(show) {
            document.getElementById('loadingOverlay').classList.toggle('show', show);
        }

        function closeForm() {
            if (typeof PopupAPI !== 'undefined') {
                PopupAPI.close();
            }
        }
    </script>
    <!-- סקריפט לגרירת סקשנים -->
    <script src="/dashboard/dashboards/cemeteries/forms/sortable-sections.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            if (typeof SortableSections !== 'undefined') {
                SortableSections.init('residencyFormSortableSections', 'residencyForm');
            }
        });
    </script>
</body>
</html>
