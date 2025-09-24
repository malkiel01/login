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

    // $formBuilder->addField('resident', 'תושבות', 'select', [
    //     'options' => [
    //         1 => 'ירושלים והסביבה',
    //         2 => 'תושב חוץ',
    //         3 => 'תושב חו״ל'
    //     ],
    //     'value' => $customer['resident'] ?? 1
    // ]);

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

    // הוסף שדה נסתר לשמירת הערך
    $formBuilder->addField('resident_hidden', '', 'hidden', [
        'value' => $customer['resident'] ?? 3,
        'name' => 'resident'
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
<script>
    // פונקציה לחישוב תושבות בצד הלקוח (לתצוגה בלבד)
    function calculateResidencyClient() {
        alert('calculateResidencyClient התחיל');
        
        const typeId = document.querySelector('[name="typeId"]')?.value;
        const countryId = document.querySelector('[name="countryId"]')?.value;
        const cityId = document.querySelector('[name="cityId"]')?.value;
        
        alert(`נתונים שנשלפו:\ntypeId: ${typeId}\ncountryId: ${countryId}\ncityId: ${cityId}`);
        
        let residency = 3; // ברירת מחדל - תושב חו"ל
        
        // אם סוג הזיהוי הוא דרכון (2) - תמיד תושב חו"ל
        if (typeId == 2) {
            alert('סוג זיהוי = דרכון, מגדיר תושב חו"ל (3)');
            residency = 3;
            updateResidencyDisplay(residency);
        } else if (countryId) {
            alert(`יש מדינה: ${countryId}, שולח לשרת לחישוב...`);
            
            // כאן צריך לבדוק מול הגדרות התושבות
            // נעשה קריאת AJAX לשרת לחישוב
            fetch('/dashboard/dashboards/cemeteries/api/customers-api.php?action=calculate_residency', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    typeId: typeId,
                    countryId: countryId,
                    cityId: cityId
                })
            })
            .then(response => {
                alert('קיבלנו תגובה מהשרת');
                return response.json();
            })
            .then(data => {
                alert(`תוצאה מהשרת:\nsuccess: ${data.success}\nresidency: ${data.residency}\ntext: ${data.residency_text}`);
                
                if (data.success && data.residency) {
                    updateResidencyDisplay(data.residency);
                }
            })
            .catch(error => {
                alert(`שגיאה בקריאה לשרת: ${error.message}`);
                console.error('Error calculating residency:', error);
            });
        } else {
            alert('אין מדינה, מגדיר תושב חו"ל (3)');
            updateResidencyDisplay(residency);
        }
    }

    // עדכון התצוגה של שדה התושבות
    function updateResidencyDisplay(value) {
        alert(`updateResidencyDisplay: מעדכן ערך ל-${value}`);
        
        const selectElement = document.querySelector('[name="resident"]');
        const hiddenElement = document.querySelector('[name="resident_hidden"]');
        
        if (selectElement) {
            selectElement.value = value;
            alert(`שדה SELECT עודכן ל-${value}`);
            
            // עדכון צבע רקע לפי הערך
            switch(parseInt(value)) {
                case 1:
                    selectElement.style.backgroundColor = '#e8f5e9'; // ירוק בהיר
                    alert('צבע רקע: ירוק (תושב העיר)');
                    break;
                case 2:
                    selectElement.style.backgroundColor = '#e3f2fd'; // כחול בהיר
                    alert('צבע רקע: כחול (תושב הארץ)');
                    break;
                case 3:
                    selectElement.style.backgroundColor = '#fff3e0'; // כתום בהיר
                    alert('צבע רקע: כתום (תושב חו"ל)');
                    break;
            }
        } else {
            alert('לא נמצא שדה SELECT של resident!');
        }
        
        if (hiddenElement) {
            hiddenElement.value = value;
            alert(`שדה נסתר עודכן ל-${value}`);
        } else {
            alert('לא נמצא שדה נסתר של resident!');
        }
    }

    // הוסף מאזינים לשינויים בשדות הרלוונטיים
    document.addEventListener('DOMContentLoaded', function() {
        alert('DOMContentLoaded - מתחיל אתחול');
        
        // רק אם זה טופס הוספה (לא עריכה)
        const isEdit = <?php echo $itemId ? 'true' : 'false'; ?>;
        alert(`מצב טופס: ${isEdit ? 'עריכה' : 'הוספה חדשה'}`);
        
        if (!isEdit) {
            // הוסף מאזינים לשינויים
            const typeSelect = document.querySelector('[name="typeId"]');
            const countrySelect = document.querySelector('[name="countryId"]');
            const citySelect = document.querySelector('[name="cityId"]');
            
            if (typeSelect) {
                alert('מוסיף מאזין לשדה סוג זיהוי');
                typeSelect.addEventListener('change', function() {
                    alert('שדה סוג זיהוי השתנה');
                    calculateResidencyClient();
                });
            } else {
                alert('לא נמצא שדה typeId!');
            }
            
            if (countrySelect) {
                alert('מוסיף מאזין לשדה מדינה');
                countrySelect.addEventListener('change', function() {
                    alert('שדה מדינה השתנה');
                    calculateResidencyClient();
                });
            } else {
                alert('לא נמצא שדה countryId!');
            }
            
            if (citySelect) {
                alert('מוסיף מאזין לשדה עיר');
                citySelect.addEventListener('change', function() {
                    alert('שדה עיר השתנה');
                    calculateResidencyClient();
                });
            } else {
                alert('לא נמצא שדה cityId!');
            }
            
            // חשב תושבות ראשונית
            alert('מבצע חישוב תושבות ראשוני');
            calculateResidencyClient();
        } else {
            alert('מצב עריכה - לא מבצעים חישוב תושבות');
        }
    });
</script>