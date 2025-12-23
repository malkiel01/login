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
let pdfScale = 1.0;

const canvas = document.getElementById('pdfCanvas');
const ctx = canvas.getContext('2d');

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
        right: 200
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
                    <option value="david" ${item.font === 'david' ? 'selected' : ''}>דיויד</option>
                    <option value="rubik" ${item.font === 'rubik' ? 'selected' : ''}>רוביק</option>
                    <option value="helvetica" ${item.font === 'helvetica' ? 'selected' : ''}>הלבטיקה</option>
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
            viewport: viewport
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

function drawTextsOnCanvas2(viewport) {
    textItems.forEach(item => {
        const text = item.text;
        const fontName = item.font === 'david' ? 'David Libre' : 
                        item.font === 'rubik' ? 'Rubik' : 'Arial';
        const fontSize = parseInt(item.size);
        const color = item.color;
        const topOffset = parseFloat(item.top);
        const rightOffset = parseFloat(item.right);
        
        const x = viewport.width - rightOffset;
        const y = topOffset;
        
        ctx.font = `${fontSize}px ${fontName}`;
        ctx.fillStyle = color;
        ctx.globalAlpha = 0.7;
        
        const reversedText = text.split('').reverse().join('');
        ctx.fillText(reversedText, x, y);
        
        ctx.globalAlpha = 1.0;
    });
}

function drawTextsOnCanvas(viewport) {
    textItems.forEach(item => {
        const text = item.text;
        const fontName = item.font === 'david' ? 'David Libre' : 
                        item.font === 'rubik' ? 'Rubik' : 'Arial';
        const fontSize = parseInt(item.size);
        const color = item.color;
        const topOffset = parseFloat(item.top);
        const rightOffset = parseFloat(item.right);
        
        const x = viewport.width - rightOffset;
        const y = topOffset;
        
        ctx.font = `${fontSize}px ${fontName}`;
        ctx.fillStyle = color;
        ctx.globalAlpha = 0.7;
        ctx.textAlign = 'right';  // ← הוסף את זה! יישור ימינה
        
        // אל תהפוך את הטקסט - הפונטים העבריים מטפלים בזה
        ctx.fillText(text, x, y);  // ← השתמש ישירות ב-text
        
        ctx.globalAlpha = 1.0;
    });
}