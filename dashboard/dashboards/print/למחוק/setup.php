<?php
/**
 * Setup Script - מתקין אוטומטי
 * הרץ את הקובץ הזה פעם אחת להתקנה
 */

echo "PDF Text Printer - Setup Script\n";
echo "================================\n\n";

// Create output directory
echo "1. Creating output directory... ";
if (!file_exists('output')) {
    mkdir('output', 0777, true);
    echo "✓ Created\n";
} else {
    echo "✓ Already exists\n";
}

// Check if FPDF is needed
echo "2. Checking for FPDF... ";
if (!file_exists('fpdf/fpdf.php')) {
    echo "Not found\n";
    echo "   Would you like to download FPDF for better PDF support? (y/n): ";
    
    // For web interface
    if (php_sapi_name() !== 'cli') {
        echo "\n";
        echo '<br><br>';
        echo '<h3>Manual FPDF Installation (Optional but Recommended):</h3>';
        echo '<ol>';
        echo '<li>Download FPDF from <a href="http://www.fpdf.org/en/download.php" target="_blank">http://www.fpdf.org/</a></li>';
        echo '<li>Extract the ZIP file</li>';
        echo '<li>Upload the "fpdf" folder to the same directory as this script</li>';
        echo '<li>Refresh this page to verify installation</li>';
        echo '</ol>';
        
        // Try to download automatically
        echo '<h3>Attempting automatic download...</h3>';
        $fpdf_url = 'http://www.fpdf.org/downloads/fpdf185.zip';
        $zip_file = 'fpdf.zip';
        
        if (function_exists('file_get_contents') && ini_get('allow_url_fopen')) {
            $content = @file_get_contents($fpdf_url);
            if ($content) {
                file_put_contents($zip_file, $content);
                echo "✓ Downloaded FPDF<br>";
                
                if (class_exists('ZipArchive')) {
                    $zip = new ZipArchive;
                    if ($zip->open($zip_file) === TRUE) {
                        $zip->extractTo('.');
                        $zip->close();
                        echo "✓ Extracted FPDF<br>";
                        unlink($zip_file);
                        
                        // Rename folder if needed
                        if (file_exists('fpdf185') && !file_exists('fpdf')) {
                            rename('fpdf185', 'fpdf');
                        }
                    }
                } else {
                    echo "⚠ ZipArchive not available. Please extract manually.<br>";
                }
            } else {
                echo "⚠ Could not download FPDF automatically. Please install manually.<br>";
            }
        } else {
            echo "⚠ URL file access is disabled. Please install FPDF manually.<br>";
        }
    }
} else {
    echo "✓ Found\n";
}

// Test minimal PDF creation
echo "\n3. Testing Minimal PDF creation... ";
require_once('process-pdf-simple.php');

$testPdf = new MinimalPDF();
$testPdf->addPage();
$testPdf->addText(100, 100, "Test PDF Creation");
$testFile = 'output/test_' . time() . '.pdf';

if ($testPdf->save($testFile)) {
    echo "✓ Success\n";
    echo "   Test file created: $testFile\n";
} else {
    echo "✗ Failed\n";
}

// Check PHP extensions
echo "\n4. Checking PHP Extensions:\n";
$extensions = [
    'json' => 'Required for JSON processing',
    'mbstring' => 'Recommended for UTF-8 support',
    'iconv' => 'Recommended for character encoding',
    'gd' => 'Optional for image support',
    'zip' => 'Optional for automatic FPDF installation'
];

foreach ($extensions as $ext => $description) {
    $installed = extension_loaded($ext);
    $status = $installed ? '✓' : '✗';
    $color = $installed ? 'green' : 'red';
    
    if (php_sapi_name() !== 'cli') {
        echo "   <span style='color: $color'>$status</span> $ext - $description<br>";
    } else {
        echo "   $status $ext - $description\n";
    }
}

