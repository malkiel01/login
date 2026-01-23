/*
 * File: user-settings/update-theme-options.sql
 * Description: עדכון אפשרויות לבחירת ערכת נושא
 * הרץ את זה אם הטבלאות כבר קיימות
 */

UPDATE user_settings_defaults
SET options = '[{"value":"light","label":"בהיר"},{"value":"dark","label":"כהה"}]'
WHERE settingKey = 'theme';
