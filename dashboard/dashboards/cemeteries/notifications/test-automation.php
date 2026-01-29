<?php
/**
 * Notification Test Automation
 * UI for running and monitoring automated tests
 *
 * @version 1.0.0
 */

require_once $_SERVER['DOCUMENT_ROOT'] . '/dashboard/dashboards/cemeteries/config.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/auth/token-init.php';

// Permission check
requireDashboard(['cemetery_manager', 'admin']);

if (!isAdmin() && !hasModulePermission('notifications', 'edit')) {
    http_response_code(403);
    die('אין לך הרשאה לצפות בדף זה');
}

// Load user settings
require_once $_SERVER['DOCUMENT_ROOT'] . '/dashboard/dashboards/cemeteries/user-settings/api/UserSettingsManager.php';
$userSettingsConn = getDBConnection();
$userId = getCurrentUserId();

function detectDeviceType() {
    if (isset($_COOKIE['deviceType']) && in_array($_COOKIE['deviceType'], ['mobile', 'desktop'])) {
        return $_COOKIE['deviceType'];
    }
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $mobileKeywords = ['Mobile', 'Android', 'iPhone', 'iPad', 'iPod', 'BlackBerry', 'Windows Phone'];
    foreach ($mobileKeywords as $keyword) {
        if (stripos($userAgent, $keyword) !== false) {
            return 'mobile';
        }
    }
    return 'desktop';
}

$detectedDeviceType = detectDeviceType();
$userSettingsManager = new UserSettingsManager($userSettingsConn, $userId, $detectedDeviceType);
$userPrefs = $userSettingsManager->getAllWithDefaults();

$isDarkMode = isset($userPrefs['darkMode']) && ($userPrefs['darkMode']['value'] === true || $userPrefs['darkMode']['value'] === 'true');
$colorScheme = isset($userPrefs['colorScheme']) ? $userPrefs['colorScheme']['value'] : 'purple';
$fontSize = isset($userPrefs['fontSize']) ? max(10, min(30, (int)$userPrefs['fontSize']['value'])) : 14;

