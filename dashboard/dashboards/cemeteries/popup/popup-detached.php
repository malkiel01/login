<?php
/**
 * Popup Detached Window
 * עמוד לחלון popup נפרד
 */

// טעינת קונפיג
require_once $_SERVER['DOCUMENT_ROOT'] . '/dashboard/dashboards/cemeteries/config.php';

// קבלת popup ID
$popupId = $_GET['id'] ?? null;

if (!$popupId) {
    die('Error: No popup ID provided');
}
?>
<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Popup Window</title>

    <!-- Popup API -->
    <script src="/dashboard/dashboards/cemeteries/popup/popup-api.js"></script>

    <!-- Dynamic Stylesheets (loaded from state) -->
    <div id="dynamicStylesheets"></div>

    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        html, body {
            width: 100%;
            height: 100%;
            overflow: hidden;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
        }

        body {
            background: #f9fafb;
            display: flex;
            flex-direction: column;
        }

        /* Header */
        .detached-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 12px 16px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        .detached-title {
            font-size: 16px;
            font-weight: 600;
        }

        .detached-controls {
            display: flex;
            gap: 8px;
        }

        .detached-btn {
            width: 28px;
            height: 28px;
            border: none;
            background: rgba(255, 255, 255, 0.15);
            color: white;
            border-radius: 4px;
            cursor: pointer;
            font-size: 18px;
            font-weight: bold;
            transition: background 0.2s ease;
        }

        .detached-btn:hover {
            background: rgba(255, 255, 255, 0.25);
        }

        .detached-close:hover {
            background: #ef4444;
        }

        /* Content */
        .detached-content {
            flex: 1;
            overflow: hidden;
            position: relative;
        }

        .detached-content iframe {
            width: 100%;
            height: 100%;
            border: none;
        }

        /* Loading */
        .detached-loading {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            text-align: center;
            color: #667eea;
            font-size: 14px;
        }

        .detached-loading::before {
            content: '';
            display: block;
            width: 40px;
            height: 40px;
            margin: 0 auto 16px;
            border: 4px solid #e5e7eb;
            border-top-color: #667eea;
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        /* Error */
        .detached-error {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            text-align: center;
            color: #ef4444;
            font-size: 14px;
            padding: 20px;
        }

        .detached-error::before {
            content: '⚠️';
            display: block;
            font-size: 48px;
            margin-bottom: 16px;
        }
    </style>
</head>
<body class="popup-detached-window">

    <!-- Header -->
    <div class="detached-header">
        <span class="detached-title" id="detachedTitle">טוען...</span>
        <div class="detached-controls">
            <button class="detached-btn detached-close" onclick="window.close()" title="סגור">×</button>
        </div>
    </div>

    <!-- Content -->
    <div class="detached-content" id="detachedContent">
        <div class="detached-loading">טוען תוכן...</div>
    </div>

    <script>
        (function() {
            const popupId = '<?php echo htmlspecialchars($popupId); ?>';

            // טעינת מצב מ-localStorage
            function loadState() {
                try {
                    const stateKey = `popup-detached-${popupId}`;
                    const stateJSON = localStorage.getItem(stateKey);

                    if (!stateJSON) {
                        showError('לא נמצא מידע על החלון. ייתכן שהקישור פג תוקף.');
                        return null;
                    }

                    const state = JSON.parse(stateJSON);
                    console.log('[Detached] Loaded state:', state);
                    return state;

                } catch (error) {
                    console.error('[Detached] Error loading state:', error);
                    showError('שגיאה בטעינת המצב: ' + error.message);
                    return null;
                }
            }

            // טעינת stylesheets
            function loadStylesheets(stylesheets) {
                if (!stylesheets || !Array.isArray(stylesheets) || stylesheets.length === 0) {
                    return;
                }

                console.log('[Detached] Loading stylesheets:', stylesheets);

                stylesheets.forEach(href => {
                    const link = document.createElement('link');
                    link.rel = 'stylesheet';
                    link.href = href;
                    document.head.appendChild(link);
                    console.log('[Detached] Loaded stylesheet:', href);
                });
            }

            // טעינת תוכן
            function loadContent(state) {
                const container = document.getElementById('detachedContent');
                const titleElement = document.getElementById('detachedTitle');

                // טען stylesheets
                loadStylesheets(state.config.stylesheets);

                // עדכון כותרת
                titleElement.textContent = state.config.title || 'Popup Window';

                // עדכון title של החלון
                document.title = state.config.title || 'Popup Window';

                // טעינת תוכן לפי סוג
                if (state.config.type === 'iframe') {
                    const iframe = document.createElement('iframe');

                    // העברת popup ID ב-URL
                    const separator = state.config.src.includes('?') ? '&' : '?';
                    iframe.src = `${state.config.src}${separator}popupId=${popupId}`;

                    iframe.onload = function() {
                        console.log('[Detached] Content loaded');

                        // שחזור גלילה
                        if (state.state?.scrollPosition) {
                            try {
                                iframe.contentWindow.scrollTo(
                                    state.state.scrollPosition.x,
                                    state.state.scrollPosition.y
                                );
                            } catch (e) {
                                console.warn('[Detached] Cannot restore scroll:', e);
                            }
                        }
                    };

                    iframe.onerror = function() {
                        showError('שגיאה בטעינת התוכן');
                    };

                    container.innerHTML = '';
                    container.appendChild(iframe);

                } else if (state.config.type === 'html') {
                    container.innerHTML = state.config.content;

                    // שחזור גלילה
                    if (state.state?.scrollPosition) {
                        window.scrollTo(
                            state.state.scrollPosition.x,
                            state.state.scrollPosition.y
                        );
                    }

                } else if (state.config.type === 'ajax') {
                    fetch(state.config.url)
                        .then(response => response.text())
                        .then(html => {
                            container.innerHTML = html;

                            // שחזור גלילה
                            if (state.state?.scrollPosition) {
                                window.scrollTo(
                                    state.state.scrollPosition.x,
                                    state.state.scrollPosition.y
                                );
                            }
                        })
                        .catch(error => {
                            showError('שגיאה בטעינת התוכן: ' + error.message);
                        });
                }
            }

            // הצגת שגיאה
            function showError(message) {
                const container = document.getElementById('detachedContent');
                container.innerHTML = `<div class="detached-error">${message}</div>`;
            }

            // אתחול
            window.addEventListener('DOMContentLoaded', function() {
                console.log('[Detached] Initializing popup:', popupId);

                const state = loadState();
                if (state) {
                    loadContent(state);
                }
            });

            // טיפול בסגירה
            window.addEventListener('beforeunload', function() {
                // ניקוי localStorage
                try {
                    localStorage.removeItem(`popup-detached-${popupId}`);
                } catch (e) {
                    console.error('[Detached] Error cleaning up:', e);
                }
            });

        })();
    </script>

</body>
</html>
