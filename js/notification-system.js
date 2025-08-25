// /family/js/notification-system.js
// מערכת התראות מתוקנת שעובדת בכל הסביבות

// פונקציה אוניברסלית מתוקנת להתראות
async function showNotificationUniversal(title, options = {}) {
    console.log(`📢 Attempting to show notification: ${title}`);
    
    // בדוק הרשאות
    if (!('Notification' in window)) {
        console.log('❌ Browser does not support notifications');
        showInPageBanner({ title, body: options.body });
        return false;
    }
    
    // בקש הרשאה אם צריך
    if (Notification.permission === 'default') {
        const permission = await Notification.requestPermission();
        if (permission !== 'granted') {
            console.log('❌ Permission denied');
            showInPageBanner({ title, body: options.body });
            return false;
        }
    } else if (Notification.permission === 'denied') {
        console.log('❌ Notifications are blocked');
        showInPageBanner({ title, body: options.body });
        return false;
    }
    
    // נסה דרך Service Worker (המועדף)
    if ('serviceWorker' in navigator) {
        try {
            const registration = await navigator.serviceWorker.ready;
            console.log('✅ Using Service Worker for notification');
            
            await registration.showNotification(title, {
                body: options.body || '',
                icon: options.icon || '/family/images/icons/android/android-launchericon-192-192.png',
                badge: options.badge || '/family/images/icons/android/android-launchericon-96-96.png',
                tag: options.tag || 'notification-' + Date.now(),
                requireInteraction: options.requireInteraction || false,
                vibrate: options.vibrate || [200, 100, 200],
                data: options.data || {},
                dir: 'rtl',
                lang: 'he',
                silent: false
            });
            
            return true;
        } catch (error) {
            console.error('Service Worker notification failed:', error);
            // המשך ל-fallback
        }
    }
    
    // נסה Notification API רגיל (רק אם לא HTTPS או אין Service Worker)
    try {
        // בדוק אם אנחנו ב-HTTP (לא HTTPS)
        if (window.location.protocol === 'http:' || window.location.hostname === 'localhost') {
            console.log('📱 Using standard Notification API');
            const notification = new Notification(title, {
                body: options.body || '',
                icon: options.icon || '/family/images/icons/android/android-launchericon-192-192.png',
                tag: options.tag || 'notification-' + Date.now(),
                dir: 'rtl',
                lang: 'he'
            });
            
            // סגור אוטומטית אחרי 5 שניות
            setTimeout(() => notification.close(), 5000);
            
            return true;
        } else {
            console.log('⚠️ HTTPS requires Service Worker');
            throw new Error('Must use Service Worker on HTTPS');
        }
    } catch (error) {
        console.error('Standard notification failed:', error);
    }
    
    // Fallback - הצג באנר בדף
    console.log('📋 Falling back to in-page banner');
    showInPageBanner({ title, body: options.body });
    
    return false;
}

// באנר בתוך הדף - משופר
function showInPageBanner(notification) {
    // מחק באנרים ישנים
    const oldBanners = document.querySelectorAll('.notification-banner');
    oldBanners.forEach(banner => banner.remove());
    
    const banner = document.createElement('div');
    banner.className = 'notification-banner';
    banner.style.cssText = `
        position: fixed;
        top: 80px;
        left: 20px;
        background: white;
        border-radius: 10px;
        padding: 15px;
        box-shadow: 0 4px 15px rgba(0,0,0,0.2);
        border-right: 4px solid #667eea;
        max-width: 350px;
        z-index: 9999;
        animation: slideInBanner 0.5s ease;
        direction: rtl;
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    `;
    
    banner.innerHTML = `
        <div style="display: flex; align-items: start; gap: 10px;">
            <div style="font-size: 24px;">🔔</div>
            <div style="flex: 1;">
                <h4 style="margin: 0 0 5px 0; color: #333; font-size: 16px;">
                    ${notification.title || 'התראה'}
                </h4>
                <p style="margin: 0; color: #666; font-size: 14px; line-height: 1.4;">
                    ${notification.body || ''}
                </p>
                <div style="margin-top: 8px; font-size: 12px; color: #999;">
                    ${new Date().toLocaleTimeString('he-IL')}
                </div>
            </div>
            <button onclick="this.parentElement.parentElement.remove()" 
                    style="background: none; border: none; font-size: 20px; 
                           color: #999; cursor: pointer; padding: 0; margin: 0;">
                ×
            </button>
        </div>
    `;
    
    document.body.appendChild(banner);
    
    // הסר אוטומטית אחרי 10 שניות
    setTimeout(() => {
        banner.style.animation = 'slideOutBanner 0.5s ease';
        setTimeout(() => banner.remove(), 500);
    }, 10000);
}

// הוסף CSS לאנימציות אם לא קיים
if (!document.getElementById('notification-banner-styles')) {
    const style = document.createElement('style');
    style.id = 'notification-banner-styles';
    style.textContent = `
        @keyframes slideInBanner {
            from {
                transform: translateX(-100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }
        
        @keyframes slideOutBanner {
            from {
                transform: translateX(0);
                opacity: 1;
            }
            to {
                transform: translateX(-100%);
                opacity: 0;
            }
        }
        
        .notification-banner {
            transition: all 0.3s ease;
        }
        
        .notification-banner:hover {
            transform: scale(1.02);
            box-shadow: 0 6px 20px rgba(0,0,0,0.25) !important;
        }
    `;
    document.head.appendChild(style);
}

// פונקציית בדיקה מתוקנת
async function testNotificationNow() {
    const timestamp = new Date().toLocaleTimeString('he-IL');
    const result = await showNotificationUniversal('בדיקה 🔔', {
        body: `התראת בדיקה - ${timestamp}`,
        icon: '/family/images/icons/android/android-launchericon-192-192.png',
        badge: '/family/images/icons/android/android-launchericon-96-96.png',
        tag: 'test-' + Date.now(),
        data: { test: true }
    });
    
    console.log(result ? '✅ Notification sent successfully!' : '⚠️ Displayed in-page banner');
    return result;
}

// בדיקת תמיכה וסטטוס
function checkNotificationSupport() {
    const support = {
        notifications: 'Notification' in window,
        serviceWorker: 'serviceWorker' in navigator,
        https: window.location.protocol === 'https:',
        permission: 'Notification' in window ? Notification.permission : 'not-supported'
    };
    
    console.log('📊 Notification Support Status:', support);
    return support;
}

// אתחול אוטומטי
console.log('🔔 Notification system loaded and ready');
checkNotificationSupport();