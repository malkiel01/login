<?php
// dashboard/dashboards/cemeteries/forms/residency-form.php
// טופס להוספה/עריכה של הגדרות תושבות - משתמש ב-FormBuilder

error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: text/html; charset=utf-8');

require_once __DIR__ . '/FormBuilder.php';
require_once dirname(__DIR__) . '/config.php';


// === קבלת פרמטרים אחידה ===
$itemId = $_GET['itemId'] ?? $_GET['id'] ?? null;
$parentId = $_GET['parentId'] ?? $_GET['parent_id'] ?? null;
$formType = basename(__FILE__, '.php'); // מזהה אוטומטי של סוג הטופס

$parentId = $_GET['parent_id'] ?? null;

try {
    $conn = getDBConnection();
    
    // טען מדינות
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
    
    // טען את כל הערים
    $citiesStmt = $conn->prepare("
        SELECT unicId, countryId, cityNameHe, cityNameEn 
        FROM cities 
        WHERE isActive = 1 
        ORDER BY cityNameHe
    ");
    $citiesStmt->execute();
    $allCities = $citiesStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // טען הגדרת תושבות אם בעריכה
    $residency = null;
    if ($itemId) {
        $stmt = $conn->prepare("
            SELECT * FROM residency_settings 
            WHERE unicId = ? AND isActive = 1
        ");
        $stmt->execute([$itemId]);
        $residency = $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
} catch (Exception $e) {
    FormUtils::handleError($e);
}

// הכן את ה-JSON של הערים
$citiesJson = json_encode($allCities);

// יצירת FormBuilder
$formBuilder = new FormBuilder('residency', $itemId, $parentId);

// שם הגדרת תושבות
$formBuilder->addField('residencyName', 'שם הגדרת תושבות', 'text', [
    'required' => true,
    'placeholder' => 'לדוגמה: תושבי ירושלים - אזור מרכז',
    'value' => $residency['residencyName'] ?? ''
]);

// סוג תושבות
$formBuilder->addField('residencyType', 'סוג תושבות', 'select', [
    'required' => true,
    'options' => [
        '' => '-- בחר סוג תושבות --',
        'jerusalem_area' => 'תושבי ירושלים והסביבה',
        'israel' => 'תושבי ישראל',
        'abroad' => 'תושבי חו״ל'
    ],
    'value' => $residency['residencyType'] ?? ''
]);

// HTML מותאם אישית לבחירת מדינה ועיר עם data attribute
$locationSelectorHTML = '
<fieldset class="form-section" 
        id="location-fieldset"
        style="border: 1px solid #e2e8f0; border-radius: 8px; padding: 15px; margin-bottom: 20px;"
        data-cities=\'' . htmlspecialchars($citiesJson, ENT_QUOTES, 'UTF-8') . '\'>
    <legend style="padding: 0 10px; font-weight: bold;">מיקום</legend>
    <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 15px;">
        <div class="form-group">
            <label>מדינה</label>
            <select id="countrySelect" name="countryId" class="form-control">
                <option value="">-- בחר מדינה (אופציונלי) --</option>';

foreach ($countries as $unicId => $name) {
    $selected = ($residency && $residency['countryId'] == $unicId) ? 'selected' : '';
    $locationSelectorHTML .= '<option value="' . $unicId . '" ' . $selected . '>' . 
                            htmlspecialchars($name) . '</option>';
}

$locationSelectorHTML .= '
            </select>
        </div>
        <div class="form-group">
            <label>עיר</label>
            <select id="citySelect" name="cityId" class="form-control">
                <option value="">-- בחר עיר (אופציונלי) --</option>';

// אם בעריכה ויש מדינה נבחרת, הצג רק את הערים של המדינה
if ($residency && $residency['countryId']) {
    foreach ($allCities as $city) {
        if ($city['countryId'] == $residency['countryId']) {
            $selected = ($residency['cityId'] == $city['unicId']) ? 'selected' : '';
            $locationSelectorHTML .= '<option value="' . $city['unicId'] . '" ' . $selected . '>' . 
                                htmlspecialchars($city['cityNameHe']) . '</option>';
        }
    }
} else {
    // אם אין מדינה נבחרת, הצג את כל הערים
    foreach ($allCities as $city) {
        $selected = ($residency && $residency['cityId'] == $city['unicId']) ? 'selected' : '';
        $locationSelectorHTML .= '<option value="' . $city['unicId'] . '" ' . $selected . '>' . 
                            htmlspecialchars($city['cityNameHe']) . '</option>';
    }
}

$locationSelectorHTML .= '
            </select>
        </div>
    </div>
</fieldset>';

// הוסף את ה-HTML המותאם אישית
$formBuilder->addCustomHTML($locationSelectorHTML);

// תיאור
$formBuilder->addField('description', 'תיאור', 'textarea', [
    'rows' => 4,
    'placeholder' => 'תיאור ההגדרה (אופציונלי)',
    'value' => $residency['description'] ?? ''
]);

// אם זה עריכה, הוסף את ה-unicId כשדה מוסתר
if ($residency && $residency['unicId']) {
    $formBuilder->addField('unicId', '', 'hidden', [
        'value' => $residency['unicId']
    ]);
}

// הוסף שדות נסתרים לשמות המדינה והעיר (לשמירה בטבלה)
$formBuilder->addCustomHTML('
<input type="hidden" id="countryNameHe" name="countryNameHe" value="' . ($residency['countryNameHe'] ?? '') . '">
<input type="hidden" id="cityNameHe" name="cityNameHe" value="' . ($residency['cityNameHe'] ?? '') . '">
');

// הצג את הטופס עם FormBuilder
echo $formBuilder->renderModal();

// הוסף את הסקריפטים בסוף, אחרי ה-render
?>
<script>
 document.addEventListener("DOMContentLoaded", function() {
     const countrySelect = document.getElementById("countrySelect");
     const citySelect = document.getElementById("citySelect");
     const fieldset = document.getElementById("location-fieldset");
     const citiesData = JSON.parse(fieldset.dataset.cities);
     
     // פונקציה לסינון ערים לפי מדינה
     countrySelect.addEventListener("change", function() {
         const selectedCountryId = this.value;
         
         // נקה את רשימת הערים
         citySelect.innerHTML = "";
         
         if (!selectedCountryId) {
             // אם לא נבחרה מדינה, הצג את כל הערים
             citySelect.innerHTML = '<option value="">-- בחר עיר (אופציונלי) --</option>';
             citiesData.forEach(city => {
                 const option = document.createElement("option");
                 option.value = city.unicId;
                 option.textContent = city.cityNameHe;
                 citySelect.appendChild(option);
             });
         } else {
             // סנן ערים לפי המדינה שנבחרה
             citySelect.innerHTML = '<option value="">-- בחר עיר (אופציונלי) --</option>';
             const filteredCities = citiesData.filter(city => city.countryId === selectedCountryId);
             
             if (filteredCities.length === 0) {
                 citySelect.innerHTML = '<option value="">אין ערים במדינה זו</option>';
             } else {
                 filteredCities.forEach(city => {
                     const option = document.createElement("option");
                     option.value = city.unicId;
                     option.textContent = city.cityNameHe;
                     citySelect.appendChild(option);
                 });
             }
         }
     });
     
     // עדכן את שמות המדינה והעיר בשדות הנסתרים
     const countryNameField = document.getElementById("countryNameHe");
     const cityNameField = document.getElementById("cityNameHe");
     
     countrySelect.addEventListener("change", function() {
         const selectedOption = this.options[this.selectedIndex];
         countryNameField.value = selectedOption.text !== "-- בחר מדינה (אופציונלי) --" ? selectedOption.text : "";
     });
     
     citySelect.addEventListener("change", function() {
         const selectedOption = this.options[this.selectedIndex];
         cityNameField.value = (selectedOption.text !== "-- בחר עיר (אופציונלי) --" && 
                                selectedOption.text !== "אין ערים במדינה זו") ? selectedOption.text : "";
     });
 });
</script>