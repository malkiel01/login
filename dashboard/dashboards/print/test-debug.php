<?php
/**
 * Debug Test File
 * קובץ בדיקה למציאת בעיות
 */

// הצג את כל השגיאות
error_reporting(E_ALL);
ini_set('display_errors', 1);

// בדיקה 1: האם PHP עובד?
echo "✅ PHP is working!\n";
echo "PHP Version: " . PHP_VERSION . "\n\n";

// בדיקה 2: האם יש הרשאות כתיבה?
$testDir = 'output';
if (!file_exists($testDir)) {
    if (mkdir($testDir, 0777, true)) {
        echo "✅ Created output directory\n";
    } else {
        echo "❌ Cannot create output directory\n";
    }
} else {
    echo "✅ Output directory exists\n";
}

if (is_writable($testDir)) {
    echo "✅ Output directory is writable\n\n";
} else {
    echo "❌ Output directory is NOT writable\n\n";
}

// בדיקה 3: האם אפשר לקבל JSON?
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    echo "✅ POST request received\n";
    
    $input = file_get_contents('php://input');
    echo "Raw input: " . $input . "\n";
    
    $data = json_decode($input, true);
    if (json_last_error() === JSON_ERROR_NONE) {
        echo "✅ JSON decoded successfully\n";
        echo "Data: " . print_r($data, true) . "\n";
    } else {
        echo "❌ JSON decode error: " . json_last_error_msg() . "\n";
    }
} else {
    echo "Method: " . $_SERVER['REQUEST_METHOD'] . "\n";
}

// בדיקה 4: האם יש FPDF?
if (file_exists('fpdf/fpdf.php')) {
    echo "✅ FPDF found\n";
} else {
    echo "ℹ️ FPDF not found (optional)\n";
}

// בדיקה 5: יצירת PDF בסיסי
try {
    $testContent = "%PDF-1.4\ntest";
    $testFile = $testDir . '/test_' . time() . '.pdf';
    
    if (file_put_contents($testFile, $testContent)) {
        echo "✅ Can create files\n";
        echo "Test file: " . $testFile . "\n";
        unlink($testFile); // מחק את הקובץ
    } else {
        echo "❌ Cannot create files\n";
    }
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}

// בדיקה 6: הצג את כל ההגדרות
echo "\n--- PHP Info ---\n";
echo "Memory Limit: " . ini_get('memory_limit') . "\n";
echo "Max Execution Time: " . ini_get('max_execution_time') . "\n";
echo "Post Max Size: " . ini_get('post_max_size') . "\n";
echo "Upload Max Filesize: " . ini_get('upload_max_filesize') . "\n";
echo "Display Errors: " . ini_get('display_errors') . "\n";

// בדיקה 7: נסה להריץ את הקוד הבסיסי
echo "\n--- Testing Minimal PDF Creation ---\n";

class TestMinimalPDF {
    public function create() {
        $pdf = "%PDF-1.4\n";
        $pdf .= "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n";
        $pdf .= "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n";
        $pdf .= "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] >>\nendobj\n";
        $pdf .= "xref\n0 4\n0000000000 65535 f\n";
        $pdf .= "trailer\n<< /Size 4 /Root 1 0 R >>\n";
        $pdf .= "startxref\n0\n%%EOF";
        
        return $pdf;
    }
}

try {
    $testPdf = new TestMinimalPDF();
    $content = $testPdf->create();
    echo "✅ PDF generation works! (Length: " . strlen($content) . " bytes)\n";
} catch (Exception $e) {
    echo "❌ PDF generation error: " . $e->getMessage() . "\n";
}

echo "\n--- End of Test ---\n";
?>