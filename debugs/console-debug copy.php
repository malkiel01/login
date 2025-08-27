<?php
/**
 * Console Debug Window - Standalone Version
 * 
 * שימוש:
 * require_once 'path/to/console-debug.php';
 * 
 * או:
 * include 'path/to/console-debug.php';
 */

// בדיקה אם להציג את הקונסול (אופציונלי - אפשר להגדיר במקום אחר)
if (!defined('SHOW_DEBUG_CONSOLE')) {
    define('SHOW_DEBUG_CONSOLE', true);
}

// הצג רק אם מוגדר
if (SHOW_DEBUG_CONSOLE):
?>

<!-- Console Debug Window - Mobile Responsive -->
<div id="console-debug-window" style="
    position: fixed;
    bottom: 20px;
    right: 20px;
    width: calc(100vw - 40px);
    max-width: 400px;
    height: 300px;
    background: rgba(26, 26, 26, 0.95);
    backdrop-filter: blur(10px);
    border: 2px solid #333;
    border-radius: 12px;
    box-shadow: 0 8px 32px rgba(0,0,0,0.8);
    z-index: 10000;
    display: flex;
    flex-direction: column;
    font-family: 'Consolas', 'Monaco', monospace;
    font-size: 11px;
    direction: ltr;
    resize: both;
    overflow: auto;
">
    <!-- Header -->
    <div style="
        background: rgba(45, 45, 45, 0.9);
        padding: 8px;
        border-bottom: 1px solid #444;
        display: flex;
        justify-content: space-between;
        align-items: center;
        cursor: move;
        border-radius: 10px 10px 0 0;
        min-height: 35px;
    " id="console-header">
        <span style="color: #0f0; font-weight: bold; font-size: 12px;">📟 Console</span>
        <div style="display: flex; gap: 5px;">
            <button onclick="copyConsoleContent()" style="
                background: #08f;
                color: white;
                border: none;
                padding: 4px 8px;
                border-radius: 4px;
                cursor: pointer;
                font-size: 10px;
            " title="Copy all">📋</button>
            <button onclick="clearConsoleDebug()" style="
                background: #444;
                color: white;
                border: none;
                padding: 4px 8px;
                border-radius: 4px;
                cursor: pointer;
                font-size: 10px;
            ">Clear</button>
            <button onclick="minimizeConsole()" style="
                background: #fa0;
                color: white;
                border: none;
                padding: 4px 8px;
                border-radius: 4px;
                cursor: pointer;
                font-size: 10px;
            ">_</button>
            <button onclick="closeConsoleDebug()" style="
                background: #f44;
                color: white;
                border: none;
                padding: 4px 8px;
                border-radius: 4px;
                cursor: pointer;
                font-size: 10px;
            ">✕</button>
        </div>
    </div>
    
    <!-- Console Output -->
    <div id="console-output" style="
        flex: 1;
        overflow-y: auto;
        overflow-x: auto;
        padding: 8px;
        background: rgba(13, 13, 13, 0.9);
        color: #fff;
        white-space: pre-wrap;
        word-wrap: break-word;
        min-height: 0;
        user-select: text;
        -webkit-user-select: text;
    "></div>
    
    <!-- Input -->
    <div style="
        border-top: 1px solid #444;
        padding: 5px;
        background: rgba(26, 26, 26, 0.9);
        border-radius: 0 0 10px 10px;
    ">
        <input type="text" id="console-input" placeholder="Type command..." style="
            width: 100%;
            background: rgba(45, 45, 45, 0.9);
            color: #0f0;
            border: 1px solid #444;
            padding: 5px;
            font-family: monospace;
            font-size: 11px;
            border-radius: 3px;
        " onkeypress="handleConsoleInput(event)">
    </div>
</div>

<!-- Floating Button (Minimized State) -->
<button id="console-float-btn" style="
    position: fixed;
    bottom: 20px;
    right: 20px;
    width: 50px;
    height: 50px;
    background: rgba(26, 26, 26, 0.95);
    backdrop-filter: blur(10px);
    color: #0f0;
    border: 2px solid #333;
    border-radius: 50%;
    cursor: pointer;
    z-index: 9999;
    display: none;
    font-size: 20px;
    box-shadow: 0 4px 16px rgba(0,0,0,0.8);
    transition: all 0.3s ease;
