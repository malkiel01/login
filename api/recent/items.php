<?php
/**
 * Recent Items API - פריטים אחרונים לשיתוף מהיר
 * דומה לרשימת הצ'אטים האחרונים בוואטסאפ
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../auth/middleware.php';

// בדיקת חיבור
if (!isAuthenticated()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Authentication required']);
    exit;
}

$userId = getCurrentUserId();
$pdo = getDBConnection();

$action = $_GET['action'] ?? $_POST['action'] ?? 'get';

try {
    switch ($action) {

        // ========================================
        // קבלת פריטים אחרונים
        // ========================================
        case 'get':
            $limit = min((int)($_GET['limit'] ?? 10), 20);
            $type = $_GET['type'] ?? 'all'; // all, graves, notes, files

            $recentItems = [];

            // קברים אחרונים (אם יש גישה)
            if ($type === 'all' || $type === 'graves') {
                $gravesStmt = $pdo->prepare("
                    SELECT
                        g.id,
                        g.deceased_name as name,
                        g.photo as image,
                        c.name as subtitle,
                        g.updated_at as last_used,
                        'grave' as type
                    FROM graves g
                    LEFT JOIN cemeteries c ON g.cemetery_id = c.id
                    WHERE g.created_by = ? OR g.updated_by = ?
                    ORDER BY g.updated_at DESC
                    LIMIT ?
                ");
                $gravesStmt->execute([$userId, $userId, $limit]);
                $graves = $gravesStmt->fetchAll(PDO::FETCH_ASSOC);

                foreach ($graves as $grave) {
                    $recentItems[] = [
                        'id' => $grave['id'],
                        'name' => $grave['name'],
                        'subtitle' => $grave['subtitle'] ?? 'קבר',
                        'image' => $grave['image'],
                        'type' => 'grave',
                        'icon' => 'grave',
                        'color' => '#8E8E93',
                        'last_used' => $grave['last_used']
                    ];
                }
            }

            // הערות אחרונות (אם קיימת טבלה)
            if ($type === 'all' || $type === 'notes') {
                try {
                    $notesStmt = $pdo->prepare("
                        SELECT
                            id,
                            title as name,
                            LEFT(content, 50) as subtitle,
                            updated_at as last_used,
                            'note' as type
                        FROM notes
                        WHERE user_id = ?
                        ORDER BY updated_at DESC
                        LIMIT ?
                    ");
                    $notesStmt->execute([$userId, $limit]);
                    $notes = $notesStmt->fetchAll(PDO::FETCH_ASSOC);

                    foreach ($notes as $note) {
                        $recentItems[] = [
                            'id' => $note['id'],
                            'name' => $note['name'] ?: 'הערה ללא כותרת',
                            'subtitle' => $note['subtitle'],
                            'image' => null,
                            'type' => 'note',
                            'icon' => 'note',
                            'color' => '#FF9500',
                            'last_used' => $note['last_used']
                        ];
                    }
                } catch (PDOException $e) {
                    // טבלת notes לא קיימת - נדלג
                }
            }

            // קבצים/תיקיות אחרונים
            if ($type === 'all' || $type === 'files') {
                try {
                    $filesStmt = $pdo->prepare("
                        SELECT
                            id,
                            filename as name,
                            folder_path as subtitle,
                            mime_type,
                            updated_at as last_used,
                            'file' as type
                        FROM user_files
                        WHERE user_id = ?
                        ORDER BY updated_at DESC
                        LIMIT ?
                    ");
                    $filesStmt->execute([$userId, $limit]);
                    $files = $filesStmt->fetchAll(PDO::FETCH_ASSOC);

                    foreach ($files as $file) {
                        $recentItems[] = [
                            'id' => $file['id'],
                            'name' => $file['name'],
                            'subtitle' => $file['subtitle'] ?: 'קובץ',
                            'image' => null,
                            'type' => 'file',
                            'icon' => getFileIcon($file['mime_type']),
                            'color' => '#007AFF',
                            'last_used' => $file['last_used']
                        ];
                    }
                } catch (PDOException $e) {
                    // טבלת files לא קיימת - נדלג
                }
            }

            // מיון לפי תאריך שימוש אחרון
            usort($recentItems, function($a, $b) {
                return strtotime($b['last_used']) - strtotime($a['last_used']);
            });

            // הגבל למספר המבוקש
            $recentItems = array_slice($recentItems, 0, $limit);

            echo json_encode([
                'success' => true,
                'items' => $recentItems,
                'count' => count($recentItems)
            ]);
            break;

        // ========================================
        // רישום שימוש בפריט (עדכון last_used)
        // ========================================
        case 'touch':
            $itemId = $_POST['item_id'] ?? null;
            $itemType = $_POST['item_type'] ?? null;

            if (!$itemId || !$itemType) {
                throw new Exception('Missing item_id or item_type');
            }

            // עדכן את זמן השימוש האחרון
            switch ($itemType) {
                case 'grave':
                    $stmt = $pdo->prepare("UPDATE graves SET updated_at = NOW() WHERE id = ?");
                    $stmt->execute([$itemId]);
                    break;
                case 'note':
                    $stmt = $pdo->prepare("UPDATE notes SET updated_at = NOW() WHERE id = ? AND user_id = ?");
                    $stmt->execute([$itemId, $userId]);
                    break;
                case 'file':
                    $stmt = $pdo->prepare("UPDATE user_files SET updated_at = NOW() WHERE id = ? AND user_id = ?");
                    $stmt->execute([$itemId, $userId]);
                    break;
            }

            echo json_encode(['success' => true]);
            break;

        // ========================================
        // הוספה להיסטוריית שיתוף
        // ========================================
        case 'log_share':
            $itemId = $_POST['item_id'] ?? null;
            $itemType = $_POST['item_type'] ?? null;
            $shareMethod = $_POST['share_method'] ?? 'unknown';

            // רשום בטבלת היסטוריה אם קיימת
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO share_history (user_id, item_id, item_type, share_method, created_at)
                    VALUES (?, ?, ?, ?, NOW())
                ");
                $stmt->execute([$userId, $itemId, $itemType, $shareMethod]);
            } catch (PDOException $e) {
                // טבלה לא קיימת - נדלג
            }

            echo json_encode(['success' => true]);
            break;

        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid action']);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

/**
 * קבלת אייקון לפי סוג קובץ
 */
function getFileIcon($mimeType) {
    if (strpos($mimeType, 'image/') === 0) return 'image';
    if (strpos($mimeType, 'video/') === 0) return 'video';
    if (strpos($mimeType, 'audio/') === 0) return 'audio';
    if ($mimeType === 'application/pdf') return 'pdf';
    if (strpos($mimeType, 'spreadsheet') !== false || strpos($mimeType, 'excel') !== false) return 'spreadsheet';
    if (strpos($mimeType, 'document') !== false || strpos($mimeType, 'word') !== false) return 'document';
    return 'file';
}
