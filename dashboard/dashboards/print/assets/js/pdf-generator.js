/**
 * PDF Generation Functions
 */

// ========== Main Process Functions ==========
async function processValues() {
    if (values.length === 0) {
        showStatus('נא להוסיף לפחות ערך אחד', 'error');
        return;
    }

    const pdfUrl = document.getElementById('pdfUrl').value;
    
    const data = {
        language: document.getElementById('language').value,
        orientation: selectedOrientation,
        values: values
    };
    
    // Add URL only if provided
    if (pdfUrl && pdfUrl.trim() !== '') {
        data.filename = pdfUrl;
        debugLog(`Using existing PDF: ${pdfUrl}`, 'info');
    } else {
        debugLog('Creating new PDF', 'info');
    }

    debugLog(`Sending to ${selectedMethod}: ${JSON.stringify(data, null, 2)}`, 'info');
    await sendToServer(data, selectedMethod);
}

async function processJson() {
    const jsonInput = document.getElementById('jsonInput').value;

    try {
        const data = JSON.parse(jsonInput);
        debugLog(`Parsed JSON: ${JSON.stringify(data, null, 2)}`, 'success');
        
        if (!data.values || !Array.isArray(data.values)) {
            throw new Error('Invalid JSON structure - missing values array');
        }

        const method = data.method || selectedMethod;
        await sendToServer(data, method);
    } catch (e) {
        showStatus('שגיאה בפענוח JSON', 'error');
        debugLog(`JSON parse error: ${e.message}`, 'error');
    }
}

async function sendToServer(data, method) {
    const METHOD_FILES = {
        'minimal': 'api/pdf-minimal.php',
        'fpdf': 'api/pdf-fpdf.php',
        'html': 'api/pdf-html.php',
        'postscript': 'api/pdf-postscript.php',
        'mpdf': 'api/pdf-mpdf-overlay.php',
        'tcpdf': 'api/pdf-tcpdf-overlay.php',
    };
    
    const apiUrl = METHOD_FILES[method];
    
    if (!apiUrl) {
        showStatus('שיטה לא חוקית', 'error');
        return;
    }
    
    debugLog(`Sending request to ${apiUrl}...`, 'info');
    
    try {
        const response = await fetch(apiUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(data)
        });

        const result = await response.json();
        
        if (result.success) {
            showStatus('הקובץ נוצר בהצלחה!', 'success');
            debugLog(`Success: ${result.message || 'File created successfully'}`, 'success');
            debugLog(`Method used: ${result.method}`, 'info');
            debugLog(`Orientation: ${result.orientation || 'not specified'}`, 'info');
            
            // Debug positions if available
            if (result.debug_positions) {
                debugLog(`Positions: ${JSON.stringify(result.debug_positions)}`, 'info');
            }
            
            // Open file in new window
            if (result.view_url || result.direct_url) {
                const url = result.view_url || result.direct_url;
                debugLog(`Opening URL: ${url}`, 'success');
                window.open(url, '_blank');
            }
            
            // Show download option
            if (result.download_url) {
                debugLog(`Download available: ${result.download_url}`, 'success');
            }
        } else {
            throw new Error(result.error || 'Unknown error');
        }
    } catch (error) {
        showStatus('שגיאה ביצירת הקובץ', 'error');
        debugLog(`Server error: ${error.message}`, 'error');
    }
}

// ========== Test Method ==========
async function testMethod() {
    const testData = {
        orientation: selectedOrientation,
        values: [
            { text: `Test ${selectedMethod.toUpperCase()}`, x: 100, y: 100, fontSize: 20, color: '#FF0000' },
            { text: 'בדיקת עברית', x: 100, y: 130, fontSize: 14, color: '#00FF00' },
            { text: new Date().toLocaleString(), x: 100, y: 160, fontSize: 12, color: '#0000FF' }
        ],
        language: document.getElementById('language').value
    };
    
    debugLog(`Testing method: ${selectedMethod} with orientation: ${selectedOrientation}`, 'info');
    await sendToServer(testData, selectedMethod);
}

