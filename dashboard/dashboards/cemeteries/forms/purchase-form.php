<?php
    // forms/purchase-form.php

    error_reporting(E_ALL);
    ini_set('display_errors', 1);
    header('Content-Type: text/html; charset=utf-8');

    require_once __DIR__ . '/FormBuilder.php';
    require_once $_SERVER['DOCUMENT_ROOT'] . '/dashboard/dashboards/cemeteries/config.php';

    $itemId = $_GET['item_id'] ?? null;
    $parentId = $_GET['parent_id'] ?? null;

    try {
        $conn = getDBConnection();
        
        // טען לקוחות פנויים
        $customersStmt = $conn->prepare("
            SELECT unicId, CONCAT(lastName, ' ', firstName) as full_name, numId 
            FROM customers 
            WHERE statusCustomer = 1 AND isActive = 1 
            ORDER BY lastName, firstName
        ");
        $customersStmt->execute();
        $customers = [];
        while ($row = $customersStmt->fetch(PDO::FETCH_ASSOC)) {
            $label = $row['full_name'];
            if ($row['numId']) {
                $label .= ' (' . $row['numId'] . ')';
            }
            $customers[$row['unicId']] = $label;
        }
        
        // טען בתי עלמין
        $cemeteriesStmt = $conn->prepare("
            SELECT c.id, c.name,
            EXISTS (
                SELECT 1 FROM graves g
                INNER JOIN area_graves ag ON g.area_grave_id = ag.id
                INNER JOIN rows r ON ag.row_id = r.id
                INNER JOIN plots p ON r.plot_id = p.id
                INNER JOIN blocks b ON p.block_id = b.id
                WHERE b.cemetery_id = c.id 
                AND g.grave_status = 1 
                AND g.isActive = 1
            ) as has_available_graves
            FROM cemeteries c
            WHERE c.isActive = 1
            ORDER BY c.name
        ");
        $cemeteriesStmt->execute();
        $cemeteries = $cemeteriesStmt->fetchAll(PDO::FETCH_ASSOC);
        
        // // הכן את כל הנתונים להיררכיה
        // $hierarchyData = [];
        
        // // טען את כל הגושים
        // $blocksStmt = $conn->prepare("
        //     SELECT b.*, c.id as cemetery_id 
        //     FROM blocks b 
        //     INNER JOIN cemeteries c ON b.cemetery_id = c.id 
        //     WHERE b.isActive = 1
        //     ORDER BY b.name
        // ");
        // $blocksStmt->execute();
        // $hierarchyData['blocks'] = $blocksStmt->fetchAll(PDO::FETCH_ASSOC);
        
        // // טען את כל החלקות
        // $plotsStmt = $conn->prepare("
        //     SELECT p.*, b.cemetery_id 
        //     FROM plots p 
        //     INNER JOIN blocks b ON p.block_id = b.id 
        //     WHERE p.isActive = 1
        //     ORDER BY p.name
        // ");
        // $plotsStmt->execute();
        // $hierarchyData['plots'] = $plotsStmt->fetchAll(PDO::FETCH_ASSOC);
        
        // // טען את כל השורות
        // $rowsStmt = $conn->prepare("
        //     SELECT r.* 
        //     FROM rows r 
        //     WHERE r.isActive = 1
        //     ORDER BY r.name
        // ");
        // $rowsStmt->execute();
        // $hierarchyData['rows'] = $rowsStmt->fetchAll(PDO::FETCH_ASSOC);
        
        // // טען את כל אחוזות הקבר
        // $areaGravesStmt = $conn->prepare("
        //     SELECT ag.* 
        //     FROM area_graves ag 
        //     WHERE ag.isActive = 1
        //     ORDER BY ag.name
        // ");
        // $areaGravesStmt->execute();
        // $hierarchyData['areaGraves'] = $areaGravesStmt->fetchAll(PDO::FETCH_ASSOC);
        
        // // טען את כל הקברים הפנויים
        // $gravesStmt = $conn->prepare("
        //     SELECT g.* 
        //     FROM graves g 
        //     WHERE g.grave_status = 1 AND g.isActive = 1
        //     ORDER BY g.grave_number
        // ");
        // $gravesStmt->execute();
        // $hierarchyData['graves'] = $gravesStmt->fetchAll(PDO::FETCH_ASSOC);
        
        // טען רכישה אם קיימת
        $purchase = null;
        if ($itemId) {
            $stmt = $conn->prepare("SELECT * FROM purchases WHERE unicId = ? AND isActive = 1");
            $stmt->execute([$itemId]);
            $purchase = $stmt->fetch(PDO::FETCH_ASSOC);
        }
        
    } catch (Exception $e) {
        die(json_encode(['error' => $e->getMessage()]));
    }

    // יצירת FormBuilder
    $formBuilder = new FormBuilder('purchase', $itemId, $parentId);

    // // הוספת שדה לקוח
    // $formBuilder->addField('clientId', 'לקוח', 'select', [
    //     'required' => true,
    //     'options' => $customers,
    //     'value' => $purchase['clientId'] ?? ''
    // ]);

    // // הוספת שדה סטטוס רוכש
    // $formBuilder->addField('buyer_status', 'סטטוס רוכש', 'select', [
    //     'options' => [
    //         1 => 'רוכש לעצמו',
    //         2 => 'רוכש לאחר'
    //     ],
    //     'value' => $purchase['buyer_status'] ?? 1
    // ]);

    // // HTML מותאם אישית לבחירת קבר
    // $graveSelectorHTML = '
    // <fieldset class="form-section" style="border: 1px solid #e2e8f0; border-radius: 8px; padding: 15px; margin-bottom: 20px;">
    //     <legend style="padding: 0 10px; font-weight: bold;">בחירת קבר</legend>
    //     <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 15px;">
    //         <div class="form-group">
    //             <label>בית עלמין</label>
    //             <select id="cemeterySelect" class="form-control" onchange="filterHierarchy(\'cemetery\')">
    //                 <option value="">-- כל בתי העלמין --</option>';

    // foreach ($cemeteries as $cemetery) {
    //     $disabled = !$cemetery['has_available_graves'] ? 'disabled style="color: #999;"' : '';
    //     $graveSelectorHTML .= '<option value="' . $cemetery['id'] . '" ' . $disabled . '>' . 
    //                         htmlspecialchars($cemetery['name']) . 
    //                         (!$cemetery['has_available_graves'] ? ' (אין קברים פנויים)' : '') . 
    //                         '</option>';
    // }

    // $graveSelectorHTML .= '
    //             </select>
    //         </div>
    //         <div class="form-group">
    //             <label>גוש</label>
    //             <select id="blockSelect" class="form-control" onchange="filterHierarchy(\'block\')">
    //                 <option value="">-- כל הגושים --</option>
    //             </select>
    //         </div>
    //         <div class="form-group">
    //             <label>חלקה</label>
    //             <select id="plotSelect" class="form-control" onchange="filterHierarchy(\'plot\')">
    //                 <option value="">-- כל החלקות --</option>
    //             </select>
    //         </div>
    //         <div class="form-group">
    //             <label>שורה</label>
    //             <select id="rowSelect" class="form-control" onchange="filterHierarchy(\'row\')" disabled>
    //                 <option value="">-- בחר חלקה תחילה --</option>
    //             </select>
    //         </div>
    //         <div class="form-group">
    //             <label>אחוזת קבר</label>
    //             <select id="areaGraveSelect" class="form-control" onchange="filterHierarchy(\'area_grave\')" disabled>
    //                 <option value="">-- בחר שורה תחילה --</option>
    //             </select>
    //         </div>
    //         <div class="form-group">
    //             <label>קבר <span class="text-danger">*</span></label>
    //             <select name="graveId" id="graveSelect" class="form-control" required disabled onchange="onGraveSelected(this.value)">
    //                 <option value="">-- בחר אחוזת קבר תחילה --</option>
    //             </select>
    //         </div>
    //     </div>
    // </fieldset>';

    // // הוסף את ה-HTML המותאם אישית
    // $formBuilder->addCustomHTML($graveSelectorHTML);

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

    // // HTML מותאם אישית לניהול תשלומים חכם
    // $paymentsHTML = '
    // <fieldset class="form-section" style="border: 1px solid #e2e8f0; border-radius: 8px; padding: 15px; margin-bottom: 20px;">
    //     <legend style="padding: 0 10px; font-weight: bold;">תשלומים</legend>
        
    //     <!-- הצגת פרמטרים נבחרים -->
    //     <div id="selectedParameters" style="background: #f0f9ff; padding: 10px; border-radius: 5px; margin-bottom: 15px; display: none;">
    //         <div style="font-size: 12px; color: #666;">פרמטרים לחישוב:</div>
    //         <div id="parametersDisplay" style="margin-top: 5px;"></div>
    //     </div>
        
    //     <!-- סכום כולל -->
    //     <div style="margin-bottom: 15px;">
    //         <label>סכום כולל</label>
    //         <input type="number" name="price" id="total_price" 
    //             value="' . ($purchase['price'] ?? '0') . '" 
    //             step="0.01" readonly
    //             style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; background: #f8f9fa; font-size: 18px; font-weight: bold;">
    //     </div>
        
    //     <!-- כפתורי ניהול תשלומים -->
    //     <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
    //         <button type="button" onclick="openPaymentsManager()" style="
    //             padding: 10px 20px;
    //             background: #6c757d;
    //             color: white;
    //             border: none;
    //             border-radius: 4px;
    //             cursor: pointer;
    //         ">
    //             ניהול תשלומים ידני
    //         </button>
            
    //         <button type="button" onclick="openSmartPaymentsManager()" style="
    //             padding: 10px 20px;
    //             background: #17a2b8;
    //             color: white;
    //             border: none;
    //             border-radius: 4px;
    //             cursor: pointer;
    //         ">
    //             <span id="paymentsButtonText">חשב תשלומים אוטומטית</span>
    //         </button>
    //     </div>
        
    //     <!-- תצוגת פירוט תשלומים -->
    //     <div id="paymentsDisplay" style="
    //         background: #f8f9fa;
    //         padding: 10px;
    //         border-radius: 4px;
    //         min-height: 50px;
    //         margin-top: 15px;
    //     ">' . 
    //     ($purchase && $purchase['payments_data'] ? 
    //         '<script>document.write(displayPaymentsSummary())</script>' : 
    //         '<p style="color: #999; text-align: center;">לחץ על אחד הכפתורים לניהול תשלומים</p>') . 
    //     '</div>
        
    //     <input type="hidden" name="payments_data" id="payments_data" 
    //         value=\'' . ($purchase['payments_data'] ?? '[]') . '\'>
    // </fieldset>';

    // $formBuilder->addCustomHTML($paymentsHTML);

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