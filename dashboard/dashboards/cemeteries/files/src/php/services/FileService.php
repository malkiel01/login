<?php
/**
 * File Service
 *
 * שירות לניהול קבצים - העלאה, שמירה, מחיקה, ניקוי
 *
 * @package PDFEditor\Services
 * @version 1.0.0
 * @since Phase 2 Refactoring
 */

namespace PDFEditor\Services;

class FileService {

    /**
     * שמירת קובץ מועלה
     *
     * מעביר קובץ מועלה מ-tmp לתיקייה היעד
     *
     * @param array $file $_FILES['pdf']
     * @param string $targetDir תיקיית יעד
     * @param string $filename שם קובץ (אופציונלי - אם לא מצוין, ייווצר ייחודי)
     * @return array ['success' => bool, 'filepath' => string, 'error' => string|null]
     *
     * @throws \Exception
     *
     * @example
     * $result = FileService::saveUploadedFile($_FILES['pdf'], $uploadDir);
     * if ($result['success']) {
     *     echo "Saved to: " . $result['filepath'];
     * }
     */
    public static function saveUploadedFile($file, $targetDir, $filename = null) {
        // Ensure target directory exists
        if (!file_exists($targetDir)) {
            mkdir($targetDir, 0755, true);
        }

        // Generate filename if not provided
        if ($filename === null) {
            $uniqueId = self::generateUniqueId();
            $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
            $filename = $uniqueId . '.' . $extension;
        }

        $targetPath = $targetDir . $filename;

        // Move uploaded file
        if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
            return [
                'success' => false,
                'error' => 'שגיאה בשמירת הקובץ'
            ];
        }

