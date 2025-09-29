<?php
// Enable all error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

echo "<h2>PDF Editor Debug Test</h2>";
echo "<pre>";

// Step 1: Check PHP Version
echo "1. PHP Version: " . PHP_VERSION . "\n";
echo "   ✓ OK\n\n";

// Step 2: Check main config
echo "2. Checking main config.php:\n";
$main_config = $_SERVER['DOCUMENT_ROOT'] . '/config.php';
if (file_exists($main_config)) {
    echo "   File exists: YES\n";
    
    // Check if we can read it
    if (is_readable($main_config)) {
        echo "   Readable: YES\n";
        
        // Try to include it
        try {
            @include_once $main_config;
            echo "   Loaded: YES\n";
            
            // Check if database constants are defined
            echo "   DB_HOST defined: " . (defined('DB_HOST') ? 'YES (' . DB_HOST . ')' : 'NO') . "\n";
            echo "   DB_NAME defined: " . (defined('DB_NAME') ? 'YES (' . DB_NAME . ')' : 'NO') . "\n";
            echo "   DB_USER defined: " . (defined('DB_USER') ? 'YES' : 'NO') . "\n";
            echo "   DB_PASS defined: " . (defined('DB_PASS') ? 'YES' : 'NO') . "\n";
        } catch (Exception $e) {
            echo "   ERROR loading: " . $e->getMessage() . "\n";
        }
    } else {
        echo "   Readable: NO\n";
    }
} else {
    echo "   File exists: NO\n";
}
echo "\n";

// Step 3: Check PDF Editor config WITHOUT including it
echo "3. Checking PDF Editor config.php:\n";
$pdf_config = __DIR__ . '/config.php';
if (file_exists($pdf_config)) {
    echo "   File exists: YES\n";
    echo "   File size: " . filesize($pdf_config) . " bytes\n";
    echo "   Last modified: " . date('Y-m-d H:i:s', filemtime($pdf_config)) . "\n";
    
    // Check syntax without executing
    $output = shell_exec('php -l ' . escapeshellarg($pdf_config) . ' 2>&1');
    echo "   Syntax check: " . trim($output) . "\n";
} else {
    echo "   File exists: NO\n";
}
echo "\n";

// Step 4: Check functions.php WITHOUT including it
echo "4. Checking functions.php:\n";
$functions_file = __DIR__ . '/includes/functions.php';
if (file_exists($functions_file)) {
    echo "   File exists: YES\n";
    echo "   File size: " . filesize($functions_file) . " bytes\n";
    
    // Check syntax without executing
    $output = shell_exec('php -l ' . escapeshellarg($functions_file) . ' 2>&1');
    echo "   Syntax check: " . trim($output) . "\n";
    
    // Check for problematic lines
    $content = file_get_contents($functions_file);
    if (strpos($content, "require_once \$_SERVER['DOCUMENT_ROOT'] . '/config.php'") !== false) {
        echo "   ⚠️ WARNING: Found require_once for /config.php - This will cause loop!\n";
    } else {
        echo "   ✓ No problematic require_once found\n";
    }
} else {
    echo "   File exists: NO\n";
}
echo "\n";

// Step 5: Test database connection directly
echo "5. Testing database connection:\n";
if (defined('DB_HOST') && defined('DB_NAME') && defined('DB_USER')) {
    try {
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
        $db = new PDO($dsn, DB_USER, DB_PASS);
        echo "   Connection: SUCCESS\n";
        
        // Check if pdf_editor tables exist
        $stmt = $db->query("SHOW TABLES LIKE 'pdf_editor_%'");
        $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
        echo "   PDF Editor tables found: " . count($tables) . "\n";
        if (count($tables) > 0) {
            foreach ($tables as $table) {
                echo "     - " . $table . "\n";
            }
        }
    } catch (PDOException $e) {
        echo "   Connection: FAILED\n";
        echo "   Error: " . $e->getMessage() . "\n";
    }
} else {
    echo "   Cannot test - DB constants not defined\n";
}
echo "\n";

// Step 6: Check session
echo "6. Session check:\n";
if (session_status() === PHP_SESSION_NONE) {
    session_start();
    echo "   Session started: YES\n";
} else {
    echo "   Session already active: YES\n";
}
echo "   Session ID: " . session_id() . "\n";
echo "\n";

// Step 7: Test loading config.php step by step
echo "7. Testing config.php load (step by step):\n";
if (file_exists($pdf_config)) {
    // Read the file line by line
    $lines = file($pdf_config);
    echo "   Total lines: " . count($lines) . "\n";
    
    // Look for potential issues
    $line_num = 0;
    foreach ($lines as $line) {
        $line_num++;
        // Check for includes/requires
        if (preg_match('/^\s*(require|include)(_once)?\s/i', $line)) {
            echo "   Line $line_num: Found " . trim($line) . "\n";
        }
    }
}
echo "\n";

// Step 8: Check permissions
echo "8. Directory permissions:\n";
echo "   Current dir: " . __DIR__ . "\n";
echo "   Writable: " . (is_writable(__DIR__) ? 'YES' : 'NO') . "\n";

$temp_dir = __DIR__ . '/temp';
if (file_exists($temp_dir)) {
    echo "   /temp exists: YES\n";
    echo "   /temp writable: " . (is_writable($temp_dir) ? 'YES' : 'NO') . "\n";
} else {
    echo "   /temp exists: NO\n";
}
echo "\n";

// Step 9: Try minimal config load
echo "9. Attempting minimal config load:\n";
try {
    // Define constants if not defined
    if (!defined('PDF_EDITOR_VERSION')) {
        define('PDF_EDITOR_VERSION', '1.0.0');
        echo "   PDF_EDITOR_VERSION defined: YES\n";
    }
    
    if (!defined('CSRF_TOKEN_NAME')) {
        define('CSRF_TOKEN_NAME', 'pdf_editor_csrf');
        echo "   CSRF_TOKEN_NAME defined: YES\n";
    }
    
    // Test CSRF function
    if (!function_exists('generateCSRFToken')) {
        function generateCSRFToken() {
            if (!isset($_SESSION['pdf_editor_csrf'])) {
                $_SESSION['pdf_editor_csrf'] = bin2hex(random_bytes(32));
            }
            return $_SESSION['pdf_editor_csrf'];
        }
        echo "   generateCSRFToken created: YES\n";
    }
    
    $token = generateCSRFToken();
    echo "   CSRF Token generated: " . substr($token, 0, 10) . "...\n";
    
} catch (Exception $e) {
    echo "   ERROR: " . $e->getMessage() . "\n";
}

echo "\n</pre>";
echo "<hr>";
echo "<h3>Summary:</h3>";
echo "<p>Check the output above for any errors or warnings.</p>";
echo "<p>If all checks pass but index.php still gives 500 error, the problem is in the actual loading process.</p>";
?>