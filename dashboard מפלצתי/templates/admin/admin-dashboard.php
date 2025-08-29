<?php
/**
 * Admin Dashboard Template
 */
?>

<div class="admin-dashboard">
    <!-- Page Header -->
    <div class="page-header">
        <h1>דשבורד מנהל</h1>
        <div class="page-actions">
            <button class="btn btn-primary" onclick="AdminActions.createUser()">
                <i class="fas fa-user-plus"></i>
                משתמש חדש
            </button>
            <button class="btn btn-secondary" onclick="AdminActions.exportData()">
                <i class="fas fa-download"></i>
                ייצוא נתונים
            </button>
        </div>
    </div>

    <!-- Stats Overview -->
    <div class="stats-grid">
        <?php AdminWidgets::renderAdvancedStats($stats); ?>
    </div>

    <!-- Main Grid -->
    <div class="dashboard-grid">
        <!-- System Status -->
        <div class="dashboard-card">
            <div class="card-header">
                <h3><i class="fas fa-server"></i> מצב המערכת</h3>
            </div>
            <div class="card-body">
                <div class="system-status">
                    <div class="status-item">
                        <span class="status-label">PHP Version:</span>
                        <span class="status-value"><?php echo $systemStatus['php_version']; ?></span>
                    </div>
                    <div class="status-item">
                        <span class="status-label">MySQL Version:</span>
                        <span class="status-value"><?php echo $systemStatus['mysql_version']; ?></span>
                    </div>
                    <div class="status-item">
                        <span class="status-label">Server:</span>
                        <span class="status-value"><?php echo $systemStatus['server_software']; ?></span>
                    </div>
                    <div class="status-item">
                        <span class="status-label">SSL:</span>
                        <span class="status-value">
                            <?php if ($systemStatus['ssl_enabled']): ?>
                                <span class="badge badge-success">מאובטח</span>
                            <?php else: ?>
                                <span class="badge badge-warning">לא מאובטח</span>
                            <?php endif; ?>
                        </span>
                    </div>
                    <div class="status-item">
                        <span class="status-label">Memory Limit:</span>
                        <span class="status-value"><?php echo $systemStatus['memory_limit']; ?></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Users -->
        <div class="dashboard-card">
            <div class="card-header">
                <h3><i class="fas fa-users"></i> משתמשים אחרונים</h3>
                <a href="/dashboard/users" class="view-all">צפה בכולם</a>
            </div>
            <div class="card-body">
                <div class="users-list">
                    <?php foreach ($recentUsers as $user): ?>
                    <div class="user-item">
                        <div class="user-avatar">
                            <?php if ($user['avatar']): ?>
                                <img src="<?php echo htmlspecialchars($user['avatar']); ?>" alt="">
                            <?php else: ?>
                                <div class="avatar-placeholder">
                                    <?php echo strtoupper(substr($user['username'], 0, 1)); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="user-info">
                            <div class="user-name"><?php echo htmlspecialchars($user['name'] ?? $user['username']); ?></div>
                            <div class="user-email"><?php echo htmlspecialchars($user['email']); ?></div>
                        </div>
                        <div class="user-meta">
                            <span class="badge badge-<?php echo $user['role']; ?>">
                                <?php echo $user['role']; ?>
                            </span>
                            <div class="user-date">
                                <?php echo Dashboard\Utils::timeAgo($user['created_at']); ?>
                            </div>
                        </div>
                        <div class="user-actions">
                            <button class="btn-icon" onclick="AdminActions.editUser(<?php echo $user['id']; ?>)">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button class="btn-icon danger" onclick="AdminActions.deleteUser(<?php echo $user['id']; ?>)">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Activity Log -->
        <?php AdminWidgets::renderRealtimeActivity($activityLog); ?>

        <!-- Quick Actions -->
        <?php AdminWidgets::renderQuickActions(); ?>

        <!-- Performance Chart -->
        <?php AdminWidgets::renderPerformanceChart(); ?>
    </div>
</div>

<!-- Admin JavaScript -->
<script src="/dashboard/modules/admin/assets/admin.js"></script>
