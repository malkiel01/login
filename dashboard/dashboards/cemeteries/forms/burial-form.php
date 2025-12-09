<?php
    // forms/burial-form.php

    error_reporting(E_ALL);
    ini_set('display_errors', 1);
    header('Content-Type: text/html; charset=utf-8');

    require_once __DIR__ . '/FormBuilder.php';
    require_once __DIR__ . '/FormUtils.php';
    require_once dirname(__DIR__) . '/config.php';
    require_once __DIR__ . '/SmartSelect.php';

    // === קבלת פרמטרים אחידה ===
    $itemId = $_GET['itemId'] ?? $_GET['id'] ?? null;
    $parentId = $_GET['parentId'] ?? $_GET['parent_id'] ?? null;
    $formType = basename(__FILE__, '.php');

    try {
        $conn = getDBConnection();

        // טען קבורה אם קיימת
        $burial = null;
        if ($itemId) {
            $stmt = $conn->prepare("SELECT * FROM burials WHERE unicId = ? AND isActive = 1");
            $stmt->execute([$itemId]);
            $burial = $stmt->fetch(PDO::FETCH_ASSOC);
        }
        
    } catch (Exception $e) {
        FormUtils::handleError($e);
    }

    // יצירת FormBuilder
    $formBuilder = new FormBuilder('burial', $itemId, $parentId);

    // // ✅ לקוחות - טעינה מ-API (כמו ב-purchase)
    // $customersSelectorHTML = '
    // <div class="form-group">
    //     <label>נפטר/ת <span class="text-danger">*</span></label>
    //     <select name="clientId" id="clientId" class="form-control" required>
    //         <option value="">טוען לקוחות...</option>
    //     </select>
    // </div>';

    // $formBuilder->addCustomHTML($customersSelectorHTML);


    // ✅ SmartSelect ללקוחות - ריק (יתמלא ב-JavaScript)
    $smartCustomer = new SmartSelect('clientId', 'נפטר/ת', [], [
        'searchable' => true,
        'placeholder' => 'טוען לקוחות...',
        'search_placeholder' => 'חפש לקוח...',
        'required' => true,
        'value' => $burial['clientId'] ?? ''
    ]);

    $formBuilder->addCustomHTML('<div style="margin-bottom: 15px;">' . $smartCustomer->render() . '</div>');


    
    // ✅ בחירת קבר - זהה לחלוטין ל-purchase
    $graveSelectorHTML = '
    <fieldset class="form-section" 
    id="grave-selector-fieldset"
    data-load-from-api="true"
        <!-- data-burial-grave-id="' . htmlspecialchars($burial['graveId'] ?? '', ENT_QUOTES) . '" -->
        data-purchase-grave-id="' . htmlspecialchars($parentId ?? $purchase['graveId'] ?? '', ENT_QUOTES) . '"
        style="border: 1px solid #e2e8f0; border-radius: 8px; padding: 15px; margin-bottom: 20px;">
        <legend style="padding: 0 10px; font-weight: bold;">בחירת קבר</legend>
        <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 15px;">
            <div class="form-group">
                <label>בית עלמין</label>
                <select id="cemeterySelect" class="form-control" onchange="filterHierarchy(\'cemetery\')" disabled>
                    <option value="">טוען בתי עלמין...</option>
                </select>
            </div>
            <div class="form-group">
                <label>גוש</label>
                <select id="blockSelect" class="form-control" onchange="filterHierarchy(\'block\')" disabled>
                    <option value="">-- בחר בית עלמין תחילה --</option>
                </select>
            </div>
            <div class="form-group">
                <label>חלקה</label>
                <select id="plotSelect" class="form-control" onchange="filterHierarchy(\'plot\')" disabled>
                    <option value="">-- בחר בית עלמין תחילה --</option>
                </select>
            </div>
            <div class="form-group">
                <label>שורה</label>
                <select id="rowSelect" class="form-control" onchange="filterHierarchy(\'row\')" disabled>
                    <option value="">-- בחר חלקה תחילה --</option>
                </select>
            </div>
            <div class="form-group">
                <label>אחוזת קבר</label>
                <select id="areaGraveSelect" class="form-control" onchange="filterHierarchy(\'areaGrave\')" disabled>
                    <option value="">-- בחר שורה תחילה --</option>
                </select>
            </div>
            <div class="form-group">
                <label>קבר <span class="text-danger">*</span></label>
                <select name="graveId" id="graveSelect" class="form-control" required disabled>
                    <option value="">-- בחר אחוזת קבר תחילה --</option>
                </select>
            </div>
        </div>
    </fieldset>';

    $formBuilder->addCustomHTML($graveSelectorHTML);

    // שדה רכישה (אופציונלי) - נשאיר את זה כמו שהיה
    $formBuilder->addField('purchaseId', 'רכישה קשורה', 'select', [
        'options' => ['' => '-- טוען רכישות... --'],
        'value' => $burial['purchaseId'] ?? ''
    ]);

    // מספר תיק קבורה
    if ($itemId) {
        $formBuilder->addField('serialBurialId', 'מספר תיק קבורה', 'text', [
            'readonly' => true,
            'value' => $burial['serialBurialId'] ?? ''
        ]);
    }

    // פרטי פטירה
    $formBuilder->addField('dateDeath', 'תאריך פטירה', 'date', [
        'required' => true,
        'value' => $burial['dateDeath'] ?? ''
    ]);

    $formBuilder->addField('timeDeath', 'שעת פטירה', 'time', [
        'value' => $burial['timeDeath'] ?? ''
    ]);

    $formBuilder->addField('placeDeath', 'מקום הפטירה', 'text', [
        'required' => true,
        'placeholder' => 'עיר/בית חולים',
        'value' => $burial['placeDeath'] ?? ''
    ]);

    $formBuilder->addField('deathAbroad', 'פטירה בחו"ל', 'select', [
        'options' => [
            'לא' => 'לא',
            'כן' => 'כן'
        ],
        'value' => $burial['deathAbroad'] ?? 'לא'
    ]);

    // פרטי קבורה
    $formBuilder->addField('dateBurial', 'תאריך קבורה', 'date', [
        'required' => true,
        'value' => $burial['dateBurial'] ?? ''
    ]);

    $formBuilder->addField('timeBurial', 'שעת קבורה', 'time', [
        'required' => true,
        'value' => $burial['timeBurial'] ?? ''
    ]);

    $formBuilder->addField('nationalInsuranceBurial', 'קבורת ביטוח לאומי', 'select', [
        'options' => [
            'לא' => 'לא',
            'כן' => 'כן'
        ],
        'value' => $burial['nationalInsuranceBurial'] ?? 'לא'
    ]);

    $formBuilder->addField('buriaLicense', 'רשיון קבורה', 'text', [
        'placeholder' => 'מספר רשיון',
        'value' => $burial['buriaLicense'] ?? ''
    ]);

    // איש קשר
    $formBuilder->addField('kinship', 'קרבת איש קשר', 'text', [
        'placeholder' => 'בן/בת/אח/הורה וכו\'',
        'value' => $burial['kinship'] ?? ''
    ]);

    // תאריכים נוספים
    $formBuilder->addField('dateOpening_tld', 'תאריך פתיחת תיק', 'date', [
        'value' => $burial['dateOpening_tld'] ?? date('Y-m-d')
    ]);

    $formBuilder->addField('reportingBL', 'תאריך דיווח לביטוח לאומי', 'date', [
        'value' => $burial['reportingBL'] ?? ''
    ]);

    // הערות
    $formBuilder->addField('comment', 'הערות', 'textarea', [
        'rows' => 3,
        'placeholder' => 'הערות נוספות...',
        'value' => $burial['comment'] ?? ''
    ]);

    // שדה מוסתר לעריכה
    if ($burial && $burial['unicId']) {
        $formBuilder->addField('unicId', '', 'hidden', [
            'value' => $burial['unicId']
        ]);
    }

    echo $formBuilder->renderModal();
?>

