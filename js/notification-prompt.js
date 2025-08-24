// js/notification-prompt.js - מערכת בקשת התראות אוטומטית

(function initNotificationPrompt() {
    // סגנונות לחלון בקשת התראות
    const notificationStyles = `
        <style>
            .notification-permission-modal {
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background: rgba(0,0,0,0.6);
                backdrop-filter: blur(5px);
                display: none;
                align-items: center;
                justify-content: center;
                z-index: 10001;
                animation: fadeIn 0.3s ease;
            }
            
            .notification-permission-modal.show {
                display: flex;
            }
            
            @keyframes fadeIn {
                from { opacity: 0; }
                to { opacity: 1; }
            }
            
            .notification-dialog {
                background: white;
                border-radius: 20px;
                padding: 0;
                max-width: 450px;
                width: 90%;
                box-shadow: 0 20px 60px rgba(0,0,0,0.3);
                animation: slideUp 0.5s cubic-bezier(0.68, -0.55, 0.265, 1.55);
                overflow: hidden;
            }
            
            @keyframes slideUp {
                from {
                    transform: translateY(50px) scale(0.95);
                    opacity: 0;
                }
                to {
                    transform: translateY(0) scale(1);
                    opacity: 1;
                }
            }
            
            .notification-header {
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                padding: 30px;
                text-align: center;
                color: white;
            }
            
            .notification-bell-icon {
                width: 80px;
                height: 80px;
                background: white;
                border-radius: 50%;
                margin: 0 auto 20px;
                display: flex;
                align-items: center;
                justify-content: center;
                font-size: 40px;
                box-shadow: 0 5px 20px rgba(0,0,0,0.1);
                animation: bellRing 2s ease-in-out infinite;
            }
            
            @keyframes bellRing {
                0%, 100% { transform: rotate(0deg); }
                10%, 30% { transform: rotate(10deg); }
                20%, 40% { transform: rotate(-10deg); }
                50% { transform: rotate(0deg); }
            }
            
            .notification-title {
                font-size: 24px;
                font-weight: bold;
                margin-bottom: 10px;
            }
            
            .notification-subtitle {
                font-size: 16px;
                opacity: 0.95;
            }
            
            .notification-body {
                padding: 30px;
            }
            
            .notification-features {
                margin-bottom: 25px;
            }
            
            .notification-feature {
                display: flex;
                align-items: center;
                gap: 15px;
                margin-bottom: 15px;
                padding: 12px;
                background: #f8f9fa;
                border-radius: 12px;
                transition: all 0.3s;
            }
            
            .notification-feature:hover {
                background: #e9ecef;
                transform: translateX(5px);
            }
            
            .notification-feature-icon {
                width: 40px;
                height: 40px;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                border-radius: 10px;
                display: flex;
                align-items: center;
                justify-content: center;
                color: white;
                font-size: 20px;
                flex-shrink: 0;
            }
            
            .notification-feature-text {
                flex: 1;
            }
            
            .notification-feature-title {
                font-weight: 600;
                color: #333;
                margin-bottom: 2px;
            }
            
            .notification-feature-desc {
                font-size: 13px;
                color: #666;
            }
            
            .notification-actions {
                display: flex;
                gap: 12px;
                margin-top: 25px;
            }
            
            .btn-allow {
                flex: 1;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                color: white;
                border: none;
                padding: 14px 24px;
                border-radius: 12px;
                font-size: 16px;
                font-weight: bold;
                cursor: pointer;
                transition: all 0.3s;
                display: flex;
                align-items: center;
                justify-content: center;
                gap: 8px;
                box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
            }
            
            .btn-allow:hover {
                transform: translateY(-2px);
                box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4);
            }
            
            .btn-later {
                flex: 1;
                background: #f8f9fa;
                color: #666;
                border: 2px solid #e9ecef;
                padding: 14px 24px;
                border-radius: 12px;
                font-size: 16px;
                cursor: pointer;
                transition: all 0.3s;
            }
            
            .btn-later:hover {
                background: #e9ecef;
                border-color: #dee2e6;
            }
            
            .notification-footer {
                padding: 15px 30px;
                background: #f8f9fa;
                text-align: center;
                font-size: 13px;
                color: #666;
            }
            
            @media (max-width: 480px) {
                .notification-dialog {
                    width: 95%;
                }
                
                .notification-header {
                    padding: 25px;
                }
                
                .notification-body {
                    padding: 20px;
                }
                
                .notification-bell-icon {
                    width: 70px;
                    height: 70px;
                    font-size: 35px;
                }
                
                .notification-title {
                    font-size: 20px;
                }
                
                .notification-subtitle {
                    font-size: 14px;
                }
            }
        </style>
    `;
    
    // הוסף סגנונות אם עוד לא קיימים
    if (!document.getElementById('notification-prompt-styles')) {
        const styleElement = document.createElement('div');
        styleElement.id = 'notification-prompt-styles';
        styleElement.innerHTML = notificationStyles;
        document.head.appendChild(styleElement);
    }
    
    // פונקציה לבדיקה והצגת החלון
    window.checkAndShowNotificationPrompt = function(forceShow = false) {
        // בדוק אם התראות נתמכות
        if (!('Notification' in window)) {
            console.log('הדפדפן לא תומך בהתראות');
            return;
        }
        
        // אם יש כבר הרשאה, לא צריך לבקש
        if (!forceShow && Notification.permission === 'granted') {
            console.log('התראות כבר מאושרות');
            return;
        }
        
        // בדוק אם כבר ביקשנו בעבר
        const notificationPermission = localStorage.getItem('notification-permission-asked');
        const lastAskTime = localStorage.getItem('notification-permission-time');
        
        // אם לא בכוח ונדחינו, חכה 3 ימים
        if (!forceShow && notificationPermission === 'denied' && lastAskTime) {
            const daysPassed = (Date.now() - parseInt(lastAskTime)) / (1000 * 60 * 60 * 24);
            if (daysPassed < 3) {
                return;
            }
        }
        
        // אם דחו "אולי מאוחר יותר", חכה יום
        if (!forceShow && notificationPermission === 'later' && lastAskTime) {
            const hoursPassed = (Date.now() - parseInt(lastAskTime)) / (1000 * 60 * 60);
            if (hoursPassed < 24) {
                return;
            }
        }
        
        // הצג את החלון
        showNotificationDialog();
    };
    
    function showNotificationDialog() {
        // צור את המודל
        const modal = document.createElement('div');
        modal.className = 'notification-permission-modal';
        modal.innerHTML = `
            <div class="notification-dialog">
                <div class="notification-header">
                    <div class="notification-bell-icon">
                        🔔
                    </div>
                    <div class="notification-title">קבל התראות בזמן אמת</div>
                    <div class="notification-subtitle">הישאר מעודכן עם כל הפעילות בקבוצות שלך</div>
                </div>
                
                <div class="notification-body">
                    <div class="notification-features">
                        <div class="notification-feature">
                            <div class="notification-feature-icon">👥</div>
                            <div class="notification-feature-text">
                                <div class="notification-feature-title">הזמנות לקבוצות</div>
                                <div class="notification-feature-desc">קבל התראה כשמזמינים אותך לקבוצה חדשה</div>
                            </div>
                        </div>
                        
                        <div class="notification-feature">
                            <div class="notification-feature-icon">🛒</div>
                            <div class="notification-feature-text">
                                <div class="notification-feature-title">קניות חדשות</div>
                                <div class="notification-feature-desc">דע מיד כשמישהו מוסיף קנייה בקבוצה שלך</div>
                            </div>
                        </div>
                        
                        <div class="notification-feature">
                            <div class="notification-feature-icon">💰</div>
                            <div class="notification-feature-text">
                                <div class="notification-feature-title">עדכוני חישובים</div>
                                <div class="notification-feature-desc">קבל סיכום מה המצב שלך בכל קבוצה</div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="notification-actions">
                        <button class="btn-allow" id="allow-notifications">
                            <span>אפשר התראות</span>
                            <span>✨</span>
                        </button>
                        <button class="btn-later" id="later-notifications">
                            אולי מאוחר יותר
                        </button>
                    </div>
                </div>
                
                <div class="notification-footer">
                    <span>תוכל לשנות את ההגדרות בכל עת</span>
                </div>
            </div>
        `;
        
        document.body.appendChild(modal);
        
        // הצג עם אנימציה
        setTimeout(() => {
            modal.classList.add('show');
        }, 100);
        
        // כפתור אישור
        document.getElementById('allow-notifications').onclick = async () => {
            try {
                const permission = await Notification.requestPermission();
                
                // שמור שביקשנו
                localStorage.setItem('notification-permission-asked', permission);
                localStorage.setItem('notification-permission-time', Date.now().toString());
                
                if (permission === 'granted') {
                    // הצג הודעת הצלחה
                    showNotificationSuccess();
                    
                    // שלח התראת בדיקה
                    setTimeout(() => {
                        new Notification('ברוך הבא! 👋', {
                            body: 'ההתראות הופעלו בהצלחה. מעכשיו תקבל עדכונים בזמן אמת.',
                            icon: '/family/images/icons/android/android-launchericon-192-192.png',
                            badge: '/family/images/icons/android/android-launchericon-96-96.png',
                            vibrate: [200, 100, 200],
                            tag: 'welcome'
                        });
                    }, 2000);
                }
                
                // הסר את המודל
                modal.classList.remove('show');
                setTimeout(() => {
                    modal.remove();
                }, 300);
                
            } catch (error) {
                console.error('Error requesting notification permission:', error);
            }
        };
        
        // כפתור דחייה
        document.getElementById('later-notifications').onclick = () => {
            localStorage.setItem('notification-permission-asked', 'later');
            localStorage.setItem('notification-permission-time', Date.now().toString());
            
            modal.classList.remove('show');
            setTimeout(() => {
                modal.remove();
            }, 300);
        };
    }
    
    function showNotificationSuccess() {
        const successBanner = document.createElement('div');
        successBanner.className = 'pwa-install-banner show';
        successBanner.style.cssText = `
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
            padding: 15px 20px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.2);
            z-index: 10000;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 15px;
            animation: slideDown 0.5s ease;
        `;
        successBanner.innerHTML = `
            <div style="font-size: 30px;">✅</div>
            <div>
                <div style="font-size: 18px; font-weight: bold;">התראות הופעלו בהצלחה!</div>
                <div style="font-size: 14px; opacity: 0.95;">תקבל עדכונים על כל הפעילות בקבוצות שלך</div>
            </div>
        `;
        
        document.body.appendChild(successBanner);
        
        setTimeout(() => {
            successBanner.style.animation = 'slideUp 0.5s ease';
            setTimeout(() => {
                successBanner.remove();
            }, 500);
        }, 4000);
    }
})();