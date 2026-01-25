<?php
/**
 * Company Settings Page
 * v1.0.0 - 2026-01-25
 *
 * Allows admin to configure company details including:
 * - Company name
 * - Logo
 * - Contact information
 * - Social security code
 */

// Load main config
require_once $_SERVER['DOCUMENT_ROOT'] . '/config.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/auth/middleware.php';

// Check authentication
requireDashboard(['admin', 'cemetery_manager']);

// Load company settings
require_once __DIR__ . '/api/CompanySettingsManager.php';
$conn = getDBConnection();
$manager = CompanySettingsManager::getInstance($conn);
$settings = $manager->getAll();
?>
<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>הגדרות חברה - מערכת ניהול בתי עלמין</title>
    <link rel="stylesheet" href="css/settings.css">
</head>
<body>
    <div class="settings-wrapper">
        <!-- Header -->
        <header class="settings-header">
            <div class="header-content">
                <div class="header-title">
                    <span class="header-icon">⚙️</span>
                    <h1>הגדרות חברה</h1>
                </div>
                <a href="/dashboard/dashboards/cemetery_manager.php" class="btn-back">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M19 12H5M12 19l-7-7 7-7"/>
                    </svg>
                    חזרה לדף הראשי
                </a>
            </div>
        </header>

        <!-- Main Content -->
        <main class="settings-main">
            <div class="settings-container">
                <!-- Messages container -->
                <div id="messagesContainer"></div>

                <div class="settings-card">
                    <div class="card-header">
                        <h2>
                            <span>🏢</span>
                            פרטי החברה
                        </h2>
                    </div>
                    <div class="card-body">
                        <form id="settingsForm">
                            <!-- Company Name -->
                            <div class="form-group">
                                <label class="form-label">
                                    <span class="required">*</span>
                                    שם החברה
                                </label>
                                <input
                                    type="text"
                                    name="company_name"
                                    class="form-input"
                                    placeholder="לדוגמה: חברה קדישא לעדת המערבים"
                                    value="<?php echo htmlspecialchars($settings['company_name'] ?? ''); ?>"
                                    required
                                >
                                <p class="form-hint">שם זה יוצג בכותרת המערכת ובמסמכים</p>
                            </div>

                            <!-- Logo Upload -->
                            <div class="form-group">
                                <label class="form-label">לוגו החברה</label>
                                <div class="logo-upload-wrapper">
                                    <div class="logo-preview-container">
                                        <div class="logo-preview" id="logoPreview">
                                            <?php if (!empty($settings['company_logo'])): ?>
                                                <img src="<?php echo htmlspecialchars($settings['company_logo']); ?>" alt="לוגו">
                                            <?php else: ?>
                                                <div class="logo-placeholder">
                                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                                                        <rect x="3" y="3" width="18" height="18" rx="2" ry="2"/>
                                                        <circle cx="8.5" cy="8.5" r="1.5"/>
                                                        <polyline points="21 15 16 10 5 21"/>
                                                    </svg>
                                                    <span>אין לוגו</span>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        <?php if (!empty($settings['company_logo'])): ?>
                                            <button type="button" class="btn-remove-logo" id="removeLogo">
                                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14">
                                                    <polyline points="3 6 5 6 21 6"/>
                                                    <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>
                                                </svg>
                                                הסר לוגו
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                    <div class="logo-upload-area">
                                        <div class="upload-dropzone" id="uploadDropzone">
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                                                <polyline points="17 8 12 3 7 8"/>
                                                <line x1="12" y1="3" x2="12" y2="15"/>
                                            </svg>
                                            <p class="upload-text">
                                                גרור תמונה לכאן או <span>לחץ לבחירה</span>
                                            </p>
                                            <p class="upload-hint">PNG, JPG, GIF או SVG. מקסימום 2MB</p>
                                        </div>
                                        <input type="file" id="logoInput" accept="image/*" hidden>
                                    </div>
                                </div>
                            </div>

                            <!-- Phone Numbers -->
                            <div class="form-row">
                                <div class="form-group">
                                    <label class="form-label">טלפון ראשי</label>
                                    <input
                                        type="tel"
                                        name="phone_primary"
                                        class="form-input"
                                        placeholder="02-1234567"
                                        value="<?php echo htmlspecialchars($settings['phone_primary'] ?? ''); ?>"
                                    >
                                </div>
                                <div class="form-group">
                                    <label class="form-label">טלפון משני</label>
                                    <input
                                        type="tel"
                                        name="phone_secondary"
                                        class="form-input"
                                        placeholder="050-1234567"
                                        value="<?php echo htmlspecialchars($settings['phone_secondary'] ?? ''); ?>"
                                    >
                                </div>
                            </div>

                            <!-- Email -->
                            <div class="form-group">
                                <label class="form-label">דואר אלקטרוני</label>
                                <input
                                    type="email"
                                    name="email"
                                    class="form-input"
                                    placeholder="info@company.co.il"
                                    value="<?php echo htmlspecialchars($settings['email'] ?? ''); ?>"
                                >
                            </div>

                            <!-- Address -->
                            <div class="form-group">
                                <label class="form-label">כתובת</label>
                                <textarea
                                    name="address"
                                    class="form-textarea"
                                    placeholder="כתובת מלאה של החברה"
                                    rows="3"
                                ><?php echo htmlspecialchars($settings['address'] ?? ''); ?></textarea>
                            </div>

                            <!-- Social Security Code -->
                            <div class="form-group">
                                <label class="form-label">קוד ביטוח לאומי</label>
                                <input
                                    type="text"
                                    name="social_security_code"
                                    class="form-input"
                                    placeholder="קוד מזהה לביטוח לאומי"
                                    value="<?php echo htmlspecialchars($settings['social_security_code'] ?? ''); ?>"
                                >
                                <p class="form-hint">קוד זה משמש לדיווחים לביטוח לאומי</p>
                            </div>

                            <!-- Actions -->
                            <div class="form-actions">
                                <button type="button" class="btn btn-secondary" onclick="location.reload()">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/>
                                        <path d="M3 3v5h5"/>
                                    </svg>
                                    איפוס
                                </button>
                                <button type="submit" class="btn btn-primary" id="saveBtn">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/>
                                        <polyline points="17 21 17 13 7 13 7 21"/>
                                        <polyline points="7 3 7 8 15 8"/>
                                    </svg>
                                    שמור הגדרות
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </main>

        <!-- Footer -->
        <footer class="settings-footer">
            <p class="footer-text">© <?php echo date('Y'); ?> מערכת ניהול בתי עלמין - הגדרות חברה</p>
        </footer>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const form = document.getElementById('settingsForm');
        const saveBtn = document.getElementById('saveBtn');
        const logoInput = document.getElementById('logoInput');
        const uploadDropzone = document.getElementById('uploadDropzone');
        const logoPreview = document.getElementById('logoPreview');
        const messagesContainer = document.getElementById('messagesContainer');
        const removeLogo = document.getElementById('removeLogo');

        // Form submit
        form.addEventListener('submit', async function(e) {
            e.preventDefault();

            saveBtn.disabled = true;
            saveBtn.innerHTML = '<span class="spinner" style="width:18px;height:18px;border-width:2px;margin-left:8px"></span> שומר...';

            try {
                const formData = new FormData(form);
                const data = Object.fromEntries(formData.entries());

                const response = await fetch('/dashboard/dashboards/settings/api/company-settings.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(data)
                });

                const result = await response.json();

                if (result.success) {
                    showMessage('success', 'ההגדרות נשמרו בהצלחה!');
                } else {
                    showMessage('error', result.error || 'שגיאה בשמירת ההגדרות');
                }
            } catch (error) {
                showMessage('error', 'שגיאת תקשורת. נסה שוב.');
                console.error(error);
            } finally {
                saveBtn.disabled = false;
                saveBtn.innerHTML = `
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/>
                        <polyline points="17 21 17 13 7 13 7 21"/>
                        <polyline points="7 3 7 8 15 8"/>
                    </svg>
                    שמור הגדרות
                `;
            }
        });

        // Logo upload - click
        uploadDropzone.addEventListener('click', () => logoInput.click());

        // Logo upload - drag & drop
        uploadDropzone.addEventListener('dragover', (e) => {
            e.preventDefault();
            uploadDropzone.classList.add('drag-over');
        });

        uploadDropzone.addEventListener('dragleave', () => {
            uploadDropzone.classList.remove('drag-over');
        });

        uploadDropzone.addEventListener('drop', (e) => {
            e.preventDefault();
            uploadDropzone.classList.remove('drag-over');
            const files = e.dataTransfer.files;
            if (files.length > 0) {
                handleLogoUpload(files[0]);
            }
        });

        // Logo upload - file input
        logoInput.addEventListener('change', () => {
            if (logoInput.files.length > 0) {
                handleLogoUpload(logoInput.files[0]);
            }
        });

        // Remove logo
        if (removeLogo) {
            removeLogo.addEventListener('click', async () => {
                if (!confirm('האם למחוק את הלוגו?')) return;

                try {
                    const response = await fetch('/dashboard/dashboards/settings/api/company-settings.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ company_logo: '' })
                    });

                    const result = await response.json();
                    if (result.success) {
                        logoPreview.innerHTML = `
                            <div class="logo-placeholder">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                                    <rect x="3" y="3" width="18" height="18" rx="2" ry="2"/>
                                    <circle cx="8.5" cy="8.5" r="1.5"/>
                                    <polyline points="21 15 16 10 5 21"/>
                                </svg>
                                <span>אין לוגו</span>
                            </div>
                        `;
                        removeLogo.style.display = 'none';
                        showMessage('success', 'הלוגו הוסר בהצלחה');
                    }
                } catch (error) {
                    showMessage('error', 'שגיאה בהסרת הלוגו');
                }
            });
        }

        async function handleLogoUpload(file) {
            // Validate file type
            if (!file.type.startsWith('image/')) {
                showMessage('error', 'יש להעלות קובץ תמונה בלבד');
                return;
            }

            // Validate file size (2MB)
            if (file.size > 2 * 1024 * 1024) {
                showMessage('error', 'גודל הקובץ חייב להיות עד 2MB');
                return;
            }

            const formData = new FormData();
            formData.append('logo', file);

            // Show loading state
            logoPreview.innerHTML = '<div class="loading"><div class="spinner"></div></div>';

            try {
                const response = await fetch('/dashboard/dashboards/settings/api/upload-logo.php', {
                    method: 'POST',
                    body: formData
                });

                const result = await response.json();

                if (result.success) {
                    logoPreview.innerHTML = `<img src="${result.path}?t=${Date.now()}" alt="לוגו">`;

                    // Show remove button
                    let removeBtn = document.getElementById('removeLogo');
                    if (!removeBtn) {
                        removeBtn = document.createElement('button');
                        removeBtn.type = 'button';
                        removeBtn.className = 'btn-remove-logo';
                        removeBtn.id = 'removeLogo';
                        removeBtn.innerHTML = `
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14">
                                <polyline points="3 6 5 6 21 6"/>
                                <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>
                            </svg>
                            הסר לוגו
                        `;
                        logoPreview.parentNode.appendChild(removeBtn);
                    } else {
                        removeBtn.style.display = 'inline-flex';
                    }

                    showMessage('success', 'הלוגו הועלה בהצלחה');
                } else {
                    throw new Error(result.error || 'שגיאה בהעלאה');
                }
            } catch (error) {
                logoPreview.innerHTML = `
                    <div class="logo-placeholder">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                            <rect x="3" y="3" width="18" height="18" rx="2" ry="2"/>
                            <circle cx="8.5" cy="8.5" r="1.5"/>
                            <polyline points="21 15 16 10 5 21"/>
                        </svg>
                        <span>שגיאה</span>
                    </div>
                `;
                showMessage('error', error.message);
            }
        }

        function showMessage(type, text) {
            const icon = type === 'success'
                ? '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>'
                : '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>';

            const message = document.createElement('div');
            message.className = `message message-${type}`;
            message.innerHTML = `${icon}<span>${text}</span>`;

            messagesContainer.innerHTML = '';
            messagesContainer.appendChild(message);

            // Auto-hide after 5 seconds
            setTimeout(() => {
                message.style.opacity = '0';
                message.style.transform = 'translateY(-10px)';
                setTimeout(() => message.remove(), 300);
            }, 5000);
        }
    });
    </script>
</body>
</html>
