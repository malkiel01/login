<?php
// enable-all-functions.php

$functions_content = '<?php
/**
 * PDF Editor Functions - FULL VERSION
 */

function getPDFEditorDB() {
    return getDBConnection();
}

function createPDFEditorTables($db) {
    // הטבלאות כבר קיימות, אז הפונקציה לא צריכה לעשות כלום
    return true;
}

function logActivity($action, $module = "pdf_editor", $details = "", $metadata = []) {
    try {
        $db = getDBConnection();
        if (!$db) return;
        
        $userId = $_SESSION["user_id"] ?? 0;
        
        $stmt = $db->prepare("
            INSERT INTO pdf_editor_activity_log 
            (user_id, action, module, details, metadata, created_at) 
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        
        $stmt->execute([
            $userId,
            $action,
            $module,
            $details,
            json_encode($metadata)
        ]);
        
    } catch (Exception $e) {
        error_log("Failed to log activity: " . $e->getMessage());
    }
}
?>';

file_put_contents(
    $_SERVER['DOCUMENT_ROOT'] . '/dashboard/dashboards/printPDF/includes/functions.php',
    $functions_content
);

echo "functions.php updated with all functions enabled.\n";
echo "Now try index.php - it should work!";
?>