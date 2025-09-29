<?php
/**
 * Load Project API
 * Location: /dashboard/dashboards/printPDF/api/load-project.php
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
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-CSRF-Token');

// Handle OPTIONS request for CORS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Helper function to check permissions
function checkLoadPermission() {
    // If checkPermission function exists from config.php, use it
    if (function_exists('checkPermission')) {
        return checkPermission('view', 'pdf_editor');
    }
    // Otherwise allow for now
    return true;
}

// Check permissions
if (!checkLoadPermission()) {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'message' => 'אין לך הרשאה לטעון פרויקטים'
    ]);
    exit();
}

try {
    // Get project ID from request
    $projectId = null;
    
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $projectId = $_GET['id'] ?? null;
    } else if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        $projectId = $input['projectId'] ?? null;
    }
    
    if (!$projectId) {
        throw new Exception('מזהה פרויקט חסר');
    }
    
    // Sanitize project ID
    $projectId = preg_replace('/[^a-zA-Z0-9_-]/', '', $projectId);
    
    // Get user ID
    $userId = $_SESSION['user_id'] ?? 'guest';
    
    // Build project file path
    $projectsPath = defined('PROJECTS_PATH') ? PROJECTS_PATH : dirname(__DIR__) . '/projects/';
    $projectFile = $projectsPath . $userId . '/' . $projectId . '.json';
    
    // Check if project exists
    if (!file_exists($projectFile)) {
        // Try to find in shared projects
        $sharedFile = $projectsPath . 'shared/' . $projectId . '.json';
        if (file_exists($sharedFile)) {
            $projectFile = $sharedFile;
        } else {
            throw new Exception('הפרויקט לא נמצא');
        }
    }
    
    // Load project data
    $projectData = json_decode(file_get_contents($projectFile), true);
    
    if (!$projectData) {
        throw new Exception('נתוני הפרויקט פגומים');
    }
    
    // Return success response
    echo json_encode([
        'success' => true,
        'message' => 'הפרויקט נטען בהצלחה',
        'data' => [
            'project' => $projectData
        ]
    ]);
    
} catch (Exception $e) {
    error_log("Load project error: " . $e->getMessage());
    http_response_code(404);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

/**
 * Optional: Load project from database
 */
function loadProjectFromDatabase($projectId, $userId) {
    // If you have a database connection, implement loading here
    // Example:
    /*
    global $db;
    
    $stmt = $db->prepare("
        SELECT * FROM pdf_projects 
        WHERE id = ? AND (user_id = ? OR is_shared = 1)
        LIMIT 1
    ");
    
    $stmt->execute([$projectId, $userId]);
    $project = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($project) {
        return json_decode($project['data'], true);
    }
    
    return null;
    */
    return null;
}

/**
 * Get list of user's projects
 */
function getUserProjects($userId) {
    $projectsPath = defined('PROJECTS_PATH') ? PROJECTS_PATH : dirname(__DIR__) . '/projects/';
    $userPath = $projectsPath . $userId . '/';
    
    $projects = [];
    
    if (is_dir($userPath)) {
        $files = glob($userPath . '*.json');
        foreach ($files as $file) {
            $data = json_decode(file_get_contents($file), true);
            if ($data) {
                $projects[] = [
                    'id' => $data['id'] ?? basename($file, '.json'),
                    'name' => $data['name'] ?? 'Untitled',
                    'updated_at' => $data['updated_at'] ?? filemtime($file),
                    'created_at' => $data['created_at'] ?? filectime($file)
                ];
            }
        }
    }
    
    return $projects;
}
?>