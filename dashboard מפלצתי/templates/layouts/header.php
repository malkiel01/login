<!DOCTYPE html>
<html dir="rtl" lang="he">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    
    <title><?php echo htmlspecialchars($pageTitle ?? 'דשבורד'); ?></title>
    
    <!-- Meta Tags -->
    <meta name="description" content="מערכת ניהול דשבורד מתקדמת">
    <meta name="theme-color" content="#667eea">
    
    <!-- Preloads -->
    <?php echo $preloads ?? DependencyLoader::renderPreloads(); ?>
    
    <!-- Critical CSS -->
    <?php echo $inlineCSS ?? DependencyLoader::renderInlineCSS($userType ?? 'user'); ?>
    
    <!-- Dependencies -->
    <?php echo $dependencies ?? DependencyLoader::renderHTML($userType ?? 'user'); ?>
    
    <!-- PWA -->
    <?php if (PWA_ENABLED && function_exists('getPWAHeaders')): ?>
        <?php echo getPWAHeaders(); ?>
    <?php endif; ?>
    
    <!-- CSRF Token -->
    <meta name="csrf-token" content="<?php echo generateCSRFToken(); ?>">
    
    <!-- API Config -->
    <script>
        window.dashboardConfig = {
            apiUrl: '<?php echo DASHBOARD_URL; ?>/api',
            csrfToken: '<?php echo generateCSRFToken(); ?>',
            userId: <?php echo $_SESSION['user_id'] ?? 'null'; ?>,
            userType: '<?php echo $userType ?? 'guest'; ?>',
            permissions: <?php echo json_encode($_SESSION['permissions'] ?? []); ?>
        };
    </script>
</head>
<body class="dashboard-body <?php echo $userType ?? 'user'; ?>-theme">
    
    <!-- Loading Screen -->
    <div id="loadingScreen" class="loading-screen">
        <div class="spinner"></div>
        <div>טוען...</div>
    </div>
    
    <!-- Main Container -->
    <div class="dashboard-container">
        
        <!-- Header -->
        <header class="dashboard-header">
            <div class="header-content">
                <div class="header-right">
                    <!-- Logo -->
                    <div class="logo">
                        <i class="fas fa-th-large"></i>
                        <span><?php echo SITE_NAME; ?></span>
                    </div>
                    
                    <!-- Navigation -->
                    <nav class="main-nav">
                        <?php include __DIR__ . '/../components/navigation.php'; ?>
                    </nav>
                </div>
                
                <div class="header-left">
                    <!-- Search -->
                    <div class="search-box">
                        <input type="search" placeholder="חיפוש..." id="globalSearch">
                        <i class="fas fa-search"></i>
                    </div>
                    
                    <!-- Notifications -->
                    <div class="notifications-dropdown">
                        <button class="notification-btn">
                            <i class="fas fa-bell"></i>
                            <span class="badge" id="notificationCount">0</span>
                        </button>
                        <div class="dropdown-content" id="notificationDropdown">
                            <!-- נטען דינמית -->
                        </div>
                    </div>
                    
                    <!-- User Menu -->
                    <div class="user-menu">
                        <button class="user-btn">
                            <?php if (!empty($user['avatar'])): ?>
                                <img src="<?php echo htmlspecialchars($user['avatar']); ?>" alt="Avatar">
                            <?php else: ?>
                                <div class="avatar-placeholder">
                                    <?php echo strtoupper(substr($user['username'] ?? 'U', 0, 1)); ?>
                                </div>
                            <?php endif; ?>
                            <span><?php echo htmlspecialchars($user['name'] ?? $user['username'] ?? 'משתמש'); ?></span>
                            <i class="fas fa-chevron-down"></i>
                        </button>
                        <div class="dropdown-content">
                            <a href="/dashboard/profile">
                                <i class="fas fa-user"></i> הפרופיל שלי
                            </a>
                            <a href="/dashboard/settings">
                                <i class="fas fa-cog"></i> הגדרות
                            </a>
                            <hr>
                            <a href="/auth/logout.php" class="logout">
                                <i class="fas fa-sign-out-alt"></i> יציאה
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </header>
        
        <!-- Sidebar -->
        <aside class="dashboard-sidebar" id="sidebar">
            <?php include __DIR__ . '/../components/sidebar.php'; ?>
        </aside>
        
        <!-- Main Content -->
        <main class="dashboard-main">
            <!-- Breadcrumbs -->
            <div class="breadcrumbs">
                <?php include __DIR__ . '/../components/breadcrumbs.php'; ?>
            </div>
            
            <!-- Content Area -->
            <div class="content-area">
