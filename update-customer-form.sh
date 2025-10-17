#!/bin/bash

# ========================================
# Customer Form Update Script
# מעדכן את טופס הלקוח עם כל השיפורים
# ========================================

echo ""
echo "========================================" 
echo "   📝 עדכון טופס הלקוח - Smart Select + Validation"
echo "========================================" 
echo ""

# צבעים
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m'

# פונקציות עזר
print_success() { echo -e "${GREEN}✅ $1${NC}"; }
print_info() { echo -e "${YELLOW}ℹ️ $1${NC}"; }
print_error() { echo -e "${RED}❌ $1${NC}"; }

# בדיקת נתיב
FORMS_PATH="dashboard/dashboards/cemeteries/forms"

if [[ ! -d "$FORMS_PATH" ]]; then
    print_error "תיקיית forms לא נמצאה"
    exit 1
fi

# שלב 1: גיבוי הטופס הקיים
print_info "יוצר גיבוי של הטופס הקיים..."
BACKUP_DIR="backup_customer_$(date '+%Y%m%d_%H%M%S')"
mkdir -p "$BACKUP_DIR"

if [[ -f "$FORMS_PATH/customer-form.php" ]]; then
    cp "$FORMS_PATH/customer-form.php" "$BACKUP_DIR/customer-form.php.bak"
    print_success "גיבוי נוצר ב-$BACKUP_DIR"
fi

# שלב 2: עדכון FormBuilder כדי לתמוך ב-field groups
print_info "מוסיף תמיכה ב-Field Groups ל-FormBuilder..."

# בדוק אם כבר יש תמיכה
if ! grep -q "addFieldGroup" "$FORMS_PATH/FormBuilder.php"; then
    cat >> "$FORMS_PATH/FormBuilder.php" << 'FIELDGROUP'

    /**
     * Add field group
     */
    public function addFieldGroup($id, $title, $config = []) {
        $html = '<fieldset class="field-group ' . ($config['collapsible'] ?? '') . '" id="' 
. $id . '-group">';
        $html .= '<legend>' . $title . '</legend>';
        $html .= '<div class="form-fields ' . ($config['layout'] ?? '') . '">';
        
        if (isset($config['fields'])) {
            foreach ($config['fields'] as $field) {
                // Handle span
                $wrapperClass = 'form-field';
                if (isset($field['span'])) {
                    $wrapperClass .= ' span-' . $field['span'];
                }
                
                $html .= '<div class="' . $wrapperClass . '">';
                
                // Check if it's smart_select
                if ($field['type'] === 'smart_select') {
                    $smartSelect = new SmartSelect(
                        $field['name'],
                        $field['label'],
                        $field['options'] ?? [],
                        [
                            'searchable' => $field['searchable'] ?? false,
                            'placeholder' => $field['placeholder'] ?? 'בחר...',
                            'required' => $field['required'] ?? false,
                            'display_mode' => $field['display_mode'] ?? 'simple',
                            'depends_on' => $field['depends_on'] ?? null,
                            'ajax_url' => $field['ajax_url'] ?? null
                        ]
                    );
                    $html .= $smartSelect->render();
                } else {
                    // Regular field
                    $this->addField(
                        $field['name'],
                        $field['label'],
                        $field['type'],
                        $field
                    );
                }
                
                $html .= '</div>';
            }
        }
        
        $html .= '</div></fieldset>';
        $this->customHTML[] = $html;
        return $this;
    }
FIELDGROUP
    print_success "תמיכה ב-Field Groups נוספה"
else
    print_info "תמיכה ב-Field Groups כבר קיימת"
fi

# שלב 3: יצירת טופס הלקוח המעודכן
print_info "יוצר את טופס הלקוח המעודכן..."

