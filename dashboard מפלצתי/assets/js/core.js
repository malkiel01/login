 /**
  * Core JavaScript
  * פונקציות ליבה של הדשבורד
  */

 // Namespace
 window.Dashboard = window.Dashboard || {};

 // Configuration
 Dashboard.config = window.dashboardConfig || {};

 // API Client
 Dashboard.API = class {
     constructor(baseUrl) {
         this.baseUrl = baseUrl || Dashboard.config.apiUrl;
         this.headers = {
             'Content-Type': 'application/json',
             'X-CSRF-Token': Dashboard.config.csrfToken,
             'X-Requested-With': 'XMLHttpRequest'
         };
     }

     async request(method, endpoint, data = null) {
         const options = {
             method: method,
             headers: this.headers,
             credentials: 'same-origin'
         };

         if (data && method !== 'GET') {
             options.body = JSON.stringify(data);
         }

         try {
             const response = await fetch(this.baseUrl + endpoint, options);
             
             if (!response.ok) {
                 throw new Error(`HTTP ${response.status}: ${response.statusText}`);
             }

             const result = await response.json();
             
             if (!result.success && result.error) {
                 throw new Error(result.error);
             }

             return result;
         } catch (error) {
             console.error('API Error:', error);
             Dashboard.Toast.error('שגיאת API: ' + error.message);
             throw error;
         }
     }

     get(endpoint) {
         return this.request('GET', endpoint);
     }

     post(endpoint, data) {
         return this.request('POST', endpoint, data);
     }

     put(endpoint, data) {
         return this.request('PUT', endpoint, data);
     }

     delete(endpoint) {
         return this.request('DELETE', endpoint);
     }
 };

 // Toast Notifications
 Dashboard.Toast = {
     container: null,

     init() {
         this.container = document.getElementById('toastContainer');
     },

     show(message, type = 'info', duration = 3000) {
         const toast = document.createElement('div');
         toast.className = `toast toast-${type}`;
         toast.innerHTML = `
             <i class="fas fa-${this.getIcon(type)}"></i>
             <span>${message}</span>
             <button class="toast-close">&times;</button>
         `;

         this.container.appendChild(toast);

         // Animation
         setTimeout(() => toast.classList.add('show'), 10);

         // Auto remove
         const timer = setTimeout(() => this.remove(toast), duration);

         // Manual remove
         toast.querySelector('.toast-close').addEventListener('click', () => {
             clearTimeout(timer);
             this.remove(toast);
         });
     },

     remove(toast) {
         toast.classList.remove('show');
         setTimeout(() => toast.remove(), 300);
     },

     getIcon(type) {
         const icons = {
             success: 'check-circle',
             error: 'exclamation-circle',
             warning: 'exclamation-triangle',
             info: 'info-circle'
         };
         return icons[type] || icons.info;
     },

     success(message, duration) {
         this.show(message, 'success', duration);
     },

     error(message, duration) {
         this.show(message, 'error', duration);
     },

     warning(message, duration) {
         this.show(message, 'warning', duration);
     },

     info(message, duration) {
         this.show(message, 'info', duration);
     }
 };

 // Modal Manager
 Dashboard.Modal = {
     container: null,

     init() {
         this.container = document.getElementById('modalsContainer');
     },

     open(content, options = {}) {
         const modal = document.createElement('div');
         modal.className = 'modal';
         modal.innerHTML = `
             <div class="modal-backdrop"></div>
             <div class="modal-content ${options.size || ''}">
                 <div class="modal-header">
                     <h3>${options.title || ''}</h3>
                     <button class="modal-close">&times;</button>
                 </div>
                 <div class="modal-body">
                     ${content}
                 </div>
                 ${options.footer ? `<div class="modal-footer">${options.footer}</div>` : ''}
             </div>
         `;

         this.container.appendChild(modal);

         // Events
         modal.querySelector('.modal-close').addEventListener('click', () => this.close(modal));
         modal.querySelector('.modal-backdrop').addEventListener('click', () => this.close(modal));

         // Animation
         setTimeout(() => modal.classList.add('show'), 10);

         return modal;
     },

     close(modal) {
         modal.classList.remove('show');
         setTimeout(() => modal.remove(), 300);
     },

     confirm(message, onConfirm, onCancel) {
         const content = `<p>${message}</p>`;
         const footer = `
             <button class="btn btn-secondary" id="modalCancel">ביטול</button>
             <button class="btn btn-primary" id="modalConfirm">אישור</button>
         `;

         const modal = this.open(content, {
             title: 'אישור',
             footer: footer,
             size: 'small'
         });

         modal.querySelector('#modalConfirm').addEventListener('click', () => {
             this.close(modal);
             if (onConfirm) onConfirm();
         });

         modal.querySelector('#modalCancel').addEventListener('click', () => {
             this.close(modal);
             if (onCancel) onCancel();
         });
     }
 };

 // Utils
 Dashboard.Utils = {
     debounce(func, wait) {
         let timeout;
         return function executedFunction(...args) {
             const later = () => {
                 clearTimeout(timeout);
                 func(...args);
             };
             clearTimeout(timeout);
             timeout = setTimeout(later, wait);
         };
     },

     throttle(func, limit) {
         let inThrottle;
         return function(...args) {
             if (!inThrottle) {
                 func.apply(this, args);
                 inThrottle = true;
                 setTimeout(() => inThrottle = false, limit);
             }
         };
     },

     formatDate(date, format = 'short') {
         const d = new Date(date);
         const options = {
             short: { day: '2-digit', month: '2-digit', year: 'numeric' },
             long: { day: 'numeric', month: 'long', year: 'numeric' },
             full: { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' }
         };
         return d.toLocaleDateString('he-IL', options[format] || options.short);
     },

     formatNumber(num) {
         return new Intl.NumberFormat('he-IL').format(num);
     },

     timeAgo(date) {
         const seconds = Math.floor((new Date() - new Date(date)) / 1000);
         
         const intervals = {
             year: 31536000,
             month: 2592000,
             week: 604800,
             day: 86400,
             hour: 3600,
             minute: 60
         };

         for (const [unit, secondsInUnit] of Object.entries(intervals)) {
             const interval = Math.floor(seconds / secondsInUnit);
             if (interval >= 1) {
                 const rtf = new Intl.RelativeTimeFormat('he', { numeric: 'auto' });
                 return rtf.format(-interval, unit);
             }
         }

         return 'עכשיו';
     }
 };

 // Session Manager
 Dashboard.Session = {
     timeout: Dashboard.config.sessionTimeout || 3600,
     warningTime: 300, // 5 minutes before timeout
     timer: null,
     warningShown: false,

     init() {
         this.resetTimer();
         this.attachEventListeners();
     },

     attachEventListeners() {
         ['mousedown', 'keypress', 'scroll', 'touchstart'].forEach(event => {
             document.addEventListener(event, () => this.resetTimer(), true);
         });
     },

     resetTimer() {
         clearTimeout(this.timer);
         this.warningShown = false;
         
         // Warning timer
         this.timer = setTimeout(() => {
             this.showWarning();
         }, (this.timeout - this.warningTime) * 1000);
     },

     showWarning() {
         if (this.warningShown) return;
         this.warningShown = true;

         Dashboard.Modal.confirm(
             'הסשן שלך עומד להסתיים בעוד 5 דקות. האם ברצונך להמשיך?',
             () => {
                 this.refresh();
             },
             () => {
                 this.logout();
             }
         );

         // Final timeout
         setTimeout(() => {
             if (this.warningShown) {
                 this.logout();
             }
         }, this.warningTime * 1000);
     },

     async refresh() {
         try {
             const api = new Dashboard.API();
             await api.post('/session/refresh');
             this.resetTimer();
             Dashboard.Toast.success('הסשן חודש בהצלחה');
         } catch (error) {
             console.error('Session refresh failed:', error);
         }
     },

     logout() {
         window.location.href = '/auth/logout.php';
     }
 };

 // Notifications Manager
 Dashboard.Notifications = {
     count: 0,
     interval: null,

     init() {
         this.updateCount();
         this.startPolling();
         this.attachEventListeners();
     },

     startPolling(interval = 60000) {
         this.interval = setInterval(() => {
             this.fetchNotifications();
         }, interval);
     },

     stopPolling() {
         clearInterval(this.interval);
     },

     async fetchNotifications() {
         try {
             const api = new Dashboard.API();
             const response = await api.get('/notifications');
             this.updateUI(response.data);
         } catch (error) {
             console.error('Failed to fetch notifications:', error);
         }
     },

     updateCount(count) {
         this.count = count || 0;
         const badge = document.getElementById('notificationCount');
         if (badge) {
             badge.textContent = this.count;
             badge.style.display = this.count > 0 ? 'block' : 'none';
         }
     },

     updateUI(notifications) {
         const dropdown = document.getElementById('notificationDropdown');
         if (!dropdown) return;

         if (notifications.length === 0) {
             dropdown.innerHTML = '<div class="no-notifications">אין התראות חדשות</div>';
             return;
         }

         dropdown.innerHTML = notifications.map(notif => `
             <div class="notification-item ${notif.read ? 'read' : 'unread'}" data-id="${notif.id}">
                 <div class="notification-icon">
                     <i class="fas fa-${this.getIcon(notif.type)}"></i>
                 </div>
                 <div class="notification-content">
                     <div class="notification-message">${notif.message}</div>
                     <div class="notification-time">${Dashboard.Utils.timeAgo(notif.created_at)}</div>
                 </div>
             </div>
         `).join('');

         this.updateCount(notifications.filter(n => !n.read).length);
     },

     getIcon(type) {
         const icons = {
             info: 'info-circle',
             success: 'check-circle',
             warning: 'exclamation-triangle',
             error: 'exclamation-circle',
             message: 'envelope',
             user: 'user'
         };
         return icons[type] || 'bell';
     },

     attachEventListeners() {
         document.addEventListener('click', (e) => {
             if (e.target.closest('.notification-item')) {
                 const item = e.target.closest('.notification-item');
                 this.markAsRead(item.dataset.id);
             }
         });
     },

     async markAsRead(id) {
         try {
             const api = new Dashboard.API();
             await api.put(`/notifications/${id}/read`);
             this.fetchNotifications();
         } catch (error) {
             console.error('Failed to mark notification as read:', error);
         }
     }
 };

 // Initialize on DOM ready
 document.addEventListener('DOMContentLoaded', () => {
     // Hide loading screen
     const loadingScreen = document.getElementById('loadingScreen');
     if (loadingScreen) {
         setTimeout(() => {
             loadingScreen.style.opacity = '0';
             setTimeout(() => loadingScreen.remove(), 300);
         }, 500);
     }

     // Initialize components
     Dashboard.Toast.init();
     Dashboard.Modal.init();
     Dashboard.Session.init();
     Dashboard.Notifications.init();

     // Back to top button
     const backToTop = document.getElementById('backToTop');
     if (backToTop) {
         window.addEventListener('scroll', Dashboard.Utils.throttle(() => {
             backToTop.style.display = window.scrollY > 300 ? 'block' : 'none';
         }, 100));

         backToTop.addEventListener('click', () => {
             window.scrollTo({ top: 0, behavior: 'smooth' });
         });
     }

     // Global search
     const searchInput = document.getElementById('globalSearch');
     if (searchInput) {
         searchInput.addEventListener('input', Dashboard.Utils.debounce((e) => {
             if (e.target.value.length > 2) {
                 Dashboard.Search.perform(e.target.value);
             }
         }, 300));
     }

     // Server time update
     const serverTime = document.getElementById('serverTime');
     if (serverTime) {
         setInterval(() => {
             const now = new Date();
             serverTime.textContent = now.toLocaleTimeString('he-IL');
         }, 1000);
     }
 });

 // Expose API
 window.DashboardAPI = new Dashboard.API();
