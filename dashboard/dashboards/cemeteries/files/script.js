// Global Variables
const uploadArea = document.getElementById('uploadArea');
const fileInput = document.getElementById('fileInput');
const fileInfo = document.getElementById('fileInfo');
const fileName = document.getElementById('fileName');
const fileSize = document.getElementById('fileSize');
const processBtn = document.getElementById('processBtn');
const loading = document.getElementById('loading');
const error = document.getElementById('error');
const results = document.getElementById('results');
const downloadBtn = document.getElementById('downloadBtn');
const deleteSourceBtn = document.getElementById('deleteSourceBtn');
const deleteProcessedBtn = document.getElementById('deleteProcessedBtn');

let selectedFile = null;
let processedFileName = null;
let textItems = [];
let nextTextId = 1;

// PDF Preview Variables
let pdfDoc = null;
let currentPageNum = 1;
let totalPagesNum = 0;
let pageRendering = false;
let pageNumPending = null;
let pdfScale = 2.0;

let draggingTextId = null;
let dragStartX = 0;
let dragStartY = 0;
let dragStartTop = 0;
let dragStartRight = 0;
let selectedTextId = null;

let resizingCorner = null;  // 'top-right', 'top-left', 'bottom-right', 'bottom-left'
let resizeStartSize = 0;
let resizeStartX = 0;
let resizeStartY = 0;

const minScale = 0.5;
const maxScale = 4.0;
const scaleStep = 0.25;

// Zoom Controls
document.getElementById('zoomIn').addEventListener('click', () => {
    if (pdfScale < maxScale) {
        pdfScale += scaleStep;
        updateZoom();
    }
});

document.getElementById('zoomOut').addEventListener('click', () => {
    if (pdfScale > minScale) {
        pdfScale -= scaleStep;
        updateZoom();
    }
});

function updateZoom() {
    document.getElementById('zoomLevel').textContent = Math.round(pdfScale * 100) + '%';
    if (pdfDoc) {
        renderPage(currentPageNum);
    }
}

const canvas = document.getElementById('pdfCanvas');
const ctx = canvas.getContext('2d');

canvas.addEventListener('mousedown', handleCanvasMouseDown);
canvas.addEventListener('mousemove', handleCanvasMouseMove);
canvas.addEventListener('mouseup', handleCanvasMouseUp);


// ===============================
// גרירה על הקנבס
// ===============================

function handleCanvasMouseDown2(e) {
    const rect = canvas.getBoundingClientRect();
    const x = e.clientX - rect.left;
    const y = e.clientY - rect.top;
    
    const scaleX = canvas.width / rect.width;
    const scaleY = canvas.height / rect.height;
    
    const canvasX = x * scaleX;
    const canvasY = y * scaleY;
    
    const clickedText = findTextAtPosition(canvasX, canvasY);
    
    if (clickedText) {
        selectedTextId = clickedText.id;  // ← בחר את הטקסט
        draggingTextId = clickedText.id;
        dragStartX = canvasX;
        dragStartY = canvasY;
        dragStartTop = clickedText.top;
        dragStartRight = clickedText.right;
        canvas.style.cursor = 'grabbing';
        renderPage(currentPageNum);  // ← רנדר מחדש עם מסגרת
    } else {
        selectedTextId = null;  // ← בטל בחירה
        renderPage(currentPageNum);
    }
}

async function handleCanvasMouseDown(e) {
    const rect = canvas.getBoundingClientRect();
    const x = e.clientX - rect.left;
    const y = e.clientY - rect.top;
    
    const scaleX = canvas.width / rect.width;
    const scaleY = canvas.height / rect.height;
    
    const canvasX = x * scaleX;
    const canvasY = y * scaleY;
    
    // בדוק אם לחצנו על פינה של טקסט נבחר
    if (selectedTextId !== null) {
        const selectedItem = textItems.find(t => t.id === selectedTextId);
        if (selectedItem) {
            const page = await pdfDoc.getPage(currentPageNum);
            const viewport = page.getViewport({ scale: pdfScale });
            const corner = findCornerAtPosition(canvasX, canvasY, selectedItem, viewport);
            
            if (corner) {
                // התחל resize
                resizingCorner = corner;
                resizeStartSize = parseInt(selectedItem.size);
                resizeStartX = canvasX;
                resizeStartY = canvasY;
                canvas.style.cursor = 'nwse-resize';
                return;
            }
        }
    }
    
    const clickedText = findTextAtPosition(canvasX, canvasY);
    
    if (clickedText) {
        selectedTextId = clickedText.id;
        draggingTextId = clickedText.id;
        dragStartX = canvasX;
        dragStartY = canvasY;
        dragStartTop = clickedText.top;
        dragStartRight = clickedText.right;
        canvas.style.cursor = 'grabbing';
        renderPage(currentPageNum);
    } else {
        selectedTextId = null;
        renderPage(currentPageNum);
    }
}