cat > "$FORMS_PATH/customer-form.php" << 'CUSTOMERFORM'
<?php
/**
 * Enhanced Customer Form with Smart Select
 * טופס לקוח משופר עם Smart Select, Validation ו-Field Groups
 * 
 * @version 2.0.0
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: text/html; charset=utf-8');

// טעינת קבצים נדרשים
require_once __DIR__ . '/FormBuilder.php';
require_once __DIR__ . '/SmartSelect.php';
require_once __DIR__ . '/ValidationUtils.php';
require_once __DIR__ . '/FormUtils.php';
require_once dirname(__DIR__) . '/config.php';

// קבלת פרמטרים
$itemId = $_GET['itemId'] ?? $_GET['id'] ?? null;
$parentId = $_GET['parentId'] ?? $_GET['parent_id'] ?? null;
$formType = 'customer';

try {
    $conn = getDBConnection();
    
    // === טעינת מדינות עם מידע מתקדם ===
    $countriesStmt = $conn->prepare("
        SELECT 
            c.unicId, 
            c.countryNameHe, 
            c.countryNameEn,
            COUNT(DISTINCT ct.unicId) as cities_count
        FROM countries c
        LEFT JOIN cities ct ON ct.countryId = c.unicId AND ct.isActive = 1
        WHERE c.isActive = 1
        GROUP BY c.unicId
        ORDER BY c.countryNameHe
    ");
    $countriesStmt->execute();
    
    $countries = [];
    $countriesAdvanced = [];
    
    while ($row = $countriesStmt->fetch(PDO::FETCH_ASSOC)) {
        // רשימה רגילה לתאימות
        $countries[$row['unicId']] = $row['countryNameHe'];
        
        // רשימה מתקדמת ל-SmartSelect
        $countriesAdvanced[$row['unicId']] = [
            'text' => $row['countryNameHe'],
            'subtitle' => $row['countryNameEn'],
            'badge' => $row['cities_count'] > 0 ? $row['cities_count'] . ' ערים' : ''
        ];
    }
    
    // === טעינת לקוח בעריכה ===
    $customer = null;
    if ($itemId) {
        $stmt = $conn->prepare("
            SELECT c.*, 
                   cn.countryNameHe, cn.countryNameEn,
                   ct.cityNameHe, ct.cityNameEn
            FROM customers c
            LEFT JOIN countries cn ON c.countryId = cn.unicId
            LEFT JOIN cities ct ON c.cityId = ct.unicId
            WHERE c.id = ? AND c.isActive = 1
        ");
        $stmt->execute([$itemId]);
        $customer = $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
} catch (Exception $e) {
    FormUtils::handleError($e);
}

// יצירת FormBuilder
$formBuilder = new FormBuilder('customer', $itemId, $parentId);
?>

<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $itemId ? 'עריכת לקוח' : 'הוספת לקוח חדש' 
?></title>
    
    <!-- CSS Files -->
    <link rel="stylesheet" href="/dashboard/dashboards/cemeteries/css/forms.css">
    <link rel="stylesheet" href="/dashboard/dashboards/cemeteries/css/smart-select.css">
    
    <style>
        /* Field Groups Styling */
        .field-group {
            margin-bottom: 2rem;
            padding: 1.5rem;
            background: #f8f9fa;
            border-radius: 8px;
            border: 1px solid #e2e8f0;
        }
        
        .field-group legend {
            font-weight: 600;
            font-size: 1.1rem;
            color: #374151;
            padding: 0 0.5rem;
            background: white;
            border-radius: 4px;
        }
        
        .form-fields.grid-2 {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1rem;
        }
        
        .form-fields.grid-3 {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 1rem;
        }
        
        .form-field.span-2 {
            grid-column: span 2;
        }
        
        .form-field.span-3 {
            grid-column: span 3;
        }
        
        .field-group.collapsible legend {
            cursor: pointer;
            user-select: none;
        }
        
        .field-group.collapsible legend::before {
            content: "▼ ";
            display: inline-block;
            transition: transform 0.3s;
        }
        
        .field-group.collapsed legend::before {
            transform: rotate(-90deg);
        }
        
        .field-group.collapsed .form-fields {
            display: none;
        }
        
        .badge-residency {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 4px;
            font-weight: 500;
            font-size: 0.875rem;
        }
        
        .badge-residency.green {
            background: #d1fae5;
            color: #065f46;
        }
        
        .badge-residency.blue {
            background: #dbeafe;
            color: #1e3a8a;
        }
        
        .badge-residency.orange {
            background: #fed7aa;
            color: #7c2d12;
        }
        
        @media (max-width: 768px) {
            .form-fields.grid-2,
            .form-fields.grid-3 {
                grid-template-columns: 1fr;
            }
            
            .form-field.span-2,
            .form-field.span-3 {
                grid-column: span 1;
            }
        }
    </style>
