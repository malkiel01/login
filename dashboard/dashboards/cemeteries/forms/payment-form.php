<?php
// forms/payment-form.php
require_once __DIR__ . '/FormBuilder.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/dashboard/dashboards/cemeteries/config.php';

$itemId = $_GET['item_id'] ?? $_GET['id'] ?? null;

try {
    $conn = getDBConnection();
    
    $payment = null;
    if ($itemId) {
        $stmt = $conn->prepare("SELECT * FROM payments WHERE id = ? AND isActive = 1");
        $stmt->execute([$itemId]);
        $payment = $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
} catch (Exception $e) {
    die("שגיאה: " . $e->getMessage());
}


// טען את ההיררכיה למיקום
$cemeteries = [];
$blocks = [];
$plots = [];
$rows = [];

// טען בתי עלמין
$cemeteriesStmt = $conn->prepare("
    SELECT unicId, cemeteryNameHe 
    FROM cemeteries 
    WHERE isActive = 1 
    ORDER BY cemeteryNameHe
");
$cemeteriesStmt->execute();
$cemeteries = $cemeteriesStmt->fetchAll(PDO::FETCH_ASSOC);

// טען את כל הנתונים להיררכיה
$hierarchyData = [
    'blocks' => $conn->query("SELECT * FROM blocks WHERE isActive = 1")->fetchAll(PDO::FETCH_ASSOC),
    'plots' => $conn->query("SELECT * FROM plots WHERE isActive = 1")->fetchAll(PDO::FETCH_ASSOC),
    'rows' => $conn->query("SELECT * FROM rows WHERE isActive = 1")->fetchAll(PDO::FETCH_ASSOC)
];


// יצירת FormBuilder
$formBuilder = new FormBuilder('payment', $itemId, null);

// הוספת כל השדות
$formBuilder->addField('plotType', 'סוג חלקה', 'select', [
    'options' => [
        '' => '-- בחר --',
        1 => 'פטורה',
        2 => 'חריגה', 
        3 => 'סגורה'
    ],
    'value' => $payment['plotType'] ?? ''
]);

$formBuilder->addField('graveType', 'סוג קבר', 'select', [
    'options' => [
        '' => '-- בחר --',
        1 => 'שדה',
        2 => 'רוויה',
        3 => 'סנהדרין'
    ],
    'value' => $payment['graveType'] ?? ''
]);

$formBuilder->addField('resident', 'סוג תושב', 'select', [
    'options' => [
        '' => '-- בחר --',
        1 => 'ירושלים והסביבה',
        2 => 'תושב חוץ',
        3 => 'תושב חו״ל'
    ],
    'value' => $payment['resident'] ?? ''
]);

$formBuilder->addField('buyerStatus', 'סטטוס רוכש', 'select', [
    'options' => [
        '' => '-- בחר --',
        1 => 'בחיים',
        2 => 'לאחר פטירה',
        3 => 'בן זוג נפטר'
    ],
    'value' => $payment['buyerStatus'] ?? ''
]);

// הוסף HTML מותאם אישית למיקום
$locationHTML = '
<fieldset class="form-section" 
    id="payment-location-fieldset"
    data-hierarchy=\'' . htmlspecialchars(json_encode($hierarchyData), ENT_QUOTES, 'UTF-8') . '\'
    style="border: 1px solid #e2e8f0; border-radius: 8px; padding: 15px; margin: 15px 0;">
    <legend style="padding: 0 10px; font-weight: bold;">מיקום (אופציונלי)</legend>
    <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 15px;">
        <div class="form-group">
            <label>בית עלמין</label>
            <select name="cemeteryId" id="paymentCemeterySelect" class="form-control" onchange="filterPaymentLocation(\'cemetery\')">
                <option value="-1">-- כל בתי העלמין --</option>';

foreach ($cemeteries as $cemetery) {
    $selected = ($payment && $payment['cemeteryId'] == $cemetery['unicId']) ? 'selected' : '';
    $locationHTML .= '<option value="' . $cemetery['unicId'] . '" ' . $selected . '>' . 
                     htmlspecialchars($cemetery['cemeteryNameHe']) . '</option>';
}

$locationHTML .= '
            </select>
        </div>
        <div class="form-group">
            <label>גוש</label>
            <select name="blockId" id="paymentBlockSelect" class="form-control" onchange="filterPaymentLocation(\'block\')">
                <option value="-1">-- כל הגושים --</option>
            </select>
        </div>
        <div class="form-group">
            <label>חלקה</label>
            <select name="plotId" id="paymentPlotSelect" class="form-control" onchange="filterPaymentLocation(\'plot\')">
                <option value="-1">-- כל החלקות --</option>
            </select>
        </div>
        <div class="form-group">
            <label>שורה</label>
            <select name="lineId" id="paymentLineSelect" class="form-control" disabled>
                <option value="-1">-- בחר חלקה תחילה --</option>
            </select>
        </div>
    </div>
</fieldset>

<script>
    // נתוני ההיררכיה
    window.paymentHierarchy = ' . json_encode($hierarchyData) . ';

    // פונקציה לסינון המיקום
    window.filterPaymentLocation = function(level) {
        const cemeteryId = document.getElementById("paymentCemeterySelect").value;
        const blockId = document.getElementById("paymentBlockSelect").value;
        const plotId = document.getElementById("paymentPlotSelect").value;
        
        switch(level) {
            case "cemetery":
                // נקה ילדים
                populatePaymentBlocks(cemeteryId);
                populatePaymentPlots(cemeteryId, "-1");
                document.getElementById("paymentLineSelect").innerHTML = \'<option value="-1">-- בחר חלקה תחילה --</option>\';
                document.getElementById("paymentLineSelect").disabled = true;
                break;
                
            case "block":
                if (blockId !== "-1") {
                    populatePaymentPlots("-1", blockId);
                } else if (cemeteryId !== "-1") {
                    populatePaymentPlots(cemeteryId, "-1");
                } else {
                    populatePaymentPlots("-1", "-1");
                }
                document.getElementById("paymentLineSelect").innerHTML = \'<option value="-1">-- בחר חלקה תחילה --</option>\';
                document.getElementById("paymentLineSelect").disabled = true;
                break;
                
            case "plot":
                if (plotId !== "-1") {
                    document.getElementById("paymentLineSelect").disabled = false;
                    populatePaymentRows(plotId);
                } else {
                    document.getElementById("paymentLineSelect").innerHTML = \'<option value="-1">-- בחר חלקה תחילה --</option>\';
                    document.getElementById("paymentLineSelect").disabled = true;
                }
                break;
        }
    }

    // מילוי גושים
    window.populatePaymentBlocks = function(cemeteryId) {
        const select = document.getElementById("paymentBlockSelect");
        select.innerHTML = \'<option value="-1">-- כל הגושים --</option>\';
        
        if (cemeteryId === "-1") return;
        
        const blocks = window.paymentHierarchy.blocks.filter(b => b.cemeteryId === cemeteryId);
        blocks.forEach(block => {
            const option = document.createElement("option");
            option.value = block.unicId;
            option.textContent = block.blockNameHe;
            select.appendChild(option);
        });
    }

    // מילוי חלקות
    window.populatePaymentPlots = function(cemeteryId, blockId) {
        const select = document.getElementById("paymentPlotSelect");
        select.innerHTML = \'<option value="-1">-- כל החלקות --</option>\';
        
        let plots = window.paymentHierarchy.plots;
        
        if (blockId !== "-1") {
            plots = plots.filter(p => p.blockId === blockId);
        } else if (cemeteryId !== "-1") {
            const blockIds = window.paymentHierarchy.blocks
                .filter(b => b.cemeteryId === cemeteryId)
                .map(b => b.unicId);
            plots = plots.filter(p => blockIds.includes(p.blockId));
        }
        
        plots.forEach(plot => {
            const option = document.createElement("option");
            option.value = plot.unicId;
            option.textContent = plot.plotNameHe;
            select.appendChild(option);
        });
    }

    // מילוי שורות
    window.populatePaymentRows = function(plotId) {
        const select = document.getElementById("paymentLineSelect");
        select.innerHTML = \'<option value="-1">-- כל השורות --</option>\';
        
        const rows = window.paymentHierarchy.rows.filter(r => r.plotId === plotId);
        rows.forEach(row => {
            const option = document.createElement("option");
            option.value = row.unicId;
            option.textContent = row.lineNameHe || "שורה " + row.serialNumber;
            select.appendChild(option);
        });
    }

    // אם זה עריכה, טען את הערכים השמורים
    ' . ($payment ? '
    setTimeout(function() {
        const payment = ' . json_encode($payment) . ';
        if (payment.cemeteryId && payment.cemeteryId !== "-1") {
            document.getElementById("paymentCemeterySelect").value = payment.cemeteryId;
            filterPaymentLocation("cemetery");
            
            setTimeout(function() {
                if (payment.blockId && payment.blockId !== "-1") {
                    document.getElementById("paymentBlockSelect").value = payment.blockId;
                    filterPaymentLocation("block");
                }
                
                setTimeout(function() {
                    if (payment.plotId && payment.plotId !== "-1") {
                        document.getElementById("paymentPlotSelect").value = payment.plotId;
                        filterPaymentLocation("plot");
                    }
                    
                    setTimeout(function() {
                        if (payment.lineId && payment.lineId !== "-1") {
                            document.getElementById("paymentLineSelect").value = payment.lineId;
                        }
                    }, 100);
                }, 100);
            }, 100);
        }
    }, 100);
    ' : '') . '
</script>';

// הוסף את ה-HTML המותאם אישית לטופס
$formBuilder->addCustomHTML($locationHTML);


$formBuilder->addField('priceDefinition', 'הגדרת מחיר', 'select', [
    'options' => [
        '' => '-- בחר --',
        1 => 'עלות קבר',
        2 => 'שירותי לוויה',
        3 => 'שירותי קבורה',
        4 => 'אגרת מצבה',
        5 => 'בדיקת עומק',
        6 => 'פירוק מצבה',
        7 => 'הובלה מנתב״ג',
        8 => 'טהרה',
        9 => 'תכריכים',
        10 => 'החלפת שם'
    ],
    'value' => $payment['priceDefinition'] ?? ''
]);



$formBuilder->addField('price', 'מחיר', 'number', [
    'required' => true,
    'step' => '0.01',
    'min' => 0,
    'value' => $payment['price'] ?? ''
]);

$formBuilder->addField('startPayment', 'תאריך התחלת תשלום', 'date', [
    'value' => $payment['startPayment'] ?? ''
]);

// קבל את ה-HTML מה-FormBuilder
$formContent = $formBuilder->renderModal();

// בדוק אם ה-FormBuilder מחזיר HTML עם modal wrapper
if (strpos($formContent, 'class="modal') !== false) {
    // אם כן, פשוט הצג אותו
    echo $formContent;
} else {
    // אם לא, עטוף אותו במודל מלא
    ?>
    <div class="modal show" id="paymentFormModal" style="display: block; position: fixed; top: 0; left: 0; right: 0; bottom: 0; z-index: 1040;">
        <!-- רקע כהה -->
        <div class="modal-overlay" onclick="FormHandler.closeForm('payment')" style="
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1040;
        "></div>
        
        <!-- חלון המודל -->
        <div class="modal-dialog" style="
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            z-index: 1050;
            width: 90%;
            max-width: 600px;
        ">
            <div class="modal-content" style="
                background: white;
                border-radius: 8px;
                box-shadow: 0 5px 15px rgba(0,0,0,0.5);
                max-height: 90vh;
                overflow: hidden;
                display: flex;
                flex-direction: column;
            ">
                <!-- כותרת -->
                <div class="modal-header" style="
                    padding: 20px;
                    border-bottom: 1px solid #e5e7eb;
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                ">
                    <h2 style="margin: 0; font-size: 1.5rem;">
                        <?php echo $itemId ? 'עריכת תשלום' : 'הוספת תשלום חדש'; ?>
                    </h2>
                    <button type="button" class="close" onclick="FormHandler.closeForm('payment')" style="
                        background: none;
                        border: none;
                        font-size: 1.5rem;
                        cursor: pointer;
                        padding: 0;
                        width: 30px;
                        height: 30px;
                        display: flex;
                        align-items: center;
                        justify-content: center;
                    ">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                
                <!-- גוף הטופס -->
                <div class="modal-body" style="
                    padding: 20px;
                    overflow-y: auto;
                    flex: 1;
                ">
                    <?php echo $formContent; ?>
                </div>
            </div>
        </div>
    </div>
    <?php
}
?>