function handleCanvasMouseMove2(e) {
    if (draggingTextId === null) return;
    
    const rect = canvas.getBoundingClientRect();
    const x = e.clientX - rect.left;
    const y = e.clientY - rect.top;
    
    // ← התאם
    const scaleX = canvas.width / rect.width;
    const scaleY = canvas.height / rect.height;
    
    const canvasX = x * scaleX;
    const canvasY = y * scaleY;
    
    const deltaX = canvasX - dragStartX;
    const deltaY = canvasY - dragStartY;
    
    const item = textItems.find(t => t.id === draggingTextId);
    if (item) {
        item.top = Math.round(dragStartTop + (deltaY / pdfScale));
        item.right = Math.round(dragStartRight - (deltaX / pdfScale));
        
        updateFieldValues(item);
        renderPage(currentPageNum);
    }
}

function handleCanvasMouseMove(e) {
    const rect = canvas.getBoundingClientRect();
    const x = e.clientX - rect.left;
    const y = e.clientY - rect.top;
    
    const scaleX = canvas.width / rect.width;
    const scaleY = canvas.height / rect.height;
    
    const canvasX = x * scaleX;
    const canvasY = y * scaleY;
    
    // אם בresize
    if (resizingCorner !== null) {
        const item = textItems.find(t => t.id === selectedTextId);
        if (item) {
            // חשב שינוי בגודל לפי מרחק מהנקודה ההתחלתית
            const deltaX = canvasX - resizeStartX;
            const deltaY = canvasY - resizeStartY;
            const delta = Math.sqrt(deltaX * deltaX + deltaY * deltaY);
            
            // קבע כיוון (+/-)
            let sign = 1;
            if (resizingCorner === 'top-left' || resizingCorner === 'bottom-left') {
                sign = deltaX < 0 ? 1 : -1;
            } else {
                sign = deltaX > 0 ? 1 : -1;
            }
            
            const newSize = Math.max(12, Math.round(resizeStartSize + (delta * sign / pdfScale / 3)));
            item.size = newSize;
            
            updateFieldValues(item);
            renderPage(currentPageNum);
        }
        return;
    }
    
    // אם בגרירה
    if (draggingTextId === null) return;
    
    const deltaX = canvasX - dragStartX;
    const deltaY = canvasY - dragStartY;
    
    const item = textItems.find(t => t.id === draggingTextId);
    if (item) {
        item.top = Math.round(dragStartTop + (deltaY / pdfScale));
        item.right = Math.round(dragStartRight - (deltaX / pdfScale));
        
        updateFieldValues(item);
        renderPage(currentPageNum);
    }
}

function handleCanvasMouseUp2() {
    draggingTextId = null;
    canvas.style.cursor = 'grab';
}

function handleCanvasMouseUp() {
    draggingTextId = null;
    resizingCorner = null;
    canvas.style.cursor = 'grab';
}

