// /**
//  * PWA Native Prompt Manager
//  * מנהל התקנת PWA עם באנר נייטיב בלבד
//  * 
//  * גרסה פשוטה וקלה שמשתמשת רק בבאנר המובנה של הדפדפן
//  */

// (function() {
//     'use strict';

//     class PWANativePrompt {
//         constructor() {
//             this.deferredPrompt = null;
//             this.isIOS = /iPad|iPhone|iPod/.test(navigator.userAgent);
//             this.isStandalone = window.matchMedia('(display-mode: standalone)').matches || 
//                               window.navigator.standalone === true;
            
//             // אתחול רק אם לא מותקן
//             if (!this.isStandalone) {
//                 this.init();
//             }
//         }

//         init() {
//             // האזנה לאירוע beforeinstallprompt
//             window.addEventListener('beforeinstallprompt', (e) => {
//                 console.log('PWA: Install prompt available');
                
//                 // שמור את האירוע
//                 this.deferredPrompt = e;
                
//                 // אפשרות 1: הצג מיד (אוטומטי)
//                 // הדפדפן יציג את הבאנר הנייטיב שלו
//                 // פשוט אל תעשה preventDefault() והבאנר יופיע!
                
//                 // אפשרות 2: השהה את הבאנר (ידני)
//                 // אם תרצה לשלוט מתי להציג:
//                 // e.preventDefault();
//                 // ואז תוכל להפעיל ידנית עם: this.showPrompt();
//             });

//             // האזנה להתקנה מוצלחת
//             window.addEventListener('appinstalled', () => {
//                 console.log('PWA: App was installed successfully');
//                 this.deferredPrompt = null;
                
//                 // שמור סטטוס
//                 localStorage.setItem('pwa-installed', 'true');
                
//                 // אפשר להציג הודעת תודה
//                 this.showThankYouMessage();
//             });

//             // בדיקה ל-iOS
//             if (this.isIOS && !this.isStandalone) {
//                 this.checkIOSPrompt();
//             }

//             // הוסף כפתור להתקנה ידנית (אופציונלי)
//             this.addInstallButton();
//         }

//         // הצגת הבאנר הנייטיב (אם נדחה קודם)
//         async showPrompt() {
//             if (!this.deferredPrompt) {
//                 console.log('PWA: No installation prompt available');
//                 return false;
//             }

//             // הצג את הבאנר הנייטיב
//             this.deferredPrompt.prompt();
            
//             // חכה לתגובת המשתמש
//             const { outcome } = await this.deferredPrompt.userChoice;
            
//             console.log(`PWA: User response - ${outcome}`);
            
//             // נקה את הרפרנס
//             this.deferredPrompt = null;
            
//             return outcome === 'accepted';
//         }

//         // בדיקה ל-iOS - הם לא תומכים בבאנר אוטומטי
//         checkIOSPrompt() {
//             // בדוק אם המשתמש לא ראה את ההודעה
//             if (!localStorage.getItem('ios-prompt-shown')) {
//                 // הצג הודעה פעם אחת בלבד
//                 setTimeout(() => {
//                     if (confirm('להוסיף את האפליקציה למסך הבית?\n\nלחץ על כפתור השיתוף ⬆️ ואז "הוסף למסך הבית"')) {
//                         // סמן שהוצג
//                         localStorage.setItem('ios-prompt-shown', 'true');
//                     }
//                 }, 5000); // אחרי 5 שניות
//             }
//         }

//         // הוסף כפתור התקנה בממשק (אופציונלי)
//         addInstallButton() {
//             // בדוק אם יש כפתור בדף
//             const installButtons = document.querySelectorAll('.pwa-install-trigger');
            
//             installButtons.forEach(button => {
//                 // הסתר אם כבר מותקן
//                 if (this.isStandalone) {
//                     button.style.display = 'none';
//                     return;
//                 }

//                 // הצג רק אם יש אפשרות התקנה
//                 if (this.deferredPrompt || this.isIOS) {
//                     button.style.display = 'inline-block';
                    
//                     button.addEventListener('click', async () => {
//                         if (this.isIOS) {
//                             alert('להתקנה:\n1. לחץ על כפתור השיתוף ⬆️\n2. בחר "הוסף למסך הבית"\n3. לחץ "הוסף"');
//                         } else {
//                             const installed = await this.showPrompt();
//                             if (installed) {
//                                 button.style.display = 'none';
//                             }
//                         }
//                     });
//                 } else {
//                     // הסתר אם אין תמיכה
//                     button.style.display = 'none';
//                 }
//             });
//         }

//         // הודעת תודה אחרי התקנה
//         showThankYouMessage() {
//             // אפשר להציג Toast או הודעה
//             console.log('תודה שהתקנת את האפליקציה! 🎉');
            
//             // או alert פשוט
//             // alert('האפליקציה הותקנה בהצלחה! 🎉');
//         }

//         // API ציבורי
        
//         // בדיקה אם ניתן להתקין
//         canInstall() {
//             return !!this.deferredPrompt || this.isIOS;
//         }

//         // בדיקה אם מותקן
//         isInstalled() {
//             return this.isStandalone || localStorage.getItem('pwa-installed') === 'true';
//         }

//         // התקנה ידנית
//         async install() {
//             if (this.isIOS) {
//                 alert('להתקנה ב-iOS:\n1. לחץ על כפתור השיתוף ⬆️\n2. בחר "הוסף למסך הבית"');
//                 return false;
//             }
//             return await this.showPrompt();
//         }
//     }

//     // אתחול אוטומטי
//     if (document.readyState === 'loading') {
//         document.addEventListener('DOMContentLoaded', () => {
//             window.pwaPrompt = new PWANativePrompt();
//         });
//     } else {
//         window.pwaPrompt = new PWANativePrompt();
//     }

//     // חשוף גם את המחלקה
//     window.PWANativePrompt = PWANativePrompt;
// })();