// SmartSelect JavaScript - Basic Version
window.SmartSelectManager = {
    instances: {},
    
    init: function(name, config) {
        const element = document.getElementById(name);
        if (!element) return;
        
        this.instances[name] = {
            element: element,
            config: config
        };
        
        if (config.searchable) {
            this.makeSearchable(element);
        }
    },
    
    makeSearchable: function(select) {
        // יצירת wrapper
        const wrapper = document.createElement('div');
        wrapper.className = 'smart-select-wrapper';
        select.parentNode.insertBefore(wrapper, select);
        
        // יצירת שדה חיפוש
        const searchInput = document.createElement('input');
        searchInput.type = 'text';
        searchInput.className = 'smart-select-search';
        searchInput.placeholder = 'חפש...';
        searchInput.style.display = 'none';
        
        wrapper.appendChild(searchInput);
        wrapper.appendChild(select);
        
        // הוסף אירועים
        select.addEventListener('focus', function() {
            searchInput.style.display = 'block';
            searchInput.focus();
        });
        
        searchInput.addEventListener('input', function(e) {
            const query = e.target.value.toLowerCase();
            const options = select.querySelectorAll('option');
            
            options.forEach(option => {
                if (!option.value) return;
                const text = option.textContent.toLowerCase();
                option.style.display = text.includes(query) ? '' : 'none';
            });
        });
    },
    
    updateOptions: function(name, options) {
        const instance = this.instances[name];
        if (!instance) return;
        
        const select = instance.element;
        select.innerHTML = '<option value="">בחר...</option>';
        
        options.forEach(opt => {
            const option = document.createElement('option');
            option.value = opt.value || opt.id;
            option.textContent = opt.text || opt.name;
            select.appendChild(option);
        });
    }
};

// אתחול אוטומטי
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.smart-select[data-searchable="true"]').forEach(select => {
        SmartSelectManager.init(select.id, {searchable: true});
    });
});
