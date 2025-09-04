<?php
/**
 * PDF Text Printer - Simplified Version Without Composer
 * גרסה פשוטה ללא צורך ב-Composer
 * 
 * This version uses FPDF which is a single file library
 * Download FPDF from: http://www.fpdf.org/
 */

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// CORS headers
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json; charset=UTF-8');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Test connection endpoint
if (isset($_GET['test'])) {
    echo json_encode(['success' => true, 'message' => 'Server is running (No-Composer Version)']);
    exit();
}

// UTF-8 FPDF Extension - defined outside of any method
if (file_exists('fpdf/fpdf.php')) {
    require_once('fpdf/fpdf.php');
    
    class UTF8_FPDF extends FPDF {
        function Text($x, $y, $txt) {
            // Convert UTF-8 to Windows-1252 for basic Latin characters
            // For Hebrew support, you'll need Hebrew fonts
            $txt = @iconv('UTF-8', 'windows-1252//IGNORE', $txt);
            parent::Text($x, $y, $txt);
        }
    }
}

/**
 * Simple PDF Class using FPDF
 */
class SimplePDF {
    private $pdf;
    private $outputDir = 'output/';
    
    public function __construct() {
        // Create output directory if doesn't exist
        if (!file_exists($this->outputDir)) {
            mkdir($this->outputDir, 0777, true);
        }
    }
    
    /**
     * Method 1: Using FPDF (Single file library)
     */
    public function createWithFPDF($data) {
        // Check if FPDF class exists
        if (class_exists('UTF8_FPDF')) {
            $this->pdf = new UTF8_FPDF();
            $this->pdf->AddPage();
            $this->pdf->SetFont('Arial', '', 12);
            
            // Add values
            foreach ($data['values'] as $value) {
                if (isset($value['fontSize'])) {
                    $this->pdf->SetFontSize($value['fontSize']);
                }
                
                if (isset($value['color']) && is_array($value['color'])) {
                    $this->pdf->SetTextColor($value['color'][0], $value['color'][1], $value['color'][2]);
                }
                
                $this->pdf->Text($value['x'], $value['y'], $value['text']);
            }
            
            $filename = $this->generateFilename();
            $this->pdf->Output('F', $filename);
            
            return [
                'success' => true,
                'method' => 'FPDF',
                'filename' => basename($filename),
                'download_url' => $this->getDownloadUrl($filename)
            ];
        } else {
            return $this->createWithNativePHP($data);
        }
    }
    
    /**
     * Method 2: Create PDF using native PHP (very basic)
     */
    public function createWithNativePHP($data) {
        $filename = $this->generateFilename();
        
        // Basic PDF structure
        $pdf_content = "%PDF-1.4\n";
        $pdf_content .= "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n";
        $pdf_content .= "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n";
        $pdf_content .= "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources 4 0 R /MediaBox [0 0 612 792] /Contents 5 0 R >>\nendobj\n";
        $pdf_content .= "4 0 obj\n<< /Font << /F1 << /Type /Font /Subtype /Type1 /BaseFont /Helvetica >> >> >>\nendobj\n";
        
        // Content stream
        $stream = "BT\n/F1 12 Tf\n";
        foreach ($data['values'] as $value) {
            $x = $value['x'];
            $y = 792 - $value['y']; // PDF coordinates start from bottom
            $text = str_replace(['(', ')', '\\'], ['\\(', '\\)', '\\\\'], $value['text']);
            $fontSize = isset($value['fontSize']) ? $value['fontSize'] : 12;
            $stream .= "/F1 $fontSize Tf\n";
            $stream .= "$x $y Td\n($text) Tj\n";
        }
        $stream .= "ET";
        
        $pdf_content .= "5 0 obj\n<< /Length " . strlen($stream) . " >>\nstream\n$stream\nendstream\nendobj\n";
        $pdf_content .= "xref\n0 6\n";
        $pdf_content .= "0000000000 65535 f\n";
        $pdf_content .= "trailer\n<< /Size 6 /Root 1 0 R >>\n";
        $pdf_content .= "startxref\n116\n%%EOF";
        
        file_put_contents($filename, $pdf_content);
        
        return [
            'success' => true,
            'method' => 'Native PHP (No dependencies)',
            'filename' => basename($filename),
            'download_url' => $this->getDownloadUrl($filename)
        ];
    }
    
    /**
     * Method 3: Create HTML for PDF conversion
     */
    public function createHTMLForPDF($data) {
        $language = $data['language'] ?? 'en';
        $isRTL = $language === 'he';
        
        $html = '<!DOCTYPE html>
<html lang="' . $language . '" dir="' . ($isRTL ? 'rtl' : 'ltr') . '">
<head>
    <meta charset="UTF-8">
    <style>
        @page { size: A4; margin: 0; }
        body { 
            font-family: Arial, sans-serif; 
            position: relative;
            width: 210mm;
            height: 297mm;
            margin: 0;
            padding: 0;
        }
        .text-element {
            position: absolute;
        }
    </style>
</head>
<body>';
        
        foreach ($data['values'] as $value) {
            $style = 'left:' . $value['x'] . 'px;top:' . $value['y'] . 'px;';
            
            if (isset($value['fontSize'])) {
                $style .= 'font-size:' . $value['fontSize'] . 'px;';
            }
            
            if (isset($value['color']) && is_array($value['color'])) {
                $style .= 'color:rgb(' . implode(',', $value['color']) . ');';
            }
            
            $html .= '<div class="text-element" style="' . $style . '">' . 
                     htmlspecialchars($value['text']) . '</div>';
        }
        
        $html .= '</body></html>';
        
        $filename = $this->outputDir . 'html_' . time() . '_' . rand(1000, 9999) . '.html';
        file_put_contents($filename, $html);
        
        return [
            'success' => true,
            'method' => 'HTML',
            'filename' => basename($filename),
            'download_url' => $this->getDownloadUrl($filename),
            'html_content' => $html,
            'message' => 'HTML created. Use browser print-to-PDF or online converter.'
        ];
    }
    
