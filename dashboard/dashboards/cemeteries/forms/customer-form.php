<?php
// forms/customer-form.php - גרסה מתוקנת

error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: text/html; charset=utf-8');

require_once __DIR__ . '/FormBuilder.php';
require_once __DIR__ . 
'/FormUtils.php';
require_once dirname(__DIR__) . '/config.php';
require_once __DIR__ . '/SmartSelect.php';


// === קבלת פרמטרים אחידה ===
$itemId = $_GET['itemId'] ?? $_GET['id'] ?? null;
$parentId = $_GET['parentId'] ?? $_GET['parent_id'] ?? null;
$formType = basename(__FILE__, '.php'); // מזהה אוטומטי של סוג הטופס

try {
    $conn = getDBConnection();
    
    // 🆕 כבר לא טוענים מדינות וערים מה-PHP!
    // JavaScript יטען אותם דינמית מה-API
    
    // טען לקוח אם בעריכה
    $customer = null;
    if ($itemId) {
        $stmt = $conn->prepare("SELECT * FROM customers WHERE id = ? AND isActive = 1");
        $stmt->execute([$itemId]);
        $customer = $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
} catch (Exception $e) {
    FormUtils::handleError($e);
}

// הכן את ה-JSON של הערים
$citiesJson = json_encode($allCities);

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
    'value' => $customer['numId'] ?? '',
    'validations' => ['validateIsraeliId']
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

// === כתובת עם SmartSelect - בדיוק כמו שהיה! ===
$addressHTML = '
<fieldset class="form-section" 
        id="address-fieldset"
        style="border: 1px solid #e2e8f0; border-radius: 8px; padding: 15px; margin-bottom: 20px;"
        data-load-from-api="true"
        data-customer-country-id="' . htmlspecialchars($customer['countryId'] ?? '') . '"
        data-customer-city-id="' . htmlspecialchars($customer['cityId'] ?? '') . '">
    <legend style="padding: 0 10px; font-weight: bold;">כתובת</legend>
    <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 15px;">
';

// מדינה - SmartSelect ריק (יתמלא ב-JavaScript)
$smartCountry = new SmartSelect('countryId', 'מדינה', [], [
    'searchable' => true,
    'placeholder' => 'טוען מדינות...',
    'search_placeholder' => 'חפש מדינה...',
    'required' => true,
    'value' => $customer['countryId'] ?? ''
]);

$addressHTML .= '<div style="margin-bottom: 0;">' . $smartCountry->render() . '</div>';

// עיר - SmartSelect ריק
$smartCity = new SmartSelect('cityId', 'עיר', [], [
    'searchable' => true,
    'placeholder' => 'בחר קודם מדינה...',
    'search_placeholder' => 'חפש עיר...',
    'disabled' => true,
    'value' => $customer['cityId'] ?? ''
]);

$addressHTML .= '<div style="margin-bottom: 0;">' . $smartCity->render() . '</div>';

// כתובת מלאה - תופסת 2 עמודות
$addressHTML .= '
        <div class="form-group" style="grid-column: span 2; margin-bottom: 0;">
            <label>כתובת מלאה</label>
            <input type="text" name="address" class="form-control" 
                value="' . htmlspecialchars($customer['address'] ?? '') . '" 
                placeholder="רחוב, מספר בית">
        </div>
    </div>
</fieldset>';

$formBuilder->addCustomHTML($addressHTML);


// --------------------

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

// שדה תושבות - קריאה בלבד
$formBuilder->addField('resident', 'תושבות', 'select', [
    'options' => [
        1 => 'ירושלים והסביבה',
        2 => 'תושב חוץ',
        3 => 'תושב חו״ל'
    ],
    'value' => $customer['resident'] ?? 3,
    'readonly' => true,
    'disabled' => true,
    'help_text' => 'מחושב אוטומטית על פי הגדרות התושבות',
    'attributes' => [
        'style' => 'background-color: #f5f5f5; cursor: not-allowed;'
    ]
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

// הוסף את ה-unicId אם בעריכה
if ($customer && $customer['unicId']) {
    $formBuilder->addField('unicId', '', 'hidden', [
        'value' => $customer['unicId']
    ]);
}

// הצג את הטופס
echo $formBuilder->renderModal();
?>