<?php
// download-geonames.php
// הורדת קובץ כל הערים בעולם מ-GeoNames

$files = [
    'cities15000' => 'http://download.geonames.org/export/dump/cities15000.zip', // ערים מעל 15,000 תושבים
    'cities5000' => 'http://download.geonames.org/export/dump/cities5000.zip',   // ערים מעל 5,000 תושבים
    'cities1000' => 'http://download.geonames.org/export/dump/cities1000.zip',   // ערים מעל 1,000 תושבים
    'countryInfo' => 'http://download.geonames.org/export/dump/countryInfo.txt'  // מידע על מדינות
];

echo "קבצים זמינים להורדה מ-GeoNames:<br>";
foreach ($files as $name => $url) {
    echo "<a href='$url'>$name</a><br>";
}
?>