/**
 * OTP UI - ממשק משתמש לאימות SMS
 *
 * @version 1.0.0
 * @requires webotp.js
 */

class OTPUI {
    constructor() {
        this.otp = window.webOTP;
    }

    /**
     * הצגת מודל אימות SMS
     *
     * @param {Object} options
     * @param {string} options.phone - מספר טלפון (אופציונלי - אם לא סופק, יבקש מהמשתמש)
     * @param {string} options.purpose - מטרה
     * @param {string} options.title - כותרת
     * @param {Function} options.onSuccess - callback להצלחה
     * @param {Function} options.onError - callback לשגיאה
     * @returns {HTMLElement}
     */
    showVerificationModal(options = {}) {
        const {
            phone = '',
            purpose = 'verification',
            title = 'אימות טלפון',
            onSuccess,
            onError
        } = options;

        const overlay = document.createElement('div');
        overlay.className = 'otp-modal-overlay';
        overlay.innerHTML = `
            <div class="otp-modal">
                <button class="otp-close-btn">&times;</button>
                <div class="otp-modal-header">
                    <span class="otp-icon">📱</span>
                    <h2>${title}</h2>
                </div>

                <!-- שלב 1: הזנת טלפון -->
                <div class="otp-step" id="otp-step-phone" ${phone ? 'style="display:none"' : ''}>
                    <p>הזן את מספר הטלפון שלך לקבלת קוד אימות</p>
                    <div class="otp-phone-input">
                        <input type="tel" id="otp-phone" placeholder="050-000-0000" dir="ltr"
                               pattern="[0-9]{10}" maxlength="12" value="${phone}">
                    </div>
                    <button class="otp-btn-primary" id="otp-send-btn">
                        שלח קוד
                    </button>
                </div>

                <!-- שלב 2: הזנת קוד -->
                <div class="otp-step" id="otp-step-code" style="display:none">
                    <p>הזן את הקוד שנשלח ל-<span id="otp-phone-display"></span></p>

                    <div class="otp-code-inputs">
                        <input type="text" maxlength="1" class="otp-digit" data-index="0" inputmode="numeric">
                        <input type="text" maxlength="1" class="otp-digit" data-index="1" inputmode="numeric">
                        <input type="text" maxlength="1" class="otp-digit" data-index="2" inputmode="numeric">
                        <input type="text" maxlength="1" class="otp-digit" data-index="3" inputmode="numeric">
                        <input type="text" maxlength="1" class="otp-digit" data-index="4" inputmode="numeric">
                        <input type="text" maxlength="1" class="otp-digit" data-index="5" inputmode="numeric">
                    </div>

                    <div class="otp-timer" id="otp-timer">
                        <span id="otp-countdown">05:00</span>
                    </div>

                    <div class="otp-resend" id="otp-resend-section" style="display:none">
                        <span>לא קיבלת?</span>
                        <button class="otp-link-btn" id="otp-resend-btn">שלח שוב</button>
                    </div>

                    <button class="otp-btn-primary" id="otp-verify-btn" disabled>
                        אמת
                    </button>

                    <button class="otp-link-btn" id="otp-change-phone">שנה מספר</button>
                </div>

                <!-- שלב 3: הצלחה -->
                <div class="otp-step" id="otp-step-success" style="display:none">
                    <div class="otp-success-icon">✅</div>
                    <h3>אומת בהצלחה!</h3>
                </div>

                <div class="otp-status" id="otp-status"></div>
            </div>
        `;

        document.body.appendChild(overlay);
        this.injectStyles();

        // State
        let currentPhone = phone;
        let countdownInterval = null;
        let waitingForAutoOTP = false;

        // Elements
        const phoneInput = overlay.querySelector('#otp-phone');
        const sendBtn = overlay.querySelector('#otp-send-btn');
        const verifyBtn = overlay.querySelector('#otp-verify-btn');
        const resendBtn = overlay.querySelector('#otp-resend-btn');
        const changePhoneBtn = overlay.querySelector('#otp-change-phone');
        const closeBtn = overlay.querySelector('.otp-close-btn');
        const codeInputs = overlay.querySelectorAll('.otp-digit');
        const statusEl = overlay.querySelector('#otp-status');

        // Close
        const closeModal = () => {
            this.otp.cancelWait();
            if (countdownInterval) clearInterval(countdownInterval);
            overlay.classList.add('closing');
            setTimeout(() => overlay.remove(), 300);
        };

        closeBtn.addEventListener('click', closeModal);
        overlay.addEventListener('click', (e) => {
            if (e.target === overlay) closeModal();
        });

        // Phone formatting
        phoneInput.addEventListener('input', (e) => {
            let value = e.target.value.replace(/[^0-9]/g, '');
            if (value.length > 10) value = value.substring(0, 10);

            // Format as 050-000-0000
            if (value.length > 3 && value.length <= 6) {
                value = value.substring(0, 3) + '-' + value.substring(3);
            } else if (value.length > 6) {
                value = value.substring(0, 3) + '-' + value.substring(3, 6) + '-' + value.substring(6);
            }

            e.target.value = value;
        });

        // Send OTP
        const sendOTP = async () => {
            currentPhone = phoneInput.value.replace(/[^0-9]/g, '');

            if (currentPhone.length !== 10) {
                showStatus('יש להזין מספר טלפון תקין', 'error');
                return;
            }

            sendBtn.disabled = true;
            sendBtn.innerHTML = '<span class="spinner"></span> שולח...';

            const result = await this.otp.sendOTP(currentPhone, purpose);

            if (result.success) {
                // עבור לשלב הקוד
                overlay.querySelector('#otp-step-phone').style.display = 'none';
                overlay.querySelector('#otp-step-code').style.display = 'block';
                overlay.querySelector('#otp-phone-display').textContent = this.otp.formatPhone(currentPhone);

                // התחל countdown
                startCountdown(result.expires_in || 300);

                // נסה לקרוא OTP אוטומטית
                if (this.otp.isSupported) {
                    waitingForAutoOTP = true;
                    showStatus('ממתין לקוד...', 'info');
                    this.otp.waitForOTP().then(code => {
                        if (code && waitingForAutoOTP) {
                            fillCode(code);
                            verifyCode();
                        }
                    });
                }

                codeInputs[0].focus();
            } else {
                showStatus(result.error || 'שגיאה בשליחה', 'error');
                sendBtn.disabled = false;
                sendBtn.innerHTML = 'שלח קוד';
            }
        };

        sendBtn.addEventListener('click', sendOTP);

        // אם יש טלפון מראש, שלח אוטומטית
        if (phone) {
            sendOTP();
        }

        // Code inputs
        codeInputs.forEach((input, index) => {
            input.addEventListener('input', (e) => {
                const value = e.target.value.replace(/[^0-9]/g, '');
                e.target.value = value;

                if (value && index < 5) {
                    codeInputs[index + 1].focus();
                }

                checkCodeComplete();
            });

            input.addEventListener('keydown', (e) => {
                if (e.key === 'Backspace' && !e.target.value && index > 0) {
                    codeInputs[index - 1].focus();
                }
            });

            input.addEventListener('paste', (e) => {
                e.preventDefault();
                const pastedData = e.clipboardData.getData('text').replace(/[^0-9]/g, '');
                fillCode(pastedData);
            });
        });

        const fillCode = (code) => {
            const digits = code.substring(0, 6).split('');
            digits.forEach((digit, i) => {
                if (codeInputs[i]) {
                    codeInputs[i].value = digit;
                }
            });
            checkCodeComplete();
        };

        const checkCodeComplete = () => {
            const code = Array.from(codeInputs).map(i => i.value).join('');
            verifyBtn.disabled = code.length !== 6;
        };

        const getCode = () => {
            return Array.from(codeInputs).map(i => i.value).join('');
        };

        // Verify
        const verifyCode = async () => {
            waitingForAutoOTP = false;
            const code = getCode();

            if (code.length !== 6) return;

            verifyBtn.disabled = true;
            verifyBtn.innerHTML = '<span class="spinner"></span> מאמת...';

            const result = await this.otp.verifyOTP(currentPhone, code, purpose);

            if (result.success) {
                // הצלחה
                overlay.querySelector('#otp-step-code').style.display = 'none';
                overlay.querySelector('#otp-step-success').style.display = 'block';

                if (countdownInterval) clearInterval(countdownInterval);

                setTimeout(() => {
                    closeModal();
                    if (onSuccess) onSuccess(result);
                }, 1500);
            } else {
                showStatus(result.error || 'קוד שגוי', 'error');
                verifyBtn.disabled = false;
                verifyBtn.innerHTML = 'אמת';

                // נקה inputs
                codeInputs.forEach(i => i.value = '');
                codeInputs[0].focus();

                if (result.locked) {
                    showStatus('יותר מדי ניסיונות. בקש קוד חדש.', 'error');
                }

                if (onError) onError(result.error);
            }
        };

        verifyBtn.addEventListener('click', verifyCode);

        // Resend
        resendBtn.addEventListener('click', async () => {
            resendBtn.disabled = true;
            const result = await this.otp.resendOTP(currentPhone, purpose);

            if (result.success) {
                showStatus('קוד נשלח שוב', 'success');
                startCountdown(result.expires_in || 300);
                overlay.querySelector('#otp-resend-section').style.display = 'none';
            } else {
                showStatus(result.error || 'שגיאה', 'error');
            }

            resendBtn.disabled = false;
        });

        // Change phone
        changePhoneBtn.addEventListener('click', () => {
            this.otp.cancelWait();
            if (countdownInterval) clearInterval(countdownInterval);
            overlay.querySelector('#otp-step-code').style.display = 'none';
            overlay.querySelector('#otp-step-phone').style.display = 'block';
            sendBtn.disabled = false;
            sendBtn.innerHTML = 'שלח קוד';
            codeInputs.forEach(i => i.value = '');
        });

        // Countdown
        const startCountdown = (seconds) => {
            if (countdownInterval) clearInterval(countdownInterval);

            let remaining = seconds;
            const timerEl = overlay.querySelector('#otp-countdown');
            const resendSection = overlay.querySelector('#otp-resend-section');

            const updateTimer = () => {
                const mins = Math.floor(remaining / 60);
                const secs = remaining % 60;
                timerEl.textContent = `${mins.toString().padStart(2, '0')}:${secs.toString().padStart(2, '0')}`;

                if (remaining <= 0) {
                    clearInterval(countdownInterval);
                    resendSection.style.display = 'block';
                    timerEl.textContent = 'הקוד פג תוקף';
                }

                remaining--;
            };

            updateTimer();
            countdownInterval = setInterval(updateTimer, 1000);
        };

        // Status
        const showStatus = (message, type) => {
            statusEl.textContent = message;
            statusEl.className = 'otp-status ' + type;
            statusEl.style.display = 'block';

            if (type !== 'info') {
                setTimeout(() => {
                    statusEl.style.display = 'none';
                }, 3000);
            }
        };

        // Animation
        requestAnimationFrame(() => overlay.classList.add('active'));

        return overlay;
    }

