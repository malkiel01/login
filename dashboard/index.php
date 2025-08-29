<?php
// dashboard/index.php - קובץ ראשי של הדשבורד
require_once '../pwa/pwa-init.php';
session_start();
require_once '../config.php';
require_once 'includes/functions.php';
require_once '../permissions/init.php';
require_once '../debugs/console-debug-single.php';

// require_once '../debugs/index.php';

// בדיקת התחברות
checkAuthentication();

// הוספה

// בתחילת הקובץ, אחרי בדיקת ההתחברות
$currentUser = getCurrentUser();
$dashboardType = getUserDashboardType($currentUser['id']);

// הפניה לדשבורד המתאים
if ($dashboardType !== 'default') {
    redirectToDashboard($currentUser['id']);
}

// סוף הוספה

// קבלת נתוני משתמש
$currentUser = getCurrentUser();
$allUsers = getAllUsers();
$stats = getDashboardStats();

// הגדרת נתיבים
define('DASHBOARD_URL', '/dashboard');
define('DASHBOARD_PATH', __DIR__);
?>
<!DOCTYPE html>
<html dir="rtl" lang="he">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>מערכת ניהול דשבורדים</title>
        <style>
            * {
                margin: 0;
                padding: 0;
                box-sizing: border-box;
            }

            body {
                font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', 'Roboto', sans-serif;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                min-height: 100vh;
                padding: 20px;
                direction: rtl;
            }

            .container {
                max-width: 1200px;
                margin: 0 auto;
            }

            .header {
                background: white;
                border-radius: 12px;
                padding: 20px;
                margin-bottom: 20px;
                box-shadow: 0 4px 6px rgba(0,0,0,0.1);
                display: flex;
                justify-content: space-between;
                align-items: center;
            }

            .header h1 {
                color: #333;
                font-size: 24px;
            }

            .user-info {
                display: flex;
                align-items: center;
                gap: 10px;
                background: #f3f4f6;
                padding: 8px 15px;
                border-radius: 8px;
            }

            .main-content {
                display: grid;
                grid-template-columns: 1fr 2fr;
                gap: 20px;
            }

            @media (max-width: 768px) {
                .main-content {
                    grid-template-columns: 1fr;
                }
            }

            .card {
                background: white;
                border-radius: 12px;
                padding: 20px;
                box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            }

            .card h2 {
                color: #333;
                margin-bottom: 15px;
                padding-bottom: 10px;
                border-bottom: 2px solid #f3f4f6;
            }

            /* Dashboard Types */
            .dashboard-display {
                min-height: 400px;
                background: #f9fafb;
                border-radius: 8px;
                padding: 30px;
                display: flex;
                flex-direction: column;
                align-items: center;
                justify-content: center;
                text-align: center;
            }

            .dashboard-icon {
                font-size: 48px;
                margin-bottom: 20px;
            }

            .dashboard-title {
                font-size: 28px;
                color: #333;
                margin-bottom: 10px;
            }

            .dashboard-description {
                color: #666;
                font-size: 16px;
                line-height: 1.5;
                max-width: 500px;
            }

            /* Dashboard Types Styling */
            .dashboard-admin { background: linear-gradient(135deg, #667eea, #764ba2); color: white; }
            .dashboard-admin .dashboard-title,
            .dashboard-admin .dashboard-description { color: white; }

            .dashboard-manager { background: linear-gradient(135deg, #11998e, #38ef7d); color: white; }
            .dashboard-manager .dashboard-title,
            .dashboard-manager .dashboard-description { color: white; }

            .dashboard-employee { background: linear-gradient(135deg, #FC466B, #3F5EFB); color: white; }
            .dashboard-employee .dashboard-title,
            .dashboard-employee .dashboard-description { color: white; }

            .dashboard-client { background: linear-gradient(135deg, #FDBB2D, #22C1C3); color: white; }
            .dashboard-client .dashboard-title,
            .dashboard-client .dashboard-description { color: white; }

            .dashboard-default { background: #e5e7eb; }

            /* Permissions Management */
            .permissions-table {
                width: 100%;
                margin-top: 15px;
            }

            .permissions-table table {
                width: 100%;
                border-collapse: collapse;
            }

            .permissions-table th,
            .permissions-table td {
                padding: 12px;
                text-align: right;
                border-bottom: 1px solid #e5e7eb;
            }

            .permissions-table th {
                background: #f3f4f6;
                font-weight: 600;
                color: #4b5563;
            }

            .select-dashboard {
                padding: 6px 12px;
                border: 1px solid #d1d5db;
                border-radius: 6px;
                background: white;
                cursor: pointer;
            }

            .btn-save {
                background: #667eea;
                color: white;
                border: none;
                padding: 8px 20px;
                border-radius: 6px;
                cursor: pointer;
                font-size: 14px;
            }

            .btn-save:hover {
                background: #5a67d8;
            }

            .status-badge {
                padding: 4px 12px;
                border-radius: 20px;
                font-size: 12px;
                font-weight: 500;
            }

            .status-active {
                background: #d1fae5;
                color: #065f46;
            }

            .status-inactive {
                background: #fee2e2;
                color: #991b1b;
            }

            /* Login Simulation */
            .login-simulator {
                margin-top: 20px;
                padding: 15px;
                background: #f9fafb;
                border-radius: 8px;
                border: 2px dashed #e5e7eb;
            }

            .login-simulator h3 {
                color: #4b5563;
                margin-bottom: 10px;
                font-size: 16px;
            }

            .btn-login {
                background: #10b981;
                color: white;
                border: none;
                padding: 8px 16px;
                border-radius: 6px;
                cursor: pointer;
                margin: 5px;
                font-size: 14px;
            }

            .btn-login:hover {
                background: #059669;
            }

            .alert {
                padding: 12px;
                border-radius: 8px;
                margin-top: 10px;
            }

            .alert-success {
                background: #d1fae5;
                color: #065f46;
                border: 1px solid #6ee7b7;
            }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <h1>🎯 מערכת ניהול דשבורדים</h1>
                <div class="user-info">
                    <span>משתמש נוכחי:</span>
                    <strong id="currentUser">admin</strong>
                    <span class="status-badge status-active">מנהל</span>
                </div>
            </div>

            <div class="main-content">
                <!-- Permissions Management Section -->
                <div class="card">
                    <h2>⚙️ ניהול הרשאות משתמשים</h2>
                    <div class="permissions-table">
                        <table>
                            <thead>
                                <tr>
                                    <th>משתמש</th>
                                    <th>סוג דשבורד</th>
                                    <th>פעולה</th>
                                </tr>
                            </thead>
                            <tbody id="permissionsTable">
                                <!-- Will be populated by JavaScript -->
                            </tbody>
                        </table>
                    </div>

                    <div class="login-simulator">
                        <h3>🔐 סימולציית התחברות</h3>
                        <p style="color: #6b7280; margin-bottom: 10px;">לחץ על משתמש כדי לראות את הדשבורד שלו:</p>
                        <div id="userButtons">
                            <!-- Will be populated by JavaScript -->
                        </div>
                    </div>
                </div>

                <!-- Dashboard Display Section -->
                <div class="card">
                    <h2>📊 תצוגת דשבורד</h2>
                    <div id="dashboardDisplay" class="dashboard-display dashboard-default">
                        <div class="dashboard-icon">🏠</div>
                        <div class="dashboard-title">דשבורד ברירת מחדל</div>
                        <div class="dashboard-description">
                            בחר משתמש מרשימת המשתמשים כדי לראות את הדשבורד המותאם עבורו
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <script>
            // Database simulation - in real implementation, this would be in MySQL
            let usersDB = [
                { id: 1, username: 'admin', name: 'מנהל ראשי', dashboard_type: 'admin' },
                { id: 2, username: 'manager1', name: 'יוסי כהן', dashboard_type: 'manager' },
                { id: 3, username: 'employee1', name: 'שרה לוי', dashboard_type: 'employee' },
                { id: 4, username: 'employee2', name: 'דוד ישראלי', dashboard_type: 'employee' },
                { id: 5, username: 'client1', name: 'חברת ABC', dashboard_type: 'client' },
                { id: 6, username: 'client2', name: 'חברת XYZ', dashboard_type: 'client' },
                { id: 7, username: 'guest', name: 'אורח', dashboard_type: 'default' }
            ];

            // Dashboard types configuration
            const dashboardTypes = {
                admin: {
                    icon: '👨‍💼',
                    title: 'דשבורד מנהל מערכת',
                    description: 'גישה מלאה לכל המערכת: ניהול משתמשים, הגדרות מערכת, דוחות מתקדמים, ניטור ביצועים וניהול הרשאות.',
                    class: 'dashboard-admin'
                },
                manager: {
                    icon: '📈',
                    title: 'דשבורד מנהל',
                    description: 'ניהול צוותים, צפייה בדוחות, ניהול משימות, מעקב אחר ביצועים וניהול פרויקטים.',
                    class: 'dashboard-manager'
                },
                employee: {
                    icon: '💼',
                    title: 'דשבורד עובד',
                    description: 'משימות אישיות, דיווח שעות, צפייה בלוח זמנים, הגשת בקשות ועדכונים שוטפים.',
                    class: 'dashboard-employee'
                },
                client: {
                    icon: '🏢',
                    title: 'דשבורד לקוח',
                    description: 'צפייה בפרויקטים, מעקב התקדמות, הורדת דוחות, פתיחת פניות ותקשורת עם הצוות.',
                    class: 'dashboard-client'
                },
                default: {
                    icon: '🏠',
                    title: 'דשבורד ברירת מחדל',
                    description: 'דשבורד בסיסי עם גישה מוגבלת. מידע כללי ופעולות בסיסיות בלבד.',
                    class: 'dashboard-default'
                }
            };

            // Current logged in user
            let currentLoggedUser = 'admin';

            // Initialize the page
            function init() {
                renderPermissionsTable();
                renderUserButtons();
                showDashboard('admin');
            }

            // Render permissions table
            function renderPermissionsTable() {
                const tbody = document.getElementById('permissionsTable');
                tbody.innerHTML = '';

                usersDB.forEach(user => {
                    const row = document.createElement('tr');
                    row.innerHTML = `
                        <td>
                            <strong>${user.name}</strong><br>
                            <small style="color: #6b7280;">@${user.username}</small>
                        </td>
                        <td>
                            <select class="select-dashboard" data-user-id="${user.id}" onchange="updateUserDashboard(${user.id}, this.value)">
                                <option value="default" ${user.dashboard_type === 'default' ? 'selected' : ''}>ברירת מחדל</option>
                                <option value="admin" ${user.dashboard_type === 'admin' ? 'selected' : ''}>מנהל מערכת</option>
                                <option value="manager" ${user.dashboard_type === 'manager' ? 'selected' : ''}>מנהל</option>
                                <option value="employee" ${user.dashboard_type === 'employee' ? 'selected' : ''}>עובד</option>
                                <option value="client" ${user.dashboard_type === 'client' ? 'selected' : ''}>לקוח</option>
                            </select>
                        </td>
                        <td>
                            <button class="btn-save" onclick="saveUserPermission(${user.id})">שמור</button>
                        </td>
                    `;
                    tbody.appendChild(row);
                });
            }

            // Render user buttons for simulation
            function renderUserButtons() {
                const container = document.getElementById('userButtons');
                container.innerHTML = '';

                usersDB.forEach(user => {
                    const button = document.createElement('button');
                    button.className = 'btn-login';
                    button.textContent = user.name;
                    button.onclick = () => simulateLogin(user.username);
                    container.appendChild(button);
                });
            }

            // Update user dashboard type
            function updateUserDashboard(userId, dashboardType) {
                const user = usersDB.find(u => u.id === userId);
                if (user) {
                    user.dashboard_type = dashboardType;
                    console.log(`Updated user ${user.name} to dashboard type: ${dashboardType}`);
                }
            }

            // Save user permission (would send to server in real implementation)
            function saveUserPermission(userId) {
                const user = usersDB.find(u => u.id === userId);
                if (user) {
                    // Simulate saving to database
                    showAlert(`ההרשאות של ${user.name} נשמרו בהצלחה!`);
                    
                    // If this is the current user, update their dashboard
                    if (user.username === currentLoggedUser) {
                        showDashboard(user.dashboard_type);
                    }
                }
            }

            // Simulate user login
            function simulateLogin(username) {
                const user = usersDB.find(u => u.username === username);
                if (user) {
                    currentLoggedUser = username;
                    document.getElementById('currentUser').textContent = user.name;
                    
                    // Show the dashboard based on user's permission
                    showDashboard(user.dashboard_type);
                    
                    showAlert(`התחברת בהצלחה כ-${user.name}`);
                }
            }

            // Show dashboard based on type
            function showDashboard(type) {
                const display = document.getElementById('dashboardDisplay');
                const dashboard = dashboardTypes[type] || dashboardTypes.default;
                
                display.className = `dashboard-display ${dashboard.class}`;
                display.innerHTML = `
                    <div class="dashboard-icon">${dashboard.icon}</div>
                    <div class="dashboard-title">${dashboard.title}</div>
                    <div class="dashboard-description">${dashboard.description}</div>
                `;
            }

            // Show alert message
            function showAlert(message) {
                const existingAlert = document.querySelector('.alert');
                if (existingAlert) {
                    existingAlert.remove();
                }

                const alert = document.createElement('div');
                alert.className = 'alert alert-success';
                alert.textContent = message;
                document.querySelector('.login-simulator').appendChild(alert);

                setTimeout(() => {
                    alert.remove();
                }, 3000);
            }

            // Initialize on page load
            init();
        </script>
    </body>
</html>