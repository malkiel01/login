/*
 * File: user-settings/migration.sql
 * Version: 1.0.0
 * Created: 2026-01-23
 * Author: Malkiel
 * Description: טבלת הגדרות משתמש
 */

-- טבלת הגדרות משתמש
CREATE TABLE IF NOT EXISTS user_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    userId VARCHAR(50) NOT NULL,
    settingKey VARCHAR(100) NOT NULL,
    settingValue TEXT,
    settingType ENUM('string', 'number', 'boolean', 'json') DEFAULT 'string',
    category VARCHAR(50) DEFAULT 'general',
    createDate DATETIME DEFAULT CURRENT_TIMESTAMP,
    updateDate DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY unique_user_setting (userId, settingKey),
    INDEX idx_userId (userId),
    INDEX idx_category (category),
    INDEX idx_settingKey (settingKey)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ערכי ברירת מחדל גלובליים (אופציונלי)
CREATE TABLE IF NOT EXISTS user_settings_defaults (
    id INT AUTO_INCREMENT PRIMARY KEY,
    settingKey VARCHAR(100) NOT NULL UNIQUE,
    defaultValue TEXT,
    settingType ENUM('string', 'number', 'boolean', 'json') DEFAULT 'string',
    category VARCHAR(50) DEFAULT 'general',
    label VARCHAR(100),
    description TEXT,
    options TEXT COMMENT 'JSON array of options for select/radio',
    sortOrder INT DEFAULT 0,
    isActive TINYINT(1) DEFAULT 1,
    createDate DATETIME DEFAULT CURRENT_TIMESTAMP,
    updateDate DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_category (category),
    INDEX idx_sortOrder (sortOrder)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- הכנסת ערכי ברירת מחדל
INSERT INTO user_settings_defaults (settingKey, defaultValue, settingType, category, label, description, sortOrder) VALUES
-- הגדרות תצוגה
('theme', 'light', 'string', 'display', 'ערכת נושא', 'בחר ערכת צבעים', 1),
('fontSize', '14', 'number', 'display', 'גודל גופן', 'גודל הגופן בפיקסלים', 2),
('tableRowsPerPage', '25', 'number', 'display', 'שורות בטבלה', 'מספר שורות להצגה בכל עמוד', 3),
('sidebarCollapsed', 'false', 'boolean', 'display', 'סרגל צד מכווץ', 'האם לכווץ את סרגל הצד', 4),
('compactMode', 'false', 'boolean', 'display', 'מצב קומפקטי', 'תצוגה מרוכזת יותר', 5),

-- הגדרות התראות
('notificationsEnabled', 'true', 'boolean', 'notifications', 'הפעל התראות', 'קבל התראות על פעולות', 1),
('soundEnabled', 'true', 'boolean', 'notifications', 'צלילים', 'השמע צלילים להתראות', 2),
('emailNotifications', 'true', 'boolean', 'notifications', 'התראות במייל', 'קבל עדכונים למייל', 3),

-- הגדרות טבלאות
('tableDefaultSort', 'createDate', 'string', 'tables', 'מיון ברירת מחדל', 'העמודה למיון ברירת מחדל', 1),
('tableSortDirection', 'desc', 'string', 'tables', 'כיוון מיון', 'כיוון המיון (עולה/יורד)', 2),
('tableShowFilters', 'true', 'boolean', 'tables', 'הצג מסננים', 'הצג פאנל מסננים בטבלאות', 3),
('tableAnimations', 'true', 'boolean', 'tables', 'אנימציות', 'הפעל אנימציות בטבלאות', 4),

-- הגדרות ניווט
('defaultLandingPage', 'dashboard', 'string', 'navigation', 'דף נחיתה', 'הדף הראשון בכניסה', 1),
('rememberLastPage', 'true', 'boolean', 'navigation', 'זכור עמוד אחרון', 'חזור לעמוד האחרון בכניסה', 2),
('breadcrumbsEnabled', 'true', 'boolean', 'navigation', 'נתיב ניווט', 'הצג נתיב ניווט', 3),

-- הגדרות שפה ואזור
('language', 'he', 'string', 'locale', 'שפה', 'שפת הממשק', 1),
('dateFormat', 'dd/mm/yyyy', 'string', 'locale', 'פורמט תאריך', 'אופן הצגת תאריכים', 2),
('timeFormat', '24h', 'string', 'locale', 'פורמט שעה', 'פורמט 12/24 שעות', 3),
('currency', 'ILS', 'string', 'locale', 'מטבע', 'מטבע ברירת מחדל', 4)

ON DUPLICATE KEY UPDATE updateDate = CURRENT_TIMESTAMP;
