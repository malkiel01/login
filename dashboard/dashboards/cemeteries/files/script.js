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
let allItems = [];

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
let selectedImageId = null;
let draggingImageId = null;
let resizingImageCorner = null;
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
// תמונות על הקנבס
// ===============================

// משתנה לשמירת תמונות
let imageItems = [];
let imageIdCounter = 1;
let renderTimeout = null;

// כפתור הוספת תמונה
document.getElementById('addImageBtn').addEventListener('click', () => {
    document.getElementById('imageFileInput').click();
});

// העלאת קובץ תמונה
document.getElementById('imageFileInput').addEventListener('change', async (e) => {
    const file = e.target.files[0];
    if (!file) return;
    
    // המר תמונה ל-base64
    const reader = new FileReader();
    reader.onload = function(event) {
        const base64Image = event.target.result;
        addImageItem(base64Image, file.name);
    };
    reader.readAsDataURL(file);
    
    // אפס את ה-input
    e.target.value = '';
});

function addImageItem(base64Image, fileName) {
    const imageId = imageIdCounter++;
    
    const imageItem = {
        id: imageId,
        type: 'image',
        fileName: fileName,
        base64: base64Image,
        page: currentPageNum || 1,
        top: 100,
        left: 100,
        width: 200,
        height: 200,
        opacity: 1.0
    };
    
    imageItems.push(imageItem);
    allItems.push(imageItem);  // ← הוסף
    renderImageItem(imageItem);
    
    document.getElementById('textsContainer').style.display = 'block';
    
    if (pdfDoc) {
        renderPage(currentPageNum);
    }
}

function renderImageItem(imageItem) {
    const container = document.getElementById('textsList');
    
    const itemDiv = document.createElement('div');
    itemDiv.className = 'text-item';
    itemDiv.id = `image-item-${imageItem.id}`;
    itemDiv.setAttribute('data-item-id', imageItem.id);
    itemDiv.setAttribute('data-item-type', 'image');
    itemDiv.setAttribute('draggable', 'true');
    
    const layerIndex = imageItems.indexOf(imageItem) + 1;
    const key = `image-${imageItem.id}`;
    const isCollapsed = collapsedStates[key] || false;  // ← קרא מצב
    const collapseIcon = isCollapsed ? '▶' : '▼';
    const collapsedClass = isCollapsed ? 'collapsed' : '';
    
    itemDiv.innerHTML = `
        <div class="text-item-header">
            <div style="display: flex; align-items: center; gap: 10px;">
                <span class="drag-handle">⋮⋮</span>
                <span class="layer-number">#${layerIndex}</span>
                <span class="text-item-title">🖼️ תמונה #${imageItem.id}</span>
            </div>
            <div style="display: flex; gap: 5px;">
                <button type="button" class="collapse-btn" onclick="toggleCollapse(${imageItem.id}, 'image')">
                    <span class="collapse-icon">${collapseIcon}</span>
                </button>
                <button type="button" class="remove-text-btn" onclick="removeImageItem(${imageItem.id})">🗑️</button>
            </div>
        </div>
        
        <div class="text-item-body ${collapsedClass}" id="image-item-body-${imageItem.id}">
            ${generateImageItemFields(imageItem)}
        </div>
    `;
    
    setupDragAndDrop(itemDiv);
    container.appendChild(itemDiv);
}

