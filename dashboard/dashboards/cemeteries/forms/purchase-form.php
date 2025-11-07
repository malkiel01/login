<?php
    // forms/purchase-form.php

    error_reporting(E_ALL);
    ini_set('display_errors', 1);
    header('Content-Type: text/html; charset=utf-8');

    require_once __DIR__ . '/FormBuilder.php';
require_once __DIR__ . 
'/FormUtils.php';
    require_once dirname(__DIR__) . '/config.php';

    
// === קבלת פרמטרים אחידה ===
$itemId = $_GET['itemId'] ?? $_GET['id'] ?? null;
$parentId = $_GET['parentId'] ?? $_GET['parent_id'] ?? null;
$formType = basename(__FILE__, '.php'); // מזהה אוטומטי של סוג הטופס

    
    try {
        $conn = getDBConnection();

        // טען רכישה אם קיימת (העבר את זה לפני טעינת הלקוחות)
        $purchase = null;
        if ($itemId) {
            $stmt = $conn->prepare("SELECT * FROM purchases WHERE unicId = ? AND isActive = 1");
            $stmt->execute([$itemId]);
            $purchase = $stmt->fetch(PDO::FETCH_ASSOC);
        }

        // עכשיו טען את ההיררכיה
        $hierarchyData = [];
        $cemeteries = [];

        // בתי עלמין - כלול גם את בית העלמין של הקבר הנוכחי
        if ($purchase && $purchase['graveId']) {
            $cemeteriesStmt = $conn->prepare("
                SELECT c.unicId, c.cemeteryNameHe as name,
                (EXISTS (
                    SELECT 1 FROM graves g
                    INNER JOIN areaGraves ag ON g.areaGraveId = ag.unicId
                    INNER JOIN rows r ON ag.lineId = r.unicId
                    INNER JOIN plots p ON r.plotId = p.unicId
                    INNER JOIN blocks b ON p.blockId = b.unicId
                    WHERE b.cemeteryId = c.unicId
                    AND g.graveStatus = 1 
                    AND g.isActive = 1
                ) OR EXISTS (
                    SELECT 1 FROM graves g
                    INNER JOIN areaGraves ag ON g.areaGraveId = ag.unicId
                    INNER JOIN rows r ON ag.lineId = r.unicId
                    INNER JOIN plots p ON r.plotId = p.unicId
                    INNER JOIN blocks b ON p.blockId = b.unicId
                    WHERE b.cemeteryId = c.unicId
                    AND g.unicId = :currentGrave
                )) as has_available_graves
                FROM cemeteries c
                WHERE c.isActive = 1
                ORDER BY c.cemeteryNameHe
            ");
            $cemeteriesStmt->execute(['currentGrave' => $purchase['graveId']]);
        } else {
            // קוד רגיל לרכישה חדשה
            $cemeteriesStmt = $conn->prepare("
                SELECT c.unicId, c.cemeteryNameHe as name,
                EXISTS (
                    SELECT 1 FROM graves g
                    INNER JOIN areaGraves ag ON g.areaGraveId = ag.unicId
                    INNER JOIN rows r ON ag.lineId = r.unicId
                    INNER JOIN plots p ON r.plotId = p.unicId
                    INNER JOIN blocks b ON p.blockId = b.unicId
                    WHERE b.cemeteryId = c.unicId
                    AND g.graveStatus = 1 
                    AND g.isActive = 1
                ) as has_available_graves
                FROM cemeteries c
                WHERE c.isActive = 1
                ORDER BY c.cemeteryNameHe
            ");
            $cemeteriesStmt->execute();
        }
        
    } catch (Exception $e) {
        FormUtils::handleError($e);
    }

    // יצירת FormBuilder
    $formBuilder = new FormBuilder('purchase', $itemId, $parentId);

    // $customersSelectorHTML = '
    // <div class="form-group">
    //     <label>לקוח <span class="text-danger">*</span></label>
    //     <select name="clientId" id="clientId" class="form-control" required>
    //         <option value="">טוען לקוחות...</option>
    //     </select>
    // </div>';

    $customersSelectorHTML = '
    <style>
    @keyframes spin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }
    .loading-spinner {
        display: inline-block;
        width: 14px;
        height: 14px;
        border: 2px solid #f3f3f3;
        border-top: 2px solid #3498db;
        border-radius: 50%;
        animation: spin 0.8s linear infinite;
        margin-left: 8px;
        vertical-align: middle;
    }
    </style>
    <div class="form-group" style="position: relative;">
        <label>לקוח <span class="text-danger">*</span></label>
        <div style="position: relative;">
            <select name="clientId" id="clientId" class="form-control" required disabled style="opacity: 0.7;">
                <option value="">טוען לקוחות...</option>
            </select>
            <span class="loading-spinner" id="customerLoadingSpinner" style="position: absolute; left: 10px; top: 50%; transform: translateY(-50%);"></span>
        </div>
    </div>';

    $formBuilder->addCustomHTML($customersSelectorHTML);

    // הוספת שדה סטטוס רוכש
    $formBuilder->addField('buyer_status', 'סטטוס רוכש', 'select', [
        'options' => [
            1 => 'רוכש לעצמו',
            2 => 'רוכש לאחר'
        ],
        'value' => $purchase['buyer_status'] ?? 1
    ]);

    $graveSelectorHTML = '
    <fieldset class="form-section" 
        id="grave-selector-fieldset"
        data-load-from-api="true"
        data-purchase-grave-id="' . htmlspecialchars($purchase['graveId'] ?? '', ENT_QUOTES) . '"
        style="border: 1px solid #e2e8f0; border-radius: 8px; padding: 15px; margin-bottom: 20px;">
        <legend style="padding: 0 10px; font-weight: bold;">בחירת  קבר</legend>
        <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 15px;">
            <div class="form-group">
                <label>בית עלמין</label>
                <select id="cemeterySelect" class="form-control" onchange="filterHierarchy(\'cemetery\')">
                    <option value="">טוען בתי עלמין...</option>
                ';

    $graveSelectorHTML .= '
                </select>
            </div>
            <div class="form-group">
                <label>גוש</label>
                <select id="blockSelect" class="form-control" onchange="filterHierarchy(\'block\')">
                    <option value="">-- כל הגושים --</option>
                </select>
            </div>
            <div class="form-group">
                <label>חלקה</label>
                <select id="plotSelect" class="form-control" onchange="filterHierarchy(\'plot\')">
                    <option value="">-- כל החלקות --</option>
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
                <select name="graveId" id="graveSelect" class="form-control" required disabled onchange="onGraveSelected(this.value)">
                    <option value="">-- בחר אחוזת קבר תחילה --</option>
                </select>
            </div>
        </div>
    </fieldset>';

    // הוסף את ה-HTML המותאם אישית
    $formBuilder->addCustomHTML($graveSelectorHTML);

    // המשך השדות
    $formBuilder->addField('purchaseStatus', 'סטטוס רכישה', 'select', [
        'options' => [
            1 => 'פתוח',
            2 => 'שולם', 
            3 => 'סגור',
            4 => 'בוטל'
        ],
        'value' => $purchase['purchaseStatus'] ?? 1
    ]);

    // HTML מותאם אישית לניהול תשלומים חכם
    $paymentsHTML = '
    <fieldset class="form-section" style="border: 1px solid #e2e8f0; border-radius: 8px; padding: 15px; margin-bottom: 20px;">
        <legend style="padding: 0 10px; font-weight: bold;">תשלומים</legend>
        
        <!-- הצגת פרמטרים נבחרים -->
        <div id="selectedParameters" style="background: #f0f9ff; padding: 10px; border-radius: 5px; margin-bottom: 15px; display: none;">
            <div style="font-size: 12px; color: #666;">פרמטרים לחישוב:</div>
            <div id="parametersDisplay" style="margin-top: 5px;"></div>
        </div>
        
        <!-- סכום כולל -->
        <div style="margin-bottom: 15px;">
            <label>סכום כולל</label>
            <input type="number" name="price" id="total_price" 
                value="' . ($purchase['price'] ?? '0') . '" 
                step="0.01" readonly
                style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; background: #f8f9fa; font-size: 18px; font-weight: bold;">
        </div>
        
        <!-- כפתורי ניהול תשלומים -->
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
    
            <button type="button" onclick="openSmartPaymentsManager()" style="
                padding: 10px 20px;
                background: #17a2b8;
                color: white;
                border: none;
                border-radius: 4px;
                cursor: pointer;
            ">
                <span id="paymentsButtonText">חשב תשלומים אוטומטית</span>
            </button>
        </div>
        
        <!-- תצוגת פירוט תשלומים -->
        <div id="paymentsDisplay" style="
            background: #f8f9fa;
            padding: 10px;
            border-radius: 4px;
            min-height: 50px;
            margin-top: 15px;
        ">' . 
        ($purchase && $purchase['paymentsList'] ? 
            '<script>document.write(displayPaymentsSummary())</script>' : 
            '<p style="color: #999; text-align: center;">לחץ על אחד הכפתורים לניהול תשלומים</p>') . 
        '</div>
        
        <input type="hidden" name="paymentsList" id="paymentsList" 
            value=\'' . ($purchase['paymentsList'] ?? '[]') . '\'>
    </fieldset>';

    $formBuilder->addCustomHTML($paymentsHTML);

    $formBuilder->addField('numOfPayments', 'מספר תשלומים', 'number', [
        'min' => 1,
        'value' => $purchase['numOfPayments'] ?? 1
    ]);

    $formBuilder->addField('PaymentEndDate', 'תאריך סיום תשלומים', 'date', [
        'value' => $purchase['PaymentEndDate'] ?? ''
    ]);

    $formBuilder->addField('comment', 'הערות', 'textarea', [
        'rows' => 3,
        'value' => $purchase['comment'] ?? ''
    ]);

    // אם זה עריכה, הוסף את ה-unicId כשדה מוסתר
    if ($purchase && $purchase['unicId']) {
        $formBuilder->addField('unicId', '', 'hidden', [
            'value' => $purchase['unicId']
        ]);
    }

    // הצג את הטופס
    echo $formBuilder->renderModal();
?>