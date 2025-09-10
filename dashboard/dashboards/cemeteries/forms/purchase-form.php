<?php
// forms/purchase-form.php
require_once __DIR__ . '/FormBuilder.php';
require_once __DIR__ . '/forms-config.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/dashboard/dashboards/cemeteries/config.php';

$itemId = $_GET['item_id'] ?? null;
$parentId = $_GET['parent_id'] ?? null;

try {
    $conn = getDBConnection();
    
    // טען לקוחות פנויים
    $customersStmt = $conn->prepare("
        SELECT id, CONCAT(last_name, ' ', first_name) as full_name, id_number 
        FROM customers 
        WHERE customer_status = 1 AND is_active = 1 
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

$formBuilder->addField('grave_id', 'קבר', 'select', [
    'required' => true,
    'options' => ['0' => '-- יש לבחור קבר דרך המערכת --'],
    'value' => $purchase['grave_id'] ?? '',
    'class' => 'form-control grave-selector'
]);

$formBuilder->addField('buyer_status', 'סטטוס רוכש', 'select', [
    'options' => [
        '' => '-- בחר סטטוס --',
        '1' => 'רוכש לעצמו',
        '2' => 'רוכש לאחר'
    ],
    'value' => $purchase['buyer_status'] ?? ''
]);

$formBuilder->addField('price', 'מחיר כולל', 'number', [
    'step' => '0.01',
    'value' => $purchase['price'] ?? ''
]);

$formBuilder->addField('num_payments', 'מספר תשלומים', 'number', [
    'min' => 1,
    'value' => $purchase['num_payments'] ?? 1
]);

$formBuilder->addField('purchase_status', 'סטטוס רכישה', 'select', [
    'options' => [
        '' => '-- בחר סטטוס --',
        '1' => 'טיוטה',
        '2' => 'אושר',
        '3' => 'שולם',
        '4' => 'בוטל'
    ],
    'value' => $purchase['purchase_status'] ?? 1
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
// אחרי שהטופס נטען, הוסף כפתור לבחירת קבר
document.addEventListener('DOMContentLoaded', function() {
    const graveSelect = document.querySelector('select[name="grave_id"]');
    if (graveSelect) {
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'btn btn-secondary';
        button.textContent = 'בחר קבר מהמערכת';
        button.onclick = openGraveSelector;
        button.style.marginTop = '10px';
        graveSelect.parentElement.appendChild(button);
    }
});

function openGraveSelector() {
    // פתח חלון לבחירת קבר
    const modal = document.createElement('div');
    modal.className = 'modal-overlay';
    modal.style.zIndex = '10001';
    modal.innerHTML = `
        <div class="modal-content" style="max-width: 600px;">
            <div class="modal-header">
                <h3>בחירת קבר</h3>
                <button type="button" class="btn-close" onclick="this.closest('.modal-overlay').remove()">×</button>
            </div>
            <div class="modal-body">
                <p>בחר קבר דרך ההיררכיה:</p>
                <button class="btn btn-primary" onclick="alert('בחירת קבר בפיתוח')">
                    פתח בוחר קברים
                </button>
            </div>
        </div>
    `;
    document.body.appendChild(modal);
}
</script>