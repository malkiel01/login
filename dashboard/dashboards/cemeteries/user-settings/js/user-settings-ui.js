/*
 * File: user-settings/js/user-settings-ui.js
 * Version: 1.0.0
 * Created: 2026-01-23
 * Author: Malkiel
 * Description: רכיבי UI להגדרות משתמש
 */

const UserSettingsUI = (function() {

    // תרגומים לקטגוריות
    const categoryLabels = {
        'display': 'תצוגה',
        'notifications': 'התראות',
        'tables': 'טבלאות',
        'navigation': 'ניווט',
        'locale': 'שפה ואזור',
        'general': 'כללי'
    };

    // אייקונים לקטגוריות
    const categoryIcons = {
        'display': 'fa-palette',
        'notifications': 'fa-bell',
        'tables': 'fa-table',
        'navigation': 'fa-compass',
        'locale': 'fa-globe',
        'general': 'fa-cog'
    };

    /**
     * יצירת רכיב הגדרה בודדת
     */
    function createSettingItem(key, data) {
        const item = document.createElement('div');
        item.className = 'setting-item';
        item.dataset.key = key;

        const header = document.createElement('div');
        header.className = 'setting-header';

        const label = document.createElement('label');
        label.className = 'setting-label';
        label.textContent = data.label || key;

        const resetBtn = document.createElement('button');
        resetBtn.className = 'setting-reset-btn' + (data.isDefault ? ' hidden' : '');
        resetBtn.innerHTML = '<i class="fas fa-undo"></i>';
        resetBtn.title = 'חזור לברירת מחדל';
        resetBtn.onclick = async () => {
            await UserSettings.reset(key);
            const newValue = data.defaultValue;
            updateInputValue(item.querySelector('.setting-input'), newValue, data.type);
            resetBtn.classList.add('hidden');
        };

        header.appendChild(label);
        header.appendChild(resetBtn);

        if (data.description) {
            const desc = document.createElement('div');
            desc.className = 'setting-description';
            desc.textContent = data.description;
            header.appendChild(desc);
        }

        const input = createInput(key, data);
        input.classList.add('setting-input');

        item.appendChild(header);
        item.appendChild(input);

        return item;
    }

    /**
     * יצירת רכיב קלט לפי סוג
     */
    function createInput(key, data) {
        const { type, value, options } = data;

        switch (type) {
            case 'boolean':
                return createToggle(key, value);
            case 'number':
                return createNumberInput(key, value, options);
            default:
                if (options && options.length > 0) {
                    return createSelect(key, value, options);
                }
                return createTextInput(key, value);
        }
    }

    /**
     * יצירת toggle switch
     */
    function createToggle(key, value) {
        const wrapper = document.createElement('label');
        wrapper.className = 'toggle-switch';

        const input = document.createElement('input');
        input.type = 'checkbox';
        input.checked = value === true || value === 'true';
        input.onchange = async () => {
            await UserSettings.set(key, input.checked);
            wrapper.closest('.setting-item')?.querySelector('.setting-reset-btn')?.classList.remove('hidden');
        };

        const slider = document.createElement('span');
        slider.className = 'toggle-slider';

        wrapper.appendChild(input);
        wrapper.appendChild(slider);

        return wrapper;
    }

    /**
     * יצירת שדה מספר
     */
    function createNumberInput(key, value, options) {
        const input = document.createElement('input');
        input.type = 'number';
        input.className = 'form-control';
        input.value = value || 0;

        if (options) {
            if (options.min !== undefined) input.min = options.min;
            if (options.max !== undefined) input.max = options.max;
            if (options.step !== undefined) input.step = options.step;
        }

        let debounceTimer;
        input.onchange = async () => {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(async () => {
                await UserSettings.set(key, parseFloat(input.value));
                input.closest('.setting-item')?.querySelector('.setting-reset-btn')?.classList.remove('hidden');
            }, 300);
        };

        return input;
    }

    /**
     * יצירת select
     */
    function createSelect(key, value, options) {
        const select = document.createElement('select');
        select.className = 'form-control';

        options.forEach(opt => {
            const option = document.createElement('option');
            option.value = opt.value;
            option.textContent = opt.label;
            if (opt.value === value) option.selected = true;
            select.appendChild(option);
        });

        select.onchange = async () => {
            await UserSettings.set(key, select.value);
            select.closest('.setting-item')?.querySelector('.setting-reset-btn')?.classList.remove('hidden');
        };

        return select;
    }

    /**
     * יצירת שדה טקסט
     */
    function createTextInput(key, value) {
        const input = document.createElement('input');
        input.type = 'text';
        input.className = 'form-control';
        input.value = value || '';

        let debounceTimer;
        input.onchange = async () => {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(async () => {
                await UserSettings.set(key, input.value);
                input.closest('.setting-item')?.querySelector('.setting-reset-btn')?.classList.remove('hidden');
            }, 300);
        };

        return input;
    }

    /**
     * עדכון ערך בקלט
     */
    function updateInputValue(input, value, type) {
        if (!input) return;

        if (type === 'boolean') {
            const checkbox = input.querySelector('input[type="checkbox"]');
            if (checkbox) checkbox.checked = value;
        } else if (input.tagName === 'SELECT') {
            input.value = value;
        } else {
            input.value = value;
        }
    }

    /**
     * יצירת קטגוריה
     */
    function createCategory(category, settings) {
        const section = document.createElement('div');
        section.className = 'settings-category';
        section.dataset.category = category;

        const header = document.createElement('div');
        header.className = 'category-header';

        const icon = document.createElement('i');
        icon.className = `fas ${categoryIcons[category] || 'fa-cog'}`;

        const title = document.createElement('h3');
        title.textContent = categoryLabels[category] || category;

        header.appendChild(icon);
        header.appendChild(title);
        section.appendChild(header);

        const content = document.createElement('div');
        content.className = 'category-content';

        for (const [key, data] of Object.entries(settings)) {
            content.appendChild(createSettingItem(key, data));
        }

        section.appendChild(content);

        return section;
    }

    /**
     * רינדור כל ההגדרות
     */
    function render(container, settings = null) {
        if (!container) {
            console.error('UserSettingsUI: Container not found');
            return;
        }

        settings = settings || UserSettings.getAll();
        container.innerHTML = '';

        // קיבוץ לפי קטגוריות
        const categories = {};
        for (const [key, data] of Object.entries(settings)) {
            const cat = data.category || 'general';
            if (!categories[cat]) categories[cat] = {};
            categories[cat][key] = data;
        }

        // סדר קטגוריות
        const categoryOrder = ['display', 'tables', 'navigation', 'notifications', 'locale', 'general'];

        categoryOrder.forEach(cat => {
            if (categories[cat]) {
                container.appendChild(createCategory(cat, categories[cat]));
            }
        });

        // קטגוריות נוספות
        for (const cat of Object.keys(categories)) {
            if (!categoryOrder.includes(cat)) {
                container.appendChild(createCategory(cat, categories[cat]));
            }
        }
    }

    /**
     * פתיחת פופאפ הגדרות
     */
    async function openModal() {
        // טעינת הגדרות
        await UserSettings.load();

        if (typeof PopupManager !== 'undefined') {
            PopupManager.create({
                id: 'user-settings-modal',
                type: 'iframe',
                src: '/dashboard/dashboards/cemeteries/user-settings/settings-page.php',
                title: 'הגדרות אישיות',
                width: 700,
                height: 600
            });
        } else {
            // fallback - פתיחה בחלון חדש
            window.open('/dashboard/dashboards/cemeteries/user-settings/settings-page.php', '_blank');
        }
    }

    /**
     * הוספת כפתור הגדרות לתפריט
     */
    function addMenuButton(menuSelector = '.user-menu, .dropdown-menu') {
        const menu = document.querySelector(menuSelector);
        if (!menu) return;

        const btn = document.createElement('a');
        btn.href = '#';
        btn.className = 'dropdown-item settings-link';
        btn.innerHTML = '<i class="fas fa-cog"></i> הגדרות אישיות';
        btn.onclick = (e) => {
            e.preventDefault();
            openModal();
        };

        menu.insertBefore(btn, menu.firstChild);
    }

    return {
        createSettingItem,
        createCategory,
        render,
        openModal,
        addMenuButton,
        categoryLabels,
        categoryIcons
    };
})();

// Export for use
if (typeof window !== 'undefined') {
    window.UserSettingsUI = UserSettingsUI;
}
