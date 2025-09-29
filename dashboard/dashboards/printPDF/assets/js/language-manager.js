/**
 * Language Manager for PDF Editor
 * Location: /dashboard/dashboards/printPDF/assets/js/language-manager.js
 */

class LanguageManager {
    constructor() {
        this.currentLang = localStorage.getItem('pdf_editor_language') || 'he';
        this.translations = {};
        this.loadedLanguages = new Set();
        this.rtlLanguages = ['he', 'ar'];
        this.init();
    }

    async init() {
        // Load current language
        await this.loadLanguage(this.currentLang);
        
        // Apply language to UI
        this.applyLanguage();
        
        // Setup language switcher
        this.setupLanguageSwitcher();
        
        // Setup RTL/LTR
        this.updateDirection();
    }

    async loadLanguage(lang) {
        if (this.loadedLanguages.has(lang)) {
            return;
        }

        try {
            const response = await fetch(`lang/${lang}.json`);
            if (!response.ok) {
                throw new Error(`Failed to load language: ${lang}`);
            }
            
            const translations = await response.json();
            this.translations[lang] = translations;
            this.loadedLanguages.add(lang);
            
            console.log(`Language loaded: ${lang}`);
        } catch (error) {
            console.error(`Error loading language ${lang}:`, error);
            
            // Fallback to Hebrew if loading fails
            if (lang !== 'he') {
                await this.loadLanguage('he');
            }
        }
    }

    setLanguage(lang) {
        if (this.currentLang === lang) {
            return;
        }

        this.currentLang = lang;
        localStorage.setItem('pdf_editor_language', lang);
        
        // Load language if not already loaded
        this.loadLanguage(lang).then(() => {
            this.applyLanguage();
            this.updateDirection();
            
            // Dispatch language change event
            window.dispatchEvent(new CustomEvent('languageChanged', {
                detail: { language: lang }
            }));
        });
    }

    get(key, defaultValue = '') {
        const keys = key.split('.');
        let value = this.translations[this.currentLang];
        
        for (const k of keys) {
            if (value && typeof value === 'object' && k in value) {
                value = value[k];
            } else {
                return defaultValue || key;
            }
        }
        
        return value;
    }

    // Shorthand for get
    t(key, defaultValue = '') {
        return this.get(key, defaultValue);
    }

    // Get with replacements
    getText(key, replacements = {}) {
        let text = this.get(key);
        
        for (const [placeholder, value] of Object.entries(replacements)) {
            text = text.replace(`{{${placeholder}}}`, value);
        }
        
        return text;
    }

    getCurrentLanguage() {
        return this.currentLang;
    }

    getAvailableLanguages() {
        return [
            { code: 'he', name: 'עברית', dir: 'rtl' },
            { code: 'en', name: 'English', dir: 'ltr' },
            { code: 'ar', name: 'العربية', dir: 'rtl' }
        ];
    }

    isRTL() {
        return this.rtlLanguages.includes(this.currentLang);
    }

    updateDirection() {
        const html = document.documentElement;
        const body = document.body;
        
        if (this.isRTL()) {
            html.setAttribute('dir', 'rtl');
            html.setAttribute('lang', this.currentLang);
            body.classList.add('rtl');
            body.classList.remove('ltr');
        } else {
            html.setAttribute('dir', 'ltr');
            html.setAttribute('lang', this.currentLang);
            body.classList.add('ltr');
            body.classList.remove('rtl');
        }
    }

    applyLanguage() {
        // Update all elements with data-i18n attribute
        const elements = document.querySelectorAll('[data-i18n]');
        elements.forEach(element => {
            const key = element.getAttribute('data-i18n');
            const text = this.get(key);
            
            if (element.tagName === 'INPUT' || element.tagName === 'TEXTAREA') {
                element.placeholder = text;
            } else {
                element.textContent = text;
            }
        });
        
        // Update all elements with data-i18n-title attribute
        const titleElements = document.querySelectorAll('[data-i18n-title]');
        titleElements.forEach(element => {
            const key = element.getAttribute('data-i18n-title');
            element.title = this.get(key);
        });
        
        // Update document title
        document.title = this.get('app.title');
    }

    setupLanguageSwitcher() {
        const langButtons = document.querySelectorAll('.lang-btn');
        
        langButtons.forEach(btn => {
            const lang = btn.getAttribute('data-lang');
            
            // Set active state
            if (lang === this.currentLang) {
                btn.classList.add('active');
            } else {
                btn.classList.remove('active');
            }
            
            // Add click handler
            btn.addEventListener('click', () => {
                // Remove active from all buttons
                langButtons.forEach(b => b.classList.remove('active'));
                
                // Add active to clicked button
                btn.classList.add('active');
                
                // Change language
                this.setLanguage(lang);
            });
        });
    }

    // Format number according to locale
    formatNumber(number) {
        const locales = {
            'he': 'he-IL',
            'en': 'en-US',
            'ar': 'ar-SA'
        };
        
        const locale = locales[this.currentLang] || 'en-US';
        return new Intl.NumberFormat(locale).format(number);
    }

    // Format date according to locale
    formatDate(date, options = {}) {
        const locales = {
            'he': 'he-IL',
            'en': 'en-US',
            'ar': 'ar-SA'
        };
        
        const locale = locales[this.currentLang] || 'en-US';
        return new Intl.DateTimeFormat(locale, options).format(date);
    }

    // Format file size
    formatFileSize(bytes) {
        const sizes = this.get('units.sizes', ['Bytes', 'KB', 'MB', 'GB']);
        if (bytes === 0) return '0 ' + sizes[0];
        
        const k = 1024;
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        const size = parseFloat((bytes / Math.pow(k, i)).toFixed(2));
        
        return this.formatNumber(size) + ' ' + (sizes[i] || 'MB');
    }

    // Get text direction for specific language
    getDirection(lang = null) {
        lang = lang || this.currentLang;
        return this.rtlLanguages.includes(lang) ? 'rtl' : 'ltr';
    }

    // Check if language is loaded
    isLanguageLoaded(lang) {
        return this.loadedLanguages.has(lang);
    }

    // Preload all languages
    async preloadAllLanguages() {
        const languages = this.getAvailableLanguages();
        const promises = languages.map(lang => this.loadLanguage(lang.code));
        await Promise.all(promises);
    }

    // Get specific translation for a language without switching
    async getTranslation(lang, key) {
        if (!this.isLanguageLoaded(lang)) {
            await this.loadLanguage(lang);
        }
        
        const keys = key.split('.');
        let value = this.translations[lang];
        
        for (const k of keys) {
            if (value && typeof value === 'object' && k in value) {
                value = value[k];
            } else {
                return key;
            }
        }
        
        return value;
    }

    // Export translations for current language
    exportTranslations() {
        return JSON.stringify(this.translations[this.currentLang], null, 2);
    }

    // Import custom translations
    importTranslations(lang, translations) {
        this.translations[lang] = { ...this.translations[lang], ...translations };
        this.loadedLanguages.add(lang);
        
        if (lang === this.currentLang) {
            this.applyLanguage();
        }
    }
}

// Create global language manager instance
window.languageManager = new LanguageManager();

// Shorthand global function for translations
window.t = (key, defaultValue) => window.languageManager.t(key, defaultValue);
window.__ = window.t; // Alternative shorthand