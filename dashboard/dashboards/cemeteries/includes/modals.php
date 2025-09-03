<?php
// dashboard/dashboards/cemeteries/includes/modals.php
// חלונות קופצים לפעולות CRUD
?>

<!-- מודל להוספה/עריכה -->
<div class="modal" id="itemModal">
    <div class="modal-overlay" onclick="closeModal()"></div>
    <div class="modal-content">
        <div class="modal-header">
            <h2 id="modalTitle">הוסף פריט</h2>
            <button class="modal-close" onclick="closeModal()">×</button>
        </div>
        
        <form id="itemForm" onsubmit="saveItem(event)">
            <div class="modal-body">
                <input type="hidden" id="itemId" name="id">
                <input type="hidden" id="itemType" name="type">
                <input type="hidden" id="parentId" name="parent_id">
                
                <!-- שדות דינמיים לפי סוג -->
                <div class="form-group">
                    <label class="form-label">שם <span class="required">*</span></label>
                    <input type="text" class="form-control" id="itemName" name="name" required>
                </div>
                
                <div class="form-group" id="codeGroup">
                    <label class="form-label">קוד</label>
                    <input type="text" class="form-control" id="itemCode" name="code">
                </div>
                
                <!-- שדות ספציפיים לקברים -->
                <div class="form-group grave-only" style="display:none">
                    <label class="form-label">מספר קבר <span class="required">*</span></label>
                    <input type="text" class="form-control" id="graveNumber" name="grave_number">
                </div>
                
                <div class="form-group grave-only" style="display:none">
                    <label class="form-label">סוג חלקה</label>
                    <select class="form-control" id="plotType" name="plot_type">
                        <option value="">בחר סוג</option>
                        <?php foreach (PLOT_TYPES as $key => $type): ?>
                            <option value="<?php echo $key; ?>">
                                <?php echo $type['icon'] . ' ' . $type['name']; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group grave-only" style="display:none">
                    <label class="form-label">סטטוס קבר</label>
                    <select class="form-control" id="graveStatus" name="grave_status">
                        <?php foreach (GRAVE_STATUS as $key => $status): ?>
                            <option value="<?php echo $key; ?>">
                                <?php echo $status['name']; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <!-- שדות ספציפיים לאחוזת קבר -->
                <div class="form-group area-grave-only" style="display:none">
                    <label class="form-label">סוג קבר</label>
                    <select class="form-control" id="graveType" name="grave_type">
                        <option value="">בחר סוג</option>
                        <option value="1">פטור</option>
                        <option value="2">חריג</option>
                        <option value="3">סגור</option>
                    </select>
                </div>
                
                <!-- שדות משותפים -->
                <div class="form-group">
                    <label class="form-label">מיקום</label>
                    <input type="text" class="form-control" id="itemLocation" name="location">
                </div>
                
                <div class="form-group">
                    <label class="form-label">קואורדינטות</label>
                    <input type="text" class="form-control" id="itemCoordinates" name="coordinates" 
                           placeholder="31.7683, 35.2137">
                </div>
                
                <div class="form-group">
                    <label class="form-label">הערות</label>
                    <textarea class="form-control" id="itemComments" name="comments" rows="3"></textarea>
                </div>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal()">ביטול</button>
                <button type="submit" class="btn btn-primary">
                    <span id="saveButtonText">שמור</span>
                </button>
            </div>
        </form>
    </div>
</div>

<!-- מודל אישור מחיקה -->
<div class="modal" id="deleteModal">
    <div class="modal-overlay" onclick="closeDeleteModal()"></div>
    <div class="modal-content modal-sm">
        <div class="modal-header">
            <h2>אישור מחיקה</h2>
        </div>
        <div class="modal-body">
            <p id="deleteMessage">האם אתה בטוח שברצונך למחוק פריט זה?</p>
            <div class="alert alert-warning" id="deleteWarning" style="display:none">
                <strong>אזהרה:</strong> לפריט זה יש פריטים משויכים. המחיקה תכשל.
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeDeleteModal()">ביטול</button>
            <button class="btn btn-danger" id="confirmDeleteBtn" onclick="confirmDelete()">
                מחק
            </button>
        </div>
    </div>
