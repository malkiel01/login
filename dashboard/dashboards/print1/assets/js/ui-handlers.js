/**
 * UI Handler Functions
 */

// ========== Value Management ==========
function addValue() {
    const text = document.getElementById('textValue').value;
    const x = parseInt(document.getElementById('xCoord').value) || 100;
    const y = parseInt(document.getElementById('yCoord').value) || 100;
    const fontSize = parseInt(document.getElementById('fontSize').value) || 12;
    const color = document.getElementById('fontColor').value;

    if (!text) {
        showStatus('נא להכניס טקסט', 'error');
        debugLog('Failed to add value: missing text', 'error');
        return;
    }

    const value = { text, x, y, fontSize, color };
    values.push(value);
    
    debugLog(`Added value: ${JSON.stringify(value)}`, 'success');
    updateValuesList();
    clearInputs();
    showStatus('הערך נוסף בהצלחה', 'success');
    saveState();
}

function updateValuesList() {
    const listDiv = document.getElementById('valuesList');
    const previewDiv = document.getElementById('previewArea');
    
    if (values.length === 0) {
        listDiv.innerHTML = '<p style="text-align: center; color: #718096;">לא הוספו ערכים עדיין</p>';
        previewDiv.innerHTML = '<span>אין ערכים להצגה</span>';
        return;
    }

    listDiv.innerHTML = values.map((val, index) => `
        <div class="value-item">
            <span class="value-item-text">${val.text}</span>
            <span class="value-item-coords">(${val.x}, ${val.y}) - ${val.fontSize}px</span>
            <span class="color-dot" style="background: ${val.color};"></span>
            <button class="btn btn-danger btn-small" onclick="removeValue(${index})">
                הסר
            </button>
        </div>
    `).join('');

    previewDiv.innerHTML = `<strong>סה"כ ערכים: ${values.length}</strong>`;
}

function removeValue(index) {
    const removed = values.splice(index, 1)[0];
    debugLog(`Removed value: ${JSON.stringify(removed)}`, 'info');
    updateValuesList();
    showStatus('הערך הוסר', 'success');
    saveState();
}

function clearAll() {
    if (confirm('האם אתה בטוח שברצונך לנקות את כל הערכים?')) {
        values = [];
        updateValuesList();
        clearInputs();
        document.getElementById('pdfUrl').value = '';
        debugLog('Cleared all values', 'info');
        showStatus('כל הנתונים נוקו', 'success');
        saveState();
    }
}

// ========== Method & Orientation Selection ==========
function selectMethod(method) {
    selectedMethod = method;
    
    // Update badges
    document.querySelectorAll('.method-badge').forEach(badge => {
        badge.classList.remove('active');
    });
    document.querySelector(`[data-method="${method}"]`).classList.add('active');
    
    // Update description
    const descriptions = {
        'mpdf': 'mPDF - תמיכה מושלמת בעברית!',
        'tcpdf': 'tcpdf - תמיכה מושלמת בקובץ ובעברית!',
        'fpdf': 'FPDF - ספרייה מתקדמת ליצירת PDF איכותי',
        'minimal': 'Minimal PDF - יוצר PDF בסיסי ללא תלויות'
    };
    
    document.getElementById('methodDescription').textContent = descriptions[method] || '';
    debugLog(`Selected method: ${method}`, 'info');
    saveState();
}

function selectOrientation(orientation) {
    selectedOrientation = orientation;
    
    // Update buttons
    document.querySelectorAll('.orientation-btn').forEach(btn => {
        btn.classList.remove('active');
    });
    document.querySelector(`[data-orientation="${orientation}"]`).classList.add('active');
    
    debugLog(`Selected orientation: ${orientation === 'P' ? 'Portrait' : 'Landscape'}`, 'info');
    saveState();
}

// ========== Color Preview ==========
function updateColorPreview() {
    const color = document.getElementById('fontColor').value;
    const preview = document.getElementById('colorPreview');
    if (preview) {
        preview.textContent = color.toUpperCase();
        preview.style.color = color;
    }
}

// ========== JSON Modal ==========
function showJsonInput() {
    document.getElementById('jsonSection').style.display = 'block';
    document.getElementById('jsonSection').scrollIntoView({ behavior: 'smooth' });
}

function hideJsonInput() {
    document.getElementById('jsonSection').style.display = 'none';
}