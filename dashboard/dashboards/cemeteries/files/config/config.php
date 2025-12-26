<?php
/**
 * PDF Editor System - Configuration File
 *
 * קובץ הגדרות מרכזי למערכת עריכת PDF
 * מכיל את כל ההגדרות והקבועים הגלובליים של המערכת
 *
 * @version 1.0.0
 * @since Phase 1 Refactoring
 */

namespace PDFEditor;

class Config {

    // ===================================
    // Directory Paths
    // ===================================

    /**
     * תיקיית העלאות (קבצי PDF מקוריים)
     */
    const UPLOAD_DIR = __DIR__ . '/../uploads/';

    /**
     * תיקיית פלטים (קבצי PDF מעובדים)
     */
    const OUTPUT_DIR = __DIR__ . '/../outputs/';

    /**
     * תיקיית תבניות
     */
    const TEMPLATES_DIR = __DIR__ . '/../templates/';

    /**
     * תיקיית פונטים
     */
    const FONTS_DIR = __DIR__ . '/../fonts/';

    /**
     * קובץ הגדרות פונטים
     */
    const FONTS_JSON = __DIR__ . '/../fonts.json';

    // ===================================
    // Python Configuration
    // ===================================

    /**
     * נתיב ל-Python interpreter (virtual environment)
     */
    const PYTHON_VENV = '/home2/mbeplusc/public_html/form/login/venv/bin/python3';

    /**
     * נתיב לסקריפט עיבוד PDF
     */
    const PYTHON_SCRIPT = __DIR__ . '/../python/pdf_processor.py';

    /**
     * נתיב לסקריפט הישן (fallback)
     * @deprecated Will be removed in Phase 2
     */
    const PYTHON_SCRIPT_LEGACY = __DIR__ . '/../add_text_to_pdf.py';

    // ===================================
    // File Management
    // ===================================

    /**
     * גיל מקסימלי לקבצים זמניים (בשניות)
     * Default: 3600 (1 hour)
     */
    const MAX_FILE_AGE = 3600;

    /**
     * גודל מקסימלי לקובץ PDF (bytes)
     * Default: 10MB
     */
    const MAX_FILE_SIZE = 10 * 1024 * 1024;

    /**
     * סוגי קבצים מותרים להעלאה
     */
    const ALLOWED_MIME_TYPES = [
        'application/pdf'
    ];

    // ===================================
    // Template Settings
    // ===================================

    /**
     * קובץ רשימת תבניות
     */
    const TEMPLATES_JSON = __DIR__ . '/../templates.json';

    /**
     * אורך מינימלי לשם תבנית
     */
    const TEMPLATE_NAME_MIN_LENGTH = 3;

    /**
     * אורך מקסימלי לשם תבנית
     */
    const TEMPLATE_NAME_MAX_LENGTH = 50;

    // ===================================
    // Canvas & Editor Settings
    // ===================================

    /**
     * סקייל מינימלי לתצוגת PDF
     */
    const CANVAS_MIN_SCALE = 0.5;

    /**
     * סקייל מקסימלי לתצוגת PDF
     */
    const CANVAS_MAX_SCALE = 4.0;

    /**
     * צעד סקייל (zoom)
     */
    const CANVAS_SCALE_STEP = 0.25;

    /**
     * סקייל ברירת מחדל
     */
    const CANVAS_DEFAULT_SCALE = 2.0;

    // ===================================
    // Error Handling
    // ===================================

    /**
     * הצגת שגיאות (development/production)
     */
    const DEBUG_MODE = true;

    /**
     * רישום שגיאות ל-log
     */
    const LOG_ERRORS = true;

    /**
     * נתיב לקובץ log
     */
    const ERROR_LOG_FILE = __DIR__ . '/../logs/error.log';

    // ===================================
    // Security
    // ===================================

    /**
     * תבנית regex לולידציה של שמות קבצים מעובדים
     */
    const OUTPUT_FILENAME_PATTERN = '/^pdf_[a-f0-9.]+_output\.pdf$/';

    /**
     * תבנית regex לולידציה של מזהה תבנית
     */
    const TEMPLATE_ID_PATTERN = '/^template_[a-f0-9]+$/';

    // ===================================
    // Helper Methods
    // ===================================

    /**
     * בדיקה והכנת תיקיות
     *
     * @return void
     */
    public static function ensureDirectories() {
        $dirs = [
            self::UPLOAD_DIR,
            self::OUTPUT_DIR,
            self::TEMPLATES_DIR,
            dirname(self::ERROR_LOG_FILE)
        ];

        foreach ($dirs as $dir) {
            if (!file_exists($dir)) {
                mkdir($dir, 0755, true);
            }
        }
    }

    /**
     * קבלת כל ההגדרות כמערך
     * שימושי לייצוא JSON או debug
     *
     * @return array
     */
    public static function toArray() {
        $reflection = new \ReflectionClass(self::class);
        $constants = $reflection->getConstants();

        return $constants;
    }

    /**
     * בדיקה אם במצב debug
     *
     * @return bool
     */
    public static function isDebugMode() {
        return self::DEBUG_MODE;
    }

    /**
     * קבלת נתיב מלא לקובץ
     *
     * @param string $relativePath נתיב יחסי
     * @return string נתיב מלא
     */
    public static function path($relativePath) {
        return __DIR__ . '/../' . ltrim($relativePath, '/');
    }
}

// Auto-ensure directories on load
// Config::ensureDirectories(); // Uncomment in Phase 2