</div>

<!-- מודל הודעות -->
<div id="toastContainer"></div>

<style>
/* Modal Styles */
.modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    z-index: 9999;
}

.modal.show {
    display: flex;
    align-items: center;
    justify-content: center;
}

.modal-overlay {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.5);
    backdrop-filter: blur(5px);
}

.modal-content {
    position: relative;
    background: white;
    border-radius: var(--radius-xl);
    width: 90%;
    max-width: 600px;
    max-height: 90vh;
    overflow: hidden;
    box-shadow: var(--shadow-xl);
    animation: modalSlideIn 0.3s ease;
}

.modal-sm {
    max-width: 400px;
}

@keyframes modalSlideIn {
    from {
        opacity: 0;
        transform: translateY(-20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.modal-header {
    padding: 1.5rem;
    border-bottom: 1px solid var(--border-color);
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.modal-header h2 {
    margin: 0;
    font-size: 1.25rem;
    font-weight: 600;
}

.modal-close {
    background: none;
    border: none;
    font-size: 1.5rem;
    cursor: pointer;
    color: var(--text-muted);
    transition: var(--transition);
    width: 32px;
    height: 32px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: var(--radius-md);
}

.modal-close:hover {
    background: var(--bg-tertiary);
    color: var(--text-primary);
}

.modal-body {
    padding: 1.5rem;
    max-height: calc(90vh - 140px);
    overflow-y: auto;
}

.modal-footer {
    padding: 1rem 1.5rem;
    border-top: 1px solid var(--border-color);
    display: flex;
    justify-content: flex-end;
    gap: 0.75rem;
}

.required {
    color: var(--danger-color);
}

/* Toast Notifications */
#toastContainer {
    position: fixed;
    top: 20px;
    left: 50%;
    transform: translateX(-50%);
    z-index: 10000;
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.toast {
    background: white;
    padding: 1rem 1.5rem;
    border-radius: var(--radius-md);
    box-shadow: var(--shadow-lg);
    display: flex;
    align-items: center;
    gap: 1rem;
    min-width: 300px;
    animation: toastSlideIn 0.3s ease;
    border-right: 4px solid;
}

@keyframes toastSlideIn {
    from {
        opacity: 0;
        transform: translateY(-20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.toast.success {
    border-color: var(--success-color);
}

.toast.error {
    border-color: var(--danger-color);
}

.toast.warning {
    border-color: var(--warning-color);
}

.toast.info {
    border-color: var(--info-color);
}

.toast-icon {
    font-size: 1.5rem;
}

.toast-message {
    flex: 1;
}

.toast-close {
    background: none;
    border: none;
    cursor: pointer;
    color: var(--text-muted);
    font-size: 1.25rem;
}
</style>

<script>
// משתנים גלובליים למודלים
let editingItemId = null;
let deletingItemId = null;

// פתיחת מודל הוספה/עריכה
function openModal(type = null, parentId = null, itemId = null) {
    const modal = document.getElementById('itemModal');
    const form = document.getElementById('itemForm');
    
    // איפוס הטופס
    form.reset();
    editingItemId = itemId;
    
    // הגדרת סוג ואב
    document.getElementById('itemType').value = type || currentType;
    document.getElementById('parentId').value = parentId || currentParentId;
    
    // התאמת השדות לסוג
    adjustFieldsForType(type || currentType);
    
    // אם עריכה, טען נתונים
    if (itemId) {
        document.getElementById('modalTitle').textContent = 'ערוך ' + getHierarchyLevel(type || currentType);
        document.getElementById('saveButtonText').textContent = 'עדכן';
        loadItemData(itemId);
    } else {
        document.getElementById('modalTitle').textContent = 'הוסף ' + getHierarchyLevel(type || currentType);
        document.getElementById('saveButtonText').textContent = 'שמור';
    }
    
    modal.classList.add('show');
}

// סגירת מודל
function closeModal() {
    document.getElementById('itemModal').classList.remove('show');
    editingItemId = null;
}

// התאמת שדות לסוג הפריט
function adjustFieldsForType2(type) {
    // הסתרת כל השדות הספציפיים
    document.querySelectorAll('.grave-only, .area-grave-only').forEach(el => {
        el.style.display = 'none';
    });
    
    // הצגת שדות לפי סוג
    if (type === 'grave') {
        document.querySelectorAll('.grave-only').forEach(el => {
            el.style.display = 'block';
        });
        // בקברים השם הוא מספר הקבר
        document.getElementById('itemName').parentElement.style.display = 'none';
    } else {
        document.getElementById('itemName').parentElement.style.display = 'block';
    }
    
    if (type === 'area_grave') {
        document.querySelectorAll('.area-grave-only').forEach(el => {
            el.style.display = 'block';
        });
    }
}
// בפונקציה adjustFieldsForType - שורה ~197 בערך
function adjustFieldsForType(type) {
    // הסתרת כל השדות הספציפיים
    document.querySelectorAll('.grave-only, .area-grave-only').forEach(el => {
        el.style.display = 'none';
    });
    
    // הצגת שדות לפי סוג
    if (type === 'grave') {
        document.querySelectorAll('.grave-only').forEach(el => {
            el.style.display = 'block';
        });
        // בקברים השם הוא מספר הקבר
        const nameField = document.getElementById('itemName');
        const nameContainer = nameField.parentElement;
        
        // הסר את required מהשדה name כשהוא מוסתר
        nameField.removeAttribute('required');
        nameContainer.style.display = 'none';
        
        // הוסף required לשדה grave_number
        const graveNumberField = document.getElementById('graveNumber');
        if (graveNumberField) {
            graveNumberField.setAttribute('required', 'required');
        }
    } else {
        // בכל המקרים האחרים - החזר את השדה name
        const nameField = document.getElementById('itemName');
        const nameContainer = nameField.parentElement;
        
        nameField.setAttribute('required', 'required');
        nameContainer.style.display = 'block';
    }
    
    if (type === 'area_grave') {
        document.querySelectorAll('.area-grave-only').forEach(el => {
            el.style.display = 'block';
        });
    }
}

// שמירת פריט
// תיקון לפונקציית saveItem במודאל
// להחליף את הפונקציה הקיימת ב-modals.php

// בפונקציה saveItem - שורה ~245 בערך
// בתחילת הפונקציה saveItem
async function saveItem-אא(event) {
    event.preventDefault();
    
    const form = event.target;
    const formData = new FormData(form);
    const type = document.getElementById('itemType').value;
    const parentId = document.getElementById('parentId').value;
    
    // בניית אובייקט נתונים לפי סוג הפריט
    const data = {};
    
    // שדות בסיסיים לכל הסוגים
    if (type === 'grave') {
        // בקברים - לא צריך את השדה name, רק grave_number
        if (formData.get('grave_number')) {
            data.grave_number = formData.get('grave_number');
            data.name = formData.get('grave_number'); // הוסף גם כ-name לתאימות
        }
    } else {
        // בכל שאר הסוגים - צריך את השדה name
        if (formData.get('name')) {
            data.name = formData.get('name');
        }
    }
    
    // המשך עם שאר השדות...
    if (formData.get('code')) {
        data.code = formData.get('code');
    }
    
    // וכו'...
}
async function saveItem2(event) {
    event.preventDefault();
    
    const form = event.target;
    const formData = new FormData(form);
    const type = document.getElementById('itemType').value;
    const parentId = document.getElementById('parentId').value;
    
    // בניית אובייקט נתונים לפי סוג הפריט
    const data = {};
    
    // שדות בסיסיים לכל הסוגים
    if (formData.get('name') && type !== 'grave') {
        data.name = formData.get('name');
    }
    
    if (formData.get('code')) {
        data.code = formData.get('code');
    }
    
    if (formData.get('location')) {
        data.location = formData.get('location');
    }
    
    if (formData.get('coordinates')) {
        data.coordinates = formData.get('coordinates');
    }
    
    if (formData.get('comments')) {
        data.comments = formData.get('comments');
    }
    
    // שדות ספציפיים לפי סוג
    switch(type) {
        case 'cemetery':
            // שדות ספציפיים לבית עלמין
            if (formData.get('address')) {
                data.address = formData.get('address');
            }
            if (formData.get('contact_name')) {
                data.contact_name = formData.get('contact_name');
            }
            if (formData.get('contact_phone')) {
                data.contact_phone = formData.get('contact_phone');
            }
            break;
            
        case 'row':
            // שדות ספציפיים לשורה
            if (formData.get('serial_number')) {
                data.serial_number = formData.get('serial_number');
            }
            break;
            
        case 'area_grave':
            // שדות ספציפיים לאחוזת קבר
            if (formData.get('grave_type')) {
                data.grave_type = formData.get('grave_type');
            }
            break;
            
        case 'grave':
            // שדות ספציפיים לקבר
            if (formData.get('grave_number')) {
                data.grave_number = formData.get('grave_number');
                data.name = data.grave_number; // בקברים השם הוא מספר הקבר
            }
            if (formData.get('plot_type')) {
                data.plot_type = formData.get('plot_type');
            }
            if (formData.get('grave_status')) {
                data.grave_status = formData.get('grave_status');
            }
            if (formData.get('grave_location')) {
                data.grave_location = formData.get('grave_location');
            }
            if (formData.get('construction_cost')) {
                data.construction_cost = formData.get('construction_cost');
            }
            if (formData.get('is_small_grave')) {
                data.is_small_grave = formData.get('is_small_grave') ? 1 : 0;
            }
            break;
    }
    
    // הוספת parent_id אם נדרש
    const parentColumn = getParentColumn(type);
    if (parentColumn && parentId) {
        data[parentColumn] = parentId;
    }
    
    // הוספת is_active
    data.is_active = 1;
    
    try {
        const url = editingItemId 
            ? `/dashboard/dashboards/cemeteries/api/cemetery-hierarchy.php?action=update&type=${type}&id=${editingItemId}`
            : `/dashboard/dashboards/cemeteries/api/cemetery-hierarchy.php?action=create&type=${type}`;
            
        const method = editingItemId ? 'PUT' : 'POST';
        
        console.log('Sending data:', data); // לוג לבדיקה
        
        const response = await fetch(url, {
            method: method,
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(data)
        });
        
        const result = await response.json();
        
        if (result.success) {
            showToast('success', editingItemId ? 'הפריט עודכן בהצלחה' : 'הפריט נוצר בהצלחה');
            closeModal();
            if (typeof refreshAllData === 'function') {
                refreshAllData();
            }
        } else {
            showToast('error', result.error || 'שגיאה בשמירה');
            console.error('Save error:', result.error);
        }
    } catch (error) {
        showToast('error', 'שגיאה בתקשורת עם השרת');
        console.error('Network error:', error);
    }
}
async function saveItem(event) {
    event.preventDefault();
    
    const form = event.target;
    const formData = new FormData(form);
    const type = document.getElementById('itemType').value;
    const parentId = document.getElementById('parentId').value;
    
    // בניית אובייקט נתונים לפי סוג הפריט
    const data = {};
    
    // שדות בסיסיים לכל הסוגים - חוץ מקברים
    if (type !== 'grave') {
        if (formData.get('name')) {
            data.name = formData.get('name');
        }
    }
    
    if (formData.get('code')) {
        data.code = formData.get('code');
    }
    
    if (formData.get('location')) {
        data.location = formData.get('location');
    }
    
    if (formData.get('coordinates')) {
        data.coordinates = formData.get('coordinates');
    }
    
    if (formData.get('comments')) {
        data.comments = formData.get('comments');
    }
    
    // שדות ספציפיים לפי סוג
    switch(type) {
        case 'cemetery':
            // שדות ספציפיים לבית עלמין
            if (formData.get('address')) {
                data.address = formData.get('address');
            }
            if (formData.get('contact_name')) {
                data.contact_name = formData.get('contact_name');
            }
            if (formData.get('contact_phone')) {
                data.contact_phone = formData.get('contact_phone');
            }
            break;
            
        case 'row':
            // שדות ספציפיים לשורה
            if (formData.get('serial_number')) {
                data.serial_number = formData.get('serial_number');
            }
            break;
            
        case 'area_grave':
            // שדות ספציפיים לאחוזת קבר
            if (formData.get('grave_type')) {
                data.grave_type = formData.get('grave_type');
            }
            break;
            
        case 'grave':
            // שדות ספציפיים לקבר - בלי השדה name!
            if (formData.get('grave_number')) {
                data.grave_number = formData.get('grave_number');
                // אל תוסיף את זה: data.name = data.grave_number;
                // כי אין עמודת name בטבלת graves!
            }
            if (formData.get('plot_type')) {
                data.plot_type = formData.get('plot_type');
            }
            if (formData.get('grave_status')) {
                data.grave_status = formData.get('grave_status');
            }
            if (formData.get('grave_location')) {
                data.grave_location = formData.get('grave_location');
            }
            if (formData.get('construction_cost')) {
                data.construction_cost = formData.get('construction_cost');
            }
            if (formData.get('is_small_grave')) {
                data.is_small_grave = formData.get('is_small_grave') ? 1 : 0;
            }
            break;
    }
    
    // הוספת parent_id אם נדרש
    const parentColumn = getParentColumn(type);
    if (parentColumn && parentId) {
        data[parentColumn] = parentId;
    }
    
    // הוספת is_active
    data.is_active = 1;
    
    try {
        const url = editingItemId 
            ? `/dashboard/dashboards/cemeteries/api/cemetery-hierarchy.php?action=update&type=${type}&id=${editingItemId}`
            : `/dashboard/dashboards/cemeteries/api/cemetery-hierarchy.php?action=create&type=${type}`;
            
        const method = editingItemId ? 'PUT' : 'POST';
        
        console.log('Sending data:', data); // לוג לבדיקה
        
        const response = await fetch(url, {
            method: method,
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(data)
        });
        
        const result = await response.json();
        
        if (result.success) {
            showToast('success', editingItemId ? 'הפריט עודכן בהצלחה' : 'הפריט נוצר בהצלחה');
            closeModal();
            if (typeof refreshAllData === 'function') {
                refreshAllData();
            }
        } else {
            showToast('error', result.error || 'שגיאה בשמירה');
            console.error('Save error:', result.error);
        }
    } catch (error) {
        showToast('error', 'שגיאה בתקשורת עם השרת');
        console.error('Network error:', error);
    }
}

// טעינת נתוני פריט לעריכה
async function loadItemData(itemId) {
    const type = document.getElementById('itemType').value;
    
    try {
        const response = await fetch(`/dashboard/dashboards/cemeteries/api/cemetery-hierarchy.php?action=get&type=${type}&id=${itemId}`);
        const result = await response.json();
        
        if (result.success && result.data) {
            const data = result.data;
            
            // מילוי השדות
            document.getElementById('itemId').value = data.id;
            document.getElementById('itemName').value = data.name || '';
            document.getElementById('itemCode').value = data.code || '';
            document.getElementById('itemLocation').value = data.location || '';
            document.getElementById('itemCoordinates').value = data.coordinates || '';
            document.getElementById('itemComments').value = data.comments || '';
            
            // שדות ספציפיים
            if (type === 'grave') {
                document.getElementById('graveNumber').value = data.grave_number || '';
                document.getElementById('plotType').value = data.plot_type || '';
                document.getElementById('graveStatus').value = data.grave_status || 1;
            }
            
            if (type === 'area_grave') {
                document.getElementById('graveType').value = data.grave_type || '';
            }
        }
    } catch (error) {
        showToast('error', 'שגיאה בטעינת הנתונים');
        console.error(error);
    }
}

// פתיחת מודל מחיקה
async function openDeleteModal(itemId) {
    deletingItemId = itemId;
    const modal = document.getElementById('deleteModal');
    const warning = document.getElementById('deleteWarning');
    const confirmBtn = document.getElementById('confirmDeleteBtn');
    
    // בדיקה אם יש ילדים
    const hasChildren = await checkForChildren(currentType, itemId);
    
    if (hasChildren) {
        warning.style.display = 'block';
        confirmBtn.disabled = true;
        confirmBtn.classList.add('disabled');
    } else {
        warning.style.display = 'none';
        confirmBtn.disabled = false;
        confirmBtn.classList.remove('disabled');
    }
    
    modal.classList.add('show');
}

// בדיקה אם יש ילדים
async function checkForChildren(type, itemId) {
    const childTypes = {
        'cemetery': 'block',
        'block': 'plot',
        'plot': 'row',
        'row': 'area_grave',
        'area_grave': 'grave'
    };
    
    const childType = childTypes[type];
    if (!childType) return false;
    
    try {
        const response = await fetch(`/dashboard/dashboards/cemeteries/api/cemetery-hierarchy.php?action=list&type=${childType}&parent_id=${itemId}`);
        const result = await response.json();
        
        return result.success && result.data && result.data.length > 0;
    } catch (error) {
        return false;
    }
}

// סגירת מודל מחיקה
function closeDeleteModal() {
    document.getElementById('deleteModal').classList.remove('show');
    deletingItemId = null;
}

// אישור מחיקה
async function confirmDelete() {
    if (!deletingItemId) return;
    
    try {
        const response = await fetch(
            `/dashboard/dashboards/cemeteries/api/cemetery-hierarchy.php?action=delete&type=${currentType}&id=${deletingItemId}`,
            { method: 'DELETE' }
        );
        
        const result = await response.json();
        
        if (result.success) {
            showToast('success', 'הפריט נמחק בהצלחה');
            closeDeleteModal();
            refreshAllData();
        } else {
            showToast('error', result.error || 'שגיאה במחיקה');
        }
    } catch (error) {
        showToast('error', 'שגיאה בתקשורת עם השרת');
        console.error(error);
    }
}

// הצגת הודעה
function showToast(type, message) {
    const container = document.getElementById('toastContainer');
    const toast = document.createElement('div');
    toast.className = `toast ${type}`;
    
    const icons = {
        success: '✓',
        error: '✗',
        warning: '⚠',
        info: 'ℹ'
    };
    
    toast.innerHTML = `
        <span class="toast-icon">${icons[type]}</span>
        <span class="toast-message">${message}</span>
        <button class="toast-close" onclick="this.parentElement.remove()">×</button>
    `;
    
    container.appendChild(toast);
    
    // הסרה אוטומטית אחרי 5 שניות
    setTimeout(() => {
        toast.remove();
    }, 5000);
}

// פונקציות עזר
function getHierarchyLevel(type) {
    const levels = {
        'cemetery': 'בית עלמין',
        'block': 'גוש',
        'plot': 'חלקה',
        'row': 'שורה',
        'area_grave': 'אחוזת קבר',
        'grave': 'קבר'
    };
    return levels[type] || type;
}

function getParentColumn(type) {
    const parents = {
        'block': 'cemetery_id',
        'plot': 'block_id',
        'row': 'plot_id',
        'area_grave': 'row_id',
        'grave': 'area_grave_id'
    };
    return parents[type] || null;
}

// חשיפת פונקציות גלובליות
window.openModal = openModal;
window.closeModal = closeModal;
window.openDeleteModal = openDeleteModal;
window.closeDeleteModal = closeDeleteModal;
window.showToast = showToast;
</script>