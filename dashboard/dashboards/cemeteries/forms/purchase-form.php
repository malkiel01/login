<?php
// forms/purchase-form.php
require_once __DIR__ . '/FormBuilder.php';
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
           AND g.is_active = 1
       ) as has_available_graves
       FROM cemeteries c
       WHERE c.is_active = 1
       ORDER BY c.name
   ");
   $cemeteriesStmt->execute();
   $cemeteries = $cemeteriesStmt->fetchAll(PDO::FETCH_ASSOC);
   
   // טען רכישה אם קיימת
   $purchase = null;
   if ($itemId) {
       $stmt = $conn->prepare("SELECT * FROM purchases WHERE id = ?");
       $stmt->execute([$itemId]);
       $purchase = $stmt->fetch(PDO::FETCH_ASSOC);
   }
   
} catch (Exception $e) {
   die("שגיאה: " . $e->getMessage());
}

// יצירת FormBuilder
$formBuilder = new FormBuilder('purchase', $itemId, $parentId);

// הוספת שדה לקוח
$formBuilder->addField('customer_id', 'לקוח', 'select', [
   'required' => true,
   'options' => $customers,
   'value' => $purchase['customer_id'] ?? ''
]);

// הוספת שדה סטטוס רוכש
$formBuilder->addField('buyer_status', 'סטטוס רוכש', 'select', [
   'options' => [
       1 => 'רוכש לעצמו',
       2 => 'רוכש לאחר'
   ],
   'value' => $purchase['buyer_status'] ?? 1
]);

// HTML מותאם אישית לבחירת קבר
$graveSelectorHTML = '
<fieldset class="form-section" style="border: 1px solid #e2e8f0; border-radius: 8px; padding: 15px; margin-bottom: 20px;">
   <legend style="padding: 0 10px; font-weight: bold;">בחירת קבר</legend>
   <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 15px;">
       <div class="form-group">
           <label>בית עלמין</label>
           <select id="cemeterySelect" class="form-control">
               <option value="">-- כל בתי העלמין --</option>';

foreach ($cemeteries as $cemetery) {
   $disabled = !$cemetery['has_available_graves'] ? 'disabled style="color: #999;"' : '';
   $graveSelectorHTML .= '<option value="' . $cemetery['id'] . '" ' . $disabled . '>' . 
                         htmlspecialchars($cemetery['name']) . 
                         (!$cemetery['has_available_graves'] ? ' (אין קברים פנויים)' : '') . 
                         '</option>';
}

$graveSelectorHTML .= '
           </select>
       </div>
       <div class="form-group">
           <label>גוש</label>
           <select id="blockSelect" class="form-control">
               <option value="">-- כל הגושים --</option>
           </select>
       </div>
       <div class="form-group">
           <label>חלקה</label>
           <select id="plotSelect" class="form-control">
               <option value="">-- כל החלקות --</option>
           </select>
       </div>
       <div class="form-group">
           <label>שורה</label>
           <select id="rowSelect" class="form-control" disabled>
               <option value="">-- בחר חלקה תחילה --</option>
           </select>
       </div>
       <div class="form-group">
           <label>אחוזת קבר</label>
           <select id="areaGraveSelect" class="form-control" disabled>
               <option value="">-- בחר שורה תחילה --</option>
           </select>
       </div>
       <div class="form-group">
           <label>קבר <span class="text-danger">*</span></label>
           <select name="grave_id" id="graveSelect" class="form-control" required disabled>
               <option value="">-- בחר אחוזת קבר תחילה --</option>
           </select>
       </div>
   </div>
</fieldset>';

// הוסף את ה-HTML המותאם אישית
$formBuilder->addCustomHTML($graveSelectorHTML);

// המשך השדות
$formBuilder->addField('purchase_status', 'סטטוס רכישה', 'select', [
   'options' => [
       1 => 'טיוטה',
       2 => 'אושר',
       3 => 'שולם',
       4 => 'בוטל'
   ],
   'value' => $purchase['purchase_status'] ?? 1
]);