</head>
<body>

<div class="form-container">
    <form id="customerForm" method="POST" 
action="/dashboard/dashboards/cemeteries/handlers/save-handler.php">
        <input type="hidden" name="formType" value="customer">
        <?php if ($itemId): ?>
            <input type="hidden" name="itemId" value="<?= $itemId ?>">
            <input type="hidden" name="unicId" value="<?= $customer['unicId'] ?? '' ?>">
        <?php endif; ?>
        
        <!-- פרטי זיהוי -->
        <fieldset class="field-group" id="identification-group">
            <legend>פרטי זיהוי</legend>
            <div class="form-fields grid-2">
                <div class="form-field">
                    <label for="typeId">סוג זיהוי</label>
                    <select id="typeId" name="typeId" class="form-control" 
onchange="handleIdTypeChange(this.value)">
                        <option value="1" <?= ($customer['typeId'] ?? 1) == 1 ? 'selected' 
: '' ?>>ת.ז.</option>
                        <option value="2" <?= ($customer['typeId'] ?? 1) == 2 ? 'selected' 
: '' ?>>דרכון</option>
                        <option value="3" <?= ($customer['typeId'] ?? 1) == 3 ? 'selected' 
: '' ?>>אלמוני</option>
                        <option value="4" <?= ($customer['typeId'] ?? 1) == 4 ? 'selected' 
: '' ?>>תינוק</option>
                    </select>
                </div>
                <div class="form-field">
                    <label for="numId">מספר זיהוי <span 
class="text-danger">*</span></label>
                    <input type="text" id="numId" name="numId" class="form-control" 
                           pattern="[0-9]{9}" maxlength="9" 
                           placeholder="9 ספרות"
                           value="<?= htmlspecialchars($customer['numId'] ?? '') ?>" 
required>
                </div>
            </div>
        </fieldset>
        
        <!-- פרטים אישיים -->
        <fieldset class="field-group" id="personal-group">
            <legend>פרטים אישיים</legend>
            <div class="form-fields grid-2">
                <div class="form-field">
                    <label for="firstName">שם פרטי <span 
class="text-danger">*</span></label>
                    <input type="text" id="firstName" name="firstName" class="form-control" 
                           value="<?= htmlspecialchars($customer['firstName'] ?? '') ?>" 
required>
                </div>
                <div class="form-field">
                    <label for="lastName">שם משפחה <span 
class="text-danger">*</span></label>
                    <input type="text" id="lastName" name="lastName" class="form-control" 
                           value="<?= htmlspecialchars($customer['lastName'] ?? '') ?>" 
required>
                </div>
                <div class="form-field">
                    <label for="nom">כינוי</label>
                    <input type="text" id="nom" name="nom" class="form-control" 
                           value="<?= htmlspecialchars($customer['nom'] ?? '') ?>">
                </div>
                <div class="form-field">
                    <label for="gender">מגדר</label>
                    <select id="gender" name="gender" class="form-control">
                        <option value="">-- בחר --</option>
                        <option value="1" <?= ($customer['gender'] ?? '') == 1 ? 'selected' 
: '' ?>>זכר</option>
                        <option value="2" <?= ($customer['gender'] ?? '') == 2 ? 'selected' 
