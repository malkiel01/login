<?php
/**
 * Cloud Save API
 * Location: /dashboard/dashboards/printPDF/api/cloud-save.php
 */

// Include configuration
require_once '../config.php';

// Check user access
checkUserAccess();

// Set headers
header('Content-Type: application/json');
header('Access-Control-Allow-Methods: POST');

// Check request method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    die(json_encode([
        'success' => false,
        'message' => 'Method not allowed'
    ]));
}

// Get input
$input = json_decode(file_get_contents('php://input'), true);

// Verify CSRF token
$csrfToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (!verifyCSRFToken($csrfToken)) {
    http_response_code(403);
    die(json_encode([
        'success' => false,
        'message' => 'Invalid CSRF token'
    ]));
}

// Get action
$action = $input['action'] ?? '';
$userId = $_SESSION['user_id'] ?? 0;

// Get database connection
$db = getPDFEditorDB();

try {
    switch ($action) {
        case 'save':
            $result = saveProject($db, $userId, $input);
            break;
            
        case 'load':
            $result = loadProject($db, $userId, $input['projectId'] ?? '');
            break;
            
        case 'list':
            $result = listProjects($db, $userId, $input['page'] ?? 1, $input['limit'] ?? 20);
            break;
            
        case 'delete':
            $result = deleteProject($db, $userId, $input['projectId'] ?? '');
            break;
            
        case 'share':
            $result = shareProject($db, $userId, $input['projectId'] ?? '', $input['expiresIn'] ?? 604800);
            break;
            
        case 'loadShared':
            $result = loadSharedProject($db, $input['shareToken'] ?? '');
            break;
            
        default:
            throw new Exception('Invalid action');
    }
    
    echo json_encode([
        'success' => true,
        'data' => $result
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

/**
 * Save project to database
 */
function saveProject($db, $userId, $data) {
    $project = $data['project'] ?? [];
    
    if (empty($project)) {
        throw new Exception('Project data is required');
    }
    
    // Generate project ID if new
    $projectId = $project['id'] ?? 'project_' . uniqid();
    $name = $project['name'] ?? 'Untitled';
    $projectData = json_encode($project);
    
    // Generate thumbnail if canvas data exists
    $thumbnail = '';
    if (isset($project['canvas'])) {
        $thumbnail = generateThumbnail($project['canvas']);
    }
    
    // Check if project exists
    $stmt = $db->prepare("SELECT id FROM " . DB_PREFIX . "projects WHERE project_id = ? AND user_id = ?");
    $stmt->execute([$projectId, $userId]);
    $exists = $stmt->fetch();
    
    if ($exists) {
        // Update existing project
        $stmt = $db->prepare("
            UPDATE " . DB_PREFIX . "projects 
            SET name = ?, data = ?, thumbnail = ?, updated_at = NOW() 
            WHERE project_id = ? AND user_id = ?
        ");
        $stmt->execute([$name, $projectData, $thumbnail, $projectId, $userId]);
    } else {
        // Insert new project
        $stmt = $db->prepare("
            INSERT INTO " . DB_PREFIX . "projects 
            (project_id, user_id, name, data, thumbnail, created_at, updated_at) 
            VALUES (?, ?, ?, ?, ?, NOW(), NOW())
        ");
        $stmt->execute([$projectId, $userId, $name, $projectData, $thumbnail]);
    }
    
    // Save to autosave table
    $stmt = $db->prepare("
        INSERT INTO " . DB_PREFIX . "autosave 
        (project_id, state_data, saved_at) 
        VALUES (?, ?, NOW())
    ");
    $stmt->execute([$projectId, $projectData]);
    
    // Clean old autosaves (keep last 10)
    $stmt = $db->prepare("
        DELETE FROM " . DB_PREFIX . "autosave 
        WHERE project_id = ? 
        ORDER BY saved_at DESC 
        LIMIT 1000 OFFSET 10
    ");
    $stmt->execute([$projectId]);
    
    return [
        'projectId' => $projectId,
        'message' => 'Project saved successfully'
    ];
}

/**
 * Load project from database
 */
function loadProject($db, $userId, $projectId) {
    if (empty($projectId)) {
        throw new Exception('Project ID is required');
    }
    
    $stmt = $db->prepare("
        SELECT project_id, name, data, thumbnail, created_at, updated_at 
        FROM " . DB_PREFIX . "projects 
        WHERE project_id = ? AND user_id = ?
    ");
    $stmt->execute([$projectId, $userId]);
    $project = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$project) {
        throw new Exception('Project not found');
    }
    
    // Parse JSON data
    $project['data'] = json_decode($project['data'], true);
    
    return [
        'project' => $project['data']
    ];
}

/**
 * List user projects
 */
function listProjects($db, $userId, $page = 1, $limit = 20) {
    $offset = ($page - 1) * $limit;
    
    // Get total count
    $stmt = $db->prepare("
        SELECT COUNT(*) as total 
        FROM " . DB_PREFIX . "projects 
        WHERE user_id = ? AND is_template = 0
    ");
    $stmt->execute([$userId]);
    $total = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Get projects
    $stmt = $db->prepare("
        SELECT project_id, name, thumbnail, created_at, updated_at 
        FROM " . DB_PREFIX . "projects 
        WHERE user_id = ? AND is_template = 0 
        ORDER BY updated_at DESC 
        LIMIT ? OFFSET ?
    ");
    $stmt->bindValue(1, $userId, PDO::PARAM_INT);
    $stmt->bindValue(2, $limit, PDO::PARAM_INT);
    $stmt->bindValue(3, $offset, PDO::PARAM_INT);
    $stmt->execute();
    
    $projects = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    return [
        'projects' => $projects,
        'total' => $total,
        'page' => $page,
        'pages' => ceil($total / $limit)
    ];
}

/**
 * Delete project
 */
function deleteProject($db, $userId, $projectId) {
    if (empty($projectId)) {
        throw new Exception('Project ID is required');
    }
    
    // Check ownership
    $stmt = $db->prepare("
        SELECT id FROM " . DB_PREFIX . "projects 
        WHERE project_id = ? AND user_id = ?
    ");
    $stmt->execute([$projectId, $userId]);
    $project = $stmt->fetch();
    
    if (!$project) {
        throw new Exception('Project not found or access denied');
    }
    
    // Delete project (cascades to shares and autosaves)
    $stmt = $db->prepare("
        DELETE FROM " . DB_PREFIX . "projects 
        WHERE project_id = ? AND user_id = ?
    ");
    $stmt->execute([$projectId, $userId]);
    
    return [
        'message' => 'Project deleted successfully'
    ];
}

/**
 * Share project
 */
function shareProject($db, $userId, $projectId, $expiresIn = 604800) {
    if (empty($projectId)) {
        throw new Exception('Project ID is required');
    }
    
    // Check ownership
    $stmt = $db->prepare("
        SELECT id FROM " . DB_PREFIX . "projects 
        WHERE project_id = ? AND user_id = ?
    ");
    $stmt->execute([$projectId, $userId]);
    $project = $stmt->fetch();
    
    if (!$project) {
        throw new Exception('Project not found or access denied');
    }
    
    // Generate share token
    $shareToken = bin2hex(random_bytes(16));
    
    // Calculate expiration
    $expiresAt = $expiresIn > 0 ? date('Y-m-d H:i:s', time() + $expiresIn) : null;
    
    // Save share
    $stmt = $db->prepare("
        INSERT INTO " . DB_PREFIX . "shares 
        (project_id, share_token, expires_at, created_at) 
        VALUES (?, ?, ?, NOW())
    ");
    $stmt->execute([$projectId, $shareToken, $expiresAt]);
    
    // Generate share URL
    $shareUrl = PDF_EDITOR_URL . '?share=' . $shareToken;
    
    return [
        'shareToken' => $shareToken,
        'shareUrl' => $shareUrl,
        'expiresAt' => $expiresAt
    ];
}

/**
 * Load shared project
 */
function loadSharedProject($db, $shareToken) {
    if (empty($shareToken)) {
        throw new Exception('Share token is required');
    }
    
    // Get share info
    $stmt = $db->prepare("
        SELECT s.project_id, s.expires_at, p.data 
        FROM " . DB_PREFIX . "shares s 
        JOIN " . DB_PREFIX . "projects p ON s.project_id = p.project_id 
        WHERE s.share_token = ?
    ");
    $stmt->execute([$shareToken]);
    $share = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$share) {
        throw new Exception('Invalid share token');
    }
    
    // Check expiration
    if ($share['expires_at'] && strtotime($share['expires_at']) < time()) {
        throw new Exception('Share link has expired');
    }
    
    // Parse project data
    $projectData = json_decode($share['data'], true);
    
    return [
        'project' => $projectData
    ];
}

/**
 * Generate thumbnail from canvas data
 */
function generateThumbnail($canvasData) {
    // This is a simplified version
    // In production, you would generate an actual image thumbnail
    // For now, we'll return a placeholder or extract first image
    
    if (isset($canvasData['backgroundImage'])) {
        return $canvasData['backgroundImage']['src'] ?? '';
    }
    
    // Look for first image object
    if (isset($canvasData['objects'])) {
        foreach ($canvasData['objects'] as $object) {
            if ($object['type'] === 'image' && isset($object['src'])) {
                return $object['src'];
            }
        }
    }
    
    return ''; // No thumbnail
}

?>