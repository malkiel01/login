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
