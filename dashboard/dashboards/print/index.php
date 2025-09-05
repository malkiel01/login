<?php
// הגדרות בסיסיות
require_once 'config.php';
?>
<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $config['title'] ?? 'PDF Text Printer' ?></title>
    
    <!-- CSS Files -->
    <link rel="stylesheet" href="assets/css/main.css">
    <link rel="stylesheet" href="assets/css/components.css">
    <link rel="stylesheet" href="assets/css/debug.css">
</head>
<body>
    <div class="container">
        <!-- Header -->
        <?php include 'includes/header.php'; ?>
        
        <div class="main-content">
            <!-- Status Message -->
            <div id="statusMessage" class="status-message"></div>
            
            <!-- Settings Section -->
            <?php include 'includes/settings-section.php'; ?>
            
            <!-- Values Input Section -->
            <?php include 'includes/values-section.php'; ?>
            
            <!-- Values List Section -->
            <?php include 'includes/list-section.php'; ?>
            
            <!-- Preview Canvas (Future) -->
            <?php if ($config['enable_preview'] ?? false): ?>
                <?php include 'includes/preview-canvas.php'; ?>
            <?php endif; ?>
            
            <!-- Debug Console -->
            <?php include 'includes/debug-console.php'; ?>
        </div>
    </div>
    
    <!-- Modals -->
    <?php include 'includes/json-modal.php'; ?>
    <?php include 'includes/export-modal.php'; ?>
    
    <!-- JavaScript Files -->
    <script src="assets/js/app.js"></script>
    <script src="assets/js/pdf-generator.js"></script>
    <script src="assets/js/ui-handlers.js"></script>
    <script src="assets/js/font-manager.js"></script>
    <script src="assets/js/debug.js"></script>
    
    <?php if ($config['enable_preview'] ?? false): ?>
        <script src="assets/js/preview.js"></script>
    <?php endif; ?>
</body>
</html>