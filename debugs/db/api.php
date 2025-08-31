<?php
// api.php - Database to JSON API
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't show errors in response

// Only allow POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['error' => true, 'message' => 'רק POST requests מותרים']);
    exit;
}

// Get POST data
$input = json_decode(file_get_contents('php://input'), true) ?: $_POST;

$dbType = $input['dbType'] ?? '';
$host = $input['host'] ?? '';
$port = $input['port'] ?? '';
$database = $input['database'] ?? '';
$username = $input['username'] ?? '';
$password = $input['password'] ?? '';
$query = trim($input['query'] ?? '');

// Validate required fields
if (empty($dbType) || empty($host) || empty($port) || empty($database) || empty($username) || empty($query)) {
    echo json_encode(['error' => true, 'message' => 'חסרים שדות נדרשים']);
    exit;
}

try {
    // Create connection
    $pdo = createConnection($dbType, $host, $port, $database, $username, $password);
    
    // Test connection
    $testStmt = $pdo->prepare("SELECT 1 as test");
    $testStmt->execute();
    $testResult = $testStmt->fetch();
    
    if (!$testResult) {
        throw new Exception('מבחן החיבור נכשל');
    }
    
    // Get data
    $finalQuery = prepareQuery($query);
    $stmt = $pdo->prepare($finalQuery);
    $stmt->execute();
    $results = $stmt->fetchAll();
    
    // Return JSON
    echo json_encode($results, JSON_UNESCAPED_UNICODE);
    
} catch (PDOException $e) {
    echo json_encode([
        'error' => true, 
        'message' => 'שגיאת חיבור למסד הנתונים: ' . $e->getMessage()
    ]);
} catch (Exception $e) {
    echo json_encode([
        'error' => true, 
        'message' => $e->getMessage()
    ]);
}

function createConnection($dbType, $host, $port, $database, $username, $password) {
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_TIMEOUT => 300
    ];
    
    switch ($dbType) {
        case 'mysql':
            $dsn = "mysql:host={$host};port={$port};dbname={$database};charset=utf8mb4";
            $options[PDO::MYSQL_ATTR_USE_BUFFERED_QUERY] = false;
            break;
            
        case 'postgresql':
            $dsn = "pgsql:host={$host};port={$port};dbname={$database}";
            break;
            
        case 'sqlserver':
            $dsn = "sqlsrv:Server={$host},{$port};Database={$database}";
            break;
            
        default:
            throw new Exception("סוג מסד נתונים לא נתמך: {$dbType}");
    }
    
    return new PDO($dsn, $username, $password, $options);
}

function prepareQuery($query) {
    $query = trim($query);
    
    // If it's just a table/view name, create SELECT query
    if (!preg_match('/^\s*SELECT/i', $query)) {
        // Sanitize table name
        $tableName = preg_replace('/[^a-zA-Z0-9_]/', '', $query);
        return "SELECT * FROM `{$tableName}`";
    }
    
    return $query;
}
?>