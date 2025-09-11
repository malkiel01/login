<?php
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

$formBuilder = new FormBuilder('payment', $itemId, null);

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

echo $formBuilder->renderModal();
?>