function findTextAtPosition(x, y) {
    console.log('Looking for text at:', x, y);
    console.log('Current page:', currentPageNum);
    console.log('Text items:', textItems);
    
    for (let i = textItems.length - 1; i >= 0; i--) {
        const item = textItems[i];
        const itemPage = parseInt(item.page) || 1;
        
        console.log(`Checking item ${i}: page=${itemPage}, currentPage=${currentPageNum}`);
        
        if (itemPage !== currentPageNum) {
            console.log('  Skipping - wrong page');
            continue;
        }
        
        const fontSize = parseInt(item.size) * pdfScale;
        const topOffset = parseFloat(item.top) * pdfScale;
        const rightOffset = parseFloat(item.right) * pdfScale;
        const align = item.align || 'right';
        
        // מצא את הפונט ברשימה למדידת רוחב
        const fontData = availableFonts.find(f => f.id === item.font);
        const fontName = fontData ? fontData.id : 'Arial';
        
        ctx.font = `${fontSize}px "${fontName}", sans-serif`;
        const textWidth = ctx.measureText(item.text).width;
        
        let textX, textY;
        if (align === 'right') {
            textX = canvas.width - rightOffset;
        } else {
            textX = rightOffset;
        }
        textY = topOffset;
        
        // הגדר hitbox גדול יותר - מלבן מלא סביב הטקסט
        let hitBoxLeft, hitBoxRight, hitBoxTop, hitBoxBottom;
        
        if (align === 'right') {
            hitBoxRight = textX + 10;  // margin קטן מימין
            hitBoxLeft = textX - textWidth - 10;  // margin קטן משמאל
        } else {
            hitBoxLeft = textX - 10;
            hitBoxRight = textX + textWidth + 10;
        }
        
        hitBoxTop = textY - fontSize * 1.2;  // מעל הטקסט (כולל ascenders)
        hitBoxBottom = textY + fontSize * 0.3;  // מתחת לטקסט (כולל descenders)
        
        console.log(`  HitBox: left=${hitBoxLeft}, right=${hitBoxRight}, top=${hitBoxTop}, bottom=${hitBoxBottom}`);
        console.log(`  Click: x=${x}, y=${y}`);
        
        if (x >= hitBoxLeft && x <= hitBoxRight &&
            y >= hitBoxTop && y <= hitBoxBottom) {
            console.log('  ✅ HIT!');
            return item;
        }
    }
    
    console.log('No text found');
    return null;
}

function updateFieldValues2(item) {
    // עדכן את השדות בטופס
    const itemDiv = document.getElementById(`text-item-${item.id}`);
    if (itemDiv) {
        const topInput = itemDiv.querySelector('input[type="number"]');
        const inputs = itemDiv.querySelectorAll('input[type="number"]');
        inputs[2].value = item.top;  // top
        inputs[3].value = item.right; // right
    }
}

function updateFieldValues(item) {
    const itemDiv = document.getElementById(`text-item-${item.id}`);
    if (itemDiv) {
        const inputs = itemDiv.querySelectorAll('input[type="number"]');
        inputs[0].value = item.size;   // ← size (index 0)
        inputs[1].value = item.page;   // ← page (index 1)
        inputs[2].value = item.top;    // ← top (index 2)
        inputs[3].value = item.right;  // ← right (index 3)
    }
}

function drawSelectionBox(item, viewport) {
    const fontSize = parseInt(item.size) * pdfScale;
    const topOffset = parseFloat(item.top) * pdfScale;
    const rightOffset = parseFloat(item.right) * pdfScale;
    const align = item.align || 'right';
    
    const fontData = availableFonts.find(f => f.id === item.font);
    const fontName = fontData ? fontData.id : 'Arial';
    
    ctx.font = `${fontSize}px "${fontName}", sans-serif`;
    const textWidth = ctx.measureText(item.text).width;
    
    let textX, textY;
    if (align === 'right') {
        textX = viewport.width - rightOffset;
    } else {
        textX = rightOffset;
    }
    textY = topOffset;
    
    // מסגרת
    let boxLeft, boxRight, boxTop, boxBottom;
    
    if (align === 'right') {
        boxRight = textX + 5;
        boxLeft = textX - textWidth - 5;
    } else {
        boxLeft = textX - 5;
        boxRight = textX + textWidth + 5;
    }
    
    boxTop = textY - fontSize * 1.1;
    boxBottom = textY + fontSize * 0.2;
    
    // צייר מסגרת
    ctx.strokeStyle = '#667eea';
    ctx.lineWidth = 2;
    ctx.setLineDash([5, 5]);
    ctx.strokeRect(boxLeft, boxTop, boxRight - boxLeft, boxBottom - boxTop);
    ctx.setLineDash([]);
    
    // צייר פינות (נקודות resize)
    const cornerSize = 8;
    ctx.fillStyle = '#667eea';
    
    // פינה ימנית עליונה
    ctx.fillRect(boxRight - cornerSize/2, boxTop - cornerSize/2, cornerSize, cornerSize);
    
    // פינה שמאלית עליונה
    ctx.fillRect(boxLeft - cornerSize/2, boxTop - cornerSize/2, cornerSize, cornerSize);
    
    // פינה ימנית תחתונה
    ctx.fillRect(boxRight - cornerSize/2, boxBottom - cornerSize/2, cornerSize, cornerSize);
    
    // פינה שמאלית תחתונה
    ctx.fillRect(boxLeft - cornerSize/2, boxBottom - cornerSize/2, cornerSize, cornerSize);
}

