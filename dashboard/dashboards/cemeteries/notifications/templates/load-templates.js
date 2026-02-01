/**
 * Notification Templates Loader
 * Loads all notification templates dynamically
 *
 * Usage: Include this file in your page to load all templates
 * <script src="/dashboard/dashboards/cemeteries/notifications/templates/load-templates.js"></script>
 *
 * @version 1.0.0
 */

(function() {
    const basePath = '/dashboard/dashboards/cemeteries/notifications/templates/';

    const templates = [
        'notification-templates.js',  // Main manager (must be first)
        'info-notification.js',
        'approval-notification.js',
        'entity-approval-notification.js'
    ];

    // Load scripts sequentially
    function loadScript(src) {
        return new Promise((resolve, reject) => {
            const script = document.createElement('script');
            script.src = src;
            script.onload = resolve;
            script.onerror = reject;
            document.head.appendChild(script);
        });
    }

    async function loadAllTemplates() {
        for (const template of templates) {
            try {
                await loadScript(basePath + template);
            } catch (e) {
                console.error('[TemplateLoader] Failed to load:', template, e);
            }
        }
        console.log('[TemplateLoader] All templates loaded');

        // Fire ready event
        window.dispatchEvent(new CustomEvent('notificationTemplatesReady'));
    }

    // Start loading
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', loadAllTemplates);
    } else {
        loadAllTemplates();
    }
})();