    /**
     * יצירת input להזנת קוד OTP (לשילוב בטפסים קיימים)
     *
     * @param {HTMLElement} container
     * @param {Object} options
     */
    createOTPInput(container, options = {}) {
        const {
            onComplete,
            autoSubmit = false
        } = options;

        container.innerHTML = `
            <div class="otp-inline-input">
                <input type="text" maxlength="1" class="otp-digit-inline" data-index="0" inputmode="numeric">
                <input type="text" maxlength="1" class="otp-digit-inline" data-index="1" inputmode="numeric">
                <input type="text" maxlength="1" class="otp-digit-inline" data-index="2" inputmode="numeric">
                <span class="otp-separator">-</span>
                <input type="text" maxlength="1" class="otp-digit-inline" data-index="3" inputmode="numeric">
                <input type="text" maxlength="1" class="otp-digit-inline" data-index="4" inputmode="numeric">
                <input type="text" maxlength="1" class="otp-digit-inline" data-index="5" inputmode="numeric">
            </div>
        `;

        this.injectStyles();

        const inputs = container.querySelectorAll('.otp-digit-inline');

        inputs.forEach((input, index) => {
            input.addEventListener('input', (e) => {
                const value = e.target.value.replace(/[^0-9]/g, '');
                e.target.value = value;

                if (value && index < 5) {
                    inputs[index + 1].focus();
                }

                // בדוק אם מלא
                const code = Array.from(inputs).map(i => i.value).join('');
                if (code.length === 6) {
                    if (onComplete) onComplete(code);
                }
            });

            input.addEventListener('keydown', (e) => {
                if (e.key === 'Backspace' && !e.target.value && index > 0) {
                    inputs[index - 1].focus();
                }
            });

            input.addEventListener('paste', (e) => {
                e.preventDefault();
                const pastedData = e.clipboardData.getData('text').replace(/[^0-9]/g, '');
                const digits = pastedData.substring(0, 6).split('');
                digits.forEach((digit, i) => {
                    if (inputs[i]) inputs[i].value = digit;
                });

                const code = Array.from(inputs).map(i => i.value).join('');
                if (code.length === 6 && onComplete) {
                    onComplete(code);
                }
            });
        });

        // WebOTP auto-fill
        if (this.otp.isSupported) {
            this.otp.waitForOTP().then(code => {
                if (code) {
                    const digits = code.split('');
                    digits.forEach((digit, i) => {
                        if (inputs[i]) inputs[i].value = digit;
                    });
                    if (onComplete) onComplete(code);
                }
            });
        }

        return {
            getCode: () => Array.from(inputs).map(i => i.value).join(''),
            clear: () => inputs.forEach(i => i.value = ''),
            focus: () => inputs[0].focus()
        };
    }

