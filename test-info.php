<?php
// import-countries-cities.php

require_once $_SERVER['DOCUMENT_ROOT'] . '/dashboard/dashboards/cemeteries/config.php';

// פונקציה לייבוא מדינות מ-RestCountries
function importCountries($pdo) {
    $url = 'https://restcountries.com/v3.1/all';
    $json = file_get_contents($url);
    $countries = json_decode($json, true);
    
    $stmt = $pdo->prepare("
        INSERT INTO countries (countryNameHe, countryNameEn, createDate, isActive, unicId)
        VALUES (:nameHe, :nameEn, NOW(), 1, :unicId)
        ON DUPLICATE KEY UPDATE 
        countryNameHe = VALUES(countryNameHe),
        countryNameEn = VALUES(countryNameEn),
        updateDate = NOW()
    ");
    
    $count = 0;
    foreach ($countries as $country) {
        $nameEn = $country['name']['common'];
        $nameHe = $country['translations']['heb']['common'] ?? $nameEn;
        $unicId = 'COUNTRY_' . $country['cca3']; // ISO 3166-1 alpha-3
        
        $stmt->execute([
            'nameHe' => $nameHe,
            'nameEn' => $nameEn,
            'unicId' => $unicId
        ]);
        $count++;
    }
    
    return $count;
}

// פונקציה לייבוא ערים ישראליות
function importIsraeliCities($pdo) {
    // קודם נמצא את ישראל
    $stmt = $pdo->prepare("SELECT unicId FROM countries WHERE countryNameEn LIKE '%Israel%' LIMIT 1");
    $stmt->execute();
    $israelId = $stmt->fetchColumn();
    
    if (!$israelId) {
        echo "Israel not found in database\n";
        return 0;
    }
    
    // רשימת ערים ישראליות (דוגמה חלקית - אפשר להרחיב מקובץ CSV של הלמ"ס)
    $cities = [
        ['he' => 'ירושלים', 'en' => 'Jerusalem'],
        ['he' => 'תל אביב-יפו', 'en' => 'Tel Aviv-Yafo'],
        ['he' => 'חיפה', 'en' => 'Haifa'],
        ['he' => 'ראשון לציון', 'en' => 'Rishon LeZion'],
        ['he' => 'פתח תקווה', 'en' => 'Petah Tikva'],
        ['he' => 'אשדוד', 'en' => 'Ashdod'],
        ['he' => 'נתניה', 'en' => 'Netanya'],
        ['he' => 'באר שבע', 'en' => 'Beer Sheva'],
        ['he' => 'בני ברק', 'en' => 'Bnei Brak'],
        ['he' => 'חולון', 'en' => 'Holon'],
        ['he' => 'רמת גן', 'en' => 'Ramat Gan'],
        ['he' => 'אשקלון', 'en' => 'Ashkelon'],
        ['he' => 'רחובות', 'en' => 'Rehovot'],
        ['he' => 'בת ים', 'en' => 'Bat Yam'],
        ['he' => 'בית שמש', 'en' => 'Beit Shemesh'],
        // ... הוסף עוד ערים
    ];
    
    $stmt = $pdo->prepare("
        INSERT INTO cities (countryId, cityNameHe, cityNameEn, countryNameHe, createDate, isActive, unicId)
        VALUES (:countryId, :nameHe, :nameEn, 'ישראל', NOW(), 1, :unicId)
        ON DUPLICATE KEY UPDATE 
        cityNameHe = VALUES(cityNameHe),
        cityNameEn = VALUES(cityNameEn),
        updateDate = NOW()
    ");
    
    $count = 0;
    foreach ($cities as $city) {
        $unicId = 'CITY_IL_' . strtoupper(str_replace([' ', '-'], '_', $city['en']));
        
        $stmt->execute([
            'countryId' => $israelId,
            'nameHe' => $city['he'],
            'nameEn' => $city['en'],
            'unicId' => $unicId
        ]);
        $count++;
    }
    
    return $count;
}

// הרצת הייבוא
try {
    $pdo = getDBConnection();
    
    echo "Starting import...\n";
    
    // ייבוא מדינות
    $countriesCount = importCountries($pdo);
    echo "Imported $countriesCount countries\n";
    
    // ייבוא ערים ישראליות
    $citiesCount = importIsraeliCities($pdo);
    echo "Imported $citiesCount Israeli cities\n";
    
    echo "Import completed successfully!\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>