function generateImageItemFields(imageItem) {
    return `
        <div class="form-row">
            <div class="form-group">
                <label>רוחב (px)</label>
                <input type="number" value="${imageItem.width}" min="10" max="2000" 
                    onchange="updateImageItem(${imageItem.id}, 'width', parseInt(this.value))">
            </div>
            <div class="form-group">
                <label>גובה (px)</label>
                <input type="number" value="${imageItem.height}" min="10" max="2000" 
                    onchange="updateImageItem(${imageItem.id}, 'height', parseInt(this.value))">
            </div>
        </div>
        
        <div class="form-row">
            <div class="form-group">
                <label>מעלה (px)</label>
                <input type="number" value="${imageItem.top}" min="0" 
                    onchange="updateImageItem(${imageItem.id}, 'top', parseFloat(this.value))">
            </div>
            <div class="form-group">
                <label>משמאל (px)</label>
                <input type="number" value="${imageItem.left}" min="0" 
                    onchange="updateImageItem(${imageItem.id}, 'left', parseFloat(this.value))">
            </div>
        </div>
        
        <div class="form-row">
            <div class="form-group">
                <label>עמוד</label>
                <input type="number" value="${imageItem.page}" min="1" 
                    onchange="updateImageItem(${imageItem.id}, 'page', parseInt(this.value))">
            </div>
            <div class="form-group">
                <label>שקיפות</label>
                <input type="number" value="${imageItem.opacity}" min="0" max="1" step="0.1" 
                    onchange="updateImageItem(${imageItem.id}, 'opacity', parseFloat(this.value))">
            </div>
        </div>
        
        <div class="form-group full-width">
            <img src="${imageItem.base64}" style="max-width: 100%; max-height: 150px; border-radius: 8px; margin-top: 10px;">
        </div>
    `;
}

function updateImageItem(id, field, value) {
    const imageItem = imageItems.find(item => item.id === id);
    if (imageItem) {
        imageItem[field] = value;
        if (pdfDoc) {
            renderPage(currentPageNum);
        }
    }
}

function removeImageItem2(id) {
    imageItems = imageItems.filter(item => item.id !== id);
    const itemDiv = document.getElementById(`image-item-${id}`);
    if (itemDiv) {
        itemDiv.remove();
    }
    if (pdfDoc) {
        renderPage(currentPageNum);
    }
}

function removeImageItem(id) {
    // מחק מ-imageItems
    imageItems = imageItems.filter(item => item.id !== id);
    
    // מחק מ-allItems
    allItems = allItems.filter(item => !(item.id === id && item.type === 'image'));
    
    // מחק את הבחירה אם זה הפריט הנבחר
    if (selectedImageId === id) {
        selectedImageId = null;
    }
    
    // הסר את הטופס
    const itemDiv = document.getElementById(`image-item-${id}`);
    if (itemDiv) {
        itemDiv.remove();
    }
    
    // רנדר מחדש
    if (pdfDoc) {
        renderPage(currentPageNum);
    }
}

function updateImageFieldValues(item) {
    const itemDiv = document.getElementById(`image-item-${item.id}`);  // ← תקן כאן!
    if (itemDiv) {
        const inputs = itemDiv.querySelectorAll('input[type="number"]');
        inputs[0].value = Math.round(item.width);   // width
        inputs[1].value = Math.round(item.height);  // height
        inputs[2].value = Math.round(item.top);     // top
        inputs[3].value = Math.round(item.left);    // left
    }
}

async function drawImagesOnCanvas(viewport) {
    for (const imageItem of imageItems) {
        // בדוק אם התמונה שייכת לעמוד הנוכחי
        const imagePage = parseInt(imageItem.page) || 1;
        if (imagePage !== currentPageNum) {
            continue;
        }
        
        // טען את התמונה
        const img = new Image();
        img.src = imageItem.base64;
        
        // חכה שהתמונה תיטען
        await new Promise((resolve) => {
            if (img.complete) {
                resolve();
            } else {
                img.onload = resolve;
            }
        });
        
        // חשב מיקום ומידות
        const x = parseFloat(imageItem.left) * pdfScale;
        const y = parseFloat(imageItem.top) * pdfScale;
        const width = parseFloat(imageItem.width) * pdfScale;
        const height = parseFloat(imageItem.height) * pdfScale;
        
        // צייר את התמונה
        ctx.globalAlpha = parseFloat(imageItem.opacity) || 1.0;
        ctx.drawImage(img, x, y, width, height);
        ctx.globalAlpha = 1.0;

        // ← הוסף מסגרת אם התמונה נבחרת
        if (selectedImageId === imageItem.id) {
            drawImageSelectionBox(imageItem);
        }
    }
}
// מציאת מיקום תמונה
function findImageAtPosition(x, y) {
    // חפש מהסוף להתחלה (תמונות עליונות קודם)
    for (let i = imageItems.length - 1; i >= 0; i--) {
        const item = imageItems[i];
        const imagePage = parseInt(item.page) || 1;
        if (imagePage !== currentPageNum) continue;
        
        const imgX = parseFloat(item.left) * pdfScale;
        const imgY = parseFloat(item.top) * pdfScale;
        const imgWidth = parseFloat(item.width) * pdfScale;
        const imgHeight = parseFloat(item.height) * pdfScale;
        
        if (x >= imgX && x <= imgX + imgWidth &&
            y >= imgY && y <= imgY + imgHeight) {
            return item;
        }
    }
    return null;
}

