<?php
// forms/customer-form.php
require_once __DIR__ . '/FormBuilder.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/dashboard/dashboards/cemeteries/config.php';

$itemId = $_GET['item_id'] ?? $_GET['id'] ?? null;
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
    
    // טען את כל הערים (לשימוש ב-JavaScript)
    $citiesStmt = $conn->prepare("
        SELECT unicId, countryId, cityNameHe, cityNameEn 
        FROM cities 
        WHERE isActive = 1 
        ORDER BY cityNameHe
    ");
    $citiesStmt->execute();
    $allCities = $citiesStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // טען לקוח אם בעריכה
    $customer = null;
    if ($itemId) {
        $stmt = $conn->prepare("SELECT * FROM customers WHERE id = ? AND is_active = 1");
        $stmt->execute([$itemId]);
        $customer = $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
} catch (Exception $e) {
    die("שגיאה: " . $e->getMessage());
}

// יצירת FormBuilder
$formBuilder = new FormBuilder('customer', $itemId, $parentId);

// פרטים אישיים
$formBuilder->addField('first_name', 'שם פרטי', 'text', [
    'required' => true,
    'value' => $customer['first_name'] ?? ''
]);

$formBuilder->addField('last_name', 'שם משפחה', 'text', [
    'required' => true,
    'value' => $customer['last_name'] ?? ''
]);

$formBuilder->addField('id_number', 'תעודת זהות', 'text', [
    'value' => $customer['id_number'] ?? ''
]);

$formBuilder->addField('gender', 'מגדר', 'select', [
    'options' => [
        '' => '-- בחר --',
        1 => 'זכר',
        2 => 'נקבה'
    ],
    'value' => $customer['gender'] ?? ''
]);

$formBuilder->addField('birth_date', 'תאריך לידה', 'date', [
    'value' => $customer['birth_date'] ?? ''
]);

// HTML מותאם אישית לבחירת כתובת
$addressSelectorHTML = '
<fieldset class="form-section" style="border: 1px solid #e2e8f0; border-radius: 8px; padding: 15px; margin-bottom: 20px;">
    <legend style="padding: 0 10px; font-weight: bold;">כתובת</legend>
    <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 15px;">
        <div class="form-group">
            <label>מדינה</label>
            <select id="countrySelect" name="country" class="form-control" onchange="filterCities()">
                <option value="">-- בחר מדינה --</option>';

foreach ($countries as $unicId => $name) {
    $selected = ($customer && $customer['country'] == $unicId) ? 'selected' : '';
    $addressSelectorHTML .= '<option value="' . $unicId . '" ' . $selected . '>' . 
                            htmlspecialchars($name) . '</option>';
}

$addressSelectorHTML .= '
            </select>
        </div>
        <div class="form-group">
            <label>עיר</label>
            <select id="citySelect" name="city" class="form-control">
                <option value="">-- בחר קודם מדינה --</option>
            </select>
        </div>
        <div class="form-group" style="grid-column: span 2;">
            <label>כתובת מלאה</label>
            <input type="text" name="address" class="form-control" 
                   value="' . ($customer['address'] ?? '') . '" 
                   placeholder="רחוב, מספר בית">
        </div>
    </div>
</fieldset>';

// הוסף את ה-HTML המותאם אישית
$formBuilder->addCustomHTML($addressSelectorHTML);

// פרטי התקשרות
$formBuilder->addField('phone', 'טלפון', 'tel', [
    'value' => $customer['phone'] ?? ''
]);

$formBuilder->addField('mobile_phone', 'טלפון נייד', 'tel', [
    'value' => $customer['mobile_phone'] ?? ''
]);

$formBuilder->addField('email', 'אימייל', 'email', [
    'value' => $customer['email'] ?? ''
]);

// סטטוסים
$formBuilder->addField('customer_status', 'סטטוס לקוח', 'select', [
    'options' => [
        1 => 'פעיל',
        2 => 'רכש',
        3 => 'נפטר'
    ],
    'value' => $customer['customer_status'] ?? 1
]);

$formBuilder->addField('type_id', 'סוג לקוח', 'select', [
    'options' => [
        1 => 'רגיל',
        2 => 'VIP'
    ],
    'value' => $customer['type_id'] ?? 1
]);

// הערות
$formBuilder->addField('comments', 'הערות', 'textarea', [
    'rows' => 3,
    'value' => $customer['comments'] ?? ''
]);

// הצג את הטופס
echo $formBuilder->renderModal();
?>

<script>
// העבר את כל הערים ל-JavaScript
window.allCities = <?php echo json_encode($allCities); ?>;

// המדינה והעיר הנוכחיות (בעריכה)
window.currentCountry = '<?php echo $customer['country'] ?? ''; ?>';
window.currentCity = '<?php echo $customer['city'] ?? ''; ?>';

// אתחול
(function initializeForm() {
    // אם יש מדינה נבחרת, טען את הערים שלה
    if (window.currentCountry) {
        filterCities();
    }
})();

// פונקציה לסינון ערים לפי מדינה
window.filterCities = function() {
    const countrySelect = document.getElementById('countrySelect');
    const citySelect = document.getElementById('citySelect');
    const selectedCountry = countrySelect.value;
    
    // נקה את רשימת הערים
    citySelect.innerHTML = '<option value="">-- בחר עיר --</option>';
    
    if (!selectedCountry) {
        citySelect.innerHTML = '<option value="">-- בחר קודם מדינה --</option>';
        return;
    }
    
    // סנן ערים לפי המדינה הנבחרת
    const filteredCities = window.allCities.filter(city => city.countryId === selectedCountry);
    
    if (filteredCities.length === 0) {
        citySelect.innerHTML = '<option value="">-- אין ערים למדינה זו --</option>';
        return;
    }
    
    // הוסף את הערים לסלקט
    filteredCities.forEach(city => {
        const option = document.createElement('option');
        option.value = city.unicId;
        option.textContent = city.cityNameHe;
        
        // אם זו העיר הנוכחית (בעריכה), בחר אותה
        if (window.currentCity && city.unicId === window.currentCity) {
            option.selected = true;
        }
        
        citySelect.appendChild(option);
    });
}
</script>