function findCornerAtPosition(x, y, item, viewport) {
    const fontSize = parseInt(item.size) * pdfScale;
    const topOffset = parseFloat(item.top) * pdfScale;
    const rightOffset = parseFloat(item.right) * pdfScale;
    const align = item.align || 'right';
    
    const fontData = availableFonts.find(f => f.id === item.font);
    const fontName = fontData ? fontData.id : 'Arial';
    
    ctx.font = `${fontSize}px "${fontName}", sans-serif`;
    const textWidth = ctx.measureText(item.text).width;
    
    let textX, textY;
    if (align === 'right') {
        textX = viewport.width - rightOffset;
    } else {
        textX = rightOffset;
    }
    textY = topOffset;
    
    let boxLeft, boxRight, boxTop, boxBottom;
    
    if (align === 'right') {
        boxRight = textX + 5;
        boxLeft = textX - textWidth - 5;
    } else {
        boxLeft = textX - 5;
        boxRight = textX + textWidth + 5;
    }
    
    boxTop = textY - fontSize * 1.1;
    boxBottom = textY + fontSize * 0.2;
    
    const cornerSize = 8;
    const hitSize = 12;  // אזור לחיצה גדול יותר
    
    // בדוק כל פינה
    if (Math.abs(x - boxRight) < hitSize && Math.abs(y - boxTop) < hitSize) {
        return 'top-right';
    }
    if (Math.abs(x - boxLeft) < hitSize && Math.abs(y - boxTop) < hitSize) {
        return 'top-left';
    }
    if (Math.abs(x - boxRight) < hitSize && Math.abs(y - boxBottom) < hitSize) {
        return 'bottom-right';
    }
    if (Math.abs(x - boxLeft) < hitSize && Math.abs(y - boxBottom) < hitSize) {
        return 'bottom-left';
    }
    
    return null;
}

// ===============================
// Dynamic Font Loading
// ===============================

let availableFonts = [];

async function loadFonts() {
    try {
        const response = await fetch('fonts.json');
        const data = await response.json();
        availableFonts = data.fonts;
        
        // טען כל פונט דינמית
        for (const font of availableFonts) {
            const fontFace = new FontFace(
                font.id, 
                `url(${font.path})`
            );
            
            try {
                await fontFace.load();
                document.fonts.add(fontFace);
                console.log(`✅ Loaded font: ${font.name}`);
            } catch (err) {
                console.error(`❌ Failed to load font ${font.name}:`, err);
            }
        }
        
        // עדכן את ה-select
        updateFontSelectors();
        
    } catch (error) {
        console.error('Error loading fonts:', error);
    }
}

function updateFontSelectors() {
    // עדכן כל ה-select של הפונטים
    document.querySelectorAll('select[data-font-selector]').forEach(select => {
        select.innerHTML = availableFonts.map(font => 
            `<option value="${font.id}">${font.name}</option>`
        ).join('');
    });
}

// טען פונטים בטעינת הדף
loadFonts();

// ===============================
// File Upload Handlers
// ===============================

uploadArea.addEventListener('click', () => fileInput.click());

fileInput.addEventListener('change', (e) => {
    handleFile(e.target.files[0]);
});

uploadArea.addEventListener('dragover', (e) => {
    e.preventDefault();
    uploadArea.classList.add('dragover');
});

uploadArea.addEventListener('dragleave', () => {
    uploadArea.classList.remove('dragover');
});

