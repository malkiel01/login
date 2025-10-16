<?php
/**
 * FormUtils - פונקציות עזר אחידות לטפסים
 */

class FormUtils {
    
    /**
     * קבלת פרמטרים אחידה
     */
    public static function getParams() {
        return [
            'formType' => $_GET['formType'] ?? $_GET['type'] ?? '',
            'itemId' => $_GET['itemId'] ?? $_GET['id'] ?? null,
            'parentId' => $_GET['parentId'] ?? $_GET['parent_id'] ?? null
        ];
    }
    
    /**
     * טיפול אחיד בשגיאות
     */
    public static function handleError($exception) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => false,
            'error' => $exception->getMessage()
        ]);
        exit;
    }
    
    /**
     * טעינת נתונים בצורה אחידה
     */
    public static function loadItemData($table, $idField, $id) {
        if (!$id) return null;
        
        try {
            $conn = getDBConnection();
            $stmt = $conn->prepare("
                SELECT * FROM $table 
                WHERE $idField = ? AND isActive = 1
            ");
            $stmt->execute([$id]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            return null;
        }
    }
}
?>