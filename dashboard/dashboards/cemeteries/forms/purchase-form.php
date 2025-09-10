<?php
// forms/purchase-form.php
require_once __DIR__ . '/FormBuilder.php';
require_once __DIR__ . '/forms-config.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/dashboard/dashboards/cemeteries/config.php';

$itemId = $_GET['item_id'] ?? null;
$parentId = $_GET['parent_id'] ?? null;

try {
    $conn = getDBConnection();
    
    // טען רק לקוחות עם סטטוס 1 (פנוי)
    $customersStmt = $conn->prepare("
        SELECT id, CONCAT(last_name, ' ', first_name) as full_name, id_number 
        FROM customers 
        WHERE customer_status = 1 
        AND is_active = 1 
        ORDER BY last_name, first_name
    ");
    $customersStmt->execute();
    $customers = [];
    $customers[''] = '-- בחר לקוח --';
    while ($row = $customersStmt->fetch(PDO::FETCH_ASSOC)) {
        $label = $row['full_name'];
        if ($row['id_number']) {
            $label .= ' (' . $row['id_number'] . ')';
        }
        $customers[$row['id']] = $label;
    }
    
    // בדוק אם יש לקוחות פנויים
    if (count($customers) <= 1) { // רק האופציה הריקה
        echo "<div class='alert alert-warning'>אין לקוחות פנויים במערכת. יש להוסיף לקוח חדש תחילה.</div>";
        echo "<button class='btn btn-primary' onclick='FormHandler.closeForm(); openAddCustomer();'>הוסף לקוח חדש</button>";
        exit;
    }
    
    // אם עורכים רכישה קיימת
    $purchase = null;
    if ($itemId) {
        $stmt = $conn->prepare("SELECT * FROM purchases WHERE id = ?");
        $stmt->execute([$itemId]);
        $purchase = $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
} catch (Exception $e) {
    echo "<div class='alert alert-danger'>שגיאה: " . $e->getMessage() . "</div>";
    exit;
}

// צור את הטופס עם FormBuilder
$formBuilder = new FormBuilder('purchase', $itemId, $parentId);

// הוסף את השדות
$formBuilder->addField('customer_id', 'לקוח', 'select', [
    'required' => true,
    'options' => $customers,
    'value' => $purchase['customer_id'] ?? ''
]);

$formBuilder->addField('grave_id', 'קבר', 'number', [
    'required' => true,
    'value' => $purchase['grave_id'] ?? '',
    'placeholder' => 'יש לבחור קבר',
    'readonly' => true
]);

$formBuilder->addField('buyer_status', 'סטטוס רוכש', 'select', [
    'options' => [
        '1' => 'רוכש לעצמו',
        '2' => 'רוכש לאחר'
    ],
    'value' => $purchase['buyer_status'] ?? 1
]);

$formBuilder->addField('purchase_status', 'סטטוס רכישה', 'select', [
    'options' => [
        '1' => 'טיוטה',
        '2' => 'אושר',
        '3' => 'שולם',
        '4' => 'בוטל'
    ],
    'value' => $purchase['purchase_status'] ?? 1
]);

$formBuilder->addField('price', 'מחיר', 'number', [
    'step' => '0.01',
    'value' => $purchase['price'] ?? '',
    'placeholder' => '0.00'
]);

$formBuilder->addField('num_payments', 'מספר תשלומים', 'number', [
    'min' => 1,
    'value' => $purchase['num_payments'] ?? 1
]);

$formBuilder->addField('payment_end_date', 'תאריך סיום תשלומים', 'date', [
    'value' => $purchase['payment_end_date'] ?? ''
]);

$formBuilder->addField('comments', 'הערות', 'textarea', [
    'rows' => 3,
    'value' => $purchase['comments'] ?? ''
]);

// הצג את הטופס
echo $formBuilder->renderModal();
?>

<script>
// הוסף כפתור לבחירת קבר
document.addEventListener('DOMContentLoaded', function() {
    const graveInput = document.querySelector('input[name="grave_id"]');
    if (graveInput) {
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'btn btn-info btn-sm';
        button.textContent = 'בחר קבר';
        button.style.marginTop = '5px';
        button.onclick = function() {
            alert('בחירת קבר - בפיתוח');
            // TODO: פתח מודל לבחירת קבר
        };
        graveInput.parentElement.appendChild(button);
    }
});
</script>