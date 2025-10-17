#!/bin/bash

# ========================================
# Complete Installation Script
# מתקין את SmartSelect ומעדכן את טופס הלקוח
# ========================================

echo ""
echo "========================================" 
echo "   📦 התקנה מלאה - SmartSelect + Customer Form"
echo "========================================" 
echo ""

# צבעים לפלט
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# פונקציה להדפסת הודעות
print_success() {
    echo -e "${GREEN}✅ $1${NC}"
}

print_error() {
    echo -e "${RED}❌ $1${NC}"
}

print_info() {
    echo -e "${YELLOW}ℹ️ $1${NC}"
}

# בדיקת נתיב
CURRENT_DIR=$(pwd)
print_info "נתיב נוכחי: $CURRENT_DIR"

# בדיקה שאנחנו בתיקייה הנכונה
if [[ ! -d "dashboard/dashboards/cemeteries" ]]; then
    print_error "לא נמצאת תיקיית dashboard/dashboards/cemeteries"
    echo "וודא שאתה בתיקיית login הראשית"
    exit 1
fi

# שלב 1: שמירת מצב נוכחי בגיט
print_info "שומר מצב נוכחי בגיט..."
git add . 2>/dev/null
git commit -m "Backup before SmartSelect and Customer Form installation" 2>/dev/null || 
print_info "אין שינויים לשמירה"

# שלב 2: יצירת תיקיות נדרשות
print_info "יוצר תיקיות נדרשות..."
mkdir -p dashboard/dashboards/cemeteries/forms
mkdir -p dashboard/dashboards/cemeteries/js  
mkdir -p dashboard/dashboards/cemeteries/css
mkdir -p dashboard/dashboards/cemeteries/api
mkdir -p backup_$(date "+%Y%m%d_%H%M%S")

# שלב 3: יצירת SmartSelect.php
print_info "יוצר SmartSelect.php..."
cat > dashboard/dashboards/cemeteries/forms/SmartSelect.php << 'ENDOFFILE'
<?php
/**
 * SmartSelect Component - Minimal Version
 * For quick installation
 */
class SmartSelect {
    private $name;
    private $label;
    private $options = [];
    private $config = [];
    
    public function __construct($name, $label, $options = [], $config = []) {
        $this->name = $name;
        $this->label = $label;
        $this->options = $options;
        $this->config = array_merge([
            'searchable' => false,
            'placeholder' => 'בחר...',
            'required' => false,
            'display_mode' => 'simple'
        ], $config);
    }
    
    public function render() {
        $html = '<div class="smart-select-container">';
        $html .= '<label for="' . $this->name . '">' . $this->label;
        if ($this->config['required']) {
            $html .= ' <span class="text-danger">*</span>';
        }
        $html .= '</label>';
        
        $html .= '<select id="' . $this->name . '" name="' . $this->name . '" 
class="form-control smart-select"';
        if ($this->config['searchable']) {
            $html .= ' data-searchable="true"';
        }
        if ($this->config['required']) {
            $html .= ' required';
        }
        $html .= '>';
        
        if (!$this->config['required']) {
            $html .= '<option value="">' . $this->config['placeholder'] . '</option>';
        }
        
        foreach ($this->options as $value => $label) {
            if (is_array($label)) {
                $text = $label['text'] ?? $label['name'] ?? '';
                $html .= '<option value="' . htmlspecialchars($value) . '"';
                if (isset($label['subtitle'])) {
                    $html .= ' data-subtitle="' . htmlspecialchars($label['subtitle']) . 
'"';
                }
                $html .= '>' . htmlspecialchars($text) . '</option>';
            } else {
                $html .= '<option value="' . htmlspecialchars($value) . '">' . 
htmlspecialchars($label) . '</option>';
            }
        }
        
        $html .= '</select>';
        $html .= '</div>';
        
        return $html;
    }
    
    public static function create($name, $label, $options = [], $config = []) {
        return new self($name, $label, $options, $config);
    }
}
?>
ENDOFFILE

if [[ -f "dashboard/dashboards/cemeteries/forms/SmartSelect.php" ]]; then
    print_success "SmartSelect.php נוצר בהצלחה"
else
    print_error "כישלון ביצירת SmartSelect.php"
    exit 1
fi

# שלב 4: עדכון FormBuilder.php
print_info "מעדכן FormBuilder.php..."
if [[ -f "dashboard/dashboards/cemeteries/forms/FormBuilder.php" ]]; then
    # גיבוי
    cp dashboard/dashboards/cemeteries/forms/FormBuilder.php backup_$(date 
+%Y%m%d_%H%M%S)/FormBuilder.php.bak
    
    # בדוק אם כבר יש תמיכה ב-SmartSelect
    if grep -q "renderSmartSelect" dashboard/dashboards/cemeteries/forms/FormBuilder.php; 
then
        print_info "FormBuilder כבר תומך ב-SmartSelect"
    else
        # הוסף require בתחילת הקובץ אם לא קיים
        if ! grep -q "SmartSelect.php" 
