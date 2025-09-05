<?php
/**
 * Minimal PDF Generator
 * יוצר PDF בסיסי ללא תלויות
 * גרסה מתוקנת עם תמיכה בעברית
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
    echo json_encode(['success' => true, 'method' => 'Minimal PDF']);
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

class MinimalPDF {
    private $pages = [];
    private $currentPage = null;
    
    public function addPage() {
        $this->currentPage = ['texts' => []];
        $this->pages[] = &$this->currentPage;
    }
    
    public function addText($x, $y, $text, $fontSize = 12) {
        if ($this->currentPage === null) {
            $this->addPage();
        }
        
        $this->currentPage['texts'][] = [
            'x' => $x,
            'y' => $y,
            'text' => $text,
            'fontSize' => $fontSize
        ];
    }
    
    /**
     * פונקציה להמרת טקסט עברי ל-PDF
     * מטפלת בסדר הנכון של האותיות
     */
    private function reverseHebrew($text) {
        // בדוק אם יש עברית בטקסט
        if (preg_match('/[\x{0590}-\x{05FF}]/u', $text)) {
            // הפוך את סדר האותיות העבריות
            $text = strrev($text);
        }
        return $text;
    }
    
    /**
     * המרת טקסט למחרוזת PDF
     * תומך בעברית ואנגלית
     */
    private function encodeText($text) {
        // בדוק אם הטקסט מכיל עברית
        if (preg_match('/[\x{0590}-\x{05FF}]/u', $text)) {
            // הפוך את הטקסט העברי לסדר הנכון
            $text = hebrev($text); // פונקציה מובנית של PHP
            
            // המר ל-ISO-8859-8 (קידוד עברי)
            $encoded = iconv('UTF-8', 'ISO-8859-8//IGNORE', $text);
            if ($encoded !== false) {
                return '(' . str_replace(['(', ')', '\\'], ['\\(', '\\)', '\\\\'], $encoded) . ')';
            }
        }
        
        // לאנגלית ותווים רגילים
        return '(' . str_replace(['(', ')', '\\'], ['\\(', '\\)', '\\\\'], $text) . ')';
    }
    
    public function save($filename) {
        $pdf = "%PDF-1.4\n";
        $objects = [];
        
        $objects[] = "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj";
        
        $kids = [];
        for ($i = 0; $i < count($this->pages); $i++) {
            $kids[] = (3 + $i * 2) . " 0 R";
        }
        $objects[] = "2 0 obj\n<< /Type /Pages /Kids [" . implode(' ', $kids) . "] /Count " . count($this->pages) . " >>\nendobj";
        
        $objNum = 3;
        foreach ($this->pages as $page) {
            // הגדר פונט עם תמיכה בעברית
            $objects[] = "$objNum 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Contents " . ($objNum + 1) . " 0 R /Resources << /Font << /F1 << /Type /Font /Subtype /Type1 /BaseFont /Courier /Encoding /WinAnsiEncoding >> >> >> >>\nendobj";
            // $objects[] = "$objNum 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Contents " . ($objNum + 1) . " 0 R /Resources << /Font << /F1 << /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >> >> >> >>\nendobj";
            $objNum++;
            
            $stream = "BT\n";
            foreach ($page['texts'] as $textData) {
                $stream .= "/F1 " . $textData['fontSize'] . " Tf\n";
                $stream .= $textData['x'] . " " . (792 - $textData['y']) . " Td\n";
                
                // קידוד הטקסט
                $encodedText = $this->encodeText($textData['text']);
                $stream .= $encodedText . " Tj\n";
            }
            $stream .= "ET";
            
            $objects[] = "$objNum 0 obj\n<< /Length " . strlen($stream) . " >>\nstream\n$stream\nendstream\nendobj";
            $objNum++;
        }
        
        $pdf .= implode("\n", $objects);
        $pdf .= "\nxref\n0 " . (count($objects) + 1) . "\n";
        $pdf .= "0000000000 65535 f\n";
        
        $offset = strlen("%PDF-1.4\n");
        foreach ($objects as $object) {
            $pdf .= sprintf("%010d 00000 n\n", $offset);
            $offset += strlen($object) + 1;
        }
        
        $pdf .= "trailer\n<< /Size " . (count($objects) + 1) . " /Root 1 0 R >>\n";
        $pdf .= "startxref\n$offset\n%%EOF";
        
        file_put_contents($filename, $pdf);
        return file_exists($filename);
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
    
    // צור תיקייה אם לא קיימת
    if (!file_exists('output')) {
        mkdir('output', 0777, true);
    }
    
    try {
        $pdf = new MinimalPDF();
        $pdf->addPage();
        
        // הוסף את הטקסטים
        foreach ($input['values'] as $value) {
            $pdf->addText(
                $value['x'] ?? 100,
                $value['y'] ?? 100,
                $value['text'] ?? '',
                $value['fontSize'] ?? 12
            );
        }
        
        // שמור
        $filename = 'output/minimal_' . date('Ymd_His') . '_' . rand(1000, 9999) . '.pdf';
        
        if ($pdf->save($filename)) {
            $base_url = 'https://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['SCRIPT_NAME']);
            
            echo json_encode([
                'success' => true,
                'method' => 'Minimal PDF',
                'filename' => basename($filename),
                'view_url' => $base_url . '/pdf-minimal.php?file=' . basename($filename),
                'download_url' => $base_url . '/pdf-minimal.php?file=' . basename($filename) . '&action=download',
                'direct_url' => $base_url . '/' . $filename,
                'note' => 'Hebrew support is limited in basic PDF. Use HTML or FPDF with Hebrew fonts for better results.'
            ]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Failed to save PDF']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}
?>