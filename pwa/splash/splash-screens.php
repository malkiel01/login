<?php
/**
 * iOS Splash Screens - מטא תגיות ל-launch images
 * יש לכלול קובץ זה ב-head של הדף
 *
 * שימוש: <?php include __DIR__ . '/pwa/splash/splash-screens.php'; ?>
 */

// הגדרות ברירת מחדל
$splashConfig = [
    'background' => '#667eea', // צבע רקע
    'icon_path' => '/pwa/icons/ios/512.png', // נתיב לאייקון
    'title' => 'חברה קדישא' // כותרת (אופציונלי)
];

// מאפשר דריסה מבחוץ
if (isset($GLOBALS['splashConfig'])) {
    $splashConfig = array_merge($splashConfig, $GLOBALS['splashConfig']);
}

// רשימת כל המכשירים של אפל עם המימדים שלהם
$devices = [
    // iPhone
    ['width' => 1290, 'height' => 2796, 'ratio' => 3, 'name' => 'iPhone 15 Pro Max, 14 Pro Max'],
    ['width' => 1179, 'height' => 2556, 'ratio' => 3, 'name' => 'iPhone 15 Pro, 15, 14 Pro'],
    ['width' => 1170, 'height' => 2532, 'ratio' => 3, 'name' => 'iPhone 14, 13 Pro, 13, 12 Pro, 12'],
    ['width' => 1125, 'height' => 2436, 'ratio' => 3, 'name' => 'iPhone X, XS, 11 Pro'],
    ['width' => 1242, 'height' => 2688, 'ratio' => 3, 'name' => 'iPhone XS Max, 11 Pro Max'],
    ['width' => 828, 'height' => 1792, 'ratio' => 2, 'name' => 'iPhone XR, 11'],
    ['width' => 1080, 'height' => 2340, 'ratio' => 3, 'name' => 'iPhone 13 mini, 12 mini'],
    ['width' => 1080, 'height' => 1920, 'ratio' => 3, 'name' => 'iPhone 6+, 6s+, 7+, 8+'],
    ['width' => 750, 'height' => 1334, 'ratio' => 2, 'name' => 'iPhone 6, 6s, 7, 8, SE2, SE3'],
    ['width' => 640, 'height' => 1136, 'ratio' => 2, 'name' => 'iPhone 5, 5s, 5c, SE1'],

    // iPad
    ['width' => 2048, 'height' => 2732, 'ratio' => 2, 'name' => 'iPad Pro 12.9"'],
    ['width' => 1668, 'height' => 2388, 'ratio' => 2, 'name' => 'iPad Pro 11"'],
    ['width' => 1668, 'height' => 2224, 'ratio' => 2, 'name' => 'iPad Pro 10.5", Air 3'],
    ['width' => 1620, 'height' => 2160, 'ratio' => 2, 'name' => 'iPad 10.2"'],
    ['width' => 1536, 'height' => 2048, 'ratio' => 2, 'name' => 'iPad Mini, Air, Pro 9.7"'],
    ['width' => 1488, 'height' => 2266, 'ratio' => 2, 'name' => 'iPad Mini 6'],
    ['width' => 1640, 'height' => 2360, 'ratio' => 2, 'name' => 'iPad Air 4, 5'],
];

// בדיקה אם יש קבצי splash מוכנים
$splashDir = __DIR__;
$hasSplashImages = file_exists($splashDir . '/splash-1290x2796.png');

// יצירת התגיות
foreach ($devices as $device) {
    $w = $device['width'];
    $h = $device['height'];
    $ratio = $device['ratio'];

    // בדוק אם יש תמונה מוכנה
    $splashFile = "splash-{$w}x{$h}.png";
    $splashPath = $hasSplashImages ? "/pwa/splash/{$splashFile}" : "/pwa/splash/generate.php?w={$w}&h={$h}";

    // Portrait
    echo "<link rel=\"apple-touch-startup-image\" href=\"{$splashPath}\" media=\"(device-width: " . ($w/$ratio) . "px) and (device-height: " . ($h/$ratio) . "px) and (-webkit-device-pixel-ratio: {$ratio}) and (orientation: portrait)\">\n";

    // Landscape (הפוך את המימדים)
    $splashPathLandscape = $hasSplashImages ? "/pwa/splash/splash-{$h}x{$w}.png" : "/pwa/splash/generate.php?w={$h}&h={$w}";
    echo "<link rel=\"apple-touch-startup-image\" href=\"{$splashPathLandscape}\" media=\"(device-width: " . ($w/$ratio) . "px) and (device-height: " . ($h/$ratio) . "px) and (-webkit-device-pixel-ratio: {$ratio}) and (orientation: landscape)\">\n";
}
?>
