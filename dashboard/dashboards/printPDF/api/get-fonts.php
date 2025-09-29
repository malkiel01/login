<?php
/**
 * Get Fonts API
 * Location: /dashboard/dashboards/printPDF/api/get-fonts.php
 */

// Include configuration
require_once $_SERVER['DOCUMENT_ROOT'] . '/dashboard/dashboards/printPDF/config.php';

// Check user permission
if (!checkPermission('view', 'pdf_editor')) {
    http_response_code(403);
    die(json_encode([
        'success' => false,
        'message' => 'אין הרשאה'
    ]));
}

// Set headers
header('Content-Type: application/json');

// Include fonts configuration
require_once FONTS_PATH . 'fonts-config.php';

// Get language parameter
$language = $_GET['language'] ?? 'all';

try {
    $fonts = [];
    
    if ($language === 'all') {
        // Return all fonts grouped by language
        $fonts = [
            'hebrew' => getLanguageFonts('hebrew'),
            'english' => getLanguageFonts('english'),
            'arabic' => getLanguageFonts('arabic'),
            'system' => getLanguageFonts('system')
        ];
    } else {
        // Return fonts for specific language
        $fonts = getLanguageFonts($language);
    }
    
    // Add availability status
    $fonts = checkFontAvailability($fonts);
    
    echo json_encode([
        'success' => true,
        'data' => [
            'fonts' => $fonts,
            'cdnUrls' => $FONT_CDNS ?? []
        ]
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

/**
 * Get fonts for specific language
 */
function getLanguageFonts($language) {
    global $FONTS_CONFIG;
    
    if (!isset($FONTS_CONFIG[$language])) {
        return [];
    }
    
    $fonts = [];
    foreach ($FONTS_CONFIG[$language] as $key => $font) {
        $fonts[] = [
            'id' => $key,
            'name' => $font['name'],
            'displayName' => $font['display_name'],
            'supportsRTL' => $font['supports_rtl'],
            'variants' => [
                'regular' => !empty($font['file']),
                'bold' => !empty($font['bold']),
                'italic' => !empty($font['italic']),
                'boldItalic' => !empty($font['bold_italic'])
            ]
        ];
    }
    
    return $fonts;
}

/**
 * Check which font files actually exist
 */
function checkFontAvailability($fonts) {
    if (!is_array($fonts)) {
        return $fonts;
    }
    
    // If it's a grouped array (by language)
    if (isset($fonts['hebrew']) || isset($fonts['english'])) {
        foreach ($fonts as $language => &$languageFonts) {
            foreach ($languageFonts as &$font) {
                $font['available'] = checkFontFileExists($font['id']);
            }
        }
    } else {
        // Single language array
        foreach ($fonts as &$font) {
            $font['available'] = checkFontFileExists($font['id']);
        }
    }
    
    return $fonts;
}

/**
 * Check if font file exists
 */
function checkFontFileExists($fontId) {
    global $FONTS_CONFIG;
    
    foreach ($FONTS_CONFIG as $category => $fonts) {
        if (isset($fonts[$fontId])) {
            $fontFile = FONTS_PATH . $fonts[$fontId]['file'];
            return file_exists($fontFile);
        }
    }
    
    return false;
}
?>