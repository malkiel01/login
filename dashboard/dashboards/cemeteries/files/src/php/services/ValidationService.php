<?php
/**
 * Validation Service
 *
 * שירות לולידציה של נתונים - קבצים, שמות, פורמטים, וכו'.
 * מרכז את כל הולידציות במקום אחד לשימוש חוזר.
 *
 * @package PDFEditor\Services
 * @version 1.0.0
 * @since Phase 2 Refactoring
 */

namespace PDFEditor\Services;

class ValidationService {

    /**
     * ולידציה של קובץ PDF מועלה
     *
     * בודק שהקובץ הועלה בהצלחה ושהוא PDF אמיתי
     *
     * @param array $file $_FILES['pdf']
     * @return array ['valid' => bool, 'error' => string|null]
     *
     * @example
     * $validation = ValidationService::validatePDFFile($_FILES['pdf']);
     * if (!$validation['valid']) {
     *     Response::error($validation['error']);
     * }
     */
    public static function validatePDFFile($file) {
        // Check if file exists
        if (!isset($file) || !is_array($file)) {
            return [
                'valid' => false,
                'error' => 'לא התקבל קובץ'
            ];
        }

        // Check upload errors
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $errorMessages = [
                UPLOAD_ERR_INI_SIZE => 'הקובץ גדול מדי',
                UPLOAD_ERR_FORM_SIZE => 'הקובץ גדול מדי',
                UPLOAD_ERR_PARTIAL => 'הקובץ הועלה חלקית',
                UPLOAD_ERR_NO_FILE => 'לא נבחר קובץ',
                UPLOAD_ERR_NO_TMP_DIR => 'תיקייה זמנית חסרה',
                UPLOAD_ERR_CANT_WRITE => 'שגיאה בכתיבה לדיסק',
                UPLOAD_ERR_EXTENSION => 'העלאה נחסמה על ידי הרחבה'
            ];

            $error = $errorMessages[$file['error']] ?? 'שגיאה בהעלאה';

            return [
                'valid' => false,
                'error' => $error
            ];
        }

