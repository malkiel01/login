-- Notifications Permissions
-- Insert permissions for notifications module
-- Run this SQL to enable the notifications module in roles management

INSERT INTO `permissions` (`module`, `action`, `display_name`, `description`, `sort_order`) VALUES
('notifications', 'view', 'צפייה בהתראות', 'צפייה בהתראות מתוזמנות', 100),
('notifications', 'create', 'יצירת התראות', 'יצירת התראות חדשות', 101),
('notifications', 'edit', 'עריכת התראות', 'עריכת התראות קיימות', 102),
('notifications', 'delete', 'מחיקת התראות', 'מחיקה וביטול של התראות', 103)
ON DUPLICATE KEY UPDATE display_name=VALUES(display_name), description=VALUES(description);