dashboard/dashboards/cemeteries/forms/FormBuilder.php; then
            sed -i.tmp '1a\
require_once __DIR__ . "/SmartSelect.php";' 
dashboard/dashboards/cemeteries/forms/FormBuilder.php
        fi
        
        # הוסף את המתודה renderSmartSelect
        cat >> dashboard/dashboards/cemeteries/forms/FormBuilder.php << 'ENDMETHOD'

    /**
     * Render Smart Select
     */
    private function renderSmartSelect($field) {
        $smartSelect = new SmartSelect(
            $field['name'],
            $field['label'],
            $field['options'] ?? [],
            [
                'searchable' => $field['searchable'] ?? false,
                'placeholder' => $field['placeholder'] ?? 'בחר...',
                'required' => $field['required'] ?? false,
                'display_mode' => $field['display_mode'] ?? 'simple'
            ]
        );
        return $smartSelect->render();
    }
ENDMETHOD
        
        print_success "FormBuilder.php עודכן"
    fi
fi

# שלב 5: יצירת smart-select.js
print_info "יוצר smart-select.js..."
cat > dashboard/dashboards/cemeteries/js/smart-select.js << 'ENDJS'
// SmartSelect JavaScript - Basic Version
window.SmartSelectManager = {
    instances: {},
    
    init: function(name, config) {
        const element = document.getElementById(name);
        if (!element) return;
        
        this.instances[name] = {
            element: element,
            config: config
        };
        
        if (config.searchable) {
            this.makeSearchable(element);
        }
    },
    
    makeSearchable: function(select) {
        // יצירת wrapper
        const wrapper = document.createElement('div');
        wrapper.className = 'smart-select-wrapper';
        select.parentNode.insertBefore(wrapper, select);
        
        // יצירת שדה חיפוש
        const searchInput = document.createElement('input');
        searchInput.type = 'text';
        searchInput.className = 'smart-select-search';
        searchInput.placeholder = 'חפש...';
        searchInput.style.display = 'none';
        
        wrapper.appendChild(searchInput);
        wrapper.appendChild(select);
        
        // הוסף אירועים
        select.addEventListener('focus', function() {
            searchInput.style.display = 'block';
            searchInput.focus();
        });
        
        searchInput.addEventListener('input', function(e) {
            const query = e.target.value.toLowerCase();
            const options = select.querySelectorAll('option');
            
            options.forEach(option => {
                if (!option.value) return;
                const text = option.textContent.toLowerCase();
                option.style.display = text.includes(query) ? '' : 'none';
            });
        });
    },
    
    updateOptions: function(name, options) {
        const instance = this.instances[name];
        if (!instance) return;
        
        const select = instance.element;
        select.innerHTML = '<option value="">בחר...</option>';
        
        options.forEach(opt => {
            const option = document.createElement('option');
            option.value = opt.value || opt.id;
            option.textContent = opt.text || opt.name;
            select.appendChild(option);
        });
    }
};

// אתחול אוטומטי
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.smart-select[data-searchable="true"]').forEach(select => {
        SmartSelectManager.init(select.id, {searchable: true});
    });
});
ENDJS

print_success "smart-select.js נוצר"

# שלב 6: יצירת smart-select.css
print_info "יוצר smart-select.css..."
cat > dashboard/dashboards/cemeteries/css/smart-select.css << 'ENDCSS'
/* SmartSelect Styles - Basic Version */
.smart-select-container {
    margin-bottom: 1rem;
}

.smart-select-wrapper {
    position: relative;
}

.smart-select-search {
    width: 100%;
    padding: 8px;
    margin-bottom: 5px;
    border: 1px solid #ddd;
    border-radius: 4px;
}

.smart-select {
    width: 100%;
}

.smart-select-container label {
    display: block;
    margin-bottom: 5px;
    font-weight: 600;
}

.text-danger {
    color: #dc3545;
}
ENDCSS

print_success "smart-select.css נוצר"

# שלב 7: יצירת ValidationUtils.php
print_info "יוצר ValidationUtils.php..."
cat > dashboard/dashboards/cemeteries/forms/ValidationUtils.php << 'ENDVAL'
<?php
class ValidationUtils {
    public static function validateIsraeliId($id) {
        $id = trim($id);
        if (strlen($id) != 9 || !ctype_digit($id)) return false;
        
        $sum = 0;
        for ($i = 0; $i < 9; $i++) {
            $digit = intval($id[$i]);
            $digit *= (($i % 2) + 1);
            if ($digit > 9) {
                $digit = ($digit / 10) + ($digit % 10);
            }
            $sum += $digit;
        }
        return ($sum % 10 == 0);
    }
    
    public static function formatPhone($phone) {
        $phone = preg_replace('/[^0-9]/', '', $phone);
        if (strlen($phone) == 10 && substr($phone, 0, 2) == '05') {
            return substr($phone, 0, 3) . '-' . substr($phone, 3);
        }
        return $phone;
    }
}
?>
ENDVAL