// מציאת פינת תמונה
function findImageCornerAtPosition(x, y, imageItem) {
    const imgX = parseFloat(imageItem.left) * pdfScale;
    const imgY = parseFloat(imageItem.top) * pdfScale;
    const imgWidth = parseFloat(imageItem.width) * pdfScale;
    const imgHeight = parseFloat(imageItem.height) * pdfScale;
    
    const hitSize = 12;
    
    // פינה ימנית תחתונה (resize)
    if (Math.abs(x - (imgX + imgWidth)) < hitSize && 
        Math.abs(y - (imgY + imgHeight)) < hitSize) {
        return 'bottom-right';
    }
    
    // פינה שמאלית תחתונה
    if (Math.abs(x - imgX) < hitSize && 
        Math.abs(y - (imgY + imgHeight)) < hitSize) {
        return 'bottom-left';
    }
    
    // פינה ימנית עליונה
    if (Math.abs(x - (imgX + imgWidth)) < hitSize && 
        Math.abs(y - imgY) < hitSize) {
        return 'top-right';
    }
    
    // פינה שמאלית עליונה
    if (Math.abs(x - imgX) < hitSize && 
        Math.abs(y - imgY) < hitSize) {
        return 'top-left';
    }
    
    return null;
}

// ציור על מסגרת לתמונה
function drawImageSelectionBox(imageItem) {
    const imgX = parseFloat(imageItem.left) * pdfScale;
    const imgY = parseFloat(imageItem.top) * pdfScale;
    const imgWidth = parseFloat(imageItem.width) * pdfScale;
    const imgHeight = parseFloat(imageItem.height) * pdfScale;
    
    // מסגרת
    ctx.strokeStyle = '#8b5cf6';
    ctx.lineWidth = 2;
    ctx.setLineDash([5, 5]);
    ctx.strokeRect(imgX, imgY, imgWidth, imgHeight);
    ctx.setLineDash([]);
    
    // פינות
    const cornerSize = 8;
    ctx.fillStyle = '#8b5cf6';
    
    ctx.fillRect(imgX - cornerSize/2, imgY - cornerSize/2, cornerSize, cornerSize);
    ctx.fillRect(imgX + imgWidth - cornerSize/2, imgY - cornerSize/2, cornerSize, cornerSize);
    ctx.fillRect(imgX - cornerSize/2, imgY + imgHeight - cornerSize/2, cornerSize, cornerSize);
    ctx.fillRect(imgX + imgWidth - cornerSize/2, imgY + imgHeight - cornerSize/2, cornerSize, cornerSize);
}

function scheduleRender() {
    if (renderTimeout) {
        clearTimeout(renderTimeout);
    }
    renderTimeout = setTimeout(() => {
        if (!pageRendering) {
            renderPage(currentPageNum);
        }
        renderTimeout = null;
    }, 16);
}

// ===============================
// גרירה על הקנבס
// ===============================

let collapsedStates = {}; // { 'text-1': true, 'image-2': false, ... }

