// ===============================
// Page Load
// ===============================

document.addEventListener('DOMContentLoaded', () => {
    loadFonts();
    loadTemplates();
});

// ===============================
// Global Variables
// ===============================


let allTemplates = [];
let currentTestTemplate = null;


// ===============================
// API Documentation Toggle
// ===============================

function toggleApiDocs() {
    const content = document.getElementById('apiDocsContent');
    const icon = document.getElementById('apiToggleIcon');
    
    content.classList.toggle('show');
    icon.classList.toggle('rotated');
}

// ===============================
// Load Templates
// ===============================

async function loadTemplates() {
    const grid = document.getElementById('templatesGrid');
    
    try {
        const response = await fetch('get_templates.php');
        const data = await response.json();
        
        if (data.success && data.templates.length > 0) {
            allTemplates = data.templates;
            renderTemplates(data.templates);
        } else {
            grid.innerHTML = `
                <div class="no-templates">
                    <h3>📭 אין תבניות שמורות</h3>
                    <p>התחל ב<a href="index.html">יצירת תבנית חדשה</a></p>
                </div>
            `;
        }
    } catch (error) {
        console.error('Error loading templates:', error);
        grid.innerHTML = `
            <div class="no-templates">
                <h3>❌ שגיאה בטעינת תבניות</h3>
                <p>נסה לרענן את הדף</p>
            </div>
        `;
    }
}

function renderTemplates(templates) {
    const grid = document.getElementById('templatesGrid');
    
    grid.innerHTML = templates.map(template => `
        <div class="template-card">
            <div class="template-card-header">
                <div class="template-name">${escapeHtml(template.name)}</div>
                <div class="template-id">${template.template_id}</div>
            </div>
            
            <div class="template-description">
                ${template.description ? escapeHtml(template.description) : '<em>אין תיאור</em>'}
            </div>
            
            <div class="template-meta">
                <div class="template-meta-item">
                    📄 ${template.page_count} דף${template.page_count > 1 ? 'ים' : ''}
                </div>
                <div class="template-meta-item">
                    📝 ${template.field_count} שדות
                </div>
            </div>
            
            <div class="template-meta">
                <div class="template-meta-item">
                    📅 ${formatDate(template.created_at)}
                </div>
            </div>
            
            <div class="template-actions">
                <button class="template-btn template-btn-test" onclick="openTestModal('${template.template_id}')">
                    🧪 בדיקה
                </button>
                <button class="template-btn template-btn-view" onclick="viewTemplate('${template.template_id}')">
                    👁️ צפייה
                </button>
                <button class="template-btn template-btn-delete" onclick="deleteTemplate('${template.template_id}')">
                    🗑️
                </button>
            </div>
        </div>
    `).join('');
}

// ===============================
// Test Template Modal
// ===============================

async function openTestModal2(templateId) {
    try {
        const response = await fetch(`get_templates.php?id=${templateId}`);
        const data = await response.json();
        
        if (!data.success) {
            alert('שגיאה בטעינת התבנית');
            return;
        }
        
        currentTestTemplate = data.template;
        
        document.getElementById('testTemplateName').textContent = data.template.template_name || 'תבנית ללא שם';
        
        const fieldsContainer = document.getElementById('testFieldsContainer');
        fieldsContainer.innerHTML = data.template.fields.map(field => `
            <div class="test-field">
                <label>
                    <span class="field-label-text">${escapeHtml(field.label)}</span>
                    <span class="field-id">${field.id}</span>
                </label>
                <input 
                    type="text" 
                    id="test_${field.id}" 
                    value="${escapeHtml(field.text)}"
                    placeholder="הזן ערך עבור ${escapeHtml(field.label)}"
                >
            </div>
        `).join('');
        
        document.getElementById('testTemplateModal').classList.add('show');
        
    } catch (error) {
        console.error('Error opening test modal:', error);
        alert('שגיאה בטעינת התבנית');
    }
}

