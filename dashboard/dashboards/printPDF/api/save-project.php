<?php
/**
 * Save Project API
 * Location: /dashboard/dashboards/printPDF/api/save-project.php
 */

// Error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include configuration
$configPath = dirname(__DIR__) . '/config.php';
if (file_exists($configPath)) {
    require_once $configPath;
} else {
    // Basic configuration if config.php doesn't exist
    define('PROJECTS_PATH', dirname(__DIR__) . '/projects/');
}

// Set headers
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-CSRF-Token');

// Handle OPTIONS request for CORS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Check request method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Method not allowed'
    ]);
    exit();
}

// Get input data
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid input data'
    ]);
    exit();
}

// Helper function to check permissions
function checkSavePermission() {
    // If checkPermission function exists from config.php, use it
    if (function_exists('checkPermission')) {
        return checkPermission('edit', 'pdf_editor');
    }
    // Otherwise allow for now
    return true;
}

// Helper function to verify CSRF token
function verifySaveCSRFToken($token) {
    // If verifyCSRFToken function exists from config.php, use it
    if (function_exists('verifyCSRFToken')) {
        return verifyCSRFToken($token);
    }
    // Basic CSRF check
    if (!isset($_SESSION['csrf_token'])) {
        return true; // Allow if no token in session
    }
    return $token === $_SESSION['csrf_token'];
}

// Check permissions
if (!checkSavePermission()) {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'message' => 'אין לך הרשאה לשמור פרויקטים'
    ]);
    exit();
}

// Verify CSRF token
$csrfToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (!verifySaveCSRFToken($csrfToken)) {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'message' => 'Invalid CSRF token'
    ]);
    exit();
}

try {
    // Extract project data
    $projectData = $input['project'] ?? $input;
    
    if (!$projectData || !isset($projectData['id'])) {
        throw new Exception('נתוני הפרויקט חסרים');
    }
    
    // Get user ID
    $userId = $_SESSION['user_id'] ?? 'guest';
    
    // Create projects directory if it doesn't exist
    $projectsPath = defined('PROJECTS_PATH') ? PROJECTS_PATH : dirname(__DIR__) . '/projects/';
    $userPath = $projectsPath . $userId . '/';
    
    if (!is_dir($userPath)) {
        if (!mkdir($userPath, 0755, true)) {
            error_log("Failed to create directory: $userPath");
            throw new Exception('לא ניתן ליצור תיקיית פרויקטים');
        }
    }
    
    // Prepare project data
    $projectId = preg_replace('/[^a-zA-Z0-9_-]/', '', $projectData['id']);
    $projectFile = $userPath . $projectId . '.json';
    
    // Add metadata
    $projectData['updated_at'] = date('Y-m-d H:i:s');
    $projectData['user_id'] = $userId;
    
    // If it's a new project
    if (!file_exists($projectFile)) {
        $projectData['created_at'] = date('Y-m-d H:i:s');
    } else {
        // Keep original creation date
        $existingData = json_decode(file_get_contents($projectFile), true);
        if ($existingData && isset($existingData['created_at'])) {
            $projectData['created_at'] = $existingData['created_at'];
        }
    }
    
    // Save project to file
    $json = json_encode($projectData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    if (file_put_contents($projectFile, $json) === false) {
        error_log("Failed to save project to: $projectFile");
        throw new Exception('שגיאה בשמירת הפרויקט');
    }
    
    // Optional: Save to database if you have one
    // saveProjectToDatabase($projectData);
    
    // Return success response
    echo json_encode([
        'success' => true,
        'message' => 'הפרויקט נשמר בהצלחה',
        'data' => [
            'projectId' => $projectId,
            'filename' => $projectId . '.json',
            'path' => $projectFile,
            'updated_at' => $projectData['updated_at']
        ]
    ]);
    
} catch (Exception $e) {
    error_log("Save project error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

/**
 * Optional: Save project to database
 */
function saveProjectToDatabase($projectData) {
    // If you have a database connection, implement saving here
    // Example:
    /*
    global $db;
    
    $stmt = $db->prepare("
        INSERT INTO pdf_projects (id, user_id, name, data, created_at, updated_at) 
        VALUES (?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE 
        name = VALUES(name),
        data = VALUES(data),
        updated_at = VALUES(updated_at)
    ");
    
    $stmt->execute([
        $projectData['id'],
        $projectData['user_id'],
        $projectData['name'] ?? 'Untitled',
        json_encode($projectData),
        $projectData['created_at'],
        $projectData['updated_at']
    ]);
    */
}
?>