: '' ?>>נקבה</option>
                    </select>
                </div>
                <div class="form-field">
                    <label for="dateBirth">תאריך לידה</label>
                    <input type="date" id="dateBirth" name="dateBirth" class="form-control" 
                           max="<?= date('Y-m-d') ?>"
                           value="<?= $customer['dateBirth'] ?? '' ?>">
                </div>
                <div class="form-field">
                    <label for="maritalStatus">מצב משפחתי</label>
                    <select id="maritalStatus" name="maritalStatus" class="form-control">
                        <option value="">-- בחר --</option>
                        <option value="1" <?= ($customer['maritalStatus'] ?? '') == 1 ? 
'selected' : '' ?>>רווק/ה</option>
                        <option value="2" <?= ($customer['maritalStatus'] ?? '') == 2 ? 
'selected' : '' ?>>נשוי/אה</option>
                        <option value="3" <?= ($customer['maritalStatus'] ?? '') == 3 ? 
'selected' : '' ?>>אלמן/ה</option>
                        <option value="4" <?= ($customer['maritalStatus'] ?? '') == 4 ? 
'selected' : '' ?>>גרוש/ה</option>
                    </select>
                </div>
            </div>
        </fieldset>
        
        <!-- פרטי משפחה -->
        <fieldset class="field-group collapsible" id="family-group">
            <legend>פרטי משפחה</legend>
            <div class="form-fields grid-2">
                <div class="form-field">
                    <label for="nameFather">שם אב</label>
                    <input type="text" id="nameFather" name="nameFather" 
class="form-control" 
                           value="<?= htmlspecialchars($customer['nameFather'] ?? '') ?>">
                </div>
                <div class="form-field">
                    <label for="nameMother">שם אם</label>
                    <input type="text" id="nameMother" name="nameMother" 
class="form-control" 
                           value="<?= htmlspecialchars($customer['nameMother'] ?? '') ?>">
                </div>
                <div class="form-field span-2">
                    <label for="spouse">בן/בת זוג</label>
                    <input type="text" id="spouse" name="spouse" class="form-control" 
                           value="<?= htmlspecialchars($customer['spouse'] ?? '') ?>">
                </div>
            </div>
        </fieldset>
        
        <!-- כתובת עם Smart Select -->
        <fieldset class="field-group" id="address-group">
            <legend>כתובת</legend>
            <div class="form-fields grid-2">
                <div class="form-field">
                    <?php
                    // Smart Select למדינה
                    $countrySelect = SmartSelect::create('countryId', 'מדינה', 
$countriesAdvanced, [
                        'searchable' => true,
                        'placeholder' => 'הקלד לחיפוש מדינה...',
                        'display_mode' => 'advanced'
                    ]);
                    echo $countrySelect->render();
                    ?>
                </div>
                <div class="form-field">
                    <?php
                    // Smart Select לעיר
                    $citySelect = SmartSelect::create('cityId', 'עיר', [], [
                        'searchable' => true,
                        'placeholder' => 'בחר קודם מדינה...',
                        'display_mode' => 'advanced',
                        'depends_on' => 'countryId',
                        'ajax_url' => '/dashboard/dashboards/cemeteries/api/get-cities.php'
                    ]);
                    echo $citySelect->render();
                    ?>
                </div>
                <div class="form-field span-2">
                    <label for="address">כתובת מלאה</label>
                    <input type="text" id="address" name="address" class="form-control" 
                           placeholder="רחוב, מספר בית, דירה"
                           value="<?= htmlspecialchars($customer['address'] ?? '') ?>">
                </div>
            </div>
        </fieldset>
        
        <!-- פרטי התקשרות -->
        <fieldset class="field-group" id="contact-group">
            <legend>פרטי התקשרות</legend>
            <div class="form-fields grid-2">
                <div class="form-field">
                    <label for="phone">טלפון</label>
                    <input type="tel" id="phone" name="phone" class="form-control" 
                           pattern="[0-9-]+"
                           value="<?= htmlspecialchars($customer['phone'] ?? '') ?>">
                </div>
                <div class="form-field">
                    <label for="phoneMobile">טלפון נייד</label>
                    <input type="tel" id="phoneMobile" name="phoneMobile" 
