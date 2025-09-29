<?php
/**
 * Fonts Configuration File
 * Location: /dashboard/dashboards/printPDF/fonts/fonts-config.php
 */

// Font definitions for PDF Editor
$FONTS_CONFIG = [
    'hebrew' => [
        'rubik' => [
            'name' => 'Rubik',
            'display_name' => 'רוביק',
            'file' => 'Rubik-Regular.ttf',
            'bold' => 'Rubik-Bold.ttf',
            'italic' => 'Rubik-Italic.ttf',
            'bold_italic' => 'Rubik-BoldItalic.ttf',
            'supports_rtl' => true
        ],
        'heebo' => [
            'name' => 'Heebo',
            'display_name' => 'היבו',
            'file' => 'Heebo-Regular.ttf',
            'bold' => 'Heebo-Bold.ttf',
            'italic' => null, // Heebo doesn't have italic
            'bold_italic' => null,
            'supports_rtl' => true
        ],
        'assistant' => [
            'name' => 'Assistant',
            'display_name' => 'אסיסטנט',
            'file' => 'Assistant-Regular.ttf',
            'bold' => 'Assistant-Bold.ttf',
            'italic' => null,
            'bold_italic' => null,
            'supports_rtl' => true
        ],
        'david' => [
            'name' => 'David',
            'display_name' => 'דוד',
            'file' => 'David-Regular.ttf',
            'bold' => 'David-Bold.ttf',
            'italic' => null,
            'bold_italic' => null,
            'supports_rtl' => true
        ],
        'miriam' => [
            'name' => 'Miriam',
            'display_name' => 'מרים',
            'file' => 'Miriam-Regular.ttf',
            'bold' => 'Miriam-Bold.ttf',
            'italic' => null,
            'bold_italic' => null,
            'supports_rtl' => true
        ]
    ],
    'english' => [
        'roboto' => [
            'name' => 'Roboto',
            'display_name' => 'Roboto',
            'file' => 'Roboto-Regular.ttf',
            'bold' => 'Roboto-Bold.ttf',
            'italic' => 'Roboto-Italic.ttf',
            'bold_italic' => 'Roboto-BoldItalic.ttf',
            'supports_rtl' => false
        ],
        'montserrat' => [
            'name' => 'Montserrat',
            'display_name' => 'Montserrat',
            'file' => 'Montserrat-Regular.ttf',
            'bold' => 'Montserrat-Bold.ttf',
            'italic' => 'Montserrat-Italic.ttf',
            'bold_italic' => 'Montserrat-BoldItalic.ttf',
            'supports_rtl' => false
        ],
        'opensans' => [
            'name' => 'Open Sans',
            'display_name' => 'Open Sans',
            'file' => 'OpenSans-Regular.ttf',
            'bold' => 'OpenSans-Bold.ttf',
            'italic' => 'OpenSans-Italic.ttf',
            'bold_italic' => 'OpenSans-BoldItalic.ttf',
            'supports_rtl' => false
        ],
        'poppins' => [
            'name' => 'Poppins',
            'display_name' => 'Poppins',
            'file' => 'Poppins-Regular.ttf',
            'bold' => 'Poppins-Bold.ttf',
            'italic' => 'Poppins-Italic.ttf',
            'bold_italic' => 'Poppins-BoldItalic.ttf',
            'supports_rtl' => false
        ]
    ],
    'arabic' => [
        'noto_arabic' => [
            'name' => 'Noto Sans Arabic',
            'display_name' => 'نوتو سانس العربية',
            'file' => 'NotoSansArabic-Regular.ttf',
            'bold' => 'NotoSansArabic-Bold.ttf',
            'italic' => null,
            'bold_italic' => null,
            'supports_rtl' => true
        ],
        'amiri' => [
            'name' => 'Amiri',
            'display_name' => 'أميري',
            'file' => 'Amiri-Regular.ttf',
            'bold' => 'Amiri-Bold.ttf',
            'italic' => 'Amiri-Italic.ttf',
            'bold_italic' => 'Amiri-BoldItalic.ttf',
            'supports_rtl' => true
        ],
        'cairo' => [
            'name' => 'Cairo',
            'display_name' => 'القاهرة',
            'file' => 'Cairo-Regular.ttf',
            'bold' => 'Cairo-Bold.ttf',
            'italic' => null,
            'bold_italic' => null,
            'supports_rtl' => true
        ]
    ],
    'system' => [
        'arial' => [
            'name' => 'Arial',
            'display_name' => 'Arial',
            'file' => 'arial.ttf',
            'bold' => 'arialbd.ttf',
            'italic' => 'ariali.ttf',
            'bold_italic' => 'arialbi.ttf',
            'supports_rtl' => true
        ],
        'times' => [
            'name' => 'Times New Roman',
            'display_name' => 'Times New Roman',
            'file' => 'times.ttf',
            'bold' => 'timesbd.ttf',
            'italic' => 'timesi.ttf',
            'bold_italic' => 'timesbi.ttf',
            'supports_rtl' => false
        ]
    ]
];

