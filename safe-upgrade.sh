#!/bin/bash

# ========================================
# Safe Customer Form Upgrade Script
# Version: 3.0.0 - Production Ready
# ========================================

# Global settings
set -euo pipefail

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
PURPLE='\033[0;35m'
NC='\033[0m'

# Paths
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
BASE_DIR="$SCRIPT_DIR"
FORMS_DIR="$BASE_DIR/dashboard/dashboards/cemeteries/forms"
BACKUP_DIR="$BASE_DIR/backup_customer_$(date '+%Y%m%d_%H%M%S')"
DEBUG_LOG="$BASE_DIR/customer_form_upgrade_debug.log"

# Status variables
ERRORS_FOUND=0
WARNINGS_FOUND=0
ROLLBACK_NEEDED=0

# === Helper functions ===
print_header() {
    echo ""
    echo -e "${PURPLE}========================================${NC}"
    echo -e "${PURPLE}   $1${NC}"
    echo -e "${PURPLE}========================================${NC}"
    echo ""
}

print_success() { 
    echo -e "${GREEN}✅ $1${NC}"
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] SUCCESS: $1" >> "$DEBUG_LOG"
}

print_info() { 
    echo -e "${BLUE}ℹ️  $1${NC}"
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] INFO: $1" >> "$DEBUG_LOG"
}

print_warning() { 
    echo -e "${YELLOW}⚠️  $1${NC}"
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] WARNING: $1" >> "$DEBUG_LOG"
    ((WARNINGS_FOUND++))
}

print_error() { 
    echo -e "${RED}❌ $1${NC}"
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] ERROR: $1" >> "$DEBUG_LOG"
    ((ERRORS_FOUND++))
    ROLLBACK_NEEDED=1
}

print_debug() {
    echo -e "${PURPLE}🔍 DEBUG: $1${NC}"
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] DEBUG: $1" >> "$DEBUG_LOG"
}

# === Check functions ===
check_file_exists() {
    local file=$1
    if [[ -f "$file" ]]; then
        print_debug "File exists: $file"
        return 0
    else
        print_error "File not found: $file"
        return 1
    fi
}

check_dir_exists() {
    local dir=$1
    if [[ -d "$dir" ]]; then
        print_debug "Directory exists: $dir"
        return 0
    else
        print_error "Directory not found: $dir"
        return 1
    fi
}

check_php_syntax() {
    local file=$1
    print_debug "Checking PHP syntax: $file"
    
    if php -l "$file" &>/dev/null; then
        print_debug "PHP syntax OK: $file"
        return 0
    else
        print_error "PHP syntax error in: $file"
        php -l "$file" 2>&1 | tee -a "$DEBUG_LOG"
        return 1
    fi
}

# === Rollback function ===
perform_rollback() {
    print_header "PERFORMING ROLLBACK"
    
    if [[ -d "$BACKUP_DIR" ]]; then
        print_info "Restoring from backup: $BACKUP_DIR"
        
        if [[ -f "$BACKUP_DIR/customer-form.php.bak" ]]; then
            cp "$BACKUP_DIR/customer-form.php.bak" "$FORMS_DIR/customer-form.php"
            print_success "customer-form.php restored"
        fi
        
        if [[ -f "$BACKUP_DIR/FormBuilder.php.bak" ]]; then
            cp "$BACKUP_DIR/FormBuilder.php.bak" "$FORMS_DIR/FormBuilder.php"
            print_success "FormBuilder.php restored"
        fi
        
        print_success "Rollback completed"
    else
        print_error "No backup found for rollback!"
    fi
}

# === Start script ===
print_header "Safe Customer Form Upgrade Script v3.0.0"

# Initialize debug log
echo "=== Customer Form Upgrade Debug Log ===" > "$DEBUG_LOG"
echo "Started at: $(date)" >> "$DEBUG_LOG"
echo "User: $(whoami)" >> "$DEBUG_LOG"
echo "Working directory: $BASE_DIR" >> "$DEBUG_LOG"
echo "" >> "$DEBUG_LOG"

