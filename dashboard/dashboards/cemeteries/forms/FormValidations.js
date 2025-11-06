/**
 * מערכת Validations מרכזית
 * מיקום: /dashboard/dashboards/cemeteries/forms/FormValidations.js
 */

const FormValidations = {
    
    /**
     * בדיקת תעודת זהות ישראלית
     */
    validateIsraeliId: function(value, fieldName, form) {
        value = String(value).trim();
        
        // בדיקה: בדיוק 9 ספרות
        if (value.length !== 9 || !/^\d+$/.test(value)) {
            return {
                valid: false,
                message: 'מספר תעודת זהות חייב להכיל 9 ספרות'
            };
        }
        
        // אלגוריתם Luhn
        let sum = 0;
        for (let i = 0; i < 9; i++) {
            let digit = parseInt(value[i]);
            digit *= ((i % 2) + 1);
            if (digit > 9) {
                digit = Math.floor(digit / 10) + (digit % 10);
            }
            sum += digit;
        }
        
        if (sum % 10 !== 0) {
            return {
                valid: false,
                message: '⚠️ מספר תעודת זהות לא תקין'
            };
        }
        
        return { valid: true };
    },
    
    /**
     * בדיקת אימייל
     */
    validateEmail: function(value) {
        const regex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!regex.test(value)) {
            return {
                valid: false,
                message: 'כתובת אימייל לא תקינה'
            };
        }
        return { valid: true };
    },
    
    /**
     * בדיקת טלפון ישראלי
     */
    validatePhone: function(value) {
        const cleaned = value.replace(/[^0-9]/g, '');
        if (cleaned.length < 9 || cleaned.length > 10) {
            return {
                valid: false,
                message: 'מספר טלפון לא תקין'
            };
        }
        return { valid: true };
    },
    
    /**
     * בדיקת ערך מינימלי
     */
    validateMin: function(value, minValue) {
        if (parseFloat(value) < minValue) {
            return {
                valid: false,
                message: `הערך חייב להיות לפחות ${minValue}`
            };
        }
        return { valid: true };
    },
    
    /**
     * בדיקת ערך מקסימלי
     */
    validateMax: function(value, maxValue) {
        if (parseFloat(value) > maxValue) {
            return {
                valid: false,
                message: `הערך לא יכול לעבור ${maxValue}`
            };
        }
        return { valid: true };
    },
    
    /**
     * בדיקת אורך מינימלי
     */
    validateMinLength: function(value, minLength) {
        if (value.length < minLength) {
            return {
                valid: false,
                message: `נדרשים לפחות ${minLength} תווים`
            };
        }
        return { valid: true };
    },
    
    /**
     * בדיקת אורך מקסימלי
     */
    validateMaxLength: function(value, maxLength) {
        if (value.length > maxLength) {
            return {
                valid: false,
                message: `מקסימום ${maxLength} תווים`
            };
        }
        return { valid: true };
    },
    
    /**
     * בדיקת ערך ייחודי (דורש קריאה לשרת)
     */
    validateUnique: async function(value, fieldName, form, entityType) {
        try {
            const response = await fetch(`/dashboard/dashboards/cemeteries/api/validation-api.php`, {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({
                    action: 'checkUnique',
                    field: fieldName,
                    value: value,
                    entityType: entityType,
                    excludeId: form.elements['unicId']?.value
                })
            });
            
            const result = await response.json();
            
            if (!result.unique) {
                return {
                    valid: false,
                    message: 'ערך זה כבר קיים במערכת'
                };
            }
            
            return { valid: true };
        } catch (error) {
            console.error('Error checking uniqueness:', error);
            return { valid: true }; // במקרה של שגיאה - אל תחסום
        }
    },
    
    /**
     * מנהל הרצת ולידציות
     */
    runValidations: async function(field, validations) {
        const value = field.value.trim();
        
        // אם השדה ריק ולא required - דלג
        if (!value && !field.required) {
            return { valid: true };
        }
        
        // הרץ כל ולידציה
        for (let validation of validations) {
            let result;
            
            if (typeof validation === 'string') {
                // ולידציה פשוטה: 'validateIsraeliId'
                if (this[validation]) {
                    result = await this[validation](value, field.name, field.form);
                }
            } else if (typeof validation === 'object') {
                // ולידציה עם פרמטרים: {rule: 'validateMin', params: [18]}
                const func = this[validation.rule];
                if (func) {
                    result = await func(value, ...(validation.params || []));
                }
            }
            
            if (result && !result.valid) {
                return result; // החזר את השגיאה הראשונה
            }
        }
        
        return { valid: true };
    },
    
    /**
     * הצגת שגיאה בשדה
     */
    showError: function(field, message) {
        // נקה שגיאות קודמות
        this.clearError(field);
        
        // הוסף מסגרת אדומה
        field.classList.add('validation-error');
        field.style.borderColor = '#dc2626';
        
        // צור הודעת שגיאה
        const errorDiv = document.createElement('div');
        errorDiv.className = 'validation-error-message';
        errorDiv.style.color = '#dc2626';
        errorDiv.style.fontSize = '12px';
        errorDiv.style.marginTop = '4px';
        errorDiv.style.fontWeight = '500';
        errorDiv.textContent = message;
        
        // הוסף אחרי השדה
        field.parentElement.appendChild(errorDiv);
    },
    
    /**
     * ניקוי שגיאה
     */
    clearError: function(field) {
        field.classList.remove('validation-error');
        field.style.borderColor = '';
        
        const error = field.parentElement.querySelector('.validation-error-message');
        if (error) error.remove();
    },
    
    /**
     * אתחול - הוסף listeners לכל השדות עם ולידציות
     */
    init: function(formElement) {
        // ✅ בדוק אם כבר אותחל - מנע אתחול כפול
        if (formElement.dataset.validationsInitialized === 'true') {
            console.log('⚠️ FormValidations already initialized for', formElement.id);
            return;
        }
        
        const fields = formElement.querySelectorAll('[data-validations]');
        
        if (fields.length === 0) {
            console.warn('⚠️ No fields with data-validations found in', formElement.id);
            return;
        }
        
        fields.forEach(field => {
            try {
                const validations = JSON.parse(field.dataset.validations);
                
                // בדיקה כשעוזבים את השדה
                field.addEventListener('blur', async () => {
                    const result = await this.runValidations(field, validations);
                    
                    if (!result.valid) {
                        this.showError(field, result.message);
                    } else {
                        this.clearError(field);
                    }
                });
                
                // נקה שגיאה כשמתחילים להקליד
                field.addEventListener('input', () => {
                    if (field.classList.contains('validation-error')) {
                        this.clearError(field);
                    }
                });
            } catch (e) {
                console.error('Error parsing validations for field:', field.name, e);
            }
        });
        
        // סמן שהאתחול הושלם
        formElement.dataset.validationsInitialized = 'true';
        console.log(`✅ FormValidations initialized: ${fields.length} fields with validations`);
    }
};

