<?php
// dashboard/dashboards/cemeteries/includes/header.php
?>
<header class="dashboard-header">
    <div class="header-content">
        <div class="header-right">
            <!-- כפתור המבורגר -->
            <button class="hamburger-menu" onclick="toggleSidebar()" aria-label="תפריט">
                <svg class="icon"><use xlink:href="#icon-menu"></use></svg>
            </button>
            
            <!-- כפתור חזרה לדף הראשי - רספונסיבי -->
            <a href="/dashboard/" class="btn-home-responsive" title="חזרה לדף הראשי">
                <svg class="icon-home" viewBox="0 0 24 24" fill="none">
                    <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z" stroke="currentColor" stroke-width="2"/>
                    <polyline points="9 22 9 12 15 12 15 22" stroke="currentColor" stroke-width="2"/>
                </svg>
                <span class="btn-home-text">חזרה לראשי</span>
            </a>
            
            <h1 class="header-title">
                <span class="header-icon">🪦</span>
                <?php echo DASHBOARD_NAME; ?>
            </h1>
        </div>
        
        <div class="header-left">
            <!-- סטטיסטיקות ראשיות -->
            <div class="header-stats">
                <div class="header-stat">
                    <span class="stat-value" id="headerTotalGraves">0</span>
                    <span class="stat-label">קברים כללי</span>
                </div>
                <div class="header-stat stat-success">
                    <span class="stat-value" id="headerAvailableGraves">0</span>
                    <span class="stat-label">פנויים</span>
                </div>
                <div class="header-stat stat-warning">
                    <span class="stat-value" id="headerReservedGraves">0</span>
                    <span class="stat-label">שמורים</span>
                </div>
                <div class="header-stat stat-danger">
                    <span class="stat-value" id="headerOccupiedGraves">0</span>
                    <span class="stat-label">תפוסים</span>
                </div>
            </div>
            
            <!-- כפתורי פעולה מהירים -->
            <div class="header-actions">
                <button class="btn btn-sm btn-secondary" onclick="toggleFullscreen()" aria-label="מסך מלא">
                    <svg class="icon-sm"><use xlink:href="#icon-fullscreen"></use></svg>
                </button>
                <button class="btn btn-sm btn-secondary" onclick="refreshAllData()" aria-label="רענון">
                    <svg class="icon-sm"><use xlink:href="#icon-refresh"></use></svg>
                </button>
            </div>
        </div>
    </div>
</header>

<style>
/* ================================ */
/* כפתור חזרה רספונסיבי            */
/* ================================ */

/* סגנון בסיסי משותף */
.btn-home-responsive {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    text-decoration: none;
    transition: all 0.3s ease;
    margin-left: 15px;
    position: relative;
}

.icon-home {
    width: 20px;
    height: 20px;
    transition: transform 0.3s ease;
}

/* ================================ */
/* מסך גדול - אופציה 3             */
/* כפתור עם אנימציית מילוי         */
/* ================================ */
@media (min-width: 769px) {
    .btn-home-responsive {
        gap: 8px;
        padding: 10px 20px;
        background: transparent;
        border: 2px solid rgba(255, 255, 255, 0.8);
        border-radius: 25px;
        color: white;
        font-weight: 600;
        overflow: hidden;
    }
    
    .btn-home-text {
        display: inline;
        font-size: 14px;
        transition: color 0.3s ease;
    }
    
    /* אפקט מילוי באנימציה */
    .btn-home-responsive::before {
        content: "";
        position: absolute;
        top: 0;
        left: -100%;
        width: 100%;
        height: 100%;
        background: linear-gradient(90deg, 
            rgba(255, 255, 255, 0.2), 
            rgba(255, 255, 255, 0.3));
        transition: left 0.5s cubic-bezier(0.4, 0, 0.2, 1);
        z-index: -1;
    }
    
    .btn-home-responsive:hover::before {
        left: 0;
    }
    
    .btn-home-responsive:hover {
        border-color: white;
        transform: translateY(-2px);
        box-shadow: 0 5px 20px rgba(0, 0, 0, 0.2);
    }
    
    .btn-home-responsive:hover .icon-home {
        transform: translateX(3px);
    }
    
    /* אנימציית לחיצה */
    .btn-home-responsive:active {
        transform: translateY(0);
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);
    }
    
    /* אפקט גלים בלחיצה */
    @keyframes ripple {
        0% {
            transform: scale(0);
            opacity: 1;
        }
        100% {
            transform: scale(4);
            opacity: 0;
        }
    }
    
    .btn-home-responsive::after {
        content: "";
        position: absolute;
        border-radius: 50%;
        background: rgba(255, 255, 255, 0.5);
        width: 20px;
        height: 20px;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%) scale(0);
        opacity: 0;
    }
    
    .btn-home-responsive:active::after {
        animation: ripple 0.6s ease-out;
    }
}