        // Check file was uploaded via HTTP POST
        if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            return [
                'valid' => false,
                'error' => 'קובץ לא תקין'
            ];
        }

        // Check MIME type
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        if ($mimeType !== 'application/pdf') {
            return [
                'valid' => false,
                'error' => 'הקובץ חייב להיות PDF'
            ];
        }

        // Check file size (if configured)
        if (defined('PDFEditor\Config::MAX_FILE_SIZE')) {
            $maxSize = \PDFEditor\Config::MAX_FILE_SIZE;
            if ($file['size'] > $maxSize) {
                $maxSizeMB = round($maxSize / 1024 / 1024, 1);
                return [
                    'valid' => false,
                    'error' => "הקובץ גדול מדי. מקסימום: {$maxSizeMB}MB"
                ];
            }
        }

        return ['valid' => true];
    }

    /**
     * ולידציה של שם קובץ פלט
     *
     * בודק ששם הקובץ המעובד תקין ועומד בתבנית הנדרשת
     *
     * @param string $filename שם הקובץ
     * @return array ['valid' => bool, 'error' => string|null]
     *
     * @example
     * $validation = ValidationService::validateOutputFilename($filename);
     */
    public static function validateOutputFilename($filename) {
        if (empty($filename)) {
            return [
                'valid' => false,
                'error' => 'שם קובץ חסר'
            ];
        }

        // Pattern: pdf_[a-f0-9.]+_output.pdf
        $pattern = '/^pdf_[a-f0-9.]+_output\.pdf$/';

        if (!preg_match($pattern, $filename)) {
            return [
                'valid' => false,
                'error' => 'שם קובץ לא תקין'
            ];
        }

        return ['valid' => true];
    }

    /**
     * ולידציה של מזהה תבנית
     *
     * בודק שמזהה התבנית תקין
     *
     * @param string $templateId מזהה תבנית
     * @return array ['valid' => bool, 'error' => string|null]
     *
     * @example
     * $validation = ValidationService::validateTemplateId($id);
     */
    public static function validateTemplateId($templateId) {
        if (empty($templateId)) {
            return [
                'valid' => false,
                'error' => 'מזהה תבנית חסר'
            ];
        }

        // Pattern: template_[a-f0-9]+
        $pattern = '/^template_[a-f0-9]+$/';

        if (!preg_match($pattern, $templateId)) {
            return [
                'valid' => false,
                'error' => 'מזהה תבנית לא תקין'
            ];
        }

        return ['valid' => true];
    }

    /**
     * ולידציה של שם תבנית
     *
     * בודק ששם התבנית תקין (אורך, תווים)
     *
     * @param string $name שם התבנית
     * @param int $minLength אורך מינימלי (default: 3)
     * @param int $maxLength אורך מקסימלי (default: 50)
     * @return array ['valid' => bool, 'error' => string|null]
     *
     * @example
     * $validation = ValidationService::validateTemplateName($name);
     */
    public static function validateTemplateName($name, $minLength = 3, $maxLength = 50) {
        if (empty($name)) {
            return [
                'valid' => false,
                'error' => 'שם תבנית חסר'
            ];
        }

        $name = trim($name);
        $length = mb_strlen($name, 'UTF-8');

        if ($length < $minLength) {
            return [
                'valid' => false,
                'error' => "שם התבנית קצר מדי (מינימום {$minLength} תווים)"
            ];
        }

        if ($length > $maxLength) {
            return [
                'valid' => false,
                'error' => "שם התבנית ארוך מדי (מקסימום {$maxLength} תווים)"
            ];
        }

        return ['valid' => true];
    }

    /**
     * ולידציה של מערך פריטים (טקסטים + תמונות)
     *
     * בודק שהפריטים בפורמט תקין
     *
     * @param array $items מערך פריטים
     * @return array ['valid' => bool, 'error' => string|null, 'errors' => array]
     *
     * @example
     * $validation = ValidationService::validateItems($allItems);
     */
    public static function validateItems($items) {
        if (!is_array($items)) {
            return [
                'valid' => false,
                'error' => 'פורמט פריטים לא תקין'
            ];
        }

        // Empty is valid (no items to add)
        if (empty($items)) {
            return ['valid' => true];
        }

        $errors = [];

        foreach ($items as $index => $item) {
            // Check if item is array
            if (!is_array($item)) {
                $errors[] = "פריט {$index}: פורמט לא תקין";
                continue;
            }

            // Check type
            if (!isset($item['type']) || !in_array($item['type'], ['text', 'image'])) {
                $errors[] = "פריט {$index}: סוג לא תקין";
                continue;
            }

            // Validate text item
            if ($item['type'] === 'text') {
                if (!isset($item['text']) || empty($item['text'])) {
                    $errors[] = "פריט טקסט {$index}: תוכן חסר";
                }
                if (!isset($item['font'])) {
                    $errors[] = "פריט טקסט {$index}: פונט חסר";
                }
                if (!isset($item['size']) || !is_numeric($item['size'])) {
                    $errors[] = "פריט טקסט {$index}: גודל לא תקין";
                }
            }

            // Validate image item
            if ($item['type'] === 'image') {
                if (!isset($item['base64']) || empty($item['base64'])) {
                    $errors[] = "פריט תמונה {$index}: נתוני תמונה חסרים";
                }
                if (!isset($item['width']) || !is_numeric($item['width'])) {
                    $errors[] = "פריט תמונה {$index}: רוחב לא תקין";
                }
                if (!isset($item['height']) || !is_numeric($item['height'])) {
                    $errors[] = "פריט תמונה {$index}: גובה לא תקין";
                }
            }
        }

        if (!empty($errors)) {
            return [
                'valid' => false,
                'error' => 'שגיאות ולידציה בפריטים',
                'errors' => $errors
            ];
        }

        return ['valid' => true];
    }

    /**
     * ולידציה כללית של שם קובץ
     *
     * מונעת path traversal ותווים מסוכנים
     *
     * @param string $filename שם קובץ
     * @return array ['valid' => bool, 'error' => string|null]
     *
     * @example
     * $validation = ValidationService::validateFilename($name);
     */
    public static function validateFilename($filename) {
        if (empty($filename)) {
            return [
                'valid' => false,
                'error' => 'שם קובץ חסר'
            ];
        }

        // Check for path traversal
        if (strpos($filename, '..') !== false) {
            return [
                'valid' => false,
                'error' => 'שם קובץ לא חוקי'
            ];
        }

        // Check for directory separators
        if (strpos($filename, '/') !== false || strpos($filename, '\\') !== false) {
            return [
                'valid' => false,
                'error' => 'שם קובץ לא חוקי'
            ];
        }

        // Check for null bytes
        if (strpos($filename, "\0") !== false) {
            return [
                'valid' => false,
                'error' => 'שם קובץ לא חוקי'
            ];
        }

        return ['valid' => true];
    }

    /**
     * ולידציה של נתיב קובץ
     *
     * בודק שהקובץ נמצא בתיקייה המותרת
     *
     * @param string $filepath נתיב מלא לקובץ
     * @param string $allowedDir תיקייה מותרת
     * @return array ['valid' => bool, 'error' => string|null]
     *
     * @example
     * $validation = ValidationService::validateFilePath($path, $outputDir);
     */
    public static function validateFilePath($filepath, $allowedDir) {
        if (empty($filepath)) {
            return [
                'valid' => false,
                'error' => 'נתיב קובץ חסר'
            ];
        }

        // Check file exists
        if (!file_exists($filepath)) {
            return [
                'valid' => false,
                'error' => 'הקובץ לא נמצא'
            ];
        }

        // Check real path
        $realPath = realpath($filepath);
        $realAllowedDir = realpath($allowedDir);

        if ($realPath === false || $realAllowedDir === false) {
            return [
                'valid' => false,
                'error' => 'נתיב לא חוקי'
            ];
        }

        // Check file is within allowed directory
        if (strpos($realPath, $realAllowedDir) !== 0) {
            return [
                'valid' => false,
                'error' => 'גישה לא מורשית לקובץ'
            ];
        }

        return ['valid' => true];
    }
}
