<?php
// test-import-api.php
header('Content-Type: text/html; charset=utf-8');

require_once $_SERVER['DOCUMENT_ROOT'] . '/dashboard/dashboards/cemeteries/config.php';

echo "<h2>בדיקת API למדינות וערים</h2>";

// 1. בדיקת RestCountries API
echo "<h3>1. בודק RestCountries API...</h3>";

$context = stream_context_create([
    "http" => [
        "timeout" => 30,
        "header" => "Accept: application/json\r\n"
    ],
    "ssl" => [
        "verify_peer" => false,
        "verify_peer_name" => false
    ]
]);

$url = 'https://restcountries.com/v3.1/all';
$response = @file_get_contents($url, false, $context);

if ($response !== false) {
    $countries = json_decode($response, true);
    echo "<p style='color: green;'>✓ RestCountries API עובד!</p>";
    echo "<p>נמצאו: " . count($countries) . " מדינות</p>";
    
    // דוגמה לכמה מדינות
    echo "<p>דוגמאות:</p><ul>";
    for ($i = 0; $i < 5 && $i < count($countries); $i++) {
        $country = $countries[$i];
        echo "<li>{$country['name']['common']} - ";
        echo isset($country['translations']['heb']) ? $country['translations']['heb']['common'] : 'אין תרגום לעברית';
        echo "</li>";
    }
    echo "</ul>";
} else {
    echo "<p style='color: red;'>✗ RestCountries API לא זמין</p>";
}

// 2. בדיקת GeoNames API (צריך להירשם ל-API key חינמי)
echo "<h3>2. GeoNames API (דורש הרשמה חינמית)</h3>";
echo "<p>להרשמה: <a href='http://www.geonames.org/export/web-services.html' target='_blank'>http://www.geonames.org/export/web-services.html</a></p>";
echo "<p>אחרי ההרשמה, תקבל username שתוכל להשתמש בו ל-API</p>";

// 3. בדיקת data.gov.il - נתונים ממשלתיים ישראליים
echo "<h3>3. נתונים ממשלתיים ישראליים</h3>";

$govUrl = 'https://data.gov.il/api/3/action/datastore_search?resource_id=5c78e9fa-c2e2-4771-93ff-7f400a12f7ba&limit=5';
$govResponse = @file_get_contents($govUrl, false, $context);

if ($govResponse !== false) {
    $data = json_decode($govResponse, true);
    if ($data['success']) {
        echo "<p style='color: green;'>✓ data.gov.il API עובד!</p>";
        echo "<p>דוגמה ליישובים:</p><ul>";
        foreach ($data['result']['records'] as $record) {
            echo "<li>{$record['שם_ישוב']}</li>";
        }
        echo "</ul>";
        echo "<p>ניתן לייבא את כל היישובים בישראל מכאן</p>";
    }
} else {
    echo "<p style='color: red;'>✗ data.gov.il API לא זמין</p>";
}

// 4. אפשרות לייבוא מקובץ CSV
echo "<h3>4. ייבוא מקובץ CSV</h3>";
echo "<p>ניתן להוריד קבצי CSV עם נתונים מ:</p>";
echo "<ul>";
echo "<li><a href='https://www.cbs.gov.il/he/settlements/Pages/default.aspx' target='_blank'>הלמ״ס - רשימת יישובים</a></li>";
echo "<li><a href='https://github.com/datasets/country-list' target='_blank'>GitHub - Country List CSV</a></li>";
echo "</ul>";

echo "<hr>";
echo "<h3>המלצות:</h3>";
echo "<ol>";
echo "<li>למדינות: השתמש ב-RestCountries API (עובד)</li>";
echo "<li>לערים בישראל: השתמש ב-data.gov.il או קובץ CSV מהלמ״ס</li>";
echo "<li>לערים בעולם: GeoNames עם API key (חינמי אחרי הרשמה)</li>";
echo "</ol>";

// כפתור לייבוא
echo "<hr>";
echo "<h3>פעולות זמינות:</h3>";
echo "<button onclick='window.location.href=\"import-from-restcountries.php\"'>ייבא מדינות מ-RestCountries</button> ";
echo "<button onclick='window.location.href=\"import-israeli-cities.php\"'>ייבא יישובים ישראליים</button>";
?>