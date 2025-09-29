<?php
/**
 * Process Document API
 * Location: /dashboard/dashboards/printPDF/api/process-document.php
 */

// Include configuration - בדיוק כמו בבתי עלמין
require_once $_SERVER['DOCUMENT_ROOT'] . '/dashboard/dashboards/printPDF/config.php';

// Check user permission
if (!checkPermission('edit', 'pdf_editor')) {
    http_response_code(403);
    die(json_encode([
        'success' => false,
        'message' => 'אין לך הרשאה לבצע פעולה זו'
    ]));
}

// Set headers
header('Content-Type: application/json');
header('Access-Control-Allow-Methods: POST');

// Check request method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    die(json_encode([
        'success' => false,
        'message' => 'Method not allowed'
    ]));
}

// Get and validate input
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    die(json_encode([
        'success' => false,
        'message' => 'Invalid input data'
    ]));
}

// Verify CSRF token
$csrfToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (!verifyCSRFToken($csrfToken)) {
    http_response_code(403);
    die(json_encode([
        'success' => false,
        'message' => 'Invalid CSRF token'
    ]));
}

try {
    // Validate document data
    if (!isset($input['document']) || !isset($input['elements'])) {
        throw new Exception('Missing required fields');
    }

    $document = $input['document'];
    $elements = $input['elements'];

    // Initialize TCPDF
    require_once TCPDF_PATH . 'tcpdf.php';
    
    // Include fonts configuration
    require_once FONTS_PATH . 'fonts-config.php';

    // Create PDF processor
    $processor = new PDFProcessor($document, $elements);
    
    // Process document
    $result = $processor->process();

    // Return result
    echo json_encode([
        'success' => true,
        'message' => 'Document processed successfully',
        'data' => $result
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

/**
 * PDF Processor Class
 */
class PDFProcessor {
    private $document;
    private $elements;
    private $pdf;
    private $tempFile;
    
    public function __construct($document, $elements) {
        $this->document = $document;
        $this->elements = $elements;
    }
    
    public function process() {
        // Determine document type
        $file = $this->getDocumentFile();
        
        if (!$file) {
            throw new Exception('No document file provided');
        }
        
        // Check file type
        $fileInfo = pathinfo($file);
        $extension = strtolower($fileInfo['extension'] ?? '');
        
        if ($extension === 'pdf') {
            return $this->processPDF($file);
        } else if (in_array($extension, ['jpg', 'jpeg', 'png'])) {
            return $this->processImage($file);
        } else {
            throw new Exception('Unsupported file format');
        }
    }
    
    private function getDocumentFile() {
        $file = $this->document['file'] ?? null;
        
        if (!$file) {
            return null;
        }
        
        // Handle base64
        if (isset($file['base64'])) {
            return $this->saveBase64ToFile($file['base64']);
        }
        
        // Handle URL
        if (isset($file['url'])) {
            return $this->downloadFile($file['url']);
        }
        
        // Handle path
        if (isset($file['path'])) {
            return $this->validatePath($file['path']);
        }
        
        return null;
    }
    
    private function processPDF($inputFile) {
        // Initialize TCPDF
        $this->initializePDF();
        
        // Check if we have FPDI for PDF import
        $fpdiPath = TCPDF_PATH . '../fpdi/src/autoload.php';
        if (file_exists($fpdiPath)) {
            require_once $fpdiPath;
            $this->pdf = new \setasign\Fpdi\Tcpdf\Fpdi();
        } else {
            // Fallback - create new PDF and add as image
            $this->pdf = new TCPDF();
            
            // Try to convert PDF to image (if Imagick is available)
            if (extension_loaded('imagick')) {
                $imagick = new \Imagick();
                $imagick->readImage($inputFile . '[0]'); // First page
                $imagick->setImageFormat('png');
                $tempImage = TEMP_PATH . 'pdf_page_' . uniqid() . '.png';
                $imagick->writeImage($tempImage);
                
                // Get dimensions
                $width = $imagick->getImageWidth();
                $height = $imagick->getImageHeight();
                
                // Convert pixels to mm
                $widthMM = $width * 25.4 / 72;
                $heightMM = $height * 25.4 / 72;
                
                // Add page with custom size
                $this->pdf->AddPage('P', array($widthMM, $heightMM));
                
                // Add image
                $this->pdf->Image($tempImage, 0, 0, $widthMM, $heightMM);
                
                // Clean up
                @unlink($tempImage);
            } else {
                // If no Imagick, just create blank page
                $this->pdf->AddPage();
                
                // Add note
                $this->pdf->SetFont('helvetica', '', 12);
                $this->pdf->Cell(0, 10, 'PDF import requires FPDI or ImageMagick', 0, 1, 'C');
            }
            
            // Add elements to this page
            $this->addElementsToPDF(1);
            
            // Output PDF
            return $this->outputPDF();
        }
        
        // If FPDI is available, use it
        if ($this->pdf instanceof \setasign\Fpdi\Tcpdf\Fpdi) {
            // Get page count
            $pageCount = $this->pdf->setSourceFile($inputFile);
            
            // Process each page
            for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {
                // Import page
                $templateId = $this->pdf->importPage($pageNo);
                $size = $this->pdf->getTemplateSize($templateId);
                
                // Add page
                $this->pdf->AddPage($size['orientation'], [$size['width'], $size['height']]);
                
                // Use template
                $this->pdf->useTemplate($templateId);
                
                // Add elements to this page
                $this->addElementsToPDF($pageNo);
            }
        }
        
        // Output PDF
        return $this->outputPDF();
    }
    
    private function processImage($inputFile) {
        // Get image info
        $imageInfo = getimagesize($inputFile);
        if (!$imageInfo) {
            throw new Exception('Invalid image file');
        }
        
        $width = $imageInfo[0];
        $height = $imageInfo[1];
        
        // Convert pixels to mm (assuming 72 DPI)
        $widthMM = $width * 25.4 / 72;
        $heightMM = $height * 25.4 / 72;
        
        // Initialize TCPDF
        $this->initializePDF($widthMM, $heightMM);
        
        // Add page
        $this->pdf->AddPage();
        
        // Add image as background
        $this->pdf->Image($inputFile, 0, 0, $widthMM, $heightMM);
        
        // Add elements
        $this->addElementsToPDF(1);
        
        // Output PDF
        return $this->outputPDF();
    }
    
    private function initializePDF($width = 210, $height = 297) {
        // Create new PDF document
        $this->pdf = new TCPDF('P', 'mm', [$width, $height], true, 'UTF-8', false);
        
        // Set document information
        $this->pdf->SetCreator('PDF Editor');
        $this->pdf->SetAuthor('User');
        $this->pdf->SetTitle('Processed Document');
        
        // Remove default header/footer
        $this->pdf->setPrintHeader(false);
        $this->pdf->setPrintFooter(false);
        
        // Set margins
        $this->pdf->SetMargins(0, 0, 0);
        $this->pdf->SetAutoPageBreak(false);
        
        // Set font subsetting
        $this->pdf->setFontSubsetting(true);
        
        // Set default font
        $this->pdf->SetFont('dejavusans', '', 12);
    }
    
    private function addElementsToPDF($pageNumber) {
        foreach ($this->elements as $element) {
            // Check if element is for this page
            $elementPage = $element['page'] ?? 1;
            if ($elementPage !== $pageNumber) {
                continue;
            }
            
            $type = $element['type'] ?? '';
            
            switch ($type) {
                case 'text':
                    $this->addTextElement($element);
                    break;
                case 'image':
                    $this->addImageElement($element);
                    break;
                case 'shape':
                    $this->addShapeElement($element);
                    break;
            }
        }
    }
    
    private function addTextElement($element) {
        $text = $element['value'] ?? '';
        $position = $element['position'] ?? [];
        $style = $element['style'] ?? [];
        $layout = $element['layout'] ?? [];
        
        // Get position
        $x = $this->convertPosition($position['from_left'] ?? 0, $position['unit'] ?? 'mm');
        $y = $this->convertPosition($position['from_top'] ?? 0, $position['unit'] ?? 'mm');
        
        // Set font
        $fontFamily = $style['font_family'] ?? 'helvetica';
        $fontSize = $style['font_size_pt'] ?? 12;
        $fontStyle = '';
        
        if ($style['bold'] ?? false) $fontStyle .= 'B';
        if ($style['italic'] ?? false) $fontStyle .= 'I';
        if ($style['underline'] ?? false) $fontStyle .= 'U';
        
        // Map font to TCPDF font
        $tcpdfFont = $this->mapFontToTCPDF($fontFamily);
        $this->pdf->SetFont($tcpdfFont, $fontStyle, $fontSize);
        
        // Set color
        $color = $style['color'] ?? '#000000';
        $rgb = $this->hexToRGB($color);
        $this->pdf->SetTextColor($rgb['r'], $rgb['g'], $rgb['b']);
        
        // Set position
        $this->pdf->SetXY($x, $y);
        
        // Handle RTL text
        $language = $element['language'] ?? 'en';
        $isRTL = in_array($language, ['he', 'ar']);
        
        if ($isRTL) {
            $this->pdf->setRTL(true);
        }
        
        // Calculate width
        $maxWidth = $layout['max_width'] ?? 0;
        if ($maxWidth > 0) {
            $width = $this->convertPosition($maxWidth, $position['unit'] ?? 'mm');
        } else {
            $width = $this->pdf->getPageWidth() - $x;
        }
        
        // Set alignment
        $align = $this->mapAlignment($layout['align'] ?? 'start', $isRTL);
        
        // Add text
        $this->pdf->MultiCell($width, 0, $text, 0, $align, false, 1);
        
        if ($isRTL) {
            $this->pdf->setRTL(false);
        }
    }
    
    private function addImageElement($element) {
        $imageData = $element['value'] ?? '';
        $position = $element['position'] ?? [];
        $style = $element['style'] ?? [];
        
        // Get position
        $x = $this->convertPosition($position['from_left'] ?? 0, $position['unit'] ?? 'mm');
        $y = $this->convertPosition($position['from_top'] ?? 0, $position['unit'] ?? 'mm');
        
        // Get size
        $width = $this->convertPosition($element['width'] ?? 50, $position['unit'] ?? 'mm');
        $height = $this->convertPosition($element['height'] ?? 50, $position['unit'] ?? 'mm');
        
        // Handle image data
        if (strpos($imageData, 'data:') === 0) {
            // Base64 image
            $this->pdf->Image('@' . base64_decode(explode(',', $imageData)[1]), $x, $y, $width, $height);
        } else if (filter_var($imageData, FILTER_VALIDATE_URL)) {
            // URL image
            $this->pdf->Image($imageData, $x, $y, $width, $height);
        } else if (file_exists($imageData)) {
            // File path
            $this->pdf->Image($imageData, $x, $y, $width, $height);
        }
        
        // Apply rotation if needed
        if (isset($style['rotation']) && $style['rotation'] != 0) {
            // TCPDF rotation would be applied here
        }
    }
    
    private function addShapeElement($element) {
        $shapeType = $element['shape'] ?? 'rectangle';
        $position = $element['position'] ?? [];
        $style = $element['style'] ?? [];
        
        // Get position
        $x = $this->convertPosition($position['from_left'] ?? 0, $position['unit'] ?? 'mm');
        $y = $this->convertPosition($position['from_top'] ?? 0, $position['unit'] ?? 'mm');
        
        // Get size
        $width = $this->convertPosition($element['width'] ?? 50, $position['unit'] ?? 'mm');
        $height = $this->convertPosition($element['height'] ?? 50, $position['unit'] ?? 'mm');
        
        // Set colors
        $fillColor = $style['fill_color'] ?? null;
        $borderColor = $style['border_color'] ?? '#000000';
        $borderWidth = $style['border_width'] ?? 1;
        
        // Set border
        $this->pdf->SetLineWidth($borderWidth * 0.264583); // Convert pt to mm
        $rgb = $this->hexToRGB($borderColor);
        $this->pdf->SetDrawColor($rgb['r'], $rgb['g'], $rgb['b']);
        
        // Set fill if exists
        $fillStyle = 'D'; // Draw only
        if ($fillColor) {
            $rgb = $this->hexToRGB($fillColor);
            $this->pdf->SetFillColor($rgb['r'], $rgb['g'], $rgb['b']);
            $fillStyle = 'DF'; // Draw and fill
        }
        
        // Draw shape
        switch ($shapeType) {
            case 'rectangle':
                $this->pdf->Rect($x, $y, $width, $height, $fillStyle);
                break;
            case 'circle':
                $radius = min($width, $height) / 2;
                $this->pdf->Circle($x + $radius, $y + $radius, $radius, 0, 360, $fillStyle);
                break;
            case 'line':
                $this->pdf->Line($x, $y, $x + $width, $y + $height);
                break;
        }
    }
    
    private function convertPosition($value, $unit) {
        switch ($unit) {
            case 'px':
                return $value * 25.4 / 72; // Convert pixels to mm (assuming 72 DPI)
            case 'pt':
                return $value * 0.352778; // Convert points to mm
            case 'inch':
                return $value * 25.4; // Convert inches to mm
            case 'cm':
                return $value * 10; // Convert cm to mm
            case 'mm':
            default:
                return $value;
        }
    }
    
    private function mapFontToTCPDF($fontFamily) {
        $fontMap = [
            'Arial' => 'helvetica',
            'Helvetica' => 'helvetica',
            'Times' => 'times',
            'Times New Roman' => 'times',
            'Courier' => 'courier',
            'Courier New' => 'courier',
            'Rubik' => 'dejavusans',
            'Heebo' => 'dejavusans',
            'Assistant' => 'dejavusans'
        ];
        
        return $fontMap[$fontFamily] ?? 'dejavusans';
    }
    
    private function mapAlignment($align, $isRTL) {
        $alignMap = [
            'start' => $isRTL ? 'R' : 'L',
            'end' => $isRTL ? 'L' : 'R',
            'left' => 'L',
            'right' => 'R',
            'center' => 'C',
            'justify' => 'J'
        ];
        
        return $alignMap[$align] ?? 'L';
    }
    
    private function hexToRGB($hex) {
        $hex = str_replace('#', '', $hex);
        
        if (strlen($hex) == 3) {
            $r = hexdec(substr($hex, 0, 1) . substr($hex, 0, 1));
            $g = hexdec(substr($hex, 1, 1) . substr($hex, 1, 1));
            $b = hexdec(substr($hex, 2, 1) . substr($hex, 2, 1));
        } else {
            $r = hexdec(substr($hex, 0, 2));
            $g = hexdec(substr($hex, 2, 2));
            $b = hexdec(substr($hex, 4, 2));
        }
        
        return ['r' => $r, 'g' => $g, 'b' => $b];
    }
    
    private function outputPDF() {
        // Generate unique filename
        $filename = 'processed_' . time() . '_' . uniqid() . '.pdf';
        $filepath = TEMP_PATH . $filename;
        
        // Save PDF
        $this->pdf->Output($filepath, 'F');
        
        // Get base64
        $base64 = base64_encode(file_get_contents($filepath));
        
        // Generate download URL
        $downloadUrl = PDF_EDITOR_URL . 'api/download.php?file=' . $filename;
        
        return [
            'filename' => $filename,
            'filepath' => $filepath,
            'base64' => 'data:application/pdf;base64,' . $base64,
            'download_url' => $downloadUrl,
            'size' => filesize($filepath)
        ];
    }
    
    private function saveBase64ToFile($base64) {
        $data = explode(',', $base64);
        $fileData = base64_decode($data[1] ?? $data[0]);
        
        $tempFile = TEMP_PATH . 'upload_' . uniqid();
        file_put_contents($tempFile, $fileData);
        
        return $tempFile;
    }
    
    private function downloadFile($url) {
        $tempFile = TEMP_PATH . 'download_' . uniqid();
        $fileData = file_get_contents($url);
        
        if ($fileData === false) {
            throw new Exception('Failed to download file');
        }
        
        file_put_contents($tempFile, $fileData);
        return $tempFile;
    }
    
    private function validatePath($path) {
        if (!file_exists($path)) {
            throw new Exception('File not found');
        }
        
        return $path;
    }
}