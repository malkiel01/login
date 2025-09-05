/**
 * PDF Text Printer - Main Application
 */

// ========== Global Variables ==========
let values = [];
let selectedMethod = 'mpdf';
let selectedOrientation = 'P';

// Configuration from server (will be injected by PHP)
const APP_CONFIG = window.APP_CONFIG || {
    methods: {},
    defaults: {
        fontSize: 12,
        color: '#000000',
        x: 100,
        y: 100
    },
    limits: {
        max_values: 100,
        max_font_size: 72,
        min_font_size: 8
    }
};

// ========== Initialization ==========
document.addEventListener('DOMContentLoaded', () => {
    console.log('📚 PDF Text Printer initialized');
    
    // Initialize UI
    updateValuesList();
    updateColorPreview();
    
    // Check available methods
    if (typeof checkAvailableMethods === 'function') {
        checkAvailableMethods();
    }
    
    // Load saved state from localStorage (if exists)
    loadState();
    
    // Initialize event listeners
    initEventListeners();
});

// ========== State Management ==========
function saveState() {
    const state = {
        values,
        selectedMethod,
        selectedOrientation,
        language: document.getElementById('language')?.value,
        pdfUrl: document.getElementById('pdfUrl')?.value
    };
    localStorage.setItem('pdfPrinterState', JSON.stringify(state));
}

function loadState() {
    try {
        const saved = localStorage.getItem('pdfPrinterState');
        if (saved) {
            const state = JSON.parse(saved);
            values = state.values || [];
            selectedMethod = state.selectedMethod || 'mpdf';
            selectedOrientation = state.selectedOrientation || 'P';
            
            // Restore UI
            if (state.language) {
                const langSelect = document.getElementById('language');
                if (langSelect) langSelect.value = state.language;
            }
            if (state.pdfUrl) {
                const urlInput = document.getElementById('pdfUrl');
                if (urlInput) urlInput.value = state.pdfUrl;
            }
            
            updateValuesList();
            console.log('✅ State restored from localStorage');
        }
    } catch (error) {
        console.error('Error loading state:', error);
    }
}

// ========== Event Listeners ==========
function initEventListeners() {
    // Auto-save on changes
    document.addEventListener('change', saveState);
    
    // Keyboard shortcuts
    document.addEventListener('keydown', (e) => {
        // Ctrl/Cmd + S to save/export
        if ((e.ctrlKey || e.metaKey) && e.key === 's') {
            e.preventDefault();
            exportToJson();
        }
        
        // Ctrl/Cmd + Enter to generate PDF
        if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') {
            e.preventDefault();
            processValues();
        }
    });
}

// ========== Helper Functions ==========
function showStatus(message, type = 'success') {
    const statusDiv = document.getElementById('statusMessage');
    if (!statusDiv) return;
    
    statusDiv.textContent = message;
    statusDiv.className = `status-message status-${type}`;
    statusDiv.style.display = 'block';
    
    setTimeout(() => {
        statusDiv.style.display = 'none';
    }, 5000);
}

function clearInputs() {
    const fields = ['textValue', 'xCoord', 'yCoord', 'fontSize'];
    fields.forEach(id => {
        const element = document.getElementById(id);
        if (element) {
            element.value = APP_CONFIG.defaults[id.replace('Coord', '').replace('Value', '')] || '';
        }
    });
    
    const colorInput = document.getElementById('fontColor');
    if (colorInput) {
        colorInput.value = APP_CONFIG.defaults.color || '#000000';
    }
    
    updateColorPreview();
}