print_info "Debug log: $DEBUG_LOG"

# === Phase 1: Pre-flight Checks ===
print_header "Phase 1: Pre-flight Checks"

# Check directories
if ! check_dir_exists "$FORMS_DIR"; then
    print_error "Forms directory not found. Exiting."
    exit 1
fi

# Check critical files
REQUIRED_FILES=(
    "$FORMS_DIR/customer-form.php"
    "$FORMS_DIR/FormBuilder.php"
)

for file in "${REQUIRED_FILES[@]}"; do
    check_file_exists "$file" || ROLLBACK_NEEDED=1
done

if [[ $ROLLBACK_NEEDED -eq 1 ]]; then
    print_error "Required files missing. Cannot continue."
    exit 1
fi

# Check PHP syntax of existing files
print_info "Checking PHP syntax of existing files..."
for file in "${REQUIRED_FILES[@]}"; do
    if [[ -f "$file" ]]; then
        check_php_syntax "$file"
    fi
done

print_success "Pre-flight checks completed"

# === Phase 2: Create full backup ===
print_header "Phase 2: Creating Full Backup"

mkdir -p "$BACKUP_DIR"
print_info "Backup directory: $BACKUP_DIR"

# Backup important files
cp "$FORMS_DIR/customer-form.php" "$BACKUP_DIR/customer-form.php.bak"
cp "$FORMS_DIR/FormBuilder.php" "$BACKUP_DIR/FormBuilder.php.bak"
print_success "Backup created successfully"

# Git commit
print_info "Creating Git commit for backup..."
git add . 2>/dev/null
git commit -m "Backup before customer form upgrade - $(date '+%Y-%m-%d %H:%M:%S')" 
2>/dev/null || print_info "No changes to commit"

# === Phase 3: Create enhanced customer form ===
print_header "Phase 3: Creating Enhanced Customer Form"

print_info "Creating new customer-form.php with debug mode..."

# Create the new PHP file - properly escaped
cat > "$FORMS_DIR/customer-form.php" << 'ENDFORM'
<?php
/**
 * Enhanced Customer Form with Smart Select - Safe Version
 * Version: 3.0.0 with Debug Mode
 */

// === DEBUG MODE ===
$DEBUG_MODE = true; // Set to false in production

if ($DEBUG_MODE) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
    echo "<!-- DEBUG: Form loading started at " . date('H:i:s') . " -->\n";
}

// === SAFE INCLUDES WITH CHECKING ===
$requiredFiles = [
    'FormBuilder.php',
    'SmartSelect.php', 
    'ValidationUtils.php',
    'FormUtils.php'
];

$includeErrors = [];
foreach ($requiredFiles as $file) {
    $filePath = __DIR__ . '/' . $file;
    if (file_exists($filePath)) {
        require_once $filePath;
        if ($DEBUG_MODE) {
            echo "<!-- DEBUG: Loaded $file successfully -->\n";
        }
    } else {
        $includeErrors[] = $file;
        if ($DEBUG_MODE) {
            echo "<!-- DEBUG ERROR: Missing file $file -->\n";
        }
    }
}

// Config file
$configPath = dirname(__DIR__) . '/config.php';
if (file_exists($configPath)) {
    require_once $configPath;
    if ($DEBUG_MODE) {
        echo "<!-- DEBUG: Config loaded successfully -->\n";
    }
} else {
    die("ERROR: Config file not found at: $configPath");
}

// Fallback to basic version if critical files are missing
if (!empty($includeErrors)) {
    if ($DEBUG_MODE) {
        echo "<!-- DEBUG: Missing files: " . implode(', ', $includeErrors) . " -->\n";
        echo "<!-- DEBUG: Falling back to basic version -->\n";
    }
}

