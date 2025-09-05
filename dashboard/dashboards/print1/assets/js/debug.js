/**
 * Debug Console Functions
 */

function debugLog(message, type = 'info') {
    const debugOutput = document.getElementById('debugOutput');
    if (!debugOutput) return;
    
    const timestamp = new Date().toLocaleTimeString();
    const prefix = type === 'error' ? '❌' : type === 'success' ? '✅' : type === 'warning' ? '⚠️' : 'ℹ️';
    
    debugOutput.innerHTML += `\n[${timestamp}] ${prefix} ${message}`;
    debugOutput.scrollTop = debugOutput.scrollHeight;
}

function clearDebug() {
    const debugOutput = document.getElementById('debugOutput');
    if (debugOutput) {
        debugOutput.innerHTML = 'Debug console cleared...';
        debugLog('Console cleared', 'info');
    }
}

async function testConnection() {
    debugLog('Testing server connections...', 'info');
    
    const METHOD_FILES = {
        'minimal': 'api/pdf-minimal.php',
        'fpdf': 'api/pdf-fpdf.php',
        'html': 'api/pdf-html.php',
        'postscript': 'api/pdf-postscript.php',
        'mpdf': 'api/pdf-mpdf-overlay.php',
        'tcpdf': 'api/pdf-tcpdf-overlay.php',
    };
    
    for (const [method, file] of Object.entries(METHOD_FILES)) {
        try {
            const response = await fetch(file + '?test=1');
            if (response.ok) {
                const data = await response.json();
                debugLog(`✅ ${method}: ${data.message || 'OK'}`, 'success');
            } else {
                debugLog(`❌ ${method}: HTTP ${response.status}`, 'error');
            }
        } catch (error) {
            debugLog(`❌ ${method}: ${error.message}`, 'error');
        }
    }
}

async function checkAvailableMethods() {
    debugLog('Checking available methods...', 'info');
    
    const METHOD_FILES = {
        'minimal': 'api/pdf-minimal.php',
        'fpdf': 'api/pdf-fpdf.php',
        'html': 'api/pdf-html.php',
        'postscript': 'api/pdf-postscript.php',
        'mpdf': 'api/pdf-mpdf-overlay.php',
        'tcpdf': 'api/pdf-tcpdf-overlay.php',
    };
    
    const available = [];
    for (const [method, file] of Object.entries(METHOD_FILES)) {
        try {
            const response = await fetch(file + '?test=1');
            if (response.ok) {
                const data = await response.json();
                available.push(method);
                
                if (method === 'fpdf' && data.installed === false) {
                    debugLog(`⚠️ ${method}: File exists but FPDF not installed`, 'warning');
                } else {
                    debugLog(`✅ ${method}: Available`, 'success');
                }
            }
        } catch (error) {
            debugLog(`❌ ${method}: Not available`, 'error');
        }
    }
    
    if (available.length > 0) {
        showStatus(`${available.length} שיטות זמינות: ${available.join(', ')}`, 'success');
    } else {
        showStatus('אף שיטה לא זמינה!', 'error');
    }
}