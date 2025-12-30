<?php
/*
 * File: api/get-config.php
 * Version: 3.0.0
 * Updated: 2025-12-10
 * Author: Malkiel
 * Description: API endpoint לקבלת קונפיגורציה מ-cemetery-hierarchy-config.php
 * Change Summary:
 * - v3.0.0: תמיכה מלאה ב-Entity Framework
 *   ✅ הוספת section=entity להחזרת קונפיג מלא בפורמט Entity Framework
 *   ✅ המרת title ל-label בעמודות
 *   ✅ הוספת section=all להחזרת כל הסקשנים
 *   ✅ תמיכה במספר entities בבקשה אחת
 * - v2.0.0: שיפור error handling ו-validation
 * - v1.0.0: גרסה ראשונית
 */

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, must-revalidate');

// פונקציה לשליחת תשובת error
function sendError($message, $code = 400, $details = null) {
    http_response_code($code);
    $response = [
        'success' => false,
        'error' => $message
    ];
    if ($details) {
        $response['details'] = $details;
    }
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * המרת קונפיג PHP לפורמט Entity Framework
 * @param array $phpConfig - הקונפיג מ-PHP
 * @param string $entityType - סוג הישות
 * @return array - קונפיג בפורמט Entity Framework
 */
function convertToEntityFormat($phpConfig, $entityType) {
    $entityConfig = [];
    
    // ===================================================================
    // שדות בסיסיים
    // ===================================================================
    $entityConfig['singular'] = $phpConfig['singular'] ?? '';
    $entityConfig['singularArticle'] = $phpConfig['singularArticle'] ?? '';
    $entityConfig['plural'] = $phpConfig['plural'] ?? $phpConfig['title'] ?? '';
    
    // ===================================================================
    // API
    // ===================================================================
    $entityConfig['apiEndpoint'] = $phpConfig['api']['endpoint'] ?? '';
    $entityConfig['apiFile'] = basename($entityConfig['apiEndpoint']);
    
    // ===================================================================
    // שדות זיהוי
    // ===================================================================
    $entityConfig['idField'] = $phpConfig['idField'] ?? $phpConfig['primaryKey'] ?? 'unicId';
    $entityConfig['nameField'] = $phpConfig['nameField'] ?? '';
    
    // ===================================================================
    // הורה (Parent)
    // ===================================================================
    $entityConfig['hasParent'] = $phpConfig['hasParent'] ?? ($phpConfig['parentKey'] !== null);
    $entityConfig['parentParam'] = $phpConfig['parentParam'] ?? $phpConfig['parentKey'] ?? null;
    
    // ===================================================================
    // ברירות מחדל
    // ===================================================================
    $entityConfig['defaultLimit'] = $phpConfig['defaultLimit'] ?? 200;
    $entityConfig['defaultOrderBy'] = $phpConfig['defaultOrderBy'] ?? 'createDate';
    $entityConfig['defaultSortDirection'] = $phpConfig['defaultSortDirection'] ?? 'DESC';
    
    // ===================================================================
    // משתני JS (backward compatibility)
    // ===================================================================
    if (isset($phpConfig['jsVars'])) {
        // אם יש jsVars מוגדר - השתמש בו
        foreach ($phpConfig['jsVars'] as $key => $value) {
            $entityConfig[$key] = $value;
        }
    } else {
        // אחרת - צור אוטומטית על בסיס entityType
        $entityConfig['searchVar'] = $entityType . 'Search';
        $entityConfig['tableVar'] = $entityType . 'sTable';
        $entityConfig['currentPageVar'] = $entityType . 'sCurrentPage';
        $entityConfig['totalPagesVar'] = $entityType . 'sTotalPages';
        $entityConfig['dataArrayVar'] = 'current' . ucfirst($entityType) . 's';
        $entityConfig['isLoadingVar'] = $entityType . 'sIsLoadingMore';
        $entityConfig['isSearchModeVar'] = $entityType . 'sIsSearchMode';
        $entityConfig['currentQueryVar'] = $entityType . 'sCurrentQuery';
        $entityConfig['searchResultsVar'] = $entityType . 'sSearchResults';
    }
    
    // ===================================================================
    // פונקציות JS (backward compatibility)
    // ===================================================================
    if (isset($phpConfig['jsFunctions'])) {
        foreach ($phpConfig['jsFunctions'] as $key => $value) {
            $entityConfig[$key] = $value;
        }
    } else {
        // צור אוטומטית
        $ucEntityType = ucfirst($entityType);
        $entityConfig['renderFunctionName'] = 'render' . $ucEntityType . 'sRows';
        $entityConfig['loadFunctionName'] = 'load' . $ucEntityType . 's';
        $entityConfig['loadBrowseFunctionName'] = 'load' . $ucEntityType . 'sBrowseData';
        $entityConfig['appendMoreFunctionName'] = 'appendMore' . $ucEntityType . 's';
    }
    
    // ===================================================================
    // עמודות טבלה - המרת title ל-label
    // ===================================================================
    if (isset($phpConfig['table_columns'])) {
        $entityConfig['columns'] = array_map(function($col) {
            return [
                'field' => $col['field'] ?? '',
                'label' => $col['title'] ?? $col['label'] ?? '',  // תמיכה בשני הפורמטים
                'type' => $col['type'] ?? 'text',
                'width' => $col['width'] ?? 'auto',
                'sortable' => $col['sortable'] ?? false,
                'style' => $col['style'] ?? null,
                'badge_style' => $col['badge_style'] ?? null,
            ];
        }, $phpConfig['table_columns']);
    }
    
    // ===================================================================
    // שדות חיפוש
    // ===================================================================
    if (isset($phpConfig['searchableFields'])) {
        $entityConfig['searchableFields'] = $phpConfig['searchableFields'];
    }
    
    // ===================================================================
    // סטטיסטיקות
    // ===================================================================
    if (isset($phpConfig['statsConfig'])) {
        $entityConfig['statsConfig'] = $phpConfig['statsConfig'];
        // הוסף endpoint אם חסר
        if (!isset($entityConfig['statsConfig']['endpoint']) && $entityConfig['apiEndpoint']) {
            $entityConfig['statsConfig']['endpoint'] = $entityConfig['apiEndpoint'] . '?action=stats';
        }
    }
    
    // ===================================================================
    // סטטוסים
    // ===================================================================
    if (isset($phpConfig['statuses'])) {
        $entityConfig['statuses'] = $phpConfig['statuses'];
    }
    
    // ===================================================================
    // הגדרות חיפוש
    // ===================================================================
    if (isset($phpConfig['search'])) {
        $entityConfig['searchConfig'] = $phpConfig['search'];
    }
    
    // ===================================================================
    // שדות טופס
    // ===================================================================
    if (isset($phpConfig['form_fields'])) {
        $entityConfig['formFields'] = $phpConfig['form_fields'];
    }
    
    // ===================================================================
    // שדות נוספים (queryFields, displayFields)
    // ===================================================================
    if (isset($phpConfig['queryFields'])) {
        $entityConfig['queryFields'] = $phpConfig['queryFields'];
    }
    if (isset($phpConfig['displayFields'])) {
        $entityConfig['displayFields'] = $phpConfig['displayFields'];
    }
    
    // ===================================================================
    // מידע נוסף
    // ===================================================================
    $entityConfig['table'] = $phpConfig['table'] ?? '';
    $entityConfig['icon'] = $phpConfig['icon'] ?? '';
    
    return $entityConfig;
}

try {
    // 1️⃣ קבלת פרמטרים
    $type = $_GET['type'] ?? '';
    $section = $_GET['section'] ?? '';
    
    // 2️⃣ בדיקת פרמטרים
    if (empty($type)) {
        sendError('חסר פרמטר type', 400, 'דוגמה: ?type=plot&section=entity');
    }
    
    if (empty($section)) {
        sendError('חסר פרמטר section', 400, 'אפשרויות: entity, all, table_columns, form_fields, searchableFields');
    }
    
    // 3️⃣ טעינת הקונפיג
    $configPath = $_SERVER['DOCUMENT_ROOT'] . '/dashboard/dashboards/cemeteries/config/cemetery-hierarchy-config.php';
    
    if (!file_exists($configPath)) {
        sendError('קובץ קונפיגורציה לא נמצא', 500, $configPath);
    }
    
    $config = require $configPath;
    
    if (!is_array($config)) {
        sendError('קונפיגורציה לא תקינה', 500, 'Expected array, got ' . gettype($config));
    }
    
    // ===================================================================
    // 4️⃣ טיפול ב-section=entity (פורמט Entity Framework)
    // ===================================================================
    if ($section === 'entity') {
        // תמיכה במספר types בבקשה אחת (מופרדים בפסיק)
        $types = explode(',', $type);
        $result = [];
        
        foreach ($types as $t) {
            $t = trim($t);
            if (!isset($config[$t])) {
                sendError("Type '{$t}' לא קיים בקונפיג", 404, 'Types זמינים: ' . implode(', ', array_keys($config)));
            }
            $result[$t] = convertToEntityFormat($config[$t], $t);
        }
        
        // אם רק type אחד - החזר אותו ישירות
        if (count($types) === 1) {
            echo json_encode([
                'success' => true,
                'data' => $result[$types[0]],
                'meta' => [
                    'type' => $types[0],
                    'section' => 'entity',
                    'format' => 'EntityFramework',
                    'timestamp' => date('Y-m-d H:i:s')
                ]
            ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        } else {
            // מספר types - החזר מערך
            echo json_encode([
                'success' => true,
                'data' => $result,
                'meta' => [
                    'types' => $types,
                    'section' => 'entity',
                    'format' => 'EntityFramework',
                    'timestamp' => date('Y-m-d H:i:s')
                ]
            ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        }
        exit;
    }
    
    // ===================================================================
    // 5️⃣ טיפול ב-section=all (כל הסקשנים)
    // ===================================================================
    if ($section === 'all') {
        if (!isset($config[$type])) {
            sendError("Type '{$type}' לא קיים בקונפיג", 404);
        }
        
        echo json_encode([
            'success' => true,
            'data' => $config[$type],
            'meta' => [
                'type' => $type,
                'section' => 'all',
                'sections' => array_keys($config[$type]),
                'timestamp' => date('Y-m-d H:i:s')
            ]
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }
    
    // ===================================================================
    // 6️⃣ טיפול ב-search (החזרת קונפיג חיפוש)
    // ===================================================================
    if ($section === 'search') {
        if (isset($config[$type]) && isset($config[$type]['search'])) {
            echo json_encode([
                'success' => true,
                'data' => $config[$type]['search']
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        // Fallback - החזר placeholder ברירת מחדל
        $entityName = $config[$type]['plural'] ?? $type;
        echo json_encode([
            'success' => true,
            'data' => [
                'placeholder' => "חיפוש ב{$entityName}..."
            ]
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // ===================================================================
    // 7️⃣ טיפול ב-searchableFields (עם fallback)
    // ===================================================================
    if ($section === 'searchableFields') {
        // נסה קודם מ-cemetery-hierarchy-config.php
        if (isset($config[$type]) && isset($config[$type]['searchableFields'])) {
            echo json_encode([
                'success' => true,
                'data' => $config[$type]['searchableFields']
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }
        
        // Fallback ל-search-config.php
        $searchConfigPath = $_SERVER['DOCUMENT_ROOT'] . '/dashboard/dashboards/cemeteries/config/search-config.php';
        
        if (file_exists($searchConfigPath)) {
            $searchConfig = require $searchConfigPath;
            
            if (isset($searchConfig[$type]) && isset($searchConfig[$type]['searchableFields'])) {
                echo json_encode([
                    'success' => true,
                    'data' => $searchConfig[$type]['searchableFields']
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }
        }
        
        sendError("searchableFields לא נמצא עבור '{$type}'", 404);
    }
    
    // ===================================================================
    // 7️⃣ טיפול רגיל (section ספציפי)
    // ===================================================================
    if (!isset($config[$type])) {
        sendError(
            "Type '{$type}' לא קיים בקונפיג", 
            404,
            'Types זמינים: ' . implode(', ', array_keys($config))
        );
    }
    
    if (!isset($config[$type][$section])) {
        sendError(
            "Section '{$section}' לא קיים עבור type '{$type}'",
            404,
            'Sections זמינים: ' . implode(', ', array_keys($config[$type]))
        );
    }
    
    // 8️⃣ החזרת הנתונים
    $data = $config[$type][$section];
    
    echo json_encode([
        'success' => true,
        'data' => $data,
        'meta' => [
            'type' => $type,
            'section' => $section,
            'count' => is_array($data) ? count($data) : 1,
            'timestamp' => date('Y-m-d H:i:s')
        ]
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    error_log("❌ Error in get-config.php: " . $e->getMessage());
    sendError(
        'שגיאה בטעינת קונפיגורציה',
        500,
        defined('DEBUG_MODE') && DEBUG_MODE ? $e->getMessage() : null
    );
}
?>