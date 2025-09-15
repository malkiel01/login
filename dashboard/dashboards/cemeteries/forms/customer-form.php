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

    // HTML מותאם אישית לבחירת כתובת עם data attribute
    $addressSelectorHTML = '
    <fieldset class="form-section" 
            id="address-fieldset"
            style="border: 1px solid #e2e8f0; border-radius: 8px; padding: 15px; margin-bottom: 20px;"
            data-cities=\'' . htmlspecialchars($citiesJson, ENT_QUOTES, 'UTF-8') . '\'>
        <legend style="padding: 0 10px; font-weight: bold;">כתובת</legend>
        <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 15px;">
            <div class="form-group">
                <label>מדינה</label>
                <select id="countrySelect" name="countryId" class="form-control">
                    <option value="">-- בחר מדינה --</option>';

    foreach ($countries as $unicId => $name) {
        $selected = ($customer && $customer['countryId'] == $unicId) ? 'selected' : '';
        $addressSelectorHTML .= '<option value="' . $unicId . '" ' . $selected . '>' . 
                                htmlspecialchars($name) . '</option>';
    }

    $addressSelectorHTML .= '
                </select>
            </div>
            <div class="form-group">
                <label>עיר</label>
                <select id="citySelect" name="cityId" class="form-control">
                    <option value="">-- בחר קודם מדינה --</option>';

    // אם יש מדינה נבחרת (בעריכה), טען את הערים שלה
    if ($customer && $customer['countryId']) {
        foreach ($allCities as $city) {
            if ($city['countryId'] == $customer['countryId']) {
                $selected = ($customer['cityId'] == $city['unicId']) ? 'selected' : '';
                $addressSelectorHTML .= '<option value="' . $city['unicId'] . '" ' . $selected . '>' . 
                                    htmlspecialchars($city['cityNameHe']) . '</option>';
            }
        }
    }

    $addressSelectorHTML .= '
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

    // אם זה עריכה, הוסף את ה-unicId כשדה מוסתר
    if ($customer && $customer['unicId']) {
        $formBuilder->addField('unicId', '', 'hidden', [
            'value' => $customer['unicId']
        ]);
    }

    // הצג את הטופס
    echo $formBuilder->renderModal();
?>