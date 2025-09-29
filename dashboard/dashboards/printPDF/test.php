<?php
// test-functions-structure.php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<pre>";

$file = $_SERVER['DOCUMENT_ROOT'] . '/dashboard/dashboards/printPDF/includes/functions.php';
$content = file_get_contents($file);

// Count braces
$open_braces = substr_count($content, '{');
$close_braces = substr_count($content, '}');

echo "Brace count:\n";
echo "  Opening braces {: $open_braces\n";
echo "  Closing braces }: $close_braces\n";

if ($open_braces != $close_braces) {
    echo "  ⚠️ MISMATCH! Difference: " . ($close_braces - $open_braces) . " extra closing braces\n";
}

echo "\nChecking structure around problematic lines:\n\n";

$lines = explode("\n", $content);

// Show context around line 26
echo "Around line 26:\n";
for ($i = 23; $i <= 28 && $i < count($lines); $i++) {
    echo sprintf("  %3d: %s\n", $i+1, $lines[$i]);
}

echo "\nAround line 97:\n";
for ($i = 94; $i <= 99 && $i < count($lines); $i++) {
    echo sprintf("  %3d: %s\n", $i+1, $lines[$i]);
}

echo "\nAround line 126:\n";
for ($i = 123; $i <= 128 && $i < count($lines); $i++) {
    echo sprintf("  %3d: %s\n", $i+1, $lines[$i]);
}

echo "</pre>";
?>