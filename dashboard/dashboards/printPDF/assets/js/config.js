/**
 * Configuration for PDF Editor
 * Location: /dashboard/dashboards/printPDF/assets/js/config.js
 */

const PDFEditorConfig = {
    // API Endpoints
    api: {
        baseUrl: '/dashboard/dashboards/printPDF/api/',
        endpoints: {
            processDocument: 'process-document.php',
            cloudSave: 'cloud-save.php',
            templates: 'templates.php',
            batchProcess: 'batch-process.php',
            uploadFile: 'upload-file.php',
            getFonts: 'get-fonts.php'
        }
    },
    
    // Storage Settings
    storage: {
        mode: 'server', // 'server' or 'local'
        autoSave: true,
        autoSaveInterval: 120000, // 2 minutes in milliseconds
        maxLocalStorage: 50 * 1024 * 1024, // 50MB
        projectPrefix: 'pdf_editor_project_',
        settingsPrefix: 'pdf_editor_settings_'
    },
    
    // Canvas Settings
    canvas: {
        defaultWidth: 595, // A4 width in points (210mm)
        defaultHeight: 842, // A4 height in points (297mm)
        backgroundColor: '#ffffff',
        gridSize: 10,
        snapToGrid: false,
        showRulers: true,
        rulerUnit: 'mm', // 'mm', 'cm', 'inch', 'px'
        zoom: {
            min: 10,
            max: 500,
            step: 10,
            default: 100,
            fitPadding: 50
        }
    },
    
    // File Settings
    file: {
        maxSize: 10 * 1024 * 1024, // 10MB
        allowedTypes: ['application/pdf', 'image/jpeg', 'image/png'],
        allowedExtensions: ['pdf', 'jpg', 'jpeg', 'png'],
        compressionQuality: 0.92,
        thumbnailSize: {
            width: 200,
            height: 280
        }
    },
    
    // Text Settings
    text: {
        defaultFont: 'rubik',
        defaultFontSize: 14,
        defaultColor: '#000000',
        defaultLineHeight: 1.2,
        defaultLetterSpacing: 0,
        minFontSize: 8,
        maxFontSize: 72
    },
    
    // Undo/Redo Settings
    history: {
        maxStates: 50,
        compressStates: true,
        saveDelay: 500 // milliseconds to wait before saving state
    },
    
    // Layer Settings
    layers: {
        maxLayers: 100,
        defaultLayers: [
            { name: 'רקע', type: 'background', locked: false, visible: true },
            { name: 'טקסט', type: 'text', locked: false, visible: true },
            { name: 'תמונות', type: 'images', locked: false, visible: true }
        ]
    },
    
    // Template Categories
    templates: {
        categories: {
            business: 'עסקי',
            certificates: 'תעודות',
            presentations: 'מצגות',
            receipts: 'קבלות',
            invoices: 'חשבוניות',
            letterhead: 'נייר מכתבים',
            cards: 'כרטיסים',
            custom: 'מותאם אישית'
        },
        defaultTemplates: [
            {
                id: 'blank',
                name: 'ריק',
                category: 'business',
                data: null
            },
            {
                id: 'invoice',
                name: 'חשבונית',
                category: 'invoices',
                data: null // Will be loaded from server
            },
            {
                id: 'certificate',
                name: 'תעודה',
                category: 'certificates',
                data: null
            }
        ]
    },
    
    // Batch Processing
    batch: {
        maxFiles: 20,
        maxConcurrent: 3,
        timeout: 30000, // 30 seconds per file
        retryAttempts: 2
    },
    
    // Default Language
    language: {
        default: 'he',
        available: ['he', 'en', 'ar'],
        rtlLanguages: ['he', 'ar']
    },
    
    // Keyboard Shortcuts
    shortcuts: {
        'ctrl+z': 'undo',
        'ctrl+y': 'redo',
        'ctrl+s': 'save',
        'ctrl+o': 'open',
        'ctrl+n': 'new',
        'ctrl+a': 'selectAll',
        'ctrl+c': 'copy',
        'ctrl+v': 'paste',
        'ctrl+x': 'cut',
        'delete': 'delete',
        'ctrl+d': 'duplicate',
        'ctrl+g': 'toggleGrid',
        'ctrl+r': 'toggleRulers',
        'ctrl+l': 'toggleLayers',
        'ctrl+plus': 'zoomIn',
        'ctrl+minus': 'zoomOut',
        'ctrl+0': 'zoomFit',
        't': 'textTool',
        'i': 'imageTool',
        'v': 'selectTool',
        'g': 'toggleGrid',
        'escape': 'deselect'
    },
    
    // Debug Mode
    debug: false,
    
    // Performance Settings
    performance: {
        enableWebGL: true,
        renderOnDemand: true,
        cacheImages: true,
        maxCacheSize: 100 * 1024 * 1024, // 100MB
        throttleResize: 100, // milliseconds
        debounceInput: 300 // milliseconds
    },
    
    // Export Settings
    export: {
        pdf: {
            quality: 'high',
            compression: true,
            embedFonts: true,
            colorSpace: 'RGB'
        },
        image: {
            format: 'png',
            quality: 0.95,
            scale: 2 // for retina displays
        }
    },
    
    // Cloud Storage
    cloud: {
        syncInterval: 60000, // 1 minute
        maxProjects: 100,
        shareExpiration: 7 * 24 * 60 * 60, // 7 days in seconds
        thumbnailGeneration: true
    },
    
    // Notifications
    notifications: {
        position: 'top-right',
        duration: 3000,
        showProgress: true,
        sounds: false
    },
    
    // Paper Sizes (in mm)
    paperSizes: {
        'A0': { width: 841, height: 1189 },
        'A1': { width: 594, height: 841 },
        'A2': { width: 420, height: 594 },
        'A3': { width: 297, height: 420 },
        'A4': { width: 210, height: 297 },
        'A5': { width: 148, height: 210 },
        'Letter': { width: 216, height: 279 },
        'Legal': { width: 216, height: 356 },
        'Tabloid': { width: 279, height: 432 },
        'Custom': { width: 210, height: 297 }
    },
    
    // API Request Settings
    request: {
        timeout: 30000,
        retries: 3,
        retryDelay: 1000,
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    }
};

