<?php
/**
 * Notification Admin Cleanup
 * דף ניהול ומחיקת התראות
 */

require_once $_SERVER['DOCUMENT_ROOT'] . '/dashboard/dashboards/cemeteries/api/api-auth.php';

// רק אדמין
if (!isAdmin()) {
    die('אין הרשאה');
}

$pdo = getDBConnection();

// טיפול בפעולות
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    try {
        switch ($action) {
            case 'delete_all':
                // מחיקה בסדר הנכון - קודם טבלאות ילד, אחר כך אב
                $c1 = $pdo->exec("DELETE FROM notification_logs");
                $c2 = $pdo->exec("DELETE FROM notification_deliveries");
                $c3 = $pdo->exec("DELETE FROM notification_approvals");
                $c4 = $pdo->exec("DELETE FROM push_notifications");
                $c5 = $pdo->exec("DELETE FROM scheduled_notifications");
                $message = "נמחקו: $c5 מתוזמנות, $c4 push, $c3 אישורים, $c2 deliveries, $c1 logs";
                $messageType = 'success';
                break;

            case 'delete_all_scheduled':
                // מחק קודם את כל הזרועות
                $pdo->exec("DELETE FROM notification_logs");
                $pdo->exec("DELETE FROM notification_deliveries");
                $pdo->exec("DELETE FROM notification_approvals");
                $pdo->exec("DELETE FROM push_notifications");
                $count = $pdo->exec("DELETE FROM scheduled_notifications");
                $message = "נמחקו $count התראות מתוזמנות + כל הזרועות";
                $messageType = 'success';
                break;

            case 'delete_all_push':
                $count = $pdo->exec("DELETE FROM push_notifications");
                $message = "נמחקו $count התראות push";
                $messageType = 'success';
                break;

            case 'delete_all_approvals':
                $count = $pdo->exec("DELETE FROM notification_approvals");
                $message = "נמחקו $count רשומות אישור";
                $messageType = 'success';
                break;

            case 'delete_all_deliveries':
                $count = $pdo->exec("DELETE FROM notification_deliveries");
                $message = "נמחקו $count רשומות delivery";
                $messageType = 'success';
                break;

            case 'delete_specific':
                $table = $_POST['table'] ?? '';
                $id = (int)($_POST['id'] ?? 0);
                if ($table && $id) {
                    $allowedTables = ['scheduled_notifications', 'push_notifications', 'notification_approvals', 'notification_deliveries', 'notification_logs'];
                    if (in_array($table, $allowedTables)) {
                        // אם מוחקים scheduled - מחק קודם כל הזרועות
                        if ($table === 'scheduled_notifications') {
                            $pdo->prepare("DELETE FROM notification_logs WHERE notification_id = ?")->execute([$id]);
                            $pdo->prepare("DELETE FROM notification_deliveries WHERE notification_id = ?")->execute([$id]);
                            $pdo->prepare("DELETE FROM notification_approvals WHERE notification_id = ?")->execute([$id]);
                            $pdo->prepare("DELETE FROM push_notifications WHERE scheduled_notification_id = ?")->execute([$id]);
                        }
                        $stmt = $pdo->prepare("DELETE FROM $table WHERE id = ?");
                        $stmt->execute([$id]);
                        $message = "נמחקה רשומה $id מטבלת $table + זרועות";
                        $messageType = 'success';
                    }
                }
                break;

            case 'mark_all_read':
                $count = $pdo->exec("UPDATE push_notifications SET is_read = 1");
                $message = "סומנו $count התראות כנקראו";
                $messageType = 'success';
                break;

            case 'truncate_all':
                // TRUNCATE מאפס גם את ה-AUTO_INCREMENT
                $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
                $pdo->exec("TRUNCATE TABLE notification_logs");
                $pdo->exec("TRUNCATE TABLE notification_deliveries");
                $pdo->exec("TRUNCATE TABLE notification_approvals");
                $pdo->exec("TRUNCATE TABLE push_notifications");
                $pdo->exec("TRUNCATE TABLE scheduled_notifications");
                $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
                $message = "כל הטבלאות רוקנו ואופסו (כולל מספור ID)";
                $messageType = 'success';
                break;

            case 'delete_all_logs':
                $count = $pdo->exec("DELETE FROM notification_logs");
                $message = "נמחקו $count רשומות לוג";
                $messageType = 'success';
                break;
        }
    } catch (PDOException $e) {
        $message = 'שגיאה: ' . $e->getMessage();
        $messageType = 'error';
    }
}