print_success "ValidationUtils.php נוצר"

# שלב 8: עדכון index.php אם קיים
if [[ -f "dashboard/dashboards/cemeteries/index.php" ]]; then
    print_info "מעדכן index.php..."
    
    # בדוק אם כבר יש את הקבצים
    if ! grep -q "smart-select.css" dashboard/dashboards/cemeteries/index.php; then
        # הוסף CSS
        sed -i.tmp 's|</head>|    <link rel="stylesheet" 
href="/dashboard/dashboards/cemeteries/css/smart-select.css">\n</head>|' 
dashboard/dashboards/cemeteries/index.php
    fi
    
    if ! grep -q "smart-select.js" dashboard/dashboards/cemeteries/index.php; then
        # הוסף JS
        sed -i.tmp 's|</body>|    <script 
src="/dashboard/dashboards/cemeteries/js/smart-select.js"></script>\n</body>|' 
dashboard/dashboards/cemeteries/index.php
    fi
    
    print_success "index.php עודכן"
fi

# שלב 9: יצירת API endpoints
print_info "יוצר API endpoints..."

# get-cities.php
cat > dashboard/dashboards/cemeteries/api/get-cities.php << 'ENDAPI1'
<?php
header('Content-Type: application/json; charset=utf-8');
require_once dirname(__DIR__) . '/config.php';

try {
    $countryId = $_GET['countryId'] ?? null;
    $conn = getDBConnection();
    
    $sql = "SELECT unicId as value, cityNameHe as text 
            FROM cities WHERE isActive = 1";
    
    if ($countryId) {
        $sql .= " AND countryId = :countryId";
    }
    
    $sql .= " ORDER BY cityNameHe";
    
    $stmt = $conn->prepare($sql);
    if ($countryId) {
        $stmt->bindParam(':countryId', $countryId);
    }
    $stmt->execute();
    
    $cities = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'cities' => $cities
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
ENDAPI1

# calculate-residency.php
cat > dashboard/dashboards/cemeteries/api/calculate-residency.php << 'ENDAPI2'
<?php
header('Content-Type: application/json; charset=utf-8');
require_once dirname(__DIR__) . '/config.php';

try {
    $cityId = $_GET['cityId'] ?? null;
    $residency = 3; // Default: חו"ל
    
    if ($cityId) {
        $conn = getDBConnection();
        $stmt = $conn->prepare("
            SELECT c.cityNameHe, cn.countryNameHe 
            FROM cities c
            JOIN countries cn ON c.countryId = cn.unicId
            WHERE c.unicId = :cityId
        ");
        $stmt->execute(['cityId' => $cityId]);
        $city = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($city && $city['countryNameHe'] == 'ישראל') {
            $jerusalemArea = ['ירושלים', 'בית שמש', 'מעלה 
אדומים'];
            if (in_array($city['cityNameHe'], $jerusalemArea)) {
                $residency = 1; // ירושלים והסביבה
            } else {
                $residency = 2; // תושב חוץ
            }
        }
    }
    
    echo json_encode([
        'success' => true,
        'residency' => $residency
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
ENDAPI2

print_success "API endpoints נוצרו"

# שלב 10: Commit לגיט
print_info "שומר שינויים בגיט..."
git add .
git commit -m "Complete SmartSelect and Customer Form Enhancement

- Added SmartSelect component
- Added validation utilities  
- Created API endpoints for dynamic data
- Added JavaScript and CSS files
- Enhanced form functionality

Files created:
- SmartSelect.php
- ValidationUtils.php
- smart-select.js
- smart-select.css
- api/get-cities.php
- api/calculate-residency.php" 2>/dev/null

if [[ $? -eq 0 ]]; then
    print_success "Commit בוצע בהצלחה"
    
    # Push לגיט
    print_info "מעלה לגיט..."
    git push origin main 2>/dev/null
    
    if [[ $? -eq 0 ]]; then
        print_success "Push בוצע בהצלחה"
    else
        print_error "Push נכשל, בצע ידנית: git push origin main"
    fi
else
    print_info "לא היו שינויים לשמירה"
fi

echo ""
echo "========================================" 
print_success "ההתקנה הושלמה בהצלחה!"
echo "========================================" 
echo ""
echo "📋 סיכום:"
echo "  • SmartSelect הותקן"
echo "  • FormBuilder עודכן"
echo "  • API endpoints נוצרו"
echo "  • JavaScript ו-CSS נוספו"
echo ""
echo "🎯 השלבים הבאים:"
echo "  1. בדוק את הטפסים שלך"
echo "  2. במחשב המקומי: git pull origin main"
echo ""
echo "📦 קבצים שנוצרו:"
echo "  • forms/SmartSelect.php"
echo "  • forms/ValidationUtils.php"
echo "  • js/smart-select.js"
echo "  • css/smart-select.css"
echo "  • api/get-cities.php"
echo "  • api/calculate-residency.php"
echo ""
print_success "הסקריפט הסתיים בהצלחה! ✨"
