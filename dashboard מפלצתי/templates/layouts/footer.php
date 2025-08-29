            </div>
            <!-- End Content Area -->
        </main>
        <!-- End Main Content -->
        
    </div>
    <!-- End Dashboard Container -->
    
    <!-- Footer -->
    <footer class="dashboard-footer">
        <div class="footer-content">
            <div class="footer-right">
                <span>&copy; <?php echo date('Y'); ?> <?php echo SITE_NAME; ?>. כל הזכויות שמורות.</span>
            </div>
            <div class="footer-left">
                <span>גרסה <?php echo DASHBOARD_VERSION; ?></span>
                <span class="separator">|</span>
                <span id="serverTime"><?php echo date('H:i:s'); ?></span>
            </div>
        </div>
    </footer>
    
    <!-- Modals Container -->
    <div id="modalsContainer"></div>
    
    <!-- Toast Notifications -->
    <div id="toastContainer" class="toast-container"></div>
    
    <!-- Back to Top -->
    <button id="backToTop" class="back-to-top" aria-label="חזרה למעלה">
        <i class="fas fa-arrow-up"></i>
    </button>
    
    <!-- JavaScript Data -->
    <script>
        window.dashboardData = <?php echo json_encode([
            'user' => $user ?? null,
            'stats' => $stats ?? null,
            'config' => [
                'sessionTimeout' => SESSION_TIMEOUT,
                'apiRateLimit' => API_RATE_LIMIT,
                'locale' => 'he-IL',
                'timezone' => date_default_timezone_get()
            ]
        ]); ?>;
    </script>
    
    <!-- Core JavaScript -->
    <script src="<?php echo DASHBOARD_URL; ?>/assets/js/core.js" defer></script>
    
    <!-- Page Specific JavaScript -->
    <?php if (isset($pageScripts)): ?>
        <?php foreach ($pageScripts as $script): ?>
            <script src="<?php echo $script; ?>" defer></script>
        <?php endforeach; ?>
    <?php endif; ?>
    
    <!-- PWA Scripts -->
    <?php if (PWA_ENABLED && function_exists('getPWAScripts')): ?>
        <?php echo getPWAScripts(); ?>
    <?php endif; ?>
    
    <!-- Analytics -->
    <?php if (defined('ANALYTICS_ID') && !empty(ANALYTICS_ID)): ?>
        <!-- Google Analytics or other analytics code -->
    <?php endif; ?>
    
</body>
</html>
