<!DOCTYPE html>
<html dir="rtl" lang="he">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ניהול התראות</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, 'Segoe UI', sans-serif;
            background: #f0f2f5;
            min-height: 100vh;
        }
        
        .header {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            position: sticky;
            top: 0;
            z-index: 100;
        }
        
        .header-content {
            max-width: 800px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .header h1 {
            font-size: 24px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .notification-count {
            background: white;
            color: #667eea;
            padding: 4px 12px;
            border-radius: 20px;
            font-weight: bold;
            font-size: 14px;
        }
        
        .container {
            max-width: 800px;
            margin: 20px auto;
            padding: 0 20px;
        }
        
        .actions-bar {
            background: white;
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        .btn {
            padding: 8px 16px;
            border: none;
            border-radius: 6px;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.3s;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .btn-primary {
            background: #667eea;
            color: white;
        }
        
        .btn-primary:hover {
            background: #5a67d8;
            transform: translateY(-1px);
        }
        
        .btn-danger {
            background: #ef4444;
            color: white;
        }
        
        .btn-danger:hover {
            background: #dc2626;
        }
        
        .btn-success {
            background: #10b981;
            color: white;
        }
        
        .notification-list {
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        
        .notification-item {
            padding: 20px;
            border-bottom: 1px solid #e5e7eb;
            transition: all 0.3s;
            cursor: pointer;
            position: relative;
        }
        
        .notification-item:hover {
            background: #f9fafb;
            padding-right: 25px;
        }
        
        .notification-item:last-child {
            border-bottom: none;
        }
        
        .notification-item.unread {
            background: #f0f9ff;
            border-right: 4px solid #3b82f6;
        }
        
        .notification-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 10px;
        }
        
        .notification-title {
            font-size: 16px;
            font-weight: 600;
            color: #1f2937;
            flex: 1;
        }
        
        .notification-time {
            font-size: 12px;
            color: #6b7280;
            white-space: nowrap;
        }
        
        .notification-body {
            color: #4b5563;
            font-size: 14px;
            line-height: 1.5;
            margin-bottom: 10px;
        }
        
        .notification-actions {
            display: flex;
            gap: 10px;
        }
        
        .action-btn {
            padding: 6px 12px;
            border: 1px solid #e5e7eb;
            background: white;
            border-radius: 4px;
            font-size: 12px;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .action-btn:hover {
            background: #f3f4f6;
            border-color: #9ca3af;
        }
        
        .action-btn.delete {
            color: #ef4444;
            border-color: #fca5a5;
        }
        
        .action-btn.delete:hover {
            background: #fef2f2;
        }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #6b7280;
        }
        
        .empty-state-icon {
            font-size: 64px;
            margin-bottom: 20px;
            opacity: 0.5;
        }
        
        .empty-state-title {
            font-size: 20px;
            font-weight: 600;
            margin-bottom: 10px;
            color: #374151;
        }
        
        .filter-tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
        }
        
        .tab {
            padding: 10px 20px;
            background: white;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s;
            font-weight: 500;
        }
        
        .tab.active {
            background: #667eea;
            color: white;
            border-color: #667eea;
        }
        
        .tab:hover:not(.active) {
            background: #f9fafb;
        }
        
        .search-box {
            background: white;
            padding: 10px 15px;
            border-radius: 8px;
            border: 1px solid #e5e7eb;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .search-box input {
            flex: 1;
            border: none;
            outline: none;
            font-size: 14px;
        }
        
        .search-box i {
            color: #6b7280;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-content">
            <h1>
                🔔 מרכז התראות
                <span class="notification-count" id="totalCount">0</span>
            </h1>
            <button class="btn" style="background: white; color: #667eea;" onclick="window.close()">
                ❌ סגור
            </button>
        </div>
    </div>
    
    <div class="container">
        <!-- חיפוש -->
        <div class="search-box">
            <i>🔍</i>
            <input type="text" id="searchInput" placeholder="חפש בהתראות..." onkeyup="filterNotifications()">
        </div>
        
        <!-- טאבים לסינון -->
        <div class="filter-tabs">
            <div class="tab active" onclick="filterByType('all')">הכל</div>
            <div class="tab" onclick="filterByType('unread')">לא נקראו</div>
            <div class="tab" onclick="filterByType('today')">היום</div>
            <div class="tab" onclick="filterByType('week')">השבוע</div>
        </div>
        
        <!-- פעולות -->
        <div class="actions-bar">
            <button class="btn btn-primary" onclick="markAllRead()">
                ✓ סמן הכל כנקרא
            </button>
            <button class="btn btn-danger" onclick="clearAll()">
                🗑️ נקה הכל
            </button>
            <button class="btn btn-success" onclick="testNotification()">
                🧪 התראת בדיקה
            </button>
        </div>
        
        <!-- רשימת התראות -->
        <div class="notification-list" id="notificationList">
            <!-- יטען מ-JavaScript -->
        </div>
    </div>
    
    <script>
        // מאגר התראות (בדרך כלל יגיע מ-DB)
        let notifications = JSON.parse(localStorage.getItem('notifications') || '[]');
        let currentFilter = 'all';
        
        // טעינת התראות בטעינת הדף
        window.onload = function() {
            loadNotifications();
            updateCount();
        };
        
        // טעינת התראות
        function loadNotifications() {
            const list = document.getElementById('notificationList');
            let filteredNotifications = filterNotificationsByType(notifications, currentFilter);
            
            // סינון לפי חיפוש
            const searchTerm = document.getElementById('searchInput').value.toLowerCase();
            if (searchTerm) {
                filteredNotifications = filteredNotifications.filter(n => 
                    n.title.toLowerCase().includes(searchTerm) || 
                    n.body.toLowerCase().includes(searchTerm)
                );
            }
            
            if (filteredNotifications.length === 0) {
                list.innerHTML = `
                    <div class="empty-state">
                        <div class="empty-state-icon">📭</div>
                        <div class="empty-state-title">אין התראות</div>
                        <div>כל ההתראות שלך יופיעו כאן</div>
                    </div>
                `;
                return;
            }
            
            list.innerHTML = filteredNotifications.map(notification => `
                <div class="notification-item ${notification.read ? '' : 'unread'}" 
                     data-id="${notification.id}"
                     onclick="openNotification('${notification.id}')">
                    <div class="notification-header">
                        <div class="notification-title">${notification.title}</div>
                        <div class="notification-time">${formatTime(notification.timestamp)}</div>
                    </div>
                    <div class="notification-body">${notification.body}</div>
                    <div class="notification-actions" onclick="event.stopPropagation()">
                        ${!notification.read ? 
                            `<button class="action-btn" onclick="markAsRead('${notification.id}')">
                                ✓ סמן כנקרא
                            </button>` : ''
                        }
                        <button class="action-btn delete" onclick="deleteNotification('${notification.id}')">
                            🗑️ מחק
                        </button>
                        ${notification.url ? 
                            `<button class="action-btn" onclick="window.location.href='${notification.url}'">
                                ↗️ עבור
                            </button>` : ''
                        }
                    </div>
                </div>
            `).join('');
        }
        
        // סינון לפי סוג
        function filterByType(type) {
            currentFilter = type;
            
            // עדכון טאבים
            document.querySelectorAll('.tab').forEach(tab => {
                tab.classList.remove('active');
            });
            event.target.classList.add('active');
            
            loadNotifications();
        }
        
        // סינון התראות לפי סוג
        function filterNotificationsByType(notifs, type) {
            const now = Date.now();
            const day = 24 * 60 * 60 * 1000;
            
            switch(type) {
                case 'unread':
                    return notifs.filter(n => !n.read);
                case 'today':
                    return notifs.filter(n => (now - n.timestamp) < day);
                case 'week':
                    return notifs.filter(n => (now - n.timestamp) < (7 * day));
                default:
                    return notifs;
            }
        }
        
        // חיפוש
        function filterNotifications() {
            loadNotifications();
        }
        
        // פתיחת התראה
        function openNotification(id) {
            const notification = notifications.find(n => n.id === id);
            if (notification) {
                notification.read = true;
                saveNotifications();
                
                if (notification.url) {
                    window.location.href = notification.url;
                } else {
                    loadNotifications();
                    updateCount();
                }
            }
        }
        
        // סימון כנקרא
        function markAsRead(id) {
            const notification = notifications.find(n => n.id === id);
            if (notification) {
                notification.read = true;
                saveNotifications();
                loadNotifications();
                updateCount();
            }
        }
        
        // מחיקת התראה
        function deleteNotification(id) {
            if (confirm('למחוק את ההתראה?')) {
                notifications = notifications.filter(n => n.id !== id);
                saveNotifications();
                loadNotifications();
                updateCount();
            }
        }
        
        // סימון הכל כנקרא
        function markAllRead() {
            notifications.forEach(n => n.read = true);
            saveNotifications();
            loadNotifications();
            updateCount();
        }
        
        // ניקוי הכל
        function clearAll() {
            if (confirm('למחוק את כל ההתראות?')) {
                notifications = [];
                saveNotifications();
                loadNotifications();
                updateCount();
            }
        }
        
        // התראת בדיקה
        function testNotification() {
            const newNotif = {
                id: Date.now().toString(),
                title: 'התראת בדיקה 🧪',
                body: 'זו התראת בדיקה שנוספה מדף הניהול',
                timestamp: Date.now(),
                read: false,
                url: null
            };
            
            notifications.unshift(newNotif);
            saveNotifications();
            loadNotifications();
            updateCount();
        }
        
        // שמירת התראות
        function saveNotifications() {
            localStorage.setItem('notifications', JSON.stringify(notifications));
        }
        
        // עדכון מונה
        function updateCount() {
            const unreadCount = notifications.filter(n => !n.read).length;
            document.getElementById('totalCount').textContent = unreadCount || notifications.length;
        }
        
        // פורמט זמן
        function formatTime(timestamp) {
            const date = new Date(timestamp);
            const now = new Date();
            const diff = now - date;
            
            if (diff < 60000) return 'עכשיו';
            if (diff < 3600000) return `לפני ${Math.floor(diff/60000)} דקות`;
            if (diff < 86400000) return `לפני ${Math.floor(diff/3600000)} שעות`;
            if (diff < 604800000) return `לפני ${Math.floor(diff/86400000)} ימים`;
            
            return date.toLocaleDateString('he-IL');
        }
    </script>
</body>
</html>