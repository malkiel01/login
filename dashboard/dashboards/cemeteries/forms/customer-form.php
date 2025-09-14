<?php
// forms/customer-form.php

error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: text/html; charset=utf-8');

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
    
    // טען את כל הערים
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
        $stmt = $conn->prepare("SELECT * FROM customers WHERE id = ? AND isActive = 1");
        $stmt->execute([$itemId]);
        $customer = $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
} catch (Exception $e) {
    die(json_encode(['error' => $e->getMessage()]));
}

// יצירת FormBuilder
$formBuilder = new FormBuilder('customer', $itemId, $parentId);

// סוג זיהוי
$formBuilder->addField('typeId', 'סוג זיהוי', 'select', [
    'options' => [
        1 => 'ת.ז.',
        2 => 'דרכון',
        3 => 'אלמוני',
        4 => 'תינוק'
    ],
    'value' => $customer['typeId'] ?? 1
]);

// פרטים אישיים
$formBuilder->addField('numId', 'מספר זיהוי', 'text', [
    'required' => true,
    'value' => $customer['numId'] ?? ''
]);

$formBuilder->addField('firstName', 'שם פרטי', 'text', [
    'required' => true,
    'value' => $customer['firstName'] ?? ''
]);

$formBuilder->addField('lastName', 'שם משפחה', 'text', [
    'required' => true,
    'value' => $customer['lastName'] ?? ''
]);

$formBuilder->addField('nom', 'כינוי', 'text', [
    'value' => $customer['nom'] ?? ''
]);

$formBuilder->addField('gender', 'מגדר', 'select', [
    'options' => [
        '' => '-- בחר --',
        1 => 'זכר',
        2 => 'נקבה'
    ],
    'value' => $customer['gender'] ?? ''
]);

$formBuilder->addField('dateBirth', 'תאריך לידה', 'date', [
    'value' => $customer['dateBirth'] ?? ''
]);

$formBuilder->addField('nameFather', 'שם אב', 'text', [
    'value' => $customer['nameFather'] ?? ''
]);

$formBuilder->addField('nameMother', 'שם אם', 'text', [
    'value' => $customer['nameMother'] ?? ''
]);

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

// מדינה
$formBuilder->addField('countryId', 'מדינה', 'select', [
    'options' => $countries,
    'value' => $customer['countryId'] ?? '',
    'attributes' => 'id="countrySelect"'
]);

// עיר
$formBuilder->addField('cityId', 'עיר', 'select', [
    'options' => [],
    'value' => $customer['cityId'] ?? '',
    'attributes' => 'id="citySelect"'
]);

// כתובת
$formBuilder->addField('address', 'כתובת מלאה', 'text', [
    'placeholder' => 'רחוב, מספר בית',
    'value' => $customer['address'] ?? ''
]);

// פרטי התקשרות
$formBuilder->addField('phone', 'טלפון', 'tel', [
    'value' => $customer['phone'] ?? ''
]);

$formBuilder->addField('phoneMobile', 'טלפון נייד', 'tel', [
    'value' => $customer['phoneMobile'] ?? ''
]);

// סטטוסים
$formBuilder->addField('statusCustomer', 'סטטוס לקוח', 'select', [
    'options' => [
        1 => 'פעיל',
        2 => 'רוכש',
        3 => 'נפטר'
    ],
    'value' => $customer['statusCustomer'] ?? 1
]);

$formBuilder->addField('resident', 'תושבות', 'select', [
    'options' => [
        1 => 'ירושלים והסביבה',
        2 => 'תושב חוץ',
        3 => 'תושב חו״ל'
    ],
    'value' => $customer['resident'] ?? 1
]);

$formBuilder->addField('association', 'שיוך', 'select', [
    'options' => [
        1 => 'ישראל',
        2 => 'כהן',
        3 => 'לוי'
    ],
    'value' => $customer['association'] ?? 1
]);

// בן/בת זוג
$formBuilder->addField('spouse', 'בן/בת זוג', 'text', [
    'value' => $customer['spouse'] ?? ''
]);

// הערות
$formBuilder->addField('comment', 'הערות', 'textarea', [
    'rows' => 3,
    'value' => $customer['comment'] ?? ''
]);

// הצג את הטופס
echo $formBuilder->renderModal();
?>

<script>
// שמור את הנתונים
window.citiesData = <?php echo json_encode($allCities); ?>;
window.currentCountryId = '<?php echo $customer['countryId'] ?? ''; ?>';
window.currentCityId = '<?php echo $customer['cityId'] ?? ''; ?>';

// הגדר פונקציה גלובלית
window.filterCities = function() {
    console.log('filterCities called');
    const countrySelect = document.getElementById('countrySelect');
    const citySelect = document.getElementById('citySelect');
    
    if (!countrySelect || !citySelect) {
        console.log('Elements not found');
        return;
    }
    
    const selectedCountry = countrySelect.value;
    console.log('Selected country:', selectedCountry);
    
    citySelect.innerHTML = '<option value="">-- בחר עיר --</option>';
    
    if (!selectedCountry) {
        citySelect.innerHTML = '<option value="">-- בחר קודם מדינה --</option>';
        return;
    }
    
    if (!window.citiesData) {
        console.log('Cities data not found');
        return;
    }
    
    const filteredCities = window.citiesData.filter(city => city.countryId === selectedCountry);
    console.log('Filtered cities:', filteredCities.length);
    
    if (filteredCities.length === 0) {
        citySelect.innerHTML = '<option value="">-- אין ערים למדינה זו --</option>';
        return;
    }
    
    filteredCities.forEach(city => {
        const option = document.createElement('option');
        option.value = city.unicId;
        option.textContent = city.cityNameHe;
        if (window.currentCityId && city.unicId === window.currentCityId) {
            option.selected = true;
        }
        citySelect.appendChild(option);
    });
};

// הוסף event listener
setTimeout(function() {
    const countrySelect = document.getElementById('countrySelect');
    if (countrySelect) {
        countrySelect.addEventListener('change', window.filterCities);
        
        // טען ערים אם יש מדינה נבחרת
        if (window.currentCountryId) {
            window.filterCities();
        }
    }
}, 500);
</script>