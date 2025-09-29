<?php
// test-functions-load.php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('max_execution_time', 5); // הגבל ל-5 שניות כדי לראות אם יש לולאה אינסופית

echo "<pre>";

// Load config
require_once $_SERVER['DOCUMENT_ROOT'] . '/dashboard/dashboards/printPDF/config.php';
echo "1. Config loaded\n";

// Now let's manually include functions.php and see what happens
echo "2. Reading functions.php content...\n";
$functions_content = file_get_contents($_SERVER['DOCUMENT_ROOT'] . '/dashboard/dashboards/printPDF/includes/functions.php');

// Check for infinite loops or recursive calls
echo "3. Checking for potential issues:\n";
if (preg_match_all('/getPDFEditorDB\(\)/', $functions_content, $matches)) {
    echo "   - Found " . count($matches[0]) . " calls to getPDFEditorDB()\n";
}
if (preg_match_all('/createPDFEditorTables\([^)]*\)/', $functions_content, $matches)) {
    echo "   - Found " . count($matches[0]) . " calls to createPDFEditorTables()\n";
}
if (preg_match_all('/static\s+\$initialized/', $functions_content, $matches)) {
    echo "   - Found " . count($matches[0]) . " static initialized variables\n";
}

echo "\n4. Looking for code that runs automatically on include:\n";
// Check if there's code outside of functions that runs immediately
$lines = explode("\n", $functions_content);
$in_function = false;
$brace_count = 0;

foreach ($lines as $num => $line) {
    // Track if we're inside a function
    if (preg_match('/^\s*function\s+/', $line)) {
        $in_function = true;
        $brace_count = 0;
    }
    
    if ($in_function) {
        $brace_count += substr_count($line, '{') - substr_count($line, '}');
        if ($brace_count <= 0) {
            $in_function = false;
        }
    }
    
    // Check for code outside functions (excluding comments and <?php tags)
    if (!$in_function && !preg_match('/^\s*(\/\/|\/\*|\*|<\?php|\?>|\s*$)/', $line)) {
        if (preg_match('/\S/', $line)) { // Has non-whitespace content
            echo "   Line " . ($num + 1) . ": Code outside function: " . trim($line) . "\n";
        }
    }
}

echo "\n5. Now trying to actually load functions.php with output buffering...\n";
ob_start();
$start_time = microtime(true);

try {
    require_once $_SERVER['DOCUMENT_ROOT'] . '/dashboard/dashboards/printPDF/includes/functions.php';
    $end_time = microtime(true);
    echo "   ✓ Loaded successfully in " . round($end_time - $start_time, 3) . " seconds\n";
} catch (Exception $e) {
    echo "   ✗ Exception: " . $e->getMessage() . "\n";
}

$output = ob_get_clean();
if ($output) {
    echo "   Output during load: " . $output . "\n";
}

echo "</pre>";
?>