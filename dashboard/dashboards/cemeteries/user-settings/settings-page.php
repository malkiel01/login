<?php
/*
 * File: user-settings/settings-page.php
 * Version: 1.0.0
 * Created: 2026-01-23
 * Author: Malkiel
 * Description: דף הגדרות אישיות - עצמאי לטעינה ב-iframe
 */

header('Content-Type: text/html; charset=utf-8');

require_once dirname(__DIR__) . '/config.php';
require_once __DIR__ . '/api/UserSettingsManager.php';

// בדיקת התחברות
if (!isLoggedIn()) {
    die('<!DOCTYPE html><html dir="rtl"><head><meta charset="UTF-8"></head><body style="font-family: Arial; padding: 20px; color: #ef4444;">שגיאה: יש להתחבר למערכת</body></html>');
}

try {
    $conn = getDBConnection();
    $userId = getCurrentUserId();

    // קבלת סוג המכשיר מה-URL או ברירת מחדל
    $deviceType = $_GET['deviceType'] ?? 'auto';
    // אם auto, יזוהה ב-JS - לצורך PHP נשתמש ב-desktop
    $phpDeviceType = ($deviceType === 'auto') ? 'desktop' : $deviceType;

    $settings = new UserSettingsManager($conn, $userId, $phpDeviceType);

    // קבלת כל ההגדרות עם ברירות מחדל
    $allSettings = $settings->getAllWithDefaults();
    $categories = $settings->getCategories();

} catch (Exception $e) {
    die('<!DOCTYPE html><html dir="rtl"><head><meta charset="UTF-8"></head><body style="font-family: Arial; padding: 20px; color: #ef4444;">שגיאה: ' . htmlspecialchars($e->getMessage()) . '</body></html>');
}

// מיפוי קטגוריות
$categoryLabels = [
    'display' => 'תצוגה',
    'notifications' => 'התראות',
    'tables' => 'טבלאות',
    'navigation' => 'ניווט',
    'locale' => 'שפה ואזור',
    'general' => 'כללי'
];

$categoryIcons = [
    'display' => 'fa-palette',
    'notifications' => 'fa-bell',
    'tables' => 'fa-table',
    'navigation' => 'fa-compass',
    'locale' => 'fa-globe',
    'general' => 'fa-cog'
];

// קיבוץ הגדרות לפי קטגוריה
$settingsByCategory = [];
foreach ($allSettings as $key => $data) {
    $cat = $data['category'] ?? 'general';
    if (!isset($settingsByCategory[$cat])) {
        $settingsByCategory[$cat] = [];
    }
    $settingsByCategory[$cat][$key] = $data;
}