$formBuilder->addField('price', 'מחיר', 'number', [
   'step' => '0.01',
   'value' => $purchase['price'] ?? ''
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
// הגדר API_BASE אם לא קיים
if (typeof window.API_BASE === 'undefined') {
   window.API_BASE = '/dashboard/dashboards/cemeteries/api/';
}

// פונקציה מרכזית לעדכון בוררי הקבר
window.updateGraveSelectors = async function(changedLevel) {
   const cemetery = document.getElementById('cemeterySelect').value;
   const block = document.getElementById('blockSelect').value;
   const plot = document.getElementById('plotSelect').value;
   
   switch(changedLevel) {
       case 'cemetery':
           await loadBlocks(cemetery);
           await loadPlots(cemetery, null);
           clearSelectors(['row', 'area_grave', 'grave']);
           break;
           
       case 'block':
           await loadPlots(cemetery, block);
           clearSelectors(['row', 'area_grave', 'grave']);
           break;
           
       case 'plot':
           if (plot) {
               await loadRows(plot);
               document.getElementById('rowSelect').disabled = false;
           } else {
               clearSelectors(['row', 'area_grave', 'grave']);
               document.getElementById('rowSelect').disabled = true;
           }
           break;
           
       case 'row':
           const row = document.getElementById('rowSelect').value;
           if (row) {
               await loadAreaGraves(row);
               document.getElementById('areaGraveSelect').disabled = false;
           } else {
               clearSelectors(['area_grave', 'grave']);
               document.getElementById('areaGraveSelect').disabled = true;
           }
           break;
           
       case 'area_grave':
           const areaGrave = document.getElementById('areaGraveSelect').value;
           if (areaGrave) {
               await loadGraves(areaGrave);
               document.getElementById('graveSelect').disabled = false;
           } else {
               clearSelectors(['grave']);
               document.getElementById('graveSelect').disabled = true;
           }
           break;
   }
}

// טעינת גושים
window.loadBlocks = async function(cemeteryId) {
   console.log('loadBlocks called with cemeteryId:', cemeteryId);
   
   const blockSelect = document.getElementById('blockSelect');
   if (!blockSelect) {
       console.error('blockSelect element not found!');
       return;
   }
   
   let url = `${window.API_BASE}cemetery-hierarchy.php?action=list&type=block`;
   if (cemeteryId) url += `&parent_id=${cemeteryId}`;
   
   console.log('Fetching blocks from:', url);
   
   try {
       const response = await fetch(url);
       console.log('Response status:', response.status);
       
       const data = await response.json();
       console.log('Blocks data:', data);
       
       blockSelect.innerHTML = '<option value="">-- כל הגושים --</option>';
       
       if (data.success && data.data) {
           console.log('Number of blocks:', data.data.length);
           
           data.data.forEach(block => {
               console.log('Adding block:', block);
               const option = document.createElement('option');
               option.value = block.id;
               option.textContent = block.name;
               blockSelect.appendChild(option);
           });
       } else {
           console.warn('No blocks data or success=false');
       }
   } catch (error) {
       console.error('Error loading blocks:', error);
   }
}

// טעינת חלקות
window.loadPlots = async function(cemeteryId, blockId) {
   const plotSelect = document.getElementById('plotSelect');
   if (!plotSelect) return;
   
   let url = `${window.API_BASE}cemetery-hierarchy.php?action=list&type=plot`;
   
   if (blockId) {
       url += `&parent_id=${blockId}`;
   } else if (cemeteryId) {
       // טען את כל החלקות של כל הגושים בבית העלמין
       const blocksResponse = await fetch(`${window.API_BASE}cemetery-hierarchy.php?action=list&type=block&parent_id=${cemeteryId}`);
       const blocksData = await blocksResponse.json();
       
       plotSelect.innerHTML = '<option value="">-- כל החלקות --</option>';
       
       if (blocksData.success && blocksData.data) {
           for (const block of blocksData.data) {
               const plotsResponse = await fetch(`${window.API_BASE}cemetery-hierarchy.php?action=list&type=plot&parent_id=${block.id}`);
               const plotsData = await plotsResponse.json();
               
               if (plotsData.success && plotsData.data) {
                   plotsData.data.forEach(plot => {
                       const option = document.createElement('option');
                       option.value = plot.id;
                       option.textContent = `${plot.name} (גוש ${block.name})`;
                       plotSelect.appendChild(option);
                   });
               }
           }
       }
       return;
   }
   
   try {
       const response = await fetch(url);
       const data = await response.json();
       
       plotSelect.innerHTML = '<option value="">-- כל החלקות --</option>';
       if (data.success && data.data) {
           data.data.forEach(plot => {
               const option = document.createElement('option');
               option.value = plot.id;
               option.textContent = plot.name;
               plotSelect.appendChild(option);
           });
       }
   } catch (error) {
       console.error('Error loading plots:', error);
   }
}

// טעינת שורות
window.loadRows = async function(plotId) {
   const rowSelect = document.getElementById('rowSelect');
   if (!rowSelect) return;
   
   try {
       const response = await fetch(`${window.API_BASE}cemetery-hierarchy.php?action=list&type=row&parent_id=${plotId}`);
       const data = await response.json();
       
       rowSelect.innerHTML = '<option value="">-- בחר שורה --</option>';
       if (data.success && data.data) {
           data.data.forEach(row => {
               const option = document.createElement('option');
               option.value = row.id;
               option.textContent = row.name;
               rowSelect.appendChild(option);
           });
       }
   } catch (error) {
       console.error('Error loading rows:', error);
   }
}

// טעינת אחוזות קבר
window.loadAreaGraves = async function(rowId) {
   const areaGraveSelect = document.getElementById('areaGraveSelect');
   if (!areaGraveSelect) return;
   
   try {
       const response = await fetch(`${window.API_BASE}cemetery-hierarchy.php?action=list&type=area_grave&parent_id=${rowId}`);
       const data = await response.json();
       
       areaGraveSelect.innerHTML = '<option value="">-- בחר אחוזת קבר --</option>';
       if (data.success && data.data) {
           data.data.forEach(areaGrave => {
               const option = document.createElement('option');
               option.value = areaGrave.id;
               option.textContent = areaGrave.name;
               areaGraveSelect.appendChild(option);
           });
       }
   } catch (error) {
       console.error('Error loading area graves:', error);
   }
}

// טעינת קברים פנויים
window.loadGraves = async function(areaGraveId) {
   const graveSelect = document.getElementById('graveSelect');
   if (!graveSelect) return;
   
   try {
       const response = await fetch(`${window.API_BASE}cemetery-hierarchy.php?action=list&type=grave&parent_id=${areaGraveId}`);
       const data = await response.json();
       
       graveSelect.innerHTML = '<option value="">-- בחר קבר --</option>';
       if (data.success && data.data) {
           data.data.forEach(grave => {
               // רק קברים פנויים (סטטוס 1)
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

// ניקוי בוררים
window.clearSelectors = function(levels) {
   const configs = {
       'row': { id: 'rowSelect', default: '-- בחר חלקה תחילה --', disabled: true },
       'area_grave': { id: 'areaGraveSelect', default: '-- בחר שורה תחילה --', disabled: true },
       'grave': { id: 'graveSelect', default: '-- בחר אחוזת קבר תחילה --', disabled: true }
   };
   
   levels.forEach(level => {
       const config = configs[level];
       if (config) {
           const element = document.getElementById(config.id);
           if (element) {
               element.innerHTML = `<option value="">${config.default}</option>`;
               element.disabled = config.disabled;
           }
       }
   });
}

// צור פונקציה גלובלית לאתחול
window.initGraveSelectors = function() {
   console.log('Init grave selectors - running');
   
   const blockSelect = document.getElementById('blockSelect');
   console.log('blockSelect element:', blockSelect);
   
   if (blockSelect) {
       console.log('Loading initial blocks...');
       window.loadBlocks('');
   }
   
   const plotSelect = document.getElementById('plotSelect');
   console.log('plotSelect element:', plotSelect);
   
   if (plotSelect) {
       console.log('Loading initial plots...');
       window.loadPlots('', '');
   }
   
   // הוסף event listeners
   const cemeterySelect = document.getElementById('cemeterySelect');
   if (cemeterySelect) {
       console.log('Adding cemetery change listener');
       cemeterySelect.addEventListener('change', function() {
           console.log('Cemetery changed to:', this.value);
           window.updateGraveSelectors('cemetery');
       });
   }
   
   const blockSelectForEvent = document.getElementById('blockSelect');
   if (blockSelectForEvent) {
       console.log('Adding block change listener');
       blockSelectForEvent.addEventListener('change', function() {
           console.log('Block changed to:', this.value);
           window.updateGraveSelectors('block');
       });
   }
   
   const plotSelectForEvent = document.getElementById('plotSelect');
   if (plotSelectForEvent) {
       console.log('Adding plot change listener');
       plotSelectForEvent.addEventListener('change', function() {
           console.log('Plot changed to:', this.value);
           window.updateGraveSelectors('plot');
       });
   }
   
   const rowSelect = document.getElementById('rowSelect');
   if (rowSelect) {
       console.log('Adding row change listener');
       rowSelect.addEventListener('change', function() {
           window.updateGraveSelectors('row');
       });
   }
   
   const areaGraveSelect = document.getElementById('areaGraveSelect');
   if (areaGraveSelect) {
       console.log('Adding area grave change listener');
       areaGraveSelect.addEventListener('change', function() {
           window.updateGraveSelectors('area_grave');
       });
   }
};

// נסה להריץ אחרי 500 מילישניות
setTimeout(function() {
   console.log('Trying to init after 500ms delay...');
   window.initGraveSelectors();
}, 500);
</script>