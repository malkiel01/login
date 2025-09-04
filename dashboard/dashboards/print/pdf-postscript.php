<?php
/**
 * PostScript Generator
 * יוצר קובץ PS להמרה ל-PDF
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
    echo json_encode(['success' => true, 'method' => 'PostScript']);
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
        header('Content-Type: application/postscript');
        header('Content-Disposition: attachment; filename="' . $file . '"');
        header('Content-Length: ' . filesize($filepath));
    } else {
        header('Content-Type: application/postscript');
        header('Content-Disposition: inline; filename="' . $file . '"');
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
        $ps = "%!PS-Adobe-3.0\n";
        $ps .= "%%Pages: 1\n";
        $ps .= "%%Page: 1 1\n";
        $ps .= "/Helvetica findfont 12 scalefont setfont\n";
        
        // הוסף את הטקסטים
        foreach ($input['values'] as $value) {
            $x = $value['x'] ?? 100;
            $y = 792 - ($value['y'] ?? 100); // PS coordinates from bottom
            $text = addslashes($value['text'] ?? '');
            
            if (isset($value['fontSize'])) {
                $ps .= "/Helvetica findfont " . $value['fontSize'] . " scalefont setfont\n";
            }
            
            $ps .= "$x $y moveto\n";
            $ps .= "($text) show\n";
        }
        
        $ps .= "showpage\n%%EOF";
        
        // שמור
        $filename = 'output/ps_' . date('Ymd_His') . '_' . rand(1000, 9999) . '.ps';
        file_put_contents($filename, $ps);
        
        $base_url = 'https://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['SCRIPT_NAME']);
        
        echo json_encode([
            'success' => true,
            'method' => 'PostScript',
            'filename' => basename($filename),
            'view_url' => $base_url . '/pdf-postscript.php?file=' . basename($filename),
            'download_url' => $base_url . '/pdf-postscript.php?file=' . basename($filename) . '&action=download',
            'direct_url' => $base_url . '/' . $filename,
            'message' => 'PostScript file created. Convert to PDF using ps2pdf.com'
        ]);
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}
?>