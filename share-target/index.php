<?php
/**
 * Share Target - דף קבלת שיתופים מאפליקציות אחרות
 * עובד עם Web Share Target API
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth/middleware.php';

// בדוק אם המשתמש מחובר
$isAuthenticated = isAuthenticated();
$user = $isAuthenticated ? getCurrentUser() : null;

// קבלת הנתונים משיתוף
$sharedData = [
    'title' => $_POST['title'] ?? $_GET['title'] ?? '',
    'text' => $_POST['text'] ?? $_GET['text'] ?? '',
    'url' => $_POST['url'] ?? $_GET['url'] ?? '',
    'files' => []
];

// טיפול בקבצים
if (!empty($_FILES['files'])) {
    $uploadDir = __DIR__ . '/../uploads/shared/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $files = $_FILES['files'];

    // נרמל למערך אם זה קובץ בודד
    if (!is_array($files['name'])) {
        $files = [
            'name' => [$files['name']],
            'type' => [$files['type']],
            'tmp_name' => [$files['tmp_name']],
            'error' => [$files['error']],
            'size' => [$files['size']]
        ];
    }

    for ($i = 0; $i < count($files['name']); $i++) {
        if ($files['error'][$i] === UPLOAD_ERR_OK) {
            $originalName = $files['name'][$i];
            $tmpName = $files['tmp_name'][$i];
            $mimeType = $files['type'][$i];
            $size = $files['size'][$i];

            // יצירת שם ייחודי
            $ext = pathinfo($originalName, PATHINFO_EXTENSION);
            $uniqueName = uniqid('share_') . '_' . time() . '.' . $ext;
            $targetPath = $uploadDir . $uniqueName;

            if (move_uploaded_file($tmpName, $targetPath)) {
                $sharedData['files'][] = [
                    'name' => $originalName,
                    'path' => '/uploads/shared/' . $uniqueName,
                    'type' => $mimeType,
                    'size' => $size
                ];
            }
        }
    }
}

// המר לJSON לשימוש ב-JS
$sharedDataJson = json_encode($sharedData, JSON_UNESCAPED_UNICODE);
?>
<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="theme-color" content="#667eea">
    <title>שיתוף - חברה קדישא</title>
    <link rel="manifest" href="/manifest.json">
    <link rel="icon" href="/pwa/icons/ios/180.png">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            min-height: 100dvh;
            display: flex;
            flex-direction: column;
        }

        .share-header {
            padding: 20px;
            padding-top: max(20px, env(safe-area-inset-top));
            text-align: center;
            color: white;
        }

        .share-header h1 {
            font-size: 24px;
            margin-bottom: 8px;
        }

        .share-header p {
            opacity: 0.9;
            font-size: 14px;
        }

        .share-container {
            flex: 1;
            background: white;
            border-radius: 24px 24px 0 0;
            padding: 24px;
            overflow-y: auto;
        }

        /* תצוגה מקדימה של התוכן המשותף */
        .shared-preview {
            background: #f8f9fa;
            border-radius: 16px;
            padding: 16px;
            margin-bottom: 24px;
        }

        .shared-preview-title {
            font-size: 12px;
            color: #666;
            margin-bottom: 8px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .shared-content-title {
            font-weight: 600;
            font-size: 16px;
            margin-bottom: 8px;
            color: #333;
        }

        .shared-content-text {
            color: #666;
            font-size: 14px;
            margin-bottom: 8px;
            word-break: break-word;
        }

        .shared-content-url {
            color: #007AFF;
            font-size: 13px;
            text-decoration: none;
            display: block;
            word-break: break-all;
        }

        .shared-files {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 12px;
        }

        .shared-file {
            display: flex;
            align-items: center;
            gap: 8px;
            background: white;
            padding: 10px 14px;
            border-radius: 10px;
            font-size: 13px;
        }

        .shared-file-icon {
            width: 32px;
            height: 32px;
            background: #007AFF;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
        }

        .shared-file-image {
            width: 50px;
            height: 50px;
            object-fit: cover;
            border-radius: 8px;
        }

        /* בחירת יעד */
        .destination-section {
            margin-bottom: 24px;
        }

        .section-title {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 16px;
            color: #333;
        }

        .destinations-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
            gap: 12px;
        }

        .destination-card {
            background: #f8f9fa;
            border: 2px solid transparent;
            border-radius: 16px;
            padding: 16px;
            text-align: center;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .destination-card:hover {
            background: #f0f0f5;
        }

        .destination-card.selected {
            border-color: #007AFF;
            background: rgba(0, 122, 255, 0.05);
        }

        .destination-icon {
            width: 48px;
            height: 48px;
            margin: 0 auto 10px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
        }

        .destination-icon svg {
            width: 24px;
            height: 24px;
            fill: currentColor;
        }

        .destination-name {
            font-size: 14px;
            font-weight: 500;
            color: #333;
        }

        .destination-desc {
            font-size: 11px;
            color: #999;
            margin-top: 4px;
        }

        /* תיקיות אחרונות */
        .recent-section {
            margin-bottom: 24px;
        }

        .recent-items {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .recent-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 14px;
            background: #f8f9fa;
            border-radius: 12px;
            cursor: pointer;
            transition: background 0.2s ease;
        }

        .recent-item:hover {
            background: #f0f0f5;
        }

        .recent-item.selected {
            background: rgba(0, 122, 255, 0.1);
            box-shadow: inset 0 0 0 2px #007AFF;
        }

        .recent-icon {
            width: 40px;
            height: 40px;
            background: #e0e0e0;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #666;
        }

        .recent-info {
            flex: 1;
        }

        .recent-name {
            font-weight: 500;
            font-size: 14px;
            color: #333;
        }

        .recent-path {
            font-size: 12px;
            color: #999;
        }

        /* כפתורים */
        .actions {
            display: flex;
            gap: 12px;
            padding-bottom: env(safe-area-inset-bottom);
        }

        .btn {
            flex: 1;
            padding: 16px;
            border: none;
            border-radius: 14px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .btn-primary {
            background: linear-gradient(135deg, #007AFF 0%, #5856D6 100%);
            color: white;
        }

        .btn-primary:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .btn-secondary {
            background: #f0f0f5;
            color: #333;
        }

        /* טעינה */
        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(255,255,255,0.95);
            display: none;
            align-items: center;
            justify-content: center;
            flex-direction: column;
            gap: 16px;
            z-index: 1000;
        }

        .loading-overlay.active {
            display: flex;
        }

        .spinner {
            width: 40px;
            height: 40px;
            border: 3px solid #f0f0f0;
            border-top-color: #007AFF;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        /* אם לא מחובר */
        .login-required {
            text-align: center;
            padding: 40px 20px;
        }

        .login-required-icon {
            width: 80px;
            height: 80px;
            margin: 0 auto 20px;
            background: #f0f0f5;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .login-required h2 {
            font-size: 20px;
            margin-bottom: 10px;
            color: #333;
        }

        .login-required p {
            color: #666;
            margin-bottom: 20px;
        }

        .login-btn {
            display: inline-block;
            padding: 14px 32px;
            background: linear-gradient(135deg, #007AFF 0%, #5856D6 100%);
            color: white;
            text-decoration: none;
            border-radius: 12px;
            font-weight: 600;
        }
    </style>
</head>
<body>
    <div class="share-header">
        <h1>שיתוף לאפליקציה</h1>
        <p>בחר לאן לשמור את התוכן</p>
    </div>

    <div class="share-container">
        <?php if (!$isAuthenticated): ?>
            <!-- לא מחובר -->
            <div class="login-required">
                <div class="login-required-icon">
                    <svg width="40" height="40" viewBox="0 0 24 24" fill="#666">
                        <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 3c1.66 0 3 1.34 3 3s-1.34 3-3 3-3-1.34-3-3 1.34-3 3-3zm0 14.2c-2.5 0-4.71-1.28-6-3.22.03-1.99 4-3.08 6-3.08 1.99 0 5.97 1.09 6 3.08-1.29 1.94-3.5 3.22-6 3.22z"/>
                    </svg>
                </div>
                <h2>נדרשת התחברות</h2>
                <p>כדי לשמור את התוכן המשותף, יש להתחבר לחשבון שלך</p>
                <a href="#" onclick="location.replace('/auth/login.php?redirect=<?= urlencode('/share-target/?' . http_build_query($_GET)) ?>'); return false;" class="login-btn">
                    התחבר
                </a>
            </div>
        <?php else: ?>
            <!-- תצוגה מקדימה -->
            <div class="shared-preview">
                <div class="shared-preview-title">תוכן משותף</div>
                <div id="preview-content">
                    <?php if ($sharedData['title']): ?>
                        <div class="shared-content-title"><?= htmlspecialchars($sharedData['title']) ?></div>
                    <?php endif; ?>
                    <?php if ($sharedData['text']): ?>
                        <div class="shared-content-text"><?= htmlspecialchars($sharedData['text']) ?></div>
                    <?php endif; ?>
                    <?php if ($sharedData['url']): ?>
                        <a href="<?= htmlspecialchars($sharedData['url']) ?>" class="shared-content-url" target="_blank">
                            <?= htmlspecialchars($sharedData['url']) ?>
                        </a>
                    <?php endif; ?>
                    <?php if (!empty($sharedData['files'])): ?>
                        <div class="shared-files">
                            <?php foreach ($sharedData['files'] as $file): ?>
                                <?php if (strpos($file['type'], 'image/') === 0): ?>
                                    <img src="<?= htmlspecialchars($file['path']) ?>" alt="<?= htmlspecialchars($file['name']) ?>" class="shared-file-image">
                                <?php else: ?>
                                    <div class="shared-file">
                                        <div class="shared-file-icon">
                                            <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
                                                <path d="M14,2H6A2,2 0 0,0 4,4V20A2,2 0 0,0 6,22H18A2,2 0 0,0 20,20V8L14,2M18,20H6V4H13V9H18V20Z"/>
                                            </svg>
                                        </div>
                                        <span><?= htmlspecialchars($file['name']) ?></span>
                                    </div>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                    <?php if (empty($sharedData['title']) && empty($sharedData['text']) && empty($sharedData['url']) && empty($sharedData['files'])): ?>
                        <div class="shared-content-text">לא התקבל תוכן לשיתוף</div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- יעדים זמינים -->
            <div class="destination-section">
                <h3 class="section-title">שמור ב:</h3>
                <div class="destinations-grid" id="destinations">
                    <!-- יוזן דינמית -->
                </div>
            </div>

            <!-- תיקיות אחרונות -->
            <div class="recent-section" id="recent-section" style="display:none;">
                <h3 class="section-title">פריטים אחרונים</h3>
                <div class="recent-items" id="recent-items">
                    <!-- יוזן דינמית -->
                </div>
            </div>

            <!-- כפתורים -->
            <div class="actions">
                <button class="btn btn-secondary" onclick="window.close(); history.back();">ביטול</button>
                <button class="btn btn-primary" id="save-btn" disabled>שמור</button>
            </div>
        <?php endif; ?>
    </div>

    <div class="loading-overlay" id="loading">
        <div class="spinner"></div>
        <div>שומר...</div>
    </div>

    <?php if ($isAuthenticated): ?>
    <script>
        // נתונים משותפים
        const sharedData = <?= $sharedDataJson ?>;

        // יעדים זמינים במערכת
        const destinations = [
            {
                id: 'notes',
                name: 'הערות',
                icon: '<svg viewBox="0 0 24 24"><path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm-5 14H7v-2h7v2zm3-4H7v-2h10v2zm0-4H7V7h10v2z"/></svg>',
                endpoint: '/api/notes/add.php',
                color: '#FF9500'
            },
            {
                id: 'files',
                name: 'קבצים',
                icon: '<svg viewBox="0 0 24 24"><path d="M10 4H4c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V8c0-1.1-.9-2-2-2h-8l-2-2z"/></svg>',
                endpoint: '/api/files/upload.php',
                color: '#007AFF'
            },
            {
                id: 'graves',
                name: 'קברים',
                icon: '<svg viewBox="0 0 24 24"><path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z"/></svg>',
                endpoint: '/api/graves/attach.php',
                color: '#8E8E93',
                needsSelection: true
            },
            {
                id: 'shopping',
                name: 'רשימת קניות',
                icon: '<svg viewBox="0 0 24 24"><path d="M7 18c-1.1 0-2 .9-2 2s.9 2 2 2 2-.9 2-2-.9-2-2-2zM1 2v2h2l3.6 7.59-1.35 2.45c-.16.28-.25.61-.25.96 0 1.1.9 2 2 2h12v-2H7.42c-.14 0-.25-.11-.25-.25l.03-.12.9-1.63h7.45c.75 0 1.41-.41 1.75-1.03l3.58-6.49c.08-.14.12-.31.12-.48 0-.55-.45-1-1-1H5.21l-.94-2H1zm16 16c-1.1 0-2 .9-2 2s.9 2 2 2 2-.9 2-2-.9-2-2-2z"/></svg>',
                endpoint: '/api/shopping/add.php',
                color: '#34C759'
            }
        ];

        let selectedDestination = null;
        let selectedItem = null;

        // אתחול
        document.addEventListener('DOMContentLoaded', () => {
            renderDestinations();
            loadRecentItems();
        });

        // רנדור יעדים
        function renderDestinations() {
            const container = document.getElementById('destinations');
            container.innerHTML = destinations.map(dest => `
                <div class="destination-card" data-id="${dest.id}" onclick="selectDestination('${dest.id}')">
                    <div class="destination-icon" style="background: ${dest.color}">
                        ${dest.icon}
                    </div>
                    <div class="destination-name">${dest.name}</div>
                </div>
            `).join('');
        }

        // בחירת יעד
        function selectDestination(id) {
            selectedDestination = destinations.find(d => d.id === id);

            // עדכון UI
            document.querySelectorAll('.destination-card').forEach(card => {
                card.classList.toggle('selected', card.dataset.id === id);
            });

            // אם צריך בחירה נוספת
            if (selectedDestination.needsSelection) {
                loadItemsForDestination(id);
                document.getElementById('recent-section').style.display = 'block';
            } else {
                document.getElementById('recent-section').style.display = 'none';
                selectedItem = null;
            }

            updateSaveButton();
        }

        // טעינת פריטים ליעד
        async function loadItemsForDestination(destId) {
            const container = document.getElementById('recent-items');

            if (destId === 'graves') {
                // טען קברים אחרונים
                try {
                    const response = await fetch('/api/graves/recent.php');
                    const data = await response.json();

                    if (data.success && data.graves.length > 0) {
                        container.innerHTML = data.graves.map(grave => `
                            <div class="recent-item" data-id="${grave.id}" onclick="selectItem(${grave.id}, 'grave')">
                                <div class="recent-icon">
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                                        <path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7z"/>
                                    </svg>
                                </div>
                                <div class="recent-info">
                                    <div class="recent-name">${grave.deceased_name}</div>
                                    <div class="recent-path">${grave.cemetery_name || 'בית עלמין'}</div>
                                </div>
                            </div>
                        `).join('');
                    } else {
                        container.innerHTML = '<div class="recent-item"><div class="recent-info"><div class="recent-name">אין קברים אחרונים</div></div></div>';
                    }
                } catch (e) {
                    container.innerHTML = '<div class="recent-item"><div class="recent-info"><div class="recent-name">שגיאה בטעינה</div></div></div>';
                }
            }
        }

        // טעינת פריטים אחרונים כללית
        async function loadRecentItems() {
            // נטען רק כשנבחר יעד שדורש בחירה
        }

        // בחירת פריט
        function selectItem(id, type) {
            selectedItem = { id, type };

            document.querySelectorAll('.recent-item').forEach(item => {
                item.classList.toggle('selected', item.dataset.id == id);
            });

            updateSaveButton();
        }

        // עדכון כפתור שמירה
        function updateSaveButton() {
            const btn = document.getElementById('save-btn');

            if (!selectedDestination) {
                btn.disabled = true;
                return;
            }

            if (selectedDestination.needsSelection && !selectedItem) {
                btn.disabled = true;
                return;
            }

            btn.disabled = false;
        }

        // שמירה
        document.getElementById('save-btn')?.addEventListener('click', async () => {
            if (!selectedDestination) return;

            const loading = document.getElementById('loading');
            loading.classList.add('active');

            try {
                const formData = new FormData();
                formData.append('title', sharedData.title);
                formData.append('text', sharedData.text);
                formData.append('url', sharedData.url);

                if (selectedItem) {
                    formData.append('item_id', selectedItem.id);
                    formData.append('item_type', selectedItem.type);
                }

                // הוסף קבצים
                if (sharedData.files && sharedData.files.length > 0) {
                    formData.append('shared_files', JSON.stringify(sharedData.files));
                }

                const response = await fetch(selectedDestination.endpoint, {
                    method: 'POST',
                    body: formData,
                    credentials: 'include'
                });

                const result = await response.json();

                if (result.success) {
                    // הצלחה - נווט ליעד או סגור
                    if (result.redirect) {
                        window.location.href = result.redirect;
                    } else {
                        alert('נשמר בהצלחה!');
                        window.close();
                        history.back();
                    }
                } else {
                    throw new Error(result.error || 'שגיאה בשמירה');
                }
            } catch (error) {
                console.error('Save error:', error);
                alert('שגיאה: ' + error.message);
            } finally {
                loading.classList.remove('active');
            }
        });
    </script>
    <?php endif; ?>
</body>
</html>
