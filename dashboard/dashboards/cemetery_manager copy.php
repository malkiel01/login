<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>מערכת ניהול בתי עלמין - דף ראשי</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', 'Roboto', 'Helvetica Neue', Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        /* כותרת עליונה */
        .header {
            background: rgba(255, 255, 255, 0.95);
            padding: 20px 30px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            backdrop-filter: blur(10px);
        }

        .header-content {
            max-width: 1400px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .logo-section {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .logo {
            width: 50px;
            height: 50px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            color: white;
        }

        .site-title {
            font-size: 24px;
            font-weight: 700;
            color: #2d3748;
        }

        .site-subtitle {
            font-size: 14px;
            color: #718096;
            margin-top: 2px;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 10px 20px;
            background: #f7fafc;
            border-radius: 10px;
        }

        .user-avatar {
            width: 40px;
            height: 40px;
            background: #cbd5e0;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
        }

        .user-name {
            font-weight: 600;
            color: #2d3748;
        }

        .user-role {
            font-size: 12px;
            color: #718096;
        }

        /* תוכן ראשי */
        .main-container {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 40px 20px;
        }

        .modules-grid {
            max-width: 1200px;
            width: 100%;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 25px;
            padding: 20px;
        }

        .module-card {
            background: white;
            border-radius: 20px;
            padding: 30px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
            cursor: pointer;
            position: relative;
            overflow: hidden;
            text-decoration: none;
            color: inherit;
            display: block;
        }

        .module-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--color-primary), var(--color-secondary));
            transform: scaleX(0);
            transition: transform 0.3s ease;
        }

        .module-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.15);
        }

        .module-card:hover::before {
            transform: scaleX(1);
        }

        .module-icon {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, var(--color-primary), var(--color-secondary));
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
            margin-bottom: 20px;
            transition: transform 0.3s ease;
        }

        .module-card:hover .module-icon {
            transform: scale(1.1) rotate(5deg);
        }

        .module-title {
            font-size: 20px;
            font-weight: 700;
            color: #2d3748;
            margin-bottom: 10px;
        }

        .module-description {
            font-size: 14px;
            color: #718096;
            line-height: 1.6;
            margin-bottom: 15px;
        }

        .module-stats {
            display: flex;
            gap: 20px;
            padding-top: 15px;
            border-top: 1px solid #e2e8f0;
        }

        .stat-item {
            display: flex;
            flex-direction: column;
        }

        .stat-value {
            font-size: 18px;
            font-weight: 700;
            color: var(--color-primary);
        }

        .stat-label {
            font-size: 12px;
            color: #a0aec0;
            margin-top: 2px;
        }

        /* צבעים למודולים */
        .module-cemeteries {
            --color-primary: #667eea;
            --color-secondary: #764ba2;
        }

        .module-customers {
            --color-primary: #4facfe;
            --color-secondary: #00f2fe;
        }

        .module-purchases {
            --color-primary: #43e97b;
            --color-secondary: #38f9d7;
        }

        .module-burials {
            --color-primary: #fa709a;
            --color-secondary: #fee140;
        }

        .module-reports {
            --color-primary: #30cfd0;
            --color-secondary: #330867;
        }

        .module-settings {
            --color-primary: #a8edea;
            --color-secondary: #fed6e3;
        }

        /* תגית חדש/בקרוב */
        .module-badge {
            position: absolute;
            top: 15px;
            left: 15px;
            background: #ff6b6b;
            color: white;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .badge-new {
            background: #48bb78;
        }

        .badge-soon {
            background: #ed8936;
        }

        /* כותרת תחתונה */
        .footer {
            background: rgba(255, 255, 255, 0.95);
            padding: 20px;
            text-align: center;
            margin-top: auto;
        }

        .footer-text {
            color: #718096;
            font-size: 14px;
        }

        /* רספונסיב */
        @media (max-width: 768px) {
            .header-content {
                flex-direction: column;
                gap: 20px;
            }

            .modules-grid {
                grid-template-columns: 1fr;
                padding: 10px;
            }

            .module-card {
                padding: 25px;
            }
        }

        /* אנימציה לטעינה */
        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .module-card {
            animation: fadeIn 0.5s ease forwards;
            opacity: 0;
        }

        .module-card:nth-child(1) { animation-delay: 0.1s; }
        .module-card:nth-child(2) { animation-delay: 0.2s; }
        .module-card:nth-child(3) { animation-delay: 0.3s; }
        .module-card:nth-child(4) { animation-delay: 0.4s; }
        .module-card:nth-child(5) { animation-delay: 0.5s; }
        .module-card:nth-child(6) { animation-delay: 0.6s; }
    </style>
</head>
<body>
    <!-- כותרת עליונה -->
    <header class="header">
        <div class="header-content">
            <div class="logo-section">
                <div class="logo">🏛️</div>
                <div>
                    <div class="site-title">מערכת ניהול בתי עלמין</div>
                    <div class="site-subtitle">ניהול מקיף ויעיל של בתי עלמין</div>
                </div>
            </div>
            <div class="user-info">
                <div class="user-avatar">👤</div>
                <div>
                    <div class="user-name">מנהל המערכת</div>
                    <div class="user-role">הרשאת מנהל ראשי</div>
                </div>
            </div>
        </div>
    </header>

    <!-- תוכן ראשי -->
    <main class="main-container">
        <div class="modules-grid">
            <!-- מודול ניהול בתי עלמין -->
            <a href="/dashboard/dashboards/cemeteries/index.php" class="module-card module-cemeteries">
                <div class="module-icon">🏛️</div>
                <h3 class="module-title">ניהול בתי עלמין</h3>
                <p class="module-description">
                    ניהול היררכי מלא: בתי עלמין, גושים, חלקות, אחוזות קבר וקברים
                </p>
                <div class="module-stats">
                    <div class="stat-item">
                        <span class="stat-value">5</span>
                        <span class="stat-label">בתי עלמין</span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-value">1,234</span>
                        <span class="stat-label">קברים</span>
                    </div>
                </div>
            </a>

            <!-- מודול ניהול לקוחות -->
            <a href="/dashboard/dashboards/customers/" class="module-card module-customers">
                <span class="module-badge badge-soon">בקרוב</span>
                <div class="module-icon">👥</div>
                <h3 class="module-title">ניהול לקוחות</h3>
                <p class="module-description">
                    ניהול רשימת לקוחות, נפטרים, בני משפחה ופרטי קשר
                </p>
                <div class="module-stats">
                    <div class="stat-item">
                        <span class="stat-value">856</span>
                        <span class="stat-label">לקוחות</span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-value">234</span>
                        <span class="stat-label">פעילים</span>
                    </div>
                </div>
            </a>

            <!-- מודול ניהול רכישות -->
            <a href="/dashboard/dashboards/purchases/" class="module-card module-purchases">
                <span class="module-badge badge-soon">בקרוב</span>
                <div class="module-icon">💰</div>
                <h3 class="module-title">ניהול רכישות</h3>
                <p class="module-description">
                    ניהול רכישות חלקות קבר, חוזים, תשלומים ומעקב פיננסי
                </p>
                <div class="module-stats">
                    <div class="stat-item">
                        <span class="stat-value">342</span>
                        <span class="stat-label">רכישות</span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-value">₪2.5M</span>
                        <span class="stat-label">סה"כ</span>
                    </div>
                </div>
            </a>

            <!-- מודול ניהול קבורות -->
            <a href="/dashboard/dashboards/burials/" class="module-card module-burials">
                <span class="module-badge badge-soon">בקרוב</span>
                <div class="module-icon">⚱️</div>
                <h3 class="module-title">ניהול קבורות</h3>
                <p class="module-description">
                    רישום וניהול קבורות, תיעוד נפטרים ומיקומי קבורה
                </p>
                <div class="module-stats">
                    <div class="stat-item">
                        <span class="stat-value">678</span>
                        <span class="stat-label">קבורות</span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-value">45</span>
                        <span class="stat-label">החודש</span>
                    </div>
                </div>
            </a>

            <!-- מודול דוחות -->
            <a href="/dashboard/dashboards/reports/" class="module-card module-reports">
                <span class="module-badge badge-soon">בקרוב</span>
                <div class="module-icon">📊</div>
                <h3 class="module-title">דוחות וסטטיסטיקות</h3>
                <p class="module-description">
                    הפקת דוחות מפורטים, ניתוחים סטטיסטיים ותובנות עסקיות
                </p>
                <div class="module-stats">
                    <div class="stat-item">
                        <span class="stat-value">25</span>
                        <span class="stat-label">סוגי דוחות</span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-value">PDF</span>
                        <span class="stat-label">ייצוא</span>
                    </div>
                </div>
            </a>

            <!-- מודול הגדרות -->
            <a href="/dashboard/dashboards/settings/" class="module-card module-settings">
                <span class="module-badge badge-soon">בקרוב</span>
                <div class="module-icon">⚙️</div>
                <h3 class="module-title">הגדרות מערכת</h3>
                <p class="module-description">
                    ניהול משתמשים, הרשאות, הגדרות מערכת וגיבויים
                </p>
                <div class="module-stats">
                    <div class="stat-item">
                        <span class="stat-value">8</span>
                        <span class="stat-label">משתמשים</span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-value">3</span>
                        <span class="stat-label">תפקידים</span>
                    </div>
                </div>
            </a>
        </div>
    </main>

    <!-- כותרת תחתונה -->
    <footer class="footer">
        <div class="footer-text">
            © 2025 מערכת ניהול בתי עלמין - כל הזכויות שמורות | גרסה 1.0.0
        </div>
    </footer>

    <script>
        // הוספת אינטראקטיביות
        document.addEventListener('DOMContentLoaded', function() {
            // סימולציה של טעינת נתונים אמיתיים
            setTimeout(() => {
                updateStats();
            }, 1000);
        });

        function updateStats() {
            // כאן תוכל להוסיף קריאת AJAX לקבלת נתונים אמיתיים
            console.log('Loading real statistics...');
        }

        // הוספת אפקט hover למודולים
        const cards = document.querySelectorAll('.module-card');
        cards.forEach(card => {
            card.addEventListener('mouseenter', function() {
                this.style.transform = 'translateY(-8px) scale(1.02)';
            });
            
            card.addEventListener('mouseleave', function() {
                this.style.transform = 'translateY(0) scale(1)';
            });
        });
    </script>
</body>
</html>