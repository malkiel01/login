<?php
    /*
    * File: api/get-config.php
    * Version: 2.0.0
    * Updated: 2025-11-03
    * Author: Malkiel
    * Description: API endpoint לקבלת קונפיגורציה מ-cemetery-hierarchy-config.php
    * Change Summary:
    * - v2.0.0: שיפור error handling ו-validation
    *   - הוספת בדיקות קיום type ו-section
    *   - הוספת error messages ברורים
    *   - הוספת logging לשגיאות
    *   - תמיכה ברשימת types מותרים
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

    try {
        // 1️⃣ קבלת פרמטרים
        $type = $_GET['type'] ?? '';
        $section = $_GET['section'] ?? '';
        
        // 2️⃣ בדיקת פרמטרים
        if (empty($type)) {
            sendError('חסר פרמטר type', 400, 'דוגמה: ?type=cemetery&section=table_columns');
        }
        
        if (empty($section)) {
            sendError('חסר פרמטר section', 400, 'אפשרויות: table_columns, form_fields, queryFields');
        }
        
        // 4️⃣ טעינת הקונפיג

        // ⭐ אם מבקשים searchableFields - טען מקונפיג החיפוש!
        if ($section === 'searchableFields') {
            // נסה קודם לטעון מ-cemetery-hierarchy-config.php
            $configPath = $_SERVER['DOCUMENT_ROOT'] . '/dashboard/dashboards/cemeteries/config/cemetery-hierarchy-config.php';
            
            if (file_exists($configPath)) {
                $config = require $configPath;
                
                // אם קיים בקונפיג הראשי - השתמש בו
                if (isset($config[$type]) && isset($config[$type]['searchableFields'])) {
                    echo json_encode([
                        'success' => true,
                        'data' => $config[$type]['searchableFields']
                    ], JSON_UNESCAPED_UNICODE);
                    exit;
                }
            }
            
            // אם לא נמצא - נסה search-config.php (fallback)
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

            if (!file_exists($searchConfigPath)) {
                sendError('קובץ search-config.php לא נמצא', 500);
                // לא נמצא בשום מקום
                sendError("searchableFields לא נמצא עבור '{$type}'", 404);
            }
            
            $searchConfig = require $searchConfigPath;
            
            if (!isset($searchConfig[$type])) {
                sendError("Type '{$type}' לא קיים ב-search-config", 404);
            }
            
            if (!isset($searchConfig[$type]['searchableFields'])) {
                sendError("searchableFields לא מוגדר עבור '{$type}'", 404);
            }
            
            echo json_encode([
                'success' => true,
                'data' => $searchConfig[$type]['searchableFields']
            ]);
            exit;
        }

        // אחרת - המשך עם cemetery-hierarchy-config
        $configPath = $_SERVER['DOCUMENT_ROOT'] . '/dashboard/dashboards/cemeteries/config/cemetery-hierarchy-config.php';
        
        if (!file_exists($configPath)) {
            sendError('קובץ קונפיגורציה לא נמצא', 500, $configPath);
        }
        
        $config = require $configPath;
        
        if (!is_array($config)) {
            sendError('קונפיגורציה לא תקינה', 500, 'Expected array, got ' . gettype($config));
        }
        
        // 5️⃣ בדיקה אם ה-type קיים בקונפיג
        if (!isset($config[$type])) {
            sendError(
                "Type '{$type}' לא קיים בקונפיג", 
                404,
                'Types זמינים: ' . implode(', ', array_keys($config))
            );
        }
        
        // 6️⃣ בדיקה אם ה-section קיים
        if (!isset($config[$type][$section])) {
            sendError(
                "Section '{$section}' לא קיים עבור type '{$type}'",
                404,
                'Sections זמינים: ' . implode(', ', array_keys($config[$type]))
            );
        }
        
        // 7️⃣ החזרת הנתונים
        $data = $config[$type][$section];
        
        // לוג להצלחה (אופציונלי - רק בסביבת פיתוח)
        if (defined('DEBUG_MODE') && DEBUG_MODE) {
            error_log("✅ Config loaded: type={$type}, section={$section}, items=" . count($data));
        }
        
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
        // 8️⃣ תפיסת שגיאות כלליות
        error_log("❌ Error in get-config.php: " . $e->getMessage());
        sendError(
            'שגיאה בטעינת קונפיגורציה',
            500,
            defined('DEBUG_MODE') && DEBUG_MODE ? $e->getMessage() : null
        );
    }
?>