class="form-control" 
                           pattern="05[0-9]-?[0-9]{7}"
                           placeholder="050-1234567"
                           value="<?= htmlspecialchars($customer['phoneMobile'] ?? '') ?>">
                </div>
            </div>
        </fieldset>
        
        <!-- סטטוסים -->
        <fieldset class="field-group" id="status-group">
            <legend>סטטוסים</legend>
            <div class="form-fields grid-3">
                <div class="form-field">
                    <label for="statusCustomer">סטטוס לקוח</label>
                    <select id="statusCustomer" name="statusCustomer" class="form-control">
                        <option value="1" <?= ($customer['statusCustomer'] ?? 1) == 1 ? 
'selected' : '' ?>>פעיל</option>
                        <option value="2" <?= ($customer['statusCustomer'] ?? 1) == 2 ? 
'selected' : '' ?>>רוכש</option>
                        <option value="3" <?= ($customer['statusCustomer'] ?? 1) == 3 ? 
'selected' : '' ?>>נפטר</option>
                    </select>
                </div>
                <div class="form-field">
                    <label for="resident">תושבות</label>
                    <div id="residency-display">
                        <?php
                        $residencyValue = $customer['resident'] ?? 3;
                        $residencyTypes = [
                            1 => ['text' => 'ירושלים והסביבה', 
'class' => 'green'],
                            2 => ['text' => 'תושב חוץ', 'class' => 'blue'],
                            3 => ['text' => 'תושב חו"ל', 'class' => 'orange']
                        ];
                        $residency = $residencyTypes[$residencyValue];
                        ?>
                        <span class="badge-residency <?= $residency['class'] ?>">
                            <?= $residency['text'] ?>
                        </span>
                        <input type="hidden" id="resident" name="resident" value="<?= 
$residencyValue ?>">
                    </div>
                    <small class="text-muted">מחושב אוטומטית 
לפי העיר</small>
                </div>
                <div class="form-field">
                    <label for="association">שיוך</label>
                    <select id="association" name="association" class="form-control">
                        <option value="1" <?= ($customer['association'] ?? 1) == 1 ? 
'selected' : '' ?>>ישראל</option>
                        <option value="2" <?= ($customer['association'] ?? 1) == 2 ? 
'selected' : '' ?>>כהן</option>
                        <option value="3" <?= ($customer['association'] ?? 1) == 3 ? 
'selected' : '' ?>>לוי</option>
                    </select>
                </div>
            </div>
        </fieldset>
        
        <!-- הערות -->
        <fieldset class="field-group" id="notes-group">
            <legend>הערות</legend>
            <div class="form-fields">
                <div class="form-field">
                    <textarea id="comment" name="comment" class="form-control" rows="3" 
                              placeholder="הערות נוספות..."><?= 
htmlspecialchars($customer['comment'] ?? '') ?></textarea>
                </div>
            </div>
        </fieldset>
        
        <!-- כפתורי פעולה -->
        <div class="form-actions">
            <button type="submit" class="btn btn-primary">
                <?= $itemId ? 'עדכן לקוח' : 'שמור לקוח חדש' 
?>
            </button>
            <button type="button" class="btn btn-secondary" onclick="window.close()">
                ביטול
            </button>
        </div>
    </form>
</div>