" onclick="showConsoleDebug()">
    📟
</button>

<!-- Mini Bar (Collapsed State) -->
<div id="console-mini-bar" style="
    position: fixed;
    bottom: 20px;
    right: 20px;
    background: rgba(26, 26, 26, 0.95);
    backdrop-filter: blur(10px);
    border: 2px solid #333;
    border-radius: 25px;
    padding: 8px 15px;
    display: none;
    align-items: center;
    gap: 10px;
    z-index: 9999;
    box-shadow: 0 4px 16px rgba(0,0,0,0.8);
    cursor: pointer;
" onclick="expandConsole()">
    <span style="color: #0f0; font-size: 12px;">📟 Console</span>
    <span id="mini-bar-count" style="
        background: #f44;
        color: white;
        padding: 2px 6px;
        border-radius: 10px;
        font-size: 10px;
        font-weight: bold;
        display: none;
    ">0</span>
</div>

<style>
/* Mobile Responsive */
@media (max-width: 768px) {
    #console-debug-window {
        width: calc(100vw - 20px) !important;
        max-width: none !important;
        right: 10px !important;
        bottom: 10px !important;
        height: 250px !important;
        font-size: 10px !important;
    }
    
    #console-float-btn {
        bottom: 10px !important;
        right: 10px !important;
        width: 45px !important;
        height: 45px !important;
    }
    
    #console-mini-bar {
        bottom: 10px !important;
        right: 10px !important;
    }
    
    #console-input {
        font-size: 10px !important;
    }
}

/* Animations */
@keyframes slideIn {
    from { transform: translateY(100%); opacity: 0; }
    to { transform: translateY(0); opacity: 1; }
}

@keyframes fadeInOut {
    0% { opacity: 0; transform: translate(-50%, -50%) scale(0.8); }
    20% { opacity: 1; transform: translate(-50%, -50%) scale(1); }
    80% { opacity: 1; transform: translate(-50%, -50%) scale(1); }
    100% { opacity: 0; transform: translate(-50%, -50%) scale(0.9); }
}

#console-debug-window.show { animation: slideIn 0.3s ease; }
#console-float-btn:hover { transform: scale(1.1); background: rgba(45, 45, 45, 0.95); }
#console-mini-bar:hover { transform: scale(1.02); background: rgba(45, 45, 45, 0.95); }

/* Scrollbar Styling */
#console-output::-webkit-scrollbar { width: 6px; height: 6px; }
#console-output::-webkit-scrollbar-track { background: #1a1a1a; }
#console-output::-webkit-scrollbar-thumb { background: #444; border-radius: 3px; }
#console-output::-webkit-scrollbar-thumb:hover { background: #555; }
</style>

