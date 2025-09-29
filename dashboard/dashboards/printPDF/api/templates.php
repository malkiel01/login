<?php
/**
 * Templates API
 * Location: /dashboard/dashboards/printPDF/api/templates.php
 */

// Include configuration
require_once $_SERVER['DOCUMENT_ROOT'] . '/dashboard/dashboards/printPDF/config.php';

// Check user permission
if (!checkPermission('view', 'pdf_editor')) {
    http_response_code(403);
    die(json_encode([
        'success' => false,
        'message' => 'אין הרשאה לגשת לתבניות'
    ]));
}

// Set headers
header('Content-Type: application/json');

// Get request method and action
$method = $_SERVER['REQUEST_METHOD'];
$action = $_POST['action'] ?? $_GET['action'] ?? '';

try {
    switch ($method) {
        case 'GET':
            // Get templates list
            $category = $_GET['category'] ?? 'all';
            $templates = getTemplates($category);
            echo json_encode([
                'success' => true,
                'data' => [
                    'templates' => $templates
                ]
            ]);
            break;
            
        case 'POST':
            // Handle different actions
            $input = json_decode(file_get_contents('php://input'), true);
            
            switch ($action) {
                case 'save':
                    // Save new template
                    if (!checkPermission('create', 'pdf_editor')) {
                        throw new Exception('אין הרשאה ליצור תבניות');
                    }
                    
                    $result = saveTemplate($input['template'] ?? []);
                    echo json_encode([
                        'success' => true,
                        'data' => $result
                    ]);
                    break;
                    
                case 'update':
                    // Update existing template
                    if (!checkPermission('edit', 'pdf_editor')) {
                        throw new Exception('אין הרשאה לערוך תבניות');
                    }
                    
                    $result = updateTemplate($input['templateId'] ?? '', $input['template'] ?? []);
                    echo json_encode([
                        'success' => true,
                        'data' => $result
                    ]);
                    break;
                    
                case 'delete':
                    // Delete template
                    if (!checkPermission('delete', 'pdf_editor')) {
                        throw new Exception('אין הרשאה למחוק תבניות');
                    }
                    
                    $result = deleteTemplate($input['templateId'] ?? '');
                    echo json_encode([
                        'success' => true,
                        'data' => $result
                    ]);
                    break;
                    
                case 'load':
                    // Load template data
                    $result = loadTemplate($input['templateId'] ?? '');
                    echo json_encode([
                        'success' => true,
                        'data' => [
                            'template' => $result
                        ]
                    ]);
                    break;
                    
                default:
                    throw new Exception('Invalid action');
            }
            break;
            
        default:
            throw new Exception('Method not allowed');
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

/**
 * Get templates list
 */
function getTemplates($category = 'all') {
    $templates = [];
    
    // Get default templates
    $defaultTemplates = getDefaultTemplates();
    
    // Get user templates from database
    $userTemplates = getUserTemplates($category);
    
    // Merge templates
    $templates = array_merge($defaultTemplates, $userTemplates);
    
    // Filter by category if needed
    if ($category !== 'all') {
        $templates = array_filter($templates, function($template) use ($category) {
            return $template['category'] === $category;
        });
    }
    
    return array_values($templates);
}

/**
 * Get default templates
 */
function getDefaultTemplates() {
    return [
        [
            'id' => 'blank',
            'name' => 'דף ריק',
            'category' => 'business',
            'thumbnail' => null,
            'isDefault' => true,
            'data' => [
                'version' => '5.3.0',
                'objects' => []
            ]
        ],
        [
            'id' => 'invoice',
            'name' => 'חשבונית',
            'category' => 'invoices',
            'thumbnail' => null,
            'isDefault' => true,
            'data' => [
                'version' => '5.3.0',
                'objects' => [
                    [
                        'type' => 'text',
                        'text' => 'חשבונית מס',
                        'fontSize' => 30,
                        'fontWeight' => 'bold',
                        'left' => 50,
                        'top' => 50
                    ],
                    [
                        'type' => 'text',
                        'text' => 'מספר: _______',
                        'fontSize' => 16,
                        'left' => 50,
                        'top' => 100
                    ],
                    [
                        'type' => 'text',
                        'text' => 'תאריך: _______',
                        'fontSize' => 16,
                        'left' => 50,
                        'top' => 130
                    ]
                ]
            ]
        ],
        [
            'id' => 'certificate',
            'name' => 'תעודת הוקרה',
            'category' => 'certificates',
            'thumbnail' => null,
            'isDefault' => true,
            'data' => [
                'version' => '5.3.0',
                'objects' => [
                    [
                        'type' => 'text',
                        'text' => 'תעודת הוקרה',
                        'fontSize' => 36,
                        'fontWeight' => 'bold',
                        'left' => 297,
                        'top' => 100,
                        'originX' => 'center'
                    ],
                    [
                        'type' => 'text',
                        'text' => 'מוענקת בזאת ל',
                        'fontSize' => 20,
                        'left' => 297,
                        'top' => 200,
                        'originX' => 'center'
                    ]
                ]
            ]
        ],
        [
            'id' => 'letterhead',
            'name' => 'נייר מכתבים',
            'category' => 'letterhead',
            'thumbnail' => null,
            'isDefault' => true,
            'data' => [
                'version' => '5.3.0',
                'objects' => [
                    [
                        'type' => 'rect',
                        'width' => 595,
                        'height' => 80,
                        'left' => 0,
                        'top' => 0,
                        'fill' => '#667eea'
                    ],
                    [
                        'type' => 'text',
                        'text' => 'שם החברה',
                        'fontSize' => 24,
                        'fontWeight' => 'bold',
                        'fill' => '#ffffff',
                        'left' => 50,
                        'top' => 25
                    ]
                ]
            ]
        ],
        [
            'id' => 'receipt',
            'name' => 'קבלה',
            'category' => 'receipts',
            'thumbnail' => null,
            'isDefault' => true,
            'data' => [
                'version' => '5.3.0',
                'objects' => [
                    [
                        'type' => 'text',
                        'text' => 'קבלה',
                        'fontSize' => 28,
                        'fontWeight' => 'bold',
                        'left' => 50,
                        'top' => 30
                    ],
                    [
                        'type' => 'text',
                        'text' => 'מספר קבלה: _______',
                        'fontSize' => 14,
                        'left' => 50,
                        'top' => 80
                    ]
                ]
            ]
        ]
    ];
}

/**
 * Get user templates from database
 */
function getUserTemplates($category = 'all') {
    try {
        $db = getPDFEditorDB();
        $userId = $_SESSION['user_id'] ?? 0;
        
        $sql = "SELECT * FROM " . DB_PREFIX . "projects 
                WHERE user_id = ? AND is_template = 1";
        
        $params = [$userId];
        
        if ($category !== 'all') {
            $sql .= " AND template_category = ?";
            $params[] = $category;
        }
        
        $sql .= " ORDER BY created_at DESC";
        
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        
        $templates = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $templates[] = [
                'id' => $row['project_id'],
                'name' => $row['name'],
                'category' => $row['template_category'],
                'thumbnail' => $row['thumbnail'],
                'isDefault' => false,
                'created' => $row['created_at'],
                'updated' => $row['updated_at']
            ];
        }
        
        return $templates;
        
    } catch (Exception $e) {
        error_log('Failed to get user templates: ' . $e->getMessage());
        return [];
    }
}

/**
 * Save new template
 */
function saveTemplate($templateData) {
    if (empty($templateData)) {
        throw new Exception('Template data is required');
    }
    
    $db = getPDFEditorDB();
    $userId = $_SESSION['user_id'] ?? 0;
    
    $templateId = 'template_' . uniqid();
    $name = $templateData['name'] ?? 'תבנית ללא שם';
    $category = $templateData['category'] ?? 'custom';
    $data = json_encode($templateData['data'] ?? []);
    $thumbnail = $templateData['thumbnail'] ?? '';
    
    $stmt = $db->prepare("
        INSERT INTO " . DB_PREFIX . "projects 
        (project_id, user_id, name, data, thumbnail, is_template, template_category, created_at, updated_at) 
        VALUES (?, ?, ?, ?, ?, 1, ?, NOW(), NOW())
    ");
    
    $stmt->execute([$templateId, $userId, $name, $data, $thumbnail, $category]);
    
    return [
        'templateId' => $templateId,
        'message' => 'התבנית נשמרה בהצלחה'
    ];
}

/**
 * Update existing template
 */
function updateTemplate($templateId, $templateData) {
    if (empty($templateId)) {
        throw new Exception('Template ID is required');
    }
    
    $db = getPDFEditorDB();
    $userId = $_SESSION['user_id'] ?? 0;
    
    // Check ownership
    $stmt = $db->prepare("
        SELECT id FROM " . DB_PREFIX . "projects 
        WHERE project_id = ? AND user_id = ? AND is_template = 1
    ");
    $stmt->execute([$templateId, $userId]);
    
    if (!$stmt->fetch()) {
        throw new Exception('Template not found or access denied');
    }
    
    // Update template
    $name = $templateData['name'] ?? null;
    $category = $templateData['category'] ?? null;
    $data = isset($templateData['data']) ? json_encode($templateData['data']) : null;
    $thumbnail = $templateData['thumbnail'] ?? null;
    
    $updates = [];
    $params = [];
    
    if ($name !== null) {
        $updates[] = "name = ?";
        $params[] = $name;
    }
    if ($category !== null) {
        $updates[] = "template_category = ?";
        $params[] = $category;
    }
    if ($data !== null) {
        $updates[] = "data = ?";
        $params[] = $data;
    }
    if ($thumbnail !== null) {
        $updates[] = "thumbnail = ?";
        $params[] = $thumbnail;
    }
    
    if (empty($updates)) {
        throw new Exception('No updates provided');
    }
    
    $updates[] = "updated_at = NOW()";
    $params[] = $templateId;
    $params[] = $userId;
    
    $sql = "UPDATE " . DB_PREFIX . "projects 
            SET " . implode(', ', $updates) . " 
            WHERE project_id = ? AND user_id = ?";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    
    return [
        'message' => 'התבנית עודכנה בהצלחה'
    ];
}

/**
 * Delete template
 */
function deleteTemplate($templateId) {
    if (empty($templateId)) {
        throw new Exception('Template ID is required');
    }
    
    // Don't allow deleting default templates
    $defaultIds = ['blank', 'invoice', 'certificate', 'letterhead', 'receipt'];
    if (in_array($templateId, $defaultIds)) {
        throw new Exception('לא ניתן למחוק תבניות ברירת מחדל');
    }
    
    $db = getPDFEditorDB();
    $userId = $_SESSION['user_id'] ?? 0;
    
    $stmt = $db->prepare("
        DELETE FROM " . DB_PREFIX . "projects 
        WHERE project_id = ? AND user_id = ? AND is_template = 1
    ");
    
    $stmt->execute([$templateId, $userId]);
    
    if ($stmt->rowCount() === 0) {
        throw new Exception('Template not found or access denied');
    }
    
    return [
        'message' => 'התבנית נמחקה בהצלחה'
    ];
}

/**
 * Load template data
 */
function loadTemplate($templateId) {
    if (empty($templateId)) {
        throw new Exception('Template ID is required');
    }
    
    // Check if it's a default template
    $defaultTemplates = getDefaultTemplates();
    foreach ($defaultTemplates as $template) {
        if ($template['id'] === $templateId) {
            return $template['data'];
        }
    }
    
    // Load from database
    $db = getPDFEditorDB();
    $userId = $_SESSION['user_id'] ?? 0;
    
    $stmt = $db->prepare("
        SELECT data FROM " . DB_PREFIX . "projects 
        WHERE project_id = ? AND user_id = ? AND is_template = 1
    ");
    
    $stmt->execute([$templateId, $userId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$row) {
        throw new Exception('Template not found');
    }
    
    return json_decode($row['data'], true);
}
?>