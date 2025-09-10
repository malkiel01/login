<?php
// forms/purchase-form.php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// טען את הקונפיג
require_once $_SERVER['DOCUMENT_ROOT'] . '/dashboard/dashboards/cemeteries/config.php';

$parentId = $_GET['parent_id'] ?? null;
$itemId = $_GET['item_id'] ?? null;

try {
   // קבל חיבור למסד נתונים
   $conn = getDBConnection();
   
   // טען לקוחות פנויים בלבד
   $customersQuery = "SELECT id, first_name, last_name, id_number 
                      FROM customers 
                      WHERE customer_status = 1 AND is_active = 1 
                      ORDER BY last_name, first_name";
   $customersStmt = $conn->prepare($customersQuery);
   $customersStmt->execute();
   $customers = $customersStmt->fetchAll(PDO::FETCH_ASSOC);
   
   // טען בתי עלמין עם קברים פנויים
   $cemeteriesQuery = "
       SELECT DISTINCT c.id, c.name 
       FROM cemeteries c
       INNER JOIN blocks b ON b.cemetery_id = c.id
       INNER JOIN plots p ON p.block_id = b.id
       INNER JOIN rows r ON r.plot_id = p.id
       INNER JOIN area_graves ag ON ag.row_id = r.id
       INNER JOIN graves g ON g.area_grave_id = ag.id
       WHERE g.grave_status = 1 AND g.is_active = 1
       ORDER BY c.name";
   $cemeteriesStmt = $conn->prepare($cemeteriesQuery);
   $cemeteriesStmt->execute();
   $cemeteries = $cemeteriesStmt->fetchAll(PDO::FETCH_ASSOC);
   
   // אם עורכים רכישה קיימת
   $purchase = null;
   if ($itemId) {
       $stmt = $conn->prepare("SELECT * FROM purchases WHERE id = ?");
       $stmt->execute([$itemId]);
       $purchase = $stmt->fetch(PDO::FETCH_ASSOC);
   }
   
} catch (Exception $e) {
   echo "<div style='color: red; padding: 20px;'>";
   echo "שגיאה: " . $e->getMessage();
   echo "</div>";
   exit;
}
?>

<div class="modal-overlay">
   <div class="modal-content">
       <div class="modal-header">
           <h3><?php echo $itemId ? 'עריכת רכישה' : 'רכישה חדשה'; ?></h3>
           <button type="button" class="btn-close" onclick="FormHandler.closeForm()">×</button>
       </div>
       
       <form id="genericForm" onsubmit="return FormHandler.submitForm(event, 'purchase')">
           <div class="modal-body">
               <?php if ($itemId): ?>
                   <input type="hidden" name="id" value="<?php echo $itemId; ?>">
               <?php endif; ?>
               
               <!-- בחירת לקוח -->
               <fieldset class="form-section">
                   <legend>פרטי הרוכש</legend>
                   <div class="form-row">
                       <div class="form-group">
                           <label>לקוח <span class="required">*</span></label>
                           <select name="customer_id" id="customerSelect" required class="form-control">
                               <option value="">-- בחר לקוח --</option>
                               <?php foreach ($customers as $customer): ?>
                                   <option value="<?php echo $customer['id']; ?>" 
                                       <?php echo ($purchase && $purchase['customer_id'] == $customer['id']) ? 'selected' : ''; ?>>
                                       <?php echo $customer['last_name'] . ' ' . $customer['first_name']; ?>
                                       <?php if ($customer['id_number']): ?>
                                           (<?php echo $customer['id_number']; ?>)
                                       <?php endif; ?>
                                   </option>
                               <?php endforeach; ?>
                           </select>
                       </div>
                       
                       <div class="form-group">
                           <label>סטטוס רוכש</label>
                           <select name="buyer_status" class="form-control">
                               <option value="1" <?php echo ($purchase && $purchase['buyer_status'] == 1) ? 'selected' : ''; ?>>רוכש לעצמו</option>
                               <option value="2" <?php echo ($purchase && $purchase['buyer_status'] == 2) ? 'selected' : ''; ?>>רוכש לאחר</option>
                           </select>
                       </div>
                   </div>
               </fieldset>
               
               <!-- בחירת קבר -->
               <fieldset class="form-section">
                   <legend>מיקום הקבר</legend>
                   <div class="form-row">
                       <div class="form-group">
                           <label>בית עלמין</label>
                           <select id="cemeterySelect" class="form-control" onchange="loadBlocks(this.value)">
                               <option value="">-- בחר בית עלמין --</option>
                               <?php foreach ($cemeteries as $cemetery): ?>
                                   <option value="<?php echo $cemetery['id']; ?>">
                                       <?php echo $cemetery['name']; ?>
                                   </option>
                               <?php endforeach; ?>
                           </select>
                       </div>
                       
                       <div class="form-group">
                           <label>גוש</label>
                           <select id="blockSelect" class="form-control" onchange="loadPlots(this.value)" disabled>
                               <option value="">-- בחר גוש --</option>
                           </select>
                       </div>
                       
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
               </fieldset>
               
               <!-- תשלומים -->
               <fieldset class="form-section">
                   <legend>פרטי תשלום</legend>
                   <div class="form-row">
                       <div class="form-group">
                           <label>מחיר כולל</label>
                           <input type="number" name="price" step="0.01" class="form-control" 
                                  value="<?php echo $purchase['price'] ?? ''; ?>">
                       </div>
                       
                       <div class="form-group">
                           <label>מספר תשלומים</label>
                           <input type="number" name="num_payments" min="1" class="form-control" 
                                  value="<?php echo $purchase['num_payments'] ?? 1; ?>">
                       </div>
                       
                       <div class="form-group">
                           <label>סטטוס רכישה</label>
                           <select name="purchase_status" class="form-control">
                               <option value="1" <?php echo (!$purchase || $purchase['purchase_status'] == 1) ? 'selected' : ''; ?>>טיוטה</option>
                               <option value="2" <?php echo ($purchase && $purchase['purchase_status'] == 2) ? 'selected' : ''; ?>>אושר</option>
                               <option value="3" <?php echo ($purchase && $purchase['purchase_status'] == 3) ? 'selected' : ''; ?>>שולם</option>
                               <option value="4" <?php echo ($purchase && $purchase['purchase_status'] == 4) ? 'selected' : ''; ?>>בוטל</option>
                           </select>
                       </div>
                       
                       <div class="form-group">
                           <label>תאריך סיום תשלומים</label>
                           <input type="date" name="payment_end_date" class="form-control" 
                                  value="<?php echo $purchase['payment_end_date'] ?? ''; ?>">
                       </div>
                   </div>
                   
                   <div class="form-row">
                       <div class="form-group" style="grid-column: 1 / -1;">
                           <button type="button" class="btn btn-secondary" onclick="openPaymentsModal()">
                               ניהול תשלומים מפורט
                           </button>
                       </div>
                   </div>
               </fieldset>
               
               <!-- הערות -->
               <fieldset class="form-section">
                   <legend>הערות</legend>
                   <div class="form-group">
                       <textarea name="comments" class="form-control" rows="3"><?php echo $purchase['comments'] ?? ''; ?></textarea>
                   </div>
               </fieldset>
           </div>
           
           <div class="modal-footer">
               <button type="button" class="btn btn-secondary" onclick="FormHandler.closeForm()">ביטול</button>
               <button type="submit" class="btn btn-primary">שמור</button>
           </div>
       </form>
   </div>
