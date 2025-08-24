// // js/notifications.js - מערכת ניהול התראות Push

// class NotificationManager {
//     constructor() {
//         this.vapidPublicKey = null;
//         this.subscription = null;
//         this.isSupported = 'Notification' in window && 'serviceWorker' in navigator && 'PushManager' in window;
//     }

//     // אתחול מערכת ההתראות
//     async init() {
//         if (!this.isSupported) {
//             console.log('Push notifications are not supported');
//             return false;
//         }

//         try {
//             // קבל את המפתח הציבורי מהשרת
//             const response = await fetch('/api/get-vapid-key.php');
//             const data = await response.json();
//             this.vapidPublicKey = data.publicKey;

//             // בדוק אם יש הרשאה
//             const permission = await this.checkPermission();
//             if (permission === 'granted') {
//                 await this.subscribeUser();
//             }

//             return true;
//         } catch (error) {
//             console.error('Failed to initialize notifications:', error);
//             return false;
//         }
//     }

//     // בדיקת הרשאות
//     async checkPermission() {
//         return Notification.permission;
//     }

//     // בקשת הרשאה מהמשתמש
//     async requestPermission() {
//         if (!this.isSupported) {
//             this.showFallbackMessage('הדפדפן שלך אינו תומך בהתראות');
//             return false;
//         }

//         try {
//             const permission = await Notification.requestPermission();
            
//             if (permission === 'granted') {
//                 await this.subscribeUser();
//                 this.showSuccessMessage('התראות הופעלו בהצלחה!');
//                 return true;
//             } else if (permission === 'denied') {
//                 this.showErrorMessage('ההרשאה להתראות נדחתה');
//                 this.showInstructions();
//                 return false;
//             }
//         } catch (error) {
//             console.error('Permission request failed:', error);
//             this.showErrorMessage('שגיאה בבקשת הרשאה');
//             return false;
//         }
//     }

//     // רישום המשתמש להתראות Push
//     async subscribeUser() {
//         try {
//             const registration = await navigator.serviceWorker.ready;
            
//             // המרת המפתח הציבורי
//             const convertedVapidKey = this.urlBase64ToUint8Array(this.vapidPublicKey);
            
//             // יצירת מנוי
//             this.subscription = await registration.pushManager.subscribe({
//                 userVisibleOnly: true,
//                 applicationServerKey: convertedVapidKey
//             });

//             // שלח את המנוי לשרת
//             await this.sendSubscriptionToServer(this.subscription);
            
//             console.log('User is subscribed to push notifications');
//             return true;
//         } catch (error) {
//             console.error('Failed to subscribe user:', error);
//             return false;
//         }
//     }

//     // שליחת מנוי לשרת
//     async sendSubscriptionToServer(subscription) {
//         try {
//             const response = await fetch('/api/save-subscription.php', {
//                 method: 'POST',
//                 headers: {
//                     'Content-Type': 'application/json'
//                 },
//                 body: JSON.stringify({
//                     subscription: subscription,
//                     user_agent: navigator.userAgent,
//                     device_type: this.getDeviceType()
//                 })
//             });

//             if (!response.ok) {
//                 throw new Error('Failed to save subscription');
//             }

//             const data = await response.json();
//             console.log('Subscription saved:', data);
//         } catch (error) {
//             console.error('Failed to send subscription to server:', error);
//             throw error;
//         }
//     }

//     // ביטול מנוי
//     async unsubscribe() {
//         try {
//             if (this.subscription) {
//                 await this.subscription.unsubscribe();
                
//                 // הודע לשרת על ביטול המנוי
//                 await fetch('/api/remove-subscription.php', {
//                     method: 'POST',
//                     headers: {
//                         'Content-Type': 'application/json'
//                     },
//                     body: JSON.stringify({
//                         endpoint: this.subscription.endpoint
//                     })
//                 });

//                 this.subscription = null;
//                 this.showSuccessMessage('התראות בוטלו');
//                 return true;
//             }
//         } catch (error) {
//             console.error('Failed to unsubscribe:', error);
//             return false;
//         }
//     }