uploadArea.addEventListener('drop', (e) => {
    e.preventDefault();
    uploadArea.classList.remove('dragover');
    handleFile(e.dataTransfer.files[0]);
});

function handleFile(file) {
    if (!file) return;
    
    if (file.type !== 'application/pdf') {
        showError('נא להעלות קובץ PDF בלבד');
        return;
    }

    selectedFile = file;
    fileName.textContent = `📄 ${file.name}`;
    fileSize.textContent = `גודל: ${formatFileSize(file.size)}`;
    fileInfo.classList.add('show');
    
    document.getElementById('textsContainer').classList.add('show');
    
    if (textItems.length === 0) {
        addTextItem();
    }
    
    processBtn.classList.add('show');
    results.classList.remove('show');
    error.classList.remove('show');
    
    // Load PDF Preview
    loadPDF(file);
}

function formatFileSize(bytes) {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i];
}

// ===============================
// Text Items Management
// ===============================

document.getElementById('addTextBtn').addEventListener('click', addTextItem);

function addTextItem() {
    const id = nextTextId++;
    const textItem = {
        id: id,
        text: 'ניסיון',
        font: 'david',
        size: 48,
        color: '#808080',
        top: 300,
        right: 200,
        page: 1,
        align: 'right'  // ← הוסף: 'right' או 'left'
    };
    
    textItems.push(textItem);
    renderTextItem(textItem);
    
    if (pdfDoc) {
        renderPage(currentPageNum);
    }
}

function renderTextItem(item) {
    const textsList = document.getElementById('textsList');
    const itemDiv = document.createElement('div');
    itemDiv.className = 'text-item';
    itemDiv.id = `text-item-${item.id}`;
    
    // בנה אפשרויות פונט דינמית
    const fontOptions = availableFonts.map(font => 
        `<option value="${font.id}" ${item.font === font.id ? 'selected' : ''}>${font.name}</option>`
    ).join('');
    
    itemDiv.innerHTML = `
        <div class="text-item-header">
            <span class="text-item-title">טקסט #${item.id}</span>
            <button type="button" class="remove-text-btn" onclick="removeTextItem(${item.id})">🗑️ הסר</button>
        </div>
        
        <div class="form-group full-width">
            <label>תוכן הטקסט:</label>
            <input type="text" value="${item.text}" oninput="updateTextItem(${item.id}, 'text', this.value)">
        </div>
        
        <div class="form-row">
            <div class="form-group">
                <label>פונט:</label>
                <select onchange="updateTextItem(${item.id}, 'font', this.value)">
                    ${fontOptions}
                </select>
            </div>
            
            <div class="form-group">
                <label>גודל פונט:</label>
                <input type="number" value="${item.size}" min="8" max="200" oninput="updateTextItem(${item.id}, 'size', this.value)">
            </div>
        </div>
        
        <div class="form-row">
            <div class="form-group">
                <label>צבע:</label>
                <input type="color" value="${item.color}" oninput="updateTextItem(${item.id}, 'color', this.value)">
            </div>
            
            <div class="form-group">
                <label>מרחק מלמעלה (פיקסלים):</label>
                <input type="number" value="${item.top}" min="0" oninput="updateTextItem(${item.id}, 'top', this.value)">
            </div>
        </div>
        
        <div class="form-row">
            <div class="form-group">
                <label>מרחק מימין (פיקסלים):</label>
                <input type="number" value="${item.right}" min="0" oninput="updateTextItem(${item.id}, 'right', this.value)">
            </div>

            <div class="form-group">
                <label>עמוד להדפסה:</label>
                <input type="number" value="${item.page || 1}" min="1" max="99" oninput="updateTextItem(${item.id}, 'page', this.value)">
            </div>
        </div>
        
        <div class="form-row">
            <div class="form-group">
                <label>יישור טקסט:</label>
                <select onchange="updateTextItem(${item.id}, 'align', this.value)">
                    <option value="right" ${(item.align || 'right') === 'right' ? 'selected' : ''}>ימין (עברית)</option>
                    <option value="left" ${item.align === 'left' ? 'selected' : ''}>שמאל (אנגלית)</option>
                </select>
            </div>
        </div>
    `;
    
    textsList.appendChild(itemDiv);
}