async function openTestModal3(templateId) {
    try {
        const response = await fetch(`get_templates.php?id=${templateId}`);
        const data = await response.json();
        
        console.log('API Response:', data); // ← לדיבוג
        
        if (!data.success) {
            alert('שגיאה בטעינת התבנית: ' + (data.error || 'לא ידוע'));
            return;
        }
        
        // בדוק אם template קיים
        if (!data.template) {
            alert('שגיאה: התבנית לא הוחזרה מהשרת');
            console.error('Data received:', data);
            return;
        }
        
        currentTestTemplate = data.template;
        
        // בדוק אם יש fields
        if (!currentTestTemplate.fields || currentTestTemplate.fields.length === 0) {
            alert('התבנית לא מכילה שדות');
            return;
        }
        
        document.getElementById('testTemplateName').textContent = currentTestTemplate.template_name || 'תבנית';
        
        const fieldsContainer = document.getElementById('testFieldsContainer');
        fieldsContainer.innerHTML = currentTestTemplate.fields.map(field => {
            // מצא את הפונט
            const fontData = availableFonts.find(f => f.id === field.font);
            const fontFamily = fontData ? fontData.id : 'Arial';
            
            return `
                <div class="test-field">
                    <label>
                        <span class="field-label-text">${escapeHtml(field.label)}</span>
                        <span class="field-id">${field.id}</span>
                    </label>
                    <input 
                        type="text" 
                        id="test_${field.id}" 
                        value="${escapeHtml(field.text)}"
                        placeholder="הזן ערך עבור ${escapeHtml(field.label)}"
                        style="font-family: '${fontFamily}', Arial, sans-serif; direction: rtl; text-align: right;"
                    >
                </div>
            `;
        }).join('');

        fieldsContainer.innerHTML = currentTestTemplate.fields.map(field => `
            <div class="test-field">
                <label>
                    <span class="field-label-text">${escapeHtml(field.label)}</span>
                    <span class="field-id">${field.id}</span>
                </label>
                <input 
                    type="text" 
                    id="test_${field.id}" 
                    value="${field.text}"
                    placeholder="הזן ערך עבור ${field.label}"
                >
            </div>
        `).join('');
        
        document.getElementById('testTemplateModal').classList.add('show');
        
    } catch (error) {
        console.error('Error opening test modal:', error);
        alert('שגיאה בטעינת התבנית');
    }
}

async function openTestModal(templateId) {
    try {
        const response = await fetch(`get_templates.php?id=${templateId}`);
        const data = await response.json();
        
        console.log('API Response:', data);
        
        if (!data.success) {
            alert('שגיאה בטעינת התבנית: ' + (data.error || 'לא ידוע'));
            return;
        }
        
        if (!data.template) {
            alert('שגיאה: התבנית לא הוחזרה מהשרת');
            console.error('Data received:', data);
            return;
        }
        
        currentTestTemplate = data.template;
        
        if (!currentTestTemplate.fields || currentTestTemplate.fields.length === 0) {
            alert('התבנית לא מכילה שדות');
            return;
        }
        
        document.getElementById('testTemplateName').textContent = currentTestTemplate.template_name || 'תבנית';
        
        const fieldsContainer = document.getElementById('testFieldsContainer');
        fieldsContainer.innerHTML = ''; // נקה
        
        // צור כל שדה ב-JavaScript (לא HTML string!)
        currentTestTemplate.fields.forEach(field => {
            const fieldDiv = document.createElement('div');
            fieldDiv.className = 'test-field';
            
            const label = document.createElement('label');
            
            const labelText = document.createElement('span');
            labelText.className = 'field-label-text';
            labelText.textContent = field.label;
            
            const fieldId = document.createElement('span');
            fieldId.className = 'field-id';
            fieldId.textContent = field.id;
            
            label.appendChild(labelText);
            label.appendChild(fieldId);
            
            const input = document.createElement('input');
            input.type = 'text';
            input.id = `test_${field.id}`;
            input.value = field.text;  // ← ישירות ב-JavaScript!
            input.placeholder = `הזן ערך עבור ${field.label}`;
            input.style.direction = 'rtl';
            input.style.textAlign = 'right';
            
            fieldDiv.appendChild(label);
            fieldDiv.appendChild(input);
            fieldsContainer.appendChild(fieldDiv);
        });
        
        document.getElementById('testTemplateModal').classList.add('show');
        
    } catch (error) {
        console.error('Error opening test modal:', error);
        alert('שגיאה בטעינת התבנית');
    }
}

document.getElementById('cancelTestBtn').addEventListener('click', () => {
    document.getElementById('testTemplateModal').classList.remove('show');
    currentTestTemplate = null;
});

document.getElementById('generateTestBtn').addEventListener('click', async () => {
    if (!currentTestTemplate) return;
    
    const generateBtn = document.getElementById('generateTestBtn');
    
    // אסוף את כל הערכים
    const data = {};
    currentTestTemplate.fields.forEach(field => {
        const input = document.getElementById(`test_${field.id}`);
        if (input) {
            data[field.id] = input.value;
        }
    });
    
    // שלח ל-API
    generateBtn.disabled = true;
    generateBtn.textContent = 'יוצר PDF...';
    
    try {
        const response = await fetch('generate_pdf.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                template_id: currentTestTemplate.template_id,
                data: data
            })
        });
        
        const result = await response.json();
        
        if (result.success) {
            // הורד את הקובץ
            window.location.href = result.pdf_url;
            
            // סגור את המודל
            setTimeout(() => {
                document.getElementById('testTemplateModal').classList.remove('show');
                currentTestTemplate = null;
            }, 1000);
            
        } else {
            alert('שגיאה: ' + result.error);
        }
        
    } catch (error) {
        console.error('Error generating PDF:', error);
        alert('שגיאה בתקשורת עם השרת');
    } finally {
        generateBtn.disabled = false;
        generateBtn.textContent = '🚀 צור PDF';
    }
});