// Check write permissions
echo "\n5. Checking Write Permissions:\n";
$dirs = [
    '.' => 'Current directory',
    'output' => 'Output directory'
];

foreach ($dirs as $dir => $description) {
    $writable = is_writable($dir);
    $status = $writable ? '✓' : '✗';
    
    if (php_sapi_name() !== 'cli') {
        $color = $writable ? 'green' : 'red';
        echo "   <span style='color: $color'>$status</span> $description<br>";
    } else {
        echo "   $status $description\n";
    }
}

// System commands check
echo "\n6. Checking System PDF Tools:\n";
$tools = [
    'wkhtmltopdf' => 'High quality HTML to PDF converter',
    'chromium-browser' => 'Chromium headless PDF generation',
    'google-chrome' => 'Chrome headless PDF generation'
];

foreach ($tools as $tool => $description) {
    exec("which $tool 2>/dev/null", $output, $return);
    $installed = $return === 0;
    $status = $installed ? '✓' : '✗';
    
    if (php_sapi_name() !== 'cli') {
        $color = $installed ? 'green' : 'gray';
        echo "   <span style='color: $color'>$status</span> $tool - $description<br>";
    } else {
        echo "   $status $tool - $description\n";
    }
}

// Summary
echo "\n\n";
echo "========================================\n";
echo "SETUP COMPLETE / ההתקנה הושלמה\n";
echo "========================================\n\n";

if (php_sapi_name() !== 'cli') {
    echo '<div style="background: #e8f5e9; padding: 15px; border-radius: 5px; margin-top: 20px;">';
    echo '<h2>✓ Your system is ready to use!</h2>';
    echo '<p>The PDF Text Printer is configured and ready.</p>';
    echo '<p><strong>Next steps:</strong></p>';
    echo '<ol>';
    echo '<li>Open the <a href="index.html">main interface</a></li>';
    echo '<li>Or use the API directly with process-pdf-simple.php</li>';
    echo '</ol>';
    echo '</div>';
    
    // Create a simple test button
    echo '<div style="margin-top: 20px;">';
    echo '<h3>Quick Test:</h3>';
    echo '<button onclick="testAPI()" style="padding: 10px 20px; background: #4CAF50; color: white; border: none; border-radius: 5px; cursor: pointer;">Test PDF Creation</button>';
    echo '<div id="testResult"></div>';
    echo '</div>';
    
    echo '<script>
    async function testAPI() {
        const data = {
            filename: "",
            language: "en",
            values: [
                { text: "Hello World", x: 100, y: 100 },
                { text: "Test PDF Creation", x: 100, y: 120 },
                { text: "Generated: " + new Date().toLocaleString(), x: 100, y: 140 }
            ]
        };
        
        try {
            const response = await fetch("process-pdf-simple.php", {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify(data)
            });
            
            const result = await response.json();
            
            if (result.success) {
                document.getElementById("testResult").innerHTML = 
                    `<p style="color: green;">✓ PDF created successfully!</p>
                     <p>Method used: ${result.method}</p>
                     <p><a href="${result.download_url}" target="_blank">Download PDF</a></p>`;
            } else {
                document.getElementById("testResult").innerHTML = 
                    `<p style="color: red;">✗ Error: ${result.error}</p>`;
            }
        } catch (error) {
            document.getElementById("testResult").innerHTML = 
                `<p style="color: red;">✗ Network error: ${error.message}</p>`;
        }
    }
    </script>';
} else {
    echo "Your system is ready to create PDFs!\n";
    echo "You can now use process-pdf-simple.php\n";
}

// Create info file
$info = [
    'setup_date' => date('Y-m-d H:i:s'),
    'php_version' => PHP_VERSION,
    'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
    'fpdf_installed' => file_exists('fpdf/fpdf.php'),
    'output_dir_writable' => is_writable('output')
];

file_put_contents('setup_info.json', json_encode($info, JSON_PRETTY_PRINT));
?>