<?php
/**
 * Font Configuration for mPDF
 */

function getFontConfig() {
    return [
        'fontDir' => [
            __DIR__ . '/vendor/mpdf/mpdf/ttfonts/',  // mPDF default fonts
            __DIR__ . '/assets/fonts/',               // Custom fonts
        ],
        'fontdata' => [
            // Default fonts
            'dejavusans' => [
                'R' => 'DejaVuSans.ttf',
                'B' => 'DejaVuSans-Bold.ttf',
                'I' => 'DejaVuSans-Oblique.ttf',
                'BI' => 'DejaVuSans-BoldOblique.ttf',
                'useOTL' => 0xFF,
                'useKashida' => 75,
            ],
            
            // Hebrew fonts - check if files exist before adding
            'rubik' => [
                'R' => 'Rubik-Regular.ttf',
                'B' => 'Rubik-Bold.ttf',
                'useOTL' => 0xFF,
                'useKashida' => 75,
            ],
            
            'heebo' => [
                'R' => 'Heebo-Regular.ttf',
                'B' => 'Heebo-Bold.ttf',
                'useOTL' => 0xFF,
                'useKashida' => 75,
            ],
            
            'assistant' => [
                'R' => 'Assistant-Regular.ttf',
                'B' => 'Assistant-Bold.ttf',
                'useOTL' => 0xFF,
                'useKashida' => 75,
            ],
            
            // Fallback to system fonts if available
            'arial' => [
                'R' => 'Arial.ttf',
                'B' => 'Arial-Bold.ttf',
                'I' => 'Arial-Italic.ttf',
                'BI' => 'Arial-BoldItalic.ttf',
            ],
        ]
    ];
}

/**
 * Validate font family and return valid font name for mPDF
 */
function validateFontFamily($fontFamily) {
    $validFonts = [
        'dejavusans' => 'dejavusans',
        'rubik' => 'rubik',
        'heebo' => 'heebo',
        'assistant' => 'assistant',
        'arial' => 'arial',
    ];
    
    // Check if font exists in our list
    if (isset($validFonts[$fontFamily])) {
        // Check if font file exists
        $fontFile = __DIR__ . '/assets/fonts/' . ucfirst($fontFamily) . '-Regular.ttf';
        if (file_exists($fontFile)) {
            return $fontFamily;
        }
    }
    
    // Default fallback
    return 'dejavusans';
}