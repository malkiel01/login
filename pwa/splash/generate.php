<?php
/**
 * Dynamic Splash Screen Generator
 * יוצר splash screens דינמיים עבור iOS PWA
 *
 * שימוש: /pwa/splash/generate.php?w=1290&h=2796
 */

// קבלת פרמטרים
$width = isset($_GET['w']) ? (int)$_GET['w'] : 1170;
$height = isset($_GET['h']) ? (int)$_GET['h'] : 2532;

// הגבלות
$width = max(320, min(3000, $width));
$height = max(320, min(3000, $height));

// Cache key
$cacheKey = "splash_{$width}x{$height}";
$cacheDir = __DIR__ . '/cache';
$cacheFile = $cacheDir . "/{$cacheKey}.png";

// בדוק cache
if (file_exists($cacheFile) && (time() - filemtime($cacheFile)) < 86400 * 30) {
    header('Content-Type: image/png');
    header('Cache-Control: public, max-age=2592000'); // 30 days
    readfile($cacheFile);
    exit;
}

// הגדרות עיצוב
$config = [
    // גרדיאנט רקע
    'bg_color_start' => [102, 126, 234], // #667eea
    'bg_color_end' => [118, 75, 162],    // #764ba2

    // אייקון
    'icon_path' => __DIR__ . '/../icons/ios/512.png',
    'icon_size_ratio' => 0.2, // 20% מהרוחב

    // טקסט (אופציונלי)
    'show_title' => false,
    'title' => 'חברה קדישא',
    'title_color' => [255, 255, 255]
];

// יצירת התמונה
$image = imagecreatetruecolor($width, $height);

// יצירת גרדיאנט אנכי
for ($y = 0; $y < $height; $y++) {
    $ratio = $y / $height;

    $r = (int)($config['bg_color_start'][0] + ($config['bg_color_end'][0] - $config['bg_color_start'][0]) * $ratio);
    $g = (int)($config['bg_color_start'][1] + ($config['bg_color_end'][1] - $config['bg_color_start'][1]) * $ratio);
    $b = (int)($config['bg_color_start'][2] + ($config['bg_color_end'][2] - $config['bg_color_start'][2]) * $ratio);

    $color = imagecolorallocate($image, $r, $g, $b);
    imageline($image, 0, $y, $width, $y, $color);
}

// הוספת האייקון במרכז
if (file_exists($config['icon_path'])) {
    $icon = imagecreatefrompng($config['icon_path']);

    if ($icon) {
        $iconOrigWidth = imagesx($icon);
        $iconOrigHeight = imagesy($icon);

        // חישוב גודל האייקון
        $iconSize = (int)($width * $config['icon_size_ratio']);
        $iconX = (int)(($width - $iconSize) / 2);
        $iconY = (int)(($height - $iconSize) / 2);

        // העתקת האייקון עם שינוי גודל
        imagecopyresampled(
            $image, $icon,
            $iconX, $iconY,
            0, 0,
            $iconSize, $iconSize,
            $iconOrigWidth, $iconOrigHeight
        );

        imagedestroy($icon);
    }
}

// הוספת כותרת (אופציונלי)
if ($config['show_title'] && !empty($config['title'])) {
    $textColor = imagecolorallocate(
        $image,
        $config['title_color'][0],
        $config['title_color'][1],
        $config['title_color'][2]
    );

    $fontSize = (int)($width * 0.05);
    $fontPath = __DIR__ . '/../../assets/fonts/heebo.ttf';

    if (file_exists($fontPath)) {
        $bbox = imagettfbbox($fontSize, 0, $fontPath, $config['title']);
        $textWidth = abs($bbox[4] - $bbox[0]);
        $textX = (int)(($width - $textWidth) / 2);
        $textY = (int)($height * 0.7);

        imagettftext($image, $fontSize, 0, $textX, $textY, $textColor, $fontPath, $config['title']);
    }
}

// שמירה ל-cache
if (!is_dir($cacheDir)) {
    mkdir($cacheDir, 0755, true);
}
imagepng($image, $cacheFile, 9);

// שליחה לדפדפן
header('Content-Type: image/png');
header('Cache-Control: public, max-age=2592000'); // 30 days
imagepng($image, null, 9);

imagedestroy($image);