// סדר קטגוריות
$categoryOrder = ['display', 'tables', 'navigation', 'notifications', 'locale', 'general'];
?>
<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>הגדרות אישיות</title>

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <!-- Styles -->
    <link rel="stylesheet" href="/dashboard/dashboards/cemeteries/user-settings/css/user-settings.css?v=20260125b">

    <!-- Popup API -->
    <script src="/dashboard/dashboards/cemeteries/popup/popup-api.js?v=<?= time() ?>"></script>

    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: var(--settings-bg);
            color: var(--settings-text);
            padding: 20px;
            direction: rtl;
        }
        .device-profile-indicator {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            justify-content: center;
        }
        .profile-badge {
            padding: 10px 20px;
            border-radius: 25px;
            background: var(--settings-card-bg, #f3f4f6);
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 8px;
            font-weight: 500;
            border: 2px solid transparent;
        }
        .profile-badge:hover {
            background: var(--settings-hover, #e5e7eb);
        }
        .profile-badge.active {
            background: var(--primary-color, #667eea);
            color: white;
            border-color: var(--primary-dark, #5a67d8);
        }
        .profile-badge i {
            font-size: 1.1em;
        }
    </style>
</head>
<body>
    <div class="settings-container">
        <div class="settings-header">
            <h1><i class="fas fa-cog"></i> הגדרות אישיות</h1>
            <button class="btn-settings btn-settings-danger" onclick="resetAllSettings()">
                <i class="fas fa-undo"></i> איפוס הכל
            </button>
        </div>

        <!-- Device Profile Indicator -->
        <div class="device-profile-indicator">
            <span class="profile-badge" data-device="desktop" onclick="switchProfile('desktop')">
                <i class="fas fa-desktop"></i> דסקטופ
            </span>
            <span class="profile-badge" data-device="mobile" onclick="switchProfile('mobile')">
                <i class="fas fa-mobile-alt"></i> מובייל
            </span>
        </div>

        <div id="settingsMessage" class="settings-message" style="display: none;"></div>

        <div id="settingsContent">
            <?php foreach ($categoryOrder as $category):
                if (!isset($settingsByCategory[$category])) continue;
                $catSettings = $settingsByCategory[$category];
            ?>
            <div class="settings-category" data-category="<?= $category ?>">
                <div class="category-header">
                    <i class="fas <?= $categoryIcons[$category] ?? 'fa-cog' ?>"></i>
                    <h3><?= $categoryLabels[$category] ?? $category ?></h3>
                </div>
                <div class="category-content">
                    <?php foreach ($catSettings as $key => $data): ?>
                    <div class="setting-item" data-key="<?= htmlspecialchars($key) ?>">
                        <div class="setting-header">
                            <label class="setting-label"><?= htmlspecialchars($data['label'] ?? $key) ?></label>
                            <?php if (!empty($data['description'])): ?>
                            <div class="setting-description"><?= htmlspecialchars($data['description']) ?></div>
                            <?php endif; ?>
                        </div>

                        <button type="button"
                                class="setting-reset-btn <?= $data['isDefault'] ? 'hidden' : '' ?>"
                                onclick="resetSetting('<?= htmlspecialchars($key) ?>')"
                                title="חזור לברירת מחדל">
                            <i class="fas fa-undo"></i>
                        </button>

                        <?php if ($data['type'] === 'boolean'): ?>
                        <label class="toggle-switch">
                            <input type="checkbox"
                                   data-key="<?= htmlspecialchars($key) ?>"
                                   <?= $data['value'] ? 'checked' : '' ?>
                                   onchange="saveSetting('<?= htmlspecialchars($key) ?>', this.checked)">
                            <span class="toggle-slider"></span>
                        </label>

                        <?php elseif (!empty($data['options'])): ?>
                        <select class="form-control"
                                data-key="<?= htmlspecialchars($key) ?>"
                                onchange="saveSetting('<?= htmlspecialchars($key) ?>', this.value)">
                            <?php foreach ($data['options'] as $opt): ?>
                            <option value="<?= htmlspecialchars($opt['value']) ?>"
                                    <?= $data['value'] == $opt['value'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($opt['label']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>

                        <?php elseif ($data['type'] === 'number'): ?>
                        <?php
                            $minMax = '';
                            if ($key === 'fontSize') {
                                $minMax = 'min="10" max="30"';
                            }
                        ?>
                        <input type="number"
                               class="form-control"
                               data-key="<?= htmlspecialchars($key) ?>"
                               value="<?= htmlspecialchars($data['value'] ?? 0) ?>"
                               <?= $minMax ?>
                               onchange="saveSetting('<?= htmlspecialchars($key) ?>', parseFloat(this.value))">

                        <?php else: ?>
                        <input type="text"
                               class="form-control"
                               data-key="<?= htmlspecialchars($key) ?>"
                               value="<?= htmlspecialchars($data['value'] ?? '') ?>"
                               onchange="saveSetting('<?= htmlspecialchars($key) ?>', this.value)">
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Scripts -->
    <script src="/dashboard/dashboards/cemeteries/user-settings/js/user-settings-storage.js"></script>
    <script src="/dashboard/dashboards/cemeteries/user-settings/js/user-settings-core.js"></script>

    <script>
        const API_URL = '/dashboard/dashboards/cemeteries/user-settings/api/api.php';

        // סוג המכשיר הנוכחי
        let currentDeviceType = '<?= $deviceType ?>' === 'auto'
            ? (typeof UserSettingsStorage !== 'undefined' ? UserSettingsStorage.getDeviceType() : 'desktop')
            : '<?= $deviceType ?>';

        // עדכון אינדיקטור הפרופיל
        function updateProfileIndicator() {
            document.querySelectorAll('.profile-badge').forEach(badge => {
                badge.classList.toggle('active', badge.dataset.device === currentDeviceType);
            });
        }

        // מעבר בין פרופילים
        function switchProfile(deviceType) {
            if (deviceType === currentDeviceType) return;

            currentDeviceType = deviceType;
            updateProfileIndicator();

            // טעינה מחדש עם הפרופיל החדש
            const url = new URL(window.location.href);
            url.searchParams.set('deviceType', deviceType);
            window.location.href = url.toString();
        }

        // הצגת הודעה
        function showMessage(text, type = 'success') {
            const msg = document.getElementById('settingsMessage');
            msg.className = 'settings-message ' + type;
            msg.innerHTML = '<i class="fas fa-' + (type === 'success' ? 'check-circle' : 'exclamation-circle') + '"></i> ' + text;
            msg.style.display = 'flex';

            setTimeout(() => {
                msg.style.display = 'none';
            }, 3000);
        }

        // הצג/הסתר colorScheme לפי darkMode
        function updateColorSchemeVisibility(isDark) {
            const colorSchemeItem = document.querySelector('.setting-item[data-key="colorScheme"]');
            if (colorSchemeItem) {
                colorSchemeItem.style.display = isDark ? 'none' : 'flex';
            }
        }

        // שמירת הגדרה
        async function saveSetting(key, value) {
            // וולידציה לגודל פונט
            if (key === 'fontSize') {
                value = Math.min(30, Math.max(10, value));
                // עדכון ה-input עם הערך המתוקן
                const input = document.querySelector('.setting-item[data-key="fontSize"] input');
                if (input) input.value = value;
            }

            try {
                const response = await fetch(API_URL, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'set', key, value, deviceType: currentDeviceType })
                });

                const data = await response.json();

                if (data.success) {
                    // הצגת כפתור reset
                    const item = document.querySelector(`.setting-item[data-key="${key}"]`);
                    if (item) {
                        item.querySelector('.setting-reset-btn')?.classList.remove('hidden');
                    }

                    // עדכון cache
                    if (typeof UserSettingsStorage !== 'undefined') {
                        UserSettingsStorage.updateCacheItem(key, value, currentDeviceType);
                    }

                    // שליחת event לparent
                    if (window.parent && window.parent !== window) {
                        window.parent.postMessage({
                            type: 'userSettingChanged',
                            key: key,
                            value: value,
                            deviceType: currentDeviceType
                        }, '*');
                    }

                    // אם שונה darkMode, עדכן את נראות colorScheme
                    if (key === 'darkMode') {
                        updateColorSchemeVisibility(value === true || value === 'true');
                    }

                    showMessage('נשמר בהצלחה');
                } else {
                    throw new Error(data.message || 'שגיאה בשמירה');
                }
            } catch (error) {
                console.error('Error saving setting:', error);
                showMessage('שגיאה בשמירה: ' + error.message, 'error');
            }
        }

        // איפוס הגדרה
        async function resetSetting(key) {
            try {
                const response = await fetch(API_URL, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'reset', key, deviceType: currentDeviceType })
                });

                const data = await response.json();

                if (data.success) {
                    // רענון העמוד לקבלת ערך ברירת מחדל
                    location.reload();
                } else {
                    throw new Error(data.message || 'שגיאה באיפוס');
                }
            } catch (error) {
                console.error('Error resetting setting:', error);
                showMessage('שגיאה באיפוס: ' + error.message, 'error');
            }
        }

        // איפוס כל ההגדרות
        async function resetAllSettings() {
            if (!confirm('האם אתה בטוח שברצונך לאפס את כל ההגדרות לברירת המחדל?')) {
                return;
            }

            try {
                const response = await fetch(API_URL, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'reset', deviceType: currentDeviceType })
                });

                const data = await response.json();

                if (data.success) {
                    // ניקוי cache עבור הפרופיל הנוכחי
                    if (typeof UserSettingsStorage !== 'undefined') {
                        UserSettingsStorage.clearCache(currentDeviceType);
                    }

                    // רענון העמוד
                    location.reload();
                } else {
                    throw new Error(data.message || 'שגיאה באיפוס');
                }
            } catch (error) {
                console.error('Error resetting all settings:', error);
                showMessage('שגיאה באיפוס: ' + error.message, 'error');
            }
        }

        // סנכרון הגדרות מ-localStorage לשרת (פעם אחת בטעינה)
        async function syncFromLocalStorage() {
            try {
                // קבלת מפתח cache נכון לפרופיל הנוכחי
                const storageKey = typeof UserSettingsStorage !== 'undefined'
                    ? UserSettingsStorage.getStorageKey(currentDeviceType)
                    : 'user_settings_cache_' + currentDeviceType;

                // נסה לקרוא מ-localStorage של ה-parent (אם זה iframe)
                let cached = null;
                try {
                    if (window.parent && window.parent !== window) {
                        cached = window.parent.localStorage.getItem(storageKey);
                    }
                } catch(e) {}

                if (!cached) {
                    cached = localStorage.getItem(storageKey);
                }

                if (!cached) return false;

                const data = JSON.parse(cached);
                if (!data.settings) return false;

                // בדיקה אם יש הבדלים בין localStorage לשרת
                const serverSettings = <?= json_encode(array_map(function($s) { return $s['value']; }, $allSettings)) ?>;
                const localSettings = {};
                let hasDifferences = false;

                for (const [key, setting] of Object.entries(data.settings)) {
                    if (setting && setting.value !== undefined) {
                        localSettings[key] = setting.value;
                        if (serverSettings[key] !== setting.value) {
                            hasDifferences = true;
                        }
                    }
                }

                if (!hasDifferences) return false;

                // שליחה לשרת
                const response = await fetch(API_URL, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'set', settings: localSettings, deviceType: currentDeviceType })
                });

                const result = await response.json();
                return result.success;
            } catch (e) {
                console.error('Sync error:', e);
                return false;
            }
        }

        // אתחול
        document.addEventListener('DOMContentLoaded', async function() {
            // אם deviceType היה auto, בדוק אם צריך לרענן עם הפרופיל הנכון
            const urlDeviceType = '<?= $deviceType ?>';
            if (urlDeviceType === 'auto') {
                const detectedDevice = typeof UserSettingsStorage !== 'undefined'
                    ? UserSettingsStorage.getDeviceType()
                    : (window.innerWidth < 768 ? 'mobile' : 'desktop');

                // רענון עם deviceType הנכון (פעם אחת בלבד)
                const url = new URL(window.location.href);
                url.searchParams.set('deviceType', detectedDevice);
                window.location.replace(url.toString());
                return; // עצור את שאר האתחול
            }

            // עדכון כותרת הפופאפ
            if (typeof PopupAPI !== 'undefined') {
                const profileLabel = currentDeviceType === 'mobile' ? 'מובייל' : 'דסקטופ';
                PopupAPI.setTitle('הגדרות אישיות - ' + profileLabel);
            }

            // עדכון אינדיקטור הפרופיל
            updateProfileIndicator();

            // בדיקת מצב darkMode התחלתי והסתרת colorScheme אם צריך
            const darkModeCheckbox = document.querySelector('.setting-item[data-key="darkMode"] input[type="checkbox"]');
            if (darkModeCheckbox) {
                updateColorSchemeVisibility(darkModeCheckbox.checked);
            }

            // סנכרון הגדרות מ-localStorage אם יש הבדלים (רק אם deviceType ידוע)
            // const synced = await syncFromLocalStorage();
            // if (synced) {
            //     location.reload();
            // }
        });
    </script>
</body>
</html>
