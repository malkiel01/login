<?php
// dashboard/dashboards/cemeteries/test-permissions.php
// דף לבדיקת מערכת ההרשאות

session_start();

// הגדר הרשאה לבדיקה (שנה את זה לבדיקות שונות)
$testRole = $_GET['role'] ?? 'cemetery_manager';
$_SESSION['dashboard_type'] = $testRole;

require_once __DIR__ . '/forms/forms-config.php';

// קרא לפונקציות הדיבאג
debugPermissions();

// טען את השדות לכל תפקיד
$fields = getFormFields('cemetery');

?>
<!DOCTYPE html>
<html dir="rtl" lang="he">
<head>
    <meta charset="UTF-8">
    <title>בדיקת הרשאות</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
            background: #f5f5f5;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 {
            color: #333;
            border-bottom: 2px solid #667eea;
            padding-bottom: 10px;
        }
        .role-selector {
            margin: 20px 0;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 5px;
        }
        .role-selector select {
            padding: 10px;
            font-size: 16px;
            border: 1px solid #ddd;
            border-radius: 5px;
            margin-right: 10px;
        }
        .role-selector button {
            padding: 10px 20px;
            background: #667eea;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
        }
        .role-selector button:hover {
            background: #5a67d8;
        }
        .info-box {
            margin: 20px 0;
            padding: 15px;
            background: #e8f4f8;
            border-right: 4px solid #3182ce;
            border-radius: 5px;
        }
        .permissions-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin: 20px 0;
        }
        .permission-card {
            padding: 15px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            text-align: center;
        }
        .permission-card.allowed {
            background: #c6f6d5;
            border-color: #48bb78;
        }
        .permission-card.denied {
            background: #fed7d7;
            border-color: #f56565;
        }
        .fields-table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        .fields-table th,
        .fields-table td {
            padding: 12px;
            text-align: right;
            border: 1px solid #e2e8f0;
        }
        .fields-table th {
            background: #667eea;
            color: white;
            font-weight: bold;
        }
        .fields-table tr:nth-child(even) {
            background: #f7fafc;
        }
        .field-required {
            color: #e53e3e;
            font-weight: bold;
        }
        .field-type {
            display: inline-block;
            padding: 2px 8px;
            background: #edf2f7;
            border-radius: 4px;
            font-size: 12px;
        }
        .role-badge {
            display: inline-block;
            padding: 5px 15px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            border-radius: 20px;
            font-weight: bold;
            margin: 0 10px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔐 בדיקת מערכת ההרשאות</h1>
        
        <div class="role-selector">
            <form method="GET">
                <label>בחר תפקיד לבדיקה:</label>
                <select name="role" id="role">
                    <option value="cemetery_manager" <?= $testRole === 'cemetery_manager' ? 'selected' : '' ?>>מנהל בית עלמין</option>
                    <option value="manager" <?= $testRole === 'manager' ? 'selected' : '' ?>>מנהל צוות</option>
                    <option value="employee" <?= $testRole === 'employee' ? 'selected' : '' ?>>עובד</option>
                    <option value="client" <?= $testRole === 'client' ? 'selected' : '' ?>>לקוח</option>
                    <option value="admin" <?= $testRole === 'admin' ? 'selected' : '' ?>>מנהל מערכת</option>
                </select>
                <button type="submit">בדוק הרשאות</button>
            </form>
        </div>
        
        <div class="info-box">
            <strong>תפקיד נוכחי:</strong> 
            <span class="role-badge"><?= htmlspecialchars($testRole) ?></span>
            <br>
            <strong>תפקיד ממופה:</strong> 
            <span class="role-badge"><?= htmlspecialchars(getUserRole()) ?></span>
        </div>
        
        <h2>הרשאות פעולה</h2>
        <div class="permissions-grid">
            <?php
            $permissions = ['view', 'create', 'edit', 'delete'];
            foreach ($permissions as $perm):
                $hasPermission = hasUserPermission($perm);
            ?>
            <div class="permission-card <?= $hasPermission ? 'allowed' : 'denied' ?>">
                <h3><?= ucfirst($perm) ?></h3>
                <p><?= $hasPermission ? '✅ מאושר' : '❌ נדחה' ?></p>
            </div>
            <?php endforeach; ?>
        </div>
        
        <h2>שדות זמינים בטופס (סוג: cemetery)</h2>
        <table class="fields-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>שם שדה</th>
                    <th>תווית</th>
                    <th>סוג</th>
                    <th>חובה</th>
                    <th>גישה</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $allFieldsConfig = require __DIR__ . '/config/cemetery-hierarchy-config.php';
                $cemeteryFields = $allFieldsConfig['cemetery']['form_fields'] ?? [];
                
                foreach ($cemeteryFields as $index => $fieldConfig):
                    $hasAccess = canUserEditField($fieldConfig['name'], 'cemetery');
                    $fieldFound = false;
                    foreach ($fields as $field) {
                        if ($field['name'] === $fieldConfig['name']) {
                            $fieldFound = true;
                            break;
                        }
                    }
                ?>
                <tr style="<?= !$fieldFound ? 'opacity: 0.5; background: #f0f0f0;' : '' ?>">
                    <td><?= $index + 1 ?></td>
                    <td><code><?= htmlspecialchars($fieldConfig['name']) ?></code></td>
                    <td><?= htmlspecialchars($fieldConfig['label']) ?></td>
                    <td><span class="field-type"><?= htmlspecialchars($fieldConfig['type']) ?></span></td>
                    <td><?= isset($fieldConfig['required']) && $fieldConfig['required'] ? '<span class="field-required">*</span>' : '-' ?></td>
                    <td>
                        <?php if ($fieldFound): ?>
                            <span style="color: green;">✅ נגיש</span>
                        <?php else: ?>
                            <span style="color: red;">❌ חסום</span>
                            <?php if (isset($fieldConfig['permissions'])): ?>
                                <br><small>נדרש: <?= implode(', ', $fieldConfig['permissions']) ?></small>
                            <?php endif; ?>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <div class="info-box" style="background: #fef5e7; border-color: #f39c12;">
            <strong>סיכום:</strong><br>
            • סה"כ שדות בקונפיג: <?= count($cemeteryFields) ?><br>
            • שדות נגישים לתפקיד הנוכחי: <?= count($fields) ?><br>
            • שדות חסומים: <?= count($cemeteryFields) - count($fields) ?>
        </div>
        
        <h2>בדיקת כל התפקידים</h2>
        <table class="fields-table">
            <thead>
                <tr>
                    <th>תפקיד</th>
                    <th>תפקיד ממופה</th>
                    <th>צפייה</th>
                    <th>יצירה</th>
                    <th>עריכה</th>
                    <th>מחיקה</th>
                    <th>שדות נגישים</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $testRoles = ['cemetery_manager', 'manager', 'employee', 'client'];
                foreach ($testRoles as $role):
                    $_SESSION['dashboard_type'] = $role;
                    $mappedRole = getUserRole();
                    $roleFields = getFormFields('cemetery');
                ?>
                <tr>
                    <td><strong><?= $role ?></strong></td>
                    <td><?= $mappedRole ?></td>
                    <td><?= hasUserPermission('view') ? '✅' : '❌' ?></td>
                    <td><?= hasUserPermission('create') ? '✅' : '❌' ?></td>
                    <td><?= hasUserPermission('edit') ? '✅' : '❌' ?></td>
                    <td><?= hasUserPermission('delete') ? '✅' : '❌' ?></td>
                    <td><?= count($roleFields) ?> / <?= count($cemeteryFields) ?></td>
                </tr>
                <?php endforeach; ?>
                <?php $_SESSION['dashboard_type'] = $testRole; // החזר את הערך המקורי ?>
            </tbody>
        </table>
    </div>
</body>
</html>
?>