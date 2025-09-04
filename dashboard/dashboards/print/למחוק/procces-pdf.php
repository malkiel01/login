<?php
/**
 * PDF Text Printer - Server Side
 * Supports Hebrew (RTL) and English (LTR) text printing on PDF files
 */

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// CORS headers - adjust according to your needs
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
    echo json_encode(['success' => true, 'message' => 'Server is running']);
    exit();
}

// Include TCPDF library - make sure to install it via Composer or download it
// composer require tecnickcom/tcpdf
require_once('vendor/autoload.php'); // Adjust path as needed

// Or if you downloaded TCPDF manually:
// require_once('tcpdf/tcpdf.php');

use TCPDF;
use FPDI;

// If using FPDI for importing existing PDFs
// composer require setasign/fpdi
// require_once('vendor/setasign/fpdi/src/autoload.php');

class PDFTextPrinter {
    private $pdf;
    private $filename;
    private $language;
    private $values;
    private $outputDir = 'output/'; // Directory to save processed PDFs
    
    public function __construct($data) {
        $this->validateInput($data);
        $this->filename = $data['filename'];
        $this->language = $data['language'] ?? 'en';
        $this->values = $data['values'];
        
        // Create output directory if it doesn't exist
        if (!file_exists($this->outputDir)) {
            mkdir($this->outputDir, 0777, true);
        }
    }
    
    private function validateInput($data) {
        if (!isset($data['filename']) || empty($data['filename'])) {
            throw new Exception('Filename is required');
        }
        
        if (!isset($data['values']) || !is_array($data['values'])) {
            throw new Exception('Values array is required');
        }
        
        foreach ($data['values'] as $value) {
            if (!isset($value['text']) || !isset($value['x']) || !isset($value['y'])) {
                throw new Exception('Each value must have text, x, and y properties');
            }
        }
    }
    