/* ================================ */
/* מסך קטן - אופציה 2              */
/* כפתור עגול עם סיבוב            */
/* ================================ */
@media (max-width: 768px) {
    .btn-home-responsive {
        width: 40px;
        height: 40px;
        background: rgba(255, 255, 255, 0.95);
        border: 2px solid #667eea;
        border-radius: 50%;
        color: #667eea;
        margin-left: 10px;
    }
    
    /* הסתרת הטקסט במובייל */
    .btn-home-text {
        display: none;
    }
    
    /* אפקט סיבוב בהובר/טאץ' */
    .btn-home-responsive:hover,
    .btn-home-responsive:active {
        background: linear-gradient(135deg, #667eea, #764ba2);
        border-color: transparent;
        color: white;
        transform: rotate(360deg) scale(1.1);
        box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
    }
    
    .btn-home-responsive:hover .icon-home,
    .btn-home-responsive:active .icon-home {
        transform: scale(1.2);
    }
    
    /* אנימציית פעימה במובייל */
    @keyframes mobilePulse {
        0% {
            box-shadow: 0 0 0 0 rgba(102, 126, 234, 0.7);
        }
        70% {
            box-shadow: 0 0 0 10px rgba(102, 126, 234, 0);
        }
        100% {
            box-shadow: 0 0 0 0 rgba(102, 126, 234, 0);
        }
    }
    
    .btn-home-responsive:focus {
        animation: mobilePulse 1.5s;
        outline: none;
    }
}

/* ================================ */
/* אנימציות כניסה                  */
/* ================================ */
@keyframes slideInFromLeft {
    from {
        opacity: 0;
        transform: translateX(-20px);
    }
    to {
        opacity: 1;
        transform: translateX(0);
    }
}

.btn-home-responsive {
    animation: slideInFromLeft 0.5s ease-out;
}

/* ================================ */
/* תמיכה בדפדפנים שונים           */
/* ================================ */
.btn-home-responsive {
    -webkit-tap-highlight-color: transparent;
    -webkit-touch-callout: none;
    -webkit-user-select: none;
    -moz-user-select: none;
    -ms-user-select: none;
    user-select: none;
}

/* ================================ */
/* Dark mode support (אופציונלי)    */
/* ================================ */
@media (prefers-color-scheme: dark) {
    .btn-home-responsive {
        /* התאמות לדארק מוד אם צריך */
    }
}

/* ================================ */
/* מסך קטן מאוד (שעונים חכמים)     */
/* ================================ */
@media (max-width: 380px) {
    .btn-home-responsive {
        width: 35px;
        height: 35px;
        margin-left: 5px;
    }
    
    .icon-home {
        width: 18px;
        height: 18px;
    }
}

/* ================================ */
/* Tablet portrait                  */
/* ================================ */
@media (min-width: 481px) and (max-width: 768px) {
    .btn-home-responsive {
        width: 42px;
        height: 42px;
    }
}

/* ================================ */
/* הדפסה - הסתרת הכפתור           */
/* ================================ */
@media print {
    .btn-home-responsive {
        display: none;
    }
}
</style>

<script>
// JavaScript לתמיכה נוספת באנימציות
document.addEventListener('DOMContentLoaded', function() {
    const homeBtn = document.querySelector('.btn-home-responsive');
    
    if (homeBtn) {
        // הוספת אפקט גלים בלחיצה (Ripple effect)
        homeBtn.addEventListener('click', function(e) {
            // מחשב את המיקום של הלחיצה
            const rect = this.getBoundingClientRect();
            const ripple = document.createElement('span');
            const size = Math.max(rect.width, rect.height);
            const x = e.clientX - rect.left - size / 2;
            const y = e.clientY - rect.top - size / 2;
            
            ripple.style.width = ripple.style.height = size + 'px';
            ripple.style.left = x + 'px';
            ripple.style.top = y + 'px';
            ripple.classList.add('ripple-effect');
            
            this.appendChild(ripple);
            
            setTimeout(() => {
                ripple.remove();
            }, 600);
        });
        
        // אנימציה בטעינת הדף
        homeBtn.style.opacity = '0';
        setTimeout(() => {
            homeBtn.style.transition = 'opacity 0.5s ease';
            homeBtn.style.opacity = '1';
        }, 100);
    }
});
</script>