async function handleCanvasMouseDown(e) {
    const rect = canvas.getBoundingClientRect();
    const x = e.clientX - rect.left;
    const y = e.clientY - rect.top;
    
    const scaleX = canvas.width / rect.width;
    const scaleY = canvas.height / rect.height;
    
    const canvasX = x * scaleX;
    const canvasY = y * scaleY;

    // בדוק אם לחצנו על פינה של תמונה נבחרת
    if (selectedImageId !== null) {
        const selectedImage = imageItems.find(img => img.id === selectedImageId);
        if (selectedImage) {
            const corner = findImageCornerAtPosition(canvasX, canvasY, selectedImage);
            
            if (corner) {
                resizingImageCorner = corner;
                resizeStartX = canvasX;
                resizeStartY = canvasY;
                resizeStartSize = {
                    width: parseFloat(selectedImage.width),
                    height: parseFloat(selectedImage.height)
                };
                dragStartLeft = parseFloat(selectedImage.left);  // ← הוסף!
                dragStartTop = parseFloat(selectedImage.top);     // ← הוסף!
                canvas.style.cursor = 'nwse-resize';
                return;
            }
        }
    }
    
    // בדוק אם לחצנו על פינה של טקסט נבחר
    if (selectedTextId !== null) {
        const selectedItem = textItems.find(t => t.id === selectedTextId);
        if (selectedItem) {
            const page = await pdfDoc.getPage(currentPageNum);
            const viewport = page.getViewport({ scale: pdfScale });
            const corner = findCornerAtPosition(canvasX, canvasY, selectedItem, viewport);
            
            if (corner) {
                resizingCorner = corner;
                resizeStartSize = parseInt(selectedItem.size);
                resizeStartX = canvasX;
                resizeStartY = canvasY;
                canvas.style.cursor = 'nwse-resize';
                return;
            }
        }
    }

    // בדוק אם לחצנו על תמונה
    const clickedImage = findImageAtPosition(canvasX, canvasY);
    if (clickedImage) {
        selectedImageId = clickedImage.id;
        selectedTextId = null;
        draggingImageId = clickedImage.id;
        dragStartX = canvasX;
        dragStartY = canvasY;
        dragStartTop = clickedImage.top;
        dragStartLeft = clickedImage.left;  // ← וודא שזה קיים!
        canvas.style.cursor = 'grabbing';
        renderPage(currentPageNum);
        return;
    }
    
    // בדוק אם לחצנו על טקסט
    const clickedText = findTextAtPosition(canvasX, canvasY);
    if (clickedText) {
        selectedTextId = clickedText.id;
        selectedImageId = null;  // בטל בחירת תמונה
        draggingTextId = clickedText.id;
        dragStartX = canvasX;
        dragStartY = canvasY;
        dragStartTop = clickedText.top;
        dragStartRight = clickedText.right;
        canvas.style.cursor = 'grabbing';
        renderPage(currentPageNum);
    } else {
        selectedTextId = null;
        selectedImageId = null;
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
    
    // אם בresize תמונה
    if (resizingImageCorner !== null) {
        const item = imageItems.find(img => img.id === selectedImageId);
        if (item) {
            const deltaX = (canvasX - resizeStartX) / pdfScale;
            const deltaY = (canvasY - resizeStartY) / pdfScale;
            
            const oldWidth = resizeStartSize.width;
            const oldHeight = resizeStartSize.height;
            
            if (resizingImageCorner === 'bottom-right') {
                item.width = Math.max(20, oldWidth + deltaX);
                item.height = Math.max(20, oldHeight + deltaY);
            } else if (resizingImageCorner === 'bottom-left') {
                const newWidth = Math.max(20, oldWidth - deltaX);
                item.width = newWidth;
                item.height = Math.max(20, oldHeight + deltaY);
                item.left = dragStartLeft + (oldWidth - newWidth);
            } else if (resizingImageCorner === 'top-right') {
                const newHeight = Math.max(20, oldHeight - deltaY);
                item.width = Math.max(20, oldWidth + deltaX);
                item.height = newHeight;
                item.top = dragStartTop + (oldHeight - newHeight);
            } else if (resizingImageCorner === 'top-left') {
                const newWidth = Math.max(20, oldWidth - deltaX);
                const newHeight = Math.max(20, oldHeight - deltaY);
                item.width = newWidth;
                item.height = newHeight;
                item.left = dragStartLeft + (oldWidth - newWidth);
                item.top = dragStartTop + (oldHeight - newHeight);
            }
            
            updateImageFieldValues(item);
            scheduleRender();  // ← שינוי כאן!
        }
        return;
    }
    
    // אם בגרירת תמונה
    if (draggingImageId !== null) {
        const deltaX = (canvasX - dragStartX) / pdfScale;
        const deltaY = (canvasY - dragStartY) / pdfScale;
        
        const item = imageItems.find(img => img.id === draggingImageId);
        if (item) {
            item.left = Math.round(dragStartLeft + deltaX);
            item.top = Math.round(dragStartTop + deltaY);
            
            updateImageFieldValues(item);
            scheduleRender();  // ← שינוי כאן!
        }
        return;
    }
    
    // אם בresize טקסט
    if (resizingCorner !== null) {
        const item = textItems.find(t => t.id === selectedTextId);
        if (item) {
            const deltaX = canvasX - resizeStartX;
            const deltaY = canvasY - resizeStartY;
            const delta = Math.sqrt(deltaX * deltaX + deltaY * deltaY);
            
            let sign = 1;
            if (resizingCorner === 'top-left' || resizingCorner === 'bottom-left') {
                sign = deltaX < 0 ? 1 : -1;
            } else {
                sign = deltaX > 0 ? 1 : -1;
            }
            
            const newSize = Math.max(12, Math.round(resizeStartSize + (delta * sign / pdfScale / 3)));
            item.size = newSize;
            
            updateFieldValues(item);
            scheduleRender();  // ← שינוי כאן!
        }
        return;
    }
    
    // אם בגרירת טקסט
    if (draggingTextId !== null) {
        const deltaX = canvasX - dragStartX;
        const deltaY = canvasY - dragStartY;
        
        const item = textItems.find(t => t.id === draggingTextId);
        if (item) {
            item.top = Math.round(dragStartTop + (deltaY / pdfScale));
            item.right = Math.round(dragStartRight - (deltaX / pdfScale));
            
            updateFieldValues(item);
            scheduleRender();  // ← שינוי כאן!
        }
        return;
    }
    
    // שנה cursor כשעוברים על פינות תמונה
    if (selectedImageId !== null) {
        const selectedImage = imageItems.find(img => img.id === selectedImageId);
        if (selectedImage && (parseInt(selectedImage.page) || 1) === currentPageNum) {
            const corner = findImageCornerAtPosition(canvasX, canvasY, selectedImage);
            
            if (corner) {
                canvas.style.cursor = 'nwse-resize';
                return;
            }
        }
    }
    
    // שנה cursor כשעוברים על פינות טקסט
    if (selectedTextId !== null) {
        const selectedItem = textItems.find(t => t.id === selectedTextId);
        if (selectedItem && (parseInt(selectedItem.page) || 1) === currentPageNum) {
            const page = pdfDoc.getPage(currentPageNum);
            page.then(p => {
                const viewport = p.getViewport({ scale: pdfScale });
                const corner = findCornerAtPosition(canvasX, canvasY, selectedItem, viewport);
                
                if (corner) {
                    if (corner === 'top-right' || corner === 'bottom-left') {
                        canvas.style.cursor = 'nesw-resize';
                    } else {
                        canvas.style.cursor = 'nwse-resize';
                    }
                }
            });
            return;
        }
    }
    
    canvas.style.cursor = 'grab';
}

function handleCanvasMouseUp() {
    draggingTextId = null;
    draggingImageId = null;
    resizingCorner = null;
    resizingImageCorner = null;
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
        type: 'text',  // ← הוסף
        text: 'ניסיון',
        font: 'david',
        size: 48,
        color: '#808080',
        top: 300,
        right: 200,
        page: 1,
        align: 'right'
    };
    
    textItems.push(textItem);
    allItems.push(textItem);  // ← הוסף גם כאן
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
    itemDiv.setAttribute('data-item-id', item.id);
    itemDiv.setAttribute('data-item-type', 'text');
    itemDiv.setAttribute('draggable', 'true');
    
    const fontOptions = availableFonts.map(font => 
        `<option value="${font.id}" ${item.font === font.id ? 'selected' : ''}>${font.name}</option>`
    ).join('');
    
    const layerIndex = textItems.indexOf(item) + 1;
    const key = `text-${item.id}`;
    const isCollapsed = collapsedStates[key] || false;  // ← קרא מצב
    const collapseIcon = isCollapsed ? '▶' : '▼';
    const collapsedClass = isCollapsed ? 'collapsed' : '';
    
    itemDiv.innerHTML = `
        <div class="text-item-header">
            <div style="display: flex; align-items: center; gap: 10px;">
                <span class="drag-handle">⋮⋮</span>
                <span class="layer-number">#${layerIndex}</span>
                <span class="text-item-title">📝 טקסט #${item.id}</span>
            </div>
            <div style="display: flex; gap: 5px;">
                <button type="button" class="collapse-btn" onclick="toggleCollapse(${item.id}, 'text')">
                    <span class="collapse-icon">${collapseIcon}</span>
                </button>
                <button type="button" class="remove-text-btn" onclick="removeTextItem(${item.id})">🗑️</button>
            </div>
        </div>
        
        <div class="text-item-body ${collapsedClass}" id="text-item-body-${item.id}">
            ${generateTextItemFields(item, fontOptions)}
        </div>
    `;
    
    setupDragAndDrop(itemDiv);
    textsList.appendChild(itemDiv);
}