//     // בדיקת סטטוס מנוי
//     async checkSubscription() {
//         try {
//             const registration = await navigator.serviceWorker.ready;
//             this.subscription = await registration.pushManager.getSubscription();
//             return this.subscription !== null;
//         } catch (error) {
//             console.error('Failed to check subscription:', error);
//             return false;
//         }
//     }

//     // הצגת התראה מקומית (לבדיקה)
//     async showLocalNotification(title, options = {}) {
//         if (!this.isSupported || Notification.permission !== 'granted') {
//             return false;
//         }

//         try {
//             const registration = await navigator.serviceWorker.ready;
//             await registration.showNotification(title, {
//                 body: options.body || '',
//                 icon: options.icon || '/images/icons/icon-192x192.png',
//                 badge: options.badge || '/images/icons/badge-72x72.png',
//                 vibrate: options.vibrate || [200, 100, 200],
//                 tag: options.tag || 'local-notification',
//                 requireInteraction: options.requireInteraction || false,
//                 silent: options.silent || false,
//                 dir: 'rtl',
//                 lang: 'he',
//                 data: options.data || {},
//                 actions: options.actions || []
//             });
//             return true;
//         } catch (error) {
//             console.error('Failed to show notification:', error);
//             return false;
//         }
//     }

//     // פונקציות עזר
//     urlBase64ToUint8Array(base64String) {
//         const padding = '='.repeat((4 - base64String.length % 4) % 4);
//         const base64 = (base64String + padding)
//             .replace(/\-/g, '+')
//             .replace(/_/g, '/');

//         const rawData = window.atob(base64);
//         const outputArray = new Uint8Array(rawData.length);

//         for (let i = 0; i < rawData.length; ++i) {
//             outputArray[i] = rawData.charCodeAt(i);
//         }
//         return outputArray;
//     }

//     getDeviceType() {
//         const userAgent = navigator.userAgent;
//         if (/android/i.test(userAgent)) return 'Android';
//         if (/iPad|iPhone|iPod/.test(userAgent)) return 'iOS';
//         if (/Windows/.test(userAgent)) return 'Windows';
//         if (/Mac/.test(userAgent)) return 'Mac';
//         return 'Unknown';
//     }

//     // הודעות למשתמש
//     showSuccessMessage(message) {
//         this.showToast(message, 'success');
//     }

//     showErrorMessage(message) {
//         this.showToast(message, 'error');
//     }

//     showFallbackMessage(message) {
//         this.showToast(message, 'warning');
//     }

//     showToast(message, type = 'info') {
//         // יצירת אלמנט toast
//         const toast = document.createElement('div');
//         toast.className = `notification-toast notification-toast-${type}`;
//         toast.innerHTML = `
//             <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-circle' : 'info-circle'}"></i>
//             <span>${message}</span>
//         `;

//         // הוסף לדף
//         document.body.appendChild(toast);

//         // הצג עם אנימציה
//         setTimeout(() => toast.classList.add('show'), 100);

//         // הסר אחרי 3 שניות
//         setTimeout(() => {
//             toast.classList.remove('show');
//             setTimeout(() => toast.remove(), 300);
//         }, 3000);
//     }

//     showInstructions() {
//         const modal = document.createElement('div');
//         modal.className = 'notification-instructions-modal';
//         modal.innerHTML = `
//             <div class="modal-content">
//                 <h3>הפעלת התראות</h3>
//                 <p>כדי לקבל התראות, יש לאפשר הרשאה בהגדרות הדפדפן:</p>
//                 <ol>
//                     <li>לחץ על סמל המנעול בשורת הכתובת</li>
//                     <li>חפש את "התראות" או "Notifications"</li>
//                     <li>שנה ל"אפשר" או "Allow"</li>
//                     <li>רענן את הדף</li>
//                 </ol>
//                 <button onclick="this.parentElement.parentElement.remove()">סגור</button>
//             </div>
//         `;
//         document.body.appendChild(modal);
//     }
// }

// // יצירת אינסטנס גלובלי
// const notificationManager = new NotificationManager();

// // אתחול בטעינת הדף
// document.addEventListener('DOMContentLoaded', async () => {
//     // אתחל את מערכת ההתראות
//     await notificationManager.init();

