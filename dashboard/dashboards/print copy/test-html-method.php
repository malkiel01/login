<?php
/**
 * בדיקה ישירה של שיטת HTML
 */

// מנע כל פלט
ob_start();

// טען את הקובץ
require_once('process-pdf-simple.php');

// נקה את הבאפר
ob_clean();

// הגדר headers
header('Content-Type: application/json');

// נתונים לבדיקה
$testData = [
    'method' => 'html',
    'language' => 'he',
    'values' => [
        ['text' => 'Test HTML Method', 'x' => 100, 'y' => 100],
        ['text' => 'בדיקת עברית', 'x' => 100, 'y' => 120]
    ]
];

try {
    $pdfCreator = new SimplePDF();
    $result = $pdfCreator->createHTMLForPDF($testData);
    
    // הוסף URLs אם חסרים
    if (!isset($result['view_url'])) {
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'];
        $script = dirname($_SERVER['SCRIPT_NAME']);
        
        $result['view_url'] = $protocol . '://' . $host . $script . '/process-pdf-simple.php?file=' . $result['filename'];
        $result['download_url'] = $protocol . '://' . $host . $script . '/process-pdf-simple.php?file=' . $result['filename'] . '&action=download';
        $result['direct_url'] = $protocol . '://' . $host . $script . '/output/' . $result['filename'];
    }
    
    echo json_encode($result, JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

ob_end_flush();
?>