        return [
            'success' => true,
            'filepath' => $targetPath,
            'filename' => $filename
        ];
    }

    /**
     * מחיקת קובץ
     *
     * מוחק קובץ בצורה בטוחה
     *
     * @param string $filepath נתיב מלא לקובץ
     * @return array ['success' => bool, 'error' => string|null]
     *
     * @example
     * $result = FileService::deleteFile($filepath);
     */
    public static function deleteFile($filepath) {
        if (!file_exists($filepath)) {
            return [
                'success' => false,
                'error' => 'הקובץ לא נמצא'
            ];
        }

        if (!@unlink($filepath)) {
            return [
                'success' => false,
                'error' => 'שגיאה במחיקת הקובץ'
            ];
        }

        return ['success' => true];
    }

    /**
     * ניקוי קבצים ישנים
     *
     * מוחק קבצים ישנים מעבר לגיל מסוים
     *
     * @param string $dir תיקייה לניקוי
     * @param int $maxAge גיל מקסימלי בשניות (default: 3600 = 1 hour)
     * @return array ['success' => bool, 'deleted_count' => int, 'errors' => array]
     *
     * @example
     * $result = FileService::cleanOldFiles($uploadDir, 3600);
     * echo "Deleted {$result['deleted_count']} files";
     */
    public static function cleanOldFiles($dir, $maxAge = 3600) {
        if (!is_dir($dir)) {
            return [
                'success' => false,
                'error' => 'התיקייה לא קיימת',
                'deleted_count' => 0
            ];
        }

        $now = time();
        $deletedCount = 0;
        $errors = [];

        $files = glob($dir . '*');

        foreach ($files as $file) {
            if (!is_file($file)) {
                continue;
            }

            $fileAge = $now - filemtime($file);

            if ($fileAge >= $maxAge) {
                if (@unlink($file)) {
                    $deletedCount++;
                } else {
                    $errors[] = "Failed to delete: " . basename($file);
                }
            }
        }

        return [
            'success' => true,
            'deleted_count' => $deletedCount,
            'errors' => $errors
        ];
    }

    /**
     * יצירת מזהה ייחודי
     *
     * מייצר מזהה ייחודי לשמות קבצים
     *
     * @param string $prefix prefix אופציונלי
     * @return string מזהה ייחודי
     *
     * @example
     * $id = FileService::generateUniqueId('pdf_');
     * // Returns: pdf_abc123def456...
     */
    public static function generateUniqueId($prefix = '') {
        return $prefix . uniqid('', true);
    }

    /**
     * קבלת גודל קובץ בפורמט קריא
     *
     * @param string $filepath נתיב לקובץ
     * @return string גודל בפורמט קריא (KB, MB, וכו')
     *
     * @example
     * $size = FileService::getReadableFileSize($filepath);
     * // Returns: "2.5 MB"
     */
    public static function getReadableFileSize($filepath) {
        if (!file_exists($filepath)) {
            return '0 Bytes';
        }

        $bytes = filesize($filepath);

        if ($bytes === 0) {
            return '0 Bytes';
        }

        $k = 1024;
        $sizes = ['Bytes', 'KB', 'MB', 'GB', 'TB'];
        $i = floor(log($bytes) / log($k));

        return round($bytes / pow($k, $i), 2) . ' ' . $sizes[$i];
    }

    /**
     * קבלת מטא-דאטה של קובץ
     *
     * @param string $filepath נתיב לקובץ
     * @return array|null מטא-דאטה או null אם הקובץ לא קיים
     *
     * @example
     * $meta = FileService::getFileMetadata($filepath);
     * // Returns: ['size' => 1234, 'modified' => timestamp, ...]
     */
    public static function getFileMetadata($filepath) {
        if (!file_exists($filepath)) {
            return null;
        }

        return [
            'size' => filesize($filepath),
            'size_readable' => self::getReadableFileSize($filepath),
            'modified' => filemtime($filepath),
            'modified_readable' => date('Y-m-d H:i:s', filemtime($filepath)),
            'name' => basename($filepath),
            'extension' => pathinfo($filepath, PATHINFO_EXTENSION),
            'directory' => dirname($filepath)
        ];
    }

    /**
     * בדיקה אם קובץ קיים
     *
     * @param string $filepath נתיב לקובץ
     * @return bool
     */
    public static function exists($filepath) {
        return file_exists($filepath) && is_file($filepath);
    }

    /**
     * קבלת מסגרת MIME type של קובץ
     *
     * @param string $filepath נתיב לקובץ
     * @return string|false MIME type או false במקרה של שגיאה
     *
     * @example
     * $mime = FileService::getMimeType($filepath);
     * // Returns: "application/pdf"
     */
    public static function getMimeType($filepath) {
        if (!file_exists($filepath)) {
            return false;
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $filepath);
        finfo_close($finfo);

        return $mimeType;
    }

    /**
     * העתקת קובץ
     *
     * @param string $source נתיב מקור
     * @param string $destination נתיב יעד
     * @return array ['success' => bool, 'error' => string|null]
     *
     * @example
     * $result = FileService::copyFile($source, $dest);
     */
    public static function copyFile($source, $destination) {
        if (!file_exists($source)) {
            return [
                'success' => false,
                'error' => 'קובץ המקור לא נמצא'
            ];
        }

        // Ensure destination directory exists
        $destDir = dirname($destination);
        if (!file_exists($destDir)) {
            mkdir($destDir, 0755, true);
        }

        if (!@copy($source, $destination)) {
            return [
                'success' => false,
                'error' => 'שגיאה בהעתקת הקובץ'
            ];
        }

        return ['success' => true];
    }

    /**
     * הזזת קובץ
     *
     * @param string $source נתיב מקור
     * @param string $destination נתיב יעד
     * @return array ['success' => bool, 'error' => string|null]
     *
     * @example
     * $result = FileService::moveFile($source, $dest);
     */
    public static function moveFile($source, $destination) {
        if (!file_exists($source)) {
            return [
                'success' => false,
                'error' => 'קובץ המקור לא נמצא'
            ];
        }

        // Ensure destination directory exists
        $destDir = dirname($destination);
        if (!file_exists($destDir)) {
            mkdir($destDir, 0755, true);
        }

        if (!@rename($source, $destination)) {
            return [
                'success' => false,
                'error' => 'שגיאה בהזזת הקובץ'
            ];
        }

        return ['success' => true];
    }

    /**
     * קריאת תוכן קובץ JSON
     *
     * @param string $filepath נתיב לקובץ JSON
     * @param bool $associative החזר כמערך אסוציאטיבי (default: true)
     * @return mixed|null תוכן ה-JSON או null במקרה של שגיאה
     *
     * @example
     * $data = FileService::readJsonFile('config.json');
     */
    public static function readJsonFile($filepath, $associative = true) {
        if (!file_exists($filepath)) {
            return null;
        }

        $content = file_get_contents($filepath);

        if ($content === false) {
            return null;
        }

        return json_decode($content, $associative);
    }

    /**
     * כתיבת נתונים לקובץ JSON
     *
     * @param string $filepath נתיב לקובץ
     * @param mixed $data נתונים לשמירה
     * @param int $flags JSON flags (default: JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
     * @return array ['success' => bool, 'error' => string|null]
     *
     * @example
     * FileService::writeJsonFile('config.json', $data);
     */
    public static function writeJsonFile($filepath, $data, $flags = null) {
        if ($flags === null) {
            $flags = JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT;
        }

        $json = json_encode($data, $flags);

        if ($json === false) {
            return [
                'success' => false,
                'error' => 'שגיאה בהמרה ל-JSON'
            ];
        }

        // Ensure directory exists
        $dir = dirname($filepath);
        if (!file_exists($dir)) {
            mkdir($dir, 0755, true);
        }

        if (file_put_contents($filepath, $json) === false) {
            return [
                'success' => false,
                'error' => 'שגיאה בכתיבה לקובץ'
            ];
        }

        return ['success' => true];
    }
}
