<?php
/**
 * Push Permission Request Page
 * Opens in a popup to request notification permission
 */
session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/config.php';

$vapidPublicKey = defined('VAPID_PUBLIC_KEY') ? VAPID_PUBLIC_KEY : '';
?>
<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>הפעלת התראות</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .container {
            background: white;
            border-radius: 16px;
            padding: 30px;
            text-align: center;
            max-width: 350px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
        }
        .icon {
            font-size: 48px;
            color: #667eea;
            margin-bottom: 20px;
        }
        h1 {
            font-size: 20px;
            color: #333;
            margin-bottom: 15px;
        }
        p {
            color: #666;
            font-size: 14px;
            margin-bottom: 25px;
            line-height: 1.6;
        }
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 14px 28px;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            border: none;
            transition: all 0.3s;
            width: 100%;
        }
        .btn-primary {
            background: #667eea;
            color: white;
        }
        .btn-primary:hover {
            background: #5a67d8;
            transform: translateY(-2px);
        }
        .btn-primary:disabled {
            background: #ccc;
            cursor: not-allowed;
            transform: none;
        }
        .status {
            margin-top: 20px;
            padding: 12px;
            border-radius: 8px;
            font-size: 14px;
        }
        .status-success {
            background: #d1fae5;
            color: #065f46;
        }
        .status-error {
            background: #fee2e2;
            color: #991b1b;
        }
        .status-loading {
            background: #e0e7ff;
            color: #3730a3;
        }
        .close-note {
            margin-top: 15px;
            font-size: 12px;
            color: #999;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="icon">
            <i class="fas fa-bell"></i>
        </div>
        <h1>הפעלת התראות</h1>
        <p>לחץ על הכפתור למטה כדי לאפשר קבלת התראות מהמערכת. תתבקש לאשר את ההרשאה בדפדפן.</p>

        <button class="btn btn-primary" id="enableBtn" onclick="requestPermission()">
            <i class="fas fa-bell"></i>
            <span>הפעל התראות</span>
        </button>

        <div id="status" style="display: none;"></div>
        <div class="close-note" id="closeNote" style="display: none;">
            ניתן לסגור חלון זה
        </div>
    </div>

    <script src="/push/push-subscribe.js"></script>
    <script>
        const VAPID_KEY = '<?= $vapidPublicKey ?>';

        async function requestPermission() {
            const btn = document.getElementById('enableBtn');
            const status = document.getElementById('status');
            const closeNote = document.getElementById('closeNote');

            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> <span>ממתין להרשאה...</span>';

            status.style.display = 'block';
            status.className = 'status status-loading';
            status.innerHTML = '<i class="fas fa-spinner fa-spin"></i> מבקש הרשאה...';

            try {
                // בקש הרשאה
                const permission = await Notification.requestPermission();

                if (permission === 'granted') {
                    status.className = 'status status-loading';
                    status.innerHTML = '<i class="fas fa-spinner fa-spin"></i> נרשם להתראות...';

                    // רשום ל-push
                    if (typeof PushSubscriptionManager !== 'undefined') {
                        const result = await PushSubscriptionManager.subscribe();

                        if (result.success) {
                            status.className = 'status status-success';
                            status.innerHTML = '<i class="fas fa-check-circle"></i> התראות הופעלו בהצלחה!';
                            closeNote.style.display = 'block';

                            // שלח הודעה לחלון האב
                            if (window.opener) {
                                window.opener.postMessage({
                                    type: 'pushPermissionGranted',
                                    success: true
                                }, '*');
                            }

                            // סגור אוטומטית אחרי 2 שניות
                            setTimeout(() => window.close(), 2000);
                        } else {
                            throw new Error(result.error || 'שגיאה ברישום');
                        }
                    } else {
                        throw new Error('מנהל ההתראות לא זמין');
                    }
                } else {
                    status.className = 'status status-error';
                    status.innerHTML = '<i class="fas fa-times-circle"></i> ההרשאה נדחתה';
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fas fa-bell"></i> <span>נסה שוב</span>';
                }

            } catch (error) {
                console.error('Error:', error);
                status.className = 'status status-error';
                status.innerHTML = '<i class="fas fa-exclamation-circle"></i> שגיאה: ' + error.message;
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-bell"></i> <span>נסה שוב</span>';
            }
        }

        // בדוק אם כבר יש הרשאה
        if (Notification.permission === 'granted') {
            document.getElementById('status').style.display = 'block';
            document.getElementById('status').className = 'status status-success';
            document.getElementById('status').innerHTML = '<i class="fas fa-check-circle"></i> התראות כבר מופעלות!';
            document.getElementById('enableBtn').style.display = 'none';
            document.getElementById('closeNote').style.display = 'block';

            // רשום אם צריך
            if (typeof PushSubscriptionManager !== 'undefined') {
                PushSubscriptionManager.subscribe().then(result => {
                    if (result.success) {
                        setTimeout(() => window.close(), 1500);
                    }
                });
            }
        } else if (Notification.permission === 'denied') {
            document.getElementById('status').style.display = 'block';
            document.getElementById('status').className = 'status status-error';
            document.getElementById('status').innerHTML = '<i class="fas fa-times-circle"></i> התראות נחסמו בדפדפן. יש לאפשר בהגדרות.';
            document.getElementById('enableBtn').disabled = true;
        }
    </script>
</body>
</html>