<script>
(function() {
    // Save original console functions
    const originalLog = console.log;
    const originalError = console.error;
    const originalWarn = console.warn;
    const originalInfo = console.info;
    
    const debugOutput = document.getElementById('console-output');
    let logCounter = 0;
    let errorCount = 0;  // Define errorCount here in the closure
    
    // Make errorCount accessible globally for the functions
    window.consoleDebugErrorCount = 0;
    
    // Add message to debug window
    function addToDebugWindow(type, args) {
        if (!debugOutput) return;
        
        logCounter++;
        if (type === 'error') {
            window.consoleDebugErrorCount++;
        }
        
        const timestamp = new Date().toLocaleTimeString();
        const entry = document.createElement('div');
        
        // Colors by type
        const colors = {
            log: '#fff',
            error: '#f44',
            warn: '#fa0',
            info: '#08f',
            success: '#0f0'
        };
        
        entry.style.cssText = `
            color: ${colors[type] || '#fff'};
            padding: 4px;
            border-bottom: 1px solid #222;
            margin-bottom: 2px;
            font-size: 11px;
            cursor: text;
            user-select: text;
            -webkit-user-select: text;
        `;
        
        // Add long press event for mobile
        let pressTimer;
        entry.addEventListener('touchstart', function(e) {
            pressTimer = setTimeout(() => {
                selectText(entry);
                showCopyTooltip(e.touches[0].clientX, e.touches[0].clientY);
            }, 500);
        });
        
        entry.addEventListener('touchend', function() {
            clearTimeout(pressTimer);
        });
        
        entry.addEventListener('touchmove', function() {
            clearTimeout(pressTimer);
        });
        
        // Double click to select on desktop
        entry.addEventListener('dblclick', function() {
            selectText(entry);
        });
        
        // Type indicators
        const typeLabel = {
            log: '›',
            error: '✕',
            warn: '⚠',
            info: 'ℹ'
        }[type] || '›';
        
        // Convert arguments to text
        const message = Array.from(args).map(arg => {
            if (typeof arg === 'object') {
                try {
                    return JSON.stringify(arg, null, 2);
                } catch (e) {
                    return String(arg);
                }
            }
            return String(arg);
        }).join(' ');
        
        entry.innerHTML = `<span style="color: #666;">${timestamp}</span> ${typeLabel} ${escapeHtml(message)}`;
        
        debugOutput.appendChild(entry);
        debugOutput.scrollTop = debugOutput.scrollHeight;
        
        // Update mini bar count
        updateMiniBarCount();
        
        // Limit messages
        if (debugOutput.children.length > 100) {
            debugOutput.removeChild(debugOutput.firstChild);
        }
    }
    
    // Escape HTML
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    // Override console functions
    console.log = function(...args) {
        originalLog.apply(console, args);
        addToDebugWindow('log', args);
    };
    
    console.error = function(...args) {
        originalError.apply(console, args);
        addToDebugWindow('error', args);
    };
    
    console.warn = function(...args) {
        originalWarn.apply(console, args);
        addToDebugWindow('warn', args);
    };
    
    console.info = function(...args) {
        originalInfo.apply(console, args);
        addToDebugWindow('info', args);
    };
    
    // Catch global errors
    window.addEventListener('error', function(event) {
        addToDebugWindow('error', [`${event.message} at ${event.filename}:${event.lineno}`]);
    });
    
    // Catch promise rejections
    window.addEventListener('unhandledrejection', function(event) {
        addToDebugWindow('error', [`Unhandled Promise: ${event.reason}`]);
    });
    
    // Initial message
    console.log('Console Debug Ready');
    
    // Make draggable
    let isDragging = false;
    let currentX;
    let currentY;
    let initialX;
    let initialY;
    let xOffset = 0;
    let yOffset = 0;
    
    const debugWindow = document.getElementById('console-debug-window');
    const header = document.getElementById('console-header');
    
    if (header) {
        header.addEventListener('touchstart', dragStart, {passive: false});
        header.addEventListener('touchmove', drag, {passive: false});
        header.addEventListener('touchend', dragEnd, {passive: false});
        
        header.addEventListener('mousedown', dragStart);
        document.addEventListener('mousemove', drag);
        document.addEventListener('mouseup', dragEnd);
    }
    
    function dragStart(e) {
        if (e.type === "touchstart") {
            initialX = e.touches[0].clientX - xOffset;
            initialY = e.touches[0].clientY - yOffset;
        } else {
            initialX = e.clientX - xOffset;
            initialY = e.clientY - yOffset;
        }
        
        if (e.target === header || e.target.parentNode === header) {
            isDragging = true;
        }
    }
    
    function drag(e) {
        if (isDragging) {
            e.preventDefault();
            
            if (e.type === "touchmove") {
                currentX = e.touches[0].clientX - initialX;
                currentY = e.touches[0].clientY - initialY;
            } else {
                currentX = e.clientX - initialX;
                currentY = e.clientY - initialY;
            }
            
            xOffset = currentX;
            yOffset = currentY;
            
            debugWindow.style.transform = `translate(${currentX}px, ${currentY}px)`;
        }
    }
    
    function dragEnd(e) {
        initialX = currentX;
        initialY = currentY;
        isDragging = false;
    }
})();

// Global functions
function copyConsoleContent() {
    const output = document.getElementById('console-output');
    const text = output.innerText || output.textContent;
    
    if (!text) {
        console.log('Nothing to copy');
        return;
    }
    
    // Try modern clipboard API first
    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(text).then(() => {
            showNotification('Copied to clipboard! ✓', '#0f0');
        }).catch(err => {
            fallbackCopy(text);
        });
    } else {
        fallbackCopy(text);
    }
}

