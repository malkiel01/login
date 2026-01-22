<?php
/*
 * File: dashboard/dashboards/cemeteries/api/get-entity-config.php
 * Version: 1.0.0
 * Updated: 2025-11-18
 * Author: Malkiel
 * Description: API endpoint להחזרת entity-config.php בפורמט JSON
 * Change Summary:
 * - v1.0.0: יצירת endpoint להחזרת קונפיג ל-JavaScript
 */

// אימות והרשאות - חייב להיות מחובר!
require_once __DIR__ . '/api-auth.php';

try {
    // נתיב לקובץ הקונפיג
    $configPath = __DIR__ . '/../config/entity-config.php';
    
    // בדוק אם הקובץ קיים
    if (!file_exists($configPath)) {
        throw new Exception('קובץ הקונפיג לא נמצא');
    }
    
    // טען את הקונפיג
    $entityConfig = require $configPath;
    
    // וודא שזה array
    if (!is_array($entityConfig)) {
        throw new Exception('פורמט קונפיג לא תקין');
    }
    
    // סנן רק entities פעילים (אופציונלי)
    $activeOnly = isset($_GET['active']) && $_GET['active'] === 'true';
    
    if ($activeOnly) {
        $entityConfig = array_filter($entityConfig, function($entity) {
            return isset($entity['enabled']) && $entity['enabled'] === true;
        });
    }
    
    // החזר תשובה
    echo json_encode([
        'success' => true,
        'data' => $entityConfig,
        'count' => count($entityConfig),
        'timestamp' => date('Y-m-d H:i:s')
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    http_response_code(500);
    
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s')
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
}