function generateTextItemFields(item, fontOptions) {
    return `
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
                <label>מרחק מלמעלה (px):</label>
                <input type="number" value="${item.top}" min="0" oninput="updateTextItem(${item.id}, 'top', this.value)">
            </div>
        </div>
        
        <div class="form-row">
            <div class="form-group">
                <label>מרחק מימין (px):</label>
                <input type="number" value="${item.right}" min="0" oninput="updateTextItem(${item.id}, 'right', this.value)">
            </div>

            <div class="form-group">
                <label>עמוד:</label>
                <input type="number" value="${item.page || 1}" min="1" max="99" oninput="updateTextItem(${item.id}, 'page', this.value)">
            </div>
        </div>
        
        <div class="form-row">
            <div class="form-group">
                <label>יישור:</label>
                <select onchange="updateTextItem(${item.id}, 'align', this.value)">
                    <option value="right" ${(item.align || 'right') === 'right' ? 'selected' : ''}>ימין</option>
                    <option value="left" ${item.align === 'left' ? 'selected' : ''}>שמאל</option>
                </select>
            </div>
        </div>
    `;
}

function toggleCollapse(id, type) {
    const key = `${type}-${id}`;
    const bodyId = type === 'text' ? `text-item-body-${id}` : `image-item-body-${id}`;
    const body = document.getElementById(bodyId);
    const icon = body.parentElement.querySelector('.collapse-icon');
    
    if (body.classList.contains('collapsed')) {
        body.classList.remove('collapsed');
        body.style.maxHeight = body.scrollHeight + 'px';
        icon.textContent = '▼';
        collapsedStates[key] = false;  // ← שמור מצב
    } else {
        body.style.maxHeight = body.scrollHeight + 'px';
        setTimeout(() => {
            body.classList.add('collapsed');
            icon.textContent = '▶';
            collapsedStates[key] = true;  // ← שמור מצב
        }, 10);
    }
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

function removeTextItem2(id) {
    const index = textItems.findIndex(t => t.id === id);
    if (index > -1) {
        textItems.splice(index, 1);
        document.getElementById(`text-item-${id}`).remove();
        if (pdfDoc) {
            renderPage(currentPageNum);
        }
    }
}

function removeTextItem(id) {
    // מחק מ-textItems
    const textIndex = textItems.findIndex(t => t.id === id);
    if (textIndex > -1) {
        textItems.splice(textIndex, 1);
    }
    
    // מחק מ-allItems
    const allIndex = allItems.findIndex(item => item.id === id && item.type === 'text');
    if (allIndex > -1) {
        allItems.splice(allIndex, 1);
    }
    
    // מחק את הבחירה אם זה הפריט הנבחר
    if (selectedTextId === id) {
        selectedTextId = null;
    }
    
    // הסר את הטופס
    const itemDiv = document.getElementById(`text-item-${id}`);
    if (itemDiv) {
        itemDiv.remove();
    }
    
    // רנדר מחדש
    if (pdfDoc) {
        renderPage(currentPageNum);
    }
}

// ===============================
// לוגיקת Drag & Drop עבור תפריט
// ===============================

let draggedElement = null;
let draggedItemId = null;
let draggedItemType = null;

function setupDragAndDrop(element) {
    element.addEventListener('dragstart', handleDragStart);
    element.addEventListener('dragend', handleDragEnd);
    element.addEventListener('dragover', handleDragOver);
    element.addEventListener('drop', handleDrop);
    element.addEventListener('dragleave', handleDragLeave);
}

function handleDragStart(e) {
    draggedElement = e.currentTarget;
    draggedItemId = parseInt(draggedElement.getAttribute('data-item-id'));
    draggedItemType = draggedElement.getAttribute('data-item-type');
    
    draggedElement.classList.add('dragging');
    e.dataTransfer.effectAllowed = 'move';
    e.dataTransfer.setData('text/html', draggedElement.innerHTML);
}

function handleDragEnd(e) {
    draggedElement.classList.remove('dragging');
    
    // הסר את כל ה-drag-over classes
    document.querySelectorAll('.text-item').forEach(item => {
        item.classList.remove('drag-over');
    });
    
    draggedElement = null;
    draggedItemId = null;
    draggedItemType = null;
}

function handleDragOver(e) {
    if (e.preventDefault) {
        e.preventDefault();
    }
    
    e.dataTransfer.dropEffect = 'move';
    
    const targetElement = e.currentTarget;
    if (targetElement !== draggedElement) {
        targetElement.classList.add('drag-over');
    }
    
    return false;
}

function handleDragLeave(e) {
    e.currentTarget.classList.remove('drag-over');
}

function handleDrop(e) {
    if (e.stopPropagation) {
        e.stopPropagation();
    }
    
    e.preventDefault();
    
    const targetElement = e.currentTarget;
    const targetItemId = parseInt(targetElement.getAttribute('data-item-id'));
    const targetItemType = targetElement.getAttribute('data-item-type');
    
    if (draggedElement !== targetElement) {
        reorderAllItems(draggedItemId, draggedItemType, targetItemId, targetItemType);
    }
    
    targetElement.classList.remove('drag-over');
    
    return false;
}

function reorderAllItems(draggedId, draggedType, targetId, targetType) {
    // מצא indices ב-allItems
    const draggedIndex = allItems.findIndex(item => 
        item.id === draggedId && item.type === draggedType
    );
    const targetIndex = allItems.findIndex(item => 
        item.id === targetId && item.type === targetType
    );
    
    if (draggedIndex === -1 || targetIndex === -1) return;
    
    // הוצא והכנס
    const [draggedItem] = allItems.splice(draggedIndex, 1);
    allItems.splice(targetIndex, 0, draggedItem);
    
    // עדכן גם את המערכים הנפרדים (לתאימות לאחור)
    syncSeparateArrays();
    
    refreshItemsList();
    
    if (pdfDoc) {
        scheduleRender();
    }
}

function syncSeparateArrays() {
    textItems = allItems.filter(item => item.type === 'text');
    imageItems = allItems.filter(item => item.type === 'image');
}

function refreshItemsList() {
    const textsList = document.getElementById('textsList');
    textsList.innerHTML = '';
    
    // עבור על allItems לפי הסדר
    allItems.forEach(item => {
        if (item.type === 'text') {
            renderTextItem(item);
        } else if (item.type === 'image') {
            renderImageItem(item);
        }
    });
}

// ===============================
// PDF Processing
// ===============================

processBtn.addEventListener('click', async () => {
    if (!selectedFile) return;

    const formData = new FormData();
    formData.append('pdf', selectedFile);
    formData.append('texts', JSON.stringify(textItems));
    formData.append('images', JSON.stringify(imageItems));  // ← הוסף תמונות
    formData.append('allItems', JSON.stringify(allItems));  // ← הוסף את הסדר המלא

    // Debug
    console.log('INDEX - Sending texts:', textItems);
    console.log('INDEX - Sending images:', imageItems);
    console.log('INDEX - Sending allItems:', allItems);
    console.log('INDEX - JSON:', JSON.stringify(allItems, null, 2));

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
    if (pageRendering) {
        pageNumPending = num;
        return;
    }
    
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
        
        // רנדר לפי allItems (סדר השכבות)
        for (const item of allItems) {
            const itemPage = parseInt(item.page) || 1;
            if (itemPage !== currentPageNum) continue;
            
            if (item.type === 'image') {
                await drawSingleImage(item, viewport);
            } else if (item.type === 'text') {
                drawSingleText(item, viewport);
            }
        }
        
        pageRendering = false;
        
        if (pageNumPending !== null) {
            const pending = pageNumPending;
            pageNumPending = null;
            renderPage(pending);
        }
        
    } catch (error) {
        console.error('Error rendering page:', error);
        pageRendering = false;
    }
}

async function drawSingleImage(imageItem, viewport) {
    const img = new Image();
    img.src = imageItem.base64;
    
    await new Promise((resolve) => {
        if (img.complete) {
            resolve();
        } else {
            img.onload = resolve;
        }
    });
    
    const x = parseFloat(imageItem.left) * pdfScale;
    const y = parseFloat(imageItem.top) * pdfScale;
    const width = parseFloat(imageItem.width) * pdfScale;
    const height = parseFloat(imageItem.height) * pdfScale;
    
    ctx.globalAlpha = parseFloat(imageItem.opacity) || 1.0;
    ctx.drawImage(img, x, y, width, height);
    ctx.globalAlpha = 1.0;
    
    if (selectedImageId === imageItem.id) {
        drawImageSelectionBox(imageItem);
    }
}

function drawSingleText(item, viewport) {
    const fontSize = Math.round(parseInt(item.size) * viewport.scale);
    const color = item.color;
    const topOffset = Math.round(parseFloat(item.top) * viewport.scale);
    const rightOffset = Math.round(parseFloat(item.right) * viewport.scale);
    const align = item.align || 'right';
    
    const fontData = availableFonts.find(f => f.id === item.font);
    const fontName = fontData ? fontData.id : 'Arial';
    
    let x;
    if (align === 'right') {
        x = viewport.width - rightOffset;
    } else {
        x = rightOffset;
    }
    const y = topOffset;
    
    ctx.font = `${fontSize}px "${fontName}", sans-serif`;
    ctx.fillStyle = color;
    ctx.globalAlpha = 0.7;
    ctx.textAlign = align;
    
    ctx.fillText(item.text, x, y);
    
    ctx.globalAlpha = 1.0;
    ctx.textAlign = 'left';
    
    if (selectedTextId === item.id) {
        drawSelectionBox(item, viewport);
    }
}

// פונקציה נפרדת לרנדור רק תמונות וטקסטים (ללא PDF)
async function redrawOverlays() {
    if (!pdfDoc) return;
    
    try {
        const page = await pdfDoc.getPage(currentPageNum);
        const viewport = page.getViewport({ scale: pdfScale });
        
        // נקה רק את החלק של התמונות והטקסטים
        // אבל זה יוריד גם את ה-PDF, אז צריך לרנדר הכל מחדש
        
        await renderPage(currentPageNum);
        
    } catch (error) {
        console.error('Error redrawing overlays:', error);
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
    const templateData2 = {
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
            align: item.align || 'right',
        }))
    };

    const templateData = {
        name: templateName,
        description: templateDescription,
        original_filename: selectedFile.name,
        pdf_dimensions: {
            width: parseFloat(document.getElementById('pageWidth').textContent),
            height: parseFloat(document.getElementById('pageHeight').textContent)
        },
        page_count: parseInt(document.getElementById('pageCount').textContent),
        allItems: allItems,
        texts: textItems,
        images: imageItems,
        fields: allItems
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