// ===============================
// View Template
// ===============================

async function viewTemplate(templateId) {
    try {
        const response = await fetch(`get_templates.php?id=${templateId}`);
        const data = await response.json();
        
        if (!data.success) {
            alert('שגיאה בטעינת התבנית');
            return;
        }
        
        const template = data.template;
        
        const content = `
            <div class="template-detail-section">
                <h3>📋 פרטי תבנית</h3>
                <div class="detail-grid">
                    <div class="detail-item">
                        <span class="detail-label">שם תבנית:</span>
                        <span class="detail-value">${escapeHtml(template.template_name || 'ללא שם')}</span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">מזהה:</span>
                        <span class="detail-value">${template.template_id}</span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">מספר דפים:</span>
                        <span class="detail-value">${template.page_count}</span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">תאריך יצירה:</span>
                        <span class="detail-value">${formatDate(template.created_at)}</span>
                    </div>
                    <div class="detail-item" style="grid-column: 1 / -1;">
                        <span class="detail-label">תיאור:</span>
                        <span class="detail-value">${template.description || 'אין תיאור'}</span>
                    </div>
                </div>
            </div>
            
            <div class="template-detail-section">
                <h3>📐 מידות דף</h3>
                <div class="detail-grid">
                    <div class="detail-item">
                        <span class="detail-label">רוחב:</span>
                        <span class="detail-value">${template.pdf_dimensions.width} נקודות</span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">גובה:</span>
                        <span class="detail-value">${template.pdf_dimensions.height} נקודות</span>
                    </div>
                </div>
            </div>
            
            <div class="template-detail-section">
                <h3>📝 שדות (${template.fields.length})</h3>
                <div class="fields-list">
                    ${template.fields.map(field => `
                        <div class="field-card">
                            <div class="field-card-header">
                                <span class="field-name">${escapeHtml(field.label)}</span>
                                <span class="field-id-badge">${field.id}</span>
                            </div>
                            <div class="field-properties">
                                <div class="field-prop"><strong>טקסט ברירת מחדל:</strong> ${escapeHtml(field.text)}</div>
                                <div class="field-prop"><strong>פונט:</strong> ${field.font}</div>
                                <div class="field-prop"><strong>גודל:</strong> ${field.size}px</div>
                                <div class="field-prop"><strong>צבע:</strong> ${field.color}</div>
                                <div class="field-prop"><strong>מלמעלה:</strong> ${field.top}px</div>
                                <div class="field-prop"><strong>מימין:</strong> ${field.right}px</div>
                            </div>
                        </div>
                    `).join('')}
                </div>
            </div>
        `;
        
        document.getElementById('viewTemplateContent').innerHTML = content;
        document.getElementById('viewTemplateModal').classList.add('show');
        
    } catch (error) {
        console.error('Error viewing template:', error);
        alert('שגיאה בטעינת התבנית');
    }
}

function closeViewModal() {
    document.getElementById('viewTemplateModal').classList.remove('show');
}

// Click outside modal to close
document.getElementById('viewTemplateModal').addEventListener('click', (e) => {
    if (e.target.id === 'viewTemplateModal') {
        closeViewModal();
    }
});

document.getElementById('testTemplateModal').addEventListener('click', (e) => {
    if (e.target.id === 'testTemplateModal') {
        document.getElementById('testTemplateModal').classList.remove('show');
    }
});

// ===============================
// Delete Template
// ===============================

async function deleteTemplate(templateId) {
    const template = allTemplates.find(t => t.template_id === templateId);
    if (!template) return;
    
    if (!confirm(`האם אתה בטוח שברצונך למחוק את התבנית "${template.name}"?\n\nפעולה זו בלתי הפיכה!`)) {
        return;
    }
    
    try {
        const response = await fetch('delete_template.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                template_id: templateId
            })
        });
        
        const result = await response.json();
        
        if (result.success) {
            alert('✅ התבנית נמחקה בהצלחה');
            loadTemplates(); // טען מחדש את הרשימה
        } else {
            alert('שגיאה במחיקה: ' + result.error);
        }
        
    } catch (error) {
        console.error('Error deleting template:', error);
        alert('שגיאה בתקשורת עם השרת');
    }
}

// ===============================
// Utility Functions
// ===============================

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function formatDate(dateString) {
    const date = new Date(dateString);
    return date.toLocaleDateString('he-IL', {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
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
        
    } catch (error) {
        console.error('Error loading fonts:', error);
    }
}

// טען פונטים בטעינת הדף
document.addEventListener('DOMContentLoaded', () => {
    loadFonts();
    loadTemplates();
});