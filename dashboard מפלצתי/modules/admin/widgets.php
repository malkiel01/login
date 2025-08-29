<?php
/**
 * Admin Widgets
 * וידג'טים מתקדמים למנהלים
 */

class AdminWidgets {
    
    /**
     * וידג'ט סטטיסטיקות מתקדמות
     */
    public static function renderAdvancedStats($stats) {
        ?>
        <div class="widget advanced-stats">
            <h3><i class="fas fa-chart-line"></i> סטטיסטיקות מתקדמות</h3>
            <div class="stats-grid">
                <div class="stat-item">
                    <div class="stat-value"><?php echo number_format($stats['total_users']); ?></div>
                    <div class="stat-label">סה"כ משתמשים</div>
                    <div class="stat-change positive">+<?php echo $stats['new_users_week']; ?> השבוע</div>
                </div>
                <div class="stat-item">
                    <div class="stat-value"><?php echo $stats['active_sessions']; ?></div>
                    <div class="stat-label">משתמשים מחוברים</div>
                    <div class="stat-indicator online"></div>
                </div>
                <div class="stat-item">
                    <div class="stat-value"><?php echo $stats['server_load']; ?></div>
                    <div class="stat-label">עומס שרת</div>
                    <div class="progress-bar">
                        <div class="progress" style="width: <?php echo min($stats['server_load'] * 20, 100); ?>%"></div>
                    </div>
                </div>
                <div class="stat-item">
                    <div class="stat-value"><?php echo $stats['disk_usage']; ?>%</div>
                    <div class="stat-label">שימוש בדיסק</div>
                    <div class="progress-bar">
                        <div class="progress" style="width: <?php echo $stats['disk_usage']; ?>%"></div>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * וידג'ט פעילות בזמן אמת
     */
    public static function renderRealtimeActivity($activities) {
        ?>
        <div class="widget realtime-activity">
            <h3><i class="fas fa-pulse"></i> פעילות בזמן אמת</h3>
            <div class="activity-stream" id="activityStream">
                <?php foreach ($activities as $activity): ?>
                <div class="activity-item" data-id="<?php echo $activity['id']; ?>">
                    <div class="activity-icon">
                        <?php echo self::getActivityIcon($activity['action']); ?>
                    </div>
                    <div class="activity-content">
                        <div class="activity-user">
                            <?php echo htmlspecialchars($activity['username']); ?>
                        </div>
                        <div class="activity-action">
                            <?php echo self::getActivityDescription($activity['action']); ?>
                        </div>
                        <div class="activity-time" data-timestamp="<?php echo strtotime($activity['created_at']); ?>">
                            <?php echo self::timeAgo($activity['created_at']); ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
    }
    
    /**
     * וידג'ט ניהול מהיר
     */
    public static function renderQuickActions() {
        ?>
        <div class="widget quick-actions">
            <h3><i class="fas fa-bolt"></i> פעולות מהירות</h3>
            <div class="actions-grid">
                <button class="action-btn" onclick="AdminActions.createUser()">
                    <i class="fas fa-user-plus"></i>
                    <span>משתמש חדש</span>
                </button>
                <button class="action-btn" onclick="AdminActions.exportData()">
                    <i class="fas fa-download"></i>
                    <span>ייצוא נתונים</span>
                </button>
                <button class="action-btn" onclick="AdminActions.clearCache()">
                    <i class="fas fa-broom"></i>
                    <span>ניקוי מטמון</span>
                </button>
                <button class="action-btn" onclick="AdminActions.backup()">
                    <i class="fas fa-database"></i>
                    <span>גיבוי</span>
                </button>
                <button class="action-btn" onclick="AdminActions.viewLogs()">
                    <i class="fas fa-file-alt"></i>
                    <span>צפייה בלוגים</span>
                </button>
                <button class="action-btn" onclick="AdminActions.settings()">
                    <i class="fas fa-cog"></i>
                    <span>הגדרות</span>
                </button>
            </div>
        </div>
        <?php
    }
    
    /**
     * וידג'ט גרף ביצועים
     */
    public static function renderPerformanceChart() {
        ?>
        <div class="widget performance-chart">
            <h3><i class="fas fa-tachometer-alt"></i> ביצועי מערכת</h3>
            <canvas id="performanceChart"></canvas>
            <script>
            // נטען ב-admin.js
            AdminCharts.initPerformanceChart();
            </script>
        </div>
        <?php
    }
    
    /**
     * קבלת אייקון לפעילות
     */
    private static function getActivityIcon($action) {
        $icons = [
            'login' => '<i class="fas fa-sign-in-alt text-success"></i>',
            'logout' => '<i class="fas fa-sign-out-alt text-warning"></i>',
            'create_user' => '<i class="fas fa-user-plus text-primary"></i>',
            'update_user' => '<i class="fas fa-user-edit text-info"></i>',
            'delete_user' => '<i class="fas fa-user-times text-danger"></i>',
            'export_data' => '<i class="fas fa-download text-secondary"></i>',
            'clear_cache' => '<i class="fas fa-broom text-warning"></i>',
            'database_backup' => '<i class="fas fa-database text-success"></i>'
        ];
        
        return $icons[$action] ?? '<i class="fas fa-circle text-muted"></i>';
    }
    
    /**
     * קבלת תיאור פעילות
     */
    private static function getActivityDescription($action) {
        $descriptions = [
            'login' => 'התחבר למערכת',
            'logout' => 'התנתק מהמערכת',
            'create_user' => 'יצר משתמש חדש',
            'update_user' => 'עדכן פרטי משתמש',
            'delete_user' => 'מחק משתמש',
            'export_data' => 'ייצא נתונים',
            'clear_cache' => 'ניקה מטמון',
            'database_backup' => 'ביצע גיבוי'
        ];
        
        return $descriptions[$action] ?? $action;
    }
    
    /**
     * חישוב זמן יחסי
     */
    private static function timeAgo($datetime) {
        $timestamp = strtotime($datetime);
        $diff = time() - $timestamp;
        
        if ($diff < 60) {
            return 'לפני רגע';
        } elseif ($diff < 3600) {
            $minutes = round($diff / 60);
            return "לפני {$minutes} דקות";
        } elseif ($diff < 86400) {
            $hours = round($diff / 3600);
            return "לפני {$hours} שעות";
        } else {
            $days = round($diff / 86400);
            return "לפני {$days} ימים";
        }
    }
}
?>