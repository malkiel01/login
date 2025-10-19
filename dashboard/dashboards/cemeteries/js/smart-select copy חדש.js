/**
 * SmartSelect Manager - Full Featured
 * מנהל סלקטים חכמים עם חיפוש מתקדם
 * 
 * @version 2.0.0
 */

window.SmartSelectManager = {
    
    instances: {},
    
    /**
     * אתחול SmartSelect
     */
    init: function(name, config) {
        const wrapper = document.querySelector(`[data-name="${name}"]`);
        if (!wrapper) {
            console.error('SmartSelect: Wrapper not found for', name);
            return;
        }
        
        const hiddenInput = document.getElementById(name);
        const container = wrapper.querySelector('.smart-select-container');
        const display = wrapper.querySelector('.smart-select-display');
        const dropdown = wrapper.querySelector('.smart-select-dropdown');
        const searchInput = wrapper.querySelector('.smart-select-search-input');
        const optionsContainer = wrapper.querySelector('.smart-select-options');
        const noResults = wrapper.querySelector('.smart-select-no-results');
        
        if (!hiddenInput || !container || !display || !dropdown || !optionsContainer) {
            console.error('SmartSelect: Missing elements for', name);
            return;
        }
        
        // שמור את המופע
        this.instances[name] = {
            name: name,
            wrapper: wrapper,
            hiddenInput: hiddenInput,
            container: container,
            display: display,
            dropdown: dropdown,
            searchInput: searchInput,
            optionsContainer: optionsContainer,
            noResults: noResults,
            config: config,
            isOpen: false,
            allOptions: []
        };
        
        // שמור את כל האופציות
        this.instances[name].allOptions = Array.from(
            optionsContainer.querySelectorAll('.smart-select-option')
        );
        
        // הוסף event listeners
        this.bindEvents(name);
        
        // אם יש תלות בשדה אחר
        if (config.depends_on) {
            this.setupDependency(name, config.depends_on);
        }
        
        console.log('SmartSelect initialized:', name);
    },
    
    /**
     * חיבור אירועים
     */
    bindEvents: function(name) {
        const instance = this.instances[name];
        
        // לחיצה על ה-display - פתיחה/סגירה
        instance.display.addEventListener('click', (e) => {
            e.stopPropagation();
            this.toggle(name);
        });
        
        // חיפוש
        if (instance.searchInput) {
            instance.searchInput.addEventListener('input', (e) => {
                this.search(name, e.target.value);
            });
            
            // מנע סגירה בלחיצה על שדה החיפוש
            instance.searchInput.addEventListener('click', (e) => {
                e.stopPropagation();
            });
        }
        
        // בחירת אופציה
        instance.allOptions.forEach(option => {
            option.addEventListener('click', (e) => {
                e.stopPropagation();
                const value = option.getAttribute('data-value');
                this.select(name, value);
            });
        });
        
        // סגירה בלחיצה מחוץ לסלקט
        document.addEventListener('click', (e) => {
            if (instance.isOpen && !instance.wrapper.contains(e.target)) {
                this.close(name);
            }
        });
        
        // מקשי מקלדת
        instance.wrapper.addEventListener('keydown', (e) => {
            this.handleKeyboard(name, e);
        });
    },
    
    /**
     * פתיחה/סגירה
     */
    toggle: function(name) {
        const instance = this.instances[name];
        
        if (instance.isOpen) {
            this.close(name);
        } else {
            this.open(name);
        }
    },
    
    /**
     * פתיחה
     */
    open: function(name) {
        const instance = this.instances[name];
        
        if (instance.config.disabled) return;
        
        // סגור את כל השאר
        Object.keys(this.instances).forEach(key => {
            if (key !== name) {
                this.close(key);
            }
        });
        
        instance.dropdown.style.display = 'block';
        instance.container.classList.add('open');
        instance.isOpen = true;
        
        // פוקוס על שדה החיפוש
        if (instance.searchInput) {
            setTimeout(() => {
                instance.searchInput.focus();
                instance.searchInput.select();
            }, 50);
        }
        
        // גלול לאופציה הנבחרת
        this.scrollToSelected(name);
    },
    
    /**
     * סגירה
     */
    close: function(name) {
        const instance = this.instances[name];
        
        if (!instance || !instance.isOpen) return;
        
        instance.dropdown.style.display = 'none';
        instance.container.classList.remove('open');
        instance.isOpen = false;
        
        // נקה חיפוש
        if (instance.searchInput) {
            instance.searchInput.value = '';
            this.search(name, '');
        }
    },
    
    /**
     * חיפוש
     */
    search: function(name, query) {
        const instance = this.instances[name];
        
        query = query.toLowerCase().trim();
        
        let visibleCount = 0;
        
        instance.allOptions.forEach(option => {
            const searchText = option.getAttribute('data-search') || 
                              option.textContent.toLowerCase();
            
            // חיפוש חכם - גם באמצע המילים
            if (query === '' || searchText.includes(query)) {
                option.style.display = '';
                visibleCount++;
            } else {
                option.style.display = 'none';
            }
        });
        
        // הצג/הסתר הודעת "אין תוצאות"
        if (instance.noResults) {
            instance.noResults.style.display = visibleCount === 0 ? 'block' : 'none';
        }
    },
    
    /**
     * בחירת ערך
     */
    select: function(name, value) {
        const instance = this.instances[name];
        
        // עדכן את ה-hidden input
        instance.hiddenInput.value = value;
        
        // מצא את האופציה הנבחרת
        const selectedOption = instance.allOptions.find(
            opt => opt.getAttribute('data-value') === value
        );
        
        // עדכן את התצוגה
        if (selectedOption) {
            const text = selectedOption.textContent.trim();
            instance.display.querySelector('.smart-select-value').textContent = text;
            
            // סמן את האופציה הנבחרת
            instance.allOptions.forEach(opt => opt.classList.remove('selected'));
            selectedOption.classList.add('selected');
        } else if (value === '') {
            instance.display.querySelector('.smart-select-value').textContent = 
                instance.config.placeholder || 'בחר...';
        }
        
        // סגור את הדרופדאון
        this.close(name);
        
        // הפעל אירוע change
        const event = new Event('change', { bubbles: true });
        instance.hiddenInput.dispatchEvent(event);
        
        // אם יש שדות תלויים, עדכן אותם
        this.updateDependentFields(name, value);
    },
    
    /**
     * גלילה לאופציה הנבחרת
     */
    scrollToSelected: function(name) {
        const instance = this.instances[name];
        const selected = instance.optionsContainer.querySelector('.smart-select-option.selected');
        
        if (selected) {
            setTimeout(() => {
                selected.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
            }, 100);
        }
    },
    
    /**
     * טיפול במקלדת
     */
    handleKeyboard: function(name, e) {
        const instance = this.instances[name];
        
        if (!instance.isOpen) {
            // פתח עם Enter או Space או חץ למטה
            if (e.key === 'Enter' || e.key === ' ' || e.key === 'ArrowDown') {
                e.preventDefault();
                this.open(name);
            }
            return;
        }
        
        // סגור עם Escape
        if (e.key === 'Escape') {
            e.preventDefault();
            this.close(name);
            return;
        }
        
        // ניווט עם חצים
        if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
            e.preventDefault();
            this.navigateOptions(name, e.key === 'ArrowDown' ? 1 : -1);
            return;
        }
        
        // בחירה עם Enter
        if (e.key === 'Enter') {
            e.preventDefault();
            const focused = instance.optionsContainer.querySelector('.smart-select-option.focused');
            if (focused) {
                const value = focused.getAttribute('data-value');
                this.select(name, value);
            }
            return;
        }
    },
    
    /**
     * ניווט באופציות עם מקלדת
     */
    navigateOptions: function(name, direction) {
        const instance = this.instances[name];
        const visibleOptions = Array.from(instance.allOptions).filter(
            opt => opt.style.display !== 'none'
        );
        
        if (visibleOptions.length === 0) return;
        
        const currentFocused = instance.optionsContainer.querySelector('.smart-select-option.focused');
        let currentIndex = currentFocused ? visibleOptions.indexOf(currentFocused) : -1;
        
        // הסר פוקוס מהנוכחי
        if (currentFocused) {
            currentFocused.classList.remove('focused');
        }
        
        // חשב אינדקס חדש
        currentIndex += direction;
        
        // wrap around
        if (currentIndex < 0) {
            currentIndex = visibleOptions.length - 1;
        } else if (currentIndex >= visibleOptions.length) {
            currentIndex = 0;
        }
        
        // הוסף פוקוס לחדש
        const newFocused = visibleOptions[currentIndex];
        newFocused.classList.add('focused');
        newFocused.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
    },
    
    /**
     * הגדרת תלות בשדה אחר
     */
    setupDependency: function(name, dependsOn) {
        const parentField = document.getElementById(dependsOn);
        
        if (!parentField) {
            console.warn('SmartSelect: Parent field not found:', dependsOn);
            return;
        }
        
        // השבת את השדה התלוי בהתחלה
        this.setDisabled(name, true);
        
        // האזן לשינויים בשדה האב
        parentField.addEventListener('change', () => {
            const parentValue = parentField.value;
            
            if (!parentValue) {
                // אם השדה האב ריק, השבת את השדה התלוי
                this.setDisabled(name, true);
                this.clearOptions(name);
            } else {
                // טען אופציות חדשות
                this.loadDependentOptions(name, dependsOn, parentValue);
            }
        });
    },
    
    /**
     * טעינת אופציות תלויות
     */
    loadDependentOptions: function(name, parentField, parentValue) {
        const instance = this.instances[name];
        
        if (!instance.config.ajax_url) {
            console.error('SmartSelect: ajax_url not configured for dependent field');
            return;
        }
        
        // הצג loading
        this.showLoading(name);
        
        // בנה URL עם פרמטרים
        const url = `${instance.config.ajax_url}?${parentField}=${encodeURIComponent(parentValue)}`;
        
        fetch(url)
            .then(response => response.json())
            .then(data => {
                if (data.success && data.options) {
                    this.updateOptions(name, data.options);
                    this.setDisabled(name, false);
                } else {
                    console.error('SmartSelect: Invalid response format');
                    this.clearOptions(name);
                }
            })
            .catch(error => {
                console.error('SmartSelect: Error loading options:', error);
                this.clearOptions(name);
            })
            .finally(() => {
                this.hideLoading(name);
            });
    },
    
    /**
     * עדכון אופציות
     */
    updateOptions: function(name, options) {
        const instance = this.instances[name];
        
        // נקה אופציות קיימות
        instance.optionsContainer.innerHTML = '';
        
        // הוסף אופציות חדשות
        const fragment = document.createDocumentFragment();
        
        options.forEach(opt => {
            const div = document.createElement('div');
            div.className = 'smart-select-option';
            div.setAttribute('data-value', opt.value || opt.id);
            div.setAttribute('data-search', (opt.text || opt.name || '').toLowerCase());
            
            if (instance.config.display_mode === 'advanced' && (opt.subtitle || opt.badge)) {
                div.innerHTML = `
                    <div class="smart-select-option-content">
                        <div class="smart-select-option-text">${opt.text || opt.name}</div>
                        ${opt.subtitle ? `<div class="smart-select-option-subtitle">${opt.subtitle}</div>` : ''}
                    </div>
                    ${opt.badge ? `<span class="smart-select-option-badge">${opt.badge}</span>` : ''}
                `;
            } else {
                div.textContent = opt.text || opt.name;
            }
            
            // הוסף event listener
            div.addEventListener('click', (e) => {
                e.stopPropagation();
                this.select(name, div.getAttribute('data-value'));
            });
            
            fragment.appendChild(div);
        });
        
        instance.optionsContainer.appendChild(fragment);
        
        // עדכן את רשימת כל האופציות
        instance.allOptions = Array.from(
            instance.optionsContainer.querySelectorAll('.smart-select-option')
        );
    },
    
    /**
     * ניקוי אופציות
     */
    clearOptions: function(name) {
        const instance = this.instances[name];
        instance.optionsContainer.innerHTML = '<div class="smart-select-option" data-value="">בחר תחילה את השדה הקודם</div>';
        instance.allOptions = [];
        this.select(name, '');
    },
    
    /**
     * הצג loading
     */
    showLoading: function(name) {
        const instance = this.instances[name];
        instance.optionsContainer.innerHTML = '<div class="smart-select-loading">טוען...</div>';
    },
    
    /**
     * הסתר loading
     */
    hideLoading: function(name) {
        const instance = this.instances[name];
        const loading = instance.optionsContainer.querySelector('.smart-select-loading');
        if (loading) {
            loading.remove();
        }
    },
    
    /**
     * הפעל/בטל
     */
    setDisabled: function(name, disabled) {
        const instance = this.instances[name];
        instance.config.disabled = disabled;
        
        if (disabled) {
            instance.container.classList.add('disabled');
            instance.display.style.cursor = 'not-allowed';
            instance.display.style.opacity = '0.6';
        } else {
            instance.container.classList.remove('disabled');
            instance.display.style.cursor = 'pointer';
            instance.display.style.opacity = '1';
        }
    },
    
    /**
     * עדכון שדות תלויים
     */
    updateDependentFields: function(name, value) {
        // מצא שדות שתלויים בשדה הנוכחי
        Object.keys(this.instances).forEach(fieldName => {
            const instance = this.instances[fieldName];
            if (instance.config.depends_on === name) {
                if (!value) {
                    this.setDisabled(fieldName, true);
                    this.clearOptions(fieldName);
                } else {
                    this.loadDependentOptions(fieldName, name, value);
                }
            }
        });
    },
    
    /**
     * קבלת ערך
     */
    getValue: function(name) {
        const instance = this.instances[name];
        return instance ? instance.hiddenInput.value : null;
    },
    
    /**
     * הגדרת ערך
     */
    setValue: function(name, value) {
        const instance = this.instances[name];
        if (instance) {
            this.select(name, value);
        }
    }
};

// אתחול אוטומטי של כל ה-SmartSelects בטעינת הדף
document.addEventListener('DOMContentLoaded', function() {
    console.log('SmartSelect: DOM loaded, initializing...');
    
    // מצא את כל ה-wrappers
    const wrappers = document.querySelectorAll('.smart-select-wrapper');
    
    wrappers.forEach(wrapper => {
        const name = wrapper.getAttribute('data-name');
        const dataElement = document.getElementById('data_' + name);
        
        if (dataElement) {
            try {
                const data = JSON.parse(dataElement.textContent);
                SmartSelectManager.init(name, data.config);
            } catch (e) {
                console.error('SmartSelect: Error parsing config for', name, e);
            }
        }
    });
});