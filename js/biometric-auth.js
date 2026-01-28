/**
 * Biometric Auth - אימות ביומטרי (WebAuthn)
 * תומך ב-Touch ID, Face ID, Windows Hello
 *
 * @version 1.0.0
 */

class BiometricAuth {
    constructor() {
        this.apiUrl = '/auth/biometric/api.php';
        this.isSupported = this.checkSupport();
    }

    /**
     * בדיקת תמיכה ב-WebAuthn
     * @returns {boolean}
     */
    checkSupport() {
        return !!(
            window.PublicKeyCredential &&
            typeof window.PublicKeyCredential === 'function'
        );
    }

    /**
     * בדיקה אם יש authenticator זמין (platform)
     * @returns {Promise<boolean>}
     */
    async isPlatformAuthenticatorAvailable() {
        if (!this.isSupported) return false;

        try {
            return await PublicKeyCredential.isUserVerifyingPlatformAuthenticatorAvailable();
        } catch (e) {
            console.warn('[Biometric] Platform authenticator check failed:', e);
            return false;
        }
    }

    /**
     * בדיקה אם למשתמש יש ביומטרי מוגדר
     * @returns {Promise<boolean>}
     */
    async userHasBiometric() {
        try {
            const response = await fetch(this.apiUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'check_support' }),
                credentials: 'include'
            });

            const data = await response.json();
            return data.success && data.has_biometric;
        } catch (e) {
            console.error('[Biometric] Check failed:', e);
            return false;
        }
    }

    // ========================================
    // רישום Credential חדש
    // ========================================

    /**
     * רישום ביומטרי חדש
     * @param {string} deviceName - שם המכשיר (אופציונלי)
     * @returns {Promise<Object>}
     */
    async register(deviceName = '') {
        if (!this.isSupported) {
            return { success: false, error: 'WebAuthn not supported' };
        }

        try {
            // שלב 1: קבל אפשרויות מהשרת
            console.log('[Biometric] Getting registration options...');
            const optionsResponse = await fetch(this.apiUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'register_options' }),
                credentials: 'include'
            });

            const optionsData = await optionsResponse.json();
            if (!optionsData.success) {
                return { success: false, error: optionsData.message || 'Failed to get options' };
            }

            const options = optionsData.options;

            // המר מ-base64url ל-ArrayBuffer
            options.challenge = this.base64UrlToArrayBuffer(options.challenge);
            options.user.id = this.base64UrlToArrayBuffer(options.user.id);

            if (options.excludeCredentials) {
                options.excludeCredentials = options.excludeCredentials.map(cred => ({
                    ...cred,
                    id: this.base64UrlToArrayBuffer(cred.id)
                }));
            }

            // שלב 2: יצירת credential בדפדפן
            console.log('[Biometric] Creating credential...');
            const credential = await navigator.credentials.create({
                publicKey: options
            });

            if (!credential) {
                return { success: false, error: 'User cancelled or no credential created' };
            }

            // שלב 3: שלח לשרת לאימות
            console.log('[Biometric] Verifying with server...');
            const credentialData = {
                id: credential.id,
                rawId: this.arrayBufferToBase64Url(credential.rawId),
                type: credential.type,
                response: {
                    clientDataJSON: this.arrayBufferToBase64Url(credential.response.clientDataJSON),
                    attestationObject: this.arrayBufferToBase64Url(credential.response.attestationObject)
                }
            };

            // הוסף transports אם זמין
            if (credential.response.getTransports) {
                credentialData.response.transports = credential.response.getTransports();
            }

            const verifyResponse = await fetch(this.apiUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'register_verify',
                    credential: credentialData,
                    device_name: deviceName || this.guessDeviceName()
                }),
                credentials: 'include'
            });

            const result = await verifyResponse.json();
            console.log('[Biometric] Registration result:', result.success);

            return result;

        } catch (error) {
            console.error('[Biometric] Registration error:', error);

            // טיפול בשגיאות ספציפיות
            if (error.name === 'NotAllowedError') {
                return { success: false, error: 'User denied permission', userCancelled: true };
            }
            if (error.name === 'InvalidStateError') {
                return { success: false, error: 'Credential already exists' };
            }

            return { success: false, error: error.message };
        }
    }

    // ========================================
    // אימות עם Credential קיים
    // ========================================

    /**
     * אימות ביומטרי (התחברות)
     * @param {number|null} userId - מזהה משתמש (אופציונלי)
     * @returns {Promise<Object>}
     */
    async authenticate(userId = null) {
        if (!this.isSupported) {
            return { success: false, error: 'WebAuthn not supported' };
        }

        try {
            // שלב 1: קבל אפשרויות מהשרת
            console.log('[Biometric] Getting authentication options...');
            const optionsResponse = await fetch(this.apiUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'auth_options',
                    user_id: userId
                }),
                credentials: 'include'
            });

            const optionsData = await optionsResponse.json();
            if (!optionsData.success) {
                return { success: false, error: optionsData.message || 'Failed to get options' };
            }

            const options = optionsData.options;

            // המר מ-base64url ל-ArrayBuffer
            options.challenge = this.base64UrlToArrayBuffer(options.challenge);

            if (options.allowCredentials && options.allowCredentials.length > 0) {
                options.allowCredentials = options.allowCredentials.map(cred => ({
                    ...cred,
                    id: this.base64UrlToArrayBuffer(cred.id)
                }));
            }

            // שלב 2: אימות בדפדפן
            console.log('[Biometric] Authenticating...');
            const assertion = await navigator.credentials.get({
                publicKey: options
            });

            if (!assertion) {
                return { success: false, error: 'User cancelled or authentication failed' };
            }

            // שלב 3: שלח לשרת לאימות
            console.log('[Biometric] Verifying with server...');
            const assertionData = {
                id: assertion.id,
                rawId: this.arrayBufferToBase64Url(assertion.rawId),
                type: assertion.type,
                response: {
                    clientDataJSON: this.arrayBufferToBase64Url(assertion.response.clientDataJSON),
                    authenticatorData: this.arrayBufferToBase64Url(assertion.response.authenticatorData),
                    signature: this.arrayBufferToBase64Url(assertion.response.signature),
                    userHandle: assertion.response.userHandle ?
                        this.arrayBufferToBase64Url(assertion.response.userHandle) : null
                }
            };

            const verifyResponse = await fetch(this.apiUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'auth_verify',
                    assertion: assertionData,
                    user_id: userId
                }),
                credentials: 'include'
            });

            const result = await verifyResponse.json();

            if (result.success && result.token) {
                // שמור token
                if (window.persistentAuth) {
                    await window.persistentAuth.saveLogin(result.token);
                }
            }

            return result;

        } catch (error) {
            console.error('[Biometric] Authentication error:', error);

            if (error.name === 'NotAllowedError') {
                return { success: false, error: 'User denied permission', userCancelled: true };
            }

            return { success: false, error: error.message };
        }
    }

    // ========================================
    // אישור פעולות רגישות
    // ========================================

    /**
     * אישור פעולה עם ביומטרי
     * @param {string} actionType - סוג הפעולה (payment, delete, etc.)
     * @param {Object} actionData - מידע על הפעולה
     * @returns {Promise<Object>}
     */
    async confirmAction(actionType, actionData = {}) {
        if (!this.isSupported) {
            // fallback לאישור רגיל
            return { success: confirm('האם לאשר את הפעולה?'), fallback: true };
        }

        try {
            // שלב 1: קבל אפשרויות
            const optionsResponse = await fetch(this.apiUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'confirm_options',
                    action_type: actionType,
                    action_data: actionData
                }),
                credentials: 'include'
            });

            const optionsData = await optionsResponse.json();
            if (!optionsData.success) {
                return { success: false, error: optionsData.message };
            }

            const options = optionsData.options;
            options.challenge = this.base64UrlToArrayBuffer(options.challenge);

            if (options.allowCredentials) {
                options.allowCredentials = options.allowCredentials.map(cred => ({
                    ...cred,
                    id: this.base64UrlToArrayBuffer(cred.id)
                }));
            }

            // שלב 2: אישור בדפדפן
            const assertion = await navigator.credentials.get({
                publicKey: options
            });

            if (!assertion) {
                return { success: false, error: 'User cancelled' };
            }

            // שלב 3: אימות בשרת
            const assertionData = {
                id: assertion.id,
                rawId: this.arrayBufferToBase64Url(assertion.rawId),
                type: assertion.type,
                response: {
                    clientDataJSON: this.arrayBufferToBase64Url(assertion.response.clientDataJSON),
                    authenticatorData: this.arrayBufferToBase64Url(assertion.response.authenticatorData),
                    signature: this.arrayBufferToBase64Url(assertion.response.signature),
                    userHandle: assertion.response.userHandle ?
                        this.arrayBufferToBase64Url(assertion.response.userHandle) : null
                }
            };

            const verifyResponse = await fetch(this.apiUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'confirm_verify',
                    assertion: assertionData
                }),
                credentials: 'include'
            });

            return await verifyResponse.json();

        } catch (error) {
            if (error.name === 'NotAllowedError') {
                return { success: false, error: 'User denied', userCancelled: true };
            }
            return { success: false, error: error.message };
        }
    }

    // ========================================
    // ניהול Credentials
    // ========================================

    /**
     * קבלת רשימת credentials
     * @returns {Promise<Array>}
     */
    async listCredentials() {
        try {
            const response = await fetch(this.apiUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'list_credentials' }),
                credentials: 'include'
            });

            const data = await response.json();
            return data.success ? data.credentials : [];
        } catch (e) {
            console.error('[Biometric] List credentials error:', e);
            return [];
        }
    }

    /**
     * מחיקת credential
     * @param {string} credentialId
     * @returns {Promise<boolean>}
     */
    async deleteCredential(credentialId) {
        try {
            const response = await fetch(this.apiUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'delete_credential',
                    credential_id: credentialId
                }),
                credentials: 'include'
            });

            const data = await response.json();
            return data.success;
        } catch (e) {
            console.error('[Biometric] Delete credential error:', e);
            return false;
        }
    }

    // ========================================
    // פונקציות עזר
    // ========================================

    /**
     * המרת base64url ל-ArrayBuffer
     */
    base64UrlToArrayBuffer(base64url) {
        const base64 = base64url.replace(/-/g, '+').replace(/_/g, '/');
        const padding = '='.repeat((4 - base64.length % 4) % 4);
        const binary = atob(base64 + padding);
        const buffer = new ArrayBuffer(binary.length);
        const view = new Uint8Array(buffer);
        for (let i = 0; i < binary.length; i++) {
            view[i] = binary.charCodeAt(i);
        }
        return buffer;
    }

    /**
     * המרת ArrayBuffer ל-base64url
     */
    arrayBufferToBase64Url(buffer) {
        const bytes = new Uint8Array(buffer);
        let binary = '';
        for (let i = 0; i < bytes.length; i++) {
            binary += String.fromCharCode(bytes[i]);
        }
        const base64 = btoa(binary);
        return base64.replace(/\+/g, '-').replace(/\//g, '_').replace(/=/g, '');
    }

    /**
     * ניחוש שם המכשיר
     */
    guessDeviceName() {
        const ua = navigator.userAgent;
        if (/iPhone/.test(ua)) return 'iPhone';
        if (/iPad/.test(ua)) return 'iPad';
        if (/Mac/.test(ua)) return 'Mac';
        if (/Windows/.test(ua)) return 'Windows PC';
        if (/Android/.test(ua)) return 'Android Device';
        return 'Unknown Device';
    }
}

// יצירת instance גלובלי
window.biometricAuth = new BiometricAuth();

// Static method to check availability
BiometricAuth.isAvailable = function() {
    return window.biometricAuth && window.biometricAuth.isSupported;
};

// Shorthand for checking platform authenticator
BiometricAuth.checkPlatformAuthenticator = async function() {
    if (!BiometricAuth.isAvailable()) return false;
    return await window.biometricAuth.isPlatformAuthenticatorAvailable();
};

// Export
if (typeof module !== 'undefined' && module.exports) {
    module.exports = BiometricAuth;
}
