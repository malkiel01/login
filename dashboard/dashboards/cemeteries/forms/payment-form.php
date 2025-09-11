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