function updateTextItem(id, field, value) {
    const item = textItems.find(t => t.id === id);
    if (item) {
        item[field] = value;
        if (pdfDoc) {
            renderPage(currentPageNum);
        }
    }
}

function removeTextItem(id) {
    const index = textItems.findIndex(t => t.id === id);
    if (index > -1) {
        textItems.splice(index, 1);
        document.getElementById(`text-item-${id}`).remove();
        if (pdfDoc) {
            renderPage(currentPageNum);
        }
    }
}

// ===============================
// PDF Processing
// ===============================

processBtn.addEventListener('click', async () => {
    if (!selectedFile) return;

    if (textItems.length === 0) {
        showError('נא להוסיף לפחות טקסט אחד');
        return;
    }

    const formData = new FormData();
    formData.append('pdf', selectedFile);
    formData.append('texts', JSON.stringify(textItems));

    // הוסף מיד אחריה:
   console.log('INDEX - Sending texts:', textItems);
   console.log('INDEX - JSON:', JSON.stringify(textItems, null, 2));

    processBtn.disabled = true;
    processBtn.textContent = 'מעבד...';
    loading.classList.add('show');
    error.classList.remove('show');
    results.classList.remove('show');

    try {
        const response = await fetch('process.php', {
            method: 'POST',
            body: formData
        });

        const data = await response.json();

        if (data.success) {
            document.getElementById('pageCount').textContent = data.pages;
            document.getElementById('pageWidth').textContent = data.width + ' נקודות';
            document.getElementById('pageHeight').textContent = data.height + ' נקודות';
            processedFileName = data.output_file;
            results.classList.add('show');
        } else {
            showError(data.error || 'שגיאה בעיבוד הקובץ');
        }
    } catch (err) {
        showError('שגיאה בתקשורת עם השרת');
        console.error(err);
    } finally {
        loading.classList.remove('show');
        processBtn.disabled = false;
        processBtn.textContent = 'עבד את הקובץ';
    }
});

downloadBtn.addEventListener('click', () => {
    if (processedFileName) {
        window.location.href = 'download.php?file=' + encodeURIComponent(processedFileName);
    }
});

function showError(message) {
    error.textContent = message;
    error.classList.add('show');
}

// ===============================
// Delete Handlers
// ===============================

deleteSourceBtn.addEventListener('click', async () => {
    if (!confirm('האם אתה בטוח שברצונך למחוק את הקובץ המקורי מהשרת?')) {
        return;
    }
    
    try {
        const response = await fetch('delete.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ 
                type: 'source',
                file: selectedFile.name 
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            deleteSourceBtn.classList.add('disabled');
            deleteSourceBtn.textContent = '✅ קובץ מקור נמחק';
        } else {
            alert('שגיאה במחיקה: ' + data.error);
        }
    } catch (err) {
        alert('שגיאה בתקשורת');
    }
});

deleteProcessedBtn.addEventListener('click', async () => {
    if (!confirm('האם אתה בטוח שברצונך למחוק את הקובץ המעובד מהשרת?')) {
        return;
    }
    
    try {
        const response = await fetch('delete.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ 
                type: 'processed',
                file: processedFileName 
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            deleteProcessedBtn.classList.add('disabled');
            deleteProcessedBtn.textContent = '✅ קובץ מעובד נמחק';
            downloadBtn.classList.add('disabled');
            downloadBtn.textContent = '❌ הקובץ נמחק';
        } else {
            alert('שגיאה במחיקה: ' + data.error);
        }
    } catch (err) {
        alert('שגיאה בתקשורת');
    }
});

// ===============================
// PDF Preview Functions
// ===============================

async function loadPDF(file) {
    const fileReader = new FileReader();
    
    fileReader.onload = async function() {
        const typedarray = new Uint8Array(this.result);
        
        try {
            pdfDoc = await pdfjsLib.getDocument(typedarray).promise;
            totalPagesNum = pdfDoc.numPages;
            
            document.getElementById('totalPages').textContent = totalPagesNum;
            document.getElementById('currentPage').textContent = '1';
            
            if (totalPagesNum > 1) {
                document.getElementById('pageNav').style.display = 'flex';
            }
            
            renderPage(1);
            document.getElementById('previewSection').style.display = 'block';
            
        } catch (error) {
            console.error('Error loading PDF:', error);
            showError('שגיאה בטעינת ה-PDF לתצוגה');
        }
    };
    
    fileReader.readAsArrayBuffer(file);
}