// הפוך גלובלי
window.FormValidations = FormValidations;

// 🆕 גיבוי: האזן ל-custom event 'formReady'
document.addEventListener('formReady', function(e) {
    const form = e.detail.form;
    if (form && !form.dataset.validationsInitialized) {
        FormValidations.init(form);
        form.dataset.validationsInitialized = 'true'; // מנע אתחול כפול
        console.log('✅ FormValidations initialized via event for', e.detail.type);
    }
});

// 🆕 גיבוי נוסף: MutationObserver - אם בכל זאת משהו השתבש
(function() {
    const observer = new MutationObserver(function(mutations) {
        mutations.forEach(function(mutation) {
            mutation.addedNodes.forEach(function(node) {
                // בדוק אם זה modal עם טופס
                if (node.nodeType === 1 && node.classList && node.classList.contains('modal')) {
                    const form = node.querySelector('form');
                    if (form && !form.dataset.validationsInitialized) {
                        // מצאנו טופס שטרם אותחל - אתחל אותו!
                        requestAnimationFrame(() => {
                            FormValidations.init(form);
                            form.dataset.validationsInitialized = 'true';
                            console.log('✅ FormValidations initialized via MutationObserver for', form.id);
                        });
                    }
                }
            });
        });
    });
    
    // התחל להאזין לשינויים ב-body
    observer.observe(document.body, {
        childList: true,
        subtree: false // רק ילדים ישירים של body
    });
})();