function fallbackCopy(text) {
    const textarea = document.createElement('textarea');
    textarea.value = text;
    textarea.style.position = 'fixed';
    textarea.style.opacity = '0';
    document.body.appendChild(textarea);
    textarea.select();
    
    try {
        document.execCommand('copy');
        showNotification('Copied to clipboard! ✓', '#0f0');
    } catch (err) {
        showNotification('Copy failed! Select text manually', '#f44');
    }
    
    document.body.removeChild(textarea);
}

function selectText(element) {
    const selection = window.getSelection();
    const range = document.createRange();
    range.selectNodeContents(element);
    selection.removeAllRanges();
    selection.addRange(range);
}

function showCopyTooltip(x, y) {
    const tooltip = document.createElement('div');
    tooltip.textContent = 'Text selected - Copy with Ctrl+C';
    tooltip.style.cssText = `
        position: fixed;
        left: ${x}px;
        top: ${y - 40}px;
        background: rgba(0,0,0,0.8);
        color: white;
        padding: 5px 10px;
        border-radius: 4px;
        font-size: 11px;
        z-index: 10001;
        pointer-events: none;
    `;
    document.body.appendChild(tooltip);
    
    setTimeout(() => {
        tooltip.remove();
    }, 2000);
}

function showNotification(message, color) {
    const notification = document.createElement('div');
    notification.textContent = message;
    notification.style.cssText = `
        position: fixed;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        background: rgba(26, 26, 26, 0.95);
        color: ${color || '#fff'};
        padding: 10px 20px;
        border-radius: 8px;
        border: 2px solid ${color || '#333'};
        font-size: 14px;
        z-index: 10002;
        animation: fadeInOut 2s ease;
    `;
    
    document.body.appendChild(notification);
    
    setTimeout(() => {
        notification.remove();
    }, 2000);
}

function clearConsoleDebug() {
    document.getElementById('console-output').innerHTML = '';
    window.consoleDebugErrorCount = 0;
    updateMiniBarCount();
    console.log('Console cleared');
}

function closeConsoleDebug() {
    document.getElementById('console-debug-window').style.display = 'none';
    document.getElementById('console-float-btn').style.display = 'block';
}

function minimizeConsole() {
    document.getElementById('console-debug-window').style.display = 'none';
    document.getElementById('console-mini-bar').style.display = 'flex';
}

function expandConsole() {
    document.getElementById('console-debug-window').style.display = 'flex';
    document.getElementById('console-mini-bar').style.display = 'none';
    window.consoleDebugErrorCount = 0;
    updateMiniBarCount();
}

function showConsoleDebug() {
    document.getElementById('console-debug-window').style.display = 'flex';
    document.getElementById('console-float-btn').style.display = 'none';
    document.getElementById('console-mini-bar').style.display = 'none';
}

function handleConsoleInput(event) {
    if (event.key === 'Enter') {
        const input = event.target;
        const command = input.value.trim();
        if (command) {
            console.log('> ' + command);
            try {
                const result = eval(command);
                if (result !== undefined) {
                    console.log('< ', result);
                }
            } catch (e) {
                console.error('Error: ' + e.message);
            }
            input.value = '';
        }
    }
}

function updateMiniBarCount() {
    const countEl = document.getElementById('mini-bar-count');
    if (countEl && window.consoleDebugErrorCount > 0) {
        countEl.textContent = window.consoleDebugErrorCount;
        countEl.style.display = 'inline-block';
    } else if (countEl) {
        countEl.style.display = 'none';
    }
}

// Keyboard shortcuts
document.addEventListener('keydown', function(e) {
    // Ctrl+` - toggle console
    if (e.ctrlKey && e.key === '`') {
        const debugWindow = document.getElementById('console-debug-window');
        if (debugWindow.style.display === 'none') {
            showConsoleDebug();
        } else {
            closeConsoleDebug();
        }
    }
    
    // Ctrl+Shift+C - copy console content
    if (e.ctrlKey && e.shiftKey && e.key === 'C') {
        copyConsoleContent();
    }
});
</script>

<?php endif; // End of SHOW_DEBUG_CONSOLE check ?>