    public function process() {
        try {
            // Initialize FPDI to work with existing PDFs
            $this->pdf = new \setasign\Fpdi\Tcpdf\Fpdi();
            
            // Set document information
            $this->pdf->SetCreator('PDF Text Printer');
            $this->pdf->SetAuthor('Your System');
            $this->pdf->SetTitle('Modified PDF');
            
            // Remove default header/footer
            $this->pdf->setPrintHeader(false);
            $this->pdf->setPrintFooter(false);
            
            // Set margins
            $this->pdf->SetMargins(0, 0, 0);
            $this->pdf->SetAutoPageBreak(false, 0);
            
            // Download the PDF file if it's a URL
            $localFile = $this->downloadPDF($this->filename);
            
            // Get the number of pages
            $pageCount = $this->pdf->setSourceFile($localFile);
            
            // Process each page
            for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {
                // Import page
                $templateId = $this->pdf->importPage($pageNo);
                $size = $this->pdf->getTemplateSize($templateId);
                
                // Add a page
                $this->pdf->AddPage($size['orientation'], [$size['width'], $size['height']]);
                
                // Use the imported page
                $this->pdf->useTemplate($templateId, 0, 0, $size['width'], $size['height']);
                
                // Add text values to the first page only (modify as needed)
                if ($pageNo == 1) {
                    $this->addTextValues();
                }
            }
            
            // Generate output filename
            $outputFilename = $this->generateOutputFilename();
            
            // Save the PDF
            $this->pdf->Output($outputFilename, 'F');
            
            // Clean up temporary file if it was downloaded
            if ($localFile !== $this->filename && file_exists($localFile)) {
                unlink($localFile);
            }
            
            return [
                'success' => true,
                'message' => 'PDF processed successfully',
                'download_url' => $this->getDownloadUrl($outputFilename),
                'filename' => basename($outputFilename)
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    private function downloadPDF($url) {
        // Check if it's a URL or local file
        if (filter_var($url, FILTER_VALIDATE_URL)) {
            $tempFile = tempnam(sys_get_temp_dir(), 'pdf_') . '.pdf';
            $content = file_get_contents($url);
            
            if ($content === false) {
                throw new Exception('Failed to download PDF from URL');
            }
            
            file_put_contents($tempFile, $content);
            return $tempFile;
        }
        
        // It's a local file
        if (!file_exists($url)) {
            throw new Exception('PDF file not found');
        }
        
        return $url;
    }
    
    private function addTextValues() {
        // Set font based on language
        if ($this->language === 'he') {
            // For Hebrew support, use a font that supports Hebrew characters
            // You need to add Hebrew font to TCPDF fonts directory
            // Example: $this->pdf->AddFont('dejavusans', '', 'dejavusans.php');
            $this->pdf->SetFont('dejavusans', '', 12);
        } else {
            $this->pdf->SetFont('helvetica', '', 12);
        }
        
        // Set text color (black)
        $this->pdf->SetTextColor(0, 0, 0);
        
        // Process each value
        foreach ($this->values as $value) {
            $text = $value['text'];
            $x = $value['x'];
            $y = $value['y'];
            
            // Additional optional parameters
            $fontSize = isset($value['fontSize']) ? $value['fontSize'] : 12;
            $fontStyle = isset($value['fontStyle']) ? $value['fontStyle'] : '';
            $color = isset($value['color']) ? $value['color'] : [0, 0, 0];
            
            // Set font size
            $this->pdf->SetFontSize($fontSize);
            
            // Set text color if provided
            if (is_array($color) && count($color) === 3) {
                $this->pdf->SetTextColor($color[0], $color[1], $color[2]);
            }
            
            // Handle RTL for Hebrew
            if ($this->language === 'he') {
                // For RTL languages, adjust X coordinate
                // Get string width to position correctly from right
                $stringWidth = $this->pdf->GetStringWidth($text);
                $pageWidth = $this->pdf->getPageWidth();
                
                // If x is meant to be from the right edge
                $adjustedX = $pageWidth - $x - $stringWidth;
                
                // Enable RTL
                $this->pdf->setRTL(true);
                
                // Write the text
                $this->pdf->Text($adjustedX, $y, $text);
                
                // Disable RTL for next item
                $this->pdf->setRTL(false);
            } else {
                // For LTR languages (English)
                $this->pdf->Text($x, $y, $text);
            }
        }
    }
    
    private function generateOutputFilename() {
        $timestamp = date('Y-m-d_H-i-s');
        $randomStr = bin2hex(random_bytes(4));
        return $this->outputDir . 'pdf_output_' . $timestamp . '_' . $randomStr . '.pdf';
    }
    
    private function getDownloadUrl($filename) {
        // Adjust this based on your server configuration
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'];
        $script = dirname($_SERVER['SCRIPT_NAME']);
        
        return $protocol . '://' . $host . $script . '/' . $filename;
    }
}

// Alternative implementation using TCPDF only (without FPDI)
class SimplePDFTextPrinter {
    private $pdf;
    private $language;
    private $values;
    private $outputDir = 'output/';
    
    public function __construct($data) {
        $this->language = $data['language'] ?? 'en';
        $this->values = $data['values'];
        
        if (!file_exists($this->outputDir)) {
            mkdir($this->outputDir, 0777, true);
        }
    }
    
    public function createNewPDF() {
        try {
            // Create new PDF document
            $this->pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
            
            // Set document information
            $this->pdf->SetCreator('PDF Text Printer');
            $this->pdf->SetAuthor('Your System');
            $this->pdf->SetTitle('Generated PDF');
            
            // Remove default header/footer
            $this->pdf->setPrintHeader(false);
            $this->pdf->setPrintFooter(false);
            
            // Set margins
            $this->pdf->SetMargins(15, 15, 15);
            
            // Add a page
            $this->pdf->AddPage();
            
            // Set font for Hebrew or English
            if ($this->language === 'he') {
                // For Hebrew, use FreeSans or DejaVu Sans (includes Hebrew characters)
                $this->pdf->SetFont('freesans', '', 12);
            } else {
                $this->pdf->SetFont('helvetica', '', 12);
            }
            
            // Add text values
            foreach ($this->values as $value) {
                $text = $value['text'];
                $x = $value['x'];
                $y = $value['y'];
                
                if ($this->language === 'he') {
                    // Enable RTL for Hebrew
                    $this->pdf->setRTL(true);
                    
                    // Adjust positioning for RTL
                    $pageWidth = $this->pdf->getPageWidth();
                    $adjustedX = $pageWidth - $x;
                    
                    $this->pdf->Text($adjustedX, $y, $text);
                    $this->pdf->setRTL(false);
                } else {
                    $this->pdf->Text($x, $y, $text);
                }
            }
            
            // Generate output filename
            $outputFilename = $this->generateOutputFilename();
            
            // Save PDF
            $this->pdf->Output($outputFilename, 'F');
            
            return [
                'success' => true,
                'message' => 'PDF created successfully',
                'download_url' => $this->getDownloadUrl($outputFilename),
                'filename' => basename($outputFilename)
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    private function generateOutputFilename() {
        $timestamp = date('Y-m-d_H-i-s');
        $randomStr = bin2hex(random_bytes(4));
        return $this->outputDir . 'pdf_new_' . $timestamp . '_' . $randomStr . '.pdf';
    }
    
    private function getDownloadUrl($filename) {
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'];
        $script = dirname($_SERVER['SCRIPT_NAME']);
        
        return $protocol . '://' . $host . $script . '/' . $filename;
    }
}

// Main execution
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        echo json_encode([
            'success' => false,
            'error' => 'Invalid JSON input'
        ]);
        exit();
    }
    
    try {
        // Check if we have an existing PDF file or need to create new one
        if (isset($input['filename']) && !empty($input['filename'])) {
            // Process existing PDF (requires FPDI)
            $processor = new PDFTextPrinter($input);
            $result = $processor->process();
        } else {
            // Create new PDF
            $processor = new SimplePDFTextPrinter($input);
            $result = $processor->createNewPDF();
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