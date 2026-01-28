<?php
/**
 * Push Notification Debug Page
 */
require_once $_SERVER['DOCUMENT_ROOT'] . '/push/config.php';
?>
<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Push Debug</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; max-width: 800px; margin: 0 auto; }
        .box { border: 1px solid #ccc; padding: 15px; margin: 10px 0; border-radius: 8px; }
        .success { background: #d4edda; border-color: #28a745; }
        .error { background: #f8d7da; border-color: #dc3545; }
        .info { background: #e2e3e5; }
        pre { background: #f4f4f4; padding: 10px; overflow-x: auto; font-size: 12px; }
        button { padding: 10px 20px; margin: 5px; cursor: pointer; }
        #log { background: #1e1e1e; color: #d4d4d4; padding: 15px; min-height: 200px; font-family: monospace; font-size: 12px; white-space: pre-wrap; }
    </style>
</head>
<body>
    <h1>🔔 Push Notification Debug</h1>

    <div class="box info">
        <h3>Server Status</h3>
        <p><strong>VAPID Public Key:</strong> <?php echo strlen(VAPID_PUBLIC_KEY) > 0 ? '✅ Configured (' . strlen(VAPID_PUBLIC_KEY) . ' chars)' : '❌ Missing'; ?></p>
        <p><strong>VAPID Private Key:</strong> <?php echo defined('VAPID_PRIVATE_KEY_PEM') && strlen(VAPID_PRIVATE_KEY_PEM) > 0 ? '✅ Configured' : '❌ Missing'; ?></p>
        <p><strong>VAPID Subject:</strong> <?php echo VAPID_SUBJECT; ?></p>
    </div>

    <div class="box info">
        <h3>Browser Status</h3>
        <p id="browserStatus">Checking...</p>
    </div>

    <div class="box">
        <h3>Actions</h3>
        <button onclick="checkSubscription()">Check Subscription</button>
        <button onclick="subscribe()">Subscribe</button>
        <button onclick="unsubscribe()">Unsubscribe</button>
        <button onclick="testVapidKey()">Test VAPID Key</button>
        <button onclick="clearLog()">Clear Log</button>
    </div>

    <div class="box">
        <h3>Console Log</h3>
        <div id="log"></div>
    </div>

    <script>
        const log = (msg, type = 'info') => {
            const el = document.getElementById('log');
            const time = new Date().toLocaleTimeString();
            const color = type === 'error' ? '#f48771' : type === 'success' ? '#89d185' : '#d4d4d4';
            el.innerHTML += `<span style="color:${color}">[${time}] ${msg}</span>\n`;
            el.scrollTop = el.scrollHeight;
            console.log(msg);
        };

        const clearLog = () => document.getElementById('log').innerHTML = '';

        // Check browser status
        async function checkBrowserStatus() {
            let html = '';
            html += `<p>Service Worker: ${'serviceWorker' in navigator ? '✅' : '❌'}</p>`;
            html += `<p>Push Manager: ${'PushManager' in window ? '✅' : '❌'}</p>`;
            html += `<p>Notification: ${'Notification' in window ? '✅' : '❌'}</p>`;
            html += `<p>Notification Permission: <strong>${Notification.permission}</strong></p>`;

            if ('serviceWorker' in navigator) {
                const reg = await navigator.serviceWorker.getRegistration();
                html += `<p>SW Registration: ${reg ? '✅ Active' : '❌ None'}</p>`;
                if (reg) {
                    const sub = await reg.pushManager.getSubscription();
                    html += `<p>Push Subscription: ${sub ? '✅ Active' : '❌ None'}</p>`;
                }
            }

            document.getElementById('browserStatus').innerHTML = html;
        }

        async function checkSubscription() {
            log('Checking subscription...');
            try {
                const reg = await navigator.serviceWorker.ready;
                const sub = await reg.pushManager.getSubscription();
                if (sub) {
                    log('✅ Subscription found:', 'success');
                    log(JSON.stringify(sub.toJSON(), null, 2));
                } else {
                    log('❌ No subscription found', 'error');
                }
            } catch (e) {
                log('Error: ' + e.message, 'error');
            }
        }

        async function subscribe() {
            log('Starting subscription process...');

            // Request permission
            const permission = await Notification.requestPermission();
            log('Permission: ' + permission);
            if (permission !== 'granted') {
                log('❌ Permission denied', 'error');
                return;
            }

            // Get VAPID key
            log('Getting VAPID key from server...');
            const vapidResp = await fetch('/push/subscription-api.php?action=vapid_key');
            const vapidData = await vapidResp.json();
            log('VAPID response: ' + JSON.stringify(vapidData));

            if (!vapidData.success || !vapidData.publicKey) {
                log('❌ Failed to get VAPID key', 'error');
                return;
            }

            // Convert VAPID key
            log('Converting VAPID key...');
            const vapidKey = urlBase64ToUint8Array(vapidData.publicKey);
            log('VAPID key converted, length: ' + vapidKey.length);

            // Subscribe
            log('Subscribing to push...');
            try {
                const reg = await navigator.serviceWorker.ready;
                const sub = await reg.pushManager.subscribe({
                    userVisibleOnly: true,
                    applicationServerKey: vapidKey
                });
                log('✅ Subscribed!', 'success');
                log(JSON.stringify(sub.toJSON(), null, 2));

                // Save to server
                log('Saving subscription to server...');
                const saveResp = await fetch('/push/subscription-api.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    credentials: 'include',
                    body: JSON.stringify({
                        action: 'subscribe',
                        subscription: sub.toJSON()
                    })
                });
                const saveData = await saveResp.json();
                log('Save response: ' + JSON.stringify(saveData));

                if (saveData.success) {
                    log('✅ Subscription saved to server!', 'success');
                } else {
                    log('❌ Failed to save: ' + saveData.error, 'error');
                }

            } catch (e) {
                log('❌ Subscribe error: ' + e.message, 'error');
                console.error(e);
            }

            checkBrowserStatus();
        }

        async function unsubscribe() {
            log('Unsubscribing...');
            try {
                const reg = await navigator.serviceWorker.ready;
                const sub = await reg.pushManager.getSubscription();
                if (sub) {
                    await sub.unsubscribe();
                    log('✅ Unsubscribed', 'success');
                } else {
                    log('No subscription to unsubscribe');
                }
            } catch (e) {
                log('Error: ' + e.message, 'error');
            }
            checkBrowserStatus();
        }

        async function testVapidKey() {
            log('Testing VAPID key...');
            const resp = await fetch('/push/subscription-api.php?action=vapid_key');
            const data = await resp.json();
            log('Response: ' + JSON.stringify(data, null, 2));

            if (data.publicKey) {
                log('Public key length: ' + data.publicKey.length + ' chars');
                try {
                    const bytes = urlBase64ToUint8Array(data.publicKey);
                    log('Decoded length: ' + bytes.length + ' bytes');
                    if (bytes.length === 65 && bytes[0] === 4) {
                        log('✅ Valid uncompressed EC point', 'success');
                    } else {
                        log('❌ Invalid key format', 'error');
                    }
                } catch (e) {
                    log('❌ Failed to decode: ' + e.message, 'error');
                }
            }
        }

        function urlBase64ToUint8Array(base64String) {
            const padding = '='.repeat((4 - base64String.length % 4) % 4);
            const base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
            const rawData = window.atob(base64);
            const outputArray = new Uint8Array(rawData.length);
            for (let i = 0; i < rawData.length; ++i) {
                outputArray[i] = rawData.charCodeAt(i);
            }
            return outputArray;
        }

        // Init
        checkBrowserStatus();
        log('Debug page loaded');
    </script>
</body>
</html>
