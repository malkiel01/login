/**
 * SmartSelect JavaScript - גרסה פשוטה
 */
window.SmartSelectManager = {
    
    instances: {},
    
    init: function() {
        // מצא את כל ה-SmartSelects
        document.querySelectorAll('.smart-select-wrapper').forEach(wrapper => {
            const name = wrapper.dataset.name;
            if (this.instances[name]) return; // כבר מאותחל
            
            const hiddenInput = wrapper.querySelector('input[type="hidden"]');
            const display = wrapper.querySelector('.smart-select-display');
            const dropdown = wrapper.querySelector('.smart-select-dropdown');
            const searchInput = wrapper.querySelector('.smart-select-search-input');
            const optionsContainer = wrapper.querySelector('.smart-select-options');
            const valueSpan = wrapper.querySelector('.smart-select-value');
            const noResults = wrapper.querySelector('.smart-select-no-results');
            
            if (!hiddenInput || !display) return;
            
            // שמור מופע
            this.instances[name] = {
                wrapper, hiddenInput, display, dropdown, 
                searchInput, optionsContainer, valueSpan, noResults,
                isOpen: false,
                allOptions: Array.from(optionsContainer.querySelectorAll('.smart-select-option'))
            };
            
            // הוסף אירועים
            this.bindEvents(name);
        });
    },
    
    bindEvents: function(name) {
        const inst = this.instances[name];
        
        // פתיחה/סגירה
        inst.display.addEventListener('click', (e) => {
            e.stopPropagation();
            this.toggle(name);
        });
        
        // חיפוש
        if (inst.searchInput) {
            inst.searchInput.addEventListener('input', (e) => {
                this.search(name, e.target.value);
            });
        }
        
        // בחירת אופציה
        inst.allOptions.forEach(option => {
            option.addEventListener('click', () => {
                this.select(name, option.dataset.value);
            });
        });
        
        // סגירה בלחיצה מחוץ
        document.addEventListener('click', (e) => {
            if (inst.isOpen && !inst.wrapper.contains(e.target)) {
                this.close(name);
            }
        });
    },
    
    toggle: function(name) {
        if (this.instances[name].isOpen) {
            this.close(name);
        } else {
            this.open(name);
        }
    },
    
    open: function(name) {
        const inst = this.instances[name];
        
        // סגור אחרים
        Object.keys(this.instances).forEach(n => {
            if (n !== name) this.close(n);
        });
        
        inst.isOpen = true;
        inst.display.classList.add('active');
        inst.dropdown.style.display = 'block';
        
        if (inst.searchInput) {
            setTimeout(() => inst.searchInput.focus(), 100);
        }
    },
    
    close: function(name) {
        const inst = this.instances[name];
        inst.isOpen = false;
        inst.display.classList.remove('active');
        inst.dropdown.style.display = 'none';
        
        if (inst.searchInput) {
            inst.searchInput.value = '';
            this.search(name, '');
        }
    },
    
    search: function(name, query) {
        const inst = this.instances[name];
        query = query.trim().toLowerCase();
        
        let visibleCount = 0;
        
        inst.allOptions.forEach(option => {
            const text = option.textContent.toLowerCase();
            
            if (query === '' || text.includes(query)) {
                option.classList.remove('hidden');
                visibleCount++;
            } else {
                option.classList.add('hidden');
            }
        });
        
        inst.noResults.style.display = visibleCount === 0 ? 'block' : 'none';
    },
    
    select: function(name, value) {
        const inst = this.instances[name];
        
        inst.hiddenInput.value = value;
        
        const selectedOption = inst.allOptions.find(opt => opt.dataset.value === value);
        if (selectedOption) {
            inst.valueSpan.textContent = selectedOption.textContent;
            
            inst.allOptions.forEach(opt => opt.classList.remove('selected'));
            selectedOption.classList.add('selected');
        }
        
        this.close(name);
        
        // הפעל אירוע change
        inst.hiddenInput.dispatchEvent(new Event('change', { bubbles: true }));
    }
};

// אתחול אוטומטי
document.addEventListener('DOMContentLoaded', function() {
    SmartSelectManager.init();
});