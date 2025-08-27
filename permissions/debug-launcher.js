// הוסף את הקוד הזה לדף שלך

// אפשרות 1: כפתור צף
function addDebugButton() {
    const button = document.createElement('button');
    button.innerHTML = '🔧';
    button.style.cssText = `
        position: fixed;
        bottom: 20px;
        right: 20px;
        width: 50px;
        height: 50px;
        border-radius: 50%;
        background: #000;
        border: 2px solid #0f0;
        color: #0f0;
        font-size: 24px;
        cursor: pointer;
        z-index: 9999;
        box-shadow: 0 0 10px rgba(0,255,0,0.5);
        transition: all 0.3s;
    `;
    
    button.onmouseover = () => {
        button.style.transform = 'scale(1.2)';
        button.style.boxShadow = '0 0 20px rgba(0,255,0,0.8)';
    };
    
    button.onmouseout = () => {
        button.style.transform = 'scale(1)';
        button.style.boxShadow = '0 0 10px rgba(0,255,0,0.5)';
    };
    
    button.onclick = () => openDebugPanel();
    
    document.body.appendChild(button);
}

// אפשרות 2: קיצור מקלדת - Ctrl+Shift+D
document.addEventListener('keydown', function(e) {
    if (e.ctrlKey && e.shiftKey && e.key === 'D') {
        e.preventDefault();
        openDebugPanel();
    }
});

// אפשרות 3: פקודה בקונסול
window.debug = () => openDebugPanel();

// פונקציה לפתיחת הדיבאג
function openDebugPanel() {
    // חלון popup
    const width = 900;
    const height = 700;
    const left = (screen.width - width) / 2;
    const top = (screen.height - height) / 2;
    
    window.open(
        '/debug.php',
        'NotificationDebugPanel',
        `width=${width},height=${height},left=${left},top=${top},resizable=yes,scrollbars=yes`
    );
}

// הפעל את הכפתור הצף
addDebugButton();

// הודעה בקונסול
console.log('%c🔧 Debug Panel Ready!', 'color: #0f0; font-size: 16px; font-weight: bold;');
console.log('Press Ctrl+Shift+D or click the debug button');
console.log('Or type: debug() in console');