async function renderPage(num) {
    pageRendering = true;
    
    try {
        const page = await pdfDoc.getPage(num);
        const viewport = page.getViewport({ scale: pdfScale });
        
        canvas.width = viewport.width;
        canvas.height = viewport.height;
        
        const renderContext = {
            canvasContext: ctx,
            viewport: viewport,
        };
        
        await page.render(renderContext).promise;
        
        pageRendering = false;
        
        if (pageNumPending !== null) {
            renderPage(pageNumPending);
            pageNumPending = null;
        }
        
        drawTextsOnCanvas(viewport);
        
    } catch (error) {
        console.error('Error rendering page:', error);
        pageRendering = false;
    }
}
async function renderPage2(num) {
    pageRendering = true;
    
    try {
        const page = await pdfDoc.getPage(num);
        const viewport = page.getViewport({ scale: pdfScale });
        
        // canvas.width = viewport.width;
        // canvas.height = viewport.height;

        const outputScale = window.devicePixelRatio || 1;
        canvas.width = viewport.width * outputScale;
        canvas.height = viewport.height * outputScale;
        canvas.style.width = viewport.width + 'px';
        canvas.style.height = viewport.height + 'px';

        ctx.scale(outputScale, outputScale);
        
        const renderContext = {
            canvasContext: ctx,
            viewport: viewport,
            intent: 'print'
        };
        
        await page.render(renderContext).promise;
        
        pageRendering = false;
        
        if (pageNumPending !== null) {
            renderPage(pageNumPending);
            pageNumPending = null;
        }
        
        drawTextsOnCanvas(viewport);
        
    } catch (error) {
        console.error('Error rendering page:', error);
        pageRendering = false;
    }
}

function queueRenderPage(num) {
    if (pageRendering) {
        pageNumPending = num;
    } else {
        renderPage(num);
    }
}

function onPrevPage() {
    if (currentPageNum <= 1) return;
    currentPageNum--;
    document.getElementById('currentPage').textContent = currentPageNum;
    queueRenderPage(currentPageNum);
    updatePageButtons();
}

function onNextPage() {
    if (currentPageNum >= totalPagesNum) return;
    currentPageNum++;
    document.getElementById('currentPage').textContent = currentPageNum;
    queueRenderPage(currentPageNum);
    updatePageButtons();
}

function updatePageButtons() {
    document.getElementById('prevPage').disabled = (currentPageNum <= 1);
    document.getElementById('nextPage').disabled = (currentPageNum >= totalPagesNum);
}

document.getElementById('prevPage').addEventListener('click', onPrevPage);
document.getElementById('nextPage').addEventListener('click', onNextPage);

function drawTextsOnCanvas(viewport) {
    const baseScale = viewport.scale;
    
    textItems.forEach(item => {
        const itemPage = parseInt(item.page) || 1;
        if (itemPage !== currentPageNum) {
            return;
        }
        
        const text = item.text;
        const fontSize = Math.round(parseInt(item.size) * baseScale);
        const color = item.color;
        const topOffset = Math.round(parseFloat(item.top) * baseScale);
        const rightOffset = Math.round(parseFloat(item.right) * baseScale);
        const align = item.align || 'right';
        
        const fontData = availableFonts.find(f => f.id === item.font);
        const fontName = fontData ? fontData.id : 'Arial';
        
        let x;
        if (align === 'right') {
            // ← הוסף offset קטן לתיקון
            // x = Math.round(viewport.width - rightOffset) + (fontSize * 0.02);
            // x = Math.round(viewport.width - rightOffset) - (fontSize * 0.04);
            x = viewport.width - rightOffset;
        } else {
            x = rightOffset;
        }
        const y = topOffset;
        
        ctx.font = `${fontSize}px "${fontName}", sans-serif`;
        ctx.fillStyle = color;
        ctx.globalAlpha = 0.7;
        ctx.textAlign = align;
        
        ctx.fillText(text, x, y);
        
        ctx.globalAlpha = 1.0;
        ctx.textAlign = 'left';
    });

    // צייר מסגרת סביב טקסט נבחר
    if (selectedTextId !== null) {
        const selectedItem = textItems.find(t => t.id === selectedTextId);
        if (selectedItem && (parseInt(selectedItem.page) || 1) === currentPageNum) {
            drawSelectionBox(selectedItem, viewport);
        }
    }
}

