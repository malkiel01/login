/*
 * File: user-settings/update-v2-colorscheme.sql
 * Description: עדכון להגדרות חדשות - darkMode כ-toggle + colorScheme
 * הרץ את זה אם הטבלאות כבר קיימות
 */

-- מחיקת הגדרת theme הישנה
DELETE FROM user_settings_defaults WHERE settingKey = 'theme';

-- הוספת הגדרות חדשות
INSERT INTO user_settings_defaults (settingKey, defaultValue, settingType, category, label, description, options, sortOrder) VALUES
('darkMode', 'false', 'boolean', 'display', 'מצב כהה', 'הפעל מצב תצוגה כהה', NULL, 1),
('colorScheme', 'purple', 'string', 'display', 'סגנון צבע', 'בחר סגנון צבעים (במצב בהיר)', '[{"value":"purple","label":"סגול"},{"value":"green","label":"ירוק מטאלי"}]', 2)
ON DUPLICATE KEY UPDATE
    defaultValue = VALUES(defaultValue),
    options = VALUES(options),
    label = VALUES(label),
    description = VALUES(description);

-- עדכון sortOrder של שאר ההגדרות
UPDATE user_settings_defaults SET sortOrder = 3 WHERE settingKey = 'fontSize';
UPDATE user_settings_defaults SET sortOrder = 4 WHERE settingKey = 'tableRowsPerPage';
UPDATE user_settings_defaults SET sortOrder = 5 WHERE settingKey = 'sidebarCollapsed';
UPDATE user_settings_defaults SET sortOrder = 6 WHERE settingKey = 'compactMode';

-- המרת theme קיים של משתמשים ל-darkMode
UPDATE user_settings
SET settingKey = 'darkMode',
    settingValue = CASE WHEN settingValue = 'dark' THEN 'true' ELSE 'false' END,
    settingType = 'boolean'
WHERE settingKey = 'theme';
