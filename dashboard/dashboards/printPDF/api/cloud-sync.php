<?php
/**
 * Cloud Sync API
 * Location: /dashboard/dashboards/printPDF/api/cloud-sync.php
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

if (!$input || !isset($input['action'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid input data'
    ]);
    exit();
}

// Get user ID
$userId = $_SESSION['user_id'] ?? 'guest';

// Process action
$action = $input['action'];

try {
    switch ($action) {
        case 'save':
            $result = saveProject($input, $userId);
            break;
            
        case 'load':
            $result = loadProject($input['projectId'], $userId);
            break;
            
        case 'list':
            $result = listProjects($userId, $input['page'] ?? 1, $input['limit'] ?? 20);
            break;
            
        case 'delete':
            $result = deleteProject($input['projectId'], $userId);
            break;
            
        case 'share':
            $result = shareProject($input['projectId'], $userId, $input['expiresIn'] ?? 604800);
            break;
            
        case 'loadShared':
            $result = loadSharedProject($input['shareToken']);
            break;
            
        default:
            throw new Exception('Invalid action');
    }
    
    echo json_encode($result);
    
} catch (Exception $e) {
    error_log("Cloud sync error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

/**
 * Save project
 */
function saveProject($input, $userId) {
    $projectData = $input['project'] ?? $input;
    
    if (!$projectData || !isset($projectData['id'])) {
        throw new Exception('Project data missing');
    }
    
    $projectsPath = defined('PROJECTS_PATH') ? PROJECTS_PATH : dirname(__DIR__) . '/projects/';
    $userPath = $projectsPath . $userId . '/';
    
    if (!is_dir($userPath)) {
        if (!mkdir($userPath, 0755, true)) {
            throw new Exception('Failed to create projects directory');
        }
    }
    
    $projectId = preg_replace('/[^a-zA-Z0-9_-]/', '', $projectData['id']);
    $projectFile = $userPath . $projectId . '.json';
    
    $projectData['updated_at'] = date('Y-m-d H:i:s');
    $projectData['user_id'] = $userId;
    
    if (!file_exists($projectFile)) {
        $projectData['created_at'] = date('Y-m-d H:i:s');
    }
    
    $json = json_encode($projectData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    if (file_put_contents($projectFile, $json) === false) {
        throw new Exception('Failed to save project');
    }
    
    return [
        'success' => true,
        'message' => 'Project saved successfully',
        'data' => [
            'projectId' => $projectId,
            'updated_at' => $projectData['updated_at']
        ]
    ];
}

/**
 * Load project
 */
function loadProject($projectId, $userId) {
    if (!$projectId) {
        throw new Exception('Project ID missing');
    }
    
    $projectId = preg_replace('/[^a-zA-Z0-9_-]/', '', $projectId);
    
    $projectsPath = defined('PROJECTS_PATH') ? PROJECTS_PATH : dirname(__DIR__) . '/projects/';
    $projectFile = $projectsPath . $userId . '/' . $projectId . '.json';
    
    if (!file_exists($projectFile)) {
        throw new Exception('Project not found');
    }
    
    $projectData = json_decode(file_get_contents($projectFile), true);
    
    if (!$projectData) {
        throw new Exception('Invalid project data');
    }
    
    return [
        'success' => true,
        'message' => 'Project loaded successfully',
        'data' => [
            'project' => $projectData
        ]
    ];
}

/**
 * List projects
 */
function listProjects($userId, $page = 1, $limit = 20) {
    $projectsPath = defined('PROJECTS_PATH') ? PROJECTS_PATH : dirname(__DIR__) . '/projects/';
    $userPath = $projectsPath . $userId . '/';
    
    $projects = [];
    
    if (is_dir($userPath)) {
        $files = glob($userPath . '*.json');
        
        // Sort by modification time (newest first)
        usort($files, function($a, $b) {
            return filemtime($b) - filemtime($a);
        });
        
        // Paginate
        $offset = ($page - 1) * $limit;
        $files = array_slice($files, $offset, $limit);
        
        foreach ($files as $file) {
            $data = json_decode(file_get_contents($file), true);
            if ($data) {
                $projects[] = [
                    'id' => $data['id'] ?? basename($file, '.json'),
                    'name' => $data['name'] ?? 'Untitled',
                    'updated' => $data['updated_at'] ?? date('Y-m-d H:i:s', filemtime($file)),
                    'created' => $data['created_at'] ?? date('Y-m-d H:i:s', filectime($file)),
                    'thumbnail' => $data['thumbnail'] ?? null
                ];
            }
        }
    }
    
    return [
        'success' => true,
        'data' => [
            'projects' => $projects,
            'page' => $page,
            'limit' => $limit,
            'total' => count(glob($userPath . '*.json'))
        ]
    ];
}

/**
 * Delete project
 */
function deleteProject($projectId, $userId) {
    if (!$projectId) {
        throw new Exception('Project ID missing');
    }
    
    $projectId = preg_replace('/[^a-zA-Z0-9_-]/', '', $projectId);
    
    $projectsPath = defined('PROJECTS_PATH') ? PROJECTS_PATH : dirname(__DIR__) . '/projects/';
    $projectFile = $projectsPath . $userId . '/' . $projectId . '.json';
    
    if (!file_exists($projectFile)) {
        throw new Exception('Project not found');
    }
    
    if (!unlink($projectFile)) {
        throw new Exception('Failed to delete project');
    }
    
    return [
        'success' => true,
        'message' => 'Project deleted successfully'
    ];
}

/**
 * Share project
 */
function shareProject($projectId, $userId, $expiresIn = 604800) {
    if (!$projectId) {
        throw new Exception('Project ID missing');
    }
    
    $shareToken = bin2hex(random_bytes(16));
    $shareUrl = '/dashboard/dashboards/printPDF/shared.php?token=' . $shareToken;
    
    // Save share info
    $shareData = [
        'token' => $shareToken,
        'project_id' => $projectId,
        'user_id' => $userId,
        'created_at' => time(),
        'expires_at' => time() + $expiresIn
    ];
    
    $sharesPath = defined('PROJECTS_PATH') ? PROJECTS_PATH : dirname(__DIR__) . '/projects/';
    $sharesPath .= 'shares/';
    
    if (!is_dir($sharesPath)) {
        mkdir($sharesPath, 0755, true);
    }
    
    file_put_contents($sharesPath . $shareToken . '.json', json_encode($shareData));
    
    return [
        'success' => true,
        'data' => [
            'shareUrl' => $shareUrl,
            'shareToken' => $shareToken,
            'expiresAt' => date('Y-m-d H:i:s', $shareData['expires_at'])
        ]
    ];
}

/**
 * Load shared project
 */
function loadSharedProject($shareToken) {
    if (!$shareToken) {
        throw new Exception('Share token missing');
    }
    
    $sharesPath = defined('PROJECTS_PATH') ? PROJECTS_PATH : dirname(__DIR__) . '/projects/';
    $shareFile = $sharesPath . 'shares/' . $shareToken . '.json';
    
    if (!file_exists($shareFile)) {
        throw new Exception('Invalid or expired share link');
    }
    
    $shareData = json_decode(file_get_contents($shareFile), true);
    
    // Check if expired
    if ($shareData['expires_at'] < time()) {
        unlink($shareFile);
        throw new Exception('Share link has expired');
    }
    
    // Load the actual project
    return loadProject($shareData['project_id'], $shareData['user_id']);
}
?>