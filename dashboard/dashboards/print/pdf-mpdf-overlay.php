<?php
/**
 * mPDF - כתיבה על PDF קיים עם SetDocTemplate
 * משתמש בשיטה שעובדת: SetDocTemplate + WriteHTML + position:absolute
 */

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit();
}

require_once __DIR__ . '/vendor/autoload.php';

// בדיקת התקנה
if (isset($_GET['test'])) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'mpdf_installed' => class_exists('\Mpdf\Mpdf'),
        'message' => 'mPDF with SetDocTemplate ready!'
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
    } else {
        header('Content-Type: application/pdf');
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
    
    @mkdir('output', 0777, true);
    @mkdir('temp', 0777, true);
    
    try {
        // הורד טמפלייט
        $templateUrl = $input['filename'] ?? "https://login.form.mbe-plus.com/dashboard/dashboards/print/templates/DeepEmpty.pdf";
        $tempFile = 'temp/template_' . uniqid() . '.pdf';
        file_put_contents($tempFile, file_get_contents($templateUrl));
        
        // צור PDF לרוחב
        $pdf = new \Mpdf\Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4-L',  // L = Landscape (לרוחב)
            'orientation' => 'L', // לרוחב
            'default_font' => 'dejavusans',
            'margin_left' => 0,
            'margin_right' => 0,
            'margin_top' => 0,
            'margin_bottom' => 0
        ]);
        
        // הגדר RTL
        $pdf->SetDirectionality('rtl');
        
        // הגדר טמפלייט
        $pdf->SetDocTemplate($tempFile, true);
        
        // הוסף עמוד לרוחב
        $pdf->AddPage('L');
        
        // בנה HTML מהערכים
        $html = '<div dir="rtl">';
        
        foreach ($input['values'] as $value) {
            $x = $value['x'] ?? 100;
            $y = $value['y'] ?? 100;
            $text = htmlspecialchars($value['text'] ?? '', ENT_QUOTES, 'UTF-8');
            $fontSize = $value['fontSize'] ?? 12;
            
            $color = 'black';
            if (isset($value['color']) && is_array($value['color'])) {
                $color = sprintf('rgb(%d,%d,%d)', 
                    $value['color'][0] ?? 0, 
                    $value['color'][1] ?? 0, 
                    $value['color'][2] ?? 0
                );
            }
            
            $html .= sprintf(
                '<div style="position: absolute; left: %dpx; top: %dpx;">
                    <span style="color: %s; font-size: %dpt;">%s</span>
                </div>',
                $x, $y, $color, $fontSize, $text
            );
        }
        
        $html .= '</div>';
        
        // כתוב HTML
        $pdf->WriteHTML($html);
        
        // שמור
        $filename = 'output/pdf_' . date('Ymd_His') . '.pdf';
        $pdf->Output($filename, 'F');
        
        // נקה
        unlink($tempFile);
        
        $base_url = 'https://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['SCRIPT_NAME']);
        
        echo json_encode([
            'success' => true,
            'method' => 'mPDF with SetDocTemplate',
            'filename' => basename($filename),
            'view_url' => $base_url . '/pdf-mpdf-overlay.php?file=' . basename($filename),
            'download_url' => $base_url . '/pdf-mpdf-overlay.php?file=' . basename($filename) . '&action=download',
            'direct_url' => $base_url . '/' . $filename
        ]);
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit();
}
?>