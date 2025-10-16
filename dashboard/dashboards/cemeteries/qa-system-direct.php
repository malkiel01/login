<?php
// qa-minimal.php - בדיקה מינימלית

// הפעל הצגת שגיאות
error_reporting(E_ALL);
ini_set('display_errors', 1);

// בדיקה בסיסית
echo "<h1 dir='rtl'>בדיקת מערכת בסיסית</h1>";
echo "<p>PHP Version: " . phpversion() . "</p>";
echo "<p>Script Location: " . __FILE__ . "</p>";
echo "<p>Document Root: " . $_SERVER['DOCUMENT_ROOT'] . "</p>";

// בדיקת session
session_start();
$_SESSION['test'] = 'working';
echo "<p>Session Status: " . (isset($_SESSION['test']) ? 'OK' : 'ERROR') . "</p>";

// בדיקת קובץ ENV
$env_file = $_SERVER['DOCUMENT_ROOT'] . '/.env';
echo "<p>ENV File: " . (file_exists($env_file) ? 'EXISTS' : 'NOT FOUND') . "</p>";

// בדיקת קובץ config
$config_file = $_SERVER['DOCUMENT_ROOT'] . '/config.php';
echo "<p>Config File: " . (file_exists($config_file) ? 'EXISTS' : 'NOT FOUND') . "</p>";

// בדיקת PDO
echo "<p>PDO MySQL: " . (extension_loaded('pdo_mysql') ? 'LOADED' : 'NOT LOADED') . "</p>";

// קישורים
echo "<hr>";
echo "<h2>קישורים לבדיקה:</h2>";
echo "<ul dir='rtl'>";
echo "<li><a href='index.php'>index.php</a></li>";
echo "<li><a href='test-permissions.php'>test-permissions.php</a></li>";
echo "<li><a href='forms/test-form.php'>forms/test-form.php</a></li>";
echo "</ul>";

// הצג phpinfo בתחתית
echo "<hr>";
echo "<h2>PHP Info:</h2>";
echo "<details>";
echo "<summary>לחץ להצגה</summary>";
phpinfo();
echo "</details>";
?>