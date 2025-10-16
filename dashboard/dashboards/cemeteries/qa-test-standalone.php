<?php
session_start();
$_SESSION['user_id'] = 999;
$_SESSION['dashboard_type'] = 'cemetery_manager';

echo "<h1>Session Test</h1>";
echo "<pre>";
print_r($_SESSION);
echo "</pre>";

echo "<h2>Files Check</h2>";
$files = array(
    'index.php' => __DIR__ . '/index.php',
    'config.php' => $_SERVER['DOCUMENT_ROOT'] . '/config.php'
);

foreach ($files as $name => $path) {
    echo $name . ": " . (file_exists($path) ? "OK" : "MISSING") . "<br>";
}
?>