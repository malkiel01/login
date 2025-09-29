<?php
echo "<h2>PHP Extensions Check:</h2>";

// בדיקת GD
if (extension_loaded('gd')) {
    echo "✅ GD Library is installed<br>";
    $gd_info = gd_info();
    echo "GD Version: " . $gd_info['GD Version'] . "<br>";
    echo "Supported formats: ";
    if ($gd_info['JPEG Support']) echo "JPEG ";
    if ($gd_info['PNG Support']) echo "PNG ";
    echo "<br><br>";
} else {
    echo "❌ GD Library is NOT installed<br><br>";
}

// בדיקת ImageMagick
if (extension_loaded('imagick')) {
    echo "✅ ImageMagick is installed<br>";
    $imagick = new Imagick();
    $formats = $imagick->queryFormats();
    echo "Supported formats: " . implode(', ', array_slice($formats, 0, 10)) . "...<br><br>";
} else {
    echo "❌ ImageMagick is NOT installed<br><br>";
}

// בדיקת TCPDF ו-FPDI
$vendor_path = dirname(__DIR__) . '/vendor/autoload.php';
if (file_exists($vendor_path)) {
    require_once $vendor_path;
    
    if (class_exists('TCPDF')) {
        echo "✅ TCPDF is available<br>";
    } else {
        echo "❌ TCPDF is NOT available<br>";
    }
    
    if (class_exists('setasign\Fpdi\Fpdi')) {
        echo "✅ FPDI is available<br>";
    } else {
        echo "❌ FPDI is NOT available<br>";
    }
} else {
    echo "⚠️ Vendor folder not found at: " . $vendor_path . "<br>";
}

// בדיקות נוספות חשובות
echo "<br><h3>Other Important Checks:</h3>";
echo "PHP Version: " . PHP_VERSION . "<br>";
echo "Max upload size: " . ini_get('upload_max_filesize') . "<br>";
echo "Max POST size: " . ini_get('post_max_size') . "<br>";
echo "Memory limit: " . ini_get('memory_limit') . "<br>";
?>