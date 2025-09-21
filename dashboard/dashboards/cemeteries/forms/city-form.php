<?php
 // dashboard/dashboards/cemeteries/forms/city-form.php
 // טופס להוספה/עריכה של ערים - עם תמיכה בהוספה מכרטיס מדינה

 error_reporting(E_ALL);
 ini_set('display_errors', 1);
 header('Content-Type: text/html; charset=utf-8');

 require_once __DIR__ . '/FormBuilder.php';
 require_once dirname(__DIR__) . '/config.php';

 $itemId = $_GET['item_id'] ?? $_GET['id'] ?? null;
 $parentId = $_GET['parent_id'] ?? null; // זה יהיה ה-countryId אם מגיעים מכרטיס מדינה

 try {
     $conn = getDBConnection();
     
     // טען מדינות לרשימה
     $countriesStmt = $conn->prepare("
         SELECT unicId, countryNameHe 
         FROM countries 
         WHERE isActive = 1 
         ORDER BY countryNameHe
     ");
     $countriesStmt->execute();
     $countries = [];
     while ($row = $countriesStmt->fetch(PDO::FETCH_ASSOC)) {
         $countries[$row['unicId']] = $row['countryNameHe'];
     }
     
     // טען עיר אם בעריכה
     $city = null;
     if ($itemId) {
         $stmt = $conn->prepare("
             SELECT c.*, co.countryNameHe 
             FROM cities c
             LEFT JOIN countries co ON c.countryId = co.unicId
             WHERE c.unicId = ? AND c.isActive = 1
         ");
         $stmt->execute([$itemId]);
         $city = $stmt->fetch(PDO::FETCH_ASSOC);
         
         // אם בעריכה, השתמש במדינה של העיר
         if ($city) {
             $parentId = $city['countryId'];
         }
     }
     
     // אם יש parent_id, טען את פרטי המדינה
     $parentCountry = null;
     if ($parentId) {
         $stmt = $conn->prepare("
             SELECT unicId, countryNameHe 
             FROM countries 
             WHERE unicId = ? AND isActive = 1
         ");
         $stmt->execute([$parentId]);
         $parentCountry = $stmt->fetch(PDO::FETCH_ASSOC);
     }
     
 } catch (Exception $e) {
     die(json_encode(['error' => $e->getMessage()]));
 }

 // יצירת FormBuilder
 $formBuilder = new FormBuilder('city', $itemId, $parentId);

 // אם יש מדינת הורה, הצג אותה כשדה קריאה בלבד
 if ($parentCountry) {
     $formBuilder->addCustomHTML('
         <div class="form-group">
             <label>מדינה</label>
             <input type="text" class="form-control" value="' . htmlspecialchars($parentCountry['countryNameHe']) . '" readonly style="background: #f8f9fa;">
             <input type="hidden" name="countryId" value="' . htmlspecialchars($parentCountry['unicId']) . '">
             <input type="hidden" name="countryNameHe" value="' . htmlspecialchars($parentCountry['countryNameHe']) . '">
         </div>
     ');
 } else {
     // אם אין מדינת הורה, הצג סלקט לבחירת מדינה
     $formBuilder->addField('countryId', 'מדינה', 'select', [
         'required' => true,
         'options' => array_merge(
             ['' => '-- בחר מדינה --'],
             $countries
         ),
         'value' => $city['countryId'] ?? ''
     ]);
     
     // הוסף שדה נסתר לשם המדינה
     $formBuilder->addCustomHTML('
         <input type="hidden" id="countryNameHe" name="countryNameHe" value="' . ($city['countryNameHe'] ?? '') . '">
     ');
 }

 // שם עיר בעברית
 $formBuilder->addField('cityNameHe', 'שם עיר בעברית', 'text', [
     'required' => true,
     'placeholder' => 'לדוגמה: ירושלים',
     'value' => $city['cityNameHe'] ?? ''
 ]);

 // שם עיר באנגלית
 $formBuilder->addField('cityNameEn', 'שם עיר באנגלית', 'text', [
     'required' => true,
     'placeholder' => 'Example: Jerusalem',
     'value' => $city['cityNameEn'] ?? ''
 ]);

 // אם זו עריכה, הוסף את ה-unicId כשדה מוסתר
 if ($city && $city['unicId']) {
     $formBuilder->addField('unicId', '', 'hidden', [
         'value' => $city['unicId']
     ]);
 }

 // הצג את הטופס עם FormBuilder
 echo $formBuilder->renderModal();

 // הוסף סקריפט לעדכון שם המדינה אם אין parent_id
 if (!$parentCountry) {
?>
<script>
 document.addEventListener('DOMContentLoaded', function() {
     const countrySelect = document.querySelector('select[name="countryId"]');
     const countryNameField = document.getElementById('countryNameHe');
     
     if (countrySelect && countryNameField) {
         countrySelect.addEventListener('change', function() {
             const selectedOption = this.options[this.selectedIndex];
             if (selectedOption.value && selectedOption.value !== '') {
                 // עדכן את שם המדינה, אבל רק אם זה לא האופציה הריקה
                 countryNameField.value = selectedOption.text !== '-- בחר מדינה --' ? selectedOption.text : '';
             } else {
                 countryNameField.value = '';
             }
         });
         
         // אם יש ערך התחלתי (בעריכה), עדכן את שם המדינה
         if (countrySelect.value) {
             const selectedOption = countrySelect.options[countrySelect.selectedIndex];
             if (selectedOption && selectedOption.value) {
                 countryNameField.value = selectedOption.text;
             }
         }
     }
 });
</script>
<?php
}
?>