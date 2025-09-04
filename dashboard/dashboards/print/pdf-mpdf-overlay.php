<?php
/**
 * mPDF - כתיבה על PDF קיים עם SetDocTemplate
 * עובד עם עברית ו-RTL
 */

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

error_reporting(0); // ביטול הודעות שגיאה שמפריעות ל-JSON

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
    
    // צור תיקיות
    @mkdir('output', 0777, true);
    @mkdir('temp', 0777, true);
    
    try {
        // הורד את הטמפלייט אם יש
        $tempFile = null;
        if (isset($input['filename']) && !empty($input['filename'])) {
            $pdfUrl = $input['filename'];
            
            if (filter_var($pdfUrl, FILTER_VALIDATE_URL)) {
                $tempFile = 'temp/template_' . uniqid() . '.pdf';
                
                $ch = curl_init($pdfUrl);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                curl_setopt($ch, CURLOPT_TIMEOUT, 30);
                $pdfContent = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);
                
                if ($httpCode !== 200 || empty($pdfContent)) {
                    throw new Exception("Failed to download PDF. HTTP Code: $httpCode");
                }
                
                file_put_contents($tempFile, $pdfContent);
            } else {
                $tempFile = $pdfUrl; // קובץ מקומי
            }
        }
        
        // הגדר כיוון לרוחב או לאורך
        $orientation = isset($input['orientation']) && $input['orientation'] === 'L' ? 'L' : 'P';
        $format = 'A4-' . $orientation;
        
        // צור PDF
        $pdf = new \Mpdf\Mpdf([
            'mode' => 'utf-8',
            'format' => $format,
            'orientation' => $orientation,
            'default_font' => 'dejavusans',
            'margin_left' => 0,
            'margin_right' => 0,
            'margin_top' => 0,
            'margin_bottom' => 0
        ]);
        
        // הגדר RTL אם נדרש
        $isHebrew = isset($input['language']) && $input['language'] === 'he';
        if ($isHebrew) {
            $pdf->SetDirectionality('rtl');
        }
        
        // הגדר טמפלייט כרקע אם יש
        if ($tempFile && file_exists($tempFile)) {
            $pdf->SetDocTemplate($tempFile, true);
        }
        
        // הוסף עמוד
        $pdf->AddPage($orientation);
        
        // בנה HTML עם כל הטקסטים
        $html = $isHebrew ? '<div dir="rtl">' : '<div>';
        
        foreach ($input['values'] as $value) {
            $x = $value['x'] ?? 100;
            $y = $value['y'] ?? 100;
            $text = htmlspecialchars($value['text'] ?? '', ENT_QUOTES, 'UTF-8');
            $fontSize = $value['fontSize'] ?? 12;
            
            // צבע
            $color = 'black';
            if (isset($value['color']) && is_array($value['color'])) {
                $color = sprintf('rgb(%d,%d,%d)', 
                    $value['color'][0], 
                    $value['color'][1], 
                    $value['color'][2]
                );
            }
            
            // הוסף div עם position absolute
            $html .= sprintf(
                '<div style="position:absolute; left:%dpx; top:%dpx; color:%s; font-size:%dpt;">%s</div>',
                $x, $y, $color, $fontSize, $text
            );
        }
        
        $html .= '</div>';
        
        // כתוב HTML
        $pdf->WriteHTML($html);
        
        // שמור
        $filename = 'output/pdf_' . date('Ymd_His') . '_' . rand(1000, 9999) . '.pdf';
        $pdf->Output($filename, 'F');
        
        // נקה קובץ זמני
        if ($tempFile && strpos($tempFile, 'temp/') === 0 && file_exists($tempFile)) {
            unlink($tempFile);
        }
        
        $base_url = 'https://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['SCRIPT_NAME']);
        
        echo json_encode([
            'success' => true,
            'method' => 'mPDF with SetDocTemplate',
            'filename' => basename($filename),
            'view_url' => $base_url . '/pdf-mpdf-overlay.php?file=' . basename($filename),
            'download_url' => $base_url . '/pdf-mpdf-overlay.php?file=' . basename($filename) . '&action=download',
            'direct_url' => $base_url . '/' . $filename,
            'features' => [
                'Full Hebrew/RTL support',
                'Template overlay support',
                'Position absolute for exact placement',
                'Color support'
            ]
        ]);
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}