$bodyClasses = [];
$bodyClasses[] = $isDarkMode ? 'dark-theme' : 'light-theme';
if (!$isDarkMode) {
    $bodyClasses[] = 'color-scheme-' . $colorScheme;
}
?>
<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>בדיקות אוטומציה - <?php echo DASHBOARD_NAME; ?></title>
    <link rel="icon" href="data:,">

    <link rel="stylesheet" href="/dashboard/dashboards/cemeteries/css/main.css">
    <link rel="stylesheet" href="/dashboard/dashboards/cemeteries/css/dashboard.css?v=20260122c">
    <link rel="stylesheet" href="/dashboard/dashboards/cemeteries/notifications/css/logs.css?v=1">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>
        .test-automation-container {
            padding: 20px;
            max-width: 1400px;
            margin: 0 auto;
        }

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
            flex-wrap: wrap;
            gap: 16px;
        }

        .page-title {
            font-size: 1.75rem;
            font-weight: 600;
            color: var(--text-primary);
            margin: 0;
        }

        .header-actions {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }

        /* Test Cards Section */
        .tests-section {
            margin-bottom: 32px;
        }

        .section-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 16px;
        }

        .test-cards {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 16px;
        }

        .test-card {
            background: var(--bg-secondary);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            padding: 20px;
            transition: all 0.2s;
        }

        .test-card:hover {
            border-color: var(--primary-color);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }

        .test-card-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 12px;
        }

        .test-card-title {
            font-weight: 600;
            color: var(--text-primary);
            font-size: 1rem;
        }

        .test-card-description {
            color: var(--text-secondary);
            font-size: 0.875rem;
            line-height: 1.5;
            margin-bottom: 16px;
        }

        .test-card-actions {
            display: flex;
            justify-content: flex-end;
        }

        /* Run All Button */
        .run-all-section {
            background: var(--bg-secondary);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            padding: 24px;
            margin-bottom: 32px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 16px;
        }

        .run-all-info h3 {
            margin: 0 0 8px 0;
            font-size: 1.125rem;
            color: var(--text-primary);
        }

        .run-all-info p {
            margin: 0;
            color: var(--text-secondary);
            font-size: 0.875rem;
        }

        /* Results Section */
        .results-section {
            margin-bottom: 32px;
        }

        .current-run {
            background: var(--bg-secondary);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            padding: 24px;
            margin-bottom: 24px;
            display: none;
        }

        .current-run.active {
            display: block;
        }

        .run-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .run-status {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .run-status-icon {
            width: 24px;
            height: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .run-status-icon.running {
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }

        .run-results-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
            margin-bottom: 20px;
        }

        .result-stat {
            text-align: center;
            padding: 16px;
            background: var(--bg-primary);
            border-radius: 8px;
        }

        .result-stat-value {
            font-size: 2rem;
            font-weight: 700;
            color: var(--text-primary);
        }

        .result-stat-value.passed { color: var(--success-color, #22c55e); }
        .result-stat-value.failed { color: var(--error-color, #ef4444); }

        .result-stat-label {
            font-size: 0.875rem;
            color: var(--text-secondary);
            margin-top: 4px;
        }

        /* Test Results List */
        .test-results-list {
            border: 1px solid var(--border-color);
            border-radius: 8px;
            overflow: hidden;
        }

        .test-result-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 16px;
            border-bottom: 1px solid var(--border-color);
            background: var(--bg-primary);
        }

        .test-result-item:last-child {
            border-bottom: none;
        }

        .test-result-icon {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .test-result-icon.passed {
            background: rgba(34, 197, 94, 0.1);
            color: #22c55e;
        }

        .test-result-icon.failed {
            background: rgba(239, 68, 68, 0.1);
            color: #ef4444;
        }

        .test-result-content {
            flex: 1;
        }

        .test-result-name {
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 4px;
        }

        .test-result-message {
            font-size: 0.875rem;
            color: var(--text-secondary);
        }

        .test-result-time {
            font-size: 0.75rem;
            color: var(--text-muted);
        }

        /* History Section */
        .history-section {
            margin-top: 32px;
        }

        .history-table {
            width: 100%;
            border-collapse: collapse;
        }

        .history-table th,
        .history-table td {
            padding: 12px 16px;
            text-align: right;
            border-bottom: 1px solid var(--border-color);
        }

        .history-table th {
            background: var(--bg-secondary);
            font-weight: 600;
            color: var(--text-primary);
        }

        .history-table td {
            color: var(--text-primary);
        }

        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 500;
        }

        .status-badge.completed {
            background: rgba(34, 197, 94, 0.1);
            color: #22c55e;
        }

        .status-badge.failed {
            background: rgba(239, 68, 68, 0.1);
            color: #ef4444;
        }

        .status-badge.running {
            background: rgba(59, 130, 246, 0.1);
            color: #3b82f6;
        }

        /* Empty/Loading States */
        .empty-state, .loading-state {
            text-align: center;
            padding: 40px 20px;
            color: var(--text-secondary);
        }

        .empty-state .empty-icon,
        .loading-state .spinner {
            font-size: 3rem;
            margin-bottom: 16px;
        }

        .loading-state .spinner {
            animation: spin 1s linear infinite;
        }

        /* Buttons */
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            border-radius: 6px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
            border: none;
            font-size: 0.875rem;
            text-decoration: none;
        }

        .btn-primary {
            background: var(--primary-color);
            color: white;
        }

        .btn-primary:hover:not(:disabled) {
            filter: brightness(1.1);
        }

        .btn-secondary {
            background: var(--bg-secondary);
            color: var(--text-primary);
            border: 1px solid var(--border-color);
        }

        .btn-secondary:hover:not(:disabled) {
            background: var(--bg-tertiary, var(--bg-secondary));
            border-color: var(--primary-color);
        }

        .btn-sm {
            padding: 6px 12px;
            font-size: 0.8125rem;
        }

        .btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .test-automation-container {
                padding: 16px;
            }

            .page-header {
                flex-direction: column;
                align-items: flex-start;
            }

            .run-all-section {
                flex-direction: column;
                text-align: center;
            }

            .test-cards {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body class="<?php echo implode(' ', $bodyClasses); ?>" style="--font-size-base: <?php echo $fontSize; ?>px;">
    <div class="test-automation-container">
        <!-- Page Header -->
        <div class="page-header">
            <h1 class="page-title">בדיקות אוטומציה</h1>
            <div class="header-actions">
                <a href="logs.php" class="btn btn-secondary">
                    <i class="fas fa-clipboard-list"></i>
                    <span>מרכז לוגים</span>
                </a>
                <a href="index.php" class="btn btn-secondary">
                    <i class="fas fa-bell"></i>
                    <span>ניהול התראות</span>
                </a>
            </div>
        </div>

        <!-- Run All Section -->
        <div class="run-all-section">
            <div class="run-all-info">
                <h3>הרץ את כל הבדיקות</h3>
                <p>יריץ את כל הבדיקות האוטומטיות ברצף ויציג דוח מסכם</p>
            </div>
            <button type="button" class="btn btn-primary" id="runAllBtn" onclick="TestManager.runAllTests()">
                <i class="fas fa-play"></i>
                <span>הרץ הכל</span>
            </button>
        </div>

        <!-- Current Run Results -->
        <div class="results-section">
            <div class="current-run" id="currentRun">
                <div class="run-header">
                    <h3>תוצאות ריצה</h3>
                    <div class="run-status" id="runStatus">
                        <div class="run-status-icon running" id="runStatusIcon">
                            <i class="fas fa-spinner"></i>
                        </div>
                        <span id="runStatusText">מריץ...</span>
                    </div>
                </div>

                <div class="run-results-grid">
                    <div class="result-stat">
                        <div class="result-stat-value" id="totalTests">0</div>
                        <div class="result-stat-label">סה"כ בדיקות</div>
                    </div>
                    <div class="result-stat">
                        <div class="result-stat-value passed" id="passedTests">0</div>
                        <div class="result-stat-label">עברו</div>
                    </div>
                    <div class="result-stat">
                        <div class="result-stat-value failed" id="failedTests">0</div>
                        <div class="result-stat-label">נכשלו</div>
                    </div>
                </div>

                <div class="test-results-list" id="testResultsList">
                    <!-- Results will be loaded here -->
                </div>
            </div>
        </div>

        <!-- Individual Tests -->
        <div class="tests-section">
            <h2 class="section-title">בדיקות בודדות</h2>
            <div class="test-cards" id="testCards">
                <div class="loading-state">
                    <div class="spinner"><i class="fas fa-spinner"></i></div>
                    <p>טוען בדיקות...</p>
                </div>
            </div>
        </div>

        <!-- History Section -->
        <div class="history-section">
            <h2 class="section-title">היסטוריית בדיקות</h2>
            <div class="table-responsive">
                <table class="history-table" id="historyTable">
                    <thead>
                        <tr>
                            <th>מזהה</th>
                            <th>סטטוס</th>
                            <th>בדיקות</th>
                            <th>עברו/נכשלו</th>
                            <th>התחלה</th>
                            <th>הריץ</th>
                        </tr>
                    </thead>
                    <tbody id="historyTableBody">
                        <tr>
                            <td colspan="6" class="empty-state">טוען היסטוריה...</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
        const TestManager = {
            isRunning: false,

            async init() {
                await Promise.all([
                    this.loadAvailableTests(),
                    this.loadHistory()
                ]);
            },

            async loadAvailableTests() {
                try {
                    const response = await fetch('api/test-api.php?action=available_tests');
                    const data = await response.json();

                    if (data.success) {
                        this.renderTestCards(data.data);
                    }
                } catch (error) {
                    console.error('Failed to load tests:', error);
                    document.getElementById('testCards').innerHTML = `
                        <div class="empty-state">
                            <p>שגיאה בטעינת הבדיקות</p>
                        </div>
                    `;
                }
            },

            renderTestCards(tests) {
                const container = document.getElementById('testCards');
                container.innerHTML = tests.map(test => `
                    <div class="test-card" data-test-id="${test.id}">
                        <div class="test-card-header">
                            <span class="test-card-title">${test.name}</span>
                        </div>
                        <p class="test-card-description">${test.description}</p>
                        <div class="test-card-actions">
                            <button type="button" class="btn btn-secondary btn-sm" onclick="TestManager.runSingleTest('${test.id}')">
                                <i class="fas fa-play"></i>
                                הרץ
                            </button>
                        </div>
                    </div>
                `).join('');
            },

            async loadHistory() {
                try {
                    const response = await fetch('api/test-api.php?action=history&limit=10');
                    const data = await response.json();

                    if (data.success) {
                        this.renderHistory(data.data);
                    }
                } catch (error) {
                    console.error('Failed to load history:', error);
                }
            },

            renderHistory(runs) {
                const tbody = document.getElementById('historyTableBody');

                if (runs.length === 0) {
                    tbody.innerHTML = `
                        <tr>
                            <td colspan="6" class="empty-state">
                                <div class="empty-icon">📋</div>
                                <p>אין היסטוריית בדיקות</p>
                            </td>
                        </tr>
                    `;
                    return;
                }

                tbody.innerHTML = runs.map(run => `
                    <tr>
                        <td><code>${run.run_id.substring(0, 8)}...</code></td>
                        <td>
                            <span class="status-badge ${run.status}">
                                ${this.getStatusIcon(run.status)}
                                ${this.getStatusText(run.status)}
                            </span>
                        </td>
                        <td>${run.total_tests}</td>
                        <td>
                            <span style="color: #22c55e">${run.passed_tests}</span> /
                            <span style="color: #ef4444">${run.failed_tests}</span>
                        </td>
                        <td>${this.formatDate(run.started_at)}</td>
                        <td>${run.creator_name || '-'}</td>
                    </tr>
                `).join('');
            },

            getStatusIcon(status) {
                const icons = {
                    'running': '<i class="fas fa-spinner fa-spin"></i>',
                    'completed': '<i class="fas fa-check"></i>',
                    'failed': '<i class="fas fa-times"></i>',
                    'cancelled': '<i class="fas fa-ban"></i>'
                };
                return icons[status] || '';
            },

            getStatusText(status) {
                const texts = {
                    'running': 'פועל',
                    'completed': 'הושלם',
                    'failed': 'נכשל',
                    'cancelled': 'בוטל'
                };
                return texts[status] || status;
            },

            formatDate(dateStr) {
                if (!dateStr) return '-';
                const date = new Date(dateStr);
                return date.toLocaleString('he-IL', {
                    day: '2-digit',
                    month: '2-digit',
                    year: 'numeric',
                    hour: '2-digit',
                    minute: '2-digit'
                });
            },

            async runAllTests() {
                if (this.isRunning) return;

                this.isRunning = true;
                this.showRunningState();

                try {
                    const response = await fetch('api/test-api.php?action=start_all', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' }
                    });

                    const data = await response.json();

                    if (data.success) {
                        this.showResults(data.data);
                    } else {
                        throw new Error(data.error || 'Unknown error');
                    }
                } catch (error) {
                    console.error('Test failed:', error);
                    this.showError(error.message);
                } finally {
                    this.isRunning = false;
                    this.loadHistory();
                }
            },

            async runSingleTest(testId) {
                if (this.isRunning) return;

                this.isRunning = true;
                this.showRunningState();

                try {
                    const response = await fetch('api/test-api.php?action=start_test', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ test_id: testId })
                    });

                    const data = await response.json();

                    if (data.success) {
                        this.showResults(data.data);
                    } else {
                        throw new Error(data.error || 'Unknown error');
                    }
                } catch (error) {
                    console.error('Test failed:', error);
                    this.showError(error.message);
                } finally {
                    this.isRunning = false;
                    this.loadHistory();
                }
            },

            showRunningState() {
                const runEl = document.getElementById('currentRun');
                runEl.classList.add('active');

                document.getElementById('runAllBtn').disabled = true;
                document.getElementById('runStatusIcon').className = 'run-status-icon running';
                document.getElementById('runStatusIcon').innerHTML = '<i class="fas fa-spinner"></i>';
                document.getElementById('runStatusText').textContent = 'מריץ...';

                document.getElementById('totalTests').textContent = '0';
                document.getElementById('passedTests').textContent = '0';
                document.getElementById('failedTests').textContent = '0';
                document.getElementById('testResultsList').innerHTML = '';
            },

            showResults(data) {
                document.getElementById('runAllBtn').disabled = false;

                const statusIcon = document.getElementById('runStatusIcon');
                const statusText = document.getElementById('runStatusText');

                if (data.failed === 0) {
                    statusIcon.className = 'run-status-icon';
                    statusIcon.innerHTML = '<i class="fas fa-check-circle" style="color: #22c55e"></i>';
                    statusText.textContent = 'הושלם בהצלחה';
                } else {
                    statusIcon.className = 'run-status-icon';
                    statusIcon.innerHTML = '<i class="fas fa-exclamation-circle" style="color: #ef4444"></i>';
                    statusText.textContent = 'הושלם עם שגיאות';
                }

                document.getElementById('totalTests').textContent = data.total;
                document.getElementById('passedTests').textContent = data.passed;
                document.getElementById('failedTests').textContent = data.failed;

                const resultsHtml = (data.results || []).map(result => `
                    <div class="test-result-item">
                        <div class="test-result-icon ${result.passed ? 'passed' : 'failed'}">
                            <i class="fas fa-${result.passed ? 'check' : 'times'}"></i>
                        </div>
                        <div class="test-result-content">
                            <div class="test-result-name">${this.getTestName(result.test)}</div>
                            <div class="test-result-message">${result.message}</div>
                        </div>
                        <div class="test-result-time">${result.timestamp ? this.formatDate(result.timestamp) : ''}</div>
                    </div>
                `).join('');

                document.getElementById('testResultsList').innerHTML = resultsHtml;
            },

            getTestName(testId) {
                const names = {
                    'basic_send': 'שליחה בסיסית',
                    'multiple_users': 'שליחה למספר משתמשים',
                    'approval': 'התראת אישור',
                    'scheduled': 'התראה מתוזמנת',
                    'delivery_logging': 'רישום לוגים'
                };
                return names[testId] || testId;
            },

            showError(message) {
                document.getElementById('runAllBtn').disabled = false;

                const statusIcon = document.getElementById('runStatusIcon');
                const statusText = document.getElementById('runStatusText');

                statusIcon.className = 'run-status-icon';
                statusIcon.innerHTML = '<i class="fas fa-exclamation-triangle" style="color: #ef4444"></i>';
                statusText.textContent = 'שגיאה: ' + message;
            }
        };

        // Initialize on load
        document.addEventListener('DOMContentLoaded', () => {
            TestManager.init();
        });
    </script>
</body>
</html>
