<?php
/**
 * Explorer API - סייר קבצים למסמכי קברים
 * Version: 1.0.0
 *
 * Actions:
 * - list: רשימת קבצים ותיקיות
 * - upload: העלאת קובץ
 * - delete: מחיקת קובץ/תיקייה
 * - createFolder: יצירת תיקייה
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, DELETE');
header('Access-Control-Allow-Headers: Content-Type');

// נתיב בסיס למסמכים
define('DOCUMENTS_BASE', dirname(__DIR__) . '/documents/');

// סוגי קבצים מותרים
define('ALLOWED_EXTENSIONS', ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx', 'xls', 'xlsx', 'txt']);

// גודל מקסימלי (10MB)
define('MAX_FILE_SIZE', 10 * 1024 * 1024);

$action = $_GET['action'] ?? '';
$unicId = $_GET['unicId'] ?? null;
$subPath = $_GET['path'] ?? ''; // נתיב יחסי בתוך תיקיית ה-unicId

try {
    // וידוא unicId
    if (!$unicId) {
        throw new Exception('unicId is required');
    }

    // ניקוי unicId מתווים מסוכנים (מאפשר אותיות, מספרים, קו תחתי, מקף ונקודה)
    $unicId = preg_replace('/[^a-zA-Z0-9\-_\.]/', '', $unicId);
    if (empty($unicId)) {
        throw new Exception('Invalid unicId');
    }

    // נתיב התיקייה הראשית של הרשומה
    $basePath = DOCUMENTS_BASE . $unicId . '/';

    // ניקוי נתיב יחסי
    $subPath = str_replace(['..', '\\'], '', $subPath);
    $subPath = trim($subPath, '/');

    // נתיב מלא
    $fullPath = $basePath . ($subPath ? $subPath . '/' : '');

    switch ($action) {
        case 'list':
            // יצירת תיקייה אם לא קיימת
            if (!is_dir($basePath)) {
                mkdir($basePath, 0755, true);
            }

            // יצירת תיקיית תמונות מוסתרת אם לא קיימת
            $hiddenImagesPath = $basePath . '.images/';
            if (!is_dir($hiddenImagesPath)) {
                mkdir($hiddenImagesPath, 0755, true);
            }

            if (!is_dir($fullPath)) {
                $fullPath = $basePath;
                $subPath = '';
            }

            $items = [];
            $files = scandir($fullPath);

            foreach ($files as $file) {
                if ($file === '.' || $file === '..') continue;

                // הסתר תיקיות וקבצים שמתחילים בנקודה (hidden)
                if (strpos($file, '.') === 0) continue;

                $filePath = $fullPath . $file;
                $isDir = is_dir($filePath);
                $ext = $isDir ? null : strtolower(pathinfo($file, PATHINFO_EXTENSION));

                $item = [
                    'name' => $file,
                    'isDir' => $isDir,
                    'ext' => $ext,
                    'size' => $isDir ? null : filesize($filePath),
                    'modified' => date('Y-m-d H:i:s', filemtime($filePath)),
                    'path' => $subPath ? $subPath . '/' . $file : $file
                ];

                // תצוגה מקדימה לתמונות
                if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif'])) {
                    $item['isImage'] = true;
                    $item['thumbUrl'] = "/dashboard/dashboards/cemeteries/explorer/explorer-api.php?action=thumb&unicId={$unicId}&path=" . urlencode($item['path']);
                }

                $items[] = $item;
            }

            // מיון: תיקיות קודם, אח"כ קבצים
            usort($items, function($a, $b) {
                if ($a['isDir'] && !$b['isDir']) return -1;
                if (!$a['isDir'] && $b['isDir']) return 1;
                return strcasecmp($a['name'], $b['name']);
            });

            echo json_encode([
                'success' => true,
                'data' => $items,
                'currentPath' => $subPath,
                'breadcrumb' => $subPath ? explode('/', $subPath) : []
            ]);
            break;

        case 'thumb':
            // תצוגה מקדימה לתמונה
            $filePath = $basePath . $subPath;

            if (!file_exists($filePath)) {
                throw new Exception('File not found');
            }

            $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
            $mimeTypes = [
                'jpg' => 'image/jpeg',
                'jpeg' => 'image/jpeg',
                'png' => 'image/png',
                'gif' => 'image/gif'
            ];

            if (!isset($mimeTypes[$ext])) {
                throw new Exception('Not an image');
            }

            header('Content-Type: ' . $mimeTypes[$ext]);
            header('Content-Length: ' . filesize($filePath));
            readfile($filePath);
            exit;

        case 'download':
            // הורדת קובץ
            $filePath = $basePath . $subPath;

            if (!file_exists($filePath) || is_dir($filePath)) {
                throw new Exception('File not found');
            }

            $fileName = basename($filePath);
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="' . $fileName . '"');
            header('Content-Length: ' . filesize($filePath));
            readfile($filePath);
            exit;

        case 'upload':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                throw new Exception('POST method required');
            }

            if (!isset($_FILES['file'])) {
                throw new Exception('No file uploaded');
            }

            $file = $_FILES['file'];

            // בדיקת שגיאות העלאה
            if ($file['error'] !== UPLOAD_ERR_OK) {
                throw new Exception('Upload error: ' . $file['error']);
            }

            // בדיקת גודל
            if ($file['size'] > MAX_FILE_SIZE) {
                throw new Exception('File too large (max 10MB)');
            }

            // בדיקת סיומת
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, ALLOWED_EXTENSIONS)) {
                throw new Exception('File type not allowed');
            }

            // יצירת תיקייה אם לא קיימת
            if (!is_dir($fullPath)) {
                mkdir($fullPath, 0755, true);
            }

            // ניקוי שם קובץ - מאפשר עברית, אנגלית, מספרים, נקודות ומקפים
            $fileName = preg_replace('/[^\p{Hebrew}a-zA-Z0-9\-_\.]/u', '_', $file['name']);
            $destPath = $fullPath . $fileName;

            // אם קובץ קיים, הוסף מספר
            $counter = 1;
            while (file_exists($destPath)) {
                $name = pathinfo($fileName, PATHINFO_FILENAME);
                $destPath = $fullPath . $name . '_' . $counter . '.' . $ext;
                $counter++;
            }

            if (!move_uploaded_file($file['tmp_name'], $destPath)) {
                throw new Exception('Failed to save file');
            }

            echo json_encode([
                'success' => true,
                'message' => 'File uploaded successfully',
                'fileName' => basename($destPath)
            ]);
            break;

        case 'createFolder':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                throw new Exception('POST method required');
            }

            $input = json_decode(file_get_contents('php://input'), true);
            $folderName = $input['name'] ?? null;

            if (!$folderName) {
                throw new Exception('Folder name required');
            }

            // ניקוי שם תיקייה - מאפשר עברית, אנגלית, מספרים, רווחים ומקפים
            $folderName = preg_replace('/[^\p{Hebrew}a-zA-Z0-9\-_ ]/u', '', $folderName);
            $folderName = trim($folderName);

            if (empty($folderName)) {
                throw new Exception('Invalid folder name');
            }

            if (!is_dir($fullPath)) {
                mkdir($fullPath, 0755, true);
            }

            $newFolderPath = $fullPath . $folderName;

            if (is_dir($newFolderPath)) {
                throw new Exception('Folder already exists');
            }

            if (!mkdir($newFolderPath, 0755)) {
                throw new Exception('Failed to create folder');
            }

            echo json_encode([
                'success' => true,
                'message' => 'Folder created successfully'
            ]);
            break;

        case 'delete':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST' && $_SERVER['REQUEST_METHOD'] !== 'DELETE') {
                throw new Exception('POST or DELETE method required');
            }

            $input = json_decode(file_get_contents('php://input'), true);
            $targetPath = $input['path'] ?? $subPath;

            if (!$targetPath) {
                throw new Exception('Path required');
            }

            $targetPath = str_replace(['..', '\\'], '', $targetPath);
            $fullTargetPath = $basePath . $targetPath;

            if (!file_exists($fullTargetPath)) {
                throw new Exception('File or folder not found');
            }

            // מחיקת תיקייה רקורסיבית
            if (is_dir($fullTargetPath)) {
                deleteDirectory($fullTargetPath);
            } else {
                unlink($fullTargetPath);
            }

            echo json_encode([
                'success' => true,
                'message' => 'Deleted successfully'
            ]);
            break;

        case 'rename':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                throw new Exception('POST method required');
            }

            $input = json_decode(file_get_contents('php://input'), true);
            $oldPath = $input['oldPath'] ?? null;
            $newName = $input['newName'] ?? null;

            if (!$oldPath || !$newName) {
                throw new Exception('Old path and new name required');
            }

            $oldPath = str_replace(['..', '\\'], '', $oldPath);
            $fullOldPath = $basePath . $oldPath;

            if (!file_exists($fullOldPath)) {
                throw new Exception('File or folder not found');
            }

            // ניקוי שם חדש
            $newName = preg_replace('/[^\p{Hebrew}a-zA-Z0-9\-_\. ]/u', '', $newName);
            $newName = trim($newName);

            if (empty($newName)) {
                throw new Exception('Invalid new name');
            }

            // בנה נתיב חדש
            $parentDir = dirname($fullOldPath);
            $fullNewPath = $parentDir . '/' . $newName;

            if (file_exists($fullNewPath)) {
                throw new Exception('A file with this name already exists');
            }

            if (!rename($fullOldPath, $fullNewPath)) {
                throw new Exception('Failed to rename');
            }

            echo json_encode([
                'success' => true,
                'message' => 'Renamed successfully'
            ]);
            break;

        case 'copy':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                throw new Exception('POST method required');
            }

            $input = json_decode(file_get_contents('php://input'), true);
            $sourcePath = $input['sourcePath'] ?? null;
            $destPath = $input['destPath'] ?? '';

            if (!$sourcePath) {
                throw new Exception('Source path required');
            }

            $sourcePath = str_replace(['..', '\\'], '', $sourcePath);
            $destPath = str_replace(['..', '\\'], '', $destPath);

            $fullSourcePath = $basePath . $sourcePath;
            $fullDestPath = $basePath . ($destPath ? $destPath . '/' : '') . basename($sourcePath);

            if (!file_exists($fullSourcePath)) {
                throw new Exception('Source not found');
            }

            // יצירת שם ייחודי אם כבר קיים
            $counter = 1;
            $originalDestPath = $fullDestPath;
            while (file_exists($fullDestPath)) {
                $info = pathinfo($originalDestPath);
                $fullDestPath = $info['dirname'] . '/' . $info['filename'] . '_' . $counter;
                if (isset($info['extension'])) {
                    $fullDestPath .= '.' . $info['extension'];
                }
                $counter++;
            }

            if (is_dir($fullSourcePath)) {
                copyDirectory($fullSourcePath, $fullDestPath);
            } else {
                copy($fullSourcePath, $fullDestPath);
            }

            echo json_encode([
                'success' => true,
                'message' => 'Copied successfully'
            ]);
            break;

        case 'move':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                throw new Exception('POST method required');
            }

            $input = json_decode(file_get_contents('php://input'), true);
            $sourcePath = $input['sourcePath'] ?? null;
            $destPath = $input['destPath'] ?? '';

            if (!$sourcePath) {
                throw new Exception('Source path required');
            }

            $sourcePath = str_replace(['..', '\\'], '', $sourcePath);
            $destPath = str_replace(['..', '\\'], '', $destPath);

            $fullSourcePath = $basePath . $sourcePath;
            $fullDestPath = $basePath . ($destPath ? $destPath . '/' : '') . basename($sourcePath);

            if (!file_exists($fullSourcePath)) {
                throw new Exception('Source not found');
            }

            if (file_exists($fullDestPath)) {
                throw new Exception('A file with this name already exists in destination');
            }

            if (!rename($fullSourcePath, $fullDestPath)) {
                throw new Exception('Failed to move');
            }

            echo json_encode([
                'success' => true,
                'message' => 'Moved successfully'
            ]);
            break;

        // ========================================
        // API לתמונות קבר (תיקיה מוסתרת .images)
        // ========================================

        case 'listGraveImages':
            // יצירת תיקיית תמונות מוסתרת אם לא קיימת
            $imagesPath = $basePath . '.images/';
            if (!is_dir($imagesPath)) {
                mkdir($imagesPath, 0755, true);
            }

            $images = [];
            $files = scandir($imagesPath);

            foreach ($files as $file) {
                if ($file === '.' || $file === '..') continue;

                $filePath = $imagesPath . $file;
                if (is_dir($filePath)) continue;

                $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
                if (!in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) continue;

                $images[] = [
                    'name' => $file,
                    'url' => "/dashboard/dashboards/cemeteries/explorer/explorer-api.php?action=getGraveImage&unicId={$unicId}&file=" . urlencode($file),
                    'modified' => date('Y-m-d H:i:s', filemtime($filePath))
                ];
            }

            // מיון לפי תאריך שינוי (חדש ראשון)
            usort($images, function($a, $b) {
                return strtotime($b['modified']) - strtotime($a['modified']);
            });

            echo json_encode([
                'success' => true,
                'images' => $images
            ]);
            break;

        case 'getGraveImage':
            $fileName = $_GET['file'] ?? null;
            if (!$fileName) {
                throw new Exception('File name required');
            }

            $fileName = basename($fileName); // אבטחה
            $filePath = $basePath . '.images/' . $fileName;

            if (!file_exists($filePath)) {
                throw new Exception('Image not found');
            }

            $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
            $mimeTypes = [
                'jpg' => 'image/jpeg',
                'jpeg' => 'image/jpeg',
                'png' => 'image/png',
                'gif' => 'image/gif',
                'webp' => 'image/webp'
            ];

            if (!isset($mimeTypes[$ext])) {
                throw new Exception('Not an image');
            }

            header('Content-Type: ' . $mimeTypes[$ext]);
            header('Content-Length: ' . filesize($filePath));
            header('Cache-Control: max-age=86400'); // cache ליום
            readfile($filePath);
            exit;

        case 'uploadGraveImage':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                throw new Exception('POST method required');
            }

            if (!isset($_FILES['image'])) {
                throw new Exception('No image uploaded');
            }

            $file = $_FILES['image'];

            // בדיקת שגיאות העלאה
            if ($file['error'] !== UPLOAD_ERR_OK) {
                throw new Exception('Upload error: ' . $file['error']);
            }

            // בדיקת גודל (5MB לתמונות)
            if ($file['size'] > 5 * 1024 * 1024) {
                throw new Exception('Image too large (max 5MB)');
            }

            // בדיקת סיומת
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
                throw new Exception('Only images allowed (jpg, png, gif, webp)');
            }

            // יצירת תיקיה אם לא קיימת
            $imagesPath = $basePath . '.images/';
            if (!is_dir($imagesPath)) {
                mkdir($imagesPath, 0755, true);
            }

            // שם קובץ ייחודי
            $fileName = 'grave_' . date('Ymd_His') . '_' . uniqid() . '.' . $ext;
            $destPath = $imagesPath . $fileName;

            if (!move_uploaded_file($file['tmp_name'], $destPath)) {
                throw new Exception('Failed to save image');
            }

            echo json_encode([
                'success' => true,
                'message' => 'Image uploaded successfully',
                'fileName' => $fileName,
                'url' => "/dashboard/dashboards/cemeteries/explorer/explorer-api.php?action=getGraveImage&unicId={$unicId}&file=" . urlencode($fileName)
            ]);
            break;

        case 'deleteGraveImage':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                throw new Exception('POST method required');
            }

            $input = json_decode(file_get_contents('php://input'), true);
            $fileName = $input['fileName'] ?? null;

            if (!$fileName) {
                throw new Exception('File name required');
            }

            $fileName = basename($fileName); // אבטחה
            $filePath = $basePath . '.images/' . $fileName;

            if (!file_exists($filePath)) {
                throw new Exception('Image not found');
            }

            if (!unlink($filePath)) {
                throw new Exception('Failed to delete image');
            }

            echo json_encode([
                'success' => true,
                'message' => 'Image deleted successfully'
            ]);
            break;

        default:
            throw new Exception('Unknown action: ' . $action);
    }

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

/**
 * מחיקת תיקייה רקורסיבית
 */
function deleteDirectory($dir) {
    if (!is_dir($dir)) return;

    $files = array_diff(scandir($dir), ['.', '..']);
    foreach ($files as $file) {
        $path = $dir . '/' . $file;
        is_dir($path) ? deleteDirectory($path) : unlink($path);
    }
    rmdir($dir);
}

/**
 * העתקת תיקייה רקורסיבית
 */
function copyDirectory($src, $dst) {
    $dir = opendir($src);
    @mkdir($dst, 0755, true);

    while (($file = readdir($dir)) !== false) {
        if ($file == '.' || $file == '..') continue;

        $srcPath = $src . '/' . $file;
        $dstPath = $dst . '/' . $file;

        if (is_dir($srcPath)) {
            copyDirectory($srcPath, $dstPath);
        } else {
            copy($srcPath, $dstPath);
        }
    }

    closedir($dir);
}