// ===============================
// Save Template Functionality
// ===============================

const saveTemplateBtn = document.getElementById('saveTemplateBtn');
const saveTemplateModal = document.getElementById('saveTemplateModal');
const templateNameInput = document.getElementById('templateName');
const templateDescriptionInput = document.getElementById('templateDescription');
const cancelSaveBtn = document.getElementById('cancelSaveBtn');
const confirmSaveBtn = document.getElementById('confirmSaveBtn');
const modalError = document.getElementById('modalError');

saveTemplateBtn.addEventListener('click', () => {
    // פתח את המודל
    saveTemplateModal.classList.add('show');
    templateNameInput.value = '';
    templateDescriptionInput.value = '';
    modalError.classList.remove('show');
    templateNameInput.focus();
});

cancelSaveBtn.addEventListener('click', () => {
    saveTemplateModal.classList.remove('show');
});

// סגירה בלחיצה מחוץ למודל
saveTemplateModal.addEventListener('click', (e) => {
    if (e.target === saveTemplateModal) {
        saveTemplateModal.classList.remove('show');
    }
});

confirmSaveBtn.addEventListener('click', async () => {
    const templateName = templateNameInput.value.trim();
    const templateDescription = templateDescriptionInput.value.trim();
    
    // ולידציה
    if (!templateName) {
        showModalError('נא להזין שם לתבנית');
        return;
    }
    
    if (templateName.length < 3) {
        showModalError('שם התבנית חייב להכיל לפחות 3 תווים');
        return;
    }
    
    if (templateName.length > 50) {
        showModalError('שם התבנית ארוך מדי (מקסימום 50 תווים)');
        return;
    }
    
    // הכן את הנתונים לשמירה
    const templateData = {
        name: templateName,
        description: templateDescription,
        original_filename: selectedFile.name,
        pdf_dimensions: {
            width: parseFloat(document.getElementById('pageWidth').textContent),
            height: parseFloat(document.getElementById('pageHeight').textContent)
        },
        page_count: parseInt(document.getElementById('pageCount').textContent),
        fields: textItems.map((item, index) => ({
            id: `field_${index + 1}`,
            label: item.text, // כרגע השם הוא הטקסט עצמו
            text: item.text,
            font: item.font,
            size: parseInt(item.size),
            color: item.color,
            top: parseFloat(item.top),
            right: parseFloat(item.right),
            page: parseInt(item.page) || 1,
            align: item.align || 'right'  // ← הוסף align
        }))
    };
    
    // שלח לשרת
    confirmSaveBtn.disabled = true;
    confirmSaveBtn.textContent = 'שומר...';
    
    try {
        // צור FormData עם הקובץ המקורי והנתונים
        const formData = new FormData();
        formData.append('template_data', JSON.stringify(templateData));
        formData.append('pdf_file', selectedFile);
        
        const response = await fetch('save_template.php', {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        
        if (result.success) {
            // סגור את המודל
            saveTemplateModal.classList.remove('show');
            
            // הצג הודעת הצלחה
            alert(`✅ התבנית "${templateName}" נשמרה בהצלחה!\nמזהה: ${result.template_id}`);
            
        } else {
            showModalError(result.error || 'שגיאה בשמירת התבנית');
        }
        
    } catch (error) {
        console.error('Error saving template:', error);
        showModalError('שגיאה בתקשורת עם השרת');
    } finally {
        confirmSaveBtn.disabled = false;
        confirmSaveBtn.textContent = 'שמור תבנית';
    }
});

function showModalError(message) {
    modalError.textContent = message;
    modalError.classList.add('show');
}