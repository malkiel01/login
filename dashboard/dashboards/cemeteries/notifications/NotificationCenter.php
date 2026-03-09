<?php
/**
 * NotificationCenter - מוקד התראות חכם
 * מנהל את תור ההתראות לכל משתמש
 *
 * @version 1.0.0
 */

require_once $_SERVER['DOCUMENT_ROOT'] . '/dashboard/dashboards/cemeteries/config.php';

class NotificationCenter
{
    private PDO $conn;
    private int $userId;

    /** מקסימום התראות לסשן */
    const MAX_PER_SESSION = 5;

    /** זמן מינימלי (שעות) בין התעלמויות */
    const DISMISS_COOLDOWN_HOURS = 5;

    /** מפתח sessionStorage למעקב כמות */
    const SESSION_COUNT_KEY = 'notification_shown_count';

    public function __construct(int $userId)
    {
        $this->conn = getDBConnection();
        $this->userId = $userId;
    }

    /**
     * קבלת ההתראה הבאה בתור להצגה
     * סדר: מהחדשה לישנה
     * מסנן: לא נקראו + לא נדחו ב-5 שעות האחרונות
     *
     * @return array|null נתוני ההתראה או null אם אין
     */
    public function getNextNotification(): ?array
    {
        $sql = "
            SELECT
                pn.id,
                pn.scheduled_notification_id,
                pn.title,
                pn.body,
                pn.url,
                pn.created_at,
                COALESCE(sn.notification_type, 'info') as notification_type,
                COALESCE(sn.requires_approval, 0) as requires_approval,
                sn.approval_message,
                na.status as approval_status,
                na.responded_at as approval_responded_at,
                pn.dismissed_at
            FROM push_notifications pn
            LEFT JOIN scheduled_notifications sn ON sn.id = pn.scheduled_notification_id
            LEFT JOIN notification_approvals na
                ON na.notification_id = pn.scheduled_notification_id
                AND na.user_id = pn.user_id
            WHERE pn.user_id = :userId
              AND pn.is_read = 0
              AND (
                  pn.dismissed_at IS NULL
                  OR pn.dismissed_at < DATE_SUB(NOW(), INTERVAL :cooldown HOUR)
              )
            ORDER BY pn.created_at DESC
            LIMIT 1
        ";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute([
            ':userId' => $this->userId,
            ':cooldown' => self::DISMISS_COOLDOWN_HOURS
        ]);

        $notification = $stmt->fetch(PDO::FETCH_ASSOC);
        return $notification ?: null;
    }

    /**
     * קבלת התראה ספציפית לפי ID
     * @param int $notificationId - push_notifications.id
     * @return array|null
     */
    public function getNotificationById(int $notificationId): ?array
    {
        $sql = "
            SELECT
                pn.id,
                pn.scheduled_notification_id,
                pn.title,
                pn.body,
                pn.url,
                pn.created_at,
                COALESCE(sn.notification_type, 'info') as notification_type,
                COALESCE(sn.requires_approval, 0) as requires_approval,
                sn.approval_message,
                na.status as approval_status,
                na.responded_at as approval_responded_at
            FROM push_notifications pn
            LEFT JOIN scheduled_notifications sn ON sn.id = pn.scheduled_notification_id
            LEFT JOIN notification_approvals na
                ON na.notification_id = pn.scheduled_notification_id
                AND na.user_id = pn.user_id
            WHERE pn.id = :notificationId
              AND pn.user_id = :userId
            LIMIT 1
        ";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute([
            ':notificationId' => $notificationId,
            ':userId' => $this->userId
        ]);

        $notification = $stmt->fetch(PDO::FETCH_ASSOC);
        return $notification ?: null;
    }

    /**
     * ספירת התראות eligible (לא נקראו + לא נדחו לאחרונה)
     * @return int
     */
    public function countEligible(): int
    {
        $sql = "
            SELECT COUNT(*) as cnt
            FROM push_notifications pn
            WHERE pn.user_id = :userId
              AND pn.is_read = 0
              AND (
                  pn.dismissed_at IS NULL
                  OR pn.dismissed_at < DATE_SUB(NOW(), INTERVAL :cooldown HOUR)
              )
        ";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute([
            ':userId' => $this->userId,
            ':cooldown' => self::DISMISS_COOLDOWN_HOURS
        ]);

        return (int)$stmt->fetchColumn();
    }

    /**
     * סימון התראה כנקראה (ענה עליה)
     * @param int $notificationId - push_notifications.id
     * @return bool
     */
    public function markAsRead(int $notificationId): bool
    {
        $sql = "
            UPDATE push_notifications
            SET is_read = 1
            WHERE id = :id AND user_id = :userId
        ";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute([
            ':id' => $notificationId,
            ':userId' => $this->userId
        ]);

        return $stmt->rowCount() > 0;
    }

    /**
     * סימון התראה כנדחתה (לחץ חזרה / התעלם)
     * @param int $notificationId - push_notifications.id
     * @return bool
     */
    public function markAsDismissed(int $notificationId): bool
    {
        $sql = "
            UPDATE push_notifications
            SET dismissed_at = NOW()
            WHERE id = :id AND user_id = :userId AND is_read = 0
        ";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute([
            ':id' => $notificationId,
            ':userId' => $this->userId
        ]);

        return $stmt->rowCount() > 0;
    }

    /**
     * קבלת URL של דף ההתראה המתאים
     * @param array $notification - נתוני ההתראה
     * @return string URL
     */
    public static function getNotificationPageUrl(array $notification): string
    {
        $base = '/dashboard/dashboards/cemeteries/notifications/';
        $id = $notification['id'];

        if (!empty($notification['requires_approval']) && $notification['requires_approval'] == 1) {
            return $base . 'notification-action.php?id=' . $id;
        }

        return $base . 'notification-info.php?id=' . $id;
    }

    /**
     * האם יש התראות eligible להצגה?
     * @return bool
     */
    public function hasEligibleNotifications(): bool
    {
        return $this->countEligible() > 0;
    }

    /**
     * יצירת סקריפט JS לדשבורד - בדיקת התראות והפניה אחרי 5 שניות
     * @return string
     */
    public function getDashboardScript(): string
    {
        $count = $this->countEligible();

        if ($count === 0) {
            return '<!-- No eligible notifications -->';
        }

        $next = $this->getNextNotification();
        if (!$next) {
            return '<!-- No next notification -->';
        }

        $url = self::getNotificationPageUrl($next);
        $maxPerSession = self::MAX_PER_SESSION;

        return <<<JS
<script>
(function() {
    // מוקד התראות חכם - בדיקה והפניה
    var countKey = 'notification_shown_count_{$this->userId}';
    var shown = parseInt(sessionStorage.getItem(countKey) || '0', 10);

    if (shown >= {$maxPerSession}) {
        console.log('[NotificationCenter] Session limit reached (' + shown + '/{$maxPerSession})');
        return;
    }

    console.log('[NotificationCenter] {$count} eligible notifications. Redirecting in 5s...');

    var timerId = setTimeout(function() {
        sessionStorage.setItem(countKey, (shown + 1).toString());
        window.location.href = '{$url}';
    }, 5000);

    // אם המשתמש מנווט ידנית - בטל את הטיימר
    window.addEventListener('beforeunload', function() {
        clearTimeout(timerId);
    });
})();
</script>
JS;
    }
}
