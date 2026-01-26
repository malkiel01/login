/**
 * WebOTP - קריאת קוד SMS אוטומטית
 * תומך ב-WebOTP API (Android Chrome)
 *
 * @version 1.0.0
 */

class WebOTP {
    constructor() {
        this.apiUrl = '/auth/otp/api.php';
        this.isSupported = this.checkSupport();
        this.abortController = null;
    }

    /**
     * בדיקת תמיכה ב-WebOTP
     */
    checkSupport() {
        return 'OTPCredential' in window;
    }

    /**
     * שליחת קוד OTP
     *
     * @param {string} phone - מספר טלפון
     * @param {string} purpose - מטרה (login, registration, verification, etc.)
     * @param {Object} actionData - מידע נוסף (אופציונלי)
     * @returns {Promise<Object>}
     */
    async sendOTP(phone, purpose = 'verification', actionData = {}) {
        try {
            const response = await fetch(this.apiUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'send',
                    phone,
                    purpose,
                    action_data: actionData
                }),
                credentials: 'include'
            });

            return await response.json();
        } catch (error) {
            console.error('[WebOTP] Send error:', error);
            return { success: false, error: error.message };
        }
    }

    /**
     * אימות קוד OTP
     *
     * @param {string} phone - מספר טלפון
     * @param {string} code - הקוד
     * @param {string} purpose - מטרה
     * @returns {Promise<Object>}
     */
    async verifyOTP(phone, code, purpose = 'verification') {
        try {
            const response = await fetch(this.apiUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'verify',
                    phone,
                    code,
                    purpose
                }),
                credentials: 'include'
            });

            return await response.json();
        } catch (error) {
            console.error('[WebOTP] Verify error:', error);
            return { success: false, error: error.message };
        }
    }

    /**
     * המתנה לקבלת OTP אוטומטית (WebOTP API)
     * עובד רק ב-Android Chrome
     *
     * @param {number} timeout - timeout במילישניות (ברירת מחדל: 60 שניות)
     * @returns {Promise<string|null>} - הקוד או null
     */
    async waitForOTP(timeout = 60000) {
        if (!this.isSupported) {
            console.log('[WebOTP] WebOTP not supported on this device');
            return null;
        }

        try {
            // בטל בקשה קודמת אם יש
            if (this.abortController) {
                this.abortController.abort();
            }

            this.abortController = new AbortController();

            console.log('[WebOTP] Waiting for OTP...');

            const credential = await navigator.credentials.get({
                otp: { transport: ['sms'] },
                signal: this.abortController.signal
            });

            if (credential && credential.code) {
                console.log('[WebOTP] OTP received:', credential.code);
                return credential.code;
            }

            return null;

        } catch (error) {
            if (error.name === 'AbortError') {
                console.log('[WebOTP] Aborted');
            } else {
                console.error('[WebOTP] Error waiting for OTP:', error);
            }
            return null;
        }
    }

    /**
     * ביטול המתנה ל-OTP
     */
    cancelWait() {
        if (this.abortController) {
            this.abortController.abort();
            this.abortController = null;
        }
    }

    /**
     * שליחה מחדש
     *
     * @param {string} phone
     * @param {string} purpose
     * @returns {Promise<Object>}
     */
    async resendOTP(phone, purpose = 'verification') {
        try {
            const response = await fetch(this.apiUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'resend',
                    phone,
                    purpose
                }),
                credentials: 'include'
            });

            return await response.json();
        } catch (error) {
            return { success: false, error: error.message };
        }
    }

    /**
     * בדיקת סטטוס
     *
     * @param {string} phone
     * @param {string} purpose
     * @returns {Promise<Object>}
     */
    async getStatus(phone, purpose = 'verification') {
        try {
            const response = await fetch(this.apiUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'status',
                    phone,
                    purpose
                }),
                credentials: 'include'
            });

            return await response.json();
        } catch (error) {
            return { success: false, error: error.message };
        }
    }

    /**
     * פורמט מספר טלפון להצגה
     */
    formatPhone(phone) {
        // הסר +972 והחזר 05X-XXX-XXXX
        let cleaned = phone.replace(/[^0-9]/g, '');

        if (cleaned.startsWith('972')) {
            cleaned = '0' + cleaned.substring(3);
        }

        if (cleaned.length === 10) {
            return cleaned.substring(0, 3) + '-' + cleaned.substring(3, 6) + '-' + cleaned.substring(6);
        }

        return phone;
    }
}

// יצירת instance גלובלי
window.webOTP = new WebOTP();

// Export
if (typeof module !== 'undefined' && module.exports) {
    module.exports = WebOTP;
}