// ========== Export/Import Functions ==========
function exportToJson() {
    if (values.length === 0) {
        showStatus('אין ערכים לייצוא', 'error');
        return;
    }
    
    const pdfUrl = document.getElementById('pdfUrl').value;
    
    // Build JSON object
    const exportData = {
        method: selectedMethod,
        orientation: selectedOrientation,
        language: document.getElementById('language').value,
        values: values
    };
    
    // Add URL only if exists
    if (pdfUrl && pdfUrl.trim() !== '') {
        exportData.filename = pdfUrl;
    }
    
    // Create formatted JSON
    const jsonString = JSON.stringify(exportData, null, 2);
    
    // Create modal
    const modal = document.createElement('div');
    modal.innerHTML = exportModalTemplate;
    const backdrop = document.createElement('div');
    backdrop.innerHTML = exportBackdropTemplate;
    
    document.body.appendChild(backdrop.firstElementChild);
    document.body.appendChild(modal.firstElementChild);
    
    // Set JSON content
    document.getElementById('exportedJson').value = jsonString;
    document.getElementById('exportedJson').select();
    
    debugLog(`Exported JSON with ${values.length} values`, 'success');
}

function copyJsonToClipboard() {
    const textarea = document.getElementById('exportedJson');
    textarea.select();
    document.execCommand('copy');
    showStatus('JSON הועתק ללוח!', 'success');
}

function downloadJson() {
    const textarea = document.getElementById('exportedJson');
    const blob = new Blob([textarea.value], { type: 'application/json' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `pdf-config-${Date.now()}.json`;
    a.click();
    URL.revokeObjectURL(url);
    showStatus('הקובץ הורד!', 'success');
}

function closeExportModal() {
    const modal = document.getElementById('exportModal');
    const backdrop = document.getElementById('modalBackdrop');
    if (modal) modal.remove();
    if (backdrop) backdrop.remove();
}

// ========== Global PDF Generator Function ==========
window.generatePDF = async function(jsonData) {
    if (!jsonData || typeof jsonData !== 'object') {
        throw new Error('Invalid JSON data provided');
    }

    const defaultConfig = {
        method: 'mpdf',
        language: 'he',
        orientation: 'P',
        filename: null,
        values: []
    };

    const config = Object.assign({}, defaultConfig, jsonData);

    if (!Array.isArray(config.values) || config.values.length === 0) {
        throw new Error('No values provided in JSON');
    }

    config.values = config.values.map((value, index) => {
        if (!value.text) {
            console.warn(`Value at index ${index} missing text, skipping...`);
            return null;
        }
        
        return {
            text: String(value.text),
            x: parseInt(value.x) || 100,
            y: parseInt(value.y) || 100,
            fontSize: parseInt(value.fontSize) || 12,
            color: value.color || '#000000'
        };
    }).filter(v => v !== null);

    if (config.values.length === 0) {
        throw new Error('No valid values found after validation');
    }

    const apiUrl = 'api/pdf-mpdf-overlay.php';

    console.log('🚀 Generating PDF with config:', config);

    try {
        const response = await fetch(apiUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(config)
        });

        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }

        const result = await response.json();

        if (result.success) {
            console.log('✅ PDF created successfully:', result);
            
            if (result.view_url || result.direct_url) {
                const pdfUrl = result.view_url || result.direct_url;
                window.open(pdfUrl, '_blank');
                console.log('📥 PDF opened in new tab');
            }
            
            return {
                success: true,
                message: 'PDF generated successfully',
                data: result
            };
        } else {
            throw new Error(result.error || 'PDF generation failed');
        }

    } catch (error) {
        console.error('❌ Error generating PDF:', error);
        return {
            success: false,
            error: error.message,
            details: error
        };
    }
};