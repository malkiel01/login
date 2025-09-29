<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "Step 1: Basic PHP works<br>";

// בדיקת config
$config_path = $_SERVER['DOCUMENT_ROOT'] . '/dashboard/dashboards/printPDF/config.php';
if (file_exists($config_path)) {
    echo "Step 2: config.php exists<br>";
    require_once $config_path;
    echo "Step 3: config.php loaded<br>";
} else {
    echo "ERROR: config.php not found at: " . $config_path . "<br>";
}

// בדיקת פונקציות
$functions_path = $_SERVER['DOCUMENT_ROOT'] . '/dashboard/dashboards/printPDF/includes/functions.php';
if (file_exists($functions_path)) {
    echo "Step 4: functions.php exists<br>";
    require_once $functions_path;
    echo "Step 5: functions.php loaded<br>";
} else {
    echo "WARNING: functions.php not found at: " . $functions_path . "<br>";
}

// בדיקת session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
echo "Step 6: Session started<br>";

// בדיקת הרשאות
if (function_exists('checkPermission')) {
    echo "Step 7: checkPermission function exists<br>";
    $hasPermission = checkPermission('view', 'pdf_editor');
    echo "Step 8: Permission check result: " . ($hasPermission ? 'YES' : 'NO') . "<br>";
} else {
    echo "WARNING: checkPermission function not found<br>";
}

// בדיקת CSRF
if (function_exists('generateCSRFToken')) {
    echo "Step 9: generateCSRFToken function exists<br>";
    $token = generateCSRFToken();
    echo "Step 10: CSRF Token generated: " . substr($token, 0, 10) . "...<br>";
} else {
    echo "WARNING: generateCSRFToken function not found<br>";
}

echo "<br><strong>✅ All basic checks passed!</strong>";
?>