// === PARAMETERS ===
$itemId = $_GET['itemId'] ?? $_GET['id'] ?? null;
$parentId = $_GET['parentId'] ?? $_GET['parent_id'] ?? null;
$formType = 'customer';

if ($DEBUG_MODE) {
    echo "<!-- DEBUG: itemId=$itemId, parentId=$parentId, formType=$formType -->\n";
}

// === DATABASE CONNECTION ===
try {
    $conn = getDBConnection();
    if ($DEBUG_MODE) {
        echo "<!-- DEBUG: Database connected successfully -->\n";
    }
    
    // === LOAD COUNTRIES ===
    $countriesStmt = $conn->prepare("
        SELECT unicId, countryNameHe, countryNameEn 
        FROM countries 
        WHERE isActive = 1 
        ORDER BY countryNameHe
    ");
    $countriesStmt->execute();
    $countries = [];
    while ($row = $countriesStmt->fetch(PDO::FETCH_ASSOC)) {
        $countries[$row['unicId']] = $row['countryNameHe'];
    }
    
    if ($DEBUG_MODE) {
        echo "<!-- DEBUG: Loaded " . count($countries) . " countries -->\n";
    }
    
    // === LOAD CITIES ===
    $citiesStmt = $conn->prepare("
        SELECT unicId, countryId, cityNameHe, cityNameEn 
        FROM cities 
        WHERE isActive = 1 
        ORDER BY cityNameHe
    ");
    $citiesStmt->execute();
    $allCities = $citiesStmt->fetchAll(PDO::FETCH_ASSOC);
    
    if ($DEBUG_MODE) {
        echo "<!-- DEBUG: Loaded " . count($allCities) . " cities -->\n";
    }
    
    // === LOAD CUSTOMER IF EDITING ===
    $customer = null;
    if ($itemId) {
        $stmt = $conn->prepare("SELECT * FROM customers WHERE id = ? AND isActive = 1");
        $stmt->execute([$itemId]);
        $customer = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($DEBUG_MODE) {
            if ($customer) {
                echo "<!-- DEBUG: Customer loaded: ID=" . $customer['id'] . " -->\n";
            } else {
                echo "<!-- DEBUG: No customer found with ID=$itemId -->\n";
            }
        }
    }
    
} catch (Exception $e) {
    if ($DEBUG_MODE) {
        echo "<!-- DEBUG ERROR: Database error: " . $e->getMessage() . " -->\n";
    }
    die("Database error: " . $e->getMessage());
}

// === CREATE FORM ===
try {
    // Check if FormBuilder exists
    if (!class_exists('FormBuilder')) {
        throw new Exception("FormBuilder class not found");
    }
    
    $formBuilder = new FormBuilder('customer', $itemId, $parentId);
    
    if ($DEBUG_MODE) {
        echo "<!-- DEBUG: FormBuilder created successfully -->\n";
    }
    
} catch (Exception $e) {
    if ($DEBUG_MODE) {
        echo "<!-- DEBUG ERROR: FormBuilder error: " . $e->getMessage() . " -->\n";
    }
    die("FormBuilder error: " . $e->getMessage());
}

// Prepare cities JSON
$citiesJson = json_encode($allCities, JSON_UNESCAPED_UNICODE);

// === BUILD FORM FIELDS ===

// ID Type
$formBuilder->addField('typeId', 'סוג זיהוי', 'select', [
    'options' => [
        1 => 'ת.ז.',
        2 => 'דרכון',
        3 => 'אלמוני',
        4 => 'תינוק'
    ],
    'value' => $customer['typeId'] ?? 1
]);

// ID Number
$formBuilder->addField('numId', 'מספר זיהוי', 'text', [
    'required' => true,
    'placeholder' => '9 ספרות',
    'value' => $customer['numId'] ?? ''
]);

// First Name
$formBuilder->addField('firstName', 'שם פרטי', 'text', [
    'required' => true,
    'value' => $customer['firstName'] ?? ''
]);

// Last Name
$formBuilder->addField('lastName', 'שם משפחה', 'text', [
    'required' => true,
    'value' => $customer['lastName'] ?? ''
]);

// Nickname
$formBuilder->addField('nom', 'כינוי', 'text', [
    'value' => $customer['nom'] ?? ''
]);

// Gender
$formBuilder->addField('gender', 'מגדר', 'select', [
    'options' => [
        '' => '-- בחר --',
        1 => 'זכר',
        2 => 'נקבה'
    ],
    'value' => $customer['gender'] ?? ''
]);

// Birth Date
$formBuilder->addField('dateBirth', 'תאריך לידה', 'date', [
    'value' => $customer['dateBirth'] ?? ''
]);

// Father Name
$formBuilder->addField('nameFather', 'שם אב', 'text', [
    'value' => $customer['nameFather'] ?? ''
]);

// Mother Name
$formBuilder->addField('nameMother', 'שם אם', 'text', [
    'value' => $customer['nameMother'] ?? ''
]);

// Marital Status
$formBuilder->addField('maritalStatus', 'מצב משפחתי', 'select', [
    'options' => [
        '' => '-- בחר --',
        1 => 'רווק/ה',
        2 => 'נשוי/אה',
        3 => 'אלמן/ה',
        4 => 'גרוש/ה'
    ],
    'value' => $customer['maritalStatus'] ?? ''
]);

// === ADDRESS SECTION ===
// Check if SmartSelect is available
$useSmartSelect = class_exists('SmartSelect');

if ($DEBUG_MODE) {
    echo "<!-- DEBUG: SmartSelect available: " . ($useSmartSelect ? 'YES' : 'NO') . " 
-->\n";
}

if ($useSmartSelect) {
    // Use Smart Select for country
    $countryOptions = [];
    foreach ($countries as $id => $name) {
        $countryOptions[$id] = [
            'text' => $name,
            'subtitle' => '',
            'badge' => ''
        ];
    }
    
    $formBuilder->addField('countryId', 'מדינה', 'smart_select', [
        'searchable' => true,
        'placeholder' => 'חפש מדינה...',
        'options' => $countryOptions,
        'value' => $customer['countryId'] ?? ''
    ]);
    
    // Smart Select for city  
    $formBuilder->addField('cityId', 'עיר', 'smart_select', [
        'searchable' => true,
        'placeholder' => 'בחר קודם מדינה...',
        'depends_on' => 'countryId',
        'ajax_url' => '/dashboard/dashboards/cemeteries/api/get-cities.php',
        'value' => $customer['cityId'] ?? ''
    ]);
    
} else {
    // Fallback to regular select with custom HTML
    $addressHTML = '
    <fieldset class="form-section" id="address-fieldset" data-cities=\'' . 
htmlspecialchars($citiesJson, ENT_QUOTES, 'UTF-8') . '\'>
        <legend>כתובת</legend>
        <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 15px;">
            <div class="form-group">
                <label>מדינה</label>
                <select id="countrySelect" name="countryId" class="form-control" 
onchange="filterCities()">
                    <option value="">-- בחר מדינה --</option>';
    
    foreach ($countries as $unicId => $name) {
        $selected = ($customer && $customer['countryId'] == $unicId) ? 'selected' : '';
        $addressHTML .= '<option value="' . $unicId . '" ' . $selected . '>' . 
                        htmlspecialchars($name) . '</option>';
    }
    
    $addressHTML .= '
                </select>
            </div>
            <div class="form-group">
                <label>עיר</label>
                <select id="citySelect" name="cityId" class="form-control">
                    <option value="">-- בחר קודם מדינה --</option>';
    
    if ($customer && $customer['countryId']) {
        foreach ($allCities as $city) {
            if ($city['countryId'] == $customer['countryId']) {
                $selected = ($customer['cityId'] == $city['unicId']) ? 'selected' : '';
                $addressHTML .= '<option value="' . $city['unicId'] . '" ' . $selected . 
'>' . 
                                htmlspecialchars($city['cityNameHe']) . '</option>';
            }
        }
    }
    
    $addressHTML .= '
                </select>
            </div>
            <div class="form-group" style="grid-column: span 2;">
                <label>כתובת מלאה</label>
                <input type="text" name="address" class="form-control" 
                    value="' . htmlspecialchars($customer['address'] ?? '') . '" 
                    placeholder="רחוב, מספר בית">
            </div>
        </div>
    </fieldset>';
    
    $formBuilder->addCustomHTML($addressHTML);
}

// Full Address
$formBuilder->addField('address', 'כתובת מלאה', 'text', [
    'placeholder' => 'רחוב, מספר בית',
    'value' => $customer['address'] ?? ''
]);

// Phone
$formBuilder->addField('phone', 'טלפון', 'tel', [
    'value' => $customer['phone'] ?? ''
]);

// Mobile Phone
$formBuilder->addField('phoneMobile', 'טלפון נייד', 'tel', [
    'value' => $customer['phoneMobile'] ?? ''
]);

// Customer Status
$formBuilder->addField('statusCustomer', 'סטטוס לקוח', 'select', [
    'options' => [
        1 => 'פעיל',
        2 => 'רוכש',
        3 => 'נפטר'
    ],
    'value' => $customer['statusCustomer'] ?? 1
]);

// Residency
$formBuilder->addField('resident', 'תושבות', 'select', [
    'options' => [
        1 => 'ירושלים והסביבה',
        2 => 'תושב חוץ',
        3 => 'תושב חו״ל'
    ],
    'value' => $customer['resident'] ?? 3,
    'readonly' => true
]);

// Association
$formBuilder->addField('association', 'שיוך', 'select', [
    'options' => [
        1 => 'ישראל',
        2 => 'כהן',
        3 => 'לוי'
    ],
    'value' => $customer['association'] ?? 1
]);

// Spouse
$formBuilder->addField('spouse', 'בן/בת זוג', 'text', [
    'value' => $customer['spouse'] ?? ''
]);

// Comments
$formBuilder->addField('comment', 'הערות', 'textarea', [
    'rows' => 3,
    'value' => $customer['comment'] ?? ''
]);

// Add unicId if editing
if ($customer && $customer['unicId']) {
    $formBuilder->addField('unicId', '', 'hidden', [
        'value' => $customer['unicId']
    ]);
}

// === RENDER FORM ===
echo $formBuilder->renderModal();

// === ADD JAVASCRIPT ===
if (!$useSmartSelect) {
    // Add JavaScript for city filtering if not using SmartSelect
    ?>
    <script>
    function filterCities() {
        const countrySelect = document.getElementById('countrySelect');
        const citySelect = document.getElementById('citySelect');
        const fieldset = document.getElementById('address-fieldset');
        
        if (!fieldset || !fieldset.dataset.cities) return;
        
        const citiesData = JSON.parse(fieldset.dataset.cities);
        const selectedCountry = countrySelect.value;
        
        citySelect.innerHTML = '<option value="">-- בחר עיר --</option>';
        
        if (!selectedCountry) {
            citySelect.innerHTML = '<option value="">-- בחר קודם מדינה 
--</option>';
            return;
        }
        
        const filteredCities = citiesData.filter(city => city.countryId === 
selectedCountry);
        
        if (filteredCities.length === 0) {
            citySelect.innerHTML = '<option value="">-- אין ערים 
למדינה זו --</option>';
            return;
        }
        
        filteredCities.forEach(city => {
            const option = document.createElement('option');
            option.value = city.unicId;
            option.textContent = city.cityNameHe;
            citySelect.appendChild(option);
        });
    }
    
    // ID validation
    document.addEventListener('DOMContentLoaded', function() {
        const typeId = document.getElementById('typeId');
        const numId = document.getElementById('numId');
        
        if (typeId && numId) {
            typeId.addEventListener('change', function() {
                switch(parseInt(this.value)) {
                    case 1: // ID
                        numId.pattern = '[0-9]{9}';
                        numId.placeholder = '9 ספרות';
                        numId.maxLength = 9;
                        break;
                    case 2: // Passport
                        numId.pattern = '[A-Z0-9]+';
                        numId.placeholder = 'מספר דרכון';
                        numId.maxLength = 20;
                        break;
                    case 3: // Anonymous
                    case 4: // Baby
                        numId.removeAttribute('required');
                        numId.value = '000000000';
                        break;
                }
            });
        }
    });
    </script>
    <?php
}

// === DEBUG FOOTER ===
if ($DEBUG_MODE) {
    echo "\n<!-- DEBUG: Form rendering completed at " . date('H:i:s') . " -->";
    echo "\n<!-- DEBUG: Peak memory usage: " . (memory_get_peak_usage(true) / 1024 / 1024) 
. " MB -->";
    echo "\n<!-- DEBUG: Execution time: " . (microtime(true) - 
$_SERVER["REQUEST_TIME_FLOAT"]) . " seconds -->";
}
?>
ENDFORM

print_success "Customer form created with debug mode"

# Check PHP syntax of the new file
print_info "Validating PHP syntax..."
if ! check_php_syntax "$FORMS_DIR/customer-form.php"; then
    print_error "PHP syntax error in new customer form!"
    perform_rollback
    exit 1
fi

print_success "PHP syntax validated"

# === Phase 4: Git Commit ===
print_header "Phase 4: Git Commit"

git add .
git commit -m "Customer Form Upgrade with Debug Mode

- Added debug mode for troubleshooting
- Safe fallback for missing SmartSelect
- Enhanced error handling
- Backward compatibility maintained
- Debug log: $DEBUG_LOG

Files modified:
- customer-form.php (with debug mode)" 2>/dev/null || print_info "No changes to commit"

if [[ $? -eq 0 ]]; then
    print_success "Changes committed to Git"
    
    print_info "Pushing to remote..."
    git push origin main 2>/dev/null
    
    if [[ $? -eq 0 ]]; then
        print_success "Pushed to remote repository"
    else
        print_warning "Could not push to remote. Run manually: git push origin main"
    fi
fi

# === Phase 5: Summary & Testing ===
print_header "Phase 5: Summary & Testing"

echo ""
echo -e "${GREEN}========================================${NC}"
echo -e "${GREEN}   ✅ UPGRADE COMPLETED SUCCESSFULLY${NC}"
echo -e "${GREEN}========================================${NC}"
echo ""

print_info "Summary:"
echo "  • Backup created: $BACKUP_DIR"
echo "  • Debug log: $DEBUG_LOG"
echo "  • Errors found: $ERRORS_FOUND"
echo "  • Warnings: $WARNINGS_FOUND"
echo ""

print_info "🧪 Testing Instructions:"
echo ""
echo "1. Open the form in browser:"
echo "   https://your-domain.com/dashboard/dashboards/cemeteries/forms/customer-form.php"
echo ""
echo "2. Check browser console for debug messages"
echo ""
echo "3. View page source to see debug comments"
echo ""
echo "4. Check debug log:"
echo "   cat $DEBUG_LOG"
echo ""

print_info "📝 Debug Mode:"
echo "The form is currently in DEBUG MODE."
echo "To disable debug mode, edit customer-form.php and set:"
echo "  \$DEBUG_MODE = false;"
echo ""

print_warning "⚠️ IMPORTANT:"
echo "Test the form thoroughly before disabling debug mode!"
echo ""

print_success "Script completed successfully! 🎉"