</div>

<script>
// הגדר את ה-API_BASE אם לא מוגדר
if (typeof API_BASE === 'undefined') {
   window.API_BASE = '/dashboard/dashboards/cemeteries/api/';
}

// טעינת גושים לפי בית עלמין
async function loadBlocks(cemeteryId) {
   if (!cemeteryId) {
       resetSelects(['blockSelect', 'plotSelect', 'rowSelect', 'areaGraveSelect', 'graveSelect']);
       return;
   }
   
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

// טעינת חלקות לפי גוש
async function loadPlots(blockId) {
   if (!blockId) {
       resetSelects(['plotSelect', 'rowSelect', 'areaGraveSelect', 'graveSelect']);
       return;
   }
   
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

// טעינת שורות לפי חלקה
async function loadRows(plotId) {
   if (!plotId) {
       resetSelects(['rowSelect', 'areaGraveSelect', 'graveSelect']);
       return;
   }
   
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

// טעינת אחוזות קבר לפי שורה
async function loadAreaGraves(rowId) {
   if (!rowId) {
       resetSelects(['areaGraveSelect', 'graveSelect']);
       return;
   }
   
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

// טעינת קברים לפי אחוזת קבר
async function loadGraves(areaGraveId) {
   if (!areaGraveId) {
       resetSelects(['graveSelect']);
       return;
   }
   
   try {
       const response = await fetch(`${API_BASE}cemetery-hierarchy.php?action=list&type=grave&parent_id=${areaGraveId}`);
       const data = await response.json();
       
       if (data.success && data.data) {
           const graveSelect = document.getElementById('graveSelect');
           graveSelect.disabled = false;
           graveSelect.innerHTML = '<option value="">-- בחר קבר --</option>';
           
           data.data.forEach(grave => {
               // הצג רק קברים פנויים (סטטוס 1)
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

// מילוי select
function populateSelect(selectId, items, valueField, textField) {
   const select = document.getElementById(selectId);
   if (!select) return;
   
   select.disabled = false;
   select.innerHTML = '<option value="">-- בחר --</option>';
   
   if (items && items.length > 0) {
       items.forEach(item => {
           const option = document.createElement('option');
           option.value = item[valueField];
           option.textContent = item[textField];
           select.appendChild(option);
       });
   }
}

// איפוס selects
function resetSelects(selectIds) {
   selectIds.forEach(id => {
       const select = document.getElementById(id);
       if (select) {
           select.innerHTML = '<option value="">-- בחר --</option>';
           select.disabled = true;
       }
   });
}

// פתיחת מודל תשלומים
function openPaymentsModal() {
   alert('מודל תשלומים בפיתוח');
}

// אם עורכים רכישה קיימת, טען את הנתונים
<?php if ($purchase && $purchase['grave_id']): ?>
window.addEventListener('DOMContentLoaded', async function() {
   // כאן צריך לטעון את כל ה-selects בהתאם לקבר שנבחר
   // TODO: implement loading existing grave selection
});
<?php endif; ?>
</script>