<!-- JavaScript Files -->
<script src="/dashboard/dashboards/cemeteries/js/smart-select.js"></script>
<script>
// === Customer Form Handler ===
window.CustomerFormHandler = {
    
    // טיפול בשינוי סוג זיהוי
    handleIdTypeChange: function(typeId) {
        const numIdField = document.getElementById('numId');
        if (!numIdField) return;
        
        switch(parseInt(typeId)) {
            case 1: // ת.ז.
                numIdField.pattern = '[0-9]{9}';
                numIdField.placeholder = '9 ספרות';
                numIdField.maxLength = 9;
                break;
            case 2: // דרכון
                numIdField.pattern = '[A-Z0-9]+';
                numIdField.placeholder = 'מספר דרכון';
                numIdField.maxLength = 20;
                break;
            case 3: // אלמוני
            case 4: // תינוק
                numIdField.removeAttribute('required');
                numIdField.value = '000000000';
                break;
        }
    },
    
    // טיפול בשינוי מדינה
    handleCountryChange: function(countryId) {
        console.log('Country changed:', countryId);
        
        // עדכן את שדה העיר
        if (countryId && window.SmartSelectManager) {
            fetch('/dashboard/dashboards/cemeteries/api/get-cities.php?countryId=' + 
countryId)
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.cities) {
                        window.SmartSelectManager.updateOptions('cityId', data.cities);
                    }
                })
                .catch(error => console.error('Error loading cities:', error));
        }
    },
    
    // טיפול בשינוי עיר
    handleCityChange: function(cityId) {
        console.log('City changed:', cityId);
        
        // חשב תושבות
        if (cityId) {
            fetch('/dashboard/dashboards/cemeteries/api/calculate-residency.php?cityId=' + 
cityId)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        this.updateResidencyDisplay(data.residency);
                    }
                })
                .catch(error => console.error('Error calculating residency:', error));
        }
    },
    
    // עדכון תצוגת תושבות
    updateResidencyDisplay: function(residency) {
        const types = {
            1: {text: 'ירושלים והסביבה', class: 'green'},
            2: {text: 'תושב חוץ', class: 'blue'},
            3: {text: 'תושב חו"ל', class: 'orange'}
        };
        
        const type = types[residency] || types[3];
        document.getElementById('residency-display').innerHTML = `
            <span class="badge-residency ${type.class}">${type.text}</span>
            <input type="hidden" id="resident" name="resident" value="${residency}">
        `;
    },
    
    // Validation של ת.ז.
    validateIsraeliId: function(id) {
        if (id.length !== 9 || !/^\d+$/.test(id)) return false;
        
        let sum = 0;
        for (let i = 0; i < 9; i++) {
            let digit = parseInt(id[i]);
            digit *= ((i % 2) + 1);
            if (digit > 9) {
                digit = Math.floor(digit / 10) + (digit % 10);
            }
            sum += digit;
        }
        return sum % 10 === 0;
    },
    
    // פורמט טלפון
    formatPhone: function(phone) {
        phone = phone.replace(/[^0-9]/g, '');
        if (phone.length === 10 && phone.startsWith('05')) {
            return phone.slice(0, 3) + '-' + phone.slice(3);
        }
        if (phone.length === 9 && phone.startsWith('0')) {
            return phone.slice(0, 2) + '-' + phone.slice(2);
        }
        return phone;
    },
    
    // אתחול
    init: function() {
        // הוסף listeners לשינויים
        document.getElementById('countryId')?.addEventListener('change', (e) => {
            this.handleCountryChange(e.target.value);
        });
        
        document.getElementById('cityId')?.addEventListener('change', (e) => {
            this.handleCityChange(e.target.value);
        });
        
        // פורמט אוטומטי לטלפונים
        document.querySelectorAll('input[type="tel"]').forEach(input => {
            input.addEventListener('blur', (e) => {
                e.target.value = this.formatPhone(e.target.value);
            });
        });
        
        // Validation לת.ז.
        document.getElementById('numId')?.addEventListener('blur', function() {
            const typeId = document.getElementById('typeId').value;
            if (typeId == 1 && !CustomerFormHandler.validateIsraeliId(this.value)) {
                this.setCustomValidity('מספר ת.ז. לא תקין');
                this.reportValidity();
            } else {
                this.setCustomValidity('');
            }
        });
        
        // Collapsible field groups
        document.querySelectorAll('.field-group.collapsible legend').forEach(legend => {
            legend.addEventListener('click', function() {
                this.parentElement.classList.toggle('collapsed');
            });
        });
        
        // Form submission
        document.getElementById('customerForm')?.addEventListener('submit', (e) => {
            e.preventDefault();
            
            // Validate
            const numId = document.getElementById('numId');
            const typeId = document.getElementById('typeId').value;
            
            if (typeId == 1 && !this.validateIsraeliId(numId.value)) {
                alert('מספר ת.ז. לא תקין');
                numId.focus();
                return false;
            }
            
            // Submit form
            const formData = new FormData(e.target);
            
            fetch(e.target.action, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('הלקוח נשמר בהצלחה!');
                    window.opener?.location.reload();
                    window.close();
                } else {
                    alert('שגיאה: ' + (data.error || 'שגיאה לא 
ידועה'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('שגיאה בשמירת הנתונים');
            });
        });
        
        // אם בעריכה, טען ערים של המדינה
        <?php if ($customer && $customer['countryId']): ?>
        this.handleCountryChange('<?= $customer['countryId'] ?>');
        setTimeout(() => {
            document.getElementById('cityId').value = '<?= $customer['cityId'] ?? '' ?>';
        }, 500);
        <?php endif; ?>
    }
};