    /**
     * Method 4: Using system commands (if available)
     */
    public function createWithSystemCommand($data) {
        // First create HTML
        $htmlResult = $this->createHTMLForPDF($data);
        $htmlFile = $this->outputDir . $htmlResult['filename'];
        $pdfFile = str_replace('.html', '.pdf', $htmlFile);
        
        // Try different commands
        $commands = [
            'wkhtmltopdf' => "wkhtmltopdf $htmlFile $pdfFile 2>&1",
            'chrome' => "google-chrome --headless --print-to-pdf=$pdfFile $htmlFile 2>&1",
            'chromium' => "chromium-browser --headless --print-to-pdf=$pdfFile $htmlFile 2>&1"
        ];
        
        foreach ($commands as $tool => $command) {
            exec("which $tool", $output, $return);
            if ($return === 0) {
                exec($command, $output, $return);
                if ($return === 0 && file_exists($pdfFile)) {
                    return [
                        'success' => true,
                        'method' => "System Command ($tool)",
                        'filename' => basename($pdfFile),
                        'download_url' => $this->getDownloadUrl($pdfFile)
                    ];
                }
            }
        }
        
        return $htmlResult; // Return HTML if no PDF converter available
    }
    
    private function generateFilename() {
        return $this->outputDir . 'pdf_' . date('Y-m-d_H-i-s') . '_' . rand(1000, 9999) . '.pdf';
    }
    
    private function getDownloadUrl($filename) {
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'];
        $script = dirname($_SERVER['SCRIPT_NAME']);
        return $protocol . '://' . $host . $script . '/' . $filename;
    }
}

/**
 * Alternative: Minimal PDF Creator Class
 */
class MinimalPDF {
    private $pages = [];
    private $currentPage = null;
    
    public function addPage() {
        $this->currentPage = [
            'texts' => []
        ];
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
    
    public function save($filename) {
        $pdf = "%PDF-1.4\n";
        $objects = [];
        
        // Catalog
        $objects[] = "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj";
        
        // Pages
        $kids = [];
        for ($i = 0; $i < count($this->pages); $i++) {
            $kids[] = (3 + $i * 2) . " 0 R";
        }
        $objects[] = "2 0 obj\n<< /Type /Pages /Kids [" . implode(' ', $kids) . "] /Count " . count($this->pages) . " >>\nendobj";
        
        // Each page
        $objNum = 3;
        foreach ($this->pages as $page) {
            // Page object
            $objects[] = "$objNum 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Contents " . ($objNum + 1) . " 0 R /Resources << /Font << /F1 << /Type /Font /Subtype /Type1 /BaseFont /Helvetica >> >> >> >>\nendobj";
            $objNum++;
            
            // Content stream
            $stream = "BT\n";
            foreach ($page['texts'] as $text) {
                $stream .= "/F1 " . $text['fontSize'] . " Tf\n";
                $stream .= $text['x'] . " " . (792 - $text['y']) . " Td\n";
                $stream .= "(" . str_replace(['(', ')', '\\'], ['\\(', '\\)', '\\\\'], $text['text']) . ") Tj\n";
            }
            $stream .= "ET";
            
            $objects[] = "$objNum 0 obj\n<< /Length " . strlen($stream) . " >>\nstream\n$stream\nendstream\nendobj";
            $objNum++;
        }
        
        // Write PDF
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

// Main processing
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        echo json_encode([
            'success' => false,
            'error' => 'Invalid JSON input'
        ]);
        exit();
    }
    
    try {
        $pdfCreator = new SimplePDF();
        
        // Try different methods in order of preference
        $result = null;
        
        // Method 1: Try FPDF if available
        if (class_exists('UTF8_FPDF')) {
            $result = $pdfCreator->createWithFPDF($input);
        }
        // Method 2: Try system command (Linux/Mac)
        elseif (PHP_OS_FAMILY !== 'Windows') {
            $result = $pdfCreator->createWithSystemCommand($input);
        }
        // Method 3: Use Minimal PDF (always works)
        else {
            $outputDir = 'output/';
            if (!file_exists($outputDir)) {
                mkdir($outputDir, 0777, true);
            }
            
            $pdf = new MinimalPDF();
            $pdf->addPage();
            
            foreach ($input['values'] as $value) {
                $pdf->addText(
                    $value['x'], 
                    $value['y'], 
                    $value['text'], 
                    isset($value['fontSize']) ? $value['fontSize'] : 12
                );
            }
            
            $filename = $outputDir . 'pdf_' . date('Y-m-d_H-i-s') . '_' . rand(1000, 9999) . '.pdf';
            $pdf->save($filename);
            
            $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
            $host = $_SERVER['HTTP_HOST'];
            $script = dirname($_SERVER['SCRIPT_NAME']);
            
            $result = [
                'success' => true,
                'method' => 'Minimal PDF (No dependencies)',
                'filename' => basename($filename),
                'download_url' => $protocol . '://' . $host . $script . '/' . $filename
            ];
        }
        
        echo json_encode($result);
        
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }
} else {
    echo json_encode([
        'success' => false,
        'error' => 'Only POST method is allowed'
    ]);
}
?>