    /**
     * הזרקת סגנונות
     */
    injectStyles() {
        if (document.getElementById('otp-ui-styles')) return;

        const styles = document.createElement('style');
        styles.id = 'otp-ui-styles';
        styles.textContent = `
            .otp-modal-overlay {
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background: rgba(0, 0, 0, 0.5);
                display: flex;
                align-items: center;
                justify-content: center;
                z-index: 10000;
                opacity: 0;
                transition: opacity 0.3s ease;
            }

            .otp-modal-overlay.active {
                opacity: 1;
            }

            .otp-modal-overlay.closing {
                opacity: 0;
            }

            .otp-modal {
                background: white;
                border-radius: 20px;
                padding: 30px;
                max-width: 380px;
                width: 90%;
                text-align: center;
                position: relative;
                box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            }

            .otp-close-btn {
                position: absolute;
                top: 15px;
                left: 15px;
                background: none;
                border: none;
                font-size: 24px;
                color: #999;
                cursor: pointer;
            }

            .otp-modal-header {
                margin-bottom: 20px;
            }

            .otp-icon {
                font-size: 48px;
                display: block;
                margin-bottom: 10px;
            }

            .otp-modal-header h2 {
                margin: 0;
                color: #333;
            }

            .otp-step p {
                color: #666;
                margin-bottom: 20px;
            }

            .otp-phone-input input {
                width: 100%;
                padding: 15px;
                font-size: 20px;
                text-align: center;
                border: 2px solid #e5e7eb;
                border-radius: 12px;
                outline: none;
                transition: border-color 0.3s;
            }

            .otp-phone-input input:focus {
                border-color: #667eea;
            }

            .otp-code-inputs {
                display: flex;
                justify-content: center;
                gap: 8px;
                margin-bottom: 20px;
            }

            .otp-digit {
                width: 45px;
                height: 55px;
                font-size: 24px;
                text-align: center;
                border: 2px solid #e5e7eb;
                border-radius: 10px;
                outline: none;
                transition: border-color 0.3s;
            }

            .otp-digit:focus {
                border-color: #667eea;
            }

            .otp-timer {
                color: #666;
                margin-bottom: 15px;
            }

            .otp-resend {
                margin-bottom: 20px;
                color: #666;
            }

            .otp-btn-primary {
                width: 100%;
                padding: 15px;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                color: white;
                border: none;
                border-radius: 12px;
                font-size: 16px;
                font-weight: 600;
                cursor: pointer;
                transition: all 0.3s;
                margin-top: 10px;
            }

            .otp-btn-primary:hover:not(:disabled) {
                transform: translateY(-2px);
                box-shadow: 0 8px 20px rgba(102, 126, 234, 0.4);
            }

            .otp-btn-primary:disabled {
                opacity: 0.6;
                cursor: not-allowed;
            }

            .otp-link-btn {
                background: none;
                border: none;
                color: #667eea;
                cursor: pointer;
                font-weight: 500;
                padding: 10px;
            }

            .otp-link-btn:hover {
                text-decoration: underline;
            }

            .otp-success-icon {
                font-size: 60px;
                margin-bottom: 15px;
            }

            .otp-status {
                margin-top: 15px;
                padding: 10px;
                border-radius: 8px;
                font-size: 14px;
                display: none;
            }

            .otp-status.error {
                background: #fee2e2;
                color: #991b1b;
            }

            .otp-status.success {
                background: #d1fae5;
                color: #065f46;
            }

            .otp-status.info {
                background: #e0e7ff;
                color: #3730a3;
            }

            /* Inline input */
            .otp-inline-input {
                display: flex;
                justify-content: center;
                align-items: center;
                gap: 6px;
            }

            .otp-digit-inline {
                width: 40px;
                height: 48px;
                font-size: 20px;
                text-align: center;
                border: 2px solid #e5e7eb;
                border-radius: 8px;
                outline: none;
            }

            .otp-digit-inline:focus {
                border-color: #667eea;
            }

            .otp-separator {
                font-size: 20px;
                color: #999;
            }

            .spinner {
                display: inline-block;
                width: 16px;
                height: 16px;
                border: 2px solid rgba(255,255,255,0.3);
                border-top-color: white;
                border-radius: 50%;
                animation: spin 1s linear infinite;
                vertical-align: middle;
                margin-left: 8px;
            }

            @keyframes spin {
                to { transform: rotate(360deg); }
            }
        `;

        document.head.appendChild(styles);
    }
}

// יצירת instance גלובלי
window.otpUI = new OTPUI();

// Export
if (typeof module !== 'undefined' && module.exports) {
    module.exports = OTPUI;
}