// CDN URLs for Google Fonts (for web display)
$FONT_CDNS = [
    'google' => [
        'rubik' => 'https://fonts.googleapis.com/css2?family=Rubik:wght@400;700&display=swap',
        'heebo' => 'https://fonts.googleapis.com/css2?family=Heebo:wght@400;700&display=swap',
        'assistant' => 'https://fonts.googleapis.com/css2?family=Assistant:wght@400;700&display=swap',
        'roboto' => 'https://fonts.googleapis.com/css2?family=Roboto:wght@400;700&display=swap',
        'montserrat' => 'https://fonts.googleapis.com/css2?family=Montserrat:wght@400;700&display=swap',
        'opensans' => 'https://fonts.googleapis.com/css2?family=Open+Sans:wght@400;700&display=swap',
        'poppins' => 'https://fonts.googleapis.com/css2?family=Poppins:wght@400;700&display=swap',
        'amiri' => 'https://fonts.googleapis.com/css2?family=Amiri:wght@400;700&display=swap',
        'cairo' => 'https://fonts.googleapis.com/css2?family=Cairo:wght@400;700&display=swap',
        'noto_arabic' => 'https://fonts.googleapis.com/css2?family=Noto+Sans+Arabic:wght@400;700&display=swap'
    ]
];

/**
 * Get fonts for a specific language
 */
function getFontsByLanguage($language) {
    global $FONTS_CONFIG;
    
    $fonts = [];
    
    // Add language-specific fonts
    if (isset($FONTS_CONFIG[$language])) {
        $fonts = array_merge($fonts, $FONTS_CONFIG[$language]);
    }
    
    // Always add system fonts
    if (isset($FONTS_CONFIG['system'])) {
        $fonts = array_merge($fonts, $FONTS_CONFIG['system']);
    }
    
    return $fonts;
}

/**
 * Get all RTL-supporting fonts
 */
function getRTLFonts() {
    global $FONTS_CONFIG;
    
    $rtlFonts = [];
    
    foreach ($FONTS_CONFIG as $category => $fonts) {
        foreach ($fonts as $key => $font) {
            if ($font['supports_rtl']) {
                $rtlFonts[$key] = $font;
            }
        }
    }
    
    return $rtlFonts;
}

/**
 * Get font file path
 */
function getFontPath($fontKey, $variant = 'regular') {
    global $FONTS_CONFIG;
    
    foreach ($FONTS_CONFIG as $category => $fonts) {
        if (isset($fonts[$fontKey])) {
            $font = $fonts[$fontKey];
            
            switch ($variant) {
                case 'bold':
                    return $font['bold'] ?? $font['file'];
                case 'italic':
                    return $font['italic'] ?? $font['file'];
                case 'bold_italic':
                    return $font['bold_italic'] ?? $font['bold'] ?? $font['file'];
                default:
                    return $font['file'];
            }
        }
    }
    
    return null;
}

/**
 * Get font display name
 */
function getFontDisplayName($fontKey, $language = 'en') {
    global $FONTS_CONFIG;
    
    foreach ($FONTS_CONFIG as $category => $fonts) {
        if (isset($fonts[$fontKey])) {
            return $fonts[$fontKey]['display_name'] ?? $fonts[$fontKey]['name'];
        }
    }
    
    return $fontKey;
}

/**
 * Check if font file exists
 */
function fontFileExists($fontFile) {
    $fontPath = __DIR__ . '/' . $fontFile;
    return file_exists($fontPath);
}

/**
 * Get all available fonts as JSON
 */
function getFontsJSON() {
    global $FONTS_CONFIG;
    
    $availableFonts = [];
    
    foreach ($FONTS_CONFIG as $category => $fonts) {
        foreach ($fonts as $key => $font) {
            // Check if at least the regular font file exists
            if (fontFileExists($font['file'])) {
                $availableFonts[$category][$key] = [
                    'name' => $font['name'],
                    'display_name' => $font['display_name'],
                    'supports_rtl' => $font['supports_rtl'],
                    'has_bold' => !empty($font['bold']) && fontFileExists($font['bold']),
                    'has_italic' => !empty($font['italic']) && fontFileExists($font['italic'])
                ];
            }
        }
    }
    
    return json_encode($availableFonts, JSON_UNESCAPED_UNICODE);
}

/**
 * Register font with TCPDF
 */
function registerFontWithTCPDF($tcpdf, $fontKey) {
    global $FONTS_CONFIG;
    
    foreach ($FONTS_CONFIG as $category => $fonts) {
        if (isset($fonts[$fontKey])) {
            $font = $fonts[$fontKey];
            $fontPath = __DIR__ . '/';
            
            // Register regular font
            if (fontFileExists($font['file'])) {
                $tcpdf->addTTFfont($fontPath . $font['file'], 'TrueTypeUnicode', '', 96);
            }
            
            // Register bold variant if exists
            if (!empty($font['bold']) && fontFileExists($font['bold'])) {
                $tcpdf->addTTFfont($fontPath . $font['bold'], 'TrueTypeUnicode', '', 96);
            }
            
            // Register italic variant if exists
            if (!empty($font['italic']) && fontFileExists($font['italic'])) {
                $tcpdf->addTTFfont($fontPath . $font['italic'], 'TrueTypeUnicode', '', 96);
            }
            
            // Register bold-italic variant if exists
            if (!empty($font['bold_italic']) && fontFileExists($font['bold_italic'])) {
                $tcpdf->addTTFfont($fontPath . $font['bold_italic'], 'TrueTypeUnicode', '', 96);
            }
            
            return true;
        }
    }
    
    return false;
}
?>