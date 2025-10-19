<?php
require_once __DIR__ . '/SmartSelect.php';
require_once dirname(__DIR__) . '/config.php';

// טען מדינות מהשרת
try {
    $conn = getDBConnection();
    
    $stmt = $conn->prepare("
        SELECT unicId, countryNameHe 
        FROM countries 
        WHERE isActive = 1 
        ORDER BY countryNameHe
    ");
    $stmt->execute();
    
    $countries = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $countries[$row['unicId']] = $row['countryNameHe'];
    }
    
} catch (Exception $e) {
    die("שגיאה: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>טופס דוגמה - SmartSelect</title>
    
    <!-- Bootstrap (אם יש לך) -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- SmartSelect CSS -->
    <link href="../css/smart-select.css" rel="stylesheet">
    
    <style>
        body { padding: 2rem; background: #f5f7fa; }
        .demo-container { max-width: 600px; margin: 0 auto; background: white; padding: 2rem; border-radius: 8px; }
        .form-control { width: 100%; padding: 0.625rem; border: 1px solid #cbd5e0; border-radius: 0.375rem; }
    </style>
</head>
<body>
    <div class="demo-container">
        <h1>🎯 טופס דוגמה - SmartSelect</h1>
        <p>טופס הדגמה עם 3 סוגי שדות</p>
        <hr>
        
        <form method="POST" action="">
            
            <!-- שדה 1: אינפוט רגיל -->
            <div class="form-group">
                <label>שם מלא <span style="color: red;">*</span></label>
                <input type="text" name="fullName" class="form-control" required placeholder="הכנס שם מלא">
            </div>
            
            <!-- שדה 2: Select פשוט (ללא חיפוש) -->
            <?php
            $genderSelect = new SmartSelect('gender', 'מגדר', [
                '' => '-- בחר --',
                1 => 'זכר',
                2 => 'נקבה'
            ], [
                'searchable' => false,  // ללא חיפוש
                'required' => true
            ]);
            echo $genderSelect->render();
            ?>
            
            <!-- שדה 3: SmartSelect עם חיפוש (מדינות מהשרת) -->
            <?php
            $countrySelect = new SmartSelect('countryId', 'מדינה', $countries, [
                'searchable' => true,  // עם חיפוש!
                'placeholder' => 'בחר מדינה...',
                'search_placeholder' => 'חפש מדינה...',
                'required' => true
            ]);
            echo $countrySelect->render();
            ?>
            
            <!-- שדה 4: SmartSelect עם תלות (ערים) -->
            <div id="cities-container" style="display: none;">
                <div class="form-group">
                    <label>עיר</label>
                    <select id="cityId" name="cityId" class="form-control">
                        <option value="">-- בחר קודם מדינה --</option>
                    </select>
                </div>
            </div>
            
            <button type="submit" class="btn btn-primary btn-block">שלח טופס</button>
        </form>
        
        <hr>
        <h3>תוצאות:</h3>
        <pre id="results">לא נשלח עדיין</pre>
    </div>
    
    <!-- SmartSelect JS -->
    <script src="../js/smart-select.js"></script>
    
    <script>
    // טיפול בתלות: מדינה → ערים
    document.getElementById('countryId').addEventListener('change', function() {
        const countryId = this.value;
        const citiesContainer = document.getElementById('cities-container');
        
        if (!countryId) {
            citiesContainer.style.display = 'none';
            return;
        }
        
        // טען ערים (AJAX)
        fetch(`../api/get-cities.php?countryId=${countryId}`)
            .then(response => response.json())
            .then(cities => {
                const citySelect = document.getElementById('cityId');
                citySelect.innerHTML = '<option value="">-- בחר עיר --</option>';
                
                cities.forEach(city => {
                    const option = document.createElement('option');
                    option.value = city.unicId;
                    option.textContent = city.cityNameHe;
                    citySelect.appendChild(option);
                });
                
                citiesContainer.style.display = 'block';
            });
    });
    </script>
    
    <?php
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        echo '<script>';
        echo 'document.getElementById("results").textContent = ' . json_encode(print_r($_POST, true)) . ';';
        echo '</script>';
    }
    ?>
</body>
</html>