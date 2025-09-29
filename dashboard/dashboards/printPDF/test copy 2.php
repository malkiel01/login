<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>Connection Test</h2><pre>";

// Step 1: Load main config
echo "1. Loading main config...\n";
$main_config = $_SERVER['DOCUMENT_ROOT'] . '/config.php';
if (file_exists($main_config)) {
    require_once $main_config;
    echo "   ✓ Loaded\n\n";
} else {
    die("   ✗ Main config not found\n");
}

// Step 2: Check what's defined
echo "2. Checking defined constants:\n";
echo "   DB_HOST: " . (defined('DB_HOST') ? DB_HOST : 'NOT DEFINED') . "\n";
echo "   DB_NAME: " . (defined('DB_NAME') ? DB_NAME : 'NOT DEFINED') . "\n";
echo "   DB_USER: " . (defined('DB_USER') ? DB_USER : 'NOT DEFINED') . "\n";
echo "   DB_PASSWORD: " . (defined('DB_PASSWORD') ? 'DEFINED (hidden)' : 'NOT DEFINED') . "\n";
echo "   DB_PASS: " . (defined('DB_PASS') ? 'DEFINED (hidden)' : 'NOT DEFINED') . "\n";
echo "   DB_CHARSET: " . (defined('DB_CHARSET') ? DB_CHARSET : 'NOT DEFINED') . "\n\n";

// Step 3: Check if getDBConnection exists
echo "3. Checking for getDBConnection function:\n";
if (function_exists('getDBConnection')) {
    echo "   ✓ Function exists\n\n";
    
    echo "4. Trying to connect using getDBConnection():\n";
    try {
        $db = getDBConnection();
        if ($db) {
            echo "   ✓ Connected successfully!\n";
            
            // Test query
            $result = $db->query("SELECT 1 as test");
            if ($result) {
                echo "   ✓ Test query successful\n";
            }
        } else {
            echo "   ✗ Connection returned null\n";
        }
    } catch (Exception $e) {
        echo "   ✗ Error: " . $e->getMessage() . "\n";
    }
} else {
    echo "   ✗ Function doesn't exist\n\n";
    
    echo "4. Trying manual connection:\n";
    
    // Try to connect manually
    $host = defined('DB_HOST') ? DB_HOST : 'localhost';
    $dbname = defined('DB_NAME') ? DB_NAME : '';
    $user = defined('DB_USER') ? DB_USER : '';
    $pass = defined('DB_PASSWORD') ? DB_PASSWORD : '';
    $charset = defined('DB_CHARSET') ? DB_CHARSET : 'utf8mb4';
    
    if (empty($dbname) || empty($user)) {
        echo "   ✗ Missing database name or username\n";
    } else {
        try {
            $dsn = "mysql:host=$host;dbname=$dbname;charset=$charset";
            echo "   DSN: $dsn\n";
            echo "   User: $user\n";
            echo "   Connecting...\n";
            
            $pdo = new PDO($dsn, $user, $pass);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            echo "   ✓ Connected successfully!\n";
            
            // Test query
            $result = $pdo->query("SELECT 1 as test");
            if ($result) {
                echo "   ✓ Test query successful\n";
            }
            
        } catch (PDOException $e) {
            echo "   ✗ Connection failed: " . $e->getMessage() . "\n";
        }
    }
}

echo "\n5. Testing if includes/functions.php will work:\n";
$functions_file = __DIR__ . '/includes/functions.php';
if (file_exists($functions_file)) {
    echo "   File exists\n";
    
    // Check if it tries to call getDBConnection
    $content = file_get_contents($functions_file);
    if (strpos($content, 'getDBConnection()') !== false) {
        echo "   ✓ Uses getDBConnection() - good!\n";
    } else {
        echo "   ⚠ Doesn't use getDBConnection()\n";
    }
} else {
    echo "   File not found\n";
}

echo "\n6. Testing cemetery dashboard connection:\n";
$cemetery_index = $_SERVER['DOCUMENT_ROOT'] . '/dashboard/dashboards/cemeteries/index.php';
if (file_exists($cemetery_index)) {
    echo "   Cemetery dashboard exists\n";
    
    // Check how it connects
    $cemetery_functions = $_SERVER['DOCUMENT_ROOT'] . '/dashboard/dashboards/cemeteries/includes/functions.php';
    if (file_exists($cemetery_functions)) {
        echo "   Cemetery functions.php exists\n";
        $content = file_get_contents($cemetery_functions);
        if (strpos($content, 'getDBConnection()') !== false) {
            echo "   ✓ Cemetery also uses getDBConnection()\n";
        } else if (strpos($content, 'new PDO') !== false) {
            echo "   ⚠ Cemetery creates its own PDO connection\n";
        } else {
            echo "   ℹ Cemetery might use different method\n";
        }
    }
}

echo "</pre>";
?>