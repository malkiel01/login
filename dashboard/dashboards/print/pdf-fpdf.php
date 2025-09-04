<?php
/**
 * FPDF Generator
 * דורש התקנת FPDF
 * תמיכה בעברית דרך היפוך טקסט
 */

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit();
}

// בדיקת חיבור והאם FPDF מותקן
if (isset($_GET['test'])) {
    header('Content-Type: application/json');
    $fpdf_exists = file_exists('fpdf/fpdf.php');
    echo json_encode([
        'success' => true, 
        'method' => 'FPDF',
        'installed' => $fpdf_exists,
        'message' => $fpdf_exists ? 'FPDF is installed' : 'FPDF not found - please install'
    ]);
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
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $file . '"');
        header('Content-Length: ' . filesize($filepath));
    } else {
        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="' . $file . '"');
    }
    
    readfile($filepath);
    exit();
}

// טען FPDF אם קיים
if (file_exists('fpdf/fpdf.php')) {
    require_once('fpdf/fpdf.php');
    
    class Hebrew_FPDF extends FPDF {
        
        function Text($x, $y, $txt) {
            // בדוק אם יש עברית בטקסט
            if (preg_match('/[\x{0590}-\x{05FF}]/u', $txt)) {
                // הפוך את הטקסט העברי
                $txt = $this->reverseHebrew($txt);
                // המר לקידוד Windows-1255
                $txt = iconv('UTF-8', 'Windows-1255//TRANSLIT//IGNORE', $txt);
            }
            parent::Text($x, $y, $txt);
        }
        
        // פונקציה להיפוך טקסט עברי
        private function reverseHebrew($text) {
            // פרק למילים
            $words = preg_split('/\s+/u', $text);
            $reversed = [];
            
            foreach ($words as $word) {
                // הפוך כל מילה עברית
                if (preg_match('/[\x{0590}-\x{05FF}]/u', $word)) {
                    $chars = mb_str_split($word, 1, 'UTF-8');
                    $word = implode('', array_reverse($chars));
                }
                $reversed[] = $word;
            }
            
            // הפוך את סדר המילים
            return implode(' ', array_reverse($reversed));
        }
    }
}

// עיבוד בקשות POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        echo json_encode(['success' => false, 'error' => 'Invalid JSON']);
        exit();
    }
    
    // בדוק אם FPDF מותקן
    if (!class_exists('FPDF')) {
        echo json_encode([
            'success' => false, 
            'error' => 'FPDF not installed. Download from http://www.fpdf.org/'
        ]);
        exit();
    }
    
    // צור תיקייה אם לא קיימת
    if (!file_exists('output')) {
        mkdir('output', 0777, true);
    }
    
    try {
        $pdf = new Hebrew_FPDF();
        $pdf->AddPage();
        
        // נסה להגדיר פונט שתומך בעברית
        // אם יש פונט עברי מותקן
        if (file_exists('fpdf/font/arial.php')) {
            $pdf->AddFont('arial', '', 'arial.php');
            $pdf->SetFont('arial', '', 12);
        } else {
            // אחרת השתמש בפונט רגיל
            $pdf->SetFont('Arial', '', 12);
        }
        
        // הוסף את הטקסטים
        foreach ($input['values'] as $value) {
            if (isset($value['fontSize'])) {
                $pdf->SetFontSize($value['fontSize']);
            }
            
            if (isset($value['color']) && is_array($value['color'])) {
                $pdf->SetTextColor($value['color'][0], $value['color'][1], $value['color'][2]);
            }
            
            $pdf->Text(
                $value['x'] ?? 100,
                $value['y'] ?? 100,
                $value['text'] ?? ''
            );
            
            // אפס צבע
            $pdf->SetTextColor(0, 0, 0);
        }
        
        // שמור
        $filename = 'output/fpdf_' . date('Ymd_His') . '_' . rand(1000, 9999) . '.pdf';
        $pdf->Output('F', $filename);
        
        $base_url = 'https://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['SCRIPT_NAME']);
        
        echo json_encode([
            'success' => true,
            'method' => 'FPDF',
            'filename' => basename($filename),
            'view_url' => $base_url . '/pdf-fpdf.php?file=' . basename($filename),
            'download_url' => $base_url . '/pdf-fpdf.php?file=' . basename($filename) . '&action=download',
            'direct_url' => $base_url . '/' . $filename
        ]);
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}
?>