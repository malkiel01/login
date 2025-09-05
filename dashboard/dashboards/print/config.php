<?php
/**
 * PDF Text Printer - Configuration File
 */

$config = [
    // כללי
    'title' => 'PDF Text Printer - הדפסת טקסט על PDF',
    'version' => '2.0.0',
    'default_language' => 'he',
    'default_orientation' => 'P',
    
    // שיטות PDF זמינות
    'methods' => [
        'mpdf' => [
            'name' => 'mPDF',
            'description' => 'תמיכה מושלמת בעברית!',
            'file' => 'api/pdf-mpdf-overlay.php',
            'default' => true
        ],
        'tcpdf' => [
            'name' => 'TCPDF',
            'description' => 'תמיכה בקובץ ובעברית!',
            'file' => 'api/pdf-tcpdf-overlay.php'
        ],
        'fpdf' => [
            'name' => 'FPDF',
            'description' => 'ספרייה מתקדמת ליצירת PDF',
            'file' => 'api/pdf-fpdf.php'
        ],
        'minimal' => [
            'name' => 'Minimal PDF',
            'description' => 'יוצר PDF בסיסי ללא תלויות',
            'file' => 'api/pdf-minimal.php'
        ]
    ],
    
    // תבניות ברירת מחדל
    'default_template' => 'https://login.form.mbe-plus.com/dashboard/dashboards/print/templates/DeepEmpty.pdf',
    
    // תכונות
    'enable_preview' => false,  // תצוגה מקדימה (בפיתוח)
    'enable_image_upload' => false,  // העלאת תמונות (בפיתוח)
    'enable_text_rotation' => false,  // סיבוב טקסט (בפיתוח)
    'enable_debug' => true,
    
    // הגדרות ברירת מחדל
    'defaults' => [
        'fontSize' => 12,
        'color' => '#000000',
        'x' => 100,
        'y' => 100
    ],
    
    // הגבלות
    'limits' => [
        'max_values' => 100,
        'max_font_size' => 72,
        'min_font_size' => 8,
        'max_file_size' => 10 * 1024 * 1024  // 10MB
    ]
];

// פונקציות עזר
function getConfig($key, $default = null) {
    global $config;
    return isset($config[$key]) ? $config[$key] : $default;
}

function isFeatureEnabled($feature) {
    global $config;
    return isset($config[$feature]) && $config[$feature] === true;
}