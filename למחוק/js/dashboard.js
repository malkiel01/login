// js/dashboard.js - פונקציונליות הדשבורד

class Dashboard {
    constructor() {
        this.csrfToken = window.APP_CONFIG.csrfToken;
        this.init();
    }
    
    init() {
        console.log('Dashboard: Initializing...');
        this.setupEventListeners();
        this.addDebugTools();
    }
    
    setupEventListeners() {
        // טופס יצירת קבוצה
        const createGroupForm = document.getElementById('createGroupForm');
        if (createGroupForm) {
            createGroupForm.addEventListener('submit', (e) => this.handleCreateGroup(e));
        }
        
        // סגירת מודל בלחיצה מחוץ לו
        window.addEventListener('click', (event) => {
            const modal = document.getElementById('createGroupModal');
            if (event.target === modal) {
                this.closeCreateGroupModal();
            }
        });
        
        // כפתור ESC לסגירת מודל
        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') {
                this.closeCreateGroupModal();
            }
        });
    }
    
    // יצירת קבוצה חדשה
    async handleCreateGroup(event) {
        event.preventDefault();
        
        const participationType = document.querySelector('input[name="ownerParticipationType"]:checked').value;
        const participationValue = parseFloat(document.getElementById('ownerParticipationValue').value);
        
        // בדיקות תקינות
        if (participationType === 'percentage' && participationValue > 100) {
            this.showAlert('לא ניתן להגדיר יותר מ-100% השתתפות', 'error');
            return;
        }
        
        if (participationValue <= 0) {
            this.showAlert('ערך ההשתתפות חייב להיות חיובי', 'error');
            return;
        }
        
        // הכן נתונים
        const formData = new FormData();
        formData.append('action', 'createGroup');
        formData.append('name', document.getElementById('groupName').value);
        formData.append('description', document.getElementById('groupDescription').value);
        formData.append('participation_type', participationType);
        formData.append('participation_value', participationValue);
        formData.append('csrf_token', this.csrfToken);
        
        try {
            const response = await fetch('dashboard.php', {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                body: formData
            });
            
            const data = await response.json();
            
            if (data.success) {
                // הפנה לקבוצה החדשה
                window.location.href = `group.php?id=${data.group_id}`;
            } else {
                this.showAlert(data.message || 'שגיאה ביצירת הקבוצה', 'error');
            }
        } catch (error) {
            console.error('Error creating group:', error);
            this.showAlert('שגיאת תקשורת עם השרת', 'error');
        }
    }
    
    // עזיבת קבוצה
    async leaveGroup(groupId) {
        if (!confirm('האם אתה בטוח שברצונך לעזוב את הקבוצה?')) {
            return;
        }
        
        const formData = new FormData();
        formData.append('action', 'leaveGroup');
        formData.append('group_id', groupId);
        formData.append('csrf_token', this.csrfToken);
        
        try {
            const response = await fetch('dashboard.php', {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                body: formData
            });
            
            const data = await response.json();
            
            if (data.success) {
                location.reload();
            } else {
                this.showAlert(data.message || 'שגיאה בעזיבת הקבוצה', 'error');
            }
        } catch (error) {
            console.error('Error leaving group:', error);
            this.showAlert('שגיאת תקשורת עם השרת', 'error');
        }
    }
    
    // תגובה להזמנה
    async respondInvitation(invitationId, response) {
        const formData = new FormData();
        formData.append('action', 'respondInvitation');
        formData.append('invitation_id', invitationId);
        formData.append('response', response);
        formData.append('csrf_token', this.csrfToken);
        
        try {
            const responseData = await fetch('dashboard.php', {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                body: formData
            });
            
            const data = await responseData.json();
            
            if (data.success) {
                location.reload();
            } else {
                this.showAlert(data.message || 'שגיאה בטיפול בהזמנה', 'error');
            }
        } catch (error) {
            console.error('Error responding to invitation:', error);
            this.showAlert('שגיאת תקשורת עם השרת', 'error');
        }
    }
    
    // פונקציות UI
    showCreateGroupModal() {
        const modal = document.getElementById('createGroupModal');
        if (modal) {
            modal.style.display = 'block';
            // התמקד בשדה הראשון
            setTimeout(() => {
                const firstInput = modal.querySelector('input[type="text"]');
                if (firstInput) firstInput.focus();
            }, 100);
        }
    }
    
    closeCreateGroupModal() {
        const modal = document.getElementById('createGroupModal');
        if (modal) {
            modal.style.display = 'none';
            // נקה טופס
            const form = document.getElementById('createGroupForm');
            if (form) form.reset();
            // אפס את הסיומת
            const suffix = document.getElementById('ownerValueSuffix');
            if (suffix) suffix.textContent = '%';
        }
    }
    
    toggleOwnerParticipationType() {
        const type = document.querySelector('input[name="ownerParticipationType"]:checked').value;
        const suffix = document.getElementById('ownerValueSuffix');
        if (suffix) {
            suffix.textContent = type === 'percentage' ? '%' : '₪';
        }
    }
    
    // הצגת הודעות
    showAlert(message, type = 'info') {
        // הסר התראות קיימות
        const existingAlerts = document.querySelectorAll('.dashboard-alert');
        existingAlerts.forEach(alert => alert.remove());
        
        const alert = document.createElement('div');
        alert.className = `dashboard-alert dashboard-alert-${type}`;
        
        const icon = type === 'success' ? '✅' : 
                    type === 'error' ? '❌' : 
                    type === 'warning' ? '⚠️' : 'ℹ️';
        
        alert.innerHTML = `
            <span class="alert-icon">${icon}</span>
            <span class="alert-message">${message}</span>
        `;
        
        document.body.appendChild(alert);
        
        // הצג עם אנימציה
        setTimeout(() => alert.classList.add('show'), 100);
        
        // הסר אחרי 4 שניות
        setTimeout(() => {
            alert.classList.remove('show');
            setTimeout(() => alert.remove(), 300);
        }, 4000);
    }
    
    // כלי דיבוג (רק בסביבת פיתוח)
    addDebugTools() {
        if (window.location.hostname !== 'localhost' && !window.location.hostname.includes('test')) {
            return;
        }
        
        const debugPanel = document.createElement('div');
        debugPanel.id = 'debug-panel';
        debugPanel.style.cssText = `
            position: fixed;
            top: 80px;
            left: 10px;
            background: rgba(0, 0, 0, 0.8);
            color: white;
            padding: 10px;
            border-radius: 8px;
            font-size: 12px;
            z-index: 9999;
            display: flex;
            flex-direction: column;
            gap: 5px;
            max-width: 200px;
        `;
        
        debugPanel.innerHTML = `
            <strong>🔧 Debug Tools</strong>
            <button onclick="dashboard.showAlert('Test success!', 'success')" style="padding: 5px; font-size: 11px;">✅ Success Alert</button>
            <button onclick="dashboard.showAlert('Test error!', 'error')" style="padding: 5px; font-size: 11px;">❌ Error Alert</button>
            <button onclick="window.pwaNotifications?.sendTestNotification()" style="padding: 5px; font-size: 11px;">🔔 Test Notification</button>
            <button onclick="window.pwaInstaller?.showInstallPrompt()" style="padding: 5px; font-size: 11px;">📱 Install Prompt</button>
            <button onclick="console.log('PWA Installed:', window.pwaInstaller?.isInstalled())" style="padding: 5px; font-size: 11px;">📊 PWA Status</button>
        `;
        
        document.body.appendChild(debugPanel);
        
        // הוסף סגנונות להתראות
        this.addAlertStyles();
    }
    
    addAlertStyles() {
        if (document.getElementById('dashboard-alert-styles')) return;
        
        const style = document.createElement('style');
        style.id = 'dashboard-alert-styles';
        style.textContent = `
            .dashboard-alert {
                position: fixed;
                bottom: 30px;
                left: 50%;
                transform: translateX(-50%) translateY(100px);
                background: white;
                padding: 16px 24px;
                border-radius: 12px;
                box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
                z-index: 10002;
                display: flex;
                align-items: center;
                gap: 12px;
                font-weight: 600;
                opacity: 0;
                transition: all 0.4s cubic-bezier(0.68, -0.55, 0.265, 1.55);
                max-width: 90%;
                direction: rtl;
            }
            
            .dashboard-alert.show {
                transform: translateX(-50%) translateY(0);
                opacity: 1;
            }
            
            .dashboard-alert-success {
                background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
                color: white;
            }
            
            .dashboard-alert-error {
                background: linear-gradient(135deg, #dc3545 0%, #fd7e14 100%);
                color: white;
            }
            
            .dashboard-alert-warning {
                background: linear-gradient(135deg, #ffc107 0%, #fd7e14 100%);
                color: #333;
            }
            
            .dashboard-alert-info {
                background: linear-gradient(135deg, #17a2b8 0%, #6f42c1 100%);
                color: white;
            }
            
            .alert-icon {
                font-size: 20px;
                filter: drop-shadow(0 1px 2px rgba(0, 0, 0, 0.1));
            }
            
            .alert-message {
                font-size: 15px;
                text-shadow: 0 1px 2px rgba(0, 0, 0, 0.1);
            }
        `;
        
        document.head.appendChild(style);
    }
}

// יצור אינסטנס גלובלי
const dashboard = new Dashboard();

// חשוף פונקציות גלובליות
window.showCreateGroupModal = () => dashboard.showCreateGroupModal();
window.closeCreateGroupModal = () => dashboard.closeCreateGroupModal();
window.toggleOwnerParticipationType = () => dashboard.toggleOwnerParticipationType();
window.leaveGroup = (groupId) => dashboard.leaveGroup(groupId);
window.respondInvitation = (invitationId, response) => dashboard.respondInvitation(invitationId, response);

// חשוף את האובייקט הראשי לדיבוג
window.dashboard = dashboard;

console.log('Dashboard loaded! 🏠');