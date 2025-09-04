<?php
/**
 * HTML Generator for PDF
 * יוצר HTML שניתן להמיר ל-PDF
 */

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit();
}

// בדיקת חיבור
if (isset($_GET['test'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'method' => 'HTML']);
    exit();
}

// טיפול בהצגה/הורדה
if (isset($_GET['file'])) {
    $file = basename($_GET['file']);
    $filepath = 'output/' . $file;
    
    if (!file_exists($filepath)) {
        header('HTTP/1.0 404 Not Found');
        echo 'File not found';
        exit();
    }
    
    $action = $_GET['action'] ?? 'view';
    
    if ($action === 'download') {
        header('Content-Type: text/html');
        header('Content-Disposition: attachment; filename="' . $file . '"');
        header('Content-Length: ' . filesize($filepath));
    } else {
        header('Content-Type: text/html');
    }
    
    readfile($filepath);
    exit();
}

// עיבוד בקשות POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        echo json_encode(['success' => false, 'error' => 'Invalid JSON']);
        exit();
    }
    
    // צור תיקייה אם לא קיימת
    if (!file_exists('output')) {
        mkdir('output', 0777, true);
    }
    
    try {
        $language = $input['language'] ?? 'en';
        $isRTL = $language === 'he';
        
        $html = '<!DOCTYPE html>
<html lang="' . $language . '" dir="' . ($isRTL ? 'rtl' : 'ltr') . '">
<head>
    <meta charset="UTF-8">
    <title>PDF Document</title>
    <style>
        @page { 
            size: A4; 
            margin: 20mm;
        }
        @media print {
            body { 
                margin: 0;
            }
            .no-print {
                display: none !important;
            }
        }
        body { 
            font-family: Arial, "Noto Sans Hebrew", sans-serif; 
            position: relative;
            width: 210mm;
            height: 297mm;
            margin: 0;
            padding: 0;
        }
        .text-element {
            position: absolute;
        }
        .print-button {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 10px 20px;
            background: #4CAF50;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            z-index: 1000;
            font-size: 16px;
        }
        .print-button:hover {
            background: #45a049;
        }
    </style>
    <script>
        function printToPDF() {
            window.print();
        }
        
        // הדפס אוטומטית אם יש פרמטר auto_print
        window.onload = function() {
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.get("auto_print") === "true") {
                setTimeout(function() {
                    window.print();
                }, 500);
            }
        }
    </script>
</head>
<body>
    <button class="print-button no-print" onclick="printToPDF()">🖨️ הדפס ל-PDF</button>';
        
        // הוסף את הטקסטים
        foreach ($input['values'] as $value) {
            $style = 'left:' . ($value['x'] ?? 100) . 'px;';
            $style .= 'top:' . ($value['y'] ?? 100) . 'px;';
            
            if (isset($value['fontSize'])) {
                $style .= 'font-size:' . $value['fontSize'] . 'px;';
            }
            
            if (isset($value['color']) && is_array($value['color'])) {
                $style .= 'color:rgb(' . implode(',', $value['color']) . ');';
            }
            
            $html .= '<div class="text-element" style="' . $style . '">' . 
                     htmlspecialchars($value['text'] ?? '') . '</div>';
        }
        
        $html .= '</body></html>';
        
        // שמור
        $filename = 'output/html_' . date('Ymd_His') . '_' . rand(1000, 9999) . '.html';
        file_put_contents($filename, $html);
        
        $base_url = 'https://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['SCRIPT_NAME']);
        
        echo json_encode([
            'success' => true,
            'method' => 'HTML to PDF',
            'filename' => basename($filename),
            'view_url' => $base_url . '/pdf-html.php?file=' . basename($filename),
            'print_url' => $base_url . '/pdf-html.php?file=' . basename($filename) . '&auto_print=true',
            'download_url' => $base_url . '/pdf-html.php?file=' . basename($filename) . '&action=download',
            'direct_url' => $base_url . '/' . $filename,
            'message' => 'HTML נוצר. לחץ על כפתור "הדפס ל-PDF" או Ctrl+P'
        ]);
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}
?>