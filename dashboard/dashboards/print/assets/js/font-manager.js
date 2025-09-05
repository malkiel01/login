/**
 * Font Manager - Handle font selection and loading
 */

let availableFonts = [];
let selectedFont = 'dejavusans';

// Load fonts from JSON
async function loadFonts() {
    try {
        const response = await fetch('assets/fonts/fonts.json');
        const data = await response.json();
        availableFonts = data.fonts;
        
        // Populate font selector
        const fontSelect = document.getElementById('fontFamily');
        if (fontSelect) {
            fontSelect.innerHTML = '';
            
            // Group fonts by category
            const categories = {
                'sans-serif': 'Sans Serif',
                'serif': 'Serif',
                'custom': 'מותאם אישית'
            };
            
            Object.keys(categories).forEach(category => {
                const categoryFonts = availableFonts.filter(f => f.category === category);
                if (categoryFonts.length > 0) {
                    const optgroup = document.createElement('optgroup');
                    optgroup.label = categories[category];
                    
                    categoryFonts.forEach(font => {
                        const option = document.createElement('option');
                        option.value = font.id;
                        option.textContent = font.displayName;
                        if (font.default) {
                            option.selected = true;
                        }
                        
                        // Add language support indicators
                        if (font.supports.includes('hebrew') && !font.supports.includes('arabic')) {
                            option.textContent += ' 🔤';
                        } else if (font.supports.includes('arabic')) {
                            option.textContent += ' ﷽';
                        }
                        
                        optgroup.appendChild(option);
                    });
                    
                    fontSelect.appendChild(optgroup);
                }
            });
        }
        
        debugLog('Fonts loaded successfully', 'success');
        return availableFonts;
    } catch (error) {
        debugLog('Error loading fonts: ' + error.message, 'error');
        return [];
    }
}

// Handle font selection change
function handleFontChange() {
    const fontSelect = document.getElementById('fontFamily');
    const customFontInput = document.getElementById('customFontInput');
    
    if (!fontSelect) return;
    
    selectedFont = fontSelect.value;
    const fontInfo = availableFonts.find(f => f.id === selectedFont);
    
    // Show/hide custom font URL input
    if (fontInfo && fontInfo.requiresUrl) {
        customFontInput.style.display = 'block';
    } else {
        customFontInput.style.display = 'none';
    }
    
    // Preview font if possible
    previewFont(fontInfo);
    
    debugLog(`Selected font: ${fontInfo ? fontInfo.name : selectedFont}`, 'info');
}

// Preview font in the interface
function previewFont(fontInfo) {
    if (!fontInfo) return;
    
    const previewElements = [
        document.getElementById('textValue'),
        document.getElementById('previewArea')
    ];
    
    previewElements.forEach(element => {
        if (element) {
            // For local fonts, try to load and apply
            if (fontInfo.type === 'local') {
                // Try to load font dynamically (for preview only)
                const fontFace = new FontFace(fontInfo.name, `url(assets/fonts/${fontInfo.file})`);
                fontFace.load().then(loadedFont => {
                    document.fonts.add(loadedFont);
                    element.style.fontFamily = `"${fontInfo.name}", sans-serif`;
                }).catch(err => {
                    console.warn('Could not load font preview:', err);
                });
            }
        }
    });
}

// Get font configuration for value
function getFontConfig() {
    const fontInfo = availableFonts.find(f => f.id === selectedFont);
    
    if (!fontInfo) {
        return {
            fontFamily: 'dejavusans',
            fontFile: null,
            fontUrl: null
        };
    }
    
    const config = {
        fontFamily: fontInfo.id,
        fontFile: fontInfo.file,
        fontUrl: null
    };
    
    // If custom font, get URL
    if (fontInfo.requiresUrl) {
        const customUrl = document.getElementById('customFontUrl')?.value;
        if (customUrl) {
            config.fontUrl = customUrl;
            config.fontFamily = 'custom';
        }
    }
    
    return config;
}

// Update addValue function to include font
const originalAddValue = window.addValue;
window.addValue = function() {
    const text = document.getElementById('textValue').value;
    const x = parseInt(document.getElementById('xCoord').value) || 100;
    const y = parseInt(document.getElementById('yCoord').value) || 100;
    const fontSize = parseInt(document.getElementById('fontSize').value) || 12;
    const color = document.getElementById('fontColor').value;
    const fontConfig = getFontConfig();

    if (!text) {
        showStatus('נא להכניס טקסט', 'error');
        debugLog('Failed to add value: missing text', 'error');
        return;
    }

    const value = { 
        text, 
        x, 
        y, 
        fontSize, 
        color,
        fontFamily: fontConfig.fontFamily,
        fontUrl: fontConfig.fontUrl
    };
    
    values.push(value);
    
    debugLog(`Added value with font: ${JSON.stringify(value)}`, 'success');
    updateValuesList();
    clearInputs();
    showStatus('הערך נוסף בהצלחה', 'success');
    saveState();
};

// Initialize fonts on page load
document.addEventListener('DOMContentLoaded', () => {
    loadFonts();
});