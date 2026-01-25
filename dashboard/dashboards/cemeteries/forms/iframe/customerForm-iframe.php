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
$residentOptions = [1 => 'תושב העיר', 2 => 'תושב חוץ לעיר', 3 => 'תושב חו״ל'];
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
    <link rel="stylesheet" href="/dashboard/dashboards/cemeteries/css/user-preferences.css">
    <link rel="stylesheet" href="/dashboard/dashboards/cemeteries/popup/popup-forms.css?v=<?= time() ?>">
    <link rel="stylesheet" href="/dashboard/dashboards/cemeteries/popup/popup-sections.css?v=<?= time() ?>">
    <script src="/dashboard/dashboards/cemeteries/popup/popup-api.js?v=<?= time() ?>"></script>
</head>
<body>
    <div class="loading-overlay" id="loadingOverlay">
        <div class="loading-spinner"></div>
    </div>

    <div class="form-container">
        <div id="alertBox" class="alert"></div>

        <form id="customerForm" novalidate>
            <input type="hidden" name="unicId" value="<?= htmlspecialchars($customer['unicId'] ?? '') ?>">

            <div class="sortable-sections" id="customerFormSortableSections">
                <!-- סקשן 1: פרטים אישיים -->
                <div class="sortable-section section-blue" data-section="personal">
                    <div class="section-drag-handle">
                        <button type="button" class="section-toggle-btn" onclick="toggleSection(this)">
                            <i class="fas fa-chevron-down"></i>
                        </button>
                        <span class="section-title">
                            <i class="fas fa-user"></i> פרטים אישיים
                        </span>
                    </div>
                    <div class="section-content">
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
                                <label>בן/בת זוג</label>
                                <div class="smart-select-container" id="spouseSelectContainer">
                                    <input type="hidden" name="spouse" id="spouseId" value="<?= htmlspecialchars($customer['spouse'] ?? '') ?>">
                                    <div class="smart-select-display" id="spouseDisplay" onclick="toggleSpouseDropdown()">
                                        <span id="spouseDisplayText">טוען...</span>
                                        <i class="fas fa-chevron-down"></i>
                                    </div>
                                    <div class="smart-select-dropdown" id="spouseDropdown">
                                        <div class="smart-select-search">
                                            <input type="text" id="spouseSearch" placeholder="חיפוש לפי שם..." oninput="filterSpouseOptions()">
                                        </div>
                                        <div class="smart-select-option" data-value="" onclick="selectSpouse('', 'ללא בן/בת זוג')">
                                            <span style="color: #94a3b8;">ללא בן/בת זוג</span>
                                        </div>
                                        <div id="spouseOptions"></div>
                                    </div>
                                </div>
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
                <div class="sortable-section section-green" data-section="address">
                    <div class="section-drag-handle">
                        <button type="button" class="section-toggle-btn" onclick="toggleSection(this)">
                            <i class="fas fa-chevron-down"></i>
                        </button>
                        <span class="section-title">
                            <i class="fas fa-map-marker-alt"></i> כתובת
                        </span>
                    </div>
                    <div class="section-content">
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
                <div class="sortable-section section-orange" data-section="contact">
                    <div class="section-drag-handle">
                        <button type="button" class="section-toggle-btn" onclick="toggleSection(this)">
                            <i class="fas fa-chevron-down"></i>
                        </button>
                        <span class="section-title">
                            <i class="fas fa-phone"></i> פרטי התקשרות
                        </span>
                    </div>
                    <div class="section-content">
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
                <div class="sortable-section section-purple" data-section="additional">
                    <div class="section-drag-handle">
                        <button type="button" class="section-toggle-btn" onclick="toggleSection(this)">
                            <i class="fas fa-chevron-down"></i>
                        </button>
                        <span class="section-title">
                            <i class="fas fa-info-circle"></i> פרטים נוספים
                        </span>
                    </div>
                    <div class="section-content">
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
                            <div class="form-group span-2">
                                <label>הערות</label>
                                <textarea name="comment" class="form-control" rows="3"><?= htmlspecialchars($customer['comment'] ?? '') ?></textarea>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- סקשן 5: מסמכים -->
                <?php if ($isEditMode): ?>
                <div class="sortable-section section-pink" data-section="documents">
                    <div class="section-drag-handle">
                        <button type="button" class="section-toggle-btn" onclick="toggleSection(this)">
                            <i class="fas fa-chevron-down"></i>
                        </button>
                        <span class="section-title">
                            <i class="fas fa-folder-open"></i> מסמכים
                        </span>
                    </div>
                    <div class="section-content">
                        <div id="customerExplorer" style="min-height: 300px;">
                            <div style="text-align: center; color: #94a3b8; padding: 40px;">
                                <i class="fas fa-spinner fa-spin" style="font-size: 32px; margin-bottom: 10px; display: block;"></i>
                                <span>טוען סייר קבצים...</span>
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
        // ========== VERSION 2.0 - <?= date('Y-m-d H:i:s') ?> ==========
        console.log('%c🔥 CUSTOMER FORM VERSION 2.0 LOADED 🔥', 'background: #ff0000; color: white; font-size: 20px; padding: 10px;');

        const isEditMode = <?= $isEditMode ? 'true' : 'false' ?>;
        const customerId = '<?= addslashes($itemId ?? '') ?>';
        const customerCountryId = '<?= addslashes($customer['countryId'] ?? '') ?>';
        const customerCityId = '<?= addslashes($customer['cityId'] ?? '') ?>';
        const currentSpouseId = '<?= addslashes($customer['spouse'] ?? '') ?>';

        // משתנים גלובליים לבחירת בן/בת זוג
        let allAvailableSpouses = [];
        let selectedSpouseId = currentSpouseId;

        document.addEventListener('DOMContentLoaded', function() {
            // עדכון כותרת הפופאפ
            if (typeof PopupAPI !== 'undefined') {
                PopupAPI.setTitle('<?= addslashes($pageTitle) ?>');
            }

            // טעינת מדינות
            loadCountries();

            // טעינת אפשרויות בן/בת זוג
            loadSpouseOptions();

            // האזנה לשינוי מדינה
            document.getElementById('countryId').addEventListener('change', function() {
                loadCities(this.value);
                calculateResidency(); // חישוב תושבות בשינוי מדינה
            });

            // האזנה לשינוי עיר
            document.getElementById('cityId').addEventListener('change', function() {
                calculateResidency(); // חישוב תושבות בשינוי עיר
            });

            // האזנה לשינוי מצב משפחתי
            document.getElementById('maritalStatus').addEventListener('change', function() {
                handleMaritalStatusChange(this.value);
            });

            // האזנה לשינוי סוג זיהוי
            document.getElementById('typeId').addEventListener('change', function() {
                calculateResidency(); // חישוב תושבות בשינוי סוג זיהוי
            });

            // חישוב תושבות ראשוני
            if (isEditMode) {
                calculateResidency();
                // טעינת סייר מסמכים
                initFileExplorer();
            }
        });

        // אתחול סייר מסמכים
        function initFileExplorer() {
            if (!customerId) return;

            // טעינת explorer.js דינמית
            const script = document.createElement('script');
            script.src = '/dashboard/dashboards/cemeteries/explorer/explorer.js?v=' + Date.now();
            script.onload = function() {
                if (typeof FileExplorer !== 'undefined') {
                    window.customerExplorer = new FileExplorer('customerExplorer', customerId, {});
                    // הגדרת window.explorer לשימוש בכפתורי הסייר הפנימיים
                    window.explorer = window.customerExplorer;
                } else {
                    console.error('FileExplorer class not found');
                    document.getElementById('customerExplorer').innerHTML = `
                        <div style="text-align: center; color: #ef4444; padding: 40px;">
                            <i class="fas fa-exclamation-triangle" style="font-size: 32px; margin-bottom: 10px; display: block;"></i>
                            <span>שגיאה בטעינת סייר הקבצים</span>
                        </div>
                    `;
                }
            };
            script.onerror = function() {
                console.error('Failed to load explorer.js');
                document.getElementById('customerExplorer').innerHTML = `
                    <div style="text-align: center; color: #ef4444; padding: 40px;">
                        <i class="fas fa-exclamation-triangle" style="font-size: 32px; margin-bottom: 10px; display: block;"></i>
                        <span>שגיאה בטעינת סייר הקבצים</span>
                    </div>
                `;
            };
            document.head.appendChild(script);
        }

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
            console.log('=== calculateResidency START ===');

            try {
                const typeId = document.getElementById('typeId')?.value || '1';
                const countryId = document.getElementById('countryId')?.value || '';
                const cityId = document.getElementById('cityId')?.value || '';
                const residentSelect = document.getElementById('resident');

                console.log('Inputs:', { typeId, countryId, cityId });

                if (!residentSelect) {
                    console.error('CRITICAL: Resident select not found!');
                    return;
                }

                // שמור את המצב הנוכחי של השדה
                const parentElement = residentSelect.parentElement;
                console.log('Parent element:', parentElement);
                console.log('Select display:', window.getComputedStyle(residentSelect).display);
                console.log('Select visibility:', window.getComputedStyle(residentSelect).visibility);

                // בניית URL עם פרמטרים
                let url = `/dashboard/dashboards/cemeteries/api/calculate-residency.php?typeId=${typeId}`;
                if (countryId) url += `&countryId=${countryId}`;
                if (cityId) url += `&cityId=${cityId}`;

                console.log('Fetching URL:', url);

                const response = await fetch(url);
                const text = await response.text();
                console.log('Raw response:', text);

                let result;
                try {
                    result = JSON.parse(text);
                } catch (parseError) {
                    console.error('JSON parse error:', parseError);
                    return;
                }

                console.log('Parsed result:', result);

                if (result.success && result.residency) {
                    const newValue = String(result.residency);
                    console.log('Will set value to:', newValue);
                    console.log('Available options:', Array.from(residentSelect.options).map(o => o.value));

                    // בדיקה שהשדה עדיין קיים ונראה
                    console.log('Before set - display:', window.getComputedStyle(residentSelect).display);

                    // הגדרת הערך
                    residentSelect.value = newValue;

                    // בדיקה אחרי הגדרת הערך
                    console.log('After set - value:', residentSelect.value);
                    console.log('After set - display:', window.getComputedStyle(residentSelect).display);
                    console.log('After set - visibility:', window.getComputedStyle(residentSelect).visibility);
                    console.log('After set - offsetHeight:', residentSelect.offsetHeight);

                    console.log('תושבות:', result.label, '| סיבה:', result.reason);
                }

            } catch (error) {
                console.error('calculateResidency ERROR:', error);
                console.error('Stack:', error.stack);
            }

            console.log('=== calculateResidency END ===');
        }

        // ========== בחירת בן/בת זוג ==========

        /**
         * טיפול בשינוי מצב משפחתי - מקושר לשדה בן/בת זוג
         * כללים:
         * - ריק או רווק (1): לא ניתן לקשר בן זוג
         * - נשוי (2): חובה לקשר בן זוג
         * - אלמן (3) או גרוש (4): רשות לקשר בן זוג
         */
        function handleMaritalStatusChange(status) {
            const spouseContainer = document.getElementById('spouseSelectContainer');
            const spouseDisplay = document.getElementById('spouseDisplay');
            const spouseLabel = spouseContainer.closest('.form-group').querySelector('label');

            if (status === '' || status === '1') {
                // ריק או רווק - לא ניתן לקשר בן זוג
                spouseContainer.style.opacity = '0.5';
                spouseContainer.style.pointerEvents = 'none';
                spouseDisplay.style.cursor = 'not-allowed';
                spouseLabel.innerHTML = 'בן/בת זוג <span style="color: #94a3b8; font-size: 11px;">(לא זמין)</span>';

                // נקה את הבחירה אם יש
                if (selectedSpouseId) {
                    selectSpouse('', 'ללא בן/בת זוג');
                }
            } else if (status === '2') {
                // נשוי - חובה לקשר בן זוג
                spouseContainer.style.opacity = '1';
                spouseContainer.style.pointerEvents = 'auto';
                spouseDisplay.style.cursor = 'pointer';
                spouseLabel.innerHTML = 'בן/בת זוג <span class="required">*</span>';
            } else if (status === '3' || status === '4') {
                // אלמן או גרוש - רשות לקשר בן זוג
                spouseContainer.style.opacity = '1';
                spouseContainer.style.pointerEvents = 'auto';
                spouseDisplay.style.cursor = 'pointer';
                spouseLabel.innerHTML = 'בן/בת זוג <span style="color: #94a3b8; font-size: 11px;">(אופציונלי)</span>';
            }
        }

        // משתנה לניהול timeout של חיפוש (debounce)
        let spouseSearchTimeout = null;

        // אתחול בחירת בן/בת זוג
        async function loadSpouseOptions() {
            try {
                // הצגה מיידית של בן הזוג הנוכחי (אם יש)
                if (currentSpouseId) {
                    document.getElementById('spouseDisplayText').textContent = 'טוען...';
                    const spouseResponse = await fetch(`/dashboard/dashboards/cemeteries/api/customers-api.php?action=get&id=${currentSpouseId}`);
                    const spouseResult = await spouseResponse.json();
                    if (spouseResult.success && spouseResult.data) {
                        const displayName = `${spouseResult.data.firstName || ''} ${spouseResult.data.lastName || ''}`.trim();
                        document.getElementById('spouseDisplayText').textContent = displayName || currentSpouseId;
                    } else {
                        document.getElementById('spouseDisplayText').textContent = currentSpouseId;
                    }
                } else {
                    document.getElementById('spouseDisplayText').textContent = 'ללא בן/בת זוג';
                }

                // החל את כללי מצב המשפחתי מיד
                const currentMaritalStatus = document.getElementById('maritalStatus').value;
                handleMaritalStatusChange(currentMaritalStatus);

                // הצג הודעה ראשונית ב-dropdown
                document.getElementById('spouseOptions').innerHTML = `
                    <div style="padding: 15px; text-align: center; color: #64748b;">
                        הקלד לחיפוש בן/בת זוג...
                    </div>
                `;

            } catch (error) {
                console.error('Error loading spouse options:', error);
                document.getElementById('spouseDisplayText').textContent = 'שגיאה בטעינה';
            }
        }

        // חיפוש בני/בנות זוג בצד השרת
        async function searchSpouses(searchTerm) {
            const container = document.getElementById('spouseOptions');

            // אם אין טקסט חיפוש - הצג הודעה
            if (!searchTerm || searchTerm.length < 2) {
                container.innerHTML = `
                    <div style="padding: 15px; text-align: center; color: #64748b;">
                        ${searchTerm.length === 0 ? 'הקלד לחיפוש בן/בת זוג...' : 'הקלד לפחות 2 תווים...'}
                    </div>
                `;
                return;
            }

            // הצג אנימציית טעינה
            container.innerHTML = `
                <div style="padding: 15px; text-align: center; color: #64748b;">
                    <i class="fas fa-spinner fa-spin"></i> מחפש...
                </div>
            `;

            try {
                const params = new URLSearchParams({
                    action: 'search_spouses',
                    search: searchTerm,
                    exclude: customerId || '',
                    currentSpouse: currentSpouseId || '',
                    limit: 50
                });

                const response = await fetch(`/dashboard/dashboards/cemeteries/api/customers-api.php?${params}`);
                const result = await response.json();

                if (result.success && result.data) {
                    if (result.data.length === 0) {
                        container.innerHTML = `
                            <div style="padding: 15px; text-align: center; color: #64748b;">
                                לא נמצאו תוצאות
                            </div>
                        `;
                    } else {
                        renderSpouseOptions(result.data);
                    }
                } else {
                    container.innerHTML = `
                        <div style="padding: 15px; text-align: center; color: #dc2626;">
                            שגיאה בחיפוש
                        </div>
                    `;
                }
            } catch (error) {
                console.error('Error searching spouses:', error);
                container.innerHTML = `
                    <div style="padding: 15px; text-align: center; color: #dc2626;">
                        שגיאה בחיפוש
                    </div>
                `;
            }
        }

        // רינדור אפשרויות בן/בת זוג
        function renderSpouseOptions(spouses) {
            const container = document.getElementById('spouseOptions');
            container.innerHTML = '';

            spouses.forEach(spouse => {
                const displayName = `${spouse.firstName || ''} ${spouse.lastName || ''}`.trim();
                const numId = spouse.numId || '';

                const div = document.createElement('div');
                div.className = 'smart-select-option' + (spouse.unicId === selectedSpouseId ? ' selected' : '');
                div.dataset.value = spouse.unicId;
                div.dataset.name = displayName.toLowerCase();
                div.onclick = () => selectSpouse(spouse.unicId, displayName);

                div.innerHTML = `
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <span>${displayName}</span>
                        <span style="color: #94a3b8; font-size: 12px;">${numId}</span>
                    </div>
                `;

                container.appendChild(div);
            });
        }

        // פתיחה/סגירה של dropdown
        function toggleSpouseDropdown() {
            const dropdown = document.getElementById('spouseDropdown');
            dropdown.classList.toggle('open');

            if (dropdown.classList.contains('open')) {
                document.getElementById('spouseSearch').focus();
            }
        }

        // סגירת dropdown בלחיצה מחוץ
        document.addEventListener('click', function(e) {
            const container = document.getElementById('spouseSelectContainer');
            if (container && !container.contains(e.target)) {
                document.getElementById('spouseDropdown').classList.remove('open');
            }
        });

        // סינון אפשרויות בחיפוש - עם debounce לחיפוש בצד השרת
        function filterSpouseOptions() {
            const searchTerm = document.getElementById('spouseSearch').value.trim();

            // בטל חיפוש קודם אם יש
            if (spouseSearchTimeout) {
                clearTimeout(spouseSearchTimeout);
            }

            // debounce - המתן 300ms לפני חיפוש
            spouseSearchTimeout = setTimeout(() => {
                searchSpouses(searchTerm);
            }, 300);
        }

        // בחירת בן/בת זוג
        function selectSpouse(unicId, displayName) {
            selectedSpouseId = unicId;
            document.getElementById('spouseId').value = unicId;
            document.getElementById('spouseDisplayText').textContent = displayName;
            document.getElementById('spouseDropdown').classList.remove('open');
            document.getElementById('spouseSearch').value = '';

            // הערה: מצב משפחתי כעת שולט על בחירת בן/בת הזוג, לא להיפך
            // לכן אין שינוי אוטומטי של מצב משפחתי כאן

            // סמן את האפשרות הנבחרת
            document.querySelectorAll('#spouseOptions .smart-select-option, #spouseDropdown > .smart-select-option').forEach(opt => {
                opt.classList.toggle('selected', opt.dataset.value === unicId);
            });
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

            // ולידציה של מצב משפחתי ובן/בת זוג
            const maritalStatus = document.getElementById('maritalStatus').value;
            const spouseId = document.getElementById('spouseId').value;

            if (maritalStatus === '2' && !spouseId) {
                // נשוי אבל בלי בן זוג
                showAlert('כאשר מצב משפחתי הוא "נשוי/אה", יש לבחור בן/בת זוג', 'error');
                return;
            }

            if ((maritalStatus === '' || maritalStatus === '1') && spouseId) {
                // רווק/ריק עם בן זוג - לא אמור לקרות בגלל ה-UI, אבל בדיקה נוספת
                showAlert('לא ניתן לקשר בן/בת זוג למצב משפחתי ריק או רווק', 'error');
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
    </script>
    <!-- סקריפט לגרירת סקשנים -->
    <script src="/dashboard/dashboards/cemeteries/forms/sortable-sections.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            if (typeof SortableSections !== 'undefined') {
                SortableSections.init('customerFormSortableSections', 'customerForm');
            }
        });
    </script>
</body>
</html>