// הפעל את ה-handler
window.handleIdTypeChange = 
CustomerFormHandler.handleIdTypeChange.bind(CustomerFormHandler);

// אתחול בטעינת הדף
document.addEventListener('DOMContentLoaded', function() {
    CustomerFormHandler.init();
    
    // אתחל SmartSelect
    document.querySelectorAll('.smart-select').forEach(select => {
        if (select.dataset.searchable === 'true') {
            SmartSelectManager.init(select.id, {
                searchable: true,
                ajax_url: select.dataset.ajaxUrl
            });
        }
    });
});
</script>

</body>
</html>
CUSTOMERFORM

print_success "טופס הלקוח עודכן בהצלחה!"

# שלב 4: Commit ו-Push
print_info "שומר שינויים בגיט..."
git add .
git commit -m "Customer Form Updated with Smart Select and Advanced Features

- Implemented Smart Select for countries and cities
- Added field groups for better organization
- Added Israeli ID validation
- Added phone number formatting
- Dynamic residency calculation
- Responsive grid layout
- Collapsible sections
- AJAX city loading based on country selection

Features:
✅ Smart searchable country selector
✅ Dynamic city loading with AJAX
✅ Automatic residency calculation
✅ Real-time validation
✅ Phone formatting
✅ Field groups with grid layout
✅ Collapsible sections
✅ Full RTL support" 2>/dev/null

if [[ $? -eq 0 ]]; then
    print_success "Commit בוצע"
    
    git push origin main 2>/dev/null
    if [[ $? -eq 0 ]]; then
        print_success "Push בוצע"
    else
        print_info "בצע push ידנית: git push origin main"
    fi
else
    print_info "אין שינויים חדשים"
fi

echo ""
echo "========================================" 
print_success "העדכון הושלם בהצלחה!"
echo "========================================" 
echo ""
echo "📋 שיפורים שהוטמעו:"
echo "  ✅ Smart Select למדינות עם חיפוש"
echo "  ✅ Smart Select לערים עם טעינה דינמית"
echo "  ✅ חישוב תושבות אוטומטי"
echo "  ✅ Validation לת.ז. ישראלית"
echo "  ✅ פורמט אוטומטי לטלפונים"
echo "  ✅ Field Groups מאורגנים"
echo "  ✅ Sections מתקפלים"
echo "  ✅ עיצוב רספונסיבי"
echo ""
echo "🎯 בדיקה:"
echo "  פתח את הטופס בדפדפן:"
echo "  /dashboard/dashboards/cemeteries/forms/customer-form.php"
echo ""
echo "📥 במחשב המקומי:"
echo "  cd /Users/malkiel/projects/login/login"
echo "  git pull origin main"
echo ""
print_success "טופס הלקוח מוכן לשימוש! 🚀"