//     // בדוק אם יש כפתור הרשמה להתראות
//     const enableNotificationsBtn = document.getElementById('enable-notifications');
//     if (enableNotificationsBtn) {
//         // עדכן סטטוס הכפתור
//         const updateButtonStatus = async () => {
//             const isSubscribed = await notificationManager.checkSubscription();
//             const permission = await notificationManager.checkPermission();
            
//             if (permission === 'denied') {
//                 enableNotificationsBtn.innerHTML = '<i class="fas fa-bell-slash"></i> התראות חסומות';
//                 enableNotificationsBtn.classList.add('disabled');
//                 enableNotificationsBtn.onclick = () => notificationManager.showInstructions();
//             } else if (isSubscribed) {
//                 enableNotificationsBtn.innerHTML = '<i class="fas fa-bell"></i> התראות פעילות';
//                 enableNotificationsBtn.classList.add('active');
//                 enableNotificationsBtn.onclick = async () => {
//                     if (confirm('האם לבטל קבלת התראות?')) {
//                         await notificationManager.unsubscribe();
//                         updateButtonStatus();
//                     }
//                 };
//             } else {
//                 enableNotificationsBtn.innerHTML = '<i class="fas fa-bell"></i> הפעל התראות';
//                 enableNotificationsBtn.classList.remove('active', 'disabled');
//                 enableNotificationsBtn.onclick = async () => {
//                     await notificationManager.requestPermission();
//                     updateButtonStatus();
//                 };
//             }
//         };

//         await updateButtonStatus();
//     }

//     // האזן להודעות מה-Service Worker
//     if ('serviceWorker' in navigator) {
//         navigator.serviceWorker.addEventListener('message', event => {
//             console.log('Message from service worker:', event.data);
            
//             if (event.data.type === 'data-updated') {
//                 // רענן נתונים בממשק
//                 if (typeof refreshData === 'function') {
//                     refreshData(event.data.data);
//                 }
//             }
//         });
//     }
// });

// // CSS להתראות Toast
// const style = document.createElement('style');
// style.textContent = `
// .notification-toast {
//     position: fixed;
//     bottom: 20px;
//     left: 50%;
//     transform: translateX(-50%) translateY(100px);
//     background: white;
//     padding: 15px 25px;
//     border-radius: 8px;
//     box-shadow: 0 4px 12px rgba(0,0,0,0.15);
//     display: flex;
//     align-items: center;
//     gap: 10px;
//     z-index: 10000;
//     transition: transform 0.3s ease;
//     min-width: 250px;
// }

// .notification-toast.show {
//     transform: translateX(-50%) translateY(0);
// }

// .notification-toast-success {
//     border-right: 4px solid #28a745;
// }

// .notification-toast-success i {
//     color: #28a745;
// }

// .notification-toast-error {
//     border-right: 4px solid #dc3545;
// }

// .notification-toast-error i {
//     color: #dc3545;
// }

// .notification-toast-warning {
//     border-right: 4px solid #ffc107;
// }

// .notification-toast-warning i {
//     color: #ffc107;
// }

// .notification-instructions-modal {
//     position: fixed;
//     top: 0;
//     left: 0;
//     right: 0;
//     bottom: 0;
//     background: rgba(0,0,0,0.5);
//     display: flex;
//     align-items: center;
//     justify-content: center;
//     z-index: 10001;
// }

// .notification-instructions-modal .modal-content {
//     background: white;
//     padding: 30px;
//     border-radius: 15px;
//     max-width: 400px;
//     direction: rtl;
// }

// .notification-instructions-modal h3 {
//     margin-bottom: 15px;
//     color: #333;
// }

// .notification-instructions-modal ol {
//     margin: 20px 0;
//     padding-right: 20px;
// }

// .notification-instructions-modal button {
//     background: #667eea;
//     color: white;
//     border: none;
//     padding: 10px 20px;
//     border-radius: 8px;
//     cursor: pointer;
//     font-size: 16px;
// }

// .notification-instructions-modal button:hover {
//     background: #5569d0;
// }
// `;
// document.head.appendChild(style);