// ספירת רשומות - עם בדיקה אם הטבלה קיימת
function safeCount($pdo, $sql) {
    try {
        return $pdo->query($sql)->fetchColumn();
    } catch (PDOException $e) {
        return 0; // טבלה לא קיימת
    }
}

$counts = [
    'scheduled' => safeCount($pdo, "SELECT COUNT(*) FROM scheduled_notifications"),
    'push' => safeCount($pdo, "SELECT COUNT(*) FROM push_notifications"),
    'push_unread' => safeCount($pdo, "SELECT COUNT(*) FROM push_notifications WHERE is_read = 0"),
    'approvals' => safeCount($pdo, "SELECT COUNT(*) FROM notification_approvals"),
    'deliveries' => safeCount($pdo, "SELECT COUNT(*) FROM notification_deliveries"),
    'logs' => safeCount($pdo, "SELECT COUNT(*) FROM notification_logs"),
];

// רשימת התראות אחרונות
$recentScheduled = $pdo->query("
    SELECT id, title, status, created_at, notification_type
    FROM scheduled_notifications
    ORDER BY created_at DESC
    LIMIT 20
")->fetchAll(PDO::FETCH_ASSOC);

$recentPush = $pdo->query("
    SELECT pn.id, pn.title, pn.user_id, pn.is_read, pn.created_at, u.name as user_name
    FROM push_notifications pn
    LEFT JOIN users u ON u.id = pn.user_id
    ORDER BY pn.created_at DESC
    LIMIT 20
")->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html dir="rtl" lang="he">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ניהול התראות</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f5f5f5;
            padding: 20px;
            line-height: 1.6;
        }
        .container { max-width: 1200px; margin: 0 auto; }
        h1 { margin-bottom: 20px; color: #333; }
        h2 { margin: 20px 0 10px; color: #555; font-size: 18px; }

        .message {
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .message.success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .message.error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }

        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 30px;
        }
        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            text-align: center;
        }
        .stat-number { font-size: 36px; font-weight: bold; color: #667eea; }
        .stat-label { color: #666; margin-top: 5px; }

        .actions {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-bottom: 30px;
        }
        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.2s;
        }
        .btn-danger { background: #dc3545; color: white; }
        .btn-danger:hover { background: #c82333; }
        .btn-warning { background: #ffc107; color: #212529; }
        .btn-warning:hover { background: #e0a800; }
        .btn-primary { background: #667eea; color: white; }
        .btn-primary:hover { background: #5a6fd6; }

        table {
            width: 100%;
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }
        th, td {
            padding: 12px 16px;
            text-align: right;
            border-bottom: 1px solid #eee;
        }
        th { background: #f8f9fa; font-weight: 600; color: #555; }
        tr:hover { background: #f8f9fa; }

        .status-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 500;
        }
        .status-pending { background: #fff3cd; color: #856404; }
        .status-sent { background: #d4edda; color: #155724; }
        .status-cancelled { background: #f8d7da; color: #721c24; }
        .status-read { background: #cce5ff; color: #004085; }
        .status-unread { background: #fff3cd; color: #856404; }

        .delete-btn {
            background: #dc3545;
            color: white;
            border: none;
            padding: 4px 10px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
        }
        .delete-btn:hover { background: #c82333; }

        .warning-box {
            background: #fff3cd;
            border: 1px solid #ffc107;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>ניהול ומחיקת התראות</h1>

        <?php if ($message): ?>
            <div class="message <?= $messageType ?>"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>

        <div class="stats">
            <div class="stat-card">
                <div class="stat-number"><?= $counts['scheduled'] ?></div>
                <div class="stat-label">התראות מתוזמנות</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= $counts['push'] ?></div>
                <div class="stat-label">התראות Push</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= $counts['push_unread'] ?></div>
                <div class="stat-label">לא נקראו</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= $counts['approvals'] ?></div>
                <div class="stat-label">רשומות אישור</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= $counts['deliveries'] ?></div>
                <div class="stat-label">Deliveries</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= $counts['logs'] ?></div>
                <div class="stat-label">לוגים</div>
            </div>
        </div>

        <div class="warning-box">
            <strong>שים לב:</strong> פעולות מחיקה הן בלתי הפיכות!
        </div>

        <div class="actions">
            <form method="POST" style="display:inline" onsubmit="return confirm('למחוק את כל ההתראות מכל הטבלאות?')">
                <input type="hidden" name="action" value="delete_all">
                <button type="submit" class="btn btn-danger">מחק הכל</button>
            </form>

            <form method="POST" style="display:inline" onsubmit="return confirm('לרוקן את כל הטבלאות ולאפס מספור? זו פעולה בלתי הפיכה!')">
                <input type="hidden" name="action" value="truncate_all">
                <button type="submit" class="btn btn-danger">TRUNCATE הכל (איפוס מלא)</button>
            </form>

            <form method="POST" style="display:inline" onsubmit="return confirm('למחוק את כל ההתראות המתוזמנות + זרועות?')">
                <input type="hidden" name="action" value="delete_all_scheduled">
                <button type="submit" class="btn btn-warning">מחק מתוזמנות (<?= $counts['scheduled'] ?>)</button>
            </form>

            <form method="POST" style="display:inline" onsubmit="return confirm('למחוק את כל התראות ה-Push?')">
                <input type="hidden" name="action" value="delete_all_push">
                <button type="submit" class="btn btn-warning">מחק Push (<?= $counts['push'] ?>)</button>
            </form>

            <form method="POST" style="display:inline">
                <input type="hidden" name="action" value="mark_all_read">
                <button type="submit" class="btn btn-primary">סמן הכל כנקרא</button>
            </form>
        </div>

        <h2>התראות מתוזמנות אחרונות (<?= count($recentScheduled) ?>)</h2>
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>כותרת</th>
                    <th>סוג</th>
                    <th>סטטוס</th>
                    <th>נוצר</th>
                    <th>פעולות</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($recentScheduled as $n): ?>
                <tr>
                    <td><?= $n['id'] ?></td>
                    <td><?= htmlspecialchars($n['title']) ?></td>
                    <td><?= $n['notification_type'] ?></td>
                    <td><span class="status-badge status-<?= $n['status'] ?>"><?= $n['status'] ?></span></td>
                    <td><?= $n['created_at'] ?></td>
                    <td>
                        <form method="POST" style="display:inline" onsubmit="return confirm('למחוק?')">
                            <input type="hidden" name="action" value="delete_specific">
                            <input type="hidden" name="table" value="scheduled_notifications">
                            <input type="hidden" name="id" value="<?= $n['id'] ?>">
                            <button type="submit" class="delete-btn">מחק</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($recentScheduled)): ?>
                <tr><td colspan="6" style="text-align:center;color:#999">אין התראות</td></tr>
                <?php endif; ?>
            </tbody>
        </table>

        <h2>התראות Push אחרונות (<?= count($recentPush) ?>)</h2>
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>כותרת</th>
                    <th>משתמש</th>
                    <th>סטטוס</th>
                    <th>נוצר</th>
                    <th>פעולות</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($recentPush as $n): ?>
                <tr>
                    <td><?= $n['id'] ?></td>
                    <td><?= htmlspecialchars($n['title']) ?></td>
                    <td><?= htmlspecialchars($n['user_name'] ?? 'ID: ' . $n['user_id']) ?></td>
                    <td><span class="status-badge status-<?= $n['is_read'] ? 'read' : 'unread' ?>"><?= $n['is_read'] ? 'נקרא' : 'לא נקרא' ?></span></td>
                    <td><?= $n['created_at'] ?></td>
                    <td>
                        <form method="POST" style="display:inline" onsubmit="return confirm('למחוק?')">
                            <input type="hidden" name="action" value="delete_specific">
                            <input type="hidden" name="table" value="push_notifications">
                            <input type="hidden" name="id" value="<?= $n['id'] ?>">
                            <button type="submit" class="delete-btn">מחק</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($recentPush)): ?>
                <tr><td colspan="6" style="text-align:center;color:#999">אין התראות</td></tr>
                <?php endif; ?>
            </tbody>
        </table>

        <p style="text-align:center;color:#999;margin-top:30px">
            <a href="/dashboard/dashboards/cemeteries/" style="color:#667eea">חזרה לדשבורד</a>
        </p>
    </div>
</body>
</html>
