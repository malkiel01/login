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
   while ($row = $customersStmt->fetch(PDO::FETCH_ASSOC)) {
       $label = $row['full_name'];
       if ($row['id_number']) {
           $label .= ' (' . $row['id_number'] . ')';
       }
       $customers[$row['id']] = $label;
   }
   
   // טען בתי עלמין עם קברים פנויים
   $cemeteriesStmt = $conn->prepare("
       SELECT DISTINCT c.id, c.name 
       FROM cemeteries c
       WHERE EXISTS (
           SELECT 1 FROM graves g
           INNER JOIN area_graves ag ON g.area_grave_id = ag.id
           INNER JOIN rows r ON ag.row_id = r.id
           INNER JOIN plots p ON r.plot_id = p.id
           INNER JOIN blocks b ON p.block_id = b.id
           WHERE b.cemetery_id = c.id 
           AND g.grave_status = 1 
           AND g.is_active = 1
       )
       ORDER BY c.name
   ");
   $cemeteriesStmt->execute();
   $cemeteries = [];
   while ($row = $cemeteriesStmt->fetch(PDO::FETCH_ASSOC)) {
       $cemeteries[$row['id']] = $row['name'];
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

$formBuilder->addField('buyer_status', 'סטטוס רוכש', 'select', [
   'options' => [
       1 => 'רוכש לעצמו',
       2 => 'רוכש לאחר'
   ],
   'value' => $purchase['buyer_status'] ?? 1
]);

// סקציה של בחירת קבר - כ-HTML מותאם אישית
$graveSelectorHTML = '
<fieldset class="form-section">
   <legend>מיקום הקבר</legend>
   <div class="form-row">
       <div class="form-group">
           <label>בית עלמין</label>
           <select id="cemeterySelect" class="form-control" onchange="loadBlocks(this.value)">
               <option value="">-- בחר בית עלמין --</option>';

foreach ($cemeteries as $id => $name) {
   $graveSelectorHTML .= '<option value="' . $id . '">' . htmlspecialchars($name) . '</option>';
}

$graveSelectorHTML .= '
           </select>
       </div>
       <div class="form-group">
           <label>גוש</label>
           <select id="blockSelect" class="form-control" onchange="loadPlots(this.value)" disabled>
               <option value="">-- בחר גוש --</option>
           </select>
       </div>
   </div>
   <div class="form-row">
       <div class="form-group">
           <label>חלקה</label>
           <select id="plotSelect" class="form-control" onchange="loadRows(this.value)" disabled>
               <option value="">-- בחר חלקה --</option>
           </select>
       </div>
       <div class="form-group">
           <label>שורה</label>
           <select id="rowSelect" class="form-control" onchange="loadAreaGraves(this.value)" disabled>
               <option value="">-- בחר שורה --</option>
           </select>
       </div>
   </div>
   <div class="form-row">
       <div class="form-group">
           <label>אחוזת קבר</label>
           <select id="areaGraveSelect" class="form-control" onchange="loadGraves(this.value)" disabled>
               <option value="">-- בחר אחוזת קבר --</option>
           </select>
       </div>
       <div class="form-group">
           <label>קבר <span class="required">*</span></label>
           <select name="grave_id" id="graveSelect" class="form-control" required disabled>
               <option value="">-- בחר קבר --</option>
           </select>
       </div>
   </div>
</fieldset>';

// הוסף את ה-HTML המותאם לטופס
$formBuilder->fields[] = ['type' => 'raw_html', 'html' => $graveSelectorHTML];

// המשך שדות רגילים
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
       1 => 'טיוטה',
       2 => 'אושר',
       3 => 'שולם',
       4 => 'בוטל'
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
// הגדר API_BASE
if (typeof API_BASE === 'undefined') {
   window.API_BASE = '/dashboard/dashboards/cemeteries/api/';
}

// פונקציות טעינת הנתונים בהיררכיה
async function loadBlocks(cemeteryId) {
   resetSelects(['blockSelect', 'plotSelect', 'rowSelect', 'areaGraveSelect', 'graveSelect']);
   if (!cemeteryId) return;
   
   try {
       const response = await fetch(`${API_BASE}cemetery-hierarchy.php?action=list&type=block&parent_id=${cemeteryId}`);
       const data = await response.json();
       if (data.success) {
           populateSelect('blockSelect', data.data, 'id', 'name');
       }
   } catch (error) {
       console.error('Error loading blocks:', error);
   }
}

async function loadPlots(blockId) {
   resetSelects(['plotSelect', 'rowSelect', 'areaGraveSelect', 'graveSelect']);
   if (!blockId) return;
   
   try {
       const response = await fetch(`${API_BASE}cemetery-hierarchy.php?action=list&type=plot&parent_id=${blockId}`);
       const data = await response.json();
       if (data.success) {
           populateSelect('plotSelect', data.data, 'id', 'name');
       }
   } catch (error) {
       console.error('Error loading plots:', error);
   }
}

async function loadRows(plotId) {
   resetSelects(['rowSelect', 'areaGraveSelect', 'graveSelect']);
   if (!plotId) return;
   
   try {
       const response = await fetch(`${API_BASE}cemetery-hierarchy.php?action=list&type=row&parent_id=${plotId}`);
       const data = await response.json();
       if (data.success) {
           populateSelect('rowSelect', data.data, 'id', 'name');
       }
   } catch (error) {
       console.error('Error loading rows:', error);
   }
}

async function loadAreaGraves(rowId) {
   resetSelects(['areaGraveSelect', 'graveSelect']);
   if (!rowId) return;
   
   try {
       const response = await fetch(`${API_BASE}cemetery-hierarchy.php?action=list&type=area_grave&parent_id=${rowId}`);
       const data = await response.json();
       if (data.success) {
           populateSelect('areaGraveSelect', data.data, 'id', 'name');
       }
   } catch (error) {
       console.error('Error loading area graves:', error);
   }
}

async function loadGraves(areaGraveId) {
   resetSelects(['graveSelect']);
   if (!areaGraveId) return;
   
   try {
       const response = await fetch(`${API_BASE}cemetery-hierarchy.php?action=list&type=grave&parent_id=${areaGraveId}`);
       const data = await response.json();
       if (data.success && data.data) {
           const graveSelect = document.getElementById('graveSelect');
           graveSelect.disabled = false;
           graveSelect.innerHTML = '<option value="">-- בחר קבר --</option>';
           
           data.data.forEach(grave => {
               if (grave.grave_status == 1) {
                   const option = document.createElement('option');
                   option.value = grave.id;
                   option.textContent = `קבר ${grave.grave_number}`;
                   graveSelect.appendChild(option);
               }
           });
       }
   } catch (error) {
       console.error('Error loading graves:', error);
   }
}

function populateSelect(selectId, items, valueField, textField) {
   const select = document.getElementById(selectId);
   if (!select) return;
   
   select.disabled = false;
   select.innerHTML = '<option value="">-- בחר --</option>';
   
   items.forEach(item => {
       const option = document.createElement('option');
       option.value = item[valueField];
       option.textContent = item[textField];
       select.appendChild(option);
   });
}

function resetSelects(selectIds) {
   selectIds.forEach(id => {
       const select = document.getElementById(id);
       if (select) {
           select.innerHTML = '<option value="">-- בחר --</option>';
           select.disabled = true;
       }
   });
}

<?php if ($purchase && $purchase['grave_id']): ?>
// טען את הבחירה הקיימת
window.addEventListener('DOMContentLoaded', async function() {
   // TODO: לטעון את כל הבחירות הקיימות
});
<?php endif; ?>
</script>