// Helper function to get config value
function getConfig(path, defaultValue = null) {
    const keys = path.split('.');
    let value = PDFEditorConfig;
    
    for (const key of keys) {
        if (value && typeof value === 'object' && key in value) {
            value = value[key];
        } else {
            return defaultValue;
        }
    }
    
    return value;
}

// Helper function to set config value
function setConfig(path, value) {
    const keys = path.split('.');
    let obj = PDFEditorConfig;
    
    for (let i = 0; i < keys.length - 1; i++) {
        const key = keys[i];
        if (!(key in obj) || typeof obj[key] !== 'object') {
            obj[key] = {};
        }
        obj = obj[key];
    }
    
    obj[keys[keys.length - 1]] = value;
}

// Helper function to merge config
function mergeConfig(customConfig) {
    function deepMerge(target, source) {
        for (const key in source) {
            if (source.hasOwnProperty(key)) {
                if (typeof source[key] === 'object' && source[key] !== null && !Array.isArray(source[key])) {
                    if (!target[key]) {
                        target[key] = {};
                    }
                    deepMerge(target[key], source[key]);
                } else {
                    target[key] = source[key];
                }
            }
        }
        return target;
    }
    
    return deepMerge(PDFEditorConfig, customConfig);
}

// Initialize config from localStorage if available
function initializeConfig() {
    const savedConfig = localStorage.getItem('pdf_editor_config');
    if (savedConfig) {
        try {
            const customConfig = JSON.parse(savedConfig);
            mergeConfig(customConfig);
        } catch (e) {
            console.error('Failed to load saved config:', e);
        }
    }
}

// Save config to localStorage
function saveConfig() {
    try {
        localStorage.setItem('pdf_editor_config', JSON.stringify(PDFEditorConfig));
    } catch (e) {
        console.error('Failed to save config:', e);
    }
}

// Paper size converter
function mmToPoints(mm) {
    return mm * 2.834645669;
}

function pointsToMm(points) {
    return points / 2.834645669;
}

function getPaperSizeInPoints(sizeName) {
    const size = PDFEditorConfig.paperSizes[sizeName];
    if (!size) return null;
    
    return {
        width: mmToPoints(size.width),
        height: mmToPoints(size.height)
    };
}

// Export config and helpers
window.PDFEditorConfig = PDFEditorConfig;
window.getConfig = getConfig;
window.setConfig = setConfig;
window.mergeConfig = mergeConfig;
window.initializeConfig = initializeConfig;
window.saveConfig = saveConfig;
window.mmToPoints = mmToPoints;
window.pointsToMm = pointsToMm;
window.getPaperSizeInPoints = getPaperSizeInPoints;

// Initialize on load
initializeConfig();