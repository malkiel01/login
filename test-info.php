<?php
// import-from-excel.php
header('Content-Type: text/html; charset=utf-8');

require_once $_SERVER['DOCUMENT_ROOT'] . '/dashboard/dashboards/cemeteries/config.php';

// אם העלית קובץ
if (isset($_FILES['excel_file'])) {
    $uploadedFile = $_FILES['excel_file']['tmp_name'];
    $fileName = $_FILES['excel_file']['name'];
    
    // בדוק סוג קובץ
    $ext = pathinfo($fileName, PATHINFO_EXTENSION);
    
    if ($ext == 'csv') {
        importFromCSV($uploadedFile);
    } elseif (in_array($ext, ['xls', 'xlsx'])) {
        importFromExcel($uploadedFile);
    } else {
        echo "סוג קובץ לא נתמך";
    }
}

function importFromCSV($file) {
    $pdo = getDBConnection();
    
    // קודם יבא מדינות
    $countriesMap = [];
    
    if (($handle = fopen($file, "r")) !== FALSE) {
        $header = fgetcsv($handle);
        
        // מצא את האינדקסים של העמודות
        $countryIndex = array_search('country', array_map('strtolower', $header));
        $cityIndex = array_search('city', array_map('strtolower', $header));
        
        if ($countryIndex === false || $cityIndex === false) {
            // נסה שמות אחרים
            $countryIndex = array_search('country_name', array_map('strtolower', $header));
            $cityIndex = array_search('city_name', array_map('strtolower', $header));
        }
        
        while (($data = fgetcsv($handle)) !== FALSE) {
            $country = $data[$countryIndex] ?? '';
            $city = $data[$cityIndex] ?? '';
            
            if (empty($country) || empty($city)) continue;
            
            // יבא מדינה אם לא קיימת
            if (!isset($countriesMap[$country])) {
                $unicId = 'COUNTRY_' . strtoupper(substr(preg_replace('/[^A-Z]/', '', $country), 0, 3));
                
                $stmt = $pdo->prepare("
                    INSERT IGNORE INTO countries (countryNameEn, countryNameHe, createDate, isActive, unicId)
                    VALUES (:name, :name, NOW(), 1, :unicId)
                ");
                $stmt->execute(['name' => $country, 'unicId' => $unicId]);
                
                $countriesMap[$country] = $unicId;
            }
            
            // יבא עיר
            $cityUnicId = 'CITY_' . strtoupper(substr(preg_replace('/[^A-Z]/', '', $city), 0, 10)) . '_' . rand(1000, 9999);
            
            $stmt = $pdo->prepare("
                INSERT IGNORE INTO cities (countryId, cityNameEn, cityNameHe, countryNameHe, createDate, isActive, unicId)
                VALUES (:countryId, :city, :city, :country, NOW(), 1, :unicId)
            ");
            $stmt->execute([
                'countryId' => $countriesMap[$country],
                'city' => $city,
                'country' => $country,
                'unicId' => $cityUnicId
            ]);
        }
        fclose($handle);
        
        echo "הייבוא הושלם";
    }
}

function importFromExcel($file) {
    echo "לייבוא מ-Excel, צריך להתקין את ספריית PHPSpreadsheet";
    echo "<br>או להמיר את הקובץ ל-CSV";
}
?>

<!DOCTYPE html>
<html dir="rtl">
<head>
    <title>ייבוא ערים ומדינות מקובץ</title>
</head>
<body>
    <h2>ייבוא ערים ומדינות מקובץ Excel/CSV</h2>
    
    <form method="POST" enctype="multipart/form-data">
        <p>בחר קובץ CSV או Excel עם עמודות: country, city</p>
        <input type="file" name="excel_file" accept=".csv,.xls,.xlsx" required>
        <button type="submit">יבא נתונים</button>
    </form>
    
    <hr>
    
    <h3>הורד קבצים מוכנים:</h3>
    <ul>
        <li><a href="https://simplemaps.com/static/data/world-cities/basic/simplemaps_worldcities_basicv1.75.zip">SimpleMaps - Basic World Cities (חינם)</a></li>
        <li><a href="http://download.geonames.org/export/dump/cities15000.zip">GeoNames - ערים מעל 15,000 תושבים</a></li>
    </ul>
    
    <h3>מבנה נדרש לקובץ CSV:</h3>
    <pre>
country,city,population,lat,lng
Israel,Jerusalem,936425,31.769,35.216
Israel,Tel Aviv,460613,32.08,34.78
United States,New York,8336817,40.71,-74.00
    </pre>
</body>
</html>